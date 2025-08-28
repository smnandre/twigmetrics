<?php

declare(strict_types=1);

namespace TwigMetrics\Tests\Metric\Aggregator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use TwigMetrics\Analyzer\AnalysisResult;
use TwigMetrics\Metric\Aggregator\DirectoryMetrics;

#[CoversClass(DirectoryMetrics::class)]
final class DirectoryMetricsTest extends TestCase
{
    public function testConstructor(): void
    {
        $metrics = new DirectoryMetrics('/path/to/dir');
        $this->assertSame('/path/to/dir', $metrics->path);
    }

    public function testInitialState(): void
    {
        $metrics = new DirectoryMetrics('/path/to/dir');

        $this->assertSame(0, $metrics->fileCount);
        $this->assertSame(0, $metrics->totalLines);
        $this->assertSame(0, $metrics->totalBlank);
        $this->assertSame(0, $metrics->totalComments);
        $this->assertSame(0, $metrics->maxComplexity);
        $this->assertSame(0, $metrics->sumComplexity);
        $this->assertSame(0.0, $metrics->sumDepth);
        $this->assertSame(0, $metrics->criticalCount);
        $this->assertSame(0, $metrics->sumMaxLineLength);
        $this->assertSame(0.0, $metrics->sumFormatScore);
        $this->assertSame(0, $metrics->sumMixedIndent);

        $this->assertSame(0.0, $metrics->getEmptyLinesRatio());
        $this->assertSame(0.0, $metrics->getCommentRatio());
        $this->assertSame(0.0, $metrics->getAverageLines());
        $this->assertSame(0.0, $metrics->getAverageDepth());
        $this->assertSame(0.0, $metrics->getAverageComplexity());
        $this->assertSame(0.0, $metrics->getAverageMaxLineLength());
        $this->assertSame(0.0, $metrics->getAverageFormatScore());
        $this->assertSame(100.0, $metrics->getIndentationConsistency());
    }

    public function testAddSingleResult(): void
    {
        $metrics = new DirectoryMetrics('/path/to/dir');
        $result = $this->createAnalysisResult([
            'lines' => 100,
            'blank_lines' => 10,
            'comment_lines' => 5,
            'complexity_score' => 15,
            'max_depth' => 4,
            'max_line_length' => 120,
            'formatting_consistency_score' => 95.5,
            'mixed_indentation_lines' => 2,
        ]);

        $metrics->addResult($result);

        $this->assertSame(1, $metrics->fileCount);
        $this->assertSame(100, $metrics->totalLines);
        $this->assertSame(10, $metrics->totalBlank);
        $this->assertSame(5, $metrics->totalComments);
        $this->assertSame(15, $metrics->maxComplexity);
        $this->assertSame(15, $metrics->sumComplexity);
        $this->assertSame(4.0, $metrics->sumDepth);
        $this->assertSame(0, $metrics->criticalCount);
        $this->assertSame(120, $metrics->sumMaxLineLength);
        $this->assertSame(95.5, $metrics->sumFormatScore);
        $this->assertSame(2, $metrics->sumMixedIndent);
    }

    public function testAddSingleResultWithHighComplexity(): void
    {
        $metrics = new DirectoryMetrics('/path/to/dir');
        $result = $this->createAnalysisResult(['complexity_score' => 30]);

        $metrics->addResult($result);

        $this->assertSame(1, $metrics->fileCount);
        $this->assertSame(30, $metrics->maxComplexity);
        $this->assertSame(30, $metrics->sumComplexity);
        $this->assertSame(1, $metrics->criticalCount);
    }

    public function testAddResultWithMissingMetrics(): void
    {
        $metrics = new DirectoryMetrics('/path/to/dir');
        $result = $this->createAnalysisResult([]);

        $metrics->addResult($result);

        $this->assertSame(1, $metrics->fileCount);
        $this->assertSame(0, $metrics->totalLines);
        $this->assertSame(0, $metrics->totalBlank);
        $this->assertSame(0, $metrics->totalComments);
        $this->assertSame(0, $metrics->maxComplexity);
        $this->assertSame(0, $metrics->sumComplexity);
        $this->assertSame(0.0, $metrics->sumDepth);
        $this->assertSame(0, $metrics->criticalCount);
        $this->assertSame(0, $metrics->sumMaxLineLength);
        $this->assertSame(100.0, $metrics->sumFormatScore);
        $this->assertSame(0, $metrics->sumMixedIndent);
    }

    public function testAddMultipleResults(): void
    {
        $metrics = new DirectoryMetrics('/path/to/dir');

        $result1 = $this->createAnalysisResult([
            'lines' => 100,
            'blank_lines' => 10,
            'comment_lines' => 5,
            'complexity_score' => 15,
            'max_depth' => 4,
            'max_line_length' => 120,
            'formatting_consistency_score' => 95.5,
            'mixed_indentation_lines' => 2,
        ]);

        $result2 = $this->createAnalysisResult([
            'lines' => 50,
            'blank_lines' => 5,
            'comment_lines' => 2,
            'complexity_score' => 30,
            'max_depth' => 6,
            'max_line_length' => 80,
            'formatting_consistency_score' => 90.0,
            'mixed_indentation_lines' => 3,
        ]);

        $metrics->addResult($result1);
        $metrics->addResult($result2);

        $this->assertSame(2, $metrics->fileCount);
        $this->assertSame(150, $metrics->totalLines);
        $this->assertSame(15, $metrics->totalBlank);
        $this->assertSame(7, $metrics->totalComments);
        $this->assertSame(30, $metrics->maxComplexity);
        $this->assertSame(45, $metrics->sumComplexity);
        $this->assertSame(10.0, $metrics->sumDepth);
        $this->assertSame(1, $metrics->criticalCount);
        $this->assertSame(200, $metrics->sumMaxLineLength);
        $this->assertSame(185.5, $metrics->sumFormatScore);
        $this->assertSame(5, $metrics->sumMixedIndent);
    }

    public function testGettersWithData(): void
    {
        $metrics = new DirectoryMetrics('/path/to/dir');

        $result1 = $this->createAnalysisResult(['lines' => 100, 'blank_lines' => 10, 'comment_lines' => 5, 'complexity_score' => 15, 'max_depth' => 4, 'max_line_length' => 120, 'formatting_consistency_score' => 95.5, 'mixed_indentation_lines' => 2]);
        $result2 = $this->createAnalysisResult(['lines' => 50, 'blank_lines' => 5, 'comment_lines' => 2, 'complexity_score' => 30, 'max_depth' => 6, 'max_line_length' => 80, 'formatting_consistency_score' => 90.0, 'mixed_indentation_lines' => 3]);

        $metrics->addResult($result1);
        $metrics->addResult($result2);

        $this->assertSame(15 / 150, $metrics->getEmptyLinesRatio());
        $this->assertSame(7 / 150, $metrics->getCommentRatio());
        $this->assertSame(150.0 / 2, $metrics->getAverageLines());
        $this->assertSame(10.0 / 2, $metrics->getAverageDepth());
        $this->assertSame(45.0 / 2, $metrics->getAverageComplexity());
        $this->assertSame(200.0 / 2, $metrics->getAverageMaxLineLength());
        $this->assertSame(185.5 / 2, $metrics->getAverageFormatScore());
        $this->assertSame(100.0 - (5 / 150) * 100.0, $metrics->getIndentationConsistency());
    }

    public function testIndentationConsistencyAtZero(): void
    {
        $metrics = new DirectoryMetrics('/path/to/dir');
        $result = $this->createAnalysisResult([
            'lines' => 100,
            'mixed_indentation_lines' => 150,
        ]);

        $metrics->addResult($result);

        $this->assertSame(0.0, $metrics->getIndentationConsistency());
    }

    /**
     * @param array<string, mixed> $metrics
     */
    private function createAnalysisResult(array $metrics): AnalysisResult
    {
        $fileInfo = $this->createMock(\SplFileInfo::class);

        return new AnalysisResult($fileInfo, $metrics, 0.1);
    }
}
