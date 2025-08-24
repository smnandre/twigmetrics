<?php

declare(strict_types=1);

namespace TwigMetrics\Tests\Analyzer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use TwigMetrics\Analyzer\AnalysisResult;
use TwigMetrics\Template\TemplateFinder;

#[CoversClass(AnalysisResult::class)]
class AnalysisResultTest extends TestCase
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

    public function testConstructorAndGetters(): void
    {
        $filePath = $this->tempDir.'/template.twig';
        file_put_contents($filePath, 'dummy content');
        $file = new \SplFileInfo($filePath);
        $metrics = ['lines' => 10, 'functions' => 2];
        $analysisTime = 0.05;

        $result = new AnalysisResult($file, $metrics, $analysisTime);

        $this->assertSame($file, $result->file);
        $this->assertSame($metrics, $result->metrics);
        $this->assertSame($analysisTime, $result->analysisTime);
    }

    public function testGetRelativePath(): void
    {
        $filePath = $this->tempDir.'/template.html.twig';
        file_put_contents($filePath, 'dummy content');

        $finder = new TemplateFinder();
        $templates = iterator_to_array($finder->find($this->tempDir));

        $this->assertNotEmpty($templates, 'TemplateFinder should find at least one template.');
        $file = array_values($templates)[0];

        $result = new AnalysisResult($file, [], 0.0);

        $this->assertSame('template.html.twig', $result->getRelativePath());
    }

    public function testGetMetric(): void
    {
        $file = new \SplFileInfo($this->tempDir.'/dummy.twig');
        $metrics = ['lines' => 10, 'functions' => 2];
        $result = new AnalysisResult($file, $metrics, 0.0);

        $this->assertSame(10, $result->getMetric('lines'));
        $this->assertSame(2, $result->getMetric('functions'));
        $this->assertNull($result->getMetric('non_existent_metric'));
    }

    public function testGetData(): void
    {
        $file = new \SplFileInfo($this->tempDir.'/dummy.twig');
        $metrics = ['lines' => 10, 'functions' => 2];
        $result = new AnalysisResult($file, $metrics, 0.0);

        $this->assertSame($metrics, $result->getData());
    }
}
