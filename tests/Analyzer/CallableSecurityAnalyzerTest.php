<?php

declare(strict_types=1);

namespace TwigMetrics\Tests\Analyzer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\SplFileInfo;
use TwigMetrics\Analyzer\AnalysisResult;
use TwigMetrics\Analyzer\CallableSecurityAnalyzer;

#[CoversClass(CallableSecurityAnalyzer::class)]
final class CallableSecurityAnalyzerTest extends TestCase
{
    public function testAnalyzeSecurityScore(): void
    {
        $results = [
            $this->makeResult([
                'functions_detail' => ['dump' => 2, 'include' => 1],
                'filters_detail' => ['raw' => 1, 'upper' => 3],
                'deprecated_callables' => 2,
            ], 'a.twig'),
        ];

        $analyzer = new CallableSecurityAnalyzer();
        $metrics = $analyzer->analyzeSecurityScore($results);

        $this->assertLessThan(100, $metrics->score);
        $this->assertArrayHasKey('dump', $metrics->risks);
        $this->assertSame(2, $metrics->deprecatedCount);
    }

    private function makeResult(array $metrics, string $rel): AnalysisResult
    {
        $file = new SplFileInfo(__FILE__, '', $rel);

        return new AnalysisResult($file, $metrics, 0.0);
    }
}
