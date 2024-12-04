<?php
// email_config.php

// Konfigurasi email sistem
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'pblsikompen@gmail.com');
define('SMTP_PASSWORD', 'hnmh xgoi dqkd arnl');
define('SMTP_FROM_NAME', 'Sikompen');

// Load PHPMailer
require_once __DIR__ . '/../../vendor/phpmailer/phpmailer/src/Exception.php';
require_once __DIR__ . '/../../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../../vendor/phpmailer/phpmailer/src/SMTP.php';