<?php

declare(strict_types=1);

namespace TwigMetrics\Tests\Collector;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Source;
use TwigMetrics\Collector\InheritanceCollector;
use TwigMetrics\Template\Parser\AstTraverser;

#[CoversClass(InheritanceCollector::class)]
class InheritanceCollectorTest extends TestCase
{
    private InheritanceCollector $collector;
    private Environment $twig;
    private AstTraverser $traverser;

    protected function setUp(): void
    {
        $this->collector = new InheritanceCollector();
        $this->twig = new Environment(new ArrayLoader());
        $this->traverser = new AstTraverser();
    }

    public function testInitialState(): void
    {
        $data = $this->collector->getData();

        $this->assertEquals(0, $data['extends']);
        $this->assertEquals(0, $data['includes']);
        $this->assertEquals(0, $data['embeds']);
        $this->assertEquals(0, $data['imports']);
        $this->assertEquals([], $data['extends_from']);
        $this->assertEquals([], $data['includes_templates']);
        $this->assertEquals([], $data['embeds_templates']);
    }

    public function testCountsExtendsInTemplate(): void
    {
        $template = '{% extends "base.twig" %}{% block content %}Hello World{% endblock %}';
        $this->analyzeTemplate($template);
        $data = $this->collector->getData();

        $this->assertEquals(1, $data['extends']);
        $this->assertEquals(['base.twig'], $data['extends_from']);
    }

    public function testCountsIncludeStatements(): void
    {
        $template = '{% include "header.twig" %}Main content{% include "footer.twig" %}';
        $this->analyzeTemplate($template);
        $data = $this->collector->getData();

        $this->assertEquals(2, $data['includes']);
        $this->assertContains('header.twig', $data['includes_templates']);
        $this->assertContains('footer.twig', $data['includes_templates']);
    }

    public function testCountsIncludeFunctions(): void
    {
        $template = 'Start {{ include("content.twig") }} End';
        $this->analyzeTemplate($template);
        $data = $this->collector->getData();

        $this->assertEquals(1, $data['includes']);
        $this->assertEquals(['content.twig'], $data['includes_templates']);
    }

    public function testCountsImportStatements(): void
    {
        $template = '{% import "macros.twig" as macros %}{{ macros.input() }}';
        $this->analyzeTemplate($template);
        $data = $this->collector->getData();

        $this->assertEquals(1, $data['imports']);
    }

    public function testCountsAllInheritanceTypes(): void
    {
        $template = '
        {% extends "layout.twig" %}
        {% import "macros.twig" as m %}
        {% block content %}
            {% include "header.twig" %}
            Main content
            {{ include("sidebar.twig") }}
            {% include "footer.twig" %}
        {% endblock %}';

        $this->analyzeTemplate($template);
        $data = $this->collector->getData();

        $this->assertEquals(1, $data['extends']);
        $this->assertEquals(1, $data['imports']);
        $this->assertEquals(3, $data['includes']);

        $this->assertEquals(['layout.twig'], $data['extends_from']);
        $this->assertContains('header.twig', $data['includes_templates']);
        $this->assertContains('footer.twig', $data['includes_templates']);
    }

    public function testTracksTopIncludes(): void
    {
        $template = '
        {% include "header.twig" %}
        {% include "sidebar.twig" %}
        {% include "header.twig" %}
        {{ include("header.twig") }}
        {% include "footer.twig" %}';

        $this->analyzeTemplate($template);
        $data = $this->collector->getData();

        $this->assertEquals(5, $data['includes']);

        $topIncludes = $data['top_includes_templates'];
        $this->assertEquals(3, $topIncludes['header.twig']);
        $this->assertEquals(1, $topIncludes['sidebar.twig']);
        $this->assertEquals(1, $topIncludes['footer.twig']);
    }

    public function testCountsEmbedStatements(): void
    {
        $template = '{% embed "card.twig" %}{% endembed %}';
        $this->analyzeTemplate($template);
        $data = $this->collector->getData();

        $this->assertEquals(1, $data['embeds']);
        $this->assertEquals(['test.twig'], $data['embeds_templates']);
    }

    public function testTracksTopEmbeds(): void
    {
        $template = '{% embed "card.twig" %}{% endembed %}{% embed "modal.twig" %}{% endembed %}{% embed "alert.twig" %}{% endembed %}';
        $this->analyzeTemplate($template);
        $data = $this->collector->getData();

        $this->assertEquals(3, $data['embeds']);
        $topEmbeds = $data['top_embeds'];
        $this->assertEquals(3, $topEmbeds['test.twig']);
    }

    public function testIgnoresPlainContent(): void
    {
        $template = 'Hello World {{ variable }} <p>Some HTML</p>';
        $this->analyzeTemplate($template);
        $data = $this->collector->getData();

        $this->assertEquals(0, $data['extends']);
        $this->assertEquals(0, $data['includes']);
        $this->assertEquals(0, $data['embeds']);
        $this->assertEquals(0, $data['imports']);
    }

    public function testResetClearsAllData(): void
    {
        $template = '{% extends "base.twig" %}{% block content %}{% include "header.twig" %}{% endblock %}';
        $this->analyzeTemplate($template);

        $data = $this->collector->getData();
        $this->assertEquals(1, $data['extends']);
        $this->assertEquals(1, $data['includes']);

        $this->collector->reset();
        $data = $this->collector->getData();

        $this->assertEquals(0, $data['extends']);
        $this->assertEquals(0, $data['includes']);
        $this->assertEquals([], $data['extends_from']);
        $this->assertEquals([], $data['includes_templates']);
        $this->assertEquals([], $data['embeds_templates']);
    }

    private function analyzeTemplate(string $template): void
    {
        $source = new Source($template, 'test.twig');
        $ast = $this->twig->parse($this->twig->tokenize($source));
        $this->traverser->traverse($ast, [$this->collector]);
    }
}
