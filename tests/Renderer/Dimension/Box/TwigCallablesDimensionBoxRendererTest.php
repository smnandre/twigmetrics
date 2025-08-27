<?php

declare(strict_types=1);

namespace TwigMetrics\Tests\Renderer\Dimension\Box;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;
use TwigMetrics\Renderer\Dimension\Box\TwigCallablesDimensionBoxRenderer;

#[CoversClass(TwigCallablesDimensionBoxRenderer::class)]
final class TwigCallablesDimensionBoxRendererTest extends TestCase
{
    public function testRenderOutputsSections(): void
    {
        $out = new BufferedOutput();
        $renderer = new TwigCallablesDimensionBoxRenderer($out);
        $renderer->render([
            'summary' => [
                'total_templates' => 156,
                'unique_functions' => 47,
                'unique_filters' => 23,
                'unique_variables' => 89,
                'unique_tests' => 12,
                'functions_per_template' => 3.2,
                'filters_per_template' => 2.1,
                'variables_per_template' => 8.5,
                'macros_defined' => 15,
            ],
            'diversity' => [
                'function_diversity' => 0.78,
                'filter_diversity' => 0.65,
                'variable_diversity' => 0.82,
                'complexity_index' => 2.3,
            ],
            'security' => [
                'risky_functions' => 5,
                'unsafe_filters' => 2,
                'security_score' => 85,
            ],
            'top_functions' => [
                ['name' => 'dump', 'count' => 127, 'security' => 'risky'],
                ['name' => 'date', 'count' => 89, 'security' => 'safe'],
                ['name' => 'url', 'count' => 67, 'security' => 'safe'],
            ],
            'top_filters' => [
                ['name' => 'upper', 'count' => 156, 'security' => 'safe'],
                ['name' => 'date', 'count' => 134, 'security' => 'safe'],
            ],
            'top_variables' => [
                ['name' => 'app', 'count' => 289],
                ['name' => 'user', 'count' => 178],
            ],
            'top_tests' => [
                ['name' => 'empty', 'count' => 89],
                ['name' => 'defined', 'count' => 67],
            ],
            'top_macros' => [
                ['name' => 'helper', 'count' => 23],
                ['name' => 'util', 'count' => 15],
            ],
            'top_blocks' => [
                ['name' => 'content', 'count' => 156],
                ['name' => 'title', 'count' => 134],
            ],
            'final' => ['score' => 78.0, 'grade' => 'B'],
        ]);

        $content = $out->fetch();
        $this->assertStringContainsString('TWIG CALLABLES', $content);
        $this->assertStringContainsString('Top 7 Functions', $content);
        $this->assertStringContainsString('Top 7 Filters', $content);
        $this->assertStringContainsString('Usage distribution', $content);
        $this->assertStringContainsString('Analysis', $content);
    }

    public function testRenderHandlesEmptyData(): void
    {
        $out = new BufferedOutput();
        $renderer = new TwigCallablesDimensionBoxRenderer($out);
        $renderer->render([
            'summary' => [
                'total_templates' => 0,
                'unique_functions' => 0,
                'unique_filters' => 0,
                'functions_per_template' => 0.0,
            ],
            'top_functions' => [],
            'top_filters' => [],
            'top_variables' => [],
            'top_tests' => [],
            'top_macros' => [],
            'top_blocks' => [],
            'final' => ['score' => 0.0, 'grade' => 'E'],
        ]);

        $content = $out->fetch();
        $this->assertStringContainsString('TWIG CALLABLES', $content);
        $this->assertStringContainsString('Analysis', $content);
        $this->assertStringContainsString('Grade: E', $content);
    }

    public function testRenderFormatsNumbers(): void
    {
        $out = new BufferedOutput();
        $renderer = new TwigCallablesDimensionBoxRenderer($out);
        $renderer->render([
            'summary' => [
                'total_templates' => 1234,
                'unique_functions' => 567,
                'functions_per_template' => 3.45,
                'variables_per_template' => 8.76,
            ],
            'top_functions' => [
                ['name' => 'dump', 'count' => 9876, 'security' => 'safe'],
            ],
            'top_filters' => [],
            'top_variables' => [],
            'top_tests' => [],
            'top_macros' => [],
            'top_blocks' => [],
            'final' => ['score' => 85.5, 'grade' => 'B'],
        ]);

        $content = $out->fetch();
        $this->assertStringContainsString('TWIG CALLABLES', $content);
        $this->assertStringContainsString('dump()', $content);
        $this->assertStringContainsString('86/100', $content);
    }

    public function testRenderShowsSecurityInfo(): void
    {
        $out = new BufferedOutput();
        $renderer = new TwigCallablesDimensionBoxRenderer($out);
        $renderer->render([
            'summary' => ['total_templates' => 1],
            'security' => [
                'risky_functions' => 3,
                'security_score' => 75,
            ],
            'top_functions' => [
                ['name' => 'dump', 'count' => 5, 'security' => 'risky'],
            ],
            'top_filters' => [],
            'top_variables' => [],
            'top_tests' => [],
            'top_macros' => [],
            'top_blocks' => [],
            'final' => ['score' => 75.0, 'grade' => 'C'],
        ]);

        $content = $out->fetch();
        $this->assertStringContainsString('TWIG CALLABLES', $content);
        $this->assertStringContainsString('dump()', $content);
        $this->assertStringContainsString('Grade: C', $content);
    }
}
