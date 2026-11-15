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

namespace tool_murelation\muform\userpicker;

use stdClass;
use tool_mulib\local\sql;
use tool_murelation\local\uimode_teams;
use tool_murelation\muform\util\autocomplete\framework_users_trait;

/**
 * New members of a team, teams UI.
 *
 * @package     tool_murelation
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class members_create_subuserids extends \tool_mulib\muform\userpicker\base {
    use framework_users_trait;

    /** @var stdClass framework record */
    private stdClass $framework;
    /** @var stdClass team (supervisor) record */
    private stdClass $supervisor;
    /** @var \context context for identity fields and tenant rules */
    private \context $context;
    /** @var array candidate options, see framework_users_trait */
    private array $options;

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
        $this->options = ['notsubordinate' => true];
    }

    #[\Override]
    public function get_args(): array {
        return [$this->supervisorid];
    }

    #[\Override]
    protected function get_context(): \core\context {
        return $this->context;
    }

    #[\Override]
    protected function get_candidates_sql(): ?sql {
        return $this->get_framework_where($this->framework, 'subordinate', $this->options);
    }
}
