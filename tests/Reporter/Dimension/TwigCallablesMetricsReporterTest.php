<?php

declare(strict_types=1);

namespace TwigMetrics\Tests\Reporter\Dimension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\SplFileInfo;
use TwigMetrics\Analyzer\AnalysisResult;
use TwigMetrics\Analyzer\CallableSecurityAnalyzer;
use TwigMetrics\Calculator\DiversityCalculator;
use TwigMetrics\Reporter\Dimension\TwigCallablesMetricsReporter;

#[CoversClass(TwigCallablesMetricsReporter::class)]
final class TwigCallablesMetricsReporterTest extends TestCase
{
    public function testGenerateMetrics(): void
    {
        $results = [
            $this->res([
                'functions_detail' => ['include' => 2, 'dump' => 1],
                'filters_detail' => ['upper' => 3],
                'tests_detail' => ['empty' => 2],
                'deprecated_callables' => 1,
            ], 'a.twig'),
            $this->res([
                'functions_detail' => ['path' => 4],
                'filters_detail' => ['lower' => 2, 'raw' => 1],
                'tests_detail' => ['defined' => 1],
                'deprecated_callables' => 0,
            ], 'b.twig'),
        ];

        $reporter = new TwigCallablesMetricsReporter(new DiversityCalculator(), new CallableSecurityAnalyzer());
        $card = $reporter->generateMetrics($results);

        $this->assertSame('Twig Callables', $card->name);
        $this->assertArrayHasKey('total_calls', $card->coreMetrics);
        $this->assertArrayHasKey('diversity_index', $card->detailMetrics);
        $this->assertArrayHasKey('usage_breakdown', $card->distributions);
        $this->assertIsArray($card->insights);
    }

    private function res(array $metrics, string $rel): AnalysisResult
    {
        $file = new SplFileInfo(__FILE__, '', $rel);

        return new AnalysisResult($file, $metrics, 0.0);
    }
}
