<?php

declare(strict_types=1);

namespace TwigMetrics\Calculator;

use TwigMetrics\Analyzer\AnalysisResult;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
final class DistributionCalculator
{
    /**
     * @param AnalysisResult[] $results
     *
     * @return array<string, array{count:int, percentage:float}>
     */
    public function calculateSizeDistribution(array $results): array
    {
        $buckets = [
            '0-50' => 0,
            '51-100' => 0,
            '101-200' => 0,
            '201-500' => 0,
            '500+' => 0,
        ];

        foreach ($results as $result) {
            $lines = (int) ($result->getMetric('lines') ?? 0);
            match (true) {
                $lines <= 50 => ++$buckets['0-50'],
                $lines <= 100 => ++$buckets['51-100'],
                $lines <= 200 => ++$buckets['101-200'],
                $lines <= 500 => ++$buckets['201-500'],
                default => ++$buckets['500+'],
            };
        }

        $total = max(1, count($results));
        $out = [];
        foreach ($buckets as $range => $count) {
            $out[$range] = [
                'count' => $count,
                'percentage' => ($count / $total) * 100.0,
            ];
        }

        return $out;
    }
}
