<?php

declare(strict_types=1);

namespace TwigMetrics\Calculator;

use TwigMetrics\Metric\StatisticalSummary;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
final class StatisticalCalculator
{
    /**
     * @param array<int, float|int> $values
     */
    public function calculate(array $values): StatisticalSummary
    {
        $vals = array_map(static fn ($v): float => (float) $v, $values);
        $count = count($vals);

        if (0 === $count) {
            return new StatisticalSummary(
                count: 0,
                sum: 0.0,
                mean: 0.0,
                median: 0.0,
                stdDev: 0.0,
                coefficientOfVariation: 0.0,
                p25: 0.0,
                p75: 0.0,
                p95: 0.0,
                giniIndex: 0.0,
                entropy: 0.0,
                min: 0.0,
                max: 0.0,
                range: 0.0,
            );
        }

        sort($vals, SORT_NUMERIC);

        return new StatisticalSummary(
            count: $count,
            sum: array_sum($vals),
            mean: $this->mean($vals),
            median: $this->median($vals),
            stdDev: $this->standardDeviation($vals),
            coefficientOfVariation: $this->cv($vals),
            p25: $this->percentile($vals, 25),
            p75: $this->percentile($vals, 75),
            p95: $this->percentile($vals, 95),
            giniIndex: $this->giniCoefficient($vals),
            entropy: $this->shannonEntropy($vals),
            min: (float) $vals[0],
            max: (float) $vals[$count - 1],
            range: (float) ($vals[$count - 1] - $vals[0]),
        );
    }

    /**
     * @param array<int, float> $values
     */
    private function mean(array $values): float
    {
        $n = count($values);

        return $n > 0 ? array_sum($values) / $n : 0.0;
    }

    /**
     * @param array<int, float> $values
     */
    private function median(array $values): float
    {
        $n = count($values);
        if (0 === $n) {
            return 0.0;
        }
        $mid = intdiv($n, 2);
        if (0 === $n % 2) {
            return ($values[$mid - 1] + $values[$mid]) / 2.0;
        }

        return $values[$mid];
    }

    /**
     * @param array<int, float> $values
     */
    private function standardDeviation(array $values): float
    {
        $n = count($values);
        if ($n <= 1) {
            return 0.0;
        }
        $mean = $this->mean($values);
        $variance = 0.0;
        foreach ($values as $v) {
            $variance += ($v - $mean) ** 2;
        }
        $variance /= ($n - 1);

        return sqrt($variance);
    }

    /**
     * @param array<int, float> $values
     */
    private function cv(array $values): float
    {
        $mean = $this->mean($values);
        if ($mean <= 0.0) {
            return 0.0;
        }

        return $this->standardDeviation($values) / $mean;
    }

    /**
     * @param array<int, float> $values Sorted values
     */
    private function percentile(array $values, int $p): float
    {
        $n = count($values);
        if (0 === $n) {
            return 0.0;
        }
        $rank = ($p / 100) * ($n - 1);
        $lower = (int) floor($rank);
        $upper = (int) ceil($rank);
        if ($lower === $upper) {
            return $values[$lower];
        }
        $weight = $rank - $lower;

        return $values[$lower] * (1 - $weight) + $values[$upper] * $weight;
    }

    /**
     * @param array<int, float> $values Sorted values
     */
    private function giniCoefficient(array $values): float
    {
        $n = count($values);
        if (0 === $n) {
            return 0.0;
        }
        $sum = array_sum($values);
        if ($sum <= 0.0) {
            return 0.0;
        }

        $mad = 0.0;
        for ($i = 0; $i < $n; ++$i) {
            for ($j = 0; $j < $n; ++$j) {
                $mad += abs($values[$i] - $values[$j]);
            }
        }
        $mean = $sum / $n;

        return $mad / (2 * $n * $n * $mean);
    }

    /**
     * @param array<int, float> $values
     */
    private function shannonEntropy(array $values): float
    {
        $sum = array_sum($values);
        if ($sum <= 0.0) {
            return 0.0;
        }
        $entropy = 0.0;
        foreach ($values as $v) {
            if ($v <= 0.0) {
                continue;
            }
            $p = $v / $sum;
            $entropy -= $p * log($p, 2);
        }

        return $entropy;
    }
}
