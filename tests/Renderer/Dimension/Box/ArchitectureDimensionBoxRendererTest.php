<?php

declare(strict_types=1);

namespace TwigMetrics\Tests\Renderer\Dimension\Box;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;
use TwigMetrics\Renderer\Dimension\Box\ArchitectureDimensionBoxRenderer;

#[CoversClass(ArchitectureDimensionBoxRenderer::class)]
final class ArchitectureDimensionBoxRendererTest extends TestCase
{
    public function testRenderOutputsSections(): void
    {
        $out = new BufferedOutput();
        $renderer = new ArchitectureDimensionBoxRenderer($out);
        $renderer->render([
            'summary' => [
                'extends_total' => 222,
                'includes_total' => 984,
                'embeds_total' => 35,
                'blocks_total' => 127,
                'extends_per_template' => 0.60,
                'includes_per_template' => 2.40,
                'embeds_per_template' => 0.04,
                'blocks_per_template' => 0.31,
            ],
            'inheritance' => [
                'max_depth' => 4,
                'avg_depth' => 2.1,
                'root_templates' => 8,
                'orphan_files' => 7,
            ],
            'blocks' => [
                'definitions' => 127,
                'calls' => 89,
                'overrides' => 45,
                'unused' => 12,
            ],
            'macros' => [
                'definitions' => 23,
                'calls' => 67,
                'external_calls' => 34,
                'unused' => 5,
            ],
            'directories' => [
                ['path' => 'components', 'extends_ratio' => 0.1, 'includes_ratio' => 0.9, 'embeds_ratio' => 0.0, 'blocks_ratio' => 0.8],
                ['path' => 'layouts', 'extends_ratio' => 0.0, 'includes_ratio' => 0.2, 'embeds_ratio' => 0.7, 'blocks_ratio' => 1.0],
            ],
            'top_referenced' => [
                ['template' => 'layouts/base.html.twig', 'count' => 41],
                ['template' => 'components/form/input.html.twig', 'count' => 23],
                ['template' => 'components/navigation/menu.html.twig', 'count' => 18],
            ],
            'top_blocks' => [
                ['name' => 'content', 'count' => 67],
                ['name' => 'sidebar', 'count' => 34],
                ['name' => 'meta', 'count' => 23],
                ['name' => 'title', 'count' => 45],
                ['name' => 'javascripts', 'count' => 28],
                ['name' => 'stylesheets', 'count' => 19],
            ],
            'inheritance_patterns' => [
                'roots' => ['layouts/base.html.twig'],
                'children' => ['layouts/base.html.twig' => 18],
            ],
            'final' => ['score' => 78.0, 'grade' => 'B'],
        ]);

        $content = $out->fetch();
        $this->assertStringContainsString('ARCHITECTURE', $content);
        $this->assertStringContainsString('Construct', $content);
        $this->assertStringContainsString('Heatmap by directory', $content);
        $this->assertStringContainsString('Most included templates', $content);
        $this->assertStringContainsString('Most used block names', $content);
        $this->assertStringContainsString('Analysis', $content);
    }

    public function testRenderHandlesEmptyData(): void
    {
        $out = new BufferedOutput();
        $renderer = new ArchitectureDimensionBoxRenderer($out);
        $renderer->render([
            'summary' => [
                'extends_total' => 0,
                'includes_total' => 0,
                'embeds_total' => 0,
                'blocks_total' => 0,
                'extends_per_template' => 0.0,
                'includes_per_template' => 0.0,
                'embeds_per_template' => 0.0,
                'blocks_per_template' => 0.0,
            ],
            'inheritance' => [],
            'blocks' => [],
            'macros' => [],
            'directories' => [],
            'top_referenced' => [],
            'top_blocks' => [],
            'inheritance_patterns' => [],
            'final' => ['score' => 0.0, 'grade' => 'E'],
        ]);

        $content = $out->fetch();
        $this->assertStringContainsString('ARCHITECTURE', $content);
        $this->assertStringContainsString('Analysis', $content);
        $this->assertStringContainsString('Grade: E', $content);
    }

    public function testRenderFormatsNumbers(): void
    {
        $out = new BufferedOutput();
        $renderer = new ArchitectureDimensionBoxRenderer($out);
        $renderer->render([
            'summary' => [
                'extends_total' => 1234,
                'includes_total' => 5678,
                'embeds_total' => 90,
                'blocks_total' => 456,
                'extends_per_template' => 1.23,
                'includes_per_template' => 4.56,
                'embeds_per_template' => 0.78,
                'blocks_per_template' => 2.34,
            ],
            'directories' => [],
            'top_referenced' => [],
            'top_blocks' => [],
            'inheritance_patterns' => [],
            'final' => ['score' => 85.5, 'grade' => 'B'],
        ]);

        $content = $out->fetch();
        $this->assertStringContainsString('1,234', $content);
        $this->assertStringContainsString('5,678', $content);
        $this->assertStringContainsString('1.23', $content);
        $this->assertStringContainsString('86/100', $content);
    }
}
