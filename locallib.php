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
 * Some utility functions for the adaptive quiz activity.
 *
 * @package    mod_adaptivequiz
 * @copyright  2013 onwards Remote-Learner {@link http://www.remote-learner.ca/}
 * @copyright  2022 onwards Vitaly Potenko <potenkov@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/adaptivequiz/lib.php');
require_once($CFG->dirroot . '/question/editlib.php');
require_once($CFG->dirroot . '/lib/questionlib.php');
require_once($CFG->dirroot . '/question/engine/lib.php');

use core_question\local\bank\question_edit_contexts;
use mod_adaptivequiz\event\attempt_completed;
use mod_adaptivequiz\local\attempt\attempt_state;
use mod_adaptivequiz\local\catalgorithm\catalgo;
use qbank_managecategories\helper as qbank_managecategories_helper;

// Default tagging used.
define('ADAPTIVEQUIZ_QUESTION_TAG', 'adpq_');

// Number of attempts to display on the reporting page.
define('ADAPTIVEQUIZ_REC_PER_PAGE', 30);
// Number of questions to display for review on the page at one time.
define('ADAPTIVEQUIZ_REV_QUEST_PER_PAGE', 10);

// Attempt stopping criteria.
// The maximum number of question, defined by the adaptive parameters was achieved.
define('ADAPTIVEQUIZ_STOPCRI_MAXQUEST', 'maxqest');
// The standard error value, defined by the adaptive parameters, was achieved.
define('ADAPTIVEQUIZ_STOPCRI_STANDERR', 'stderr');
// Unable to retrieve a question, because the user either answered all of the questions in the level or no questions were found.
define('ADAPTIVEQUIZ_STOPCRI_NOQUESTFOUND', 'noqest');
// The user achieved the maximum difficulty level defined by the adaptive parameters, unable to retrieve another question.
define('ADAPTIVEQUIZ_STOPCRI_MAXLEVEL', 'maxlevel');
// The user achieved the minimum difficulty level defined by the adaptive parameters, unable to retrieve another question.
define('ADAPTIVEQUIZ_STOPCRI_MINLEVEL', 'minlevel');

/**
 * This function returns an array of question bank categories accessible to the
 * current user in the given context
 * @param context $context A context object
 * @return array An array whose keys are the question category ids and values
 * are the name of the question category
 */
function adaptivequiz_get_question_categories(context $context) {
    if (empty($context)) {
        return array();
    }

    $options      = array();
    $qesteditctx  = new question_edit_contexts($context);
    $contexts     = $qesteditctx->having_one_edit_tab_cap('editq');
    $questioncats = qbank_managecategories_helper::question_category_options($contexts);

    if (!empty($questioncats)) {
        foreach ($questioncats as $questioncatcourse) {
            foreach ($questioncatcourse as $key => $questioncat) {
                // Key format is [question cat id, question cat context id], we need to explode it.
                $questidcontext = explode(',', $key);
                $questid = array_shift($questidcontext);
                $options[$questid] = $questioncat;
            }
        }
    }

    return $options;
}

/**
 * This function is healper method to create default
 * @param object $context A context object
 * @return mixed The default category in the course context or false
 */
function adaptivequiz_make_default_categories($context) {
    if (empty($context)) {
        return false;
    }

    // Create default question categories.
    $defaultcategoryobj = question_make_default_categories(array($context));

    return $defaultcategoryobj;
}

/**
 * This function returns an array of question categories that were
 * selected for use for the activity instance
 * @param int $instance Instance id
 * @return array an array of question category ids
 */
function adaptivequiz_get_selected_question_cateogires($instance) {
    global $DB;

    $selquestcat = array();

    if (empty($instance)) {
        return array();
    }

    $records = $DB->get_records('adaptivequiz_question', array('instance' => $instance));

    if (empty($records)) {
        return array();
    }

    foreach ($records as $record) {
        $selquestcat[] = $record->questioncategory;
    }

    return $selquestcat;
}

/**
 * This function returns a count of the user's previous attempts that have been marked
 * as completed
 * @param int $instanceid activity instance id
 * @param int $userid user id
 * @return int a count of the user's previous attempts
 */
function adaptivequiz_count_user_previous_attempts($instanceid = 0, $userid = 0) {
    global $DB;

    if (empty($instanceid) || empty($userid)) {
        return 0;
    }

    $param = array('instance' => $instanceid, 'userid' => $userid, 'attemptstate' => attempt_state::COMPLETED);
    $count = $DB->count_records('adaptivequiz_attempt', $param);

    return $count;
}

/**
 * This function determins if the user has used up all of their attempts
 * @param int $maxattempts The maximum allowed attempts, 0 denotes unlimited attempts
 * @param int $attempts The number of attempts taken thus far
 * @return bool true if the attempt is allowed, otherwise false
 */
function adaptivequiz_allowed_attempt($maxattempts = 0, $attempts = 0) {
    if (0 == $maxattempts || $maxattempts > $attempts) {
        return true;
    } else {
        return false;
    }
}

/**
 * This function checks whether the minimum number of questions has been reached for the attempt.
 *
 * @param int $attemptid
 * @param int $adaptivequizid
 * @param int $userid
 * @return bool
 */
function adaptivequiz_min_number_of_questions_reached(int $attemptid, int $adaptivequizid, int $userid): bool {
    global $DB;

    $sql = "SELECT adpq.id
             FROM {adaptivequiz} adpq
             JOIN {adaptivequiz_attempt} adpqa ON adpq.id = adpqa.instance
            WHERE adpqa.id = :attemptid AND adpqa.instance = :adaptivequizid AND adpqa.userid = :userid
                  AND adpq.minimumquestions <= adpqa.questionsattempted
         ORDER BY adpq.id ASC";

    return $DB->record_exists_sql($sql, ['attemptid' => $attemptid, 'adaptivequizid' => $adaptivequizid, 'userid' => $userid]);
}

/**
 * This checks if the session property, needed to beging an attempt with a password, has been initialized
 * @param int $instance the activity instance id
 * @return bool true
 */
function adaptivequiz_user_entered_password($instance) {
    global $SESSION;

    $conditions = isset($SESSION->passwordcheckedadpq) && is_array($SESSION->passwordcheckedadpq) &&
            array_key_exists($instance, $SESSION->passwordcheckedadpq) && true === $SESSION->passwordcheckedadpq[$instance];
    return $conditions;
}

/**
 * Given a list of tags on a question, answer the question's difficulty.
 *
 * @param array $tags the tags on a question.
 * @return int|null the difficulty level or null if unknown.
 */
function adaptivequiz_get_difficulty_from_tags(array $tags) {
    foreach ($tags as $tag) {
        if (preg_match('/^'.ADAPTIVEQUIZ_QUESTION_TAG.'([0-9]+)$/', $tag, $matches)) {
            return (int) $matches[1];
        }
    }
    return null;
}


/**
 * @return array int => lang string the options for calculating the quiz grade
 *      from the individual attempt grades.
 */
function adaptivequiz_get_grading_options() {
    return array(
        ADAPTIVEQUIZ_GRADEHIGHEST => get_string('gradehighest', 'adaptivequiz'),
        ADAPTIVEQUIZ_ATTEMPTFIRST => get_string('attemptfirst', 'adaptivequiz'),
        ADAPTIVEQUIZ_ATTEMPTLAST  => get_string('attemptlast', 'adaptivequiz')
    );
}

/**
 * Return grade for given user or all users.
 *
 * @param stdClass $adaptivequiz The adaptivequiz
 * @param int $userid optional user id, 0 means all users
 * @return array array of grades, false if none. These are raw grades. They should
 * be processed with adaptivequiz_format_grade for display.
 */
function adaptivequiz_get_user_grades($adaptivequiz, $userid = 0) {
    global $CFG, $DB;

    $params = array(
        'instance' => $adaptivequiz->id,
        'attemptstate' => attempt_state::COMPLETED,
    );
    $userwhere = '';
    if ($userid) {
        $params['userid'] = $userid;
        $userwhere = 'AND aa.userid = :userid';
    }
    $sql = "SELECT aa.id, aa.userid, aa.timemodified, acp.measure, aa.timecreated, a.highestlevel, a.lowestlevel
              FROM {adaptivequiz_attempt} aa
              JOIN {adaptivequiz_cat_params} acp ON aa.id = acp.attempt
              JOIN {adaptivequiz} a ON aa.instance = a.id
             WHERE aa.instance = :instance AND aa.attemptstate = :attemptstate
                   $userwhere";
    $records = $DB->get_records_sql($sql, $params);

    $grades = array();
    foreach ($records as $grade) {
        $grade->rawgrade = catalgo::map_logit_to_scale($grade->measure,
            $grade->highestlevel, $grade->lowestlevel);

        if (empty($grades[$grade->userid])) {
            // Store the first attempt.
            $grades[$grade->userid] = $grade;
        } else {
            // If additional attempts are recorded, uses the settings to determine
            // which one to report.
            if ($adaptivequiz->grademethod == ADAPTIVEQUIZ_ATTEMPTFIRST) {
                if ($grade->timemodified < $grades[$grade->userid]->timemodified) {
                    $grades[$grade->userid] = $grade;
                }
            } else if ($adaptivequiz->grademethod == ADAPTIVEQUIZ_ATTEMPTLAST) {
                if ($grade->timemodified > $grades[$grade->userid]->timemodified) {
                    $grades[$grade->userid] = $grade;
                }
            } else {
                // By default, use the highst grade.
                if ($grade->rawgrade > $grades[$grade->userid]->rawgrade) {
                    $grades[$grade->userid] = $grade;
                }
            }
        }
    }
    return $grades;
}
