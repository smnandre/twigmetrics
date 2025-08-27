<?php

declare(strict_types=1);

namespace TwigMetrics\Reporter\Dimension;

use TwigMetrics\Analyzer\AnalysisResult;
use TwigMetrics\Calculator\ComplexityCalculator;
use TwigMetrics\Metric\DimensionMetrics;
use TwigMetrics\Reporter\Helper\DimensionGrader;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
final class MaintainabilityMetricsReporter
{
    public function __construct(
        private readonly ComplexityCalculator $complexity,
        private readonly DimensionGrader $grader = new DimensionGrader(),
    ) {
    }

    /**
     * @param AnalysisResult[] $results
     */
    public function generateMetrics(array $results): DimensionMetrics
    {
        $miValues = [];
        $riskRows = [];

        foreach ($results as $r) {
            $mi = $this->complexity->calculateMaintainabilityIndex($r);
            $miValues[] = $mi;
            $riskRows[] = [
                'template' => $r->getRelativePath(),
                'risk' => $this->priorityScore($r),
                'complexity' => (int) ($r->getMetric('complexity_score') ?? 0),
                'lines' => (int) ($r->getMetric('lines') ?? 0),
            ];
        }

        sort($miValues, SORT_NUMERIC);
        $miAvg = $this->avg($miValues);
        $miMedian = $this->median($miValues);

        [$score, $grade] = $this->grader->gradeMaintainability($miAvg);

        usort($riskRows, static fn ($a, $b) => $b['risk'] <=> $a['risk']);
        $top = array_slice($riskRows, 0, 5);

        $buckets = $this->bucketRisk(array_column($riskRows, 'risk'));

        return new DimensionMetrics(
            name: 'Maintainability',
            score: $score,
            grade: $grade,
            coreMetrics: [
                'mi_avg' => round($miAvg, 1),
                'mi_median' => round($miMedian, 1),
                'refactor_candidates' => count($top),
            ],
            detailMetrics: [
                'risk_high' => $buckets['high'],
                'risk_medium' => $buckets['medium'],
                'risk_low' => $buckets['low'],
            ],
            distributions: [
                'risk' => $buckets,
            ],
            insights: $this->insights($top),
        );
    }

    private function priorityScore(AnalysisResult $r): float
    {
        $complexity = (int) ($r->getMetric('complexity_score') ?? 0);
        $lines = (int) ($r->getMetric('lines') ?? 0);
        $deps = is_array($r->getMetric('dependencies') ?? null) ? count((array) $r->getMetric('dependencies')) : 0;
        $styleConsistency = (float) ($r->getMetric('formatting_consistency_score') ?? 100.0);

        $nx = min(1.0, $complexity / 30.0);
        $nl = min(1.0, $lines / 200.0);
        $nd = min(1.0, $deps / 5.0);
        $ns = 1.0 - min(1.0, $styleConsistency / 100.0);

        return 0.4 * $nx + 0.3 * $nl + 0.2 * $nd + 0.1 * $ns;
    }

    /**
     * @param list<float> $values
     */
    private function avg(array $values): float
    {
        $n = count($values);

        return $n > 0 ? array_sum($values) / $n : 0.0;
    }

    /**
     * @param list<float> $values
     */
    private function median(array $values): float
    {
        $n = count($values);
        if (0 === $n) {
            return 0.0;
        }
        $mid = intdiv($n, 2);

        return 0 === $n % 2 ? ($values[$mid - 1] + $values[$mid]) / 2.0 : $values[$mid];
    }

    /**
     * @param list<float> $risks
     *
     * @return array{high:int,medium:int,low:int}
     */
    private function bucketRisk(array $risks): array
    {
        $b = ['high' => 0, 'medium' => 0, 'low' => 0];
        foreach ($risks as $r) {
            if ($r >= 0.7) {
                ++$b['high'];
            } elseif ($r >= 0.35) {
                ++$b['medium'];
            } else {
                ++$b['low'];
            }
        }

        return $b;
    }

    /**
     * @param array<int,array{template:string,risk:float,complexity:int,lines:int}> $top
     *
     * @return list<string>
     */
    private function insights(array $top): array
    {
        if (empty($top)) {
            return [];
        }
        $first = $top[0];

        return [
            sprintf('Top refactor: %s (risk %.2f)', $first['template'], $first['risk']),
        ];
    }
}
