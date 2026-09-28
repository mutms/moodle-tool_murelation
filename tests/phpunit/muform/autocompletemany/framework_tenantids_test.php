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

use tool_murelation\muform\autocompletemany\framework_tenantids;
use tool_mulib\local\mulib;

/**
 * Relation framework tenants autocomplete source tests.
 *
 * @group       MuTMS
 * @package     tool_murelation
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \tool_murelation\muform\autocompletemany\framework_tenantids
 */
final class framework_tenantids_test extends \advanced_testcase {
    #[\Override]
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        if (!mulib::is_mutenancy_available()) {
            $this->markTestSkipped('Multi-tenancy is not available');
        }
    }

    public function test_constructor(): void {
        $syscontext = \context_system::instance();

        $roleid = create_role('man', 'man', 'man');
        assign_capability('tool/murelation:manageframeworks', CAP_ALLOW, $roleid, $syscontext->id);

        $user0 = $this->getDataGenerator()->create_user();
        $manager = $this->getDataGenerator()->create_user();
        role_assign($roleid, $manager->id, $syscontext);

        $this->setUser($manager);
        $source = new framework_tenantids();
        $this->assertSame([], $source->get_args());

        $this->setUser($user0);
        try {
            new framework_tenantids();
            $this->fail('exception expected');
        } catch (\core\exception\moodle_exception $ex) {
            $this->assertInstanceOf(\required_capability_exception::class, $ex);
            $this->assertSame('Sorry, but you do not currently have permissions to do that (Manage user relation frameworks).', $ex->getMessage());
        }
    }

    public function test_search(): void {
        /** @var \tool_mutenancy_generator $tenantgenerator */
        $tenantgenerator = $this->getDataGenerator()->get_plugin_generator('tool_mutenancy');

        $syscontext = \context_system::instance();

        $roleid = create_role('man', 'man', 'man');
        assign_capability('tool/murelation:manageframeworks', CAP_ALLOW, $roleid, $syscontext->id);

        $manager = $this->getDataGenerator()->create_user();
        role_assign($roleid, $manager->id, $syscontext);

        $this->setUser($manager);
        $source = new framework_tenantids();

        // Multi-tenancy not active yet.
        $this->assertSame([], $source->search('', 50, []));

        \tool_mutenancy\local\tenancy::activate();

        $tenant1 = $tenantgenerator->create_tenant([
            'name' => 'First tenant',
            'idnumber' => 'ten1',
        ]);
        $tenant2 = $tenantgenerator->create_tenant([
            'name' => 'Second tenant',
            'idnumber' => 'ten2',
        ]);
        $tenant3 = $tenantgenerator->create_tenant([
            'name' => 'Third tenant',
            'idnumber' => 'ten3',
        ]);

        $this->assertSame([
            (int)$tenant1->id => $tenant1->name,
            (int)$tenant2->id => $tenant2->name,
            (int)$tenant3->id => $tenant3->name,
        ], $source->search('', 50, []));

        $this->assertSame([
            (int)$tenant2->id => $tenant2->name,
        ], $source->search('Second', 50, []));

        $this->assertSame([
            (int)$tenant2->id => $tenant2->name,
        ], $source->search('2', 50, []));

        $this->assertSame([
            (int)$tenant1->id => $tenant1->name,
            (int)$tenant3->id => $tenant3->name,
        ], $source->search('', 50, [(string)$tenant2->id]));

        $this->assertNull($source->search('', 2, []));
        $this->assertSame([
            (int)$tenant1->id => $tenant1->name,
            (int)$tenant3->id => $tenant3->name,
        ], $source->search('', 2, [(string)$tenant2->id]));

        \tool_mutenancy\local\tenant::archive($tenant3->id);
        $this->assertSame([
            (int)$tenant1->id => $tenant1->name,
            (int)$tenant2->id => $tenant2->name,
        ], $source->search('', 50, []));
    }

    public function test_labels(): void {
        /** @var \tool_mutenancy_generator $tenantgenerator */
        $tenantgenerator = $this->getDataGenerator()->get_plugin_generator('tool_mutenancy');

        $syscontext = \context_system::instance();

        $roleid = create_role('man', 'man', 'man');
        assign_capability('tool/murelation:manageframeworks', CAP_ALLOW, $roleid, $syscontext->id);

        $manager = $this->getDataGenerator()->create_user();
        role_assign($roleid, $manager->id, $syscontext);

        \tool_mutenancy\local\tenancy::activate();

        $tenant1 = $tenantgenerator->create_tenant([
            'name' => 'First tenant',
            'idnumber' => 'ten1',
        ]);
        $tenant2 = $tenantgenerator->create_tenant([
            'name' => 'Second tenant',
            'idnumber' => 'ten2',
        ]);
        $tenant3 = $tenantgenerator->create_tenant([
            'name' => 'Third tenant',
            'idnumber' => 'ten3',
        ]);

        $this->setUser($manager);
        $source = new framework_tenantids();

        $this->assertSame([
            (int)$tenant1->id => $tenant1->name,
            (int)$tenant2->id => $tenant2->name,
            (int)$tenant3->id => $tenant3->name,
        ], $source->labels([(string)$tenant3->id, (string)$tenant1->id, (string)$tenant2->id]));
        $this->assertSame([], $source->labels(['-10']));
        $this->assertSame([], $source->labels(['999999', 'abc', '']));
        $this->assertSame([
            (int)$tenant2->id => $tenant2->name,
        ], $source->labels(['-10', (string)$tenant2->id]));
        $this->assertSame([], $source->labels([]));
        $this->assertSame([], $source->validate([(string)$tenant1->id, '-10']));

        \tool_mutenancy\local\tenancy::deactivate();
        $this->assertSame([], $source->labels([(string)$tenant1->id]));
    }
}
