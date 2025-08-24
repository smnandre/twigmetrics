<?php

declare(strict_types=1);

namespace TwigMetrics\Tests\Analyzer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TwigMetrics\Analyzer\TemplateRating;

#[CoversClass(TemplateRating::class)]
final class TemplateRatingTest extends TestCase
{
    #[DataProvider('provideSizeRatings')]
    public function testCalculateSizeRating(int $lines, string $expectedRating): void
    {
        $this->assertSame($expectedRating, TemplateRating::calculateSizeRating($lines));
    }

    public static function provideSizeRatings(): iterable
    {
        yield 'small template (A)' => [50, 'A'];
        yield 'medium template (B)' => [85, 'B'];
        yield 'large template (C)' => [125, 'C'];
        yield 'very large template (D)' => [200, 'D'];
        yield 'boundary case A/B' => [74, 'A'];
        yield 'boundary case B/C' => [99, 'B'];
        yield 'boundary case C/D' => [149, 'C'];
    }

    #[DataProvider('provideComplexityRatings')]
    public function testCalculateComplexityRating(int $complexity, int $depth, string $expectedRating): void
    {
        $this->assertSame($expectedRating, TemplateRating::calculateComplexityRating($complexity, $depth));
    }

    public static function provideComplexityRatings(): iterable
    {
        yield 'low complexity (A)' => [5, 2, 'A'];
        yield 'moderate complexity (B)' => [12, 3, 'B'];
        yield 'high complexity (C)' => [20, 4, 'C'];
        yield 'very high complexity (D)' => [30, 6, 'D'];
        yield 'boundary case A/B complexity' => [7, 2, 'A'];
        yield 'boundary case B/C complexity' => [14, 3, 'B'];
        yield 'boundary case depth fails A' => [5, 4, 'C'];
        yield 'boundary case depth fails B' => [12, 5, 'D'];
    }
}
