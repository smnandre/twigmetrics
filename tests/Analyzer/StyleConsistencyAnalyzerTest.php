<?php

declare(strict_types=1);

namespace TwigMetrics\Tests\Analyzer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\SplFileInfo;
use TwigMetrics\Analyzer\AnalysisResult;
use TwigMetrics\Analyzer\StyleConsistencyAnalyzer;

#[CoversClass(StyleConsistencyAnalyzer::class)]
final class StyleConsistencyAnalyzerTest extends TestCase
{
    public function testAnalyze(): void
    {
        $results = [
            $this->makeResult([
                'lines' => 10,
                'avg_line_length' => 90,
                'max_line_length' => 130,
                'blank_lines' => 2,
                'comment_lines' => 2,
                'trailing_spaces' => 1,
                'mixed_indentation_lines' => 1,
                'comment_density' => 20.0,
            ], 'a.twig'),
            $this->makeResult([
                'lines' => 8,
                'avg_line_length' => 70,
                'max_line_length' => 80,
                'blank_lines' => 1,
                'comment_lines' => 1,
                'trailing_spaces' => 0,
                'mixed_indentation_lines' => 0,
                'comment_density' => 10.0,
            ], 'b.twig'),
        ];

        $analyzer = new StyleConsistencyAnalyzer();
        $metrics = $analyzer->analyze($results);

        $this->assertNotEmpty($metrics->violations);
        $this->assertGreaterThan(0.0, $metrics->consistencyScore);
        $this->assertGreaterThanOrEqual(0.0, $metrics->formattingEntropy);
        $this->assertGreaterThan(0.0, $metrics->readabilityScore);
    }

    private function makeResult(array $metrics, string $rel): AnalysisResult
    {
        $file = new SplFileInfo(__FILE__, '', $rel);

        return new AnalysisResult($file, $metrics, 0.0);
    }
}
