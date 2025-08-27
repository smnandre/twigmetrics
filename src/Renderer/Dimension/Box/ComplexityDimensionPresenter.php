<?php

declare(strict_types=1);

namespace TwigMetrics\Renderer\Dimension\Box;

use TwigMetrics\Analyzer\AnalysisResult;
use TwigMetrics\Calculator\StatisticalCalculator;
use TwigMetrics\Detector\ComplexityHotspotDetector;
use TwigMetrics\Metric\Aggregator\DirectoryMetricsAggregator;
use TwigMetrics\Reporter\Dimension\LogicalComplexityMetricsReporter;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
final class ComplexityDimensionPresenter implements DimensionPresenterInterface
{
    public function __construct(
        private readonly LogicalComplexityMetricsReporter $reporter,
        private readonly DirectoryMetricsAggregator $dirAgg,
        private readonly ComplexityHotspotDetector $hotspots,
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
        $dist = $card->distributions['heatmap'] ?? ['simple' => 0, 'moderate' => 0, 'complex' => 0, 'critical' => 0];

        $totalFiles = max(1, count($results));
        $simplePct = (int) round((($dist['simple'] ?? 0) / $totalFiles) * 100);
        $moderatePct = (int) round((($dist['moderate'] ?? 0) / $totalFiles) * 100);
        $complexPct = (int) round((($dist['complex'] ?? 0) / $totalFiles) * 100);
        $criticalPct = max(0, 100 - ($simplePct + $moderatePct + $complexPct));

        $depths = array_map(static fn (AnalysisResult $r): int => (int) ($r->getMetric('max_depth') ?? 0), $results);
        $avgDepth = $this->stats->calculate($depths)->mean;
        $maxDepthObs = !empty($depths) ? max($depths) : 0;

        $dirs = [];
        if ($includeDirs && $maxDepth > 0) {
            $aggr = $this->dirAgg->aggregateByDirectory($results, $maxDepth);
            foreach ($aggr as $path => $m) {
                $avgCx = $m->getAverageComplexity();
                $dirs[] = [
                    'path' => $path,
                    'avg_cx' => $avgCx,
                    'max_cx' => $m->maxComplexity,
                    'avg_depth' => $m->getAverageDepth(),
                    'risk' => $this->riskLabel($avgCx),
                ];
            }
        }

        $hot = $this->hotspots->detectHotspots($results, 5);
        $top = array_map(function (array $row): array {
            $score = (int) $row['complexity'];

            return [
                'path' => $row['file'],
                'score' => $score,
                'grade' => $this->gradeFromComplexity($score),
            ];
        }, $hot);

        $ifs = 0;
        $fors = 0;
        foreach ($results as $r) {
            $ifs += (int) ($r->getMetric('ifs') ?? 0);
            $fors += (int) ($r->getMetric('fors') ?? 0);
        }
        $totFiles = max(1, count($results));
        $ifsPerTpl = $ifs / $totFiles;
        $forsPerTpl = $fors / $totFiles;

        return [
            'summary' => [
                'avg' => (float) ($core['avg'] ?? 0.0),
                'median' => (float) ($core['median'] ?? 0.0),
                'max' => (int) ($core['max'] ?? 0),
                'critical_files' => (int) ($core['critical_files'] ?? 0),

                'ifs_per_template' => (float) $ifsPerTpl,
                'fors_per_template' => (float) $forsPerTpl,
                'avg_depth' => (float) $avgDepth,
                'max_depth' => (int) $maxDepthObs,
            ],
            'distribution' => [
                'simple_pct' => $simplePct,
                'moderate_pct' => $moderatePct,
                'complex_pct' => $complexPct,
                'critical_pct' => $criticalPct,
            ],
            'stats' => [
                'mi_avg' => (float) ($detail['mi_avg'] ?? 0.0),
                'cyclomatic_per_loc' => (float) ($detail['decision_density'] ?? 0.0),
                'control_flow_nodes' => 'N/A',
                'logical_operators' => 'N/A',
                'cognitive_complexity' => 'N/A',
                'halstead_volume' => 'N/A',
            ],
            'directories' => $dirs,
            'top' => $top,
            'final' => [
                'score' => (float) $card->score,
                'grade' => (string) $card->grade,
            ],
        ];
    }

    private function riskLabel(float $avgCx): string
    {
        return match (true) {
            $avgCx > 20 => 'critical',
            $avgCx > 10 => 'high',
            $avgCx > 5 => 'moderate',
            default => 'low',
        };
    }

    private function gradeFromComplexity(int $score): string
    {
        return match (true) {
            $score <= 8 => 'A',
            $score <= 15 => 'B',
            $score <= 25 => 'C',
            default => 'D',
        };
    }
}
