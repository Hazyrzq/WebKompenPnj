<?php
require_once __DIR__ . '/vendor/autoload.php';
session_start();

use Gregwar\Captcha\CaptchaBuilder;

header('Content-Type: image/jpeg');

$builder = new CaptchaBuilder;
$builder->build();

$_SESSION['captcha'] = $builder->getPhrase();
$builder->output();
?>