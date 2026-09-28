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

use tool_mulib\muform\element\autocompletemany;
use tool_mulib\muform\element\buttons;
use tool_mulib\muform\element\cancel;
use tool_mulib\muform\element\submit;
use tool_mulib\muform\form;
use tool_murelation\muform\autocompletemany\subordinates_create_subuserids;

/**
 * Create team.
 *
 * @package    tool_murelation
 * @copyright  2025 Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class subordinates_create extends form {
    use details_trait;

    #[\Override]
    protected function definition(): void {
        $framework = $this->get_extra_data()['framework'];
        $tenantid = $this->get_extra_data()['tenantid'];
        $supuser = $this->get_extra_data()['supuser'];
        $subordinatestitle = self::get_title($framework->subordinatestitle);

        $this->add_framework_details($framework);
        $this->add_tenant_details($tenantid);
        $this->add_user_details('supuser', self::get_title($framework->supervisortitle), (int)$supuser->id);

        $source = new subordinates_create_subuserids((int)$framework->id, (int)$tenantid, (int)$supuser->id);
        $subuserids = new autocompletemany('subuserids', $subordinatestitle, $source);
        $subuserids->set_required(true);
        $this->add($subuserids);

        $this->add(new buttons('buttons'));
        $this->add(new submit('submit', get_string('subordinates_create_a', 'tool_murelation', $subordinatestitle)), 'buttons');
        $this->add(new cancel(), 'buttons');
    }
}
