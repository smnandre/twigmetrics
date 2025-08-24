<?php

declare(strict_types=1);

namespace TwigMetrics\Config;

/**
 * Central configuration constants for TwigMetrics analysis.
 *
 * This class contains all magic numbers and thresholds used throughout
 * the analysis system, making them easily configurable and maintainable.
 *
 * @author Simon André <smn.andre@gmail.com>
 */
final class AnalysisConstants
{
    /** Logical Complexity dimension weight in overall health score */
    public const DIMENSION_WEIGHT_COMPLEXITY = 0.20;

    /** Template Files dimension weight in overall health score */
    public const DIMENSION_WEIGHT_TEMPLATE_FILES = 0.15;

    /** Template Relationships dimension weight in overall health score */
    public const DIMENSION_WEIGHT_RELATIONSHIPS = 0.15;

    /** Architecture dimension weight in overall health score */
    public const DIMENSION_WEIGHT_ARCHITECTURE = 0.15;

    /** Maintainability dimension weight in overall health score */
    public const DIMENSION_WEIGHT_MAINTAINABILITY = 0.15;

    /** Twig Callables dimension weight in overall health score */
    public const DIMENSION_WEIGHT_CALLABLES = 0.10;

    /** Code Style dimension weight in overall health score */
    public const DIMENSION_WEIGHT_CODE_STYLE = 0.10;

    /** Points added for each if statement */
    public const COMPLEXITY_IF_POINTS = 2;

    /** Points added for each for loop */
    public const COMPLEXITY_FOR_POINTS = 3;

    /** Points added for each ternary operator */
    public const COMPLEXITY_TERNARY_POINTS = 1;

    /** Points added for each logical operator (and/or) */
    public const COMPLEXITY_LOGICAL_OPERATOR_POINTS = 1;

    /** Points added for each level of nesting depth */
    public const COMPLEXITY_NESTING_POINTS = 2;

    /** Lines threshold for A rating */
    public const SIZE_RATING_A_THRESHOLD = 75;

    /** Lines threshold for B rating */
    public const SIZE_RATING_B_THRESHOLD = 100;

    /** Lines threshold for C rating */
    public const SIZE_RATING_C_THRESHOLD = 150;

    /** Complexity score threshold for A rating */
    public const COMPLEXITY_RATING_A_SCORE = 8;

    /** Complexity score threshold for B rating */
    public const COMPLEXITY_RATING_B_SCORE = 15;

    /** Complexity score threshold for C rating */
    public const COMPLEXITY_RATING_C_SCORE = 25;

    /** Nesting depth threshold for A rating */
    public const COMPLEXITY_RATING_A_DEPTH = 3;

    /** Nesting depth threshold for B rating */
    public const COMPLEXITY_RATING_B_DEPTH = 4;

    /** Nesting depth threshold for C rating */
    public const COMPLEXITY_RATING_C_DEPTH = 5;

    /** Unique functions threshold for major penalty */
    public const CALLABLES_FUNCTIONS_HIGH_THRESHOLD = 20;

    /** Unique functions threshold for minor penalty */
    public const CALLABLES_FUNCTIONS_MEDIUM_THRESHOLD = 15;

    /** Unique variables threshold for major penalty */
    public const CALLABLES_VARIABLES_HIGH_THRESHOLD = 10;

    /** Unique variables threshold for minor penalty */
    public const CALLABLES_VARIABLES_MEDIUM_THRESHOLD = 7;

    /** Unique filters threshold for major penalty */
    public const CALLABLES_FILTERS_HIGH_THRESHOLD = 8;

    /** Unique filters threshold for minor penalty */
    public const CALLABLES_FILTERS_MEDIUM_THRESHOLD = 5;

    /** Major penalty for excessive functions */
    public const CALLABLES_FUNCTIONS_HIGH_PENALTY = 20;

    /** Minor penalty for moderate functions */
    public const CALLABLES_FUNCTIONS_MEDIUM_PENALTY = 10;

    /** Major penalty for excessive variables */
    public const CALLABLES_VARIABLES_HIGH_PENALTY = 20;

    /** Minor penalty for moderate variables */
    public const CALLABLES_VARIABLES_MEDIUM_PENALTY = 10;

    /** Major penalty for excessive filters */
    public const CALLABLES_FILTERS_HIGH_PENALTY = 15;

    /** Minor penalty for moderate filters */
    public const CALLABLES_FILTERS_MEDIUM_PENALTY = 5;

    /** Bonus for good macro reuse */
    public const CALLABLES_MACRO_REUSE_BONUS = 10;

    /** Penalty for poor macro reuse */
    public const CALLABLES_MACRO_REUSE_PENALTY = 5;

    /** Score threshold for A rating */
    public const CALLABLES_RATING_A_THRESHOLD = 85;

    /** Score threshold for B rating */
    public const CALLABLES_RATING_B_THRESHOLD = 70;

    /** Score threshold for C rating */
    public const CALLABLES_RATING_C_THRESHOLD = 55;

    /** Excellent quality threshold (90%+) */
    public const QUALITY_EXCELLENT_THRESHOLD = 90;

    /** Good quality threshold (70-89%) */
    public const QUALITY_GOOD_THRESHOLD = 70;

    /** Fair quality threshold (50-69%) */
    public const QUALITY_FAIR_THRESHOLD = 50;

    /** Inheritance ratio threshold for low inheritance warning */
    public const INSIGHT_LOW_INHERITANCE_THRESHOLD = 0.3;

    /** Inheritance ratio threshold for balanced inheritance */
    public const INSIGHT_BALANCED_INHERITANCE_MIN = 0.4;

    /** Inheritance ratio threshold for balanced inheritance */
    public const INSIGHT_BALANCED_INHERITANCE_MAX = 0.8;

    /** Inheritance ratio threshold for excessive inheritance */
    public const INSIGHT_HIGH_INHERITANCE_THRESHOLD = 0.8;

    /** Minimum templates count for inheritance analysis */
    public const INSIGHT_MIN_TEMPLATES_FOR_ANALYSIS = 5;

    /** Orphan ratio threshold for major penalty */
    public const RELATIONSHIPS_HIGH_ORPHAN_THRESHOLD = 0.3;

    /** Orphan ratio threshold for moderate penalty */
    public const RELATIONSHIPS_MEDIUM_ORPHAN_THRESHOLD = 0.2;

    /** Orphan ratio threshold for minor penalty */
    public const RELATIONSHIPS_LOW_ORPHAN_THRESHOLD = 0.1;

    /** Reusability threshold for major penalty */
    public const ARCHITECTURE_LOW_REUSABILITY_THRESHOLD = 0.3;

    /** Reusability threshold for moderate penalty */
    public const ARCHITECTURE_MEDIUM_REUSABILITY_THRESHOLD = 0.5;

    /** Reusability threshold for minor penalty */
    public const ARCHITECTURE_HIGH_REUSABILITY_THRESHOLD = 0.7;

    /** Major penalty for low reusability */
    public const ARCHITECTURE_LOW_REUSABILITY_PENALTY = 25;

    /** Moderate penalty for medium reusability */
    public const ARCHITECTURE_MEDIUM_REUSABILITY_PENALTY = 15;

    /** Minor penalty for high reusability */
    public const ARCHITECTURE_HIGH_REUSABILITY_PENALTY = 5;

    /** Role deviation penalty multiplier */
    public const ARCHITECTURE_ROLE_DEVIATION_PENALTY = 20;

    /** Ideal percentage of component templates */
    public const ARCHITECTURE_IDEAL_COMPONENTS_RATIO = 0.6;

    /** Ideal percentage of page templates */
    public const ARCHITECTURE_IDEAL_PAGES_RATIO = 0.25;

    /** Ideal percentage of layout templates */
    public const ARCHITECTURE_IDEAL_LAYOUTS_RATIO = 0.15;

    /** Block weight in reusability calculation */
    public const ARCHITECTURE_BLOCK_WEIGHT = 0.3;

    /** Macro weight in reusability calculation */
    public const ARCHITECTURE_MACRO_WEIGHT = 0.5;

    /** Complexity divisor in reusability calculation */
    public const ARCHITECTURE_COMPLEXITY_DIVISOR = 0.1;

    /** High reusability threshold for rating */
    public const ARCHITECTURE_HIGH_REUSABILITY_RATING = 0.7;

    /** Medium reusability threshold for rating */
    public const ARCHITECTURE_MEDIUM_REUSABILITY_RATING = 0.4;

    /** Duplication ratio threshold for major penalty */
    public const MAINTAINABILITY_HIGH_DUPLICATION_THRESHOLD = 0.3;

    /** Duplication ratio threshold for moderate penalty */
    public const MAINTAINABILITY_MEDIUM_DUPLICATION_THRESHOLD = 0.2;

    /** Duplication ratio threshold for minor penalty */
    public const MAINTAINABILITY_LOW_DUPLICATION_THRESHOLD = 0.1;

    /** Tech debt ratio threshold for major penalty */
    public const MAINTAINABILITY_HIGH_DEBT_THRESHOLD = 0.4;

    /** Tech debt ratio threshold for moderate penalty */
    public const MAINTAINABILITY_MEDIUM_DEBT_THRESHOLD = 0.3;

    /** Tech debt ratio threshold for minor penalty */
    public const MAINTAINABILITY_LOW_DEBT_THRESHOLD = 0.2;

    /** Code style score threshold for excellent rating */
    public const CODE_STYLE_EXCELLENT_THRESHOLD = 95;

    /** Code style score threshold for good rating */
    public const CODE_STYLE_GOOD_THRESHOLD = 85;

    /** Code style score threshold for fair rating */
    public const CODE_STYLE_FAIR_THRESHOLD = 75;

    /** Complexity threshold for excellent rating */
    public const DIRECTORY_COMPLEXITY_EXCELLENT_THRESHOLD = 10;

    /** Complexity threshold for good rating */
    public const DIRECTORY_COMPLEXITY_GOOD_THRESHOLD = 20;

    /** Complexity threshold for fair rating */
    public const DIRECTORY_COMPLEXITY_FAIR_THRESHOLD = 30;

    /** Quality score threshold for excellent rating */
    public const DIRECTORY_QUALITY_EXCELLENT_THRESHOLD = 80;

    /** Quality score threshold for good rating */
    public const DIRECTORY_QUALITY_GOOD_THRESHOLD = 60;

    /** Quality score threshold for fair rating */
    public const DIRECTORY_QUALITY_FAIR_THRESHOLD = 40;

    /** Formatting weight in directory comparison */
    public const DIRECTORY_FORMATTING_WEIGHT = 0.3;

    /** Main score weight in directory comparison */
    public const DIRECTORY_MAIN_SCORE_WEIGHT = 0.7;

    /** Maximum duplication ratio for simulation */
    public const SIMULATION_MAX_DUPLICATION_RATIO = 0.2;

    /** Maximum templates to display in some reports */
    public const DISPLAY_MAX_TEMPLATES_LIMIT = 10;

    /** Maximum templates for maintainability analysis */
    public const MAINTAINABILITY_MAX_TEMPLATES_ANALYZED = 10;

    /** Default maximum directory depth for comparison */
    public const DEFAULT_MAX_DIRECTORY_DEPTH = 2;

    /** Default quality score baseline */
    public const DEFAULT_QUALITY_SCORE = 100;

    /** Default analysis failure score */
    public const DEFAULT_ANALYSIS_FAILURE_SCORE = 0.0;

    /** Default consistency score when no patterns found */
    public const DEFAULT_CONSISTENCY_SCORE = 100.0;
}
