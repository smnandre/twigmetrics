<?php

declare(strict_types=1);

namespace TwigMetrics\Tests\Template\Parser;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Error\SyntaxError;
use Twig\Loader\ArrayLoader;
use Twig\Node\ModuleNode;
use Twig\Parser;
use Twig\Source;
use TwigMetrics\Template\Parser\TwigParser;

#[CoversClass(TwigParser::class)]
class TwigParserTest extends TestCase
{
    private TwigParser $parser;
    private Environment $twig;

    protected function setUp(): void
    {
        $this->twig = new Environment(new ArrayLoader());
        $this->parser = new TwigParser($this->twig);
    }

    public function testExtendsDefaultTwigParser(): void
    {
        $this->assertInstanceOf(Parser::class, $this->parser);
    }

    public function testShouldIgnoreUnknownTwigCallables(): void
    {
        $result = $this->parser->shouldIgnoreUnknownTwigCallables();

        $this->assertIsBool($result);
        $this->assertFalse($result);
    }

    public function testCanBeUsedWithTwigEnvironment(): void
    {
        $this->twig->setParser($this->parser);

        $source = new Source('Hello {{ name }}', 'test.twig');
        $ast = $this->parser->parse($this->twig->tokenize($source));

        $this->assertInstanceOf(ModuleNode::class, $ast);
    }

    public function testInheritsParserFunctionality(): void
    {
        $this->twig->setParser($this->parser);

        $templates = [
            'Simple text',
            '{{ variable }}',
            '{% if condition %}text{% endif %}',
            '{% for item in items %}{{ item }}{% endfor %}',
            '{% block content %}Hello{% endblock %}',
        ];

        foreach ($templates as $template) {
            $source = new Source($template, 'test.twig');
            $ast = $this->parser->parse($this->twig->tokenize($source));

            $this->assertInstanceOf(ModuleNode::class, $ast, "Failed to parse: $template");
        }
    }

    public function testWorksWithComplexTemplates(): void
    {
        $this->twig->setParser($this->parser);

        $complexTemplate = '
        {% extends "base.twig" %}
        {% import "macros.twig" as macros %}
        
        {% block content %}
            {% for user in users %}
                {% if user.active %}
                    <div class="user">
                        {{ macros.user_info(user) }}
                        {% include "user_actions.twig" with {"user": user} %}
                    </div>
                {% endif %}
            {% endfor %}
        {% endblock %}
        ';

        $source = new Source($complexTemplate, 'complex.twig');
        $ast = $this->parser->parse($this->twig->tokenize($source));

        $this->assertInstanceOf(ModuleNode::class, $ast);
    }

    public function testCanHandleParsingErrors(): void
    {
        $this->twig->setParser($this->parser);

        $this->expectException(SyntaxError::class);

        $invalidTemplate = '{% if condition %} missing endif';
        $source = new Source($invalidTemplate, 'invalid.twig');
        $this->parser->parse($this->twig->tokenize($source));
    }

    public function testClassStructure(): void
    {
        $reflection = new \ReflectionClass(TwigParser::class);

        $this->assertTrue($reflection->hasMethod('shouldIgnoreUnknownTwigCallables'));
        $this->assertTrue($reflection->getMethod('shouldIgnoreUnknownTwigCallables')->isPublic());

        $this->assertEquals('Twig\Parser', $reflection->getParentClass()->getName());
    }
}
