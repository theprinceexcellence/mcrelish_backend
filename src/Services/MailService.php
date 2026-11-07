<?php
declare(strict_types=1);
namespace McRelish\Services;

use McRelish\Core\Config;
use PHPMailer\PHPMailer\{PHPMailer, SMTP, Exception};

final class MailService
{
    /** Sends through SMTP when configured; otherwise appends the message to storage/mail.log. Returns true when really sent. */
    public static function send(string $to, string $subject, string $html, string $text): bool
    {
        $c = Config::get('mail', []);
        if (empty($c['host'])) {
            self::log($to, $subject, $text);
            return false;
        }
        $lib = dirname(__DIR__, 2) . '/lib/PHPMailer/';
        require_once $lib . 'Exception.php';
        require_once $lib . 'PHPMailer.php';
        require_once $lib . 'SMTP.php';
        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = (string) $c['host'];
            $mail->Port = (int) ($c['port'] ?? 587);
            $mail->SMTPAuth = !empty($c['username']);
            $mail->Username = (string) ($c['username'] ?? '');
            $mail->Password = (string) ($c['password'] ?? '');
            $enc = (string) ($c['encryption'] ?? 'tls');
            $mail->SMTPSecure = $enc === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : ($enc === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : '');
            $mail->SMTPAutoTLS = $enc !== '';
            $mail->CharSet = 'UTF-8';
            $mail->setFrom((string) ($c['from_email'] ?? 'no-reply@mcrelish.com'), (string) ($c['from_name'] ?? 'McRelish'));
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->isHTML(true);
            $mail->Body = $html;
            $mail->AltBody = $text;
            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log('Mail failed: ' . $e->getMessage());
            self::log($to, $subject, $text);
            return false;
        }
    }

    private static function log(string $to, string $subject, string $text): void
    {
        $dir = dirname(__DIR__, 2) . '/storage';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        @file_put_contents($dir . '/mail.log', sprintf("[%s] To: %s\nSubject: %s\n%s\n\n", date('c'), $to, $subject, $text), FILE_APPEND | LOCK_EX);
    }
}
