<?php

declare (strict_types=1);
namespace Tests\Unit;

use App\Services\RecoveryDeliveryException;
use App\Services\SmtpRecoveryDelivery;
use CodeIgniter\Email\Email;
use Config\Email as EmailConfig;
use PHPUnit\Framework\TestCase;
final class SmtpRecoveryDeliveryTest extends TestCase
{
    public function testConfiguredMailerDeliversThroughCodeIgniterEmailBoundary(): void
    {
        $token = bin2hex(random_bytes(32));
        $mailer = $this->createMock(Email::class);
        $mailer->expects(self::once())->method('initialize')->willReturnSelf();
        $mailer->expects(self::once())->method('setFrom')->with('recovery@mcc.example', 'MCC Cricket Club')->willReturnSelf();
        $mailer->expects(self::once())->method('setTo')->with('player@example.test')->willReturnSelf();
        $mailer->expects(self::once())->method('setSubject')->with('MCC account recovery')->willReturnSelf();
        $mailer->expects(self::once())->method('setMessage')->with(self::stringContains($token))->willReturnSelf();
        $mailer->expects(self::once())->method('send')->willReturn(true);
        $delivery = new SmtpRecoveryDelivery($mailer, $this->config());
        $delivery->deliver('player@example.test', $token);
        self::assertTrue(true);
    }
    public function testMailerFailureIsConvertedToSafeDeliveryException(): void
    {
        $token = bin2hex(random_bytes(32));
        $mailer = $this->createMock(Email::class);
        $mailer->method('initialize')->willReturnSelf();
        $mailer->method('setFrom')->willReturnSelf();
        $mailer->method('setTo')->willReturnSelf();
        $mailer->method('setSubject')->willReturnSelf();
        $mailer->method('setMessage')->willReturnSelf();
        $mailer->method('send')->willReturn(false);
        try {
            (new SmtpRecoveryDelivery($mailer, $this->config()))->deliver('player@example.test', $token);
            self::fail('Delivery unexpectedly succeeded.');
        } catch (RecoveryDeliveryException $exception) {
            self::assertSame('Recovery delivery failed.', $exception->getMessage());
            self::assertStringNotContainsString($token, $exception->getMessage());
        }
    }
    public function testIncompleteDeploymentConfigurationFailsBeforeMailerUse(): void
    {
        $token = bin2hex(random_bytes(32));
        $mailer = $this->createMock(Email::class);
        $mailer->expects(self::never())->method('send');
        $config = $this->config();
        $config->SMTPCrypto = '';
        $this->expectException(RecoveryDeliveryException::class);
        (new SmtpRecoveryDelivery($mailer, $config))->deliver('player@example.test', $token);
    }
    private function config(): EmailConfig
    {
        $config = new EmailConfig();
        $config->fromEmail = 'recovery@mcc.example';
        $config->fromName = 'MCC Cricket Club';
        $config->protocol = 'smtp';
        $config->SMTPHost = 'smtp.test.invalid';
        $config->SMTPAuthMethod = 'login';
        $config->SMTPUser = 'configured-user';
        $config->SMTPPass = bin2hex(random_bytes(24));
        $config->SMTPPort = 2525;
        $config->SMTPTimeout = 5;
        $config->SMTPCrypto = 'tls';
        return $config;
    }
}
