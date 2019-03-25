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
 * Kaltura video assignment library of hooks
 *
 * @package    mod_kalvidassign
 * @copyright  (C) 2016-2017 Yamaguchi University <gh-cc@mlex.cc.yamaguchi-u.ac.jp>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
// Include eventslib.php.
//require_once($CFG->libdir.'/eventslib.php');
// Include calendar/lib.php.
require_once($CFG->dirroot.'/calendar/lib.php');

if (!defined('MOODLE_INTERNAL')) {
    // It must be included from a Moodle page.
    die('Direct access to this script is forbidden.');
}

require_login();

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @param object $kalvidassign An object from the form in mod_form.php
 * @return int The id of the newly inserted kalvidassign record
 */
function kalvidassign_add_instance($kalvidassign) {
    global $DB;

    $kalvidassign->timecreated = time();

    $kalvidassign->id =  $DB->insert_record('kalvidassign', $kalvidassign);

    if ($kalvidassign->timedue) {
        $event = new stdClass();
        $event->name        = $kalvidassign->name;
        $event->description = format_module_intro('kalvidassign', $kalvidassign, $kalvidassign->coursemodule);
        $event->courseid    = $kalvidassign->course;
        $event->groupid     = 0;
        $event->userid      = 0;
        $event->modulename  = 'kalvidassign';
        $event->instance    = $kalvidassign->id;
        $event->eventtype   = 'due';
        $event->timestart   = $kalvidassign->timedue;
        $event->timeduration = 0;

        calendar_event::create($event);
    }

    kalvidassign_grade_item_update($kalvidassign);

    return $kalvidassign->id;
}

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @param object $kalvidassign An object from the form in mod_form.php
 * @return boolean Success/Fail
 */
function kalvidassign_update_instance($kalvidassign) {
    global $DB;

    $kalvidassign->timemodified = time();
    $kalvidassign->id = $kalvidassign->instance;

    $updated = $DB->update_record('kalvidassign', $kalvidassign);

    if ($kalvidassign->timedue) {
        $event = new stdClass();

        if ($event->id = $DB->get_field('event', 'id', array('modulename'=>'kalvidassign', 'instance'=>$kalvidassign->id))) {

            $event->name        = $kalvidassign->name;
            $event->description = format_module_intro('kalvidassign', $kalvidassign, $kalvidassign->coursemodule);
            $event->timestart   = $kalvidassign->timedue;

            $calendarevent = calendar_event::load($event->id);
            $calendarevent->update($event);
        } else {
            $event = new stdClass();
            $event->name        = $kalvidassign->name;
            $event->description = format_module_intro('kalvidassign', $kalvidassign, $kalvidassign->coursemodule);
            $event->courseid    = $kalvidassign->course;
            $event->groupid     = 0;
            $event->userid      = 0;
            $event->modulename  = 'kalvidassign';
            $event->instance    = $kalvidassign->id;
            $event->eventtype   = 'due';
            $event->timestart   = $kalvidassign->timedue;
            $event->timeduration = 0;

            calendar_event::create($event);
        }
    } else {
        $DB->delete_records('event', array('modulename'=>'kalvidassign', 'instance'=>$kalvidassign->id));
    }

    if ($updated) {
        kalvidassign_grade_item_update($kalvidassign);
    }

    return $updated;
}


/**
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id Id of the module instance
 * @return boolean Success/Failure
 */
function kalvidassign_delete_instance($id) {
    global $DB;

    $result = true;

    if (! $kalvidassign = $DB->get_record('kalvidassign', array('id' => $id))) {
        return false;
    }

    if (! $DB->delete_records('kalvidassign_submission', array('vidassignid' => $kalvidassign->id))) {
        $result = false;
    }

    if (! $DB->delete_records('event', array('modulename'=>'kalvidassign', 'instance'=>$kalvidassign->id))) {
        $result = false;
    }

    if (! $DB->delete_records('kalvidassign', array('id' => $kalvidassign->id))) {
        $result = false;
    }

    kalvidassign_grade_item_delete($kalvidassign);

    return $result;
}


/**
 * Return a small object with summary information about what a
 * user has done with a given particular instance of this module
 * Used for user activity reports.
 * $return->time = the time they did it
 * $return->info = a short text description
 * @param object $course - Moodle course object.
 * @param object $user - Moodle user object.
 * @param object $mod - Moodle moduble object.
 * @param object $kalmediaassign - An object from the form in mod_form.php.
 * @return object - outline of user.
 * @todo Finish documenting this function
 */
function kalvidassign_user_outline($course, $user, $mod, $kalvidassign) {
    $return = new stdClass;
    $return->time = 0;
    $return->info = ''; //TODO finish this function
    return $return;
}


/**
 * Print a detailed representation of what a user has done with
 * a given particular instance of this module, for user activity reports.
 * @param object $course - Moodle course object.
 * @param object $user - Moodle user object.
 * @param object $mod - Moodle module obuject.
 * @param object $kalmediaassign - An object from the form in mod_form.php.
 * @return bool - this function always return true.
 * @todo Finish documenting this function
 */
function kalvidassign_user_complete($course, $user, $mod, $kalvidassign) {
    return true;  //TODO: finish this function
}

/**
 * Given a course and a time, this module should find recent activity
 * that has occurred in kalvidassign activities and print it out.
 * Return true if there was output, or false is there was none.
 * @param object $course - Moodle course object.
 * @param array $viewfullnames - fullnames of course.
 * @param int $timestart - timestamp.
 * @return boolean - True if anything was printed, otherwise false.
 * @todo Finish documenting this function
 */
function kalvidassign_print_recent_activity($course, $viewfullnames, $timestart) {
    // TODO: finish this function
    return false;  //  True if anything was printed, otherwise false
}


/**
 * Must return an array of users who are participants for a given instance
 * of kalvidassign. Must include every user involved in the instance,
 * independient of his role (student, teacher, admin...). The returned objects
 * must contain at least id property. See other modules as example.
 *
 * @param int $kalvidassignid - ID of an instance of this module
 * @return boolean|array - false if no participants, array of objects otherwise
 */
function kalvidassign_get_participants($kalvidassignid) {
    // TODO: finish this function
    return false;
}


/**
 * This function returns if a scale is being used by one kalvidassign
 * if it has support for grading and scales. Commented code should be
 * modified if necessary. See forum, glossary or journal modules
 * as reference.
 *
 * @param int $kalvidassignid - id of an instance of this module
 * @param int $scaleid - id of scale.
 * @return mixed - now, this function anywhere returns "false".
 * @todo Finish documenting this function
 */
function kalvidassign_scale_used($kalvidassignid, $scaleid) {
    global $DB;

    $return = false;

    return $return;
}

/**
 * Checks if scale is being used by any instance of kalvidassign.
 * This function was added in 1.9
 *
 * This is used to find out if scale used anywhere
 * @param int $scaleid - id of scale.
 * @return bool - True if the scale is used by any kalvidassign
 */
function kalvidassign_scale_used_anywhere($scaleid) {
    global $DB;

    $param = array('grade' => -$scaleid);
    if ($scaleid and $DB->record_exists('kalvidassign', $param)) {
        return true;
    } else {
        return false;
    }
}

/**
 * This function returns support status about a feature which is received as argument.
 * @param string $feature - FEATURE_xx constant for requested feature
 * @return mixed - True if module supports feature, null if doesn't know
 */
function kalvidassign_supports($feature) {
    switch($feature) {
        case FEATURE_GROUPS:
            return true;
        case FEATURE_GROUPINGS:
            return true;
        case FEATURE_GROUPMEMBERSONLY:
            return true;
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_GRADE_OUTCOMES:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;

        default:
            return null;
    }
}

/**
 * Create/update grade item for given kaltura video assignment
 *
 * @param object $kalvidassign - kalvidassign object with extra cmidnumber
 * @param mixed $grades - optional array/object of grade(s); 'reset' means reset grades in gradebook
 * @return int - 0 if ok, error code otherwise
 */
function kalvidassign_grade_item_update($kalvidassign, $grades = null) {

    require_once(dirname(dirname(dirname(__FILE__))) . '/lib/gradelib.php');

    $params = array('itemname'=>$kalvidassign->name, 'idnumber'=>$kalvidassign->cmidnumber);

    if ($kalvidassign->grade > 0) {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax']  = $kalvidassign->grade;
        $params['grademin']  = 0;

    } else if ($kalvidassign->grade < 0) {
        $params['gradetype'] = GRADE_TYPE_SCALE;
        $params['scaleid']   = -$kalvidassign->grade;

    } else {
        $params['gradetype'] = GRADE_TYPE_TEXT; // allow text comments only
    }

    if ($grades === 'reset') {
        $params['reset'] = true;
        $grades = null;
    }

    return grade_update('mod/kalvidassign', $kalvidassign->course, 'mod', 'kalvidassign', $kalvidassign->id, 0, $grades, $params);

}

/**
 * Removes all grades from gradebook
 *
 * @param int $courseid - id of course.
 * @param string  $type - optional type.
 * @return nothing.
 */
function kalvidassign_reset_gradebook($courseid, $type='') {
    global $DB;

    $sql = "SELECT l.*, cm.idnumber as cmidnumber, l.course as courseid
              FROM {kalvidassign} l, {course_modules} cm, {modules} m
             WHERE m.name='kalvidassign' AND m.id=cm.module AND cm.instance=l.id AND l.course=:course";

    $params = array ('course' => $courseid);

    if ($kalvisassigns = $DB->get_records_sql($sql, $params)) {

        foreach ($kalvisassigns as $kalvisassign) {
            kalvidassign_grade_item_update($kalvisassign, 'reset');
        }
    }

}


/**
 * Actual implementation of the reset course functionality, delete all the
 * kaltura video submissions attempts for course $data->courseid.
 *
 * @param object $data - the data submitted from the reset course.
 * @return array - status array.
 *
 * TODO: test user data reset feature
 */
function kalvidassign_reset_userdata($data) {
    global $CFG, $DB;

    $componentstr = get_string('modulenameplural', 'kalvidassign');
    $status = array();

    if (!empty($data->reset_kalvidassign)) {
        $kalvidassignsql = "SELECT l.id
                           FROM {kalvidassign} l
                           WHERE l.course=:course";

        $params = array ("course" => $data->courseid);
        $DB->delete_records_select('kalvidassign_submission', "vidassignid IN ($kalvidassignsql)", $params);

        // remove all grades from gradebook
        if (empty($data->reset_gradebook_grades)) {
            kalvidassign_reset_gradebook($data->courseid);
        }

        $status[] = array('component' => $componentstr,
                          'item' => get_string('deleteallsubmissions', 'kalvidassign'),
                          'error' => false);
    }

    /// updating dates - shift may be negative too
    if ($data->timeshift) {
        shift_course_mod_dates('kalvidassign',array('timedue', 'timeavailable'), $data->timeshift, $data->courseid);
        $status[] = array('component'=>$componentstr,
                          'item'=>get_string('datechanged'),
                          'error'=>false);
    }

    return $status;
}

/**
 * This function deletes a grade item.
 *
 * @param object $kalvidassign - kaltura video assignment object.
 * @return array - status array.
 *
 * TODO: test user data reset feature
 */
function kalvidassign_grade_item_delete($kalvidassign) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    return grade_update('mod/kalvidassign', $kalvidassign->course, 'mod', 'kalvidassign', $kalvidassign->id, 0,
            null, array('deleted' => 1));
}


/**
 * Function to be run periodically according to the moodle cron.
 * Finds all assignment notifications that have yet to be mailed out, and mails them.
 */
function kalvidassign_cron () {
    return false;
}

/**
 * Return list of marked submissions that have not been mailed out for currently enrolled students
 *
 * @param int $starttime - start time for search submissions.
 * @param int $endtime - end time for search submissions.
 * @return array - list of marked submissions.
 */
function kalvidassign_get_unmailed_submissions($starttime, $endtime) {

    global $DB;

    return $DB->get_records_sql("SELECT ks.*, k.course, k.name
                                     FROM {kalvidassign_submission} ks,
                                     {kalvidassign} k
                                     WHERE ks.mailed = 0
                                     AND ks.timemarked <= ?
                                     AND ks.timemarked >= ?
                                     AND ks.assignment = k.id", array($endtime, $starttime));
}


/**
 * This is a standard Moodle module that prints out a summary of all activities of this kind in the My Moodle page for a user
 *
 * @param object $courses
 * @param object $htmlarray
 * @global type $USER
 * @global type $CFG
 * @global type $DB
 * @global type $OUTPUT
 * @return bool success
 */
function kalvidassign_print_overview($courses, &$htmlarray) {
    global $USER, $CFG, $DB, $OUTPUT;

    if (empty($courses) || !is_array($courses) || count($courses) == 0) {
        return array();
    }

    if (!$kalvidassigns = get_all_instances_in_courses('kalvidassign', $courses)) {
        return;
    }
		
    $submissioncount = array();
    foreach ($kalvidassigns as $kalvidassign) {
      $time = time();
      $isopen = false;
			if ($kalvidassign->timedue) {
				$isopen = ($kalvidassign->timeavailable <= $time && $time <= $kalvidassign->timedue);
			
	      if (!$kalvidassign->preventlate) {
	          $isopen = true;
	      }
			
			} else {
				$isopen = ($kalvidassign->timeavailable <= $time);
			}
			
      if ($isopen) {
          $kalvidassignmentids[] = $kalvidassign->id;
      }
  }

  if (empty($kalvidassignmentids)) {
      // No assignments to look at - we're done.
      return true;
  }
	
  $strduedate = get_string('duedate', 'assign');
  $strcutoffdate = get_string('nosubmissionsacceptedafter', 'assign');
  $strnolatesubmissions = get_string('nolatesubmissions', 'assign');
  $strduedateno = get_string('duedateno', 'assign');
  $strassignment = get_string('modulename', 'assign');
	

  // We do all possible database work here *outside* of the loop to ensure this scales.
  list($sqlkalvidassignmentids, $kalvidassignmentidparams) = $DB->get_in_or_equal($kalvidassignmentids);

  $mysubmissions = null;
  $unmarkedsubmissions = null;
	
	foreach ($kalvidassigns as $kalvidassign) {    
		
    // Do not show assignments that are not open.
    if (!in_array($kalvidassign->id, $kalvidassignmentids)) {
        continue;
    }
		
		
		
				$cm = get_coursemodule_from_id('kalvidassign', $kalvidassign->coursemodule);
        $context = context_module::instance($cm->id);
				
				
				
        if (has_capability('mod/kalvidassign:submit', $context, null, false) && !has_capability('mod/kalvidassign:gradesubmission', $context, null, false)) {
            // Does the submission status of the assignment require notification?
            $submitdetails = kalvidassign_get_mysubmission_details_for_print_overview($mysubmissions, $sqlkalvidassignmentids,
                    $kalvidassignmentidparams, $kalvidassign);
        } else {
            $submitdetails = false;
        }
				
        if (has_capability('mod/kalvidassign:gradesubmission', $context, null, false)) {
            // Does the grading status of the assignment require notification ?
            $gradedetails = kalvidassign_get_grade_details_for_print_overview($unmarkedsubmissions, $sqlkalvidassignmentids,
                    $kalvidassignmentidparams, $kalvidassign, $context);
        } else {
            $gradedetails = false;
        }

        if (empty($submitdetails) && empty($gradedetails)) {
            // There is no need to display this assignment as there is nothing to notify.
            continue;
        }

        $dimmedclass = '';
        if (!$kalvidassign->visible) {
            $dimmedclass = ' class="dimmed"';
        }
        $href = $CFG->wwwroot . '/mod/kalvidassign/view.php?id=' . $kalvidassign->coursemodule;
        $basestr = '<div class="kalvidassign overview">' .
               '<div class="name">' .
               $strassignment . ': '.
               '<a ' . $dimmedclass .
                   'title="' . $strassignment . '" ' .
                   'href="' . $href . '">' .
               format_string($kalvidassign->name) .
               '</a></div>';
        if ($kalvidassign->timedue) {
            $userdate = userdate($kalvidassign->timedue);
            $basestr .= '<div class="info">' . $strduedate . ': ' . $userdate . '</div>';
        } else {
            $basestr .= '<div class="info">' . $strduedateno . '</div>';
        }
        if ($kalvidassign->preventlate) {
            $basestr .= '<div class="info">' . $strnolatesubmissions . '</div>';
        }

        // Show only relevant information.
        if (!empty($submitdetails)) {
            $basestr .= $submitdetails;
        }

        if (!empty($gradedetails)) {
            $basestr .= $gradedetails;
        }
        $basestr .= '</div>';

        if (empty($htmlarray[$kalvidassign->course]['kalvidassign'])) {
            $htmlarray[$kalvidassign->course]['kalvidassign'] = $basestr;
        } else {
            $htmlarray[$kalvidassign->course]['kalvidassign'] .= $basestr;
        }
    }
    return true;
				
				
				
		/*
        $str = '<div class="assign_overview"><div class="name">Assignment: <a href="'.$CFG->wwwroot.'/mod/kalvidassign/view.php?id='.$kalvidassign->coursemodule.'">'.$kalvidassign->name.'</a></div><div class="info">Due date: '.userdate($kalvidassign->timedue).'</div><div class="details">My submission: </div></div>'.print_r($kalvidassign,1);
             

        if (empty($htmlarray[$kalvidassign->course]['kalvidassign'])) 				{
            $htmlarray[$kalvidassign->course]['kalvidassign'] = $str;
        } else {
            $htmlarray[$kalvidassign->course]['kalvidassign'] .= $str;
        }
		
		}
		*/

}



/**
 * This api generates html to be displayed to students in print overview section, related to their submission status of the given
 * assignment.
 *
 * @param array $mysubmissions list of submissions of current user indexed by assignment id.
 * @param string $sqlassignmentids sql clause used to filter open assignments.
 * @param array $assignmentidparams sql params used to filter open assignments.
 * @param stdClass $assignment current assignment
 *
 * @return bool|string html to display , false if nothing needs to be displayed.
 * @throws coding_exception
 */
function kalvidassign_get_mysubmission_details_for_print_overview(&$mysubmissions, $sqlkalvidassignmentids, $kalvidassignmentidparams,
                                                            $kalvidassignment) {
    global $USER, $DB;


    $strnotsubmittedyet = get_string('notsubmittedyet', 'assign');

    if (!isset($mysubmissions)) {

        // Get all user submissions, indexed by assignment id.
        $dbparams = array_merge(array($USER->id), $kalvidassignmentidparams, array($USER->id));
        $mysubmissions = $DB->get_records_sql('SELECT a.id AS kalvidassignment,
                                                      s.timemarked AS timemarked,
                                                      s.teacher AS grader,
                                                      s.grade AS grade
                                                 FROM {kalvidassign} a
                                            LEFT JOIN {kalvidassign_submission} s ON
                                                      a.id = s.vidassignid AND
                                                      s.userid = ? 
                                                WHERE a.id ' . $sqlkalvidassignmentids . ' AND
                                                      s.vidassignid = a.id AND
                                                      s.userid = ?', $dbparams);
    }

    $submitdetails = '';
    $submitdetails .= '<div class="details">';
    $submitdetails .= get_string('mysubmission', 'assign');
    $submission = false;

    if (isset($mysubmissions[$kalvidassignment->id])) {
        $submission = $mysubmissions[$kalvidassignment->id];
    }
		//debugging://$submitdetails .= print_r($submission,1).':'.print_r($submission->status,1).'||';
    //if ($submission && $submission->status == 1) {
    if ($submission) {
        // A valid submission already exists, no need to notify students about this.
        return false;
    }

    // We need to show details only if a valid submission doesn't exist.
    if (!$submission) {
        $submitdetails .= $strnotsubmittedyet;
    } else {
        $submitdetails .= get_string('submitted', 'assign');
    }
		/*
    if ($kalvidassignment->markingworkflow) {
        $workflowstate = $DB->get_field('assign_user_flags', 'workflowstate', array('assignment' =>
                $assignment->id, 'userid' => $USER->id));
        if ($workflowstate) {
            $gradingstatus = 'markingworkflowstate' . $workflowstate;
        } else {
            $gradingstatus = 'markingworkflowstate' . ASSIGN_MARKING_WORKFLOW_STATE_NOTMARKED;
        }
    } else */
		if (!empty($submission->grade) && $submission->grade !== null && $submission->grade >= 0) {
        $gradingstatus = ASSIGN_GRADING_STATUS_GRADED;
    } else {
        $gradingstatus = ASSIGN_GRADING_STATUS_NOT_GRADED;
    }
    $submitdetails .= ', ' . get_string($gradingstatus, 'assign');
    $submitdetails .= '</div>';
    return $submitdetails;
}

/**
 * This api generates html to be displayed to teachers in print overview section, related to the grading status of the given
 * assignment's submissions.
 *
 * @param array $unmarkedsubmissions list of submissions of that are currently unmarked indexed by assignment id.
 * @param string $sqlassignmentids sql clause used to filter open assignments.
 * @param array $assignmentidparams sql params used to filter open assignments.
 * @param stdClass $assignment current assignment
 * @param context $context context of the assignment.
 *
 * @return bool|string html to display , false if nothing needs to be displayed.
 * @throws coding_exception
 */
function kalvidassign_get_grade_details_for_print_overview(&$unmarkedsubmissions, $sqlkalvidassignmentids, $kalvidassignmentidparams,
                                                     $kalvidassignment, $context) {
    global $DB;
    if (!isset($unmarkedsubmissions)) {
        // Build up and array of unmarked submissions indexed by assignment id/ userid
        // for use where the user has grading rights on assignment.
        $dbparams = array_merge(array(ASSIGN_SUBMISSION_STATUS_SUBMITTED), $kalvidassignmentidparams);
        $rs = $DB->get_recordset_sql('SELECT s.vidassignid as assignment,
                                             s.userid as userid,
                                             s.id as id,
                                             s.mailed as status,
                                             s.timemarked as timegraded
                                        FROM {kalvidassign_submission} s
                                   LEFT JOIN {kalvidassign} a ON
                                             a.id = s.vidassignid
                                       WHERE
                                             s.timemarked = 0 OR
                                             s.grade = -1 AND
                                             s.vidassignid ' . $sqlkalvidassignmentids, $dbparams);

        $unmarkedsubmissions = array();
        foreach ($rs as $rd) {
            $unmarkedsubmissions[$rd->assignment][$rd->userid] = $rd->id;
        }
        $rs->close();
    }

    // Count how many people can submit.
    $submissions = 0;
    if ($students = get_enrolled_users($context, 'mod/kalvidassign:view', 0, 'u.id')) {
        foreach ($students as $student) {
            if (isset($unmarkedsubmissions[$kalvidassignment->id][$student->id])) {
                $submissions++;
            }
        }
    }

    if ($submissions) {
        $urlparams = array('id' => $kalvidassignment->coursemodule, 'action' => 'grading');
        $url = new moodle_url('/mod/kalvidassign/view.php', $urlparams);
        $gradedetails = '<div class="details">' .
                '<a href="' . $url . '">' .
                get_string('submissionsnotgraded', 'assign', $submissions) .
                '</a></div>';
        return $gradedetails;
    } else {
        return false;
    }

}
