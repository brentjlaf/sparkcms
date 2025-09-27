<?php
// Shared helper functions for calendar data processing.
// Extracted to support both administrative and front-end calendar endpoints.

declare(strict_types=1);

function generate_occurrences(array $event, DateTimeImmutable $rangeStart, DateTimeImmutable $rangeEnd, ?array $category, string $recurrenceType): array
{
    $base = normalize_event_dates($event);
    if (!$base) {
        return [];
    }
    [$start, $end, $allDay] = $base;
    $duration = max(60, $end->getTimestamp() - $start->getTimestamp());

    $occurrences = [];
    $interval = max(1, (int) ($event['recurrence']['interval'] ?? 1));
    $recurrenceEnd = !empty($event['recurrence']['end_date'])
        ? DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $event['recurrence']['end_date'] . ' 23:59:59')
        : null;

    $id = $event['id'];
    $title = $event['title'];
    $description = $event['description'] ?? '';

    $limit = min_date($rangeEnd, $recurrenceEnd);

    if ($recurrenceType === 'none') {
        if (date_ranges_overlap($start, $end, $rangeStart, $rangeEnd)) {
            $occurrences[] = format_occurrence($id, $start, $end, $title, $description, $category, $allDay, $event, $recurrenceType, $interval, $recurrenceEnd);
        }
        return $occurrences;
    }

    $intervalSpec = build_interval_spec($recurrenceType, $interval);
    if (!$intervalSpec) {
        return $occurrences;
    }

    $periodInterval = new DateInterval($intervalSpec);
    $periodEnd = ($limit ?? $rangeEnd)->modify('+1 day');
    $period = new DatePeriod($start, $periodInterval, $periodEnd);
    $count = 0;
    foreach ($period as $occurrenceStart) {
        if ($recurrenceEnd && $occurrenceStart > $recurrenceEnd) {
            break;
        }
        $occurrenceEnd = $occurrenceStart->modify('+' . $duration . ' seconds');
        if (!date_ranges_overlap($occurrenceStart, $occurrenceEnd, $rangeStart, $rangeEnd)) {
            if ($occurrenceEnd <= $rangeStart) {
                continue;
            }
            if ($occurrenceStart >= $rangeEnd) {
                break;
            }
        }
        $occurrences[] = format_occurrence($id, $occurrenceStart, $occurrenceEnd, $title, $description, $category, $allDay, $event, $recurrenceType, $interval, $recurrenceEnd);
        $count++;
        if ($count >= 500) {
            break;
        }
    }

    return $occurrences;
}

function normalize_event_dates(array $event): ?array
{
    $allDay = !empty($event['all_day']);
    $startDate = $event['start_date'] ?? '';
    $endDate = $event['end_date'] ?? $startDate;
    $startTime = $allDay ? '00:00' : ($event['start_time'] ?? '00:00');
    $endTime = $allDay ? '23:59' : ($event['end_time'] ?? $startTime);

    $start = DateTimeImmutable::createFromFormat('Y-m-d H:i', $startDate . ' ' . $startTime);
    $end = DateTimeImmutable::createFromFormat('Y-m-d H:i', $endDate . ' ' . $endTime);

    if (!$start) {
        return null;
    }
    if (!$end || $end < $start) {
        $end = $start->modify('+1 hour');
    }

    if ($allDay) {
        $start = $start->setTime(0, 0, 0);
        $end = $end->setTime(23, 59, 59);
    }

    return [$start, $end, $allDay];
}

function format_occurrence(string $id, DateTimeImmutable $start, DateTimeImmutable $end, string $title, string $description, ?array $category, bool $allDay, array $event, string $recurrenceType, int $interval, ?DateTimeImmutable $recurrenceEnd): array
{
    $occurrenceId = $id . '_' . $start->format('YmdHis');
    return [
        'id' => $occurrenceId,
        'sourceId' => $id,
        'title' => $title,
        'description' => $description,
        'start' => $start->format(DateTime::ATOM),
        'end' => $end->format(DateTime::ATOM),
        'allDay' => $allDay,
        'category' => $category,
        'recurrence' => [
            'type' => $recurrenceType,
            'interval' => $interval,
            'endDate' => $recurrenceEnd ? $recurrenceEnd->format('Y-m-d') : null,
        ],
    ];
}

function build_interval_spec(string $type, int $interval): ?string
{
    switch ($type) {
        case 'daily':
            return 'P' . $interval . 'D';
        case 'weekly':
            return 'P' . ($interval * 7) . 'D';
        case 'monthly':
            return 'P' . $interval . 'M';
        case 'yearly':
            return 'P' . $interval . 'Y';
        default:
            return null;
    }
}

function min_date(DateTimeImmutable $a, ?DateTimeImmutable $b): DateTimeImmutable
{
    if (!$b) {
        return $a;
    }
    return $a < $b ? $a : $b;
}

function date_ranges_overlap(DateTimeImmutable $startA, DateTimeImmutable $endA, DateTimeImmutable $startB, DateTimeImmutable $endB): bool
{
    return $startA < $endB && $endA > $startB;
}

function normalize_recurrence_type(string $type): string
{
    $type = strtolower(trim($type));
    return in_array($type, ['daily', 'weekly', 'monthly', 'yearly'], true) ? $type : 'none';
}
