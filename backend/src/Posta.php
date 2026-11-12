<?php
declare(strict_types=1);

namespace Nacenime;

use PHPMailer\PHPMailer\{Exception, PHPMailer};

/** Odoslanie e-mailu s PDF cez SMTP z konfigurácie. */
final class Posta
{
    public function __construct(private Config $config) {}

    public function posli(string $komu, string $predmet, string $telo, string $pdf, string $nazovPrilohy): void
    {
        $host = $this->config->get('SMTP_HOST');
        if ($host === '') {
            throw new \RuntimeException('SMTP nie je nakonfigurované (NACENIME_SMTP_HOST).');
        }
        $m = new PHPMailer(true);
        try {
            $m->isSMTP();
            $m->Host = $host;
            $m->Port = (int) $this->config->get('SMTP_PORT', '587');
            $secure = $this->config->get('SMTP_SECURE', 'tls'); // tls | ssl | none
            $m->SMTPSecure = $secure === 'none' ? '' : $secure;
            $m->SMTPAutoTLS = $secure !== 'none';
            $m->Timeout = 10;
            if ($this->config->get('SMTP_USER') !== '') {
                $m->SMTPAuth = true;
                $m->Username = $this->config->get('SMTP_USER');
                $m->Password = $this->config->get('SMTP_PASS');
            }
            $m->CharSet = 'UTF-8';
            $m->setFrom($this->config->get('MAIL_FROM', 'noreply@nacenime.sk'), 'nacenime.sk');
            $m->addAddress($komu);
            $m->Subject = $predmet;
            $m->Body = $telo;
            $m->addStringAttachment($pdf, $nazovPrilohy, PHPMailer::ENCODING_BASE64, 'application/pdf');
            $m->send();
        } catch (Exception $e) {
            throw new \RuntimeException('Odoslanie e-mailu zlyhalo: ' . $e->getMessage(), 0, $e); // bez hesla
        }
    }
}
