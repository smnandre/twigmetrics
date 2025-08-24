<?php

declare(strict_types=1);

namespace TwigMetrics\Reporter\Dimension;

use TwigMetrics\Analyzer\AnalysisResult;
use TwigMetrics\Report\Section\TableSection;
use TwigMetrics\Reporter\ReporterInterface;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
abstract class DimensionReporter implements ReporterInterface
{
    abstract public function getWeight(): float;

    abstract public function getDimensionName(): string;

    /**
     * @param AnalysisResult[] $results
     */
    abstract public function calculateDimensionScore(array $results): float;

    /**
     * Calculate percentage safely, avoiding division by zero.
     */
    protected function calculatePercentage(int $count, int $total): float
    {
        return $total > 0 ? ($count / $total) * 100 : 0;
    }

    /**
     * Format percentage as string with proper handling of zero division.
     */
    protected function formatPercentage(int $count, int $total): string
    {
        return sprintf('%.1f%%', $this->calculatePercentage($count, $total));
    }

    /**
     * Calculate statistics from an array of values, handling empty arrays safely.
     */
    /**
     * @param float[] $values
     *
     * @return array{count: int, sum: float, avg: float, min: float, max: float}
     */
    protected function calculateArrayStats(array $values): array
    {
        if (empty($values)) {
            return [
                'count' => 0,
                'sum' => 0,
                'avg' => 0,
                'min' => 0,
                'max' => 0,
            ];
        }

        return [
            'count' => count($values),
            'sum' => array_sum($values),
            'avg' => array_sum($values) / count($values),
            'min' => min($values),
            'max' => max($values),
        ];
    }

    /**
     * Create a rating distribution table with percentages.
     *
     * @param AnalysisResult[] $results
     * @param string[]         $possibleRatings
     */
    protected function createRatingDistributionTable(
        array $results,
        string $title,
        string $metricKey,
        array $possibleRatings = ['A', 'B', 'C', 'D'],
    ): TableSection {
        $ratings = array_fill_keys($possibleRatings, 0);

        foreach ($results as $result) {
            $rating = $result->getMetric($metricKey) ?? 'D';
            $ratings[$rating] = ($ratings[$rating] ?? 0) + 1;
        }

        $rows = [];
        $totalResults = count($results);
        foreach ($ratings as $rating => $count) {
            $rows[] = [
                $rating,
                $count,
                $this->formatPercentage($count, $totalResults),
            ];
        }

        return new TableSection(
            $title,
            ['Rating', 'Count', 'Percentage'],
            $rows
        );
    }

    /**
     * Extract metric values from results as array.
     *
     * @param AnalysisResult[] $results
     *
     * @return float[]
     */
    protected function extractMetricValues(array $results, string $metricKey): array
    {
        $values = [];
        foreach ($results as $result) {
            $metric = $result->getMetric($metricKey);
            $values[] = is_numeric($metric) ? (float) $metric : 0.0;
        }

        return $values;
    }

    /**
     * Create a count distribution table with percentages (for extensions, directories, etc.).
     *
     * @param array<string, int> $countData
     * @param string[]           $headers
     */
    protected function createCountDistributionTable(
        array $countData,
        string $title,
        array $headers,
        int $totalResults,
    ): TableSection {
        $rows = [];
        foreach ($countData as $key => $count) {
            $rows[] = [
                $key,
                $count,
                $this->formatPercentage($count, $totalResults),
            ];
        }

        return new TableSection($title, $headers, $rows);
    }
}
