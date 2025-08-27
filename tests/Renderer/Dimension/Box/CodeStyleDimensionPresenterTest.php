<?php

declare(strict_types=1);

namespace TwigMetrics\Tests\Renderer\Dimension\Box;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\SplFileInfo;
use TwigMetrics\Analyzer\AnalysisResult;
use TwigMetrics\Analyzer\StyleConsistencyAnalyzer;
use TwigMetrics\Calculator\StatisticalCalculator;
use TwigMetrics\Metric\Aggregator\DirectoryMetricsAggregator;
use TwigMetrics\Renderer\Dimension\Box\CodeStyleDimensionPresenter;
use TwigMetrics\Reporter\Dimension\CodeStyleMetricsReporter;

#[CoversClass(CodeStyleDimensionPresenter::class)]
final class CodeStyleDimensionPresenterTest extends TestCase
{
    public function testPresentProducesExpectedKeys(): void
    {
        $results = [
            $this->res([
                'lines' => 40,
                'avg_line_length' => 72.3,
                'max_line_length' => 120,
                'trailing_spaces' => 5,
                'blank_lines' => 6,
                'comment_lines' => 3,
                'mixed_indentation_lines' => 1,
                'formatting_consistency_score' => 85.0,
            ], 'components/a.twig'),
            $this->res([
                'lines' => 60,
                'avg_line_length' => 68.1,
                'max_line_length' => 247,
                'trailing_spaces' => 8,
                'blank_lines' => 9,
                'comment_lines' => 5,
                'mixed_indentation_lines' => 0,
                'formatting_consistency_score' => 92.0,
            ], 'pages/b.twig'),
        ];

        $presenter = new CodeStyleDimensionPresenter(
            new CodeStyleMetricsReporter(new StatisticalCalculator(), new StyleConsistencyAnalyzer()),
            new DirectoryMetricsAggregator(),
        );
        $data = $presenter->present($results, 1, true);

        $this->assertArrayHasKey('summary', $data);
        $this->assertArrayHasKey('distribution', $data);
        $this->assertArrayHasKey('formatting', $data);
        $this->assertArrayHasKey('directories', $data);
        $this->assertArrayHasKey('violations', $data);
        $this->assertArrayHasKey('final', $data);

        $summary = $data['summary'];
        $this->assertArrayHasKey('avg_line_length', $summary);
        $this->assertArrayHasKey('max_line_length', $summary);
        $this->assertIsNumeric($summary['avg_line_length']);
        $this->assertIsNumeric($summary['max_line_length']);
    }

    public function testPresentWithEmptyResults(): void
    {
        $presenter = new CodeStyleDimensionPresenter(
            new CodeStyleMetricsReporter(new StatisticalCalculator(), new StyleConsistencyAnalyzer()),
            new DirectoryMetricsAggregator(),
        );

        $data = $presenter->present([], 1, true);

        $this->assertArrayHasKey('summary', $data);
        $this->assertIsArray($data['directories']);
        $this->assertEmpty($data['directories']);
    }

    public function testPresentCalculatesMetrics(): void
    {
        $results = [
            $this->res([
                'lines' => 50,
                'avg_line_length' => 75.0,
                'trailing_spaces' => 10,
                'comment_lines' => 5,
                'blank_lines' => 8,
                'mixed_indentation_lines' => 2,
            ], 'template.twig'),
        ];

        $presenter = new CodeStyleDimensionPresenter(
            new CodeStyleMetricsReporter(new StatisticalCalculator(), new StyleConsistencyAnalyzer()),
            new DirectoryMetricsAggregator(),
        );

        $data = $presenter->present($results, 1, true);

        $summary = $data['summary'];
        $this->assertArrayHasKey('avg_line_length', $summary);
        $this->assertIsNumeric($summary['avg_line_length']);
    }

    private function res(array $metrics, string $rel): AnalysisResult
    {
        $file = new SplFileInfo(__FILE__, '', $rel);

        return new AnalysisResult($file, $metrics, 0.0);
    }
}
