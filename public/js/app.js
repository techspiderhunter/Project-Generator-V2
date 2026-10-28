// public/js/app.js
// Client logic for Task Automator Control Panel

let allTasks = [];
let allPrompts = [];
let activeTab = 'tasks';
let currentDetailTask = null;

document.addEventListener('DOMContentLoaded', () => {
    loadTasks();
    loadPrompts();
    // Auto refresh tasks every 6 seconds
    setInterval(() => {
        if (activeTab === 'tasks') {
            loadTasks(true);
        }
    }, 6000);
});

// Tab Switching
function switchTab(tab) {
    activeTab = tab;
    const tabTasks = document.getElementById('tab-tasks');
    const tabPrompts = document.getElementById('tab-prompts');
    const btnTasks = document.getElementById('tab-btn-tasks');
    const btnPrompts = document.getElementById('tab-btn-prompts');

    if (tab === 'tasks') {
        tabTasks.classList.remove('hidden');
        tabPrompts.classList.add('hidden');
        btnTasks.className = 'px-3.5 py-1.5 text-xs font-medium rounded-lg transition-all bg-sky-500 text-white shadow-sm flex items-center gap-1.5';
        btnPrompts.className = 'px-3.5 py-1.5 text-xs font-medium rounded-lg transition-all text-slate-400 hover:text-slate-200 hover:bg-slate-800 flex items-center gap-1.5';
        loadTasks();
    } else {
        tabTasks.classList.add('hidden');
        tabPrompts.classList.remove('hidden');
        btnPrompts.className = 'px-3.5 py-1.5 text-xs font-medium rounded-lg transition-all bg-sky-500 text-white shadow-sm flex items-center gap-1.5';
        btnTasks.className = 'px-3.5 py-1.5 text-xs font-medium rounded-lg transition-all text-slate-400 hover:text-slate-200 hover:bg-slate-800 flex items-center gap-1.5';
        loadPrompts();
    }
}

// ---------------- TASK OPERATIONS ---------------- //

async function loadTasks(silent = false) {
    try {
        const filterStatus = document.getElementById('filter-status').value;
        const url = filterStatus ? `../api/tasks.php?status=${encodeURIComponent(filterStatus)}` : '../api/tasks.php';
        const res = await fetch(url);
        const data = await res.json();

        if (data.success) {
            allTasks = data.tasks || [];
            updateDbBadge(data.driver);
            updateStats(allTasks);
            renderTasksTable(allTasks);
        }
    } catch (err) {
        if (!silent) showToast('Failed to load tasks: ' + err.message, 'error');
    }
}

function updateDbBadge(driver) {
    const badge = document.getElementById('db-badge');
    if (!badge) return;
    if (driver === 'mysql') {
        badge.textContent = 'MySQL: Connected';
        badge.className = 'text-xs px-2 py-0.5 rounded-full font-mono font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/20';
    } else {
        badge.textContent = 'SQLite (Local): Active';
        badge.className = 'text-xs px-2 py-0.5 rounded-full font-mono font-medium bg-sky-500/10 text-sky-400 border border-sky-500/20';
    }
}

function updateStats(tasks) {
    const total = tasks.length;
    const pending = tasks.filter(t => t.status === 'pending').length;
    const inProgress = tasks.filter(t => ['planning', 'developing', 'testing'].includes(t.status)).length;
    const completed = tasks.filter(t => t.status === 'completed').length;

    document.getElementById('stat-total').textContent = total;
    document.getElementById('stat-pending').textContent = pending;
    document.getElementById('stat-in-progress').textContent = inProgress;
    document.getElementById('stat-completed').textContent = completed;
}

function renderTasksTable(tasks) {
    const tbody = document.getElementById('tasks-table-body');
    if (!tbody) return;

    if (tasks.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" class="py-12 text-center text-slate-500">
                    <div class="flex flex-col items-center justify-center space-y-2">
                        <i data-lucide="inbox" class="w-8 h-8 text-slate-600"></i>
                        <p class="text-xs">No tasks found. Create a new task above or run Antigravity IDE!</p>
                    </div>
                </td>
            </tr>
        `;
        lucide.createIcons();
        return;
    }

    tbody.innerHTML = tasks.map(t => {
        const statusBadge = getStatusBadge(t.status);
        const roleBadge = getRoleBadge(t.current_role);
        const artifacts = getArtifactsBadge(t);

        return `
            <tr class="hover:bg-slate-900/40 transition">
                <td class="py-3 px-4 font-mono font-medium text-slate-400">#${t.id}</td>
                <td class="py-3 px-4">
                    <div class="font-semibold text-slate-200 text-xs">${escapeHtml(t.title)}</div>
                    <div class="text-[11px] text-slate-400 line-clamp-1 mt-0.5">${escapeHtml(t.description)}</div>
                </td>
                <td class="py-3 px-4">${statusBadge}</td>
                <td class="py-3 px-4">${roleBadge}</td>
                <td class="py-3 px-4">${artifacts}</td>
                <td class="py-3 px-4 text-right">
                    <div class="flex items-center justify-end space-x-1">
                        ${t.status !== 'completed' ? `
                            <button onclick="runTaskStep(${t.id})" title="Execute 1 step" class="p-1.5 rounded-lg bg-sky-500/10 hover:bg-sky-500/20 text-sky-400 border border-sky-500/30 transition">
                                <i data-lucide="play" class="w-3.5 h-3.5"></i>
                            </button>
                            <button onclick="runTaskFull(${t.id})" title="Run full pipeline" class="p-1.5 rounded-lg bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 transition">
                                <i data-lucide="zap" class="w-3.5 h-3.5"></i>
                            </button>
                        ` : ''}
                        
                        <button onclick="viewTaskDetails(${t.id})" title="View Plan & Code Outputs" class="p-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 transition">
                            <i data-lucide="file-text" class="w-3.5 h-3.5"></i>
                        </button>

                        <button onclick="retryTask(${t.id})" title="Reset to pending" class="p-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-amber-400 transition">
                            <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i>
                        </button>

                        <button onclick="deleteTask(${t.id})" title="Delete task" class="p-1.5 rounded-lg bg-slate-800 hover:bg-rose-950 text-rose-400 transition">
                            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');

    lucide.createIcons();
}

function getStatusBadge(status) {
    const map = {
        pending: 'bg-slate-800 text-slate-300 border-slate-700',
        planning: 'bg-blue-950/60 text-blue-400 border-blue-800 animate-pulse',
        planned: 'bg-blue-900/30 text-blue-300 border-blue-700',
        developing: 'bg-amber-950/60 text-amber-400 border-amber-800 animate-pulse',
        developed: 'bg-amber-900/30 text-amber-300 border-amber-700',
        testing: 'bg-purple-950/60 text-purple-400 border-purple-800 animate-pulse',
        tested: 'bg-purple-900/30 text-purple-300 border-purple-700',
        completed: 'bg-emerald-950/60 text-emerald-300 border-emerald-800',
        failed: 'bg-rose-950/60 text-rose-400 border-rose-800'
    };
    const cls = map[status] || 'bg-slate-800 text-slate-300';
    return `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium border ${cls}">${status}</span>`;
}

function getRoleBadge(role) {
    if (!role || role === 'none') {
        return `<span class="text-slate-600 text-[11px]">—</span>`;
    }
    const color = role === 'planner' ? 'text-blue-400' : (role === 'developer' ? 'text-amber-400' : 'text-purple-400');
    return `<span class="font-mono text-[11px] font-medium ${color}">${role}</span>`;
}

function getArtifactsBadge(task) {
    const badges = [];
    if (task.plan_output) badges.push('<span title="Plan generated" class="px-1.5 py-0.5 rounded bg-blue-950 text-blue-300 border border-blue-800 text-[9px] font-mono">Plan</span>');
    if (task.dev_output) badges.push('<span title="Code changes recorded" class="px-1.5 py-0.5 rounded bg-amber-950 text-amber-300 border border-amber-800 text-[9px] font-mono">Code</span>');
    if (task.test_output) badges.push('<span title="Test results recorded" class="px-1.5 py-0.5 rounded bg-purple-950 text-purple-300 border border-purple-800 text-[9px] font-mono">Test</span>');
    return badges.length > 0 ? `<div class="flex items-center gap-1">${badges.join('')}</div>` : `<span class="text-slate-600 text-[11px]">None</span>`;
}

async function handleCreateTask(e) {
    e.preventDefault();
    const btn = document.getElementById('btn-submit-task');
    const title = document.getElementById('task-title').value.trim();
    const description = document.getElementById('task-desc').value.trim();
    const projectPath = document.getElementById('task-path').value.trim();

    if (!title || !description) return;

    btn.disabled = true;
    btn.innerHTML = `<i data-lucide="loader" class="w-4 h-4 animate-spin"></i> Creating...`;
    lucide.createIcons();

    try {
        const res = await fetch('../api/tasks.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ title, description, project_path: projectPath })
        });
        const data = await res.json();
        if (data.success) {
            showToast(`Task #${data.task_id} added to execution queue!`, 'success');
            document.getElementById('create-task-form').reset();
            loadTasks();
        } else {
            showToast(data.error || 'Failed to create task', 'error');
        }
    } catch (err) {
        showToast('Error: ' + err.message, 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = `<i data-lucide="check" class="w-4 h-4"></i> Add Task to Queue`;
        lucide.createIcons();
    }
}

async function runTaskStep(taskId) {
    showToast(`Advancing Task #${taskId} by 1 step...`, 'info');
    try {
        const res = await fetch('../api/run_step.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ task_id: taskId, action: 'step' })
        });
        const data = await res.json();
        if (data.success) {
            showToast(`Task #${taskId}: ${data.role || 'Step'} &rarr; ${data.status || 'updated'}`, 'success');
            loadTasks();
        } else {
            showToast(data.error || 'Step failed', 'error');
            loadTasks();
        }
    } catch (err) {
        showToast('Error: ' + err.message, 'error');
    }
}

async function runTaskFull(taskId) {
    showToast(`Starting full autonomous pipeline for Task #${taskId}...`, 'info');
    try {
        const res = await fetch('../api/run_step.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ task_id: taskId, action: 'run_full' })
        });
        const data = await res.json();
        if (data.success) {
            showToast(`Task #${taskId} executed completely: ${data.final_status}!`, 'success');
            loadTasks();
        } else {
            showToast(data.error || 'Execution stopped with error', 'error');
            loadTasks();
        }
    } catch (err) {
        showToast('Error: ' + err.message, 'error');
    }
}

async function pickAndRunNextTask() {
    const btn = document.getElementById('btn-pick-run');
    btn.disabled = true;
    showToast('Picking next pending task...', 'info');

    try {
        const res = await fetch('../api/run_step.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'pick_and_run' })
        });
        const data = await res.json();
        if (data.success) {
            showToast(`Task #${data.task_id} picked and completed!`, 'success');
            loadTasks();
        } else {
            showToast(data.message || data.error || 'No pending tasks found', 'info');
        }
    } catch (err) {
        showToast('Error: ' + err.message, 'error');
    } finally {
        btn.disabled = false;
    }
}

async function retryTask(taskId) {
    try {
        const res = await fetch(`../api/tasks.php?id=${taskId}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'retry' })
        });
        const data = await res.json();
        if (data.success) {
            showToast(`Task #${taskId} reset to pending.`, 'success');
            loadTasks();
        }
    } catch (err) {
        showToast('Error: ' + err.message, 'error');
    }
}

async function deleteTask(taskId) {
    if (!confirm(`Are you sure you want to delete Task #${taskId}?`)) return;
    try {
        const res = await fetch(`../api/tasks.php?id=${taskId}`, {
            method: 'DELETE'
        });
        const data = await res.json();
        if (data.success) {
            showToast(`Task #${taskId} deleted.`, 'success');
            loadTasks();
        }
    } catch (err) {
        showToast('Error: ' + err.message, 'error');
    }
}

// ---------------- TASK DETAIL MODAL ---------------- //

async function viewTaskDetails(taskId) {
    try {
        const res = await fetch(`../api/tasks.php?id=${taskId}`);
        const data = await res.json();
        if (!data.success) return;

        const task = data.task;
        currentDetailTask = task;

        document.getElementById('modal-task-id').textContent = `#${task.id}`;
        document.getElementById('modal-task-title').textContent = task.title;
        document.getElementById('modal-task-status-badge').innerHTML = getStatusBadge(task.status);

        document.getElementById('detail-tab-plan').textContent = task.plan_output || '// No plan output generated yet.';
        document.getElementById('detail-tab-dev').textContent = task.dev_output || '// No developer code output generated yet.';
        document.getElementById('detail-tab-test').textContent = task.test_output || '// No test audit report generated yet.';

        const errTab = document.getElementById('tab-modal-error');
        const errContent = document.getElementById('detail-tab-error');
        if (task.error_log) {
            errTab.classList.remove('hidden');
            errTab.classList.add('flex');
            errContent.textContent = task.error_log;
        } else {
            errTab.classList.add('hidden');
            errTab.classList.remove('flex');
        }

        switchDetailTab('plan');
        document.getElementById('modal-task-detail').classList.remove('hidden');
    } catch (err) {
        showToast('Failed to load task details: ' + err.message, 'error');
    }
}

function switchDetailTab(tab) {
    const tabs = ['plan', 'dev', 'test', 'error'];
    tabs.forEach(t => {
        const el = document.getElementById(`detail-tab-${t}`);
        const btn = document.getElementById(`tab-modal-${t}`);
        if (!el || !btn) return;
        if (t === tab) {
            el.classList.remove('hidden');
            btn.className = 'px-3 py-1.5 font-medium border-b-2 border-sky-500 text-sky-400 flex items-center gap-1.5';
        } else {
            el.classList.add('hidden');
            btn.className = 'px-3 py-1.5 font-medium border-b-2 border-transparent text-slate-400 hover:text-slate-200 flex items-center gap-1.5';
        }
    });
}

// ---------------- PROMPTS OPERATIONS ---------------- //

async function loadPrompts() {
    try {
        const res = await fetch('../api/prompts.php');
        const data = await res.json();
        if (data.success) {
            allPrompts = data.prompts || [];
            renderPrompts(allPrompts);
        }
    } catch (err) {
        showToast('Failed to load prompts: ' + err.message, 'error');
    }
}

function renderPrompts(prompts) {
    const grid = document.getElementById('prompts-grid');
    if (!grid) return;

    const roles = ['planner', 'developer', 'tester'];
    grid.innerHTML = roles.map(role => {
        const prompt = prompts.find(p => p.role === role && p.is_active == 1) || {
            id: 0,
            role: role,
            title: `Default ${capitalize(role)} Prompt`,
            system_prompt: '',
            user_prompt_template: '',
            model_name: 'gemini-1.5-pro',
            temperature: 0.20
        };

        const icon = role === 'planner' ? 'compass' : (role === 'developer' ? 'code-2' : 'check-circle-2');
        const color = role === 'planner' ? 'text-blue-400' : (role === 'developer' ? 'text-amber-400' : 'text-purple-400');

        return `
            <div class="bg-slate-950 border border-slate-800 rounded-2xl p-5 shadow-xl flex flex-col justify-between space-y-4">
                <div class="space-y-3">
                    <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                        <div class="flex items-center space-x-2">
                            <i data-lucide="${icon}" class="w-4 h-4 ${color}"></i>
                            <h3 class="text-sm font-bold text-white uppercase tracking-wider">${role} Role</h3>
                        </div>
                        <span class="text-[10px] font-mono px-2 py-0.5 rounded bg-slate-900 border border-slate-800 text-slate-400">v${prompt.version || 1} Active</span>
                    </div>

                    <div>
                        <label class="block text-[11px] font-medium text-slate-400 mb-1">Prompt Name</label>
                        <input type="text" id="prompt-title-${prompt.id}" value="${escapeHtml(prompt.title)}" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-1.5 text-xs text-slate-200">
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-[11px] font-medium text-slate-400 mb-1">Target Model</label>
                            <input type="text" id="prompt-model-${prompt.id}" value="${escapeHtml(prompt.model_name)}" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-1.5 text-xs text-slate-200">
                        </div>
                        <div>
                            <label class="block text-[11px] font-medium text-slate-400 mb-1">Temperature</label>
                            <input type="number" step="0.05" min="0" max="1" id="prompt-temp-${prompt.id}" value="${prompt.temperature}" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-1.5 text-xs text-slate-200">
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11px] font-medium text-slate-400 mb-1">System Instructions</label>
                        <textarea id="prompt-sys-${prompt.id}" rows="3" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-1.5 text-xs text-slate-200">${escapeHtml(prompt.system_prompt)}</textarea>
                    </div>

                    <div>
                        <label class="block text-[11px] font-medium text-slate-400 mb-1">User Prompt Template (with {{vars}})</label>
                        <textarea id="prompt-user-${prompt.id}" rows="6" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-1.5 text-xs font-mono text-slate-200">${escapeHtml(prompt.user_prompt_template)}</textarea>
                    </div>
                </div>

                <button onclick="savePrompt(${prompt.id})" class="w-full py-2 bg-slate-800 hover:bg-slate-700 text-sky-400 rounded-lg text-xs font-semibold transition flex items-center justify-center gap-1.5 border border-slate-700">
                    <i data-lucide="save" class="w-3.5 h-3.5"></i>
                    Save ${capitalize(role)} Prompt
                </button>
            </div>
        `;
    }).join('');

    lucide.createIcons();
}

async function savePrompt(promptId) {
    const title = document.getElementById(`prompt-title-${promptId}`).value;
    const model_name = document.getElementById(`prompt-model-${promptId}`).value;
    const temperature = parseFloat(document.getElementById(`prompt-temp-${promptId}`).value);
    const system_prompt = document.getElementById(`prompt-sys-${promptId}`).value;
    const user_prompt_template = document.getElementById(`prompt-user-${promptId}`).value;

    try {
        const res = await fetch(`../api/prompts.php?id=${promptId}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ title, model_name, temperature, system_prompt, user_prompt_template })
        });
        const data = await res.json();
        if (data.success) {
            showToast('Prompt template saved successfully!', 'success');
            loadPrompts();
        } else {
            showToast(data.error || 'Failed to update prompt', 'error');
        }
    } catch (err) {
        showToast('Error: ' + err.message, 'error');
    }
}

// ---------------- ANTIGRAVITY MODAL ---------------- //

function openAntigravityModal() {
    const promptText = `You are the Autonomous Task Execution Engine for this local project. 
Your goal is to autonomously pick tasks from the MySQL database (\`task_automate\`) one by one, execute them through a strict 3-role sequential workflow (Planner -> Developer -> Tester), and update their status in the database until completion.

### Execution Protocol:
Work in a loop. For each task:

#### Step 1: Pick Next Pending Task
Run command:
php /Applications/XAMPP/xamppfiles/htdocs/Project/automation-development/runner/db_helper.php pick_task

If no task is returned (or \`success: false\`), output:
"No pending tasks found. All tasks are completed." and STOP.

#### Step 2: Role 1 — Planner Agent
1. Fetch active prompt for role \`planner\`:
   php /Applications/XAMPP/xamppfiles/htdocs/Project/automation-development/runner/db_helper.php get_prompt planner
2. Adopt the Persona & instructions from \`system_prompt\`.
3. In \`user_prompt_template\`, substitute \`{{task_id}}\`, \`{{title}}\`, \`{{description}}\`, \`{{project_path}}\`.
4. Produce a detailed step-by-step Technical Implementation Plan.
5. Save the generated plan to \`/tmp/plan.md\`.
6. Update DB status:
   php /Applications/XAMPP/xamppfiles/htdocs/Project/automation-development/runner/db_helper.php update_status <TASK_ID> planned none plan_output /tmp/plan.md

#### Step 3: Role 2 — Developer Agent
1. Update status to developing:
   php /Applications/XAMPP/xamppfiles/htdocs/Project/automation-development/runner/db_helper.php update_status <TASK_ID> developing developer
2. Fetch active prompt for role \`developer\`:
   php /Applications/XAMPP/xamppfiles/htdocs/Project/automation-development/runner/db_helper.php get_prompt developer
3. Adopt the Persona from \`system_prompt\`.
4. In \`user_prompt_template\`, substitute \`{{plan_output}}\` with the plan from Step 2, alongside \`{{title}}\` and \`{{description}}\`.
5. IMPLEMENT THE CODE: Create or update files in workspace according to plan.
6. Summarize the changes made and file paths into \`/tmp/dev.md\`.
7. Update DB status:
   php /Applications/XAMPP/xamppfiles/htdocs/Project/automation-development/runner/db_helper.php update_status <TASK_ID> developed none dev_output /tmp/dev.md

#### Step 4: Role 3 — Tester Agent
1. Update status to testing:
   php /Applications/XAMPP/xamppfiles/htdocs/Project/automation-development/runner/db_helper.php update_status <TASK_ID> testing tester
2. Fetch active prompt for role \`tester\`:
   php /Applications/XAMPP/xamppfiles/htdocs/Project/automation-development/runner/db_helper.php get_prompt tester
3. Adopt the Persona from \`system_prompt\`.
4. Verify the created files: run syntax checks (e.g. \`php -l\`), verify functionality, or run tests.
5. Compile test summary and state explicit verdict \`[PASS]\` or \`[FAIL]\` into \`/tmp/test.md\`.
6. Update DB status:
   php /Applications/XAMPP/xamppfiles/htdocs/Project/automation-development/runner/db_helper.php update_status <TASK_ID> tested none test_output /tmp/test.md

#### Step 5: Complete & Repeat
1. If verdict is \`[PASS]\`:
   php /Applications/XAMPP/xamppfiles/htdocs/Project/automation-development/runner/db_helper.php update_status <TASK_ID> completed none
   Report: "Task #<ID> successfully completed."
2. Immediately proceed to Step 1 to pick the next pending task. Continue until all tasks are complete.`;

    document.getElementById('ag-prompt-text').value = promptText;
    document.getElementById('modal-antigravity').classList.remove('hidden');
}

function copyAgPrompt() {
    const text = document.getElementById('ag-prompt-text').value;
    navigator.clipboard.writeText(text).then(() => {
        const btn = document.getElementById('btn-copy-ag');
        btn.innerHTML = `<i data-lucide="check" class="w-3.5 h-3.5"></i> Copied!`;
        lucide.createIcons();
        showToast('Antigravity IDE Prompt copied to clipboard!', 'success');
        setTimeout(() => {
            btn.innerHTML = `<i data-lucide="copy" class="w-3.5 h-3.5"></i> Copy Prompt`;
            lucide.createIcons();
        }, 2500);
    });
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.add('hidden');
}

// ---------------- UTILS ---------------- //

function showToast(message, type = 'info') {
    const container = document.getElementById('toast-container');
    if (!container) return;

    const colors = {
        success: 'border-emerald-500/40 bg-emerald-950/90 text-emerald-200',
        error: 'border-rose-500/40 bg-rose-950/90 text-rose-200',
        info: 'border-sky-500/40 bg-slate-900/90 text-sky-200'
    };

    const toast = document.createElement('div');
    toast.className = `p-3.5 rounded-xl border shadow-xl text-xs backdrop-blur pointer-events-auto flex items-center space-x-2 animate-in slide-in-from-bottom duration-200 ${colors[type] || colors.info}`;
    toast.innerHTML = `<span>${escapeHtml(message)}</span>`;

    container.appendChild(toast);
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transition = 'opacity 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 3500);
}

function escapeHtml(str) {
    if (!str) return '';
    return str.toString()
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function capitalize(str) {
    return str.charAt(0).toUpperCase() + str.slice(1);
}

