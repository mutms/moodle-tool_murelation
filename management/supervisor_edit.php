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

/**
 * Add subordinate position.
 *
 * @package    tool_murelation
 * @copyright  2025 Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_mulib\muform\handler;
use tool_murelation\local\framework;
use tool_murelation\local\uimode_supervisors;

/** @var moodle_database $DB */
/** @var moodle_page $PAGE */
/** @var core_renderer $OUTPUT */
/** @var stdClass $CFG */
/** @var stdClass $USER */

require('../../../../config.php');

$subuserid = required_param('subuserid', PARAM_INT);
$frameworkid = required_param('frameworkid', PARAM_INT);

require_login();

$framework = $DB->get_record('tool_murelation_framework', ['id' => $frameworkid], '*', MUST_EXIST);
$subuser = $DB->get_record('user', ['id' => $subuserid], '*', MUST_EXIST);

$context = context_user::instance($subuser->id);
require_capability('tool/murelation:managepositions', $context);

if ($framework->uimode != framework::UIMODE_SUPERVISORS) {
    throw new \core\exception\coding_exception('supervisor management is possible for frameworks in Supervisors mode only');
}

$returnurl = new moodle_url('/user/profile.php', ['id' => $subuser->id]);
$currenturl = new moodle_url('/admin/tool/murelation/management/supervisor_edit.php', ['frameworkid' => $framework->id, 'subuserid' => $subuser->id]);

$PAGE->set_context($context);
$PAGE->set_url($currenturl);

$subordinate = $DB->get_record('tool_murelation_subordinate', ['frameworkid' => $framework->id, 'userid' => $subuser->id]);
if ($subordinate) {
    $supervisor = $DB->get_record('tool_murelation_supervisor', ['id' => $subordinate->supervisorid]);
    if (!$supervisor) {
        // This should not happen.
        $supervisor = null;
    }
} else {
    $subordinate = null;
    $supervisor = null;
}

if (!uimode_supervisors::can_manage_subordinate($framework, $subuser->id)) {
    redirect($returnurl);
}

$current = ['userid' => $supervisor ? $supervisor->userid : null];
$extra = ['framework' => $framework, 'subuser' => $subuser, 'subordinate' => $subordinate];
$title = $subordinate ? get_string('supervisor_update_a', 'tool_murelation', format_string($framework->supervisortitle))
    : get_string('supervisor_create_a', 'tool_murelation', format_string($framework->supervisortitle));
$PAGE->set_title($title);
$PAGE->set_heading($title);

$handler = handler::from_request();

$form = new \tool_murelation\local\form\supervisor_edit($currenturl, $current, $extra);

if ($form->is_cancelled()) {
    $handler->cancelled($returnurl);
}

if ($data = $form->get_data()) {
    $data->frameworkid = $framework->id;
    $data->subuserid = $subuser->id;
    uimode_supervisors::supervisor_edit($data);
    $handler->submitted($returnurl);
}

$handler->render($form);
