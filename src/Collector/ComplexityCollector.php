<?php

declare(strict_types=1);

namespace TwigMetrics\Collector;

use Twig\Node\BlockNode;
use Twig\Node\Expression\Binary\AndBinary;
use Twig\Node\Expression\Binary\OrBinary;
use Twig\Node\Expression\Ternary\ConditionalTernary;
use Twig\Node\Expression\TestExpression;
use Twig\Node\ForNode;
use Twig\Node\IfNode;
use Twig\Node\MacroNode;
use Twig\Node\Node;
use TwigMetrics\Config\AnalysisConstants;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
final class ComplexityCollector extends AbstractCollector
{
    private int $currentDepth = 0;
    private int $maxDepth = 0;
    /**
     * @var array<int, string>
     */
    private array $depthStack = [];

    public function enterNode(Node $node): void
    {
        if ($this->isNestingNode($node)) {
            ++$this->currentDepth;
            $this->depthStack[] = $node::class;

            if ($this->currentDepth > $this->maxDepth) {
                $this->maxDepth = $this->currentDepth;
                $this->data['max_depth'] = $this->maxDepth;
            }
        }

        match (true) {
            $node instanceof IfNode => $this->data['conditions'] = ($this->data['conditions'] ?? 0) + 1,
            $node instanceof ForNode => $this->data['loops'] = ($this->data['loops'] ?? 0) + 1,
            $node instanceof ConditionalTernary => $this->data['ternary'] = ($this->data['ternary'] ?? 0) + 1,
            $node instanceof AndBinary, $node instanceof OrBinary => $this->data['logical_operators'] = ($this->data['logical_operators'] ?? 0) + 1,
            $node instanceof TestExpression => $this->data['tests'] = ($this->data['tests'] ?? 0) + 1,
            default => null,
        };
    }

    public function leaveNode(Node $node): void
    {
        if ($this->isNestingNode($node) && count($this->depthStack) > 0) {
            array_pop($this->depthStack);
            --$this->currentDepth;
        }
    }

    public function getData(): array
    {
        $data = parent::getData();

        if (empty($data)) {
            return [];
        }

        $complexity = 0;
        $complexity += ($data['conditions'] ?? 0) * AnalysisConstants::COMPLEXITY_IF_POINTS;
        $complexity += ($data['loops'] ?? 0) * AnalysisConstants::COMPLEXITY_FOR_POINTS;
        $complexity += ($data['ternary'] ?? 0) * AnalysisConstants::COMPLEXITY_TERNARY_POINTS;
        $complexity += ($data['logical_operators'] ?? 0) * AnalysisConstants::COMPLEXITY_LOGICAL_OPERATOR_POINTS;
        $complexity += ($data['max_depth'] ?? 0) * AnalysisConstants::COMPLEXITY_NESTING_POINTS;

        $data['complexity_score'] = $complexity;

        return $data;
    }

    public function reset(): void
    {
        parent::reset();
        $this->currentDepth = 0;
        $this->maxDepth = 0;
        $this->depthStack = [];
        $this->data['conditions'] = 0;
        $this->data['loops'] = 0;
        $this->data['ternary'] = 0;
        $this->data['logical_operators'] = 0;
        $this->data['tests'] = 0;
        $this->data['max_depth'] = 0;
    }

    private function isNestingNode(Node $node): bool
    {
        return $node instanceof IfNode
            || $node instanceof ForNode
            || $node instanceof BlockNode
            || $node instanceof MacroNode;
    }
}
