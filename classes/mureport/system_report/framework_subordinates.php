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
use tool_mureport\mureport\column;
use tool_mureport\mureport\entity\user;
use tool_mureport\mureport\join;
use tool_mureport\mureport\row;
use tool_mureport\mureport\system_report;
use tool_murelation\local\framework as framework_util;
use tool_murelation\mureport\entity\subordinate;
use tool_murelation\mureport\entity\supervisor;
use core\exception\invalid_parameter_exception;
use core\lang_string;
use core\url;
use stdClass;

/**
 * Subordinates and their supervisors in a framework in Supervisors mode, for framework managers.
 *
 * @package     tool_murelation
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class framework_subordinates extends system_report {
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
        return get_string('subordinates', 'tool_murelation');
    }

    /**
     * Returns framework of the report.
     *
     * @return stdClass
     */
    public function get_framework(): stdClass {
        $framework = $this->get_constraint_record('tool_murelation_framework', 'frameworkid');
        if (!$framework || $framework->uimode != framework_util::UIMODE_SUPERVISORS) {
            throw new invalid_parameter_exception('Invalid framework');
        }
        return $framework;
    }

    #[\Override]
    protected function check_access(): void {
        $this->get_framework();
        $this->require_capability('tool/murelation:viewframeworks');
        $this->require_capability('tool/murelation:viewpositions');
    }

    #[\Override]
    protected function define(): void {
        $this->set_main_entity(new subordinate('subordinate'));
        $this->add_entity(new user('auser'), new join('subordinate', 'auser.id = subordinate.userid', inner: true));
        $this->add_entity(
            new supervisor('supervisor'),
            new join('subordinate', 'supervisor.id = subordinate.supervisorid', inner: true)
        );
        $this->add_entity(new user('supuser'), new join('supervisor', 'supuser.id = supervisor.userid'));

        $this->set_default_columns([
            'auser:fullname',
            'auser:identityfields',
            'supuser:fullname',
            'subordinate:actions',
        ]);
        $this->set_default_filters([
            'auser:fullname' => ['multisearch' => true],
            'auser:email' => ['multisearch' => true],
            'supuser:fullname' => ['multisearch' => true],
        ]);
        $this->set_multisearch_label(new lang_string('searchusers', 'tool_mureport'));
        $this->set_default_sorting(['auser:fullname' => 'asc']);
    }

    #[\Override]
    protected function apply_constraints(): void {
        $this->add_constraint_restriction('frameworkid', 'subordinate', 'frameworkid');
    }

    #[\Override]
    public function get_column_names(): array {
        return array_merge(parent::get_column_names(), ['auser:identityfields']);
    }

    /**
     * User identity fields from site settings.
     *
     * @return array id => column
     */
    private function define_identity_columns(): array {
        if ($this->is_inspectmode()) {
            $title = new lang_string('identityfields', 'tool_mureport');
            return [new column('auser', 'identityfields', $title, column::TYPE_PLACEHOLDER)];
        }
        $columns = [];
        foreach ($this->get_entity('auser')->get_identity_column_ids($this->get_context()) as $id) {
            $columns += parent::define_columns($id);
        }
        return $columns;
    }

    #[\Override]
    protected function define_columns(string $selector): array {
        if ($selector === 'auser:identityfields') {
            return $this->define_identity_columns();
        }
        $columns = parent::define_columns($selector);
        if ($this->is_inspectmode()) {
            return $columns;
        }
        $framework = $this->get_framework();
        if ($selector === 'supuser:fullname') {
            $columns['supuser:fullname']->set_custom_title($framework->supervisortitle);
        } else if ($selector === 'subordinate:actions') {
            $title = format_string($framework->supervisortitle);
            $possible = fn(stdClass $fields): bool
                => \tool_murelation\local\uimode_supervisors::can_manage_subordinate($framework, (int)$fields->userid);
            $url = fn(string $script): \Closure => fn(stdClass $fields): url
                => new url("/admin/tool/murelation/management/$script.php", [
                    'subuserid' => $fields->userid,
                    'frameworkid' => $framework->id,
                ]);
            $column = $columns['subordinate:actions'];
            $column->add_action(new action(
                'update',
                new lang_string('supervisor_update_a', 'tool_murelation', $title),
                'i/settings'
            ))
                ->set_available(fn(stdClass $fields, row $row): bool => $possible($fields))
                ->set_url($url('supervisor_edit'))
                ->set_dialog('lg');
            $column->add_action(new action('delete', new lang_string('supervisor_delete_a', 'tool_murelation', $title), 'i/delete'))
                ->set_available(fn(stdClass $fields, row $row): bool => $possible($fields))
                ->set_url($url('supervisor_delete'))
                ->set_dialog('sm')
                ->set_destructive();
        }
        return $columns;
    }
}
