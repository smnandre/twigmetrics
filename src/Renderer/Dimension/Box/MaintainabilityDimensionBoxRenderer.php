<?php

declare(strict_types=1);

namespace TwigMetrics\Renderer\Dimension\Box;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
final class MaintainabilityDimensionBoxRenderer extends AbstractDimensionBoxRenderer
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
        $this->header('MAINTAINABILITY');

        $s = $data['summary'] ?? [];
        $this->twoCols('Empty Lines Ratio', $this->pct($s['empty_lines_ratio'] ?? 0.0), 'MI Average', $this->f($s['mi_avg'] ?? 0, 1));
        $this->twoCols('MI Median', $this->f($s['mi_median'] ?? 0, 1), 'Comment Density', $this->pct($s['comment_density'] ?? 0.0));
        $this->twoCols('High Risk', $this->i($s['high_risk'] ?? 0), 'Medium Risk', $this->i($s['medium_risk'] ?? 0));

        if (!empty($data['risk_distribution'])) {
            $this->divider('Risk distribution');
            $this->blank();
            $dist = $data['risk_distribution'];
            $total = max(1, array_sum($dist));
            $criticalPct = (int) round(((int) $dist['critical'] / $total) * 100);
            $highPct = (int) round(((int) $dist['high'] / $total) * 100);
            $mediumPct = (int) round(((int) $dist['medium'] / $total) * 100);
            $lowPct = 100 - $criticalPct - $highPct - $mediumPct;

            $bar = $this->riskDistributionBar($criticalPct, $highPct, $mediumPct, $lowPct);
            $this->contentLine($bar);
            $this->blank();

            $legend = sprintf(
                '<fg=red>█ Critical:</> <fg=#ffffff;options=bold>%d%%</>    <fg=yellow>▓ High:</> <fg=#ffffff;options=bold>%d%%</>    <fg=cyan>▒ Medium:</> <fg=#ffffff;options=bold>%d%%</>    <fg=green>░ Low:</> <fg=#ffffff;options=bold>%d%%</>',
                $criticalPct, $highPct, $mediumPct, $lowPct
            );
            $this->contentLine($legend);
        }

        if (!empty($data['directories'])) {
            $this->divider('Risk by directory');
            $this->blank();
            $this->contentLine('Directory                     Files AvgCx Lines Depth Risk  ');
            $this->blank();

            $treeDirectories = $this->treeFormatter->formatAsTree($data['directories']);

            foreach (array_slice($treeDirectories, 0, 8) as $row) {
                $treePath = $this->trim((string) ($row['tree_path'] ?? ''), 31);
                $col1 = str_pad($treePath, 31);

                $files = $this->i($row['files']);
                $complexity = $this->f($row['avg_complexity']);
                $lines = $this->i($row['avg_lines']);
                $depth = $this->i($row['max_depth']);
                $riskBar = $this->riskBar((float) ($row['risk'] ?? 0.0));

                $filesText = str_pad((string) $files, 4, ' ', STR_PAD_LEFT);
                $complexityText = str_pad((string) $complexity, 5, ' ', STR_PAD_LEFT);
                $linesText = str_pad((string) $lines, 5, ' ', STR_PAD_LEFT);
                $depthText = str_pad((string) $depth, 5, ' ', STR_PAD_LEFT);
                $riskText = $this->padVisual($this->truncateWithColorTags($riskBar, 6), 6);

                $col2 = sprintf('%s %s %s %s %s  ', $filesText, $complexityText, $linesText, $depthText, $riskText);

                $line = $col1.'      '.$this->padVisual($col2, 31);

                $line = $this->padVisual($line, 68);
                $this->contentLine($line);
            }
        }

        if (!empty($data['refactor_priorities'])) {
            $this->divider('Refactoring priorities');
            $this->blank();
            $rank = 1;
            foreach ($data['refactor_priorities'] as $item) {
                $template = $this->trim(basename((string) ($item['template'] ?? '')), 50);
                $risk = $this->f($item['risk'] ?? 0, 2);
                $complexity = $this->i($item['complexity'] ?? 0);
                $riskIndicator = $this->getRiskIndicator((float) ($item['risk'] ?? 0.0));

                $line = sprintf('%d. %-50s Risk: %s  Cx: %s  %s',
                    $rank, $template, $risk, $complexity, $riskIndicator);
                $this->contentLine($line);
                ++$rank;
            }
        }

        if (!empty($data['debt_analysis'])) {
            $this->divider('Technical debt analysis');
            $this->blank();
            $debt = $data['debt_analysis'];
            $debtRatio = $this->f($debt['debt_ratio'] ?? 0, 1);
            $complex = $this->i($debt['complex_templates'] ?? 0);
            $large = $this->i($debt['large_templates'] ?? 0);
            $deep = $this->i($debt['deep_templates'] ?? 0);

            $this->twoCols('Debt Ratio', $debtRatio.'%', 'Complex Templates (>20)', $complex);
            $this->twoCols('Large Templates (>200L)', $large, 'Deep Templates (>5)', $deep);
        }

        $final = $data['final'] ?? [];
        $score = (float) ($final['score'] ?? 0.0);
        $grade = (string) ($final['grade'] ?? '');
        $this->renderAnalysisFooter($score, $grade);
        $this->footer();
    }

    private function riskDistributionBar(int $critical, int $high, int $medium, int $low): string
    {
        $width = 68;
        $criticalWidth = (int) floor(($critical / 100) * $width);
        $highWidth = (int) floor(($high / 100) * $width);
        $mediumWidth = (int) floor(($medium / 100) * $width);
        $lowWidth = max(0, $width - $criticalWidth - $highWidth - $mediumWidth);

        return '<fg=red>'.str_repeat('█', $criticalWidth).'</>'
            .'<fg=yellow>'.str_repeat('█', $highWidth).'</>'
            .'<fg=cyan>'.str_repeat('█', $mediumWidth).'</>'
            .'<fg=green>'.str_repeat('█', $lowWidth).'</>';
    }

    private function riskBar(float $risk): string
    {
        $width = 10;
        $filled = (int) round($risk * $width);

        if ($risk >= 0.85) {
            return '<fg=red>'.str_repeat('█', $filled).'</>';
        } elseif ($risk >= 0.7) {
            return '<fg=yellow>'.str_repeat('█', $filled).'</>';
        } elseif ($risk >= 0.35) {
            return '<fg=cyan>'.str_repeat('█', $filled).'</>';
        } else {
            return '<fg=green>'.str_repeat('█', max(1, $filled)).'</>';
        }
    }

    private function getRiskIndicator(float $risk): string
    {
        if ($risk >= 0.85) {
            return '🔴';
        } elseif ($risk >= 0.7) {
            return '🟠';
        } elseif ($risk >= 0.35) {
            return '🟡';
        } else {
            return '🟢';
        }
    }

    private function pct(float $ratio): string
    {
        return number_format($ratio * 100.0, 1).'%';
    }
}
