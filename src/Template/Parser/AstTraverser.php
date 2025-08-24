<?php

declare(strict_types=1);

namespace TwigMetrics\Template\Parser;

use Twig\Node\Node;
use TwigMetrics\Collector\CollectorInterface;

/**
 * Traverses the AST of Twig templates and allows collectors to process nodes.
 *
 * This class is responsible for walking through the AST nodes and invoking
 * the appropriate methods on the provided collectors.
 *
 * @internal
 *
 * @author Simon André <smn.andre@gmail.com>
 */
final class AstTraverser
{
    /**
     * @param CollectorInterface[] $collectors
     */
    public function traverse(Node $node, array $collectors): void
    {
        foreach ($collectors as $collector) {
            $collector->reset();
        }

        $this->walk($node, $collectors);
    }

    /**
     * @param CollectorInterface[] $collectors
     */
    private function walk(Node $node, array $collectors): void
    {
        foreach ($collectors as $collector) {
            $collector->enterNode($node);
        }

        foreach ($node as $child) {
            $this->walk($child, $collectors);
        }

        foreach ($collectors as $collector) {
            $collector->leaveNode($node);
        }
    }
}
