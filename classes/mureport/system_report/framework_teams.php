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
use tool_murelation\local\uimode_teams;
use tool_murelation\mureport\entity\supervisor;
use core\exception\invalid_parameter_exception;
use core\lang_string;
use core\url;
use stdClass;

/**
 * Teams of a framework in Teams mode, for framework managers.
 *
 * @package     tool_murelation
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class framework_teams extends system_report {
    /**
     * Make report instance.
     *
     * @param int $frameworkid
     * @return self
     */
    public static function make_instance(int $frameworkid): self {
        return self::from_constraints(['frameworkid' => $frameworkid]);
    }

    #[\Override]
    public static function get_title(): string {
        return get_string('teams', 'tool_murelation');
    }

    /**
     * Returns framework of the report.
     *
     * @return stdClass
     */
    public function get_framework(): stdClass {
        $framework = $this->get_constraint_record('tool_murelation_framework', 'frameworkid');
        if (!$framework || $framework->uimode != framework_util::UIMODE_TEAMS) {
            throw new invalid_parameter_exception('Invalid framework');
        }
        return $framework;
    }

    #[\Override]
    protected function check_access(): void {
        $this->get_framework();
        $this->require_capability('tool/murelation:viewframeworks');
    }

    #[\Override]
    protected function define(): void {
        $this->set_main_entity(new supervisor('supervisor'));
        $this->add_entity(new user('auser'), new join('supervisor', 'auser.id = supervisor.userid'));

        $this->set_default_columns([
            'supervisor:teamname',
            'supervisor:teamidnumber',
            'auser:fullname',
            'supervisor:supmanaged',
            'supervisor:tenant',
            'supervisor:subordinates',
            'supervisor:actions',
        ]);
        $this->set_default_filters([
            'supervisor:teamname' => ['multisearch' => true],
            'supervisor:teamidnumber' => ['multisearch' => true],
            'auser:fullname' => ['multisearch' => true],
            'supervisor:vacant',
            'supervisor:supmanaged',
            'supervisor:tenant',
        ]);
        $this->set_multisearch_label(new lang_string('teamsearch', 'tool_murelation'));
        $this->set_default_sorting(['supervisor:teamname' => 'asc']);
    }

    #[\Override]
    protected function apply_constraints(): void {
        $this->add_constraint_restriction('frameworkid', 'supervisor', 'frameworkid');
    }

    #[\Override]
    protected function define_columns(string $selector): array {
        $columns = parent::define_columns($selector);
        if ($this->is_inspectmode()) {
            return $columns;
        }
        $framework = $this->get_framework();
        if ($selector === 'auser:fullname') {
            $columns['auser:fullname']->set_custom_title($framework->supervisortitle);
        } else if ($selector === 'supervisor:subordinates') {
            $columns['supervisor:subordinates']->set_custom_title($framework->subordinatestitle);
        } else if ($selector === 'supervisor:actions') {
            $possible = function (stdClass $fields) use ($framework): bool {
                $supervisor = $this->get_cached_record('tool_murelation_supervisor', (int)$fields->id);
                return uimode_teams::can_update_team($framework, $supervisor);
            };
            $url = fn(string $script): \Closure => fn(stdClass $fields): url
                => new url("/admin/tool/murelation/management/$script.php", ['id' => $fields->id]);
            $column = $columns['supervisor:actions'];
            $column->add_action(new action('update', new lang_string('team_update', 'tool_murelation'), 'i/settings'))
                ->set_available(fn(stdClass $fields, row $row): bool => $possible($fields))
                ->set_url($url('team_update'))
                ->set_dialog('lg');
            $column->add_action(new action('delete', new lang_string('team_delete', 'tool_murelation'), 'i/delete'))
                ->set_available(fn(stdClass $fields, row $row): bool => $possible($fields))
                ->set_url($url('team_delete'))
                ->set_dialog('sm')
                ->set_destructive();
        }
        return $columns;
    }
}
