<?php

declare(strict_types=1);

namespace TwigMetrics\Tests\Analyzer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\SplFileInfo;
use TwigMetrics\Analyzer\AnalysisResult;
use TwigMetrics\Analyzer\CouplingAnalyzer;

#[CoversClass(CouplingAnalyzer::class)]
final class CouplingAnalyzerTest extends TestCase
{
    public function testAnalyzeCoupling(): void
    {
        $a = $this->makeResult(['dependencies' => ['b.twig', ['template' => 'c.twig']]], 'a.twig');
        $b = $this->makeResult(['dependencies' => ['c.twig']], 'b.twig');
        $c = $this->makeResult(['dependencies' => []], 'c.twig');

        $analyzer = new CouplingAnalyzer();
        $metrics = $analyzer->analyzeCoupling([$a, $b, $c]);

        $this->assertGreaterThanOrEqual(0.0, $metrics->avgFanIn);
        $this->assertGreaterThanOrEqual(0.0, $metrics->avgFanOut);
        $this->assertGreaterThanOrEqual(0, $metrics->maxCoupling);
        $this->assertGreaterThanOrEqual(0.0, $metrics->instabilityIndex);
    }

    private function makeResult(array $metrics, string $rel): AnalysisResult
    {
        $file = new SplFileInfo(__FILE__, '', $rel);

        return new AnalysisResult($file, $metrics, 0.0);
    }
}
