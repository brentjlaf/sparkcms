<?php

class SitemapGenerator
{
    private string $sitemapPath;

    public function __construct(?string $sitemapPath = null)
    {
        $this->sitemapPath = $sitemapPath ?? __DIR__ . '/../../../sitemap.xml';
    }

    /**
     * @param array<int|string, array<string, mixed>> $pages
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public function generate(array $pages, array $options = []): array
    {
        $baseUrl = isset($options['baseUrl']) && is_string($options['baseUrl'])
            ? rtrim($options['baseUrl'], '/')
            : rtrim($this->determineBaseUrl(), '/');
        if ($baseUrl === '') {
            $baseUrl = 'http://localhost';
        }

        $published = array_values(array_filter($pages, function ($page) {
            return is_array($page) && !empty($page['published']);
        }));

        $entries = [];
        foreach ($published as $page) {
            $slug = '';
            if (isset($page['slug'])) {
                $slug = ltrim((string)$page['slug'], '/');
            }
            $url = $slug === '' ? $baseUrl . '/' : $baseUrl . '/' . $slug;
            $lastModified = isset($page['last_modified']) ? (int)$page['last_modified'] : time();
            $lastmodDate = date('Y-m-d', $lastModified);

            $entries[] = [
                'title' => isset($page['title']) ? (string)$page['title'] : '',
                'slug' => $slug,
                'url' => $url,
                'lastmodHuman' => date('F j, Y', $lastModified),
                'lastmod' => $lastmodDate,
            ];
        }

        $useDom = $this->shouldUseDom($options);

        $this->ensureDirectoryExists();

        if ($useDom) {
            $this->writeWithDom($entries);
        } else {
            $this->writeManually($entries);
        }

        $generatedAt = @filemtime($this->sitemapPath) ?: time();

        return [
            'success' => true,
            'message' => 'Sitemap regenerated successfully.',
            'entryCount' => count($entries),
            'generatedAt' => $generatedAt,
            'generatedAtFormatted' => date('F j, Y g:i a', $generatedAt),
            'entries' => $entries,
            'sitemapUrl' => $baseUrl . '/sitemap.xml',
            'sitemapPath' => $this->sitemapPath,
            'generator' => $useDom ? 'dom' : 'simple',
        ];
    }

    private function determineBaseUrl(): string
    {
        $https = $_SERVER['HTTPS'] ?? '';
        $scheme = ($https && strtolower((string)$https) !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptName = isset($_SERVER['SCRIPT_NAME']) ? (string)$_SERVER['SCRIPT_NAME'] : '';
        $scriptBase = str_replace('\\', '/', dirname($scriptName));
        $scriptBase = rtrim($scriptBase, '/');
        if (substr($scriptBase, -4) === '/CMS') {
            $scriptBase = substr($scriptBase, 0, -4);
        }
        $scriptBase = rtrim((string)$scriptBase, '/');

        return $scheme . '://' . $host . $scriptBase;
    }

    /**
     * @param array<int, array<string, string>> $entries
     */
    private function writeWithDom(array $entries): void
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $urlset = $dom->createElement('urlset');
        $urlset->setAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        foreach ($entries as $entry) {
            $url = $dom->createElement('url');
            $loc = $dom->createElement('loc', $entry['url']);
            $lastmod = $dom->createElement('lastmod', $entry['lastmod']);

            $url->appendChild($loc);
            $url->appendChild($lastmod);
            $urlset->appendChild($url);
        }

        $dom->appendChild($urlset);
        $dom->formatOutput = true;

        if ($dom->save($this->sitemapPath) === false) {
            throw new RuntimeException('Unable to write sitemap file.');
        }
    }

    /**
     * @param array<int, array<string, string>> $entries
     */
    private function writeManually(array $entries): void
    {
        $lines = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
        ];

        foreach ($entries as $entry) {
            $loc = htmlspecialchars($entry['url'], ENT_XML1);
            $lastmod = htmlspecialchars($entry['lastmod'], ENT_XML1);
            $lines[] = '  <url>';
            $lines[] = '    <loc>' . $loc . '</loc>';
            $lines[] = '    <lastmod>' . $lastmod . '</lastmod>';
            $lines[] = '  </url>';
        }

        $lines[] = '</urlset>';
        $xml = implode("\n", $lines);

        if (file_put_contents($this->sitemapPath, $xml) === false) {
            throw new RuntimeException('Unable to write sitemap file.');
        }
    }

    private function ensureDirectoryExists(): void
    {
        $directory = dirname($this->sitemapPath);
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0777, true) && !is_dir($directory)) {
                throw new RuntimeException('Unable to create sitemap directory.');
            }
        }
    }

    /**
     * @param array<string, mixed> $options
     */
    private function shouldUseDom(array $options): bool
    {
        if (isset($options['useDom'])) {
            return (bool)$options['useDom'] && class_exists('DOMDocument');
        }

        return class_exists('DOMDocument');
    }
}
