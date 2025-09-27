<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_once __DIR__ . '/schedule_helpers.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

require_login();

$action = isset($_POST['action']) ? sanitize_text($_POST['action']) : 'update';
$now = new DateTimeImmutable('now');
$scheduleFile = __DIR__ . '/../../data/speed_schedule.json';
$schedule = speed_normalize_schedule(read_json_file($scheduleFile));

function speed_save_schedule(string $file, array $schedule): bool
{
    return write_json_file($file, $schedule);
}

switch ($action) {
    case 'update':
        $cadence = isset($_POST['cadence']) ? sanitize_text($_POST['cadence']) : 'manual';
        $allowed = speed_allowed_cadences();
        if (!isset($allowed[$cadence])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid cadence selected.']);
            exit;
        }

        $schedule['cadence'] = $cadence;
        $schedule['updatedAt'] = $now->format(DateTimeInterface::ATOM);

        if ($cadence === 'manual') {
            $schedule['nextRun'] = null;
            $schedule['queue'] = [];
            $schedule['status'] = 'manual';
        } else {
            $next = speed_calculate_next_run($cadence, $now);
            if (!$next) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Unable to compute the next scan time.']);
                exit;
            }
            $schedule['nextRun'] = $next->format(DateTimeInterface::ATOM);
            $schedule['queue'] = [speed_build_queue_entry($cadence, $now, $next)];
            $schedule['status'] = 'scheduled';
            $schedule['lastScheduledAt'] = $now->format(DateTimeInterface::ATOM);
        }

        if (!speed_save_schedule($scheduleFile, $schedule)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Unable to save schedule settings.']);
            exit;
        }

        $schedule = speed_enrich_schedule($schedule);
        echo json_encode(['success' => true, 'schedule' => $schedule]);
        break;

    case 'cancel':
        $schedule['queue'] = [];
        $schedule['nextRun'] = null;
        $schedule['cancelledAt'] = $now->format(DateTimeInterface::ATOM);
        $schedule['status'] = $schedule['cadence'] === 'manual' ? 'manual' : 'paused';
        $schedule['updatedAt'] = $now->format(DateTimeInterface::ATOM);

        if (!speed_save_schedule($scheduleFile, $schedule)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Unable to cancel the scheduled scan.']);
            exit;
        }

        $schedule = speed_enrich_schedule($schedule);
        echo json_encode(['success' => true, 'schedule' => $schedule]);
        break;

    case 'reschedule':
        $cadence = $schedule['cadence'] ?? 'manual';
        if ($cadence === 'manual') {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Enable automatic scans to schedule the next run.']);
            exit;
        }

        $next = speed_calculate_next_run($cadence, $now);
        if (!$next) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Unable to compute the next scan time.']);
            exit;
        }

        $schedule['nextRun'] = $next->format(DateTimeInterface::ATOM);
        $schedule['queue'] = [speed_build_queue_entry($cadence, $now, $next)];
        $schedule['status'] = 'scheduled';
        $schedule['lastScheduledAt'] = $now->format(DateTimeInterface::ATOM);
        $schedule['updatedAt'] = $now->format(DateTimeInterface::ATOM);

        if (!speed_save_schedule($scheduleFile, $schedule)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Unable to reschedule the scan.']);
            exit;
        }

        $schedule = speed_enrich_schedule($schedule);
        echo json_encode(['success' => true, 'schedule' => $schedule]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Unsupported action.']);
        break;
}
