<?php

declare(strict_types=1);

namespace TwigMetrics\Reporter;

use TwigMetrics\Analyzer\AnalysisResult;
use TwigMetrics\Report\Report;
use TwigMetrics\Report\Section\ReportSection;
use TwigMetrics\Report\Section\TableSection;
use TwigMetrics\Reporter\Dimension\ArchitectureReporter;
use TwigMetrics\Reporter\Dimension\CodeStyleReporter;
use TwigMetrics\Reporter\Dimension\DimensionReporter;
use TwigMetrics\Reporter\Dimension\LogicalComplexityReporter;
use TwigMetrics\Reporter\Dimension\MaintainabilityReporter;
use TwigMetrics\Reporter\Dimension\TemplateFilesReporter;
use TwigMetrics\Reporter\Dimension\TwigCallablesReporter;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
final class QualityReporter implements ReporterInterface
{
    /**
     * @var DimensionReporter[]
     */
    private array $dimensionReporters;

    public function __construct()
    {
        $this->dimensionReporters = [
            new TemplateFilesReporter(),
            new LogicalComplexityReporter(),

            new TwigCallablesReporter(),
            new CodeStyleReporter(),
            new ArchitectureReporter(),
            new MaintainabilityReporter(),
        ];
    }

    public function generate(array $results): Report
    {
        $report = new Report('TwigMetrics Comprehensive Analysis');

        foreach ($this->dimensionReporters as $dimensionReporter) {
            $dimensionReport = $dimensionReporter->generate($results);

            $score = $dimensionReporter->calculateDimensionScore($results);
            $report->addSection($this->createDimensionHeader($dimensionReporter, $score));

            foreach ($dimensionReport->getSections() as $section) {
                $report->addSection($section);
            }
        }

        $report->addSection($this->createVisualDimensionRecap($results));

        $report->addSection($this->createWeightedHealthSummary($results));

        return $report;
    }

    public function getWeight(): float
    {
        return 1.0;
    }

    public function getDimensionName(): string
    {
        return 'Overall Health';
    }

    /**
     * @param AnalysisResult[] $results
     */
    public function calculateOverallHealthScore(array $results): float
    {
        $totalScore = 0;
        $totalWeight = 0;

        foreach ($this->dimensionReporters as $reporter) {
            $dimensionScore = $reporter->calculateDimensionScore($results);
            $weight = $reporter->getWeight();

            $totalScore += ($dimensionScore * $weight);
            $totalWeight += $weight;
        }

        return $totalWeight > 0 ? $totalScore / $totalWeight : 0;
    }

    /**
     * @param AnalysisResult[] $results
     */
    private function createWeightedHealthSummary(array $results): TableSection
    {
        $overallScore = $this->calculateOverallHealthScore($results);
        $scoreIcon = $this->getHealthScoreIcon($overallScore);
        $scoreDescription = $this->getHealthScoreDescription($overallScore);

        return new TableSection(
            'Final Project Health Score',
            ['Metric', 'Score', 'Status'],
            [
                ['Overall Health', sprintf('%.1f/100', $overallScore), $scoreIcon],
                ['Assessment', $scoreDescription, ''],
                ['Templates Analyzed', count($results), ''],
            ]
        );
    }

    /**
     * @param AnalysisResult[] $results
     */
    private function createVisualDimensionRecap(array $results): ReportSection
    {
        $dimensionScores = [];
        foreach ($this->dimensionReporters as $reporter) {
            $score = $reporter->calculateDimensionScore($results);
            $data = ['score' => (int) round($score)];

            $name = $reporter->getDimensionName();
            if ('Logical Complexity' === $name) {
                $complexities = [];
                $depthMax = 0;
                foreach ($results as $r) {
                    $complexityMetric = $r->getMetric('complexity_score');
                    $c = is_numeric($complexityMetric) ? (float) $complexityMetric : 0.0;
                    $complexities[] = $c;
                    $depthMetric = $r->getMetric('max_depth');
                    $depthMax = max($depthMax, is_numeric($depthMetric) ? (int) $depthMetric : 0);
                }
                $count = count($complexities);
                $avg = $count > 0 ? array_sum($complexities) / $count : 0.0;
                $critCount = 0;
                foreach ($complexities as $c) {
                    if ($c > 20) {
                        ++$critCount;
                    }
                }
                $pctCrit = $count > 0 ? ($critCount / $count) * 100 : 0.0;
                $data['mini'] = sprintf('avg %.1f • crit %.0f%% • depth %d', $avg, $pctCrit, $depthMax);
            } elseif ('Template Files' === $name) {
                $lines = [];
                foreach ($results as $r) {
                    $linesMetric = $r->getMetric('lines');
                    $lines[] = is_numeric($linesMetric) ? (int) $linesMetric : 0;
                }
                $count = count($lines);
                $avg = $count > 0 ? array_sum($lines) / $count : 0.0;
                $max = !empty($lines) ? max($lines) : 0;
                $data['mini'] = sprintf('%d files • avg %.1f • max %d', $count, $avg, $max);
            }

            $dimensionScores[$reporter->getDimensionName()] = $data;
        }

        return new ReportSection(
            'Twig Metrics',
            'dimension_visual_recap',
            ['dimensions' => $dimensionScores]
        );
    }

    private function createDimensionHeader(DimensionReporter $reporter, float $score): ReportSection
    {
        $headerData = [
            'dimension_name' => $reporter->getDimensionName(),
            'score' => $score,
            'weight' => $reporter->getWeight(),
            'status' => $this->getScoreIcon($score),
        ];

        return new ReportSection(
            sprintf('%s Analysis (%.1f/100 %s)',
                $reporter->getDimensionName(),
                $score,
                $this->getScoreIcon($score)
            ),
            'dimension_header',
            $headerData
        );
    }

    private function getHealthScoreIcon(float $score): string
    {
        return match (true) {
            $score >= 90 => '🟢 Exceptional',
            $score >= 80 => '🟡 Good',
            $score >= 70 => '🟠 Fair',
            $score >= 60 => '🔴 Poor',
            default => '⚫ Critical',
        };
    }

    private function getHealthScoreDescription(float $score): string
    {
        return match (true) {
            $score >= 90 => 'Excellent codebase with minimal issues',
            $score >= 80 => 'Good foundation with room for improvement',
            $score >= 70 => 'Fair quality with several areas needing attention',
            $score >= 60 => 'Poor quality requiring significant improvements',
            default => 'Critical issues requiring immediate attention',
        };
    }

    private function getScoreIcon(float $score): string
    {
        return match (true) {
            $score >= 90 => '🟢',
            $score >= 80 => '🟡',
            $score >= 70 => '🟠',
            $score >= 60 => '🔴',
            default => '⚫',
        };
    }
}
