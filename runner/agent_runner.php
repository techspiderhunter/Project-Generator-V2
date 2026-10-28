<?php
// runner/agent_runner.php
// Continuous / One-shot Autonomous Task Runner Worker

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../runner/llm_client.php';
require_once __DIR__ . '/../api/run_step.php';

echo "========================================================\n";
echo "   AI Sequential Task Automation Engine — Worker Loop   \n";
echo "========================================================\n";

$isContinuous = in_array('--loop', $argv);

do {
    // 1. Pick a pending task
    $stmt = $pdo->query("SELECT id, title FROM tasks WHERE status = 'pending' ORDER BY id ASC LIMIT 1");
    $task = $stmt->fetch();

    if ($task) {
        $id = (int)$task['id'];
        $title = $task['title'];
        echo "\n[CLAIMED] Task #$id: '$title'...\n";

        $res = runFullPipeline($pdo, $id);

        if ($res['success']) {
            echo "[SUCCESS] Task #$id completed successfully!\n";
        } else {
            echo "[FAILED] Task #$id failed: " . ($res['error'] ?? 'Unknown error') . "\n";
        }
    } else {
        if (!$isContinuous) {
            echo "[IDLE] No pending tasks found in queue.\n";
            break;
        }
        echo ".";
        sleep(3);
    }
} while ($isContinuous);

