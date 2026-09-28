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

use tool_murelation\muform\autocomplete\framework_cohortid;

/**
 * Relation framework cohort autocomplete source tests.
 *
 * @group       MuTMS
 * @package     tool_murelation
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_murelation\muform\autocomplete\framework_cohortid
 */
final class framework_cohortid_test extends \advanced_testcase {
    #[\Override]
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    public function test_constructor(): void {
        $syscontext = \context_system::instance();

        $roleid = create_role('man', 'man', 'man');
        assign_capability('tool/murelation:manageframeworks', CAP_ALLOW, $roleid, $syscontext->id);

        $user0 = $this->getDataGenerator()->create_user();
        $manager = $this->getDataGenerator()->create_user();
        role_assign($roleid, $manager->id, $syscontext->id);

        $this->setUser($manager);
        $source = new framework_cohortid(0);
        $this->assertSame([0], $source->get_args());
        $source = new framework_cohortid(3);
        $this->assertSame([3], $source->get_args());

        $this->setUser($user0);
        try {
            new framework_cohortid(0);
            $this->fail('exception expected');
        } catch (\core\exception\moodle_exception $ex) {
            $this->assertInstanceOf(\required_capability_exception::class, $ex);
            $this->assertSame('Sorry, but you do not currently have permissions to do that (Manage user relation frameworks).', $ex->getMessage());
        }
    }

    public function test_search(): void {
        $syscontext = \context_system::instance();

        $roleid = create_role('man', 'man', 'man');
        assign_capability('tool/murelation:manageframeworks', CAP_ALLOW, $roleid, $syscontext->id);

        $manager = $this->getDataGenerator()->create_user([
            'firstname' => 'Global',
            'lastname' => 'Manager',
            'email' => 'manager@example.com',
        ]);
        role_assign($roleid, $manager->id, $syscontext->id);

        $cohort1 = $this->getDataGenerator()->create_cohort([
            'name' => 'First kohort',
            'idnumber' => 'koh1',
        ]);
        $cohort2 = $this->getDataGenerator()->create_cohort([
            'name' => 'Second kohort',
            'idnumber' => 'koh2',
        ]);
        $cohort3 = $this->getDataGenerator()->create_cohort([
            'name' => 'Third kohort',
            'idnumber' => 'koh3',
            'visible' => 0,
        ]);

        $this->setUser($manager);
        $source = new framework_cohortid(0);

        $this->assertSame([
            (int)$cohort1->id => $cohort1->name,
            (int)$cohort2->id => $cohort2->name,
        ], $source->search('', 50));

        $this->assertSame([
            (int)$cohort1->id => $cohort1->name,
        ], $source->search('First', 50));

        $this->assertSame([
            (int)$cohort2->id => $cohort2->name,
        ], $source->search('koh2', 50));

        $this->assertNull($source->search('', 1));
        $this->assertSame([
            (int)$cohort1->id => $cohort1->name,
            (int)$cohort2->id => $cohort2->name,
        ], $source->search('', 2));

        assign_capability('moodle/cohort:view', CAP_ALLOW, $roleid, $syscontext->id);
        $this->setUser($manager);

        $this->assertSame([
            (int)$cohort1->id => $cohort1->name,
            (int)$cohort2->id => $cohort2->name,
            (int)$cohort3->id => $cohort3->name,
        ], $source->search('', 50));
    }

    public function test_label(): void {
        $syscontext = \context_system::instance();

        $roleid = create_role('man', 'man', 'man');
        assign_capability('tool/murelation:manageframeworks', CAP_ALLOW, $roleid, $syscontext->id);

        $manager = $this->getDataGenerator()->create_user([
            'firstname' => 'Global',
            'lastname' => 'Manager',
            'email' => 'manager@example.com',
        ]);
        role_assign($roleid, $manager->id, $syscontext->id);

        $cohort1 = $this->getDataGenerator()->create_cohort([
            'name' => 'First kohort',
            'idnumber' => 'koh1',
        ]);
        $cohort2 = $this->getDataGenerator()->create_cohort([
            'name' => 'Second kohort',
            'idnumber' => 'koh2',
        ]);
        $cohort3 = $this->getDataGenerator()->create_cohort([
            'name' => 'Third kohort',
            'idnumber' => 'koh3',
            'visible' => 0,
        ]);

        $this->setUser($manager);

        $source = new framework_cohortid(0);
        $this->assertSame($cohort1->name, $source->label((string)$cohort1->id));
        $this->assertSame($cohort2->name, $source->label((string)$cohort2->id));
        $this->assertNull($source->label((string)$cohort3->id));
        $this->assertNull($source->label('-10'));
        $this->assertNull($source->label('999999'));
        $this->assertNull($source->label('abc'));
        $this->assertNull($source->label(''));
        $this->assertNull($source->validate((string)$cohort1->id));

        // Current value is always accepted.
        $source = new framework_cohortid((int)$cohort3->id);
        $this->assertSame($cohort3->name, $source->label((string)$cohort3->id));
        $this->assertSame($cohort1->name, $source->label((string)$cohort1->id));

        assign_capability('moodle/cohort:view', CAP_ALLOW, $roleid, $syscontext->id);
        $this->setUser($manager);
        $source = new framework_cohortid(0);
        $this->assertSame($cohort3->name, $source->label((string)$cohort3->id));
    }
}
