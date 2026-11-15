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

namespace tool_murelation\phpunit\muform\userpicker;

use tool_murelation\muform\userpicker\team_update_userid;
use tool_murelation\local\framework;
use tool_mulib\local\mulib;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Supervisor of an existing team users picker source tests.
 *
 * @package     tool_murelation
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\tool_murelation\muform\userpicker\team_update_userid::class)]
#[Group('MuTMS')]
final class team_update_userid_test extends \advanced_testcase {
    #[\Override]
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Users on the first page of the panel.
     *
     * @param \tool_mulib\muform\userpicker\base $source
     * @param string $query
     * @param string[] $exclude
     * @return array user id => label html
     */
    private function search(\tool_mulib\muform\userpicker\base $source, string $query, array $exclude = []): array {
        $result = $source->search($query, $exclude, 0);
        return array_combine(array_column($result['rows'], 'id'), array_column($result['rows'], 'label'));
    }

    public function test_search(): void {
        global $DB;
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
            'supervisorcohortid' => $cohort->id,
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

        $this->setUser($manager);

        $supervisor0 = \tool_murelation\local\uimode_supervisors::supervisor_edit((object)[
            'frameworkid' => $framework0->id,
            'userid' => $user0->id,
            'subuserid' => $user1->id,
        ]);
        $supervisor1 = \tool_murelation\local\uimode_teams::team_create((object)[
            'frameworkid' => $framework1->id,
            'teamname' => 'Team 1',
        ]);
        $supervisor2 = \tool_murelation\local\uimode_teams::team_create((object)[
            'frameworkid' => $framework2->id,
            'teamname' => 'Team 2',
        ]);

        $source = new team_update_userid((int)$supervisor1->id);
        $this->assertSame([(int)$supervisor1->id], $source->get_args());
        $result = $this->search($source, '');
        $this->assertSame([(int)$manager->id, (int)$admin->id, (int)$user1->id, (int)$user0->id, (int)$user2->id], array_keys($result));
        $this->assertStringContainsString('First User', $result[$user1->id]);
        $this->assertStringContainsString('user1@example.com', $result[$user1->id]);

        $result = $this->search($source, 'irst');
        $this->assertSame([(int)$user1->id], array_keys($result));

        $this->assertGreaterThan(4, $source->search('', [], 0)['total']);
        $this->assertCount(5, $this->search($source, ''));

        $source = new team_update_userid((int)$supervisor2->id);
        $result = $this->search($source, '');
        $this->assertSame([(int)$user1->id, (int)$user2->id], array_keys($result));

        $result = $this->search($source, 'user2@');
        $this->assertSame([(int)$user2->id], array_keys($result));

        // Current team supervisor is always a candidate.
        $DB->set_field('tool_murelation_supervisor', 'userid', $user0->id, ['id' => $supervisor2->id]);
        $source = new team_update_userid((int)$supervisor2->id);
        $result = $this->search($source, '');
        $this->assertSame([(int)$user1->id, (int)$user0->id, (int)$user2->id], array_keys($result));

        try {
            new team_update_userid((int)$supervisor0->id);
            $this->fail('exception expected');
        } catch (\core\exception\moodle_exception $ex) {
            $this->assertInstanceOf(\invalid_parameter_exception::class, $ex);
            $this->assertSame('Invalid parameter value detected (Framework is not compatible with Teams mode)', $ex->getMessage());
        }

        $this->setUser($user1);
        try {
            new team_update_userid((int)$supervisor1->id);
            $this->fail('exception expected');
        } catch (\core\exception\moodle_exception $ex) {
            $this->assertInstanceOf(\invalid_parameter_exception::class, $ex);
            $this->assertSame('Invalid parameter value detected (Cannot update team)', $ex->getMessage());
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

        $source = new team_update_userid((int)$supervisor1->id);
        $result = $this->search($source, '');
        $this->assertSame([(int)$manager->id, (int)$admin->id, (int)$user1->id, (int)$user0->id, (int)$user2->id], array_keys($result));

        $DB->set_field('tool_murelation_supervisor', 'tenantid', $tenant1->id, ['id' => $supervisor1->id]);
        $source = new team_update_userid((int)$supervisor1->id);
        $this->assertSame([], $this->search($source, ''));

        $user1 = \tool_mutenancy\local\user::allocate($user1->id, $tenant1->id);
        $user2 = \tool_mutenancy\local\user::allocate($user2->id, $tenant2->id);
        cohort_add_member($cohort1->id, $user0->id);

        $result = $this->search($source, '');
        $this->assertSame([(int)$user1->id, (int)$user0->id], array_keys($result));

        $DB->set_field('tool_murelation_supervisor', 'tenantid', $tenant2->id, ['id' => $supervisor1->id]);
        $source = new team_update_userid((int)$supervisor1->id);
        $result = $this->search($source, '');
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
        $supervisor1 = \tool_murelation\local\uimode_teams::team_create((object)[
            'frameworkid' => $framework1->id,
            'teamname' => 'Team 1',
        ]);

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
        $source = new team_update_userid((int)$supervisor1->id);
        $value = (string)$user1->id;
        $result = $source->labels([$value])[$value] ?? null;
        $this->assertStringContainsString('First User', $result);
        $this->assertStringNotContainsString($user1->email, $result);

        $this->setUser($manager);
        $source = new team_update_userid((int)$supervisor1->id);
        $value = (string)$user1->id;
        $result = $source->labels([$value])[$value] ?? null;
        $this->assertStringContainsString('First User', $result);
        $this->assertStringContainsString($user1->email, $result);
    }

    public function test_label(): void {
        global $DB;
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
            'supervisorcohortid' => $cohort->id,
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
        $deleted = $this->getDataGenerator()->create_user();
        delete_user($deleted);

        $supervisor1 = \tool_murelation\local\uimode_teams::team_create((object)[
            'frameworkid' => $framework1->id,
            'teamname' => 'Team 1',
        ]);
        $supervisor2 = \tool_murelation\local\uimode_teams::team_create((object)[
            'frameworkid' => $framework2->id,
            'teamname' => 'Team 2',
        ]);

        $this->setUser($manager);

        $source = new team_update_userid((int)$supervisor2->id);
        $value = (string)$user1->id;
        $this->assertStringContainsString('First User', $source->labels([$value])[$value] ?? null);
        $value = (string)$user2->id;
        $this->assertStringContainsString('Second User', $source->labels([$value])[$value] ?? null);
        $value = (string)$user0->id;
        $this->assertNull($source->labels([$value])[$value] ?? null);
        $value = (string)$deleted->id;
        $this->assertNull($source->labels([$value])[$value] ?? null);
        $this->assertNull($source->labels(['0'])['0'] ?? null);
        $this->assertNull($source->labels(['-10'])['-10'] ?? null);
        $this->assertNull($source->labels(['999999'])['999999'] ?? null);
        $this->assertNull($source->labels(['abc'])['abc'] ?? null);
        $this->assertNull($source->labels([''])[''] ?? null);
        $value = (string)$user1->id;
        $this->assertNull($source->validate([$value])[$value] ?? null);

        // Current team supervisor is always accepted.
        $DB->set_field('tool_murelation_supervisor', 'userid', $user0->id, ['id' => $supervisor2->id]);
        $source = new team_update_userid((int)$supervisor2->id);
        $value = (string)$user0->id;
        $this->assertNotNull($source->labels([$value])[$value] ?? null);
        $DB->set_field('tool_murelation_supervisor', 'userid', null, ['id' => $supervisor2->id]);

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

        $source = new team_update_userid((int)$supervisor2->id);
        $value = (string)$user1->id;
        $this->assertNotNull($source->labels([$value])[$value] ?? null);
        $value = (string)$user2->id;
        $this->assertNotNull($source->labels([$value])[$value] ?? null);

        $DB->set_field('tool_murelation_supervisor', 'tenantid', $tenant1->id, ['id' => $supervisor2->id]);
        $source = new team_update_userid((int)$supervisor2->id);
        $value = (string)$user1->id;
        $this->assertNotNull($source->labels([$value])[$value] ?? null);
        $value = (string)$user2->id;
        $this->assertNull($source->labels([$value])[$value] ?? null);

        // Current supervisor moved to another tenant is still accepted, the team can be saved unchanged.
        $DB->set_field('tool_murelation_supervisor', 'userid', $user2->id, ['id' => $supervisor2->id]);
        $source = new team_update_userid((int)$supervisor2->id);
        $value = (string)$user2->id;
        $this->assertStringContainsString(fullname($user2), $source->labels([$value])[$value] ?? null);
    }

    public function test_validate(): void {
        /** @var \tool_murelation_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_murelation');

        $framework1 = $generator->create_framework(['uimode' => framework::UIMODE_TEAMS]);
        $supervisor1 = \tool_murelation\local\uimode_teams::team_create((object)[
            'frameworkid' => $framework1->id,
            'teamname' => 'Team 1',
        ]);

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user(['suspended' => 1]);

        $this->setAdminUser();
        $source = new team_update_userid((int)$supervisor1->id);
        $value = (string)$user1->id;
        $this->assertNotNull($source->labels([$value])[$value] ?? null);
        $value = (string)$user2->id;
        $this->assertNotNull($source->labels([$value])[$value] ?? null);
        $value = (string)$user1->id;
        $this->assertNull($source->validate([$value])[$value] ?? null);
        // Suspended users are allowed, the same as in 4.5.
        $value = (string)$user2->id;
        $this->assertNull($source->validate([$value])[$value] ?? null);
    }
}
