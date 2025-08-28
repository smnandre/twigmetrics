<?php

declare(strict_types=1);

namespace TwigMetrics\Tests\Renderer\Dimension\Box;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\SplFileInfo;
use TwigMetrics\Analyzer\AnalysisResult;
use TwigMetrics\Calculator\ComplexityCalculator;
use TwigMetrics\Calculator\StatisticalCalculator;
use TwigMetrics\Detector\ComplexityHotspotDetector;
use TwigMetrics\Metric\Aggregator\DirectoryMetricsAggregator;
use TwigMetrics\Renderer\Dimension\Box\ComplexityDimensionPresenter;
use TwigMetrics\Reporter\Dimension\LogicalComplexityMetricsReporter;
use TwigMetrics\Reporter\Helper\DimensionGrader;

#[CoversClass(ComplexityDimensionPresenter::class)]
final class ComplexityDimensionPresenterTest extends TestCase
{
    public function testPresentProducesExpectedKeys(): void
    {
        $results = [
            $this->res(['complexity_score' => 4, 'lines' => 10, 'blank_lines' => 1, 'comment_lines' => 1, 'max_depth' => 1], 'a.twig'),
            $this->res(['complexity_score' => 12, 'lines' => 30, 'blank_lines' => 2, 'comment_lines' => 2, 'max_depth' => 2], 'b.twig'),
            $this->res(['complexity_score' => 28, 'lines' => 50, 'blank_lines' => 3, 'comment_lines' => 3, 'max_depth' => 3], 'c.twig'),
        ];

        $presenter = new ComplexityDimensionPresenter(
            new LogicalComplexityMetricsReporter(new StatisticalCalculator(), new ComplexityCalculator(), new ComplexityHotspotDetector(), new DimensionGrader()),
            new DirectoryMetricsAggregator(),
            new ComplexityHotspotDetector(),
            new StatisticalCalculator(),
        );
        $data = $presenter->present($results, 1, true);

        $this->assertArrayHasKey('summary', $data);
        $this->assertArrayHasKey('distribution', $data);
        $this->assertArrayHasKey('directories', $data);
        $this->assertArrayHasKey('top', $data);
        $this->assertArrayHasKey('final', $data);
    }

    private function res(array $metrics, string $rel): AnalysisResult
    {
        $file = new SplFileInfo(__FILE__, '', $rel);

        return new AnalysisResult($file, $metrics, 0.0);
    }
}
