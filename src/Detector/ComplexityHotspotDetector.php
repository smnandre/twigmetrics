<?php

declare(strict_types=1);

namespace TwigMetrics\Detector;

use TwigMetrics\Analyzer\AnalysisResult;
use TwigMetrics\Calculator\ComplexityCalculator;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
final class ComplexityHotspotDetector
{
    public function __construct(private readonly ComplexityCalculator $calculator = new ComplexityCalculator())
    {
    }

    /**
     * @param AnalysisResult[] $results
     *
     * @return array<int, array{file:string, complexity:int, logicRatio:float, maintainability:float}>
     */
    public function detectHotspots(array $results, int $limit = 5): array
    {
        $complexities = [];

        foreach ($results as $result) {
            $complexities[] = [
                'file' => basename($result->getRelativePath()),
                'complexity' => (int) ($result->getMetric('complexity_score') ?? 0),
                'logicRatio' => $this->calculator->calculateLogicRatio($result),
                'maintainability' => $this->calculator->calculateMaintainabilityIndex($result),
            ];
        }

        usort($complexities, static fn ($a, $b) => $b['complexity'] <=> $a['complexity']);

        return array_slice($complexities, 0, $limit);
    }
}
