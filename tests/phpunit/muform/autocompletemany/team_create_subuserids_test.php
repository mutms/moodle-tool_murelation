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

namespace tool_murelation\phpunit\muform\autocompletemany;

use tool_murelation\muform\autocompletemany\team_create_subuserids;
use tool_murelation\local\framework;
use tool_mulib\local\mulib;

/**
 * Members of a new team autocomplete source tests.
 *
 * @group       MuTMS
 * @package     tool_murelation
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_murelation\muform\autocompletemany\team_create_subuserids
 */
final class team_create_subuserids_test extends \advanced_testcase {
    #[\Override]
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    public function test_search(): void {
        /** @var \tool_murelation_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_murelation');

        $syscontext = \context_system::instance();

        $roleid = create_role('man', 'man', 'man');
        assign_capability('tool/murelation:viewpositions', CAP_ALLOW, $roleid, $syscontext->id);
        assign_capability('tool/murelation:managepositions', CAP_ALLOW, $roleid, $syscontext->id);
        assign_capability('moodle/site:viewuseridentity', CAP_ALLOW, $roleid, $syscontext->id);

        $cohort = $this->getDataGenerator()->create_cohort();

        $framework0 = $generator->create_framework(['uimode' => framework::UIMODE_SUPERVISORS]);
        $framework1 = $generator->create_framework([
            'uimode' => framework::UIMODE_TEAMS,
        ]);
        $framework2 = $generator->create_framework([
            'uimode' => framework::UIMODE_TEAMS,
            'subordinatecohortid' => $cohort->id,
        ]);

        $admin = get_admin();
        $manager = $this->getDataGenerator()->create_user([
            'firstname' => 'Global',
            'lastname' => 'Manager',
            'email' => 'manager@example.com',
        ]);
        role_assign($roleid, $manager->id, $syscontext);
        $user0 = $this->getDataGenerator()->create_user([
            'firstname' => 'Global',
            'lastname' => 'User',
            'email' => 'user0@example.com',
        ]);
        $user1 = $this->getDataGenerator()->create_user([
            'firstname' => 'First',
            'lastname' => 'User',
            'email' => 'user1@example.com',
        ]);
        cohort_add_member($cohort->id, $user1->id);
        $user2 = $this->getDataGenerator()->create_user([
            'firstname' => 'Second',
            'lastname' => 'User',
            'email' => 'user2@example.com',
        ]);
        cohort_add_member($cohort->id, $user2->id);

        $user3 = $this->getDataGenerator()->create_user([
            'firstname' => 'Second',
            'lastname' => 'User',
            'email' => 'user2@example.com',
        ]);
        cohort_add_member($cohort->id, $user3->id);

        $supervisor3 = \tool_murelation\local\uimode_teams::team_create((object)[
            'frameworkid' => $framework1->id,
            'teamname' => 'Team 3',
            'subuserids' => [$user3->id],
        ]);

        $this->setUser($manager);

        $source = new team_create_subuserids((int)$framework1->id, 0);
        $this->assertSame([(int)$framework1->id, 0], $source->get_args());
        $result = $source->search('', 50, []);
        $this->assertSame([(int)$manager->id, (int)$admin->id, (int)$user1->id, (int)$user0->id, (int)$user2->id], array_keys($result));
        $this->assertStringContainsString('First User', $result[$user1->id]);
        $this->assertStringContainsString('user1@example.com', $result[$user1->id]);

        $result = $source->search('irst', 50, []);
        $this->assertSame([(int)$user1->id], array_keys($result));

        $result = $source->search('', 50, [(string)$manager->id, (string)$user0->id]);
        $this->assertSame([(int)$admin->id, (int)$user1->id, (int)$user2->id], array_keys($result));

        $this->assertNull($source->search('', 4, []));
        $this->assertCount(4, $source->search('', 4, [(string)$admin->id]));

        $source = new team_create_subuserids((int)$framework2->id, 0);
        $result = $source->search('', 50, []);
        $this->assertSame([(int)$user1->id, (int)$user2->id, (int)$user3->id], array_keys($result));

        $result = $source->search('user2@', 50, []);
        $this->assertSame([(int)$user2->id, (int)$user3->id], array_keys($result));

        try {
            new team_create_subuserids((int)$framework0->id, 0);
            $this->fail('exception expected');
        } catch (\core\exception\moodle_exception $ex) {
            $this->assertInstanceOf(\invalid_parameter_exception::class, $ex);
            $this->assertSame('Invalid parameter value detected (Framework is not compatible with Teams mode)', $ex->getMessage());
        }

        $this->setUser($user1);
        try {
            new team_create_subuserids((int)$framework1->id, 0);
            $this->fail('exception expected');
        } catch (\core\exception\moodle_exception $ex) {
            $this->assertInstanceOf(\invalid_parameter_exception::class, $ex);
            $this->assertSame('Invalid parameter value detected (Cannot create team)', $ex->getMessage());
        }

        if (!mulib::is_mutenancy_available()) {
            return;
        }

        /** @var \tool_mutenancy_generator $tenantgenerator */
        $tenantgenerator = $this->getDataGenerator()->get_plugin_generator('tool_mutenancy');
        \tool_mutenancy\local\tenancy::activate();

        $cohort1 = $this->getDataGenerator()->create_cohort();
        $cohort2 = $this->getDataGenerator()->create_cohort();

        $tenant1 = $tenantgenerator->create_tenant(['assoccohortid' => $cohort1->id]);
        $tenant2 = $tenantgenerator->create_tenant(['assoccohortid' => $cohort2->id]);

        $this->setUser($manager);

        $source = new team_create_subuserids((int)$framework1->id, 0);
        $result = $source->search('', 50, []);
        $this->assertSame([(int)$manager->id, (int)$admin->id, (int)$user1->id, (int)$user0->id, (int)$user2->id], array_keys($result));

        $source = new team_create_subuserids((int)$framework1->id, (int)$tenant1->id);
        $this->assertSame([(int)$framework1->id, (int)$tenant1->id], $source->get_args());
        $this->assertSame([], $source->search('', 50, []));

        $user1 = \tool_mutenancy\local\user::allocate($user1->id, $tenant1->id);
        $user2 = \tool_mutenancy\local\user::allocate($user2->id, $tenant2->id);
        cohort_add_member($cohort1->id, $user0->id);

        $source = new team_create_subuserids((int)$framework1->id, 0);
        $result = $source->search('', 50, []);
        $this->assertSame([(int)$manager->id, (int)$admin->id, (int)$user1->id, (int)$user0->id, (int)$user2->id], array_keys($result));

        $source = new team_create_subuserids((int)$framework1->id, (int)$tenant1->id);
        $result = $source->search('', 50, []);
        $this->assertSame([(int)$user1->id, (int)$user0->id], array_keys($result));

        $source = new team_create_subuserids((int)$framework1->id, (int)$tenant2->id);
        $result = $source->search('', 50, []);
        $this->assertSame([(int)$user2->id], array_keys($result));
    }

    public function test_label_identity(): void {
        /** @var \tool_murelation_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_murelation');

        $syscontext = \context_system::instance();

        $roleid = create_role('man', 'man', 'man');
        assign_capability('tool/murelation:viewpositions', CAP_ALLOW, $roleid, $syscontext->id);
        assign_capability('tool/murelation:managepositions', CAP_ALLOW, $roleid, $syscontext->id);
        $identityroleid = create_role('ident', 'ident', 'ident');
        assign_capability('moodle/site:viewuseridentity', CAP_ALLOW, $identityroleid, $syscontext->id);

        $framework1 = $generator->create_framework(['uimode' => framework::UIMODE_TEAMS]);

        $manager = $this->getDataGenerator()->create_user();
        role_assign($roleid, $manager->id, $syscontext);
        role_assign($identityroleid, $manager->id, $syscontext);
        $user = $this->getDataGenerator()->create_user();
        role_assign($roleid, $user->id, $syscontext);

        $user1 = $this->getDataGenerator()->create_user([
            'firstname' => 'First',
            'lastname' => 'User',
            'email' => 'user1@example.com',
        ]);

        $this->setUser($user);
        $source = new team_create_subuserids((int)$framework1->id, 0);
        $result = $source->labels([(string)$user1->id])[$user1->id];
        $this->assertStringContainsString('First User', $result);
        $this->assertStringNotContainsString($user1->email, $result);

        $this->setUser($manager);
        $source = new team_create_subuserids((int)$framework1->id, 0);
        $result = $source->labels([(string)$user1->id])[$user1->id];
        $this->assertStringContainsString('First User', $result);
        $this->assertStringContainsString($user1->email, $result);
    }

    public function test_labels(): void {
        /** @var \tool_murelation_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_murelation');

        $syscontext = \context_system::instance();

        $roleid = create_role('man', 'man', 'man');
        assign_capability('tool/murelation:viewpositions', CAP_ALLOW, $roleid, $syscontext->id);
        assign_capability('tool/murelation:managepositions', CAP_ALLOW, $roleid, $syscontext->id);
        assign_capability('moodle/site:viewuseridentity', CAP_ALLOW, $roleid, $syscontext->id);

        $cohort = $this->getDataGenerator()->create_cohort();

        $framework0 = $generator->create_framework(['uimode' => framework::UIMODE_SUPERVISORS]);
        $framework1 = $generator->create_framework([
            'uimode' => framework::UIMODE_TEAMS,
        ]);
        $framework2 = $generator->create_framework([
            'uimode' => framework::UIMODE_TEAMS,
            'subordinatecohortid' => $cohort->id,
        ]);

        $manager = $this->getDataGenerator()->create_user([
            'firstname' => 'Global',
            'lastname' => 'Manager',
            'email' => 'manager@example.com',
        ]);
        role_assign($roleid, $manager->id, $syscontext);
        $user0 = $this->getDataGenerator()->create_user([
            'firstname' => 'Global',
            'lastname' => 'User',
            'email' => 'user0@example.com',
        ]);
        $user1 = $this->getDataGenerator()->create_user([
            'firstname' => 'First',
            'lastname' => 'User',
            'email' => 'user1@example.com',
        ]);
        cohort_add_member($cohort->id, $user1->id);
        $user2 = $this->getDataGenerator()->create_user([
            'firstname' => 'Second',
            'lastname' => 'User',
            'email' => 'user2@example.com',
        ]);
        cohort_add_member($cohort->id, $user2->id);
        $user3 = $this->getDataGenerator()->create_user();
        $deleted = $this->getDataGenerator()->create_user();
        delete_user($deleted);

        \tool_murelation\local\uimode_teams::team_create((object)[
            'frameworkid' => $framework1->id,
            'teamname' => 'Team 3',
            'subuserids' => [$user3->id],
        ]);

        $this->setUser($manager);

        $source2 = new team_create_subuserids((int)$framework2->id, 0);
        $source1 = new team_create_subuserids((int)$framework1->id, 0);

        $this->assertSame([(int)$user1->id, (int)$user2->id], array_keys($source2->labels([(string)$user0->id, (string)$user1->id, (string)$user2->id])));
        $this->assertSame([(int)$user0->id], array_keys($source1->labels([(string)$user0->id, (string)$user3->id])));
        $this->assertSame([], $source1->labels([(string)$deleted->id, '-10', 'abc', '', '999999']));
        $this->assertSame([], $source1->validate([(string)$user0->id, (string)$user1->id]));

        if (!mulib::is_mutenancy_available()) {
            return;
        }

        /** @var \tool_mutenancy_generator $tenantgenerator */
        $tenantgenerator = $this->getDataGenerator()->get_plugin_generator('tool_mutenancy');
        \tool_mutenancy\local\tenancy::activate();

        $cohort1 = $this->getDataGenerator()->create_cohort();
        $cohort2 = $this->getDataGenerator()->create_cohort();

        $tenant1 = $tenantgenerator->create_tenant(['assoccohortid' => $cohort1->id]);
        $tenant2 = $tenantgenerator->create_tenant(['assoccohortid' => $cohort2->id]);

        $this->setUser($manager);

        $user1 = \tool_mutenancy\local\user::allocate($user1->id, $tenant1->id);
        $user2 = \tool_mutenancy\local\user::allocate($user2->id, $tenant2->id);
        cohort_add_member($cohort1->id, $user0->id);

        $source = new team_create_subuserids((int)$framework2->id, 0);
        $this->assertSame([(int)$user1->id, (int)$user2->id], array_keys($source->labels([(string)$user1->id, (string)$user2->id])));

        $source = new team_create_subuserids((int)$framework2->id, (int)$tenant1->id);
        $this->assertSame([(int)$user1->id], array_keys($source->labels([(string)$user1->id, (string)$user2->id])));
    }

    public function test_validate(): void {
        /** @var \tool_murelation_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_murelation');

        $framework1 = $generator->create_framework(['uimode' => framework::UIMODE_TEAMS]);

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user(['suspended' => 1]);

        $this->setAdminUser();
        $source = new team_create_subuserids((int)$framework1->id, 0);
        $this->assertSame([(int)$user1->id, (int)$user2->id], array_keys($source->labels([(string)$user1->id, (string)$user2->id])));
        $this->assertSame([(int)$user2->id => 'Suspended user'], $source->validate([(string)$user1->id, (string)$user2->id]));
    }
}
