<?php

declare(strict_types=1);

namespace TwigMetrics\Tests\Reporter\Dimension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\SplFileInfo;
use TwigMetrics\Analyzer\AnalysisResult;
use TwigMetrics\Report\Report;
use TwigMetrics\Reporter\Dimension\ArchitectureReporter;

#[CoversClass(ArchitectureReporter::class)]
class ArchitectureReporterTest extends TestCase
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
        $reporter = new ArchitectureReporter();

        $results = [
            $this->createMockAnalysisResult([
                'extends' => ['layouts/base.twig'],
                'includes' => ['components/header.twig', 'components/footer.twig'],
                'embeds' => [],
                'imports' => ['macros/forms.twig'],
                'inheritance_depth' => 2,
                'reusability_score' => 85,
            ], 'pages/home.twig'),
            $this->createMockAnalysisResult([
                'extends' => [],
                'includes' => [],
                'embeds' => [],
                'imports' => [],
                'inheritance_depth' => 0,
                'reusability_score' => 95,
            ], 'components/button.twig'),
            $this->createMockAnalysisResult([
                'extends' => ['layouts/base.twig'],
                'includes' => ['components/nav.twig'],
                'embeds' => ['forms/contact.twig'],
                'imports' => ['macros/forms.twig', 'macros/ui.twig'],
                'inheritance_depth' => 3,
                'reusability_score' => 70,
            ], 'pages/contact.twig'),
        ];

        $report = $reporter->generate($results);

        $this->assertInstanceOf(Report::class, $report);
        $this->assertStringContainsString('Architecture', $report->getTitle());
        $this->assertNotEmpty($report->getSections());
    }

    public function testGetWeight(): void
    {
        $reporter = new ArchitectureReporter();

        $this->assertIsFloat($reporter->getWeight());
        $this->assertGreaterThan(0, $reporter->getWeight());
    }

    public function testGetDimensionName(): void
    {
        $reporter = new ArchitectureReporter();

        $this->assertEquals('Architecture', $reporter->getDimensionName());
    }

    public function testEmptyResults(): void
    {
        $reporter = new ArchitectureReporter();

        $report = $reporter->generate([]);

        $this->assertInstanceOf(Report::class, $report);
        $this->assertStringContainsString('Architecture', $report->getTitle());
    }

    public function testSimpleArchitecture(): void
    {
        $reporter = new ArchitectureReporter();

        $results = [
            $this->createMockAnalysisResult([
                'extends' => [],
                'includes' => [],
                'embeds' => [],
                'imports' => [],
                'inheritance_depth' => 0,
                'reusability_score' => 100,
            ], 'simple.twig'),
        ];

        $report = $reporter->generate($results);

        $this->assertInstanceOf(Report::class, $report);
        $this->assertNotEmpty($report->getSections());
    }

    public function testComplexArchitecture(): void
    {
        $reporter = new ArchitectureReporter();

        $results = [
            $this->createMockAnalysisResult([
                'extends' => ['layouts/complex.twig'],
                'includes' => ['comp1.twig', 'comp2.twig', 'comp3.twig'],
                'embeds' => ['form1.twig', 'form2.twig'],
                'imports' => ['macro1.twig', 'macro2.twig', 'macro3.twig'],
                'inheritance_depth' => 5,
                'reusability_score' => 40,
            ], 'complex.twig'),
        ];

        $report = $reporter->generate($results);

        $this->assertInstanceOf(Report::class, $report);
        $this->assertNotEmpty($report->getSections());
    }
}
