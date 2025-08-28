<?php

declare(strict_types=1);

namespace TwigMetrics\Renderer\Dimension\Box;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
final class ArchitectureDimensionBoxRenderer extends AbstractDimensionBoxRenderer
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
        $this->header('ARCHITECTURE');

        $s = $data['summary'] ?? [];

        $this->twoCols('Imports/template', $this->f($s['imports_per_template'] ?? 0, 2), 'Extends/template', $this->f($s['extends_per_template'] ?? 0, 2));
        $this->twoCols('Avg Inherit Depth', $this->f($s['avg_inheritance_depth'] ?? 0, 1), 'Include/template', $this->f($s['includes_per_template'] ?? 0, 2));
        $this->twoCols('Embed (total)', $this->i($s['embeds_total'] ?? 0), 'Embed/template', $this->f($s['embeds_per_template'] ?? 0, 2));
        $this->twoCols('Blocks (total)', $this->i($s['blocks_total'] ?? 0), 'Blocks/template', $this->f($s['blocks_per_template'] ?? 0, 2));

        $this->divider('Construct');
        $this->blank();
        $ext = $s['extends_total'] ?? 0;
        $inc = $s['includes_total'] ?? 0;
        $emb = $s['embeds_total'] ?? 0;
        $blk = $s['blocks_total'] ?? 0;
        $bar = $this->constructBar($inc, $blk, $ext, $emb);
        $this->contentLine($bar);
        $this->blank();
        $legend = sprintf(
            '<fg=yellow>▒ Ext.</>:    %s     <fg=green>█ Inc.</>:  %s      <fg=red>░ Emb.</>:    %s      <fg=cyan>▓ Block</>:    %s',
            str_pad($this->i($ext), 3, ' ', STR_PAD_LEFT),
            str_pad($this->i($inc), 3, ' ', STR_PAD_LEFT),
            str_pad($this->i($emb), 3, ' ', STR_PAD_LEFT),
            str_pad($this->i($blk), 3, ' ', STR_PAD_LEFT)
        );
        $this->contentLine($legend);

        if (!empty($data['directories'])) {
            $this->divider('Heatmap by directory');
            $this->blank();

            $header = sprintf('%-31s      %-7s %-7s %-7s %-7s', 'Directory', 'Extends', 'Includes', 'Embeds', 'Blocks');
            $this->contentLine(str_pad($header, 68));
            $this->blank();

            $treeDirectories = $this->treeFormatter->formatAsTree($data['directories']);

            foreach ($treeDirectories as $row) {
                $treePath = $this->trim((string) ($row['tree_path'] ?? ''), 31);
                $col1 = str_pad($treePath, 31);

                $exBar = $this->truncateWithColorTags('<fg=#5DE6FF>'.$this->heatmapBar((float) ($row['extends_ratio'] ?? 0.0)).'</>', 7);
                $incBar = $this->truncateWithColorTags('<fg=#32DE4C>'.$this->heatmapBar((float) ($row['includes_ratio'] ?? 0.0)).'</>', 7);
                $embBar = $this->truncateWithColorTags('<fg=#429DFF>'.$this->heatmapBar((float) ($row['embeds_ratio'] ?? 0.0)).'</>', 7);
                $blkBar = $this->truncateWithColorTags('<fg=#7E7AFF>'.$this->heatmapBar((float) ($row['blocks_ratio'] ?? 0.0)).'</>', 7);

                $col2 = sprintf('%-7s %-7s %-7s %-7s', $exBar, $incBar, $embBar, $blkBar);

                $line = sprintf('%-31s      %-31s', $col1, $col2);
                $this->contentLine($line);
            }
        }

        if (!empty($data['top_referenced'])) {
            $this->divider('Most included templates');
            $this->blank();
            $rank = 1;
            foreach (array_slice($data['top_referenced'], 0, 5) as $item) {
                $template = $this->trim((string) ($item['template'] ?? ''), 50);
                $count = $this->i($item['count'] ?? 0);
                $line = sprintf('%d. %-50s %15s', $rank, $template, $count);
                $this->contentLine($line);
                ++$rank;
            }
        }

        if (!empty($data['top_blocks'])) {
            $this->divider('Most used block names');
            $this->blank();
            $topBlocks = array_slice($data['top_blocks'], 0, 8, true);
            $maxCount = max(1, ...array_map(fn ($b) => (int) ($b['count'] ?? 0), $topBlocks));

            foreach (array_chunk($topBlocks, 2) as $blocks) {
                [$leftItem, $rightItem] = $blocks + [1 => null];

                $name = str_pad((string) $leftItem['name'], 20);
                $bar = $this->blockUsageBar((int) $leftItem['count'], $maxCount, 6);
                $count = str_pad($this->i($leftItem['count']), 3, ' ', STR_PAD_LEFT);
                $leftText = sprintf('%s %s %s', $name, $count, $bar);

                if (null !== $rightItem) {
                    $name = str_pad((string) $rightItem['name'], 20);
                    $bar = $this->blockUsageBar((int) $rightItem['count'], $maxCount, 6);
                    $count = str_pad($this->i($rightItem['count']), 3, ' ', STR_PAD_LEFT);
                    $rightText = sprintf('%s %s %s', $name, $count, $bar);
                }

                $line = sprintf('%-36s      %-36s', $leftText, $rightText ?? '');
                $this->contentLine($line);
            }
        }

        $final = $data['final'] ?? [];
        $score = (float) ($final['score'] ?? 0.0);
        $grade = (string) ($final['grade'] ?? '');
        $this->renderAnalysisFooter($score, $grade);
        $this->footer();
    }

    private function constructBar(int $inc, int $blk, int $ext, int $emb): string
    {
        $total = $inc + $blk + $ext + $emb;
        if (0 === $total) {
            return str_repeat('█', 68);
        }

        $width = 68;
        $incWidth = (int) floor(($inc / $total) * $width);
        $blkWidth = (int) floor(($blk / $total) * $width);
        $extWidth = (int) floor(($ext / $total) * $width);
        $embWidth = max(0, $width - $incWidth - $blkWidth - $extWidth);

        return '<fg=green>'.str_repeat('█', $incWidth).'</>'
            .'<fg=cyan>'.str_repeat('█', $blkWidth).'</>'
            .'<fg=yellow>'.str_repeat('█', $extWidth).'</>'
            .'<fg=red>'.str_repeat('█', $embWidth).'</>';
    }

    private function heatmapBar(float $ratio): string
    {
        $width = 10;
        $intensity = (int) round($ratio * $width);

        if ($ratio < 0.1) {
            return str_repeat('░', 2);
        } elseif ($ratio < 0.4) {
            return str_repeat('█', min(3, max(1, $intensity)));
        } elseif ($ratio < 0.7) {
            return str_repeat('█', min(5, max(4, $intensity)));
        } else {
            return str_repeat('█', min(8, max(6, $intensity)));
        }
    }

    private function blockUsageBar(int $count, int $max, int $width): string
    {
        if (0 === $max) {
            return str_repeat('░', $width);
        }

        $filled = (int) round(($count / $max) * $width);

        return str_repeat('█', $filled).str_repeat('░', $width - $filled);
    }
}
