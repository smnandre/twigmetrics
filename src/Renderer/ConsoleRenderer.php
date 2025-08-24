<?php

declare(strict_types=1);

namespace TwigMetrics\Renderer;

use Symfony\Component\Console\Output\OutputInterface;
use TwigMetrics\Analyzer\AnalysisResult;
use TwigMetrics\Console\Helper\DualOutputHelper;
use TwigMetrics\Renderer\Component\DirectoryTreeRenderer;
use TwigMetrics\Renderer\Helper\ConsoleOutputHelper;
use TwigMetrics\Report\Report;
use TwigMetrics\Report\Section\ReportSection;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
final class ConsoleRenderer implements RendererInterface
{
    private ConsoleOutputHelper $outputHelper;
    private DirectoryTreeRenderer $directoryTreeRenderer;
    private bool $headerPrinted = false;

    /**
     * @var array<AnalysisResult>
     */
    private array $globalResults = [];
    private int $dimensionBlockIndex = 0;
    private int $maxDepth = 3;
    private ?Report $currentReport = null;

    public function __construct(
        private OutputInterface $output,
        int $maxDepth = 3,
    ) {
        $this->maxDepth = $maxDepth;
        $this->outputHelper = new ConsoleOutputHelper($output);
        $this->directoryTreeRenderer = new DirectoryTreeRenderer($output);
    }

    public function setHeaderPrinted(bool $printed): void
    {
        $this->headerPrinted = $printed;
    }

    /**
     * @param AnalysisResult[] $results
     */
    public function setGlobalResults(array $results): void
    {
        $this->globalResults = $results;
    }

    private function barWithPartials(float $value, float $max, int $width): string
    {
        $max = $max > 0 ? $max : 1;
        $ratio = max(0.0, min(1.0, $value / $max));
        $full = (int) floor($ratio * $width);
        $remRatio = ($ratio * $width) - $full;
        $partials = ['', '▏', '▎', '▍', '▌', '▋', '▊', '▉'];
        $partialIndex = (int) floor($remRatio * (count($partials) - 1));
        $bar = str_repeat('█', $full);
        if ($partialIndex > 0) {
            $bar .= $partials[$partialIndex];
        }

        return $bar;
    }

    /**
     * @param array<string, array<int, array<string, int|string>>> $overview
     */
    private function renderCallableTops(array $overview): void
    {
        $functions = $overview['functions'] ?? [];
        $filters = $overview['filters'] ?? [];
        $variables = $overview['variables'] ?? [];
        $tests = $overview['tests'] ?? [];
        $macros = $overview['macros'] ?? [];
        $blocks = $overview['blocks'] ?? [];

        $this->output->writeln('');

        $filters = array_values(array_filter($filters, fn ($row) => 'escape' !== (string) ($row['name'] ?? '')));

        $dual1 = new DualOutputHelper($this->output, spacing: 4, totalWidth: 72);
        $l1 = $dual1->getLeftCol();
        $r1 = $dual1->getRightCol();

        $l1->writeln('    <comment>Functions</comment>');
        $r1->writeln('    <comment>Filters</comment>');
        $topFn = array_slice($functions, 0, 10);
        $topFl = array_slice($filters, 0, 10);
        $maxFn = max(1, ...array_map(fn ($r) => (int) ($r['count'] ?? 0), $topFn) ?: [1]);
        $maxFl = max(1, ...array_map(fn ($r) => (int) ($r['count'] ?? 0), $topFl) ?: [1]);
        foreach ($topFn as $row) {
            $name = mb_strimwidth((string) ($row['name'] ?? ''), 0, 16, '…');
            $cnt = (int) ($row['count'] ?? 0);
            $l1->writeln(sprintf('     %-16s %3d %s', $name, $cnt, $this->barWithPartials($cnt, $maxFn, 8)));
        }
        foreach ($topFl as $row) {
            $name = mb_strimwidth((string) ($row['name'] ?? ''), 0, 16, '…');
            $cnt = (int) ($row['count'] ?? 0);
            $r1->writeln(sprintf('     %-16s %3d %s', $name, $cnt, $this->barWithPartials($cnt, $maxFl, 8)));
        }
        $dual1->render();

        $this->output->writeln('');

        if (!empty($macros) || !empty($blocks)) {
            $dual2 = new DualOutputHelper($this->output, spacing: 4, totalWidth: 72);
            $l2 = $dual2->getLeftCol();
            $r2 = $dual2->getRightCol();

            $l2->writeln('    <comment>Macros</comment>');
            $r2->writeln('    <comment>Blocks</comment>');
            $topMacro = array_slice($macros, 0, 6);
            $topBlock = array_slice($blocks, 0, 6);
            $maxMacro = max(1, ...array_map(fn ($r) => (int) ($r['count'] ?? 0), $topMacro) ?: [1]);
            $maxBlock = max(1, ...array_map(fn ($r) => (int) ($r['count'] ?? 0), $topBlock) ?: [1]);
            foreach ($topMacro as $row) {
                $name = mb_strimwidth((string) ($row['name'] ?? ''), 0, 14, '…');
                $cnt = (int) ($row['count'] ?? 0);
                $l2->writeln(sprintf('     %-14s %3d %s', $name, $cnt, $this->barWithPartials($cnt, $maxMacro, 8)));
            }
            foreach ($topBlock as $row) {
                $name = mb_strimwidth((string) ($row['name'] ?? ''), 0, 14, '…');
                $cnt = (int) ($row['count'] ?? 0);
                $r2->writeln(sprintf('     %-14s %3d %s', $name, $cnt, $this->barWithPartials($cnt, $maxBlock, 8)));
            }
            $dual2->render();
        }

        $this->output->writeln('');

        $dual3 = new DualOutputHelper($this->output, spacing: 4, totalWidth: 72);
        $l3 = $dual3->getLeftCol();
        $r3 = $dual3->getRightCol();

        $l3->writeln('    <comment>Variables</comment>');
        $r3->writeln('    <comment>Tests</comment>');
        $topVar = array_slice($variables, 0, 6);
        $topTest = array_slice($tests, 0, 6);
        $maxVar = max(1, ...array_map(fn ($r) => (int) ($r['count'] ?? 0), $topVar) ?: [1]);
        $maxTest = max(1, ...array_map(fn ($r) => (int) ($r['count'] ?? 0), $topTest) ?: [1]);
        foreach ($topVar as $row) {
            $name = mb_strimwidth((string) ($row['name'] ?? ''), 0, 14, '…');
            $cnt = (int) ($row['count'] ?? 0);
            $l3->writeln(sprintf('     %-14s %3d %s', $name, $cnt, $this->barWithPartials($cnt, $maxVar, 8)));
        }
        foreach ($topTest as $row) {
            $name = mb_strimwidth((string) ($row['name'] ?? ''), 0, 14, '…');
            $cnt = (int) ($row['count'] ?? 0);
            $r3->writeln(sprintf('     %-14s %3d %s', $name, $cnt, $this->barWithPartials($cnt, $maxTest, 8)));
        }
        $dual3->render();
    }

    /**
     * @param AnalysisResult[] $results
     */
    public function renderModernSummary(array $results, float $analysisTime = 0): void
    {
        $templateCount = count($results);
        $totalLines = 0;
        $maxDepth = 0;
        $directories = [];

        foreach ($results as $result) {
            $lines = $result->getMetric('lines') ?? 0;
            $depth = $result->getMetric('max_depth') ?? 0;
            $totalLines += $lines;
            $maxDepth = max($maxDepth, $depth);

            $path = $result->getRelativePath();
            $dir = dirname($path);
            if ('.' !== $dir) {
                $directories[$dir] = ($directories[$dir] ?? 0) + 1;
            }
        }

        $avgLines = $templateCount > 0 ? round($totalLines / $templateCount) : 0;
        $avgLinesPerFile = $templateCount > 0 ? round($totalLines / $templateCount, 1) : 0;
        $avgFilesPerDir = count($directories) > 0 ? round($templateCount / count($directories), 1) : 0;

        $largestTemplate = '';
        $largestLines = 0;
        foreach ($results as $result) {
            $lines = $result->getMetric('lines') ?? 0;
            if ($lines > $largestLines) {
                $largestLines = $lines;
                $largestTemplate = basename($result->getRelativePath());
            }
        }

        $leftSummary = [
            'Templates' => (string) $templateCount,
            'Max Depth' => (string) $maxDepth,
            'Avg Lines/Template' => (string) $avgLines,
            'Analysis Time' => sprintf('%.2fs', $analysisTime),
        ];

        $rightSummary = [
            'Directories' => (string) count($directories),
            '',
            '',
            '',
        ];

        $this->outputHelper->writeTwoColumnKeyValues($leftSummary, $rightSummary, 'Summary');

        $leftArch = [
            'Total Lines' => (string) $totalLines,
            'Avg line per file' => (string) $avgLinesPerFile,
            'Largest (lines)' => (string) $largestLines,
        ];

        $rightArch = [
            'Total Directories' => (string) count($directories),
            'Avg file per dir' => (string) $avgFilesPerDir,
            'Largest (files)' => $largestTemplate,
        ];

        $this->outputHelper->writeTwoColumnKeyValues($leftArch, $rightArch, 'File Architecture');
    }

    private function renderDimensionVisualRecap(ReportSection $section): void
    {
        $data = $section->getData();
        $dimensions = $data['dimensions'] ?? [];

        if (empty($dimensions)) {
            return;
        }

        $this->renderImprovedDimensionCards($dimensions);
    }

    public function render(Report $report): string
    {
        $this->currentReport = $report;
        if (!$this->headerPrinted) {
            $this->outputHelper->writeHeader();
        }

        $sections = $report->getSections();

        $deferredVisualRecap = null;

        for ($i = 0; $i < count($sections); ++$i) {
            $currentSection = $sections[$i];

            if ('dimension_visual_recap' === $currentSection->getType()) {
                $deferredVisualRecap = $currentSection;
                break;
            }
        }

        $this->renderDimensionalAnalysisBlocks();

        if ($deferredVisualRecap instanceof ReportSection) {
            $this->outputHelper->writeSectionTitle('Dimension');
            $this->renderDimensionVisualRecap($deferredVisualRecap);
        }

        return '';
    }

    private function getTextLengthWithoutAnsi(string $text): int
    {
        $cleanText = preg_replace('/\e\[[0-9;]*m/', '', $text) ?? '';
        $cleanText = preg_replace('/<[^>]+>/', '', $cleanText) ?? '';

        return mb_strlen($cleanText);
    }

    /**
     * @param array<string, mixed> $dimensionScores
     */
    public function renderDimensionRecap(array $dimensionScores): void
    {
        $this->outputHelper->writeSeparatorLine();
        $this->outputHelper->writeSection('Twig Metrics');

        $dimensionGroups = array_chunk($dimensionScores, 2, true);

        foreach ($dimensionGroups as $group) {
            $this->renderDimensionRow($group);
            $this->outputHelper->writeEmptyLine();
        }

        $this->outputHelper->writeEmptyLine();
    }

    /**
     * @param array<string, mixed> $dimensionScores
     */
    public function renderImprovedDimensionCards(array $dimensionScores): void
    {
        $dimensionGroups = array_chunk($dimensionScores, 3, true);
        foreach ($dimensionGroups as $i => $group) {
            $showMini = 0 !== $i;
            $this->renderImprovedDimensionRow($group, $showMini);
            if (0 === $i && count($dimensionGroups) > 1) {
                $this->output->writeln('');
            }
        }
    }

    /**
     * @param array<string, mixed> $dimensions
     */
    private function renderImprovedDimensionRow(array $dimensions, bool $showMini = true): void
    {
        $cardWidth = 24;
        $spacing = 2;

        $topLine = '  ';
        foreach ($dimensions as $name => $data) {
            $titleTruncated = strlen($name) > 15 ? substr($name, 0, 12).'...' : $name;
            $titlePadding = $cardWidth - strlen($titleTruncated) - 5;
            $topLine .= sprintf('<fg=#ddd>╭─</> %s <fg=#ddd>%s╮</>', $titleTruncated, str_repeat('─', max(0, $titlePadding)));
            if ($name !== array_key_last($dimensions)) {
                $topLine .= str_repeat(' ', $spacing);
            }
        }
        $this->output->writeln($this->centerFaceLine($topLine));

        $contentLine = '  ';
        foreach ($dimensions as $name => $data) {
            $score = $data['score'] ?? 0;
            $grade = $this->scoreToGrade($score);
            $dotsIndicator = $this->getDotsIndicator($score);
            $coloredGrade = $this->getColoredGradeWithScore($grade, $score);

            $content = sprintf('<fg=#ddd>│</>  %s      %s <fg=#ddd>│</>', $dotsIndicator, $coloredGrade);
            $contentLine .= $content;

            if ($name !== array_key_last($dimensions)) {
                $contentLine .= str_repeat(' ', $spacing);
            }
        }
        $this->output->writeln($this->centerFaceLine($contentLine));

        $hasMini = false;
        if ($showMini) {
            foreach ($dimensions as $data) {
                if (!empty($data['mini'])) {
                    $hasMini = true;
                    break;
                }
            }
        }
        if ($hasMini) {
            $miniLine = '  ';
            foreach ($dimensions as $name => $data) {
                $miniText = (string) ($data['mini'] ?? '');
                $innerWidth = $cardWidth - 2;

                $miniText = ' '.mb_strimwidth(strip_tags($miniText), 0, $innerWidth - 1, '…');
                $miniText = str_pad($miniText, $innerWidth, ' ');
                $miniLine .= '│'.$miniText.'│';
                if ($name !== array_key_last($dimensions)) {
                    $miniLine .= str_repeat(' ', $spacing);
                }
            }
            $this->output->writeln('<fg=#ddd>'.$this->centerFaceLine($miniLine).'</>');
        }

        $bottomLine = '  ';
        foreach ($dimensions as $name => $data) {
            $bottomLine .= '╰'.str_repeat('─', $cardWidth - 2).'╯';
            if ($name !== array_key_last($dimensions)) {
                $bottomLine .= str_repeat(' ', $spacing);
            }
        }
        $this->output->writeln('<fg=#ddd>'.$this->centerFaceLine($bottomLine).'</>');
    }

    private function centerFaceLine(string $line): string
    {
        $faceWidth = 76;
        $visibleLen = $this->getTextLengthWithoutAnsi($line) - 2;
        $visibleLen = max(0, $visibleLen);
        $left = (int) floor(max(0, ($faceWidth - $visibleLen) / 2));
        $right = max(0, $faceWidth - $visibleLen - $left);
        $content = substr($line, 2);

        return '  '.str_repeat(' ', $left).$content;
    }

    private function getDotsIndicator(int $score): string
    {
        $filledDots = min(6, max(0, (int) round($score / 100 * 6)));
        $emptyDots = 6 - $filledDots;

        $color = $this->getScoreColor($score);

        $filled = str_repeat("<fg={$color}>●</fg={$color}> ", $filledDots);
        $empty = str_repeat('<fg=gray>○</fg=gray> ', $emptyDots);

        return trim($filled.$empty);
    }

    private function getColoredGrade(string $grade): string
    {
        $color = match ($grade) {
            'A' => 'green',
            'B' => 'cyan',
            'C' => 'yellow',
            'D' => 'magenta',
            'E' => 'red',
            default => 'white',
        };

        return "<fg={$color};options=bold>{$grade}</fg={$color};options=bold>";
    }

    private function getColoredGradeWithScore(string $grade, int $score): string
    {
        $color = $this->getScoreColor($score);

        $displayGrade = $score >= 95 ? 'A+' : ($grade.' ');

        return "<fg={$color};options=bold>{$displayGrade}</fg={$color};options=bold>";
    }

    private function getScoreColor(int $score): string
    {
        return match (true) {
            $score >= 90 => 'green',
            $score >= 80 => 'cyan',
            $score >= 70 => 'yellow',
            $score >= 60 => 'magenta',
            $score >= 50 => 'red',
            default => 'red',
        };
    }

    /**
     * @param array<string, mixed> $dimensions
     */
    private function renderDimensionRow(array $dimensions): void
    {
        $boxWidth = 38;
        $spacing = 4;

        $topLine = '';
        foreach ($dimensions as $name => $data) {
            $topLine .= '╭'.str_repeat('─', $boxWidth - 2).'╮';
            if (count($dimensions) > 1 && $name !== array_key_last($dimensions)) {
                $topLine .= str_repeat(' ', $spacing);
            }
        }
        $this->output->writeln('  '.$topLine);

        $contentLine = '';
        foreach ($dimensions as $name => $data) {
            $score = $data['score'] ?? 0;
            $grade = $this->scoreToGrade($score);
            $ledIndicator = $this->getLedIndicator($grade);

            $maxNameLength = $boxWidth - 12;
            $displayName = strlen($name) > $maxNameLength ? substr($name, 0, $maxNameLength - 3).'...' : $name;

            $content = sprintf('│  %-*s %s %s │', $maxNameLength, $displayName, $ledIndicator, $grade);
            $contentLine .= $content;

            if (count($dimensions) > 1 && $name !== array_key_last($dimensions)) {
                $contentLine .= str_repeat(' ', $spacing);
            }
        }
        $this->output->writeln('  '.$contentLine);

        $bottomLine = '';
        foreach ($dimensions as $name => $data) {
            $bottomLine .= '╰'.str_repeat('─', $boxWidth - 2).'╯';
            if (count($dimensions) > 1 && $name !== array_key_last($dimensions)) {
                $bottomLine .= str_repeat(' ', $spacing);
            }
        }
        $this->output->writeln('  '.$bottomLine);
    }

    private function scoreToGrade(int $score): string
    {
        return match (true) {
            $score >= 90 => 'A',
            $score >= 80 => 'B',
            $score >= 70 => 'C',
            $score >= 60 => 'D',
            default => 'E',
        };
    }

    private function getLedIndicator(string $grade): string
    {
        return match ($grade) {
            'A' => '<fg=green>●●●●</fg=green>',
            'B' => '<fg=yellow>●●●</fg=yellow><fg=gray>○</fg=gray>',
            'C' => '<fg=yellow>●●</fg=yellow><fg=gray>○○</fg=gray>',
            'D' => '<fg=red>●</fg=red><fg=gray>○○○</fg=gray>',
            'E' => '<fg=gray>○○○○</fg=gray>',
            default => '<fg=gray>○○○○</fg=gray>',
        };
    }

    /**
     * Renders the 6 dimensional analysis blocks.
     */
    public function renderDimensionalAnalysisBlocks(): void
    {
        $this->output->writeln('');
        $this->output->writeln('');

        $this->renderTemplatesDimensionBlock();
        $this->renderDirectoryTreeForDimension('Template Files');
        $this->output->writeln('');

        $this->renderReadabilityDimensionBlock();
        $this->renderDirectoryTreeForDimension('Code Style');

        $this->renderDirectoryTreeForDimension('Code Quality');
        $this->output->writeln('');

        $this->renderCallablesDimensionBlock();
        $this->renderDirectoryTreeForDimension('Twig Callables');
        $this->output->writeln('');

        $this->renderArchitectureDimensionBlock();
        $this->renderDirectoryTreeForDimension('Architecture');
        $this->output->writeln('');

        $this->renderComplexityDimensionBlock();
        $this->output->writeln('');

        $this->renderMaintainabilityDimensionBlock();
        $this->output->writeln('');
    }

    private function renderTemplatesDimensionBlock(): void
    {
        [
            'Templates' => $templates,
            'Directories' => $dirs,
            'Total Lines' => $totalLines,
            'Avg Lines/Template' => $avgLines,
            'Characters' => $chars,
            'Chars/Template' => $charsPerTpl,
            'Dir Depth Avg' => $dirDepthAvg,
            'File Size CV %' => $fileSizeCv,
        ] = $this->computeTemplatesStats();

        $this->dimensionBlock('Template Files', [
            'Templates' => number_format($templates),
            'Directories' => number_format($dirs),
            'Total Lines' => number_format($totalLines),
            'Avg Lines/Template' => number_format($avgLines, 1),
            'Characters' => $this->formatHuman($chars),
            'Chars/Template' => number_format((float) $charsPerTpl, 0),
            'Dir Depth Avg' => number_format((float) $dirDepthAvg, 1),
            'File Size CV %' => number_format((float) $fileSizeCv, 1).'%',
        ]);
    }

    private function renderReadabilityDimensionBlock(): void
    {
        [
            'avgLineLen' => $avgLineLen,
            'commentRatio' => $commentRatio,
            'trailingPerLine' => $trailingPerLine,
            'emptyPct' => $emptyPct,
            'formatScore' => $formatScore,
            'commentsPerTpl' => $commentsPerTpl,
            'indentConsistency' => $indentConsistency,
            'namingScore' => $namingScore,
        ] = $this->computeReadabilityStats();

        $this->dimensionBlock('Code Style', [
            'Avg Line Length' => number_format($avgLineLen, 1),
            'Comments/Template' => number_format($commentsPerTpl, 1),
            'Comment Ratio' => number_format($commentRatio, 1).'%',
            'Trail Spaces/Line' => number_format($trailingPerLine, 2),
            'Empty Lines %' => number_format($emptyPct, 1).'%',
            'Formatting Score' => number_format($formatScore, 1),
            'Indent Consistency %' => number_format((float) $indentConsistency, 1).'%',
            'Naming Conv. Score %' => number_format((float) $namingScore, 1).'%',
        ]);
    }

    private function renderComplexityDimensionBlock(): void
    {
        [
            'avgCx' => $avgCx,
            'maxCx' => $maxCx,
            'avgDepth' => $avgDepth,
            'maxDepth' => $maxDepth,
            'ifsPerTpl' => $ifsPerTpl,
            'forsPerTpl' => $forsPerTpl,
            'nestedControlDepth' => $nestedControlDepth,
        ] = $this->computeComplexityStats();

        $this->dimensionBlock('Logical Complexity', [
            'Avg Complexity' => number_format($avgCx, 1),
            'Max Complexity' => (string) $maxCx,
            'Avg Depth' => number_format($avgDepth, 1),
            'Max Depth' => (string) $maxDepth,
            'IFs/Template' => number_format($ifsPerTpl, 1),
            'FORs/Template' => number_format($forsPerTpl, 1),
            'Nested Control Depth' => (string) $nestedControlDepth,
        ]);

        $this->renderComplexityTop5List(5);
    }

    private function renderArchitectureDimensionBlock(): void
    {
        [
            'avgExt' => $avgExt,
            'avgInc' => $avgInc,
            'avgEmb' => $avgEmb,
            'avgImp' => $avgImp,
            'inheritDepth' => $inheritDepth,
            'standalone' => $standalone,
        ] = $this->computeArchitectureStats();

        $this->dimensionBlock('Architecture', [
            'Extends/Template' => number_format($avgExt, 2),
            'Includes/Template' => number_format($avgInc, 2),
            'Embeds/Template' => number_format($avgEmb, 2),
            'Imports/Template' => number_format($avgImp, 2),
            'Avg Inherit Depth' => number_format($inheritDepth, 1),
            'Standalone Files' => (string) $standalone,
        ]);
    }

    private function renderCallablesDimensionBlock(): void
    {
        [
            'funcsPerTpl' => $funcsPerTpl,
            'filtersPerTpl' => $filtersPerTpl,
            'varsPerTpl' => $varsPerTpl,
            'uniqueFns' => $uniqueFns,
            'uniqueFilters' => $uniqueFilters,
            'macrosDefined' => $macrosDefined,
        ] = $this->computeCallablesStats();

        $this->dimensionBlock('Twig Callables', [
            'Funcs/Template' => number_format($funcsPerTpl, 1),
            'Filters/Template' => number_format($filtersPerTpl, 1),
            'Vars/Template' => number_format($varsPerTpl, 1),
            'Unique Funcs' => (string) $uniqueFns,
            'Unique Filters' => (string) $uniqueFilters,
            'Macros Defined' => (string) $macrosDefined,
        ]);
    }

    private function renderMaintainabilityDimensionBlock(): void
    {
        [
            'largeTemplates' => $largeTemplates,
            'highCx' => $highCx,
            'deepNesting' => $deepNesting,
            'emptyTemplates' => $emptyTemplates,
            'standalone' => $standalone,
        ] = $this->computeMaintainabilityStats();

        $this->dimensionBlock('Maintainability', [
            'Large Templates (>200L)' => (string) $largeTemplates,
            'High Complexity (>20)' => (string) $highCx,
            'Deep Nesting (>5)' => (string) $deepNesting,
            'Empty Templates' => (string) $emptyTemplates,
            'Standalone' => (string) $standalone,
            'Risk Score' => $this->scoreToGrade((int) round($this->calculateOverallScoreEstimate())),
        ]);

        $this->renderMaintainabilityTopWorstLists(5);
    }

    private function renderComplexityTop5List(int $limit = 5): void
    {
        if (empty($this->globalResults)) {
            return;
        }
        $complex = [];
        $depths = [];
        foreach ($this->globalResults as $res) {
            $name = basename($res->getRelativePath());
            $complex[] = ['name' => $name, 'val' => (int) ($res->getMetric('complexity_score') ?? 0)];
            $depths[] = ['name' => $name, 'val' => (int) ($res->getMetric('max_depth') ?? 0)];
        }
        usort($complex, fn ($a, $b) => $b['val'] <=> $a['val']);
        usort($depths, fn ($a, $b) => $b['val'] <=> $a['val']);
        $complex = array_slice($complex, 0, $limit);
        $depths = array_slice($depths, 0, $limit);
        $maxDepthVal = 1;
        foreach ($depths as $d) {
            $maxDepthVal = max($maxDepthVal, (int) $d['val']);
        }

        $this->output->writeln('');
        $dual = new DualOutputHelper($this->output, spacing: 4, totalWidth: 78);
        $l = $dual->getLeftCol();
        $r = $dual->getRightCol();
        $l->writeln('  <comment>Top Complexity</comment>');
        $r->writeln('  <comment>Top Depth</comment>');

        foreach ($complex as $row) {
            $l->writeln(sprintf('   %-24s %3d', mb_strimwidth($row['name'], 0, 24, '…'), $row['val']));
        }
        foreach ($depths as $row) {
            $bar = $this->barWithPartials((int) $row['val'], (int) $maxDepthVal, 10);
            $r->writeln(sprintf('   %-24s %3d %s', mb_strimwidth($row['name'], 0, 24, '…'), $row['val'], $bar));
        }
        $dual->render();
    }

    private function renderMaintainabilityTopWorstLists(int $limit = 5): void
    {
        if (empty($this->globalResults)) {
            return;
        }
        $rows = [];
        foreach ($this->globalResults as $res) {
            $lines = (int) ($res->getMetric('lines') ?? 0);
            $cx = (int) ($res->getMetric('complexity_score') ?? 0);
            $depth = (int) ($res->getMetric('max_depth') ?? 0);
            $score = max(0.0, 100.0 - (
                min(1.0, $cx / 20.0) * 40.0 +
                min(1.0, $depth / 5.0) * 25.0 +
                min(1.0, $lines / 200.0) * 20.0
            ));
            $grade = $this->getColoredGrade($this->scoreToGrade((int) round($score)));
            $rows[] = [
                'name' => basename($res->getRelativePath()),
                'score' => (int) round($score),
                'grade' => $grade,
            ];
        }
        usort($rows, fn ($a, $b) => $b['score'] <=> $a['score']);
        $best = array_slice($rows, 0, $limit);
        $worst = array_slice(array_reverse($rows), 0, $limit);

        $this->output->writeln('');
        $dual = new DualOutputHelper($this->output, spacing: 4, totalWidth: 78);
        $left = $dual->getLeftCol();
        $right = $dual->getRightCol();
        $left->writeln('  <comment>Top 5 Maintainability</comment>');
        $right->writeln('  <comment>Worst 5 Maintainability</comment>');

        foreach ($best as $r) {
            $left->writeln(sprintf('   %-24s %3d %s', mb_strimwidth($r['name'], 0, 24, '…'), $r['score'], $r['grade']));
        }
        foreach ($worst as $r) {
            $right->writeln(sprintf('   %-24s %3d %s', mb_strimwidth($r['name'], 0, 24, '…'), $r['score'], $r['grade']));
        }
        $dual->render();
    }

    /**
     * @param array<string, string> $values
     */
    /**
     * @param array<string, string> $values
     */
    private function dimensionBlock(string $title, array $values, int $colWidth = 32): void
    {
        $borderColor = '#ddd';
        $dotColor = 'gray';
        ++$this->dimensionBlockIndex;
        $titleColor = (1 === $this->dimensionBlockIndex % 2) ? '#f1c40f' : '#2ecc71';

        $boxWidth = 78;
        $insideWidth = $boxWidth - 2;

        $titleSeg = sprintf('─ <fg=%s>%s</> ', $titleColor, $title);
        $titleFill = str_repeat('─', max(0, $insideWidth - $this->getTextLengthWithoutAnsi($titleSeg)));
        $top = sprintf('<fg=%s>╭%s%s╮</>', $borderColor, $titleSeg, $titleFill);
        $bottom = sprintf('<fg=%s>╰%s╯</>', $borderColor, str_repeat('─', $insideWidth));
        $empty = sprintf('<fg=%s>│%s│</>', $borderColor, str_repeat(' ', $insideWidth));

        $this->output->writeln('  '.$top);
        $this->output->writeln('  '.$empty);

        $rows = array_chunk($values, 2, true);
        foreach ($rows as $pair) {
            $line = '';
            $first = true;
            foreach ($pair as $k => $v) {
                $key = (string) $k;
                $val = (string) $v;
                $visibleKeyLen = mb_strlen($key);
                $visibleValLen = $this->getTextLengthWithoutAnsi($val);
                $dotsCount = max(1, $colWidth - $visibleKeyLen - $visibleValLen - 3);
                $dots = '<fg='.$dotColor.'>'.str_repeat('.', $dotsCount).'</>';
                $column = sprintf('<fg=white>%s</> %s <fg=white>%s</>', $key, $dots, $val);
                if (!$first) {
                    $line .= str_repeat(' ', 7);
                }
                $line .= $column;
                $first = false;
            }

            $visibleLen = $this->getTextLengthWithoutAnsi($line);
            $contentLen = 1 + $visibleLen + 1;
            $padLen = max(0, $insideWidth - $contentLen);
            $pad = str_repeat(' ', $padLen);
            $this->output->writeln('  '.sprintf('<fg=%s>│</> %s%s <fg=%s>│</>', $borderColor, $line, $pad, $borderColor));
        }

        $this->output->writeln('  '.$empty);
        $this->output->writeln('  '.$bottom);
    }

    private function renderDirectoryTreeForDimension(string $dimensionTitle): void
    {
        if (empty($this->globalResults)) {
            return;
        }

        $maxDepth = $this->maxDepth;

        switch ($dimensionTitle) {
            case 'Template Files':
                $this->directoryTreeRenderer->renderDirectoryStackedBars(
                    $this->globalResults,
                    [
                        'lines' => 'Lines',
                        'chars' => 'Characters',
                        'nodes' => 'Nodes',
                    ],
                    'Template Files by Directory',
                    $maxDepth,
                    35
                );
                break;

            case 'Code Style':
                $this->directoryTreeRenderer->renderDirectoryDetailedTable(
                    $this->globalResults,
                    [
                        'lines' => 'Lines',
                        'avg_line_length' => 'File',
                        'chars' => 'Size',
                    ],
                    'Code Style Metrics by Directory',
                    $maxDepth
                );
                break;

            case 'Code Quality':
                $this->directoryTreeRenderer->renderDirectoryDualProgressBars(
                    $this->globalResults,
                    [
                        'avg_line_length' => 'Readability',
                        'comments_count' => 'Documentation',
                    ],
                    'Code Quality by Directory',
                    $maxDepth,
                    100
                );
                break;

            case 'Twig Callables':
                $this->directoryTreeRenderer->renderDirectoryHeatmapTree(
                    $this->globalResults,
                    [
                        'functions' => 'func',
                        'filters' => 'filt',
                        'variables' => 'vars',
                        'macros' => 'macr',
                    ],
                    'Twig Callables Usage by Directory',
                    $maxDepth
                );

                $this->renderCallableTopsForDimension();
                break;

            case 'Architecture':
                $this->directoryTreeRenderer->renderDirectoryMetricsTable(
                    $this->globalResults,
                    [
                        'extends' => 'ext',
                        'includes' => 'inc',
                        'embeds' => 'emb',
                        'blocks' => 'blk',
                    ],
                    $maxDepth
                );
                break;

            case 'Logical Complexity':
                $this->directoryTreeRenderer->renderDirectoryCleanLines(
                    $this->globalResults,
                    'complexity',
                    'Complexity Distribution',
                    $maxDepth
                );
                break;

            case 'Maintainability':
                $this->directoryTreeRenderer->renderDirectoryMetricsTable(
                    $this->globalResults,
                    [
                        'lines' => 'ext',
                        'complexity' => 'inc',
                        'max_depth' => 'emb',
                        'blocks' => 'blk',
                    ],
                    $maxDepth
                );
                break;
        }
    }

    private function renderCallableTopsForDimension(): void
    {
        if (!$this->currentReport) {
            return;
        }

        $sections = $this->currentReport->getSections();
        $overview = [];

        foreach ($sections as $section) {
            if ('callables_dir_chart' === $section->getType()) {
                $data = $section->getData();
                $overview = $data['overview'] ?? [];
                break;
            }
        }

        if (!empty($overview)) {
            $this->renderCallableTops($overview);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function computeTemplatesStats(): array
    {
        $count = count($this->globalResults);
        $totalLines = 0;
        $totalChars = 0;
        $dirs = [];
        $depthSum = 0.0;
        $lineVals = [];
        foreach ($this->globalResults as $res) {
            $l = (int) ($res->getMetric('lines') ?? 0);
            $c = (int) ($res->getMetric('chars') ?? 0);
            $totalLines += $l;
            $totalChars += $c;
            $path = $res->getRelativePath();
            $parts = explode('/', $path, 2);
            $dir = $parts[1] ?? false ? $parts[0] : '(root)';
            $dirs[$dir] = true;
            $depthSum += max(0, substr_count($path, '/'));
            $lineVals[] = $l;
        }
        $avgLines = $count > 0 ? $totalLines / $count : 0.0;
        $charsPerTpl = $count > 0 ? $totalChars / $count : 0.0;
        $dirDepthAvg = $count > 0 ? $depthSum / $count : 0.0;
        $fileSizeCv = 0.0;
        if ($count > 0 && $avgLines > 0) {
            $mean = $avgLines;
            $sumSq = 0.0;
            foreach ($lineVals as $v) {
                $sumSq += ($v - $mean) * ($v - $mean);
            }
            $std = sqrt($sumSq / $count);
            $fileSizeCv = ($std / $mean) * 100.0;
        }

        return [
            'Templates' => $count,
            'Directories' => count($dirs),
            'Total Lines' => $totalLines,
            'Avg Lines/Template' => $avgLines,
            'Characters' => $totalChars,
            'Chars/Template' => $charsPerTpl,
            'Dir Depth Avg' => $dirDepthAvg,
            'File Size CV %' => $fileSizeCv,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function computeReadabilityStats(): array
    {
        $count = max(1, count($this->globalResults));
        $sumAvgLine = 0.0;
        $sumComments = 0;
        $sumLines = 0;
        $sumTrailing = 0;
        $sumBlank = 0;
        $sumFormat = 0.0;
        $sumMixedIndent = 0;
        $sumBlockNaming = 0.0;
        $sumVarNaming = 0.0;
        $resultsCount = 0;
        foreach ($this->globalResults as $res) {
            $sumAvgLine += (float) ($res->getMetric('avg_line_length') ?? 0.0);
            $sumComments += (int) ($res->getMetric('comment_lines') ?? 0);
            $sumLines += (int) ($res->getMetric('lines') ?? 0);
            $sumTrailing += (int) ($res->getMetric('trailing_spaces') ?? 0);
            $sumBlank += (int) ($res->getMetric('blank_lines') ?? 0);
            $sumFormat += (float) ($res->getMetric('formatting_consistency_score') ?? 100.0);
            $sumMixedIndent += (int) ($res->getMetric('mixed_indentation_lines') ?? 0);
            $sumBlockNaming += (float) ($res->getMetric('block_naming_consistency') ?? 100.0);
            $sumVarNaming += (float) ($res->getMetric('variable_naming_consistency') ?? 100.0);
            ++$resultsCount;
        }
        $avgLineLen = $sumAvgLine / $count;
        $commentsPerTpl = $sumComments / $count;
        $commentRatio = $sumLines > 0 ? ($sumComments / $sumLines) * 100 : 0.0;
        $trailingPerLine = $sumLines > 0 ? $sumTrailing / $sumLines : 0.0;
        $emptyPct = $sumLines > 0 ? ($sumBlank / $sumLines) * 100 : 0.0;
        $formatScore = $sumFormat / $count;
        $indentConsistency = $sumLines > 0 ? max(0.0, 100.0 - ($sumMixedIndent / $sumLines) * 100.0) : 100.0;
        $namingScore = $resultsCount > 0 ? (($sumBlockNaming / $resultsCount) + ($sumVarNaming / $resultsCount)) / 2.0 : 100.0;

        return compact('avgLineLen', 'commentRatio', 'trailingPerLine', 'emptyPct', 'formatScore', 'commentsPerTpl', 'indentConsistency', 'namingScore');
    }

    /**
     * @return array<string, mixed>
     */
    private function computeComplexityStats(): array
    {
        $count = max(1, count($this->globalResults));
        $sumCx = 0.0;
        $maxCx = 0;
        $sumDepth = 0.0;
        $maxDepth = 0;
        $sumIfs = 0.0;
        $sumFors = 0.0;
        foreach ($this->globalResults as $res) {
            $cx = (int) ($res->getMetric('complexity_score') ?? 0);
            $sumCx += $cx;
            $maxCx = max($maxCx, $cx);
            $d = (int) ($res->getMetric('max_depth') ?? 0);
            $sumDepth += $d;
            $maxDepth = max($maxDepth, $d);
            $sumIfs += (int) ($res->getMetric('ifs') ?? 0);
            $sumFors += (int) ($res->getMetric('fors') ?? 0);
        }
        $avgCx = $sumCx / $count;
        $avgDepth = $sumDepth / $count;
        $ifsPerTpl = $sumIfs / $count;
        $forsPerTpl = $sumFors / $count;
        $nestedControlDepth = $maxDepth;

        return compact('avgCx', 'maxCx', 'avgDepth', 'maxDepth', 'ifsPerTpl', 'forsPerTpl', 'nestedControlDepth');
    }

    /**
     * @return array<string, mixed>
     */
    private function computeArchitectureStats(): array
    {
        $count = max(1, count($this->globalResults));
        $sumExt = 0;
        $sumInc = 0;
        $sumEmb = 0;
        $sumImp = 0;
        $sumInherit = 0;
        $standalone = 0;
        foreach ($this->globalResults as $res) {
            $deps = $res->getMetric('dependency_types') ?? [];
            if (!is_array($deps)) {
                $deps = [];
            }
            $ext = (int) ($deps['extends'] ?? 0);
            $inc = (int) (($deps['includes'] ?? 0) + ($deps['includes_function'] ?? 0));
            $emb = (int) ($deps['embeds'] ?? 0);
            $imp = (int) ($deps['imports'] ?? 0);
            $sumExt += $ext;
            $sumInc += $inc;
            $sumEmb += $emb;
            $sumImp += $imp;
            $sumInherit += (int) ($res->getMetric('inheritance_depth') ?? 0);
            if (($ext + $inc + $emb + $imp) === 0) {
                ++$standalone;
            }
        }
        $avgExt = $sumExt / $count;
        $avgInc = $sumInc / $count;
        $avgEmb = $sumEmb / $count;
        $avgImp = $sumImp / $count;
        $inheritDepth = $sumInherit / $count;

        return compact('avgExt', 'avgInc', 'avgEmb', 'avgImp', 'inheritDepth', 'standalone');
    }

    /**
     * @return array<string, mixed>
     */
    private function computeCallablesStats(): array
    {
        $count = max(1, count($this->globalResults));
        $sumFn = 0;
        $sumFl = 0;
        $sumVar = 0;
        $macrosDefined = 0;
        $uniqueFns = [];
        $uniqueFilters = [];
        foreach ($this->globalResults as $res) {
            $fns = $res->getMetric('functions_detail') ?? [];
            if (is_array($fns)) {
                $sumFn += array_sum($fns);
                foreach ($fns as $name => $_) {
                    $uniqueFns[$name] = true;
                }
            }
            $fls = $res->getMetric('filters_detail') ?? [];
            if (is_array($fls)) {
                $sumFl += array_sum($fls);
                foreach ($fls as $name => $_) {
                    $uniqueFilters[$name] = true;
                }
            }
            $vars = $res->getMetric('variables_detail') ?? [];
            if (is_array($vars)) {
                $sumVar += array_sum($vars);
            }
            $macDefs = $res->getMetric('macro_definitions_detail') ?? [];
            if (is_array($macDefs)) {
                $macrosDefined += count($macDefs);
            }
        }
        $funcsPerTpl = $sumFn / $count;
        $filtersPerTpl = $sumFl / $count;
        $varsPerTpl = $sumVar / $count;

        return [
            'funcsPerTpl' => $funcsPerTpl,
            'filtersPerTpl' => $filtersPerTpl,
            'varsPerTpl' => $varsPerTpl,
            'uniqueFns' => count($uniqueFns),
            'uniqueFilters' => count($uniqueFilters),
            'macrosDefined' => $macrosDefined,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function computeMaintainabilityStats(): array
    {
        $largeTemplates = 0;
        $highCx = 0;
        $deepNesting = 0;
        $emptyTemplates = 0;
        $standalone = 0;
        foreach ($this->globalResults as $res) {
            $lines = (int) ($res->getMetric('lines') ?? 0);
            $cx = (int) ($res->getMetric('complexity_score') ?? 0);
            $depth = (int) ($res->getMetric('max_depth') ?? 0);
            $deps = $res->getMetric('dependency_types') ?? [];
            if (!is_array($deps)) {
                $deps = [];
            }
            $depSum = (int) ($deps['extends'] ?? 0) + (int) ($deps['includes'] ?? 0) + (int) ($deps['includes_function'] ?? 0) + (int) ($deps['embeds'] ?? 0) + (int) ($deps['imports'] ?? 0);
            if ($lines > 200) {
                ++$largeTemplates;
            }
            if ($cx > 20) {
                ++$highCx;
            }
            if ($depth > 5) {
                ++$deepNesting;
            }
            if (0 === $lines) {
                ++$emptyTemplates;
            }
            if (0 === $depSum) {
                ++$standalone;
            }
        }

        return compact('largeTemplates', 'highCx', 'deepNesting', 'emptyTemplates', 'standalone');
    }

    private function formatHuman(int|float $n): string
    {
        $n = (float) $n;
        if ($n >= 1_000_000) {
            return number_format($n / 1_000_000, 1).'M';
        }
        if ($n >= 1_000) {
            return number_format($n / 1_000, 1).'k';
        }

        return (string) ((int) $n);
    }

    private function calculateOverallScoreEstimate(): float
    {
        $total = max(1, count($this->globalResults));
        $m = $this->computeMaintainabilityStats();
        $penalty = 0.0;
        $penalty += ($m['highCx'] / $total) * 40.0;
        $penalty += ($m['deepNesting'] / $total) * 25.0;
        $penalty += ($m['largeTemplates'] / $total) * 20.0;
        $score = max(0.0, 100.0 - $penalty);

        return $score;
    }
}
