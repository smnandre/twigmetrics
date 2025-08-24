<?php

declare(strict_types=1);

namespace TwigMetrics\Reporter\Dimension;

use TwigMetrics\Analyzer\AnalysisResult;
use TwigMetrics\Config\AnalysisConstants;
use TwigMetrics\Report\Report;
use TwigMetrics\Report\Section\ChartSection;
use TwigMetrics\Report\Section\TableSection;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
final class ArchitectureReporter extends DimensionReporter
{
    public function getWeight(): float
    {
        return AnalysisConstants::DIMENSION_WEIGHT_ARCHITECTURE;
    }

    public function getDimensionName(): string
    {
        return 'Architecture';
    }

    /**
     * @param AnalysisResult[] $results
     */
    public function generate(array $results): Report
    {
        $report = new Report('Template Architecture Analysis');

        $report->addSection($this->createRoleDistribution($results));
        $report->addSection($this->createReusabilityAnalysis($results));
        $report->addSection($this->createBlockUsagePatterns($results));

        return $report;
    }

    public function calculateDimensionScore(array $results): float
    {
        $architecture = $this->analyzeArchitecture($results);

        $score = AnalysisConstants::DEFAULT_QUALITY_SCORE;

        $deviation = $this->calculateRoleDeviation($architecture['role_distribution']);
        $score -= $deviation * AnalysisConstants::ARCHITECTURE_ROLE_DEVIATION_PENALTY;

        if ($architecture['avg_reusability'] < AnalysisConstants::ARCHITECTURE_LOW_REUSABILITY_THRESHOLD) {
            $score -= AnalysisConstants::ARCHITECTURE_LOW_REUSABILITY_PENALTY;
        } elseif ($architecture['avg_reusability'] < AnalysisConstants::ARCHITECTURE_MEDIUM_REUSABILITY_THRESHOLD) {
            $score -= AnalysisConstants::ARCHITECTURE_MEDIUM_REUSABILITY_PENALTY;
        } elseif ($architecture['avg_reusability'] < AnalysisConstants::ARCHITECTURE_HIGH_REUSABILITY_THRESHOLD) {
            $score -= AnalysisConstants::ARCHITECTURE_HIGH_REUSABILITY_PENALTY;
        }

        return max(0, $score);
    }

    /**
     * @param AnalysisResult[] $results
     *
     * @return array<string, mixed>
     */
    private function analyzeArchitecture(array $results): array
    {
        $roles = $this->classifyTemplateRoles($results);
        $reusability = $this->calculateReusability($results);

        return [
            'role_distribution' => $roles,
            'avg_reusability' => count($reusability) > 0 ? array_sum($reusability) / count($reusability) : 0,
        ];
    }

    /**
     * @param AnalysisResult[] $results
     *
     * @return array<string, int>
     */
    private function classifyTemplateRoles(array $results): array
    {
        $roles = ['components' => 0, 'pages' => 0, 'layouts' => 0, 'other' => 0];

        foreach ($results as $result) {
            $path = $result->getRelativePath();
            if (str_contains($path, 'component')) {
                ++$roles['components'];
            } elseif (str_contains($path, 'page') || str_contains($path, 'view')) {
                ++$roles['pages'];
            } elseif (str_contains($path, 'layout') || str_contains($path, 'base')) {
                ++$roles['layouts'];
            } else {
                ++$roles['other'];
            }
        }

        return $roles;
    }

    /**
     * @param AnalysisResult[] $results
     *
     * @return float[]
     */
    private function calculateReusability(array $results): array
    {
        $reusability = [];

        foreach ($results as $result) {
            $blocks = $result->getMetric('blocks_detail') ?? [];
            $macros = $result->getMetric('macro_definitions_detail') ?? [];
            $complexity = $result->getMetric('complexity_score') ?? 0;

            if (!is_array($blocks)) {
                $blocks = [];
            }
            if (!is_array($macros)) {
                $macros = [];
            }

            $reusabilityScore = (count($blocks) * AnalysisConstants::ARCHITECTURE_BLOCK_WEIGHT + count($macros) * AnalysisConstants::ARCHITECTURE_MACRO_WEIGHT) / max(1, $complexity * AnalysisConstants::ARCHITECTURE_COMPLEXITY_DIVISOR);
            $reusability[] = min(1.0, $reusabilityScore);
        }

        return $reusability;
    }

    /**
     * @param array<string, float> $distribution
     */
    private function calculateRoleDeviation(array $distribution): float
    {
        $total = array_sum($distribution);
        if (0 === $total) {
            return 1.0;
        }

        $actual = [
            'components' => $distribution['components'] / $total,
            'pages' => $distribution['pages'] / $total,
            'layouts' => $distribution['layouts'] / $total,
        ];

        $ideal = ['components' => AnalysisConstants::ARCHITECTURE_IDEAL_COMPONENTS_RATIO, 'pages' => AnalysisConstants::ARCHITECTURE_IDEAL_PAGES_RATIO, 'layouts' => AnalysisConstants::ARCHITECTURE_IDEAL_LAYOUTS_RATIO];

        $deviation = 0;
        foreach ($ideal as $role => $idealRatio) {
            $deviation += abs($actual[$role] - $idealRatio);
        }

        return $deviation / 3;
    }

    /**
     * @param AnalysisResult[] $results
     */
    private function createRoleDistribution(array $results): ChartSection
    {
        $roles = $this->classifyTemplateRoles($results);

        return new ChartSection(
            'Template Role Distribution',
            'pie',
            [
                'labels' => ['Components', 'Pages', 'Layouts', 'Other'],
                'values' => array_values($roles),
            ]
        );
    }

    /**
     * @param AnalysisResult[] $results
     */
    private function createReusabilityAnalysis(array $results): TableSection
    {
        $reusabilityScores = [];

        foreach ($results as $result) {
            $blocks = $result->getMetric('blocks_detail') ?? [];
            $macros = $result->getMetric('macro_definitions_detail') ?? [];
            $complexity = $result->getMetric('complexity_score') ?? 0;

            if (!is_array($blocks)) {
                $blocks = [];
            }
            if (!is_array($macros)) {
                $macros = [];
            }

            $reusabilityScore = (count($blocks) * AnalysisConstants::ARCHITECTURE_BLOCK_WEIGHT + count($macros) * AnalysisConstants::ARCHITECTURE_MACRO_WEIGHT) / max(1, $complexity * AnalysisConstants::ARCHITECTURE_COMPLEXITY_DIVISOR);
            $reusabilityScores[] = [
                basename($result->getRelativePath()),
                count($blocks),
                count($macros),
                sprintf('%.2f', min(1.0, $reusabilityScore)),
                min(1.0, $reusabilityScore) > AnalysisConstants::ARCHITECTURE_HIGH_REUSABILITY_RATING ? 'High' : (min(1.0, $reusabilityScore) > AnalysisConstants::ARCHITECTURE_MEDIUM_REUSABILITY_RATING ? 'Medium' : 'Low'),
            ];
        }

        usort($reusabilityScores, fn ($a, $b) => $b[3] <=> $a[3]);

        return new TableSection(
            'Template Reusability Analysis',
            ['Template', 'Blocks', 'Macros', 'Reusability Score', 'Level'],
            array_slice($reusabilityScores, 0, 15)
        );
    }

    /**
     * @param AnalysisResult[] $results
     */
    private function createBlockUsagePatterns(array $results): TableSection
    {
        $blockUsage = [];

        foreach ($results as $result) {
            $blocks = $result->getMetric('blocks_detail') ?? [];
            if (is_array($blocks)) {
                foreach ($blocks as $block) {
                    $blockUsage[$block] = ($blockUsage[$block] ?? 0) + 1;
                }
            }
        }

        arsort($blockUsage);
        $topBlocks = array_slice($blockUsage, 0, 15, true);

        return $this->createCountDistributionTable(
            $topBlocks,
            'Most Used Blocks',
            ['Block Name', 'Usage Count', 'Usage %'],
            count($results)
        );
    }
}
