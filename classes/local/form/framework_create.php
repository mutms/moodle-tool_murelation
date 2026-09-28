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
use tool_mulib\muform\element\autocompletemany;
use tool_mulib\muform\element\buttons;
use tool_mulib\muform\element\cancel;
use tool_mulib\muform\element\checkbox;
use tool_mulib\muform\element\editor;
use tool_mulib\muform\element\radios;
use tool_mulib\muform\element\section;
use tool_mulib\muform\element\select;
use tool_mulib\muform\element\submit;
use tool_mulib\muform\element\text;
use tool_mulib\muform\form;
use tool_murelation\local\framework;
use tool_murelation\muform\autocomplete\framework_cohortid;
use tool_murelation\muform\autocompletemany\framework_tenantids;

/**
 * Create a new relation framework.
 *
 * @package    tool_murelation
 * @copyright  2025 Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class framework_create extends form {
    #[\Override]
    protected function definition(): void {
        $current = $this->get_current_data();

        $name = new text('name', get_string('framework_name', 'tool_murelation'), ['maxlength' => 254]);
        $name->set_required(true);
        $this->add($name);

        $this->add(new text('idnumber', get_string('framework_idnumber', 'tool_murelation'), ['type' => 'rawtext', 'maxlength' => 100]));

        $uimode = new radios('uimode', get_string('framework_uimode', 'tool_murelation'), [
            framework::UIMODE_SUPERVISORS => get_string('framework_uimode_supervisors', 'tool_murelation'),
            framework::UIMODE_TEAMS => get_string('framework_uimode_teams', 'tool_murelation'),
        ]);
        $uimode->set_required(true);
        $this->add($uimode);

        $this->add(new editor('description', get_string('description')));

        $this->add(new select('visibility', get_string('framework_visibility', 'tool_murelation'), array_map('strval', framework::get_visibility_options())));

        $source = new framework_cohortid((int)($current['managecohortid'] ?? 0));
        $this->add(new autocomplete('managecohortid', get_string('framework_managecohort', 'tool_murelation'), $source));

        if (\tool_mulib\local\mulib::is_mutenancy_active()) {
            $this->add(new checkbox('alltenants', get_string('framework_alltenants', 'tool_murelation')));
            $this->add(new autocompletemany('tenantids', get_string('tenants', 'tool_mutenancy'), new framework_tenantids()));
            $this->get_display_manager()->hide_if('tenantids', 'alltenants', 'checked');
        }

        $this->add(new section('supervisorheader', get_string('supervisor', 'tool_murelation')));

        $supervisortitle = new text('supervisortitle', get_string('supervisortitle', 'tool_murelation'), ['maxlength' => 254]);
        $supervisortitle->set_required(true);
        $this->add($supervisortitle, 'supervisorheader');

        $supervisorstitle = new text('supervisorstitle', get_string('supervisorstitle', 'tool_murelation'), ['maxlength' => 254]);
        $supervisorstitle->set_required(true);
        $this->add($supervisorstitle, 'supervisorheader');

        $source = new framework_cohortid((int)($current['supervisorcohortid'] ?? 0));
        $this->add(new autocomplete('supervisorcohortid', get_string('framework_supervisorcohort', 'tool_murelation'), $source), 'supervisorheader');

        $roles = framework::get_allowed_supervisor_roles(empty($current['supervisorroleid']) ? null : (int)$current['supervisorroleid']);
        if ($roles) {
            $roles = ['' => get_string('choosedots')] + array_map('strval', $roles);
            $this->add(new select('supervisorroleid', get_string('framework_supervisorrole', 'tool_murelation'), $roles), 'supervisorheader');
        }

        $this->add(new section('subordinateheader', get_string('subordinate', 'tool_murelation')));

        $subordinatetitle = new text('subordinatetitle', get_string('subordinatetitle', 'tool_murelation'), ['maxlength' => 254]);
        $subordinatetitle->set_required(true);
        $this->add($subordinatetitle, 'subordinateheader');

        $subordinatestitle = new text('subordinatestitle', get_string('subordinatestitle', 'tool_murelation'), ['maxlength' => 254]);
        $subordinatestitle->set_required(true);
        $this->add($subordinatestitle, 'subordinateheader');

        $source = new framework_cohortid((int)($current['subordinatecohortid'] ?? 0));
        $this->add(new autocomplete('subordinatecohortid', get_string('framework_subordinatecohort', 'tool_murelation'), $source), 'subordinateheader');

        $this->add(new buttons('buttons'));
        $this->add(new submit('submit', get_string('framework_create', 'tool_murelation')), 'buttons');
        $this->add(new cancel(), 'buttons');
    }

    #[\Override]
    protected function validation(array $data, array &$allerrors): void {
        global $DB;
        $idnumber = $data['idnumber'];
        if (trim($idnumber) !== $idnumber) {
            $allerrors['idnumber'][] = get_string('error');
        } else if ($idnumber !== '') {
            $select = "LOWER(idnumber) = LOWER(?) AND id <> ?";
            if ($DB->record_exists_select('tool_murelation_framework', $select, [$idnumber, 0])) {
                $allerrors['idnumber'][] = get_string('error');
            }
        }
    }
}
