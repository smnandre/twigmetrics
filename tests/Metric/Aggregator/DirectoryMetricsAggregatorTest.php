<?php

declare(strict_types=1);

namespace TwigMetrics\Tests\Metric\Aggregator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\SplFileInfo;
use TwigMetrics\Analyzer\AnalysisResult;
use TwigMetrics\Metric\Aggregator\DirectoryMetricsAggregator;

#[CoversClass(DirectoryMetricsAggregator::class)]
final class DirectoryMetricsAggregatorTest extends TestCase
{
    public function testAggregateByDirectory(): void
    {
        $results = [
            $this->res(['lines' => 10, 'blank_lines' => 1, 'comment_lines' => 1], 'components/card.html.twig'),
            $this->res(['lines' => 20, 'blank_lines' => 2, 'comment_lines' => 2], 'pages/home.html.twig'),
        ];

        $agg = new DirectoryMetricsAggregator();
        $dirs = $agg->aggregateByDirectory($results, 2);

        $this->assertArrayHasKey('components', $dirs);
        $this->assertArrayHasKey('pages', $dirs);
        $this->assertGreaterThan(0, $dirs['components']->fileCount);
    }

    private function res(array $metrics, string $rel): AnalysisResult
    {
        $file = new SplFileInfo(__FILE__, '', $rel);

        return new AnalysisResult($file, $metrics, 0.0);
    }
}
