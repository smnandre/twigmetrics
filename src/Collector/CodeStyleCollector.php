<?php

declare(strict_types=1);

namespace TwigMetrics\Collector;

use Twig\Node\Node;
use Twig\Source;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
final class CodeStyleCollector extends AbstractCollector
{
    /**
     * @return array<string, mixed>
     */
    public function collect(Source $source): array
    {
        $content = $source->getCode();
        $lines = explode("\n", $content);
        $totalLines = count($lines);

        $metrics = [
            'avg_line_length' => 0,
            'max_line_length' => 0,
            'comment_lines' => 0,
            'blank_lines' => 0,
            'trailing_spaces' => 0,
            'indentation_spaces' => 0,
            'indentation_tabs' => 0,
            'block_names' => [],
            'variable_names' => [],
            'mixed_indentation_lines' => 0,
        ];

        $totalLength = 0;
        foreach ($lines as $line) {
            $length = strlen($line);
            $totalLength += $length;

            if ($length > $metrics['max_line_length']) {
                $metrics['max_line_length'] = $length;
            }

            if ('' === trim($line)) {
                ++$metrics['blank_lines'];
            }

            if (str_starts_with(trim($line), '{#')) {
                ++$metrics['comment_lines'];
            }

            if ($length > 0 && preg_match('/\s$/', $line)) {
                ++$metrics['trailing_spaces'];
            }

            if (preg_match('/^(\s+)/', $line, $matches)) {
                $hasSpaces = str_contains($matches[1], ' ');
                $hasTabs = str_contains($matches[1], "\t");

                if ($hasSpaces && $hasTabs) {
                    ++$metrics['mixed_indentation_lines'];
                } elseif ($hasTabs) {
                    ++$metrics['indentation_tabs'];
                } else {
                    ++$metrics['indentation_spaces'];
                }
            }

            if (preg_match('/{%\s*block\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*%}/', $line, $matches)) {
                $metrics['block_names'][] = $matches[1];
            }

            if (preg_match_all('/\{\{\s*([a-zA-Z_][a-zA-Z0-9_.]*)\s*[|}]/', $line, $matches)) {
                foreach ($matches[1] as $varName) {
                    if (!str_contains($varName, '(') && !str_contains($varName, '.')) {
                        $metrics['variable_names'][] = $varName;
                    }
                }
            }
        }

        $metrics['avg_line_length'] = round($totalLength / $totalLines, 2);
        $metrics['comment_density'] = ($metrics['comment_lines'] / $totalLines) * 100;

        $metrics['lines'] = $totalLines;
        $metrics['chars'] = $totalLength;

        $metrics = array_merge($metrics, $this->analyzeNamingConventions($metrics['block_names'], $metrics['variable_names']));

        $metrics['formatting_consistency_score'] = $this->calculateFormattingConsistency($metrics, $totalLines);

        return $metrics;
    }

    public function enterNode(Node $node): void
    {
    }

    public function leaveNode(Node $node): void
    {
    }

    /**
     * @param string[] $blockNames
     * @param string[] $variableNames
     *
     * @return array<string, mixed>
     */
    private function analyzeNamingConventions(array $blockNames, array $variableNames): array
    {
        $snakeCasePattern = '/^[a-z][a-z0-9]*(_[a-z0-9]+)*$/';
        $camelCasePattern = '/^[a-z][a-zA-Z0-9]*$/';
        $kebabCasePattern = '/^[a-z][a-z0-9]*(-[a-z0-9]+)*$/';

        $blockConventions = $this->analyzeNames($blockNames, [
            'snake_case' => $snakeCasePattern,
            'camelCase' => $camelCasePattern,
            'kebab-case' => $kebabCasePattern,
        ]);

        $variableConventions = $this->analyzeNames($variableNames, [
            'snake_case' => $snakeCasePattern,
            'camelCase' => $camelCasePattern,
        ]);

        return [
            'unique_block_names' => count(array_unique($blockNames)),
            'unique_variable_names' => count(array_unique($variableNames)),
            'block_naming_consistency' => $blockConventions['consistency'],
            'block_naming_pattern' => $blockConventions['dominant_pattern'],
            'variable_naming_consistency' => $variableConventions['consistency'],
            'variable_naming_pattern' => $variableConventions['dominant_pattern'],
        ];
    }

    /**
     * @param string[]              $names
     * @param array<string, string> $patterns
     *
     * @return array{consistency: float, dominant_pattern: string}
     */
    private function analyzeNames(array $names, array $patterns): array
    {
        if (empty($names)) {
            return ['consistency' => 100.0, 'dominant_pattern' => 'none'];
        }

        $patternCounts = [];
        foreach ($patterns as $patternName => $regex) {
            $patternCounts[$patternName] = 0;
        }
        $patternCounts['other'] = 0;

        foreach ($names as $name) {
            $matched = false;
            foreach ($patterns as $patternName => $regex) {
                if (preg_match($regex, $name)) {
                    ++$patternCounts[$patternName];
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                ++$patternCounts['other'];
            }
        }

        $total = count($names);
        $maxCount = max($patternCounts);
        $dominantPattern = array_search($maxCount, $patternCounts) ?: 'mixed';
        $consistency = round(($maxCount / $total) * 100, 1);

        return [
            'consistency' => $consistency,
            'dominant_pattern' => $dominantPattern,
        ];
    }

    /**
     * @param array<string, mixed> $metrics
     */
    private function calculateFormattingConsistency(array $metrics, int $totalLines): float
    {
        $score = 100.0;

        if ($metrics['mixed_indentation_lines'] > 0) {
            $penalty = ($metrics['mixed_indentation_lines'] / $totalLines) * 30;
            $score -= min($penalty, 15);
        }

        if ($metrics['trailing_spaces'] > 0) {
            $penalty = ($metrics['trailing_spaces'] / $totalLines) * 20;
            $score -= min($penalty, 10);
        }

        if ($metrics['max_line_length'] > 120) {
            $score -= min(($metrics['max_line_length'] - 120) / 10, 10);
        }

        $commentDensity = $metrics['comment_density'];
        if ($commentDensity < 5 || $commentDensity > 50) {
            $score -= 5;
        }

        return max(round($score, 1), 0.0);
    }
}
