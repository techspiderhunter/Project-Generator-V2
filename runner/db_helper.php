<?php
// runner/db_helper.php
// CLI bridge for Antigravity IDE and automation workers

require_once __DIR__ . '/../config/db.php';

$action = $argv[1] ?? '';

switch ($action) {
    case 'pick_task':
        // Claim next pending task atomically
        $pdo->beginTransaction();
        try {
            $forUpdate = ($activeDriver === 'mysql') ? ' FOR UPDATE' : '';
            $stmt = $pdo->query("SELECT * FROM tasks WHERE status = 'pending' ORDER BY id ASC LIMIT 1" . $forUpdate);
            $task = $stmt->fetch();
            if ($task) {
                $upd = $pdo->prepare("UPDATE tasks SET status = 'planning', `current_role` = 'planner' WHERE id = ?");
                $upd->execute([$task['id']]);
                $pdo->commit();
                echo json_encode(['success' => true, 'task' => $task], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            } else {
                $pdo->commit();
                echo json_encode(['success' => false, 'message' => 'No pending tasks found']);
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit(1);
        }
        break;

    case 'get_prompt':
        $role = $argv[2] ?? '';
        $stmt = $pdo->prepare("SELECT * FROM prompts WHERE role = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$role]);
        $prompt = $stmt->fetch();
        if ($prompt) {
            echo json_encode(['success' => true, 'prompt' => $prompt], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        } else {
            echo json_encode(['success' => false, 'message' => "No active prompt for role: $role"]);
        }
        break;

    case 'update_status':
        // Usage: php runner/db_helper.php update_status <task_id> <status> <current_role> [output_column] [output_file]
        $taskId = (int)($argv[2] ?? 0);
        $status = $argv[3] ?? '';
        $role   = $argv[4] ?? 'none';
        $col    = $argv[5] ?? '';
        $file   = $argv[6] ?? '';

        if ($taskId <= 0 || !$status) {
            echo json_encode(['success' => false, 'error' => 'Missing task_id or status']);
            exit(1);
        }

        $content = ($file && file_exists($file)) ? file_get_contents($file) : '';

        $nowFunc = ($activeDriver === 'mysql') ? 'NOW()' : 'CURRENT_TIMESTAMP';

        if ($col && in_array($col, ['plan_output', 'dev_output', 'test_output', 'error_log'])) {
            $sql = "UPDATE tasks SET status = :status, `current_role` = :role, $col = :val, updated_at = $nowFunc WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['status' => $status, 'role' => $role, 'val' => $content, 'id' => $taskId]);
        } else {
            $sql = "UPDATE tasks SET status = :status, `current_role` = :role, updated_at = $nowFunc WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['status' => $status, 'role' => $role, 'id' => $taskId]);
        }
        echo json_encode(['success' => true, 'task_id' => $taskId, 'status' => $status, 'current_role' => $role]);
        break;

    default:
        echo json_encode([
            'error' => 'Invalid action',
            'available_actions' => [
                'pick_task' => 'php runner/db_helper.php pick_task',
                'get_prompt' => 'php runner/db_helper.php get_prompt <planner|developer|tester>',
                'update_status' => 'php runner/db_helper.php update_status <id> <status> <role> [col] [file]'
            ]
        ], JSON_PRETTY_PRINT);
        break;
}
echo PHP_EOL;

