<?php

declare(strict_types=1);

namespace TwigMetrics\Reporter\Dimension;

use TwigMetrics\Analyzer\AnalysisResult;
use TwigMetrics\Config\AnalysisConstants;
use TwigMetrics\Report\Report;
use TwigMetrics\Report\Section\KeyValueSection;
use TwigMetrics\Report\Section\ListSection;
use TwigMetrics\Report\Section\ReportSection;
use TwigMetrics\Report\Section\TableSection;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
final class TwigCallablesReporter extends DimensionReporter
{
    public function getWeight(): float
    {
        return AnalysisConstants::DIMENSION_WEIGHT_CALLABLES;
    }

    public function getDimensionName(): string
    {
        return 'Twig Callables';
    }

    /**
     * @param AnalysisResult[] $results
     */
    public function generate(array $results): Report
    {
        $report = new Report('Twig Callables Usage Analysis');

        $summary = $this->buildSummary($results);
        $report->addSection(new KeyValueSection('Summary', $summary));

        $overview = $this->buildOverviewStats($results);
        $report->addSection(new ReportSection('Function & Filter Usage', 'callables_overview', $overview));

        $dirMatrix = $this->buildDirectoryMatrix($results);
        if (!empty($dirMatrix)) {
            $dirMatrix['overview'] = $overview;
            $report->addSection(new ReportSection('Callables by Directory', 'callables_dir_chart', $dirMatrix));
        }

        $report->addSection($this->createFunctionUsageAnalysis($results));
        $report->addSection($this->createFilterUsageAnalysis($results));
        $report->addSection($this->createMacroAnalysis($results));
        $report->addSection($this->createGlobalVariableAnalysis($results));
        $report->addSection($this->createCallableRatings($results));

        [$topFnPaths, $topFlPaths] = $this->buildTopPaths($results);
        if (!empty($topFnPaths)) {
            $report->addSection(new ListSection('Most Functions (per template)', $topFnPaths));
        }
        if (!empty($topFlPaths)) {
            $report->addSection(new ListSection('Most Filters (per template)', $topFlPaths));
        }

        return $report;
    }

    /**
     * @param AnalysisResult[] $results
     *
     * @return array<string, string>
     */
    private function buildSummary(array $results): array
    {
        $funcTotal = 0;
        $filterTotal = 0;
        $macroDefs = 0;
        $macroCalls = 0;
        $varsTotal = 0;
        foreach ($results as $res) {
            $f = $res->getMetric('functions_detail') ?? [];
            $fl = $res->getMetric('filters_detail') ?? [];
            $md = $res->getMetric('macro_definitions_detail') ?? [];
            $mc = $res->getMetric('macro_calls_detail') ?? [];
            $v = $res->getMetric('variables_detail') ?? [];
            if (is_array($f)) {
                $funcTotal += array_sum($f);
            }
            if (is_array($fl)) {
                $filterTotal += array_sum($fl);
            }
            if (is_array($md)) {
                $macroDefs += count($md);
            }
            if (is_array($mc)) {
                $macroCalls += array_sum($mc);
            }
            if (is_array($v)) {
                $varsTotal += array_sum($v);
            }
        }

        return [
            'Functions' => (string) $funcTotal,
            'Filters' => (string) $filterTotal,
            'Macros' => sprintf('%d defs / %d calls', $macroDefs, $macroCalls),
            'Variables' => (string) $varsTotal,
        ];
    }

    /**
     * @param AnalysisResult[] $results
     *
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    private function buildTopPaths(array $results): array
    {
        $fnPerPath = [];
        $flPerPath = [];
        foreach ($results as $res) {
            $path = $res->getRelativePath();
            $f = $res->getMetric('functions_detail') ?? [];
            $fl = $res->getMetric('filters_detail') ?? [];
            if (is_array($f)) {
                $fnPerPath[$path] = array_sum($f);
            }
            if (is_array($fl)) {
                $flPerPath[$path] = array_sum($fl);
            }
        }
        arsort($fnPerPath);
        arsort($flPerPath);
        $topFn = array_slice($fnPerPath, 0, 5, true);
        $topFl = array_slice($flPerPath, 0, 5, true);

        $fnItems = [];
        foreach ($topFn as $p => $cnt) {
            $fnItems[] = sprintf('%s  %d functions', basename($p), (int) $cnt);
        }
        $flItems = [];
        foreach ($topFl as $p => $cnt) {
            $flItems[] = sprintf('%s  %d filters', basename($p), (int) $cnt);
        }

        return [$fnItems, $flItems];
    }

    /**
     * @param AnalysisResult[] $results
     *
     * @return array<string, mixed>
     */
    private function buildDirectoryMatrix(array $results): array
    {
        $dirs = [];
        foreach ($results as $res) {
            $path = $res->getRelativePath();
            $parts = explode('/', $path, 2);
            $dir = $parts[1] ?? false ? $parts[0].'/' : '(root)/';

            $dep = $res->getMetric('dependency_types') ?? [];
            $inc = (int) (($dep['includes'] ?? 0) + ($dep['includes_function'] ?? 0));
            $emb = (int) ($dep['embeds'] ?? 0);
            $ext = (int) ($dep['extends'] ?? 0);
            $imp = (int) ($dep['imports'] ?? 0);

            $blocks = $res->getMetric('provided_blocks') ?? [];
            if (!is_array($blocks)) {
                $blocks = [];
            }
            $blk = count($blocks);

            $macros = $res->getMetric('macro_definitions_detail') ?? [];
            if (!is_array($macros)) {
                $macros = [];
            }
            $mac = count($macros);

            if (!isset($dirs[$dir])) {
                $dirs[$dir] = ['blk' => 0, 'inc' => 0, 'emb' => 0, 'ext' => 0, 'imp' => 0, 'mac' => 0];
            }
            $dirs[$dir]['blk'] += $blk;
            $dirs[$dir]['inc'] += $inc;
            $dirs[$dir]['emb'] += $emb;
            $dirs[$dir]['ext'] += $ext;
            $dirs[$dir]['imp'] += $imp;
            $dirs[$dir]['mac'] += $mac;
        }

        uasort($dirs, function ($a, $b) {
            $ta = array_sum($a);
            $tb = array_sum($b);
            if ($tb === $ta) {
                return 0;
            }

            return $tb <=> $ta;
        });

        $max = ['blk' => 1, 'inc' => 1, 'emb' => 1, 'ext' => 1, 'imp' => 1, 'mac' => 1];
        foreach ($dirs as $row) {
            foreach ($max as $k => $_) {
                $max[$k] = max($max[$k], (int) $row[$k]);
            }
        }

        $rows = [];
        $i = 0;
        foreach ($dirs as $name => $vals) {
            if ($i++ >= 12) {
                break;
            }
            $rows[] = ['dir' => $name, 'vals' => $vals];
        }

        return ['rows' => $rows, 'max' => $max, 'labels' => ['blk', 'inc', 'emb', 'ext', 'imp', 'mac']];
    }

    /**
     * @param AnalysisResult[] $results
     *
     * @return array<string, array<int, array<string, int|string>>>
     */
    private function buildOverviewStats(array $results): array
    {
        $functionCounts = [];
        $filterCounts = [];
        $variableCounts = [];
        $macroCallsAgg = [];
        $testCounts = [];
        $blockCounts = [];

        foreach ($results as $result) {
            $functions = $result->getMetric('functions_detail') ?? [];
            if (is_array($functions)) {
                foreach ($functions as $fn => $count) {
                    $functionCounts[$fn] = ($functionCounts[$fn] ?? 0) + (int) $count;
                }
            }
            $filters = $result->getMetric('filters_detail') ?? [];
            if (is_array($filters)) {
                foreach ($filters as $fl => $count) {
                    $filterCounts[$fl] = ($filterCounts[$fl] ?? 0) + (int) $count;
                }
            }

            $variables = $result->getMetric('variables_detail') ?? [];
            if (is_array($variables)) {
                foreach ($variables as $var => $count) {
                    $variableCounts[$var] = ($variableCounts[$var] ?? 0) + (int) $count;
                }
            }

            $macroCalls = $result->getMetric('macro_calls_detail') ?? [];
            if (is_array($macroCalls)) {
                foreach ($macroCalls as $macro => $count) {
                    $macroCallsAgg[$macro] = ($macroCallsAgg[$macro] ?? 0) + (int) $count;
                }
            }

            $tests = $result->getMetric('tests_detail') ?? [];
            if (is_array($tests)) {
                foreach ($tests as $test => $count) {
                    $testCounts[$test] = ($testCounts[$test] ?? 0) + (int) $count;
                }
            }

            $blocks = $result->getMetric('provided_blocks') ?? [];
            if (is_array($blocks)) {
                foreach ($blocks as $block => $count) {
                    $blockCounts[$block] = ($blockCounts[$block] ?? 0) + 1;
                }
            }
        }

        arsort($functionCounts);
        arsort($filterCounts);
        arsort($variableCounts);
        arsort($macroCallsAgg);
        arsort($testCounts);
        arsort($blockCounts);

        $topFn = array_slice($functionCounts, 0, 12, true);
        $topFl = array_slice($filterCounts, 0, 12, true);
        $topVars = array_slice($variableCounts, 0, 10, true);
        $topMacros = array_slice($macroCallsAgg, 0, 10, true);
        $topTests = array_slice($testCounts, 0, 10, true);
        $topBlocks = array_slice($blockCounts, 0, 10, true);

        $fnList = [];
        foreach ($topFn as $name => $count) {
            $fnList[] = ['name' => (string) $name, 'count' => (int) $count];
        }
        $flList = [];
        foreach ($topFl as $name => $count) {
            $flList[] = ['name' => (string) $name, 'count' => (int) $count];
        }
        $varList = [];
        foreach ($topVars as $name => $count) {
            $varList[] = ['name' => (string) $name, 'count' => (int) $count];
        }
        $macroList = [];
        foreach ($topMacros as $name => $count) {
            $macroList[] = ['name' => (string) $name, 'count' => (int) $count];
        }
        $testList = [];
        foreach ($topTests as $name => $count) {
            $testList[] = ['name' => (string) $name, 'count' => (int) $count];
        }
        $blockList = [];
        foreach ($topBlocks as $name => $count) {
            $blockList[] = ['name' => (string) $name, 'count' => (int) $count];
        }

        return [
            'functions' => $fnList,
            'filters' => $flList,
            'variables' => $varList,
            'macros' => $macroList,
            'tests' => $testList,
            'blocks' => $blockList,
        ];
    }

    public function calculateDimensionScore(array $results): float
    {
        return $this->calculateCallableHealthScore($results);
    }

    /**
     * @param AnalysisResult[] $results
     */
    private function calculateCallableHealthScore(array $results): float
    {
        $totalScore = 0;
        $validResults = 0;

        foreach ($results as $result) {
            $functions = $result->getMetric('functions_detail') ?? [];
            $globalVariables = $result->getMetric('variables_detail') ?? [];
            $macroDefinitions = $result->getMetric('macro_definitions_detail') ?? [];
            $macroCalls = $result->getMetric('macro_calls_detail') ?? [];
            $filters = $result->getMetric('filters_detail') ?? [];

            if (!is_array($functions)) {
                $functions = [];
            }
            if (!is_array($globalVariables)) {
                $globalVariables = [];
            }
            if (!is_array($macroDefinitions)) {
                $macroDefinitions = [];
            }
            if (!is_array($macroCalls)) {
                $macroCalls = [];
            }
            if (!is_array($filters)) {
                $filters = [];
            }

            $score = AnalysisConstants::DEFAULT_QUALITY_SCORE;

            $uniqueFunctions = count($functions);
            if ($uniqueFunctions > AnalysisConstants::CALLABLES_FUNCTIONS_HIGH_THRESHOLD) {
                $score -= AnalysisConstants::CALLABLES_FUNCTIONS_HIGH_PENALTY;
            } elseif ($uniqueFunctions > AnalysisConstants::CALLABLES_FUNCTIONS_MEDIUM_THRESHOLD) {
                $score -= AnalysisConstants::CALLABLES_FUNCTIONS_MEDIUM_PENALTY;
            } elseif ($uniqueFunctions > 10) {
                $score -= 10;
            }

            $uniqueGlobals = count($globalVariables);
            if ($uniqueGlobals > 10) {
                $score -= 20;
            } elseif ($uniqueGlobals > 7) {
                $score -= 15;
            } elseif ($uniqueGlobals > 5) {
                $score -= 10;
            }

            $macroReuse = count($macroDefinitions) > 0 ? count($macroCalls) / count($macroDefinitions) : 0;
            if ($macroReuse > 3) {
                $score += 10;
            } elseif ($macroReuse > 2) {
                $score += 5;
            }

            $uniqueFilters = count($filters);
            if ($uniqueFilters > 15) {
                $score -= 15;
            } elseif ($uniqueFilters > 10) {
                $score -= 10;
            } elseif ($uniqueFilters > 7) {
                $score -= 5;
            }

            $totalScore += max(0, min(100, $score));
            ++$validResults;
        }

        return $validResults > 0 ? $totalScore / $validResults : 0;
    }

    /**
     * @param AnalysisResult[] $results
     */
    private function createFunctionUsageAnalysis(array $results): TableSection
    {
        $functionCounts = [];

        foreach ($results as $result) {
            $functions = $result->getMetric('functions_detail') ?? [];
            if (is_array($functions)) {
                foreach ($functions as $function => $count) {
                    $functionCounts[$function] = ($functionCounts[$function] ?? 0) + $count;
                }
            }
        }

        arsort($functionCounts);
        $topFunctions = array_slice($functionCounts, 0, 15, true);

        $rows = [];
        foreach ($topFunctions as $function => $count) {
            $rows[] = [$function, $count];
        }

        return new TableSection(
            'Most Used Functions',
            ['Function', 'Usage Count'],
            $rows
        );
    }

    /**
     * @param AnalysisResult[] $results
     */
    private function createFilterUsageAnalysis(array $results): TableSection
    {
        $filterCounts = [];

        foreach ($results as $result) {
            $filters = $result->getMetric('filters_detail') ?? [];
            if (is_array($filters)) {
                foreach ($filters as $filter => $count) {
                    $filterCounts[$filter] = ($filterCounts[$filter] ?? 0) + $count;
                }
            }
        }

        arsort($filterCounts);
        $topFilters = array_slice($filterCounts, 0, 15, true);

        $rows = [];
        foreach ($topFilters as $filter => $count) {
            $rows[] = [$filter, $count];
        }

        return new TableSection(
            'Most Used Filters',
            ['Filter', 'Usage Count'],
            $rows
        );
    }

    /**
     * @param AnalysisResult[] $results
     */
    private function createMacroAnalysis(array $results): TableSection
    {
        $macroStats = [];

        foreach ($results as $result) {
            $macroDefinitions = $result->getMetric('macro_definitions_detail') ?? [];
            $macroCalls = $result->getMetric('macro_calls_detail') ?? [];

            if (!is_array($macroDefinitions)) {
                $macroDefinitions = [];
            }
            if (!is_array($macroCalls)) {
                $macroCalls = [];
            }

            foreach ($macroDefinitions as $macro => $defCount) {
                $usageCount = $macroCalls[$macro] ?? 0;
                $macroStats[] = [
                    $macro,
                    basename($result->getRelativePath()),
                    $usageCount,
                    $usageCount > 0 ? 'Yes' : 'No',
                ];
            }
        }

        usort($macroStats, fn ($a, $b) => $b[2] <=> $a[2]);

        return new TableSection(
            'Macro Definitions & Usage',
            ['Macro', 'Defined In', 'Usage Count', 'Reused'],
            array_slice($macroStats, 0, 20)
        );
    }

    /**
     * @param AnalysisResult[] $results
     */
    private function createGlobalVariableAnalysis(array $results): TableSection
    {
        $variableCounts = [];

        foreach ($results as $result) {
            $variables = $result->getMetric('variables_detail') ?? [];
            if (is_array($variables)) {
                foreach ($variables as $variable => $count) {
                    $variableCounts[$variable] = ($variableCounts[$variable] ?? 0) + $count;
                }
            }
        }

        arsort($variableCounts);
        $topVariables = array_slice($variableCounts, 0, 15, true);

        $rows = [];
        foreach ($topVariables as $variable => $count) {
            $rows[] = [$variable, $count];
        }

        return new TableSection(
            'Most Used Global Variables',
            ['Variable', 'Usage Count'],
            $rows
        );
    }

    /**
     * @param AnalysisResult[] $results
     */
    private function createCallableRatings(array $results): TableSection
    {
        $ratings = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0];

        foreach ($results as $result) {
            $rating = $result->getMetric('callables_rating') ?? 'D';
            $ratings[$rating] = ($ratings[$rating] ?? 0) + 1;
        }

        $rows = [];
        foreach ($ratings as $rating => $count) {
            $percentage = count($results) > 0 ? ($count / count($results)) * 100 : 0;
            $rows[] = [$rating, $count, sprintf('%.1f%%', $percentage)];
        }

        return new TableSection(
            'Callable Usage Ratings',
            ['Rating', 'Count', 'Percentage'],
            $rows
        );
    }
}
