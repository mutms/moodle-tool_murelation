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

namespace tool_murelation\local\form;

use stdClass;
use tool_mulib\muform\element\info;

/**
 * Framework, team and user details shown in relation forms.
 *
 * @package     tool_murelation
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait details_trait {
    /**
     * Framework name and idnumber.
     *
     * @param stdClass $framework
     */
    protected function add_framework_details(stdClass $framework): void {
        $this->add(new info('fwname', get_string('framework_name', 'tool_murelation'), $framework->name));
        if ($framework->idnumber !== null) {
            $this->add(new info('fwidnumber', get_string('framework_idnumber', 'tool_murelation'), $framework->idnumber, info::PLAIN));
        }
    }

    /**
     * Tenant name with multi-tenancy.
     *
     * @param int|null $tenantid
     */
    protected function add_tenant_details(?int $tenantid): void {
        if ($tenantid && \tool_mulib\local\mulib::is_mutenancy_active()) {
            $tenant = \tool_mutenancy\local\tenant::fetch($tenantid);
            $this->add(new info('tenant', get_string('tenant', 'tool_mutenancy'), $tenant->name));
        }
    }

    /**
     * Team tenant, supervisor, name and idnumber.
     *
     * @param stdClass $framework
     * @param stdClass $supervisor team (supervisor) record
     */
    protected function add_team_details(stdClass $framework, stdClass $supervisor): void {
        $this->add_tenant_details($supervisor->tenantid ? (int)$supervisor->tenantid : null);
        $this->add_user_details('supuser', self::get_title($framework->supervisortitle), $supervisor->userid ? (int)$supervisor->userid : null);
        if ($supervisor->teamname !== null) {
            $this->add(new info('teamname', get_string('team_name', 'tool_murelation'), $supervisor->teamname, info::PLAIN));
        }
        if ($supervisor->teamidnumber !== null) {
            $this->add(new info('teamidnumber', get_string('team_idnumber', 'tool_murelation'), $supervisor->teamidnumber, info::PLAIN));
        }
    }

    /**
     * User full name.
     *
     * @param string $name element name
     * @param string $label
     * @param int|null $userid null means not set
     */
    protected function add_user_details(string $name, string $label, ?int $userid): void {
        global $DB;
        if ($userid) {
            $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0]);
            $fullname = $user ? fullname($user) : get_string('error');
        } else {
            $fullname = get_string('notset', 'tool_mulib');
        }
        $this->add(new info($name, $label, $fullname, info::PLAIN));
    }

    /**
     * Supervisor or subordinate title.
     *
     * @param string $title
     * @return string
     */
    protected static function get_title(string $title): string {
        return format_string($title);
    }
}
