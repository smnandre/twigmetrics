<?php

declare(strict_types=1);

namespace TwigMetrics\Collector;

use Twig\Node\Node;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
abstract class AbstractCollector implements CollectorInterface
{
    /**
     * @var array<string, mixed>
     */
    protected array $data = [];

    public function enterNode(Node $node): void
    {
    }

    public function leaveNode(Node $node): void
    {
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function reset(): void
    {
        $this->data = [];
    }
}
