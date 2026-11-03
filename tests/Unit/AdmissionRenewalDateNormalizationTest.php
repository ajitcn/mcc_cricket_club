<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\AdmissionService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class AdmissionRenewalDateNormalizationTest extends TestCase
{
    private ReflectionMethod $normalizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->normalizer = new ReflectionMethod(AdmissionService::class, 'normalizeRenewalDate');
        $this->normalizer->setAccessible(true);
    }

    public function testSupportedRenewalDateFormatsBecomeCalendarDates(): void
    {
        $service = new AdmissionService();

        self::assertSame('2026-12-31', $this->normalizer->invoke($service, '2026-12-31'));
        self::assertSame('2026-12-31', $this->normalizer->invoke($service, '31-12-2026'));
        self::assertSame('2026-12-31', $this->normalizer->invoke($service, '2026-12-31 23:59:59'));
        self::assertSame('2026-12-31', $this->normalizer->invoke($service, '31-12-2026 23:59:59'));
    }

    public function testMccTimezoneBoundaryUsesTheLocalCalendarDate(): void
    {
        $service = new AdmissionService();

        self::assertSame(
            '2027-01-01',
            $this->normalizer->invoke($service, '2026-12-31T18:30:00+00:00'),
        );
    }

    public function testInvalidRenewalDatesAreRejected(): void
    {
        $service = new AdmissionService();

        self::assertNull($this->normalizer->invoke($service, '31-02-2026'));
        self::assertNull($this->normalizer->invoke($service, 'not-a-date'));
    }
}
