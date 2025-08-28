<?php

declare(strict_types=1);

namespace TwigMetrics\Metric\Aggregator;

use TwigMetrics\Analyzer\AnalysisResult;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
final class DirectoryMetrics
{
    public int $fileCount = 0;
    public int $totalLines = 0;
    public int $totalBlank = 0;
    public int $totalComments = 0;
    public int $maxComplexity = 0;
    public int $sumComplexity = 0;
    public float $sumDepth = 0.0;
    public int $criticalCount = 0;
    public int $sumMaxLineLength = 0;
    public float $sumFormatScore = 0.0;
    public int $sumMixedIndent = 0;

    public function __construct(public readonly string $path)
    {
    }

    public function addResult(AnalysisResult $result): void
    {
        ++$this->fileCount;

        $lines = (int) ($result->getMetric('lines') ?? 0);
        $blank = (int) ($result->getMetric('blank_lines') ?? 0);
        $comments = (int) ($result->getMetric('comment_lines') ?? 0);
        $cx = (int) ($result->getMetric('complexity_score') ?? 0);
        $depth = (int) ($result->getMetric('max_depth') ?? 0);
        $maxLen = (int) ($result->getMetric('max_line_length') ?? 0);
        $format = (float) ($result->getMetric('formatting_consistency_score') ?? 100.0);
        $mixed = (int) ($result->getMetric('mixed_indentation_lines') ?? 0);

        $this->totalLines += $lines;
        $this->totalBlank += $blank;
        $this->totalComments += $comments;
        $this->maxComplexity = max($this->maxComplexity, $cx);
        $this->sumComplexity += $cx;
        $this->sumDepth += $depth;
        $this->sumMaxLineLength += $maxLen;
        $this->sumFormatScore += $format;
        $this->sumMixedIndent += $mixed;
        if ($cx > 25) {
            ++$this->criticalCount;
        }
    }

    public function getEmptyLinesRatio(): float
    {
        return $this->totalLines > 0 ? $this->totalBlank / $this->totalLines : 0.0;
    }

    public function getCommentRatio(): float
    {
        return $this->totalLines > 0 ? $this->totalComments / $this->totalLines : 0.0;
    }

    public function getAverageLines(): float
    {
        return $this->fileCount > 0 ? $this->totalLines / $this->fileCount : 0.0;
    }

    public function getAverageDepth(): float
    {
        return $this->fileCount > 0 ? $this->sumDepth / $this->fileCount : 0.0;
    }

    public function getAverageComplexity(): float
    {
        return $this->fileCount > 0 ? $this->sumComplexity / $this->fileCount : 0.0;
    }

    public function getAverageMaxLineLength(): float
    {
        return $this->fileCount > 0 ? $this->sumMaxLineLength / $this->fileCount : 0.0;
    }

    public function getAverageFormatScore(): float
    {
        return $this->fileCount > 0 ? $this->sumFormatScore / $this->fileCount : 0.0;
    }

    public function getIndentationConsistency(): float
    {
        return $this->totalLines > 0 ? max(0.0, 100.0 - ($this->sumMixedIndent / $this->totalLines) * 100.0) : 100.0;
    }
}
