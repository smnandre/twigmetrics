<?php

declare(strict_types=1);

namespace TwigMetrics\Tests\Reporter\Dimension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\SplFileInfo;
use TwigMetrics\Analyzer\AnalysisResult;
use TwigMetrics\Report\Report;
use TwigMetrics\Reporter\Dimension\TemplateRelationshipsReporter;

#[CoversClass(TemplateRelationshipsReporter::class)]
class TemplateRelationshipsReporterTest extends TestCase
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
        $reporter = new TemplateRelationshipsReporter();

        $results = [
            $this->createMockAnalysisResult([
                'extends' => ['layouts/base.twig'],
                'includes' => ['components/header.twig', 'components/footer.twig'],
                'embeds' => [],
                'imports' => ['macros/forms.twig'],
                'used_by' => [],
                'inheritance_depth' => 2,
                'dependency_count' => 4,
                'coupling_score' => 65,
            ], 'pages/home.twig'),
            $this->createMockAnalysisResult([
                'extends' => [],
                'includes' => [],
                'embeds' => [],
                'imports' => [],
                'used_by' => ['pages/home.twig', 'pages/about.twig', 'pages/contact.twig'],
                'inheritance_depth' => 0,
                'dependency_count' => 0,
                'coupling_score' => 10,
            ], 'components/header.twig'),
            $this->createMockAnalysisResult([
                'extends' => [],
                'includes' => [],
                'embeds' => [],
                'imports' => [],
                'used_by' => ['pages/home.twig', 'pages/about.twig'],
                'inheritance_depth' => 0,
                'dependency_count' => 0,
                'coupling_score' => 15,
            ], 'components/footer.twig'),
            $this->createMockAnalysisResult([
                'extends' => ['layouts/base.twig'],
                'includes' => ['components/header.twig'],
                'embeds' => ['forms/newsletter.twig'],
                'imports' => ['macros/forms.twig', 'macros/ui.twig'],
                'used_by' => [],
                'inheritance_depth' => 3,
                'dependency_count' => 5,
                'coupling_score' => 85,
            ], 'pages/contact.twig'),
        ];

        $report = $reporter->generate($results);

        $this->assertInstanceOf(Report::class, $report);
        $this->assertStringContainsString('Template Relationships', $report->getTitle());
        $this->assertNotEmpty($report->getSections());
    }

    public function testGetWeight(): void
    {
        $reporter = new TemplateRelationshipsReporter();

        $this->assertIsFloat($reporter->getWeight());
        $this->assertGreaterThan(0, $reporter->getWeight());
    }

    public function testGetDimensionName(): void
    {
        $reporter = new TemplateRelationshipsReporter();

        $this->assertEquals('Template Relationships', $reporter->getDimensionName());
    }

    public function testEmptyResults(): void
    {
        $reporter = new TemplateRelationshipsReporter();

        $report = $reporter->generate([]);

        $this->assertInstanceOf(Report::class, $report);
        $this->assertStringContainsString('Template Relationships', $report->getTitle());
    }

    public function testIsolatedTemplates(): void
    {
        $reporter = new TemplateRelationshipsReporter();

        $results = [
            $this->createMockAnalysisResult([
                'extends' => [],
                'includes' => [],
                'embeds' => [],
                'imports' => [],
                'used_by' => [],
                'inheritance_depth' => 0,
                'dependency_count' => 0,
                'coupling_score' => 0,
            ], 'isolated1.twig'),
            $this->createMockAnalysisResult([
                'extends' => [],
                'includes' => [],
                'embeds' => [],
                'imports' => [],
                'used_by' => [],
                'inheritance_depth' => 0,
                'dependency_count' => 0,
                'coupling_score' => 0,
            ], 'isolated2.twig'),
        ];

        $report = $reporter->generate($results);

        $this->assertInstanceOf(Report::class, $report);
        $this->assertNotEmpty($report->getSections());
    }

    public function testHighlyConnectedTemplates(): void
    {
        $reporter = new TemplateRelationshipsReporter();

        $results = [
            $this->createMockAnalysisResult([
                'extends' => ['layouts/complex.twig'],
                'includes' => ['comp1.twig', 'comp2.twig', 'comp3.twig', 'comp4.twig'],
                'embeds' => ['form1.twig', 'form2.twig'],
                'imports' => ['macro1.twig', 'macro2.twig', 'macro3.twig'],
                'used_by' => ['parent1.twig', 'parent2.twig'],
                'inheritance_depth' => 4,
                'dependency_count' => 10,
                'coupling_score' => 95,
            ], 'hub.twig'),
        ];

        $report = $reporter->generate($results);

        $this->assertInstanceOf(Report::class, $report);
        $this->assertNotEmpty($report->getSections());
    }
}
