<?php
include 'session_init.php';
include 'db_connect.php';

// Check if the user is logged in and is an employee
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'Employee') {
    header('Location: login.php');
    exit();
}

$username = $_SESSION['username'];

// Fetch user profile info
$user_stmt = $conn->prepare("
    SELECT u.user_id, u.full_name, COALESCE(p.shift_rate, 28.00) AS rate, p.bank_account_number, p.email
    FROM users u
    LEFT JOIN employee_profiles p ON u.user_id = p.user_id
    WHERE u.username = ?
");
$user_stmt->bind_param("s", $username);
$user_stmt->execute();
$emp_info = $user_stmt->get_result()->fetch_assoc();
$user_stmt->close();

$user_id = $emp_info['user_id'];

// Fetch salary history records
$stmt_sal = $conn->prepare("
    SELECT month, total_shifts, calculated_salary, COALESCE(bonus,0) AS bonus, COALESCE(deduction,0) AS deduction, status
    FROM salaries
    WHERE user_id = ?
    ORDER BY month DESC
");
$stmt_sal->bind_param("i", $user_id);
$stmt_sal->execute();
$result_sal = $stmt_sal->get_result();
$salaries = $result_sal->fetch_all(MYSQLI_ASSOC);
$stmt_sal->close();
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>He&She Coffee | My Payroll</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            vertical-align: middle;
        }
        body { font-family: 'Inter', sans-serif; }

        @media print {
            body * {
                visibility: hidden;
            }
            #payslip-modal-content, #payslip-modal-content * {
                visibility: visible;
            }
            #payslip-modal-content {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                border: none !important;
                box-shadow: none !important;
                background: white !important;
                color: black !important;
            }
            .no-print {
                display: none !important;
            }
        }
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
                    <a class="text-secondary hover:text-primary transition-colors h-full flex items-center" href="my_schedule.php">My Schedule</a>
                    <a class="text-primary border-b-2 border-primary pb-1 font-semibold h-full flex items-center" href="my_payroll.php">My Payroll</a>
                    <a class="text-secondary hover:text-primary transition-colors h-full flex items-center" href="my_profile.php">My Profile</a>
                </nav>
            </div>
            <div class="flex items-center gap-4">
                <span class="text-sm text-secondary">Role: <strong class="text-on-surface">Employee</strong></span>
                <a href="logout.php" class="text-xs border border-outline-variant px-3 py-1.5 hover:bg-surface-container-low transition-colors duration-200 rounded-xl">Logout</a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-[1000px] mx-auto px-6 py-8 flex-grow w-full space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-on-surface">My Payroll History</h1>
            <p class="text-sm text-secondary mt-0.5">View your shifts and monthly earnings, and print your payslips.</p>
        </div>

        <!-- Salaries Table -->
        <div class="bg-white border border-outline-variant rounded-xl overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-surface-container-low border-b border-outline-variant text-xs font-semibold text-secondary uppercase tracking-wider">
                            <th class="py-4 px-4">Pay Month</th>
                            <th class="py-4 px-4">Shift Rate</th>
                            <th class="py-4 px-4">Total Shifts</th>
                            <th class="py-4 px-4">Total Earning</th>
                            <th class="py-4 px-4">Status</th>
                            <th class="py-4 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant text-sm">
                        <?php if (empty($salaries)): ?>
                            <tr>
                                <td colspan="6" class="py-8 text-center text-secondary text-sm">No payroll records processed yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($salaries as $sal): ?>
                                <tr class="hover:bg-surface-container-low/20 transition-colors">
                                    <td class="py-4 px-4 font-semibold text-on-surface">
                                        <?php echo date('F Y', strtotime($sal['month'] . '-01')); ?>
                                    </td>
                                    <td class="py-4 px-4 font-mono">RM<?php echo number_format($emp_info['rate'], 2); ?></td>
                                    <td class="py-4 px-4 font-mono"><?php echo $sal['total_shifts']; ?></td>
                                    <td class="py-4 px-4 font-mono font-bold text-primary">RM<?php echo number_format(floatval($sal['calculated_salary']) + floatval($sal['bonus']) - floatval($sal['deduction']), 2); ?></td>
                                    <td class="py-4 px-4">
                                        <?php if ($sal['status'] === 'Paid'): ?>
                                            <span class="px-2 py-0.5 bg-green-100 text-green-800 text-[10px] font-bold rounded-xl">Paid</span>
                                        <?php else: ?>
                                            <span class="px-2 py-0.5 bg-blue-100 text-blue-800 text-[10px] font-bold rounded-xl">Pending Approval</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-4 px-4 text-right">
                                        <button onclick="openPayslip(<?php echo htmlspecialchars(json_encode([
                                            'month' => date('F Y', strtotime($sal['month'] . '-01')),
                                            'full_name' => $emp_info['full_name'],
                                            'email' => $emp_info['email'],
                                            'bank' => $emp_info['bank_account_number'],
                                            'rate' => $emp_info['rate'],
                                            'shifts' => $sal['total_shifts'],
                                            'salary' => $sal['calculated_salary'],
                                            'bonus' => $sal['bonus'],
                                            'deduction' => $sal['deduction']
                                        ])); ?>)"
                                                class="text-xs border border-outline-variant px-3 py-1.5 hover:bg-surface-container-low transition-colors rounded-xl font-semibold inline-flex items-center gap-1">
                                            <span class="material-symbols-outlined text-sm">description</span> View Payslip
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="flex gap-4">
            <a href="user.php" class="inline-flex items-center justify-center bg-primary text-white hover:bg-neutral-800 font-semibold px-4 h-11 transition-colors rounded-xl text-sm">
                Back to Dashboard
            </a>
        </div>
    </main>

    <!-- Footer Component -->
    <footer class="w-full bg-surface-container border-t border-outline-variant py-4 px-6 mt-12">
        <div class="flex justify-between items-center max-w-[1440px] mx-auto w-full">
            <span class="text-xs text-secondary">© 2026 He&amp;She Coffee. All rights reserved.</span>
        </div>
    </footer>

    <!-- Payslip View Modal -->
    <div id="payslip-modal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl max-w-lg w-full overflow-hidden border border-outline-variant flex flex-col max-h-[90vh]">
            <div class="p-4 border-b border-outline-variant flex justify-between items-center bg-surface-container-low no-print">
                <span class="font-bold text-sm text-secondary">Employee Monthly Payslip</span>
                <div class="flex gap-2">
                    <button onclick="window.print()" class="px-3 py-1.5 bg-primary text-white text-xs font-semibold rounded-xl flex items-center gap-1">
                        <span class="material-symbols-outlined text-sm">print</span> Print
                    </button>
                    <button onclick="closePayslip()" class="px-3 py-1.5 border border-outline-variant text-xs font-semibold rounded-xl flex items-center gap-1 hover:bg-surface-container-low">
                        <span class="material-symbols-outlined text-sm">close</span> Close
                    </button>
                </div>
            </div>

            <div id="payslip-modal-content" class="p-8 space-y-6 overflow-y-auto bg-white text-black font-sans">
                <!-- Brand Header -->
                <div class="flex justify-between items-start border-b-2 border-primary pb-4">
                    <div>
                        <h2 class="text-xl font-bold tracking-tight uppercase">He&She Coffee</h2>
                        <p class="text-[10px] text-gray-500 uppercase mt-0.5">Premium Cafe & Roastery</p>
                    </div>
                    <div class="text-right">
                        <h3 class="text-sm font-bold uppercase tracking-wider text-gray-400">Salary Statement</h3>
                        <p class="text-xs font-mono font-semibold mt-1" id="payslip-month"></p>
                    </div>
                </div>

                <!-- Profile Info -->
                <div class="grid grid-cols-2 gap-4 text-xs">
                    <div>
                        <span class="text-[9px] font-bold text-gray-400 uppercase block">Employee Name</span>
                        <strong class="text-sm font-semibold" id="payslip-name"></strong>
                        <span class="text-gray-500 block" id="payslip-email"></span>
                    </div>
                    <div>
                        <span class="text-[9px] font-bold text-gray-400 uppercase block">Bank Account</span>
                        <strong class="font-mono" id="payslip-bank"></strong>
                    </div>
                </div>

                <!-- Breakdown Table -->
                <table class="w-full text-left border-collapse text-xs mt-4">
                    <thead>
                        <tr class="border-b border-gray-300 font-bold uppercase tracking-wider text-gray-500">
                            <th class="py-2">Description</th>
                            <th class="py-2 text-right">Rate</th>
                            <th class="py-2 text-right">Units (Shifts)</th>
                            <th class="py-2 text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr>
                            <td class="py-3 font-semibold">Standard Shift Duties</td>
                            <td class="py-3 text-right font-mono" id="payslip-rate-cell"></td>
                            <td class="py-3 text-right font-mono" id="payslip-shifts-cell"></td>
                            <td class="py-3 text-right font-mono font-semibold" id="payslip-subtotal-cell"></td>
                        </tr>
                        <tr id="payslip-bonus-row" class="hidden">
                            <td class="py-3 text-green-700 font-semibold">Bonus</td>
                            <td class="py-3"></td>
                            <td class="py-3"></td>
                            <td class="py-3 text-right font-mono font-semibold text-green-700" id="payslip-bonus-cell"></td>
                        </tr>
                        <tr id="payslip-deduction-row" class="hidden">
                            <td class="py-3 text-red-700 font-semibold">Deduction</td>
                            <td class="py-3"></td>
                            <td class="py-3"></td>
                            <td class="py-3 text-right font-mono font-semibold text-red-700" id="payslip-deduction-cell"></td>
                        </tr>
                    </tbody>
                </table>

                <!-- Total Summary -->
                <div class="border-t border-gray-300 pt-4 flex justify-between items-center text-sm font-bold">
                    <span>Total Net Salary Pay</span>
                    <span class="text-lg font-mono text-primary" id="payslip-total-cell"></span>
                </div>

                <!-- Footer Signatures -->
                <div class="pt-12 grid grid-cols-2 gap-8 text-[10px] text-gray-400">
                    <div class="border-t border-dashed border-gray-300 pt-2 text-center">
                        Authorized Manager Signature
                    </div>
                    <div class="border-t border-dashed border-gray-300 pt-2 text-center">
                        Employee Acknowledgment
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    function openPayslip(data) {
        document.getElementById('payslip-month').innerText = data.month;
        document.getElementById('payslip-name').innerText = data.full_name;
        document.getElementById('payslip-email').innerText = data.email ? data.email : 'No email registered';
        document.getElementById('payslip-bank').innerText = data.bank ? data.bank : 'N/A';
        
        const rateVal = parseFloat(data.rate);
        const shiftsVal = parseInt(data.shifts);
        const salaryVal = parseFloat(data.salary);
        const bonusVal = parseFloat(data.bonus) || 0;
        const deductionVal = parseFloat(data.deduction) || 0;
        const netVal = salaryVal + bonusVal - deductionVal;

        document.getElementById('payslip-rate-cell').innerText = 'RM ' + rateVal.toFixed(2);
        document.getElementById('payslip-shifts-cell').innerText = shiftsVal.toString();
        document.getElementById('payslip-subtotal-cell').innerText = 'RM ' + salaryVal.toFixed(2);

        const bonusRow = document.getElementById('payslip-bonus-row');
        const deductRow = document.getElementById('payslip-deduction-row');
        if (bonusVal > 0) {
            bonusRow.classList.remove('hidden');
            document.getElementById('payslip-bonus-cell').innerText = '+ RM ' + bonusVal.toFixed(2);
        } else { bonusRow.classList.add('hidden'); }
        if (deductionVal > 0) {
            deductRow.classList.remove('hidden');
            document.getElementById('payslip-deduction-cell').innerText = '- RM ' + deductionVal.toFixed(2);
        } else { deductRow.classList.add('hidden'); }

        document.getElementById('payslip-total-cell').innerText = 'RM ' + netVal.toFixed(2);
        document.getElementById('payslip-modal').classList.remove('hidden');
    }

    // Close modal if clicked outside content card
    document.getElementById('payslip-modal').addEventListener('click', function(e) {
        if (e.target === this) {
            closePayslip();
        }
    });

    function closePayslip() {
        document.getElementById('payslip-modal').classList.add('hidden');
    }
    </script>
</body>
</html>
