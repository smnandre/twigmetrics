<?php

declare(strict_types=1);

namespace TwigMetrics\Tests\Reporter\Dimension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\SplFileInfo;
use TwigMetrics\Analyzer\AnalysisResult;
use TwigMetrics\Report\Report;
use TwigMetrics\Reporter\Dimension\MaintainabilityReporter;

#[CoversClass(MaintainabilityReporter::class)]
class MaintainabilityReporterTest extends TestCase
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
        $reporter = new MaintainabilityReporter();

        $results = [
            $this->createMockAnalysisResult([
                'lines' => 85,
                'complexity_score' => 12,
                'max_depth' => 3,
                'unique_functions' => 8,
                'unique_variables' => 6,
                'comment_lines' => 5,
                'duplication_score' => 15,
                'maintainability_index' => 78,
            ], 'maintainable.twig'),
            $this->createMockAnalysisResult([
                'lines' => 250,
                'complexity_score' => 35,
                'max_depth' => 6,
                'unique_functions' => 25,
                'unique_variables' => 20,
                'comment_lines' => 2,
                'duplication_score' => 45,
                'maintainability_index' => 32,
            ], 'complex.twig'),
            $this->createMockAnalysisResult([
                'lines' => 45,
                'complexity_score' => 4,
                'max_depth' => 2,
                'unique_functions' => 3,
                'unique_variables' => 4,
                'comment_lines' => 8,
                'duplication_score' => 5,
                'maintainability_index' => 92,
            ], 'clean.twig'),
        ];

        $report = $reporter->generate($results);

        $this->assertInstanceOf(Report::class, $report);
        $this->assertStringContainsString('Maintainability', $report->getTitle());
        $this->assertNotEmpty($report->getSections());
    }

    public function testGetWeight(): void
    {
        $reporter = new MaintainabilityReporter();

        $this->assertIsFloat($reporter->getWeight());
        $this->assertGreaterThan(0, $reporter->getWeight());
    }

    public function testGetDimensionName(): void
    {
        $reporter = new MaintainabilityReporter();

        $this->assertEquals('Maintainability', $reporter->getDimensionName());
    }

    public function testEmptyResults(): void
    {
        $reporter = new MaintainabilityReporter();

        $report = $reporter->generate([]);

        $this->assertInstanceOf(Report::class, $report);
        $this->assertStringContainsString('Maintainability', $report->getTitle());
    }

    public function testHighMaintainability(): void
    {
        $reporter = new MaintainabilityReporter();

        $results = [
            $this->createMockAnalysisResult([
                'lines' => 30,
                'complexity_score' => 2,
                'max_depth' => 1,
                'unique_functions' => 1,
                'unique_variables' => 2,
                'comment_lines' => 10,
                'duplication_score' => 0,
                'maintainability_index' => 95,
            ], 'excellent.twig'),
        ];

        $report = $reporter->generate($results);

        $this->assertInstanceOf(Report::class, $report);
        $this->assertNotEmpty($report->getSections());
    }

    public function testLowMaintainability(): void
    {
        $reporter = new MaintainabilityReporter();

        $results = [
            $this->createMockAnalysisResult([
                'lines' => 400,
                'complexity_score' => 50,
                'max_depth' => 8,
                'unique_functions' => 40,
                'unique_variables' => 30,
                'comment_lines' => 0,
                'duplication_score' => 80,
                'maintainability_index' => 15,
            ], 'legacy.twig'),
        ];

        $report = $reporter->generate($results);

        $this->assertInstanceOf(Report::class, $report);
        $this->assertNotEmpty($report->getSections());
    }
}
