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
use tool_mulib\local\sql;
use core\lang_string;

/**
 * Subordinate entity, relations of subordinates to supervisor positions in tool_murelation_subordinate.
 *
 * Reports add actions to the actions column, their titles depend on framework.
 *
 * @package     tool_murelation
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class subordinate extends entity {
    #[\Override]
    public function get_default_source(): sql {
        return new sql('{tool_murelation_subordinate}');
    }

    #[\Override]
    protected function get_default_title(): string {
        return get_string('subordinate', 'tool_murelation');
    }

    #[\Override]
    public function get_column_names(): array {
        return ['teamposition', 'timecreated', 'actions'];
    }

    #[\Override]
    protected function define_columns(string $selector): array {
        $alias = $this->alias;
        $syscontext = \core\context\system::instance();
        $position = fn(cell $cell, bool $escape): string => $cell->get_value() === null ? ''
            : format_string($cell->get_value(), true, ['context' => $syscontext, 'escape' => $escape]);
        $column = match ($selector) {
            'teamposition' => $this->create_column(
                'teamposition',
                new lang_string('team_position', 'tool_murelation'),
                column::TYPE_TEXT
            )
                ->add_field('teamposition', "$alias.teamposition")
                ->set_sortable(true)
                ->add_formatter(
                    'name',
                    fn(cell $cell): string => $position($cell, true),
                    fn(cell $cell): export_value => export_value::text($position($cell, false))
                )
                ->set_default_formatter('name'),
            'timecreated' => $this->create_column(
                'timecreated',
                new lang_string('timecreated', 'core_reportbuilder'),
                column::TYPE_TIMESTAMP
            )
                ->add_field('timecreated', "$alias.timecreated")
                ->set_sortable(true),
            'actions' => $this->create_actions_column('actions', new lang_string('actions'))
                ->add_field('id', "$alias.id")
                ->add_field('supervisorid', "$alias.supervisorid")
                ->add_field('frameworkid', "$alias.frameworkid")
                ->add_field('userid', "$alias.userid"),
            default => null,
        };
        return $column ? [$column] : [];
    }

    #[\Override]
    public function get_filter_names(): array {
        return ['teamposition'];
    }

    #[\Override]
    protected function define_filters(string $selector): array {
        $alias = $this->alias;
        $filter = match ($selector) {
            'teamposition' => new filter\text(
                $alias,
                'teamposition',
                new lang_string('team_position', 'tool_murelation'),
                "$alias.teamposition"
            ),
            default => null,
        };
        return $filter ? [$filter] : [];
    }
}
