<?php

declare(strict_types=1);

namespace TwigMetrics\Tests\Collector;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Node\Expression\FilterExpression;
use Twig\Node\Expression\FunctionExpression;
use Twig\Node\Expression\TestExpression;
use Twig\Source;
use TwigMetrics\Collector\FunctionCollector;
use TwigMetrics\Template\Parser\AstTraverser;
use TwigMetrics\Template\TwigEnvironmentFactory;

#[CoversClass(FunctionCollector::class)]
final class FunctionCollectorTest extends TestCase
{
    private FunctionCollector $collector;
    private Environment $twig;
    private AstTraverser $traverser;

    protected function setUp(): void
    {
        $this->collector = new FunctionCollector();
        $this->twig = TwigEnvironmentFactory::createForAnalysis();
        $this->traverser = new AstTraverser();
    }

    public function testCollectDirectly(): void
    {
        $functionNode = $this->createMock(FunctionExpression::class);
        $functionNode->method('hasAttribute')
                     ->with('name')
                     ->willReturn(true);
        $functionNode->method('getAttribute')
                     ->with('name')
                     ->willReturn('path');
        $this->collector->enterNode($functionNode);
        $data = $this->collector->getData();
        $this->assertSame(1, $data['functions']);
        $this->assertSame(1, $data['unique_functions']);
        $this->assertSame(['path' => 1], $data['functions_detail']);

        $filterNode = $this->createMock(FilterExpression::class);
        $filterNode->method('hasAttribute')
                   ->with('name')
                   ->willReturn(true);
        $filterNode->method('getAttribute')
                   ->with('name')
                   ->willReturn('upper');
        $this->collector->enterNode($filterNode);
        $data = $this->collector->getData();
        $this->assertSame(1, $data['filters']);
        $this->assertSame(1, $data['unique_filters']);
        $this->assertSame(['upper' => 1], $data['filters_detail']);

        $testNode = $this->createMock(TestExpression::class);
        $testNode->method('hasAttribute')
                 ->with('name')
                 ->willReturn(true);
        $testNode->method('getAttribute')
                 ->with('name')
                 ->willReturn('empty');
        $this->collector->enterNode($testNode);
        $data = $this->collector->getData();
        $this->assertSame(1, $data['tests']);
        $this->assertSame(1, $data['unique_tests']);
        $this->assertSame(['empty' => 1], $data['tests_detail']);
    }

    #[DataProvider('provideTemplates')]
    public function testCollectFunctionsAndFilters(string $template, array $expected): void
    {
        $this->collector->reset();
        $source = new Source($template, 'test.twig');
        $ast = $this->twig->parse($this->twig->tokenize($source));

        $this->traverser->traverse($ast, [$this->collector]);
        $data = $this->collector->getData();

        $this->assertSame($expected['functions'], $data['functions'] ?? 0, 'functions count mismatch');
        $this->assertSame($expected['unique_functions'], $data['unique_functions'] ?? 0, 'unique functions count mismatch');
        $this->assertSame($expected['filters'], $data['filters'] ?? 0, 'filters count mismatch');
        $this->assertSame($expected['unique_filters'], $data['unique_filters'] ?? 0, 'unique filters count mismatch');
        $this->assertSame($expected['tests'], $data['tests'] ?? 0, 'tests count mismatch');
        $this->assertSame($expected['unique_tests'], $data['unique_tests'] ?? 0, 'unique tests count mismatch');

        if (isset($expected['functions_detail'])) {
            $this->assertEquals($expected['functions_detail'], $data['functions_detail'] ?? [], 'functions detail mismatch');
        }
        if (isset($expected['filters_detail'])) {
            $this->assertEquals($expected['filters_detail'], $data['filters_detail'] ?? [], 'filters detail mismatch');
        }
        if (isset($expected['tests_detail'])) {
            $this->assertEquals($expected['tests_detail'], $data['tests_detail'] ?? [], 'tests detail mismatch');
        }
    }

    public static function provideTemplates(): iterable
    {
        yield 'no functions or filters' => [
            'Hello World',
            [
                'functions' => 0, 'unique_functions' => 0, 'functions_detail' => [],
                'filters' => 0, 'unique_filters' => 0, 'filters_detail' => [],
                'tests' => 0, 'unique_tests' => 0, 'tests_detail' => [],
            ],
        ];

        yield 'single function' => [
            '{{ path("home") }}',
            [
                'functions' => 1, 'unique_functions' => 1, 'functions_detail' => ['path' => 1],
                'filters' => 1, 'unique_filters' => 1, 'filters_detail' => ['escape' => 1],
                'tests' => 0, 'unique_tests' => 0, 'tests_detail' => [],
            ],
        ];

        yield 'multiple functions, one duplicate' => [
            '{{ path("home") }}{{ dump() }}{{ path("about") }}',
            [
                'functions' => 3, 'unique_functions' => 2, 'functions_detail' => ['path' => 2, 'dump' => 1],
                'filters' => 3, 'unique_filters' => 1, 'filters_detail' => ['escape' => 3],
                'tests' => 0, 'unique_tests' => 0, 'tests_detail' => [],
            ],
        ];

        yield 'single filter' => [
            '{{ "hello"|upper }}',
            [
                'functions' => 0, 'unique_functions' => 0, 'functions_detail' => [],
                'filters' => 2, 'unique_filters' => 2, 'filters_detail' => ['escape' => 1, 'upper' => 1],
                'tests' => 0, 'unique_tests' => 0, 'tests_detail' => [],
            ],
        ];

        yield 'multiple filters, one duplicate' => [
            '{{ "hello"|upper }}{{ "world"|lower }}{{ "test"|upper }}',
            [
                'functions' => 0, 'unique_functions' => 0, 'functions_detail' => [],
                'filters' => 6, 'unique_filters' => 3, 'filters_detail' => ['escape' => 3, 'upper' => 2, 'lower' => 1],
                'tests' => 0, 'unique_tests' => 0, 'tests_detail' => [],
            ],
        ];

        yield 'single test' => [
            '{% if foo is empty %}{% endif %}',
            [
                'functions' => 0, 'unique_functions' => 0, 'functions_detail' => [],
                'filters' => 0, 'unique_filters' => 0, 'filters_detail' => [],
                'tests' => 1, 'unique_tests' => 1, 'tests_detail' => ['empty' => 1],
            ],
        ];

        yield 'multiple tests, one duplicate' => [
            '{% if foo is empty %}{% endif %}{% if bar is defined %}{% endif %}{% if baz is empty %}{% endif %}',
            [
                'functions' => 0, 'unique_functions' => 0, 'functions_detail' => [],
                'filters' => 0, 'unique_filters' => 0, 'filters_detail' => [],
                'tests' => 3, 'unique_tests' => 2, 'tests_detail' => ['empty' => 2, 'defined' => 1],
            ],
        ];

        yield 'mixed functions, filters, and tests' => [
            '{{ path("home") }}{{ "hello"|upper }}{% if foo is empty %}{% endif %}',
            [
                'functions' => 1, 'unique_functions' => 1, 'functions_detail' => ['path' => 1],
                'filters' => 3, 'unique_filters' => 2, 'filters_detail' => ['escape' => 2, 'upper' => 1],
                'tests' => 1, 'unique_tests' => 1, 'tests_detail' => ['empty' => 1],
            ],
        ];
    }

    public function testVariableCollectionInLoops(): void
    {
        $source = new Source('{% for item in items %}{{ item }}{% endfor %}{{ outside }}', 'test.twig');
        $ast = $this->twig->parse($this->twig->tokenize($source));

        $this->traverser->traverse($ast, [$this->collector]);
        $data = $this->collector->getData();

        $this->assertSame(2, $data['variables']);
        $this->assertSame(2, $data['unique_variables']);
        $this->assertSame(['items' => 1, 'outside' => 1], $data['variables_detail']);
    }

    public function testMacroCollectionAndVariableScoping(): void
    {
        $source = new Source('{% macro greet(name) %}{{ name }} {{ outside }}{% endmacro %}{{ _self.greet("John") }}', 'test.twig');
        $ast = $this->twig->parse($this->twig->tokenize($source));

        $this->traverser->traverse($ast, [$this->collector]);
        $data = $this->collector->getData();

        $this->assertSame(1, $data['macro_definitions']);
        $this->assertSame(1, $data['unique_macro_definitions']);
        $this->assertSame(['greet' => 1], $data['macro_definitions_detail']);
        $this->assertSame(1, $data['macro_calls']);
        $this->assertSame(1, $data['unique_macro_calls']);
        $this->assertSame(['greet' => 1], $data['macro_calls_detail']);
        $this->assertSame(1, $data['variables']);
        $this->assertSame(1, $data['unique_variables']);
        $this->assertSame(['outside' => 1], $data['variables_detail']);
    }

    public function testResetClearsData(): void
    {
        $source = new Source('{{ path("test") }}{{ "foo"|upper }}{% if bar is empty %}{% endif %}', 'test.twig');
        $ast = $this->twig->parse($this->twig->tokenize($source));

        $this->traverser->traverse($ast, [$this->collector]);
        $data = $this->collector->getData();

        $this->assertGreaterThan(0, $data['functions']);
        $this->assertGreaterThan(0, $data['filters']);
        $this->assertGreaterThan(0, $data['tests']);

        $this->collector->reset();
        $data = $this->collector->getData();

        $this->assertSame(0, $data['functions'] ?? 0);
        $this->assertSame(0, $data['unique_functions'] ?? 0);
        $this->assertSame(0, $data['filters'] ?? 0);
        $this->assertSame(0, $data['unique_filters'] ?? 0);
        $this->assertSame(0, $data['tests'] ?? 0);
        $this->assertSame(0, $data['unique_tests'] ?? 0);
        $this->assertEmpty($data['functions_detail'] ?? []);
        $this->assertEmpty($data['filters_detail'] ?? []);
        $this->assertEmpty($data['tests_detail'] ?? []);
    }
}
