<?php
session_start();
include 'db_connect.php';

// Check if the user is logged in and is a manager
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'Manager') {
    header('Location: login.php');
    exit();
}

class WeeklyShiftAssigner {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function assignWeeklyShifts($user_id, $start_date, $shift_time) {
        if (empty($user_id) || empty($shift_time)) {
            return ['success' => false, 'message' => 'Invalid user ID or shift time'];
        }

        $week_dates      = $this->getWeekDates($start_date);
        $inserted_shifts = [];
        $updated_count   = 0;
        $inserted_count  = 0;

        mysqli_begin_transaction($this->conn);

        try {
            foreach ($week_dates as $date) {
                if (!$this->shiftExists($user_id, $date)) {
                    $stmt = mysqli_prepare($this->conn,
                        "INSERT INTO schedules (user_id, schedules_date, schedules_time) VALUES (?, ?, ?)"
                    );
                    mysqli_stmt_bind_param($stmt, 'sss', $user_id, $date, $shift_time);
                    mysqli_stmt_execute($stmt);
                    $inserted_shifts[] = [
                        'shift_id'   => mysqli_insert_id($this->conn),
                        'shift_date' => $date,
                        'shift_time' => $shift_time
                    ];
                    mysqli_stmt_close($stmt);
                    $inserted_count++;
                } else {
                    $stmt = mysqli_prepare($this->conn,
                        "UPDATE schedules SET schedules_time = ? WHERE user_id = ? AND schedules_date = ?"
                    );
                    mysqli_stmt_bind_param($stmt, 'sss', $shift_time, $user_id, $date);
                    if (!mysqli_stmt_execute($stmt)) {
                        throw new Exception("Update failed: " . mysqli_stmt_error($stmt));
                    }
                    mysqli_stmt_close($stmt);
                    $updated_count++;
                }
            }

            mysqli_commit($this->conn);

            return [
                'success'         => true,
                'message'         => "Shift '$shift_time' assigned for the full week. Inserted: $inserted_count, Updated: $updated_count",
                'inserted_shifts' => $inserted_shifts,
                'shift_time'      => $shift_time,
                'week_start'      => $week_dates[0],
                'week_end'        => end($week_dates),
                'total_days'      => count($week_dates)
            ];

        } catch (Exception $e) {
            mysqli_rollback($this->conn);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getUserWeeklyShifts($user_id, $start_date) {
        $week_dates = $this->getWeekDates($start_date);
        $placeholders = implode(',', array_fill(0, count($week_dates), '?'));
        $types = 's' . str_repeat('s', count($week_dates));
        $params = array_merge([$user_id], $week_dates);

        $stmt = mysqli_prepare($this->conn,
            "SELECT id, user_id, schedules_date, schedules_time
             FROM schedules
             WHERE user_id = ? AND schedules_date IN ($placeholders)
             ORDER BY schedules_date"
        );
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $rows = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }
        mysqli_stmt_close($stmt);
        return $rows;
    }

    public function deleteUserWeeklyShifts($user_id, $start_date) {
        $week_dates   = $this->getWeekDates($start_date);
        $placeholders = implode(',', array_fill(0, count($week_dates), '?'));
        $types  = 's' . str_repeat('s', count($week_dates));
        $params = array_merge([$user_id], $week_dates);

        $stmt = mysqli_prepare($this->conn,
            "DELETE FROM schedules WHERE user_id = ? AND schedules_date IN ($placeholders)"
        );
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        $ok = mysqli_stmt_execute($stmt);
        $deleted = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);

        return ['success' => $ok, 'deleted_count' => $deleted];
    }

    public function getAllEmployees() {
        $result = mysqli_query($this->conn,
            "SELECT user_id, username, full_name FROM users WHERE role = 'Employee' ORDER BY full_name"
        );
        $rows = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }
        return $rows;
    }

    private function shiftExists($user_id, $schedules_date) {
        $stmt = mysqli_prepare($this->conn,
            "SELECT COUNT(*) AS cnt FROM schedules WHERE user_id = ? AND schedules_date = ?"
        );
        mysqli_stmt_bind_param($stmt, 'ss', $user_id, $schedules_date);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row    = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        return $row['cnt'] > 0;
    }

    private function getWeekDates($start_date) {
        $dates = [];
        $start = new DateTime($start_date);
        if ($start->format('N') != 1) {
            $start->modify('this monday');
        }
        for ($i = 0; $i < 7; $i++) {
            $dates[] = $start->format('Y-m-d');
            $start->modify('+1 day');
        }
        return $dates;
    }
}

$shiftAssigner = new WeeklyShiftAssigner($conn);

// Handle AJAX actions
if ($_POST && isset($_POST['action'])) {
    if ($_POST['action'] === 'assign') {
        $user_id    = $_POST['user_id'];
        $week_start = $_POST['week_start'];
        $shift_time = $_POST['shift_time'];
        echo json_encode($shiftAssigner->assignWeeklyShifts($user_id, $week_start, $shift_time));
        exit;
    }
    if ($_POST['action'] === 'view') {
        $user_id    = $_POST['user_id'];
        $week_start = $_POST['week_start'];
        $shifts = $shiftAssigner->getUserWeeklyShifts($user_id, $week_start);
        echo json_encode([
            'success'    => true,
            'shifts'     => $shifts,
            'shift_time' => !empty($shifts) ? $shifts[0]['schedules_time'] : 'No shifts assigned'
        ]);
        exit;
    }
    if ($_POST['action'] === 'delete') {
        $user_id    = $_POST['user_id'];
        $week_start = $_POST['week_start'];
        echo json_encode($shiftAssigner->deleteUserWeeklyShifts($user_id, $week_start));
        exit;
    }
    if ($_POST['action'] === 'get_employees') {
        echo json_encode(['success' => true, 'employees' => $shiftAssigner->getAllEmployees()]);
        exit;
    }
}

$employees = $shiftAssigner->getAllEmployees();
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>He&She Coffee | Manage Shifts</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            vertical-align: middle;
        }
        body { font-family: 'Inter', sans-serif; }
    </style>
    <script id="tailwind-config">
        tailwind.config = {
          darkMode: "class",
          theme: {
            extend: {
              colors: {
                "secondary": "#545f73",
                "surface-container": "#eceef0",
                "surface-container-lowest": "#ffffff",
                "on-background": "#191c1e",
                "on-surface-variant": "#434655",
                "on-primary": "#ffffff",
                "surface": "#f7f9fb",
                "primary": "#000000",
                "background": "#f7f9fb",
                "primary-container": "#000000",
                "on-primary-container": "#ffffff",
                "on-surface": "#191c1e",
                "outline-variant": "#c3c6d7",
                "outline": "#737686",
                "surface-container-low": "#f2f4f6"
              },
              borderRadius: {
                "DEFAULT": "0.125rem",
                "lg": "0.25rem",
                "xl": "0.5rem",
                "full": "0.75rem"
              }
            }
          }
        }
    </script>
</head>
<body class="bg-background text-on-surface min-h-screen flex flex-col justify-between">

    <!-- TopNavBar -->
    <header class="bg-surface-container-lowest w-full top-0 border-b border-outline-variant sticky z-50">
        <div class="flex justify-between items-center h-16 px-6 max-w-[1440px] mx-auto">
            <div class="flex items-center gap-6">
                <div class="font-bold text-xl text-primary flex items-center gap-2">
                    <img src="images/logo.png" alt="He&She Coffee Logo" class="h-8 w-auto object-contain">
                    He&She Coffee
                </div>
                <nav class="hidden md:flex items-center gap-6 h-full mt-1">
                    <a class="text-secondary hover:text-primary transition-colors h-full flex items-center" href="user.php">Dashboard</a>
                    <a class="text-primary border-b-2 border-primary pb-1 font-semibold h-full flex items-center" href="manage_schedule.php">Schedules</a>
                    <a class="text-secondary hover:text-primary transition-colors h-full flex items-center" href="manage_salaries.php">Payroll</a>
                    <a class="text-secondary hover:text-primary transition-colors h-full flex items-center" href="manage_employee_profile.php">Profiles</a>
                </nav>
            </div>
            <div class="flex items-center gap-4">
                <span class="text-sm text-secondary">Role: <strong class="text-on-surface">Manager</strong></span>
                <a href="logout.php" class="text-xs border border-outline-variant px-3 py-1.5 hover:bg-surface-container-low transition-colors duration-200 rounded">Logout</a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-[860px] mx-auto px-6 py-8 flex-grow w-full">

        <!-- Page Header -->
        <section class="mb-6">
            <h1 class="text-2xl font-bold text-on-surface">Weekly Shift Assignment</h1>
            <p class="text-sm text-secondary mt-1">Assign the same shift to an employee for the entire week (Mon – Sun)</p>
        </section>

        <!-- Step 1: Select Employee -->
        <div class="bg-white border border-outline-variant p-6 rounded-xl mb-4 space-y-4">
            <div class="flex items-center gap-3">
                <div class="w-7 h-7 rounded-full bg-primary text-white text-xs font-semibold flex items-center justify-center flex-shrink-0">1</div>
                <span class="font-semibold text-on-surface">Select employee</span>
            </div>

            <input
                type="text"
                id="empSearch"
                placeholder="Search by name…"
                oninput="filterEmployees(this.value)"
                autocomplete="off"
                class="w-full h-10 px-4 border border-outline-variant bg-surface text-sm text-on-surface focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all duration-200 rounded"
            >

            <div id="empList" class="flex flex-col gap-2 max-h-56 overflow-y-auto pr-1">
                <?php if (empty($employees)): ?>
                    <p class="text-sm text-secondary text-center py-6">No employees found.</p>
                <?php else: ?>
                    <?php foreach ($employees as $emp):
                        $initials = implode('', array_map(fn($w) => strtoupper($w[0]), explode(' ', trim($emp['full_name']))));
                        $initials = substr($initials, 0, 2);
                    ?>
                    <label class="emp-option flex items-center gap-3 p-3 border border-outline-variant rounded cursor-pointer hover:bg-surface-container-low transition-colors"
                           data-id="<?= htmlspecialchars($emp['user_id']) ?>"
                           data-name="<?= htmlspecialchars($emp['full_name']) ?>"
                           onclick="selectEmployee(this)">
                        <input type="radio" name="user_id" value="<?= htmlspecialchars($emp['user_id']) ?>" class="hidden">
                        <div class="emp-avatar w-9 h-9 rounded-full bg-surface-container flex items-center justify-center text-xs font-semibold text-secondary flex-shrink-0 uppercase">
                            <?= $initials ?>
                        </div>
                        <div class="flex-grow">
                            <div class="text-sm font-semibold text-on-surface"><?= htmlspecialchars($emp['full_name']) ?></div>
                            <div class="text-xs text-secondary">ID #<?= htmlspecialchars($emp['user_id']) ?></div>
                        </div>
                        <div class="emp-check w-5 h-5 rounded-full border border-outline-variant flex items-center justify-center flex-shrink-0">
                            <div class="emp-check-dot w-2 h-2 rounded-full bg-primary hidden"></div>
                        </div>
                    </label>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Step 2: Choose Week -->
        <div class="bg-white border border-outline-variant p-6 rounded-xl mb-4 space-y-4">
            <div class="flex items-center gap-3">
                <div class="w-7 h-7 rounded-full bg-primary text-white text-xs font-semibold flex items-center justify-center flex-shrink-0">2</div>
                <span class="font-semibold text-on-surface">Choose week</span>
            </div>
            <input
                type="date"
                id="weekStart"
                oninput="updateWeekPreview(this.value)"
                class="w-full h-10 px-4 border border-outline-variant bg-surface text-sm text-on-surface focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all duration-200 rounded font-mono"
            >
            <div id="weekPreview" class="flex gap-2 flex-wrap mt-2"></div>
        </div>

        <!-- Step 3: Select Shift -->
        <div class="bg-white border border-outline-variant p-6 rounded-xl mb-4 space-y-4">
            <div class="flex items-center gap-3">
                <div class="w-7 h-7 rounded-full bg-primary text-white text-xs font-semibold flex items-center justify-center flex-shrink-0">3</div>
                <span class="font-semibold text-on-surface">Select shift</span>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <!-- Morning -->
                <label class="shift-card flex flex-col gap-1 p-4 border-2 border-outline-variant rounded-xl cursor-pointer hover:border-primary transition-all" data-value="morning" onclick="selectShift(this)">
                    <input type="radio" name="shift_time" value="morning" class="hidden">
                    <span class="text-2xl">☀️</span>
                    <span class="font-semibold text-sm text-on-surface">Morning</span>
                    <span class="text-xs text-secondary font-mono">08:00 – 13:00</span>
                    <div class="shift-tick hidden mt-1">
                        <span class="inline-flex items-center gap-1 text-xs font-semibold text-primary">
                            <span class="material-symbols-outlined text-sm">check_circle</span> Selected
                        </span>
                    </div>
                </label>
                <!-- Evening -->
                <label class="shift-card flex flex-col gap-1 p-4 border-2 border-outline-variant rounded-xl cursor-pointer hover:border-primary transition-all" data-value="evening" onclick="selectShift(this)">
                    <input type="radio" name="shift_time" value="evening" class="hidden">
                    <span class="text-2xl">🌤️</span>
                    <span class="font-semibold text-sm text-on-surface">Evening</span>
                    <span class="text-xs text-secondary font-mono">13:00 – 18:00</span>
                    <div class="shift-tick hidden mt-1">
                        <span class="inline-flex items-center gap-1 text-xs font-semibold text-primary">
                            <span class="material-symbols-outlined text-sm">check_circle</span> Selected
                        </span>
                    </div>
                </label>
                <!-- Night -->
                <label class="shift-card flex flex-col gap-1 p-4 border-2 border-outline-variant rounded-xl cursor-pointer hover:border-primary transition-all" data-value="night" onclick="selectShift(this)">
                    <input type="radio" name="shift_time" value="night" class="hidden">
                    <span class="text-2xl">🌙</span>
                    <span class="font-semibold text-sm text-on-surface">Night</span>
                    <span class="text-xs text-secondary font-mono">18:00 – 23:00</span>
                    <div class="shift-tick hidden mt-1">
                        <span class="inline-flex items-center gap-1 text-xs font-semibold text-primary">
                            <span class="material-symbols-outlined text-sm">check_circle</span> Selected
                        </span>
                    </div>
                </label>
            </div>
        </div>

        <!-- Actions -->
        <div class="bg-white border border-outline-variant p-6 rounded-xl mb-4">
            <div class="flex flex-wrap gap-3">
                <button id="btnAssign" onclick="assignShifts()" class="h-10 px-5 bg-primary text-white text-sm font-semibold hover:bg-neutral-800 transition-colors rounded flex items-center gap-2">
                    <span class="material-symbols-outlined text-sm">check</span> Assign Shifts
                </button>
                <button id="btnView" onclick="viewShifts()" class="h-10 px-5 border border-outline-variant text-on-surface text-sm font-semibold hover:bg-surface-container-low transition-colors rounded flex items-center gap-2">
                    <span class="material-symbols-outlined text-sm">visibility</span> View Shifts
                </button>
               
            </div>
        </div>

        <!-- Result Area -->
        <div id="result-area"></div>

        <!-- Back Button -->
        <div class="mt-4">
            <a href="user.php" class="inline-flex items-center gap-1 border border-outline-variant text-on-surface text-sm font-semibold px-4 h-10 hover:bg-surface-container-low transition-colors rounded">
                <span class="material-symbols-outlined text-sm">arrow_back</span> Back to Dashboard
            </a>
        </div>
    </main>

    <!-- Footer -->
    <footer class="w-full bg-surface-container border-t border-outline-variant py-4 px-6 mt-12">
        <div class="flex flex-col md:flex-row justify-between items-center max-w-[1440px] mx-auto w-full gap-2">
            <span class="text-xs text-on-surface-variant font-semibold uppercase tracking-wider">BrewManager Systems</span>
            <span class="text-xs text-secondary">© 2026 He&amp;She Coffee. All rights reserved.</span>
        </div>
    </footer>

<script>
let selectedUserId    = null;
let selectedShift     = null;
let selectedWeekStart = null;

const DAYS = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];

function selectEmployee(el) {
    document.querySelectorAll('.emp-option').forEach(e => {
        e.classList.remove('bg-primary', 'text-white', 'border-primary');
        e.classList.add('border-outline-variant');
        e.querySelector('.emp-avatar').classList.remove('bg-white', 'text-primary');
        e.querySelector('.emp-check-dot').classList.add('hidden');
    });
    el.classList.add('bg-primary', 'text-white', 'border-primary');
    el.classList.remove('border-outline-variant');
    el.querySelector('.emp-avatar').classList.add('bg-white', 'text-primary');
    el.querySelector('.emp-check-dot').classList.remove('hidden');
    el.querySelector('input').checked = true;
    selectedUserId = el.dataset.id; // now holds user_id
}

function filterEmployees(q) {
    const items = document.querySelectorAll('.emp-option');
    const lower = q.toLowerCase();
    items.forEach(el => {
        el.style.display = el.dataset.name.toLowerCase().includes(lower) ? '' : 'none';
    });
}

function selectShift(el) {
    document.querySelectorAll('.shift-card').forEach(c => {
        c.classList.remove('border-primary', 'bg-surface-container-low');
        c.querySelector('.shift-tick').classList.add('hidden');
    });
    el.classList.add('border-primary', 'bg-surface-container-low');
    el.querySelector('.shift-tick').classList.remove('hidden');
    el.querySelector('input').checked = true;
    selectedShift = el.dataset.value;
}

function updateWeekPreview(val) {
    selectedWeekStart = val;
    const preview = document.getElementById('weekPreview');
    if (!val) { preview.innerHTML = ''; return; }

    const d = new Date(val + 'T00:00:00');
    const day = d.getDay();
    const diff = (day === 0 ? -6 : 1 - day);
    d.setDate(d.getDate() + diff);

    preview.innerHTML = '';
    for (let i = 0; i < 7; i++) {
        const chip = document.createElement('div');
        chip.className = 'text-xs font-mono px-2 py-1 rounded border border-outline-variant bg-surface-container text-secondary';
        const dd = String(d.getDate()).padStart(2,'0');
        const mm = String(d.getMonth()+1).padStart(2,'0');
        chip.textContent = DAYS[i] + ' ' + dd + '/' + mm;
        preview.appendChild(chip);
        d.setDate(d.getDate() + 1);
    }
}

function getFormData(action) {
    const fd = new FormData();
    fd.append('action', action);
    fd.append('user_id', selectedUserId || '');
    fd.append('week_start', selectedWeekStart || '');
    if (action === 'assign') fd.append('shift_time', selectedShift || '');
    return fd;
}

function validate(needShift = false) {
    if (!selectedUserId)    { showToast('error', 'Please select an employee.'); return false; }
    if (!selectedWeekStart) { showToast('error', 'Please choose a week.'); return false; }
    if (needShift && !selectedShift) { showToast('error', 'Please select a shift type.'); return false; }
    return true;
}

function setLoading(btnId, loading) {
    const btn = document.getElementById(btnId);
    if (loading) {
        btn._orig = btn.innerHTML;
        btn.innerHTML = '<svg class="animate-spin w-4 h-4 inline-block mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path></svg> Working…';
        btn.disabled = true;
    } else {
        btn.innerHTML = btn._orig;
        btn.disabled = false;
    }
}

const PHP_URL = location.pathname;

function assignShifts() {
    if (!validate(true)) return;
    const checkedShift = document.querySelector('input[name="shift_time"]:checked');
    if (checkedShift) selectedShift = checkedShift.value;
    const empName = document.querySelector('.emp-option.bg-primary .text-sm.font-semibold')?.textContent || '';
    const shiftLabel = document.querySelector('.shift-card.border-primary .font-semibold')?.textContent || selectedShift;

    setLoading('btnAssign', true);
    fetch(PHP_URL, { method: 'POST', body: getFormData('assign') })
        .then(r => r.text())
        .then(raw => {
            setLoading('btnAssign', false);
            let data;
            try { data = JSON.parse(raw); }
            catch(e) { showToast('error', 'Server error: ' + raw.substring(0, 200)); return; }
            if (data.success) {
                showAssignResult(empName, shiftLabel, data);
            } else {
                showToast('error', data.message);
            }
        })
        .catch(err => { setLoading('btnAssign', false); showToast('error', 'Network error: ' + err); });
}

function viewShifts() {
    window.location.href = 'shifts.php';
}

function deleteShifts() {
    if (!validate()) return;
    if (!confirm('Delete all shifts for this employee this week?')) return;
    setLoading('btnDelete', true);
    fetch(PHP_URL, { method: 'POST', body: getFormData('delete') })
        .then(r => r.text())
        .then(raw => {
            setLoading('btnDelete', false);
            let data;
            try { data = JSON.parse(raw); }
            catch(e) { showToast('error', 'Server error: ' + raw.substring(0, 200)); return; }
            if (data.success) {
                showToast('success', `Deleted ${data.deleted_count} shift(s) successfully.`);
            } else {
                showToast('error', 'Delete failed. ' + (data.message || ''));
            }
        })
        .catch(err => { setLoading('btnDelete', false); showToast('error', 'Network error: ' + err); });
}

function showAssignResult(empName, shiftLabel, data) {
    document.getElementById('result-area').innerHTML = `
    <div class="bg-white border border-outline-variant rounded-xl overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-outline-variant">
            <div>
                <p class="font-semibold text-on-surface">✅ Shifts assigned successfully</p>
                <p class="text-xs text-secondary mt-0.5">
                    ${empName ? '<strong>' + escHtml(empName) + '</strong> &nbsp;·&nbsp; ' : ''}
                    ${data.week_start} → ${data.week_end} &nbsp;·&nbsp; ${data.total_days} days
                </p>
            </div>
            <span class="text-xs font-semibold px-3 py-1 bg-surface-container border border-outline-variant rounded">${escHtml(shiftLabel)}</span>
        </div>
        <div class="px-6 py-3 text-xs text-secondary">
            ${data.inserted_shifts.length > 0 ? `<span class="text-green-700 font-semibold">New:</span> ${data.inserted_shifts.map(s => s.shift_date).join(', ')}` : ''}
            ${data.inserted_shifts.length > 0 && (data.total_days - data.inserted_shifts.length) > 0 ? ' &nbsp;|&nbsp; ' : ''}
            ${(data.total_days - data.inserted_shifts.length) > 0 ? `<span class="text-amber-700 font-semibold">Updated:</span> ${data.total_days - data.inserted_shifts.length} existing day(s)` : ''}
        </div>
    </div>`;
}

function showToast(type, msg) {
    const isSuccess = type === 'success';
    document.getElementById('result-area').innerHTML = `
    <div class="flex items-center gap-3 px-4 py-3 rounded border text-sm font-medium
        ${isSuccess ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-800'}">
        <span class="material-symbols-outlined text-base">${isSuccess ? 'check_circle' : 'error'}</span>
        <span>${msg}</span>
    </div>`;
}

function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

(function setDefaultWeek() {
    const today = new Date();
    const day = today.getDay();
    const diff = day === 0 ? -6 : 1 - day;
    today.setDate(today.getDate() + diff);
    const iso = today.toISOString().split('T')[0];
    document.getElementById('weekStart').value = iso;
    updateWeekPreview(iso);
})();
</script>
</body>
</html>