<?php

declare(strict_types=1);

namespace TwigMetrics\Tests\Renderer\Dimension\Box;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\SplFileInfo;
use TwigMetrics\Analyzer\AnalysisResult;
use TwigMetrics\Analyzer\CallableSecurityAnalyzer;
use TwigMetrics\Calculator\DiversityCalculator;
use TwigMetrics\Renderer\Dimension\Box\TwigCallablesDimensionPresenter;
use TwigMetrics\Reporter\Dimension\TwigCallablesMetricsReporter;

#[CoversClass(TwigCallablesDimensionPresenter::class)]
final class TwigCallablesDimensionPresenterTest extends TestCase
{
    public function testPresentProducesExpectedKeys(): void
    {
        $results = [
            $this->res([
                'functions_detail' => ['dump' => 2, 'date' => 1],
                'filters_detail' => ['upper' => 3, 'lower' => 1],
                'variables_detail' => ['app' => 5, 'user' => 2],
                'tests_detail' => ['empty' => 2, 'defined' => 1],
                'macro_definitions_detail' => ['helper' => 1],
                'blocks_detail' => ['content' => 1, 'title' => 1],
            ], 'components/a.twig'),
            $this->res([
                'functions_detail' => ['url' => 4, 'path' => 2],
                'filters_detail' => ['date' => 2, 'format' => 1],
                'variables_detail' => ['request' => 3],
                'tests_detail' => ['null' => 1],
                'macro_definitions_detail' => [],
                'blocks_detail' => ['sidebar' => 1],
            ], 'pages/b.twig'),
        ];

        $presenter = new TwigCallablesDimensionPresenter(
            new TwigCallablesMetricsReporter(new DiversityCalculator(), new CallableSecurityAnalyzer()),
            new CallableSecurityAnalyzer(),
        );
        $data = $presenter->present($results, 1, true);

        $this->assertArrayHasKey('summary', $data);
        $this->assertArrayHasKey('distribution', $data);
        $this->assertArrayHasKey('top_functions', $data);
        $this->assertArrayHasKey('top_filters', $data);
        $this->assertArrayHasKey('directories', $data);
        $this->assertArrayHasKey('security_issues', $data);
        $this->assertArrayHasKey('final', $data);

        $summary = $data['summary'];
        $this->assertArrayHasKey('unique_functions', $summary);
        $this->assertArrayHasKey('unique_filters', $summary);
        $this->assertIsNumeric($summary['unique_functions']);
        $this->assertIsNumeric($summary['unique_filters']);
    }

    public function testPresentWithEmptyResults(): void
    {
        $presenter = new TwigCallablesDimensionPresenter(
            new TwigCallablesMetricsReporter(new DiversityCalculator(), new CallableSecurityAnalyzer()),
            new CallableSecurityAnalyzer(),
        );

        $data = $presenter->present([], 1, true);

        $this->assertArrayHasKey('summary', $data);
        $this->assertIsArray($data['top_functions']);
        $this->assertEmpty($data['top_functions']);
    }

    private function res(array $metrics, string $rel): AnalysisResult
    {
        $file = new SplFileInfo(__FILE__, '', $rel);

        return new AnalysisResult($file, $metrics, 0.0);
    }
}
