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
use tool_mulib\muform\element\info;
use tool_mulib\muform\element\submit;
use tool_mulib\muform\element\text;
use tool_mulib\muform\form;
use tool_murelation\muform\autocomplete\members_add_cohort_cohortid;

/**
 * Add cohort members as new team members.
 *
 * @package    tool_murelation
 * @copyright  2026 Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class members_add_cohort extends form {
    use details_trait;

    #[\Override]
    protected function definition(): void {
        global $DB;
        $framework = $this->get_extra_data()['framework'];
        $supervisor = $this->get_extra_data()['supervisor'];

        $this->add_framework_details($framework);
        $this->add_team_details($framework, $supervisor);

        if ($supervisor->maxsubordinates) {
            $current = $DB->count_records('tool_murelation_subordinate', ['supervisorid' => $supervisor->id]);
            $max = $current . ' / ' . $supervisor->maxsubordinates;
            $this->add(new info('maxsubordinates', get_string('team_maxsubordinates', 'tool_murelation'), $max, info::PLAIN));
        }

        $this->add(new text('teamposition', get_string('team_position', 'tool_murelation'), ['maxlength' => 254]));

        $cohortid = new autocomplete('cohortid', get_string('cohort', 'core_cohort'), new members_add_cohort_cohortid((int)$supervisor->id));
        $cohortid->set_required(true);
        $this->add($cohortid);

        $label = get_string('members_add_cohort_a', 'tool_murelation', self::get_title($framework->subordinatestitle));
        $this->add(new buttons('buttons'));
        $this->add(new submit('submit', $label), 'buttons');
        $this->add(new cancel(), 'buttons');
    }
}
