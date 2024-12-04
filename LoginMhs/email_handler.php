<?php
// Include required files
require_once 'email_config.php';
require '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Class to handle all email operations
 * This class manages the setup and sending of emails using PHPMailer
 */
class EmailHandler {
    private $mailer;
    
    /**
     * Constructor initializes PHPMailer with basic configuration
     */
    public function __construct() {
        $this->mailer = new PHPMailer(true);
        $this->setupMailer();
    }
    
    /**
     * Sets up basic SMTP configuration for PHPMailer
     */
    private function setupMailer() {
        try {
            // Server settings
            $this->mailer->isSMTP();
            $this->mailer->Host = EmailConfig::SMTP_HOST;
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = EmailConfig::SMTP_USERNAME;
            $this->mailer->Password = EmailConfig::SMTP_PASSWORD;
            $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $this->mailer->Port = EmailConfig::SMTP_PORT;
    
            // Default sender settings
            $this->mailer->setFrom(
                EmailConfig::SMTP_USERNAME, 
                EmailConfig::SMTP_FROM_NAME
            );
    
            // Character encoding and email headers
            $this->mailer->CharSet = EmailConfig::CHARSET;
            $this->mailer->isHTML(true);
    
            // Add custom headers (optional but improves deliverability)
            $this->mailer->addCustomHeader('X-Mailer', 'PHP/' . phpversion());
            $this->mailer->addCustomHeader('List-Unsubscribe', '<mailto:no-reply@yourdomain.com>');
    
        } catch (Exception $e) {
            error_log("Mailer setup failed: " . $e->getMessage());
            throw new Exception("Failed to setup email system");
        }
    }
    
    
    /**
     * Sends password reset email to user
     */
    public function sendPasswordResetEmail($email, $token, $nim) {
        try {
            // Clear previous recipients
            $this->mailer->clearAddresses();
            
            // Add recipient
            $this->mailer->addAddress($email);
            
            // Set email subject
            $this->mailer->Subject = EmailConfig::EMAIL_SUBJECT;
            
            // Generate reset link
            $reset_link = $this->generateResetLink($token, $nim);
            
            // Set email content
            $this->mailer->Body = $this->getHtmlTemplate($reset_link);
            $this->mailer->AltBody = $this->getPlainTextTemplate($reset_link);
            
            // Send email
            $this->mailer->send();
            
            // Log success
            error_log("Reset password email sent successfully to: " . $email);
            return true;
            
        } catch (Exception $e) {
            error_log("Failed to send reset email to {$email}: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generates the password reset link
     */
    private function generateResetLink($token, $nim) {
        $baseUrl = EmailConfig::getSiteUrl();
        
        return sprintf(
            "%s/reset_password.php?token=%s&nim=%s",
            rtrim($baseUrl, '/'), // Remove trailing slash if exists
            urlencode($token),
            urlencode($nim)
        );
    }
    
    /**
     * Returns HTML email template
     */
    private function getHtmlTemplate($reset_link) {
        return <<<HTML
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
            <div style="background-color: #4F46E5; padding: 20px; text-align: center;">
                <h2 style="color: white; margin: 0;">Reset Password</h2>
            </div>
            
            <div style="padding: 20px; background-color: #ffffff; border: 1px solid #e5e7eb;">
                <p>Yth. Mahasiswa,</p>
                <p>Kami menerima permintaan untuk reset password akun Anda.</p>
                <p>Untuk melanjutkan proses reset password, silakan klik tombol di bawah ini:</p>
                
                <div style="text-align: center; margin: 30px 0;">
                    <a href="{$reset_link}" 
                       target="_blank"
                       style="background-color: #4F46E5; 
                              color: white; 
                              padding: 12px 24px; 
                              text-decoration: none; 
                              border-radius: 5px; 
                              display: inline-block; 
                              font-weight: bold;">
                        Reset Password
                    </a>
                </div>
                
                <p style="color: #666; font-size: 14px;">
                    Jika tombol di atas tidak berfungsi, copy dan paste link berikut ke browser Anda:<br>
                    <a href="{$reset_link}" style="color: #4F46E5; word-break: break-word;">{$reset_link}</a>
                </p>
                <p style="color: #666; font-size: 14px;">Jika Anda tidak meminta reset password, abaikan email ini.</p>
            </div>
            
            <div style="background-color: #f8f9fa; padding: 20px; text-align: center;">
                <p style="color: #666; font-size: 12px; margin: 0;">
                    © 2024 Sistem Kompensasi Mahasiswa<br>
                    Email ini dikirim secara otomatis, mohon tidak membalas email ini.
                </p>
            </div>
        </div>
    HTML;
    }
    
    
    /**
     * Returns plain text email template
     */
    private function getPlainTextTemplate($reset_link) {
        return <<<TEXT
        Reset Password - Sistem Kompensasi Mahasiswa

        Yth. Mahasiswa,
        
        Kami menerima permintaan untuk reset password akun Anda.
        
        Untuk melanjutkan proses reset password, silakan klik link berikut:
        {$reset_link}
        
        Link ini akan kadaluarsa dalam 1 jam.
        
        Jika Anda tidak meminta reset password, abaikan email ini.
        
        © 2024 Sistem Kompensasi Mahasiswa
        Email ini dikirim secara otomatis, mohon tidak membalas email ini.
TEXT;
    }
}