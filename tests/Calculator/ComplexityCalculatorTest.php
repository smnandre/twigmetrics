<?php

declare(strict_types=1);

namespace TwigMetrics\Tests\Calculator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\SplFileInfo;
use TwigMetrics\Analyzer\AnalysisResult;
use TwigMetrics\Calculator\ComplexityCalculator;

#[CoversClass(ComplexityCalculator::class)]
final class ComplexityCalculatorTest extends TestCase
{
    public function testLogicRatioDecisionDensityMaintainability(): void
    {
        $calc = new ComplexityCalculator();
        $res = $this->makeResult([
            'conditions' => 3,
            'loops' => 2,
            'blank_lines' => 1,
            'comment_lines' => 1,
            'lines' => 10,
            'complexity_score' => 7,
            'total_line_length' => 100,
        ], 'x.twig');

        $this->assertGreaterThan(0.0, $calc->calculateLogicRatio($res));
        $this->assertEquals(0.7, $calc->calculateDecisionDensity($res));
        $this->assertGreaterThan(0.0, $calc->calculateMaintainabilityIndex($res));
    }

    private function makeResult(array $metrics, string $rel): AnalysisResult
    {
        $file = new SplFileInfo(__FILE__, '', $rel);

        return new AnalysisResult($file, $metrics, 0.0);
    }
}
