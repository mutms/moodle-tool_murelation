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
 * Teams mode reports tests.
 *
 * @package     tool_murelation
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\tool_murelation\mureport\system_report\framework_teams::class)]
#[CoversClass(\tool_murelation\mureport\system_report\framework_members::class)]
#[CoversClass(\tool_murelation\mureport\system_report\team_members::class)]
#[CoversClass(\tool_murelation\mureport\system_report\user_teams::class)]
#[CoversClass(\tool_murelation\mureport\entity\supervisor::class)]
#[CoversClass(\tool_murelation\mureport\entity\subordinate::class)]
#[Group('MuTMS')]
final class teams_test extends \advanced_testcase {
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
        global $DB;

        /** @var \tool_murelation_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_murelation');
        $framework = $generator->create_framework(['name' => 'Teams', 'uimode' => 'teams', 'supervisortitle' => 'Leader',
            'subordinatetitle' => 'Member', 'subordinatestitle' => 'Members']);
        $other = $generator->create_framework(['name' => 'Other', 'uimode' => 'supervisors']);
        $leader = $this->getDataGenerator()->create_user(['firstname' => 'Lea', 'lastname' => 'Leader']);
        $user1 = $this->getDataGenerator()->create_user(['firstname' => 'Anna', 'lastname' => 'Alpha']);
        $user2 = $this->getDataGenerator()->create_user(['firstname' => 'Bob', 'lastname' => 'Beta']);
        $user3 = $this->getDataGenerator()->create_user(['firstname' => 'Cyril', 'lastname' => 'Gamma']);
        $team1 = $generator->create_team(['frameworkid' => $framework->id, 'teamname' => 'Team A', 'teamidnumber' => 'TA',
            'userid' => $leader->id, 'maxsubordinates' => 5]);
        $team2 = $generator->create_team(['frameworkid' => $framework->id, 'teamname' => 'Zeta']);
        $generator->create_team_member(['supervisorid' => $team1->id, 'userid' => $user1->id, 'teamposition' => 'Lead dev']);
        $generator->create_team_member(['supervisorid' => $team1->id, 'userid' => $user2->id]);
        $generator->create_team_member(['supervisorid' => $team2->id, 'userid' => $user3->id]);

        $this->setAdminUser();
        $report = \tool_murelation\mureport\system_report\framework_teams::make_instance((int)$framework->id);
        $rows = $this->get_rows($report);
        $this->assertSame(['Team A', 'Zeta'], $this->get_texts($rows, 'supervisor:teamname'));
        $this->assertStringContainsString('/admin/tool/murelation/management/team.php?id=' . $team1->id, $rows[0]['cells']['supervisor:teamname']);
        $this->assertSame(['2 / 5', '1'], $this->get_texts($rows, 'supervisor:subordinates'));
        $this->assertSame('Leader', $report->get_columns()['auser:fullname']->get_title());
        $this->assertSame('Members', $report->get_columns()['supervisor:subordinates']->get_title());
        $this->assertSame(['update', 'delete'], $this->get_actions($rows[0], 'supervisor:actions'));
        $rows = $this->get_rows($report, new request(['supervisor:vacant' => ['value' => 1]]));
        $this->assertSame(['Zeta'], $this->get_texts($rows, 'supervisor:teamname'));

        $report = \tool_murelation\mureport\system_report\framework_members::make_instance((int)$framework->id);
        $rows = $this->get_rows($report);
        $this->assertSame(['Anna Alpha', 'Bob Beta', 'Cyril Gamma'], $this->get_texts($rows, 'auser:fullname'));
        $this->assertSame('Lead dev', $rows[0]['cells']['subordinate:teamposition']);
        $this->assertSame('Leader', $report->get_columns()['supuser:fullname']->get_title());
        $this->assertSame(['update', 'delete'], $this->get_actions($rows[0], 'subordinate:actions'));
        $this->assertSame('Update Member', $rows[0]['actions']['subordinate:actions'][0]['title']);
        $rows = $this->get_rows($report, new request([], null, 0, null, 'zeta'));
        $this->assertSame(['Cyril Gamma'], $this->get_texts($rows, 'auser:fullname'));

        $report = \tool_murelation\mureport\system_report\team_members::make_instance((int)$team1->id);
        $rows = $this->get_rows($report);
        $this->assertSame(['Anna Alpha', 'Bob Beta'], $this->get_texts($rows, 'auser:fullname'));
        $this->assertSame(\context_system::instance()->id, $report->get_context()->id);

        $this->setUser($leader);
        $report = \tool_murelation\mureport\system_report\user_teams::make_instance((int)$leader->id);
        $rows = $this->get_rows($report);
        $this->assertSame(['Team A'], $this->get_texts($rows, 'supervisor:teamname'));
        $this->assertSame(['Teams'], $this->get_texts($rows, 'framework:name'));
        $DB->set_field('tool_murelation_framework', 'visibility', framework::VISIBILITY_HIDDEN, ['id' => $framework->id]);
        $this->assertSame([], $this->get_rows(\tool_murelation\mureport\system_report\user_teams::make_instance((int)$leader->id)));

        try {
            $this->get_rows(\tool_murelation\mureport\system_report\user_teams::make_instance((int)$user1->id));
            $this->fail('Exception expected');
        } catch (\core\exception\required_capability_exception $ex) {
            $this->assertInstanceOf(\core\exception\required_capability_exception::class, $ex);
        }
        try {
            $this->get_rows(\tool_murelation\mureport\system_report\framework_teams::make_instance((int)$framework->id));
            $this->fail('Exception expected');
        } catch (\core\exception\required_capability_exception $ex) {
            $this->assertInstanceOf(\core\exception\required_capability_exception::class, $ex);
        }
        $this->setAdminUser();
        $this->expectException(\core\exception\invalid_parameter_exception::class);
        \tool_murelation\mureport\system_report\framework_teams::make_instance((int)$other->id)->require_access();
    }
}
