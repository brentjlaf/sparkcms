<?php
// Shared helper functions for calendar data processing tailored to the
// simplified calendar data structure managed via manage_data.php.

declare(strict_types=1);

/**
 * Normalize an event into a list of occurrences within the requested range.
 */
function expand_event_occurrences(
    array $event,
    DateTimeImmutable $rangeStart,
    DateTimeImmutable $rangeEnd,
    ?array $category
): array {
    $base = normalize_event_dates($event);
    if (!$base) {
        return [];
    }
    [$start, $end, $allDay] = $base;

    // Extend end when it's earlier than start (minimum duration 1 hour)
    if ($end < $start) {
        $end = $start->modify('+1 hour');
    }

    $duration = max(60, $end->getTimestamp() - $start->getTimestamp());

    $recurrenceType = normalize_recurrence_type($event['recurring_interval'] ?? 'none');
    $recurrenceEnd = parse_calendar_datetime($event['recurring_end_date'] ?? '', true);

    if ($recurrenceType === 'none') {
        if (!date_ranges_overlap($start, $end, $rangeStart, $rangeEnd)) {
            return [];
        }
        return [format_occurrence($event, $start, $end, $allDay, $category, $recurrenceType, $recurrenceEnd)];
    }

    $intervalSpec = build_interval_spec($recurrenceType);
    if (!$intervalSpec) {
        return [];
    }

    $periodInterval = new DateInterval($intervalSpec);
    $limit = $recurrenceEnd && $recurrenceEnd < $rangeEnd ? $recurrenceEnd : $rangeEnd;
    $period = new DatePeriod($start, $periodInterval, $limit->modify('+1 day'));

    $occurrences = [];
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
        $occurrences[] = format_occurrence($event, $occurrenceStart, $occurrenceEnd, $allDay, $category, $recurrenceType, $recurrenceEnd);
        $count++;
        if ($count >= 500) {
            break;
        }
    }

    return $occurrences;
}

function normalize_event_dates(array $event): ?array
{
    $startRaw = (string) ($event['start_date'] ?? '');
    if ($startRaw === '') {
        return null;
    }
    $endRaw = (string) ($event['end_date'] ?? '');

    $start = parse_calendar_datetime($startRaw, false);
    if (!$start) {
        return null;
    }
    $end = $endRaw !== '' ? parse_calendar_datetime($endRaw, true) : null;
    if (!$end) {
        $end = $start;
    }

    $allDay = is_all_day_value($startRaw) && ($endRaw === '' || is_all_day_value($endRaw));
    if ($allDay) {
        $start = $start->setTime(0, 0, 0);
        $end = $end->setTime(23, 59, 0);
    }

    return [$start, $end, $allDay];
}

function parse_calendar_datetime(?string $value, bool $isEnd): ?DateTimeImmutable
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    $value = str_replace('T', ' ', $value);
    $formats = ['Y-m-d H:i', 'Y-m-d H:i:s', 'Y-m-d'];
    foreach ($formats as $format) {
        $dt = DateTimeImmutable::createFromFormat($format, $value);
        if ($dt instanceof DateTimeImmutable) {
            if ($format === 'Y-m-d' && $isEnd) {
                $dt = $dt->setTime(23, 59, 0);
            }
            return $dt;
        }
    }

    try {
        $dt = new DateTimeImmutable($value);
        if ($isEnd && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            $dt = $dt->setTime(23, 59, 0);
        }
        return $dt;
    } catch (Exception $e) {
        return null;
    }
}

function is_all_day_value(string $value): bool
{
    return !preg_match('/\d{2}:\d{2}/', $value);
}

function format_occurrence(
    array $event,
    DateTimeImmutable $start,
    DateTimeImmutable $end,
    bool $allDay,
    ?array $category,
    string $recurrenceType,
    ?DateTimeImmutable $recurrenceEnd
): array {
    $sourceId = (string) ($event['id'] ?? '');
    if ($sourceId === '') {
        $sourceId = uniqid('evt_', true);
    }

    return [
        'id' => $sourceId . '_' . $start->format('YmdHis'),
        'sourceId' => $sourceId,
        'title' => (string) ($event['title'] ?? 'Event'),
        'description' => (string) ($event['description'] ?? ''),
        'start' => $start->format(DateTime::ATOM),
        'end' => $end->format(DateTime::ATOM),
        'allDay' => $allDay,
        'category' => $category ? [
            'id' => $category['id'] ?? null,
            'name' => $category['name'] ?? '',
            'color' => $category['color'] ?? '#2563eb',
        ] : null,
        'recurrence' => [
            'type' => $recurrenceType,
            'interval' => 1,
            'endDate' => $recurrenceEnd ? $recurrenceEnd->format('Y-m-d') : null,
        ],
    ];
}

function build_interval_spec(string $type): ?string
{
    switch ($type) {
        case 'daily':
            return 'P1D';
        case 'weekly':
            return 'P7D';
        case 'monthly':
            return 'P1M';
        case 'yearly':
            return 'P1Y';
        default:
            return null;
    }
}

function date_ranges_overlap(
    DateTimeImmutable $startA,
    DateTimeImmutable $endA,
    DateTimeImmutable $startB,
    DateTimeImmutable $endB
): bool {
    return $startA < $endB && $endA > $startB;
}

function normalize_recurrence_type(string $type): string
{
    $type = strtolower(trim($type));
    return in_array($type, ['daily', 'weekly', 'monthly', 'yearly'], true) ? $type : 'none';
}
