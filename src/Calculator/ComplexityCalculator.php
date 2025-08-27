<?php

declare(strict_types=1);

namespace TwigMetrics\Calculator;

use TwigMetrics\Analyzer\AnalysisResult;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
final class ComplexityCalculator
{
    public function calculateLogicRatio(AnalysisResult $result): float
    {
        $ifCount = (int) ($result->getMetric('conditions') ?? 0);
        $loopCount = (int) ($result->getMetric('loops') ?? 0);

        $whileCount = (int) ($result->getMetric('whileCount') ?? 0);
        $switchCount = (int) ($result->getMetric('switchCount') ?? 0);

        $logicNodes = $ifCount + $loopCount + $whileCount + $switchCount;
        $totalLines = (int) ($result->getMetric('lines') ?? 1);
        $blank = (int) ($result->getMetric('blank_lines') ?? 0);
        $comment = (int) ($result->getMetric('comment_lines') ?? 0);
        $codeLines = max(1, $totalLines - $blank - $comment);

        return $logicNodes / max(1, $codeLines);
    }

    public function calculateDecisionDensity(AnalysisResult $result): float
    {
        $decisions = (int) ($result->getMetric('complexity_score') ?? 0);
        $lines = (int) ($result->getMetric('lines') ?? 1);

        return $decisions / max(1, $lines);
    }

    public function calculateMaintainabilityIndex(AnalysisResult $result): float
    {
        $volume = (float) ($result->getMetric('total_line_length') ?? 0.0);
        if ($volume <= 0) {
            $volume = 1.0;
        }
        $complexity = (int) ($result->getMetric('complexity_score') ?? 0);
        $lines = (int) ($result->getMetric('lines') ?? 1);

        $mi = 171 - 5.2 * log($volume) - 0.23 * $complexity - 16.2 * log(max(1, $lines));

        return max(0.0, (float) $mi);
    }
}
