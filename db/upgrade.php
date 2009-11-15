<?php

// This file keeps track of upgrades to 
// the facetoface module
//
// Sometimes, changes between versions involve
// alterations to database structures and other
// major things that may break installations.
//
// The upgrade function in this file will attempt
// to perform all the necessary actions to upgrade
// your older installtion to the current version.
//
// If there's something it cannot do itself, it
// will tell you what you need to do.
//
// The commands in here will all be database-neutral,
// using the functions defined in lib/ddllib.php

function xmldb_facetoface_upgrade($oldversion=0) {

    global $CFG, $db;

    $result = true;

    if ($result && $oldversion < 2008050500) {
        $table = new XMLDBTable('facetoface');
        $field = new XMLDBField('thirdpartywaitlist');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0', 'thirdparty');
        $result = $result && add_field($table, $field);
    }

    if ($result && $oldversion < 2008061000) {
        $table = new XMLDBTable('facetoface_submissions');
        $field = new XMLDBField('notificationtype');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0', 'timemodified');
        $result = $result && add_field($table, $field);
    }

    if ($result && $oldversion < 2008080100) {
        notify('Processing Face-to-face grades, this may take a while if there are many sessions...', 'notifysuccess');
        require_once $CFG->dirroot.'/mod/facetoface/lib.php';

        begin_sql();
        $db->debug = false; // too much debug output

        // Migrate the grades to the gradebook
        $sql = "SELECT f.id, f.name, f.course, s.grade, s.timegraded, s.userid,
                       cm.idnumber as cmidnumber
                  FROM {$CFG->prefix}facetoface_submissions s
                  JOIN {$CFG->prefix}facetoface f ON s.facetoface = f.id
                  JOIN {$CFG->prefix}course_modules cm ON cm.instance = f.id
                  JOIN {$CFG->prefix}modules m ON m.id = cm.module
                 WHERE m.name='facetoface'";
        if ($rs = get_recordset_sql($sql)) {
            while ($result and $facetoface = rs_fetch_next_record($rs)) {
                $grade = new stdclass();
                $grade->userid = $facetoface->userid;
                $grade->rawgrade = $facetoface->grade;
                $grade->rawgrademin = 0;
                $grade->rawgrademax = 100;
                $grade->timecreated = $facetoface->timegraded;
                $grade->timemodified = $facetoface->timegraded;

                $result = $result && (GRADE_UPDATE_OK == facetoface_grade_item_update($facetoface, $grade));
            }
            rs_close($rs);
        }
        $db->debug = true;

        // Remove the grade and timegraded fields from mdl_facetoface_submissions
        if ($result) {
            $table = new XMLDBTable('facetoface_submissions');
            $field1 = new XMLDBField('grade');
            $field2 = new XMLDBField('timegraded');
            $result = $result && drop_field($table, $field1, false, true);
            $result = $result && drop_field($table, $field2, false, true);
        }

        if ($result) {
            commit_sql();
        } else {
            rollback_sql();
        }
    }

    if ($result && $oldversion < 2008090800) {

        // Define field timemodified to be added to facetoface_submissions
        $table = new XMLDBTable('facetoface_submissions');
        $field = new XMLDBField('timecancelled');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '20', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, 0, 'timemodified');

        // Launch add field
        $result = $result && add_field($table, $field);
    }

    if ($result && $oldversion < 2009111300) {
        // New fields necessary for the training calendar
        $table = new XMLDBTable('facetoface');
        $field1 = new XMLDBField('shortname');
        $field1->setAttributes(XMLDB_TYPE_CHAR, '32', null, null, null, null, null, null, 'timemodified');
        $result = $result && add_field($table, $field1);

        $field2 = new XMLDBField('description');
        $field2->setAttributes(XMLDB_TYPE_TEXT, 'medium', null, null, null, null, null, null, 'shortname');
        $result = $result && add_field($table, $field2);

        $field3 = new XMLDBField('showoncalendar');
        $field3->setAttributes(XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '1', 'description');
        $result = $result && add_field($table, $field3);
    }

    if ($result && $oldversion < 2009111600) {

        $table1 = new XMLDBTable('facetoface_session_field');
        $table1->addFieldInfo('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null, null);
        $table1->addFieldInfo('name', XMLDB_TYPE_CHAR, '255', null, null, null, null, null, null);
        $table1->addFieldInfo('shortname', XMLDB_TYPE_CHAR, '255', null, null, null, null, null, null);
        $table1->addFieldInfo('type', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0');
        $table1->addFieldInfo('possiblevalues', XMLDB_TYPE_TEXT, 'medium', null, null, null, null, null, null);
        $table1->addFieldInfo('required', XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0');
        $table1->addFieldInfo('defaultvalue', XMLDB_TYPE_CHAR, '255', null, null, null, null, null, null);
        $table1->addFieldInfo('isfilter', XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '1');
        $table1->addFieldInfo('showinsummary', XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '1');
        $table1->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('id'));
        $result = $result && create_table($table1);

        $table2 = new XMLDBTable('facetoface_session_data');
        $table2->addFieldInfo('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null, null);
        $table2->addFieldInfo('fieldid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0');
        $table2->addFieldInfo('sessionid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0');
        $table2->addFieldInfo('data', XMLDB_TYPE_CHAR, '255', null, null, null, null, null, null);
        $table2->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('id'));
        $result = $result && create_table($table2);
    }

    return $result;
}
