<?php
namespace Pika;

use PHPMailer\PHPMailer\PHPMailer;

/**
 * Sends plain-text mail. MAIL_DRIVER: "smtp" (PHPMailer) or "log" (writes the message to the log; local use only).
 * Settings: MAIL_HOST, MAIL_PORT, MAIL_USER, MAIL_PASS, MAIL_ENCRYPTION (tls|ssl|none), MAIL_FROM, MAIL_FROM_NAME.
 */
class Mail
{
    public static function send(string $to, string $subject, string $text): bool
    {
        $subject = str_replace(["\r", "\n"], ' ', $subject);
        $driver = pika_env('MAIL_DRIVER', APP_ENV === 'local' ? 'log' : 'smtp');

        if ($driver === 'log') {
            if (APP_ENV !== 'local') {
                throw new \RuntimeException('MAIL_DRIVER=log is only allowed when APP_ENV=local.');
            }
            log_message('mail', "to=$to subject=$subject\n$text");
            return true;
        }

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = (string) pika_env('MAIL_HOST', '');
            $mail->Port = (int) pika_env('MAIL_PORT', 587);
            $mail->SMTPAuth = pika_env('MAIL_USER', '') !== '';
            $mail->Username = (string) pika_env('MAIL_USER', '');
            $mail->Password = (string) pika_env('MAIL_PASS', '');
            $enc = pika_env('MAIL_ENCRYPTION', 'tls');
            $mail->SMTPSecure = $enc === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : ($enc === 'none' ? '' : PHPMailer::ENCRYPTION_STARTTLS);
            $mail->CharSet = 'UTF-8';
            $mail->setFrom((string) pika_env('MAIL_FROM', ''), (string) pika_env('MAIL_FROM_NAME', 'Pika'));
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->Body = $text;
            $mail->isHTML(false);
            return $mail->send();
        } catch (\Throwable $e) {
            log_message('error', 'Mail failed: ' . $e->getMessage());
            return false;
        }
    }
}
