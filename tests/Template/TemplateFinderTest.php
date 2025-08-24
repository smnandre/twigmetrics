<?php

declare(strict_types=1);

namespace TwigMetrics\Tests\Template;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use TwigMetrics\Template\TemplateFinder;

#[CoversClass(TemplateFinder::class)]
class TemplateFinderTest extends TestCase
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

    public function testFindTemplates(): void
    {
        file_put_contents($this->tempDir.'/template1.html.twig', 'content');
        file_put_contents($this->tempDir.'/template2.twig', 'content');
        file_put_contents($this->tempDir.'/not_a_template.txt', 'content');
        mkdir($this->tempDir.'/subdir');
        file_put_contents($this->tempDir.'/subdir/template3.html.twig', 'content');

        $finder = new TemplateFinder();
        $templates = iterator_to_array($finder->find($this->tempDir));

        $this->assertCount(3, $templates);

        $foundNames = array_map(fn ($file) => $file->getFilename(), $templates);

        $htmlTwigTemplates = array_filter(
            $foundNames,
            fn (string $name): bool => str_ends_with($name, '.html.twig')
        );
        $twigTemplates = array_filter(
            $foundNames,
            fn (string $name): bool => str_ends_with($name, '.twig') && !str_ends_with($name, '.html.twig')
        );

        $this->assertCount(2, $htmlTwigTemplates);
        $this->assertCount(1, $twigTemplates);

        sort($foundNames);

        $this->assertSame([
            'template1.html.twig',
            'template2.twig',
            'template3.html.twig',
        ], $foundNames);
    }

    public function testFindTemplatesWithInvalidPath(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Path ".*" is not a valid directory/');

        $finder = new TemplateFinder();
        $finder->find($this->tempDir.'/non_existent_dir');
    }
}
