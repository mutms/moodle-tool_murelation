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

use tool_mulib\muform\element\autocomplete;
use tool_mulib\muform\element\buttons;
use tool_mulib\muform\element\cancel;
use tool_mulib\muform\element\checkbox;
use tool_mulib\muform\element\number;
use tool_mulib\muform\element\submit;
use tool_mulib\muform\element\text;
use tool_mulib\muform\form;
use tool_mulib\muform\validator\required_if_visible;
use tool_murelation\muform\autocomplete\team_update_userid;

/**
 * Update team.
 *
 * @package    tool_murelation
 * @copyright  2025 Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class team_update extends form {
    use details_trait;

    #[\Override]
    protected function definition(): void {
        $framework = $this->get_extra_data()['framework'];
        $supervisor = $this->get_extra_data()['supervisor'];

        $this->add_framework_details($framework);
        $this->add_tenant_details($supervisor->tenantid ? (int)$supervisor->tenantid : null);

        $teamname = new text('teamname', get_string('team_name', 'tool_murelation'), ['maxlength' => 254]);
        $teamname->set_required(true);
        $this->add($teamname);

        $this->add(new text('teamidnumber', get_string('team_idnumber', 'tool_murelation'), ['type' => 'rawtext', 'maxlength' => 100]));

        $source = new team_update_userid((int)$supervisor->id);
        $this->add(new autocomplete('userid', self::get_title($framework->supervisortitle), $source));

        $this->add(new checkbox('supmanaged', get_string('team_supmanaged', 'tool_murelation')));

        $this->add(new number('maxsubordinates', get_string('team_maxsubordinates', 'tool_murelation'), ['min' => 0, 'width' => 'small']));

        $hascohort = $this->get_extra_data()['hascohort'];
        $dm = $this->get_display_manager();
        $teamcohortname = new text('teamcohortname', get_string('team_cohort_name', 'tool_murelation'), ['maxlength' => 254]);
        if ($hascohort) {
            $teamcohortname->set_required(true);
        } else {
            $this->add(new checkbox('teamcohortcreate', get_string('team_cohort_create', 'tool_murelation')));
            $teamcohortname->set_required_marker(true);
            $teamcohortname->add_validator(new required_if_visible());
            $dm->hide_if('teamcohortname', 'teamcohortcreate', 'notchecked');
        }
        $this->add($teamcohortname);

        $this->add(new buttons('buttons'));
        $this->add(new submit('submit', get_string('team_update', 'tool_murelation')), 'buttons');
        $this->add(new cancel(), 'buttons');
    }

    #[\Override]
    protected function validation(array $data, array &$allerrors): void {
        global $DB;
        $idnumber = $data['teamidnumber'];
        if (trim($idnumber) !== $idnumber) {
            $allerrors['teamidnumber'][] = get_string('error');
        } else if ($idnumber !== '') {
            $select = "LOWER(teamidnumber) = LOWER(?) AND id <> ?";
            if ($DB->record_exists_select('tool_murelation_supervisor', $select, [$idnumber, $this->get_extra_data()['supervisor']->id])) {
                $allerrors['teamidnumber'][] = get_string('error');
            }
        }
    }
}
