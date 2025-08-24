<?php

declare(strict_types=1);

namespace TwigMetrics\Renderer\Component;

use Symfony\Component\Console\Output\OutputInterface;
use TwigMetrics\Analyzer\AnalysisResult;

/**
 * Renders directory tree visualization with data visualization bars and charts.
 */
final class DirectoryTreeRenderer
{
    public function __construct(
        private OutputInterface $output,
    ) {
    }

    /**
     * @param AnalysisResult[] $results
     *
     * @return array<string, array{children: array<string, mixed>, files: list<AnalysisResult>, path: non-empty-string, metrics: array<string, mixed>}>
     */
    private function buildDirectoryTree(array $results, int $maxDepth): array
    {
        /** @var array<string, array{children: array<string, mixed>, files: list<AnalysisResult>, path: non-empty-string, metrics: array<string, mixed>}> $tree */
        $tree = [];

        foreach ($results as $result) {
            $path = $result->getRelativePath();
            $parts = explode('/', dirname($path));

            if (count($parts) > $maxDepth) {
                $parts = array_slice($parts, 0, $maxDepth);
            }

            $current = &$tree;
            /** @var array<string, array{children: array<string, mixed>, files: list<AnalysisResult>, path: non-empty-string, metrics: array<string, mixed>}> $current */
            $currentPath = '';

            foreach ($parts as $i => $part) {
                /*
                 * @phpstan-var array<string, array{children: array<string, mixed>, files: list<AnalysisResult>, path: non-empty-string, metrics: array<string, mixed>}> $current
                 */
                if ('.' === $part || '' === $part) {
                    continue;
                }

                $currentPath = $currentPath ? $currentPath.'/'.$part : $part;

                if (!isset($current[$part])) {
                    $current[$part] = [
                        'children' => [],
                        'files' => [],
                        'path' => $currentPath,
                        'metrics' => [],
                    ];
                }

                if ($i === count($parts) - 1 || ($i === $maxDepth - 1 && count($parts) > $maxDepth)) {
                    $current[$part]['files'][] = $result;
                }

                $current = &$current[$part]['children'];
            }
        }

        return $tree;
    }

    /**
     * Render a compact metrics table for directories.
     *
     * @param AnalysisResult[]      $results
     * @param array<string, string> $metrics Array of metric_name => display_label
     */
    public function renderDirectoryMetricsTable(
        array $results,
        array $metrics,
        int $maxDepth = 2,
    ): void {
        if (empty($results) || empty($metrics)) {
            return;
        }

        $tree = $this->buildDirectoryTree($results, $maxDepth);
        $directoriesData = [];

        foreach ($tree as $name => $node) {
            $data = ['name' => $name, 'files' => 0];

            foreach ($metrics as $metricName => $label) {
                $total = 0;
                $files = $node['files'];

                if (!empty($node['children'])) {
                    $files = array_merge($files, $this->getFilesRecursive($node['children']));
                }

                foreach ($files as $file) {
                    $total += (float) ($file->getMetric($metricName) ?? 0);
                }

                $data[$metricName] = $total;
                $data['files'] = count($files);
            }

            $directoriesData[] = $data;
        }

        usort($directoriesData, fn ($a, $b) => strcmp($a['name'], $b['name']));

        $this->output->writeln('');
        $this->renderCompactMetricsTable($directoriesData, $metrics);
    }

    /**
     * Render a heatmap-style tree visualization for callables/architecture metrics.
     *
     * @param AnalysisResult[]      $results
     * @param array<string, string> $metrics Array of metric_name => display_label
     */
    public function renderDirectoryHeatmapTree(
        array $results,
        array $metrics,
        string $title = 'Directory Metrics',
        int $maxDepth = 2,
    ): void {
        if (empty($results) || empty($metrics)) {
            return;
        }

        $tree = $this->buildDirectoryTree($results, $maxDepth);
        $directoriesData = [];
        $maxValues = [];

        foreach ($tree as $name => $node) {
            $data = ['name' => $name, 'files' => 0];

            foreach ($metrics as $metricName => $label) {
                $total = 0;
                $files = $node['files'];

                if (!empty($node['children'])) {
                    $files = array_merge($files, $this->getFilesRecursive($node['children']));
                }

                foreach ($files as $file) {
                    $total += (float) ($file->getMetric($metricName) ?? 0);
                }

                $data[$metricName] = $total;
                $maxValues[$metricName] = max($maxValues[$metricName] ?? 0, $total);
            }

            $data['files'] = count($files);
            $directoriesData[] = $data;
        }

        usort($directoriesData, fn ($a, $b) => strcmp($a['name'], $b['name']));

        $this->output->writeln('');

        $this->renderHeatmapTreeHeader($metrics);
        $this->renderHeatmapTreeNodes($directoriesData, $metrics, $maxValues);
    }

    /**
     * @param array<string, array{children: array<string, mixed>, files: list<AnalysisResult>, path: non-empty-string, metrics: array<string, mixed>}> $children
     *
     * @return list<AnalysisResult>
     */
    private function getFilesRecursive(array $children): array
    {
        $files = [];

        foreach ($children as $child) {
            foreach ($child['files'] as $file) {
                $files[] = $file;
            }
            $files = [...$files, ...$this->getFilesRecursive($child['children'])];
        }

        return $files;
    }

    /**
     * @param array<int, array<string, mixed>> $directoriesData
     * @param array<string, string>            $metrics
     */
    private function renderCompactMetricsTable(array $directoriesData, array $metrics): void
    {
        if (empty($directoriesData)) {
            return;
        }

        $maxValues = [];
        foreach (array_keys($metrics) as $metricName) {
            $maxValues[$metricName] = max(array_column($directoriesData, $metricName));
        }

        $header = '    Directory                ';
        foreach ($metrics as $metricName => $label) {
            $header .= sprintf('%8s', substr($label, 0, 8));
        }
        $header .= ' Grade';

        $this->output->writeln('<fg=white;options=bold>'.$header.'</>');

        $this->output->writeln('  <fg=#666>'.str_repeat('─', 70).'</>');

        foreach ($directoriesData as $data) {
            $line = sprintf('    <fg=white>%-20s</>', $data['name'].'/');

            foreach ($metrics as $metricName => $label) {
                $value = $data[$metricName];
                $maxValue = $maxValues[$metricName];

                $cell = $this->createArchitectureCell($value, $maxValue);
                $line .= $cell;
            }

            $avgPercentage = array_sum(array_map(fn ($metric) => $maxValues[$metric] > 0 ? $data[$metric] / $maxValues[$metric] : 0,
                array_keys($metrics)
            )) / count($metrics);

            $grade = match (true) {
                $avgPercentage >= 0.9 => '<fg=green;options=bold>A+</>',
                $avgPercentage >= 0.8 => '<fg=green>A</>',
                $avgPercentage >= 0.7 => '<fg=yellow>B+</>',
                $avgPercentage >= 0.6 => '<fg=yellow>B</>',
                $avgPercentage >= 0.5 => '<fg=red>C+</>',
                default => '<fg=red;options=bold>C</>',
            };

            $line .= sprintf('    %s', $grade);

            $this->output->writeln($line);
        }
    }

    /**
     * @param array<string, string> $metrics
     */
    private function renderHeatmapTreeHeader(array $metrics): void
    {
        $header = sprintf('    <fg=white>%-24s</>', 'Directory');

        foreach ($metrics as $metricName => $label) {
            $header .= sprintf('    <fg=gray>%4s</>', $label);
        }

        $this->output->writeln($header);

        $separatorLine = sprintf('    <fg=#666>%s</>', str_repeat('─', 24));
        foreach ($metrics as $metricName => $label) {
            $separatorLine .= sprintf('    <fg=#666>%s</>', str_repeat('─', 4));
        }
        $this->output->writeln($separatorLine);
    }

    /**
     * @param array<int, array<string, mixed>> $directoriesData
     * @param array<string, string>            $metrics
     * @param array<string, float>             $maxValues
     */
    private function renderHeatmapTreeNodes(array $directoriesData, array $metrics, array $maxValues): void
    {
        $totalEntries = count($directoriesData);

        foreach ($directoriesData as $index => $data) {
            $isLast = $index === $totalEntries - 1;
            $symbol = $isLast ? '└─' : '├─';

            $fileCount = $data['files'] ?? 0;
            $line = sprintf(
                '    <fg=white>%s</> <fg=white>%-18s/</> <fg=gray>( %d )</>',
                $symbol,
                $data['name'],
                $fileCount,
            );

            foreach ($metrics as $metricName => $label) {
                $value = $data[$metricName] ?? 0;

                if ($value > 0) {
                    $line .= sprintf('    <fg=white>%3.0f</>', $value);
                } else {
                    $line .= '    <fg=gray>  0</>';
                }
            }

            $this->output->writeln($line);
        }
    }

    /**
     * Render a compact dual-metric progress bar visualization.
     *
     * @param AnalysisResult[]      $results
     * @param array<string, string> $metrics Array of exactly 2 metrics: metric_name => display_label
     */
    public function renderDirectoryDualProgressBars(
        array $results,
        array $metrics,
        string $title = 'Directory Comparison',
        int $maxDepth = 2,
        int $maxScore = 100,
    ): void {
        if (empty($results) || 2 !== count($metrics)) {
            return;
        }

        $tree = $this->buildDirectoryTree($results, $maxDepth);
        $directoriesData = [];
        $maxValues = [];

        foreach ($tree as $name => $node) {
            $data = ['name' => $name, 'files' => 0];

            foreach ($metrics as $metricName => $label) {
                $total = 0;
                $files = $node['files'];

                if (!empty($node['children'])) {
                    $files = array_merge($files, $this->getFilesRecursive($node['children']));
                }

                foreach ($files as $file) {
                    $total += (float) ($file->getMetric($metricName) ?? 0);
                }

                $avgTotal = count($files) > 0 ? $total / count($files) : 0;
                $data[$metricName] = $avgTotal;
                $maxValues[$metricName] = max($maxValues[$metricName] ?? 0, $avgTotal);
            }

            $data['files'] = count($files);
            $directoriesData[] = $data;
        }

        usort($directoriesData, fn ($a, $b) => strcmp($a['name'], $b['name']));

        $this->output->writeln('');

        $this->renderDualProgressBarLines($directoriesData, $metrics, $maxValues, $maxScore);
    }

    /**
     * @param array<int, array<string, mixed>> $directoriesData
     * @param array<string, string>            $metrics
     * @param array<string, float>             $maxValues
     */
    private function renderDualProgressBarLines(array $directoriesData, array $metrics, array $maxValues, int $maxScore): void
    {
        $metricNames = array_keys($metrics);
        $metric1 = $metricNames[0];
        $metric2 = $metricNames[1];

        foreach ($directoriesData as $data) {
            $name = $data['name'];
            $value1 = $data[$metric1] ?? 0;
            $value2 = $data[$metric2] ?? 0;

            $score1 = min($maxScore, (int) ($value1 / max($maxValues[$metric1], 1) * $maxScore));
            $score2 = min($maxScore, (int) ($value2 / max($maxValues[$metric2], 1) * $maxScore));

            $bar1 = $this->createCompactProgressBar($score1, $maxScore, 13);
            $bar2 = $this->createCompactProgressBar($score2, $maxScore, 13);

            $dirCol = sprintf('<fg=cyan>%-18s</>', $name.'/');
            $line = sprintf(
                '    %s  %3d/%d [%s]   %3d/%d [%s]',
                $dirCol,
                $score1,
                $maxScore,
                $bar1,
                $score2,
                $maxScore,
                $bar2
            );

            $this->output->writeln($line.' ');
        }
    }

    private function createCompactProgressBar(int $score, int $maxScore, int $width): string
    {
        $percentage = $maxScore > 0 ? $score / $maxScore : 0;
        $filled = (int) ($percentage * $width);
        $empty = $width - $filled;

        $color = match (true) {
            $percentage >= 0.8 => 'green',
            $percentage >= 0.6 => 'yellow',
            $percentage >= 0.4 => 'red',
            default => 'gray',
        };

        $bar = sprintf(
            '<fg=%s>%s</><fg=gray>%s</>',
            $color,
            str_repeat('█', $filled),
            str_repeat('░', $empty)
        );

        return $bar;
    }

    /**
     * Render stacked horizontal bars showing multiple metrics per directory.
     *
     * @param AnalysisResult[]      $results
     * @param array<string, string> $metrics Array of metric_name => display_label
     */
    public function renderDirectoryStackedBars(
        array $results,
        array $metrics,
        string $title = 'Directory Overview',
        int $maxDepth = 2,
        int $barWidth = 30,
    ): void {
        if (empty($results) || empty($metrics)) {
            return;
        }

        $tree = $this->buildDirectoryTree($results, $maxDepth);
        $directoriesData = [];
        $maxValues = [];

        foreach ($tree as $name => $node) {
            $data = ['name' => $name, 'files' => 0];

            foreach ($metrics as $metricName => $label) {
                $total = 0;
                $files = $node['files'];

                if (!empty($node['children'])) {
                    $files = array_merge($files, $this->getFilesRecursive($node['children']));
                }

                foreach ($files as $file) {
                    $total += (float) ($file->getMetric($metricName) ?? 0);
                }

                $data[$metricName] = match ($metricName) {
                    'lines', 'chars', 'nodes' => $total,
                    default => count($files) > 0 ? $total / count($files) : 0,
                };

                $maxValues[$metricName] = max($maxValues[$metricName] ?? 0, $data[$metricName]);
            }

            $data['files'] = count($files);
            $directoriesData[] = $data;
        }

        usort($directoriesData, fn ($a, $b) => strcmp($a['name'], $b['name']));

        $this->output->writeln('');

        $this->renderStackedBarLines($directoriesData, $metrics, $maxValues, $barWidth);
    }

    /**
     * @param array<int, array<string, mixed>> $directoriesData
     * @param array<string, string>            $metrics
     * @param array<string, float>             $maxValues
     */
    private function renderStackedBarLines(array $directoriesData, array $metrics, array $maxValues, int $barWidth): void
    {
        $totalEntries = count($directoriesData);

        foreach ($directoriesData as $index => $data) {
            $name = $data['name'];
            $fileCount = $data['files'];
            $isLast = $index === $totalEntries - 1;
            $symbol = $isLast ? '└─' : '├─';

            $stackedBar = $this->createStackedBar($data, $metrics, $maxValues, $barWidth);

            $line = sprintf(
                '    <fg=white>%s</> <fg=cyan;options=bold>%-18s/</> %s    ',
                $symbol,
                $name,
                $stackedBar
            );

            $this->output->writeln($line);
        }
    }

    /**
     * Create a stacked bar with gradient segments for multiple metrics.
     *
     * @param array<string, mixed>  $data
     * @param array<string, string> $metrics
     * @param array<string, float>  $maxValues
     */
    private function createStackedBar(array $data, array $metrics, array $maxValues, int $barWidth): string
    {
        $totalValue = 0;
        $values = [];

        foreach ($metrics as $metricName => $label) {
            $value = $data[$metricName] ?? 0;
            $values[$metricName] = $value;
            $totalValue += $value;
        }

        if (0 == $totalValue) {
            return '<fg=gray>'.str_repeat('░', $barWidth).'</>';
        }

        $segments = [];
        $colors = ['#2E8B57', '#4682B4', '#9370DB', '#CD853F'];
        $usedWidth = 0;
        $metricIndex = 0;

        foreach ($metrics as $metricName => $label) {
            $value = $values[$metricName];

            if ($value > 0) {
                $proportion = $value / $totalValue;
                $segmentWidth = (int) round($proportion * $barWidth);

                if ($segmentWidth > 0 && $usedWidth + $segmentWidth <= $barWidth) {
                    $color = $colors[$metricIndex % count($colors)];
                    $intensity = min(1.0, $value / max($maxValues[$metricName], 1));

                    $segment = $this->createGradientSegment($segmentWidth, $intensity, $color, '█');
                    $segments[] = $segment;
                    $usedWidth += $segmentWidth;
                }
            }

            ++$metricIndex;
        }

        $remainingWidth = $barWidth - $usedWidth;
        if ($remainingWidth > 0) {
            $segments[] = '<fg=gray>'.str_repeat('░', $remainingWidth).'</>';
        }

        return implode('', $segments);
    }

    private function createGradientSegment(int $width, float $intensity, string $color, string $pattern): string
    {
        if ($width <= 0) {
            return '';
        }

        $segment = '';

        for ($i = 0; $i < $width; ++$i) {
            $position = $i / max(1, $width - 1);

            if ($intensity > 0.8) {
                $char = match (true) {
                    $position < 0.6 => '█',
                    $position < 0.8 => '▓',
                    default => '▒',
                };
            } elseif ($intensity > 0.5) {
                $char = match (true) {
                    $position < 0.4 => '▓',
                    $position < 0.7 => '▒',
                    default => '░',
                };
            } else {
                $char = match (true) {
                    $position < 0.3 => '▒',
                    $position < 0.6 => '░',
                    default => '░',
                };
            }

            $segment .= $char;
        }

        return sprintf('<fg=%s>%s</>', $color, $segment);
    }

    /**
     * Render a detailed table with multiple metrics and visual indicators.
     *
     * @param AnalysisResult[]      $results
     * @param array<string, string> $metrics Array of metric_name => display_label
     */
    public function renderDirectoryDetailedTable(
        array $results,
        array $metrics,
        string $title = 'Directory Analysis',
        int $maxDepth = 2,
    ): void {
        if (empty($results) || empty($metrics)) {
            return;
        }

        $tree = $this->buildDirectoryTree($results, $maxDepth);
        $directoriesData = [];
        $maxValues = [];

        foreach ($tree as $name => $node) {
            $data = ['name' => $name, 'files' => 0];

            foreach ($metrics as $metricName => $label) {
                $total = 0;
                $files = $node['files'];
                $count = 0;

                if (!empty($node['children'])) {
                    $files = array_merge($files, $this->getFilesRecursive($node['children']));
                }

                foreach ($files as $file) {
                    $value = (float) ($file->getMetric($metricName) ?? 0);
                    $total += $value;
                    if ($value > 0) {
                        ++$count;
                    }
                }

                $data[$metricName] = match ($metricName) {
                    'lines', 'chars', 'nodes' => $total,
                    default => count($files) > 0 ? $total / count($files) : 0,
                };

                $maxValues[$metricName] = max($maxValues[$metricName] ?? 0, $data[$metricName]);
            }

            $data['files'] = count($files);
            $directoriesData[] = $data;
        }

        usort($directoriesData, fn ($a, $b) => strcmp($a['name'], $b['name']));

        $this->output->writeln('');

        $this->renderDetailedTableHeader($metrics);
        $this->renderDetailedTableRows($directoriesData, $metrics, $maxValues);
    }

    /**
     * @param array<string, string> $metrics
     */
    private function renderDetailedTableHeader(array $metrics): void
    {
        $header = '    Directory         ';

        foreach ($metrics as $metricName => $label) {
            $width = match ($label) {
                'Lines' => 8,
                'File' => 20,
                'Size' => 8,
                default => 8,
            };
            $header .= sprintf('    %'.$width.'s', $label);
        }

        $this->output->writeln('<fg=#aaa;options=bold>'.$header.'</>');

        $separator = '    '.str_repeat('─', 18);
        foreach ($metrics as $metricName => $label) {
            $width = match ($label) {
                'Lines' => 8,
                'File' => 20,
                'Size' => 8,
                default => 8,
            };
            $separator .= '    '.str_repeat('─', $width);
        }
        $this->output->writeln('<fg=#666>'.$separator.'</>');
    }

    /**
     * @param array<int, array<string, mixed>> $directoriesData
     * @param array<string, string>            $metrics
     * @param array<string, float>             $maxValues
     */
    private function renderDetailedTableRows(array $directoriesData, array $metrics, array $maxValues): void
    {
        foreach ($directoriesData as $data) {
            $name = $data['name'];
            $totalLines = $data['lines'] ?? 0;
            $isHighRisk = $totalLines > 1000;

            if ($isHighRisk) {
                $dirName = '    '.sprintf('<fg=white;options=bold>%-18s</>', $name.'/');
            } else {
                $dirName = '    '.sprintf('<fg=white>%-18s</>', $name.'/');
            }
            $line = $dirName;

            foreach ($metrics as $metricName => $label) {
                $value = $data[$metricName] ?? 0;
                $maxValue = $maxValues[$metricName] ?? 1;

                $width = match ($label) {
                    'Lines' => 8,
                    'File' => 20,
                    'Size' => 8,
                    default => 8,
                };

                $formatted = match ($label) {
                    'Lines' => $this->formatLinesWithRisk((int) $value, $isHighRisk),
                    'File' => $this->createThinVisualization($value, $maxValue, 20),
                    'Size' => $this->formatSizeWithRiskRightAlign($value, $isHighRisk, $width),
                    default => sprintf('<fg=white>%6.1f</>', $value),
                };

                $line .= '    '.$formatted;
            }

            $this->output->writeln($line);
        }
    }

    private function createThinVisualization(float $value, float $maxValue, int $width): string
    {
        $intensity = $maxValue > 0 ? $value / $maxValue : 0;

        $result = '';
        for ($i = 0; $i < $width; ++$i) {
            $pos = $i / max(1, $width - 1);

            if ($pos <= $intensity) {
                if ($intensity >= 0.8) {
                    $result .= '<fg=red;options=bold>o</>';
                } elseif ($intensity >= 0.6) {
                    $result .= '<fg=yellow;options=bold>o</>';
                } elseif ($intensity >= 0.4) {
                    $result .= '<fg=green>o</>';
                } else {
                    $result .= '<fg=cyan>o</>';
                }
            } else {
                $result .= '<fg=gray>·</>';
            }
        }

        return $result;
    }

    private function formatLinesWithRisk(int $lines, bool $isHighRisk): string
    {
        if ($isHighRisk) {
            return sprintf('<fg=red;options=bold>%6d</>', $lines);
        } elseif ($lines > 500) {
            return sprintf('<fg=yellow;options=bold>%6d</>', $lines);
        } else {
            return sprintf('<fg=white>%6d</>', $lines);
        }
    }

    private function formatSizeWithRiskRightAlign(float $value, bool $isHighRisk, int $width): string
    {
        $formatted = '';
        if ($isHighRisk) {
            $formatted = sprintf('<fg=red;options=bold>%.0f</>', $value);
        } elseif ($value > 5) {
            $formatted = sprintf('<fg=yellow;options=bold>%.0f</>', $value);
        } else {
            $formatted = sprintf('<fg=white>%.0f</>', $value);
        }

        $cleanFormatted = preg_replace('/\033\[[0-9;]*m/', '', $formatted);
        $actualWidth = mb_strlen($cleanFormatted);
        $padding = max(0, $width - $actualWidth);

        return str_repeat(' ', $padding).$formatted;
    }

    /**
     * Create architecture cell with 8-char width, background 5% opacity simulation, and shade/saturation variations.
     */
    private function createArchitectureCell(float $value, float $maxValue): string
    {
        if (0 == $value) {
            return '<fg=gray>        </>';
        }

        $intensity = $maxValue > 0 ? min(1.0, $value / $maxValue) : 0;

        $valueStr = $value < 100 ? sprintf('%.0f', $value) : sprintf('%.0f', min(999, $value));

        if ($intensity >= 0.8) {
            $color = 'red';
            $options = ';options=bold';
            $bgChar = '█';
        } elseif ($intensity >= 0.6) {
            $color = 'yellow';
            $options = ';options=bold';
            $bgChar = '▓';
        } elseif ($intensity >= 0.4) {
            $color = 'blue';
            $options = '';
            $bgChar = '▒';
        } elseif ($intensity >= 0.2) {
            $color = 'cyan';
            $options = '';
            $bgChar = '░';
        } else {
            $color = 'gray';
            $options = '';
            $bgChar = '·';
        }

        $valueWidth = strlen($valueStr);

        $spacedValue = ' '.$valueStr.' ';
        $spacedWidth = strlen($spacedValue);

        if ($spacedWidth <= 8) {
            $bgWidth = 8 - $spacedWidth;
            $cell = str_repeat($bgChar, $bgWidth).$spacedValue;
        } else {
            $cell = str_pad(' '.$valueStr, 8);
        }

        return sprintf('<fg=%s%s>%s</>', $color, $options, $cell);
    }

    /**
     * Render directory tree in clean lines format following user's example:
     * L Components  87 ( 36.8%) ■■■■■■■■■■■■■■■■■■■■■■■■■■■■──
     *
     * @param AnalysisResult[] $results
     */
    public function renderDirectoryCleanLines(
        array $results,
        string $metricName,
        string $metricLabel,
        int $maxDepth = 2,
    ): void {
        if (empty($results)) {
            return;
        }

        $tree = $this->buildDirectoryTree($results, $maxDepth);
        $directoriesData = [];
        $totalValue = 0;

        foreach ($tree as $name => $node) {
            $files = $node['files'];

            if (!empty($node['children'])) {
                $files = array_merge($files, $this->getFilesRecursive($node['children']));
            }

            $total = 0;
            foreach ($files as $file) {
                $total += (float) ($file->getMetric($metricName) ?? 0);
            }

            $directoriesData[] = [
                'name' => $name,
                'value' => $total,
                'files' => count($files),
            ];
            $totalValue += $total;
        }

        if (empty($directoriesData) || 0 == $totalValue) {
            return;
        }

        usort($directoriesData, fn ($a, $b) => strcmp($a['name'], $b['name']));

        $this->output->writeln('');
        $this->output->writeln('<fg=cyan;options=bold>Directory Distribution - '.$metricLabel.'</>');
        $this->output->writeln('<fg=gray>'.str_repeat('─', 76).'</>');
        $this->output->writeln('');

        foreach ($directoriesData as $data) {
            $name = $data['name'];
            $value = $data['value'];
            $percentage = $totalValue > 0 ? ($value / $totalValue * 100) : 0;

            $barWidth = 30;
            $filledWidth = (int) round($percentage / 100 * $barWidth);
            $emptyWidth = $barWidth - $filledWidth;
            $bar = str_repeat('■', $filledWidth).str_repeat('─', $emptyWidth);

            $line = sprintf(
                '    <fg=white>L %-12s %3.0f (%5.1f%%) <fg=gray>%s</>',
                $name,
                $value,
                $percentage,
                $bar
            );

            $this->output->writeln($line);
        }

        $this->output->writeln('');
    }
}
