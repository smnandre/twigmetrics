<?php

declare(strict_types=1);

namespace TwigMetrics\Renderer\Dimension;

use Symfony\Component\Console\Output\OutputInterface;
use TwigMetrics\Analyzer\BlockUsageAnalyzer;
use TwigMetrics\Analyzer\CallableSecurityAnalyzer;
use TwigMetrics\Analyzer\CouplingAnalyzer;
use TwigMetrics\Analyzer\StyleConsistencyAnalyzer;
use TwigMetrics\Calculator\ComplexityCalculator;
use TwigMetrics\Calculator\DistributionCalculator;
use TwigMetrics\Calculator\DiversityCalculator;
use TwigMetrics\Calculator\StatisticalCalculator;
use TwigMetrics\Detector\ComplexityHotspotDetector;
use TwigMetrics\Metric\Aggregator\DirectoryMetricsAggregator;
use TwigMetrics\Renderer\Dimension\Box\AbstractDimensionBoxRenderer;
use TwigMetrics\Renderer\Dimension\Box\ArchitectureDimensionBoxRenderer;
use TwigMetrics\Renderer\Dimension\Box\ArchitectureDimensionPresenter;
use TwigMetrics\Renderer\Dimension\Box\CodeStyleDimensionBoxRenderer;
use TwigMetrics\Renderer\Dimension\Box\CodeStyleDimensionPresenter;
use TwigMetrics\Renderer\Dimension\Box\ComplexityDimensionBoxRenderer;
use TwigMetrics\Renderer\Dimension\Box\ComplexityDimensionPresenter;
use TwigMetrics\Renderer\Dimension\Box\DimensionPresenterInterface;
use TwigMetrics\Renderer\Dimension\Box\MaintainabilityDimensionBoxRenderer;
use TwigMetrics\Renderer\Dimension\Box\MaintainabilityDimensionPresenter;
use TwigMetrics\Renderer\Dimension\Box\TemplateFilesDimensionBoxRenderer;
use TwigMetrics\Renderer\Dimension\Box\TemplateFilesDimensionPresenter;
use TwigMetrics\Renderer\Dimension\Box\TwigCallablesDimensionBoxRenderer;
use TwigMetrics\Renderer\Dimension\Box\TwigCallablesDimensionPresenter;
use TwigMetrics\Reporter\Dimension\ArchitectureMetricsReporter;
use TwigMetrics\Reporter\Dimension\CodeStyleMetricsReporter;
use TwigMetrics\Reporter\Dimension\LogicalComplexityMetricsReporter;
use TwigMetrics\Reporter\Dimension\MaintainabilityMetricsReporter;
use TwigMetrics\Reporter\Dimension\TemplateFilesMetricsReporter;
use TwigMetrics\Reporter\Dimension\TwigCallablesMetricsReporter;
use TwigMetrics\Reporter\Helper\DimensionGrader;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
final class DimensionRendererFactory
{
    public function createRenderer(string $dimension, OutputInterface $output): AbstractDimensionBoxRenderer
    {
        return match ($dimension) {
            'template-files' => new TemplateFilesDimensionBoxRenderer($output),
            'complexity' => new ComplexityDimensionBoxRenderer($output),
            'code-style' => new CodeStyleDimensionBoxRenderer($output),
            'callables' => new TwigCallablesDimensionBoxRenderer($output),
            'architecture' => new ArchitectureDimensionBoxRenderer($output),
            'maintainability' => new MaintainabilityDimensionBoxRenderer($output),
            default => throw new \InvalidArgumentException("Unknown dimension: $dimension"),
        };
    }

    public function createPresenter(string $dimension): DimensionPresenterInterface
    {
        return match ($dimension) {
            'template-files' => $this->createTemplateFilesPresenter(),
            'complexity' => $this->createComplexityPresenter(),
            'code-style' => $this->createCodeStylePresenter(),
            'callables' => $this->createCallablesPresenter(),
            'architecture' => $this->createArchitecturePresenter(),
            'maintainability' => $this->createMaintainabilityPresenter(),
            default => throw new \InvalidArgumentException("Unknown dimension: $dimension"),
        };
    }

    private function createTemplateFilesPresenter(): TemplateFilesDimensionPresenter
    {
        return new TemplateFilesDimensionPresenter(
            new TemplateFilesMetricsReporter(new StatisticalCalculator(), new DistributionCalculator()),
            new DirectoryMetricsAggregator(),
            new StatisticalCalculator()
        );
    }

    private function createComplexityPresenter(): ComplexityDimensionPresenter
    {
        return new ComplexityDimensionPresenter(
            new LogicalComplexityMetricsReporter(
                new StatisticalCalculator(),
                new ComplexityCalculator(),
                new ComplexityHotspotDetector(),
                new DimensionGrader(),
            ),
            new DirectoryMetricsAggregator(),
            new ComplexityHotspotDetector(),
            new StatisticalCalculator()
        );
    }

    private function createCodeStylePresenter(): CodeStyleDimensionPresenter
    {
        return new CodeStyleDimensionPresenter(
            new CodeStyleMetricsReporter(new StatisticalCalculator(), new StyleConsistencyAnalyzer()),
            new DirectoryMetricsAggregator(),
        );
    }

    private function createCallablesPresenter(): TwigCallablesDimensionPresenter
    {
        return new TwigCallablesDimensionPresenter(
            new TwigCallablesMetricsReporter(new DiversityCalculator(), new CallableSecurityAnalyzer()),
            new CallableSecurityAnalyzer()
        );
    }

    private function createArchitecturePresenter(): ArchitectureDimensionPresenter
    {
        return new ArchitectureDimensionPresenter(
            new ArchitectureMetricsReporter(
                new CouplingAnalyzer(),
                new BlockUsageAnalyzer(),
                new DimensionGrader(),
            ),
            new DirectoryMetricsAggregator(),
            new StatisticalCalculator()
        );
    }

    private function createMaintainabilityPresenter(): MaintainabilityDimensionPresenter
    {
        return new MaintainabilityDimensionPresenter(
            new MaintainabilityMetricsReporter(
                new ComplexityCalculator(),
                new DimensionGrader(),
            ),
            new DirectoryMetricsAggregator(),
        );
    }
}
