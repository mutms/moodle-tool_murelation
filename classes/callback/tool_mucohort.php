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


namespace tool_murelation\callback;

use tool_murelation\local\framework;
use tool_murelation\local\uimode_teams;

/**
 * Hook callbacks for tool_mucohort.
 *
 * @package    tool_murelation
 * @copyright  2026 Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class tool_mucohort {
    /**
     * Describe team cohorts.
     *
     * @param \tool_mucohort\hook\cohort_owner $hook
     */
    public static function cohort_owner(\tool_mucohort\hook\cohort_owner $hook): void {
        global $DB;

        $cohort = $hook->get_cohort();
        if ($cohort->component !== 'tool_murelation') {
            return;
        }
        $supervisor = $DB->get_record('tool_murelation_supervisor', ['teamcohortid' => $cohort->id], '*', IGNORE_MULTIPLE);
        if (!$supervisor) {
            return;
        }
        [$name, $url] = self::get_team_link($supervisor);
        $hook->set_description(get_string('team_cohort_owner', 'tool_murelation', $name), $url);
    }

    /**
     * List frameworks and teams using the cohort.
     *
     * @param \tool_mucohort\hook\cohort_usage $hook
     */
    public static function cohort_usage(\tool_mucohort\hook\cohort_usage $hook): void {
        global $DB;

        $cohort = $hook->get_cohort();
        $syscontext = \context_system::instance();
        $canview = has_capability('tool/murelation:viewframeworks', $syscontext);
        $fields = [
            'managecohortid' => 'framework_managecohort',
            'supervisorcohortid' => 'framework_supervisorcohort',
            'subordinatecohortid' => 'framework_subordinatecohort',
        ];
        foreach ($fields as $field => $stringid) {
            $section = get_string('cohort_usage_framework', 'tool_murelation', get_string($stringid, 'tool_murelation'));
            $frameworks = $DB->get_records('tool_murelation_framework', [$field => $cohort->id], 'name ASC, id ASC');
            foreach ($frameworks as $framework) {
                $url = $canview ? new \core\url('/admin/tool/murelation/management/framework.php', ['id' => $framework->id]) : null;
                $name = format_string($framework->name, true, ['context' => $syscontext, 'escape' => false]);
                $hook->add_item($section, $name, $url, null, $canview);
            }
        }

        $section = get_string('cohort_usage_team', 'tool_murelation');
        $supervisors = $DB->get_records('tool_murelation_supervisor', ['teamcohortid' => $cohort->id], 'teamname ASC, id ASC');
        foreach ($supervisors as $supervisor) {
            [$name, $url] = self::get_team_link($supervisor);
            $hook->add_item($section, $name, $url, null, $url !== null);
        }
    }

    /**
     * Returns plain text team name and link if the current user may view the team.
     *
     * @param \stdClass $supervisor
     * @return array
     */
    private static function get_team_link(\stdClass $supervisor): array {
        global $DB;

        $url = null;
        $framework = $DB->get_record('tool_murelation_framework', ['id' => $supervisor->frameworkid], '*', MUST_EXIST);
        if ($framework->uimode == framework::UIMODE_TEAMS) {
            $context = uimode_teams::get_team_context($framework, $supervisor);
            if (has_capability('tool/murelation:viewpositions', $context)) {
                $url = new \core\url('/admin/tool/murelation/management/team.php', ['id' => $supervisor->id]);
            }
        }
        $name = format_string((string)$supervisor->teamname, true, ['context' => \context_system::instance(), 'escape' => false]);
        return [$name, $url];
    }
}
