<?php

declare(strict_types=1);

namespace TwigMetrics\Renderer\Dimension\Box;

use TwigMetrics\Analyzer\AnalysisResult;
use TwigMetrics\Metric\Aggregator\DirectoryMetricsAggregator;
use TwigMetrics\Reporter\Dimension\CodeStyleMetricsReporter;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
final class CodeStyleDimensionPresenter implements DimensionPresenterInterface
{
    public function __construct(
        private readonly CodeStyleMetricsReporter $reporter,
        private readonly DirectoryMetricsAggregator $dirAgg,
    ) {
    }

    /**
     * @param AnalysisResult[] $results
     *
     * @return array<string, mixed>
     */
    public function present(array $results, int $maxDepth = 4, bool $includeDirs = true): array
    {
        $card = $this->reporter->generateMetrics($results);
        $core = $card->coreMetrics;
        $detail = $card->detailMetrics;
        $dist = $card->distributions['line_length'] ?? [];

        $p = static fn (string $k): int => (int) round((float) ($dist[$k]['percentage'] ?? 0.0));
        $b0 = $p('<=80');
        $b1 = $p('81-120');
        $b2 = $p('121-160');
        $b3 = $p('>160');

        $dirs = [];
        if ($includeDirs && $maxDepth > 0) {
            $aggr = $this->dirAgg->aggregateByDirectory($results, $maxDepth);
            foreach ($aggr as $path => $m) {
                $score = $m->getAverageFormatScore();
                $level = $this->levelFromScore($score);
                $dirs[] = [
                    'path' => $path,
                    'score' => $score,
                    'bar_ratio' => min(1.0, $score / 100.0),
                    'level' => $level,
                ];
            }
        }

        $viol = [
            'long_lines_files' => (int) ($detail['long_lines_files'] ?? 0),
            'trailing_spaces_lines' => (int) ($detail['trailing_spaces_files'] ?? 0),
            'mixed_indent_files' => (int) ($detail['mixed_indent_files'] ?? 0),
            'inconsistent_spacing' => 0,
            'missing_final_newline' => 0,
        ];

        $totalFiles = max(1, count($results));
        $mixedIndentFiles = (int) ($detail['mixed_indent_files'] ?? 0);
        $indentConsistency = 100.0 - min(100.0, ($mixedIndentFiles / $totalFiles) * 100.0);

        $sumComments = 0;
        foreach ($results as $r) {
            $sumComments += (int) ($r->getMetric('comment_lines') ?? 0);
        }
        $commentsPerTemplate = $sumComments / $totalFiles;

        return [
            'summary' => [
                'avg_line_length' => (float) ($core['consistency'] ?? 0) > 0 ? (float) ($core['p95_line_length'] ?? 0) * 0.6 : 0.0,
                'max_line_length' => (int) ($core['p95_line_length'] ?? 0),

                'indent_consistency' => $indentConsistency,
                'p95_length' => (int) ($core['p95_line_length'] ?? 0),
                'consistency_score' => (float) ($core['consistency'] ?? 0.0),
                'style_violations' => array_sum($viol),

                'comments_per_template' => $commentsPerTemplate,
                'mixed_indentation' => (int) ($detail['mixed_indent_files'] ?? 0),
            ],
            'distribution' => [
                'le_80' => $b0,
                '81_120' => $b1,
                '121_160' => $b2,
                'gt_160' => $b3,
            ],
            'formatting' => [
                'trailing_spaces' => (int) ($detail['trailing_spaces_files'] ?? 0),
                'readability_score' => (float) ($core['readability'] ?? 0.0),
                'empty_lines_ratio' => (float) ($detail['comment_density_avg'] ?? 0.0),
                'comment_density' => (float) ($detail['comment_density_avg'] ?? 0.0),
                'format_entropy' => (float) ($core['entropy'] ?? 0.0),
            ],
            'directories' => $dirs,
            'violations' => $viol,
            'final' => [
                'score' => (float) $card->score,
                'grade' => (string) $card->grade,
            ],
        ];
    }

    private function levelFromScore(float $score): string
    {
        return match (true) {
            $score >= 95 => 'excellent',
            $score >= 80 => 'good',
            default => 'needs work',
        };
    }
}
