<?php

declare(strict_types=1);

namespace TwigMetrics\Collector;

use Twig\Node\BlockNode;
use Twig\Node\ForNode;
use Twig\Node\IfNode;
use Twig\Node\MacroNode;
use Twig\Node\Node;
use Twig\Node\SetNode;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
final class ControlFlowCollector extends AbstractCollector
{
    private int $currentDepth = 0;
    private int $maxDepth = 0;
    private int $totalBlocks = 0;
    /**
     * @var array<int, string>
     */
    private array $blockNames = [];
    /**
     * @var array<int, string>
     */
    private array $macroNames = [];

    public function enterNode(Node $node): void
    {
        match (true) {
            $node instanceof IfNode => $this->handleIfNode($node),
            $node instanceof ForNode => $this->handleForNode(),
            $node instanceof BlockNode => $this->handleBlockNode($node),
            $node instanceof MacroNode => $this->handleMacroNode($node),
            $node instanceof SetNode => $this->handleSetNode(),
            default => null,
        };

        if ($this->isNestingNode($node)) {
            ++$this->currentDepth;
            if ($this->currentDepth > $this->maxDepth) {
                $this->maxDepth = $this->currentDepth;
            }
        }
    }

    public function leaveNode(Node $node): void
    {
        if ($this->isNestingNode($node)) {
            --$this->currentDepth;
        }
    }

    public function getData(): array
    {
        return [
            'ifs' => $this->data['ifs'] ?? 0,
            'fors' => $this->data['fors'] ?? 0,
            'blocks' => $this->totalBlocks,
            'macros' => count($this->macroNames),
            'variables_set' => $this->data['variables_set'] ?? 0,
            'max_depth' => $this->maxDepth,
            'unique_blocks' => count($this->blockNames),
            'block_names' => $this->blockNames,
            'macro_names' => $this->macroNames,

            'has_nested_loops' => $this->hasNestedLoops(),
            'has_complex_conditions' => $this->hasComplexConditions(),
        ];
    }

    public function reset(): void
    {
        parent::reset();
        $this->currentDepth = 0;
        $this->maxDepth = 0;
        $this->totalBlocks = 0;
        $this->blockNames = [];
        $this->macroNames = [];
        $this->data = [
            'ifs' => 0,
            'fors' => 0,
            'variables_set' => 0,
        ];
    }

    private function handleIfNode(IfNode $node): void
    {
        $this->data['ifs'] = ($this->data['ifs'] ?? 0) + 1;

        if ($node->hasNode('tests')) {
            $tests = $node->getNode('tests');
            if ($tests->count() > 2) {
                $this->data['complex_conditions'] = ($this->data['complex_conditions'] ?? 0) + 1;
            }
        }
    }

    private function handleForNode(): void
    {
        $this->data['fors'] = ($this->data['fors'] ?? 0) + 1;

        if ($this->currentDepth > 1) {
            $this->data['nested_loops'] = ($this->data['nested_loops'] ?? 0) + 1;
        }
    }

    private function handleBlockNode(BlockNode $node): void
    {
        ++$this->totalBlocks;
        $blockName = $node->getAttribute('name');
        if ($blockName && !in_array($blockName, $this->blockNames, true)) {
            $this->blockNames[] = $blockName;
        }
    }

    private function handleMacroNode(MacroNode $node): void
    {
        $macroName = $node->getAttribute('name');
        if ($macroName && !in_array($macroName, $this->macroNames, true)) {
            $this->macroNames[] = $macroName;
        }
    }

    private function handleSetNode(): void
    {
        $this->data['variables_set'] = ($this->data['variables_set'] ?? 0) + 1;
    }

    private function isNestingNode(Node $node): bool
    {
        return $node instanceof IfNode
            || $node instanceof ForNode
            || $node instanceof BlockNode
            || $node instanceof MacroNode;
    }

    private function hasNestedLoops(): bool
    {
        return ($this->data['nested_loops'] ?? 0) > 0;
    }

    private function hasComplexConditions(): bool
    {
        return ($this->data['complex_conditions'] ?? 0) > 0;
    }
}
