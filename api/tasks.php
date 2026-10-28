<?php
// api/tasks.php
// REST API for Task Management

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

require_once __DIR__ . '/../config/db.php';

$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

try {
    switch ($method) {
        case 'GET':
            if ($id) {
                $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ?");
                $stmt->execute([$id]);
                $task = $stmt->fetch();
                if (!$task) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Task not found']);
                    exit;
                }
                echo json_encode(['success' => true, 'task' => $task]);
            } else {
                $status = $_GET['status'] ?? null;
                if ($status) {
                    $stmt = $pdo->prepare("SELECT * FROM tasks WHERE status = ? ORDER BY id DESC");
                    $stmt->execute([$status]);
                } else {
                    $stmt = $pdo->query("SELECT * FROM tasks ORDER BY id DESC");
                }
                $tasks = $stmt->fetchAll();
                echo json_encode(['success' => true, 'tasks' => $tasks, 'driver' => $activeDriver]);
            }
            break;

        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            $title = trim($input['title'] ?? '');
            $description = trim($input['description'] ?? '');
            $projectPath = trim($input['project_path'] ?? '');

            if (!$title || !$description) {
                http_response_code(400);
                echo json_encode(['error' => 'Title and Description are required.']);
                exit;
            }

            if (!$projectPath) {
                $projectPath = realpath(__DIR__ . '/..');
            }

            $stmt = $pdo->prepare("
                INSERT INTO tasks (title, description, project_path, status, `current_role`)
                VALUES (?, ?, ?, 'pending', 'none')
            ");
            $stmt->execute([$title, $description, $projectPath]);
            $newId = $pdo->lastInsertId();

            http_response_code(201);
            echo json_encode([
                'success' => true,
                'message' => 'Task created successfully',
                'task_id' => $newId
            ]);
            break;

        case 'PUT':
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'Task ID required']);
                exit;
            }
            $input = json_decode(file_get_contents('php://input'), true);
            $action = $input['action'] ?? '';

            if ($action === 'retry') {
                $stmt = $pdo->prepare("
                    UPDATE tasks 
                    SET status = 'pending', `current_role` = 'none', error_log = NULL, retry_count = retry_count + 1, updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                $stmt->execute([$id]);
                echo json_encode(['success' => true, 'message' => "Task #$id reset to pending for retry."]);
            } else {
                // Generic field update
                $fields = [];
                $params = [];
                foreach (['title', 'description', 'project_path', 'status', 'current_role'] as $col) {
                    if (isset($input[$col])) {
                        $fields[] = "`$col` = ?";
                        $params[] = $input[$col];
                    }
                }
                if (empty($fields)) {
                    echo json_encode(['error' => 'No fields to update']);
                    exit;
                }
                $params[] = $id;
                $sql = "UPDATE tasks SET " . implode(', ', $fields) . ", updated_at = CURRENT_TIMESTAMP WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                echo json_encode(['success' => true, 'message' => "Task #$id updated."]);
            }
            break;

        case 'DELETE':
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'Task ID required']);
                exit;
            }
            $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => "Task #$id deleted."]);
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

