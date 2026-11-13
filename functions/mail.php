<?php
use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Application config from functions/config.php, falling back to the example file.
 */
function appConfig()
{
    static $config = null;
    if ($config === null) {
        $file = __DIR__ . '/config.php';
        $config = file_exists($file) ? require $file : require __DIR__ . '/config.example.php';
    }
    return $config;
}

/**
 * Send an HTML email over SMTP. Returns true on success, false if mail is
 * not configured or sending failed (the error is written to the PHP error log).
 */
function sendMail($to, $subject, $html)
{
    $smtp = appConfig()['smtp'];
    if (empty($smtp['host'])) {
        return false;
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = $smtp['host'];
        $mail->Port = (int) $smtp['port'];
        $mail->SMTPAuth = !empty($smtp['username']);
        $mail->Username = $smtp['username'];
        $mail->Password = $smtp['password'];
        if (!empty($smtp['encryption'])) {
            $mail->SMTPSecure = $smtp['encryption'];
        }
        $mail->setFrom($smtp['from_email'], $smtp['from_name']);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html;
        $mail->AltBody = trim(strip_tags(str_replace(['<br>', '</p>'], "\n", $html)));
        return $mail->send();
    } catch (MailException $e) {
        error_log('Mail to ' . $to . ' failed: ' . $mail->ErrorInfo);
        return false;
    }
}
