<?php

declare(strict_types=1);

namespace TwigMetrics\Tests\Reporter\Dimension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\SplFileInfo;
use TwigMetrics\Analyzer\AnalysisResult;
use TwigMetrics\Report\Report;
use TwigMetrics\Reporter\Dimension\TemplateFilesReporter;

#[CoversClass(TemplateFilesReporter::class)]
class TemplateFilesReporterTest extends TestCase
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
        $reporter = new TemplateFilesReporter();

        $results = [
            $this->createMockAnalysisResult([
                'lines' => 50,
                'characters' => 2500,
                'nodes' => 25,
                'blocks' => 3,
            ], 'components/button.twig'),
            $this->createMockAnalysisResult([
                'lines' => 120,
                'characters' => 6000,
                'nodes' => 60,
                'blocks' => 5,
            ], 'pages/home.twig'),
            $this->createMockAnalysisResult([
                'lines' => 30,
                'characters' => 1200,
                'nodes' => 15,
                'blocks' => 2,
            ], 'layouts/base.twig'),
        ];

        $report = $reporter->generate($results);

        $this->assertInstanceOf(Report::class, $report);
        $this->assertStringContainsString('Template Files', $report->getTitle());
        $this->assertNotEmpty($report->getSections());

        $sections = $report->getSections();
        $this->assertGreaterThan(1, count($sections));
    }

    public function testGetWeight(): void
    {
        $reporter = new TemplateFilesReporter();

        $this->assertIsFloat($reporter->getWeight());
        $this->assertGreaterThan(0, $reporter->getWeight());
    }

    public function testGetDimensionName(): void
    {
        $reporter = new TemplateFilesReporter();

        $this->assertEquals('Template Files', $reporter->getDimensionName());
    }

    public function testEmptyResults(): void
    {
        $reporter = new TemplateFilesReporter();

        $report = $reporter->generate([]);

        $this->assertInstanceOf(Report::class, $report);
        $this->assertStringContainsString('Template Files', $report->getTitle());
    }

    public function testSingleTemplate(): void
    {
        $reporter = new TemplateFilesReporter();

        $results = [
            $this->createMockAnalysisResult([
                'lines' => 50,
                'characters' => 2500,
                'nodes' => 25,
                'blocks' => 3,
            ], 'single.twig'),
        ];

        $report = $reporter->generate($results);

        $this->assertInstanceOf(Report::class, $report);
        $this->assertNotEmpty($report->getSections());
    }
}
