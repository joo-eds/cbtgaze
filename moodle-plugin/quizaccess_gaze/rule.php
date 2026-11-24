<?php
// This file is part of Moodle - http://moodle.org/
//
// @package    quizaccess_gaze
// @copyright  2026 UBAYA x PCU x ITS Gaze-CBT Team
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

if (!class_exists('quiz_access_rule_base') && class_exists('\mod_quiz\local\access_rule_base')) {
    class_alias('\mod_quiz\local\access_rule_base', 'quiz_access_rule_base');
}

/**
 * Gaze-CBT Moodle 4.x Quiz Access Rule.
 *
 * Always-On by default on all quiz attempts and summaries, persisting mode flags
 * (?gaze, ?gaze_mock, ?dwell) across Moodle HTTP POST/Redirect transitions via $SESSION.
 */
class quizaccess_gaze extends quiz_access_rule_base {

    public static function make(\mod_quiz\quiz_settings $quizobj, $timenow, $canignoretimelimits) {
        return new self($quizobj, $timenow);
    }

    /**
     * Shows Gaze-CBT active status on the quiz overview page (view.php) and allows
     * switching between Live Webcam, Mock Mouse Benchmark, and Normal mode via $SESSION.
     */
    public function description(): array {
        global $SESSION;

        $gazeParam = optional_param('gaze', -1, PARAM_INT);
        $mockParam = optional_param('gaze_mock', -1, PARAM_INT);
        $dwellParam = optional_param('dwell', 0, PARAM_INT);
        $this->sync_session_flags($gazeParam, $mockParam, $dwellParam);

        $siteDefaultRaw = get_config('quizaccess_gaze', 'enabled_by_default');
        $siteDefault = ($siteDefaultRaw === false) ? true : (bool)$siteDefaultRaw;
        $enabled = isset($SESSION->gaze_cbt_enabled) ? (bool)$SESSION->gaze_cbt_enabled : $siteDefault;
        $isMock = !empty($SESSION->gaze_cbt_mock);

        $cmid = $this->quizobj->get_cmid();
        $liveUrl = new \moodle_url('/mod/quiz/view.php', ['id' => $cmid, 'gaze' => 1, 'gaze_mock' => 0]);
        $mockUrl = new \moodle_url('/mod/quiz/view.php', ['id' => $cmid, 'gaze' => 1, 'gaze_mock' => 1, 'dwell' => 2000]);
        $offUrl = new \moodle_url('/mod/quiz/view.php', ['id' => $cmid, 'gaze' => 0, 'gaze_mock' => 0]);

        $statusLabel = !$enabled
            ? 'NONAKTIF (Moodle Standar)'
            : ($isMock ? 'AKTIF — Mode Mock Mouse (Simulasi)' : 'AKTIF — Mode Live Webcam (Eye-Tracker)');

        $controls = \html_writer::link($liveUrl, 'Live Webcam', ['class' => 'btn btn-sm ' . ($enabled && !$isMock ? 'btn-primary' : 'btn-outline-primary') . ' mr-1'])
            . ' ' . \html_writer::link($mockUrl, 'Mock Mouse (2s)', ['class' => 'btn btn-sm ' . ($enabled && $isMock ? 'btn-info' : 'btn-outline-info') . ' mr-1'])
            . ' ' . \html_writer::link($offUrl, 'Matikan', ['class' => 'btn btn-sm ' . (!$enabled ? 'btn-secondary' : 'btn-outline-secondary')]);

        return [
            'Gaze-CBT Eye-Tracker: <strong>' . $statusLabel . '</strong> &nbsp; ' . $controls,
        ];
    }

    /**
     * Injects CSS Gaze Focus Mode, runtime config, and the bundled Canvas HUD + CV script
     * on attempt.php and summary.php.
     */
    public function setup_attempt_page($page): void {
        global $CFG, $SESSION;

        parent::setup_attempt_page($page);

        $gazeParam = optional_param('gaze', -1, PARAM_INT);
        $mockParam = optional_param('gaze_mock', -1, PARAM_INT);
        $dwellParam = optional_param('dwell', 0, PARAM_INT);
        $this->sync_session_flags($gazeParam, $mockParam, $dwellParam);

        $siteDefaultRaw = get_config('quizaccess_gaze', 'enabled_by_default');
        $siteDefault = ($siteDefaultRaw === false) ? true : (bool)$siteDefaultRaw;

        $enabled = isset($SESSION->gaze_cbt_enabled) ? (bool)$SESSION->gaze_cbt_enabled : $siteDefault;
        if (!$enabled) {
            return;
        }

        $isMock = !empty($SESSION->gaze_cbt_mock);
        $defaultDwell = (int)(get_config('quizaccess_gaze', 'default_answer_dwell_ms') ?: 3000);
        $answerDwellMs = !empty($SESSION->gaze_cbt_dwell) ? (int)$SESSION->gaze_cbt_dwell : $defaultDwell;

        $cssFile = __DIR__ . '/styles.css';
        $jsFile = __DIR__ . '/assets/gaze-cbt.bundle.js';
        $cssVer = is_file($cssFile) ? (string)filemtime($cssFile) : '1';
        $jsVer = is_file($jsFile) ? (string)filemtime($jsFile) : '1';

        // Use explicit moodle_url with cache-busting query param so Moodle serves static files
        // directly instead of routing through lib/javascript.php (which rejects symlink/junction paths).
        $page->requires->css(new \moodle_url('/mod/quiz/accessrule/gaze/styles.css', ['v' => $cssVer]));
        $page->requires->js(new \moodle_url('/mod/quiz/accessrule/gaze/assets/gaze-cbt.bundle.js', ['v' => $jsVer]), false);

        $config = [
            'enabled' => true,
            'mockMode' => $isMock,
            'localAssetBaseUrl' => $CFG->wwwroot . '/mod/quiz/accessrule/gaze/assets',
            'hudConfig' => [
                'answerDwellMs' => $answerDwellMs,
            ],
        ];

        $configJson = json_encode($config, JSON_UNESCAPED_SLASHES);
        $inlineBoot = "window.GAZE_CBT_CONFIG = {$configJson};\n"
            . "if (window.GazeCBT && typeof window.GazeCBT.boot === 'function') {\n"
            . "    window.GazeCBT.boot(window.GAZE_CBT_CONFIG);\n"
            . "}";

        $page->requires->js_init_code($inlineBoot, false);
    }

    private function sync_session_flags(int $gazeParam, int $mockParam, int $dwellParam): void {
        global $SESSION;

        if ($gazeParam === 0 || $gazeParam === 1) {
            $SESSION->gaze_cbt_enabled = ($gazeParam === 1);
        }
        if ($mockParam === 0 || $mockParam === 1) {
            $SESSION->gaze_cbt_mock = ($mockParam === 1);
            if ($mockParam === 1) {
                $SESSION->gaze_cbt_enabled = true;
            }
        }
        if ($dwellParam >= 500) {
            $SESSION->gaze_cbt_dwell = $dwellParam;
        }
    }
}
