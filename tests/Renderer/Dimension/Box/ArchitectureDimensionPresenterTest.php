<?php

declare(strict_types=1);

namespace TwigMetrics\Tests\Renderer\Dimension\Box;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\SplFileInfo;
use TwigMetrics\Analyzer\AnalysisResult;
use TwigMetrics\Analyzer\BlockUsageAnalyzer;
use TwigMetrics\Analyzer\CouplingAnalyzer;
use TwigMetrics\Calculator\StatisticalCalculator;
use TwigMetrics\Metric\Aggregator\DirectoryMetricsAggregator;
use TwigMetrics\Renderer\Dimension\Box\ArchitectureDimensionPresenter;
use TwigMetrics\Reporter\Dimension\ArchitectureMetricsReporter;
use TwigMetrics\Reporter\Helper\DimensionGrader;

#[CoversClass(ArchitectureDimensionPresenter::class)]
final class ArchitectureDimensionPresenterTest extends TestCase
{
    public function testPresentProducesExpectedKeys(): void
    {
        $results = [
            $this->res([
                'dependency_types' => ['extends' => 1, 'includes' => 2, 'embeds' => 0],
                'blocks_detail' => ['content' => 1, 'title' => 1],
                'inheritance_depth' => 2,
                'dependencies' => [['template' => 'layouts/base.html.twig', 'type' => 'extends']],
            ], 'components/a.twig'),
            $this->res([
                'dependency_types' => ['extends' => 0, 'includes' => 3, 'embeds' => 1],
                'blocks_detail' => ['sidebar' => 1],
                'inheritance_depth' => 0,
                'dependencies' => [['template' => 'partials/menu.html.twig', 'type' => 'includes']],
            ], 'pages/b.twig'),
            $this->res([
                'dependency_types' => ['extends' => 0, 'includes' => 0, 'embeds' => 0],
                'blocks_detail' => ['content' => 1, 'meta' => 1, 'scripts' => 1],
                'inheritance_depth' => 0,
                'dependencies' => [],
            ], 'layouts/base.html.twig'),
        ];

        $presenter = new ArchitectureDimensionPresenter(
            new ArchitectureMetricsReporter(
                new CouplingAnalyzer(),
                new BlockUsageAnalyzer(),
                new DimensionGrader(),
            ),
            new DirectoryMetricsAggregator(),
            new StatisticalCalculator(),
        );
        $data = $presenter->present($results, 1, true);

        $this->assertArrayHasKey('summary', $data);
        $this->assertArrayHasKey('inheritance', $data);
        $this->assertArrayHasKey('blocks', $data);
        $this->assertArrayHasKey('macros', $data);
        $this->assertArrayHasKey('directories', $data);
        $this->assertArrayHasKey('top_referenced', $data);
        $this->assertArrayHasKey('top_blocks', $data);
        $this->assertArrayHasKey('final', $data);

        $summary = $data['summary'];
        $this->assertArrayHasKey('total_templates', $summary);
        $this->assertArrayHasKey('extends_total', $summary);
        $this->assertArrayHasKey('includes_total', $summary);
        $this->assertArrayHasKey('extends_per_template', $summary);

        $this->assertEquals(3, $summary['total_templates']);

        $this->assertEquals(1, $summary['extends_total']);

        $this->assertEquals(5, $summary['includes_total']);
    }

    public function testPresentWithEmptyResults(): void
    {
        $presenter = new ArchitectureDimensionPresenter(
            new ArchitectureMetricsReporter(
                new CouplingAnalyzer(),
                new BlockUsageAnalyzer(),
                new DimensionGrader(),
            ),
            new DirectoryMetricsAggregator(),
            new StatisticalCalculator(),
        );

        $data = $presenter->present([], 1, true);

        $this->assertArrayHasKey('summary', $data);
        $this->assertEquals(0, $data['summary']['total_templates']);
        $this->assertEquals(0, $data['summary']['extends_total']);
        $this->assertIsArray($data['directories']);
        $this->assertEmpty($data['directories']);
    }

    public function testPresentCalculatesBlockUsage(): void
    {
        $results = [
            $this->res([
                'dependency_types' => [],
                'blocks_detail' => ['content' => 2, 'title' => 1, 'sidebar' => 1],
                'block_calls' => 3,
                'block_overrides' => 1,
            ], 'template.twig'),
        ];

        $presenter = new ArchitectureDimensionPresenter(
            new ArchitectureMetricsReporter(
                new CouplingAnalyzer(),
                new BlockUsageAnalyzer(),
                new DimensionGrader(),
            ),
            new DirectoryMetricsAggregator(),
            new StatisticalCalculator(),
        );

        $data = $presenter->present($results, 1, true);

        $blocks = $data['blocks'];
        $this->assertEquals(3, $blocks['definitions']);
        $this->assertEquals(3, $blocks['calls']);
        $this->assertEquals(1, $blocks['overrides']);
        $this->assertEquals(0, $blocks['unused']);
    }

    private function res(array $metrics, string $rel): AnalysisResult
    {
        $file = new SplFileInfo(__FILE__, '', $rel);

        return new AnalysisResult($file, $metrics, 0.0);
    }
}
