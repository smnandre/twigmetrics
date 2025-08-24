<?php

declare(strict_types=1);

namespace TwigMetrics\Tests\Reporter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\SplFileInfo;
use TwigMetrics\Analyzer\AnalysisResult;
use TwigMetrics\Report\Report;
use TwigMetrics\Reporter\QualityReporter;

#[CoversClass(QualityReporter::class)]
class QualityReporterTest extends TestCase
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
        $reporter = new QualityReporter();

        $results = [
            $this->createMockAnalysisResult([
                'lines' => 50,
                'complexity_score' => 5,
                'max_depth' => 2,
                'unique_functions' => 3,
                'unique_variables' => 5,
                'inheritance_depth' => 1,
            ]),
            $this->createMockAnalysisResult([
                'lines' => 120,
                'complexity_score' => 15,
                'max_depth' => 4,
                'unique_functions' => 10,
                'unique_variables' => 8,
                'inheritance_depth' => 2,
            ]),
        ];

        $report = $reporter->generate($results);

        $this->assertInstanceOf(Report::class, $report);
        $this->assertStringContainsString('TwigMetrics Comprehensive Analysis', $report->getTitle());
        $this->assertNotEmpty($report->getSections());
    }

    public function testGetWeight(): void
    {
        $reporter = new QualityReporter();

        $this->assertEquals(1.0, $reporter->getWeight());
    }

    public function testGetDimensionName(): void
    {
        $reporter = new QualityReporter();

        $this->assertEquals('Overall Health', $reporter->getDimensionName());
    }

    public function testEmptyResults(): void
    {
        $reporter = new QualityReporter();

        $report = $reporter->generate([]);

        $this->assertInstanceOf(Report::class, $report);
        $this->assertStringContainsString('TwigMetrics Comprehensive Analysis', $report->getTitle());
    }
}
