<?php

declare(strict_types=1);

namespace TwigMetrics\Collector;

use Twig\Node\BlockNode;
use Twig\Node\EmbedNode;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\FunctionExpression;
use Twig\Node\ImportNode;
use Twig\Node\IncludeNode;
use Twig\Node\ModuleNode;
use Twig\Node\Node;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
final class InheritanceCollector extends AbstractCollector
{
    /**
     * @var array<int, string>
     */
    private array $blocks = [];

    public function enterNode(Node $node): void
    {
        if ($node instanceof ModuleNode && $node->hasNode('parent')) {
            $this->data['extends'] = ($this->data['extends'] ?? 0) + 1;

            $parent = $node->getNode('parent');
            if ($parent instanceof ConstantExpression) {
                $this->data['extends_from'][] = $parent->getAttribute('value');
            }
        }

        if ($node instanceof IncludeNode) {
            $this->data['includes'] = ($this->data['includes'] ?? 0) + 1;

            $expr = $node->getNode('expr');
            if ($expr instanceof ConstantExpression) {
                $this->data['includes_templates'][] = $expr->getAttribute('value');
            }
        }

        if ($node instanceof FunctionExpression && 'include' === $node->getAttribute('name')) {
            $argNode = $node->getNode('arguments')->getNode('0');
            if ($argNode instanceof ConstantExpression) {
                $this->data['includes'] = ($this->data['includes'] ?? 0) + 1;
                $this->data['includes_templates'][] = $argNode->getAttribute('value');
            }
        }

        if ($node instanceof EmbedNode) {
            $this->data['embeds'] = ($this->data['embeds'] ?? 0) + 1;
            $this->data['embeds_templates'][] = $node->getAttribute('name');
        }

        if ($node instanceof ImportNode) {
            $this->data['imports'] = ($this->data['imports'] ?? 0) + 1;
        }

        if ($node instanceof BlockNode) {
            $this->data['blocks'] = ($this->data['blocks'] ?? 0) + 1;

            if ($node->hasAttribute('name')) {
                $this->blocks[] = $node->getAttribute('name');
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return [
            'extends' => $this->data['extends'] ?? 0,
            'extends_from' => $this->data['extends_from'] ?? [],
            'top_extends_from' => $this->getTop5($this->data['extends_from'] ?? []),
            'includes' => $this->data['includes'] ?? 0,
            'includes_templates' => $this->data['includes_templates'] ?? [],
            'top_includes_templates' => $this->getTop5($this->data['includes_templates'] ?? []),
            'embeds' => $this->data['embeds'] ?? 0,
            'embeds_templates' => $this->data['embeds_templates'] ?? [],
            'top_embeds' => $this->getTop5($this->data['embeds_templates'] ?? []),
            'imports' => $this->data['imports'] ?? 0,
            'blocks' => $this->data['blocks'] ?? 0,
            'blocks_detail' => $this->blocks,
        ];
    }

    /**
     * @param array<int, string> $items
     *
     * @return array<string, int>
     */
    private function getTop5(array $items): array
    {
        $counts = array_count_values($items);
        arsort($counts);

        return array_slice($counts, 0, 5, true);
    }

    public function reset(): void
    {
        parent::reset();
        $this->blocks = [];
    }
}
