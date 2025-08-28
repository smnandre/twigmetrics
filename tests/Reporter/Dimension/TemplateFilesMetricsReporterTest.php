<?php

declare(strict_types=1);

namespace TwigMetrics\Tests\Reporter\Dimension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\SplFileInfo;
use TwigMetrics\Analyzer\AnalysisResult;
use TwigMetrics\Calculator\DistributionCalculator;
use TwigMetrics\Calculator\StatisticalCalculator;
use TwigMetrics\Reporter\Dimension\TemplateFilesMetricsReporter;

#[CoversClass(TemplateFilesMetricsReporter::class)]
final class TemplateFilesMetricsReporterTest extends TestCase
{
    public function testGenerateMetricsProducesCard(): void
    {
        $results = [
            $this->res(['lines' => 40], 'components/alert.html.twig'),
            $this->res(['lines' => 60], 'components/button.html.twig'),
            $this->res(['lines' => 120], 'pages/home.html.twig'),
            $this->res(['lines' => 200], 'layouts/base.html.twig'),
        ];

        $reporter = new TemplateFilesMetricsReporter(new StatisticalCalculator(), new DistributionCalculator());
        $card = $reporter->generateMetrics($results);

        $this->assertSame('Template Files', $card->name);
        $this->assertArrayHasKey('templates', $card->coreMetrics);
        $this->assertArrayHasKey('cv', $card->detailMetrics);
        $this->assertArrayHasKey('size', $card->distributions);
        $this->assertIsArray($card->insights);
    }

    private function res(array $metrics, string $rel): AnalysisResult
    {
        $file = new SplFileInfo(__FILE__, '', $rel);

        return new AnalysisResult($file, $metrics, 0.0);
    }
}
