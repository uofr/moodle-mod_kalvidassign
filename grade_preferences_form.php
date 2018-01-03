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
 * Kaltura video assignment grade preferences form
 *
 * @package    mod_kalvidassign
 * @copyright  (C) 2016-2017 Yamaguchi University <gh-cc@mlex.cc.yamaguchi-u.ac.jp>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once(dirname(dirname(dirname(__FILE__))) . '/course/moodleform_mod.php');
require_once(dirname(__FILE__) . '/locallib.php');
require_once($CFG->libdir.'/formslib.php');

if (!defined('MOODLE_INTERNAL')) {
    // It must be included from a Moodle page.
    die('Direct access to this script is forbidden.');
}

global $PAGE, $COURSE;

$PAGE->set_url('/mod/kalvidassign/grade_preferences_form.php');

require_login();

/**
 * Grade preferencees class of mod_kalvidassign
 * @package mod_kalvidassign
 * @copyright  (C) 2016-2017 Yamaguchi University <gh-cc@mlex.cc.yamaguchi-u.ac.jp>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class kalvidassign_gradepreferences_form extends moodleform {

    /**
     * This function outputs a grade submission form.
     */
    public function definition() {
        global $COURSE, $USER;

        $mform =& $this->_form;

        $mform->addElement('hidden', 'cmid', $this->_customdata['cmid']);
        $mform->setType('cmid', PARAM_INT);

        $mform->addElement('header', 'kal_vid_subm_hdr', get_string('optionalsettings', 'kalvidassign'));

        $context = context_module::instance($this->_customdata['cmid']);

        $group_opt = array();
        $groups = array();

        // If the user doesn't have access to all group print the groups they have access to.
        if (!has_capability('moodle/site:accessallgroups', $context)) {

            // Determine the groups mode.
            switch($this->_customdata['groupmode']) {
                case NOGROUPS:
                    // No groups, do nothing.
                    break;
                case SEPARATEGROUPS:
                    $groups = groups_get_all_groups($COURSE->id, $USER->id);
                    break;
                case VISIBLEGROUPS:
                    $groups = groups_get_all_groups($COURSE->id, $USER->id);
                    break;
            }

            $group_opt[0] = get_string('all', 'mod_kalvidassign');

            foreach ($groups as $group_obj) {
                $group_opt[$group_obj->id] = $group_obj->name;
            }

        } else {
            $groups = groups_get_all_groups($COURSE->id);

            $group_opt[0] = get_string('all', 'mod_kalvidassign');

            foreach ($groups as $group_obj) {
                $group_opt[$group_obj->id] = $group_obj->name;
            }

        }

        $mform->addElement('select', 'group_filter', get_string('group_filter', 'mod_kalvidassign'), $group_opt);

        $filters = array(KALASSIGN_ALL => get_string('all', 'kalvidassign'),
                                KALASSIGN_REQ_GRADING => get_string('reqgrading', 'kalvidassign'),
                                KALASSIGN_SUBMITTED => get_string('submitted', 'kalvidassign'),
								KALASSIGN_NOTSUBMITTEDYET => get_string('notsubmittedyet', 'kalvidassign'));

        $mform->addElement('select', 'filter', get_string('show'), $filters);
        $mform->addHelpButton('filter', 'show', 'kalvidassign');

        $mform->addElement('text', 'perpage', get_string('pagesize', 'kalvidassign'), array('size' => 3, 'maxlength' => 3));
        $mform->setType('perpage', PARAM_INT);
        $mform->addHelpButton('perpage', 'pagesize', 'kalvidassign');

        $mform->addElement('checkbox', 'quickgrade', get_string('quickgrade', 'kalvidassign'));
        $mform->setDefault('quickgrade', '');
        $mform->addHelpButton('quickgrade', 'quickgrade', 'kalvidassign');

        $savepref = get_string('savepref', 'kalvidassign');

        $mform->addElement('submit', 'savepref', $savepref);

    }

    /**
     * This function validates submissons.
     * @param array $data - form data.
     * @param array $files - form data.
     * @return $string - error messages (if no error occurs, return null).
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (0 == (int) $data['perpage']) {
            $errors['perpage'] = get_string('invalidperpage', 'kalvidassign');
        }

        return $errors;
    }
}
