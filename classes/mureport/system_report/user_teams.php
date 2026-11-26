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

use tool_mureport\mureport\join;
use tool_mureport\mureport\system_report;
use tool_murelation\local\framework as framework_util;
use tool_murelation\mureport\entity\framework;
use tool_murelation\mureport\entity\supervisor;
use tool_mulib\local\sql;
use core\exception\invalid_parameter_exception;
use core\lang_string;

/**
 * Teams supervised by a user in visible frameworks in Teams mode.
 *
 * @package     tool_murelation
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class user_teams extends system_report {
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
        return get_string('userteams', 'tool_murelation');
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
        $this->set_main_entity(new supervisor('supervisor'));
        $this->add_entity(new framework('framework'), new join('supervisor', 'framework.id = supervisor.frameworkid', inner: true));
        $this->add_restriction(new sql(
            'framework.uimode = :teamuimode AND framework.visibility <> :teamhidden',
            ['teamuimode' => framework_util::UIMODE_TEAMS, 'teamhidden' => framework_util::VISIBILITY_HIDDEN]
        ), ['framework']);

        $this->set_default_columns([
            'framework:name',
            'supervisor:teamname',
            'supervisor:teamidnumber',
            'framework:supervisortitle',
            'supervisor:supmanaged',
            'supervisor:subordinates',
        ]);
        $this->set_default_filters([
            'framework:name' => ['multisearch' => true],
            'supervisor:teamname' => ['multisearch' => true],
            'supervisor:teamidnumber' => ['multisearch' => true],
        ]);
        $this->remove_columns(['supervisor:actions', 'supervisor:vacant']);
        $this->remove_filters(['supervisor:vacant']);
        $this->set_multisearch_label(new lang_string('teamsearch', 'tool_murelation'));
        $this->set_export_allowed(false);
        $this->set_default_sorting(['supervisor:teamname' => 'asc']);
    }

    #[\Override]
    protected function apply_constraints(): void {
        $this->add_constraint_restriction('userid', 'supervisor', 'userid');
    }
}
