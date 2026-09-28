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
use tool_mulib\local\search_util;
use tool_mulib\local\context_map;
use tool_mulib\local\mulib;
use tool_mulib\local\sql;
use tool_murelation\local\framework;
use tool_murelation\local\uimode_teams;

/**
 * Cohort whose members are added to a team, teams UI.
 *
 * Teams without tenant accept global cohorts only, tenant teams also cohorts of their tenant.
 *
 * @package     tool_murelation
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class members_add_cohort_cohortid extends \tool_mulib\muform\autocomplete\base {
    /** @var stdClass framework record */
    private stdClass $framework;
    /** @var stdClass team (supervisor) record */
    private stdClass $supervisor;
    /** @var \context team context */
    private \context $context;

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
    }

    #[\Override]
    public function get_args(): array {
        return [$this->supervisorid];
    }

    #[\Override]
    public function search(string $query, int $maxitems): ?array {
        global $DB;
        $sql = $this->get_cohorts_sql()->replace_comment('searchsql', search_util::get_cohort_search_query(trim($query), 'ch')->wrap('AND ', ''));
        $sql = $sql->replace_comment('idwhere', '');
        $cohorts = $DB->get_records_sql_menu($sql->sql, $sql->params, 0, $maxitems + 1);
        if (count($cohorts) > $maxitems) {
            return null;
        }
        return array_map(fn($name) => format_string($name, true, ['context' => $this->context]), $cohorts);
    }

    #[\Override]
    public function label(string $value): ?string {
        global $DB;
        if (!preg_match('/^[1-9][0-9]*$/D', $value)) {
            return null;
        }
        $sql = $this->get_cohorts_sql()->replace_comment('searchsql', '');
        $sql = $sql->replace_comment('idwhere', 'AND ch.id = ?', [(int)$value]);
        $cohorts = $DB->get_records_sql_menu($sql->sql, $sql->params);
        if (!isset($cohorts[(int)$value])) {
            return null;
        }
        return format_string($cohorts[(int)$value], true, ['context' => $this->context]);
    }

    #[\Override]
    public function validate(string $value): ?string {
        global $DB;
        $candidates = self::get_candidates($this->supervisor->id, (int)$value);
        if (!$candidates) {
            return get_string('error_nosubordinates', 'tool_murelation');
        }
        if ($this->supervisor->maxsubordinates) {
            $current = $DB->count_records('tool_murelation_subordinate', ['supervisorid' => $this->supervisor->id]);
            if ($current + count($candidates) > $this->supervisor->maxsubordinates) {
                return get_string('error_maxsubordinates', 'tool_murelation');
            }
        }
        return null;
    }

    /**
     * Visible cohorts allowed in the team tenant.
     *
     * @return sql with searchsql and idwhere comments
     */
    private function get_cohorts_sql(): sql {
        global $USER;

        $sql = (new sql(
            "SELECT ch.id, ch.name
               FROM {cohort} ch
               JOIN {context} chcontext ON chcontext.id = ch.contextid
               /* capsubquery */
              WHERE (ch.visible = 1 OR capctx.id IS NOT NULL)
                    /* idwhere */ /* searchsql */ /* tenantwhere */
           ORDER BY ch.name ASC, ch.id ASC"
        ))->replace_comment(
            'capsubquery',
            context_map::get_contexts_by_capability_query(
                'moodle/cohort:view',
                $USER->id,
                new sql("(ctx.contextlevel = ? OR ctx.contextlevel = ?)", [\context_system::LEVEL, \context_coursecat::LEVEL])
            )->wrap("LEFT JOIN (", ")capctx ON capctx.id = ch.contextid")
        );

        if (!mulib::is_mutenancy_active()) {
            return $sql->replace_comment('tenantwhere', '');
        }
        if ($this->supervisor->tenantid) {
            return $sql->replace_comment('tenantwhere', "AND (chcontext.tenantid IS NULL OR chcontext.tenantid = ?)", [$this->supervisor->tenantid]);
        }
        return $sql->replace_comment('tenantwhere', "AND chcontext.tenantid IS NULL");
    }

    /**
     * Returns candidates for new team members.
     *
     * @param int $supervisorid
     * @param int $cohortid
     * @return array user ids
     */
    public static function get_candidates(int $supervisorid, int $cohortid): array {
        global $DB;

        $supervisor = $DB->get_record('tool_murelation_supervisor', ['id' => $supervisorid], '*', MUST_EXIST);
        $framework = $DB->get_record('tool_murelation_framework', ['id' => $supervisor->frameworkid, 'uimode' => framework::UIMODE_TEAMS], '*', MUST_EXIST);
        $context = uimode_teams::get_team_context($framework, $supervisor);

        $sql = (new sql(
            "SELECT u.id
               FROM {user} u
               JOIN {cohort_members} cm ON cm.userid = u.id AND cm.cohortid = :cohortid
               /* cohortjoin */
          LEFT JOIN {tool_murelation_subordinate} sub ON sub.userid = u.id AND sub.supervisorid = :supervisorid
              WHERE u.deleted = 0 AND u.confirmed = 1 AND sub.id IS NULL
                    /* tenantwhere */
           ORDER BY u.id ASC",
            ['supervisorid' => $supervisor->id, 'cohortid' => $cohortid]
        ));

        if (mulib::is_mutenancy_active()) {
            $tenantwhere = \tool_mutenancy\local\tenancy::get_related_users_exists('u.id', $context, 'AND');
            $sql = $sql->replace_comment('tenantwhere', $tenantwhere);
        } else {
            $sql = $sql->replace_comment('tenantwhere', "");
        }

        if ($framework->subordinatecohortid) {
            $sql = $sql->replace_comment(
                'cohortjoin',
                new sql("JOIN {cohort_members} scm ON scm.userid = u.id AND scm.cohortid = ?", [$framework->subordinatecohortid])
            );
        } else {
            $sql = $sql->replace_comment('cohortjoin', "");
        }

        $sql->ensure_no_comments();

        return $DB->get_fieldset_sql($sql->sql, $sql->params);
    }
}
