<?php

declare(strict_types=1);

namespace TwigMetrics\Tests\Calculator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use TwigMetrics\Calculator\StatisticalCalculator;

#[CoversClass(StatisticalCalculator::class)]
final class StatisticalCalculatorTest extends TestCase
{
    public function testCalculateExtendedWithValues(): void
    {
        $calc = new StatisticalCalculator();
        $stats = $calc->calculate([1, 2, 3, 4, 5, 10]);

        $this->assertSame(6, $stats->count);
        $this->assertEquals(25.0, $stats->sum);
        $this->assertEquals(25.0 / 6.0, $stats->mean);
        $this->assertEquals(3.5, $stats->median);
        $this->assertGreaterThan(0.0, $stats->stdDev);
        $this->assertGreaterThanOrEqual(0.0, $stats->coefficientOfVariation);
        $this->assertEquals(2.25, $stats->p25);
        $this->assertEquals(4.75, $stats->p75);
        $this->assertEqualsWithDelta(8.75, $stats->p95, 0.15);
        $this->assertEquals(1.0, $stats->min);
        $this->assertEquals(10.0, $stats->max);
        $this->assertEquals(9.0, $stats->range);
        $this->assertGreaterThanOrEqual(0.0, $stats->giniIndex);
        $this->assertGreaterThanOrEqual(0.0, $stats->entropy);
    }

    public function testCalculateExtendedEmpty(): void
    {
        $calc = new StatisticalCalculator();
        $stats = $calc->calculate([]);
        $this->assertSame(0, $stats->count);
        $this->assertSame(0.0, $stats->sum);
        $this->assertSame(0.0, $stats->mean);
        $this->assertSame(0.0, $stats->median);
        $this->assertSame(0.0, $stats->stdDev);
        $this->assertSame(0.0, $stats->coefficientOfVariation);
        $this->assertSame(0.0, $stats->p25);
        $this->assertSame(0.0, $stats->p75);
        $this->assertSame(0.0, $stats->p95);
        $this->assertSame(0.0, $stats->min);
        $this->assertSame(0.0, $stats->max);
        $this->assertSame(0.0, $stats->range);
    }
}
