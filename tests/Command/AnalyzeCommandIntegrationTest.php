<?php

declare(strict_types=1);

namespace TwigMetrics\Tests\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use TwigMetrics\Command\AnalyzeCommand;

/**
 * Integration tests for AnalyzeCommand focusing on complex scenarios
 * and argument parsing edge cases.
 */
#[CoversClass(AnalyzeCommand::class)]
class AnalyzeCommandIntegrationTest extends TestCase
{
    private string $tempDir;
    private Application $application;
    private Command $command;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir().'/twigmetrics_integration_'.uniqid('', true);
        mkdir($this->tempDir);

        $this->createComplexTestStructure();

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

    private function createComplexTestStructure(): void
    {
        $dirs = ['components', 'pages', 'layouts', 'forms', 'emails'];
        foreach ($dirs as $dir) {
            mkdir($this->tempDir.'/'.$dir, 0755, true);
        }

        file_put_contents($this->tempDir.'/components/button.twig', '
<button type="{{ type|default("button") }}" class="{{ classes|join(" ") }}">
    {{ label|escape }}
</button>
');
        file_put_contents($this->tempDir.'/components/card.twig', '
<div class="card">
    {% block header %}
        <h2>{{ title }}</h2>
    {% endblock %}
    {% block content %}{% endblock %}
</div>
');

        file_put_contents($this->tempDir.'/pages/home.twig', '
{% extends "layouts/base.twig" %}

{% block title %}Home{% endblock %}

{% block content %}
    {% for item in items %}
        {% if item.featured %}
            {% include "components/card.twig" with {"title": item.title} %}
        {% endif %}
    {% endfor %}
{% endblock %}
');

        file_put_contents($this->tempDir.'/pages/contact.twig', '
{% extends "layouts/base.twig" %}

{% block content %}
    {% for field in form.fields %}
        {% if field.type == "text" %}
            {{ macro.input(field.name, field.label) }}
        {% else %}
            <textarea name="{{ field.name }}">{{ field.value|default("") }}</textarea>
        {% endif %}
    {% endfor %}
{% endblock %}

{% macro input(name, label) %}
    <label>{{ label }}: <input type="text" name="{{ name }}" /></label>
{% endmacro %}
');

        file_put_contents($this->tempDir.'/layouts/base.twig', '
<!DOCTYPE html>
<html>
<head>
    <title>{% block title %}Default Title{% endblock %}</title>
</head>
<body>
    <header>{% include "components/nav.twig" %}</header>
    <main>{% block content %}{% endblock %}</main>
    <footer>{% include "components/footer.twig" %}</footer>
</body>
</html>
');

        file_put_contents($this->tempDir.'/forms/newsletter.twig', '
<form method="post">
    {% for error in form.errors %}
        <div class="error">{{ error|trans }}</div>
    {% endfor %}
    
    <input type="email" name="email" value="{{ form.email|default("") }}" required>
    <button type="submit">{{ "Subscribe"|trans }}</button>
</form>
');

        file_put_contents($this->tempDir.'/components/nav.twig', '<nav><a href="/">Home</a></nav>');
        file_put_contents($this->tempDir.'/components/footer.twig', '<p>&copy; 2024</p>');

        file_put_contents($this->tempDir.'/emails/welcome.twig', '
<h1>Welcome {{ user.name }}!</h1>
<p>Thanks for signing up.</p>
');
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

    public function testComplexityDimensionWithRealTemplates(): void
    {
        $exitCode = $this->commandTester->execute([
            'target' => 'complexity',
            'path' => $this->tempDir,
        ]);

        $this->assertEquals(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();

        $this->assertStringContainsString('Dimension: complexity', $output);
        $this->assertStringContainsString('LOGICAL COMPLEXITY', $output);
    }

    public function testCallablesDimensionWithMacrosAndFilters(): void
    {
        $exitCode = $this->commandTester->execute([
            'target' => 'callables',
            'path' => $this->tempDir,
        ]);

        $this->assertEquals(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();

        $this->assertStringContainsString('Dimension: callables', $output);
        $this->assertStringContainsString('TWIG CALLABLES', $output);
    }

    public function testArchitectureDimensionWithLayeredStructure(): void
    {
        $exitCode = $this->commandTester->execute([
            'target' => 'architecture',
            'path' => $this->tempDir,
        ]);

        $this->assertEquals(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();

        $this->assertStringContainsString('Dimension: architecture', $output);
        $this->assertStringContainsString('ARCHITECTURE', $output);
    }

    public function testArgumentParsingComplexCases(): void
    {
        $exitCode = $this->commandTester->execute([
            'target' => 'template-files',
            'path' => $this->tempDir,
        ]);

        $this->assertEquals(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Dimension: template-files', $output);

        $exitCode = $this->commandTester->execute([
            'target' => $this->tempDir.'/components',
        ]);

        $this->assertEquals(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('components', $output);
    }

    public function testAllDimensionsWithComplexStructure(): void
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
                "Dimension '{$dimension}' should work with complex structure"
            );

            $output = $this->commandTester->getDisplay();
            $this->assertStringContainsString("Dimension: {$dimension}", $output);
        }
    }

    public function testPerformanceWithManyTemplates(): void
    {
        for ($i = 1; $i <= 20; ++$i) {
            file_put_contents($this->tempDir."/generated_template_{$i}.twig", '
{% for item in items %}
    {% if item.active %}
        <div>{{ item.name|escape }}</div>
    {% endif %}
{% endfor %}
');
        }

        $start = microtime(true);

        $exitCode = $this->commandTester->execute([
            'target' => $this->tempDir,
        ]);

        $duration = microtime(true) - $start;

        $this->assertEquals(Command::SUCCESS, $exitCode);
        $this->assertLessThan(10.0, $duration, 'Analysis should complete in under 10 seconds');
    }

    public function testErrorHandlingWithComplexStructure(): void
    {
        $exitCode = $this->commandTester->execute([
            'target' => $this->tempDir.'/nonexistent',
        ]);

        $this->assertEquals(Command::FAILURE, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('directory not found', strtolower($output));
    }
}
