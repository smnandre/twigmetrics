<?php

declare(strict_types=1);

namespace TwigMetrics\Tests\Analyzer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\SplFileInfo;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use TwigMetrics\Analyzer\AnalysisResult;
use TwigMetrics\Analyzer\TemplateAnalyzer;
use TwigMetrics\Collector\CollectorInterface;
use TwigMetrics\Template\Parser\AstTraverser;
use TwigMetrics\Template\Parser\TwigSourceParser;

#[CoversClass(TemplateAnalyzer::class)]
class TemplateAnalyzerTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir().'/twigmetrics_test_'.uniqid('', true);
        mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }
    }

    private function removeDirectory(string $path): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
            $todo($fileinfo->getRealPath());
        }

        rmdir($path);
    }

    public function testAnalyze(): void
    {
        $filePath = $this->tempDir.'/test.twig';
        file_put_contents($filePath, '{{ some_var }}');
        $fileInfo = new SplFileInfo($filePath, '', 'test.twig');

        $mockCollector = $this->createMock(CollectorInterface::class);
        $mockCollector->method('getData')
                      ->willReturn(['lines' => 1, 'functions' => 1]);
        $mockCollector->expects($this->atLeastOnce())
                      ->method('enterNode');

        $mockCollector->expects($this->once())
                      ->method('reset');

        $traverser = new AstTraverser();
        $twig = new Environment(new ArrayLoader());
        $parser = new TwigSourceParser($twig);

        $analyzer = new TemplateAnalyzer([$mockCollector], $traverser, $parser);
        $result = $analyzer->analyze($fileInfo);

        $this->assertInstanceOf(AnalysisResult::class, $result);
        $this->assertSame($fileInfo, $result->file);
        $this->assertArrayHasKey('lines', $result->metrics);
        $this->assertArrayHasKey('functions', $result->metrics);
        $this->assertEquals(1, $result->getMetric('lines'));
        $this->assertEquals(1, $result->getMetric('functions'));
        $this->assertIsFloat($result->analysisTime);
        $this->assertGreaterThan(0, $result->analysisTime);
    }

    public function testAnalyzeThrowsExceptionOnReadFileFailure(): void
    {
        $filePath = '/non/existent/path/to/file.twig';
        $fileInfo = new SplFileInfo($filePath, '', 'file.twig');

        $traverser = new AstTraverser();
        $twig = new Environment(new ArrayLoader());
        $parser = new TwigSourceParser($twig);

        $mockCollector = $this->createMock(CollectorInterface::class);

        $analyzer = new TemplateAnalyzer([$mockCollector], $traverser, $parser);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Unable to read file: .*/');

        $analyzer->analyze($fileInfo);
    }
}
