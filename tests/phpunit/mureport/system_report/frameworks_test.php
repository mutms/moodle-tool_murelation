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
 * User relation frameworks report tests.
 *
 * @package     tool_murelation
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\tool_murelation\mureport\system_report\frameworks::class)]
#[CoversClass(\tool_murelation\mureport\entity\framework::class)]
#[Group('MuTMS')]
final class frameworks_test extends \advanced_testcase {
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

    public function test_rows(): void {
        /** @var \tool_murelation_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_murelation');
        $framework1 = $generator->create_framework(['name' => 'Alpha', 'idnumber' => 'FA', 'uimode' => 'supervisors',
            'supervisortitle' => 'Manager']);
        $generator->create_framework(['name' => 'Beta', 'idnumber' => 'FB', 'uimode' => 'teams']);

        $this->setAdminUser();
        $report = \tool_murelation\mureport\system_report\frameworks::make_instance();
        $rows = $this->get_rows($report);
        $this->assertSame(['Alpha', 'Beta'], $this->get_texts($rows, 'framework:name'));
        $this->assertStringContainsString('/admin/tool/murelation/management/framework.php?id=' . $framework1->id, $rows[0]['cells']['framework:name']);
        $this->assertSame(['Supervisors', 'Teams'], $this->get_texts($rows, 'framework:uimode'));
        $this->assertSame('Manager', $rows[0]['cells']['framework:supervisortitle']);
        $rows = $this->get_rows($report, new request(['framework:uimode' => ['values' => [(string)framework::UIMODE_TEAMS]]]));
        $this->assertSame(['Beta'], $this->get_texts($rows, 'framework:name'));
        $rows = $this->get_rows($report, new request([], null, 0, null, 'fa'));
        $this->assertSame(['Alpha'], $this->get_texts($rows, 'framework:name'));

        $this->setUser($this->getDataGenerator()->create_user());
        $this->expectException(\core\exception\required_capability_exception::class);
        $this->get_rows(\tool_murelation\mureport\system_report\frameworks::make_instance());
    }
}
