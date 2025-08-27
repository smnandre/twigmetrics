<?php

declare(strict_types=1);

namespace TwigMetrics\Metric;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
final readonly class SecurityMetrics
{
    /**
     * @param array<string,int> $risks
     */
    public function __construct(
        public int $score,
        public array $risks,
        public int $deprecatedCount,
    ) {
    }
}
