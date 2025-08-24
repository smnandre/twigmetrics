<?php

declare(strict_types=1);

namespace TwigMetrics\Tests\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use TwigMetrics\Command\AnalyzeCommand;

#[CoversClass(AnalyzeCommand::class)]
class AnalyzeCommandTest extends TestCase
{
    private string $tempDir;
    private Application $application;
    private Command $command;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir().'/twigmetrics_test_'.uniqid('', true);
        mkdir($this->tempDir);

        $this->createTestTemplates();

        $this->application = new Application();
        $this->command = new AnalyzeCommand();
        $this->application->add($this->command);
        $this->commandTester = new CommandTester($this->command);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }
    }

    private function createTestTemplates(): void
    {
        file_put_contents($this->tempDir.'/simple.twig', '{{ message }}');
        file_put_contents($this->tempDir.'/complex.twig', '
{% extends "base.twig" %}
{% block content %}
    {% for user in users %}
        {% if user.active %}
            <p>{{ user.name|upper }}</p>
        {% endif %}
    {% endfor %}
{% endblock %}
');

        mkdir($this->tempDir.'/components');
        file_put_contents($this->tempDir.'/components/button.twig', '<button class="{{ class }}">{{ text }}</button>');
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

    public function testDefaultExecution(): void
    {
        $exitCode = $this->commandTester->execute([
            'command' => $this->command->getName(),
            'target' => $this->tempDir,
        ]);

        $this->assertEquals(Command::SUCCESS, $exitCode);
        $this->commandTester->assertCommandIsSuccessful();

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Path:', $output);
        $this->assertStringContainsString('Found 3 template(s) to analyze', $output);
    }

    public function testDimensionComplexity(): void
    {
        $this->commandTester->execute([
            'target' => 'complexity',
            'path' => $this->tempDir,
        ]);

        $this->commandTester->assertCommandIsSuccessful();

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Dimension: complexity', $output);
    }

    public function testDimensionTemplateFiles(): void
    {
        $this->commandTester->execute([
            'target' => 'template-files',
            'path' => $this->tempDir,
        ]);

        $this->commandTester->assertCommandIsSuccessful();

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Dimension: template-files', $output);
    }

    public function testDimensionCodeStyle(): void
    {
        $this->commandTester->execute([
            'target' => 'code-style',
            'path' => $this->tempDir,
        ]);

        $this->commandTester->assertCommandIsSuccessful();

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Dimension: code-style', $output);
    }

    public function testNonExistentDirectoryError(): void
    {
        $exitCode = $this->commandTester->execute([
            'target' => '/non/existent/directory',
        ]);

        $this->assertEquals(Command::FAILURE, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('directory not found', strtolower($output));
    }

    public function testInvalidDimensionError(): void
    {
        $exitCode = $this->commandTester->execute([
            'target' => 'invalid-dimension',
            'path' => $this->tempDir,
        ]);

        $this->assertEquals(Command::FAILURE, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('directory not found', strtolower($output));
    }

    public function testEmptyDirectoryHandling(): void
    {
        $emptyDir = sys_get_temp_dir().'/twigmetrics_empty_'.uniqid('', true);
        mkdir($emptyDir);

        $exitCode = $this->commandTester->execute([
            'target' => $emptyDir,
        ]);

        $this->assertEquals(Command::FAILURE, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('No Twig templates found', $output);

        rmdir($emptyDir);
    }

    public function testAllValidDimensions(): void
    {
        $dimensions = [
            'template-files',
            'complexity',
            'callables',
            'code-style',
            'architecture',
            'maintainability',
        ];

        foreach ($dimensions as $dimension) {
            $exitCode = $this->commandTester->execute([
                'target' => $dimension,
                'path' => $this->tempDir,
            ]);

            $this->assertEquals(
                Command::SUCCESS,
                $exitCode,
                "Dimension '{$dimension}' should work correctly"
            );
        }
    }

    public function testArgumentParsing(): void
    {
        $this->commandTester->execute([
            'target' => 'complexity',
            'path' => $this->tempDir,
        ]);

        $this->commandTester->assertCommandIsSuccessful();
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Dimension: complexity', $output);

        $this->commandTester->execute([
            'target' => $this->tempDir,
        ]);

        $this->commandTester->assertCommandIsSuccessful();
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString($this->tempDir, $output);
    }
}
