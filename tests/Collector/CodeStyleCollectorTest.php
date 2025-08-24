<?php

declare(strict_types=1);

namespace TwigMetrics\Tests\Collector;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Twig\Node\Node;
use Twig\Source;
use TwigMetrics\Collector\CodeStyleCollector;

#[CoversClass(CodeStyleCollector::class)]
class CodeStyleCollectorTest extends TestCase
{
    private CodeStyleCollector $collector;

    protected function setUp(): void
    {
        $this->collector = new CodeStyleCollector();
    }

    public function testAnalyzesEmptySource(): void
    {
        $source = new Source('', 'empty.twig');
        $data = $this->collector->collect($source);

        $this->assertEquals(1, $data['lines']);
        $this->assertEquals(0, $data['chars']);
        $this->assertEquals(1, $data['blank_lines']);
        $this->assertEquals(0, $data['comment_lines']);
    }

    public function testAnalyzesBasicMetrics(): void
    {
        $template = "Hello World\n{% if user %}Hi {{ user }}!{% endif %}\n\n{# Comment #}";
        $source = new Source($template, 'test.twig');
        $data = $this->collector->collect($source);

        $this->assertEquals(4, $data['lines']);
        $this->assertIsFloat($data['avg_line_length']);
        $this->assertGreaterThan(0, $data['max_line_length']);
        $this->assertEquals(1, $data['blank_lines']);
        $this->assertEquals(1, $data['comment_lines']);
    }

    public function testDetectsTrailingSpaces(): void
    {
        $template = "Line with trailing space \nClean line\nAnother trailing   ";
        $source = new Source($template, 'test.twig');
        $data = $this->collector->collect($source);

        $this->assertEquals(2, $data['trailing_spaces']);
    }

    public function testDetectsIndentationTypes(): void
    {
        $template = "\tTab indented\n    Space indented\n\t  Mixed indentation";
        $source = new Source($template, 'test.twig');
        $data = $this->collector->collect($source);

        $this->assertEquals(1, $data['indentation_tabs']);
        $this->assertEquals(1, $data['indentation_spaces']);
        $this->assertEquals(1, $data['mixed_indentation_lines']);
    }

    public function testExtractsBlockNames(): void
    {
        $template = "{% block content %}Hello{% endblock %}\n{% block header %}Header{% endblock %}";
        $source = new Source($template, 'test.twig');
        $data = $this->collector->collect($source);

        $this->assertContains('content', $data['block_names']);
        $this->assertContains('header', $data['block_names']);
        $this->assertEquals(2, $data['unique_block_names']);
    }

    public function testExtractsVariableNames(): void
    {
        $template = "{{ user }} and {{ name }}\n{{ count | number_format }}";
        $source = new Source($template, 'test.twig');
        $data = $this->collector->collect($source);

        $this->assertContains('user', $data['variable_names']);
        $this->assertContains('name', $data['variable_names']);
        $this->assertContains('count', $data['variable_names']);
        $this->assertGreaterThanOrEqual(2, $data['unique_variable_names']);
    }

    public function testCalculatesCommentDensity(): void
    {
        $template = "{# Comment 1 #}\nCode line\n{# Comment 2 #}\nAnother code line";
        $source = new Source($template, 'test.twig');
        $data = $this->collector->collect($source);

        $this->assertEquals(50.0, $data['comment_density']);
    }

    public function testAnalyzesNamingConventions(): void
    {
        $template = "{% block snake_case_block %}{{ camelCaseVar }}{% endblock %}\n{% block another_block %}{{ another_var }}{% endblock %}";
        $source = new Source($template, 'test.twig');
        $data = $this->collector->collect($source);

        $this->assertArrayHasKey('block_naming_consistency', $data);
        $this->assertArrayHasKey('block_naming_pattern', $data);
        $this->assertArrayHasKey('variable_naming_consistency', $data);
        $this->assertArrayHasKey('variable_naming_pattern', $data);

        $this->assertIsFloat($data['block_naming_consistency']);
        $this->assertIsFloat($data['variable_naming_consistency']);
    }

    public function testCalculatesFormattingConsistencyScore(): void
    {
        $template = "Clean template\n{% if condition %}\n    Well formatted\n{% endif %}";
        $source = new Source($template, 'test.twig');
        $data = $this->collector->collect($source);

        $this->assertArrayHasKey('formatting_consistency_score', $data);
        $this->assertIsFloat($data['formatting_consistency_score']);
        $this->assertGreaterThanOrEqual(0, $data['formatting_consistency_score']);
        $this->assertLessThanOrEqual(100, $data['formatting_consistency_score']);
    }

    public function testHandlesLongLines(): void
    {
        $longLine = str_repeat('x', 150);
        $template = "Short line\n{$longLine}";
        $source = new Source($template, 'test.twig');
        $data = $this->collector->collect($source);

        $this->assertEquals(150, $data['max_line_length']);
        $this->assertLessThan(100, $data['formatting_consistency_score']);
    }

    public function testHandlesPoorCommentDensity(): void
    {
        $template = "{# Comment 1 #}\n{# Comment 2 #}\n{# Comment 3 #}\nOne line of code";
        $source = new Source($template, 'test.twig');
        $data = $this->collector->collect($source);

        $this->assertEquals(75.0, $data['comment_density']);
        $this->assertLessThan(100, $data['formatting_consistency_score']);
    }

    public function testComplexTemplate(): void
    {
        $template = '
{# This is a complex template #}
{% block content %}
    {% if user %}
        Hello {{ user.name }}!
        {% for item in items %}
            <li>{{ item | title }}</li>
        {% endfor %}
    {% endif %}
{% endblock %}

{% block sidebar %}
    {{ include("sidebar.twig") }}
{% endblock %}';

        $source = new Source($template, 'complex.twig');
        $data = $this->collector->collect($source);

        $this->assertGreaterThan(5, $data['lines']);
        $this->assertEquals(2, $data['unique_block_names']);
        $this->assertContains('content', $data['block_names']);
        $this->assertContains('sidebar', $data['block_names']);
        $this->assertArrayHasKey('formatting_consistency_score', $data);
        $this->assertIsFloat($data['formatting_consistency_score']);
    }

    public function testNodeMethodsDoNothing(): void
    {
        $mockNode = $this->createMock(Node::class);

        $this->collector->enterNode($mockNode);
        $this->collector->leaveNode($mockNode);

        $this->expectNotToPerformAssertions();
    }
}
