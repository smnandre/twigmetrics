<?php

declare(strict_types=1);

namespace TwigMetrics\Metric;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
final readonly class DimensionMetrics
{
    /**
     * @param array<string, mixed>                    $coreMetrics
     * @param array<string, mixed>                    $detailMetrics
     * @param array<string, mixed>                    $distributions
     * @param array<int, string>|array<string, mixed> $insights
     */
    public function __construct(
        public string $name,
        public float $score,
        public string $grade,
        public array $coreMetrics,
        public array $detailMetrics,
        public array $distributions,
        public array $insights,
    ) {
    }
}
