Below is the complete implementation plan for your AI Development Automation Platform, updated to use MySQL and a free MySQL-backed job queue instead of PostgreSQL, Redis, and BullMQ.

The plan is structured so you can give each phase to Codex, Claude Code, or Antigravity and implement the project step by step, testing each phase before moving forward.

# AI Development Automation Platform — Complete Task Plan

Web Dashboard and Backend

Create projects, define development tasks, manage execution, and monitor progress.

MySQL Task Queue

Schedule jobs, claim tasks safely, retry failures, and recover interrupted tasks without Redis.

Local Desktop Agent

Connect your Mac to the server, prepare project workspaces, run tools, and report results.

AI Development Tool Adapters

Integrate supported Codex, Claude Code, and Antigravity workflows through separate adapters.

## 1. Final technology stack

| Component                    | Technology                       |
| ---------------------------- | -------------------------------- |
| Web dashboard                | Next.js + TypeScript             |
| UI styling                   | Tailwind CSS                     |
| Backend API                  | NestJS + TypeScript              |
| Persistent database          | MySQL 8+                         |
| Job queue                    | Custom MySQL-backed queue        |
| Scheduled jobs               | `node-cron`                      |
| Real-time progress           | WebSockets                       |
| Local agent                  | Node.js + TypeScript             |
| Desktop interface (optional) | Electron                         |
| Source control               | Git                              |
| Testing                      | Jest + API/integration tests     |
| Deployment                   | Linux server + local macOS agent |

Cost-control requirement: No Redis, BullMQ, or paid queue service in the initial implementation. Use MySQL transactions, row locking, task leases, and worker polling to implement queue safety and recovery.

## 2. Complete project phases

## 01

Foundation and repository setup

Phase 1

Create the project structure, configuration, environment files, code standards, and development scripts.

## 02

Database and authentication

Phase 2

Implement MySQL schema, migrations, login, roles, permissions, and secure API access.

## 03

Project and task management

Phase 3

Build project configuration, task creation, priorities, dependencies, queues, and dashboard screens.

## 04

MySQL-backed job queue

Phase 4

Implement safe claiming, worker polling, leases, retries, heartbeat, timeout, and crash recovery.

## 05

Local agent for macOS

Phase 5

Register the computer, connect securely, prepare Git workspaces, execute tasks, and send logs.

## 06

AI tool integrations

Phase 6

Create individual adapters, beginning with one supported command-line integration, then expand to other tools.

## 07

Verification and reporting

Phase 7

Run tests, collect results, generate reports, request approval, and track Git changes.

## 08

Security, reliability, and deployment

Phase 8

Harden permissions, test failure scenarios, deploy the server, and package the agent.

## 3. Detailed implementation tasks

The following is the master specification for the project.

# AI Development Automation Platform

## Complete Project Implementation Task Plan

### Project Objective

Build a centralized development automation platform that allows users to create software development tasks through a web dashboard and execute them on a connected local computer using supported AI development tools.

The platform must:

- Allow users to register software projects and configure their repositories.
- Accept development tasks through a web dashboard.
- Store and schedule tasks using MySQL.
- Use a free, MySQL-backed queue instead of Redis or BullMQ.
- Connect a local agent running on macOS to the server.
- Execute queued tasks sequentially using supported AI development tools.
- Track task progress, logs, execution attempts, and results.
- Run automated verification after execution.
- Recover safely from agent disconnects, server restarts, and failed tasks.
- Require approval for sensitive operations.
- Support future expansion to Windows and Linux agents.

---

## Phase 1: Project Foundation

### Task 1.1 — Inspect the Existing Environment

Before writing code:

1. Inspect the existing repository and identify its structure.
2. Check the installed Node.js, package manager, Git, and MySQL versions.
3. Determine whether the repository already contains application code.
4. Review existing configuration and environment variables.
5. Identify reusable components and avoid overwriting existing work.
6. Document any assumptions and technical decisions.

Deliverables:

- Environment assessment.
- Architecture outline.
- Development setup instructions.

### Task 1.2 — Create the Application Structure

Use a maintainable monorepo structure.

Suggested layout:

- `apps/web` — Next.js dashboard.
- `apps/api` — NestJS backend.
- `apps/agent` — local execution agent.
- `packages/shared` — shared types, validation schemas, and API contracts.
- `packages/tool-adapters` — interfaces and supported AI tool adapters.
- `docs` — architecture, setup, operations, and security documentation.

Use TypeScript throughout the application where appropriate.

Configure:

- Package manager and workspace scripts.
- TypeScript configuration.
- ESLint and formatting.
- Environment variable validation.
- Development, build, lint, and test commands.
- `.gitignore` and example environment files.

Acceptance criteria:

- All applications can be installed and built.
- Shared types can be imported without circular dependencies.
- Secrets are not committed to Git.
- Setup instructions work on the target development machine.

---

## Phase 2: MySQL Database and Authentication

### Task 2.1 — Configure MySQL

Use MySQL 8+ with InnoDB tables and appropriate indexes.

Choose a consistent database access strategy compatible with NestJS. Use migrations to manage schema changes.

Implement:

- Database configuration.
- Connection pooling.
- Migration scripts.
- Seed data for development.
- Database health checks.
- Transaction support.
- Consistent UTC timestamps.

### Task 2.2 — Create the Database Schema

Create migrations for these entities:

1. `users`
2. `projects`
3. `project_repositories`
4. `project_environments`
5. `agents`
6. `agent_capabilities`
7. `tasks`
8. `task_dependencies`
9. `task_attempts`
10. `task_events`
11. `task_logs`
12. `task_artifacts`
13. `task_approvals`
14. `tool_configurations`
15. `audit_logs`

Requirements:

- Use primary and foreign keys.
- Add indexes for queue selection, status, priority, project ID, agent ID, and timestamps.
- Use suitable unique constraints.
- Store large logs in a way that does not make task-list queries slow.
- Avoid storing plaintext passwords, API tokens, or private keys.
- Keep schema changes versioned through migrations.

### Task 2.3 — Implement Authentication

Implement:

- Login and logout.
- Password hashing.
- Session or token-based authentication.
- Secure cookie settings where applicable.
- Rate limiting for authentication endpoints.
- Input validation.
- Role-based access control.

Initial roles:

- Administrator.
- Developer.
- Read-only viewer.

Acceptance criteria:

- Unauthenticated users cannot access protected APIs.
- Users cannot access projects or tasks without permission.
- Secrets are never returned in API responses.
- Database migrations can be applied to a clean installation.

---

## Phase 3: Project and Task Management

### Task 3.1 — Project Management

Allow authorized users to create, edit, view, archive, and delete projects.

Project fields:

- Project name.
- Description.
- Repository URL or local repository reference.
- Default branch.
- Project directory configuration.
- Supported technology stack.
- Setup instructions.
- Build and test commands.
- Allowed execution tools.
- Environment configuration references.
- Created by and timestamps.

Never transmit repository credentials or sensitive environment variables to unauthorized users.

### Task 3.2 — Task Management

Build a task creation form with:

- Task title.
- Detailed task instructions.
- Project.
- Target branch.
- Priority.
- Preferred AI tool.
- Execution mode.
- Dependencies.
- Required tests.
- Maximum attempts.
- Timeout.
- Approval requirements.
- Optional due date.

Execution modes:

- Automatic execution.
- Manual approval before execution.
- Manual approval before sensitive actions.

Implement:

- Draft creation.
- Queueing and cancellation.
- Task editing before execution.
- Task duplication.
- Task dependencies.
- Task search and filters.
- Task history.
- Retry of eligible failed tasks.

Do not allow arbitrary edits to a task's execution instructions after it has started. Require a controlled stop or create a new task version.

### Task 3.3 — Define the Task State Machine

Supported states:

- `DRAFT`
- `QUEUED`
- `CLAIMED`
- `RUNNING`
- `VERIFYING`
- `NEEDS_APPROVAL`
- `NEEDS_REVIEW`
- `COMPLETED`
- `FAILED`
- `BLOCKED`
- `CANCELLED`

Implement an explicit transition service that validates all state changes.

Examples:

- `DRAFT` → `QUEUED`
- `QUEUED` → `CLAIMED`
- `CLAIMED` → `RUNNING`
- `RUNNING` → `VERIFYING`
- `VERIFYING` → `COMPLETED`
- `VERIFYING` → `NEEDS_REVIEW`
- `RUNNING` → `NEEDS_APPROVAL`
- `CLAIMED` or `RUNNING` → `FAILED`
- Eligible failed tasks → `QUEUED` for retry

Record each transition with its timestamp, actor, reason, and related attempt.

Acceptance criteria:

- Invalid transitions are rejected.
- Every state change is auditable.
- Cancelled tasks cannot be accidentally restarted by a stale worker.
- Completed tasks cannot be re-executed without an explicit new action.

---

## Phase 4: Free MySQL-Backed Job Queue

### Task 4.1 — Implement Queue Processing

Build a queue service using MySQL rather than Redis or BullMQ.

Queue responsibilities:

- Find eligible queued tasks.
- Respect task priority and dependencies.
- Atomically claim a task.
- Assign the task to one agent.
- Record a unique execution attempt.
- Set a lease expiration.
- Update the task's status.
- Return the task's execution instructions to the authorized agent.

Use transactions and appropriate row-level locking for the selected MySQL version. `SELECT ... FOR UPDATE` may be used within a transaction where supported.

Do not implement claiming as an unprotected SELECT followed by an independent UPDATE.

### Task 4.2 — Implement Worker Polling

The local agent polls the server for eligible tasks.

Requirements:

- Configurable polling interval.
- Exponential backoff for temporary server errors.
- No aggressive polling when the queue is empty.
- One active task per agent by default.
- Agent capability matching.
- Project access validation.
- Dependency checks.
- Graceful shutdown.

Queue processing must not depend on a paid external queue service.

### Task 4.3 — Implement Leases and Heartbeats

Each active task must have:

- Task ID.
- Attempt ID.
- Assigned agent ID.
- Lease token or equivalent ownership proof.
- Lease expiration timestamp.
- Last heartbeat timestamp.

The agent sends heartbeats while working.

The server rejects updates from stale attempts or agents that no longer own the lease.

When a lease expires:

1. Mark the attempt as interrupted or lost.
2. Determine whether the task is safe to retry.
3. Retry eligible tasks within their configured attempt limit.
4. Move exhausted tasks to `FAILED`.
5. Record a recovery event.

Do not blindly re-run tasks that may have performed non-idempotent actions. Use idempotency controls and require review when execution safety is uncertain.

### Task 4.4 — Implement Retry and Timeout Policies

Support:

- Configurable maximum attempts.
- Retryable and non-retryable failures.
- Exponential retry delays with a maximum delay.
- Execution timeouts.
- Manual retry.
- Permanent failure reporting.

Persist the retry schedule in MySQL. Use a periodic worker or scheduler to find eligible tasks.

### Task 4.5 — Implement Queue Concurrency

Initial policy:

- One task at a time per agent.
- Multiple agents may process independent tasks.
- Project-specific concurrency limits are configurable.
- Tasks with unresolved dependencies remain blocked.
- Agents must advertise their supported tools and capabilities.

Acceptance criteria:

- Two agents cannot claim the same task attempt.
- A server restart does not lose queued jobs.
- A crashed agent does not permanently lock a task.
- Retry limits are respected.
- Task order follows the documented priority and fairness rules.

---

## Phase 5: Local Agent

### Task 5.1 — Build the Agent Application

Create a Node.js and TypeScript agent that can run as a background process on macOS.

The agent must:

- Register with the backend.
- Authenticate securely.
- Report online/offline status.
- Advertise its installed tools and capabilities.
- Poll for tasks.
- Create isolated workspaces.
- Execute supported commands.
- Send progress updates and logs.
- Send heartbeats.
- Upload execution reports.
- Recover from temporary network failures.
- Shut down gracefully.

Keep the agent headless initially. Add an Electron interface later if a desktop dashboard is needed.

### Task 5.2 — Agent Registration and Pairing

Implement a secure pairing process:

1. An administrator creates a short-lived pairing code.
2. The agent uses the code to request registration.
3. The server validates the code and permissions.
4. The server issues a revocable agent credential.
5. The agent stores its credential securely.
6. The dashboard displays the registered agent and its status.

Do not use a permanent shared secret embedded in application source code.

### Task 5.3 — Workspace Management

For each task:

- Validate the configured repository.
- Prepare a clean workspace.
- Fetch the appropriate branch.
- Create a Git worktree or equivalent isolated workspace.
- Prevent concurrent tasks from modifying the same working directory.
- Record the starting commit.
- Keep task changes isolated.
- Collect the resulting commit and diff.
- Clean up workspaces according to retention rules.

Never execute arbitrary repository scripts automatically without applying the configured security policy.

### Task 5.4 — Agent Configuration

Support configuration for:

- Server URL.
- Agent ID.
- Workspace root.
- Polling interval.
- Heartbeat interval.
- Allowed repositories.
- Allowed tools.
- Maximum concurrency.
- Log retention.
- Resource limits.

Acceptance criteria:

- The agent reconnects after a temporary network failure.
- It rejects tasks for unauthorized projects or tools.
- It does not expose authentication credentials in logs.
- It can be stopped without corrupting task ownership.

---

## Phase 6: AI Development Tool Adapters

### Task 6.1 — Define a Common Adapter Interface

Create a common adapter contract for:

- Capability discovery.
- Availability checks.
- Task execution.
- Progress reporting.
- Cancellation.
- Process termination.
- Exit code collection.
- Log streaming.
- Result collection.
- Error classification.

Each adapter must be independent of the core queue implementation.

### Task 6.2 — Implement the First Supported Tool

Start with one tool that exposes a supported CLI or programmatic interface and is available in the target environment.

Implement:

- Tool detection.
- Version detection.
- Authentication preflight.
- Prompt or instruction delivery.
- Execution timeout.
- Output capture.
- Exit code handling.
- Cancellation.
- Result collection.

Validate the actual tool interface and authentication behavior before implementation. Do not assume that a desktop subscription automatically authorizes API access or unattended CLI execution.

### Task 6.3 — Add Codex Integration

Investigate and use the supported Codex integration method available to the user.

Requirements:

- Detect installation and version.
- Verify authentication.
- Pass task instructions and workspace configuration.
- Capture supported output and exit status.
- Handle cancellation and timeout.
- Respect tool permissions and approval requirements.

### Task 6.4 — Add Claude Code Integration

Implement a separate adapter for the supported Claude Code interface.

Requirements:

- Installation and authentication checks.
- Workspace selection.
- Task instruction delivery.
- Progress and log collection.
- Error handling.
- Cancellation and timeout.
- Permission-aware execution.

### Task 6.5 — Investigate Antigravity Integration

First verify the available supported integration options for Antigravity.

If a supported CLI or programmatic interface exists, build an adapter around it.

If only a desktop GUI workflow is supported:

- Treat GUI automation as a separate optional integration.
- Use explicit user approval and visible execution status.
- Handle focus changes, dialogs, timeouts, and UI failures.
- Do not claim reliable unattended support until tested.
- Never bypass authentication, usage restrictions, or security prompts.

Keep the adapter interface ready so this integration can be improved independently.

Acceptance criteria:

- The core system does not depend on one AI vendor.
- A missing tool is reported clearly.
- Failed tool executions are captured as structured errors.
- Tool-specific authentication and permissions are respected.

---

## Phase 7: Verification, Reports, and Approvals

### Task 7.1 — Automated Verification

After the AI tool finishes:

1. Inspect the exit status.
2. Check whether files changed.
3. Run configured formatting checks.
4. Run linting.
5. Run unit tests.
6. Run integration tests where configured.
7. Run the project build.
8. Collect the Git diff and changed-file list.
9. Generate a structured verification report.

Verification commands must come from trusted project configuration and must be subject to execution restrictions.

### Task 7.2 — Result Reporting

Store:

- Task summary.
- Tool used and version.
- Start and finish times.
- Execution attempt.
- Exit status.
- Changed files.
- Git diff reference.
- Test results.
- Build results.
- Error summary.
- Relevant logs.
- Artifact references.

Show a concise summary in the dashboard and allow authorized users to inspect full details.

### Task 7.3 — Human Approval

Require approval for configured sensitive operations, such as:

- Production deployment.
- Database migrations against shared environments.
- Destructive commands.
- Credential or permission changes.
- External publishing.
- Actions outside the approved workspace.

Allow authorized users to approve, reject, or request changes.

Record who approved the operation, when, and what was approved.

Do not automatically publish, merge, or deploy merely because an AI task reports success.

---

## Phase 8: Backend API and Real-Time Updates

### Task 8.1 — Implement Versioned REST APIs

Create versioned endpoints for:

- Authentication.
- Users and roles.
- Projects and repositories.
- Task creation and updates.
- Task queueing and cancellation.
- Task attempts and logs.
- Agent registration and status.
- Task claiming.
- Heartbeats and leases.
- Progress reporting.
- Task completion and failure.
- Approvals.
- Artifacts and reports.
- Audit logs.

Use request validation, response schemas, pagination, authorization, rate limits, and consistent error formats.

### Task 8.2 — Real-Time Updates

Use WebSockets to notify the dashboard of:

- Task status changes.
- Task progress.
- Agent connection changes.
- Log availability.
- Verification results.
- Approval requests.

WebSockets are for live notifications, not the source of truth. Persist all important task and agent state in MySQL so clients can recover after reconnecting.

Acceptance criteria:

- Reconnecting clients receive current state.
- Missed notifications can be recovered through the API.
- Users cannot subscribe to unauthorized project events.

---

## Phase 9: Dashboard UI

Build these screens:

1. Login.
2. Main dashboard.
3. Project list.
4. Project details.
5. Repository configuration.
6. Task creation and editing.
7. Task queue.
8. Task execution details.
9. Live task logs.
10. Agent management.
11. AI tool configuration.
12. Approval center.
13. Execution history.
14. Verification reports.
15. Settings and user management.
16. Audit logs.

Dashboard metrics:

- Total tasks.
- Queued tasks.
- Running tasks.
- Completed tasks.
- Failed tasks.
- Online agents.
- Recent activity.
- Success and failure rates.

UI requirements:

- Responsive layout.
- Clear loading, empty, and error states.
- Search, sorting, and filtering.
- Confirmation dialogs for destructive actions.
- Accessible forms and keyboard navigation.
- No fake metrics or hard-coded task results in production screens.

---

## Phase 10: Security and Reliability

Implement:

- HTTPS for server communications.
- Secure agent authentication and credential rotation.
- Least-privilege permissions.
- Repository and workspace allowlists.
- Command execution restrictions.
- Environment variable and secret redaction.
- Request validation.
- Rate limiting.
- Audit logs.
- Log size limits and retention.
- Task cancellation and process-tree termination.
- Workspace isolation.
- Database backups and restore testing.
- Dependency and vulnerability checks.
- Safe error messages that do not expose secrets.

Threat model:

- Malicious task instructions.
- Prompt injection from repository files.
- Malicious dependencies or scripts.
- Unauthorized repository access.
- Compromised agent credentials.
- Duplicate task execution.
- Agent disconnection.
- Stale worker updates.
- Resource exhaustion.
- Accidental destructive commands.

AI-generated instructions and repository contents must be treated as untrusted input. AI tools must not be given unrestricted access to the user's machine, secrets, or production systems.

---

## Phase 11: Testing

### Unit Tests

- State transition validation.
- Queue eligibility.
- Priority rules.
- Retry calculation.
- Lease expiration.
- Authorization.
- Tool adapter error handling.

### Integration Tests

- MySQL migrations.
- Atomic task claiming.
- Concurrent workers.
- Task dependency resolution.
- Agent registration.
- Heartbeat processing.
- Task completion and failure.
- WebSocket notifications.
- Approval workflow.

### Failure and Recovery Tests

- Server restarts with queued tasks.
- Agent crashes while running a task.
- Network disconnects and reconnects.
- Lease expiration during execution.
- Duplicate completion requests.
- Invalid or expired credentials.
- Tool installation missing.
- Tool authentication failure.
- Build or test failure.
- Cancellation during execution.

### End-to-End Test

1. Create a project.
2. Register a local agent.
3. Create and queue a development task.
4. Confirm that the agent claims it.
5. Execute it in an isolated workspace.
6. Observe progress in the dashboard.
7. Run configured verification.
8. Save the report and Git diff.
9. Confirm the final state.
10. Create a second task and verify sequential execution on the same agent.

---

## Phase 12: Deployment and Documentation

### Server

- Configure environment variables.
- Deploy the web dashboard and API.
- Configure MySQL and backups.
- Configure HTTPS.
- Set up application process management.
- Configure logs and health checks.
- Run migrations safely.
- Document restore procedures.

### Local Agent

- Provide installation instructions for macOS.
- Provide secure pairing instructions.
- Provide agent start and stop commands.
- Document supported AI tool prerequisites.
- Document permissions and workspace settings.
- Add an update strategy.

### Documentation Deliverables

- Architecture document.
- Database schema and ER diagram.
- API documentation.
- Local setup guide.
- Server deployment guide.
- Agent installation guide.
- AI tool integration guide.
- Security model.
- Troubleshooting guide.
- Test plan.
- Backup and recovery guide.

---

## Final Acceptance Criteria

The MVP is ready when:

1. An authorized user can create a project and a development task.
2. The task is persisted in MySQL.
3. The MySQL-backed queue can safely assign it to one eligible agent.
4. The local agent authenticates securely and executes the task using one verified supported tool.
5. The dashboard displays task progress and logs.
6. The agent can report completion, failure, or cancellation.
7. Verification results and Git changes are recorded.
8. Retries respect configured limits.
9. Agent disconnection and server restart recovery work as designed.
10. Concurrent workers cannot claim the same task attempt.
11. Sensitive operations can be gated by approval.
12. Automated tests pass.
13. Setup, deployment, and troubleshooting documentation is complete.
14. The initial version does not require Redis, BullMQ, or paid external queue infrastructure.

## Implementation Rule

Build and verify one phase at a time. Do not start all integrations in parallel. Begin with repository inspection, foundation, MySQL schema, and task state management. Then implement the queue and local agent before adding multiple AI tool adapters.

## 4. Task execution order

Use this order to minimize rework:

| Order | Work item                              | Completion gate                                                   |
| ----- | -------------------------------------- | ----------------------------------------------------------------- |
| 1     | Repository inspection and architecture | Existing code and requirements documented                         |
| 2     | Monorepo and development setup         | All applications build                                            |
| 3     | MySQL schema and migrations            | Clean database setup succeeds                                     |
| 4     | Authentication and permissions         | Protected APIs are tested                                         |
| 5     | Projects and task CRUD                 | Dashboard can create and manage tasks                             |
| 6     | Task state machine                     | Invalid state changes are rejected                                |
| 7     | MySQL-backed queue                     | Concurrent claiming tests pass                                    |
| 8     | Agent registration and heartbeat       | Agent stays connected and reports status                          |
| 9     | Workspace and Git isolation            | Tasks cannot overwrite each other's workspace                     |
| 10    | First AI tool adapter                  | One real development task executes successfully                   |
| 11    | Verification and reporting             | Build/test results and diffs are saved                            |
| 12    | Retries and crash recovery             | Failure scenarios pass                                            |
| 13    | Additional tool adapters               | Codex, Claude Code, and Antigravity support verified individually |
| 14    | Security and end-to-end testing        | Acceptance criteria pass                                          |
| 15    | Deployment and documentation           | Reproducible installation and recovery                            |

## 5. How to give tasks to your coding tool

Give the coding tool the master specification above, then ask it to execute only one implementation phase at a time.

You are the lead software architect and senior full-stack engineer for the AI Development Automation Platform.

Use the approved project specification as the source of truth.

Your current assignment is to implement Phase 1 only.

Instructions:

1. Inspect the existing repository before modifying anything.
2. Identify the existing technology stack and preserve reusable code.
3. Create or update the project structure required by Phase 1.
4. Configure TypeScript, package management, linting, formatting, environment validation, and development scripts.
5. Create the shared package structure and document application boundaries.
6. Add example environment files without secrets.
7. Write setup instructions for macOS.
8. Run the available build, lint, and test commands.
9. Fix issues introduced by your changes.
10. Report every created or modified file, commands executed, test results, assumptions, and remaining issues.

Constraints:

- Do not implement future phases prematurely.
- Do not introduce PostgreSQL, Redis, or BullMQ.
- MySQL is the required persistent database.
- The initial queue must be implemented using MySQL-backed processing.
- Do not invent or assume unsupported integrations with Codex, Claude Code, or Antigravity.
- Do not overwrite existing work without inspecting it.
- Do not claim that a test passed unless you actually ran it.
- Do not leave essential setup steps undocumented.

Completion condition:

Phase 1 is complete only when the repository structure is understandable, the development environment is documented, and the available checks pass.

After completion, stop and provide a concise implementation report. Wait for the next phase assignment.

Important design decision: For your first working version, get the complete flow running with one local Mac agent and one verified AI tool. Once that flow can create a task, execute it, verify the code, and report the result reliably, add the other tool adapters. This reduces the risk of building a large dashboard before the core automation actually works.