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
// phpcs:disable moodle.Files.LineLength.TooLong

namespace tool_murelation\muform\cohortpicker;

use core\context;
use stdClass;
use tool_mulib\local\mulib;
use tool_mulib\local\sql;
use tool_murelation\local\framework;
use tool_murelation\local\uimode_teams;

/**
 * Cohort whose members are added to a team, teams UI.
 *
 * Teams without tenant accept global cohorts only, tenant teams also cohorts of their tenant.
 *
 * @package     tool_murelation
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class members_add_cohort_cohortid extends \tool_mulib\muform\cohortpicker\base {
    /** @var stdClass framework record */
    private stdClass $framework;
    /** @var stdClass team (supervisor) record */
    private stdClass $supervisor;
    /** @var context team context */
    private context $context;

    /**
     * Constructor.
     *
     * @param int $supervisorid team (supervisor record) id
     */
    public function __construct(
        /** @var int team (supervisor record) id */
        private readonly int $supervisorid
    ) {
        global $DB;
        $this->supervisor = $DB->get_record('tool_murelation_supervisor', ['id' => $supervisorid], '*', MUST_EXIST);
        $this->framework = $DB->get_record('tool_murelation_framework', ['id' => $this->supervisor->frameworkid], '*', MUST_EXIST);
        $this->context = uimode_teams::get_team_context($this->framework, $this->supervisor);
        if (!uimode_teams::can_manage_members($this->framework, $this->supervisor)) {
            throw new \core\exception\invalid_parameter_exception('Cannot manage team members');
        }
    }

    #[\Override]
    public function get_args(): array {
        return [$this->supervisorid];
    }

    #[\Override]
    protected function get_context(): context {
        return $this->context;
    }

    #[\Override]
    protected function get_restriction_sql(): ?sql {
        if (!mulib::is_mutenancy_active()) {
            return null;
        }
        if ($this->supervisor->tenantid) {
            return new sql("ctx.tenantid IS NULL OR ctx.tenantid = ?", [$this->supervisor->tenantid]);
        }
        return new sql("ctx.tenantid IS NULL");
    }

    #[\Override]
    protected function validate_value(string $value): ?string {
        global $DB;
        $candidates = self::get_candidates($this->supervisor->id, (int)$value);
        if (!$candidates) {
            return get_string('error_nosubordinates', 'tool_murelation');
        }
        if ($this->supervisor->maxsubordinates) {
            $current = $DB->count_records('tool_murelation_subordinate', ['supervisorid' => $this->supervisor->id]);
            if ($current + count($candidates) > $this->supervisor->maxsubordinates) {
                return get_string('error_maxsubordinates', 'tool_murelation');
            }
        }
        return null;
    }

    /**
     * Returns candidates for new team members.
     *
     * @param int $supervisorid
     * @param int $cohortid
     * @return array user ids
     */
    public static function get_candidates(int $supervisorid, int $cohortid): array {
        global $DB;

        $supervisor = $DB->get_record('tool_murelation_supervisor', ['id' => $supervisorid], '*', MUST_EXIST);
        $framework = $DB->get_record('tool_murelation_framework', ['id' => $supervisor->frameworkid, 'uimode' => framework::UIMODE_TEAMS], '*', MUST_EXIST);
        $context = uimode_teams::get_team_context($framework, $supervisor);

        $sql = (new sql(
            "SELECT u.id
               FROM {user} u
               JOIN {cohort_members} cm ON cm.userid = u.id AND cm.cohortid = :cohortid
               /* cohortjoin */
          LEFT JOIN {tool_murelation_subordinate} sub ON sub.userid = u.id AND sub.supervisorid = :supervisorid
              WHERE u.deleted = 0 AND u.confirmed = 1 AND sub.id IS NULL
                    /* tenantwhere */
           ORDER BY u.id ASC",
            ['supervisorid' => $supervisor->id, 'cohortid' => $cohortid]
        ));

        if (mulib::is_mutenancy_active()) {
            $tenantwhere = \tool_mutenancy\local\tenancy::get_related_users_exists('u.id', $context, 'AND');
            $sql = $sql->replace_comment('tenantwhere', $tenantwhere);
        } else {
            $sql = $sql->replace_comment('tenantwhere', "");
        }

        if ($framework->subordinatecohortid) {
            $sql = $sql->replace_comment(
                'cohortjoin',
                new sql("JOIN {cohort_members} scm ON scm.userid = u.id AND scm.cohortid = ?", [$framework->subordinatecohortid])
            );
        } else {
            $sql = $sql->replace_comment('cohortjoin', "");
        }

        $sql->ensure_no_comments();

        return $DB->get_fieldset_sql($sql->sql, $sql->params);
    }
}
