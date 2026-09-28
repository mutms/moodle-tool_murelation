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

namespace tool_murelation\muform\autocompletemany;

use tool_mulib\local\mulib;
use tool_mulib\local\sql;

/**
 * Tenants of a relation framework.
 *
 * @package     tool_murelation
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class framework_tenantids extends \tool_mulib\muform\autocompletemany\base {
    /**
     * Constructor.
     */
    public function __construct() {
        require_capability('tool/murelation:manageframeworks', \context_system::instance());
    }

    #[\Override]
    public function get_args(): array {
        return [];
    }

    #[\Override]
    public function search(string $query, int $maxitems, array $exclude): ?array {
        global $DB;
        if (!mulib::is_mutenancy_active()) {
            return [];
        }
        $sql = new sql(
            "SELECT t.id, t.name
               FROM {tool_mutenancy_tenant} t
              WHERE t.archived = 0 /* exclude */ /* search */
           ORDER BY t.name ASC, t.id ASC"
        );
        $query = trim($query);
        if ($query !== '') {
            $search = \tool_mulib\local\search_util::get_search_query($query, ['name', 'idnumber'], 't');
            $sql = $sql->replace_comment('search', $search->wrap('AND ', ''));
        }
        $exclude = array_values(array_filter($exclude, fn($id) => preg_match('/^\d+$/D', $id)));
        if ($exclude) {
            [$notin, $params] = $DB->get_in_or_equal($exclude, SQL_PARAMS_NAMED, 'tex', false);
            $sql = $sql->replace_comment('exclude', new sql("AND t.id $notin", $params));
        }
        $tenants = $DB->get_records_sql_menu($sql->sql, $sql->params, 0, $maxitems + 1);
        if (count($tenants) > $maxitems) {
            return null;
        }
        return array_map(fn($name) => format_string($name), $tenants);
    }

    #[\Override]
    public function labels(array $values): array {
        global $DB;
        $values = array_values(array_filter($values, fn($id) => preg_match('/^\d+$/D', $id)));
        if (!$values || !mulib::is_mutenancy_active()) {
            return [];
        }
        $result = [];
        foreach ($DB->get_records_list('tool_mutenancy_tenant', 'id', $values, 'name ASC', 'id, name') as $tenant) {
            $result[(string)$tenant->id] = format_string($tenant->name);
        }
        return $result;
    }
}
