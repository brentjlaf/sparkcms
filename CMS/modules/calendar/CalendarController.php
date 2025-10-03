<?php
// File: CalendarController.php
// Routes calendar API requests through the repository.

class CalendarController
{
    /** @var CalendarRepository */
    private $repository;

    public function __construct(CalendarRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Handle an API action and return the response payload.
     *
     * @param string $action
     * @param string $method
     * @param array $postData
     * @return array<string,mixed>
     */
    public function handle(string $action, string $method, array $postData): array
    {
        switch ($action) {
            case 'save_event':
                $this->assertPost($method);
                $events = $this->repository->saveEvent([
                    'id' => isset($postData['evt_id']) ? (int) $postData['evt_id'] : 0,
                    'title' => $postData['title'] ?? '',
                    'category' => $postData['category'] ?? '',
                    'start_date' => $postData['start_date'] ?? '',
                    'end_date' => $postData['end_date'] ?? '',
                    'recurring_interval' => $postData['recurring_interval'] ?? 'none',
                    'recurring_end_date' => $postData['recurring_end_date'] ?? '',
                    'description' => $postData['description'] ?? '',
                ]);
                $categories = $this->repository->getCategories();
                $metrics = CalendarRepository::computeMetrics($events, $categories);
                return [
                    'success' => true,
                    'message' => isset($postData['evt_id']) && (int) $postData['evt_id'] > 0 ? 'Event updated successfully.' : 'Event created successfully.',
                    'events' => $events,
                    'categories' => $categories,
                    'metrics' => $metrics,
                ];

            case 'delete_event':
                $this->assertPost($method);
                $id = isset($postData['evt_id']) ? (int) $postData['evt_id'] : 0;
                $events = $this->repository->deleteEvent($id);
                $categories = $this->repository->getCategories();
                $metrics = CalendarRepository::computeMetrics($events, $categories);
                return [
                    'success' => true,
                    'message' => 'Event deleted.',
                    'events' => $events,
                    'categories' => $categories,
                    'metrics' => $metrics,
                ];

            case 'add_category':
                $this->assertPost($method);
                $categories = $this->repository->addCategory(
                    isset($postData['cat_name']) ? (string) $postData['cat_name'] : '',
                    isset($postData['cat_color']) ? (string) $postData['cat_color'] : CalendarRepository::DEFAULT_COLOR
                );
                $events = $this->repository->getEvents();
                $metrics = CalendarRepository::computeMetrics($events, $categories);
                return [
                    'success' => true,
                    'message' => 'Category added.',
                    'events' => $events,
                    'categories' => $categories,
                    'metrics' => $metrics,
                ];

            case 'delete_category':
                $this->assertPost($method);
                $id = isset($postData['cat_id']) ? (int) $postData['cat_id'] : 0;
                $categories = $this->repository->deleteCategory($id);
                $events = $this->repository->getEvents();
                $metrics = CalendarRepository::computeMetrics($events, $categories);
                return [
                    'success' => true,
                    'message' => 'Category deleted.',
                    'events' => $events,
                    'categories' => $categories,
                    'metrics' => $metrics,
                ];

            default:
                [$events, $categories] = $this->repository->getDataset();
                return [
                    'success' => true,
                    'events' => $events,
                    'categories' => $categories,
                    'metrics' => CalendarRepository::computeMetrics($events, $categories),
                ];
        }
    }

    private function assertPost(string $method): void
    {
        if (strtoupper($method) !== 'POST') {
            throw new RuntimeException('Invalid request method.');
        }
    }
}
