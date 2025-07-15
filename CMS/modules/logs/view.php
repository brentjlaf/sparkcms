<?php
// File: view.php
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$pagesFile = __DIR__ . '/../../data/pages.json';
$pages = file_exists($pagesFile) ? json_decode(file_get_contents($pagesFile), true) : [];
$pageLookup = [];
foreach ($pages as $p) {
    $pageLookup[$p['id']] = $p['title'];
}

$historyFile = __DIR__ . '/../../data/page_history.json';
$historyData = file_exists($historyFile) ? json_decode(file_get_contents($historyFile), true) : [];

$logs = [];
foreach ($historyData as $pid => $entries) {
    foreach ($entries as $entry) {
        $logs[] = [
            'time' => $entry['time'] ?? 0,
            'user' => $entry['user'] ?? '',
            'page_title' => $pageLookup[$pid] ?? 'Unknown',
            'action' => $entry['action'] ?? ''
        ];
    }
}
usort($logs, function($a, $b) { return $b['time'] <=> $a['time']; });
?>
<div class="content-section" id="logs">
    <div class="table-card">
        <div class="table-header">
            <div class="table-title">Activity Logs</div>
        </div>
        <table class="data-table" id="logsTable">
            <thead>
                <tr>
                    <th>Time</th>
                    <th>User</th>
                    <th>Page</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
<?php foreach ($logs as $log): ?>
                <tr>
                    <td class="time"><?php echo date('Y-m-d H:i', $log['time']); ?></td>
                    <td class="user"><?php echo htmlspecialchars($log['user']); ?></td>
                    <td class="page"><?php echo htmlspecialchars($log['page_title']); ?></td>
                    <td class="action"><?php echo htmlspecialchars($log['action']); ?></td>
                </tr>
<?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
