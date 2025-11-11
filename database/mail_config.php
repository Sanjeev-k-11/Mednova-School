<?php
// mail_config.php

// PHPMailer configuration
// !! IMPORTANT: Replace with your actual SMTP settings !!
define('SMTP_HOST', 'mail.mednova.store'); // e.g., smtp.gmail.com
define('SMTP_USERNAME', 'info@codecraft.mednova.store'); // Your full email address
define('SMTP_PASSWORD', 'Kumar@2351'); // Your email password or app password
define('SMTP_PORT', 465); // e.g., 587 for TLS, 465 for SSL
define('SMTP_ENCRYPTION', 'ssl'); // 'tls' or 'ssl'

// Email details
define('MAIL_FROM_EMAIL', 'info@codecraft.mednova.store'); // Sender email address
define('MAIL_FROM_NAME', 'CodeCraft portfolio'); // Sender name


define('MAIL_TO_EMAIL', 'sy781405@gmail.com'); // The email address where you want to receive inquiries
define('MAIL_TO_NAME', 'CodeCraft portfolio'); // Your name

// OTP settings
define('OTP_LENGTH', 6); // Length of the numeric OTP
define('OTP_EXPIRY_MINUTES', 10); // OTP valid for 15 minutes
?>