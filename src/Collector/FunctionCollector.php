<?php

declare(strict_types=1);

namespace TwigMetrics\Collector;

use Twig\Node\BlockNode;
use Twig\Node\Expression\FilterExpression;
use Twig\Node\Expression\FunctionExpression;
use Twig\Node\Expression\MacroReferenceExpression;
use Twig\Node\Expression\NameExpression;
use Twig\Node\Expression\TestExpression;
use Twig\Node\ForNode;
use Twig\Node\ImportNode;
use Twig\Node\MacroNode;
use Twig\Node\Node;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
final class FunctionCollector extends AbstractCollector
{
    /**
     * @var array<int, string>
     */
    private array $functions = [];
    /**
     * @var array<int, string>
     */
    private array $filters = [];
    /**
     * @var array<int, string>
     */
    private array $tests = [];
    /**
     * @var array<int, string>
     */
    private array $macroDefinitions = [];
    /**
     * @var array<int, string>
     */
    private array $macroCalls = [];
    /**
     * @var array<int, string>
     */
    private array $macroImports = [];
    /**
     * @var array<int, string>
     */
    private array $variables = [];
    /**
     * @var array<int, array<int, string>>
     */
    private array $contextStack = [];

    public function enterNode(Node $node): void
    {
        if ($node instanceof ForNode) {
            $this->pushForContext($node);
        } elseif ($node instanceof MacroNode) {
            $this->pushMacroContext($node);
            $this->collectMacroDefinition($node);
        } elseif ($node instanceof BlockNode) {
            $this->pushBlockContext();
        }

        match (true) {
            $node instanceof FunctionExpression => $this->collectFunction($node),
            $node instanceof FilterExpression => $this->collectFilter($node),
            $node instanceof TestExpression => $this->collectTest($node),
            $node instanceof MacroReferenceExpression => $this->collectMacroCall($node),
            $node instanceof ImportNode => $this->collectMacroImport($node),
            $node instanceof NameExpression => $this->collectVariable($node),
            default => null,
        };
    }

    public function leaveNode(Node $node): void
    {
        if ($node instanceof ForNode || $node instanceof MacroNode || $node instanceof BlockNode) {
            array_pop($this->contextStack);
        }
    }

    private function collectFunction(FunctionExpression $node): void
    {
        if ($node->hasAttribute('name')) {
            $this->functions[] = $node->getAttribute('name');
        }
    }

    private function collectFilter(FilterExpression $node): void
    {
        if ($node->hasAttribute('name')) {
            $this->filters[] = $node->getAttribute('name');
        }
    }

    private function collectTest(TestExpression $node): void
    {
        if ($node->hasAttribute('name')) {
            $this->tests[] = $node->getAttribute('name');
        }
    }

    private function collectMacroDefinition(MacroNode $node): void
    {
        if ($node->hasAttribute('name')) {
            $this->macroDefinitions[] = $node->getAttribute('name');
        }
    }

    private function collectMacroCall(MacroReferenceExpression $node): void
    {
        if ($node->hasAttribute('name')) {
            $name = $node->getAttribute('name');
            if (str_starts_with($name, 'macro_')) {
                $name = substr($name, 6);
            }
            $this->macroCalls[] = $name;
        }
    }

    private function collectMacroImport(ImportNode $node): void
    {
        $exprNode = $node->getNode('expr');
        if ($exprNode->hasAttribute('value')) {
            $this->macroImports[] = $exprNode->getAttribute('value');
        }
    }

    private function collectVariable(NameExpression $node): void
    {
        if ($node->hasAttribute('name')) {
            $name = $node->getAttribute('name');

            if (!$this->isLocalVariable($name) && !$this->isInCurrentContext($name)) {
                $this->variables[] = $name;
            }
        }
    }

    private function isLocalVariable(string $name): bool
    {
        $localPatterns = [
            'loop', 'item', 'key', 'value', 'index',

            '_self', '_parent', '_context', '_charset',
        ];

        return in_array($name, $localPatterns, true);
    }

    private function isInCurrentContext(string $name): bool
    {
        foreach ($this->contextStack as $scope) {
            if (in_array($name, $scope, true)) {
                return true;
            }
        }

        return false;
    }

    private function pushForContext(ForNode $node): void
    {
        $vars = [];
        foreach (['key_target', 'value_target'] as $target) {
            if ($node->hasNode($target)) {
                $targetNode = $node->getNode($target);
                if ($targetNode->hasAttribute('name')) {
                    $vars[] = $targetNode->getAttribute('name');
                }
            }
        }

        if ($node->getAttribute('with_loop')) {
            $vars[] = 'loop';
        }

        $this->contextStack[] = $vars;
    }

    private function pushMacroContext(MacroNode $node): void
    {
        $vars = [];
        $arguments = $node->getNode('arguments');
        if (method_exists($arguments, 'getKeyValuePairs')) {
            foreach ($arguments->getKeyValuePairs() as $pair) {
                $var = $pair['key']->getAttribute('name');
                if (str_starts_with($var, "\u{035C}")) {
                    $var = substr($var, strlen("\u{035C}"));
                }
                $vars[] = $var;
            }
        }

        $vars[] = MacroNode::VARARGS_NAME;

        $this->contextStack[] = $vars;
    }

    private function pushBlockContext(): void
    {
        $this->contextStack[] = [];
    }

    public function getData(): array
    {
        return [
            'functions' => count($this->functions),
            'unique_functions' => count(array_unique($this->functions)),
            'functions_detail' => array_count_values($this->functions),
            'filters' => count($this->filters),
            'unique_filters' => count(array_unique($this->filters)),
            'filters_detail' => array_count_values($this->filters),
            'tests' => count($this->tests),
            'unique_tests' => count(array_unique($this->tests)),
            'tests_detail' => array_count_values($this->tests),
            'macro_definitions' => count($this->macroDefinitions),
            'unique_macro_definitions' => count(array_unique($this->macroDefinitions)),
            'macro_definitions_detail' => array_count_values($this->macroDefinitions),
            'macro_calls' => count($this->macroCalls),
            'unique_macro_calls' => count(array_unique($this->macroCalls)),
            'macro_calls_detail' => array_count_values($this->macroCalls),
            'macro_imports' => count($this->macroImports),
            'unique_macro_imports' => count(array_unique($this->macroImports)),
            'macro_imports_detail' => array_count_values($this->macroImports),
            'variables' => count($this->variables),
            'unique_variables' => count(array_unique($this->variables)),
            'variables_detail' => array_count_values($this->variables),
        ];
    }

    public function reset(): void
    {
        $this->functions = [];
        $this->filters = [];
        $this->tests = [];
        $this->macroDefinitions = [];
        $this->macroCalls = [];
        $this->macroImports = [];
        $this->variables = [];
        $this->contextStack = [];
    }
}
