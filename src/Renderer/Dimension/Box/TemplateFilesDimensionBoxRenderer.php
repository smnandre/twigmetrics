<?php

declare(strict_types=1);

namespace TwigMetrics\Renderer\Dimension\Box;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
final class TemplateFilesDimensionBoxRenderer extends AbstractDimensionBoxRenderer
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
        $this->header('TEMPLATE FILES');

        $s = $data['summary'] ?? [];
        $this->twoCols('Total Templates', $this->i($s['total_templates'] ?? 0), 'Total Lines', $this->i($s['total_lines'] ?? 0));
        $this->twoCols('Average Lines/File', $this->f($s['avg_lines'] ?? 0, 1), 'Median Lines', $this->f($s['median_lines'] ?? 0, 0));
        $this->twoCols('Size Coefficient (CV)', $this->f($s['cv'] ?? 0, 2), 'Gini Index', $this->f($s['gini'] ?? 0, 3));
        $this->twoCols('Directories', $this->i($s['directories'] ?? 0), 'Characters', $this->i($s['characters'] ?? 0));

        $this->divider('Size distribution');
        $this->blank();
        $d = $data['distribution'] ?? [];
        $bar = $this->distributionBar((int) ($d['0_50'] ?? 0), (int) ($d['51_100'] ?? 0), (int) ($d['101_200'] ?? 0), (int) ($d['201_plus'] ?? 0));
        $this->contentLine($bar);
        $this->blank();
        $legend = sprintf(
            '<fg=green>█ 0-50:</> <fg=#ffffff;options=bold>%d%%</>     <fg=cyan>▓ 51-100:</> <fg=#ffffff;options=bold>%d%%</>       <fg=yellow>▒ 101-200:</> <fg=#ffffff;options=bold>%d%%</>     <fg=red>░ 201+:</> <fg=#ffffff;options=bold>%d%%</>',
            (int) ($d['0_50'] ?? 0),
            (int) ($d['51_100'] ?? 0),
            (int) ($d['101_200'] ?? 0),
            (int) ($d['201_plus'] ?? 0)
        );
        $this->contentLine($legend);

        $this->divider('Statistical Metrics');
        $st = $data['stats'] ?? [];
        $this->blank();
        $this->twoCols('Standard Deviation', $this->f($st['std_dev'] ?? 0, 1), '95th Percentile', $this->i($st['p95'] ?? 0));
        $this->twoCols('Files >500 lines', $this->i($st['files_over_500'] ?? 0), 'Orphan Templates', $this->i($st['orphans'] ?? 0));
        $this->twoCols('Shannon Entropy', $this->f($st['entropy'] ?? 0, 2), 'Dir Depth Avg', $this->f($st['dir_depth_avg'] ?? 0, 1));

        if (!empty($data['directories'])) {
            $this->divider('Files by directory');
            $this->blank();

            $treeDirectories = $this->treeFormatter->formatAsTree($data['directories']);

            foreach ($treeDirectories as $row) {
                $treePath = $this->trim((string) ($row['tree_path'] ?? ''), 31);
                $col1 = str_pad($treePath, 31);

                $count = (int) ($row['count'] ?? 0);
                $avgLines = $this->f($row['avg_lines'] ?? 0, 0);
                $bar = $this->barSmall((float) ($row['bar_ratio'] ?? 0.0));

                $countText = str_pad((string) $count, 3, ' ', STR_PAD_LEFT);
                $barText = $this->padVisual($this->truncateWithColorTags($bar, 20), 20);
                $avgText = str_pad(sprintf('%s avg', $avgLines), 6);

                $col2 = sprintf('%s %s %s', $countText, $barText, $avgText);

                $line = $col1.'      '.$this->padVisual($col2, 31);

                $line = $this->padVisual($line, 68);
                $this->contentLine($line);
            }
        }

        if (!empty($data['top'])) {
            $this->divider('Largest templates');
            $this->blank();
            $rank = 1;
            foreach ($data['top'] as $row) {
                $path = $this->trim((string) ($row['path'] ?? ''), 44);
                $lines = sprintf('%5d lines', (int) ($row['lines'] ?? 0));
                $grade = (string) ($row['grade'] ?? '');
                $line = sprintf('%2d. %-44s %13s      %s', $rank, $path, $lines, $grade);
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

    private function distributionBar(int $b0, int $b1, int $b2, int $b3): string
    {
        $width = 68;
        $segments = [
            ['char' => '█', 'pct' => max(0, $b0), 'fg' => 'green'],
            ['char' => '▓', 'pct' => max(0, $b1), 'fg' => 'cyan'],
            ['char' => '▒', 'pct' => max(0, $b2), 'fg' => 'yellow'],
            ['char' => '░', 'pct' => max(0, $b3), 'fg' => 'red'],
        ];
        $lens = [];
        $acc = 0;
        for ($i = 0; $i < 3; ++$i) {
            $lens[$i] = (int) floor(($segments[$i]['pct'] / 100) * $width);
            $acc += $lens[$i];
        }
        $lens[3] = max(0, $width - $acc);
        $bar = '';
        foreach ($segments as $i => $seg) {
            $len = $lens[$i];
            if ($len > 0) {
                $bar .= '<fg='.$seg['fg'].'>'.str_repeat($seg['char'], $len).'</>';
            }
        }

        return $bar;
    }

    private function barSmall(float $ratio): string
    {
        $width = 20;
        $filled = (int) round(max(0.0, min(1.0, $ratio)) * $width);
        $empty = $width - $filled;

        return '<fg=cyan>'.str_repeat('█', $filled).'</>'.str_repeat(' ', $empty);
    }
}
