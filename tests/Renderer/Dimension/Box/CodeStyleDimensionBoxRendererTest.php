<?php

declare(strict_types=1);

namespace TwigMetrics\Tests\Renderer\Dimension\Box;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;
use TwigMetrics\Renderer\Dimension\Box\CodeStyleDimensionBoxRenderer;

#[CoversClass(CodeStyleDimensionBoxRenderer::class)]
final class CodeStyleDimensionBoxRendererTest extends TestCase
{
    public function testRenderOutputsSections(): void
    {
        $out = new BufferedOutput();
        $renderer = new CodeStyleDimensionBoxRenderer($out);
        $renderer->render([
            'summary' => [
                'total_templates' => 156,
                'avg_line_length' => 72.3,
                'median_line_length' => 68.0,
                'max_line_length' => 247,
                'consistency_score' => 87.2,
                'trailing_spaces_total' => 156,
                'comment_density' => 0.082,
                'empty_ratio' => 0.143,
            ],
            'line_length' => [
                'p95_length' => 118,
                'style_violations' => 234,
                'mixed_indentation' => 8,
                'indentation_depth' => 2.8,
            ],
            'formatting' => [
                'readability_score' => 72,
                'format_entropy' => 3.21,
                'blank_line_consistency' => 89.4,
            ],
            'distribution' => [
                '0_80' => 68,
                '81_120' => 22,
                '121_160' => 8,
                '160_plus' => 2,
            ],
            'directories' => [
                ['path' => 'components', 'score' => 92.1, 'grade' => 'A'],
                ['path' => 'layouts', 'score' => 85.6, 'grade' => 'B'],
            ],
            'violations' => [
                ['type' => 'Long lines (>120 chars)', 'count' => 23],
                ['type' => 'Trailing whitespace', 'count' => 156],
                ['type' => 'Mixed indentation', 'count' => 8],
            ],
            'final' => ['score' => 82.0, 'grade' => 'B'],
        ]);

        $content = $out->fetch();
        $this->assertStringContainsString('CODE STYLE', $content);
        $this->assertStringContainsString('Length distribution', $content);
        $this->assertStringContainsString('Formatting Metrics', $content);
        $this->assertStringContainsString('Style by directory', $content);
        $this->assertStringContainsString('Violation breakdown', $content);
        $this->assertStringContainsString('Analysis', $content);
    }

    public function testRenderHandlesEmptyData(): void
    {
        $out = new BufferedOutput();
        $renderer = new CodeStyleDimensionBoxRenderer($out);
        $renderer->render([
            'summary' => [
                'total_templates' => 0,
                'avg_line_length' => 0.0,
                'max_line_length' => 0,
                'consistency_score' => 0.0,
            ],
            'directories' => [],
            'violations' => [],
            'final' => ['score' => 0.0, 'grade' => 'E'],
        ]);

        $content = $out->fetch();
        $this->assertStringContainsString('CODE STYLE', $content);
        $this->assertStringContainsString('Analysis', $content);
        $this->assertStringContainsString('Grade: E', $content);
    }

    public function testRenderFormatsNumbers(): void
    {
        $out = new BufferedOutput();
        $renderer = new CodeStyleDimensionBoxRenderer($out);
        $renderer->render([
            'summary' => [
                'avg_line_length' => 72.34,
                'max_line_length' => 5678,
                'consistency_score' => 87.56,
            ],
            'directories' => [],
            'violations' => [],
            'final' => ['score' => 85.5, 'grade' => 'B'],
        ]);

        $content = $out->fetch();
        $this->assertStringContainsString('CODE STYLE', $content);
        $this->assertStringContainsString('Grade: B', $content);
        $this->assertStringContainsString('86/100', $content);
    }
}
