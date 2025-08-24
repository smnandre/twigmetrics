<?php

declare(strict_types=1);

namespace TwigMetrics\Tests\Collector;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Twig\Node\Node;
use TwigMetrics\Collector\AbstractCollector;

#[CoversClass(AbstractCollector::class)]
class AbstractCollectorTest extends TestCase
{
    public function testMetricsInitiallyEmpty(): void
    {
        $collector = new class extends AbstractCollector {
            public function getName(): string
            {
                return 'DummyCollector';
            }
        };

        $this->assertEmpty($collector->getData());
    }

    public function testResetClearsData(): void
    {
        $collector = new class extends AbstractCollector {
            public function getName(): string
            {
                return 'DummyCollector';
            }

            public function enterNode(Node $node): void
            {
                $this->data[] = 'some_data';
            }
        };

        $collector->enterNode(new Node());
        $this->assertNotEmpty($collector->getData());

        $collector->reset();
        $this->assertEmpty($collector->getData());
    }

    public function testEnterAndLeaveNodeMethodsExist(): void
    {
        $collector = new class extends AbstractCollector {
            public function getName(): string
            {
                return 'DummyCollector';
            }
        };

        $node = new Node();
        $collector->enterNode($node);
        $collector->leaveNode($node);

        $this->assertTrue(true);
    }
}
