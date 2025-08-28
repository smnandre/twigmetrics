<?php

declare(strict_types=1);

namespace TwigMetrics\Tests\Renderer\Dimension\Box;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;
use TwigMetrics\Renderer\Dimension\Box\MaintainabilityDimensionBoxRenderer;

#[CoversClass(MaintainabilityDimensionBoxRenderer::class)]
final class MaintainabilityDimensionBoxRendererTest extends TestCase
{
    public function testRenderOutputsSections(): void
    {
        $out = new BufferedOutput();
        $renderer = new MaintainabilityDimensionBoxRenderer($out);
        $renderer->render([
            'summary' => [
                'total_templates' => 156,
                'mi_avg' => 68.5,
                'mi_median' => 72.0,
                'refactor_candidates' => 12,
                'high_risk' => 8,
                'medium_risk' => 24,
                'low_risk' => 124,
            ],
            'risk_distribution' => [
                'critical' => 3,
                'high' => 8,
                'medium' => 24,
                'low' => 121,
            ],
            'directories' => [
                ['path' => 'pages', 'files' => 45, 'avg_complexity' => 18.5, 'avg_lines' => 180, 'max_depth' => 6, 'risk' => 0.75],
                ['path' => 'components', 'files' => 78, 'avg_complexity' => 12.3, 'avg_lines' => 95, 'max_depth' => 4, 'risk' => 0.45],
            ],
            'refactor_priorities' => [
                ['template' => 'pages/admin/dashboard.twig', 'risk' => 0.92, 'complexity' => 35, 'lines' => 420, 'depth' => 8],
                ['template' => 'pages/reports/complex.twig', 'risk' => 0.87, 'complexity' => 28, 'lines' => 380, 'depth' => 7],
            ],
            'debt_analysis' => [
                'debt_ratio' => 15.3,
                'complex_templates' => 8,
                'large_templates' => 12,
                'deep_templates' => 5,
                'total_lines' => 45620,
            ],
            'final' => ['score' => 68.0, 'grade' => 'C'],
        ]);

        $content = $out->fetch();
        $this->assertStringContainsString('MAINTAINABILITY', $content);
        $this->assertStringContainsString('Risk distribution', $content);
        $this->assertStringContainsString('Risk by directory', $content);
        $this->assertStringContainsString('Refactoring priorities', $content);
        $this->assertStringContainsString('Technical debt analysis', $content);
        $this->assertStringContainsString('Analysis', $content);
        $this->assertStringContainsString('Grade: C', $content);
    }

    public function testRenderHandlesEmptyData(): void
    {
        $out = new BufferedOutput();
        $renderer = new MaintainabilityDimensionBoxRenderer($out);
        $renderer->render([
            'summary' => [
                'total_templates' => 0,
                'mi_avg' => 0.0,
                'mi_median' => 0.0,
                'refactor_candidates' => 0,
                'high_risk' => 0,
                'medium_risk' => 0,
                'low_risk' => 0,
            ],
            'risk_distribution' => [
                'critical' => 0,
                'high' => 0,
                'medium' => 0,
                'low' => 0,
            ],
            'directories' => [],
            'refactor_priorities' => [],
            'debt_analysis' => [
                'debt_ratio' => 0.0,
                'complex_templates' => 0,
                'large_templates' => 0,
                'deep_templates' => 0,
                'total_lines' => 0,
            ],
            'final' => ['score' => 0.0, 'grade' => 'E'],
        ]);

        $content = $out->fetch();
        $this->assertStringContainsString('MAINTAINABILITY', $content);
        $this->assertStringContainsString('Analysis', $content);
        $this->assertStringContainsString('Grade: E', $content);
    }

    public function testRenderFormatsNumbers(): void
    {
        $out = new BufferedOutput();
        $renderer = new MaintainabilityDimensionBoxRenderer($out);
        $renderer->render([
            'summary' => [
                'total_templates' => 1234,
                'mi_avg' => 68.456,
                'mi_median' => 72.789,
                'refactor_candidates' => 56,
                'high_risk' => 89,
                'medium_risk' => 234,
                'low_risk' => 911,
            ],
            'risk_distribution' => [
                'critical' => 12,
                'high' => 89,
                'medium' => 234,
                'low' => 899,
            ],
            'directories' => [],
            'refactor_priorities' => [],
            'debt_analysis' => [
                'debt_ratio' => 25.678,
                'complex_templates' => 123,
                'large_templates' => 234,
                'deep_templates' => 89,
                'total_lines' => 456789,
            ],
            'final' => ['score' => 85.5, 'grade' => 'B'],
        ]);

        $content = $out->fetch();
        $this->assertStringContainsString('MAINTAINABILITY', $content);
        $this->assertStringContainsString('68.5', $content);
        $this->assertStringContainsString('72.8', $content);
        $this->assertStringContainsString('25.7%', $content);
        $this->assertStringContainsString('86/100', $content);
        $this->assertStringContainsString('Grade: B', $content);
    }

    public function testRenderShowsRiskPriorities(): void
    {
        $out = new BufferedOutput();
        $renderer = new MaintainabilityDimensionBoxRenderer($out);
        $renderer->render([
            'summary' => ['total_templates' => 1],
            'risk_distribution' => ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 1],
            'directories' => [],
            'refactor_priorities' => [
                ['template' => 'very/long/path/to/template/file.twig', 'risk' => 0.95, 'complexity' => 45, 'lines' => 500, 'depth' => 9],
            ],
            'debt_analysis' => [
                'debt_ratio' => 33.3,
                'complex_templates' => 1,
                'large_templates' => 1,
                'deep_templates' => 1,
                'total_lines' => 500,
            ],
            'final' => ['score' => 45.0, 'grade' => 'E'],
        ]);

        $content = $out->fetch();
        $this->assertStringContainsString('MAINTAINABILITY', $content);
        $this->assertStringContainsString('Refactoring priorities', $content);
        $this->assertStringContainsString('file.twig', $content);
        $this->assertStringContainsString('Risk: 0.95', $content);
        $this->assertStringContainsString('Grade: E', $content);
    }

    public function testRenderShowsDirectoryRisk(): void
    {
        $out = new BufferedOutput();
        $renderer = new MaintainabilityDimensionBoxRenderer($out);
        $renderer->render([
            'summary' => ['total_templates' => 1],
            'risk_distribution' => ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 1],
            'directories' => [
                ['path' => 'templates/admin', 'files' => 25, 'avg_complexity' => 22.5, 'avg_lines' => 185, 'max_depth' => 6, 'risk' => 0.82],
                ['path' => 'components', 'files' => 45, 'avg_complexity' => 8.2, 'avg_lines' => 65, 'max_depth' => 3, 'risk' => 0.25],
            ],
            'refactor_priorities' => [],
            'debt_analysis' => [
                'debt_ratio' => 12.5,
                'complex_templates' => 5,
                'large_templates' => 3,
                'deep_templates' => 2,
                'total_lines' => 8500,
            ],
            'final' => ['score' => 75.0, 'grade' => 'C'],
        ]);

        $content = $out->fetch();
        $this->assertStringContainsString('MAINTAINABILITY', $content);
        $this->assertStringContainsString('Risk by directory', $content);
        $this->assertStringContainsString('└─ admin/', $content);
        $this->assertStringContainsString('components/', $content);
        $this->assertStringContainsString('25', $content);
        $this->assertStringContainsString('22.5', $content);
        $this->assertStringContainsString('185', $content);
        $this->assertStringContainsString('Grade: C', $content);
    }
}
