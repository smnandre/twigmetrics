<?php

declare(strict_types=1);

namespace TwigMetrics\Tests\Collector;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Source;
use TwigMetrics\Collector\RelationshipCollector;
use TwigMetrics\Template\Parser\AstTraverser;

#[CoversClass(RelationshipCollector::class)]
class RelationshipCollectorTest extends TestCase
{
    private RelationshipCollector $collector;
    private Environment $twig;
    private AstTraverser $traverser;

    protected function setUp(): void
    {
        $this->collector = new RelationshipCollector();
        $this->twig = new Environment(new ArrayLoader());
        $this->traverser = new AstTraverser();
    }

    public function testInitialState(): void
    {
        $data = $this->collector->getData();

        $this->assertEquals([], $data['dependencies']);
        $this->assertEquals([], $data['provided_blocks']);
        $this->assertEquals([], $data['used_blocks']);
        $this->assertEquals(0, $data['inheritance_depth']);
        $this->assertIsInt($data['coupling_score']);
        $this->assertIsInt($data['reusability_score']);
        $this->assertIsArray($data['dependency_types']);
    }

    public function testTracksDependencies(): void
    {
        $template = '{% extends "base.twig" %}{% import "macros.twig" as m %}{% block content %}{% include "header.twig" %}{% endblock %}';
        $this->analyzeTemplate($template);
        $data = $this->collector->getData();

        $this->assertCount(3, $data['dependencies']);

        $dependencyTypes = $data['dependency_types'];
        $this->assertEquals(1, $dependencyTypes['extends']);
        $this->assertEquals(1, $dependencyTypes['includes']);
        $this->assertEquals(1, $dependencyTypes['imports']);
    }

    public function testTracksBlockRelationships(): void
    {
        $template = '{% extends "base.twig" %}{% block content %}{{ parent() }}Hello{% endblock %}{% block footer %}Footer{% endblock %}';
        $this->analyzeTemplate($template);
        $data = $this->collector->getData();

        $this->assertContains('content', $data['provided_blocks']);
        $this->assertContains('footer', $data['provided_blocks']);
        $this->assertCount(2, $data['provided_blocks']);
    }

    public function testTracksIncludeFunctions(): void
    {
        $template = 'Start {{ include("content.twig") }} End';
        $this->analyzeTemplate($template);
        $data = $this->collector->getData();

        $this->assertCount(1, $data['dependencies']);
        $this->assertEquals('includes_function', $data['dependencies'][0]['type']);
        $this->assertEquals('content.twig', $data['dependencies'][0]['template']);
    }

    public function testCalculatesCouplingScore(): void
    {
        $template = '{% extends "base.twig" %}{% block content %}{% include "header.twig" %}{% include "footer.twig" %}{% endblock %}';
        $this->analyzeTemplate($template);
        $data = $this->collector->getData();

        $this->assertGreaterThan(0, $data['coupling_score']);
        $this->assertIsInt($data['coupling_score']);
    }

    public function testCalculatesReusabilityScore(): void
    {
        $template = '{% block content %}Reusable content{% endblock %}{% block sidebar %}Sidebar{% endblock %}';
        $this->analyzeTemplate($template);
        $data = $this->collector->getData();

        $this->assertIsInt($data['reusability_score']);
        $this->assertGreaterThanOrEqual(0, $data['reusability_score']);
    }

    public function testResetClearsAllData(): void
    {
        $template = '{% extends "base.twig" %}{% block content %}Test{% endblock %}';
        $this->analyzeTemplate($template);

        $data = $this->collector->getData();
        $this->assertNotEmpty($data['dependencies']);
        $this->assertNotEmpty($data['provided_blocks']);

        $this->collector->reset();
        $data = $this->collector->getData();

        $this->assertEquals([], $data['dependencies']);
        $this->assertEquals([], $data['provided_blocks']);
        $this->assertEquals([], $data['used_blocks']);
        $this->assertEquals(0, $data['inheritance_depth']);
    }

    public function testComplexTemplate(): void
    {
        $template = '
        {% extends "layout.twig" %}
        {% import "forms.twig" as forms %}

        {% block content %}
            {% include "header.twig" %}
            {{ include("sidebar.twig") }}
            {{ forms.input("name") }}
        {% endblock %}

        {% block footer %}
            {% include "footer.twig" %}
        {% endblock %}';

        $this->analyzeTemplate($template);
        $data = $this->collector->getData();

        $this->assertGreaterThanOrEqual(4, count($data['dependencies']));
        $this->assertContains('content', $data['provided_blocks']);
        $this->assertContains('footer', $data['provided_blocks']);
        $this->assertGreaterThan(0, $data['coupling_score']);
        $this->assertEquals(1, $data['inheritance_depth']);
    }

    public function testMultiLevelInheritanceDepth(): void
    {
        $resolver = static function (string $template): ?string {
            return [
                'child.twig' => 'parent.twig',
                'parent.twig' => 'base.twig',
                'base.twig' => null,
            ][$template] ?? null;
        };

        $this->collector->setDependencyResolver($resolver);
        $this->collector->setCurrentTemplate('child.twig');

        $template = '{% extends "parent.twig" %}{% block content %}{% endblock %}';
        $this->analyzeTemplate($template, 'child.twig');
        $data = $this->collector->getData();

        $this->assertEquals(2, $data['inheritance_depth']);
    }

    private function analyzeTemplate(string $template, string $name = 'test.twig'): void
    {
        $source = new Source($template, $name);
        $ast = $this->twig->parse($this->twig->tokenize($source));
        $this->traverser->traverse($ast, [$this->collector]);
    }
}
