<?php
// File: PageService.php

require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_once __DIR__ . '/../sitemap/SitemapRegenerator.php';

class PageService
{
    private string $pagesFile;
    private string $historyFile;
    private string $sitemapPath;

    /** @var callable */
    private $timeProvider;

    /** @var callable */
    private $sitemapRegenerator;

    public function __construct(
        ?string $pagesFile = null,
        ?string $historyFile = null,
        ?string $sitemapPath = null,
        ?callable $timeProvider = null,
        ?callable $sitemapRegenerator = null
    ) {
        $this->pagesFile = $pagesFile ?? __DIR__ . '/../../data/pages.json';
        $this->historyFile = $historyFile ?? __DIR__ . '/../../data/page_history.json';
        $this->sitemapPath = $sitemapPath ?? __DIR__ . '/../../../sitemap.xml';
        $this->timeProvider = $timeProvider ?? static function (): int {
            return time();
        };
        $this->sitemapRegenerator = $sitemapRegenerator ?? function (array $pages): array {
            return regenerate_sitemap($pages, $this->sitemapPath);
        };
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $session
     *
     * @return array<string, mixed>
     */
    public function save(array $input, array $session = []): array
    {
        $title = sanitize_text((string)($input['title'] ?? ''));
        if ($title === '') {
            return [
                'success' => false,
                'status' => 400,
                'message' => 'Title is required.',
            ];
        }

        $id = isset($input['id']) && $input['id'] !== '' ? (int)$input['id'] : null;
        $slugInput = sanitize_text((string)($input['slug'] ?? ''));
        if ($slugInput === '') {
            $slugInput = $title;
        }
        $slug = $this->slugify($slugInput);

        $content = isset($input['content']) ? (string)$input['content'] : '';
        $content = $this->sanitizeContent($content);

        $published = isset($input['published']) ? (bool)$input['published'] : false;
        $template = sanitize_text((string)($input['template'] ?? ''));
        if ($template === '') {
            $template = 'page.php';
        }

        $metaTitle = sanitize_text((string)($input['meta_title'] ?? ''));
        $metaDescription = sanitize_text((string)($input['meta_description'] ?? ''));
        $canonicalUrl = sanitize_url((string)($input['canonical_url'] ?? ''));
        $ogTitle = sanitize_text((string)($input['og_title'] ?? ''));
        $ogDescription = sanitize_text((string)($input['og_description'] ?? ''));
        $ogImage = sanitize_url((string)($input['og_image'] ?? ''));
        $access = sanitize_text((string)($input['access'] ?? 'public'));
        if ($access === '') {
            $access = 'public';
        }

        $pages = $this->loadPages();

        foreach ($pages as $page) {
            if (!is_array($page)) {
                continue;
            }

            $existingSlug = isset($page['slug']) ? (string)$page['slug'] : '';
            if ($existingSlug === '') {
                continue;
            }

            if ($existingSlug !== $slug) {
                continue;
            }

            $existingId = isset($page['id']) ? (int)$page['id'] : 0;
            if ($id === null || $existingId !== $id) {
                return [
                    'success' => false,
                    'status' => 409,
                    'message' => 'A page with the slug "' . $slug . '" already exists.',
                ];
            }
        }

        $timestamp = $this->currentTime();

        if ($id !== null) {
            $updateResult = $this->updateExistingPage(
                $pages,
                $id,
                [
                    'title' => $title,
                    'slug' => $slug,
                    'content' => $content,
                    'published' => $published,
                    'template' => $template,
                    'meta_title' => $metaTitle,
                    'meta_description' => $metaDescription,
                    'canonical_url' => $canonicalUrl,
                    'og_title' => $ogTitle,
                    'og_description' => $ogDescription,
                    'og_image' => $ogImage,
                    'access' => $access,
                    'last_modified' => $timestamp,
                ]
            );

            if ($updateResult === null) {
                return [
                    'success' => false,
                    'status' => 404,
                    'message' => 'Page not found.',
                ];
            }

            [$pages, $savedPage, $historyAction, $historyDetails] = $updateResult;
            $message = 'Page updated.';
        } else {
            $createResult = $this->createPage($pages, [
                'title' => $title,
                'slug' => $slug,
                'content' => $content,
                'published' => $published,
                'template' => $template,
                'meta_title' => $metaTitle,
                'meta_description' => $metaDescription,
                'canonical_url' => $canonicalUrl,
                'og_title' => $ogTitle,
                'og_description' => $ogDescription,
                'og_image' => $ogImage,
                'access' => $access,
                'views' => 0,
                'last_modified' => $timestamp,
            ]);

            [$pages, $savedPage, $historyAction, $historyDetails] = $createResult;
            $id = (int)$savedPage['id'];
            $message = 'Page created.';
        }

        if (!write_json_file($this->pagesFile, $pages)) {
            return [
                'success' => false,
                'status' => 500,
                'message' => 'Unable to save pages file.',
            ];
        }

        try {
            $historyEntry = $this->logHistory($id, $historyAction, $historyDetails, $session, $timestamp);
        } catch (RuntimeException $exception) {
            return [
                'success' => false,
                'status' => 500,
                'message' => $exception->getMessage(),
            ];
        }

        $sitemapResult = $this->callSitemapRegenerator($pages);
        if ($sitemapResult['success'] !== true) {
            return [
                'success' => false,
                'status' => 500,
                'message' => 'Failed to regenerate sitemap.',
                'page' => $savedPage,
                'historyEntry' => $historyEntry,
                'sitemap' => $sitemapResult,
            ];
        }

        try {
            $this->logSystemSitemapRegeneration($savedPage, $id);
        } catch (RuntimeException $exception) {
            return [
                'success' => false,
                'status' => 500,
                'message' => $exception->getMessage(),
                'page' => $savedPage,
                'historyEntry' => $historyEntry,
                'sitemap' => $sitemapResult,
            ];
        }

        return [
            'success' => true,
            'status' => 200,
            'message' => $message,
            'page' => $savedPage,
            'historyEntry' => $historyEntry,
            'sitemap' => $sitemapResult,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $pages
     * @param int $id
     * @param array<string, mixed> $updates
     *
     * @return array<int, mixed>|null
     */
    private function updateExistingPage(array $pages, int $id, array $updates): ?array
    {
        foreach ($pages as $index => $page) {
            if (!is_array($page)) {
                continue;
            }
            if ((int)($page['id'] ?? 0) === $id) {
                $old = $page;
                $updated = $page;
                foreach ($updates as $key => $value) {
                    $updated[$key] = $value;
                }

                $pages[$index] = $updated;

                [$action, $details] = $this->buildUpdateHistory($old, $updated);

                return [$pages, $updated, $action, $details];
            }
        }

        return null;
    }

    /**
     * @param array<int, array<string, mixed>> $pages
     * @param array<string, mixed> $data
     *
     * @return array<int, mixed>
     */
    private function createPage(array $pages, array $data): array
    {
        $nextId = 1;
        foreach ($pages as $page) {
            if (!is_array($page)) {
                continue;
            }
            $pageId = isset($page['id']) ? (int)$page['id'] : 0;
            if ($pageId >= $nextId) {
                $nextId = $pageId + 1;
            }
        }

        $data['id'] = $nextId;
        $pages[] = $data;

        [$action, $details] = $this->buildCreateHistory($data);

        return [$pages, $data, $action, $details];
    }

    /**
     * @param array<string, mixed> $page
     * @return array{0:string,1:array<int,string>}
     */
    private function buildCreateHistory(array $page): array
    {
        $details = [
            'Initial template: ' . ($page['template'] ?? ''),
            'Visibility: ' . (!empty($page['published']) ? 'Published' : 'Unpublished'),
            'Access: ' . ($page['access'] ?? 'public'),
        ];

        $action = 'created page with template ' . ($page['template'] ?? '');

        return [$action, $details];
    }

    /**
     * @param array<string, mixed> $old
     * @param array<string, mixed> $new
     * @return array{0:string,1:array<int,string>}
     */
    private function buildUpdateHistory(array $old, array $new): array
    {
        $changes = [];
        $details = [];

        if (($old['title'] ?? '') !== ($new['title'] ?? '')) {
            $details[] = 'Title: "' . ($old['title'] ?? '') . '" → "' . ($new['title'] ?? '') . '"';
        }
        if (($old['slug'] ?? '') !== ($new['slug'] ?? '')) {
            $details[] = 'Slug: ' . ($old['slug'] ?? '') . ' → ' . ($new['slug'] ?? '');
        }
        if (($old['template'] ?? '') !== ($new['template'] ?? '')) {
            $details[] = 'Template: ' . ($old['template'] ?? '') . ' → ' . ($new['template'] ?? '');
            $changes[] = 'Changed template';
        }
        if (!empty($old['published']) !== !empty($new['published'])) {
            $details[] = 'Visibility: ' . (!empty($old['published']) ? 'Published' : 'Unpublished') . ' → ' . (!empty($new['published']) ? 'Published' : 'Unpublished');
            $changes[] = !empty($new['published']) ? 'Published page' : 'Unpublished page';
        }
        if (($old['meta_title'] ?? '') !== ($new['meta_title'] ?? '')) {
            $details[] = 'Meta title updated';
        }
        if (($old['meta_description'] ?? '') !== ($new['meta_description'] ?? '')) {
            $details[] = 'Meta description updated';
        }
        if (($old['canonical_url'] ?? '') !== ($new['canonical_url'] ?? '')) {
            $details[] = 'Canonical URL: ' . (($old['canonical_url'] ?? '') !== '' ? ($old['canonical_url'] ?? '') : 'none') . ' → ' . (($new['canonical_url'] ?? '') !== '' ? ($new['canonical_url'] ?? '') : 'none');
        }
        if (($old['og_title'] ?? '') !== ($new['og_title'] ?? '')) {
            $details[] = 'OG title: "' . ($old['og_title'] ?? '') . '" → "' . ($new['og_title'] ?? '') . '"';
        }
        if (($old['og_description'] ?? '') !== ($new['og_description'] ?? '')) {
            $details[] = 'OG description updated';
        }
        if (($old['og_image'] ?? '') !== ($new['og_image'] ?? '')) {
            $details[] = 'OG image: ' . (($old['og_image'] ?? '') !== '' ? ($old['og_image'] ?? '') : 'none') . ' → ' . (($new['og_image'] ?? '') !== '' ? ($new['og_image'] ?? '') : 'none');
        }
        if (($old['access'] ?? '') !== ($new['access'] ?? '')) {
            $details[] = 'Access: ' . ($old['access'] ?? '') . ' → ' . ($new['access'] ?? '');
        }

        if (!$changes) {
            $changes[] = 'Updated page settings';
        }
        if (!$details) {
            $details[] = 'Saved without changing any settings.';
        }

        $action = implode('; ', $changes);

        return [$action, $details];
    }

    private function sanitizeContent(string $content): string
    {
        $content = trim($content);
        return (string)preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $content);
    }

    private function slugify(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        $text = trim((string)$text, '-');

        return $text !== '' ? $text : 'page';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadPages(): array
    {
        $pages = read_json_file($this->pagesFile);
        return is_array($pages) ? $pages : [];
    }

    private function currentTime(): int
    {
        return (int)call_user_func($this->timeProvider);
    }

    /**
     * @param int $pageId
     * @param array<string, mixed> $page
     * @param string $action
     * @param array<int, string> $details
     * @param array<string, mixed> $session
     * @param int $timestamp
     * @return array<string, mixed>
     */
    private function logHistory(int $pageId, string $action, array $details, array $session, int $timestamp): array
    {
        $historyData = read_json_file($this->historyFile);
        if (!is_array($historyData)) {
            $historyData = [];
        }

        if (!isset($historyData[$pageId]) || !is_array($historyData[$pageId])) {
            $historyData[$pageId] = [];
        }

        $user = 'Unknown';
        if (isset($session['user']) && is_array($session['user']) && isset($session['user']['username'])) {
            $userValue = $session['user']['username'];
            if (is_string($userValue) && $userValue !== '') {
                $user = $userValue;
            }
        }

        $entry = [
            'time' => $timestamp,
            'user' => $user,
            'action' => $action,
            'details' => $details,
            'context' => 'page',
            'page_id' => $pageId,
        ];

        $historyData[$pageId][] = $entry;
        $historyData[$pageId] = array_slice($historyData[$pageId], -20);

        if (!write_json_file($this->historyFile, $historyData)) {
            throw new RuntimeException('Unable to save page history.');
        }

        return $entry;
    }

    /**
     * @param array<string, mixed> $page
     */
    private function logSystemSitemapRegeneration(array $page, int $pageId): void
    {
        $historyData = read_json_file($this->historyFile);
        if (!is_array($historyData)) {
            $historyData = [];
        }

        if (!isset($historyData['__system__']) || !is_array($historyData['__system__'])) {
            $historyData['__system__'] = [];
        }

        $historyData['__system__'][] = [
            'time' => $this->currentTime(),
            'user' => '',
            'action' => 'Regenerated sitemap',
            'details' => [
                'Automatic sitemap refresh after updating "' . ($page['title'] ?? '') . '" (' . ($page['slug'] ?? '') . ')',
            ],
            'context' => 'system',
            'meta' => [
                'trigger' => 'sitemap_regeneration',
                'page_id' => $pageId,
            ],
            'page_title' => 'CMS Backend',
        ];
        $historyData['__system__'] = array_slice($historyData['__system__'], -50);

        if (!write_json_file($this->historyFile, $historyData)) {
            throw new RuntimeException('Unable to save page history.');
        }
    }

    /**
     * @param array<int, array<string, mixed>> $pages
     * @return array<string, mixed>
     */
    private function callSitemapRegenerator(array $pages): array
    {
        try {
            $result = call_user_func($this->sitemapRegenerator, $pages);
        } catch (Throwable $exception) {
            $message = $exception->getMessage();
            if (!is_string($message) || $message === '') {
                $message = 'Failed to regenerate sitemap.';
            }
            return [
                'success' => false,
                'message' => $message,
            ];
        }

        if (!is_array($result)) {
            return [
                'success' => false,
                'message' => 'Sitemap regenerator returned an invalid result.',
            ];
        }

        if (!isset($result['success'])) {
            $result['success'] = false;
        }

        if ($result['success'] !== true) {
            if (!isset($result['message']) || !is_string($result['message']) || $result['message'] === '') {
                $result['message'] = 'Failed to regenerate sitemap.';
            }
        }

        return $result;
    }
}
