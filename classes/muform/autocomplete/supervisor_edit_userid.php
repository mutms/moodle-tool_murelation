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

namespace tool_murelation\muform\autocomplete;

use stdClass;
use tool_murelation\local\uimode_supervisors;
use tool_murelation\muform\util\autocomplete\framework_users_trait;

/**
 * Supervisor of one subordinate, supervisors UI.
 *
 * @package     tool_murelation
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class supervisor_edit_userid extends \tool_mulib\muform\autocomplete\base {
    use framework_users_trait;

    /** @var stdClass framework record */
    private stdClass $framework;
    /** @var \context context for identity fields and tenant rules */
    private \context $context;
    /** @var array candidate options, see framework_users_trait */
    private array $options;

    /**
     * Constructor.
     *
     * @param int $frameworkid framework id
     * @param int $subuserid subordinate user id
     */
    public function __construct(
        /** @var int framework id */
        private readonly int $frameworkid,
        /** @var int subordinate user id */
        private readonly int $subuserid
    ) {
        global $DB;
        $this->framework = $DB->get_record('tool_murelation_framework', ['id' => $frameworkid], '*', MUST_EXIST);
        $subuser = $DB->get_record('user', ['id' => $subuserid, 'deleted' => 0, 'confirmed' => 1], '*', MUST_EXIST);
        $this->context = \context_user::instance($subuser->id);
        if (!uimode_supervisors::can_manage_subordinate($this->framework, $subuserid)) {
            throw new \core\exception\invalid_parameter_exception('Cannot manage subordinate');
        }
        $currentuserid = $DB->get_field_sql(
            "SELECT sup.userid
               FROM {tool_murelation_subordinate} sub
               JOIN {tool_murelation_supervisor} sup ON sup.id = sub.supervisorid
              WHERE sub.userid = ? AND sub.frameworkid = ?",
            [$subuserid, $frameworkid]
        );
        $this->options = ['notuserid' => $subuserid, 'currentuserid' => (int)$currentuserid];
    }

    #[\Override]
    public function get_args(): array {
        return [$this->frameworkid, $this->subuserid];
    }

    #[\Override]
    public function search(string $query, int $maxitems): ?array {
        return $this->search_users($this->context, $query, $maxitems, [], $this->get_framework_where($this->framework, 'supervisor', $this->options));
    }

    #[\Override]
    public function label(string $value): ?string {
        $current = $this->get_current_user_label($this->context, $value, $this->options['currentuserid'] ?? null);
        if ($current !== null) {
            return $current;
        }
        $where = $this->get_framework_where($this->framework, 'supervisor', $this->options);
        return $this->user_labels($this->context, [$value], $where)[$value] ?? null;
    }

    #[\Override]
    public function validate(string $value): ?string {
        return $this->validate_users([$value])[$value] ?? null;
    }
}
