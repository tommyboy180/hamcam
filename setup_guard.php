<?php
/**
 * setup_guard.php
 *
 * Included right after config.php by every page that needs a working
 * configuration. Until the setup wizard (setup.php) has been completed,
 * everything bounces to the wizard instead of fataling on placeholder
 * config values.
 *
 * setup.php itself does NOT include this file — it has its own,
 * more specific gate (open during first-run, login-required afterward).
 */

if (!defined('HAMCAM_SETUP_COMPLETE') || HAMCAM_SETUP_COMPLETE !== true) {
    header('Location: setup.php');
    exit;
}
