<?php
// Shared helpers for reporting modules (speed, accessibility, etc.).
if (!function_exists('report_format_change')) {
    function report_format_change(float $value, int $decimals = 0): string
    {
        $absValue = number_format(abs($value), $decimals, '.', '');
        if ($value > 0) {
            return '+' . $absValue;
        }
        if ($value < 0) {
            return '-' . $absValue;
        }

        return number_format(0, $decimals, '.', '');
    }
}

if (!function_exists('report_calculate_change')) {
    /**
     * Calculate change metadata between current and previous metric values.
     *
     * @return array{
     *     current: float,
     *     previous: float|null,
     *     absolute: float,
     *     percent: float|null,
     *     direction: string,
     *     hasBaseline: bool
     * }
     */
    function report_calculate_change(float $current, ?float $previous): array
    {
        $hasBaseline = $previous !== null;
        $absoluteChange = $hasBaseline ? $current - (float) $previous : 0.0;

        $direction = 'neutral';
        if ($absoluteChange > 0) {
            $direction = 'positive';
        } elseif ($absoluteChange < 0) {
            $direction = 'negative';
        }

        $percentChange = null;
        if ($hasBaseline) {
            $previousValue = (float) $previous;
            if ($previousValue == 0.0) {
                $percentChange = $absoluteChange == 0.0 ? 0.0 : null;
            } else {
                $percentChange = ($absoluteChange / $previousValue) * 100;
            }
        }

        return [
            'current' => $current,
            'previous' => $previous,
            'absolute' => $absoluteChange,
            'percent' => $percentChange,
            'direction' => $direction,
            'hasBaseline' => $hasBaseline,
        ];
    }
}
