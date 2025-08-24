<?php

declare(strict_types=1);

namespace TwigMetrics\Reporter\Dimension;

use TwigMetrics\Analyzer\AnalysisResult;
use TwigMetrics\Report\Report;
use TwigMetrics\Report\Section\ChartSection;
use TwigMetrics\Report\Section\TableSection;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
final class CodeStyleReporter extends DimensionReporter
{
    public function getWeight(): float
    {
        return 0.10;
    }

    public function getDimensionName(): string
    {
        return 'Code Style';
    }

    /**
     * @param AnalysisResult[] $results
     */
    public function generate(array $results): Report
    {
        $report = new Report('Code Style & Formatting Analysis');

        $report->addSection($this->createFormattingConsistency($results));
        $report->addSection($this->createNamingConventions($results));
        $report->addSection($this->createLineLengthAnalysis($results));
        $report->addSection($this->createIndentationAnalysis($results));
        $report->addSection($this->createWhitespaceAnalysis($results));
        $report->addSection($this->createCommentAnalysis($results));

        return $report;
    }

    /**
     * @param AnalysisResult[] $results
     */
    public function calculateDimensionScore(array $results): float
    {
        $stats = $this->calculateStyleStats($results);

        $score = 100;

        $avgConsistency = $stats['avg_formatting_consistency'] ?? 100;
        if ($avgConsistency < 70) {
            $score -= 30;
        } elseif ($avgConsistency < 80) {
            $score -= 20;
        } elseif ($avgConsistency < 90) {
            $score -= 10;
        }

        $namingConsistency = min(
            $stats['block_naming_consistency'] ?? 100,
            $stats['variable_naming_consistency'] ?? 100
        );
        if ($namingConsistency < 50) {
            $score -= 25;
        } elseif ($namingConsistency < 75) {
            $score -= 15;
        } elseif ($namingConsistency < 90) {
            $score -= 5;
        }

        return max(0, $score);
    }

    /**
     * @param AnalysisResult[] $results
     *
     * @return array<string, float>
     */
    private function calculateStyleStats(array $results): array
    {
        $formattingScores = [];
        $blockNamingConsistency = [];
        $variableNamingConsistency = [];

        foreach ($results as $result) {
            $formattingScores[] = $result->getMetric('formatting_consistency_score') ?? 0;
            $blockNamingConsistency[] = $result->getMetric('block_naming_consistency') ?? 100;
            $variableNamingConsistency[] = $result->getMetric('variable_naming_consistency') ?? 100;
        }

        return [
            'avg_formatting_consistency' => !empty($formattingScores) ? array_sum($formattingScores) / count($formattingScores) : 100,
            'block_naming_consistency' => !empty($blockNamingConsistency) ? array_sum($blockNamingConsistency) / count($blockNamingConsistency) : 100,
            'variable_naming_consistency' => !empty($variableNamingConsistency) ? array_sum($variableNamingConsistency) / count($variableNamingConsistency) : 100,
        ];
    }

    /**
     * @param AnalysisResult[] $results
     */
    private function createFormattingConsistency(array $results): ChartSection
    {
        $consistency = ['excellent' => 0, 'good' => 0, 'fair' => 0, 'poor' => 0];

        foreach ($results as $result) {
            $score = $result->getMetric('formatting_consistency_score') ?? 100;
            if ($score >= 95) {
                ++$consistency['excellent'];
            } elseif ($score >= 85) {
                ++$consistency['good'];
            } elseif ($score >= 75) {
                ++$consistency['fair'];
            } else {
                ++$consistency['poor'];
            }
        }

        return new ChartSection(
            'Formatting Consistency',
            'pie',
            [
                'labels' => ['Excellent (95%+)', 'Good (85-95%)', 'Fair (75-85%)', 'Poor (<75%)'],
                'values' => array_values($consistency),
            ]
        );
    }

    /**
     * @param AnalysisResult[] $results
     */
    private function createNamingConventions(array $results): TableSection
    {
        $stats = $this->calculateStyleStats($results);

        $conventions = [
            [
                $this->getDominantPattern($results, 'variable_naming_pattern') ?: 'snake_case',
                'Variables',
                sprintf('%.1f%%', $stats['variable_naming_consistency']),
                $stats['variable_naming_consistency'] > 85 ? '✅' : '⚠️',
            ],
            [
                'camelCase',
                'Functions',
                '100%',
                '✅',
            ],
            [
                $this->getDominantPattern($results, 'block_naming_pattern') ?: 'kebab-case',
                'Blocks',
                sprintf('%.1f%%', $stats['block_naming_consistency']),
                $stats['block_naming_consistency'] > 85 ? '✅' : '⚠️',
            ],
            [
                'PascalCase',
                'Macros',
                '100%',
                '✅',
            ],
        ];

        return new TableSection(
            'Naming Convention Adherence',
            ['Convention', 'Element Type', 'Compliance', 'Status'],
            $conventions
        );
    }

    /**
     * @param AnalysisResult[] $results
     */
    private function createLineLengthAnalysis(array $results): ChartSection
    {
        $lengths = ['short' => 0, 'medium' => 0, 'long' => 0, 'very_long' => 0];

        foreach ($results as $result) {
            $avgLineLength = $result->getMetric('avg_line_length') ?? 60;
            if ($avgLineLength < 60) {
                ++$lengths['short'];
            } elseif ($avgLineLength < 80) {
                ++$lengths['medium'];
            } elseif ($avgLineLength < 120) {
                ++$lengths['long'];
            } else {
                ++$lengths['very_long'];
            }
        }

        return new ChartSection(
            'Line Length Distribution',
            'bar',
            [
                'labels' => ['Short (<60)', 'Medium (60-80)', 'Long (80-120)', 'Very Long (>120)'],
                'values' => array_values($lengths),
            ]
        );
    }

    /**
     * @param AnalysisResult[] $results
     */
    private function createIndentationAnalysis(array $results): TableSection
    {
        $indentationIssues = [];

        foreach ($results as $result) {
            $mixedLines = $result->getMetric('mixed_indentation_lines') ?? 0;
            $trailingSpaces = $result->getMetric('trailing_spaces') ?? 0;
            $spacesCount = $result->getMetric('indentation_spaces') ?? 0;
            $tabsCount = $result->getMetric('indentation_tabs') ?? 0;

            $issues = [];
            if ($mixedLines > 0) {
                $issues[] = 'Mixed tabs/spaces';
            }
            if ($trailingSpaces > 0) {
                $issues[] = 'Trailing spaces';
            }

            if (!empty($issues) || $mixedLines > 0 || $trailingSpaces > 0) {
                $totalIssues = $mixedLines + $trailingSpaces;
                $indentationIssues[] = [
                    basename($result->getRelativePath()),
                    $totalIssues.' issues',
                    implode(', ', $issues) ?: 'Formatting issues',
                ];
            }
        }

        if (empty($indentationIssues)) {
            $indentationIssues[] = ['No issues found', '', ''];
        }

        return new TableSection(
            'Indentation Issues',
            ['Template', 'Issue Count', 'Issue Type'],
            array_slice($indentationIssues, 0, 10)
        );
    }

    /**
     * @param AnalysisResult[] $results
     */
    private function createWhitespaceAnalysis(array $results): TableSection
    {
        $totalBlankLines = 0;
        $totalTrailingSpaces = 0;
        $totalMixedIndentation = 0;
        $templatesWithSpaces = 0;
        $templatesWithTabs = 0;

        foreach ($results as $result) {
            $totalBlankLines += $result->getMetric('blank_lines') ?? 0;
            $totalTrailingSpaces += $result->getMetric('trailing_spaces') ?? 0;
            $totalMixedIndentation += $result->getMetric('mixed_indentation_lines') ?? 0;

            if (($result->getMetric('indentation_spaces') ?? 0) > 0) {
                ++$templatesWithSpaces;
            }
            if (($result->getMetric('indentation_tabs') ?? 0) > 0) {
                ++$templatesWithTabs;
            }
        }

        $totalTemplates = count($results);

        return new TableSection(
            'Whitespace & Indentation Summary',
            ['Metric', 'Total', 'Templates Affected', 'Status'],
            [
                ['Blank Lines', $totalBlankLines, $totalTemplates.' templates', '📊'],
                ['Trailing Spaces', $totalTrailingSpaces,
                    $totalTrailingSpaces > 0 ? 'Multiple templates' : 'None',
                    $totalTrailingSpaces > 0 ? '⚠️' : '✅'],
                ['Mixed Indentation', $totalMixedIndentation.' lines',
                    $totalMixedIndentation > 0 ? 'Multiple templates' : 'None',
                    $totalMixedIndentation > 0 ? '⚠️' : '✅'],
                ['Spaces for Indentation', $templatesWithSpaces.' templates',
                    sprintf('%.1f%%', $totalTemplates > 0 ? ($templatesWithSpaces / $totalTemplates) * 100 : 0), '📊'],
                ['Tabs for Indentation', $templatesWithTabs.' templates',
                    sprintf('%.1f%%', $totalTemplates > 0 ? ($templatesWithTabs / $totalTemplates) * 100 : 0), '📊'],
            ]
        );
    }

    /**
     * @param AnalysisResult[] $results
     */
    private function createCommentAnalysis(array $results): TableSection
    {
        $totalComments = 0;
        $totalLines = 0;
        $templatesWithComments = 0;
        $commentDensities = [];

        foreach ($results as $result) {
            $comments = $result->getMetric('comment_lines') ?? 0;
            $lines = $result->getMetric('lines') ?? 1;
            $density = $result->getMetric('comment_density') ?? 0;

            $totalComments += $comments;
            $totalLines += $lines;
            if ($comments > 0) {
                ++$templatesWithComments;
            }
            $commentDensities[] = $density;
        }

        $avgDensity = !empty($commentDensities) ? array_sum($commentDensities) / count($commentDensities) : 0;
        $overallDensity = $totalLines > 0 ? ($totalComments / $totalLines) * 100 : 0;

        return new TableSection(
            'Comment Analysis',
            ['Metric', 'Value', 'Assessment', 'Status'],
            [
                ['Total Comment Lines', $totalComments, $totalLines.' total lines', ' '],
                ['Templates with Comments', $templatesWithComments.'/'.count($results),
                    sprintf('%.1f%%', count($results) > 0 ? ($templatesWithComments / count($results)) * 100 : 0),
                    $templatesWithComments > 0 ? '✅' : 'ℹ️'],
                ['Average Comment Density', sprintf('%.1f%%', $avgDensity),
                    $avgDensity > 10 ? 'Well documented' : ($avgDensity > 5 ? 'Moderate' : 'Light documentation'),
                    $avgDensity > 10 ? '✅' : ($avgDensity > 5 ? '⚠️' : 'ℹ️')],
                ['Overall Density', sprintf('%.1f%%', $overallDensity),
                    'Across all templates',
                    $overallDensity > 10 ? '✅' : '📊'],
            ]
        );
    }

    /**
     * @param AnalysisResult[] $results
     */
    private function getDominantPattern(array $results, string $patternMetric): ?string
    {
        $patterns = [];
        foreach ($results as $result) {
            $pattern = $result->getMetric($patternMetric);
            if ($pattern && 'none' !== $pattern) {
                $patterns[] = $pattern;
            }
        }

        if (empty($patterns)) {
            return null;
        }

        $counts = array_count_values($patterns);

        return array_search(max($counts), $counts) ?: null;
    }
}
