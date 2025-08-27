<?php

declare(strict_types=1);

namespace TwigMetrics\Tests\Collector;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use TwigMetrics\Collector\FileMetricsCollector;

#[CoversClass(FileMetricsCollector::class)]
final class FileMetricsCollectorTest extends TestCase
{
    public function testCompute(): void
    {
        $collector = new FileMetricsCollector();
        $out = $collector->compute([
            'lines' => 10,
            'blank_lines' => 2,
            'comment_lines' => 3,
        ]);

        $this->assertSame(2, $out['emptyLines']);
        $this->assertSame(3, $out['commentLines']);
        $this->assertSame(5, $out['codeLines']);
        $this->assertEquals(0.2, $out['emptyRatio']);
        $this->assertEquals(0.3, $out['commentRatio']);
        $this->assertEquals(0.6, $out['commentDensity']);
    }
}
