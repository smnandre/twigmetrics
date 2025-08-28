<?php

declare(strict_types=1);

namespace TwigMetrics\Renderer\Dimension\Box;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
final class ComplexityDimensionBoxRenderer extends AbstractDimensionBoxRenderer
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
        $this->header('LOGICAL COMPLEXITY');
        $s = $data['summary'] ?? [];

        $this->twoCols(
            'Average Complexity', $this->f($s['avg'] ?? 0, 1),
            'Max Complexity', (string) ($s['max'] ?? 0)
        );
        $this->twoCols('Median Complexity', $this->f($s['median'] ?? 0, 1), 'Critical Files', (string) ($s['critical_files'] ?? 0));
        $this->twoCols('IFs/Template', $this->f($s['ifs_per_template'] ?? 0, 2), 'FORs/Template', $this->f($s['fors_per_template'] ?? 0, 2));
        $this->twoCols('Avg Nesting Depth', $this->f($s['avg_depth'] ?? 0, 1), 'Max Nesting Depth', (string) ($s['max_depth'] ?? 0));

        $this->divider('Complexity distribution');
        $this->blank();
        $d = $data['distribution'] ?? [];
        $bar = $this->distributionBar(
            (int) ($d['simple_pct'] ?? 0),
            (int) ($d['moderate_pct'] ?? 0),
            (int) ($d['complex_pct'] ?? 0),
            (int) ($d['critical_pct'] ?? 0)
        );
        $this->contentLine($bar);
        $this->blank();
        $legend = sprintf(
            '<fg=green>█ Simple</> <fg=#ffffff;options=bold>%d%%</>      <fg=cyan>▓ Medium</> <fg=#ffffff;options=bold>%d%%</>      <fg=yellow>▒ High</> <fg=#ffffff;options=bold>%d%%</>       <fg=red>░ Complex</> <fg=#ffffff;options=bold>%d%%</>',
            (int) ($d['simple_pct'] ?? 0),
            (int) ($d['moderate_pct'] ?? 0),
            (int) ($d['complex_pct'] ?? 0),
            (int) ($d['critical_pct'] ?? 0)
        );
        $this->contentLine($legend);

        $this->divider('Statistical Metrics');
        $st = $data['stats'] ?? [];
        $this->blank();
        $this->twoCols('Maintainability Index', $this->f($st['mi_avg'] ?? 0, 1), 'Cyclomatic/LOC', $this->f($st['cyclomatic_per_loc'] ?? 0, 2));
        $this->twoCols('Cognitive Complexity', (string) ($st['cognitive_complexity'] ?? 'N/A'), 'Halstead Volume', (string) ($st['halstead_volume'] ?? 'N/A'));
        $this->twoCols('Control Flow Nodes', (string) ($st['control_flow_nodes'] ?? 'N/A'), 'Logical Operators', (string) ($st['logical_operators'] ?? 'N/A'));

        if (!empty($data['directories'])) {
            $this->divider('Complexity by directory');
            $this->blank();

            $treeDirectories = $this->treeFormatter->formatAsTree($data['directories']);

            foreach ($treeDirectories as $row) {
                $treePath = $this->trim((string) ($row['tree_path'] ?? ''), 31);
                $col1 = str_pad($treePath, 31);

                $avgCx = $this->f($row['avg_cx'] ?? 0, 1);
                $bar = $this->barSmall((float) ($row['avg_cx'] ?? 0));
                $risk = $this->riskColor($this->trim($row['risk'] ?? '', 8));

                $avgCxText = str_pad((string) $avgCx, 5);
                $barText = $this->padVisual($this->truncateWithColorTags($bar, 16), 16);
                $riskText = $this->padVisual($risk, 8);

                $col2 = sprintf('%s %s %s', $avgCxText, $barText, $riskText);

                $line = $col1.'      '.$this->padVisual($col2, 31);

                $line = $this->padVisual($line, 68);
                $this->contentLine($line);
            }
        }

        if (!empty($data['top'])) {
            $this->divider('Top 5 templates');
            $this->blank();
            $rank = 1;
            foreach ($data['top'] as $row) {
                $path = $this->trim((string) ($row['path'] ?? ''), 44);
                $score = sprintf('%5.1f', (float) ($row['score'] ?? 0));
                $grade = (string) ($row['grade'] ?? '');
                $line = sprintf('%2d. %-44s %6s          %s', $rank, $path, $score, $this->gradeColor($grade));
                $this->contentLine($line);
                ++$rank;
                if ($rank > 5) {
                    break;
                }
            }
        }

        $final = $data['final'] ?? [];
        $score = (float) ($final['score'] ?? 0.0);
        $grade = (string) ($final['grade'] ?? '');
        $this->renderAnalysisFooter($score, $grade);
        $this->footer();
    }

    private function distributionBar(int $simple, int $moderate, int $complex, int $critical): string
    {
        $width = 68;

        $pcts = [
            ['char' => '█', 'pct' => max(0, $simple), 'fg' => 'green'],
            ['char' => '█', 'pct' => max(0, $moderate), 'fg' => 'cyan'],
            ['char' => '█', 'pct' => max(0, $complex), 'fg' => 'yellow'],
            ['char' => '█', 'pct' => max(0, $critical), 'fg' => 'red'],
        ];

        $lengths = [];
        $acc = 0;

        for ($i = 0; $i < 3; ++$i) {
            $len = (int) floor(($pcts[$i]['pct'] / 100) * $width);
            $lengths[$i] = $len;
            $acc += $len;
        }

        $lengths[3] = max(0, $width - $acc);

        $bar = '';
        foreach ($pcts as $i => $seg) {
            $len = $lengths[$i];
            if ($len <= 0) {
                continue;
            }
            $bar .= '<fg='.$seg['fg'].'>'.str_repeat($seg['char'], $len).'</>';
        }

        return $bar;
    }

    private function barSmall(float $score): string
    {
        $width = 20;
        $max = 25.0;
        $filled = (int) round(min(1.0, $score / $max) * $width);
        $empty = $width - $filled;

        return '<fg=yellow>'.str_repeat('█', $filled).'</>'.str_repeat(' ', $empty);
    }
}
