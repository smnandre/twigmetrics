<?php

declare(strict_types=1);

namespace TwigMetrics\Tests\Reporter\Dimension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\SplFileInfo;
use TwigMetrics\Analyzer\AnalysisResult;
use TwigMetrics\Analyzer\StyleConsistencyAnalyzer;
use TwigMetrics\Calculator\StatisticalCalculator;
use TwigMetrics\Reporter\Dimension\CodeStyleMetricsReporter;

#[CoversClass(CodeStyleMetricsReporter::class)]
final class CodeStyleMetricsReporterTest extends TestCase
{
    public function testGenerateMetrics(): void
    {
        $results = [
            $this->res(['lines' => 10, 'avg_line_length' => 70, 'max_line_length' => 80, 'blank_lines' => 1, 'comment_lines' => 1, 'trailing_spaces' => 0, 'mixed_indentation_lines' => 0, 'comment_density' => 10.0], 'a.twig'),
            $this->res(['lines' => 12, 'avg_line_length' => 90, 'max_line_length' => 130, 'blank_lines' => 1, 'comment_lines' => 1, 'trailing_spaces' => 2, 'mixed_indentation_lines' => 1, 'comment_density' => 8.0], 'b.twig'),
            $this->res(['lines' => 8, 'avg_line_length' => 60, 'max_line_length' => 75, 'blank_lines' => 1, 'comment_lines' => 0, 'trailing_spaces' => 0, 'mixed_indentation_lines' => 0, 'comment_density' => 5.0], 'c.twig'),
        ];

        $reporter = new CodeStyleMetricsReporter(new StatisticalCalculator(), new StyleConsistencyAnalyzer());
        $card = $reporter->generateMetrics($results);

        $this->assertSame('Code Style', $card->name);
        $this->assertArrayHasKey('consistency', $card->coreMetrics);
        $this->assertArrayHasKey('p95_line_length', $card->coreMetrics);
        $this->assertArrayHasKey('line_length', $card->distributions);
        $this->assertIsArray($card->insights);
    }

    private function res(array $metrics, string $rel): AnalysisResult
    {
        $file = new SplFileInfo(__FILE__, '', $rel);

        return new AnalysisResult($file, $metrics, 0.0);
    }
}
