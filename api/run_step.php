<?php
// api/run_step.php
// Endpoint to execute steps or complete task pipeline from Web Panel or CLI

if (php_sapi_name() !== 'cli') {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');

    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        exit;
    }
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../runner/llm_client.php';

// Support both JSON HTTP input and CLI execution when invoked directly
$isDirectExecution = (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'run_step.php');

if ($isDirectExecution) {
    if (php_sapi_name() === 'cli') {
        $taskId = isset($argv[1]) ? (int)$argv[1] : null;
        $action = $argv[2] ?? 'step';
    } else {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $action = $input['action'] ?? 'step';
        $taskId = isset($input['task_id']) ? (int)$input['task_id'] : null;
    }

    if ($action === 'pick_and_run') {
        $stmt = $pdo->query("SELECT id FROM tasks WHERE status = 'pending' ORDER BY id ASC LIMIT 1");
        $task = $stmt->fetch();
        if (!$task) {
            echo json_encode(['success' => false, 'message' => 'No pending tasks to run.']);
            exit;
        }
        $taskId = (int)$task['id'];
        $result = runFullPipeline($pdo, $taskId);
        echo json_encode($result, JSON_PRETTY_PRINT);
        exit;
    }

    if (!$taskId) {
        if (php_sapi_name() !== 'cli') {
            http_response_code(400);
        }
        echo json_encode(['error' => 'task_id is required']);
        exit;
    }

    try {
        if ($action === 'run_full') {
            $result = runFullPipeline($pdo, $taskId);
        } else {
            $result = runSingleStep($pdo, $taskId);
        }
        echo json_encode($result, JSON_PRETTY_PRINT);
    } catch (Exception $e) {
        if (php_sapi_name() !== 'cli') {
            http_response_code(500);
        }
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function runSingleStep(PDO $pdo, int $taskId): array {
    $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ?");
    $stmt->execute([$taskId]);
    $task = $stmt->fetch();

    if (!$task) {
        return ['success' => false, 'error' => "Task #$taskId not found."];
    }

    $status = $task['status'];

    if ($status === 'pending') {
        return executeRole($pdo, $taskId, 'planner', 'planning', 'planned', 'plan_output');
    } elseif ($status === 'planned') {
        return executeRole($pdo, $taskId, 'developer', 'developing', 'developed', 'dev_output');
    } elseif ($status === 'developed') {
        $testRes = executeRole($pdo, $taskId, 'tester', 'testing', 'tested', 'test_output');
        if ($testRes['success']) {
            $pdo->prepare("UPDATE tasks SET status = 'completed', `current_role` = 'none', updated_at = CURRENT_TIMESTAMP WHERE id = ?")
                ->execute([$taskId]);
            return [
                'success' => true,
                'step' => 'completed',
                'message' => "Task #$taskId tested and marked completed!"
            ];
        }
        return $testRes;
    } elseif ($status === 'tested') {
        $pdo->prepare("UPDATE tasks SET status = 'completed', `current_role` = 'none', updated_at = CURRENT_TIMESTAMP WHERE id = ?")
            ->execute([$taskId]);
        return ['success' => true, 'step' => 'completed', 'message' => "Task #$taskId marked completed!"];
    } elseif ($status === 'completed') {
        return ['success' => true, 'step' => 'already_completed', 'message' => "Task #$taskId is already completed."];
    } else {
        return ['success' => false, 'error' => "Task #$taskId is in '$status' state. Reset to retry."];
    }
}

function runFullPipeline(PDO $pdo, int $taskId): array {
    $stepsRun = [];
    while (true) {
        $stmt = $pdo->prepare("SELECT status FROM tasks WHERE id = ?");
        $stmt->execute([$taskId]);
        $currStatus = $stmt->fetchColumn();

        if ($currStatus === 'completed' || $currStatus === 'failed') {
            break;
        }

        $stepRes = runSingleStep($pdo, $taskId);
        $stepsRun[] = $stepRes;

        if (!$stepRes['success']) {
            return [
                'success' => false,
                'task_id' => $taskId,
                'final_status' => 'failed',
                'history' => $stepsRun,
                'error' => $stepRes['error'] ?? 'Pipeline halted on step error'
            ];
        }
    }

    return [
        'success' => true,
        'task_id' => $taskId,
        'final_status' => 'completed',
        'history' => $stepsRun
    ];
}

function executeRole(PDO $pdo, int $taskId, string $role, string $inProgressStatus, string $doneStatus, string $outputCol): array {
    $pdo->prepare("UPDATE tasks SET status = ?, `current_role` = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")
        ->execute([$inProgressStatus, $role, $taskId]);

    $tStmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ?");
    $tStmt->execute([$taskId]);
    $task = $tStmt->fetch();

    $pStmt = $pdo->prepare("SELECT * FROM prompts WHERE role = ? AND is_active = 1 LIMIT 1");
    $pStmt->execute([$role]);
    $prompt = $pStmt->fetch();

    if (!$prompt) {
        $err = "No active prompt configured for role: $role";
        $pdo->prepare("UPDATE tasks SET status = 'failed', `current_role` = 'none', error_log = ? WHERE id = ?")
            ->execute([$err, $taskId]);
        return ['success' => false, 'error' => $err];
    }

    $userPrompt = str_replace(
        ['{{task_id}}', '{{title}}', '{{description}}', '{{project_path}}', '{{plan_output}}', '{{dev_output}}'],
        [$task['id'], $task['title'], $task['description'], $task['project_path'] ?? '', $task['plan_output'] ?? '', $task['dev_output'] ?? ''],
        $prompt['user_prompt_template']
    );

    try {
        $aiResult = callLLM(
            $prompt['system_prompt'],
            $userPrompt,
            $prompt['model_name'] ?? 'gemini-1.5-pro',
            (float)($prompt['temperature'] ?? 0.2)
        );

        $pdo->prepare("UPDATE tasks SET $outputCol = ?, status = ?, `current_role` = 'none', updated_at = CURRENT_TIMESTAMP WHERE id = ?")
            ->execute([$aiResult, $doneStatus, $taskId]);

        return [
            'success' => true,
            'role' => $role,
            'status' => $doneStatus,
            'output_preview' => substr($aiResult, 0, 150) . '...'
        ];
    } catch (Exception $e) {
        $pdo->prepare("UPDATE tasks SET status = 'failed', `current_role` = 'none', error_log = ? WHERE id = ?")
            ->execute([$e->getMessage(), $taskId]);
        return ['success' => false, 'role' => $role, 'error' => $e->getMessage()];
    }
}
