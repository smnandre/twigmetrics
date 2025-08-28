<?php

declare(strict_types=1);

namespace TwigMetrics\Tests\Renderer\Dimension\Box;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\SplFileInfo;
use TwigMetrics\Analyzer\AnalysisResult;
use TwigMetrics\Calculator\DistributionCalculator;
use TwigMetrics\Calculator\StatisticalCalculator;
use TwigMetrics\Metric\Aggregator\DirectoryMetricsAggregator;
use TwigMetrics\Renderer\Dimension\Box\TemplateFilesDimensionPresenter;
use TwigMetrics\Reporter\Dimension\TemplateFilesMetricsReporter;

#[CoversClass(TemplateFilesDimensionPresenter::class)]
final class TemplateFilesDimensionPresenterTest extends TestCase
{
    public function testPresentProducesExpectedKeys(): void
    {
        $results = [
            $this->res(['lines' => 40, 'blank_lines' => 4, 'comment_lines' => 2], 'components/a.twig'),
            $this->res(['lines' => 60, 'blank_lines' => 6, 'comment_lines' => 3], 'pages/b.twig'),
            $this->res(['lines' => 120, 'blank_lines' => 12, 'comment_lines' => 6], 'layouts/c.twig'),
        ];

        $presenter = new TemplateFilesDimensionPresenter(
            new TemplateFilesMetricsReporter(new StatisticalCalculator(), new DistributionCalculator()),
            new DirectoryMetricsAggregator(),
            new StatisticalCalculator(),
        );
        $data = $presenter->present($results, 1, true);

        $this->assertArrayHasKey('summary', $data);
        $this->assertArrayHasKey('distribution', $data);
        $this->assertArrayHasKey('directories', $data);
        $this->assertArrayHasKey('top', $data);
        $this->assertArrayHasKey('final', $data);
    }

    private function res(array $metrics, string $rel): AnalysisResult
    {
        $file = new SplFileInfo(__FILE__, '', $rel);

        return new AnalysisResult($file, $metrics, 0.0);
    }
}
