<?php
/**
 * Configuration file for the MySihat Appointment System
 * Contains global settings and constants
 */

// Session settings
session_start();

// Error reporting settings
ini_set('display_errors', 1);
error_reporting(E_ALL);

// centralized based url
$scriptName = $_SERVER['SCRIPT_NAME'];
$basePath = rtrim(str_replace(basename($scriptName), '', $scriptName), '/');
define('BASE_URL', $basePath ? $basePath . '/' : '/');
 
//define('BASE_URL', '/');

// Database configuration
define('DB_PATH', dirname(dirname(__FILE__)) . '/database/healthcare.sqlite');

// Application settings
define('SITE_NAME', 'MySihat');
define('SITE_URL', 'http://' . $_SERVER['HTTP_HOST']);
define('APP_ROOT', dirname(dirname(__FILE__)));

// Time zone setting
date_default_timezone_set('UTC');

// Email settings for PHPMailer
define('MAIL_HOST', 'smtp.example.com');
define('MAIL_USERNAME', 'noreply@example.com');
define('MAIL_PASSWORD', 'your-email-password');
define('MAIL_FROM', 'noreply@example.com');
define('MAIL_FROM_NAME', SITE_NAME);
define('MAIL_PORT', 587);
define('MAIL_ENCRYPTION', 'tls');

// Define user roles
define('ROLE_PATIENT', 'patient');
define('ROLE_DOCTOR', 'doctor');
define('ROLE_ADMIN', 'admin');

// Other constants
define('MAX_APPOINTMENTS_PER_DAY', 15);
define('APPOINTMENT_DURATION_MINUTES', 30);
define('ADVANCE_BOOKING_DAYS', 30);
define('CANCEL_DEADLINE_HOURS', 24);


