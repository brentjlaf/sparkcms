<?php
require_once __DIR__ . '/../CMS/modules/calendar/CalendarRepository.php';

$eventsFile = tempnam(sys_get_temp_dir(), 'calendar-events-');
$categoriesFile = tempnam(sys_get_temp_dir(), 'calendar-categories-');
if ($eventsFile === false || $categoriesFile === false) {
    throw new RuntimeException('Unable to create temporary calendar dataset.');
}

file_put_contents($eventsFile, "[]");
file_put_contents($categoriesFile, "[]");

$repository = new CalendarRepository($eventsFile, $categoriesFile);

// Add baseline categories to validate normalization.
$repository->addCategory('Work', '#ff0000');
$repository->addCategory('Personal', '#00ff00');

$normalizedExisting = $repository->normalizeCategoryName('work');
if ($normalizedExisting !== 'Work') {
    throw new RuntimeException('Category normalization should return the canonical stored value.');
}

$normalizedMissing = $repository->normalizeCategoryName('Unknown');
if ($normalizedMissing !== '') {
    throw new RuntimeException('Unknown categories should normalize to an empty string.');
}

$validRecurrence = $repository->normalizeRecurrence('weekly');
if ($validRecurrence !== 'weekly') {
    throw new RuntimeException('Known recurrence values should be preserved.');
}

$invalidRecurrence = $repository->normalizeRecurrence('Yearly');
if ($invalidRecurrence !== 'none') {
    throw new RuntimeException('Unexpected recurrence values should default to "none".');
}

$events = $repository->saveEvent([
    'title' => 'Planning Meeting',
    'category' => 'work',
    'start_date' => '2024-04-01 10:00:00',
    'end_date' => '2024-04-01 11:00:00',
    'recurring_interval' => 'daily',
    'recurring_end_date' => '2024-04-05 10:00:00',
    'description' => 'Discuss quarterly goals.',
]);

if (count($events) !== 1) {
    throw new RuntimeException('Event should be persisted.');
}

$event = $events[0];
if ($event['category'] !== 'Work') {
    throw new RuntimeException('Event category should normalize to the canonical category name.');
}

if ($event['recurring_interval'] !== 'daily') {
    throw new RuntimeException('Recurring interval should store valid options.');
}

$metrics = CalendarRepository::computeMetrics($events, $repository->getCategories());
if (!isset($metrics['total_events']) || $metrics['total_events'] !== 1) {
    throw new RuntimeException('Metrics should count persisted events.');
}

unlink($eventsFile);
unlink($categoriesFile);

echo "CalendarRepository tests passed\n";
