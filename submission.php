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

if (!confirm_sesskey()) {
    print_error('confirmsesskeybad', 'error');
}

$entry_id = required_param('entry_id', PARAM_TEXT);
$cmid = required_param('cmid', PARAM_INT);
$cm = get_coursemodule_from_id('kalvidassign', $cmid, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$kalvidassignobj = $DB->get_record('kalvidassign', array('id' => $cm->instance), '*', MUST_EXIST);
$submission = $DB->get_record('kalvidassign_submission', ['vidassignid' => $kalvidassignobj->id, 'userid' => $USER->id]);
$time = time();

require_login($course, false, $cm);

$PAGE->set_url('/mod/kalvidassign/view.php', array('id' => $course->id));
$PAGE->set_title(format_string($kalvidassignobj->name));
$PAGE->set_heading($course->fullname);

if (kalvidassign_assignment_submission_expired($kalvidassignobj) && $kalvidassignobj->preventlate) {
    print_error('assignmentexpired', 'kalvidassign', 'course/view.php?id='. $course->id);
}

echo $OUTPUT->header();

if ($submission) {
    $old_entry_id = $submission->entry_id;
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

        $client = \local_kaltura\kaltura_client::get_client();
        $client->setKs(\local_kaltura\kaltura_session_manager::get_user_session($client));

        // Check if old entry is still being used in an assignment.
        // If so, set the "Assessment" metadata tag to "Yes". If not, set the "Assessment" metadata tag to "No."
        $old_metadata = \local_kaltura\kaltura_metadata_manger::get_custom_metadata($client, $old_entry_id);
        if ($old_metadata->totalCount) {
                $old_metadata_xml = new SimpleXMLElement($old_metadata->objects[0]->xml);
                $course_share = $old_metadata_xml->CourseShare ? (array) $old_metadata_xml->CourseShare : [];
                $store_media = (string) $old_metadata_xml->StoreMedia;
                $student_content = (string) $old_metadata_xml->StudentContent;
                $assessment = $DB->count_records('kalvidassign_submission', ['entry_id' => $old_entry_id]) > 0 ? 'Yes' : 'No';
                if ((string)$old_metadata_xml->Assessment !== $assessment)
                    \local_kaltura\kaltura_metadata_manger::update_custom_metadata($client, $old_metadata->objects[0]->id, $course_share, $store_media, $student_content, $assessment);
        }
        // Update custom metadata for new entry. Set Assessment to "Yes."
        $metadata = \local_kaltura\kaltura_metadata_manger::get_custom_metadata($client, $entry_id);
        if ($metadata->totalCount) {
                $metadata_xml = new SimpleXMLElement($metadata->objects[0]->xml);
                $course_share = $metadata_xml->CourseShare ? (array) $metadata_xml->CourseShare : [];
                $store_media = (string) $metadata_xml->StoreMedia;
                $student_content = (string) $metadata_xml->StudentContent;
                $assessment = "Yes";
                \local_kaltura\kaltura_metadata_manger::update_custom_metadata($client, $metadata->objects[0]->id, $course_share, $store_media, $student_content, $assessment);
        }
        $client->session->end();
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


        $client = \local_kaltura\kaltura_client::get_client();
        $client->setKs(\local_kaltura\kaltura_session_manager::get_user_session($client));
        // Update custom metadata for new entry. Set Assessment to "Yes."
        $metadata = \local_kaltura\kaltura_metadata_manger::get_custom_metadata($client, $entry_id);
        if ($metadata->totalCount) {
                $metadata_xml = new SimpleXMLElement($metadata->objects[0]->xml);
                $course_share = $metadata_xml->CourseShare ? (array) $metadata_xml->CourseShare : [];
                $store_media = (string) $metadata_xml->StoreMedia;
                $student_content = (string) $metadata_xml->StudentContent;
                $assessment = "Yes";
                \local_kaltura\kaltura_metadata_manger::update_custom_metadata($client, $metadata->objects[0]->id, $course_share,
                    $store_media, $student_content, $assessment);
        }
        $client->session->end();
    } else {
         print_error('not_insert', 'kalvidassign');
    }
}

// Email an alert to the teacher
if ($kalvidassignobj->emailteachers) {
    $context = $PAGE->context;
    kalvidassign_email_teachers($cm, $kalvidassignobj->name, $submission, $context);
}

echo $OUTPUT->footer();
