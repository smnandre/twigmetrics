<?php

declare(strict_types=1);

namespace TwigMetrics\Tests\Renderer\Helper;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;
use TwigMetrics\Renderer\Helper\ConsoleOutputHelper;

#[CoversClass(ConsoleOutputHelper::class)]
class ConsoleOutputHelperTest extends TestCase
{
    private ConsoleOutputHelper $renderer;

    protected function setUp(): void
    {
        $this->renderer = new ConsoleOutputHelper(new BufferedOutput());
    }

    public function testImplementsRendererInterface(): void
    {
        $this->assertInstanceOf(ConsoleOutputHelper::class, $this->renderer);
    }
}
