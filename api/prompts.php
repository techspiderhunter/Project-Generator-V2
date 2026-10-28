<?php
// api/prompts.php
// REST API for Role-Wise Prompt Management

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

require_once __DIR__ . '/../config/db.php';

$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$role = isset($_GET['role']) ? trim($_GET['role']) : null;

try {
    switch ($method) {
        case 'GET':
            if ($role) {
                $stmt = $pdo->prepare("SELECT * FROM prompts WHERE role = ? AND is_active = 1 LIMIT 1");
                $stmt->execute([$role]);
                $prompt = $stmt->fetch();
                echo json_encode(['success' => (bool)$prompt, 'prompt' => $prompt]);
            } elseif ($id) {
                $stmt = $pdo->prepare("SELECT * FROM prompts WHERE id = ?");
                $stmt->execute([$id]);
                $prompt = $stmt->fetch();
                echo json_encode(['success' => (bool)$prompt, 'prompt' => $prompt]);
            } else {
                $stmt = $pdo->query("SELECT * FROM prompts ORDER BY role ASC, version DESC");
                $prompts = $stmt->fetchAll();
                echo json_encode(['success' => true, 'prompts' => $prompts]);
            }
            break;

        case 'PUT':
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'Prompt ID required']);
                exit;
            }
            $input = json_decode(file_get_contents('php://input'), true);

            $fields = [];
            $params = [];
            foreach (['title', 'system_prompt', 'user_prompt_template', 'model_name', 'temperature', 'is_active'] as $col) {
                if (isset($input[$col])) {
                    $fields[] = "$col = ?";
                    $params[] = $input[$col];
                }
            }

            if (empty($fields)) {
                echo json_encode(['error' => 'No fields to update']);
                exit;
            }

            $params[] = $id;
            $sql = "UPDATE prompts SET " . implode(', ', $fields) . ", updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            echo json_encode(['success' => true, 'message' => "Prompt #$id updated successfully."]);
            break;

        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            $pRole = trim($input['role'] ?? '');
            $pTitle = trim($input['title'] ?? '');
            $pSys = trim($input['system_prompt'] ?? '');
            $pUser = trim($input['user_prompt_template'] ?? '');
            $pModel = trim($input['model_name'] ?? 'gemini-1.5-pro');
            $pTemp = (float)($input['temperature'] ?? 0.2);

            if (!in_array($pRole, ['planner', 'developer', 'tester'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid role. Must be planner, developer, or tester.']);
                exit;
            }

            // Get next version
            $vStmt = $pdo->prepare("SELECT COALESCE(MAX(version), 0) + 1 FROM prompts WHERE role = ?");
            $vStmt->execute([$pRole]);
            $nextVersion = (int)$vStmt->fetchColumn();

            // Deactivate previous versions if this is active
            $pdo->prepare("UPDATE prompts SET is_active = 0 WHERE role = ?")->execute([$pRole]);

            $stmt = $pdo->prepare("
                INSERT INTO prompts (role, title, system_prompt, user_prompt_template, model_name, temperature, is_active, version)
                VALUES (?, ?, ?, ?, ?, ?, 1, ?)
            ");
            $stmt->execute([$pRole, $pTitle, $pSys, $pUser, $pModel, $pTemp, $nextVersion]);

            echo json_encode([
                'success' => true,
                'message' => "New $pRole prompt version $nextVersion created and set active.",
                'prompt_id' => $pdo->lastInsertId()
            ]);
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

