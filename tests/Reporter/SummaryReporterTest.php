<?php

declare(strict_types=1);

namespace TwigMetrics\Tests\Reporter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\SplFileInfo;
use TwigMetrics\Analyzer\AnalysisResult;
use TwigMetrics\Report\Report;
use TwigMetrics\Reporter\SummaryReporter;

#[CoversClass(SummaryReporter::class)]
class SummaryReporterTest extends TestCase
{
    private function createMockAnalysisResult(array $metrics, string $filename = 'test.twig'): AnalysisResult
    {
        $file = $this->createMock(SplFileInfo::class);
        $file->method('getRelativePathname')->willReturn($filename);
        $file->method('getRealPath')->willReturn('/path/to/'.$filename);

        return new AnalysisResult($file, $metrics, 0.1);
    }

    public function testDefaultMode(): void
    {
        $reporter = new SummaryReporter(false);

        $results = [
            $this->createMockAnalysisResult(['lines' => 50, 'complexity_score' => 5]),
            $this->createMockAnalysisResult(['lines' => 30, 'complexity_score' => 3]),
        ];

        $report = $reporter->generate($results);

        $this->assertInstanceOf(Report::class, $report);
        $this->assertStringContainsString('TwigMetrics Comprehensive Analysis', $report->getTitle());
    }

    public function testGetWeight(): void
    {
        $reporter = new SummaryReporter(false);

        $this->assertEquals(0.0, $reporter->getWeight());
    }

    public function testEmptyResults(): void
    {
        $reporter = new SummaryReporter(false);

        $report = $reporter->generate([]);

        $this->assertInstanceOf(Report::class, $report);
        $this->assertNotEmpty($report->getTitle());
    }
}
