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

namespace tool_murelation\phpunit\local;

use tool_murelation\local\management;
use stdClass;

/**
 * Management helper tests.
 *
 * @group       MuTMS
 * @package     tool_murelation
 * @copyright   2025 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_murelation\local\management
 */
final class management_test extends \advanced_testcase {
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    public function test_callback_tenant_manager_capabilities(): void {
        if (!\tool_mulib\local\mulib::is_mutenancy_available()) {
            $this->markTestSkipped('mutenancy not available');
        }

        \tool_mutenancy\local\tenancy::activate();

        $capabilities = \tool_mutenancy\local\manager::get_default_capabilities();
        $this->assertSame(CAP_ALLOW, $capabilities['tool/murelation:managepositions']);
        $this->assertSame(CAP_ALLOW, $capabilities['tool/murelation:viewpositions']);
        $this->assertArrayNotHasKey('tool/murelation:viewframeworks', $capabilities);
        $this->assertArrayNotHasKey('tool/murelation:manageframeworks', $capabilities);
    }

    public function test_callback_mucohort_cohort_owner(): void {
        global $DB;

        if (!\tool_mulib\local\mulib::is_mucohort_available()) {
            $this->markTestSkipped('tool_mucohort not installed');
        }
        /** @var \tool_murelation_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_murelation');
        $framework = $generator->create_framework(['uimode' => \tool_murelation\local\framework::UIMODE_TEAMS]);
        $supervisor = \tool_murelation\local\uimode_teams::team_create((object)[
            'frameworkid' => $framework->id,
            'teamname' => 'Team <b>1</b>',
            'teamcohortcreate' => 1,
        ]);
        $cohort = $DB->get_record('cohort', ['id' => $supervisor->teamcohortid], '*', MUST_EXIST);
        $other = $this->getDataGenerator()->create_cohort();

        // The callback is registered in db/hooks.php.
        $this->setAdminUser();
        $hook = new \tool_mucohort\hook\cohort_owner($cohort);
        \core\di::get(\core\hook\manager::class)->dispatch($hook);
        $this->assertSame('Team cohort of Team 1', $hook->get_description());
        $this->assertStringContainsString('/admin/tool/murelation/management/team.php?id=' . $supervisor->id, $hook->get_url()->out(false));

        $this->setUser($this->getDataGenerator()->create_user());
        $hook = new \tool_mucohort\hook\cohort_owner($cohort);
        management::callback_mucohort_cohort_owner($hook);
        $this->assertNull($hook->get_url());

        $hook = new \tool_mucohort\hook\cohort_owner($other);
        management::callback_mucohort_cohort_owner($hook);
        $this->assertNull($hook->get_description());
    }
}
