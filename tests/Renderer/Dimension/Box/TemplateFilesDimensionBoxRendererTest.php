<?php

declare(strict_types=1);

namespace TwigMetrics\Tests\Renderer\Dimension\Box;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;
use TwigMetrics\Renderer\Dimension\Box\TemplateFilesDimensionBoxRenderer;

#[CoversClass(TemplateFilesDimensionBoxRenderer::class)]
final class TemplateFilesDimensionBoxRendererTest extends TestCase
{
    public function testRenderOutputsSections(): void
    {
        $out = new BufferedOutput();
        $renderer = new TemplateFilesDimensionBoxRenderer($out);
        $renderer->render([
            'summary' => [
                'total_templates' => 156,
                'total_lines' => 19642,
                'avg_lines' => 125.9,
                'median_lines' => 87,
                'cv' => 0.78,
                'gini' => 0.42,
                'empty_ratio' => 0.143,
                'comment_density' => 0.082,
            ],
            'distribution' => ['0_50' => 27, '51_100' => 35, '101_200' => 23, '201_plus' => 15],
            'stats' => ['std_dev' => 98.3, 'p95' => 287, 'files_over_500' => 12, 'orphans' => 7, 'entropy' => 2.34, 'dir_depth_avg' => 2.3],
            'directories' => [
                ['path' => 'components', 'count' => 67, 'avg_lines' => 428, 'bar_ratio' => 1.0],
            ],
            'top' => [
                ['path' => 'pages/admin/dashboard.html.twig', 'lines' => 1247, 'grade' => 'E'],
            ],
            'final' => ['score' => 82.0, 'grade' => 'B'],
        ]);

        $content = $out->fetch();
        $this->assertStringContainsString('TEMPLATE FILES', $content);
        $this->assertStringContainsString('Size distribution', $content);
        $this->assertStringContainsString('Files by directory', $content);
        $this->assertStringContainsString('Largest templates', $content);
        $this->assertStringContainsString('Analysis', $content);
    }
}
