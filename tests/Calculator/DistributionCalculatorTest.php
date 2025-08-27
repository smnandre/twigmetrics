<?php

declare(strict_types=1);

namespace TwigMetrics\Tests\Calculator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\SplFileInfo;
use TwigMetrics\Analyzer\AnalysisResult;
use TwigMetrics\Calculator\DistributionCalculator;

#[CoversClass(DistributionCalculator::class)]
final class DistributionCalculatorTest extends TestCase
{
    public function testCalculateSizeDistribution(): void
    {
        $results = [
            $this->makeResult(['lines' => 10], 'a.twig'),
            $this->makeResult(['lines' => 60], 'b.twig'),
            $this->makeResult(['lines' => 120], 'c.twig'),
            $this->makeResult(['lines' => 220], 'd.twig'),
            $this->makeResult(['lines' => 800], 'e.twig'),
        ];

        $calc = new DistributionCalculator();
        $dist = $calc->calculateSizeDistribution($results);

        $this->assertSame(1, $dist['0-50']['count']);
        $this->assertSame(1, $dist['51-100']['count']);
        $this->assertSame(1, $dist['101-200']['count']);
        $this->assertSame(1, $dist['201-500']['count']);
        $this->assertSame(1, $dist['500+']['count']);
        $this->assertEquals(20.0, $dist['201-500']['percentage']);
    }

    private function makeResult(array $metrics, string $rel): AnalysisResult
    {
        $file = new SplFileInfo(__FILE__, '', $rel);

        return new AnalysisResult($file, $metrics, 0.0);
    }
}
