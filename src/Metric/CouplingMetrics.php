<?php

declare(strict_types=1);

namespace TwigMetrics\Metric;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
final readonly class CouplingMetrics
{
    public function __construct(
        public float $avgFanIn,
        public float $avgFanOut,
        public int $maxCoupling,
        public float $instabilityIndex,
        public int $circularRefs,
    ) {
    }
}
