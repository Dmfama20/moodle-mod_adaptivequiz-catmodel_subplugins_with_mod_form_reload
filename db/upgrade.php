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
 * Contains function with the definition of upgrade steps for the plugin.
 *
 * @package   mod_adaptivequiz
 * @copyright 2013 Remote-Learner {@link http://www.remote-learner.ca/}
 * @copyright 2022 onwards Vitaly Potenko <potenkov@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Defines upgrade steps for the plugin.
 *
 * @param mixed $oldversion
 * @return bool True on success.
 */
function xmldb_adaptivequiz_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2014020400) {
        // Define field grademethod.
        $table = new xmldb_table('adaptivequiz');
        $field = new xmldb_field('grademethod', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, 1, 'startinglevel');

        // Conditionally add field grademethod.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Quiz savepoint reached.
        upgrade_mod_savepoint(true, 2014020400, 'adaptivequiz');
    }

    if ($oldversion < 2022012600) {
        $table = new xmldb_table('adaptivequiz');
        $field = new xmldb_field('showabilitymeasure', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, false, '0',
            'attemptfeedbackformat');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2022012600, 'adaptivequiz');
    }

    if ($oldversion < 2022092600) {
        $table = new xmldb_table('adaptivequiz');
        $field = new xmldb_field('completionattemptcompleted', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, false, 0);

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2022092600, 'adaptivequiz');
    }

    if ($oldversion < 2022110200) {
        $table = new xmldb_table('adaptivequiz');
        $field = new xmldb_field('showattemptprogress', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, 0,
            'showabilitymeasure');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2022110200, 'adaptivequiz');
    }

    if ($oldversion < 2023022100) {
        $table = new xmldb_table('adaptivequiz');
        $field = new xmldb_field('catmodel', XMLDB_TYPE_CHAR, 255);

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2023022100, 'adaptivequiz');
    }

    if ($oldversion < 2023052800) {
        // Transfer attempt parameters to a dedicated table.
        $table = new xmldb_table('adaptivequiz_cat_params');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('attempt', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('difficultysum', XMLDB_TYPE_NUMBER, '10, 7', null, XMLDB_NOTNULL, null, '0.0');
        $table->add_field('standarderror', XMLDB_TYPE_NUMBER, '10, 5', null, XMLDB_NOTNULL, null, '0.0');
        $table->add_field('measure', XMLDB_TYPE_NUMBER, '10, 5', null, XMLDB_NOTNULL, null, '0.0');
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('attempt', XMLDB_KEY_FOREIGN, ['attempt'], 'adaptivequiz_attempt', ['id']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $limitfrom = 0;
        $limitnum = 5000;

        $fields = 'id, userid, difficultysum, standarderror, measure, timecreated, timemodified';

        while ($attemptrecords = $DB->get_records('adaptivequiz_attempt', null, '', $fields, $limitfrom, $limitnum)) {
            foreach ($attemptrecords as $attemptrecord) {
                $paramsrecord = new stdClass();
                $paramsrecord->attempt = $attemptrecord->id;
                $paramsrecord->difficultysum = $attemptrecord->difficultysum;
                $paramsrecord->standarderror = $attemptrecord->standarderror;
                $paramsrecord->measure = $attemptrecord->measure;
                $paramsrecord->usermodified = $attemptrecord->userid;
                $paramsrecord->timecreated = $attemptrecord->timecreated;
                $paramsrecord->timemodified = $attemptrecord->timemodified;

                $DB->insert_record('adaptivequiz_cat_params', $paramsrecord);
            }

            $limitfrom += $limitnum;
        }

        // Remove fields from the attempts table.
        $table = new xmldb_table('adaptivequiz_attempt');

        $filedstodrop = ['difficultysum', 'standarderror', 'measure'];
        foreach ($filedstodrop as $fieldname) {
            $field = new xmldb_field($fieldname);

            if ($dbman->field_exists($table, $field)) {
                $dbman->drop_field($table, $field);
            }
        }

        upgrade_mod_savepoint(true, 2023052800, 'adaptivequiz');
    }

    return true;
}
