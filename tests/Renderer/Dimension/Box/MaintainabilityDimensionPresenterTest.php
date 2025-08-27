<?php

declare(strict_types=1);

namespace TwigMetrics\Tests\Renderer\Dimension\Box;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\SplFileInfo;
use TwigMetrics\Analyzer\AnalysisResult;
use TwigMetrics\Calculator\StatisticalCalculator;
use TwigMetrics\Metric\Aggregator\DirectoryMetricsAggregator;
use TwigMetrics\Renderer\Dimension\Box\MaintainabilityDimensionPresenter;
use TwigMetrics\Reporter\Dimension\MaintainabilityMetricsReporter;

#[CoversClass(MaintainabilityDimensionPresenter::class)]
final class MaintainabilityDimensionPresenterTest extends TestCase
{
    public function testCanBeInstantiated(): void
    {
        $this->expectNotToPerformAssertions();

        $reporter = new MaintainabilityMetricsReporter(
            new \TwigMetrics\Calculator\ComplexityCalculator(),
            new \TwigMetrics\Reporter\Helper\DimensionGrader(),
        );
        $aggregator = new DirectoryMetricsAggregator();
        $calculator = new StatisticalCalculator();

        new MaintainabilityDimensionPresenter($reporter, $aggregator, $calculator);
    }

    /**
     * @return AnalysisResult[]
     */
    private function createMockResults(): array
    {
        return [
            $this->res([
                'complexity_score' => 5,
                'lines' => 50,
                'max_depth' => 2,
                'dependencies' => [],
                'formatting_consistency_score' => 90.0,
            ], 'components/card.twig'),
            $this->res([
                'complexity_score' => 12,
                'lines' => 120,
                'max_depth' => 4,
                'dependencies' => ['extends' => 'parent.twig'],
                'formatting_consistency_score' => 85.0,
            ], 'layouts/base.twig'),
        ];
    }

    /**
     * @return AnalysisResult[]
     */
    private function createComplexMockResults(): array
    {
        return [
            $this->res([
                'complexity_score' => 25,
                'lines' => 250,
                'max_depth' => 7,
                'dependencies' => [],
                'formatting_consistency_score' => 70.0,
            ], 'complex/template.twig'),
        ];
    }

    private function res(array $metrics, string $rel): AnalysisResult
    {
        $file = new SplFileInfo(__FILE__, '', $rel);

        return new AnalysisResult($file, $metrics, 0.0);
    }
}
