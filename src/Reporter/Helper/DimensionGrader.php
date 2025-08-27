<?php

declare(strict_types=1);

namespace TwigMetrics\Reporter\Helper;

use TwigMetrics\Metric\StatisticalSummary;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
final class DimensionGrader
{
    /**
     * Grade template files based on statistical measures and size.
     *
     * @param StatisticalSummary $stats        statistical summary of template file sizes
     * @param float              $dirDominance proportion of files in the largest directory
     * @param int                $maxLines     maximum number of lines in a single template file
     *
     * @return array{float, string} tuple containing the score and grade (A, B, C, D)
     */
    public function gradeTemplateFiles(StatisticalSummary $stats, float $dirDominance, int $maxLines): array
    {
        $cv = $stats->coefficientOfVariation;
        $gini = $stats->giniIndex;

        $grade = 'D';
        $score = 60.0;

        if ($cv < 0.6 && $gini < 0.35 && $maxLines < 200 && $dirDominance < 0.45) {
            $grade = 'A';
            $score = 95.0;
        } elseif ($cv < 0.9 && $gini < 0.50 && $maxLines < 300 && $dirDominance < 0.55) {
            $grade = 'B';
            $score = 85.0;
        } elseif ($cv < 1.2 && $gini < 0.65 && $maxLines < 400 && $dirDominance < 0.65) {
            $grade = 'C';
            $score = 75.0;
        }

        return [$score, $grade];
    }

    /**
     * Grade logical complexity based on average and maximum complexity, critical ratio, and logic ratio.
     *
     * @param float $avg           average complexity score across all templates
     * @param int   $max           maximum complexity score found in any single template
     * @param float $criticalRatio proportion of templates with complexity > 20
     * @param float $logicRatio    proportion of lines that are logical (conditions + loops) vs total lines
     *
     * @return array{float, string} tuple containing the score and grade (A, B, C, D)
     */
    public function gradeComplexity(float $avg, int $max, float $criticalRatio, float $logicRatio): array
    {
        $grade = 'D';
        $score = 60.0;

        if ($avg < 10 && $max < 40 && $criticalRatio <= 0.02 && $logicRatio < 0.20) {
            $grade = 'A';
            $score = 95.0;
        } elseif ($avg < 15 && $max < 80 && $criticalRatio < 0.08 && $logicRatio < 0.30) {
            $grade = 'B';
            $score = 85.0;
        } elseif ($avg < 25 && $max < 120 && $criticalRatio < 0.15 && $logicRatio < 0.40) {
            $grade = 'C';
            $score = 75.0;
        }

        return [$score, $grade];
    }

    /**
     * Grade code style based on consistency, line length, comment density, and mixed indentation ratio.
     *
     * @param float $consistency      percentage of files following the dominant style
     * @param int   $maxLine          maximum line length found in any template
     * @param float $commentDensity   average percentage of comment lines vs total lines
     * @param float $mixedIndentRatio proportion of lines with mixed indentation vs total lines
     *
     * @return array{float, string} tuple containing the score and grade (A, B, C, D)
     */
    public function gradeCodeStyle(float $consistency, int $maxLine, float $commentDensity, float $mixedIndentRatio): array
    {
        $grade = 'D';
        $score = 60.0;

        if ($consistency > 95 && $maxLine < 150 && $commentDensity >= 5 && $commentDensity <= 25 && 0.0 === $mixedIndentRatio) {
            $grade = 'A';
            $score = 95.0;
        } elseif ($consistency > 85 && $maxLine < 200 && $commentDensity >= 2 && $commentDensity <= 30 && $mixedIndentRatio < 0.05) {
            $grade = 'B';
            $score = 85.0;
        } elseif ($consistency > 75 && $maxLine < 250 && $commentDensity >= 1 && $commentDensity <= 35 && $mixedIndentRatio < 0.10) {
            $grade = 'C';
            $score = 75.0;
        }

        return [$score, $grade];
    }

    /**
     * Grade callables based on security score, diversity index, deprecated count, and debug calls.
     *
     * @return array{float, string} tuple containing the score and grade (A, B, C, D)
     */
    public function gradeCallables(float $securityScore, float $diversityIndex, int $deprecatedCount, int $debugCalls): array
    {
        $grade = 'D';
        $score = 60.0;

        if (0 === $debugCalls && $securityScore > 95 && $diversityIndex > 0.7 && 0 === $deprecatedCount) {
            $grade = 'A';
            $score = 95.0;
        } elseif ($debugCalls < 5 && $securityScore > 85 && $diversityIndex > 0.5 && $deprecatedCount < 5) {
            $grade = 'B';
            $score = 85.0;
        } elseif ($debugCalls < 20 && $securityScore > 70 && $diversityIndex > 0.3 && $deprecatedCount < 10) {
            $grade = 'C';
            $score = 75.0;
        }

        return [$score, $grade];
    }

    /**
     * Grade architecture based on components ratio, orphan ratio, circular references, and max depth.
     *
     * @param float $componentsRatio proportion of templates that are part of a component
     * @param float $orphanRatio     proportion of templates not included by any other template
     * @param int   $circularRefs    number of circular references detected
     * @param int   $maxDepth        maximum inclusion depth found in any template
     *
     * @return array{float, string} tuple containing the score and grade (A, B, C, D)
     */
    public function gradeArchitecture(float $componentsRatio, float $orphanRatio, int $circularRefs, int $maxDepth): array
    {
        $grade = 'D';
        $score = 60.0;

        if ($componentsRatio > 0.40 && $orphanRatio < 0.30 && 0 === $circularRefs && $maxDepth < 5) {
            $grade = 'A';
            $score = 95.0;
        } elseif ($componentsRatio > 0.30 && $orphanRatio < 0.50 && $circularRefs < 2 && $maxDepth < 6) {
            $grade = 'B';
            $score = 85.0;
        } elseif ($componentsRatio > 0.20 && $orphanRatio < 0.70 && $circularRefs < 5 && $maxDepth < 8) {
            $grade = 'C';
            $score = 75.0;
        }

        return [$score, $grade];
    }

    /**
     * Grade maintainability based on maintainability index average.
     *
     * @param float $miAverage average maintainability index across all templates
     *
     * @return array{float, string} tuple containing the score and grade (A, B, C, D)
     */
    public function gradeMaintainability(float $miAverage): array
    {
        $grade = 'D';
        $score = 60.0;

        if ($miAverage > 85) {
            $grade = 'A';
            $score = 95.0;
        } elseif ($miAverage > 70) {
            $grade = 'B';
            $score = 85.0;
        } elseif ($miAverage > 55) {
            $grade = 'C';
            $score = 75.0;
        }

        return [$score, $grade];
    }
}
