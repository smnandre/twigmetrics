<?php

declare(strict_types=1);

namespace TwigMetrics\Reporter\Dimension;

use TwigMetrics\Analyzer\AnalysisResult;
use TwigMetrics\Calculator\ComplexityCalculator;
use TwigMetrics\Calculator\StatisticalCalculator;
use TwigMetrics\Detector\ComplexityHotspotDetector;
use TwigMetrics\Metric\DimensionMetrics;
use TwigMetrics\Reporter\Helper\DimensionGrader;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
final class LogicalComplexityMetricsReporter
{
    public function __construct(
        private readonly StatisticalCalculator $stats,
        private readonly ComplexityCalculator $complexityCalc,
        private readonly ComplexityHotspotDetector $hotspots,
        private readonly DimensionGrader $grader = new DimensionGrader(),
    ) {
    }

    /**
     * @param AnalysisResult[] $results
     */
    public function generateMetrics(array $results): DimensionMetrics
    {
        $complexities = $this->extractIntMetric($results, 'complexity_score');
        $summary = $this->stats->calculate($complexities);

        $buckets = $this->bucketComplexity($complexities);
        $criticalCount = $buckets['critical'];
        $criticalRatio = count($results) > 0 ? $criticalCount / count($results) : 0.0;

        [$avgLogic, $avgDecisionDensity, $avgMi] = $this->averages($results);

        [$score, $grade] = $this->grader->gradeComplexity(
            avg: $summary->mean,
            max: (int) $summary->max,
            criticalRatio: $criticalRatio,
            logicRatio: $avgLogic,
        );

        $hot = $this->hotspots->detectHotspots($results, 5);

        return new DimensionMetrics(
            name: 'Logical Complexity',
            score: $score,
            grade: $grade,
            coreMetrics: [
                'avg' => round($summary->mean, 2),
                'median' => $summary->median,
                'max' => (int) $summary->max,
                'critical_files' => $criticalCount,
            ],
            detailMetrics: [
                'logic_ratio' => round($avgLogic, 3),
                'decision_density' => round($avgDecisionDensity, 3),
                'mi_avg' => round($avgMi, 2),
            ],
            distributions: [
                'heatmap' => [
                    'simple' => $buckets['simple'],
                    'moderate' => $buckets['moderate'],
                    'complex' => $buckets['complex'],
                    'critical' => $buckets['critical'],
                ],
            ],
            insights: $this->makeInsights($criticalCount, (int) $summary->max, $hot),
        );
    }

    /**
     * @param AnalysisResult[] $results
     *
     * @return array{0: float, 1: float, 2: float}
     */
    private function averages(array $results): array
    {
        $n = max(1, count($results));
        $sumLogic = 0.0;
        $sumDensity = 0.0;
        $sumMi = 0.0;
        foreach ($results as $r) {
            $sumLogic += $this->complexityCalc->calculateLogicRatio($r);
            $sumDensity += $this->complexityCalc->calculateDecisionDensity($r);
            $sumMi += $this->complexityCalc->calculateMaintainabilityIndex($r);
        }

        return [$sumLogic / $n, $sumDensity / $n, $sumMi / $n];
    }

    /**
     * @param list<int|float> $values
     *
     * @return array{simple:int,moderate:int,complex:int,critical:int}
     */
    private function bucketComplexity(array $values): array
    {
        $b = ['simple' => 0, 'moderate' => 0, 'complex' => 0, 'critical' => 0];
        foreach ($values as $v) {
            $c = (int) $v;
            if ($c <= 5) {
                ++$b['simple'];
            } elseif ($c <= 15) {
                ++$b['moderate'];
            } elseif ($c <= 25) {
                ++$b['complex'];
            } else {
                ++$b['critical'];
            }
        }

        return $b;
    }

    /**
     * @param AnalysisResult[] $results
     *
     * @return list<int>
     */
    private function extractIntMetric(array $results, string $name): array
    {
        $values = [];
        foreach ($results as $result) {
            $values[] = (int) ($result->getMetric($name) ?? 0);
        }

        return $values;
    }

    /**
     * @param array<int, array{file:string, complexity:int, logicRatio:float, maintainability:float}> $hot
     *
     * @return list<string>
     */
    private function makeInsights(int $critical, int $max, array $hot): array
    {
        $insights = [];
        if ($critical > 0) {
            $insights[] = sprintf('%d critical file(s)', $critical);
        }
        if ($max > 25) {
            $insights[] = sprintf('Max complexity: %d', $max);
        }
        if (!empty($hot)) {
            $insights[] = sprintf('Hotspot: %s [%d]', $hot[0]['file'], $hot[0]['complexity']);
        }

        return $insights;
    }
}
