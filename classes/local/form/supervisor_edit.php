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
use tool_mulib\muform\element\submit;
use tool_mulib\muform\form;
use tool_murelation\muform\autocomplete\supervisor_edit_userid;

/**
 * Create or update supervisor for given subordinate user.
 *
 * @package    tool_murelation
 * @copyright  2025 Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class supervisor_edit extends form {
    use details_trait;

    #[\Override]
    protected function definition(): void {
        $framework = $this->get_extra_data()['framework'];
        $subuser = $this->get_extra_data()['subuser'];
        $supervisortitle = self::get_title($framework->supervisortitle);

        $this->add_framework_details($framework);
        $this->add_user_details('user', self::get_title($framework->subordinatetitle), (int)$subuser->id);

        $userid = new autocomplete('userid', $supervisortitle, new supervisor_edit_userid((int)$framework->id, (int)$subuser->id));
        $userid->set_required(true);
        $this->add($userid);

        $label = $this->get_extra_data()['subordinate']
            ? get_string('supervisor_update_a', 'tool_murelation', $supervisortitle)
            : get_string('supervisor_create_a', 'tool_murelation', $supervisortitle);
        $this->add(new buttons('buttons'));
        $this->add(new submit('submit', $label), 'buttons');
        $this->add(new cancel(), 'buttons');
    }
}
