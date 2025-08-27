<?php

declare(strict_types=1);

namespace TwigMetrics\Analyzer;

use TwigMetrics\Metric\StyleMetrics;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
final class StyleConsistencyAnalyzer
{
    /**
     * @param AnalysisResult[] $results
     */
    public function analyze(array $results): StyleMetrics
    {
        $violations = [
            'longLines' => [],
            'trailingSpaces' => [],
            'mixedIndent' => [],
        ];
        $consistencyScores = [];

        foreach ($results as $result) {
            $maxLine = (int) ($result->getMetric('max_line_length') ?? 0);
            if ($maxLine > 120) {
                $violations['longLines'][] = $result->getRelativePath();
            }

            $trailing = (int) ($result->getMetric('trailing_spaces') ?? 0);
            if ($trailing > 0) {
                $violations['trailingSpaces'][] = $result->getRelativePath();
            }

            $mixed = (int) ($result->getMetric('mixed_indentation_lines') ?? 0);
            if ($mixed > 0) {
                $violations['mixedIndent'][] = $result->getRelativePath();
            }

            $consistencyScores[] = $this->calculateFileConsistency($result);
        }

        $avgConsistency = !empty($consistencyScores) ? array_sum($consistencyScores) / count($consistencyScores) : 100.0;

        return new StyleMetrics(
            violations: $violations,
            consistencyScore: $avgConsistency,
            formattingEntropy: $this->calculateFormattingEntropy($results),
            readabilityScore: $this->calculateReadabilityScore($results),
        );
    }

    private function calculateFileConsistency(AnalysisResult $result): float
    {
        $score = 100.0;
        $lines = max(1, (int) ($result->getMetric('lines') ?? 1));

        $mixed = (int) ($result->getMetric('mixed_indentation_lines') ?? 0);
        $score -= min(15.0, ($mixed / $lines) * 30.0);

        $trailing = (int) ($result->getMetric('trailing_spaces') ?? 0);
        $score -= min(10.0, ($trailing / $lines) * 20.0);

        $maxLen = (int) ($result->getMetric('max_line_length') ?? 0);
        if ($maxLen > 120) {
            $score -= min(10.0, ($maxLen - 120) / 10.0);
        }

        $commentDensity = (float) ($result->getMetric('comment_density') ?? 0.0);
        if ($commentDensity < 5 || $commentDensity > 50) {
            $score -= 5.0;
        }

        return max(0.0, round($score, 1));
    }

    /**
     * @param AnalysisResult[] $results
     */
    private function calculateFormattingEntropy(array $results): float
    {
        $total = 0;
        $anomaly = 0;
        foreach ($results as $r) {
            $lines = (int) ($r->getMetric('lines') ?? 0);
            $total += $lines;
            $anomaly += (int) ($r->getMetric('mixed_indentation_lines') ?? 0);
            $anomaly += (int) ($r->getMetric('trailing_spaces') ?? 0);
        }
        if (0 === $total) {
            return 0.0;
        }
        $p = min(1.0, $anomaly / max(1, $total));

        return -($p > 0 ? ($p * log($p, 2)) : 0.0);
    }

    /**
     * @param AnalysisResult[] $results
     */
    private function calculateReadabilityScore(array $results): float
    {
        $scores = [];
        foreach ($results as $r) {
            $avgLen = (float) ($r->getMetric('avg_line_length') ?? 0.0);
            $blank = (int) ($r->getMetric('blank_lines') ?? 0);
            $lines = max(1, (int) ($r->getMetric('lines') ?? 1));
            $spacing = min(1.0, $blank / $lines);
            $penalty = max(0.0, ($avgLen - 80) / 80.0);
            $scores[] = max(0.0, 1.0 - $penalty) * (0.5 + 0.5 * $spacing) * 100.0;
        }

        return !empty($scores) ? array_sum($scores) / count($scores) : 100.0;
    }
}
