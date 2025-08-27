<?php

declare(strict_types=1);

namespace TwigMetrics\Tests\Detector;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\SplFileInfo;
use TwigMetrics\Analyzer\AnalysisResult;
use TwigMetrics\Detector\ComplexityHotspotDetector;

#[CoversClass(ComplexityHotspotDetector::class)]
final class ComplexityHotspotDetectorTest extends TestCase
{
    public function testDetectHotspots(): void
    {
        $results = [
            $this->makeResult(['complexity_score' => 1, 'lines' => 10], 'a.twig'),
            $this->makeResult(['complexity_score' => 5, 'lines' => 20], 'b.twig'),
            $this->makeResult(['complexity_score' => 3, 'lines' => 15], 'c.twig'),
        ];

        $detector = new ComplexityHotspotDetector();
        $hot = $detector->detectHotspots($results, 2);

        $this->assertCount(2, $hot);
        $this->assertSame('b.twig', $hot[0]['file']);
        $this->assertSame(5, $hot[0]['complexity']);
    }

    private function makeResult(array $metrics, string $rel): AnalysisResult
    {
        $file = new SplFileInfo(__FILE__, '', $rel);

        return new AnalysisResult($file, $metrics, 0.0);
    }
}
