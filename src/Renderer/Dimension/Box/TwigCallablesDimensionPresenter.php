<?php

declare(strict_types=1);

namespace TwigMetrics\Renderer\Dimension\Box;

use TwigMetrics\Analyzer\AnalysisResult;
use TwigMetrics\Analyzer\CallableSecurityAnalyzer;
use TwigMetrics\Reporter\Dimension\TwigCallablesMetricsReporter;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
final class TwigCallablesDimensionPresenter implements DimensionPresenterInterface
{
    public function __construct(
        private readonly TwigCallablesMetricsReporter $reporter,
        private readonly CallableSecurityAnalyzer $security,
    ) {
    }

    /**
     * @param AnalysisResult[] $results
     *
     * @return array<string, mixed>
     */
    public function present(array $results, int $maxDepth = 4, bool $includeDirs = true): array
    {
        $card = $this->reporter->generateMetrics($results);
        $core = $card->coreMetrics;
        $detail = $card->detailMetrics;

        [$fnTotals, $flTotals, $tsTotals, $macroTotals] = $this->aggregateByKind($results);
        $totalCalls = array_sum($fnTotals) + array_sum($flTotals) + array_sum($tsTotals);

        $p = static fn (int $v, int $tot): int => (int) round(($tot > 0 ? $v / $tot : 0) * 100);
        $fnPct = $p(array_sum($fnTotals), $totalCalls);
        $flPct = $p(array_sum($flTotals), $totalCalls);
        $tsPct = $p(array_sum($tsTotals), $totalCalls);
        $customPct = max(0, 100 - ($fnPct + $flPct + $tsPct));

        $topFns = $this->topList($fnTotals, 7);
        $topFilters = $this->topList($flTotals, 7);

        $dirs = [];
        if ($includeDirs && $maxDepth > 0) {
            $dirs = $this->aggregateByDirectory($results);
        }

        $sec = $this->security->analyzeSecurityScore($results);
        $securityIssues = $this->formatSecurityIssues($sec->risks);

        return [
            'summary' => [
                'total_calls' => (int) ($core['total_calls'] ?? 0),
                'unique_functions' => (int) ($core['unique_functions'] ?? 0),
                'unique_filters' => (int) ($core['unique_filters'] ?? 0),
                'unique_tests' => (int) ($core['unique_tests'] ?? 0),

                'funcs_per_template' => $totalCalls > 0 ? round(array_sum($fnTotals) / max(1, count($results)), 2) : 0.0,
                'filters_per_template' => $totalCalls > 0 ? round(array_sum($flTotals) / max(1, count($results)), 2) : 0.0,
                'security_score' => (int) ($detail['security_score'] ?? 0),
                'deprecated_count' => (int) ($detail['deprecated_count'] ?? 0),
            ],
            'distribution' => [
                'core_pct' => $fnPct,
                'custom_pct' => $customPct,
                'filters_pct' => $flPct,
                'tests_pct' => $tsPct,
            ],
            'top_functions' => $topFns,
            'top_filters' => $topFilters,
            'directories' => $dirs,
            'security_issues' => $securityIssues,
            'final' => [
                'score' => (float) $card->score,
                'grade' => (string) $card->grade,
            ],
        ];
    }

    /**
     * @param AnalysisResult[] $results
     *
     * @return array{0: array<string,int>, 1: array<string,int>, 2: array<string,int>, 3: array<string,int>}
     */
    private function aggregateByKind(array $results): array
    {
        $fn = $fl = $ts = $mac = [];
        foreach ($results as $r) {
            foreach ((array) ($r->getMetric('functions_detail') ?? []) as $name => $count) {
                $fn[(string) $name] = ($fn[(string) $name] ?? 0) + (int) $count;
            }
            foreach ((array) ($r->getMetric('filters_detail') ?? []) as $name => $count) {
                $fl[(string) $name] = ($fl[(string) $name] ?? 0) + (int) $count;
            }
            foreach ((array) ($r->getMetric('tests_detail') ?? []) as $name => $count) {
                $ts[(string) $name] = ($ts[(string) $name] ?? 0) + (int) $count;
            }
            foreach ((array) ($r->getMetric('macro_calls_detail') ?? []) as $name => $count) {
                $mac[(string) $name] = ($mac[(string) $name] ?? 0) + (int) $count;
            }
        }

        return [$fn, $fl, $ts, $mac];
    }

    /**
     * @param array<string,int> $totals
     *
     * @return array<int,array{name:string,count:int}>
     */
    private function topList(array $totals, int $limit): array
    {
        arsort($totals);
        $out = [];
        foreach (array_slice($totals, 0, $limit, true) as $name => $count) {
            $out[] = ['name' => (string) $name, 'count' => (int) $count];
        }

        return $out;
    }

    /**
     * @param AnalysisResult[] $results
     *
     * @return array<int, array<string, int|float|string>>
     */
    private function aggregateByDirectory(array $results): array
    {
        $dirs = [];
        $riskyFns = ['dump', 'eval'];
        $riskyFilters = ['raw', 'unsafe'];
        foreach ($results as $r) {
            $path = $r->getRelativePath();
            $dir = str_contains($path, '/') ? explode('/', $path)[0] : '(root)';
            if (!isset($dirs[$dir])) {
                $dirs[$dir] = ['path' => $dir, 'fnc' => 0, 'fil' => 0, 'mac' => 0, 'tot' => 0, 'risk' => 0];
            }
            $fns = (array) ($r->getMetric('functions_detail') ?? []);
            $dirs[$dir]['fnc'] += array_sum($fns);
            $fls = (array) ($r->getMetric('filters_detail') ?? []);
            $dirs[$dir]['fil'] += array_sum($fls);
            $mac = (array) ($r->getMetric('macro_calls_detail') ?? []);
            $dirs[$dir]['mac'] += array_sum($mac);
            $dirs[$dir]['tot'] += array_sum($fns) + array_sum($fls) + array_sum($mac);
            foreach ($fns as $name => $count) {
                if (in_array((string) $name, $riskyFns, true)) {
                    $dirs[$dir]['risk'] += (int) $count;
                }
            }
            foreach ($fls as $name => $count) {
                if (in_array((string) $name, $riskyFilters, true)) {
                    $dirs[$dir]['risk'] += (int) $count;
                }
            }
        }

        foreach ($dirs as &$row) {
            $tot = max(1, $row['tot']);
            $fncW = (int) round(($row['fnc'] / $tot) * 10);
            $filW = (int) round(($row['fil'] / $tot) * 10);
            $macW = max(0, 10 - $fncW - $filW);
            $row['bar'] = str_repeat('█', $fncW).str_repeat('▓', $filW).str_repeat('▒', $macW);
        }

        return array_values($dirs);
    }

    /**
     * @param array<string,int> $risks
     *
     * @return array<int, array<string, int|string|float>>
     */
    private function formatSecurityIssues(array $risks): array
    {
        arsort($risks);
        $out = [];
        foreach (array_slice($risks, 0, 5, true) as $name => $count) {
            $sev = match ((string) $name) {
                'dump', 'eval', 'unsafe' => 'HIGH',
                'raw' => 'MEDIUM',
                default => 'LOW',
            };
            $out[] = ['name' => (string) $name, 'count' => (int) $count, 'severity' => $sev];
        }

        return $out;
    }
}
