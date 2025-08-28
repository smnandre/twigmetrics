<?php

declare(strict_types=1);

namespace TwigMetrics\Reporter\Dimension;

use TwigMetrics\Analyzer\AnalysisResult;
use TwigMetrics\Analyzer\StyleConsistencyAnalyzer;
use TwigMetrics\Calculator\StatisticalCalculator;
use TwigMetrics\Metric\DimensionMetrics;
use TwigMetrics\Reporter\Helper\DimensionGrader;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
final class CodeStyleMetricsReporter
{
    public function __construct(
        private readonly StatisticalCalculator $stats,
        private readonly StyleConsistencyAnalyzer $styleAnalyzer,
        private readonly DimensionGrader $grader = new DimensionGrader(),
    ) {
    }

    /**
     * @param AnalysisResult[] $results
     */
    public function generateMetrics(array $results): DimensionMetrics
    {
        $maxLineValues = $this->extractIntMetric($results, 'max_line_length');
        $summary = $this->stats->calculate($maxLineValues);

        $style = $this->styleAnalyzer->analyze($results);

        $violations = $this->aggregateViolations($results);
        $mixedIndentRatio = $violations['files_mixed_indent'] / max(1, count($results));
        $commentDensityAvg = $this->averageFloatMetric($results, 'comment_density');

        [$score, $grade] = $this->grader->gradeCodeStyle(
            consistency: $style->consistencyScore,
            maxLine: (int) $summary->p95,
            commentDensity: $commentDensityAvg,
            mixedIndentRatio: $mixedIndentRatio,
        );

        return new DimensionMetrics(
            name: 'Code Style',
            score: $score,
            grade: $grade,
            coreMetrics: [
                'consistency' => round($style->consistencyScore, 1),
                'readability' => round($style->readabilityScore, 1),
                'entropy' => round($style->formattingEntropy, 3),
                'p95_line_length' => (int) round($summary->p95),
            ],
            detailMetrics: [
                'long_lines_files' => $violations['files_long_lines'],
                'trailing_spaces_files' => $violations['files_trailing_spaces'],
                'mixed_indent_files' => $violations['files_mixed_indent'],
                'comment_density_avg' => round($commentDensityAvg, 2),
            ],
            distributions: [
                'line_length' => $this->bucketLineLengths($maxLineValues),
            ],
            insights: $this->makeInsights((int) round($summary->p95), $violations['files_mixed_indent']),
        );
    }

    /**
     * @param AnalysisResult[] $results
     *
     * @return array{files_long_lines:int,files_trailing_spaces:int,files_mixed_indent:int}
     */
    private function aggregateViolations(array $results): array
    {
        $long = 0;
        $trail = 0;
        $mixed = 0;
        foreach ($results as $r) {
            $maxLine = (int) ($r->getMetric('max_line_length') ?? 0);
            $trailing = (int) ($r->getMetric('trailing_spaces') ?? 0);
            $mixedLines = (int) ($r->getMetric('mixed_indentation_lines') ?? 0);
            if ($maxLine > 120) {
                ++$long;
            }
            if ($trailing > 0) {
                ++$trail;
            }
            if ($mixedLines > 0) {
                ++$mixed;
            }
        }

        return [
            'files_long_lines' => $long,
            'files_trailing_spaces' => $trail,
            'files_mixed_indent' => $mixed,
        ];
    }

    /**
     * @param list<int|float> $values
     *
     * @return array<string, array{count:int, percentage:float}>
     */
    private function bucketLineLengths(array $values): array
    {
        $buckets = [
            '<=80' => 0,
            '81-120' => 0,
            '121-160' => 0,
            '>160' => 0,
        ];
        foreach ($values as $v) {
            $n = (int) $v;
            if ($n <= 80) {
                ++$buckets['<=80'];
            } elseif ($n <= 120) {
                ++$buckets['81-120'];
            } elseif ($n <= 160) {
                ++$buckets['121-160'];
            } else {
                ++$buckets['>160'];
            }
        }
        $total = max(1, count($values));
        $out = [];
        foreach ($buckets as $k => $c) {
            $out[$k] = ['count' => $c, 'percentage' => ($c / $total) * 100.0];
        }

        return $out;
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
     * @param AnalysisResult[] $results
     */
    private function averageFloatMetric(array $results, string $name): float
    {
        $sum = 0.0;
        $n = 0;
        foreach ($results as $r) {
            $val = $r->getMetric($name);
            if (is_numeric($val)) {
                $sum += (float) $val;
                ++$n;
            }
        }

        return $n > 0 ? $sum / $n : 0.0;
    }

    /**
     * @return list<string>
     */
    private function makeInsights(int $p95, int $mixedIndentFiles): array
    {
        $insights = [];
        if ($p95 > 120) {
            $insights[] = sprintf('High p95 line length (%d)', $p95);
        }
        if ($mixedIndentFiles > 0) {
            $insights[] = sprintf('Mixed indentation in %d file(s)', $mixedIndentFiles);
        }

        return $insights;
    }
}
