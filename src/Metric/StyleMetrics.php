<?php

declare(strict_types=1);

namespace TwigMetrics\Metric;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
final readonly class StyleMetrics
{
    /**
     * @param array<string, list<string>> $violations
     */
    public function __construct(
        public array $violations,
        public float $consistencyScore,
        public float $formattingEntropy,
        public float $readabilityScore,
    ) {
    }
}
