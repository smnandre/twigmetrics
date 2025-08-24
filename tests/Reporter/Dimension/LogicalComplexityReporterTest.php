<?php

declare(strict_types=1);

namespace TwigMetrics\Tests\Reporter\Dimension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\SplFileInfo;
use TwigMetrics\Analyzer\AnalysisResult;
use TwigMetrics\Report\Report;
use TwigMetrics\Reporter\Dimension\LogicalComplexityReporter;

#[CoversClass(LogicalComplexityReporter::class)]
class LogicalComplexityReporterTest extends TestCase
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
        $reporter = new LogicalComplexityReporter();

        $results = [
            $this->createMockAnalysisResult([
                'complexity_score' => 5,
                'max_depth' => 2,
                'if_statements' => 2,
                'for_statements' => 1,
                'ternary_operators' => 1,
                'logical_operators' => 3,
            ], 'simple.twig'),
            $this->createMockAnalysisResult([
                'complexity_score' => 15,
                'max_depth' => 4,
                'if_statements' => 5,
                'for_statements' => 3,
                'ternary_operators' => 2,
                'logical_operators' => 8,
            ], 'complex.twig'),
            $this->createMockAnalysisResult([
                'complexity_score' => 25,
                'max_depth' => 6,
                'if_statements' => 8,
                'for_statements' => 4,
                'ternary_operators' => 3,
                'logical_operators' => 12,
            ], 'very_complex.twig'),
        ];

        $report = $reporter->generate($results);

        $this->assertInstanceOf(Report::class, $report);
        $this->assertStringContainsString('Logical Complexity', $report->getTitle());
        $this->assertNotEmpty($report->getSections());

        $sections = $report->getSections();
        $this->assertGreaterThan(1, count($sections));
    }

    public function testGetWeight(): void
    {
        $reporter = new LogicalComplexityReporter();

        $this->assertIsFloat($reporter->getWeight());
        $this->assertGreaterThan(0, $reporter->getWeight());
    }

    public function testGetDimensionName(): void
    {
        $reporter = new LogicalComplexityReporter();

        $this->assertEquals('Logical Complexity', $reporter->getDimensionName());
    }

    public function testEmptyResults(): void
    {
        $reporter = new LogicalComplexityReporter();

        $report = $reporter->generate([]);

        $this->assertInstanceOf(Report::class, $report);
        $this->assertStringContainsString('Logical Complexity', $report->getTitle());
    }

    public function testLowComplexityTemplates(): void
    {
        $reporter = new LogicalComplexityReporter();

        $results = [
            $this->createMockAnalysisResult([
                'complexity_score' => 1,
                'max_depth' => 1,
                'if_statements' => 0,
                'for_statements' => 0,
                'ternary_operators' => 0,
                'logical_operators' => 0,
            ], 'simple.twig'),
        ];

        $report = $reporter->generate($results);

        $this->assertInstanceOf(Report::class, $report);
        $this->assertNotEmpty($report->getSections());
    }
}
