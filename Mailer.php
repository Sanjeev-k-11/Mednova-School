<?php
// Mail configuration
require_once './database/mail_config.php';

// Correct Composer autoload path (assuming vendor folder is in project root)
require_once  './database/vendor/autoload.php'; // adjust if needed

// Import PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mailer {
    private $mail;

    public function __construct() {
        $this->mail = new PHPMailer(true);
        try {
            // Server settings
            $this->mail->isSMTP();
            $this->mail->Host       = SMTP_HOST;
            $this->mail->SMTPAuth   = true;
            $this->mail->Username   = SMTP_USERNAME;
            $this->mail->Password   = SMTP_PASSWORD;
            $this->mail->SMTPSecure = SMTP_ENCRYPTION;
            $this->mail->Port       = SMTP_PORT;

            // Sender
            $this->mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
            
        } catch (Exception $e) {
            error_log("Failed to initialize mailer: {$this->mail->ErrorInfo}");
        }
    }

    public function sendPasswordResetOtp($recipientEmail, $otp) {
        try {
            $this->mail->addAddress($recipientEmail);
            $this->mail->isHTML(true);
            $this->mail->Subject = 'Your Password Reset Code';
            $this->mail->Body = "
                <div style='font-family: Arial, sans-serif; line-height: 1.6;'>
                    <h2>Password Reset Request</h2>
                    <p>Hello,</p>
                    <p>Use the code below to reset your password. This code is valid for " . OTP_EXPIRY_MINUTES . " minutes.</p>
                    <p style='font-size: 24px; font-weight: bold; letter-spacing: 5px; background-color: #f2f2f2; padding: 10px; text-align: center; border-radius: 5px;'>{$otp}</p>
                    <p>If you did not request this, ignore this email.</p>
                    <p>Thanks,<br>The " . MAIL_FROM_NAME . " Team</p>
                </div>";
            $this->mail->AltBody = "Your password reset code is: {$otp}. Valid for " . OTP_EXPIRY_MINUTES . " minutes.";

            $this->mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Message could not be sent to {$recipientEmail}. Mailer Error: {$this->mail->ErrorInfo}");
            return false;
        }
    }
}
?>
