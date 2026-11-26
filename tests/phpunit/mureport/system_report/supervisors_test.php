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

namespace tool_murelation\phpunit\mureport\system_report;

use tool_murelation\local\framework;
use tool_mureport\mureport\request;
use tool_mureport\mureport\system_report;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Supervisors mode reports tests.
 *
 * @package     tool_murelation
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\tool_murelation\mureport\system_report\framework_subordinates::class)]
#[CoversClass(\tool_murelation\mureport\system_report\user_subordinates::class)]
#[Group('MuTMS')]
final class supervisors_test extends \advanced_testcase {
    #[\Override]
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Returns exported rows.
     *
     * @param system_report $report
     * @param request $request
     * @return array
     */
    private function get_rows(system_report $report, request $request = new request([])): array {
        $report->require_access();
        return $report->get_query()->get_page($request)->export_rows();
    }

    /**
     * Returns plain texts of a column.
     *
     * @param array $rows
     * @param string $column
     * @return string[]
     */
    private function get_texts(array $rows, string $column): array {
        return array_map(fn(array $row): string => strip_tags($row['cells'][$column]), $rows);
    }

    /**
     * Returns action names of a row.
     *
     * @param array $row
     * @param string $column
     * @return string[]
     */
    private function get_actions(array $row, string $column): array {
        return array_column($row['actions'][$column] ?? [], 'name');
    }

    public function test_reports(): void {
        /** @var \tool_murelation_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_murelation');
        $framework = $generator->create_framework(['name' => 'Managers', 'idnumber' => 'MGR', 'uimode' => 'supervisors',
            'supervisortitle' => 'Manager', 'subordinatetitle' => 'Report']);
        $teams = $generator->create_framework(['name' => 'Teams', 'uimode' => 'teams']);
        $manager = $this->getDataGenerator()->create_user(['firstname' => 'Mia', 'lastname' => 'Manager']);
        $user1 = $this->getDataGenerator()->create_user(['firstname' => 'Anna', 'lastname' => 'Alpha']);
        $user2 = $this->getDataGenerator()->create_user(['firstname' => 'Bob', 'lastname' => 'Beta']);
        $generator->create_supervisor(['frameworkid' => $framework->id, 'subuserid' => $user1->id, 'userid' => $manager->id]);
        $generator->create_supervisor(['frameworkid' => $framework->id, 'subuserid' => $user2->id, 'userid' => $manager->id]);
        $generator->create_supervisor(['frameworkid' => $framework->id, 'subuserid' => $manager->id, 'userid' => $user1->id]);

        $this->setAdminUser();
        $report = \tool_murelation\mureport\system_report\framework_subordinates::make_instance((int)$framework->id);
        $rows = $this->get_rows($report);
        $this->assertSame(['Anna Alpha', 'Bob Beta', 'Mia Manager'], $this->get_texts($rows, 'auser:fullname'));
        $this->assertSame(['Mia Manager', 'Mia Manager', 'Anna Alpha'], $this->get_texts($rows, 'supuser:fullname'));
        $this->assertSame('Manager', $report->get_columns()['supuser:fullname']->get_title());
        $this->assertSame(['update', 'delete'], $this->get_actions($rows[0], 'subordinate:actions'));
        $this->assertSame('Update Manager', $rows[0]['actions']['subordinate:actions'][0]['title']);
        $this->assertStringContainsString('subuserid=' . $user1->id, $rows[0]['actions']['subordinate:actions'][0]['url']);
        $rows = $this->get_rows($report, new request([], null, 0, null, 'anna'));
        $this->assertSame(['Anna Alpha', 'Mia Manager'], $this->get_texts($rows, 'auser:fullname'));

        $report = \tool_murelation\mureport\system_report\user_subordinates::make_instance((int)$manager->id);
        $rows = $this->get_rows($report);
        $this->assertSame(['Anna Alpha', 'Bob Beta'], $this->get_texts($rows, 'auser:fullname'));
        $this->assertSame(['Managers', 'Managers'], $this->get_texts($rows, 'framework:name'));
        $this->assertSame('Report', $rows[0]['cells']['framework:subordinatetitle']);
        $this->assertSame(['update', 'delete'], $this->get_actions($rows[0], 'subordinate:actions'));

        $this->setUser($manager);
        $rows = $this->get_rows(\tool_murelation\mureport\system_report\user_subordinates::make_instance((int)$manager->id));
        $this->assertSame(['Anna Alpha', 'Bob Beta'], $this->get_texts($rows, 'auser:fullname'));
        $this->assertSame([], $this->get_actions($rows[0], 'subordinate:actions'));
        try {
            $this->get_rows(\tool_murelation\mureport\system_report\user_subordinates::make_instance((int)$user1->id));
            $this->fail('Exception expected');
        } catch (\core\exception\required_capability_exception $ex) {
            $this->assertInstanceOf(\core\exception\required_capability_exception::class, $ex);
        }

        $this->setAdminUser();
        $this->expectException(\core\exception\invalid_parameter_exception::class);
        \tool_murelation\mureport\system_report\framework_subordinates::make_instance((int)$teams->id)->require_access();
    }
}
