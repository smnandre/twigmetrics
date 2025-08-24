<?php

declare(strict_types=1);

namespace TwigMetrics\Collector;

use Twig\Node\Node;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
interface CollectorInterface
{
    public function enterNode(Node $node): void;

    public function leaveNode(Node $node): void;

    /**
     * @return array<string, mixed>
     */
    public function getData(): array;

    public function reset(): void;
}
