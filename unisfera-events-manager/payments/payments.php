<?php
/**
 * Unisfera Payments add-on bootstrap. Deactivate this single module to disable payments.
 */
if (!defined('ABSPATH')) exit;
define('UEM_PAYMENTS_PATH', __DIR__ . DIRECTORY_SEPARATOR);
require_once UEM_PAYMENTS_PATH . 'core.php';
require_once UEM_PAYMENTS_PATH . 'netopia-v2.php';
require_once UEM_PAYMENTS_PATH . 'checkout.php';
