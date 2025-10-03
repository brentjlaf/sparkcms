<?php
require_once __DIR__ . '/../../includes/data.php';

class PageService
{
    private string $pagesFile;
    private string $historyFile;
    /** @var callable */
    private $sitemapRegenerator;

    public function __construct(string $pagesFile, string $historyFile, callable $sitemapRegenerator)
    {
        $this->pagesFile = $pagesFile;
        $this->historyFile = $historyFile;
        $this->sitemapRegenerator = $sitemapRegenerator;
    }

    /**
     * Save a page payload to the dataset and regenerate the sitemap.
     *
     * @param array  $payload  Sanitized page data.
     * @param string $username Username responsible for the change.
     *
     * @return array
     */
    public function save(array $payload, string $username): array
    {
        try {
            $pages = $this->loadPages();
            $id = isset($payload['id']) && $payload['id'] !== '' ? (int)$payload['id'] : null;
            $title = trim((string)($payload['title'] ?? ''));
            if ($title === '') {
                return $this->failureResponse('Title is required.', 400);
            }

            $slug = $this->normalizeSlug($title, (string)($payload['slug'] ?? ''));
            $content = (string)($payload['content'] ?? '');
            $published = !empty($payload['published']);
            $templateRaw = (string)($payload['template'] ?? '');
            $template = $templateRaw !== '' ? $templateRaw : 'page.php';
            $metaTitle = (string)($payload['meta_title'] ?? '');
            $metaDescription = (string)($payload['meta_description'] ?? '');
            $canonicalUrl = (string)($payload['canonical_url'] ?? '');
            $ogTitle = (string)($payload['og_title'] ?? '');
            $ogDescription = (string)($payload['og_description'] ?? '');
            $ogImage = (string)($payload['og_image'] ?? '');
            $access = (string)($payload['access'] ?? 'public');

            $timestamp = time();
            $isUpdate = $id !== null;
            $savedPage = null;
            $oldPage = null;

            if ($isUpdate) {
                foreach ($pages as &$page) {
                    if ((int)$page['id'] === $id) {
                        $oldPage = $page;
                        $page['title'] = $title;
                        $page['slug'] = $slug;
                        $page['content'] = $content;
                        $page['published'] = $published;
                        $page['template'] = $template;
                        $page['meta_title'] = $metaTitle;
                        $page['meta_description'] = $metaDescription;
                        $page['canonical_url'] = $canonicalUrl;
                        $page['og_title'] = $ogTitle;
                        $page['og_description'] = $ogDescription;
                        $page['og_image'] = $ogImage;
                        $page['access'] = $access;
                        $page['last_modified'] = $timestamp;
                        $savedPage = $page;
                        break;
                    }
                }
                unset($page);

                if ($savedPage === null) {
                    return $this->failureResponse('Page not found.', 404);
                }
            } else {
                $id = $this->nextId($pages);
                $savedPage = [
                    'id' => $id,
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
                ];
                $pages[] = $savedPage;
            }

            if (!write_json_file($this->pagesFile, $pages)) {
                throw new RuntimeException('Unable to persist pages dataset.');
            }

            [$action, $details] = $this->buildHistoryDetails($oldPage, $savedPage, $template, $published, $access);
            $pageHistoryEntry = [
                'time' => $timestamp,
                'user' => $username !== '' ? $username : 'Unknown',
                'action' => $action,
                'details' => $details,
                'context' => 'page',
                'page_id' => $id,
            ];

            $historyData = read_json_file($this->historyFile);
            if (!is_array($historyData)) {
                $historyData = [];
            }
            if (!isset($historyData[$id]) || !is_array($historyData[$id])) {
                $historyData[$id] = [];
            }
            $historyData[$id][] = $pageHistoryEntry;
            $historyData[$id] = array_slice($historyData[$id], -20);

            try {
                $sitemapResult = call_user_func($this->sitemapRegenerator);
            } catch (Throwable $exception) {
                $sitemapResult = [
                    'success' => false,
                    'message' => 'Failed to regenerate sitemap.',
                    'error' => $exception->getMessage(),
                ];
            }

            $systemHistoryEntry = $this->buildSystemHistoryEntry($title, $slug, $id, $sitemapResult);
            if (!isset($historyData['__system__']) || !is_array($historyData['__system__'])) {
                $historyData['__system__'] = [];
            }
            $historyData['__system__'][] = $systemHistoryEntry;
            $historyData['__system__'] = array_slice($historyData['__system__'], -50);

            if (!write_json_file($this->historyFile, $historyData)) {
                throw new RuntimeException('Unable to persist history dataset.');
            }

            if (($sitemapResult['success'] ?? false) !== true) {
                return $this->failureResponse(
                    $sitemapResult['message'] ?? 'Failed to regenerate sitemap.',
                    500,
                    [
                        'page' => $savedPage,
                        'historyEntry' => $pageHistoryEntry,
                        'systemHistoryEntry' => $systemHistoryEntry,
                        'sitemap' => $sitemapResult,
                    ]
                );
            }

            return [
                'success' => true,
                'status' => $isUpdate ? 200 : 201,
                'message' => $isUpdate ? 'Page updated successfully.' : 'Page created successfully.',
                'page' => $savedPage,
                'historyEntry' => $pageHistoryEntry,
                'systemHistoryEntry' => $systemHistoryEntry,
                'sitemap' => $sitemapResult,
            ];
        } catch (Throwable $exception) {
            return $this->failureResponse(
                'Unable to save page.',
                500,
                ['error' => $exception->getMessage()]
            );
        }
    }

    private function loadPages(): array
    {
        $pages = read_json_file($this->pagesFile);
        return is_array($pages) ? $pages : [];
    }

    private function nextId(array $pages): int
    {
        $max = 0;
        foreach ($pages as $page) {
            if (isset($page['id']) && (int)$page['id'] > $max) {
                $max = (int)$page['id'];
            }
        }
        return $max + 1;
    }

    private function normalizeSlug(string $title, string $slug): string
    {
        $slug = trim($slug);
        if ($slug === '') {
            $slug = $title;
        }
        return $this->slugify($slug);
    }

    private function slugify(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        $text = trim($text, '-');
        return $text !== '' ? $text : 'page';
    }

    private function buildHistoryDetails(?array $oldPage, array $newPage, string $template, bool $published, string $access): array
    {
        if ($oldPage === null) {
            return [
                'created page with template ' . $template,
                [
                    'Initial template: ' . $template,
                    'Visibility: ' . ($published ? 'Published' : 'Unpublished'),
                    'Access: ' . $access,
                ],
            ];
        }

        $changes = [];
        $details = [];

        if (($oldPage['title'] ?? '') !== ($newPage['title'] ?? '')) {
            $details[] = 'Title: "' . ($oldPage['title'] ?? '') . '" → "' . ($newPage['title'] ?? '') . '"';
        }
        if (($oldPage['slug'] ?? '') !== ($newPage['slug'] ?? '')) {
            $details[] = 'Slug: ' . ($oldPage['slug'] ?? '') . ' → ' . ($newPage['slug'] ?? '');
        }
        if (($oldPage['template'] ?? '') !== ($newPage['template'] ?? '')) {
            $details[] = 'Template: ' . ($oldPage['template'] ?? '') . ' → ' . ($newPage['template'] ?? '');
            $changes[] = 'Changed template';
        }
        if (!empty($oldPage['published']) !== $published) {
            $details[] = 'Visibility: ' . (!empty($oldPage['published']) ? 'Published' : 'Unpublished') . ' → ' . ($published ? 'Published' : 'Unpublished');
            $changes[] = $published ? 'Published page' : 'Unpublished page';
        }
        if (($oldPage['meta_title'] ?? '') !== ($newPage['meta_title'] ?? '')) {
            $details[] = 'Meta title updated';
        }
        if (($oldPage['meta_description'] ?? '') !== ($newPage['meta_description'] ?? '')) {
            $details[] = 'Meta description updated';
        }
        if (($oldPage['canonical_url'] ?? '') !== ($newPage['canonical_url'] ?? '')) {
            $details[] = 'Canonical URL: ' . (($oldPage['canonical_url'] ?? '') !== '' ? ($oldPage['canonical_url'] ?? '') : 'none') . ' → ' . (($newPage['canonical_url'] ?? '') !== '' ? ($newPage['canonical_url'] ?? '') : 'none');
        }
        if (($oldPage['og_title'] ?? '') !== ($newPage['og_title'] ?? '')) {
            $details[] = 'OG title: "' . ($oldPage['og_title'] ?? '') . '" → "' . ($newPage['og_title'] ?? '') . '"';
        }
        if (($oldPage['og_description'] ?? '') !== ($newPage['og_description'] ?? '')) {
            $details[] = 'OG description updated';
        }
        if (($oldPage['og_image'] ?? '') !== ($newPage['og_image'] ?? '')) {
            $details[] = 'OG image: ' . (($oldPage['og_image'] ?? '') !== '' ? ($oldPage['og_image'] ?? '') : 'none') . ' → ' . (($newPage['og_image'] ?? '') !== '' ? ($newPage['og_image'] ?? '') : 'none');
        }
        if (($oldPage['access'] ?? 'public') !== ($newPage['access'] ?? 'public')) {
            $details[] = 'Access: ' . ($oldPage['access'] ?? 'public') . ' → ' . ($newPage['access'] ?? 'public');
        }

        if (!$changes) {
            $changes[] = 'Updated page settings';
        }
        if (!$details) {
            $details[] = 'Saved without changing any settings.';
        }

        return [implode('; ', $changes), $details];
    }

    private function buildSystemHistoryEntry(string $title, string $slug, int $pageId, array $sitemapResult): array
    {
        $success = ($sitemapResult['success'] ?? false) === true;
        $details = [
            ($success ? 'Automatic sitemap refresh after updating ' : 'Failed sitemap refresh while updating ') . '"' . $title . '" (' . $slug . ')',
        ];
        if (!$success && isset($sitemapResult['error'])) {
            $details[] = 'Error: ' . $sitemapResult['error'];
        }

        return [
            'time' => time(),
            'user' => '',
            'action' => $success ? 'Regenerated sitemap' : 'Sitemap regeneration failed',
            'details' => $details,
            'context' => 'system',
            'meta' => [
                'trigger' => 'sitemap_regeneration',
                'page_id' => $pageId,
                'status' => $success ? 'success' : 'error',
            ],
            'page_title' => 'CMS Backend',
        ];
    }

    private function failureResponse(string $message, int $status, array $extra = []): array
    {
        return array_merge(
            [
                'success' => false,
                'status' => $status,
                'message' => $message,
            ],
            $extra
        );
    }
}
