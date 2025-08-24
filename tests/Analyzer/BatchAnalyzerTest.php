<?php

declare(strict_types=1);

namespace TwigMetrics\Tests\Analyzer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use TwigMetrics\Analyzer\AnalysisResult;
use TwigMetrics\Analyzer\BatchAnalysisResult;
use TwigMetrics\Analyzer\BatchAnalyzer;
use TwigMetrics\Analyzer\TemplateAnalyzer;
use TwigMetrics\Collector\ComplexityCollector;
use TwigMetrics\Template\Parser\AstTraverser;
use TwigMetrics\Template\Parser\TwigSourceParser;

#[CoversClass(BatchAnalyzer::class)]
class BatchAnalyzerTest extends TestCase
{
    private BatchAnalyzer $batchAnalyzer;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir().'/twigmetrics_test_'.uniqid('', true);
        mkdir($this->tempDir, 0777, true);

        $collectors = [
            new ComplexityCollector(),
        ];

        $templateAnalyzer = new TemplateAnalyzer(
            $collectors,
            new AstTraverser(),
            new TwigSourceParser(new Environment(new ArrayLoader()))
        );

        $this->batchAnalyzer = new BatchAnalyzer($templateAnalyzer);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    public function testAnalyzesEmptyFileList(): void
    {
        $result = $this->batchAnalyzer->analyze([]);

        $this->assertInstanceOf(BatchAnalysisResult::class, $result);
        $this->assertCount(0, $result->getTemplateResults());
        $this->assertIsFloat($result->analysisTime);
        $this->assertEmpty($result->getErrors());
    }

    public function testAnalyzesSingleFile(): void
    {
        $file = $this->createTempFile('test.twig', 'Hello World');

        $result = $this->batchAnalyzer->analyze([$file]);

        $this->assertInstanceOf(BatchAnalysisResult::class, $result);
        $this->assertCount(1, $result->getTemplateResults());
        $this->assertGreaterThan(0, $result->analysisTime);
        $this->assertEmpty($result->getErrors());

        $firstResult = $result->getTemplateResults()[0];
        $this->assertInstanceOf(AnalysisResult::class, $firstResult);
        $this->assertSame('test.twig', $firstResult->getRelativePath());
    }

    public function testAnalyzesMultipleFiles(): void
    {
        $file1 = $this->createTempFile('template1.twig', 'Hello {{ name }}');
        $file2 = $this->createTempFile('template2.twig', '{% if user %}Hi!{% endif %}');

        $result = $this->batchAnalyzer->analyze([$file1, $file2]);

        $this->assertInstanceOf(BatchAnalysisResult::class, $result);
        $this->assertCount(2, $result->getTemplateResults());
        $this->assertGreaterThan(0, $result->analysisTime);
        $this->assertEmpty($result->getErrors());
    }

    public function testBuildsDependencyGraph(): void
    {
        $file1 = $this->createTempFile('template1.twig', 'Basic template');
        $file2 = $this->createTempFile('template2.twig', 'Another template');

        $result = $this->batchAnalyzer->analyze([$file1, $file2]);

        $this->assertInstanceOf(BatchAnalysisResult::class, $result);
        $dependencyGraph = $result->getDependencyGraph();
        $this->assertIsArray($dependencyGraph);
        $this->assertArrayHasKey('template1.twig', $dependencyGraph);
        $this->assertArrayHasKey('template2.twig', $dependencyGraph);
        $this->assertEmpty($result->getErrors());
    }

    public function testEnhancesResultsWithCrossTemplateInsights(): void
    {
        $file1 = $this->createTempFile('simple.twig', 'Simple content');
        $file2 = $this->createTempFile('complex.twig', '{% for i in range(1,10) %}{% if i %}{{ i }}{% endif %}{% endfor %}');

        $result = $this->batchAnalyzer->analyze([$file1, $file2]);

        $results = $result->getTemplateResults();
        $this->assertCount(2, $results);
        $this->assertEmpty($result->getErrors());

        $firstResult = $results[0];
        $this->assertArrayHasKey('times_referenced', $firstResult->getData());
        $this->assertArrayHasKey('popularity_rank', $firstResult->getData());
        $this->assertArrayHasKey('architectural_role', $firstResult->getData());
        $this->assertArrayHasKey('coupling_risk', $firstResult->getData());
        $this->assertArrayHasKey('reusability_score', $firstResult->getData());
        $this->assertArrayHasKey('potential_issues', $firstResult->getData());
    }

    public function testCalculatesBasicMetrics(): void
    {
        $file = $this->createTempFile('metrics.twig', '{% if condition %}{{ variable }}{% endif %}');

        $result = $this->batchAnalyzer->analyze([$file]);
        $results = $result->getTemplateResults();

        $this->assertCount(1, $results);
        $analysisResult = $results[0];
        $this->assertEmpty($result->getErrors());

        $this->assertIsInt($analysisResult->getMetric('times_referenced'));
        $this->assertIsInt($analysisResult->getMetric('popularity_rank'));
        $this->assertIsString($analysisResult->getMetric('architectural_role'));
        $this->assertIsString($analysisResult->getMetric('coupling_risk'));
        $this->assertIsInt($analysisResult->getMetric('reusability_score'));
        $this->assertIsArray($analysisResult->getMetric('potential_issues'));
    }

    private function createTempFile(string $relativePath, string $content): \SplFileInfo
    {
        $fullPath = $this->tempDir.'/'.$relativePath;
        $dir = dirname($fullPath);

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($fullPath, $content);

        return new class($fullPath, $relativePath) extends \SplFileInfo {
            private string $relativePathname;

            public function __construct(string $filename, string $relativePathname)
            {
                parent::__construct($filename);
                $this->relativePathname = $relativePathname;
            }

            public function getRelativePathname(): string
            {
                return $this->relativePathname;
            }
        };
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir.'/'.$file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
