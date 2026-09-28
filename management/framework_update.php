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
 * Update relation framework.
 *
 * @package    tool_murelation
 * @copyright  2025 Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_mulib\muform\handler;
use tool_murelation\local\framework;

/** @var moodle_database $DB */
/** @var moodle_page $PAGE */
/** @var core_renderer $OUTPUT */
/** @var stdClass $CFG */

require('../../../../config.php');

$id = required_param('id', PARAM_INT);

$syscontext = context_system::instance();

require_login();
require_capability('tool/murelation:manageframeworks', $syscontext);

$currenturl = new moodle_url('/admin/tool/murelation/management/framework_update.php', ['id' => $id]);
$PAGE->set_context($syscontext);
$PAGE->set_url($currenturl);
$title = get_string('framework_update', 'tool_murelation');
$PAGE->set_title($title);
$PAGE->set_heading($title);

$framework = $DB->get_record('tool_murelation_framework', ['id' => $id], '*', MUST_EXIST);

$returnurl = new moodle_url('/admin/tool/murelation/management/index.php');

$current = (array)$framework;
$current['tenantids'] = $DB->get_fieldset('tool_murelation_tenant_allow', 'tenantid', ['frameworkid' => $framework->id]);
$handler = handler::from_request();

$form = new \tool_murelation\local\form\framework_update($currenturl, $current);

if ($form->is_cancelled()) {
    $handler->cancelled($returnurl);
}

if ($data = $form->get_data()) {
    $data->id = $framework->id;
    framework::update($data);
    $handler->submitted($returnurl);
}

$handler->render($form);
