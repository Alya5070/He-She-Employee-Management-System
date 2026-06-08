<?php
include 'db_connect.php';
ob_start();

 // loads $conn; any HTML it echoes goes into the buffer

/* ── AJAX handler ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    /* ── FILTER ── */
    if ($_POST['action'] === 'filter') {
        ob_end_clean();
        header('Content-Type: application/json');

        $empId  = isset($_POST['employee_id']) && $_POST['employee_id'] !== ''
                  ? (int)$_POST['employee_id']
                  : null;

        $sql    = "SELECT s.shift_id, s.user_id, s.shift_date, s.shift_time, u.full_name
                   FROM shifts s
                   JOIN users u ON s.user_id = u.user_id
                   WHERE u.role = 'Employee'";
        $params = [];
        $types  = '';

        if ($empId !== null) {
            $sql   .= " AND s.user_id = ?";
            $params[] = $empId;
            $types .= 'i';
        }

        $sql .= " ORDER BY s.shift_date ASC, u.full_name, s.shift_time";

        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt === false) {
            echo json_encode(['success' => false, 'message' => 'Query prepare failed: ' . mysqli_error($conn)]);
            exit;
        }

        if (!empty($params)) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }

        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $shifts = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $shifts[] = $row;
        }
        mysqli_stmt_close($stmt);

        echo json_encode(['success' => true, 'shifts' => $shifts]);
        exit;
    }

    /* ── EDIT SHIFT ── */
    if ($_POST['action'] === 'edit_shift') {
        ob_end_clean();
        header('Content-Type: application/json');

        $shiftId   = isset($_POST['shift_id'])  ? (int)$_POST['shift_id']   : 0;
        $shiftDate = isset($_POST['shift_date']) ? trim($_POST['shift_date']) : '';
        $shiftTime = isset($_POST['shift_time']) ? trim($_POST['shift_time']) : '';

        $allowed = ['morning', 'evening', 'night'];
        if (!$shiftId || !$shiftDate || !in_array(strtolower($shiftTime), $allowed)) {
            echo json_encode(['success' => false, 'message' => 'Invalid input.']);
            exit;
        }

        $stmt = mysqli_prepare($conn,
            "UPDATE shifts SET shift_date = ?, shift_time = ? WHERE shift_id = ?");
        if ($stmt === false) {
            echo json_encode(['success' => false, 'message' => mysqli_error($conn)]);
            exit;
        }

        mysqli_stmt_bind_param($stmt, 'ssi', $shiftDate, $shiftTime, $shiftId);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        echo json_encode(['success' => $ok, 'message' => $ok ? '' : mysqli_error($conn)]);
        exit;
    }

    /* ── GET SHIFT (for edit modal) ── */
    if ($_POST['action'] === 'get_shift') {
        ob_end_clean();
        header('Content-Type: application/json');

        $shiftId = isset($_POST['shift_id']) ? (int)$_POST['shift_id'] : 0;
        if (!$shiftId) {
            echo json_encode(['success' => false, 'message' => 'Invalid shift ID.']);
            exit;
        }

        $stmt = mysqli_prepare($conn,
            "SELECT s.shift_id, s.user_id, s.shift_date, s.shift_time, u.full_name
             FROM shifts s
             JOIN users u ON s.user_id = u.user_id
             WHERE s.shift_id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $shiftId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if ($row) {
            echo json_encode(['success' => true, 'shift' => $row]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Shift not found.']);
        }
        exit;
    }

    /* ── DELETE SHIFT ── */
    if ($_POST['action'] === 'delete_shift') {
        ob_end_clean();
        header('Content-Type: application/json');

        $shiftId = isset($_POST['shift_id']) ? (int)$_POST['shift_id'] : 0;
        if (!$shiftId) {
            echo json_encode(['success' => false, 'message' => 'Invalid shift ID.']);
            exit;
        }

        $stmt = mysqli_prepare($conn, "DELETE FROM shifts WHERE shift_id = ?");
        if ($stmt === false) {
            echo json_encode(['success' => false, 'message' => mysqli_error($conn)]);
            exit;
        }

        mysqli_stmt_bind_param($stmt, 'i', $shiftId);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        echo json_encode(['success' => $ok, 'message' => $ok ? '' : mysqli_error($conn)]);
        exit;
    }
}

/* ── Normal page load: flush buffered header.php output ── */
ob_end_flush();

/* ── Employee list for the dropdown ── */
$emp_result = mysqli_query($conn,
    "SELECT user_id, full_name FROM users WHERE role = 'Employee' ORDER BY full_name"
);
$employees = [];
while ($row = mysqli_fetch_assoc($emp_result)) {
    $employees[] = $row;
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>He&amp;She Coffee | All Employee Shifts</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            vertical-align: middle;
        }
        body { font-family: 'Inter', sans-serif; }

        /* Spinner */
        @keyframes spin { to { transform: rotate(360deg); } }
        .spinner {
            width: 20px; height: 20px;
            border: 2px solid rgba(0,0,0,.1);
            border-top-color: #000;
            border-radius: 50%;
            animation: spin .7s linear infinite;
            display: inline-block;
        }

        /* Row fade-out on delete */
        @keyframes fadeSlideOut {
            to { opacity: 0; transform: translateX(16px); }
        }
        .row-removing {
            animation: fadeSlideOut .3s ease forwards;
        }

        /* Modal open/close */
        .modal-backdrop { display: none; }
        .modal-backdrop.open { display: flex; }
    </style>
    <script>
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

<!-- ══ TopNavBar ══ -->
<header class="bg-surface-container-lowest w-full top-0 border-b border-outline-variant sticky z-50">
    <div class="flex justify-between items-center h-16 px-6 max-w-[1440px] mx-auto">
        <div class="flex items-center gap-6">
            <div class="font-bold text-xl text-primary flex items-center gap-2">
                <img src="images/logo.png" alt="He&amp;She Coffee Logo" class="h-8 w-auto object-contain">
                He&amp;She Coffee
            </div>
            <nav class="hidden md:flex items-center gap-6 h-full mt-1">
                <a class="text-secondary hover:text-primary transition-colors h-full flex items-center" href="user.php">Dashboard</a>
                <a class="text-primary border-b-2 border-primary pb-1 font-semibold h-full flex items-center" href="shifts.php">Schedules</a>
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

<!-- ══ Main ══ -->
<main class="max-w-[1080px] mx-auto px-6 py-8 flex-grow w-full">

    <!-- Page Header -->
    <section class="mb-6">
        <h1 class="text-2xl font-bold text-on-surface">All Employee Shifts</h1>
        <p class="text-sm text-secondary mt-1">View, filter, edit and delete shifts across all employees</p>
    </section>

    <!-- Filter Card -->
    <div class="bg-white border border-outline-variant p-6 rounded-xl mb-4">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-7 h-7 rounded-full bg-primary text-white text-xs font-semibold flex items-center justify-center flex-shrink-0">
                <span class="material-symbols-outlined text-sm">filter_list</span>
            </div>
            <span class="font-semibold text-on-surface">Filter by Employee</span>
        </div>
        <div class="flex flex-wrap gap-3 items-end">
            <div class="flex-1 min-w-[220px]">
                <select id="employeeFilter"
                        class="w-full h-10 px-4 border border-outline-variant bg-surface text-sm text-on-surface focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all duration-200 rounded">
                    <option value="">All Employees</option>
                    <?php foreach ($employees as $emp): ?>
                        <option value="<?= (int)$emp['user_id'] ?>">
                            <?= htmlspecialchars($emp['full_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button id="filterBtn" onclick="filterShifts()"
                    class="h-10 px-5 bg-primary text-white text-sm font-semibold hover:bg-neutral-800 transition-colors rounded flex items-center gap-2">
                <span class="material-symbols-outlined text-sm">search</span>
                Filter
            </button>
        </div>
    </div>

    <!-- Stats Row -->
    <div id="statsRow" class="hidden grid grid-cols-2 sm:grid-cols-5 gap-3 mb-4">
        <div class="bg-white border border-outline-variant rounded-xl p-4">
            <div class="text-2xl font-bold text-on-surface" id="statTotal">0</div>
            <div class="text-xs text-secondary uppercase tracking-wide mt-0.5">Total Shifts</div>
        </div>
        <div class="bg-white border border-outline-variant rounded-xl p-4">
            <div class="text-2xl font-bold text-on-surface" id="statEmps">0</div>
            <div class="text-xs text-secondary uppercase tracking-wide mt-0.5">Employees</div>
        </div>
        <div class="bg-white border border-outline-variant rounded-xl p-4">
            <div class="text-2xl font-bold text-on-surface" id="statMorning">0</div>
            <div class="text-xs text-secondary uppercase tracking-wide mt-0.5">☀️ Morning</div>
        </div>
        <div class="bg-white border border-outline-variant rounded-xl p-4">
            <div class="text-2xl font-bold text-on-surface" id="statEvening">0</div>
            <div class="text-xs text-secondary uppercase tracking-wide mt-0.5">🌤️ Evening</div>
        </div>
        <div class="bg-white border border-outline-variant rounded-xl p-4">
            <div class="text-2xl font-bold text-on-surface" id="statNight">0</div>
            <div class="text-xs text-secondary uppercase tracking-wide mt-0.5">🌙 Night</div>
        </div>
    </div>

    <!-- Shifts Container -->
    <div id="shiftsContainer">
        <!-- loading state -->
        <div class="bg-white border border-outline-variant rounded-xl p-12 flex flex-col items-center gap-3 text-secondary">
            <div class="spinner"></div>
            <p class="text-sm font-medium">Loading shifts…</p>
        </div>
    </div>

    <!-- Back Button -->
    <div class="mt-6">
        <a href="manage_schedule.php"
           class="inline-flex items-center gap-1 border border-outline-variant text-on-surface text-sm font-semibold px-4 h-10 hover:bg-surface-container-low transition-colors rounded">
            <span class="material-symbols-outlined text-sm">arrow_back</span> Back to Schedules
        </a>
    </div>

</main>

<!-- ══ Footer ══ -->
<footer class="w-full bg-surface-container border-t border-outline-variant py-4 px-6 mt-12">
    <div class="flex flex-col md:flex-row justify-between items-center max-w-[1440px] mx-auto w-full gap-2">
        <span class="text-xs text-on-surface-variant font-semibold uppercase tracking-wider">BrewManager Systems</span>
        <span class="text-xs text-secondary">© 2026 He&amp;She Coffee. All rights reserved.</span>
    </div>
</footer>


<!-- ══ EDIT MODAL ══ -->
<div class="modal-backdrop fixed inset-0 bg-black/40 z-50 items-center justify-center" id="editModal">
    <div class="bg-white border border-outline-variant rounded-xl w-full max-w-md mx-4 shadow-xl"
         onclick="event.stopPropagation()">

        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-outline-variant">
            <span class="font-semibold text-on-surface flex items-center gap-2">
                <span class="material-symbols-outlined text-base">edit_calendar</span>
                Edit Shift
            </span>
            <button onclick="closeEditModal()"
                    class="text-secondary hover:text-on-surface hover:bg-surface-container-low rounded p-1 transition-colors">
                <span class="material-symbols-outlined text-base">close</span>
            </button>
        </div>

        <!-- Body -->
        <div class="px-6 py-5 space-y-4">
            <input type="hidden" id="editShiftId">

            <!-- Employee (read-only) -->
            <div>
                <label class="block text-xs font-semibold text-secondary uppercase tracking-wider mb-1.5">Employee</label>
                <div id="editFullName"
                     class="h-10 px-4 flex items-center border border-outline-variant bg-surface rounded text-sm text-secondary font-medium">
                    Loading…
                </div>
            </div>

            <!-- Date -->
            <div>
                <label for="editDate" class="block text-xs font-semibold text-secondary uppercase tracking-wider mb-1.5">Shift Date</label>
                <input type="date" id="editDate"
                       class="w-full h-10 px-4 border border-outline-variant bg-surface text-sm text-on-surface focus:ring-1 focus:ring-primary focus:border-primary outline-none transition-all duration-200 rounded font-mono">
            </div>

            <!-- Shift Type -->
            <div>
                <label class="block text-xs font-semibold text-secondary uppercase tracking-wider mb-1.5">Shift Type</label>
                <div class="grid grid-cols-3 gap-2">
                    <label class="edit-shift-card flex flex-col items-center gap-1 p-3 border-2 border-outline-variant rounded-xl cursor-pointer hover:border-primary transition-all text-center" data-value="morning" onclick="selectEditShift(this)">
                        <input type="radio" name="editShiftTime" value="morning" class="hidden">
                        <span class="text-xl">☀️</span>
                        <span class="text-xs font-semibold text-on-surface">Morning</span>
                    </label>
                    <label class="edit-shift-card flex flex-col items-center gap-1 p-3 border-2 border-outline-variant rounded-xl cursor-pointer hover:border-primary transition-all text-center" data-value="evening" onclick="selectEditShift(this)">
                        <input type="radio" name="editShiftTime" value="evening" class="hidden">
                        <span class="text-xl">🌤️</span>
                        <span class="text-xs font-semibold text-on-surface">Evening</span>
                    </label>
                    <label class="edit-shift-card flex flex-col items-center gap-1 p-3 border-2 border-outline-variant rounded-xl cursor-pointer hover:border-primary transition-all text-center" data-value="night" onclick="selectEditShift(this)">
                        <input type="radio" name="editShiftTime" value="night" class="hidden">
                        <span class="text-xl">🌙</span>
                        <span class="text-xs font-semibold text-on-surface">Night</span>
                    </label>
                </div>
            </div>

            <!-- Error -->
            <div id="editError"
                 class="hidden flex items-center gap-2 px-4 py-3 rounded border text-sm font-medium bg-red-50 border-red-200 text-red-800">
                <span class="material-symbols-outlined text-base">error</span>
                <span id="editErrorMsg"></span>
            </div>
        </div>

        <!-- Footer -->
        <div class="flex gap-3 justify-end px-6 py-4 border-t border-outline-variant">
            <button onclick="closeEditModal()"
                    class="h-10 px-5 border border-outline-variant text-on-surface text-sm font-semibold hover:bg-surface-container-low transition-colors rounded">
                Cancel
            </button>
            <button id="saveBtn" onclick="saveEdit()"
                    class="h-10 px-5 bg-primary text-white text-sm font-semibold hover:bg-neutral-800 transition-colors rounded flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                <span class="material-symbols-outlined text-sm">save</span>
                Save Changes
            </button>
        </div>
    </div>
</div>


<!-- ══ CONFIRM DELETE ══ -->
<div class="modal-backdrop fixed inset-0 bg-black/40 z-50 items-center justify-center" id="confirmModal">
    <div class="bg-white border border-outline-variant rounded-xl w-full max-w-sm mx-4 shadow-xl text-center p-6"
         onclick="event.stopPropagation()">
        <div class="w-12 h-12 rounded-full bg-red-50 border border-red-200 flex items-center justify-center mx-auto mb-3">
            <span class="material-symbols-outlined text-red-600">delete</span>
        </div>
        <p class="font-semibold text-on-surface mb-1">Delete this shift?</p>
        <p class="text-sm text-secondary mb-5" id="confirmBody">This action cannot be undone.</p>
        <input type="hidden" id="confirmShiftId">
        <div class="flex gap-3 justify-center">
            <button onclick="closeConfirm()"
                    class="h-10 px-5 border border-outline-variant text-on-surface text-sm font-semibold hover:bg-surface-container-low transition-colors rounded">
                Cancel
            </button>
            <button id="confirmOkBtn" onclick="confirmDelete()"
                    class="h-10 px-5 bg-red-600 text-white text-sm font-semibold hover:bg-red-700 transition-colors rounded flex items-center gap-2 disabled:opacity-50">
                <span class="material-symbols-outlined text-sm">delete</span>
                Delete
            </button>
        </div>
    </div>
</div>


<script>
const PHP_URL = '<?= htmlspecialchars(basename(__FILE__)) ?>';

/* ── helpers ── */
function esc(str) {
    return String(str)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function shiftBadge(time) {
    const map = {
        morning: { bg:'bg-amber-50',  border:'border-amber-300',  text:'text-amber-800',  icon:'☀️'  },
        evening: { bg:'bg-blue-50',   border:'border-blue-300',   text:'text-blue-800',   icon:'🌤️' },
        night:   { bg:'bg-slate-800', border:'border-slate-600',  text:'text-slate-200',  icon:'🌙'  },
    };
    const t = (time || '').toLowerCase();
    const m = map[t] || { bg:'bg-surface-container', border:'border-outline-variant', text:'text-secondary', icon:'⚪' };
    const label = t.charAt(0).toUpperCase() + t.slice(1);
    return `<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded border text-xs font-semibold font-mono ${m.bg} ${m.border} ${m.text}">${m.icon} ${label}</span>`;
}

function fmtDate(dateStr) {
    const [y, mo, d] = dateStr.split('-').map(Number);
    return new Date(y, mo - 1, d).toLocaleDateString('en-GB', {
        weekday: 'short', day: 'numeric', month: 'short', year: 'numeric'
    });
}

function getInitials(name) {
    return name.split(' ').map(w => w[0] || '').join('').toUpperCase().slice(0, 2);
}

/* ── render table ── */
function renderShifts(shifts) {
    const container = document.getElementById('shiftsContainer');

    if (!shifts.length) {
        container.innerHTML = `
            <div class="bg-white border border-outline-variant rounded-xl p-12 flex flex-col items-center gap-3 text-secondary">
                <span class="material-symbols-outlined text-4xl opacity-40">event_busy</span>
                <p class="text-sm font-medium text-on-surface">No shifts found</p>
                <p class="text-xs">Try selecting a different employee</p>
            </div>`;
        document.getElementById('statsRow').classList.add('hidden');
        return;
    }

    const rows = shifts.map(s => {
        const initials = getInitials(s.full_name);
        return `
        <tr class="border-t border-outline-variant hover:bg-surface-container-low transition-colors" data-shift-id="${esc(s.shift_id)}">
            <td class="px-5 py-3.5">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full bg-surface-container flex items-center justify-center text-xs font-semibold text-secondary flex-shrink-0 uppercase">
                        ${esc(initials)}
                    </div>
                    <div>
                        <div class="text-sm font-semibold text-on-surface">${esc(s.full_name)}</div>
                        <div class="text-xs text-secondary font-mono">ID #${esc(s.user_id)}</div>
                    </div>
                </div>
            </td>
            <td class="px-5 py-3.5">
                <span class="text-sm font-mono text-on-surface date-cell">${fmtDate(s.shift_date)}</span>
            </td>
            <td class="px-5 py-3.5 shift-cell">${shiftBadge(s.shift_time)}</td>
            <td class="px-5 py-3.5 text-right">
                <button class="btn-edit inline-flex items-center gap-1.5 h-8 px-3 border border-blue-200 bg-blue-50 text-blue-700 text-xs font-semibold rounded hover:bg-blue-100 transition-colors"
                        data-shift-id="${esc(s.shift_id)}" title="Edit shift">
                    <span class="material-symbols-outlined text-sm">edit</span> Edit
                </button>
                <button class="btn-delete inline-flex items-center gap-1.5 h-8 px-3 border border-red-200 bg-red-50 text-red-600 text-xs font-semibold rounded hover:bg-red-100 transition-colors ml-2"
                        onclick="openDelete(${esc(s.shift_id)}, '${esc(s.full_name)}', '${esc(s.shift_date)}')"
                        title="Delete shift">
                    <span class="material-symbols-outlined text-sm">delete</span> Delete
                </button>
            </td>
        </tr>`;
    }).join('');

    container.innerHTML = `
        <div class="bg-white border border-outline-variant rounded-xl overflow-hidden">
            <table class="w-full border-collapse">
                <thead class="bg-surface-container-low">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-secondary uppercase tracking-wider">Employee</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-secondary uppercase tracking-wider">Date</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-secondary uppercase tracking-wider">Shift</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold text-secondary uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody>${rows}</tbody>
            </table>
        </div>`;

    document.querySelectorAll('.btn-edit').forEach(btn => {
        btn.addEventListener('click', function() {
            openEdit(this.dataset.shiftId);
        });
    });

    updateStats(shifts);
}

function updateStats(shifts) {
    const count = t => shifts.filter(s => (s.shift_time || '').toLowerCase() === t).length;
    document.getElementById('statTotal').textContent   = shifts.length;
    document.getElementById('statEmps').textContent    = new Set(shifts.map(s => s.user_id)).size;
    document.getElementById('statMorning').textContent = count('morning');
    document.getElementById('statEvening').textContent = count('evening');
    document.getElementById('statNight').textContent   = count('night');
    document.getElementById('statsRow').classList.remove('hidden');
}

/* ── shared fetch helper ── */
function postJSON(formData) {
    return fetch(PHP_URL, { method: 'POST', body: formData })
        .then(r => {
            if (!r.ok) throw new Error(`HTTP ${r.status}`);
            return r.text();
        })
        .then(text => {
            const clean = text.replace(/^\s+/, '');
            if (!clean.startsWith('{') && !clean.startsWith('['))
                throw new Error('Unexpected server response.');
            return JSON.parse(clean);
        });
}

/* ── filter ── */
function filterShifts() {
    const btn   = document.getElementById('filterBtn');
    const empId = document.getElementById('employeeFilter').value;

    btn.disabled  = true;
    btn.innerHTML = '<div class="spinner"></div> Loading…';

    document.getElementById('shiftsContainer').innerHTML = `
        <div class="bg-white border border-outline-variant rounded-xl p-12 flex flex-col items-center gap-3 text-secondary">
            <div class="spinner"></div>
            <p class="text-sm font-medium">Fetching shifts…</p>
        </div>`;
    document.getElementById('statsRow').classList.add('hidden');

    const fd = new FormData();
    fd.append('action', 'filter');
    if (empId) fd.append('employee_id', empId);

    postJSON(fd)
        .then(data => {
            if (data.success) renderShifts(data.shifts);
            else throw new Error(data.message || 'Unknown server error');
        })
        .catch(err => {
            document.getElementById('shiftsContainer').innerHTML = `
                <div class="bg-white border border-outline-variant rounded-xl p-12 flex flex-col items-center gap-3">
                    <span class="material-symbols-outlined text-4xl text-red-400">warning</span>
                    <p class="text-sm font-medium text-on-surface">Failed to load shifts</p>
                    <p class="text-xs text-secondary">${esc(err.message)}</p>
                </div>`;
        })
        .finally(() => {
            btn.disabled  = false;
            btn.innerHTML = '<span class="material-symbols-outlined text-sm">search</span> Filter';
        });
}

/* ══ EDIT ══ */
let currentEditShift = null;

function selectEditShift(el) {
    document.querySelectorAll('.edit-shift-card').forEach(c => {
        c.classList.remove('border-primary', 'bg-surface-container-low');
    });
    el.classList.add('border-primary', 'bg-surface-container-low');
    el.querySelector('input').checked = true;
    currentEditShift = el.dataset.value;
}

function openEdit(shiftId) {
    currentEditShift = null;
    document.getElementById('editShiftId').value         = shiftId;
    document.getElementById('editFullName').textContent  = 'Loading…';
    document.getElementById('editDate').value            = '';
    document.getElementById('editError').classList.add('hidden');
    document.getElementById('saveBtn').disabled          = true;
    document.querySelectorAll('.edit-shift-card').forEach(c =>
        c.classList.remove('border-primary', 'bg-surface-container-low'));
    document.getElementById('editModal').classList.add('open');

    const fd = new FormData();
    fd.append('action',   'get_shift');
    fd.append('shift_id', shiftId);

    postJSON(fd)
        .then(data => {
            if (!data.success) throw new Error(data.message || 'Could not load shift.');
            const s = data.shift;
            document.getElementById('editFullName').textContent = s.full_name;
            document.getElementById('editDate').value           = s.shift_date;
            const card = document.querySelector(`.edit-shift-card[data-value="${s.shift_time.toLowerCase()}"]`);
            if (card) selectEditShift(card);
            currentEditShift = s.shift_time.toLowerCase();
            document.getElementById('saveBtn').disabled = false;
        })
        .catch(err => {
            showEditError(err.message);
        });
}

function closeEditModal() {
    document.getElementById('editModal').classList.remove('open');
}

function showEditError(msg) {
    const box = document.getElementById('editError');
    document.getElementById('editErrorMsg').textContent = msg;
    box.classList.remove('hidden');
}

function saveEdit() {
    const shiftId   = document.getElementById('editShiftId').value;
    const shiftDate = document.getElementById('editDate').value;
    const saveBtn   = document.getElementById('saveBtn');

    if (!currentEditShift) { showEditError('Please select a shift type.'); return; }
    if (!shiftDate)         { showEditError('Please select a date.'); return; }

    document.getElementById('editError').classList.add('hidden');
    saveBtn.disabled  = true;
    saveBtn.innerHTML = '<div class="spinner"></div> Saving…';

    const fd = new FormData();
    fd.append('action',     'edit_shift');
    fd.append('shift_id',   shiftId);
    fd.append('shift_date', shiftDate);
    fd.append('shift_time', currentEditShift);

    postJSON(fd)
        .then(data => {
            if (!data.success) throw new Error(data.message || 'Save failed.');
            const row = document.querySelector(`tr[data-shift-id="${shiftId}"]`);
            if (row) {
                row.querySelector('.date-cell').textContent = fmtDate(shiftDate);
                row.querySelector('.shift-cell').innerHTML  = shiftBadge(currentEditShift);
            }
            closeEditModal();
            refreshStats();
        })
        .catch(err => showEditError(err.message))
        .finally(() => {
            saveBtn.disabled  = false;
            saveBtn.innerHTML = '<span class="material-symbols-outlined text-sm">save</span> Save Changes';
        });
}

/* ══ DELETE ══ */
function openDelete(shiftId, empName, shiftDate) {
    document.getElementById('confirmShiftId').value  = shiftId;
    document.getElementById('confirmBody').textContent =
        `Remove the ${fmtDate(shiftDate)} shift for ${empName}? This cannot be undone.`;
    document.getElementById('confirmOkBtn').disabled = false;
    document.getElementById('confirmModal').classList.add('open');
}

function closeConfirm() {
    document.getElementById('confirmModal').classList.remove('open');
}

function confirmDelete() {
    const shiftId = document.getElementById('confirmShiftId').value;
    const okBtn   = document.getElementById('confirmOkBtn');
    okBtn.disabled  = true;
    okBtn.innerHTML = '<div class="spinner"></div> Deleting…';

    const fd = new FormData();
    fd.append('action',   'delete_shift');
    fd.append('shift_id', shiftId);

    postJSON(fd)
        .then(data => {
            if (!data.success) throw new Error(data.message || 'Delete failed.');
            const row = document.querySelector(`tr[data-shift-id="${shiftId}"]`);
            if (row) {
                row.classList.add('row-removing');
                setTimeout(() => { row.remove(); refreshStats(); }, 320);
            }
            closeConfirm();
        })
        .catch(err => alert('Error: ' + err.message))
        .finally(() => {
            okBtn.disabled  = false;
            okBtn.innerHTML = '<span class="material-symbols-outlined text-sm">delete</span> Delete';
        });
}

/* ── re-compute stats from current DOM ── */
function refreshStats() {
    const rows = document.querySelectorAll('tbody tr[data-shift-id]');
    if (!rows.length) {
        document.getElementById('statsRow').classList.add('hidden');
        return;
    }
    const empIds = new Set();
    let morning = 0, evening = 0, night = 0;
    rows.forEach(row => {
        empIds.add(row.querySelector('.text-xs.font-mono')?.textContent || Math.random());
        const badge = row.querySelector('.shift-cell span');
        if (badge) {
            const t = badge.textContent.trim().toLowerCase();
            if (t.includes('morning')) morning++;
            else if (t.includes('evening')) evening++;
            else if (t.includes('night')) night++;
        }
    });
    document.getElementById('statTotal').textContent   = rows.length;
    document.getElementById('statEmps').textContent    = empIds.size;
    document.getElementById('statMorning').textContent = morning;
    document.getElementById('statEvening').textContent = evening;
    document.getElementById('statNight').textContent   = night;
}

/* ── close modals on backdrop click / Escape ── */
document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) closeEditModal();
});
document.getElementById('confirmModal').addEventListener('click', function(e) {
    if (e.target === this) closeConfirm();
});
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') { closeEditModal(); closeConfirm(); }
});

document.addEventListener('DOMContentLoaded', filterShifts);
</script>
</body>
</html>