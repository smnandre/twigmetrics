<?php

declare(strict_types=1);

namespace TwigMetrics\Tests\Calculator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use TwigMetrics\Calculator\DiversityCalculator;

#[CoversClass(DiversityCalculator::class)]
final class DiversityCalculatorTest extends TestCase
{
    public function testDiversityAndEntropy(): void
    {
        $calc = new DiversityCalculator();
        $usage = ['a' => 10, 'b' => 10, 'c' => 0];
        $div = $calc->calculateSimpsonDiversity($usage);
        $ent = $calc->calculateUsageEntropy($usage);

        $this->assertGreaterThan(0.0, $div);
        $this->assertGreaterThan(0.0, $ent);
    }
}
