<?php

declare(strict_types=1);

namespace TwigMetrics\Tests\Reporter\Dimension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\SplFileInfo;
use TwigMetrics\Analyzer\AnalysisResult;
use TwigMetrics\Report\Report;
use TwigMetrics\Reporter\Dimension\CodeStyleReporter;

#[CoversClass(CodeStyleReporter::class)]
class CodeStyleReporterTest extends TestCase
{
    private function createMockAnalysisResult(array $metrics, string $filename = 'test.twig'): AnalysisResult
    {
        $file = $this->createMock(SplFileInfo::class);
        $file->method('getRelativePathname')->willReturn($filename);
        $file->method('getRealPath')->willReturn('/path/to/'.$filename);

        return new AnalysisResult($file, $metrics, 0.1);
    }

    public function testGenerate(): void
    {
        $reporter = new CodeStyleReporter();

        $results = [
            $this->createMockAnalysisResult([
                'avg_line_length' => 65.5,
                'max_line_length' => 85,
                'blank_lines' => 5,
                'comment_lines' => 3,
                'trailing_spaces' => 0,
                'mixed_indentation_lines' => 0,
            ], 'clean.twig'),
            $this->createMockAnalysisResult([
                'avg_line_length' => 95.2,
                'max_line_length' => 150,
                'blank_lines' => 8,
                'comment_lines' => 1,
                'trailing_spaces' => 3,
                'mixed_indentation_lines' => 2,
            ], 'messy.twig'),
        ];

        $report = $reporter->generate($results);

        $this->assertInstanceOf(Report::class, $report);
        $this->assertStringContainsString('Code Style', $report->getTitle());
        $this->assertNotEmpty($report->getSections());
    }

    public function testGetWeight(): void
    {
        $reporter = new CodeStyleReporter();

        $this->assertIsFloat($reporter->getWeight());
        $this->assertGreaterThan(0, $reporter->getWeight());
    }

    public function testGetDimensionName(): void
    {
        $reporter = new CodeStyleReporter();

        $this->assertEquals('Code Style', $reporter->getDimensionName());
    }

    public function testEmptyResults(): void
    {
        $reporter = new CodeStyleReporter();

        $report = $reporter->generate([]);

        $this->assertInstanceOf(Report::class, $report);
        $this->assertStringContainsString('Code Style', $report->getTitle());
    }

    public function testGoodCodeStyle(): void
    {
        $reporter = new CodeStyleReporter();

        $results = [
            $this->createMockAnalysisResult([
                'avg_line_length' => 60.0,
                'max_line_length' => 80,
                'blank_lines' => 10,
                'comment_lines' => 5,
                'trailing_spaces' => 0,
                'mixed_indentation_lines' => 0,
            ], 'good_style.twig'),
        ];

        $report = $reporter->generate($results);

        $this->assertInstanceOf(Report::class, $report);
        $this->assertNotEmpty($report->getSections());
    }
}
