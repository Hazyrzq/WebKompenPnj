<?php
class EmailConfig {
    const SMTP_HOST = 'smtp.gmail.com';
    const SMTP_PORT = 587;
    
    const SMTP_USERNAME = 'pblsikompen@gmail.com';
    const SMTP_PASSWORD = 'hnmh xgoi dqkd arnl';
    
    const SMTP_FROM_NAME = 'Sistem Kompensasi Mahasiswa';
    
    // Make this dynamic to handle ngrok URLs
    public static function getSiteUrl() {
        // Get the current protocol (http/https)
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
        
        // Get the current host (including ngrok URL)
        $host = $_SERVER['HTTP_HOST'];
        
        // Get the path to your application's root
        $path = dirname($_SERVER['PHP_SELF']);
        
        // Construct the base URL
        return $protocol . $host . $path . '/';
    }
    
    const CHARSET = 'UTF-8';
    const EMAIL_SUBJECT = 'Reset Password - Sistem Kompensasi Mahasiswa';
}