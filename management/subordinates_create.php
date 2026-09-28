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
 * Create a team.
 *
 * @package    tool_murelation
 * @copyright  2025 Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_murelation\local\framework;
use tool_murelation\local\uimode_supervisors;
use tool_mulib\muform\handler;

/** @var moodle_database $DB */
/** @var moodle_page $PAGE */
/** @var core_renderer $OUTPUT */
/** @var stdClass $CFG */
/** @var stdClass $USER */


require('../../../../config.php');

$frameworkid = required_param('frameworkid', PARAM_INT);
$supuserid = optional_param('supuserid', 0, PARAM_INT);

require_login();

$context = context_system::instance();
$tenantid = null;
if (\tool_mulib\local\mulib::is_mutenancy_active()) {
    // NOTE: picking tenant here would be tricky, make them switch tenants for now.
    $tenantid = \tool_mutenancy\local\tenancy::get_current_tenantid();
    if ($tenantid) {
        $context = context_tenant::instance($tenantid);
    }
}

require_capability('tool/murelation:managepositions', $context);

$currenturl = new moodle_url('/admin/tool/murelation/management/subordinates_create.php', ['frameworkid' => $frameworkid]);
$PAGE->set_context($context);
$PAGE->set_url($currenturl);

$framework = $DB->get_record('tool_murelation_framework', ['id' => $frameworkid], '*', MUST_EXIST);
$title = get_string('subordinates_create_a', 'tool_murelation', format_string($framework->subordinatestitle));
$PAGE->set_title($title);
$PAGE->set_heading($title);
if ($framework->uimode != framework::UIMODE_SUPERVISORS) {
    redirect(new moodle_url('/admin/tool/murelation/management/framework.php', ['id' => $framework->id]));
}

$returnurl = new moodle_url('/admin/tool/murelation/management/framework_subordinates.php', ['id' => $framework->id]);

if (!uimode_supervisors::can_bulk_create($framework, $context)) {
    redirect($returnurl);
}

$handler = handler::from_request();
$extra = ['framework' => $framework, 'tenantid' => $tenantid];

if ($supuserid) {
    // The supervisor comes from the URL of the second step, it must be a valid candidate.
    $source = new \tool_murelation\muform\autocomplete\subordinates_create_select_supuserid($framework->id, (int)$tenantid);
    if ($source->label((string)$supuserid) === null) {
        $supuserid = 0;
    }
}

if (!$supuserid) {
    $form = new \tool_murelation\local\form\subordinates_create_select($currenturl, [], $extra);
    if ($form->is_cancelled()) {
        $handler->cancelled($returnurl);
    }
    if ($data = $form->get_data()) {
        $nexturl = new moodle_url($currenturl, ['supuserid' => $data->supuserid]);
        if ($handler->is_dialog()) {
            // The dialog continues with the second step, the page gets it from the next URL.
            $extra['supuser'] = $DB->get_record('user', ['id' => $data->supuserid, 'deleted' => 0, 'confirmed' => 1], '*', MUST_EXIST);
            $handler->render(new \tool_murelation\local\form\subordinates_create($nexturl, [], $extra));
        }
        redirect($nexturl);
    }
    $handler->render($form);
}

$extra['supuser'] = $DB->get_record('user', ['id' => $supuserid, 'deleted' => 0, 'confirmed' => 1], '*', MUST_EXIST);
$form = new \tool_murelation\local\form\subordinates_create(new moodle_url($currenturl, ['supuserid' => $supuserid]), [], $extra);

if ($form->is_cancelled()) {
    $handler->cancelled($returnurl);
}

if ($data = $form->get_data()) {
    $data->frameworkid = $framework->id;
    $data->supuserid = $supuserid;
    $data->tenantid = $tenantid;
    uimode_supervisors::bulk_create($data);
    $handler->submitted($returnurl);
}

$handler->render($form);
