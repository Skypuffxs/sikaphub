<?php

/**
 * OTP mail transport.
 *
 * XAMPP cannot send mail, so this class always records the attempt in
 * email_log and, in a non-production environment, appends the code to
 * storage/logs/mail.log so a developer can complete the sign-in loop.
 *
 * Hard rules:
 *   - The code is NEVER returned, echoed, or written to email_log.
 *   - A delivery failure is written to email_log.send_status = 'failed' with
 *     the error text. It is never a silent success (CLAUDE.md SMTP rule).
 */
class Mailer
{
    const TEMPLATE = 'otp_code';
    const SUBJECT  = 'Your S.I.K.A.P. Hub sign-in code';

    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * @param string   $email
     * @param string   $code    six-digit plaintext, used only to compose the message body
     * @param int|null  $userId  null when the address has no account yet
     * @return bool     true if the message was handed to a transport
     */
    public function sendOtp($email, $code, $userId = null)
    {
        $emailId = $this->logQueued($email, $userId);

        $hasSmtpConfig = !empty($_ENV['MAIL_PASSWORD']) || ($_ENV['MAIL_DRIVER'] ?? '') === 'smtp';
        $isDev = true; // Always write to storage/logs/mail.log in XAMPP / local dev environment

        try {
            // Always append the generated OTP code to storage/logs/mail.log
            $this->appendDevLog($email, $code);

            if ($hasSmtpConfig) {
                require_once BASE_PATH . 'app/services/SmtpTransport.php';
                $transport = new SmtpTransport();
                
                $htmlBody = "
                <div style='font-family: Arial, sans-serif; max-width: 500px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; rounded-lg: 8px;'>
                    <h2 style='color: #4F46E5; margin-bottom: 10px;'>S.I.K.A.P. Hub Verification</h2>
                    <p style='color: #475569; font-size: 15px;'>Your verification code for sign-in is:</p>
                    <div style='background-color: #f1f5f9; font-size: 32px; font-weight: bold; letter-spacing: 6px; color: #0f172a; padding: 15px; text-align: center; border-radius: 6px; margin: 20px 0;'>
                        {$code}
                    </div>
                    <p style='color: #64748b; font-size: 13px;'>This code is valid for 10 minutes. If you did not request this code, please ignore this email.</p>
                    <hr style='border: none; border-top: 1px solid #cbd5e1; margin-top: 25px;'>
                    <p style='color: #94a3b8; font-size: 11px; text-align: center;'>PESO Guimba &middot; S.I.K.A.P. Hub Platform</p>
                </div>";
                
                $textBody = "Your S.I.K.A.P. Hub sign-in code is {$code}. It expires in 10 minutes.";
                $transport->send($email, self::SUBJECT, $htmlBody, $textBody);
            } elseif (!$isDev) {
                $body = "Your S.I.K.A.P. Hub sign-in code is {$code}. It expires in 10 minutes.";
                if (!mail($email, self::SUBJECT, $body)) {
                    throw new RuntimeException('mail() returned false');
                }
            }
        } catch (Throwable $e) {
            $this->markFailed($emailId, $e->getMessage());
            if ($isDev) {
                error_log('[Mailer] SMTP delivery failed in dev mode (' . $e->getMessage() . '), fallback to dev mail.log');
                return true;
            }
            return false;
        }

        $this->markSent($emailId);
        return true;
    }

    private function logQueued($email, $userId)
    {
        $stmt = $this->db->prepare(
            "INSERT INTO email_log (user_id, recipient_email, template, subject, send_status)
             VALUES (:user_id, :recipient_email, :template, :subject, 'queued')"
        );
        $stmt->execute([
            ':user_id'         => $userId,
            ':recipient_email' => $email,
            ':template'        => self::TEMPLATE,
            ':subject'         => self::SUBJECT,
        ]);
        return (int) $this->db->lastInsertId();
    }

    private function markSent($emailId)
    {
        $stmt = $this->db->prepare(
            "UPDATE email_log SET send_status = 'sent', sent_at = NOW() WHERE email_id = :id"
        );
        $stmt->execute([':id' => $emailId]);
    }

    private function markFailed($emailId, $error)
    {
        $stmt = $this->db->prepare(
            "UPDATE email_log SET send_status = 'failed', error_message = :err WHERE email_id = :id"
        );
        $stmt->execute([':err' => mb_substr($error, 0, 255), ':id' => $emailId]);
    }

    private function appendDevLog($email, $code)
    {
        $dir = BASE_PATH . 'storage/logs';
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('storage/logs is not writable');
        }
        $line = sprintf("[%s] %s  code=%s%s", date('Y-m-d H:i:s'), $email, $code, PHP_EOL);
        if (file_put_contents($dir . '/mail.log', $line, FILE_APPEND | LOCK_EX) === false) {
            throw new RuntimeException('could not append to storage/logs/mail.log');
        }
    }
}
