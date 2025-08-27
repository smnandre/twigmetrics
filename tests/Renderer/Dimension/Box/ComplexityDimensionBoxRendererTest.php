<?php

declare(strict_types=1);

namespace TwigMetrics\Tests\Renderer\Dimension\Box;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;
use TwigMetrics\Renderer\Dimension\Box\ComplexityDimensionBoxRenderer;

#[CoversClass(ComplexityDimensionBoxRenderer::class)]
final class ComplexityDimensionBoxRendererTest extends TestCase
{
    public function testRenderOutputsSections(): void
    {
        $out = new BufferedOutput();
        $renderer = new ComplexityDimensionBoxRenderer($out);
        $renderer->render([
            'summary' => [
                'avg' => 12.3, 'median' => 8.0, 'max' => 47, 'critical_files' => 3,
                'logic_ratio' => 0.234, 'decision_density' => 0.08,
                'avg_depth' => 2.8, 'max_depth' => 7,
            ],
            'distribution' => ['simple_pct' => 43, 'moderate_pct' => 27, 'complex_pct' => 20, 'critical_pct' => 10],
            'stats' => ['mi_avg' => 65.2, 'cyclomatic_per_loc' => 0.12, 'cognitive_complexity' => 'N/A', 'halstead_volume' => 'N/A', 'control_flow_nodes' => 'N/A', 'logical_operators' => 'N/A'],
            'directories' => [
                ['path' => 'components', 'avg_cx' => 4.2, 'max_cx' => 9, 'avg_depth' => 2.1, 'risk' => 'low'],
            ],
            'top' => [
                ['path' => 'templates/pages/admin/dashboard.html.twig', 'score' => 24.2, 'grade' => 'E'],
            ],
            'final' => ['score' => 65.0, 'grade' => 'C'],
        ]);

        $content = $out->fetch();
        $this->assertStringContainsString('LOGICAL COMPLEXITY', $content);
        $this->assertStringContainsString('Average Complexity', $content);
        $this->assertStringContainsString('Complexity distribution', $content);
        $this->assertStringContainsString('Top 5 templates', $content);
        $this->assertStringContainsString('Analysis', $content);
    }
}
