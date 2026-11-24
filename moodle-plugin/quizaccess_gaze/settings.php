<?php
// This file is part of Moodle - http://moodle.org/
//
// @package    quizaccess_gaze
// @copyright  2026 UBAYA x PCU x ITS Gaze-CBT Team
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configcheckbox(
        'quizaccess_gaze/enabled_by_default',
        get_string('setting_enabled_by_default', 'quizaccess_gaze'),
        get_string('setting_enabled_by_default_desc', 'quizaccess_gaze'),
        1
    ));

    $settings->add(new admin_setting_configselect(
        'quizaccess_gaze/default_answer_dwell_ms',
        get_string('setting_default_dwell', 'quizaccess_gaze'),
        get_string('setting_default_dwell_desc', 'quizaccess_gaze'),
        '3000',
        [
            '1500' => '1500 ms (Fast Benchmark)',
            '2000' => '2000 ms (Balanced)',
            '3000' => '3000 ms (Tremor-Safe Default)',
        ]
    ));
}
