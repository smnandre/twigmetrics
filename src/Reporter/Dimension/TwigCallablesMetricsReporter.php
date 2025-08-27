<?php

declare(strict_types=1);

namespace TwigMetrics\Reporter\Dimension;

use TwigMetrics\Analyzer\AnalysisResult;
use TwigMetrics\Analyzer\CallableSecurityAnalyzer;
use TwigMetrics\Calculator\DiversityCalculator;
use TwigMetrics\Metric\DimensionMetrics;
use TwigMetrics\Reporter\Helper\DimensionGrader;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
final class TwigCallablesMetricsReporter
{
    public function __construct(
        private readonly DiversityCalculator $diversity,
        private readonly CallableSecurityAnalyzer $security,
        private readonly DimensionGrader $grader = new DimensionGrader(),
    ) {
    }

    /**
     * @param AnalysisResult[] $results
     */
    public function generateMetrics(array $results): DimensionMetrics
    {
        [$functions, $filters, $tests] = $this->aggregateUsage($results);
        $mergedUsage = $this->mergeUsage($functions, $filters);

        $diversityIndex = $this->diversity->calculateSimpsonDiversity($mergedUsage);
        $usageEntropy = $this->diversity->calculateUsageEntropy($mergedUsage);

        $security = $this->security->analyzeSecurityScore($results);
        $debugCalls = $security->risks['dump'] ?? 0;

        [$score, $grade] = $this->grader->gradeCallables(
            securityScore: (float) $security->score,
            diversityIndex: $diversityIndex,
            deprecatedCount: (int) $security->deprecatedCount,
            debugCalls: (int) $debugCalls,
        );

        $totalCalls = array_sum($functions) + array_sum($filters) + array_sum($tests);
        $templates = max(1, count($results));

        return new DimensionMetrics(
            name: 'Twig Callables',
            score: $score,
            grade: $grade,
            coreMetrics: [
                'total_calls' => $totalCalls,
                'unique_functions' => count($functions),
                'unique_filters' => count($filters),
                'unique_tests' => count($tests),
                'avg_calls_per_template' => round($totalCalls / $templates, 2),
            ],
            detailMetrics: [
                'diversity_index' => round($diversityIndex, 3),
                'usage_entropy' => round($usageEntropy, 3),
                'security_score' => $security->score,
                'deprecated_count' => $security->deprecatedCount,
            ],
            distributions: [
                'usage_breakdown' => [
                    'functions' => array_sum($functions),
                    'filters' => array_sum($filters),
                    'tests' => array_sum($tests),
                ],
            ],
            insights: $this->buildInsights($security->risks),
        );
    }

    /**
     * @param AnalysisResult[] $results
     *
     * @return array{0: array<string,int>, 1: array<string,int>, 2: array<string,int>}
     */
    private function aggregateUsage(array $results): array
    {
        $functions = [];
        $filters = [];
        $tests = [];

        foreach ($results as $result) {
            foreach ((array) ($result->getMetric('functions_detail') ?? []) as $name => $count) {
                $functions[(string) $name] = ($functions[(string) $name] ?? 0) + (int) $count;
            }
            foreach ((array) ($result->getMetric('filters_detail') ?? []) as $name => $count) {
                $filters[(string) $name] = ($filters[(string) $name] ?? 0) + (int) $count;
            }
            foreach ((array) ($result->getMetric('tests_detail') ?? []) as $name => $count) {
                $tests[(string) $name] = ($tests[(string) $name] ?? 0) + (int) $count;
            }
        }

        ksort($functions);
        ksort($filters);
        ksort($tests);

        return [$functions, $filters, $tests];
    }

    /**
     * @param array<string,int> $a
     * @param array<string,int> $b
     *
     * @return array<string,int>
     */
    private function mergeUsage(array $a, array $b): array
    {
        $out = $a;
        foreach ($b as $k => $v) {
            $out[$k] = ($out[$k] ?? 0) + $v;
        }

        return $out;
    }

    /**
     * @param array<string,int> $risks
     *
     * @return list<string>
     */
    private function buildInsights(array $risks): array
    {
        if (empty($risks)) {
            return [];
        }
        arsort($risks);
        $top = array_slice($risks, 0, 3, true);
        $lines = [];
        foreach ($top as $name => $count) {
            $lines[] = sprintf('Risky: %s (%d)', $name, $count);
        }

        return $lines;
    }
}
