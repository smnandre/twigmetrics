<?php

declare(strict_types=1);

namespace TwigMetrics\Collector;

use Twig\Node\BlockNode;
use Twig\Node\BlockReferenceNode;
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
final class RelationshipCollector extends AbstractCollector
{
    /**
     * @var array <string, mixed>
     */
    private array $dependencies = [];

    /**
     * @var array <string, mixed>
     */
    private array $providedBlocks = [];

    /**
     * @var array <string, mixed>
     */
    private array $usedBlocks = [];

    private string $currentTemplate = '';

    /**
     * @var callable
     */
    private $dependencyResolver;

    private int $inheritanceDepth = 0;

    public function getCurrentTemplate(): string
    {
        return $this->currentTemplate;
    }

    public function setCurrentTemplate(string $templatePath): void
    {
        $this->currentTemplate = $templatePath;
    }

    public function setDependencyResolver(callable $resolver): void
    {
        $this->dependencyResolver = $resolver;
    }

    public function enterNode(Node $node): void
    {
        match ($node::class) {
            ModuleNode::class => $this->handleModuleNode($node),
            IncludeNode::class => $this->handleIncludeNode($node),
            EmbedNode::class => $this->handleEmbedNode($node),
            ImportNode::class => $this->handleImportNode($node),
            FunctionExpression::class => $this->handleFunctionExpression($node),
            BlockNode::class => $this->handleBlockNode($node),
            BlockReferenceNode::class => $this->handleBlockReferenceNode($node),
            default => null,
        };
    }

    public function getData(): array
    {
        return [
            'dependencies' => $this->dependencies,
            'provided_blocks' => $this->providedBlocks,
            'used_blocks' => $this->usedBlocks,
            'inheritance_depth' => $this->inheritanceDepth,
            'coupling_score' => $this->calculateCouplingScore(),
            'reusability_score' => $this->calculateReusabilityScore(),
            'dependency_types' => $this->categorizeDependencies(),
        ];
    }

    public function reset(): void
    {
        parent::reset();
        $this->dependencies = [];
        $this->providedBlocks = [];
        $this->usedBlocks = [];
        $this->inheritanceDepth = 0;
    }

    private function handleModuleNode(ModuleNode $node): void
    {
        if ($node->hasNode('parent')) {
            $parent = $node->getNode('parent');
            if ($parent instanceof ConstantExpression) {
                $parentTemplate = $parent->getAttribute('value');
                $this->addDependency($parentTemplate, 'extends');
                $this->inheritanceDepth = $this->calculateInheritanceDepth($parentTemplate);
            }
        }
    }

    private function handleIncludeNode(IncludeNode $node): void
    {
        $template = $this->extractTemplateName($node->getNode('expr'));
        if ($template) {
            $this->addDependency($template, 'includes', [
                'with_context' => $node->hasAttribute('with_context') && $node->getAttribute('with_context'),
                'ignore_missing' => $node->getAttribute('ignore_missing') ?? false,
            ]);
        }
    }

    private function handleEmbedNode(EmbedNode $node): void
    {
        $template = $this->extractTemplateName($node->getNode('expr'));
        if ($template) {
            $this->addDependency($template, 'embeds', [
                'with_context' => $node->hasAttribute('with_context') && $node->getAttribute('with_context'),
            ]);
        }
    }

    private function handleImportNode(ImportNode $node): void
    {
        $template = $this->extractTemplateName($node->getNode('expr'));
        if ($template) {
            $alias = null;
            try {
                $alias = $node->getNode('var')->getAttribute('name');
            } catch (\LogicException) {
            }

            $this->addDependency($template, 'imports', [
                'alias' => $alias,
            ]);
        }
    }

    private function handleFunctionExpression(FunctionExpression $node): void
    {
        $functionName = $node->getAttribute('name');

        if ('include' === $functionName) {
            $args = $node->getNode('arguments');
            if ($args->hasNode('0')) {
                $template = $this->extractTemplateName($args->getNode('0'));
                if ($template) {
                    $this->addDependency($template, 'includes_function');
                }
            }
        }
    }

    private function handleBlockNode(BlockNode $node): void
    {
        $blockName = $node->getAttribute('name');
        if ($blockName) {
            $this->providedBlocks[] = $blockName;
        }
    }

    private function handleBlockReferenceNode(BlockReferenceNode $node): void
    {
        $blockName = $node->getAttribute('name');
        if ($blockName) {
            $this->usedBlocks[] = $blockName;
        }
    }

    private function extractTemplateName(Node $node): ?string
    {
        if ($node instanceof ConstantExpression) {
            return $node->getAttribute('value');
        }

        return null;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function addDependency(string $template, string $type, array $metadata = []): void
    {
        $this->dependencies[] = [
            'template' => $template,
            'type' => $type,
            'metadata' => $metadata,
        ];
    }

    private function calculateInheritanceDepth(string $parentTemplate): int
    {
        $depth = 1;
        if (null === $this->dependencyResolver) {
            return $depth;
        }

        $resolver = $this->dependencyResolver;
        $visited = [$this->currentTemplate];
        $current = $parentTemplate;

        while ($current) {
            if (in_array($current, $visited, true)) {
                break;
            }

            $visited[] = $current;
            $next = $resolver($current);

            if (null === $next) {
                break;
            }

            ++$depth;
            $current = $next;
        }

        return $depth;
    }

    private function calculateCouplingScore(): int
    {
        $score = 0;
        $score += count($this->dependencies) * 2;
        $score += count(array_unique($this->usedBlocks)) * 1;
        $score += $this->inheritanceDepth * 3;

        return $score;
    }

    private function calculateReusabilityScore(): int
    {
        $score = 100;
        $score -= count($this->dependencies) * 5;
        $score -= $this->inheritanceDepth * 10;
        $score += count($this->providedBlocks) * 5;

        return max(0, $score);
    }

    /**
     * @return array<string, int>
     */
    private function categorizeDependencies(): array
    {
        $categories = [
            'extends' => 0,
            'includes' => 0,
            'embeds' => 0,
            'imports' => 0,
            'includes_function' => 0,
        ];

        foreach ($this->dependencies as $dependency) {
            $type = $dependency['type'];
            if (isset($categories[$type])) {
                ++$categories[$type];
            }
        }

        return $categories;
    }
}
