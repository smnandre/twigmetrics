<?php

declare(strict_types=1);

namespace TwigMetrics\Tests\Reporter\Helper;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use TwigMetrics\Metric\StatisticalSummary;
use TwigMetrics\Reporter\Helper\DimensionGrader;

#[CoversClass(DimensionGrader::class)]
final class DimensionGraderTest extends TestCase
{
    public function testGradeTemplateFilesABCD(): void
    {
        $grader = new DimensionGrader();

        $summaryA = $this->stats(cv: 0.4, gini: 0.29);
        [$scoreA, $gradeA] = $grader->gradeTemplateFiles($summaryA, dirDominance: 0.39, maxLines: 120);
        $this->assertSame('A', $gradeA);
        $this->assertGreaterThan(90.0, $scoreA);

        $summaryB = $this->stats(cv: 0.6, gini: 0.40);
        [$scoreB, $gradeB] = $grader->gradeTemplateFiles($summaryB, dirDominance: 0.49, maxLines: 180);
        $this->assertSame('B', $gradeB);

        $summaryC = $this->stats(cv: 0.9, gini: 0.55);
        [$scoreC, $gradeC] = $grader->gradeTemplateFiles($summaryC, dirDominance: 0.59, maxLines: 250);
        $this->assertSame('C', $gradeC);

        $summaryD = $this->stats(cv: 1.1, gini: 0.7);
        [$scoreD, $gradeD] = $grader->gradeTemplateFiles($summaryD, dirDominance: 0.7, maxLines: 400);
        $this->assertSame('D', $gradeD);
        $this->assertLessThanOrEqual(60.0, $scoreD);
    }

    public function testGradeComplexityABCD(): void
    {
        $grader = new DimensionGrader();

        [$scoreA, $gradeA] = $grader->gradeComplexity(avg: 7.9, max: 19, criticalRatio: 0.0, logicRatio: 0.14);
        $this->assertSame('A', $gradeA);
        $this->assertGreaterThan(90.0, $scoreA);

        [$scoreB, $gradeB] = $grader->gradeComplexity(avg: 11.5, max: 25, criticalRatio: 0.04, logicRatio: 0.24);
        $this->assertSame('B', $gradeB);

        [$scoreC, $gradeC] = $grader->gradeComplexity(avg: 17.5, max: 35, criticalRatio: 0.08, logicRatio: 0.34);
        $this->assertSame('C', $gradeC);

        [$scoreD, $gradeD] = $grader->gradeComplexity(avg: 20.0, max: 50, criticalRatio: 0.2, logicRatio: 0.5);
        $this->assertSame('D', $gradeD);
        $this->assertLessThanOrEqual(60.0, $scoreD);
    }

    private function stats(float $cv, float $gini): StatisticalSummary
    {
        return new StatisticalSummary(
            count: 10,
            sum: 100,
            mean: 10.0,
            median: 10.0,
            stdDev: $cv * 10.0,
            coefficientOfVariation: $cv,
            p25: 5.0,
            p75: 15.0,
            p95: 20.0,
            giniIndex: $gini,
            entropy: 1.0,
            min: 1.0,
            max: 20.0,
            range: 19.0,
        );
    }
}
