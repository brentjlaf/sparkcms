<?php
// File: SitemapRegenerator.php

require_once __DIR__ . '/SitemapGenerator.php';

/**
 * Regenerate the sitemap and return a structured result.
 *
 * @param array<int|string, array<string, mixed>> $pages
 * @param string|null $sitemapPath
 * @return array<string, mixed>
 */
function regenerate_sitemap(array $pages, ?string $sitemapPath = null): array
{
    try {
        $generator = new SitemapGenerator($sitemapPath);
        $result = $generator->generate($pages);
        if (!isset($result['success'])) {
            $result['success'] = true;
        }

        return $result;
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
}
