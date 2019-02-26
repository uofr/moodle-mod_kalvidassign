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
 * Kaltura video assignment renderer class
 *
 * @package    mod_kalvidassign
 * @copyright  (C) 2013 onwards Remote-Learner {@link http://www.remote-learner.ca/}
 * @copyright  (C) 2016-2017 Yamaguchi University <gh-cc@mlex.cc.yamaguchi-u.ac.jp>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once(dirname(dirname(dirname(__FILE__))) . '/lib/tablelib.php');
require_once(dirname(dirname(dirname(__FILE__))) . '/lib/moodlelib.php');
require_once(dirname(dirname(dirname(__FILE__))) . '/local/kaltura/locallib.php');
require_once(dirname(dirname(dirname(__FILE__))) . '/local/kaltura/kaltura_entries.class.php');

if (!defined('MOODLE_INTERNAL')) {
    // It must be included from a Moodle page.
    die('Direct access to this script is forbidden.');
}

require_login();

/**
 * Table class for displaying media submissions for grading.
 * @package   mod_kalvidassign
 * @copyright (C) 2016-2017 Yamaguchi University <gh-cc@mlex.cc.yamaguchi-u.ac.jp>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class submissions_table extends table_sql {

    /** @var quicgrade is enable. */
    protected $_quickgrade;
    /** @var gradeinfo */
    protected $_gradinginfo;
    /** @var instance of cntext module. */
    protected $_cminstance;
    /** @var maximum grade set by teacher. */
    protected $_grademax;
    /** @var maximum columns */
    protected $_cols = 20;
    /** @var maximum rows */
    protected $_rows = 4;
    /** @var time of first submission */
    protected $_tifirst;
    /** @var time of last submission */
    protected $_tilast;
    /** @var page number */
    protected $_page;
    /** @var array of entries. */
    protected $_entries;
    /** @var teacher can acecss all groups */
    protected $_access_all_groups = false;
    /** @var Does client connect to kaltura server ? */
    protected $_connection = false;

    /**
     * This function is a cunstructor of renderer class.
     * @param int $uniqueid - id of target submission.
     * @param object $cm - object of Kaltura Media assignment module.
     * @param object $gradinginfo - grading information object.
     * @param bool $quickgrade - true/false of quick grade is on.
     * @param string $tifirst - time of first submission.
     * @param string $tilast - time of last submission.
     * @param int $page - number of view page.
     * @param array $entries - array of submissions.
     * @param object $connection - connection object between client and Kaltura server.
     */
    public function __construct($uniqueid, $cm, $grading_info, $quickgrade = false,
                         $tifirst = '', $tilast = '', $page = 0, $entries = array(),
                         $connection) {

        global $DB;

        parent::__construct($uniqueid);

        $this->_quickgrade = $quickgrade;
        $this->_gradinginfo = $grading_info;

        $instance = $DB->get_record('kalvidassign', array('id' => $cm->instance),
                                    'id,grade');

        $instance->cmid = $cm->id;

        $this->_cminstance = $instance;

        $this->_grademax = $this->_gradinginfo->items[0]->grademax;

        $this->_tifirst      = $tifirst;
        $this->_tilast       = $tilast;
        $this->_page         = $page;
        $this->_entries      = $entries;
        $this->_connection   = $connection;

    }

    /**
     * This function return HTML markup of picture of student.
     * @param object $data - user data.
     * @return string - HTML markup of picture of user.
     */
    public function col_picture($data) {
        global $OUTPUT;

        $user = new stdClass();
        $user->id           = $data->id;
        $user->picture      = $data->picture;
        $user->imagealt     = $data->imagealt;
        $user->firstname    = $data->firstname;
        $user->lastname     = $data->lastname;
        $user->email        = $data->email;
        $user->firstnamephonetic = $data->firstnamephonetic;
        $user->lastnamephonetic = $data->lastnamephonetic;
        $user->middlename = $data->middlename;
        $user->alternatename = $data->alternatename;

        $output = $OUTPUT->user_picture($user);

        $attr = array('type' => 'hidden',
                     'name' => 'users['.$data->id.']',
                     'value' => $data->id);
        $output .= html_writer::empty_tag('input', $attr);

        return $output;
    }

    /**
     * This function return HTML markup for grade selecting.
     * @param object $data - user data.
     * @return string - HTML markup for grade selecting.
     */
    public function col_selectgrade($data) {
        global $CFG;

        $output      = '';
        $final_grade = false;

        if (array_key_exists($data->id, $this->_gradinginfo->items[0]->grades)) {

            $final_grade = $this->_gradinginfo->items[0]->grades[$data->id];

            if ($CFG->enableoutcomes) {

                $final_grade->formatted_grade = $this->_gradinginfo->items[0]->grades[$data->id]->str_grade;
            } else {

                // Equation taken from mod/assignment/lib.php display_submissions()
                $final_grade->formatted_grade = round($final_grade->grade,2) . ' / ' . round($this->_grademax,2);
            }
        }

        if (!is_bool($final_grade) && ($final_grade->locked || $final_grade->overridden) ) {

            $locked_overridden = 'locked';

            if ($final_grade->overridden) {
                $locked_overridden = 'overridden';
            }

            $attr = array('id' => 'g'.$data->id,
                          'class' => $locked_overridden);


            $output = html_writer::tag('div', $final_grade->formatted_grade, $attr);

        } else if (!empty($this->_quickgrade)) {

            $attributes = array();

            $grades_menu = make_grades_menu($this->_cminstance->grade);

            $default = array(-1 => get_string('nograde'));

            $grade = null;

            if (!empty($data->timemarked)) {
                $grade = $data->grade;
            }

            $output = html_writer::select($grades_menu, 'menu['.$data->id.']', $grade, $default, $attributes);

        } else {

            $output = get_string('nograde');

            if (!empty($data->timemarked)) {
                $output = $this->display_grade($data->grade);
            }
        }

        return $output;
    }


    /**
     * This function return HTML markup for submission comment.
     * @param object $data - user data.
     * @return string - HTML markup for submission comment.
     */
    public function col_submissioncomment($data) {

        $output      = '';
        $final_grade = false;

        if (array_key_exists($data->id, $this->_gradinginfo->items[0]->grades)) {
            $final_grade = $this->_gradinginfo->items[0]->grades[$data->id];
        }

        if ( (!is_bool($final_grade) && ($final_grade->locked || $final_grade->overridden)) ) {

            $output = shorten_text(strip_tags($data->submissioncomment), 15);

        } else if (!empty($this->_quickgrade)) {

            $param = array('id' => 'comments_' . $data->submitid,
                           'rows' => $this->_rows,
                           'cols' => $this->_cols,
                           'name' => 'submissioncomment['.$data->id.']');

            $output .= html_writer::start_tag('textarea', $param);
            $output .= $data->submissioncomment;
            $output .= html_writer::end_tag('textarea');

        } else {
            $output = shorten_text(strip_tags($data->submissioncomment), 15);
        }

        return $output;
    }

    /**
     * This function return HTML markup for marked grade.
     * @param object $data - user data.
     * @return string - HTML markup for marked grade.
     */
    public function col_grademarked($data) {

        $output = '';

        if (!empty($data->timemarked)) {
            $output = userdate($data->timemarked);
        }

        return $output;
    }

    /**
     * This function return HTML markup for modified time of submission.
     * @param object $data - user data.
     * @return string - HTML markup for modified time of submission.
     */
    public function col_timemodified($data) {

        $attr = array('id' => 'ts'.$data->id);

        $date_modified = $data->timemodified;
        $date_modified = is_null($date_modified) || empty($data->timemodified) ?
                            '' : userdate($date_modified);

        $output = html_writer::tag('div', $date_modified, $attr);

        $output .= html_writer::empty_tag('br');
        $output .= html_writer::start_tag('center');

        if (!empty($data->entry_id)) {

            $note = '';

            $attr = array('id' => 'video_' .$data->entry_id,
                          'class' => 'media_thumbnail_cl',
                          'style' => 'cursor:pointer;');

            // Check if connection to Kaltura can be established.
            if ($this->_connection) {

                if (!array_key_exists($data->entry_id, $this->_entries)) {
                    $note = get_string('grade_video_not_cache', 'kalvidassign');

                    /*
                     * If the entry has not yet been cached, force a call to retrieve the entry object
                     * from the Kaltura server so that the thumbnail can be displayed.
                     */
                    $entry_object = local_kaltura_get_ready_entry_object($data->entry_id, false);
                    $attr['src'] = $entry_object->thumbnailUrl;
                    $attr['alt'] = $entry_object->name;
                    $attr['title'] = $entry_object->name;
                } else {
                    // Retrieve object from cache.
                    $attr['src'] = $this->_entries[$data->entry_id]->thumbnailUrl;
                    $attr['alt'] = $this->_entries[$data->entry_id]->name;
                    $attr['title'] = $this->_entries[$data->entry_id]->name;
                }

                $output .= html_writer::tag('p', $note);

                $output .= html_writer::empty_tag('img', $attr);
            } else {
                $output .= html_writer::tag('p', get_string('cannotdisplaythumbnail', 'kalvidassign'));
            }

            $attr = array('id' => 'hidden_video_' .$data->entry_id,
                          'type' => 'hidden',
                          'value' => $data->entry_id);
            $output .= html_writer::empty_tag('input', $attr);

            $entryobject = local_kaltura_get_ready_entry_object($data->entry_id, false);

            if ($entryobject !== null) {
                list($modalwidth, $modalheight) = kalvidassign_get_popup_player_dimensions();
                $markup = '';

                if (KalturaMediaType::IMAGE == $entryobject->mediaType) {
                    // Determine if the mobile theme is being used.
                    $theme = core_useragent::get_device_type_theme();
                    $markup .= local_kaltura_create_image_markup($entryobject, $entryobject->name,
                                                                   $theme, $modalwidth, $modalheight);
                    $markup .= '<br><br>';
                } else {
                    $kalturahost = local_kaltura_get_host();
                    $partnerid = local_kaltura_get_partner_id();
                    $uiconfid = local_kaltura_get_player_uiconf('player_resource');
										$ksession = local_kaltura_generate_kaltura_session(array($data->entry_id));
                    $now = time();
										
                    $markup .= "<iframe src=\"" . $kalturahost . "/p/" . $partnerid . "/sp/" . $partnerid . "00";
                    $markup .= "/embedIframeJs/uiconf_id/" . $uiconfid . "/partnerid/" . $partnerid;
                    $markup .= "?iframeembed=true&playerId=kaltura_player_" . $now;
										$markup .= '&ks='.$ksession;
										$markup .= "&entry_id=" . $data->entry_id . "\" width=\"" . $modalwidth . "\" height=\"" . $modalheight . "\" ";
                    $markup .= "allowfullscreen webkitallowfullscreen mozAllowFullScreen frameborder=\"0\"></iframe>";
                }

                $attr = array('id' => 'hidden_markup_' . $data->entry_id,
                              'style' => 'display: none;');
                $output .= html_writer::start_tag('div', $attr);
                $output .= $markup;
                $output .= html_writer::end_tag('div');

            }
        }

        $output .= html_writer::end_tag('center');

        return $output;
    }

    /**
     * This function return HTML markup for grade.
     * @param object $data - user data.
     * @return string - HTML markup forgrade.
     */
    public function col_grade($data) {
        $final_grade = false;

        if (array_key_exists($data->id, $this->_gradinginfo->items[0]->grades)) {
            $final_grade = $this->_gradinginfo->items[0]->grades[$data->id];
        }

        $final_grade = (!is_bool($final_grade)) ? $final_grade->str_grade : '-';

        $attr = array('id' => 'finalgrade_'.$data->id);
        $output = html_writer::tag('span', $final_grade, $attr);

        return $output;
    }

    /**
     * This function return HTML markup about submission modified timestamp.
     * @param object $data - object of submission.
     * @return string - HTML markup.
     */
    public function col_timemarked($data) {

        $output = '-';

        if (0 < $data->timemarked) {

                $attr = array('id' => 'tt'.$data->id);
                $output = html_writer::tag('div', userdate($data->timemarked), $attr);

        } else {
            $output = '-';
        }

        return $output;
    }


    /**
     * This function return HTML markup for status.
     * @param object $data - user data.
     * @return string - HTML markup for status of submission.
     */
    public function col_status($data) {

        require_once(dirname(dirname(dirname(__FILE__))) . '/lib/weblib.php');

        $url = new moodle_url('/mod/kalvidassign/single_submission.php',
                                    array('cmid' => $this->_cminstance->cmid,
                                          'userid' => $data->id,
                                          'sesskey' => sesskey()));

        if (!empty($this->_tifirst)) {
            $url->param('tifirst', $this->_tifirst);
        }

        if (!empty($this->_tilast)) {
            $url->param('tilast', $this->_tilast);
        }

        if (!empty($this->_page)) {
            $url->param('page', $this->_page);
        }

        $buttontext = '';
        if ($data->timemarked > 0) {
            $class = 's1';
            $buttontext = get_string('update');
        } else {
            $class = 's0';
            $buttontext  = get_string('grade');
        }

        $attr = array('id' => 'up'.$data->id,
                      'class' => $class);

        $output = html_writer::link($url, $buttontext, $attr);

        return $output;

    }

    /**
     *  Return a grade in user-friendly form, whether it's a scale or not
     *
     * @param mixed $grade - grading point (int) or message (ex. "non", "yet", etc.)
     * @return string - User-friendly representation of grade.
     *
     * TODO: Move this to locallib.php
     */
    public function display_grade($grade) {
        global $DB;

        static $kalscalegrades = array();   // Cache scales for each assignment - they might have different scales!!

        if ($this->_cminstance->grade >= 0) { // Normal number.
            if ($grade == -1) {
                return '-';
            } else {
                return $grade.' / '.$this->_cminstance->grade;
            }

        } else { // Scale.

            if (empty($kalscalegrades[$this->_cminstance->id])) {

                if ($scale = $DB->get_record('scale', array('id' => -($this->_cminstance->grade)))) {

                    $kalscalegrades[$this->_cminstance->id] = make_menu_from_list($scale->scale);
                } else {

                    return '-';
                }
            }

            if (isset($kalscalegrades[$this->_cminstance->id][$grade])) {
                return $kalscalegrades[$this->_cminstance->id][$grade];
            }
            return '-';
        }
    }

}

/**
 * Renderer class of Kaltura media submissions.
 * @package   mod_kalvidassign
 * @copyright (C) 2016-2017 Yamaguchi University <gh-cc@mlex.cc.yamaguchi-u.ac.jp>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_kalvidassign_renderer extends plugin_renderer_base {

    /**
     * This function display media submission.
     * @param object $entryobj - object of media entry.
     * @return string - HTML markup to display submission.
     */
    public function display_submission($entry_obj = null) {
        global $CFG, $OUTPUT;

        $img_source = '';
        $img_name   = '';

        $html = '';

        $html .= $OUTPUT->heading(get_string('submission', 'kalvidassign'), 3);

        $html .= html_writer::start_tag('p');

        // Tabindex -1 is required in order for the focus event to be capture amongst all browsers.
        $attr = array('id' => 'notification',
                      'class' => 'notification',
                      'tabindex' => '-1');
        $html .= html_writer::tag('div', '', $attr);

        if (!empty($entry_obj)) {

            $img_name   = $entry_obj->name;
            $img_source = $entry_obj->thumbnailUrl;

        } else {
            $img_name   = 'Video submission';
            $img_source = $CFG->wwwroot . '/local/kaltura/pix/vidThumb.png';
        }


        $attr = array('id' => 'media_thumbnail',
                      'src' => $img_source,
                      'alt' => $img_name,
                      'title' => $img_name,
                      'style' => 'z-index: -2');

        $html .= html_writer::empty_tag('img', $attr);

        $html .= html_writer::end_tag('p');

        return $html;

    }

    /**
     * This function display header of form.
     * @param object $kalmediaobj - kalvidassign object.
     * @return string - HTML markup for header part of form.
     */
    public function display_mod_header($kalmediaobj) {

        $html = '';

        $html .= $this->output->container_start('introduction');
        $html .= $this->output->heading($kalmediaobj->name, 2);
        $html .= $this->output->spacer(array('height' => 10));
        $html .= $this->output->box_start('generalbox introduction');
        $html .= $kalmediaobj->intro;
        $html .= $this->output->box_end();
        $html .= $this->output->container_end();
        $html .= $this->output->spacer(array('height' => 20));

        return $html;
    }

    /**
     * This function display summary of grading.
     * @param object $cm - module context object.
     * @param object $kalmediaobj - kalmediaassign object.
     * @param object $coursecontext - course context object which kalmediaassign module is placed.
     * @return string - HTML markup for gurading summary.
     */
    public function display_grading_summary($cm, $kalmediaobj, $coursecontext) {
        global $DB;

        $html = '';

        if (!has_capability('mod/kalvidassign:gradesubmission', $coursecontext)) {
             return '';
        }

        $html .= $this->output->container_start('gradingsummary');
        $html .= $this->output->heading(get_string('gradingsummary', 'kalvidassign'), 3);
        $html .= $this->output->box_start('generalbox gradingsummary');

        $table = new html_table();
        $table->attributes['class'] = 'generaltable';

        $roleid = 0;
        $roledata = $DB->get_records('role', array('shortname' => 'student'));
        foreach ($roledata as $row) {
            $roleid = $row->id;
        }

        $nummembers = $DB->count_records('role_assignments',
                                          array('contextid' => $coursecontext->id,
                                                'roleid' => $roleid)
                                         );

        $row = new html_table_row();
        $cell1 = new html_table_cell(get_string('numberofmembers', 'kalvidassign'));
        $cell1->attributes['style'] = '';
        $cell1->attributes['width'] = '25%';
        $cell2 = new html_table_cell($nummembers);
        $cell2->attributes['style'] = '';
        $row->cells = array($cell1, $cell2);
        $table->data[] = $row;

        $csql = "select count(*) " .
                "from {kalvidassign_submission} " .
                "where vidassignid = :vidassignid " .
                "and timecreated > :timecreated ";
        $param = array('vidassignid' => $kalmediaobj->id, 'timecreated' => 0);
        $numsubmissions = $DB->count_records_sql($csql, $param);

        $row = new html_table_row();
        $cell1 = new html_table_cell(get_string('numberofsubmissions', 'kalvidassign'));
        $cell1->attributes['style'] = '';
        $cell1->attributes['width'] = '25%';
        $cell2 = new html_table_cell($numsubmissions);
        $cell2->attributes['style'] = '';
        $row->cells = array($cell1, $cell2);
        $table->data[] = $row;

        $users = kalvidassign_get_submissions($cm->instance, KALASSIGN_REQ_GRADING);

        if (empty($users)) {
            $users = array();
        }

        $students = kalvidassign_get_assignment_students($cm);

        $numrequire = 0;

        $query = "select count({user}.id) as num from {role_assignments} " .
                 "join {user} on {user}.id={role_assignments}.userid and " .
                 "{role_assignments}.contextid='$coursecontext->id' and " .
                 "{role_assignments}.roleid='$roleid' " .
                 "left join {kalvidassign_submission} ".
                 "on {kalvidassign_submission}.userid = {user}.id and " .
                 "{kalvidassign_submission}.vidassignid = $cm->instance " .
                 "where {kalvidassign_submission}.timemarked < {kalvidassign_submission}.timemodified and " .
                 "{user}.deleted = 0";

        if (!empty($users) && $users !== array()) {
            $users = array_intersect(array_keys($users), array_keys($students));
            $query = $query . " and {user}.id in (" . implode(',', $users). ")";
        }

        $result = $DB->get_recordset_sql($query);

        foreach ($result as $row) {
            $numrequire = $row->num;
        }

        $row = new html_table_row();
        $cell1 = new html_table_cell(get_string('numberofrequiregrading', 'kalvidassign'));
        $cell1->attributes['style'] = '';
        $cell1->attributes['width'] = '25%';
        $cell2 = new html_table_cell($numrequire);
        $cell2->attributes['style'] = '';
        $row->cells = array($cell1, $cell2);
        $table->data[] = $row;

        $row = new html_table_row();
        $cell1 = new html_table_cell(get_string('availabledate', 'kalvidassign'));
        $cell1->attributes['style'] = '';
        $cell1->attributes['width'] = '25%';
        $cell2 = new html_table_cell('-');

        if (!empty($kalmediaobj->timeavailable)) {
            $str = userdate($kalmediaobj->timeavailable);
            if (!kalvidassign_assignment_submission_opened($kalmediaobj)) {
                $str = html_writer::start_tag('font', array('color' => 'blue')) . $str;
                $str .= ' (' . get_string('submissionnotopened', 'kalvidassign'). ')';
                $str .= html_writer::end_tag('font');
            }

            $cell2 = new html_table_cell($str);
        }

        $cell2->attributes['style'] = '';
        $row->cells = array($cell1, $cell2);
        $table->data[] = $row;

        $row = new html_table_row();
        $cell1 = new html_table_cell(get_string('duedate', 'kalvidassign'));
        $cell1->attributes['style'] = '';
        $cell1->attributes['width'] = '25%';
        $cell2 = new html_table_cell('-');

        if (!empty($kalmediaobj->timedue)) {
            $str = userdate($kalmediaobj->timedue);
            if (kalvidassign_assignment_submission_expired($kalmediaobj)) {
                $str = html_writer::start_tag('font', array('color' => 'red')) . $str;
                $str .= ' (' . get_string('submissionexpired', 'kalvidassign') . ')';
                $str .= html_writer::end_tag('font');
            }

            $cell2 = new html_table_cell($str);
        }

        $cell2->attributes['style'] = '';
        $row->cells = array($cell1, $cell2);
        $table->data[] = $row;

        $row = new html_table_row();
        $cell1 = new html_table_cell(get_string('remainingtime', 'kalvidassign'));
        $cell1->attributes['style'] = '';
        $cell1->attributes['width'] = '25%';
        $cell2 = new html_table_cell('-');

        if (!empty($kalmediaobj->timedue)) {
            $remain = kalvidassign_get_remainingdate($kalmediaobj->timedue);
            $cell2 = new html_table_cell($remain);
        }

        $cell2->attributes['style'] = '';
        $row->cells = array($cell1, $cell2);
        $table->data[] = $row;

        $html .= html_writer::table($table);

        $html .= $this->output->box_end();
        $html .= $this->output->container_end();
        $html .= $this->output->spacer(array('height' => 20));

        return $html;
    }
	
    /**
     * This function display submission status.
     * @param object $cm - module context object.
     * @param object $kalmediaobj - kalmediaassign object.
     * @param object $coursecontext - course context object which kalmediaassign module is placed.
     * @return string - HTML markup for submission status.
     */
    public function display_submission_status($cm, $kalmediaobj, $coursecontext) {
        global $DB, $USER;

        $html = '';

        if (!has_capability('mod/kalvidassign:submit', $coursecontext)) {
            return '';
        }

        $html .= $this->output->container_start('submissionstatus');
        $html .= $this->output->heading(get_string('submissionstatus', 'kalvidassign'), 3);
        $html .= $this->output->box_start('generalbox submissionstatus');

        $table = new html_table();
        $table->attributes['class'] = 'generaltable';
        $submissionstatus = get_string('status_nosubmission', 'kalvidassign');
        $gradingstatus = get_string('status_nomarked', 'kalvidassign');

        if (! $kalvidassign = $DB->get_record('kalvidassign', array("id" => $cm->instance))) {
            print_error('invalidid', 'kalvidassign');
        }

        $param = array('vidassignid' => $kalvidassign->id, 'userid' => $USER->id);
        $submission = $DB->get_record('kalvidassign_submission', $param);

        if (!empty($submission) and !empty($submission->entry_id)) {
            $submissionstatus = get_string('status_submitted', 'kalvidassign');
        }

        if (!empty($submission) and !empty($submission->timecreated) and
            $submission->timemarked > 0 and $submission->timemarked > $submission->timecreated and
            $submission->timemarked > $submission->timemodified) {
            $gradingstatus = get_string('status_marked', 'kalvidassign');
        }

        $row = new html_table_row();
        $col1 = new html_table_cell(get_string('submissionstatus', 'kalvidassign'));
        $col1->attributes['style'] = '';
        $col1->attributes['width'] = '25%';
        $col2 = new html_table_cell($submissionstatus);
        $col2->attributes['style'] = '';
        $row->cells = array($col1, $col2);
        $table->data[] = $row;

        $row = new html_table_row();
        $col1 = new html_table_cell(get_string('gradingstatus', 'kalvidassign'));
        $col1->attributes['style'] = '';
        $col1->attributes['width'] = '25%';
        $col2 = new html_table_cell($gradingstatus);
        $col2->attributes['style'] = '';
        $row->cells = array($col1, $col2);
        $table->data[] = $row;

        $row = new html_table_row();
        $col1 = new html_table_cell(get_string('availabledate', 'kalvidassign'));
        $col1->attributes['style'] = '';
        $col1->attributes['width'] = '25%';

        if (!empty($kalmediaobj->timeavailable)) {
            $str = userdate($kalmediaobj->timeavailable);
            if (!kalvidassign_assignment_submission_opened($kalmediaobj)) {
                $str = html_writer::start_tag('font', array('color' => 'blue')) . $str;
                $str .= ' (' . get_string('submissionnotopened', 'kalvidassign'). ')';
                $str .= html_writer::end_tag('font');
            }

            $col2 = new html_table_cell($str);
        } else {
            $col2 = new html_table_cell('-');
        }

        $col2->attributes['style'] = '';
        $row->cells = array($col1, $col2);
        $table->data[] = $row;

        $row = new html_table_row();
        $col1 = new html_table_cell(get_string('duedate', 'kalvidassign'));
        $col1->attributes['style'] = '';
        $col1->attributes['width'] = '25%';

        if (!empty($kalmediaobj->timedue)) {
            $str = userdate($kalmediaobj->timedue);
            if (kalvidassign_assignment_submission_expired($kalmediaobj)) {
                $str = html_writer::start_tag('font', array('color' => 'red')) . $str;
                $str .= ' (' . get_string('submissionexpired', 'kalvidassign'). ')';
                $str .= html_writer::end_tag('font');
            }

            $col2 = new html_table_cell($str);
        } else {
            $col2 = new html_table_cell('-');
        }

        $col2->attributes['style'] = '';
        $row->cells = array($col1, $col2);
        $table->data[] = $row;

        $row = new html_table_row();
        $col1 = new html_table_cell(get_string('remainingtime', 'kalvidassign'));
        $col1->attributes['style'] = '';
        $col1->attributes['width'] = '25%';

        if (!empty($kalmediaobj->timedue)) {
            $remain = kalvidassign_get_remainingdate($kalmediaobj->timedue);
            $col2 = new html_table_cell($remain);
        } else {
            $col2 = new html_table_cell('-');
        }

        $col2->attributes['style'] = '';
        $row->cells = array($col1, $col2);
        $table->data[] = $row;

        $row = new html_table_row();
        $col1 = new html_table_cell(get_string('status_timemodified', 'kalvidassign'));
        $col1->attributes['style'] = '';
        $col1->attributes['width'] = '25%';

        if (!empty($submission->timemodified)) {
            $str = userdate($submission->timemodified);
            if ($submission->timemodified > $kalmediaobj->timedue) {
                $str = html_writer::start_tag('font', array('color' => 'red')) . $str;
                $str .= ' (' . get_string('latesubmission', 'kalvidassign'). ')';
                $str .= html_writer::end_tag('font');
            }

            $col2 = new html_table_cell($str);
        } else {
            $col2 = new html_table_cell('-');
        }

        $col2->attributes['style'] = '';
        $row->cells = array($col1, $col2);
        $table->data[] = $row;

        $html .= html_writer::table($table);

        $html .= $this->output->box_end();
        $html .= $this->output->container_end();
        $html .= $this->output->spacer(array('height' => 20));

        return $html;
    }
	

function display_mod_info($kalvideoobj, $context) {
        global $DB;

        $html = '';

        if (!empty($kalvideoobj->timeavailable)) {
            $html .= html_writer::start_tag('p');
            $html .= html_writer::tag('b', get_string('availabledate', 'kalvidassign') . ': ');
            $html .= userdate($kalvideoobj->timeavailable);
            $html .= html_writer::end_tag('p');
        }

        if (!empty($kalvideoobj->timedue)) {
            $html .= html_writer::start_tag('p');
            $html .= html_writer::tag('b', get_string('duedate', 'kalvidassign') . ': ');
            $html .= userdate($kalvideoobj->timedue);
            $html .= html_writer::end_tag('p');
        }

        // Display a count of the numuber of submissions
        if (has_capability('mod/kalvidassign:gradesubmission', $context)) {

            $param = array('vidassignid' => $kalvideoobj->id,
                           'timecreated' => 0,
                           'timemodified' => 0);

            $csql = "SELECT COUNT(*) ".
                    "FROM {kalvidassign_submission} ".
                    "WHERE vidassignid = :vidassignid ".
                    "  AND (timecreated > :timecreated ".
                    "  OR timemodified > :timemodified) ";

            $count = $DB->count_records_sql($csql, $param);

            if ($count) {
                $html .= html_writer::start_tag('p');
                $html .= get_string('numberofsubmissions', 'kalvidassign', $count);
                $html .= html_writer::end_tag('p');
            }

        }

        return $html;
    }

    /**
     * This function return HTML markup for submit button for student.
     * @param object $cm - module context object.
     * @param bool $disablesubmit - User can submit media to this assignment.
     * @return string - HTML markup for submit button for student.
     */
    public function display_student_submit_buttons($cm, $disablesubmit = false) {

        $html = '';

        $target = new moodle_url('/mod/kalvidassign/submission.php');

        $attr = array('method'=>'POST', 'action'=>$target);

        $html .= html_writer::start_tag('form', $attr);

        $attr = array('type' => 'hidden',
                     'name' => 'entry_id',
                     'id' => 'entry_id',
                     'value' => '');
        $html .= html_writer::empty_tag('input', $attr);

        $attr = array('type' => 'hidden',
                     'name' => 'cmid',
                     'value' => $cm->id);
        $html .= html_writer::empty_tag('input', $attr);

        $attr = array('type' => 'hidden',
                     'name' => 'sesskey',
                     'value' => sesskey());
        $html .= html_writer::empty_tag('input', $attr);

        $attr = array('type' => 'button',
                      'class' => 'btn btn-primary mr-2',
                     'id' => 'id_add_media',
                     'name' => 'add_media',
                     'data-toggle' => 'modal',
                     'data-target' => '#video_selector_modal',
                     'value' => get_string('addvideo', 'kalvidassign'));

        if ($disablesubmit) {
            $attr['disabled'] = 'disabled';
        }

        $html .= html_writer::empty_tag('input', $attr);

        $attr = array('type' => 'submit',
                      'class' => 'btn btn-secondary mr-2',
                     'name' => 'submit_media',
                     'id' => 'submit_media',
                     'disabled' => 'disabled',
                     'value' => get_string('submitvideo', 'kalvidassign'));

        $html .= html_writer::empty_tag('input', $attr);

        $html .= html_writer::end_tag('form');

        return $html;
    }

    /**
     * This function display resubmit button.
     * @param object $cm - module context object.
     * @param int $userid - id of user (student).
     * @param bool $disablesubmit - User can submit media to this assignment.
     * @return string - HTML markup to display resubmit button.
     */
    public function display_student_resubmit_buttons($cm, $userid, $disablesubmit = false) {
        global $DB;

        $param = array('vidassignid' => $cm->instance, 'userid' => $userid);
        $submissionrec = $DB->get_record('kalvidassign_submission', $param);

        $html = '';

        $target = new moodle_url('/mod/kalvidassign/submission.php');

        $attr = array('method'=>'POST', 'action'=>$target);

        $html .= html_writer::start_tag('form', $attr);

        $attr = array('type' => 'hidden',
                     'name'  => 'cmid',
                     'value' => $cm->id);
        $html .= html_writer::empty_tag('input', $attr);

        $attr = array('type' => 'hidden',
                     'name'  => 'entry_id',
                     'id'    => 'entry_id',
                     'value' => $submissionrec->entry_id);
        $html .= html_writer::empty_tag('input', $attr);

        $attr = array('type' => 'hidden',
                     'name'  => 'sesskey',
                     'value' => sesskey());
        $html .= html_writer::empty_tag('input', $attr);

        // Add submit and review buttons.
        $attr = array('type' => 'button',
                      'class' => 'btn btn-primary mr-2',
                     'name' => 'add_media',
                     'id' => 'id_add_media',
                     'value' => get_string('replacevideo', 'kalvidassign'),
                     'data-toggle' => 'modal',
                     'data-target' => '#video_selector_modal');

        if ($disablesubmit) {
            $attr['disabled'] = 'disabled';
        }

        $html .= html_writer::empty_tag('input', $attr);

        $attr = array('type' => 'submit',
                      'class' => 'btn btn-secondary mr-2',
                      'id'   => 'submit_media',
                      'name' => 'submit_media',
                      'disabled' => 'disabled',
                      'value' => get_string('submitvideo', 'kalvidassign'));

        if ($disablesubmit) {
            $attr['disabled'] = 'disabled';
        }

        $html .= html_writer::empty_tag('input', $attr);

        $html .= html_writer::end_tag('form');

        return $html;

    }

    /**
     * This function display buttons for instructor.
     * @param object $cm - module context object.
     * @return string - HTML markup to display buttons for instructor.
     */
    public function display_instructor_buttons($cm) {

        $html = '';

        $target = new moodle_url('/mod/kalvidassign/grade_submissions.php');

        $attr = array('method'=>'POST', 'action'=>$target);

        $html .= html_writer::start_tag('form', $attr);

        $html .= html_writer::start_tag('center');

        $attr = array('type' => 'hidden',
                     'name' => 'sesskey',
                     'value' => sesskey());
        $html .= html_writer::empty_tag('input', $attr);

        $attr = array('type' => 'hidden',
                     'name' => 'cmid',
                     'value' => $cm->id);
        $html .= html_writer::empty_tag('input', $attr);

        $attr = array('type' => 'submit',
                      'class' => 'btn btn-primary',
                     'name' => 'grade_submissions',
                     'value' => get_string('gradesubmission', 'kalvidassign'));

        $html .= html_writer::empty_tag('input', $attr);

        $html .= html_writer::end_tag('center');

        $html .= html_writer::end_tag('form');

        return $html;
    }

    /**
     * This function display submissions table.
     * @param object $cm - module context object.
     * @param int $groupfilter - group filter option.
     * @param string $filter - view filer option.
     * @param int $perpage - submissions per page.
     * @param bool $quickgrade - if quick grade is elable, return "true". Otherwise return "false".
     * @param string $tifirst - first time of submissions.
     * @param string $tilast - lasttime of submissions.
     * @param int $page - number of page.
     */
    public function display_submissions_table($cm, $group_filter = 0, $filter = 'all', $perpage, $quickgrade = false,
                                       $tifirst = '', $tilast = '', $page = 0) {

        global $DB, $OUTPUT, $COURSE, $USER;

        $kalturahost = local_kaltura_get_host();
        $partnerid = local_kaltura_get_partner_id();
        $uiconfid = local_kaltura_get_player_uiconf('player_resource');

        $mediawidth = 0;
        $mediaheight = 0;

        list($modalwidth, $modalheight) = kalvidassign_get_popup_player_dimensions();
        $mediawidth = $modalwidth - KALTURA_POPUP_WIDTH_ADJUSTMENT;
        $mediaheight = $modalheight - KALTURA_POPUP_HEIGHT_ADJUSTMENT;

        // Get a list of users who have submissions and retrieve grade data for those users.
        $users = kalvidassign_get_submissions($cm->instance, $filter);

        $define_columns = array('picture', 'fullname', 'selectgrade', 'submissioncomment', 'timemodified',
                                'timemarked', 'status', 'grade');

        if (empty($users)) {
            $users = array();
        }

        $entryids = array();
        $entries = array();
        foreach ($users as $usersubmission) {
            $entryids[$usersubmission->entry_id] = $usersubmission->entry_id;
        }

        if (!empty($entryids)) {
            $client_obj = local_kaltura_login(true);

            if ($client_obj) {
                $entries = new KalturaStaticEntries();
                $entries = KalturaStaticEntries::listEntries($entryids, $client_obj->baseEntry);
            } else {
                echo $OUTPUT->notification(get_string('conn_failed_alt', 'local_kaltura'));
            }
        }

        /*
         *  Compare student who have submitted to the assignment with students who are
         * currently enrolled in the course.
         */
        $students = kalvidassign_get_assignment_students($cm);

        $allstudents = array();

        foreach ($students as $s) {
            $allstudents[] = $s->id;
        }

        $users = array_intersect(array_keys($users), array_keys($students));

        $grading_info = grade_get_grades($cm->course, 'mod', 'kalvidassign', $cm->instance, $users);

        $where = '';
        switch ($filter) {
            case KALASSIGN_SUBMITTED:
                $where = ' kvs.timemodified > 0 AND ';
                break;
            case KALASSIGN_REQ_GRADING:
                $where = ' kvs.timemarked < kvs.timemodified AND ';
                break;
        }

        // Determine logic needed for groups mode
        $param         = array();
        $groups_where  = '';
        $groups_column = '';
        $groups_join   = '';
        $groups        = array();
        $group_ids     = '';
        $context       = context_course::instance($COURSE->id);

        // Get all groups that the user belongs to, check if the user has capability to access all groups
        if (!has_capability('moodle/site:accessallgroups', $context, $USER->id)) {
            $groups    = groups_get_all_groups($COURSE->id, $USER->id);

            if (empty($groups)) {
                $message = get_string('nosubmissions', 'kalvidassign');
                echo html_writer::tag('center', $message);
                return;
            }
        } else {
            $groups = groups_get_all_groups($COURSE->id, $USER->id);
        }

        // Create a comma separated list of group ids
        foreach ($groups as $group) {
            $group_ids .= $group->id . ',';
        }

        $group_ids = rtrim($group_ids, ',');

        if ('' !== $group_ids) {
            switch (groups_get_activity_groupmode($cm)) {
                case NOGROUPS:
                    // No groups, do nothing.
                    break;
                case SEPARATEGROUPS:

                    /*
                     * If separate groups, but displaying all users then we must display only users
                     * who are in the same group as the current user.
                     */
                    if (0 == $group_filter) {
                        $groups_column = ', {groups_members}.groupid ';
                        $groups_join = ' RIGHT JOIN {groups_members} ON {groups_members}.userid = {user}.id' .
                                      ' RIGHT JOIN {groups} ON {groups}.id = {groups_members}.groupid ';

                        $param['courseid'] = $COURSE->id;
                        $groups_where  .= ' AND {groups}.courseid = :courseid ';

                        $param['groupid'] = $groupfilter;
                        $groups_where .= ' AND {groups}.id IN ('. $group_ids . ') ';

                }

            case VISIBLEGROUPS:

                     /*
                      * If visible groups but displaying a specific group then we must display users within
                      * that group, if displaying all groups then display all users in the course.
                      */
                    if (0 != $group_filter) {
                        $groups_column = ', {groups_members}.groupid ';
                        $groups_join = ' RIGHT JOIN {groups_members} ON {groups_members}.userid = u.id' .
                                      ' RIGHT JOIN {groups} ON {groups}.id = {groups_members}.groupid ';

                        $param['courseid'] = $COURSE->id;
                        $groups_where .= ' AND {groups_members}.courseid = :courseid ';

                        $param['groupid'] = $groupfilter;
                        $groups_where .= ' AND {groups_members}.groupid = :groupid ';

                    }
                    break;
            }
        }

        $kaltura    = new kaltura_connection();
        $connection = $kaltura->get_connection(true, KALTURA_SESSION_LENGTH);
        $table      = new submissions_table('kal_video_submit_table', $cm, $grading_info, $quickgrade,
                                            $tifirst, $tilast, $page, $entries, $connection);

        $roleid = 0;

        $roledata = $DB->get_records('role', array('shortname' => 'student'));

        foreach ($roledata as $row) {
            $roleid = $row->id;
        }

        /*
         * In order for the sortable first and last names to work.
         * User ID has to be the first column returned and must be returned as id.
         * Otherwise the table will display links to user profiles that are incorrect or do not exist.
         */
        $columns = '{user}.id, {kalvidassign_submission}.id submitid, {user}.firstname, {user}.lastname, ' .
                   '{user}.picture, {user}.firstnamephonetic, {user}.lastnamephonetic, {user}.middlename, ' .
                   '{user}.alternatename, {user}.imagealt, {user}.email, '.
                   '{kalvidassign_submission}.grade, {kalvidassign_submission}.submissioncomment, ' .
                   '{kalvidassign_submission}.timemodified, {kalvidassign_submission}.entry_id, ' .
                   '{kalvidassign_submission}.timemarked, ' .
                   ' 1 as status, 1 as selectgrade' . $groups_column;
        $where .= ' {user}.deleted = 0 ';

        if ($filter == KALASSIGN_NOTSUBMITTEDYET and $users !== array()) {
            $where .= ' and {user}.id not in (' . implode(',', $users) . ') ';
        } else {
            if (($filter == KALASSIGN_REQ_GRADING or $filter == KALASSIGN_SUBMITTED) and $users !== array()) {
                $where          .= ' and {user}.id in (' . implode(',', $users) . ') ';
            }
        }

        $where .= $groups_where;

        $param['instanceid'] = $cm->instance;
        $from = "{role_assignments} " .
                "join {user} on {user}.id={role_assignments}.userid and " .
                "{role_assignments}.contextid='$context->id' and {role_assignments}.roleid='$roleid' " .
                "left join {kalvidassign_submission} on {kalvidassign_submission}.userid = {user}.id and " .
                "{kalvidassign_submission}.vidassignid = :instanceid " .
                $groups_join;

        $baseurl        = new moodle_url('/mod/kalvidassign/grade_submissions.php',
                                        array('cmid' => $cm->id));

        $col1 = get_string('fullname', 'kalvidassign');
        $col2 = get_string('grade', 'kalvidassign');
        $col3 = get_string('submissioncomment', 'kalvidassign');
        $col4 = get_string('timemodified', 'kalvidassign');
        $col5 = get_string('grademodified', 'kalvidassign');
        $col6 = get_string('status', 'kalvidassign');
        $col7 = get_string('finalgrade', 'kalvidassign');

        $table->set_sql($columns, $from, $where, $param);
        $table->define_baseurl($baseurl);
        $table->collapsible(true);

        $table->define_columns($define_columns);
        $table->define_headers(array('', $col1, $col2, $col3, $col4, $col5, $col6, $col7));

        echo html_writer::start_tag('center');

        $attributes = array('action' => new moodle_url('grade_submissions.php'),
                            'id'     => 'fastgrade',
                            'method' => 'post');
        echo html_writer::start_tag('form', $attributes);

        $attributes = array('type' => 'hidden',
                            'name' => 'cmid',
                            'value' => $cm->id);
        echo html_writer::empty_tag('input', $attributes);

        $attributes['name'] = 'mode';
        $attributes['value'] = 'fastgrade';

        echo html_writer::empty_tag('input', $attributes);

        $attributes['name'] = 'sesskey';
        $attributes['value'] = sesskey();

        echo html_writer::empty_tag('input', $attributes);

        $table->out($perpage, true);

        if ($quickgrade) {
            $attributes = array('type' => 'submit',
                                'name' => 'save_feedback',
                                'value' => get_string('savefeedback', 'kalvidassign'));

            echo html_writer::empty_tag('input', $attributes);
        }

        echo html_writer::end_tag('form');

        echo html_writer::end_tag('center');

        $attr = array('type' => 'hidden', 'name' => 'kalturahost', 'id' => 'kalturahost', 'value' => $kalturahost);
        echo html_writer::empty_tag('input', $attr);

        $attr = array('type' => 'hidden', 'name' => 'partnerid', 'id' => 'partnerid', 'value' => $partnerid);
        echo html_writer::empty_tag('input', $attr);

        $attr = array('type' => 'hidden', 'name' => 'uiconfid', 'id' => 'uiconfid', 'value' => $uiconfid);
        echo html_writer::empty_tag('input', $attr);

        $attr = array('type' => 'hidden', 'name' => 'modalwidth', 'id' => 'modalwidth', 'value' => $modalwidth);
        echo html_writer::empty_tag('input', $attr);

        $attr = array('type' => 'hidden', 'name' => 'modalheight', 'id' => 'modalheight', 'value' => $modalheight);
        echo html_writer::empty_tag('input', $attr);

        $attr = array('id' => 'modal_content', 'style' => '');
        echo html_writer::start_tag('div', $attr);
        echo html_writer::end_tag('div');

    }

    /**
     * Displays the assignments listing table.
     *
     * @param object $course - The course odject.
     * @return nothing.
     */
    public function display_kalvidassignments_table($course) {
        global $CFG, $DB, $PAGE, $OUTPUT, $USER;

        echo html_writer::start_tag('center');

        if (!$cms = get_coursemodules_in_course('kalvidassign', $course->id, 'm.timedue')) {
            echo get_string('noassignments', 'kalvidassign');
            echo $OUTPUT->continue_button($CFG->wwwroot.'/course/view.php?id='.$course->id);
        }

        $strsectionname = get_string('sectionname', 'format_'.$course->format);
        $usesections = course_format_uses_sections($course->format);
        $modinfo = get_fast_modinfo($course);

        if ($usesections) {
            $sections = $modinfo->get_section_info_all();
        }
        $courseindexsummary = new kalvidassign_course_index_summary($usesections, $strsectionname);

        $assignmentcount = 0;

        foreach ($modinfo->instances['kalvidassign'] as $cm) {
            if (!$cm->uservisible) {
                continue;
            }

            $assignmentcount++;
            $timedue = $cms[$cm->id]->timedue;

            $sectionname = '';
            if ($usesections && $cm->sectionnum) {
                $sectionname = get_section_name($course, $sections[$cm->sectionnum]);
            }

            $submitted = '';
            $context = context_module::instance($cm->id);

            if (has_capability('mod/kalvidassign:gradesubmission', $context)) {
                $submitted = $DB->count_records('kalvidassign_submission', array('vidassignid' => $cm->instance));
            } else if (has_capability('mod/kalvidassign:submit', $context)) {
                if ($DB->count_records('kalvidassign_submission', array('vidassignid' => $cm->instance, 'userid' => $USER->id)) > 0) {
                    $submitted = get_string('submitted', 'mod_kalvidassign');
                } else {
                    $submitted = get_string('nosubmission', 'mod_kalvidassign');
                }
            }

            $gradinginfo = grade_get_grades($course->id, 'mod', 'kalvidassign', $cm->instance, $USER->id);
            if (isset($gradinginfo->items[0]->grades[$USER->id]) && !$gradinginfo->items[0]->grades[$USER->id]->hidden ) {
                $grade = $gradinginfo->items[0]->grades[$USER->id]->str_grade;
            } else {
                $grade = '-';
            }

            $courseindexsummary->add_assign_info($cm->id, $cm->name, $sectionname, $timedue, $submitted, $grade);
        }

        if ($assignmentcount > 0) {
            $pagerenderer = $PAGE->get_renderer('mod_kalvidassign');
            echo $pagerenderer->render($courseindexsummary);
        }

        echo html_writer::end_tag('center');
    }

    /**
     * Displays the YUI panel markup used to display the KCW
     *
     * @return string - HTML markup
     */
    function display_kcw_panel_markup() {

        $output = '';

        $attr = array('id' => 'video_panel');
        $output .= html_writer::start_tag('div', $attr);

        $attr = array('class' => 'hd');
        $output .= html_writer::tag('div', '', $attr);

        $attr = array('class' => 'bd');
        $output .= html_writer::tag('div', '', $attr);

        $output .= html_writer::end_tag('div');

        return $output;
    }

    /**
     * Displays the YUI panel markup used to display embedded video markup
     *
     * @return string - HTML markup
     */
    function display_video_preview_markup() {
        $output = '';

        $attr = array('id' => 'id_video_preview',
                      'class' => 'video_preview');
        $output .= html_writer::start_tag('div', $attr);

        $attr = array('class' => 'hd');
        $output .= html_writer::tag('div', get_string('video_preview_header', 'kalvidassign'), $attr);

        $attr = array('class' => 'bd');
        $output .= html_writer::tag('div', '', $attr);

        $output .= html_writer::end_tag('div');

        return $output;

    }

    /**
     * Displays the YUI panel markup used to display loading screen
     *
     * @return string - HTML markup
     */
    function display_loading_markup() {
        // Panel wait markup
        $output = '';

        $output .= html_writer::end_tag('div');

        $attr = array('id' => 'wait');
        $output .=  html_writer::start_tag('div', $attr);

        $attr = array('class' => 'hd');
        $output .= html_writer::tag('div', '', $attr);

        $attr = array('class' => 'bd');

        $output .= html_writer::tag('div', '', $attr);

        $output .= html_writer::end_tag('div');

        return $output;
    }

    function display_all_panel_markup() {
        $output = $this->display_kcw_panel_markup();
        $output .= $this->display_video_preview_markup();
        $output .= $this->display_loading_markup();

        return $output;
    }

    /**
     * Display the feedback to the student
     *
     * This default method prints the teacher picture and name, date when marked,
     * grade and teacher submissioncomment.
     *
     * @param object $kalmediaassign - The submission object or NULL in which case it will be loaded.
     * @param object $context - context object.
     * @return string - HTML markup for feedback.
     *
     * TODO: correct documentation for this function
     */
    public function display_grade_feedback($kalvidassign, $context) {
        global $USER, $CFG, $DB, $OUTPUT;

        require_once($CFG->libdir.'/gradelib.php');

        // Check if the user is enrolled to the coruse and can submit to the assignment
        if (!is_enrolled($context, $USER, 'mod/kalvidassign:submit')) {
            // Can not submit assignments -> no feedback
            return;
        }

        $gradinginfo = grade_get_grades($kalvidassign->course, 'mod', 'kalvidassign', $kalvidassign->id, $USER->id);

        $item = $gradinginfo->items[0];
        $grade = $item->grades[$USER->id];

        if ($grade->hidden || $grade->grade === false) { // Hidden or Error.
            return;
        }

        if ($grade->grade === null && empty($grade->str_feedback)) { // Nothing to show yet.
            return;
        }

        $graded_date = $grade->dategraded;
        $graded_by   = $grade->usermodified;

    /// We need the teacher info
        if (!$teacher = $DB->get_record('user', array('id'=>$graded_by))) {
            print_error('cannotfindteacher');
        }

        $html = '';

        $html .= $this->output->container_start('feedback');
        $html .= $this->output->heading(get_string('feedback', 'kalvidassign'), 3);
        $html .= $this->output->box_start('generalbox feedback');

        $table = new html_table();
        $table->attributes['class'] = 'generaltable';

        $row = new html_table_row();
        $cell1 = new html_table_cell(get_string('grade'));
        $cell1->attributes['style'] = '';
        $cell1->attributes['width'] = '25%';
        $cell2 = new html_table_cell($grade->str_long_grade);
        $cell2->attributes['style'] = '';
        $row->cells = array($cell1, $cell2);
        $table->data[] = $row;

        $row = new html_table_row();
        $cell1 = new html_table_cell(get_string('gradedon', 'kalvidassign'));
        $cell1->attributes['style'] = '';
        $cell1->attributes['width'] = '25%';
        $cell2 = new html_table_cell(userdate($graded_date));
        $cell2->attributes['style'] = '';
        $row->cells = array($cell1, $cell2);
        $table->data[] = $row;

        $row = new html_table_row();
        $cell1 = new html_table_cell(get_string('gradedby', 'kalvidassign'));
        $cell1->attributes['style'] = '';
        $cell1->attributes['width'] = '25%';
        $cell2 = new html_table_cell($OUTPUT->user_picture($teacher) . '&nbsp;&nbsp;' . fullname($teacher));
        $cell2->attributes['style'] = '';
        $row->cells = array($cell1, $cell2);
        $table->data[] = $row;

        $row = new html_table_row();
        $cell1 = new html_table_cell(get_string('feedbackcomment', 'kalvidassign'));
        $cell1->attributes['style'] = '';
        $cell1->attributes['width'] = '25%';
        $cell2 = new html_table_cell($grade->str_feedback);
        $cell2->attributes['style'] = '';
        $row->cells = array($cell1, $cell2);
        $table->data[] = $row;

        $html .= html_writer::table($table);

        $html .= $this->output->box_end();
        $html .= $this->output->container_end();
        $html .= $this->output->spacer(array('height' => 20));

        return $html;

    }

    /**
     * This function return course index summary.
     *
     * @param kalmediaassign_course_index_summary $indexsummary - Structure for index summary.
     * @return string - HTML markup for course index summary.
     */
    public function render_kalvidassign_course_index_summary(kalvidassign_course_index_summary $indexsummary) {
        $strplural = get_string('modulenameplural', 'kalvidassign');
        $strsectionname  = $indexsummary->courseformatname;
        $strduedate = get_string('duedate', 'kalvidassign');
        $strsubmission = get_string('submission', 'kalvidassign');
        $strgrade = get_string('grade');

        $table = new html_table();
        if ($indexsummary->usesections) {
            $table->head  = array ($strsectionname, $strplural, $strduedate, $strsubmission, $strgrade);
            $table->align = array ('left', 'left', 'center', 'right', 'right');
        } else {
            $table->head  = array ($strplural, $strduedate, $strsubmission, $strgrade);
            $table->align = array ('left', 'left', 'center', 'right');
        }
        $table->data = array();

        $currentsection = '';

        foreach ($indexsummary->assignments as $info) {
            $params = array('id' => $info['cmid']);
            $link = html_writer::link(new moodle_url('/mod/kalvidassign/view.php', $params), $info['cmname']);
            $due = $info['timedue'] ? userdate($info['timedue']) : '-';

            $printsection = '';
            if ($indexsummary->usesections) {
                if ($info['sectionname'] !== $currentsection) {
                    if ($info['sectionname']) {
                        $printsection = $info['sectionname'];
                    }
                    if ($currentsection !== '') {
                        $table->data[] = 'hr';
                    }
                    $currentsection = $info['sectionname'];
                }
            }

            if ($indexsummary->usesections) {
                $row = array($printsection, $link, $due, $info['submissioninfo'], $info['gradeinfo']);
            } else {
                $row = array($link, $due, $info['submissioninfo'], $info['gradeinfo']);
            }
            $table->data[] = $row;
        }

        return html_writer::table($table);
    }

    public function create_video_preview_modal() {
        $output = '';
        $output .= '<div id="video_preview_modal" class="modal">';
            $output .= '<div class="modal-dialog">';
                $output .= '<div class="modal-content">';
                    
                    $output .= '<div class="modal-header">';
                        $output .= '<button class="close" data-dismiss="modal">';
                        $output .= '<span>&times;</span>';
                        $output .= '</buton>';
                    $output .= '</div>';
                    
                    $output .= '<div id="video_preview_body" class="modal-body">';
                    $output .= '</div>';

                $output .= '</div>';
            $output .= '</div>';
        $output .= '</div>';
        return $output;
    }
}
