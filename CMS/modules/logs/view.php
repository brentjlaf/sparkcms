<?php
// File: view.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_login();

$pagesFile = __DIR__ . '/../../data/pages.json';
$pages = read_json_file($pagesFile);
$pageLookup = [];
foreach ($pages as $p) {
    $pageLookup[$p['id']] = $p['title'];
}

$historyFile = __DIR__ . '/../../data/page_history.json';
$historyData = read_json_file($historyFile);

function normalize_action_label(?string $action): string {
    $label = trim((string)($action ?? ''));
    return $label !== '' ? $label : 'Updated content';
}

function slugify_action_label(string $label): string {
    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $label));
    $slug = trim($slug ?? '', '-');
    return $slug !== '' ? $slug : 'unknown';
}

function describe_time_ago(?int $timestamp, bool $forHero = false): string {
    if (!$timestamp) {
        return $forHero ? 'No activity yet' : 'Unknown time';
    }

    $now = time();
    $diff = $now - $timestamp;

    if ($diff < 0) {
        return 'Scheduled update';
    }

    if ($diff < 60) {
        return 'Just now';
    }

    if ($diff < 3600) {
        $minutes = (int) floor($diff / 60);
        return $minutes . ' min' . ($minutes === 1 ? '' : 's') . ' ago';
    }

    if ($diff < 86400) {
        $hours = (int) floor($diff / 3600);
        return $hours . ' hour' . ($hours === 1 ? '' : 's') . ' ago';
    }

    if ($diff < 604800) {
        $days = (int) floor($diff / 86400);
        return $days . ' day' . ($days === 1 ? '' : 's') . ' ago';
    }

    return date('M j, Y g:i A', $timestamp);
}

$logs = [];
foreach ($historyData as $pid => $entries) {
    foreach ($entries as $entry) {
        $actionLabel = normalize_action_label($entry['action'] ?? '');
        $context = $entry['context'] ?? (is_numeric($pid) ? 'page' : 'system');
        $details = $entry['details'] ?? [];
        if (!is_array($details)) {
            $details = $details !== '' ? [$details] : [];
        }
        $pageTitle = $entry['page_title'] ?? null;
        if ($pageTitle === null) {
            if ($context === 'system') {
                $pageTitle = 'System activity';
            } else {
                $pageTitle = $pageLookup[$pid] ?? 'Unknown';
            }
        }
        $logs[] = [
            'time' => (int)($entry['time'] ?? 0),
            'user' => $entry['user'] ?? '',
            'page_title' => $pageTitle,
            'action' => $actionLabel,
            'action_slug' => slugify_action_label($actionLabel),
            'details' => $details,
            'context' => $context,
            'meta' => $entry['meta'] ?? new stdClass(),
        ];
    }
}

usort($logs, function ($a, $b) {
    return $b['time'] <=> $a['time'];
});

$now = time();
$totalLogs = count($logs);
$lastActivity = $totalLogs > 0 ? $logs[0]['time'] : null;
$last24Hours = 0;
$last7Days = 0;
$uniqueUsers = [];
$uniquePages = [];
$actionsSummary = [];

foreach ($logs as $log) {
    $timestamp = (int) $log['time'];
    if ($timestamp >= $now - 86400) {
        $last24Hours++;
    }
    if ($timestamp >= $now - (7 * 86400)) {
        $last7Days++;
    }

    $userKey = strtolower(trim($log['user']));
    if ($userKey !== '') {
        $uniqueUsers[$userKey] = true;
    }

    $pageKey = strtolower(trim($log['page_title']));
    if ($pageKey !== '') {
        $uniquePages[$pageKey] = true;
    }

    $slug = $log['action_slug'];
    if (!isset($actionsSummary[$slug])) {
        $actionsSummary[$slug] = [
            'label' => $log['action'],
            'slug' => $slug,
            'count' => 0,
        ];
    }
    $actionsSummary[$slug]['count']++;
}

usort($actionsSummary, function ($a, $b) {
    return $b['count'] <=> $a['count'];
});

$topActions = array_slice($actionsSummary, 0, 4);
$topAction = $actionsSummary[0] ?? null;

$logsJson = htmlspecialchars(json_encode($logs, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8');
$matchCountLabel = 'No entries to display';
if ($totalLogs === 1) {
    $matchCountLabel = '1 entry';
} elseif ($totalLogs > 1) {
    $matchCountLabel = $totalLogs . ' entries';
}
$uniqueUsersCount = count($uniqueUsers);
$uniquePagesCount = count($uniquePages);
$lastActivityLabel = $lastActivity ? describe_time_ago($lastActivity, true) : 'No activity yet';
$lastActivityExact = $lastActivity ? date('M j, Y g:i A', $lastActivity) : 'No recent activity';
$recentPageTitle = $totalLogs > 0 ? htmlspecialchars($logs[0]['page_title'], ENT_QUOTES, 'UTF-8') : '';
$topActionLabel = $topAction ? htmlspecialchars($topAction['label'], ENT_QUOTES, 'UTF-8') : '—';
$topActionCountText = $topAction ? $topAction['count'] . ' entries' : 'No recorded actions yet';
$editorsHint = 'No editor activity yet';
if ($uniqueUsersCount === 1) {
    $editorsHint = 'Team member contributing recently';
} elseif ($uniqueUsersCount > 1) {
    $editorsHint = 'Editors with recent changes';
}
?>
<div class="content-section" id="logs">
    <div class="logs-dashboard" data-logs="<?php echo $logsJson; ?>" data-endpoint="modules/logs/list_logs.php">
        <header class="a11y-hero logs-hero">
            <div class="a11y-hero-content logs-hero-content">
                <div>
                    <h2 class="a11y-hero-title logs-hero-title">Activity Logs</h2>
                    <p class="a11y-hero-subtitle logs-hero-subtitle">Monitor publishing events, workflow actions, and page edits from a single, friendly timeline.</p>
                </div>
                <div class="a11y-hero-actions logs-hero-actions">
                    <button type="button" class="logs-btn logs-btn--ghost" id="logsRefreshBtn">
                        <i class="fas fa-rotate" aria-hidden="true"></i>
                        <span>Refresh</span>
                    </button>
                    <div class="a11y-hero-meta-group logs-hero-meta-group">
                        <span class="a11y-hero-meta logs-hero-meta-item">
                            <span class="logs-hero-meta__label">Last activity</span>
                            <span class="logs-hero-meta__value" id="logsLastActivity" title="<?php echo htmlspecialchars($lastActivityExact, ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars($lastActivityLabel, ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                        </span>
                        <span class="a11y-hero-meta logs-hero-meta-item">
                            <span class="logs-hero-meta__label">Past 24 hours</span>
                            <span class="logs-hero-meta__value" id="logsPast24h"><?php echo $last24Hours; ?></span>
                        </span>
                    </div>
                </div>
            </div>
            <div class="a11y-overview-grid logs-overview-grid">
                <div class="a11y-overview-card logs-overview-card">
                    <div class="a11y-overview-label logs-stat-label">Total events</div>
                    <div class="a11y-overview-value logs-stat-value" id="logsTotalCount"><?php echo $totalLogs; ?></div>
                    <div class="logs-stat-hint"><span id="logsLast7Days"><?php echo $last7Days; ?></span> in the last 7 days</div>
                </div>
                <div class="a11y-overview-card logs-overview-card">
                    <div class="a11y-overview-label logs-stat-label">Active editors</div>
                    <div class="a11y-overview-value logs-stat-value" id="logsUserCount"><?php echo $uniqueUsersCount; ?></div>
                    <div class="logs-stat-hint"><?php echo htmlspecialchars($editorsHint, ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
                <div class="a11y-overview-card logs-overview-card">
                    <div class="a11y-overview-label logs-stat-label">Pages updated</div>
                    <div class="a11y-overview-value logs-stat-value" id="logsPageCount"><?php echo $uniquePagesCount; ?></div>
                    <div class="logs-stat-hint">
                        <?php if ($uniquePagesCount > 0): ?>
                            Most recent: <?php echo $recentPageTitle; ?>
                        <?php else: ?>
                            Waiting for the first edit
                        <?php endif; ?>
                    </div>
                </div>
                <div class="a11y-overview-card logs-overview-card">
                    <div class="a11y-overview-label logs-stat-label">Most common action</div>
                    <div class="a11y-overview-value logs-stat-value" id="logsTopActionLabel"><?php echo $topActionLabel; ?></div>
                    <div class="logs-stat-hint" id="logsTopActionCount"><?php echo htmlspecialchars($topActionCountText, ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
            </div>
        </header>

        <section class="logs-activity" aria-label="Activity feed">
            <div class="logs-activity-header">
                <div>
                    <h3>Recent activity</h3>
                    <p id="logsMatchCount"><?php echo htmlspecialchars($matchCountLabel, ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
                <div class="logs-controls">
                    <div class="logs-date-range" aria-label="Filter activity by date range">
                        <label for="logsStartDate">
                            <span>From</span>
                            <input type="date" id="logsStartDate" name="logsStartDate">
                        </label>
                        <span class="logs-date-range-separator" aria-hidden="true">–</span>
                        <label for="logsEndDate">
                            <span>To</span>
                            <input type="date" id="logsEndDate" name="logsEndDate">
                        </label>
                    </div>
                    <label class="logs-search" for="logsSearch">
                        <i class="fas fa-search" aria-hidden="true"></i>
                        <input type="search" id="logsSearch" placeholder="Search by editor, page, or action" autocomplete="off">
                    </label>
                </div>
            </div>

            <div class="logs-filters" id="logsFilters">
                <button type="button" class="logs-filter-btn active" data-filter="all">
                    <span>All activity</span>
                    <span class="logs-filter-count" id="logsAllCount"><?php echo $totalLogs; ?></span>
                </button>
                <?php foreach ($topActions as $action): ?>
                    <button type="button" class="logs-filter-btn" data-filter="<?php echo htmlspecialchars($action['slug'], ENT_QUOTES, 'UTF-8'); ?>">
                        <span><?php echo htmlspecialchars($action['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="logs-filter-count" data-filter-count="<?php echo htmlspecialchars($action['slug'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo $action['count']; ?></span>
                    </button>
                <?php endforeach; ?>
            </div>

            <div class="logs-activity-table-wrapper" id="logsTimeline">
<?php if (empty($logs)): ?>
                <div class="logs-empty">
                    <i class="fas fa-clipboard-list" aria-hidden="true"></i>
                    <p>No activity recorded yet.</p>
                    <p class="logs-empty-hint">Updates will appear here as your team edits content.</p>
                </div>
<?php else: ?>
                <div class="logs-activity-table-scroll">
                    <table class="logs-activity-table">
                        <thead>
                            <tr>
                                <th scope="col">Action</th>
                                <th scope="col">Page</th>
                                <th scope="col">Editor</th>
                                <th scope="col">Details</th>
                                <th scope="col">When</th>
                            </tr>
                        </thead>
                        <tbody>
<?php foreach ($logs as $log):
    $timestamp = (int) $log['time'];
    $relative = describe_time_ago($timestamp, false);
    $absolute = $timestamp ? date('M j, Y g:i A', $timestamp) : 'Unknown time';
    $detailsText = '';
    if (!empty($log['details']) && is_array($log['details'])) {
        $detailsText = ' ' . implode(' ', $log['details']);
    }
    $searchText = strtolower(trim(($log['user'] ?? '') . ' ' . ($log['page_title'] ?? '') . ' ' . $log['action'] . $detailsText));
?>
                            <tr class="logs-activity-row" data-search="<?php echo htmlspecialchars($searchText, ENT_QUOTES, 'UTF-8'); ?>" data-action="<?php echo htmlspecialchars($log['action_slug'], ENT_QUOTES, 'UTF-8'); ?>">
                                <td class="logs-activity-cell logs-activity-cell--action" data-label="Action">
                                    <span class="logs-activity-badge"><?php echo htmlspecialchars($log['action'], ENT_QUOTES, 'UTF-8'); ?></span>
                                </td>
                                <td class="logs-activity-cell logs-activity-cell--page" data-label="Page">
                                    <span class="logs-activity-page"><?php echo htmlspecialchars($log['page_title'], ENT_QUOTES, 'UTF-8'); ?></span>
                                </td>
                                <td class="logs-activity-cell logs-activity-cell--user" data-label="Editor">
                                    <span class="logs-activity-user"><?php echo $log['user'] !== '' ? htmlspecialchars($log['user'], ENT_QUOTES, 'UTF-8') : 'System'; ?></span>
                                </td>
                                <td class="logs-activity-cell logs-activity-cell--details" data-label="Details">
<?php if (!empty($log['details']) && is_array($log['details'])): ?>
                                    <ul class="logs-activity-details">
<?php foreach ($log['details'] as $detail): ?>
                                        <li><?php echo htmlspecialchars($detail, ENT_QUOTES, 'UTF-8'); ?></li>
<?php endforeach; ?>
                                    </ul>
<?php else: ?>
                                    <span class="logs-activity-details-empty">—</span>
<?php endif; ?>
                                </td>
                                <td class="logs-activity-cell logs-activity-cell--time" data-label="When">
                                    <time datetime="<?php echo $timestamp ? date('c', $timestamp) : ''; ?>" class="logs-activity-time" title="<?php echo htmlspecialchars($absolute, ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php echo htmlspecialchars($relative, ENT_QUOTES, 'UTF-8'); ?>
                                    </time>
                                </td>
                            </tr>
<?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
<?php endif; ?>
            </div>
        </section>
    </div>
</div>
