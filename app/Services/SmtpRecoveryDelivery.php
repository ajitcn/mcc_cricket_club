<?php

declare(strict_types=1);

namespace App\Services;

use CodeIgniter\Email\Email;
use Config\Email as EmailConfig;
use Throwable;

final class SmtpRecoveryDelivery implements RecoveryDeliveryInterface
{
    public function __construct(
        private readonly ?Email $mailer = null,
        private readonly ?EmailConfig $config = null,
    ) {
    }

    public function deliver(string $recipient, string $token): void
    {
        $config = $this->config ?? config(EmailConfig::class);
        $this->assertConfigured($config);

        $mailer = $this->mailer ?? service('email', $config, false);

        try {
            $mailer->initialize($config);
            $mailer->setFrom($config->fromEmail, $config->fromName);
            $mailer->setTo($recipient);
            $mailer->setSubject('MCC account recovery');
            $mailer->setMessage("Use this recovery token to reset your MCC password:\n\n" . $token . "\n\nThis token expires soon and can be used once.");

            if (! $mailer->send()) {
                throw new RecoveryDeliveryException('Recovery delivery failed.');
            }
        } catch (RecoveryDeliveryException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new RecoveryDeliveryException('Recovery delivery failed.', 0, $exception);
        }
    }

    private function assertConfigured(EmailConfig $config): void
    {
        if ($config->protocol !== 'smtp'
            || $config->SMTPHost === ''
            || $config->SMTPPort < 1
            || $config->SMTPTimeout < 1
            || $config->SMTPCrypto === '') {
            throw new RecoveryDeliveryException('Recovery delivery is not configured.');
        }

        if ($config->SMTPAuthMethod === '' || $config->SMTPUser === '' || $config->SMTPPass === '') {
            throw new RecoveryDeliveryException('Recovery delivery authentication is not configured.');
        }

        if ($config->fromEmail === '') {
            throw new RecoveryDeliveryException('Recovery sender is not configured.');
        }
    }
}
