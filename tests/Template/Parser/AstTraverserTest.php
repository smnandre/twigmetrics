<?php

declare(strict_types=1);

namespace TwigMetrics\Tests\Template\Parser;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Twig\Node\Expression\NameExpression;
use Twig\Node\Node;
use Twig\Node\PrintNode;
use Twig\Node\TextNode;
use TwigMetrics\Collector\CollectorInterface;
use TwigMetrics\Template\Parser\AstTraverser;

#[CoversClass(AstTraverser::class)]
class AstTraverserTest extends TestCase
{
    private AstTraverser $traverser;

    protected function setUp(): void
    {
        $this->traverser = new AstTraverser();
    }

    public function testTraverseCallsResetOnCollectors(): void
    {
        $collector = $this->createMockCollector();
        $collector->expects($this->once())->method('reset');

        $node = new TextNode('Hello', 0);
        $this->traverser->traverse($node, [$collector]);
    }

    public function testTraverseCallsEnterAndLeaveNode(): void
    {
        $collector = $this->createMockCollector();

        $collector->expects($this->once())->method('enterNode');
        $collector->expects($this->once())->method('leaveNode');

        $node = new TextNode('Hello', 0);
        $this->traverser->traverse($node, [$collector]);
    }

    public function testTraverseWithNestedNodes(): void
    {
        $collector = $this->createMockCollector();

        $collector->expects($this->exactly(3))->method('enterNode');
        $collector->expects($this->exactly(3))->method('leaveNode');

        $nameExpr = new NameExpression('user', 0);
        $printNode = new PrintNode($nameExpr, 0);
        $rootNode = new Node(['print' => $printNode], [], 0);

        $this->traverser->traverse($rootNode, [$collector]);
    }

    public function testTraverseWithMultipleCollectors(): void
    {
        $collector1 = $this->createMockCollector();
        $collector2 = $this->createMockCollector();

        $collector1->expects($this->once())->method('reset');
        $collector2->expects($this->once())->method('reset');

        $collector1->expects($this->once())->method('enterNode');
        $collector2->expects($this->once())->method('enterNode');

        $node = new TextNode('Hello', 0);
        $this->traverser->traverse($node, [$collector1, $collector2]);
    }

    public function testTraverseCallsCollectorsInCorrectOrder(): void
    {
        $collector = $this->createMockCollector();

        $matcher = $this->exactly(4);
        $collector->expects($matcher)->method('enterNode')
            ->willReturnCallback(function (Node $node) use ($matcher) {
                match ($matcher->numberOfInvocations()) {
                    1 => $this->assertInstanceOf(Node::class, $node),
                    2 => $this->assertInstanceOf(PrintNode::class, $node),
                    3 => $this->assertInstanceOf(NameExpression::class, $node),
                    4 => $this->assertInstanceOf(TextNode::class, $node),
                };
            });

        $nameExpr = new NameExpression('user', 0);
        $printNode = new PrintNode($nameExpr, 0);
        $textNode = new TextNode('Hello', 0);
        $rootNode = new Node(['print' => $printNode, 'text' => $textNode], [], 0);

        $this->traverser->traverse($rootNode, [$collector]);
    }

    private function createMockCollector(): MockObject&CollectorInterface
    {
        return $this->createMock(CollectorInterface::class);
    }
}
