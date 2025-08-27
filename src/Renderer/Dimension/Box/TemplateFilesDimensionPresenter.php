<?php

declare(strict_types=1);

namespace TwigMetrics\Renderer\Dimension\Box;

use TwigMetrics\Analyzer\AnalysisResult;
use TwigMetrics\Calculator\StatisticalCalculator;
use TwigMetrics\Metric\Aggregator\DirectoryMetricsAggregator;
use TwigMetrics\Reporter\Dimension\TemplateFilesMetricsReporter;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
final class TemplateFilesDimensionPresenter implements DimensionPresenterInterface
{
    public function __construct(
        private readonly TemplateFilesMetricsReporter $reporter,
        private readonly DirectoryMetricsAggregator $dirAgg,
        private readonly StatisticalCalculator $stats,
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
        $sizeDist = $card->distributions['size'] ?? [];

        $totalTemplates = (int) ($core['templates'] ?? count($results));
        $totalLines = array_sum(array_map(static fn (AnalysisResult $r): int => (int) ($r->getMetric('lines') ?? 0), $results));

        $b0 = (int) ($sizeDist['0-50']['count'] ?? 0);
        $b1 = (int) ($sizeDist['51-100']['count'] ?? 0);
        $b2 = (int) ($sizeDist['101-200']['count'] ?? 0);
        $b3 = (int) ($sizeDist['201-500']['count'] ?? 0);
        $b4 = (int) ($sizeDist['500+']['count'] ?? 0);
        $pct = static function (int $count, int $total): int { return (int) round(($total > 0 ? ($count / $total) : 0) * 100); };
        $p0 = $pct($b0, $totalTemplates);
        $p1 = $pct($b1, $totalTemplates);
        $p2 = $pct($b2, $totalTemplates);
        $p3 = $pct($b3 + $b4, $totalTemplates);

        $filesOver500 = $b4;
        $orphanCount = $this->countOrphans($results);
        $avgDepth = $this->averageDirectoryDepth($results);

        $dirs = [];
        if ($includeDirs && $maxDepth > 0) {
            $aggr = $this->dirAgg->aggregateByDirectory($results, $maxDepth);

            $maxAvg = 0.0;
            foreach ($aggr as $m) {
                $maxAvg = max($maxAvg, $m->getAverageLines());
            }
            foreach ($aggr as $path => $m) {
                $avg = $m->getAverageLines();
                $dirs[] = [
                    'path' => $path,
                    'count' => $m->fileCount,
                    'avg_lines' => $avg,
                    'bar_ratio' => $maxAvg > 0 ? $avg / $maxAvg : 0.0,
                ];
            }
        }

        $sorted = $results;
        usort($sorted, static fn (AnalysisResult $a, AnalysisResult $b) => ((int) ($b->getMetric('lines') ?? 0)) <=> ((int) ($a->getMetric('lines') ?? 0)));
        $top = [];
        foreach (array_slice($sorted, 0, 5) as $r) {
            $lines = (int) ($r->getMetric('lines') ?? 0);
            $grade = $this->gradeFromLines($lines);
            $top[] = ['path' => $r->getRelativePath(), 'lines' => $lines, 'grade' => $grade];
        }

        return [
            'summary' => [
                'total_templates' => $totalTemplates,
                'total_lines' => $totalLines,
                'directories' => $this->countDirectories($results),
                'characters' => $this->totalCharacters($results),
                'avg_lines' => (float) ($core['avgLines'] ?? 0.0),
                'median_lines' => (float) ($core['medianLines'] ?? 0.0),
                'cv' => (float) ($detail['cv'] ?? 0.0),
                'gini' => (float) ($detail['giniIndex'] ?? 0.0),
            ],
            'distribution' => [
                '0_50' => $p0,
                '51_100' => $p1,
                '101_200' => $p2,
                '201_plus' => $p3,
            ],
            'stats' => [
                'std_dev' => (float) ($core['stdDev'] ?? 0.0),
                'p95' => (float) ($detail['p95'] ?? 0.0),
                'files_over_500' => $filesOver500,
                'orphans' => $orphanCount,
                'entropy' => (float) ($detail['entropy'] ?? 0.0),
                'dir_depth_avg' => $avgDepth,
            ],
            'directories' => $dirs,
            'top' => $top,
            'final' => [
                'score' => (float) $card->score,
                'grade' => (string) $card->grade,
            ],
        ];
    }

    /**
     * @param AnalysisResult[] $results
     */
    private function countDirectories(array $results): int
    {
        $dirs = [];
        foreach ($results as $r) {
            $path = $r->getRelativePath();
            $dir = str_contains($path, '/') ? explode('/', $path)[0] : '(root)';
            $dirs[$dir] = true;
        }

        return count($dirs);
    }

    /**
     * @param AnalysisResult[] $results
     */
    private function totalCharacters(array $results): int
    {
        $sum = 0;
        foreach ($results as $r) {
            $sum += (int) ($r->getMetric('chars') ?? 0);
        }

        return $sum;
    }

    /**
     * @param AnalysisResult[] $results
     */
    private function countOrphans(array $results): int
    {
        $count = 0;
        foreach ($results as $r) {
            $deps = $r->getMetric('dependencies') ?? [];
            if (is_array($deps) && 0 === count($deps)) {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * @param AnalysisResult[] $results
     */
    private function averageDirectoryDepth(array $results): float
    {
        $depths = [];
        foreach ($results as $r) {
            $path = $r->getRelativePath();
            $depths[] = max(0, substr_count($path, '/'));
        }

        return $this->stats->calculate($depths)->mean;
    }

    private function gradeFromLines(int $lines): string
    {
        return match (true) {
            $lines >= 1000 => 'E',
            $lines >= 800 => 'D',
            $lines >= 500 => 'C',
            $lines >= 300 => 'B',
            default => 'A',
        };
    }
}
