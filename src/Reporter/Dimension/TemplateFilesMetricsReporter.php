<?php

declare(strict_types=1);

namespace TwigMetrics\Reporter\Dimension;

use TwigMetrics\Analyzer\AnalysisResult;
use TwigMetrics\Calculator\DistributionCalculator;
use TwigMetrics\Calculator\StatisticalCalculator;
use TwigMetrics\Metric\DimensionMetrics;
use TwigMetrics\Reporter\Helper\DimensionGrader;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
final class TemplateFilesMetricsReporter
{
    public function __construct(
        private readonly StatisticalCalculator $stats,
        private readonly DistributionCalculator $distribution,
        private readonly DimensionGrader $grader = new DimensionGrader(),
    ) {
    }

    /**
     * @param AnalysisResult[] $results
     */
    public function generateMetrics(array $results): DimensionMetrics
    {
        $lineValues = $this->extractMetricValues($results, 'lines');
        $summary = $this->stats->calculate($lineValues);

        $dirDist = $this->calculateDirectoryBalance($results);
        $dirDominance = $this->maxShare($dirDist);
        $maxLines = max([0, ...$lineValues]);

        [$score, $grade] = $this->grader->gradeTemplateFiles($summary, $dirDominance, $maxLines);

        return new DimensionMetrics(
            name: 'Template Files',
            score: $score,
            grade: $grade,
            coreMetrics: [
                'templates' => count($results),
                'avgLines' => $summary->mean,
                'medianLines' => $summary->median,
                'stdDev' => $summary->stdDev,
            ],
            detailMetrics: [
                'cv' => $summary->coefficientOfVariation,
                'giniIndex' => $summary->giniIndex,
                'entropy' => $summary->entropy,
                'p95' => $summary->p95,
                'dirDominance' => $dirDominance,
            ],
            distributions: [
                'size' => $this->distribution->calculateSizeDistribution($results),
                'directory' => $this->formatDirDistribution($dirDist, count($results)),
            ],
            insights: $this->generateInsights($summary, $dirDominance, $maxLines),
        );
    }

    /**
     * @param AnalysisResult[] $results
     *
     * @return array<string,int>
     */
    private function calculateDirectoryBalance(array $results): array
    {
        $counts = [];
        foreach ($results as $result) {
            $path = $result->getRelativePath();
            $dir = str_contains($path, '/') ? dirname($path) : '.';
            $counts[$dir] = ($counts[$dir] ?? 0) + 1;
        }
        arsort($counts);

        return $counts;
    }

    /**
     * @param array<string,int> $dirCounts
     *
     * @return array<string,array{count:int,percentage:float}>
     */
    private function formatDirDistribution(array $dirCounts, int $total): array
    {
        $total = max(1, $total);
        $out = [];
        foreach ($dirCounts as $dir => $count) {
            $out[$dir] = [
                'count' => $count,
                'percentage' => ($count / $total) * 100.0,
            ];
        }

        return $out;
    }

    /**
     * @param AnalysisResult[] $results
     *
     * @return list<int>
     */
    private function extractMetricValues(array $results, string $name): array
    {
        $values = [];
        foreach ($results as $result) {
            $val = $result->getMetric($name) ?? 0;
            $values[] = (int) $val;
        }

        return $values;
    }

    /**
     * @param array<string,int> $dirDist
     */
    private function maxShare(array $dirDist): float
    {
        $total = array_sum($dirDist);
        if ($total <= 0) {
            return 0.0;
        }
        $max = 0;
        foreach ($dirDist as $count) {
            $max = max($max, $count);
        }

        return $max / $total;
    }

    /**
     * @return list<string>
     */
    private function generateInsights(\TwigMetrics\Metric\StatisticalSummary $s, float $dirDominance, int $maxLines): array
    {
        $insights = [];
        if ($s->giniIndex < 0.35) {
            $insights[] = sprintf('Balanced sizes (Gini: %.2f)', $s->giniIndex);
        }
        if ($dirDominance > 0.5) {
            $insights[] = sprintf('One directory dominates (%.0f%%)', $dirDominance * 100);
        }
        if ($maxLines > 200) {
            $insights[] = sprintf('Large templates present (max: %d lines)', $maxLines);
        }

        return $insights;
    }
}
