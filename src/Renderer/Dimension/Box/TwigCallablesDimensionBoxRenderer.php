<?php

declare(strict_types=1);

namespace TwigMetrics\Renderer\Dimension\Box;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
final class TwigCallablesDimensionBoxRenderer extends AbstractDimensionBoxRenderer
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
        $this->header('TWIG CALLABLES');
        $s = $data['summary'] ?? [];
        $this->twoCols('Total Calls', $this->i($s['total_calls'] ?? 0), 'Unique Functions', $this->i($s['unique_functions'] ?? 0));
        $this->twoCols('Unique Filters', $this->i($s['unique_filters'] ?? 0), 'Unique Tests', $this->i($s['unique_tests'] ?? 0));
        $this->twoCols('Funcs/Template', $this->f($s['funcs_per_template'] ?? 0.0, 2), 'Filters/Template', $this->f($s['filters_per_template'] ?? 0.0, 2));
        $this->twoCols('Security Score', $this->i($s['security_score'] ?? 0).'/100', 'Deprecated Count', $this->i($s['deprecated_count'] ?? 0));

        $this->divider('Usage distribution');
        $this->blank();
        $d = $data['distribution'] ?? [];
        $bar = $this->distributionBar((int) ($d['core_pct'] ?? 0), (int) ($d['custom_pct'] ?? 0), (int) ($d['filters_pct'] ?? 0), (int) ($d['tests_pct'] ?? 0));
        $this->contentLine($bar);
        $this->blank();
        $legend = sprintf(
            '<fg=green>█ Core:</> <fg=#ffffff;options=bold>%d%%</>      <fg=cyan>▓ Custom:</> <fg=#ffffff;options=bold>%d%%</>      <fg=yellow>▒ Filters:</> <fg=#ffffff;options=bold>%d%%</>     <fg=red>░ Tests:</> <fg=#ffffff;options=bold>%d%%</>',
            (int) ($d['core_pct'] ?? 0), (int) ($d['custom_pct'] ?? 0), (int) ($d['filters_pct'] ?? 0), (int) ($d['tests_pct'] ?? 0)
        );
        $this->contentLine($legend);

        if (!empty($data['top_functions'])) {
            $this->divider('Top 7 Functions');
            $this->blank();
            $max = max(1, ...array_map(fn ($r) => (int) ($r['count'] ?? 0), $data['top_functions']));
            foreach ($data['top_functions'] as $row) {
                $name = $this->trim((string) ($row['name'] ?? ''), 22);
                $cnt = (int) ($row['count'] ?? 0);
                $bar = '<fg=green>'.$this->barScaled($cnt, $max, 30).'</>';
                $this->contentLine(sprintf('%-22s %5d      %s', $name.'()', $cnt, $bar));
            }
        }

        if (!empty($data['top_filters'])) {
            $this->divider('Top 7 Filters');
            $this->blank();
            $max = max(1, ...array_map(fn ($r) => (int) ($r['count'] ?? 0), $data['top_filters']));
            foreach ($data['top_filters'] as $row) {
                $name = $this->trim((string) ($row['name'] ?? ''), 22);
                $cnt = (int) ($row['count'] ?? 0);
                $bar = '<fg=cyan>'.$this->barScaled($cnt, $max, 30).'</>';
                $this->contentLine(sprintf('%-22s %5d      %s', $name.'()', $cnt, $bar));
            }
        }

        if (!empty($data['directories'])) {
            $this->divider('Callables by directory');
            $this->blank();

            $dirHeader = $this->padVisual(' ', 29);
            $cellsHeader =
                $this->padVisual('<fg=cyan>filt</>', 6).
                $this->padVisual('<fg=green>func</>', 6).
                $this->padVisual('<fg=yellow>macr</>', 7).
                $this->padVisual('<fg=#777BB3;options=bold>Tot</>', 6);
            $headerLine = $dirHeader.'      '.$cellsHeader;
            $this->contentLine($headerLine);
            $this->blank();

            $treeDirectories = $this->treeFormatter->formatAsTree($data['directories']);

            $maxTot = 1;
            foreach ($treeDirectories as $r) {
                $maxTot = max($maxTot, (int) ($r['tot'] ?? 0));
            }

            foreach ($treeDirectories as $row) {
                $treePath = $this->trim((string) ($row['tree_path'] ?? ''), 27);
                $col1 = $this->padVisual($treePath, 27);

                $flVal = (string) (int) ($row['fil'] ?? 0);
                $fnVal = (string) (int) ($row['fnc'] ?? 0);
                $mcVal = (string) (int) ($row['mac'] ?? 0);
                $ttVal = (string) (int) ($row['tot'] ?? 0);

                $flPad = max(0, 6 - mb_strlen($flVal));
                $fnPad = max(0, 6 - mb_strlen($fnVal));
                $mcPad = max(0, 6 - mb_strlen($mcVal));
                $ttPad = max(0, 6 - mb_strlen($ttVal));

                $filt = str_repeat(' ', $flPad).'<fg=cyan>'.$flVal.'</>';
                $func = str_repeat(' ', $fnPad).'<fg=green>'.$fnVal.'</>';
                $macr = str_repeat(' ', $mcPad).'<fg=yellow>'.$mcVal.'</>';
                $totl = str_repeat(' ', $ttPad).'<fg=#777BB3;options=bold>'.$ttVal.'</>';

                $filled = (int) round(((int) ($row['tot'] ?? 0) / $maxTot) * 8);
                $filled = max(0, min(8, $filled));
                $bar = '<fg=#777BB3>'.str_repeat('█', $filled).'</>'.str_repeat(' ', 8 - $filled);
                $bar = $this->padVisual($this->truncateWithColorTags($bar, 8), 8);

                $cells = $filt.$func.$macr.$totl;
                $col2 = $cells.' '.$bar;

                $line = $col1.'      '.$col2;
                $this->contentLine($line);
            }
            $this->blank();
            $this->contentLine('Legend: <fg=green>█</> = Functions, <fg=cyan>▓</> = Filters, <fg=yellow>▒</> = Macros            ');
        }

        if (!empty($data['security_issues'])) {
            $this->divider('Security Issues');
            $this->blank();
            foreach ($data['security_issues'] as $row) {
                $name = (string) ($row['name'] ?? '');
                $cnt = (int) ($row['count'] ?? 0);
                $sev = (string) ($row['severity'] ?? 'LOW');
                $this->contentLine(sprintf('%-18s %3d occurrences %s', $name, $cnt, str_pad($sev, 8, ' ', STR_PAD_LEFT)));
            }
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
            ['ch' => '▓', 'pct' => max(0, $b), 'fg' => 'cyan'],
            ['ch' => '▒', 'pct' => max(0, $c), 'fg' => 'yellow'],
            ['ch' => '░', 'pct' => max(0, $d), 'fg' => 'red'],
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

    private function barScaled(int $val, int $max, int $width): string
    {
        $filled = (int) round(($max > 0 ? $val / $max : 0) * $width);

        return str_repeat('█', $filled).str_repeat('▏', $width - $filled);
    }
}
