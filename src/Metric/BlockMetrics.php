<?php

declare(strict_types=1);

namespace TwigMetrics\Metric;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
final readonly class BlockMetrics
{
    /**
     * @param list<string> $orphanedBlocks
     */
    public function __construct(
        public int $totalDefined,
        public int $totalUsed,
        public array $orphanedBlocks,
        public float $usageRatio,
        public float $averageReuse,
    ) {
    }
}
