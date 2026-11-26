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
use tool_murelation\local\uimode_teams;
use tool_mulib\local\mulib;
use tool_mulib\local\sql;
use core\lang_string;
use core\url;
use stdClass;

/**
 * Supervisor position entity, supervisors and teams in tool_murelation_supervisor, the user is vacant in empty positions.
 *
 * @package     tool_murelation
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class supervisor extends entity {
    #[\Override]
    public function get_default_source(): sql {
        return new sql('{tool_murelation_supervisor}');
    }

    #[\Override]
    protected function get_default_title(): string {
        return get_string('supervisor', 'tool_murelation');
    }

    #[\Override]
    public function get_column_names(): array {
        return ['teamname', 'teamidnumber', 'supmanaged', 'maxsubordinates', 'subordinates', 'vacant', 'tenant', 'actions'];
    }

    #[\Override]
    protected function define_columns(string $selector): array {
        $alias = $this->alias;
        $column = match ($selector) {
            'teamname' => $this->define_teamname_column(),
            'teamidnumber' => $this->create_column(
                'teamidnumber',
                new lang_string('team_idnumber', 'tool_murelation'),
                column::TYPE_TEXT
            )
                ->add_field('teamidnumber', "$alias.teamidnumber")
                ->set_sortable(true),
            'supmanaged' => $this->create_column(
                'supmanaged',
                new lang_string('team_supmanaged', 'tool_murelation'),
                column::TYPE_BOOLEAN
            )
                ->add_field('supmanaged', "$alias.supmanaged")
                ->set_sortable(true),
            'maxsubordinates' => $this->create_column(
                'maxsubordinates',
                new lang_string('team_maxsubordinates', 'tool_murelation'),
                column::TYPE_INTEGER
            )
                ->add_field('maxsubordinates', "$alias.maxsubordinates")
                ->set_sortable(true),
            'subordinates' => $this->define_subordinates_column(),
            'vacant' => $this->create_column(
                'vacant',
                new lang_string('supervisor_vacant', 'tool_murelation'),
                column::TYPE_BOOLEAN
            )
                ->add_field('vacant', "CASE WHEN $alias.userid IS NULL THEN 1 ELSE 0 END")
                ->set_sortable(true),
            'tenant' => $this->create_column('tenant', new lang_string('tenant', 'tool_murelation'), column::TYPE_TEXT)
                ->add_field('tenant', "(SELECT t.name FROM {tool_mutenancy_tenant} t WHERE t.id = $alias.tenantid)")
                ->set_sortable(true)
                ->set_available(fn(): bool => mulib::is_mutenancy_active()),
            'actions' => $this->create_actions_column('actions', new lang_string('actions'))
                ->add_field('id', "$alias.id")
                ->add_field('frameworkid', "$alias.frameworkid"),
            default => null,
        };
        return $column ? [$column] : [];
    }

    #[\Override]
    public function get_filter_names(): array {
        return ['teamname', 'teamidnumber', 'supmanaged', 'vacant', 'tenant'];
    }

    #[\Override]
    protected function define_filters(string $selector): array {
        $alias = $this->alias;
        $filter = match ($selector) {
            'teamname' => new filter\text($alias, 'teamname', new lang_string('team_name', 'tool_murelation'), "$alias.teamname"),
            'teamidnumber' => new filter\text(
                $alias,
                'teamidnumber',
                new lang_string('team_idnumber', 'tool_murelation'),
                "$alias.teamidnumber"
            ),
            'supmanaged' => new filter\boolean(
                $alias,
                'supmanaged',
                new lang_string('team_supmanaged', 'tool_murelation'),
                "$alias.supmanaged"
            ),
            'vacant' => new filter\boolean(
                $alias,
                'vacant',
                new lang_string('supervisor_vacant', 'tool_murelation'),
                "CASE WHEN $alias.userid IS NULL THEN 1 ELSE 0 END"
            ),
            'tenant' => (new filter\picker($alias, 'tenant', new lang_string('tenant', 'tool_murelation'), "$alias.tenantid"))
                ->set_integer_values()
                ->set_labels(fn(array $values): array => self::get_tenant_labels($values))
                ->set_available(fn(): bool => mulib::is_mutenancy_active()),
            default => null,
        };
        return $filter ? [$filter] : [];
    }

    /**
     * Returns tenant names.
     *
     * @param string[] $tenantids
     * @return array tenant id => plain text name
     */
    public static function get_tenant_labels(array $tenantids): array {
        global $DB;
        if (!$tenantids) {
            return [];
        }
        $syscontext = \core\context\system::instance();
        $records = $DB->get_records_list('tool_mutenancy_tenant', 'id', array_map('intval', $tenantids), '', 'id, name');
        return array_map(fn(stdClass $tenant): string
            => format_string($tenant->name, true, ['context' => $syscontext, 'escape' => false]), $records);
    }

    /**
     * Define team name column, linked to team management when the viewer may view the team positions.
     *
     * @return column
     */
    private function define_teamname_column(): column {
        $alias = $this->alias;
        $syscontext = \core\context\system::instance();
        $name = fn(stdClass $fields, bool $escape): string => $fields->teamname === null ? ''
            : format_string($fields->teamname, true, ['context' => $syscontext, 'escape' => $escape]);
        return $this->create_column('teamname', new lang_string('team_name', 'tool_murelation'), column::TYPE_TEXT)
            ->add_field('teamname', "$alias.teamname")
            ->add_field('id', "$alias.id")
            ->add_field('frameworkid', "$alias.frameworkid")
            ->add_field('tenantid', "$alias.tenantid")
            ->set_sortable(true)
            ->add_formatter(
                'name',
                fn(cell $cell): string => $name($cell->get_fields(), true),
                fn(cell $cell): export_value => export_value::text($name($cell->get_fields(), false))
            )
            ->add_formatter(
                'link',
                function (cell $cell) use ($name): string {
                    $fields = $cell->get_fields();
                    $framework = $cell->get_row()->get_cached_record('tool_murelation_framework', (int)$fields->frameworkid);
                    if ($framework->uimode != \tool_murelation\local\framework::UIMODE_TEAMS) {
                        return $name($fields, true);
                    }
                    $context = uimode_teams::get_team_context($framework, $fields);
                    if (!has_capability('tool/murelation:viewpositions', $context)) {
                        return $name($fields, true);
                    }
                    $url = new url('/admin/tool/murelation/management/team.php', ['id' => $fields->id]);
                    return \html_writer::link($url, $name($fields, true));
                },
                fn(cell $cell): export_value => export_value::text($name($cell->get_fields(), false))
            )
            ->set_default_formatter('link');
    }

    /**
     * Define number of subordinates column, with the team limit when set.
     *
     * @return column
     */
    private function define_subordinates_column(): column {
        $alias = $this->alias;
        return $this->create_column('subordinates', new lang_string('subordinates', 'tool_murelation'), column::TYPE_INTEGER)
            ->add_field('subordinates', "(SELECT COUNT('x')
                                            FROM {tool_murelation_subordinate} cs
                                           WHERE cs.supervisorid = $alias.id)")
            ->add_field('maxsubordinates', "$alias.maxsubordinates")
            ->set_sortable(true)
            ->add_formatter('limit', function (cell $cell): string {
                $fields = $cell->get_fields();
                $result = (string)(int)$fields->subordinates;
                if ($fields->maxsubordinates) {
                    $result .= ' / ' . (int)$fields->maxsubordinates;
                }
                return $result;
            }, fn(cell $cell): export_value => export_value::number((int)$cell->get_value()))
            ->set_default_formatter('limit');
    }
}
