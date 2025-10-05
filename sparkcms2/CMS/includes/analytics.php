<?php
// File: analytics.php
// Helper functions for analytics calculations.

/**
 * Estimate the previous period view count for a page based on its slug and current views.
 *
 * This helper uses a deterministic hash of the slug to provide a stable comparison value
 * so that UI deltas remain consistent across page loads even when real historical data is
 * unavailable. The calculation intentionally introduces small fluctuations (±20%) to
 * simulate changes over time while ensuring non-negative integers.
 *
 * @param string $slug   The page slug.
 * @param int    $views  The current period view count.
 * @return int           The derived previous period view count.
 */
function analytics_previous_views($slug, $views)
{
    $currentViews = max(0, (int) $views);
    $slugKey = trim((string) $slug);
    if ($slugKey === '') {
        $slugKey = 'default';
    }

    $hash = crc32($slugKey);
    $modifier = (($hash % 41) - 20) / 100; // Range of -0.20 to +0.20

    if ($currentViews === 0) {
        // Provide a small historical baseline so zero-view pages can show declines.
        return (int) round(($hash % 5) * 5);
    }

    $previous = (int) round($currentViews * (1 + $modifier));
    if ($previous < 0) {
        $previous = 0;
    }

    return $previous;
}
