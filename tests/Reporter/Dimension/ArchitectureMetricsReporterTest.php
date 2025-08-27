<?php

declare(strict_types=1);

namespace TwigMetrics\Tests\Reporter\Dimension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\SplFileInfo;
use TwigMetrics\Analyzer\AnalysisResult;
use TwigMetrics\Analyzer\BlockUsageAnalyzer;
use TwigMetrics\Analyzer\CouplingAnalyzer;
use TwigMetrics\Reporter\Dimension\ArchitectureMetricsReporter;

#[CoversClass(ArchitectureMetricsReporter::class)]
final class ArchitectureMetricsReporterTest extends TestCase
{
    public function testGenerateMetrics(): void
    {
        $results = [
            $this->res(['dependencies' => [], 'provided_blocks' => ['header'], 'used_blocks' => [], 'file_category' => 'component', 'inheritance_depth' => 1], 'components/card.html.twig'),
            $this->res(['dependencies' => ['components/card.html.twig'], 'provided_blocks' => [], 'used_blocks' => ['header'], 'file_category' => 'page', 'inheritance_depth' => 2], 'pages/home.html.twig'),

            $this->res(['dependencies' => [], 'provided_blocks' => ['body'], 'used_blocks' => [], 'file_category' => 'layout', 'inheritance_depth' => 0], 'layouts/base.html.twig'),
        ];

        $reporter = new ArchitectureMetricsReporter(new CouplingAnalyzer(), new BlockUsageAnalyzer());
        $card = $reporter->generateMetrics($results);

        $this->assertSame('Architecture', $card->name);
        $this->assertArrayHasKey('avg_fan_in', $card->coreMetrics);
        $this->assertArrayHasKey('components_ratio', $card->detailMetrics);
        $this->assertArrayHasKey('roles', $card->distributions);
        $this->assertIsArray($card->insights);
    }

    private function res(array $metrics, string $rel): AnalysisResult
    {
        $file = new SplFileInfo(__FILE__, '', $rel);

        return new AnalysisResult($file, $metrics, 0.0);
    }
}
