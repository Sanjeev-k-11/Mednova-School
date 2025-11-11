<?php
session_start();
require_once "../database/config.php"; // This file establishes the $link variable

// Auth Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Super Admin') {
    header("location: ../login.php");
    exit;
}

// Initialize variables to prevent errors
$total_revenue = $total_expenses = $total_profit = $total_students = $unpaid_invoices_count = $total_due_amount = 0;
$profit_percentage = 0;
$monthly_chart_labels = $income_data = $expense_data = $class_labels = $student_counts = [];
$expense_data_for_js = [];

// --- DATA FETCHING FOR DASHBOARD ---
if ($link) {
    // 1. KPI Cards Data
    $sql_total_revenue = "SELECT SUM(amount) as total FROM income";
    $total_revenue = $link->query($sql_total_revenue)->fetch_assoc()['total'] ?? 0;

    $sql_total_expenses = "SELECT SUM(amount) as total FROM expenses";
    $total_expenses = $link->query($sql_total_expenses)->fetch_assoc()['total'] ?? 0;

    $total_profit = $total_revenue - $total_expenses;
    $profit_percentage = ($total_revenue > 0) ? ($total_profit / $total_revenue) * 100 : 0;

    $sql_total_students = "SELECT COUNT(id) as total FROM students WHERE status = 'Active'";
    $total_students = $link->query($sql_total_students)->fetch_assoc()['total'] ?? 0;


    // 2. Monthly Income vs Expenses Chart Data
    for ($i = 11; $i >= 0; $i--) {
        $month_key = date('Y-m', strtotime("-$i months"));
        $monthly_chart_labels[] = date('M', strtotime("-$i months")); // Use 'M' for short month name like the example
        $income_by_month[$month_key] = 0;
        $expense_by_month[$month_key] = 0;
    }
    $sql_monthly_income = "SELECT DATE_FORMAT(income_date, '%Y-%m') AS month, SUM(amount) AS total FROM income WHERE income_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) GROUP BY month";
    $income_res = $link->query($sql_monthly_income);
    if($income_res) { while ($row = $income_res->fetch_assoc()) { $income_by_month[$row['month']] = $row['total']; } }
    $sql_monthly_expenses = "SELECT DATE_FORMAT(expense_date, '%Y-%m') AS month, SUM(amount) AS total FROM expenses WHERE expense_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) GROUP BY month";
    $expense_res = $link->query($sql_monthly_expenses);
    if($expense_res) { while ($row = $expense_res->fetch_assoc()) { $expense_by_month[$row['month']] = $row['total']; } }
    $income_data = array_values($income_by_month);
    $expense_data = array_values($expense_by_month);


    // 3. Students per Class Chart Data
    $sql_students_by_class = "SELECT c.class_name, c.section_name, COUNT(s.id) AS count FROM students s JOIN classes c ON s.class_id = c.id WHERE s.status = 'Active' GROUP BY s.class_id ORDER BY c.class_name, c.section_name";
    $students_by_class_res = $link->query($sql_students_by_class);
    if ($students_by_class_res) {
        while ($row = $students_by_class_res->fetch_assoc()) {
            $class_labels[] = $row['class_name'] . ' ' . $row['section_name'];
            $student_counts[] = $row['count'];
        }
    }


    // 4. Expense Breakdown Chart Data
    $sql_total_expenses_for_chart = "SELECT SUM(amount) as total FROM expenses";
    $total_expenses_for_chart = $link->query($sql_total_expenses_for_chart)->fetch_assoc()['total'] ?? 1;
    if($total_expenses_for_chart == 0) $total_expenses_for_chart = 1;

    $sql_expenses_by_cat = "SELECT category, SUM(amount) AS total FROM expenses GROUP BY category ORDER BY total DESC LIMIT 6";
    $expenses_by_cat_res = $link->query($sql_expenses_by_cat);
    if ($expenses_by_cat_res) {
        while ($row = $expenses_by_cat_res->fetch_assoc()) {
            $percentage = ($row['total'] / $total_expenses_for_chart) * 100;
            $expense_data_for_js[] = [
                'label' => $row['category'],
                'amount' => (float)$row['total'],
                'percentage' => round($percentage, 1)
            ];
        }
    }

    $link->close();
}

 ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School Dashboard</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <style>
        :root {
            --bg-color: #343a40;
            --card-bg: #495057;
            --sidebar-bg: #212529;
            --text-color: #f8f9fa;
            --text-muted: #adb5bd;
            --primary-accent: #20c997; /* Teal */
            --secondary-accent: #fd7e14; /* Orange */
            --border-color: #5a6268;
            --font-family: 'Segoe UI', system-ui, sans-serif;
        }
        * { box-sizing: border-box; }
        body {
            font-family: var(--font-family);
            background-color: var(--bg-color);
            margin: 0;
            color: var(--text-color);
            display: flex;
        }
        .filter-sidebar {
            width: 250px;
            background-color: var(--sidebar-bg);
            padding: 20px;
            height: 100vh;
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
        }
        .logo {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 30px;
        }
        .filter-group {
            margin-bottom: 25px;
        }
        .filter-title {
            font-size: 0.8rem;
            text-transform: uppercase;
            color: var(--text-muted);
            margin-bottom: 10px;
            font-weight: 600;
        }
        .filter-btn {
            display: block;
            width: 100%;
            padding: 10px 15px;
            margin-bottom: 5px;
            background-color: transparent;
            border: none;
            color: var(--text-muted);
            text-align: left;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.2s, color 0.2s;
        }
        .filter-btn:hover {
            background-color: var(--card-bg);
            color: var(--text-color);
        }
        .filter-btn.active {
            background-color: var(--primary-accent);
            color: #fff;
            font-weight: 600;
        }
        .dashboard-container {
            flex-grow: 1;
            padding: 25px;
            height: 100vh;
            overflow-y: auto;
        }
        .dashboard-main-header {
            display: flex;
            align-items: center;
            margin-bottom: 25px;
            font-size: 1.5rem;
            font-weight: 600;
        }
        .dashboard-main-header .icon {
            color: var(--primary-accent);
            margin-right: 15px;
        }
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 25px;
        }
        .card {
            background: var(--card-bg);
            border-radius: 8px;
            padding: 20px;
            border: 1px solid var(--border-color);
        }
        .kpi-card {
            display: flex;
            align-items: center;
        }
        .kpi-icon {
            font-size: 1.8rem;
            margin-right: 15px;
            color: var(--text-muted);
        }
        .kpi-details .kpi-title {
            font-size: 0.85rem;
            color: var(--text-muted);
        }
        .kpi-details .kpi-value {
            font-size: 1.6rem;
            font-weight: 700;
        }
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }
        .chart-card-large { grid-column: span 3; }
        .chart-card-medium { grid-column: span 1; }
        .card-title { font-size: 1.1rem; font-weight: 600; margin-bottom: 20px; }
        #doughnutLegend {
            margin-top: 15px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px 20px;
            font-size: 0.9rem;
        }
        .legend-item { display: flex; align-items: center; }
        .legend-color-box { width: 10px; height: 10px; border-radius: 3px; margin-right: 8px; }
        .legend-label { color: var(--text-muted); margin-right: auto; }
        .legend-percentage { font-weight: 600; }
    </style>
</head>
<body>
     <div class="filter-sidebar">
        <div class="logo">EBps</div>
        <div class="filter-group">
            <div class="filter-title">Year</div>
            <button class="filter-btn active">2024</button>
            <button class="filter-btn">2023</button>
        </div>
        <div class="filter-group">
            <div class="filter-title">Month</div>
            <button class="filter-btn">Jan</button>
            <button class="filter-btn active">Feb</button>
            <!-- Add other months as needed -->
        </div>
    </div>

    <div class="dashboard-container">
        <div class="dashboard-main-header">
            <i class="fas fa-home icon"></i> SCHOOL DASHBOARD
        </div>

        <div class="kpi-grid">
            <div class="card kpi-card">
                <i class="fas fa-wallet kpi-icon"></i>
                <div class="kpi-details">
                    <div class="kpi-title">TOTAL REVENUE</div>
                    <div class="kpi-value">₹<?= number_format($total_revenue) ?></div>
                </div>
            </div>
            <div class="card kpi-card">
                 <i class="fas fa-chart-line kpi-icon"></i>
                <div class="kpi-details">
                    <div class="kpi-title">TOTAL PROFIT</div>
                    <div class="kpi-value">₹<?= number_format($total_profit) ?></div>
                </div>
            </div>
             <div class="card kpi-card">
                <i class="fas fa-percentage kpi-icon"></i>
                <div class="kpi-details">
                    <div class="kpi-title">PROFIT %</div>
                    <div class="kpi-value"><?= number_format($profit_percentage, 2) ?>%</div>
                </div>
            </div>
            <div class="card kpi-card">
                <i class="fas fa-user-graduate kpi-icon"></i>
                <div class="kpi-details">
                    <div class="kpi-title">ACTIVE STUDENTS</div>
                    <div class="kpi-value"><?= number_format($total_students) ?></div>
                </div>
            </div>
        </div>

        <div class="charts-grid">
            <div class="card chart-card-large">
                <h2 class="card-title">Revenue Actual vs Expenses</h2>
                <div style="height: 300px;"><canvas id="monthlyPerformanceChart"></canvas></div>
            </div>
            <div class="card chart-card-medium">
                <h2 class="card-title">Profit by Expense Category</h2>
                <div style="height: 180px; margin: 0 auto;"><canvas id="expenseCategoryChart"></canvas></div>
                <div id="doughnutLegend"></div>
            </div>
            <div class="card chart-card-medium">
                 <h2 class="card-title">Students by Class</h2>
                <div style="height: 250px;"><canvas id="studentsByClassChart"></canvas></div>
            </div>
        </div>
    </div>
 
<script>
document.addEventListener('DOMContentLoaded', function () {
    const expenseData = <?= json_encode($expense_data_for_js) ?>;
    const expenseLabels = expenseData.map(d => d.label);
    const expenseAmounts = expenseData.map(d => d.amount);
    const themeColors = ['#20c997', '#fd7e14', '#0dcaf0', '#6f42c1', '#d63384', '#343a40'];
    const textMutedColor = '<?= 'var(--text-muted)' ?>';
    const borderColor = '<?= 'var(--border-color)' ?>';

    // Function to generate the custom legend for the doughnut chart
    const generateDoughnutLegend = (chart) => {
        const legendContainer = document.getElementById('doughnutLegend');
        if (!legendContainer) return;
        legendContainer.innerHTML = '';
        expenseData.forEach((item, index) => {
            const color = chart.data.datasets[0].backgroundColor[index % themeColors.length];
            legendContainer.innerHTML += `
                <div class="legend-item">
                    <div class="legend-color-box" style="background-color: ${color}"></div>
                    <div class="legend-label">${item.label}</div>
                    <div class="legend-percentage">${item.percentage}%</div>
                </div>`;
        });
    };

    // 1. Monthly Performance Line Chart
    const ctxMonthly = document.getElementById('monthlyPerformanceChart').getContext('2d');
    new Chart(ctxMonthly, {
        type: 'line',
        data: {
            labels: <?= json_encode($monthly_chart_labels) ?>,
            datasets: [{
                label: 'Revenue',
                data: <?= json_encode($income_data) ?>,
                borderColor: 'var(--primary-accent)',
                tension: 0.4,
                borderWidth: 2
            }, {
                label: 'Expenses',
                data: <?= json_encode($expense_data) ?>,
                borderColor: 'var(--secondary-accent)',
                tension: 0.4,
                borderWidth: 2
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            scales: {
                y: { beginAtZero: true, grid: { color: borderColor }, ticks: { color: textMutedColor } },
                x: { grid: { display: false }, ticks: { color: textMutedColor } }
            },
            plugins: { legend: { labels: { color: textMutedColor, usePointStyle: true, boxWidth: 8 } } }
        }
    });

    // 2. Expense Category Doughnut Chart
    const ctxExpenses = document.getElementById('expenseCategoryChart').getContext('2d');
    new Chart(ctxExpenses, {
        type: 'doughnut',
        data: {
            labels: expenseLabels,
            datasets: [{ data: expenseAmounts, backgroundColor: themeColors, borderWidth: 0, hoverOffset: 4 }]
        },
        options: {
            responsive: true, maintainAspectRatio: false, cutout: '70%',
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (c) => `${c.label}: ₹${c.raw.toLocaleString('en-IN')}`
                    }
                }
            }
        },
        plugins: [{ id: 'customDoughnutLegend', afterUpdate: generateDoughnutLegend }]
    });

    // 3. Students by Class Bar Chart
    const ctxStudents = document.getElementById('studentsByClassChart').getContext('2d');
    new Chart(ctxStudents, {
        type: 'bar',
        data: {
            labels: <?= json_encode($class_labels) ?>,
            datasets: [{ data: <?= json_encode($student_counts) ?>, backgroundColor: 'var(--primary-accent)' }]
        },
        options: {
            indexAxis: 'y', responsive: true, maintainAspectRatio: false,
            scales: {
                x: { beginAtZero: true, grid: { display: false, drawBorder: false }, ticks: { color: textMutedColor } },
                y: { grid: { display: false }, ticks: { color: textMutedColor } }
            },
            plugins: { legend: { display: false } }
        }
    });
});
</script>

</body>
</html>
 