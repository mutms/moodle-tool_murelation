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

namespace tool_murelation\mureport\entity;

use tool_mureport\mureport\cell;
use tool_mureport\mureport\column;
use tool_mureport\mureport\entity;
use tool_mureport\mureport\export_value;
use tool_mureport\mureport\filter;
use tool_murelation\local\framework as framework_util;
use tool_mulib\local\sql;
use core\lang_string;
use core\url;
use stdClass;

/**
 * User relation framework entity, frameworks in tool_murelation_framework.
 *
 * @package     tool_murelation
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class framework extends entity {
    #[\Override]
    public function get_default_source(): sql {
        return new sql('{tool_murelation_framework}');
    }

    #[\Override]
    protected function get_default_title(): string {
        return get_string('framework', 'tool_murelation');
    }

    #[\Override]
    public function get_column_names(): array {
        return ['name', 'idnumber', 'uimode', 'visibility', 'supervisortitle', 'subordinatetitle', 'supervisorrole', 'timecreated'];
    }

    #[\Override]
    protected function define_columns(string $selector): array {
        $alias = $this->alias;
        $column = match ($selector) {
            'name' => $this->define_name_column(),
            'idnumber' => $this->create_column(
                'idnumber',
                new lang_string('framework_idnumber', 'tool_murelation'),
                column::TYPE_TEXT
            )
                ->add_field('idnumber', "$alias.idnumber")
                ->set_sortable(true),
            'uimode' => $this->create_column(
                'uimode',
                new lang_string('framework_uimode', 'tool_murelation'),
                column::TYPE_ENUMERATION
            )
                ->add_field('uimode', "$alias.uimode")
                ->set_options(fn(): array => self::get_uimode_options())
                ->set_sortable(true),
            'visibility' => $this->create_column(
                'visibility',
                new lang_string('framework_visibility', 'tool_murelation'),
                column::TYPE_ENUMERATION
            )
                ->add_field('visibility', "$alias.visibility")
                ->set_options(fn(): array => framework_util::get_visibility_options())
                ->set_sortable(true),
            'supervisortitle', 'subordinatetitle' => $this->define_title_column($selector),
            'supervisorrole' => $this->define_supervisorrole_column(),
            'timecreated' => $this->create_column(
                'timecreated',
                new lang_string('timecreated', 'core_reportbuilder'),
                column::TYPE_TIMESTAMP
            )
                ->add_field('timecreated', "$alias.timecreated")
                ->set_sortable(true),
            default => null,
        };
        return $column ? [$column] : [];
    }

    #[\Override]
    public function get_filter_names(): array {
        return ['name', 'idnumber', 'uimode', 'visibility'];
    }

    #[\Override]
    protected function define_filters(string $selector): array {
        $alias = $this->alias;
        $filter = match ($selector) {
            'name' => new filter\text($alias, 'name', new lang_string('framework_name', 'tool_murelation'), "$alias.name"),
            'idnumber' => new filter\text(
                $alias,
                'idnumber',
                new lang_string('framework_idnumber', 'tool_murelation'),
                "$alias.idnumber"
            ),
            'uimode' => (new filter\checkboxes(
                $alias,
                'uimode',
                new lang_string('framework_uimode', 'tool_murelation'),
                "$alias.uimode"
            ))
                ->set_options(fn(): array => self::get_uimode_options()),
            'visibility' => (new filter\checkboxes(
                $alias,
                'visibility',
                new lang_string('framework_visibility', 'tool_murelation'),
                "$alias.visibility"
            ))
                ->set_options(fn(): array => framework_util::get_visibility_options()),
            default => null,
        };
        return $filter ? [$filter] : [];
    }

    /**
     * Returns names of framework modes.
     *
     * @return array mode => name
     */
    public static function get_uimode_options(): array {
        return [
            framework_util::UIMODE_SUPERVISORS => get_string('framework_uimode_supervisors', 'tool_murelation'),
            framework_util::UIMODE_TEAMS => get_string('framework_uimode_teams', 'tool_murelation'),
        ];
    }

    /**
     * Define framework name column, linked to framework management for framework viewers.
     *
     * @return column
     */
    private function define_name_column(): column {
        $alias = $this->alias;
        $syscontext = \core\context\system::instance();
        $name = fn(stdClass $fields, bool $escape): string
            => format_string($fields->name, true, ['context' => $syscontext, 'escape' => $escape]);
        $url = fn(stdClass $fields): ?url => has_capability('tool/murelation:viewframeworks', $syscontext)
            ? new url('/admin/tool/murelation/management/framework.php', ['id' => $fields->id]) : null;
        return $this->create_column('name', new lang_string('framework_name', 'tool_murelation'), column::TYPE_TEXT)
            ->add_field('name', "$alias.name")
            ->add_field('id', "$alias.id")
            ->set_sortable(true)
            ->add_formatter(
                'name',
                fn(cell $cell): string => $name($cell->get_fields(), true),
                fn(cell $cell): export_value => export_value::text($name($cell->get_fields(), false))
            )
            ->add_formatter(
                'link',
                function (cell $cell) use ($name, $url): string {
                    $fields = $cell->get_fields();
                    $link = $url($fields);
                    return $link ? \html_writer::link($link, $name($fields, true)) : $name($fields, true);
                },
                fn(cell $cell): export_value => export_value::text($name($cell->get_fields(), false))
            )
            ->set_default_formatter('link');
    }

    /**
     * Define supervisor or subordinate title column.
     *
     * @param string $name
     * @return column
     */
    private function define_title_column(string $name): column {
        $alias = $this->alias;
        $syscontext = \core\context\system::instance();
        $title = fn(cell $cell, bool $escape): string
            => format_string((string)$cell->get_value(), true, ['context' => $syscontext, 'escape' => $escape]);
        return $this->create_column($name, new lang_string($name, 'tool_murelation'), column::TYPE_TEXT)
            ->add_field($name, "$alias.$name")
            ->set_sortable(true)
            ->add_formatter(
                'title',
                fn(cell $cell): string => $title($cell, true),
                fn(cell $cell): export_value => export_value::text($title($cell, false))
            )
            ->set_default_formatter('title');
    }

    /**
     * Define supervisor role column.
     *
     * @return column
     */
    private function define_supervisorrole_column(): column {
        $alias = $this->alias;
        $rolename = function (cell $cell): string {
            $roleid = (int)$cell->get_value();
            if (!$roleid) {
                return '';
            }
            $role = $cell->get_row()->get_cached_record('role', $roleid);
            return role_get_name($role, null, ROLENAME_ORIGINAL);
        };
        return $this->create_column(
            'supervisorrole',
            new lang_string('framework_supervisorrole', 'tool_murelation'),
            column::TYPE_INTEGER
        )
            ->add_field('supervisorroleid', "(SELECT r.id FROM {role} r WHERE r.id = $alias.supervisorroleid)")
            ->add_formatter(
                'name',
                fn(cell $cell): string => s($rolename($cell)),
                fn(cell $cell): export_value => export_value::text($rolename($cell))
            )
            ->set_default_formatter('name');
    }
}
