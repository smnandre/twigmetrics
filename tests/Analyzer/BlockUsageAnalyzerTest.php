<?php

declare(strict_types=1);

namespace TwigMetrics\Tests\Analyzer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\SplFileInfo;
use TwigMetrics\Analyzer\AnalysisResult;
use TwigMetrics\Analyzer\BlockUsageAnalyzer;

#[CoversClass(BlockUsageAnalyzer::class)]
final class BlockUsageAnalyzerTest extends TestCase
{
    public function testAnalyzeBlockUsage(): void
    {
        $results = [
            $this->makeResult(['provided_blocks' => ['a', 'b'], 'used_blocks' => ['a']], 't1.twig'),
            $this->makeResult(['provided_blocks' => ['c'], 'used_blocks' => ['a', 'b']], 't2.twig'),
        ];

        $analyzer = new BlockUsageAnalyzer();
        $metrics = $analyzer->analyzeBlockUsage($results);

        $this->assertSame(3, $metrics->totalDefined);
        $this->assertSame(3, $metrics->totalUsed);
        $this->assertContains('c', $metrics->orphanedBlocks);
        $this->assertGreaterThan(0.0, $metrics->usageRatio);
        $this->assertGreaterThan(0.0, $metrics->averageReuse);
    }

    private function makeResult(array $metrics, string $rel): AnalysisResult
    {
        $file = new SplFileInfo(__FILE__, '', $rel);

        return new AnalysisResult($file, $metrics, 0.0);
    }
}
