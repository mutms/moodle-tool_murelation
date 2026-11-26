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

namespace tool_murelation\mureport\system_report;

use tool_mureport\mureport\action;
use tool_mureport\mureport\entity\user;
use tool_mureport\mureport\join;
use tool_mureport\mureport\row;
use tool_mureport\mureport\system_report;
use tool_murelation\local\framework as framework_util;
use tool_murelation\mureport\entity\framework;
use tool_murelation\mureport\entity\subordinate;
use tool_murelation\mureport\entity\supervisor;
use tool_mulib\local\sql;
use core\exception\invalid_parameter_exception;
use core\lang_string;
use core\url;
use stdClass;

/**
 * Subordinates of a user in frameworks in Supervisors mode.
 *
 * @package     tool_murelation
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class user_subordinates extends system_report {
    /**
     * Make report instance.
     *
     * @param int $userid supervisor
     * @return self
     */
    public static function make_instance(int $userid): self {
        return self::from_constraints(['userid' => $userid]);
    }

    #[\Override]
    public static function get_title(): string {
        return get_string('usersubordinates', 'tool_murelation');
    }

    #[\Override]
    public function get_context(): \core\context {
        $user = $this->get_constraint_record('user', 'userid');
        if (!$user || $user->deleted || !$user->confirmed || isguestuser($user)) {
            throw new invalid_parameter_exception('Invalid user');
        }
        return \core\context\user::instance($user->id);
    }

    #[\Override]
    protected function check_access(): void {
        global $USER;
        if ($this->get_context()->instanceid == $USER->id) {
            return;
        }
        $user = $this->get_constraint_record('user', 'userid');
        if (\tool_mulib\local\mulib::is_mutenancy_active() && $user->tenantid) {
            require_capability('tool/murelation:viewpositions', \context_tenant::instance($user->tenantid));
        } else {
            require_capability('tool/murelation:viewpositions', \core\context\system::instance());
        }
    }

    #[\Override]
    protected function define(): void {
        $this->set_main_entity(new subordinate('subordinate'));
        $this->add_entity(new user('auser'), new join('subordinate', 'auser.id = subordinate.userid', inner: true));
        $this->add_entity(
            new supervisor('supervisor'),
            new join('subordinate', 'supervisor.id = subordinate.supervisorid', inner: true)
        );
        $this->add_entity(new framework('framework'), new join('supervisor', 'framework.id = supervisor.frameworkid', inner: true));
        $this->add_restriction(
            new sql('framework.uimode = :subuimode', ['subuimode' => framework_util::UIMODE_SUPERVISORS]),
            ['framework']
        );

        $this->set_default_columns([
            'auser:fullname',
            'framework:name',
            'framework:subordinatetitle',
            'subordinate:actions',
        ]);
        $this->set_default_filters([
            'auser:fullname' => ['multisearch' => true],
            'framework:name' => ['multisearch' => true],
            'framework:idnumber',
        ]);
        $this->remove_columns(['supervisor:actions']);
        $this->set_multisearch_label(new lang_string('searchusers', 'tool_mureport'));
        $this->set_export_allowed(false);
        $this->set_default_sorting(['auser:fullname' => 'asc']);
    }

    #[\Override]
    protected function apply_constraints(): void {
        $this->add_constraint_restriction('userid', 'supervisor', 'userid');
    }

    #[\Override]
    protected function define_columns(string $selector): array {
        $columns = parent::define_columns($selector);
        if ($selector === 'subordinate:actions') {
            $possible = function (stdClass $fields): bool {
                global $DB, $USER;
                if (!has_capability('tool/murelation:managepositions', \core\context\user::instance($fields->userid))) {
                    return false;
                }
                $framework = $this->get_cached_record('tool_murelation_framework', (int)$fields->frameworkid);
                if (is_siteadmin() || !$framework->managecohortid) {
                    return true;
                }
                return $DB->record_exists('cohort_members', ['userid' => $USER->id, 'cohortid' => $framework->managecohortid]);
            };
            $url = fn(string $script): \Closure => fn(stdClass $fields): url
                => new url("/admin/tool/murelation/management/$script.php", [
                    'subuserid' => $fields->userid,
                    'frameworkid' => $fields->frameworkid,
                ]);
            $column = $columns['subordinate:actions'];
            $column->add_action(new action('update', new lang_string('supervisor_update', 'tool_murelation'), 'i/settings'))
                ->set_available(fn(stdClass $fields, row $row): bool => $possible($fields))
                ->set_url($url('supervisor_edit'))
                ->set_dialog('lg');
            $column->add_action(new action('delete', new lang_string('supervisor_delete', 'tool_murelation'), 'i/delete'))
                ->set_available(fn(stdClass $fields, row $row): bool => $possible($fields))
                ->set_url($url('supervisor_delete'))
                ->set_dialog('sm')
                ->set_destructive();
        }
        return $columns;
    }
}
