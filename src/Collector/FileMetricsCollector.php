<?php

declare(strict_types=1);

namespace TwigMetrics\Collector;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
/**
 * Lightweight helper to derive file-level ratios from existing code style metrics.
 * Not wired into the AST traversal; intended for post-processing or tests.
 */
final class FileMetricsCollector
{
    /**
     * @param array<string, mixed> $metrics Expecting keys: lines, blank_lines, comment_lines
     *
     * @return array<string, float|int>
     */
    public function compute(array $metrics): array
    {
        $lines = (int) ($metrics['lines'] ?? 0);
        $empty = (int) ($metrics['blank_lines'] ?? 0);
        $comments = (int) ($metrics['comment_lines'] ?? 0);
        $code = max(0, $lines - $empty - $comments);

        return [
            'emptyLines' => $empty,
            'commentLines' => $comments,
            'codeLines' => $code,
            'emptyRatio' => $lines > 0 ? $empty / $lines : 0.0,
            'commentRatio' => $lines > 0 ? $comments / $lines : 0.0,
            'commentDensity' => $code > 0 ? $comments / $code : 0.0,
        ];
    }
}
