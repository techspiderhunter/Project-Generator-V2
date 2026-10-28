<?php
// config/db.php
// Dual-driver database configuration (MySQL primary, SQLite seamless fallback)

$driver = getenv('DB_DRIVER') ?: 'auto'; // 'mysql', 'sqlite', or 'auto'
$host   = getenv('DB_HOST') ?: '127.0.0.1';
$port   = getenv('DB_PORT') ?: '3306';
$dbName = getenv('DB_NAME') ?: 'task_automate';
$user   = getenv('DB_USER') ?: 'root';
$pass   = getenv('DB_PASS') ?: '';
$socket = '/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock';

$pdo = null;
$activeDriver = 'mysql';

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

if ($driver === 'sqlite') {
    $activeDriver = 'sqlite';
} else {
    // Try connecting to MySQL
    try {
        if (file_exists($socket)) {
            $dsn = "mysql:unix_socket=$socket;dbname=$dbName;charset=utf8mb4";
        } else {
            $dsn = "mysql:host=$host;port=$port;dbname=$dbName;charset=utf8mb4";
        }
        $pdo = new PDO($dsn, $user, $pass, $options);
        $activeDriver = 'mysql';
    } catch (\PDOException $e) {
        // If driver was explicitly mysql, rethrow
        if ($driver === 'mysql') {
            dieError("MySQL connection failed: " . $e->getMessage());
        }
        // Otherwise fall back to SQLite
        $activeDriver = 'sqlite';
    }
}

// Fallback to SQLite if MySQL is unavailable
if ($activeDriver === 'sqlite') {
    $dataDir = __DIR__ . '/../data';
    if (!is_dir($dataDir)) {
        @mkdir($dataDir, 0777, true);
    }
    @chmod($dataDir, 0777);

    $sqliteFile = $dataDir . '/task_automate.sqlite';
    if (file_exists($sqliteFile)) {
        @chmod($sqliteFile, 0666);
    }

    try {
        $pdo = new PDO("sqlite:" . $sqliteFile, null, null, $options);
        $pdo->exec("PRAGMA busy_timeout = 5000;");
        $pdo->exec("PRAGMA journal_mode = DELETE;");
        if (file_exists($sqliteFile)) {
            @chmod($sqliteFile, 0666);
        }
    } catch (\PDOException $e) {
        dieError("SQLite connection failed: " . $e->getMessage());
    }
}

// Auto-initialize tables and seed prompts if not exists
initSchemaAndSeeds($pdo, $activeDriver);

function initSchemaAndSeeds(PDO $pdo, string $driver): void {
    if ($driver === 'mysql') {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `tasks` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `title` VARCHAR(255) NOT NULL,
                `description` TEXT NOT NULL,
                `project_path` VARCHAR(500) NULL,
                `status` ENUM('pending', 'planning', 'planned', 'developing', 'developed', 'testing', 'tested', 'completed', 'failed') NOT NULL DEFAULT 'pending',
                `current_role` ENUM('planner', 'developer', 'tester', 'none') NOT NULL DEFAULT 'none',
                `plan_output` MEDIUMTEXT NULL,
                `dev_output` MEDIUMTEXT NULL,
                `test_output` MEDIUMTEXT NULL,
                `error_log` TEXT NULL,
                `retry_count` INT UNSIGNED NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_status` (`status`),
                INDEX `idx_role` (`current_role`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS `prompts` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `role` ENUM('planner', 'developer', 'tester') NOT NULL,
                `title` VARCHAR(150) NOT NULL,
                `system_prompt` TEXT NOT NULL,
                `user_prompt_template` MEDIUMTEXT NOT NULL,
                `model_name` VARCHAR(100) NOT NULL DEFAULT 'gemini-1.5-pro',
                `temperature` DECIMAL(3,2) NOT NULL DEFAULT 0.20,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `version` INT UNSIGNED NOT NULL DEFAULT 1,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_role_active` (`role`, `is_active`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    } else {
        // SQLite Schema
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `tasks` (
                `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                `title` TEXT NOT NULL,
                `description` TEXT NOT NULL,
                `project_path` TEXT NULL,
                `status` TEXT NOT NULL DEFAULT 'pending',
                `current_role` TEXT NOT NULL DEFAULT 'none',
                `plan_output` TEXT NULL,
                `dev_output` TEXT NULL,
                `test_output` TEXT NULL,
                `error_log` TEXT NULL,
                `retry_count` INTEGER NOT NULL DEFAULT 0,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS `prompts` (
                `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                `role` TEXT NOT NULL,
                `title` TEXT NOT NULL,
                `system_prompt` TEXT NOT NULL,
                `user_prompt_template` TEXT NOT NULL,
                `model_name` TEXT NOT NULL DEFAULT 'gemini-1.5-pro',
                `temperature` REAL NOT NULL DEFAULT 0.20,
                `is_active` INTEGER NOT NULL DEFAULT 1,
                `version` INTEGER NOT NULL DEFAULT 1,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");
    }

    // Check if seed prompts exist
    $count = (int)$pdo->query("SELECT COUNT(*) FROM prompts")->fetchColumn();
    if ($count === 0) {
        $seeds = [
            [
                'role' => 'planner',
                'title' => 'Default Planner Prompt',
                'system_prompt' => 'You are an expert Software Architect and Technical Planner. Your job is to analyze task requirements and create an actionable, step-by-step implementation plan for a developer and tester. Do not write full code. Produce clear steps, file paths, and test cases.',
                'user_prompt_template' => "Task #{{task_id}}: {{title}}\nProject Directory: {{project_path}}\n\nRequirement Description:\n{{description}}\n\nProduce an actionable plan in Markdown format with the following sections:\n1. Architecture & Design Decisions\n2. Target Files to Create or Modify\n3. Step-by-Step Implementation Steps\n4. Verification & Testing Strategy",
                'model_name' => 'gemini-1.5-pro',
                'temperature' => 0.20
            ],
            [
                'role' => 'developer',
                'title' => 'Default Developer Prompt',
                'system_prompt' => 'You are a Senior Full-Stack Software Engineer. Your job is to implement the exact plan provided by the Software Architect. Write production-quality, tested, clean code adhering to best practices.',
                'user_prompt_template' => "Task #{{task_id}}: {{title}}\nProject Directory: {{project_path}}\n\nOriginal Requirement:\n{{description}}\n\nArchitect Plan:\n{{plan_output}}\n\nExecute the plan:\n1. Provide the complete code for all new or modified files.\n2. Clearly mark file paths.\n3. Implement necessary logic.\n4. Summarize all changes performed.",
                'model_name' => 'gemini-1.5-pro',
                'temperature' => 0.10
            ],
            [
                'role' => 'tester',
                'title' => 'Default Tester Prompt',
                'system_prompt' => 'You are a Lead QA and Test Automation Engineer. Your job is to review the code changes against the original requirements and plan, execute test cases or mock executions, verify syntax and logic, and provide a PASS/FAIL verdict.',
                'user_prompt_template' => "Task #{{task_id}}: {{title}}\nProject Directory: {{project_path}}\n\nOriginal Requirement:\n{{description}}\n\nArchitect Plan:\n{{plan_output}}\n\nDeveloper Code & Output:\n{{dev_output}}\n\nPerform comprehensive verification:\n1. Review code syntax, logic bugs, security edge cases.\n2. Verify all acceptance criteria from the plan.\n3. State explicit Verdict: [PASS] or [FAIL] with detailed reasoning.",
                'model_name' => 'gemini-1.5-pro',
                'temperature' => 0.05
            ]
        ];

        $ins = $pdo->prepare("
            INSERT INTO prompts (role, title, system_prompt, user_prompt_template, model_name, temperature, is_active, version)
            VALUES (:role, :title, :system_prompt, :user_prompt_template, :model_name, :temperature, 1, 1)
        ");
        foreach ($seeds as $s) {
            $ins->execute($s);
        }
    }
}

function dieError(string $message): void {
    if (php_sapi_name() === 'cli') {
        fwrite(STDERR, "[DB ERROR] $message\n");
        exit(1);
    }
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => $message]);
    exit;
}
