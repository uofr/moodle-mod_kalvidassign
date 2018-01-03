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
 * Kaltura video assignment form
 *
 * @package    mod_kalvidassign
 * @copyright  (C) 2016-2017 Yamaguchi University <gh-cc@mlex.cc.yamaguchi-u.ac.jp>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once(dirname(__FILE__) . '/locallib.php');

if (!defined('MOODLE_INTERNAL')) {
    // It must be included from a Moodle page.
    die('Direct access to this script is forbidden.');
}

if (!confirm_sesskey()) {
    print_error('confirmsesskeybad', 'error');
}

$entry_id       = required_param('entry_id', PARAM_TEXT);
$cmid           = required_param('cmid', PARAM_INT);

global $USER, $OUTPUT, $DB, $PAGE;

if (! $cm = get_coursemodule_from_id('kalvidassign', $cmid)) {
    print_error('invalidcoursemodule');
}

if (! $course = $DB->get_record('course', array('id' => $cm->course))) {
    print_error('coursemisconf');
}

if (! $kalvidassignobj = $DB->get_record('kalvidassign', array('id' => $cm->instance))) {
    print_error('invalidid', 'kalvidassign');
}

require_course_login($course->id, true, $cm);

$PAGE->set_url('/mod/kalvidassign/view.php', array('id' => $course->id));
$PAGE->set_title(format_string($kalvidassignobj->name));
$PAGE->set_heading($course->fullname);

require_login();

if (kalvidassign_assignment_submission_expired($kalvidassignobj) && $kalvidassignobj->preventlate) {
    print_error('assignmentexpired', 'kalvidassign', 'course/view.php?id='. $course->id);
}

echo $OUTPUT->header();

if (empty($entry_id)) {
    print_error('emptyentryid', 'kalvidassign', $CFG->wwwroot . '/mod/kalvidassign/view.php?id='.$cm->id);
}

$param = array('vidassignid' => $kalvidassignobj->id, 'userid' => $USER->id);
$submission = $DB->get_record('kalvidassign_submission', $param);

$time = time();

if ($submission) {

    $submission->entry_id = $entry_id;
    $submission->timemodified = $time;

    if (0 == $submission->timecreated) {
        $submission->timecreated = $time;
    }

    if ($DB->update_record('kalvidassign_submission', $submission)) {

        $message = get_string('assignmentsubmitted', 'kalvidassign');
        $continue = get_string('continue');

        echo $OUTPUT->notification($message, 'notifysuccess');

        echo html_writer::start_tag('center');

        $url = new moodle_url($CFG->wwwroot . '/mod/kalvidassign/view.php', array('id' => $cm->id));

        echo $OUTPUT->single_button($url, $continue, 'post');
        echo html_writer::end_tag('center');

        // Write a log.
        $event = \mod_kalvidassign\event\media_submitted::create(array(
            'objectid' => $kalvidassignobj->id,
            'context' => context_module::instance($cm->id),
            'relateduserid' => $USER->id
        ));
        $event->trigger();
    } else {
         print_error('not_update', 'kalvidassign');
    }
} else {
    $submission = new stdClass();
    $submission->entry_id = $entry_id;
    $submission->userid = $USER->id;
    $submission->vidassignid = $kalvidassignobj->id;
    $submission->grade = -1;
    $submission->timecreated = $time;
    $submission->timemodified = $time;

    if ($DB->insert_record('kalvidassign_submission', $submission)) {

        $message = get_string('assignmentsubmitted', 'kalvidassign');
        $continue = get_string('continue');

        echo $OUTPUT->notification($message, 'notifysuccess');

        echo html_writer::start_tag('center');

        $url = new moodle_url($CFG->wwwroot . '/mod/kalvidassign/view.php', array('id' => $cm->id));

        echo $OUTPUT->single_button($url, $continue, 'post');
        echo html_writer::end_tag('center');

    } else {
         print_error('not_insert', 'kalvidassign');
    }
}

$context = $PAGE->context;

// Email an alert to the teacher
if ($kalvidassignobj->emailteachers) {
    kalvidassign_email_teachers($cm, $kalvidassignobj->name, $submission, $context);
}

echo $OUTPUT->footer();
