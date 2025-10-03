<?php

require_once __DIR__ . '/../../CMS/includes/data.php';

class PageRepositoryException extends RuntimeException
{
    private int $statusCode;

    public function __construct(string $message, int $statusCode = 500, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->statusCode = $statusCode;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}

class PageRepository
{
    private string $pagesFile;
    private string $historyFile;
    private string $draftDirectory;
    private string $blocksDirectory;

    public function __construct(
        ?string $pagesFile = null,
        ?string $historyFile = null,
        ?string $draftDirectory = null,
        ?string $blocksDirectory = null
    ) {
        $this->pagesFile = $pagesFile ?? __DIR__ . '/../../CMS/data/pages.json';
        $this->historyFile = $historyFile ?? __DIR__ . '/../../CMS/data/page_history.json';
        $this->draftDirectory = $draftDirectory ?? __DIR__ . '/../../CMS/data/drafts';
        $this->blocksDirectory = $blocksDirectory ?? __DIR__ . '/../../theme/templates/blocks';
    }

    public function updatePageContent(int $pageId, string $content, string $username = 'Unknown'): array
    {
        $pageId = $this->validatePageId($pageId);
        $pages = $this->readPages();

        $pageIndex = null;
        foreach ($pages as $index => $page) {
            if ((int)($page['id'] ?? 0) === $pageId) {
                $pageIndex = $index;
                break;
            }
        }

        if ($pageIndex === null) {
            throw new PageRepositoryException('Page not found.', 404);
        }

        $previousContent = $pages[$pageIndex]['content'] ?? '';
        $timestamp = time();
        $pages[$pageIndex]['content'] = $content;
        $pages[$pageIndex]['last_modified'] = $timestamp;

        if (!$this->writeJson($this->pagesFile, $pages)) {
            throw new PageRepositoryException('Unable to save page content.', 500);
        }

        $historyEntry = $this->buildHistoryEntry($pageId, $username, $previousContent, $content, $timestamp);
        $this->updateHistory($pageId, $historyEntry);

        return [
            'page' => $pages[$pageIndex],
            'history_entry' => $historyEntry,
            'timestamp' => $timestamp,
            'previous_content' => $previousContent,
        ];
    }

    public function saveDraft(int $pageId, string $content, ?int $timestamp = null): void
    {
        $pageId = $this->validatePageId($pageId);
        $timestamp = $timestamp ?? time();

        if (!is_dir($this->draftDirectory) && !mkdir($this->draftDirectory, 0755, true) && !is_dir($this->draftDirectory)) {
            throw new PageRepositoryException('Unable to create drafts directory.', 500);
        }

        $draftFile = $this->draftPath($pageId);
        $data = [
            'content' => $content,
            'timestamp' => $timestamp,
        ];

        if (!$this->writeJson($draftFile, $data)) {
            throw new PageRepositoryException('Unable to save draft.', 500);
        }
    }

    public function loadDraft(int $pageId): array
    {
        $pageId = $this->validatePageId($pageId);
        $draftFile = $this->draftPath($pageId);

        if (!is_file($draftFile)) {
            return ['content' => '', 'timestamp' => 0];
        }

        $data = read_json_file($draftFile);
        if (!is_array($data)) {
            return ['content' => '', 'timestamp' => 0];
        }

        return [
            'content' => (string)($data['content'] ?? ''),
            'timestamp' => (int)($data['timestamp'] ?? 0),
        ];
    }

    public function deleteDraft(int $pageId): void
    {
        $pageId = $this->validatePageId($pageId);
        $draftFile = $this->draftPath($pageId);
        if (is_file($draftFile)) {
            unlink($draftFile);
        }
    }

    public function getHistory(int $pageId, int $limit = 20): array
    {
        $pageId = $this->validatePageId($pageId);
        $limit = $limit > 0 ? $limit : 20;

        $historyData = get_cached_json($this->historyFile);
        if (!isset($historyData[$pageId]) || !is_array($historyData[$pageId])) {
            return [];
        }

        $entries = array_slice($historyData[$pageId], -$limit);
        return array_values($entries);
    }

    public function listBlocks(): array
    {
        if (!is_dir($this->blocksDirectory)) {
            return [];
        }

        $paths = glob($this->blocksDirectory . '/*.php');
        if ($paths === false) {
            return [];
        }

        $blocks = array_map('basename', $paths);
        sort($blocks, SORT_STRING);
        return $blocks;
    }

    public function loadBlock(string $filename): string
    {
        $filename = trim($filename);
        if ($filename === '') {
            throw new PageRepositoryException('Block filename is required.', 400);
        }

        $basePath = realpath($this->blocksDirectory);
        if ($basePath === false) {
            throw new PageRepositoryException('Blocks directory is unavailable.', 500);
        }

        $resolvedPath = realpath($this->blocksDirectory . '/' . basename($filename));
        if ($resolvedPath === false || strpos($resolvedPath, $basePath) !== 0 || !is_file($resolvedPath)) {
            throw new PageRepositoryException('Block not found.', 404);
        }

        $contents = file_get_contents($resolvedPath);
        if ($contents === false) {
            throw new PageRepositoryException('Unable to read block.', 500);
        }

        return $contents;
    }

    private function validatePageId(int $pageId): int
    {
        if ($pageId <= 0) {
            throw new PageRepositoryException('Invalid page ID.', 400);
        }
        return $pageId;
    }

    private function readPages(): array
    {
        $pages = read_json_file($this->pagesFile);
        if (!is_array($pages)) {
            return [];
        }
        return $pages;
    }

    private function writeJson(string $file, $data): bool
    {
        return write_json_file($file, $data);
    }

    private function draftPath(int $pageId): string
    {
        return rtrim($this->draftDirectory, '/\\') . '/page-' . $pageId . '.json';
    }

    private function buildHistoryEntry(int $pageId, string $username, string $previousContent, string $newContent, int $timestamp): array
    {
        $username = $username !== '' ? $username : 'Unknown';
        $oldWordCount = str_word_count(strip_tags($previousContent));
        $newWordCount = str_word_count(strip_tags($newContent));
        $wordDiff = $newWordCount - $oldWordCount;

        $details = [
            'Word count: ' . $oldWordCount . ' → ' . $newWordCount . ($wordDiff === 0 ? '' : ($wordDiff > 0 ? ' (+' . $wordDiff . ')' : ' (' . $wordDiff . ')')),
        ];
        $details[] = 'Characters: ' . strlen(strip_tags($previousContent)) . ' → ' . strlen(strip_tags($newContent));

        return [
            'time' => $timestamp,
            'user' => $username,
            'action' => 'updated content',
            'details' => $details,
            'context' => 'page',
            'page_id' => $pageId,
        ];
    }

    private function updateHistory(int $pageId, array $entry): void
    {
        $history = read_json_file($this->historyFile);
        if (!is_array($history)) {
            $history = [];
        }

        if (!isset($history[$pageId]) || !is_array($history[$pageId])) {
            $history[$pageId] = [];
        }

        $history[$pageId][] = $entry;
        $history[$pageId] = array_slice($history[$pageId], -20);

        if (!isset($history['__system__']) || !is_array($history['__system__'])) {
            $history['__system__'] = [];
        }

        $history['__system__'][] = $this->buildSystemHistoryEntry($pageId);
        $history['__system__'] = array_slice($history['__system__'], -50);

        if (!$this->writeJson($this->historyFile, $history)) {
            throw new PageRepositoryException('Unable to update history.', 500);
        }
    }

    private function buildSystemHistoryEntry(int $pageId): array
    {
        return [
            'time' => time(),
            'user' => '',
            'action' => 'Regenerated sitemap',
            'details' => [
                'Automatic sitemap refresh after editing content for page ID ' . $pageId,
            ],
            'context' => 'system',
            'meta' => [
                'trigger' => 'sitemap_regeneration',
                'page_id' => $pageId,
            ],
            'page_title' => 'CMS Backend',
        ];
    }
}
