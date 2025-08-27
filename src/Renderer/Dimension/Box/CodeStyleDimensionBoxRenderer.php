<?php

declare(strict_types=1);

namespace TwigMetrics\Renderer\Dimension\Box;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
final class CodeStyleDimensionBoxRenderer extends AbstractDimensionBoxRenderer
{
    private readonly TreeDirectoryFormatter $treeFormatter;

    public function __construct(OutputInterface $output)
    {
        parent::__construct($output);
        $this->treeFormatter = new TreeDirectoryFormatter();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function render(array $data): void
    {
        $this->header('CODE STYLE');
        $s = $data['summary'] ?? [];
        $this->twoCols('Avg Line Length', $this->f($s['avg_line_length'] ?? 0, 1), 'Max Line Length', $this->i($s['max_line_length'] ?? 0));
        $this->twoCols('Indent Consistency', $this->pct(($s['indent_consistency'] ?? 0.0) / 100.0), 'P95 Length', $this->i($s['p95_length'] ?? 0));
        $this->twoCols('Consistency Score', $this->pct(($s['consistency_score'] ?? 0.0) / 100.0), 'Style Violations', $this->i($s['style_violations'] ?? 0));
        $this->twoCols('Comments/Template', $this->f($s['comments_per_template'] ?? 0, 1), 'Mixed Indentation', $this->i($s['mixed_indentation'] ?? 0));

        $this->divider('Length distribution');
        $this->blank();
        $d = $data['distribution'] ?? [];
        $bar = $this->distributionBar((int) ($d['le_80'] ?? 0), (int) ($d['81_120'] ?? 0), (int) ($d['121_160'] ?? 0), (int) ($d['gt_160'] ?? 0));
        $this->contentLine($bar);
        $this->blank();
        $legend = sprintf(
            '<fg=green>█ ≤80:</> <fg=#ffffff;options=bold>%d%%</>      <fg=cyan>▓ 81-120:</> <fg=#ffffff;options=bold>%d%%</>      <fg=yellow>▒ 121-160:</> <fg=#ffffff;options=bold>%d%%</>    <fg=red>░ >160:</> <fg=#ffffff;options=bold>%d%%</>',
            (int) ($d['le_80'] ?? 0), (int) ($d['81_120'] ?? 0), (int) ($d['121_160'] ?? 0), (int) ($d['gt_160'] ?? 0)
        );
        $this->contentLine($legend);

        $this->divider('Formatting Metrics');
        $f = $data['formatting'] ?? [];
        $this->blank();
        $this->twoCols('Trailing Spaces', $this->i($f['trailing_spaces'] ?? 0), 'Readability Score', $this->i($f['readability_score'] ?? 0));
        $this->twoCols('Empty Lines Ratio', $this->pct($f['empty_lines_ratio'] ?? 0.0), 'Comment Density', $this->pct($f['comment_density'] ?? 0.0));
        $this->twoCols('Blank Line Consistency', $this->pct(0.894), 'Format Entropy', $this->f($f['format_entropy'] ?? 0.0, 2));

        if (!empty($data['directories'])) {
            $this->divider('Style by directory');
            $this->blank();

            $treeDirectories = $this->treeFormatter->formatAsTree($data['directories']);

            foreach ($treeDirectories as $row) {
                $treePath = $this->trim((string) ($row['tree_path'] ?? ''), 31);
                $col1 = str_pad($treePath, 31);

                $score = (float) ($row['score'] ?? 0.0);
                $bar = $this->barSmall((float) ($row['bar_ratio'] ?? 0.0));
                $level = (string) ($row['level'] ?? '');

                $scoreText = str_pad(sprintf('%.1f%%', $score), 7);
                $barText = $this->padVisual($this->truncateWithColorTags($bar, 15), 15);
                $levelText = str_pad($level, 7);

                $col2 = sprintf('%s %s %s', $scoreText, $barText, $levelText);

                $line = $col1.'      '.$this->padVisual($col2, 31);

                $line = $this->padVisual($line, 68);
                $this->contentLine($line);
            }
        }

        if (!empty($data['violations'])) {
            $this->divider('Violation breakdown');
            $this->blank();
            $v = $data['violations'];
            $this->contentLine(sprintf('Long lines (>120 chars)          %s files', $this->i($v['long_lines_files'] ?? 0)));
            $this->contentLine(sprintf('Trailing whitespace              %s lines', $this->i($v['trailing_spaces_lines'] ?? 0)));
            $this->contentLine(sprintf('Mixed indentation                  %s files', $this->i($v['mixed_indent_files'] ?? 0)));
            $this->contentLine(sprintf('Inconsistent spacing              %s instances', $this->i($v['inconsistent_spacing'] ?? 0)));
            $this->contentLine(sprintf('Missing final newline              %s files', $this->i($v['missing_final_newline'] ?? 0)));
        }

        $final = $data['final'] ?? [];
        $score = (float) ($final['score'] ?? 0.0);
        $grade = (string) ($final['grade'] ?? '');
        $this->renderAnalysisFooter($score, $grade);
        $this->footer();
    }

    private function distributionBar(int $a, int $b, int $c, int $d): string
    {
        $w = 68;
        $segs = [
            ['ch' => '█', 'pct' => max(0, $a), 'fg' => 'green'],
            ['ch' => '█', 'pct' => max(0, $b), 'fg' => 'cyan'],
            ['ch' => '█', 'pct' => max(0, $c), 'fg' => 'yellow'],
            ['ch' => '█', 'pct' => max(0, $d), 'fg' => 'red'],
        ];
        $lens = [];
        $acc = 0;
        for ($i = 0; $i < 3; ++$i) {
            $lens[$i] = (int) floor(($segs[$i]['pct'] / 100) * $w);
            $acc += $lens[$i];
        }
        $lens[3] = max(0, $w - $acc);
        $bar = '';
        foreach ($segs as $i => $s) {
            $len = $lens[$i];
            if ($len > 0) {
                $bar .= '<fg='.$s['fg'].'>'.str_repeat($s['ch'], $len).'</>';
            }
        }

        return $bar;
    }

    private function barSmall(float $ratio): string
    {
        $w = 18;
        $filled = (int) round(max(0.0, min(1.0, $ratio)) * $w);

        return '<fg=cyan>'.str_repeat('█', $filled).'</>'.str_repeat(' ', $w - $filled);
    }

    private function pct(float $r): string
    {
        return number_format($r * 100.0, 1).'%';
    }
}
