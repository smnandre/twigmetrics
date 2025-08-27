<?php

declare(strict_types=1);

namespace TwigMetrics\Calculator;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
final class DiversityCalculator
{
    /**
     * @param array<string,int> $usage
     */
    public function calculateSimpsonDiversity(array $usage): float
    {
        $total = array_sum($usage);
        if ($total <= 1) {
            return 0.0;
        }

        $sum = 0.0;
        foreach ($usage as $count) {
            $sum += $count * ($count - 1);
        }

        return 1.0 - ($sum / ($total * ($total - 1)));
    }

    /**
     * @param array<string,int> $usage
     */
    public function calculateUsageEntropy(array $usage): float
    {
        $total = array_sum($usage);
        if (0 === $total) {
            return 0.0;
        }

        $entropy = 0.0;
        foreach ($usage as $count) {
            if ($count > 0) {
                $p = $count / $total;
                $entropy -= $p * log($p, 2);
            }
        }

        return $entropy;
    }
}
