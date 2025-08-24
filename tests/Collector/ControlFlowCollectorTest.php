<?php

declare(strict_types=1);

namespace TwigMetrics\Tests\Collector;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Source;
use TwigMetrics\Collector\ControlFlowCollector;
use TwigMetrics\Template\Parser\AstTraverser;

#[CoversClass(ControlFlowCollector::class)]
class ControlFlowCollectorTest extends TestCase
{
    private ControlFlowCollector $collector;
    private Environment $twig;
    private AstTraverser $traverser;

    protected function setUp(): void
    {
        $this->collector = new ControlFlowCollector();
        $this->twig = new Environment(new ArrayLoader());
        $this->traverser = new AstTraverser();
    }

    public function testInitialState(): void
    {
        $data = $this->collector->getData();

        $this->assertEquals(0, $data['ifs']);
        $this->assertEquals(0, $data['fors']);
        $this->assertEquals(0, $data['blocks']);
        $this->assertEquals(0, $data['macros']);
        $this->assertEquals(0, $data['max_depth']);
        $this->assertEquals([], $data['block_names']);
        $this->assertEquals([], $data['macro_names']);
    }

    public function testCountsIfNodes(): void
    {
        $template = '{% if user %}Hello {{ user }}{% endif %}';
        $this->analyzeTemplate($template);
        $data = $this->collector->getData();

        $this->assertEquals(1, $data['ifs']);
    }

    public function testCountsForNodes(): void
    {
        $template = '{% for item in items %}{{ item }}{% endfor %}';
        $this->analyzeTemplate($template);
        $data = $this->collector->getData();

        $this->assertEquals(1, $data['fors']);
    }

    public function testCountsBlockNodes(): void
    {
        $template = '{% block content %}Hello World{% endblock %}';
        $this->analyzeTemplate($template);
        $data = $this->collector->getData();

        $this->assertEquals(1, $data['blocks']);
        $this->assertEquals(1, $data['unique_blocks']);
        $this->assertEquals(['content'], $data['block_names']);
    }

    public function testCountsMacroNodes(): void
    {
        $template = '{% macro input(name, value) %}<input name="{{ name }}" value="{{ value }}">{% endmacro %}';
        $this->analyzeTemplate($template);
        $data = $this->collector->getData();

        $this->assertEquals(1, $data['macros']);
        $this->assertEquals(['input'], $data['macro_names']);
    }

    public function testCountsSetNodes(): void
    {
        $template = '{% set var = "value" %}{{ var }}';
        $this->analyzeTemplate($template);
        $data = $this->collector->getData();

        $this->assertEquals(1, $data['variables_set']);
    }

    public function testTracksMaxDepth(): void
    {
        $template = '{% if condition %}
            {% for item in items %}
                {% block content %}
                    {{ item }}
                {% endblock %}
            {% endfor %}
        {% endif %}';

        $this->analyzeTemplate($template);
        $data = $this->collector->getData();

        $this->assertGreaterThanOrEqual(2, $data['max_depth']);
        $this->assertLessThanOrEqual(3, $data['max_depth']);
    }

    public function testDetectsNestedLoops(): void
    {
        $template = '{% for outer in outers %}
            {% for inner in inners %}
                {{ outer }}-{{ inner }}
            {% endfor %}
        {% endfor %}';

        $this->analyzeTemplate($template);
        $data = $this->collector->getData();

        $this->assertEquals(2, $data['fors']);
        $this->assertGreaterThan(1, $data['max_depth']);

        $this->assertArrayHasKey('has_nested_loops', $data);
    }

    public function testCountsMultipleStructures(): void
    {
        $template = '
        {% if user %}
            {% for item in items %}
                {% if item.active %}
                    {{ item.name }}
                {% endif %}
            {% endfor %}
        {% endif %}
        
        {% block footer %}
            {% set copyright = "2023" %}
            Footer content
        {% endblock %}
        
        {% macro button(text) %}
            <button>{{ text }}</button>
        {% endmacro %}';

        $this->analyzeTemplate($template);
        $data = $this->collector->getData();

        $this->assertEquals(2, $data['ifs']);
        $this->assertEquals(1, $data['fors']);
        $this->assertEquals(1, $data['blocks']);
        $this->assertEquals(1, $data['macros']);
        $this->assertEquals(1, $data['variables_set']);
        $this->assertEquals(['footer'], $data['block_names']);
        $this->assertEquals(['button'], $data['macro_names']);
    }

    public function testResetClearsAllData(): void
    {
        $template = '{% if condition %}Hello{% endif %}';
        $this->analyzeTemplate($template);

        $data = $this->collector->getData();
        $this->assertEquals(1, $data['ifs']);

        $this->collector->reset();
        $data = $this->collector->getData();

        $this->assertEquals(0, $data['ifs']);
        $this->assertEquals(0, $data['fors']);
        $this->assertEquals(0, $data['blocks']);
        $this->assertEquals(0, $data['max_depth']);
        $this->assertEquals([], $data['block_names']);
    }

    public function testIgnoresPlainContent(): void
    {
        $template = 'Hello World {{ variable }}';
        $this->analyzeTemplate($template);
        $data = $this->collector->getData();

        $this->assertEquals(0, $data['ifs']);
        $this->assertEquals(0, $data['fors']);
        $this->assertEquals(0, $data['blocks']);
        $this->assertEquals(0, $data['macros']);
    }

    private function analyzeTemplate(string $template): void
    {
        $source = new Source($template, 'test.twig');
        $ast = $this->twig->parse($this->twig->tokenize($source));
        $this->traverser->traverse($ast, [$this->collector]);
    }
}
