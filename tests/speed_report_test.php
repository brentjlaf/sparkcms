<?php
require_once __DIR__ . '/../CMS/includes/reporting_helpers.php';
require_once __DIR__ . '/../CMS/modules/speed/SpeedReport.php';

$delta = report_calculate_change(120.0, 100.0);
if (abs($delta['absolute'] - 20.0) > 0.001 || ($delta['direction'] ?? '') !== 'positive') {
    throw new RuntimeException('Positive change calculation failed.');
}
if (abs(($delta['percent'] ?? 0) - 20.0) > 0.001) {
    throw new RuntimeException('Percentage change should be 20% for increase.');
}

$delta = report_calculate_change(80.0, 100.0);
if (abs($delta['absolute'] + 20.0) > 0.001 || ($delta['direction'] ?? '') !== 'negative') {
    throw new RuntimeException('Negative change calculation failed.');
}
if (abs(($delta['percent'] ?? 0) + 20.0) > 0.001) {
    throw new RuntimeException('Percentage change should be -20% for decrease.');
}

$delta = report_calculate_change(50.0, null);
if (!empty($delta['hasBaseline'])) {
    throw new RuntimeException('Baseline flag should be false when previous value missing.');
}
if ($delta['absolute'] != 0.0 || $delta['percent'] !== null) {
    throw new RuntimeException('Change should be zero with no baseline.');
}

$delta = report_calculate_change(10.0, 0.0);
if (($delta['percent'] ?? null) !== null) {
    throw new RuntimeException('Percent change should be null when baseline is zero and change non-zero.');
}
if (($delta['direction'] ?? '') !== 'positive') {
    throw new RuntimeException('Direction should be positive for growth from zero baseline.');
}

$scoreClassMap = [
    'A' => 'speed-score--a',
    'B' => 'speed-score--b',
    'c' => 'speed-score--c',
    'x' => 'speed-score--d',
];
foreach ($scoreClassMap as $grade => $expectedClass) {
    if (SpeedReport::mapGradeToScoreClass($grade) !== $expectedClass) {
        throw new RuntimeException('Unexpected score class for grade ' . $grade);
    }
}

$badgeClassMap = [
    'A' => 'grade-a',
    'b' => 'grade-b',
    'C' => 'grade-c',
    'unknown' => 'grade-d',
];
foreach ($badgeClassMap as $grade => $expectedClass) {
    if (SpeedReport::mapGradeToBadgeClass($grade) !== $expectedClass) {
        throw new RuntimeException('Unexpected badge class for grade ' . $grade);
    }
}
