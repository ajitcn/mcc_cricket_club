<?php

declare(strict_types=1);

namespace Tests\Database;

use App\Services\PortalAuthService;
use App\Services\PortalOtpDebugService;
use App\Services\WhatsAppOtpProviderInterface;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

final class PortalAuthenticationSafeTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $DBGroup = 'tests';
    protected $migrate = false;

    protected function setUp(): void
    {
        parent::setUp();
        if (! $this->db->tableExists('portal_otp_challenges')) {
            self::markTestSkipped('Portal OTP migration is not applied in this verification environment.');
        }
    }

    public function testPortalOtpSchemaHasSecureChallengeFields(): void
    {
        $fields = array_map(
            static fn (object $field): string => (string) $field->name,
            $this->db->getFieldData('portal_otp_challenges')
        );

        foreach (['account_type', 'mobile_hash', 'session_hash', 'otp_hash', 'expires_at', 'attempt_count', 'requested_at', 'consumed_at', 'provider_status'] as $field) {
            self::assertContains($field, $fields);
        }
    }

    public function testStudentOtpRequestAndVerificationUsesSyntheticActivePlayer(): void
    {
        [$mobile] = $this->createSyntheticStudent();
        session()->destroy();
        $this->resetOtpCooldown('STUDENT');
        self::assertSame(1, $this->db->table('players p')
            ->join('persons pe', 'pe.id = p.person_id')
            ->join('branches b', 'b.id = p.branch_id')
            ->where(['p.status' => 'active', 'b.is_active' => 1, 'pe.phone' => $mobile])
            ->where('pe.mobile_verified_at IS NOT NULL', null, false)
            ->countAllResults());

        $provider = new class implements WhatsAppOtpProviderInterface {
            public string $otp = '';

            public function sendOtp(string $mobile, string $otp): bool
            {
                $this->otp = $otp;
                return true;
            }
        };

        putenv('PORTAL_OTP_DEBUG=true');
        $_ENV['PORTAL_OTP_DEBUG'] = 'true';
        try {
            $service = new PortalAuthService($provider, new PortalOtpDebugService());
            $otp = $service->requestOtp('student', $mobile);

            self::assertNotNull($otp);
            self::assertSame($provider->otp, $otp);
            $challenge = $this->db->table('portal_otp_challenges')
                ->where('account_type', 'STUDENT')
                ->orderBy('id', 'DESC')
                ->get(1)
                ->getRowArray();
            self::assertIsArray($challenge);
            self::assertSame(hash('sha256', (string) session_id()), $challenge['session_hash']);
            self::assertSame('sent', $challenge['provider_status']);
            self::assertTrue(password_verify((string) $otp, (string) $challenge['otp_hash']));
            self::assertTrue($service->verifyOtp('student', $mobile, $otp));
            self::assertTrue($service->isAuthenticated());
        } finally {
            putenv('PORTAL_OTP_DEBUG=false');
            $_ENV['PORTAL_OTP_DEBUG'] = 'false';
        }
    }

    public function testParentOtpRequestAndVerificationUsesSyntheticVerifiedGuardian(): void
    {
        [$mobile] = $this->createSyntheticParent();
        session()->destroy();
        $this->resetOtpCooldown('PARENT');

        $provider = new class implements WhatsAppOtpProviderInterface {
            public string $otp = '';

            public function sendOtp(string $mobile, string $otp): bool
            {
                $this->otp = $otp;
                return true;
            }
        };

        putenv('PORTAL_OTP_DEBUG=true');
        $_ENV['PORTAL_OTP_DEBUG'] = 'true';
        try {
            $service = new PortalAuthService($provider, new PortalOtpDebugService());
            $otp = $service->requestOtp('parent', $mobile);

            self::assertNotNull($otp);
            self::assertSame($provider->otp, $otp);
            $challenge = $this->db->table('portal_otp_challenges')
                ->where('account_type', 'PARENT')
                ->orderBy('id', 'DESC')
                ->get(1)
                ->getRowArray();
            self::assertIsArray($challenge);
            self::assertSame(hash('sha256', (string) session_id()), $challenge['session_hash']);
            self::assertSame('sent', $challenge['provider_status']);
            self::assertTrue($service->verifyOtp('parent', $mobile, $otp));
            self::assertTrue($service->isAuthenticated());
        } finally {
            putenv('PORTAL_OTP_DEBUG=false');
            $_ENV['PORTAL_OTP_DEBUG'] = 'false';
        }
    }

    public function testWrongOtpRemainsInvalid(): void
    {
        [$mobile] = $this->createSyntheticStudent();
        session()->destroy();
        $this->resetOtpCooldown('STUDENT');
        [$service] = $this->testingService();

        putenv('PORTAL_OTP_DEBUG=true');
        $_ENV['PORTAL_OTP_DEBUG'] = 'true';
        $otp = $service->requestOtp('student', $mobile);

        self::assertNotNull($otp);
        self::assertFalse($service->verifyOtp('student', $mobile, '000000'));
    }

    public function testExpiredOtpRemainsInvalid(): void
    {
        [$mobile] = $this->createSyntheticStudent();
        session()->destroy();
        $this->resetOtpCooldown('STUDENT');
        [$service] = $this->testingService();

        putenv('PORTAL_OTP_DEBUG=true');
        $_ENV['PORTAL_OTP_DEBUG'] = 'true';
        $otp = $service->requestOtp('student', $mobile);
        $this->db->table('portal_otp_challenges')
            ->where(['account_type' => 'STUDENT', 'session_hash' => hash('sha256', (string) session_id())])
            ->set('expires_at', gmdate('Y-m-d H:i:s', time() - 1))
            ->update();

        self::assertFalse($service->verifyOtp('student', $mobile, (string) $otp));
    }

    public function testOtpCannotBeReused(): void
    {
        [$mobile] = $this->createSyntheticStudent();
        session()->destroy();
        $this->resetOtpCooldown('STUDENT');
        [$service] = $this->testingService();

        putenv('PORTAL_OTP_DEBUG=true');
        $_ENV['PORTAL_OTP_DEBUG'] = 'true';
        $otp = $service->requestOtp('student', $mobile);

        self::assertTrue($service->verifyOtp('student', $mobile, (string) $otp));
        self::assertFalse($service->verifyOtp('student', $mobile, (string) $otp));
    }

    public function testSessionHashMismatchIsRejected(): void
    {
        [$mobile] = $this->createSyntheticStudent();
        session()->destroy();
        $this->resetOtpCooldown('STUDENT');
        [$service] = $this->testingService();

        putenv('PORTAL_OTP_DEBUG=true');
        $_ENV['PORTAL_OTP_DEBUG'] = 'true';
        $otp = $service->requestOtp('student', $mobile);
        $this->db->table('portal_otp_challenges')
            ->where(['account_type' => 'STUDENT', 'session_hash' => hash('sha256', (string) session_id())])
            ->set('session_hash', str_repeat('0', 64))
            ->update();

        self::assertFalse($service->verifyOtp('student', $mobile, (string) $otp));
    }

    public function testResendInvalidatesThePreviousChallenge(): void
    {
        [$mobile] = $this->createSyntheticStudent();
        session()->destroy();
        $this->resetOtpCooldown('STUDENT');
        [$service] = $this->testingService();

        putenv('PORTAL_OTP_DEBUG=true');
        $_ENV['PORTAL_OTP_DEBUG'] = 'true';
        $service->requestOtp('student', $mobile);
        $first = $this->db->table('portal_otp_challenges')
            ->where(['account_type' => 'STUDENT', 'session_hash' => hash('sha256', (string) session_id())])
            ->orderBy('id', 'DESC')->get(1)->getRowArray();
        self::assertIsArray($first);
        $this->resetOtpCooldown('STUDENT');
        $service->requestOtp('student', $mobile);

        $latest = $this->db->table('portal_otp_challenges')
            ->where(['account_type' => 'STUDENT', 'session_hash' => hash('sha256', (string) session_id())])
            ->orderBy('id', 'DESC')->get(1)->getRowArray();
        self::assertIsArray($latest);
        self::assertNotSame((int) $first['id'], (int) $latest['id']);
        self::assertSame('invalidated', $this->db->table('portal_otp_challenges')
            ->where('id', (int) $first['id'])->get()->getRowArray()['provider_status']);
    }

    /** @return array{string, int} */
    private function createSyntheticStudent(): array
    {
        $marker = 'PORTAL_OTP_STUDENT_20261002';
        $existing = $this->db->table('persons')->where('email', strtolower($marker) . '@mcc.test')->get()->getRowArray();
        if (is_array($existing)) {
            $player = $this->db->table('players')->where('person_id', (int) $existing['id'])->get()->getRowArray();
            return [(string) $existing['phone'], (int) $player['id']];
        }

        $branch = $this->db->table('branches')->where('is_active', 1)->orderBy('id', 'ASC')->get()->getRowArray();
        self::assertIsArray($branch);
        $now = gmdate('Y-m-d H:i:s');
        $mobile = '+919900000101';
        $email = strtolower($marker) . '@mcc.test';
        $this->db->table('persons')->insert([
            'full_name' => $marker,
            'email' => $email,
            'phone' => $mobile,
            'mobile_verified_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $personId = (int) $this->db->insertID();
        $this->db->table('leads')->insert([
            'branch_id' => (int) $branch['id'],
            'source' => 'portal-otp-test',
            'full_name' => $marker,
            'email' => $email,
            'phone' => $mobile,
            'status' => 'new',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $leadId = (int) $this->db->insertID();
        $this->db->table('players')->insert([
            'person_id' => $personId,
            'lead_id' => $leadId,
            'branch_id' => (int) $branch['id'],
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [$mobile, (int) $this->db->insertID()];
    }

    /** @return array{string, int} */
    private function createSyntheticParent(): array
    {
        $marker = 'PORTAL_OTP_PARENT_20261002';
        $existing = $this->db->table('persons')->where('email', strtolower($marker) . '@mcc.test')->get()->getRowArray();
        if (is_array($existing)) {
            $contact = $this->db->table('player_contacts')->where('contact_person_id', (int) $existing['id'])->get()->getRowArray();
            return [(string) $existing['phone'], (int) $contact['player_id']];
        }

        [$studentMobile, $playerId] = $this->createSyntheticStudent();
        $branch = $this->db->table('players')->select('branch_id')->where('id', $playerId)->get()->getRowArray();
        self::assertIsArray($branch);
        $now = gmdate('Y-m-d H:i:s');
        $mobile = '+919900000102';
        $email = strtolower($marker) . '@mcc.test';
        $this->db->table('persons')->insert([
            'full_name' => $marker,
            'email' => $email,
            'phone' => $mobile,
            'mobile_verified_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $contactId = (int) $this->db->insertID();
        $this->db->table('player_contacts')->insert([
            'player_id' => $playerId,
            'contact_person_id' => $contactId,
            'relationship_type' => 'guardian',
            'is_primary' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [$mobile, $playerId];
    }

    private function resetOtpCooldown(string $type): void
    {
        $this->db->table('portal_otp_challenges')
            ->where([
                'account_type' => $type,
                'session_hash' => hash('sha256', (string) session_id()),
            ])
            ->set('requested_at', gmdate('Y-m-d H:i:s', time() - 120))
            ->update();
    }

    /** @return array{PortalAuthService, WhatsAppOtpProviderInterface} */
    private function testingService(): array
    {
        $provider = new class implements WhatsAppOtpProviderInterface {
            public function sendOtp(string $mobile, string $otp): bool
            {
                return true;
            }
        };

        return [new PortalAuthService($provider, new PortalOtpDebugService()), $provider];
    }
}
