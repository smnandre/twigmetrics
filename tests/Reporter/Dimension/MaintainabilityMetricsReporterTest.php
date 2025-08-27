<?php

declare(strict_types=1);

namespace TwigMetrics\Tests\Reporter\Dimension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\SplFileInfo;
use TwigMetrics\Analyzer\AnalysisResult;
use TwigMetrics\Calculator\ComplexityCalculator;
use TwigMetrics\Reporter\Dimension\MaintainabilityMetricsReporter;

#[CoversClass(MaintainabilityMetricsReporter::class)]
final class MaintainabilityMetricsReporterTest extends TestCase
{
    public function testGenerateMetrics(): void
    {
        $results = [
            $this->res(['complexity_score' => 10, 'lines' => 80, 'formatting_consistency_score' => 90.0], 'a.twig'),
            $this->res(['complexity_score' => 25, 'lines' => 200, 'formatting_consistency_score' => 70.0], 'b.twig'),
            $this->res(['complexity_score' => 5, 'lines' => 40, 'formatting_consistency_score' => 100.0], 'c.twig'),
        ];

        $reporter = new MaintainabilityMetricsReporter(new ComplexityCalculator());
        $card = $reporter->generateMetrics($results);

        $this->assertSame('Maintainability', $card->name);
        $this->assertArrayHasKey('mi_avg', $card->coreMetrics);
        $this->assertArrayHasKey('risk', $card->distributions);
        $this->assertIsArray($card->insights);
    }

    private function res(array $metrics, string $rel): AnalysisResult
    {
        $file = new SplFileInfo(__FILE__, '', $rel);

        return new AnalysisResult($file, $metrics, 0.0);
    }
}
