<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/DashboardAggregator.php';

require_login();

$scriptBase = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
if (substr($scriptBase, -4) === '/CMS') {
    $scriptBase = substr($scriptBase, 0, -4);
}
$scriptBase = rtrim($scriptBase, '/');

$templateDir = realpath(__DIR__ . '/../../../theme/templates/pages');
$aggregator = new DashboardAggregator(__DIR__ . '/../../data', $scriptBase, $templateDir ?: null);
$snapshot = $aggregator->aggregate();

header('Content-Type: application/json');
echo json_encode($snapshot->toArray());
