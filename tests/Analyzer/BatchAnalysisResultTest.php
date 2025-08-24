<?php

declare(strict_types=1);

namespace TwigMetrics\Tests\Analyzer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use TwigMetrics\Analyzer\AnalysisResult;
use TwigMetrics\Analyzer\BatchAnalysisResult;

#[CoversClass(BatchAnalysisResult::class)]
class BatchAnalysisResultTest extends TestCase
{
    public function testConstructorAndBasicGetters(): void
    {
        $results = [];
        $dependencyGraph = ['template1.twig' => ['template2.twig']];
        $analysisTime = 1.5;

        $batchResult = new BatchAnalysisResult($results, $dependencyGraph, $analysisTime, ['error']);

        $this->assertSame($results, $batchResult->getTemplateResults());
        $this->assertSame($dependencyGraph, $batchResult->getDependencyGraph());
        $this->assertSame($analysisTime, $batchResult->analysisTime);
        $this->assertSame(['error'], $batchResult->getErrors());
    }

    public function testGetArchitecturalInsights(): void
    {
        $results = [
            $this->createRealResult('popular.twig', ['complexity_score' => 10, 'file_category' => 'component']),
            $this->createRealResult('orphan.twig', ['complexity_score' => 5, 'file_category' => 'other']),
            $this->createRealResult('user.twig', ['complexity_score' => 25, 'file_category' => 'page']),
        ];

        $dependencyGraph = [
            'popular.twig' => [],
            'orphan.twig' => [],
            'user.twig' => ['popular.twig'],
        ];

        $batchResult = new BatchAnalysisResult($results, $dependencyGraph, 1.0);
        $insights = $batchResult->getArchitecturalInsights();

        $this->assertIsArray($insights);
        $this->assertArrayHasKey('most_referenced', $insights);
        $this->assertArrayHasKey('orphaned_templates', $insights);
        $this->assertArrayHasKey('circular_dependencies', $insights);
        $this->assertArrayHasKey('category_distribution', $insights);
        $this->assertArrayHasKey('complexity_by_category', $insights);
        $this->assertArrayHasKey('architectural_health_score', $insights);

        $mostReferenced = $insights['most_referenced'];
        $this->assertArrayHasKey('popular.twig', $mostReferenced);
        $this->assertEquals(1, $mostReferenced['popular.twig']);

        $this->assertContains('orphan.twig', $insights['orphaned_templates']);
        $this->assertContains('user.twig', $insights['orphaned_templates']);

        $categories = $insights['category_distribution'];
        $this->assertEquals(1, $categories['component']);
        $this->assertEquals(1, $categories['other']);
        $this->assertEquals(1, $categories['page']);
    }

    public function testGetPerformanceInsights(): void
    {
        $results = [
            $this->createRealResultWithTime('fast.twig', 0.001),
            $this->createRealResultWithTime('slow.twig', 0.1),
            $this->createRealResultWithTime('normal.twig', 0.01),
        ];

        $batchResult = new BatchAnalysisResult($results, [], 0.111);
        $performance = $batchResult->getPerformanceInsights();

        $this->assertIsArray($performance);
        $this->assertEquals(0.111, $performance['total_analysis_time']);
        $this->assertGreaterThan(0, $performance['average_per_template']);
        $this->assertIsArray($performance['slowest_templates']);
        $this->assertGreaterThan(0, $performance['templates_per_second']);

        $slowestTemplates = $performance['slowest_templates'];
        $this->assertNotEmpty($slowestTemplates);
        $slowestPaths = array_column($slowestTemplates, 'template');
        $this->assertContains('slow.twig', $slowestPaths);
    }

    public function testDetectsCircularDependencies(): void
    {
        $results = [
            $this->createRealResult('a.twig'),
            $this->createRealResult('b.twig'),
            $this->createRealResult('c.twig'),
        ];

        $dependencyGraph = [
            'a.twig' => ['b.twig'],
            'b.twig' => ['c.twig'],
            'c.twig' => ['a.twig'],
        ];

        $batchResult = new BatchAnalysisResult($results, $dependencyGraph, 1.0);
        $insights = $batchResult->getArchitecturalInsights();

        $circularDeps = $insights['circular_dependencies'];
        $this->assertIsArray($circularDeps);
    }

    public function testCalculatesComplexityByCategory(): void
    {
        $results = [
            $this->createRealResult('comp1.twig', ['complexity_score' => 5, 'file_category' => 'component']),
            $this->createRealResult('comp2.twig', ['complexity_score' => 15, 'file_category' => 'component']),
            $this->createRealResult('page1.twig', ['complexity_score' => 20, 'file_category' => 'page']),
        ];

        $batchResult = new BatchAnalysisResult($results, [], 1.0);
        $insights = $batchResult->getArchitecturalInsights();

        $complexityByCategory = $insights['complexity_by_category'];

        $this->assertArrayHasKey('component', $complexityByCategory);
        $this->assertArrayHasKey('page', $complexityByCategory);

        $componentStats = $complexityByCategory['component'];
        $this->assertEquals(10.0, $componentStats['average']);
        $this->assertEquals(15, $componentStats['max']);
        $this->assertEquals(5, $componentStats['min']);
        $this->assertEquals(2, $componentStats['count']);

        $pageStats = $complexityByCategory['page'];
        $this->assertEquals(20.0, $pageStats['average']);
        $this->assertEquals(20, $pageStats['max']);
        $this->assertEquals(20, $pageStats['min']);
        $this->assertEquals(1, $pageStats['count']);
    }

    public function testCalculatesArchitecturalHealthScore(): void
    {
        $healthyResults = [
            $this->createRealResult('comp.twig', ['complexity_score' => 5, 'file_category' => 'component']),
            $this->createRealResult('page.twig', ['complexity_score' => 10, 'file_category' => 'page']),
        ];
        $healthyGraph = [
            'comp.twig' => [],
            'page.twig' => ['comp.twig'],
        ];

        $healthyBatch = new BatchAnalysisResult($healthyResults, $healthyGraph, 1.0);
        $healthyInsights = $healthyBatch->getArchitecturalInsights();
        $healthyScore = $healthyInsights['architectural_health_score'];

        $problematicResults = [
            $this->createRealResult('orphan.twig', ['complexity_score' => 30, 'file_category' => 'other']),
            $this->createRealResult('complex.twig', ['complexity_score' => 25, 'file_category' => 'page']),
        ];
        $problematicGraph = [
            'orphan.twig' => [],
            'complex.twig' => [],
        ];

        $problematicBatch = new BatchAnalysisResult($problematicResults, $problematicGraph, 1.0);
        $problematicInsights = $problematicBatch->getArchitecturalInsights();
        $problematicScore = $problematicInsights['architectural_health_score'];

        $this->assertGreaterThan($problematicScore, $healthyScore);
        $this->assertGreaterThanOrEqual(0, $problematicScore);
        $this->assertLessThanOrEqual(100, $healthyScore);
    }

    public function testEmptyResults(): void
    {
        $batchResult = new BatchAnalysisResult([], [], 0.5);

        $insights = $batchResult->getArchitecturalInsights();
        $performance = $batchResult->getPerformanceInsights();

        $this->assertIsArray($insights);
        $this->assertIsArray($performance);
        $this->assertEquals(0.5, $performance['total_analysis_time']);
        $this->assertEquals(0, $performance['average_per_template']);
        $this->assertEquals(0, $performance['templates_per_second']);
    }

    public function testPublicProperties(): void
    {
        $results = ['test'];
        $graph = ['a' => ['b']];
        $time = 2.5;

        $batchResult = new BatchAnalysisResult($results, $graph, $time);

        $this->assertSame($results, $batchResult->templateResults);
        $this->assertSame($graph, $batchResult->dependencyGraph);
        $this->assertSame($time, $batchResult->analysisTime);
    }

    private function createRealResult(string $path, array $metrics = []): AnalysisResult
    {
        $defaultMetrics = [
            'complexity_score' => 0,
            'file_category' => 'other',
        ];
        $allMetrics = array_merge($defaultMetrics, $metrics);

        $file = new class($path) extends \SplFileInfo {
            private string $path;

            public function __construct(string $path)
            {
                $this->path = $path;
            }

            public function getRelativePathname(): string
            {
                return $this->path;
            }

            public function getFilename(): string
            {
                return basename($this->path);
            }
        };

        return new AnalysisResult($file, $allMetrics, 0.01);
    }

    private function createRealResultWithTime(string $path, float $time): AnalysisResult
    {
        $file = new class($path) extends \SplFileInfo {
            private string $path;

            public function __construct(string $path)
            {
                $this->path = $path;
            }

            public function getRelativePathname(): string
            {
                return $this->path;
            }

            public function getFilename(): string
            {
                return basename($this->path);
            }
        };

        return new AnalysisResult($file, ['complexity_score' => 0, 'file_category' => 'other'], $time);
    }
}
