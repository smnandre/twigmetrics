<?php

declare(strict_types=1);

namespace TwigMetrics\Metric;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
final readonly class StatisticalSummary
{
    public function __construct(
        public int $count,
        public float $sum,
        public float $mean,
        public float $median,
        public float $stdDev,
        public float $coefficientOfVariation,
        public float $p25,
        public float $p75,
        public float $p95,
        public float $giniIndex,
        public float $entropy,
        public float $min,
        public float $max,
        public float $range,
    ) {
    }
}
