<?php
require_once __DIR__ . '/../../includes/data.php';

/**
 * Regenerate the sitemap using the stored pages dataset.
 *
 * @param string     $pagesFile   Path to the pages JSON dataset.
 * @param string     $sitemapPath Path to the sitemap.xml output file.
 * @param array|null $server      Optional server context (defaults to $_SERVER).
 *
 * @return array{success:bool,message:string,entryCount?:int,generatedAt?:int,generatedAtFormatted?:string,entries?:array,sitemapUrl?:string,generator?:string,error?:string}
 */
function regenerate_sitemap(string $pagesFile, string $sitemapPath, ?array $server = null): array
{
    $server = $server ?? $_SERVER;

    try {
        $pages = read_json_file($pagesFile);
        if (!is_array($pages)) {
            $pages = [];
        }

        $published = array_values(array_filter($pages, static function ($page) {
            return !empty($page['published']);
        }));

        $scheme = 'http';
        if (isset($server['HTTPS']) && $server['HTTPS'] === 'on') {
            $scheme = 'https';
        }

        $host = $server['HTTP_HOST'] ?? 'localhost';
        $scriptName = $server['SCRIPT_NAME'] ?? '/';
        $scriptBase = rtrim(dirname($scriptName), '/');
        if (substr($scriptBase, -4) === '/CMS') {
            $scriptBase = substr($scriptBase, 0, -4);
        }
        $scriptBase = rtrim($scriptBase, '/');
        $baseUrl = rtrim($scheme . '://' . $host . ($scriptBase !== '' ? '/' . ltrim($scriptBase, '/') : ''), '/');

        $entries = [];
        foreach ($published as $page) {
            $slug = ltrim((string)($page['slug'] ?? ''), '/');
            $lastModified = isset($page['last_modified']) ? (int)$page['last_modified'] : time();
            $lastmodDate = date('Y-m-d', $lastModified);

            $entries[] = [
                'title' => (string)($page['title'] ?? ''),
                'slug' => $slug,
                'url' => $baseUrl . '/' . $slug,
                'lastmodHuman' => date('F j, Y', $lastModified),
                'lastmod' => $lastmodDate,
            ];
        }

        $domAvailable = class_exists('DOMDocument');

        if ($domAvailable) {
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

            if ($dom->save($sitemapPath) === false) {
                throw new RuntimeException('Unable to write sitemap file.');
            }
        } else {
            $lines = [
                '<?xml version="1.0" encoding="UTF-8"?>',
                '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
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

            if (file_put_contents($sitemapPath, $xml) === false) {
                throw new RuntimeException('Unable to write sitemap file.');
            }
        }

        $generatedAt = file_exists($sitemapPath) ? (filemtime($sitemapPath) ?: time()) : time();

        return [
            'success' => true,
            'message' => 'Sitemap regenerated successfully.',
            'entryCount' => count($entries),
            'generatedAt' => $generatedAt,
            'generatedAtFormatted' => date('F j, Y g:i a', $generatedAt),
            'entries' => $entries,
            'sitemapUrl' => $baseUrl . '/sitemap.xml',
            'generator' => $domAvailable ? 'dom' : 'simple',
        ];
    } catch (Throwable $exception) {
        return [
            'success' => false,
            'message' => 'Failed to regenerate sitemap.',
            'error' => $exception->getMessage(),
        ];
    }
}
