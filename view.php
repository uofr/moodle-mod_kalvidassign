<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Kaltura video assignment
 *
 * @package   mod_kalvidassign
 * @copyright (C) 2016-2017 Yamaguchi University <gh-cc@mlex.cc.yamaguchi-u.ac.jp>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once(dirname(dirname(dirname(__FILE__))) . '/local/kaltura/locallib.php');
require_once(dirname(__FILE__) . '/locallib.php');
$id = required_param('id', PARAM_INT); // Course Module ID.
$cm = get_coursemodule_from_id('kalvidassign', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$kalvidassign = $DB->get_record('kalvidassign', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);

$PAGE->set_url('/mod/kalvidassign/view.php', array('id'=>$id));
$PAGE->set_title(format_string($kalvidassign->name));
$PAGE->set_heading($course->fullname);

$coursecontext = context_course::instance($COURSE->id);

// Connect to Kaltura
$kaltura = new kaltura_connection();
$connection = $kaltura->get_connection(true, KALTURA_SESSION_LENGTH);

// Update 'viewed' state if required by completion system.
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

$renderer = $PAGE->get_renderer('mod_kalvidassign');

echo $renderer->header();
echo $renderer->heading($kalvidassign->name);

// Instructor View
if (has_capability('mod/kalvidassign:gradesubmission', $coursecontext)) {
    echo $renderer->display_grading_summary($cm, $kalvidassign, $coursecontext);
    echo $renderer->display_instructor_buttons($cm);
}

// Student View
if (has_capability('mod/kalvidassign:submit', $coursecontext)) {
    $submission = $DB->get_record('kalvidassign_submission', ['vidassignid' => $kalvidassign->id, 'userid' => $USER->id]);

    $client = \local_kaltura\kaltura_client::get_client('kaltura');
    $client->setKs(\local_kaltura\kaltura_session_manager::get_user_session($client));

    $client_legacy = \local_kaltura\kaltura_client::get_client('ce');
    $client_legacy->setKs(\local_kaltura\kaltura_session_manager::get_user_session_legacy($client_legacy));

    if (!empty($submission->entry_id)) {
        $entry_response = \local_kaltura\kaltura_entry_manager::get_entry($client, $submission->entry_id);
        if (!$entry_response->totalCount) {
            $entry_response = \local_kaltura\kaltura_entry_manager::get_entry($client_legacy, $submission->entry_id);
        }
        $entry_object = $entry_response->objects[0];
    }

    $has_ce = \local_kaltura\kaltura_entry_manager::count_entries($client_legacy) > 0;

    $client->session->end();
    $client_legacy->session->end();

    $PAGE->requires->js_call_amd('mod_kalvidassign/kalvidassign', 'init', [
        $PAGE->context->id,
        $entry_object ? $entry_object->id : null,
        $entry_object ? $entry_object->name : null,
        $entry_object ? $entry_object->thumbnailUrl : null,
        $has_ce
    ]);

    echo $renderer->display_submission_status($cm, $kalvidassign, $coursecontext);

    echo $renderer->display_submission($entry_object);

    if (!$submission) {
        $disabled = !kalvidassign_assignment_submission_opened($kalvidassign) ||
                    kalvidassign_assignment_submission_expired($kalvidassign) &&
                    $kalvidassign->preventlate;
        if (!$disabled)
            echo $renderer->display_student_submit_buttons($cm);
    }
    else {
        if (!kalvidassign_assignment_submission_resubmit($kalvidassign, $entry_object)) {
            $disabled = true;
        }
        if (!$disabled) {
            echo $renderer->display_student_resubmit_buttons($cm, $USER->id);
        }
        //if (!empty($submission->entry_id)) {
        //    $category = false;
        //    $enabled = local_kaltura_kaltura_repository_enabled();
        //    if ($enabled && $connection) {
        //        require_once($CFG->dirroot.'/repository/kaltura/locallib.php');
        //        $category = repository_kaltura_create_course_category($connection, $course->id);
        //    }
        //    if (!empty($category) && $enabled) {
        //        repository_kaltura_add_video_course_reference($connection, $course->id, array($submission->entry_id));
        //    }
        //}
    }

    // Feedback
    echo $renderer->display_grade_feedback($kalvidassign, $coursecontext);
}

echo $renderer->footer();
