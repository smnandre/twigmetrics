<?php

declare(strict_types=1);

namespace TwigMetrics\Tests\Collector;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Source;
use TwigMetrics\Collector\ComplexityCollector;
use TwigMetrics\Template\Parser\AstTraverser;

#[CoversClass(ComplexityCollector::class)]
final class ComplexityCollectorTest extends TestCase
{
    private ComplexityCollector $collector;
    private Environment $twig;
    private AstTraverser $traverser;

    protected function setUp(): void
    {
        $this->collector = new ComplexityCollector();
        $this->twig = new Environment(new ArrayLoader());
        $this->traverser = new AstTraverser();
    }

    #[DataProvider('provideTemplates')]
    public function testCollectComplexity(string $template, array $expected): void
    {
        $source = new Source($template, 'test.twig');
        $ast = $this->twig->parse($this->twig->tokenize($source));

        $this->traverser->traverse($ast, [$this->collector]);
        $data = $this->collector->getData();

        $this->assertSame($expected['max_depth'], $data['max_depth']);
        $this->assertSame($expected['conditions'], $data['conditions']);
        $this->assertSame($expected['loops'], $data['loops']);
        $this->assertGreaterThanOrEqual($expected['min_complexity'], $data['complexity_score'] ?? 0);
    }

    public static function provideTemplates(): iterable
    {
        yield 'simple template' => [
            'Hello World',
            ['max_depth' => 0, 'conditions' => 0, 'loops' => 0, 'min_complexity' => 0],
        ];

        yield 'single if' => [
            '{% if user %}Hello{% endif %}',
            ['max_depth' => 1, 'conditions' => 1, 'loops' => 0, 'min_complexity' => 2],
        ];

        yield 'nested if' => [
            '{% if user %}{% if admin %}Admin{% endif %}{% endif %}',
            ['max_depth' => 2, 'conditions' => 2, 'loops' => 0, 'min_complexity' => 4],
        ];

        yield 'for loop' => [
            '{% for item in items %}{{ item }}{% endfor %}',
            ['max_depth' => 1, 'conditions' => 0, 'loops' => 1, 'min_complexity' => 3],
        ];

        yield 'complex nesting' => [
            <<<'TWIG'
            {% if user %}
                {% for item in items %}
                    {% if item.active %}
                        {{ item.name }}
                    {% endif %}
                {% endfor %}
            {% endif %}
            TWIG,
            ['max_depth' => 3, 'conditions' => 2, 'loops' => 1, 'min_complexity' => 10],
        ];

        yield 'ternary operator' => [
            '{{ user ? "Yes" : "No" }}',
            ['max_depth' => 0, 'conditions' => 0, 'loops' => 0, 'min_complexity' => 1],
        ];
    }

    public function testResetClearsData(): void
    {
        $source = new Source('{% if test %}{% endif %}', 'test.twig');
        $ast = $this->twig->parse($this->twig->tokenize($source));

        $this->traverser->traverse($ast, [$this->collector]);
        $data = $this->collector->getData();
        $this->assertGreaterThan(0, $data['complexity_score']);

        $this->collector->reset();
        $data = $this->collector->getData();
        $this->assertSame(0, $data['complexity_score']);
    }

    protected function tearDown(): void
    {
    }
}
