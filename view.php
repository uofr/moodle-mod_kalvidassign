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

$id = optional_param('id', 0, PARAM_INT); // Course Module ID.

// Retrieve module instance.
if (empty($id)) {
    print_error('invalidid', 'kalvidassign');
}

if (!empty($id)) {

    if (! $cm = get_coursemodule_from_id('kalvidassign', $id)) {
        print_error('invalidcoursemodule');
    }

    if (! $course = $DB->get_record('course', array('id' => $cm->course))) {
        print_error('coursemisconf');
    }

    if (! $kalvidassign = $DB->get_record('kalvidassign', array("id"=>$cm->instance))) {
        print_error('invalidid', 'kalvidassign');
    }
}

require_course_login($course->id, true, $cm);

global $SESSION, $CFG, $USER, $COURSE;

// Connect to Kaltura
$kaltura        = new kaltura_connection();
$connection     = $kaltura->get_connection(true, KALTURA_SESSION_LENGTH);
$partner_id     = '';
$sr_unconf_id   = '';
$host           = '';

if ($connection) {

    // If a connection is made then include the JS libraries.
    $partnerid = local_kaltura_get_partner_id();
    $host = local_kaltura_get_host();

    $PAGE->requires->js_call_amd('local_kaltura/simpleselector', 'init',
                                 array($CFG->wwwroot . "/local/kaltura/simple_selector.php?seltype=kalvidassign",
                                       get_string('replace_media', 'mod_kalvidres')));
    $PAGE->requires->js_call_amd('local_kaltura/properties', 'init',
                                 array($CFG->wwwroot . "/local/kaltura/media_properties.php"));
    $PAGE->requires->css('/local/kaltura/css/simple_selector.css');
}


$PAGE->set_url('/mod/kalvidassign/view.php', array('id'=>$id));
$PAGE->set_title(format_string($kalvidassign->name));
$PAGE->set_heading($course->fullname);

require_login();

$modulecontext = context_module::instance(CONTEXT_MODULE, $cm->id);

// Update 'viewed' state if required by completion system.
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

if (local_kaltura_has_mobile_flavor_enabled() && local_kaltura_get_enable_html5()) {
    $uiconf_id = local_kaltura_get_player_uiconf('player');
    $url = new moodle_url(local_kaltura_htm5_javascript_url($uiconf_id));
    $PAGE->requires->js($url, true);
    $url = new moodle_url('/local/kaltura/js/frameapi.js');
    $PAGE->requires->js($url, true);
}

echo $OUTPUT->header();

$coursecontext = context_course::instance($COURSE->id);

$renderer = $PAGE->get_renderer('mod_kalvidassign');

$entry_object   = null;
$disabled       = false;

if (empty($connection)) {

    echo $OUTPUT->notification(get_string('conn_failed_alt', 'local_kaltura'));
    $disabled = true;

}

echo $renderer->display_mod_header($kalvidassign);

if (has_capability('mod/kalvidassign:gradesubmission', $coursecontext)) {
    echo $renderer->display_grading_summary($cm, $kalvidassign, $coursecontext);
    echo $renderer->display_instructor_buttons($cm);
}

if (is_enrolled($coursecontext, $USER->id) && has_capability('mod/kalvidassign:submit', $coursecontext)) {

   echo $renderer->display_submission_status($cm, $kalvidassign, $coursecontext);

    $param = array('vidassignid' => $kalvidassign->id, 'userid' => $USER->id);
    $submission = $DB->get_record('kalvidassign_submission', $param);

    if (!empty($submission->entry_id)) {
        $entry_object = local_kaltura_get_ready_entry_object($submission->entry_id, false);
    }

    $disabled = !kalvidassign_assignment_submission_opened($kalvidassign) ||
                kalvidassign_assignment_submission_expired($kalvidassign) &&
                $kalvidassign->preventlate;

    echo $renderer->display_submission($entry_object);

    if (empty($submission->entry_id) and empty($submission->timecreated)) {

        echo $renderer->display_student_submit_buttons($cm, $disabled);

    } else {
        if ($disabled ||
            !kalvidassign_assignment_submission_resubmit($kalvidassign, $entry_object)) {

            $disabled = true;
        }

        echo $renderer->display_student_resubmit_buttons($cm, $USER->id, $disabled);

        // Check if the repository plug-in exists.  Add Kaltura video to
        // the Kaltura category
        if (!empty($submission->entry_id)) {

            $category = false;
            $enabled = local_kaltura_kaltura_repository_enabled();

            if ($enabled && $connection) {
                require_once($CFG->dirroot.'/repository/kaltura/locallib.php');

                // Create the course category
                $category = repository_kaltura_create_course_category($connection, $course->id);
            }

            if (!empty($category) && $enabled) {
                repository_kaltura_add_video_course_reference($connection, $course->id, array($submission->entry_id));
            }
        }

    }

    echo $renderer->display_grade_feedback($kalvidassign, $coursecontext);
}

echo $OUTPUT->footer();
