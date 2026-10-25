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
// phpcs:disable moodle.Commenting.DocblockDescription.Missing

namespace tool_murelation\phpunit\callback;

use tool_murelation\callback\tool_mucohort;
use tool_murelation\local\framework;
use tool_murelation\local\uimode_teams;

/**
 * Tests of tool_mucohort hook callbacks.
 *
 * @group      MuTMS
 * @package    tool_murelation
 * @copyright  2026 Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_murelation\callback\tool_mucohort
 */
final class tool_mucohort_test extends \advanced_testcase {
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        if (!\tool_mulib\local\mulib::is_mucohort_available()) {
            $this->markTestSkipped('tool_mucohort is not installed');
        }
    }

    /**
     * Returns usage items as "section: name|url|visible" strings.
     *
     * @param \stdClass $cohort
     * @return string[]
     */
    private function get_usage(\stdClass $cohort): array {
        $hook = new \tool_mucohort\hook\cohort_usage($cohort);
        tool_mucohort::cohort_usage($hook);
        $result = [];
        foreach ($hook->get_sections() as $section => $items) {
            foreach ($items as $item) {
                $result[] = $section . ': ' . $item->name . '|' . ($item->url ? 'url' : '') . '|' . ($item->visible ? 'visible' : 'hidden');
            }
        }
        return $result;
    }

    public function test_cohort_owner(): void {
        global $DB;

        /** @var \tool_murelation_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_murelation');
        $framework = $generator->create_framework(['uimode' => framework::UIMODE_TEAMS]);
        $supervisor = uimode_teams::team_create((object)[
            'frameworkid' => $framework->id,
            'teamname' => 'Team <b>1</b>',
            'teamcohortcreate' => 1,
        ]);
        $cohort = $DB->get_record('cohort', ['id' => $supervisor->teamcohortid], '*', MUST_EXIST);

        // The callback is registered in db/hooks.php.
        $this->setAdminUser();
        $hook = new \tool_mucohort\hook\cohort_owner($cohort);
        \core\di::get(\core\hook\manager::class)->dispatch($hook);
        $this->assertSame('Team cohort of Team 1', $hook->get_description());
        $this->assertStringContainsString('/admin/tool/murelation/management/team.php?id=' . $supervisor->id, $hook->get_url()->out(false));

        $this->setUser($this->getDataGenerator()->create_user());
        $hook = new \tool_mucohort\hook\cohort_owner($cohort);
        tool_mucohort::cohort_owner($hook);
        $this->assertNull($hook->get_url());

        $hook = new \tool_mucohort\hook\cohort_owner($this->getDataGenerator()->create_cohort());
        tool_mucohort::cohort_owner($hook);
        $this->assertNull($hook->get_description());
    }

    public function test_cohort_usage(): void {
        global $DB;

        /** @var \tool_murelation_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_murelation');
        $cohort = $this->getDataGenerator()->create_cohort();
        $generator->create_framework(['name' => 'Framework 1', 'uimode' => framework::UIMODE_TEAMS, 'managecohortid' => $cohort->id]);
        $framework = $generator->create_framework(['name' => 'Framework 2', 'uimode' => framework::UIMODE_TEAMS]);
        $supervisor = uimode_teams::team_create((object)[
            'frameworkid' => $framework->id,
            'teamname' => 'Team 1',
            'teamcohortcreate' => 1,
        ]);
        $teamcohort = $DB->get_record('cohort', ['id' => $supervisor->teamcohortid], '*', MUST_EXIST);

        $section = get_string('cohort_usage_framework', 'tool_murelation', get_string('framework_managecohort', 'tool_murelation'));
        $this->setAdminUser();
        $this->assertSame([$section . ': Framework 1|url|visible'], $this->get_usage($cohort));
        $this->assertSame([get_string('cohort_usage_team', 'tool_murelation') . ': Team 1|url|visible'], $this->get_usage($teamcohort));
        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertSame([$section . ': Framework 1||hidden'], $this->get_usage($cohort));
    }
}
