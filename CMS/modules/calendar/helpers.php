<?php
// File: modules/calendar/helpers.php

if (!function_exists('compute_calendar_metrics')) {
    /**
     * Compute dashboard metrics for the calendar module.
     *
     * @param array $events
     * @param array $categories
     * @return array<string,mixed>
     */
    function compute_calendar_metrics(array $events, array $categories): array
    {
        $now = time();
        $upcomingCount = 0;
        $recurringCount = 0;
        $nextEvent = null;

        foreach ($events as $event) {
            $startDate = isset($event['start_date']) ? (string) $event['start_date'] : '';
            if ($startDate === '') {
                continue;
            }

            $startTimestamp = strtotime($startDate);
            if ($startTimestamp === false) {
                continue;
            }

            if (isset($event['recurring_interval']) && (string) $event['recurring_interval'] !== 'none') {
                $recurringCount++;
            }

            if ($startTimestamp >= $now) {
                $upcomingCount++;
                if ($nextEvent === null || $startTimestamp < $nextEvent['timestamp'] || (
                    $startTimestamp === $nextEvent['timestamp'] && ((int) ($event['id'] ?? 0)) < $nextEvent['id']
                )) {
                    $nextEvent = [
                        'id' => (int) ($event['id'] ?? 0),
                        'title' => (string) ($event['title'] ?? ''),
                        'start_date' => date('c', $startTimestamp),
                        'timestamp' => $startTimestamp,
                    ];
                }
            }
        }

        $metrics = [
            'total_events' => count($events),
            'upcoming_count' => $upcomingCount,
            'recurring_count' => $recurringCount,
            'category_count' => count($categories),
        ];

        if ($nextEvent !== null) {
            $metrics['next_event'] = $nextEvent;
        } else {
            $metrics['next_event'] = null;
        }

        return $metrics;
    }
}
