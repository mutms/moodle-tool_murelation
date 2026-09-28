<?php
// This file is part of MuTMS suite of plugins for Moodle™ LMS.
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with this program.  If not, see <https://www.gnu.org/licenses/>.

// phpcs:disable moodle.Files.BoilerplateComment.CommentEndedTooSoon

namespace tool_murelation\muform\util\autocomplete;

use stdClass;
use tool_mulib\local\sql;
use tool_mulib\muform\util\autocomplete\user_trait;

/**
 * Candidate supervisors and subordinates of a relation framework.
 *
 * Only answers who may take a role in the framework, access control stays in the source
 * constructors: the supervisors UI is about individual relations (like a parent), the teams UI
 * about a supervisor with a group (like a teacher), permissions differ, the relations do not.
 *
 * @package     tool_murelation
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait framework_users_trait {
    use user_trait;

    /**
     * Condition on user alias "u" for candidates of a role.
     *
     * @param stdClass $framework
     * @param string $role 'supervisor' or 'subordinate'
     * @param array $options notsubordinate (bool) skip users already subordinates in the framework,
     *      notuserid (int) skip one user, activeonly (bool) skip suspended users,
     *      currentuserid (int) user that is always a candidate, whatever changed since
     * @return sql
     */
    protected function get_framework_where(stdClass $framework, string $role, array $options = []): sql {
        $conditions = [];
        $cohortid = ($role === 'supervisor') ? $framework->supervisorcohortid : $framework->subordinatecohortid;
        if ($cohortid) {
            $conditions[] = new sql(
                "EXISTS (SELECT 'x' FROM {cohort_members} fcm WHERE fcm.userid = u.id AND fcm.cohortid = ?)",
                [$cohortid]
            );
        }
        if (!empty($options['notsubordinate'])) {
            $conditions[] = new sql(
                "NOT EXISTS (SELECT 'x' FROM {tool_murelation_subordinate} fsub WHERE fsub.userid = u.id AND fsub.frameworkid = ?)",
                [$framework->id]
            );
        }
        if (!empty($options['notuserid'])) {
            $conditions[] = new sql("u.id <> ?", [$options['notuserid']]);
        }
        if (!empty($options['activeonly'])) {
            $conditions[] = new sql("u.suspended = 0");
        }
        $where = $conditions ? sql::join(' AND ', $conditions) : new sql('1 = 1');
        if (!empty($options['currentuserid'])) {
            $where = sql::join(' OR ', [$where->wrap('(', ')'), new sql('u.id = ?', [$options['currentuserid']])]);
        }
        return $where->wrap('(', ')');
    }

    /**
     * Label of the current user of the edited item, tenant rules do not apply to existing values.
     *
     * @param \context $context
     * @param string $value
     * @param int|null $currentuserid
     * @return string|null label html, null when the value is not the current user
     */
    protected function get_current_user_label(\context $context, string $value, ?int $currentuserid): ?string {
        global $DB;
        if (!$currentuserid || $value !== (string)$currentuserid) {
            return null;
        }
        $user = $DB->get_record('user', ['id' => $currentuserid, 'deleted' => 0]);
        if (!$user) {
            return null;
        }
        return \tool_mulib\local\search_util::format_user_label($user, $context);
    }
}
