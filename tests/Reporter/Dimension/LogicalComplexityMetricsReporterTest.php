<?php

declare(strict_types=1);

namespace TwigMetrics\Tests\Reporter\Dimension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\SplFileInfo;
use TwigMetrics\Analyzer\AnalysisResult;
use TwigMetrics\Calculator\ComplexityCalculator;
use TwigMetrics\Calculator\StatisticalCalculator;
use TwigMetrics\Detector\ComplexityHotspotDetector;
use TwigMetrics\Reporter\Dimension\LogicalComplexityMetricsReporter;

#[CoversClass(LogicalComplexityMetricsReporter::class)]
final class LogicalComplexityMetricsReporterTest extends TestCase
{
    public function testGenerateMetrics(): void
    {
        $results = [
            $this->res(['complexity_score' => 4, 'lines' => 10, 'blank_lines' => 1, 'comment_lines' => 1, 'conditions' => 1, 'loops' => 1], 'a.twig'),
            $this->res(['complexity_score' => 12, 'lines' => 30, 'blank_lines' => 2, 'comment_lines' => 2, 'conditions' => 2, 'loops' => 2], 'b.twig'),
            $this->res(['complexity_score' => 28, 'lines' => 50, 'blank_lines' => 3, 'comment_lines' => 3, 'conditions' => 3, 'loops' => 3], 'c.twig'),
        ];

        $reporter = new LogicalComplexityMetricsReporter(
            new StatisticalCalculator(),
            new ComplexityCalculator(),
            new ComplexityHotspotDetector(),
        );

        $card = $reporter->generateMetrics($results);

        $this->assertSame('Logical Complexity', $card->name);
        $this->assertArrayHasKey('avg', $card->coreMetrics);
        $this->assertArrayHasKey('logic_ratio', $card->detailMetrics);
        $this->assertArrayHasKey('heatmap', $card->distributions);
        $this->assertIsArray($card->insights);
    }

    private function res(array $metrics, string $rel): AnalysisResult
    {
        $file = new SplFileInfo(__FILE__, '', $rel);

        return new AnalysisResult($file, $metrics, 0.0);
    }
}
