<?php

declare(strict_types=1);

namespace TwigMetrics\Tests\Renderer\Component;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;
use TwigMetrics\Renderer\Component\TopUsageRenderer;

#[CoversClass(TopUsageRenderer::class)]
final class TopUsageRendererTest extends TestCase
{
    private TopUsageRenderer $renderer;
    private BufferedOutput $output;

    protected function setUp(): void
    {
        $this->output = new BufferedOutput();
        $this->renderer = new TopUsageRenderer($this->output);
    }

    public function testRenderEmptyData(): void
    {
        $this->renderer->render([], []);

        $result = $this->output->fetch();

        self::assertStringContainsString('Most used Functions (top 10)', $result);
        self::assertStringContainsString('Most used Variables (top 10)', $result);
    }

    public function testRenderWithFunctionsAndVariables(): void
    {
        $functions = [
            'form_row' => 105,
            'include' => 105,
            'path' => 77,
            'form_start' => 36,
            'form_end' => 36,
        ];

        $variables = [
            'form' => 489,
            'sample' => 107,
            'dossier' => 92,
            '_key' => 91,
            'id' => 79,
        ];

        $this->renderer->render($functions, $variables);

        $result = $this->output->fetch();

        self::assertStringContainsString('Most used Functions (top 10)', $result);
        self::assertStringContainsString('Most used Variables (top 10)', $result);

        self::assertStringContainsString('form_row', $result);
        self::assertStringContainsString('105', $result);
        self::assertStringContainsString('form', $result);
        self::assertStringContainsString('489', $result);

        self::assertMatchesRegularExpression('/[█▉▊▋▌▍▎▏]/', $result);
    }

    public function testRenderWithUnequalCounts(): void
    {
        $functions = [
            'form_row' => 105,
            'include' => 77,
        ];

        $variables = [
            'form' => 489,
            'sample' => 107,
            'dossier' => 92,
            '_key' => 91,
            'id' => 79,
        ];

        $this->renderer->render($functions, $variables);

        $result = $this->output->fetch();
        $lines = explode("\n", $result);

        $nonEmptyLines = array_filter($lines, fn ($line) => '' !== trim($line));
        self::assertGreaterThanOrEqual(6, count($nonEmptyLines));
    }

    public function testRenderWithLongNames(): void
    {
        $functions = [
            'very_long_function_name_that_exceeds_normal_limits' => 100,
        ];

        $variables = [
            'extremely_long_variable_name_that_should_be_truncated' => 200,
        ];

        $this->renderer->render($functions, $variables);

        $result = $this->output->fetch();

        self::assertStringContainsString('…', $result);
    }

    public function testRenderWithLimit(): void
    {
        $functions = [];
        $variables = [];

        for ($i = 1; $i <= 15; ++$i) {
            $functions["func_{$i}"] = $i * 10;
            $variables["var_{$i}"] = $i * 20;
        }

        $this->renderer->render($functions, $variables, 5);

        $result = $this->output->fetch();
        $lines = explode("\n", $result);

        $dataLines = array_filter($lines, fn ($line) => !empty(trim($line))
            && !str_contains($line, 'Most used')
        );

        self::assertCount(5, $dataLines);
    }

    #[DataProvider('provideProgressBarData')]
    public function testProgressBarCreation(float $length, string $expectedPattern): void
    {
        $functions = ['test' => 100];
        $variables = [];

        $this->renderer->render($functions, $variables);
        $result = $this->output->fetch();

        self::assertMatchesRegularExpression($expectedPattern, $result);
    }

    public static function provideProgressBarData(): iterable
    {
        yield 'full bar' => [10.0, '/█+/'];
        yield 'partial bar' => [5.5, '/█+[▉▊▋▌▍▎▏]/'];
        yield 'minimal bar' => [0.5, '/[▉▊▋▌▍▎▏]/'];
    }
}
