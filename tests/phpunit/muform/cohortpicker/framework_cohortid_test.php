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

namespace tool_murelation\phpunit\muform\cohortpicker;

use tool_murelation\muform\cohortpicker\framework_cohortid;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Relation framework cohort picker source tests.
 *
 * @package     tool_murelation
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\tool_murelation\muform\cohortpicker\framework_cohortid::class)]
#[Group('MuTMS')]
final class framework_cohortid_test extends \advanced_testcase {
    #[\Override]
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Cohorts offered by the source.
     *
     * @param \tool_mulib\muform\cohortpicker\base $source
     * @param string $query
     * @param string[] $exclude
     * @return string[] cohort names indexed with cohort ids, in the order of names
     */
    private function search(\tool_mulib\muform\cohortpicker\base $source, string $query, array $exclude = []): array {
        return array_column($source->search($query, $exclude)['cohorts'], 'name', 'id');
    }

    /**
     * Values the source does not accept.
     *
     * @param \tool_mulib\muform\cohortpicker\base $source
     * @param string[] $values
     * @return string[]
     */
    private function refused(\tool_mulib\muform\cohortpicker\base $source, array $values): array {
        return array_values(array_diff($values, array_map('strval', array_keys($source->labels($values)))));
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
        ], $this->search($source, ''));

        $this->assertSame([
            (int)$cohort1->id => $cohort1->name,
        ], $this->search($source, 'First'));

        $this->assertSame([
            (int)$cohort2->id => $cohort2->name,
        ], $this->search($source, 'koh2'));

        $this->assertSame([
            (int)$cohort1->id => $cohort1->name,
            (int)$cohort2->id => $cohort2->name,
        ], $this->search($source, ''));

        assign_capability('moodle/cohort:view', CAP_ALLOW, $roleid, $syscontext->id);
        $this->setUser($manager);

        $this->assertSame([
            (int)$cohort1->id => $cohort1->name,
            (int)$cohort2->id => $cohort2->name,
            (int)$cohort3->id => $cohort3->name,
        ], $this->search($source, ''));
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
        $value = (string)$cohort1->id;
        $this->assertSame($cohort1->name, $source->labels([$value])[$value] ?? null);
        $value = (string)$cohort2->id;
        $this->assertSame($cohort2->name, $source->labels([$value])[$value] ?? null);
        $value = (string)$cohort3->id;
        $this->assertNull($source->labels([$value])[$value] ?? null);
        $this->assertNull($source->labels(['-10'])['-10'] ?? null);
        $this->assertNull($source->labels(['999999'])['999999'] ?? null);
        $this->assertNull($source->labels(['abc'])['abc'] ?? null);
        $this->assertNull($source->labels([''])[''] ?? null);
        $value = (string)$cohort1->id;
        $this->assertSame([], $this->refused($source, [$value]));

        // Current value is always accepted.
        $source = new framework_cohortid((int)$cohort3->id);
        $value = (string)$cohort3->id;
        $this->assertSame($cohort3->name, $source->labels([$value])[$value] ?? null);
        $value = (string)$cohort1->id;
        $this->assertSame($cohort1->name, $source->labels([$value])[$value] ?? null);

        assign_capability('moodle/cohort:view', CAP_ALLOW, $roleid, $syscontext->id);
        $this->setUser($manager);
        $source = new framework_cohortid(0);
        $value = (string)$cohort3->id;
        $this->assertSame($cohort3->name, $source->labels([$value])[$value] ?? null);
    }
}
