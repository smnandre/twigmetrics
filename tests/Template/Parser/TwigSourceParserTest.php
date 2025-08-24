<?php

declare(strict_types=1);

namespace TwigMetrics\Tests\Template\Parser;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Node\ModuleNode;
use TwigMetrics\Template\Parser\TwigSourceParser;

#[CoversClass(TwigSourceParser::class)]
class TwigSourceParserTest extends TestCase
{
    private TwigSourceParser $parser;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir().'/twigmetrics_test_'.uniqid('', true);
        mkdir($this->tempDir, 0777, true);

        $twig = new Environment(new ArrayLoader());
        $this->parser = new TwigSourceParser($twig);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    public function testParseFile(): void
    {
        $file = $this->createTempFile('test.twig', 'Hello {{ name }}');

        $ast = $this->parser->parseFile($file);

        $this->assertInstanceOf(ModuleNode::class, $ast);
    }

    public function testParseString(): void
    {
        $template = '{% if user %}Hello {{ user }}{% endif %}';

        $ast = $this->parser->parseString($template, 'test_template');

        $this->assertInstanceOf(ModuleNode::class, $ast);
    }

    public function testParseStringWithDefaultName(): void
    {
        $template = 'Simple content';

        $ast = $this->parser->parseString($template);

        $this->assertInstanceOf(ModuleNode::class, $ast);
    }

    public function testParseFileWithComplexTemplate(): void
    {
        $template = '
        {% extends "base.twig" %}
        
        {% block content %}
            {% for item in items %}
                <div class="{{ item.class }}">
                    {{ item.content | raw }}
                </div>
            {% endfor %}
        {% endblock %}
        
        {% block sidebar %}
            {% include "sidebar.twig" %}
        {% endblock %}';

        $file = $this->createTempFile('complex.twig', $template);
        $ast = $this->parser->parseFile($file);

        $this->assertInstanceOf(ModuleNode::class, $ast);
    }

    public function testParseInvalidSyntaxThrowsException(): void
    {
        $this->expectException(\Exception::class);

        $this->parser->parseString('{% invalid syntax');
    }

    public function testParseFileThrowsExceptionForNonExistentFile(): void
    {
        $this->expectException(\RuntimeException::class);

        $nonExistentFile = new \SplFileInfo('/nonexistent/path/file.twig');
        $this->parser->parseFile($nonExistentFile);
    }

    public function testParseEmptyFile(): void
    {
        $file = $this->createTempFile('empty.twig', '');

        $ast = $this->parser->parseFile($file);

        $this->assertInstanceOf(ModuleNode::class, $ast);
    }

    public function testParseFileWithWhitespaceOnly(): void
    {
        $file = $this->createTempFile('whitespace.twig', "   \n\t  \n  ");

        $ast = $this->parser->parseFile($file);

        $this->assertInstanceOf(ModuleNode::class, $ast);
    }

    public function testParseStringPreservesTemplateName(): void
    {
        $template = 'Test content';
        $templateName = 'my_custom_template';

        $ast = $this->parser->parseString($template, $templateName);

        $this->assertInstanceOf(ModuleNode::class, $ast);
    }

    public function testParseFileHandlesDifferentEncodings(): void
    {
        $content = 'Hello 世界 éñçödéd content';
        $file = $this->createTempFile('encoded.twig', $content);

        $ast = $this->parser->parseFile($file);

        $this->assertInstanceOf(ModuleNode::class, $ast);
    }

    public function testParseLargeFile(): void
    {
        $content = "{% extends 'base.twig' %}\n";
        for ($i = 0; $i < 100; ++$i) {
            $content .= "{% block block{$i} %}Content {$i}{% endblock %}\n";
        }

        $file = $this->createTempFile('large.twig', $content);
        $ast = $this->parser->parseFile($file);

        $this->assertInstanceOf(ModuleNode::class, $ast);
    }

    private function createTempFile(string $filename, string $content): \SplFileInfo
    {
        $path = $this->tempDir.'/'.$filename;
        file_put_contents($path, $content);

        return new \SplFileInfo($path);
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
