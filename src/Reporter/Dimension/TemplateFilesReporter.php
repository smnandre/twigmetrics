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
final class TemplateFilesReporter extends DimensionReporter
{
    public function getWeight(): float
    {
        return 0.15;
    }

    public function getDimensionName(): string
    {
        return 'Template Files';
    }

    public function generate(array $results): Report
    {
        $report = new Report('Template Files Analysis');

        $report->addSection(new KeyValueSection('Summary', [
            'Templates' => (string) count($results),
            'Avg Lines/Template' => sprintf('%.1f', $this->calculateFileStats($results)['avg_lines'] ?? 0),
            'Min/Max Lines' => sprintf('%d / %d', (int) ($this->calculateFileStats($results)['min_lines'] ?? 0), (int) ($this->calculateFileStats($results)['max_lines'] ?? 0)),
        ]));

        $stats = $this->buildPrototypeStats($results);
        $report->addSection(new ReportSection('Template Files (Prototype)', 'template_files_proto', $stats));

        $largest = array_slice($stats['largest'] ?? [], 0, 5);
        $largestItems = array_map(fn ($f) => sprintf('%s  %d lines', basename((string) $f['name']), (int) $f['lines']), $largest);
        if (!empty($largestItems)) {
            $report->addSection(new ListSection('Top Largest Files', $largestItems));
        }

        $small = [];
        foreach ($results as $result) {
            $l = (int) ($result->getMetric('lines') ?? 0);
            if ($l > 0) {
                $small[] = ['name' => $result->getRelativePath(), 'lines' => $l];
            }
        }
        usort($small, fn ($a, $b) => $a['lines'] <=> $b['lines']);
        $small = array_slice($small, 0, 5);
        $smallItems = array_map(fn ($f) => sprintf('%s  %d lines', basename((string) $f['name']), (int) $f['lines']), $small);
        if (!empty($smallItems)) {
            $report->addSection(new ListSection('Smallest Files', $smallItems));
        }

        return $report;
    }

    public function calculateDimensionScore(array $results): float
    {
        $stats = $this->calculateFileStats($results);

        $score = 100;

        if ($stats['avg_lines'] > 150) {
            $score -= 40;
        } elseif ($stats['avg_lines'] > 100) {
            $score -= 25;
        } elseif ($stats['avg_lines'] > 75) {
            $score -= 10;
        }

        if ($stats['max_lines'] > 300) {
            $score -= 30;
        } elseif ($stats['max_lines'] > 200) {
            $score -= 20;
        } elseif ($stats['max_lines'] > 150) {
            $score -= 10;
        }

        return max(0, $score);
    }

    /**
     * @param AnalysisResult[] $results
     *
     * @return array<string, float|int>
     */
    private function calculateFileStats(array $results): array
    {
        $lines = $this->extractMetricValues($results, 'lines');
        $stats = $this->calculateArrayStats($lines);

        return [
            'avg_lines' => $stats['avg'],
            'max_lines' => $stats['max'],
            'min_lines' => $stats['min'],
            'total_lines' => $stats['sum'],
        ];
    }

    /**
     * Build stats for compact renderer.
     *
     * @param AnalysisResult[] $results
     *
     * @return array<string, mixed>
     */
    private function buildPrototypeStats(array $results): array
    {
        $lines = [];
        $files = [];
        $structureCounts = [];
        foreach ($results as $result) {
            $l = (int) ($result->getMetric('lines') ?? 0);
            $lines[] = $l;
            $files[] = ['name' => $result->getRelativePath(), 'lines' => $l];

            $relPath = $result->getRelativePath();
            $dir = dirname($relPath);
            if ('.' !== $dir && DIRECTORY_SEPARATOR !== $dir) {
                $parts = explode('/', $dir);
                $key = $parts[0].'/';
                if (isset($parts[1]) && '' !== $parts[1]) {
                    $key = $parts[0].'/'.$parts[1].'/';
                }
                $structureCounts[$key] = ($structureCounts[$key] ?? 0) + 1;
            }
        }
        $count = max(1, count($lines));
        $sum = array_sum($lines);
        $avg = $sum / $count;
        $median = $this->median($lines);
        $max = !empty($lines) ? max($lines) : 0;
        $min = !empty($lines) ? min($lines) : 0;

        $buckets = ['s' => 0, 'm' => 0, 'l' => 0, 'xl' => 0];
        foreach ($lines as $l) {
            if ($l <= 50) {
                ++$buckets['s'];
            } elseif ($l <= 100) {
                ++$buckets['m'];
            } elseif ($l <= 150) {
                ++$buckets['l'];
            } else {
                ++$buckets['xl'];
            }
        }

        usort($files, fn ($a, $b) => $b['lines'] <=> $a['lines']);
        $largest = array_slice($files, 0, 3);

        $dirAgg = [];
        foreach ($results as $result) {
            $path = $result->getRelativePath();
            $parts = explode('/', $path, 2);
            $dir = $parts[1] ?? false ? $parts[0] : '(root)';
            $l = (float) ($result->getMetric('lines') ?? 0.0);
            if (!isset($dirAgg[$dir])) {
                $dirAgg[$dir] = ['sum' => 0.0, 'count' => 0, 'max' => 0.0];
            }
            $dirAgg[$dir]['sum'] += $l;
            ++$dirAgg[$dir]['count'];
            $dirAgg[$dir]['max'] = max($dirAgg[$dir]['max'], $l);
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

        arsort($structureCounts);
        $structure = [];
        foreach ($structureCounts as $path => $cnt) {
            $structure[] = [
                'path' => $path,
                'count' => $cnt,
                'percent' => ($cnt / $count) * 100,
            ];
        }

        $structure = array_slice($structure, 0, 12);

        return [
            'count' => count($lines),
            'avg' => $avg,
            'median' => $median,
            'min' => $min,
            'max' => $max,
            'buckets' => $buckets,
            'largest' => $largest,
            'dir_stats' => $dirStats,
            'structure' => $structure,
        ];
    }

    /**
     * @param int[] $values
     */
    private function median(array $values): float
    {
        if (empty($values)) {
            return 0.0;
        }
        sort($values);
        $n = count($values);
        $mid = intdiv($n, 2);

        return (0 === $n % 2) ? (($values[$mid - 1] + $values[$mid]) / 2.0) : (float) $values[$mid];
    }
}
