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

namespace tool_murelation\phpunit\muform\autocomplete;

use tool_murelation\muform\autocomplete\members_add_cohort_cohortid;
use tool_murelation\local\framework;
use tool_mulib\local\mulib;

/**
 * Cohort with subordinate candidates for team autocomplete source tests.
 *
 * @group       MuTMS
 * @package     tool_murelation
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_murelation\muform\autocomplete\members_add_cohort_cohortid
 */
final class members_add_cohort_cohortid_test extends \advanced_testcase {
    #[\Override]
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    public function test_constructor(): void {
        /** @var \tool_murelation_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_murelation');

        $syscontext = \context_system::instance();

        $framework0 = $generator->create_framework(['uimode' => framework::UIMODE_SUPERVISORS]);
        $framework1 = $generator->create_framework(['uimode' => framework::UIMODE_TEAMS]);

        $manager = $this->getDataGenerator()->create_user();
        $user0 = $this->getDataGenerator()->create_user();
        $user1 = $this->getDataGenerator()->create_user();

        $roleid = create_role('man', 'man', 'man');
        assign_capability('tool/murelation:viewpositions', CAP_ALLOW, $roleid, $syscontext->id);
        assign_capability('tool/murelation:managepositions', CAP_ALLOW, $roleid, $syscontext->id);
        role_assign($roleid, $manager->id, $syscontext);

        $supervisor0 = \tool_murelation\local\uimode_supervisors::supervisor_edit((object)[
            'frameworkid' => $framework0->id,
            'userid' => $user0->id,
            'subuserid' => $user1->id,
        ]);
        $supervisor1 = \tool_murelation\local\uimode_teams::team_create((object)[
            'frameworkid' => $framework1->id,
            'teamname' => 'Team 1',
        ]);

        $this->setUser($manager);
        $source = new members_add_cohort_cohortid((int)$supervisor1->id);
        $this->assertSame([(int)$supervisor1->id], $source->get_args());

        try {
            new members_add_cohort_cohortid((int)$supervisor0->id);
            $this->fail('exception expected');
        } catch (\core\exception\moodle_exception $ex) {
            $this->assertInstanceOf(\invalid_parameter_exception::class, $ex);
            $this->assertSame('Invalid parameter value detected (Framework is not compatible with Teams mode)', $ex->getMessage());
        }

        $this->setUser($user1);
        try {
            new members_add_cohort_cohortid((int)$supervisor1->id);
            $this->fail('exception expected');
        } catch (\core\exception\moodle_exception $ex) {
            $this->assertInstanceOf(\invalid_parameter_exception::class, $ex);
            $this->assertSame('Invalid parameter value detected (Cannot manage team members)', $ex->getMessage());
        }
    }

    public function test_search(): void {
        /** @var \tool_murelation_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_murelation');

        $category1 = $this->getDataGenerator()->create_category([]);
        $category2 = $this->getDataGenerator()->create_category([]);

        $syscontext = \context_system::instance();
        $catcontext1 = \context_coursecat::instance($category1->id);
        $catcontext2 = \context_coursecat::instance($category2->id);

        $cohort0 = $this->getDataGenerator()->create_cohort(['visible' => 1]);
        $cohort1 = $this->getDataGenerator()->create_cohort(['visible' => 1, 'contextid' => $catcontext1->id]);
        $cohort2 = $this->getDataGenerator()->create_cohort(['visible' => 0, 'contextid' => $catcontext2->id]);

        $framework0 = $generator->create_framework(['uimode' => framework::UIMODE_SUPERVISORS]);
        $framework1 = $generator->create_framework([
            'uimode' => framework::UIMODE_TEAMS,
        ]);
        $framework2 = $generator->create_framework([
            'uimode' => framework::UIMODE_TEAMS,
            'subordinatecohortid' => $cohort0->id,
        ]);

        $admin = get_admin();
        $manager = $this->getDataGenerator()->create_user();
        $user0 = $this->getDataGenerator()->create_user();
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();
        $user4 = $this->getDataGenerator()->create_user();

        $roleid = create_role('man', 'man', 'man');
        assign_capability('tool/murelation:viewpositions', CAP_ALLOW, $roleid, $syscontext->id);
        assign_capability('tool/murelation:managepositions', CAP_ALLOW, $roleid, $syscontext->id);

        role_assign($roleid, $manager->id, $syscontext);

        cohort_add_member($cohort0->id, $user0->id);
        cohort_add_member($cohort0->id, $user1->id);
        cohort_add_member($cohort0->id, $user2->id);
        cohort_add_member($cohort1->id, $user1->id);
        cohort_add_member($cohort2->id, $user1->id);
        cohort_add_member($cohort2->id, $user2->id);
        cohort_add_member($cohort2->id, $user3->id);

        $supervisor0 = \tool_murelation\local\uimode_supervisors::supervisor_edit((object)[
            'frameworkid' => $framework0->id,
            'userid' => $user0->id,
            'subuserid' => $user1->id,
        ]);
        $supervisor1 = \tool_murelation\local\uimode_teams::team_create((object)[
            'frameworkid' => $framework1->id,
            'teamname' => 'Team 1',
            'contextid' => $catcontext1->id,
        ]);
        $supervisor2 = \tool_murelation\local\uimode_teams::team_create((object)[
            'frameworkid' => $framework2->id,
            'teamname' => 'Team 2',
        ]);
        $supervisor3 = \tool_murelation\local\uimode_teams::team_create((object)[
            'frameworkid' => $framework1->id,
            'teamname' => 'Team 3',
            'subuserids' => [$user1->id],
        ]);

        $this->setUser($admin);

        $source = new members_add_cohort_cohortid((int)$supervisor1->id);
        $this->assertSame(50, $source->get_maxitems());
        $this->assertSame([
            (int)$cohort0->id => $cohort0->name,
            (int)$cohort1->id => $cohort1->name,
            (int)$cohort2->id => $cohort2->name,
        ], $source->search('', 50));

        $this->setUser($manager);

        $source = new members_add_cohort_cohortid((int)$supervisor1->id);
        $this->assertSame([
            (int)$cohort0->id => $cohort0->name,
            (int)$cohort1->id => $cohort1->name,
        ], $source->search('', 50));

        $this->assertSame([
            (int)$cohort0->id => $cohort0->name,
        ], $source->search($cohort0->name, 50));

        $this->assertNull($source->search('', 1));

        if (!mulib::is_mutenancy_available()) {
            return;
        }

        /** @var \tool_mutenancy_generator $tenantgenerator */
        $tenantgenerator = $this->getDataGenerator()->get_plugin_generator('tool_mutenancy');
        \tool_mutenancy\local\tenancy::activate();

        $tenant1 = $tenantgenerator->create_tenant(['assoccohortid' => $cohort1->id]);
        $tenant2 = $tenantgenerator->create_tenant(['assoccohortid' => $cohort2->id]);
        $tenantcatcontext1 = \context_coursecat::instance($tenant1->categoryid);
        $tenantcatcontext2 = \context_coursecat::instance($tenant2->categoryid);

        $cohort3 = $this->getDataGenerator()->create_cohort(['contextid' => $tenantcatcontext1->id]);
        $cohort4 = $this->getDataGenerator()->create_cohort(['contextid' => $tenantcatcontext2->id]);

        $this->setUser($manager);

        $source = new members_add_cohort_cohortid((int)$supervisor1->id);
        $this->assertSame([
            (int)$cohort0->id => $cohort0->name,
            (int)$cohort1->id => $cohort1->name,
        ], $source->search('', 50));

        $supervisor4 = \tool_murelation\local\uimode_teams::team_create((object)[
            'frameworkid' => $framework1->id,
            'teamname' => 'Team 4',
            'tenantid' => $tenant1->id,
        ]);

        $source = new members_add_cohort_cohortid((int)$supervisor4->id);
        $this->assertSame([
            (int)$cohort0->id => $cohort0->name,
            (int)$cohort1->id => $cohort1->name,
            (int)$cohort3->id => $cohort3->name,
        ], $source->search('', 50));
    }

    public function test_get_candidates(): void {
        /** @var \tool_murelation_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_murelation');

        $category1 = $this->getDataGenerator()->create_category([]);
        $category2 = $this->getDataGenerator()->create_category([]);

        $syscontext = \context_system::instance();
        $catcontext1 = \context_coursecat::instance($category1->id);
        $catcontext2 = \context_coursecat::instance($category2->id);

        $cohort0 = $this->getDataGenerator()->create_cohort(['visible' => 1]);
        $cohort1 = $this->getDataGenerator()->create_cohort(['visible' => 1, 'contextid' => $catcontext1->id]);
        $cohort2 = $this->getDataGenerator()->create_cohort(['visible' => 0, 'contextid' => $catcontext2->id]);

        $framework0 = $generator->create_framework(['uimode' => framework::UIMODE_SUPERVISORS]);
        $framework1 = $generator->create_framework([
            'uimode' => framework::UIMODE_TEAMS,
        ]);
        $framework2 = $generator->create_framework([
            'uimode' => framework::UIMODE_TEAMS,
            'subordinatecohortid' => $cohort0->id,
        ]);

        $admin = get_admin();
        $manager = $this->getDataGenerator()->create_user();
        $user0 = $this->getDataGenerator()->create_user();
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();
        $user4 = $this->getDataGenerator()->create_user();

        $roleid = create_role('man', 'man', 'man');
        assign_capability('tool/murelation:viewpositions', CAP_ALLOW, $roleid, $syscontext->id);
        assign_capability('tool/murelation:managepositions', CAP_ALLOW, $roleid, $syscontext->id);

        role_assign($roleid, $manager->id, $syscontext);

        cohort_add_member($cohort0->id, $user0->id);
        cohort_add_member($cohort0->id, $user1->id);
        cohort_add_member($cohort0->id, $user2->id);
        cohort_add_member($cohort2->id, $user1->id);
        cohort_add_member($cohort2->id, $user2->id);
        cohort_add_member($cohort2->id, $user3->id);

        $supervisor0 = \tool_murelation\local\uimode_supervisors::supervisor_edit((object)[
            'frameworkid' => $framework0->id,
            'userid' => $user0->id,
            'subuserid' => $user1->id,
        ]);
        $supervisor1 = \tool_murelation\local\uimode_teams::team_create((object)[
            'frameworkid' => $framework1->id,
            'teamname' => 'Team 1',
            'contextid' => $catcontext1->id,
        ]);
        $supervisor2 = \tool_murelation\local\uimode_teams::team_create((object)[
            'frameworkid' => $framework2->id,
            'teamname' => 'Team 2',
        ]);
        $supervisor3 = \tool_murelation\local\uimode_teams::team_create((object)[
            'frameworkid' => $framework1->id,
            'teamname' => 'Team 3',
            'subuserids' => [$user1->id],
        ]);

        $this->setUser($manager);

        $this->assertEquals(
            [$user0->id, $user1->id, $user2->id],
            members_add_cohort_cohortid::get_candidates($supervisor1->id, $cohort0->id)
        );
        $this->assertEquals(
            [$user1->id, $user2->id, $user3->id],
            members_add_cohort_cohortid::get_candidates($supervisor1->id, $cohort2->id)
        );
        $this->assertEquals(
            [$user0->id, $user1->id, $user2->id],
            members_add_cohort_cohortid::get_candidates($supervisor2->id, $cohort0->id)
        );
        $this->assertEquals(
            [$user1->id, $user2->id],
            members_add_cohort_cohortid::get_candidates($supervisor2->id, $cohort2->id)
        );
        $this->assertEquals(
            [$user0->id, $user2->id],
            members_add_cohort_cohortid::get_candidates($supervisor3->id, $cohort0->id)
        );
        $this->assertEquals(
            [$user2->id, $user3->id],
            members_add_cohort_cohortid::get_candidates($supervisor3->id, $cohort2->id)
        );

        try {
            members_add_cohort_cohortid::get_candidates($supervisor0->id, $cohort0->id);
            $this->fail('exception expected');
        } catch (\core\exception\moodle_exception $ex) {
            $this->assertInstanceOf(\dml_missing_record_exception::class, $ex);
        }

        if (!mulib::is_mutenancy_available()) {
            return;
        }
        /** @var \tool_mutenancy_generator $tenantgenerator */
        $tenantgenerator = $this->getDataGenerator()->get_plugin_generator('tool_mutenancy');
        \tool_mutenancy\local\tenancy::activate();

        $tenant1 = $tenantgenerator->create_tenant(['assoccohortid' => $cohort1->id]);
        $tenant2 = $tenantgenerator->create_tenant(['assoccohortid' => $cohort2->id]);
        $tenantcontext1 = \context_tenant::instance($tenant1->id);
        $tenantcontext2 = \context_tenant::instance($tenant2->id);
        $tenantcatcontext1 = \context_coursecat::instance($tenant1->categoryid);
        $tenantcatcontext2 = \context_coursecat::instance($tenant2->categoryid);

        $cohort3 = $this->getDataGenerator()->create_cohort(['contextid' => $tenantcatcontext1->id]);
        $cohort4 = $this->getDataGenerator()->create_cohort(['contextid' => $tenantcatcontext2->id]);

        $supervisor4 = \tool_murelation\local\uimode_teams::team_create((object)[
            'frameworkid' => $framework1->id,
            'teamname' => 'Team 4',
            'tenantid' => $tenant1->id,
        ]);
        $supervisor5 = \tool_murelation\local\uimode_teams::team_create((object)[
            'frameworkid' => $framework1->id,
            'teamname' => 'Team 5',
            'tenantid' => $tenant2->id,
        ]);

        $tuser1 = $this->getDataGenerator()->create_user(['tenantid' => $tenant1->id]);
        $tuser2 = $this->getDataGenerator()->create_user(['tenantid' => $tenant2->id]);

        cohort_add_member($cohort0->id, $tuser1->id);
        cohort_add_member($cohort0->id, $tuser2->id);
        cohort_add_member($cohort3->id, $tuser1->id);
        cohort_add_member($cohort4->id, $tuser2->id);

        $this->assertEquals(
            [$user0->id, $user1->id, $user2->id, $tuser1->id, $tuser2->id],
            members_add_cohort_cohortid::get_candidates($supervisor1->id, $cohort0->id)
        );

        $this->assertEquals(
            [$tuser1->id],
            members_add_cohort_cohortid::get_candidates($supervisor4->id, $cohort0->id)
        );
    }

    public function test_label(): void {
        /** @var \tool_murelation_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_murelation');

        $category1 = $this->getDataGenerator()->create_category([]);
        $category2 = $this->getDataGenerator()->create_category([]);

        $syscontext = \context_system::instance();
        $catcontext1 = \context_coursecat::instance($category1->id);
        $catcontext2 = \context_coursecat::instance($category2->id);

        $cohort0 = $this->getDataGenerator()->create_cohort(['visible' => 1]);
        $cohort1 = $this->getDataGenerator()->create_cohort(['visible' => 1, 'contextid' => $catcontext1->id]);
        $cohort2 = $this->getDataGenerator()->create_cohort(['visible' => 0, 'contextid' => $catcontext2->id]);

        $framework1 = $generator->create_framework([
            'uimode' => framework::UIMODE_TEAMS,
        ]);

        $admin = get_admin();
        $manager = $this->getDataGenerator()->create_user();

        $roleid = create_role('man', 'man', 'man');
        assign_capability('tool/murelation:viewpositions', CAP_ALLOW, $roleid, $syscontext->id);
        assign_capability('tool/murelation:managepositions', CAP_ALLOW, $roleid, $syscontext->id);

        role_assign($roleid, $manager->id, $syscontext);

        $supervisor1 = \tool_murelation\local\uimode_teams::team_create((object)[
            'frameworkid' => $framework1->id,
            'teamname' => 'Team 1',
            'contextid' => $catcontext1->id,
        ]);

        $this->setUser($manager);

        $source = new members_add_cohort_cohortid((int)$supervisor1->id);
        $this->assertSame($cohort0->name, $source->label((string)$cohort0->id));
        $this->assertSame($cohort1->name, $source->label((string)$cohort1->id));
        $this->assertNull($source->label((string)$cohort2->id));
        $this->assertNull($source->label('-10'));
        $this->assertNull($source->label('0'));
        $this->assertNull($source->label('999999'));
        $this->assertNull($source->label('abc'));
        $this->assertNull($source->label(''));

        $this->setUser($admin);
        $source = new members_add_cohort_cohortid((int)$supervisor1->id);
        $this->assertSame($cohort2->name, $source->label((string)$cohort2->id));

        if (!mulib::is_mutenancy_available()) {
            return;
        }
        /** @var \tool_mutenancy_generator $tenantgenerator */
        $tenantgenerator = $this->getDataGenerator()->get_plugin_generator('tool_mutenancy');
        \tool_mutenancy\local\tenancy::activate();

        $tenant1 = $tenantgenerator->create_tenant();
        $tenant2 = $tenantgenerator->create_tenant();
        $tenantcatcontext1 = \context_coursecat::instance($tenant1->categoryid);
        $tenantcatcontext2 = \context_coursecat::instance($tenant2->categoryid);

        $cohort3 = $this->getDataGenerator()->create_cohort(['contextid' => $tenantcatcontext1->id]);
        $cohort4 = $this->getDataGenerator()->create_cohort(['contextid' => $tenantcatcontext2->id]);

        $supervisor4 = \tool_murelation\local\uimode_teams::team_create((object)[
            'frameworkid' => $framework1->id,
            'teamname' => 'Team 4',
            'tenantid' => $tenant1->id,
        ]);

        $this->setUser($manager);

        $source = new members_add_cohort_cohortid((int)$supervisor1->id);
        $this->assertSame($cohort0->name, $source->label((string)$cohort0->id));
        $this->assertNull($source->label((string)$cohort3->id));
        $this->assertNull($source->label((string)$cohort4->id));

        $source = new members_add_cohort_cohortid((int)$supervisor4->id);
        $this->assertSame($cohort0->name, $source->label((string)$cohort0->id));
        $this->assertSame($cohort3->name, $source->label((string)$cohort3->id));
        $this->assertNull($source->label((string)$cohort4->id));
    }

    public function test_validate(): void {
        /** @var \tool_murelation_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_murelation');

        $category1 = $this->getDataGenerator()->create_category([]);
        $category2 = $this->getDataGenerator()->create_category([]);

        $syscontext = \context_system::instance();
        $catcontext1 = \context_coursecat::instance($category1->id);
        $catcontext2 = \context_coursecat::instance($category2->id);

        $cohort0 = $this->getDataGenerator()->create_cohort(['visible' => 1]);
        $cohort1 = $this->getDataGenerator()->create_cohort(['visible' => 1, 'contextid' => $catcontext1->id]);
        $cohort2 = $this->getDataGenerator()->create_cohort(['visible' => 0, 'contextid' => $catcontext2->id]);

        $framework0 = $generator->create_framework(['uimode' => framework::UIMODE_SUPERVISORS]);
        $framework1 = $generator->create_framework([
            'uimode' => framework::UIMODE_TEAMS,
        ]);
        $framework2 = $generator->create_framework([
            'uimode' => framework::UIMODE_TEAMS,
            'subordinatecohortid' => $cohort0->id,
        ]);

        $admin = get_admin();
        $manager = $this->getDataGenerator()->create_user();
        $user0 = $this->getDataGenerator()->create_user();
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();
        $user4 = $this->getDataGenerator()->create_user();

        $roleid = create_role('man', 'man', 'man');
        assign_capability('tool/murelation:viewpositions', CAP_ALLOW, $roleid, $syscontext->id);
        assign_capability('tool/murelation:managepositions', CAP_ALLOW, $roleid, $syscontext->id);

        role_assign($roleid, $manager->id, $syscontext);

        cohort_add_member($cohort0->id, $user0->id);
        cohort_add_member($cohort0->id, $user1->id);
        cohort_add_member($cohort0->id, $user2->id);
        cohort_add_member($cohort2->id, $user1->id);
        cohort_add_member($cohort2->id, $user2->id);
        cohort_add_member($cohort2->id, $user3->id);

        $supervisor0 = \tool_murelation\local\uimode_supervisors::supervisor_edit((object)[
            'frameworkid' => $framework0->id,
            'userid' => $user0->id,
            'subuserid' => $user1->id,
        ]);
        $supervisor1 = \tool_murelation\local\uimode_teams::team_create((object)[
            'frameworkid' => $framework1->id,
            'teamname' => 'Team 1',
            'contextid' => $catcontext1->id,
        ]);
        $supervisor2 = \tool_murelation\local\uimode_teams::team_create((object)[
            'frameworkid' => $framework2->id,
            'teamname' => 'Team 2',
        ]);
        $supervisor3 = \tool_murelation\local\uimode_teams::team_create((object)[
            'frameworkid' => $framework1->id,
            'teamname' => 'Team 3',
            'subuserids' => [$user1->id],
        ]);

        $this->setUser($manager);

        $source = new members_add_cohort_cohortid((int)$supervisor1->id);
        $this->assertNotNull($source->label((string)$cohort0->id));
        $this->assertNull($source->validate((string)$cohort0->id));
        $this->assertNotNull($source->label((string)$cohort1->id));
        $this->assertSame('No subordinates found', $source->validate((string)$cohort1->id));
        // Hidden cohort is not allowed for manager without cohort view capability.
        $this->assertNull($source->label((string)$cohort2->id));

        $supervisor1 = \tool_murelation\local\supervisor::update((object)[
            'id' => $supervisor1->id,
            'maxsubordinates' => 2,
        ]);
        $source = new members_add_cohort_cohortid((int)$supervisor1->id);
        $this->assertSame('Subordinates limit reached', $source->validate((string)$cohort0->id));
        $supervisor1 = \tool_murelation\local\supervisor::update((object)[
            'id' => $supervisor1->id,
            'maxsubordinates' => 3,
        ]);
        $source = new members_add_cohort_cohortid((int)$supervisor1->id);
        $this->assertNull($source->validate((string)$cohort0->id));

        if (!mulib::is_mutenancy_available()) {
            return;
        }
        /** @var \tool_mutenancy_generator $tenantgenerator */
        $tenantgenerator = $this->getDataGenerator()->get_plugin_generator('tool_mutenancy');
        \tool_mutenancy\local\tenancy::activate();

        $tenant1 = $tenantgenerator->create_tenant(['assoccohortid' => $cohort1->id]);
        $tenant2 = $tenantgenerator->create_tenant(['assoccohortid' => $cohort2->id]);
        $tenantcontext1 = \context_tenant::instance($tenant1->id);
        $tenantcontext2 = \context_tenant::instance($tenant2->id);
        $tenantcatcontext1 = \context_coursecat::instance($tenant1->categoryid);
        $tenantcatcontext2 = \context_coursecat::instance($tenant2->categoryid);

        $cohort3 = $this->getDataGenerator()->create_cohort(['contextid' => $tenantcatcontext1->id]);
        $cohort4 = $this->getDataGenerator()->create_cohort(['contextid' => $tenantcatcontext2->id]);

        $supervisor4 = \tool_murelation\local\uimode_teams::team_create((object)[
            'frameworkid' => $framework1->id,
            'teamname' => 'Team 4',
            'tenantid' => $tenant1->id,
        ]);
        $supervisor5 = \tool_murelation\local\uimode_teams::team_create((object)[
            'frameworkid' => $framework1->id,
            'teamname' => 'Team 5',
            'tenantid' => $tenant2->id,
        ]);

        $tuser1 = $this->getDataGenerator()->create_user(['tenantid' => $tenant1->id]);
        $tuser2 = $this->getDataGenerator()->create_user(['tenantid' => $tenant2->id]);

        $this->setUser($manager);

        $source4 = new members_add_cohort_cohortid((int)$supervisor4->id);
        $source5 = new members_add_cohort_cohortid((int)$supervisor5->id);

        $this->assertSame('No subordinates found', $source4->validate((string)$cohort1->id));

        cohort_add_member($cohort1->id, $tuser1->id);
        cohort_add_member($cohort1->id, $tuser2->id);
        cohort_add_member($cohort3->id, $tuser1->id);
        cohort_add_member($cohort4->id, $tuser2->id);

        $this->assertNotNull($source4->label((string)$cohort1->id));
        $this->assertNull($source4->validate((string)$cohort1->id));
        $this->assertNotNull($source5->label((string)$cohort1->id));
        $this->assertNull($source5->validate((string)$cohort1->id));

        $this->assertNotNull($source4->label((string)$cohort3->id));
        $this->assertNull($source4->validate((string)$cohort3->id));
        $this->assertNull($source5->label((string)$cohort3->id));
        $this->assertSame('No subordinates found', $source5->validate((string)$cohort3->id));
    }
}
