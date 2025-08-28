<?php

declare(strict_types=1);

namespace TwigMetrics\Renderer\Dimension\Box;

use TwigMetrics\Analyzer\AnalysisResult;
use TwigMetrics\Metric\Aggregator\DirectoryMetricsAggregator;
use TwigMetrics\Reporter\Dimension\MaintainabilityMetricsReporter;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
final class MaintainabilityDimensionPresenter implements DimensionPresenterInterface
{
    public function __construct(
        private readonly MaintainabilityMetricsReporter $reporter,
        private readonly DirectoryMetricsAggregator $aggregator,
    ) {
    }

    /**
     * @param AnalysisResult[] $results
     *
     * @return array<string, mixed>
     */
    public function present(array $results, int $maxDepth, bool $includeDirectories): array
    {
        if (empty($results)) {
            return $this->getEmptyData();
        }

        $metrics = $this->reporter->generateMetrics($results);
        $directoryMetrics = $includeDirectories ? $this->aggregator->aggregateByDirectory($results, $maxDepth) : [];

        $emptyRatio = $this->averageEmptyRatio($results);
        $commentDensity = $this->averageCommentDensity($results);

        return [
            'summary' => [
                'empty_lines_ratio' => $emptyRatio,
                'mi_avg' => $metrics->coreMetrics['mi_avg'] ?? 0.0,
                'mi_median' => $metrics->coreMetrics['mi_median'] ?? 0.0,

                'comment_density' => $commentDensity,
                'high_risk' => $metrics->detailMetrics['risk_high'] ?? 0,
                'medium_risk' => $metrics->detailMetrics['risk_medium'] ?? 0,
                'low_risk' => $metrics->detailMetrics['risk_low'] ?? 0,
            ],
            'risk_distribution' => $this->calculateRiskDistribution($results),
            'directories' => $this->calculateDirectoryRisk($directoryMetrics),
            'refactor_priorities' => $this->getRefactorPriorities($results),
            'debt_analysis' => $this->calculateDebtAnalysis($results),
            'final' => [
                'score' => (int) round($metrics->score),
                'grade' => $metrics->grade,
            ],
        ];
    }

    /**
     * @param AnalysisResult[] $results
     */
    private function averageEmptyRatio(array $results): float
    {
        $sum = 0.0;
        $n = 0;
        foreach ($results as $r) {
            $lines = (int) ($r->getMetric('lines') ?? 0);
            $blank = (int) ($r->getMetric('blank_lines') ?? 0);
            if ($lines > 0) {
                $sum += $blank / $lines;
                ++$n;
            }
        }

        return $n > 0 ? $sum / $n : 0.0;
    }

    /**
     * @param AnalysisResult[] $results
     */
    private function averageCommentDensity(array $results): float
    {
        $sum = 0.0;
        $n = 0;
        foreach ($results as $r) {
            $lines = (int) ($r->getMetric('lines') ?? 0);
            $comments = (int) ($r->getMetric('comment_lines') ?? 0);
            if ($lines > 0) {
                $sum += $comments / $lines;
                ++$n;
            }
        }

        return $n > 0 ? $sum / $n : 0.0;
    }

    /**
     * @param AnalysisResult[] $results
     *
     * @return array{critical: int, high: int, medium: int, low: int}
     */
    private function calculateRiskDistribution(array $results): array
    {
        $distribution = [
            'critical' => 0,
            'high' => 0,
            'medium' => 0,
            'low' => 0,
        ];

        foreach ($results as $result) {
            $risk = $this->calculateRiskScore($result);
            if ($risk >= 0.85) {
                ++$distribution['critical'];
            } elseif ($risk >= 0.7) {
                ++$distribution['high'];
            } elseif ($risk >= 0.35) {
                ++$distribution['medium'];
            } else {
                ++$distribution['low'];
            }
        }

        return $distribution;
    }

    /**
     * @param array<string, mixed> $directoryMetrics
     *
     * @return array<int, array{path: string, files: int, avg_complexity: float, avg_lines: float, max_depth: int,
     *                    risk: float}>
     */
    private function calculateDirectoryRisk(array $directoryMetrics): array
    {
        $directories = [];

        foreach ($directoryMetrics as $path => $metrics) {
            $avgComplexity = $metrics->getAverageComplexity();
            $avgLines = $metrics->getAverageLines();
            $avgDepth = $metrics->getAverageDepth();
            $maxDepth = (int) $avgDepth;

            $risk = $this->calculateDirectoryRisk_internal($avgComplexity, $avgLines, $maxDepth);

            $directories[] = [
                'path' => $path,
                'files' => $metrics->fileCount,
                'avg_complexity' => round($avgComplexity, 1),
                'avg_lines' => round($avgLines, 0),
                'max_depth' => $maxDepth,
                'risk' => $risk,
            ];
        }

        usort($directories, fn ($a, $b) => $b['risk'] <=> $a['risk']);

        return array_slice($directories, 0, 10);
    }

    private function calculateDirectoryRisk_internal(float $avgComplexity, float $avgLines, int $maxDepth): float
    {
        $cxRisk = min(1.0, $avgComplexity / 30.0);

        $linesRisk = min(1.0, $avgLines / 200.0);

        $depthRisk = min(1.0, $maxDepth / 8.0);

        return 0.5 * $cxRisk + 0.3 * $linesRisk + 0.2 * $depthRisk;
    }

    /**
     * @param AnalysisResult[] $results
     *
     * @return array<int, array{template: string, risk: float, complexity: int, lines: int, depth: int}>
     */
    private function getRefactorPriorities(array $results): array
    {
        $priorities = [];

        foreach ($results as $result) {
            $risk = $this->calculateRiskScore($result);

            $priorities[] = [
                'template' => $result->getRelativePath(),
                'risk' => $risk,
                'complexity' => (int) ($result->getMetric('complexity_score') ?? 0),
                'lines' => (int) ($result->getMetric('lines') ?? 0),
                'depth' => (int) ($result->getMetric('max_depth') ?? 0),
            ];
        }

        usort($priorities, fn ($a, $b) => $b['risk'] <=> $a['risk']);

        return array_slice($priorities, 0, 5);
    }

    /**
     * @param AnalysisResult[] $results
     *
     * @return array{
     *  debt_ratio: float,
     *  complex_templates: int,
     *  large_templates: int,
     *  deep_templates: int,
     *  total_lines: int
     * }
     */
    private function calculateDebtAnalysis(array $results): array
    {
        $totalLines = array_sum(array_map(fn ($r) => (int) ($r->getMetric('lines') ?? 0), $results));
        $complexTemplates = 0;
        $largeTemplates = 0;
        $deepTemplates = 0;

        foreach ($results as $result) {
            $complexity = (int) ($result->getMetric('complexity_score') ?? 0);
            $lines = (int) ($result->getMetric('lines') ?? 0);
            $depth = (int) ($result->getMetric('max_depth') ?? 0);

            if ($complexity > 20) {
                ++$complexTemplates;
            }
            if ($lines > 200) {
                ++$largeTemplates;
            }
            if ($depth > 5) {
                ++$deepTemplates;
            }
        }

        $totalTemplates = count($results);
        $debtRatio = $totalTemplates > 0 ? (($complexTemplates + $largeTemplates + $deepTemplates) / ($totalTemplates * 3)) * 100 : 0;

        return [
            'debt_ratio' => round($debtRatio, 1),
            'complex_templates' => $complexTemplates,
            'large_templates' => $largeTemplates,
            'deep_templates' => $deepTemplates,
            'total_lines' => $totalLines,
        ];
    }

    private function calculateRiskScore(AnalysisResult $result): float
    {
        $complexity = (int) ($result->getMetric('complexity_score') ?? 0);
        $lines = (int) ($result->getMetric('lines') ?? 0);
        $deps = is_array($result->getMetric('dependencies') ?? null) ? count((array) $result->getMetric('dependencies')) : 0;
        $styleConsistency = (float) ($result->getMetric('formatting_consistency_score') ?? 100.0);

        $nx = min(1.0, $complexity / 30.0);
        $nl = min(1.0, $lines / 200.0);
        $nd = min(1.0, $deps / 5.0);
        $ns = 1.0 - min(1.0, $styleConsistency / 100.0);

        return 0.4 * $nx + 0.3 * $nl + 0.2 * $nd + 0.1 * $ns;
    }

    /**
     * @return array<string, mixed>
     */
    private function getEmptyData(): array
    {
        return [
            'summary' => [
                'total_templates' => 0,
                'mi_avg' => 0.0,
                'mi_median' => 0.0,
                'refactor_candidates' => 0,
                'high_risk' => 0,
                'medium_risk' => 0,
                'low_risk' => 0,
            ],
            'risk_distribution' => [
                'critical' => 0,
                'high' => 0,
                'medium' => 0,
                'low' => 0,
            ],
            'directories' => [],
            'refactor_priorities' => [],
            'debt_analysis' => [
                'debt_ratio' => 0.0,
                'complex_templates' => 0,
                'large_templates' => 0,
                'deep_templates' => 0,
                'total_lines' => 0,
            ],
            'final' => [
                'score' => 0,
                'grade' => 'E',
            ],
        ];
    }
}
