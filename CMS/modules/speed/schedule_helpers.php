<?php

function speed_allowed_cadences(): array
{
    return [
        'manual' => [
            'label' => 'Manual (on demand)',
            'interval' => null,
        ],
        'hourly' => [
            'label' => 'Hourly',
            'interval' => 'PT1H',
        ],
        'daily' => [
            'label' => 'Daily',
            'interval' => 'P1D',
        ],
        'weekly' => [
            'label' => 'Weekly',
            'interval' => 'P1W',
        ],
    ];
}

function speed_default_schedule(): array
{
    return [
        'cadence' => 'manual',
        'status' => 'manual',
        'nextRun' => null,
        'queue' => [],
        'lastScheduledAt' => null,
        'updatedAt' => null,
        'cancelledAt' => null,
    ];
}

function speed_normalize_schedule(array $schedule): array
{
    $defaults = speed_default_schedule();
    $normalized = $defaults;
    foreach ($schedule as $key => $value) {
        if (array_key_exists($key, $defaults)) {
            $normalized[$key] = $value;
        } else {
            $normalized[$key] = $value;
        }
    }

    $allowedCadences = speed_allowed_cadences();
    if (!isset($allowedCadences[$normalized['cadence']])) {
        $normalized['cadence'] = 'manual';
    }

    if (!isset($normalized['queue']) || !is_array($normalized['queue'])) {
        $normalized['queue'] = [];
    }

    $normalized['queue'] = array_values(array_filter($normalized['queue'], function ($entry) {
        if (!is_array($entry)) {
            return false;
        }
        return !empty($entry['scheduledFor']) && is_string($entry['scheduledFor']);
    }));

    usort($normalized['queue'], function ($a, $b) {
        return strcmp($a['scheduledFor'], $b['scheduledFor']);
    });

    if (empty($normalized['nextRun']) && !empty($normalized['queue'])) {
        $normalized['nextRun'] = $normalized['queue'][0]['scheduledFor'];
    }

    if ($normalized['cadence'] === 'manual') {
        $normalized['status'] = 'manual';
    } elseif (!empty($normalized['queue'])) {
        $normalized['status'] = 'scheduled';
    } elseif ($normalized['status'] !== 'paused') {
        $normalized['status'] = 'pending';
    }

    return $normalized;
}

function speed_calculate_next_run(string $cadence, ?DateTimeImmutable $from = null): ?DateTimeImmutable
{
    $allowed = speed_allowed_cadences();
    if (!isset($allowed[$cadence])) {
        return null;
    }

    $intervalSpec = $allowed[$cadence]['interval'] ?? null;
    if (!$intervalSpec) {
        return null;
    }

    $from = $from ?: new DateTimeImmutable('now');

    try {
        $interval = new DateInterval($intervalSpec);
    } catch (Exception $e) {
        return null;
    }

    return $from->add($interval);
}

function speed_build_queue_entry(string $cadence, DateTimeImmutable $now, DateTimeImmutable $next): array
{
    return [
        'id' => str_replace('.', '', uniqid('speed_', true)),
        'status' => 'pending',
        'cadence' => $cadence,
        'createdAt' => $now->format(DateTimeInterface::ATOM),
        'scheduledFor' => $next->format(DateTimeInterface::ATOM),
    ];
}

function speed_format_schedule_time(?string $isoTimestamp): string
{
    if (empty($isoTimestamp)) {
        return '';
    }

    try {
        $date = new DateTimeImmutable($isoTimestamp);
    } catch (Exception $e) {
        return '';
    }

    return $date->setTimezone(new DateTimeZone(date_default_timezone_get()))->format('M j, Y g:i A');
}

function speed_enrich_schedule(array $schedule): array
{
    $schedule = speed_normalize_schedule($schedule);
    $cadences = speed_allowed_cadences();
    $cadenceLabel = $cadences[$schedule['cadence']]['label'] ?? ucfirst($schedule['cadence']);
    $schedule['cadenceLabel'] = $cadenceLabel;
    $schedule['nextRunHuman'] = speed_format_schedule_time($schedule['nextRun']);
    $schedule['hasNextRun'] = $schedule['nextRunHuman'] !== '';

    if ($schedule['cadence'] === 'manual') {
        $schedule['status'] = 'manual';
    } elseif (!empty($schedule['queue'])) {
        $schedule['status'] = 'scheduled';
    } elseif ($schedule['status'] !== 'paused') {
        $schedule['status'] = 'pending';
    }

    switch ($schedule['status']) {
        case 'manual':
            $schedule['statusLabel'] = 'Manual';
            break;
        case 'paused':
            $schedule['statusLabel'] = 'Paused';
            break;
        case 'scheduled':
            $schedule['statusLabel'] = 'Scheduled';
            break;
        default:
            $schedule['statusLabel'] = 'Pending';
            break;
    }

    return $schedule;
}
