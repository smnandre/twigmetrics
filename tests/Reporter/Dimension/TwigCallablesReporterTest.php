<?php

declare(strict_types=1);

namespace TwigMetrics\Tests\Reporter\Dimension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\SplFileInfo;
use TwigMetrics\Analyzer\AnalysisResult;
use TwigMetrics\Report\Report;
use TwigMetrics\Reporter\Dimension\TwigCallablesReporter;

#[CoversClass(TwigCallablesReporter::class)]
class TwigCallablesReporterTest extends TestCase
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
        $reporter = new TwigCallablesReporter();

        $results = [
            $this->createMockAnalysisResult([
                'functions' => ['date' => 2, 'url' => 1, 'path' => 1],
                'filters' => ['upper' => 3, 'date' => 1, 'raw' => 2],
                'tests' => ['defined' => 2, 'empty' => 1],
                'variables' => ['user' => 5, 'title' => 3, 'content' => 2],
                'macros' => ['button' => 2, 'modal' => 1],
                'unique_functions' => 3,
                'unique_filters' => 3,
                'unique_tests' => 2,
                'unique_variables' => 3,
                'unique_macros' => 2,
            ], 'template1.twig'),
            $this->createMockAnalysisResult([
                'functions' => ['date' => 1, 'asset' => 2],
                'filters' => ['lower' => 1, 'trim' => 1],
                'tests' => ['defined' => 1],
                'variables' => ['user' => 2, 'data' => 4],
                'macros' => ['form_field' => 3],
                'unique_functions' => 2,
                'unique_filters' => 2,
                'unique_tests' => 1,
                'unique_variables' => 2,
                'unique_macros' => 1,
            ], 'template2.twig'),
        ];

        $report = $reporter->generate($results);

        $this->assertInstanceOf(Report::class, $report);
        $this->assertStringContainsString('Twig Callables', $report->getTitle());
        $this->assertNotEmpty($report->getSections());
    }

    public function testGetWeight(): void
    {
        $reporter = new TwigCallablesReporter();

        $this->assertIsFloat($reporter->getWeight());
        $this->assertGreaterThan(0, $reporter->getWeight());
    }

    public function testGetDimensionName(): void
    {
        $reporter = new TwigCallablesReporter();

        $this->assertEquals('Twig Callables', $reporter->getDimensionName());
    }

    public function testEmptyResults(): void
    {
        $reporter = new TwigCallablesReporter();

        $report = $reporter->generate([]);

        $this->assertInstanceOf(Report::class, $report);
        $this->assertStringContainsString('Twig Callables', $report->getTitle());
    }

    public function testMinimalCallables(): void
    {
        $reporter = new TwigCallablesReporter();

        $results = [
            $this->createMockAnalysisResult([
                'functions' => [],
                'filters' => ['raw' => 1],
                'tests' => [],
                'variables' => ['content' => 1],
                'macros' => [],
                'unique_functions' => 0,
                'unique_filters' => 1,
                'unique_tests' => 0,
                'unique_variables' => 1,
                'unique_macros' => 0,
            ], 'minimal.twig'),
        ];

        $report = $reporter->generate($results);

        $this->assertInstanceOf(Report::class, $report);
        $this->assertNotEmpty($report->getSections());
    }
}
