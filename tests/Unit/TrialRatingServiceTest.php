<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\TrialRatingService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class TrialRatingServiceTest extends TestCase
{
    private TrialRatingService $ratings;

    protected function setUp(): void
    {
        $this->ratings = new TrialRatingService();
    }

    /** @dataProvider categoryBoundaries */
    public function testCategoryBoundaries(string $value, string $category): void
    {
        self::assertSame($category, $this->ratings->category($value));
    }

    public static function categoryBoundaries(): array
    {
        return [
            ['0.0', 'Raw'],
            ['1', 'Raw'],
            ['1.5', 'Raw'],
            ['1.6', 'Basic'],
            ['2.5', 'Basic'],
            ['2.6', 'Regular'],
            ['3.5', 'Regular'],
            ['3.6', 'Advanced'],
            ['5.5', 'Advanced'],
            ['5.6', 'Excellent'],
            ['10', 'Excellent'],
        ];
    }

    public function testBlankRatingIsNotRated(): void
    {
        self::assertNull($this->ratings->normalize(null));
        self::assertNull($this->ratings->category(''));
        self::assertSame(['value' => null, 'category' => null], $this->ratings->present(null));
    }

    public function testDecimalValuesAreNormalizedWithoutLosingPrecision(): void
    {
        self::assertSame('1.6', $this->ratings->normalize('1.6'));
        self::assertSame('1.6', $this->ratings->normalize('1.6 - Basic'));
        self::assertSame('5.6', $this->ratings->normalize('5.6'));
        self::assertSame(2.5, $this->ratings->numeric('2.5'));
        self::assertSame(2, $this->ratings->numeric('2'));
        self::assertSame('2.0 - Basic', $this->ratings->formatted('2'));
        self::assertSame('5.6 - Excellent', $this->ratings->formatted('5.6 - ignored'));
    }

    /** @dataProvider invalidValues */
    public function testInvalidOrOutOfRangeRatingsAreRejected(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->ratings->normalize($value);
    }

    public static function invalidValues(): array
    {
        return [
            ['-0.1'],
            ['10.1'],
            ['1.23'],
            ['1.234'],
            ['rating'],
        ];
    }
}
