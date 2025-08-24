<?php

declare(strict_types=1);

namespace TwigMetrics\Analyzer;

use TwigMetrics\Config\AnalysisConstants;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
final class TemplateRating
{
    public static function calculateSizeRating(int $lines): string
    {
        return match (true) {
            $lines < AnalysisConstants::SIZE_RATING_A_THRESHOLD => 'A',
            $lines < AnalysisConstants::SIZE_RATING_B_THRESHOLD => 'B',
            $lines < AnalysisConstants::SIZE_RATING_C_THRESHOLD => 'C',
            default => 'D',
        };
    }

    public static function calculateComplexityRating(int $complexity, int $depth): string
    {
        return match (true) {
            $complexity < AnalysisConstants::COMPLEXITY_RATING_A_SCORE && $depth < AnalysisConstants::COMPLEXITY_RATING_A_DEPTH => 'A',
            $complexity < AnalysisConstants::COMPLEXITY_RATING_B_SCORE && $depth < AnalysisConstants::COMPLEXITY_RATING_B_DEPTH => 'B',
            $complexity < AnalysisConstants::COMPLEXITY_RATING_C_SCORE && $depth < AnalysisConstants::COMPLEXITY_RATING_C_DEPTH => 'C',
            default => 'D',
        };
    }

    /**
     * @param array<string, int> $metrics
     */
    public static function calculateCallablesRating(array $metrics): string
    {
        $score = AnalysisConstants::DEFAULT_QUALITY_SCORE;

        $uniqueFunctions = $metrics['unique_functions'] ?? 0;
        if ($uniqueFunctions > AnalysisConstants::CALLABLES_FUNCTIONS_HIGH_THRESHOLD) {
            $score -= AnalysisConstants::CALLABLES_FUNCTIONS_HIGH_PENALTY;
        } elseif ($uniqueFunctions > AnalysisConstants::CALLABLES_FUNCTIONS_MEDIUM_THRESHOLD) {
            $score -= AnalysisConstants::CALLABLES_FUNCTIONS_MEDIUM_PENALTY;
        }

        $uniqueVariables = $metrics['unique_variables'] ?? 0;
        if ($uniqueVariables > AnalysisConstants::CALLABLES_VARIABLES_HIGH_THRESHOLD) {
            $score -= AnalysisConstants::CALLABLES_VARIABLES_HIGH_PENALTY;
        } elseif ($uniqueVariables > AnalysisConstants::CALLABLES_VARIABLES_MEDIUM_THRESHOLD) {
            $score -= AnalysisConstants::CALLABLES_VARIABLES_MEDIUM_PENALTY;
        }

        $macroDefinitions = $metrics['macro_definitions'] ?? 0;
        $macroCalls = $metrics['macro_calls'] ?? 0;
        if ($macroDefinitions > 0 && $macroCalls > $macroDefinitions) {
            $score += AnalysisConstants::CALLABLES_MACRO_REUSE_BONUS;
        } elseif ($macroDefinitions > 0 && $macroCalls < $macroDefinitions) {
            $score -= AnalysisConstants::CALLABLES_MACRO_REUSE_PENALTY;
        }

        $uniqueFilters = $metrics['unique_filters'] ?? 0;
        if ($uniqueFilters > AnalysisConstants::CALLABLES_FILTERS_HIGH_THRESHOLD) {
            $score -= AnalysisConstants::CALLABLES_FILTERS_HIGH_PENALTY;
        } elseif ($uniqueFilters > AnalysisConstants::CALLABLES_FILTERS_MEDIUM_THRESHOLD) {
            $score -= AnalysisConstants::CALLABLES_FILTERS_MEDIUM_PENALTY;
        }

        return match (true) {
            $score >= AnalysisConstants::CALLABLES_RATING_A_THRESHOLD => 'A',
            $score >= AnalysisConstants::CALLABLES_RATING_B_THRESHOLD => 'B',
            $score >= AnalysisConstants::CALLABLES_RATING_C_THRESHOLD => 'C',
            default => 'D',
        };
    }
}
