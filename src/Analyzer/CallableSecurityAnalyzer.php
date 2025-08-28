<?php

declare(strict_types=1);

namespace TwigMetrics\Analyzer;

use TwigMetrics\Metric\SecurityMetrics;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
final class CallableSecurityAnalyzer
{
    private const RISKY_FUNCTIONS = ['dump', 'eval', 'include_raw'];
    private const RISKY_FILTERS = ['raw', 'unsafe'];

    /**
     * @param AnalysisResult[] $results
     */
    public function analyzeSecurityScore(array $results): SecurityMetrics
    {
        $risks = [];
        $score = 100;

        foreach ($results as $result) {
            $functions = $result->getMetric('functions_detail') ?? [];
            $filters = $result->getMetric('filters_detail') ?? [];

            if (is_array($functions)) {
                foreach ($functions as $func => $count) {
                    if (in_array((string) $func, self::RISKY_FUNCTIONS, true)) {
                        $risks[(string) $func] = ($risks[(string) $func] ?? 0) + (int) $count;
                        $score -= 5;
                    }
                }
            }

            if (is_array($filters)) {
                foreach ($filters as $filter => $count) {
                    if (in_array((string) $filter, self::RISKY_FILTERS, true)) {
                        $risks[(string) $filter] = ($risks[(string) $filter] ?? 0) + (int) $count;
                        $score -= 3;
                    }
                }
            }
        }

        return new SecurityMetrics(
            score: max(0, $score),
            risks: $risks,
            deprecatedCount: $this->countDeprecated($results),
        );
    }

    /**
     * @param AnalysisResult[] $results
     */
    private function countDeprecated(array $results): int
    {
        $count = 0;
        foreach ($results as $result) {
            $deprecated = $result->getMetric('deprecated_callables') ?? 0;
            $count += (int) (is_numeric($deprecated) ? $deprecated : 0);
        }

        return $count;
    }
}
