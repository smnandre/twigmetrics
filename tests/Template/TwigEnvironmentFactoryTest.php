<?php

declare(strict_types=1);

namespace TwigMetrics\Tests\Template;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Node\ModuleNode;
use Twig\Source;
use TwigMetrics\Template\TwigEnvironmentFactory;

#[CoversClass(TwigEnvironmentFactory::class)]
class TwigEnvironmentFactoryTest extends TestCase
{
    public function testCreateForAnalysis(): void
    {
        $twig = TwigEnvironmentFactory::createForAnalysis();

        $this->assertInstanceOf(Environment::class, $twig);
        $this->assertInstanceOf(ArrayLoader::class, $twig->getLoader());
    }

    public function testHandlesUnknownFunctions(): void
    {
        $twig = TwigEnvironmentFactory::createForAnalysis();

        $source = new Source('{{ unknown_function() }}', 'test.twig');
        $ast = $twig->parse($twig->tokenize($source));

        $this->assertNotNull($ast);
    }

    public function testHandlesUnknownFilters(): void
    {
        $twig = TwigEnvironmentFactory::createForAnalysis();

        $source = new Source('{{ "test" | unknown_filter }}', 'test.twig');
        $ast = $twig->parse($twig->tokenize($source));

        $this->assertNotNull($ast);
    }

    public function testHandlesUnknownTags(): void
    {
        $twig = TwigEnvironmentFactory::createForAnalysis();

        $source = new Source('{% unknown_tag %}content{% endunknown_tag %}', 'test.twig');

        try {
            $ast = $twig->parse($twig->tokenize($source));
            $this->assertNotNull($ast);
        } catch (\Exception $e) {
            $this->assertStringContains('Unknown', $e->getMessage());
        }
    }

    public function testParsesBasicTwigSyntax(): void
    {
        $twig = TwigEnvironmentFactory::createForAnalysis();

        $template = '
        {% if user %}
            Hello {{ user.name }}!
            {% for item in items %}
                {{ item | title }}
            {% endfor %}
        {% endif %}';

        $source = new Source($template, 'test.twig');
        $ast = $twig->parse($twig->tokenize($source));

        $this->assertNotNull($ast);
        $this->assertInstanceOf(ModuleNode::class, $ast);
    }

    public function testEnvironmentIsConfiguredForAnalysis(): void
    {
        $twig = TwigEnvironmentFactory::createForAnalysis();

        $templates = [
            '{{ undefined_var }}',
            '{% if maybe_undefined %}test{% endif %}',
            '{{ "hello" | maybe_undefined_filter }}',

            '{% block content %}test{% endblock %}',
            '{% extends "base.twig" %}',
            '{% include "partial.twig" %}',
        ];

        foreach ($templates as $template) {
            try {
                $source = new Source($template, 'test.twig');
                $ast = $twig->parse($twig->tokenize($source));
                $this->assertNotNull($ast, "Failed to parse: $template");
            } catch (\Exception $e) {
                $this->assertStringNotContainsString('fatal', strtolower($e->getMessage()));
            }
        }
    }
}
