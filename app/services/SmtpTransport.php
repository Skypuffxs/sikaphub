<?php

/**
 * Native PHP SMTP Transport for sending email over SSL/TLS (e.g. Gmail SMTP).
 * Supports AUTH LOGIN, multipart text/HTML messages, and custom ports.
 */
class SmtpTransport
{
    private $host;
    private $port;
    private $encryption;
    private $username;
    private $password;
    private $fromAddress;
    private $fromName;

    public function __construct(array $config = [])
    {
        $this->host        = $config['host']        ?? $_ENV['MAIL_HOST']         ?? 'smtp.gmail.com';
        $this->port        = (int) ($config['port']  ?? $_ENV['MAIL_PORT']         ?? 465);
        $this->encryption  = $config['encryption']  ?? $_ENV['MAIL_ENCRYPTION']   ?? 'ssl';
        $this->username    = $config['username']    ?? $_ENV['MAIL_USERNAME']     ?? 'sikaphubinfo@gmail.com';
        $this->password    = str_replace(' ', '', $config['password'] ?? $_ENV['MAIL_PASSWORD'] ?? '');
        $this->fromAddress = $config['from_email']  ?? $_ENV['MAIL_FROM_ADDRESS'] ?? $this->username;
        $this->fromName    = $config['from_name']   ?? $_ENV['MAIL_FROM_NAME']    ?? 'SIKAPHub';
    }

    public function send(string $toEmail, string $subject, string $htmlBody, string $textBody = ''): bool
    {
        if (empty($this->username) || empty($this->password)) {
            throw new RuntimeException('SMTP username or password is not configured in .env');
        }

        $remote = ($this->encryption === 'ssl' ? 'ssl://' : '') . $this->host;
        $socket = @stream_socket_client("{$remote}:{$this->port}", $errno, $errstr, 15);

        if (!$socket) {
            throw new RuntimeException("Could not connect to SMTP server {$this->host}:{$this->port} — {$errstr} ({$errno})");
        }

        try {
            $this->readResponse($socket, 220);
            $this->sendCommand($socket, "EHLO localhost", 250);

            if ($this->encryption === 'tls') {
                $this->sendCommand($socket, "STARTTLS", 220);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
                    throw new RuntimeException("Failed to negotiate TLS encryption");
                }
                $this->sendCommand($socket, "EHLO localhost", 250);
            }

            // Authenticate with Gmail / SMTP server
            $this->sendCommand($socket, "AUTH LOGIN", 334);
            $this->sendCommand($socket, base64_encode($this->username), 334);
            $this->sendCommand($socket, base64_encode($this->password), 235);

            // Envelope
            $this->sendCommand($socket, "MAIL FROM: <{$this->fromAddress}>", 250);
            $this->sendCommand($socket, "RCPT TO: <{$toEmail}>", 250);

            // Send message content
            $this->sendCommand($socket, "DATA", 354);

            $boundary = "=_SikapHub_" . md5(uniqid((string) time(), true));

            $headers   = [];
            $headers[] = "From: =?UTF-8?B?" . base64_encode($this->fromName) . "?= <{$this->fromAddress}>";
            $headers[] = "To: <{$toEmail}>";
            $headers[] = "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=";
            $headers[] = "MIME-Version: 1.0";
            $headers[] = "Content-Type: multipart/alternative; boundary=\"{$boundary}\"";
            $headers[] = "X-Mailer: SIKAP-Hub-Mailer/2.0";
            $headers[] = "";

            $message   = implode("\r\n", $headers);
            $message  .= "--{$boundary}\r\n";
            $message  .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $message  .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
            $message  .= ($textBody ?: strip_tags($htmlBody)) . "\r\n\r\n";

            $message  .= "--{$boundary}\r\n";
            $message  .= "Content-Type: text/html; charset=UTF-8\r\n";
            $message  .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
            $message  .= $htmlBody . "\r\n\r\n";
            $message  .= "--{$boundary}--\r\n.";

            $this->sendCommand($socket, $message, 250);
            $this->sendCommand($socket, "QUIT", 221);

            fclose($socket);
            return true;

        } catch (Throwable $e) {
            @fclose($socket);
            throw $e;
        }
    }

    private function sendCommand($socket, string $cmd, int $expectedCode)
    {
        fwrite($socket, $cmd . "\r\n");
        return $this->readResponse($socket, $expectedCode);
    }

    private function readResponse($socket, int $expectedCode)
    {
        $response = '';
        while ($line = fgets($socket, 512)) {
            $response .= $line;
            if (substr($line, 3, 1) === ' ') break;
        }
        $code = (int) substr($response, 0, 3);
        if ($code !== $expectedCode) {
            throw new RuntimeException("SMTP Error [Expected {$expectedCode}, Got {$code}]: " . trim($response));
        }
        return $response;
    }
}
