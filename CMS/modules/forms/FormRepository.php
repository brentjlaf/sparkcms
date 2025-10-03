<?php
// File: FormRepository.php
// Repository for managing forms and their submissions, including timestamp utilities.

require_once __DIR__ . '/../../includes/data.php';

class FormRepository
{
    /** @var string */
    private $formsFile;

    /** @var string */
    private $submissionsFile;

    /** @var array<int,array<string,mixed>>|null */
    private $formsCache = null;

    /** @var array<int,array<string,mixed>>|null */
    private $submissionsCache = null;

    /**
     * @param string|null $formsFile
     * @param string|null $submissionsFile
     */
    public function __construct($formsFile = null, $submissionsFile = null)
    {
        if ($formsFile === null) {
            $formsFile = __DIR__ . '/../../data/forms.json';
        }
        if ($submissionsFile === null) {
            $submissionsFile = __DIR__ . '/../../data/form_submissions.json';
        }

        $this->formsFile = $formsFile;
        $this->submissionsFile = $submissionsFile;

        $this->ensureStorageFile($this->formsFile);
        $this->ensureStorageFile($this->submissionsFile);
    }

    /**
     * Retrieve all stored forms.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getForms(): array
    {
        if ($this->formsCache === null) {
            $this->formsCache = $this->loadCollection($this->formsFile);
        }

        return $this->formsCache;
    }

    /**
     * Retrieve all stored submissions, optionally filtered by form ID.
     *
     * @param int|null $formId
     * @return array<int,array<string,mixed>>
     */
    public function getSubmissions(?int $formId = null): array
    {
        if ($this->submissionsCache === null) {
            $this->submissionsCache = $this->loadCollection($this->submissionsFile);
        }

        if ($formId === null) {
            return $this->submissionsCache;
        }

        $formId = (int) $formId;
        if ($formId <= 0) {
            return [];
        }

        return array_values(array_filter(
            $this->submissionsCache,
            static function ($submission) use ($formId) {
                return is_array($submission) && isset($submission['form_id']) && (int) $submission['form_id'] === $formId;
            }
        ));
    }

    /**
     * Persist the provided forms dataset.
     *
     * @param array<int,array<string,mixed>> $forms
     * @return void
     */
    public function saveForms(array $forms): void
    {
        $normalized = $this->filterArrayCollection($forms);
        write_json_file($this->formsFile, $normalized);
        $this->formsCache = $normalized;
    }

    /**
     * Persist the provided submissions dataset.
     *
     * @param array<int,array<string,mixed>> $submissions
     * @return void
     */
    public function saveSubmissions(array $submissions): void
    {
        $normalized = $this->filterArrayCollection($submissions);
        write_json_file($this->submissionsFile, $normalized);
        $this->submissionsCache = $normalized;
    }

    /**
     * Retrieve submissions normalized for API responses.
     *
     * @param int|null $formId
     * @return array<int,array<string,mixed>>
     */
    public function getNormalizedSubmissions(?int $formId = null): array
    {
        $submissions = $this->getSubmissions($formId);

        usort($submissions, static function ($a, $b) {
            $left = is_array($a) ? self::extractTimestamp($a) : 0;
            $right = is_array($b) ? self::extractTimestamp($b) : 0;
            return $right <=> $left;
        });

        $normalized = [];
        foreach ($submissions as $submission) {
            if (!is_array($submission)) {
                continue;
            }
            $normalized[] = $this->normalizeSubmissionRecord($submission);
        }

        return $normalized;
    }

    /**
     * Extract the best timestamp candidate from a submission-like entry.
     *
     * @param array<string,mixed> $entry
     * @return int
     */
    public static function extractTimestamp(array $entry): int
    {
        $candidates = ['submitted_at', 'created_at', 'timestamp'];
        foreach ($candidates as $key) {
            if (!array_key_exists($key, $entry)) {
                continue;
            }
            $value = $entry[$key];
            if ($value === null || $value === '') {
                continue;
            }

            if (is_numeric($value)) {
                $numeric = (float) $value;
                if ($numeric <= 0) {
                    continue;
                }
                if ($numeric < 1_000_000_000_000) {
                    return (int) round($numeric);
                }
                return (int) round($numeric / 1000);
            }

            $time = strtotime((string) $value);
            if ($time !== false) {
                return $time;
            }
        }

        return 0;
    }

    /**
     * Normalize a persisted submission for API consumption.
     *
     * @param array<string,mixed> $submission
     * @return array<string,mixed>
     */
    private function normalizeSubmissionRecord(array $submission): array
    {
        $normalized = [
            'id' => $submission['id'] ?? null,
            'form_id' => isset($submission['form_id']) ? (int) $submission['form_id'] : null,
        ];

        $data = $submission['data'] ?? [];
        if (!is_array($data)) {
            $data = [];
        }
        $normalized['data'] = (object) $data;

        $meta = $submission['meta'] ?? [];
        if (!is_array($meta)) {
            $meta = [];
        }
        if (isset($submission['ip']) && !isset($meta['ip'])) {
            $meta['ip'] = $submission['ip'];
        }
        $normalized['meta'] = (object) $meta;

        $timestamp = self::extractTimestamp($submission);
        if ($timestamp > 0) {
            $normalized['submitted_at'] = date(DATE_ATOM, $timestamp);
        } else {
            $fallback = null;
            foreach (['submitted_at', 'created_at', 'timestamp'] as $key) {
                if (!empty($submission[$key])) {
                    $fallback = (string) $submission[$key];
                    break;
                }
            }
            $normalized['submitted_at'] = $fallback;
        }

        if (isset($submission['source'])) {
            $normalized['source'] = $submission['source'];
        }

        return $normalized;
    }

    /**
     * Ensure the storage file and parent directory exist.
     *
     * @param string $path
     * @return void
     */
    private function ensureStorageFile(string $path): void
    {
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
        if (!file_exists($path)) {
            write_json_file($path, []);
        }
    }

    /**
     * Load a collection of array entries from disk.
     *
     * @param string $path
     * @return array<int,array<string,mixed>>
     */
    private function loadCollection(string $path): array
    {
        $data = read_json_file($path);
        if (!is_array($data)) {
            return [];
        }

        return $this->filterArrayCollection($data);
    }

    /**
     * Filter a mixed dataset down to array entries only.
     *
     * @param array<int,mixed> $items
     * @return array<int,array<string,mixed>>
     */
    private function filterArrayCollection(array $items): array
    {
        $filtered = [];
        foreach ($items as $item) {
            if (is_array($item)) {
                $filtered[] = $item;
            }
        }

        return $filtered;
    }
}
