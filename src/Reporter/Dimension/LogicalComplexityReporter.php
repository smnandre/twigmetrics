<?php

declare(strict_types=1);

namespace TwigMetrics\Reporter\Dimension;

use TwigMetrics\Analyzer\AnalysisResult;
use TwigMetrics\Report\Report;
use TwigMetrics\Report\Section\KeyValueSection;
use TwigMetrics\Report\Section\ListSection;
use TwigMetrics\Report\Section\ReportSection;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
final class LogicalComplexityReporter extends DimensionReporter
{
    public function getWeight(): float
    {
        return 0.20;
    }

    public function getDimensionName(): string
    {
        return 'Logical Complexity';
    }

    public function generate(array $results): Report
    {
        $report = new Report('Logical Complexity Analysis');

        $report->addSection(new KeyValueSection('Summary', [
            'Templates' => (string) count($results),
            'Avg Complexity' => sprintf('%.1f', $this->calculateComplexityStats($results)['avg_complexity'] ?? 0),
            'Max Depth' => (string) ((int) ($this->calculateComplexityStats($results)['max_depth'] ?? 0)),
        ]));

        $stats = $this->buildPrototypeStats($results);
        $report->addSection(new ReportSection('Logical Complexity (Prototype)', 'complexity_proto', $stats));

        $top = [];
        $bottom = [];
        foreach ($results as $res) {
            $cx = (int) ($res->getMetric('complexity_score') ?? 0);
            $path = $res->getRelativePath();
            $top[] = ['path' => $path, 'cx' => $cx];
            $bottom[] = ['path' => $path, 'cx' => $cx];
        }
        usort($top, fn ($a, $b) => $b['cx'] <=> $a['cx']);
        usort($bottom, fn ($a, $b) => $a['cx'] <=> $b['cx']);
        $topItems = array_map(fn ($e) => sprintf('%s  %d complexity', basename($e['path']), $e['cx']), array_slice($top, 0, 5));
        $bottomItems = array_map(fn ($e) => sprintf('%s  %d complexity', basename($e['path']), $e['cx']), array_slice($bottom, 0, 5));
        if (!empty($topItems)) {
            $report->addSection(new ListSection('Most Complex', $topItems));
        }
        if (!empty($bottomItems)) {
            $report->addSection(new ListSection('Least Complex', $bottomItems));
        }

        return $report;
    }

    public function calculateDimensionScore(array $results): float
    {
        $stats = $this->calculateComplexityStats($results);

        $score = 100;

        if ($stats['avg_complexity'] > 25) {
            $score -= 40;
        } elseif ($stats['avg_complexity'] > 20) {
            $score -= 30;
        } elseif ($stats['avg_complexity'] > 15) {
            $score -= 20;
        } elseif ($stats['avg_complexity'] > 10) {
            $score -= 10;
        }

        if ($stats['avg_depth'] > 6) {
            $score -= 25;
        } elseif ($stats['avg_depth'] > 5) {
            $score -= 20;
        } elseif ($stats['avg_depth'] > 4) {
            $score -= 15;
        } elseif ($stats['avg_depth'] > 3) {
            $score -= 10;
        }

        return max(0, $score);
    }

    /**
     * @param AnalysisResult[] $results
     *
     * @return array<string, float|int>
     */
    private function calculateComplexityStats(array $results): array
    {
        $complexities = $this->extractMetricValues($results, 'complexity_score');
        $depths = $this->extractMetricValues($results, 'max_depth');

        $complexityStats = $this->calculateArrayStats($complexities);
        $depthStats = $this->calculateArrayStats($depths);

        return [
            'avg_complexity' => $complexityStats['avg'],
            'max_complexity' => $complexityStats['max'],
            'avg_depth' => $depthStats['avg'],
            'max_depth' => $depthStats['max'],
        ];
    }

    /**
     * Build stats array expected by ConsoleRenderer::renderComplexityPrototype.
     *
     * @param AnalysisResult[] $results
     *
     * @return array<string, mixed>
     */
    private function buildPrototypeStats(array $results): array
    {
        $complexities = $this->extractMetricValues($results, 'complexity_score');
        $depths = $this->extractMetricValues($results, 'max_depth');
        $count = max(1, count($complexities));

        $avg = array_sum($complexities) / $count;
        $median = $this->median($complexities);
        $max = !empty($complexities) ? max($complexities) : 0;

        $depthAvg = !empty($depths) ? array_sum($depths) / max(1, count($depths)) : 0;
        $depthMax = !empty($depths) ? max($depths) : 0;

        $buckets = ['low' => 0, 'medium' => 0, 'high' => 0, 'critical' => 0];
        foreach ($complexities as $cx) {
            if ($cx < 5) {
                ++$buckets['low'];
            } elseif ($cx < 10) {
                ++$buckets['medium'];
            } elseif ($cx < 20) {
                ++$buckets['high'];
            } else {
                ++$buckets['critical'];
            }
        }
        $pctCritical = $this->calculatePercentage($buckets['critical'], max(1, count($complexities)));

        $depthCounts = array_fill(0, 7, 0);
        foreach ($depths as $d) {
            $idx = (int) $d;
            if ($idx >= 0 && $idx <= 6) {
                ++$depthCounts[$idx];
            } elseif ($idx > 6) {
                ++$depthCounts[6];
            }
        }

        $hotspots = [];
        foreach ($results as $result) {
            $cx = (int) ($result->getMetric('complexity_score') ?? 0);
            if ($cx <= 15) {
                continue;
            }
            $hotspots[] = [
                'template' => $result->getRelativePath(),
                'complexity' => $cx,
                'depth' => (int) ($result->getMetric('max_depth') ?? 0),
                'rating' => (string) ($result->getMetric('complexity_rating') ?? 'D'),
            ];
        }
        usort($hotspots, fn ($a, $b) => $b['complexity'] <=> $a['complexity']);
        $hotspots = array_slice($hotspots, 0, 5);

        $dirAgg = [];
        foreach ($results as $result) {
            $path = $result->getRelativePath();
            $parts = explode('/', $path, 2);
            $dir = $parts[1] ?? false ? $parts[0] : '(root)';
            $cx = (float) ($result->getMetric('complexity_score') ?? 0.0);
            if (!isset($dirAgg[$dir])) {
                $dirAgg[$dir] = ['sum' => 0.0, 'count' => 0, 'max' => 0.0];
            }
            $dirAgg[$dir]['sum'] += $cx;
            ++$dirAgg[$dir]['count'];
            $dirAgg[$dir]['max'] = max($dirAgg[$dir]['max'], $cx);
        }
        $dirStats = [];
        foreach ($dirAgg as $dir => $d) {
            $avgDir = $d['sum'] / $d['count'];
            $dirStats[] = [
                'name' => $dir,
                'count' => $d['count'],
                'avg' => $avgDir,
                'max' => $d['max'],
            ];
        }

        usort($dirStats, function ($a, $b) {
            return [$b['count'], $b['avg']] <=> [$a['count'], $a['avg']];
        });
        $dirStats = array_slice($dirStats, 0, 10);

        return [
            'avg' => $avg,
            'median' => $median,
            'max' => $max,
            'depth_avg' => $depthAvg,
            'depth_max' => $depthMax,
            'pct_critical' => $pctCritical,
            'buckets' => $buckets,
            'depth_counts' => $depthCounts,
            'hotspots' => $hotspots,
            'dir_stats' => $dirStats,
        ];
    }

    /**
     * @param array<int|float> $values
     */
    private function median(array $values): float
    {
        if (empty($values)) {
            return 0.0;
        }
        sort($values);
        $n = count($values);
        $mid = intdiv($n, 2);
        if (0 === $n % 2) {
            return ($values[$mid - 1] + $values[$mid]) / 2.0;
        }

        return (float) $values[$mid];
    }
}
