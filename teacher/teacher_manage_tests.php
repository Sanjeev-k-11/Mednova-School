<?php
session_start();
require_once "../database/config.php";

// --- AUTHENTICATION & SECURITY ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Teacher') {
    header("location: ../login.php");
    exit;
}
$teacher_id = $_SESSION["id"];
$teacher_name = $_SESSION["full_name"];

// --- DATA FETCHING ---
$test_id = $_GET['test_id'] ?? null;
$tests = [];
$test_details = null;
$attempts = [];
$stats = ['total_students' => 0, 'attempts' => 0, 'average_score' => 0];

if ($test_id) {
    // --- Detailed View: Fetch data for a single test ---
    $sql_test = "SELECT ot.*, c.class_name, c.section_name, s.subject_name, (SELECT SUM(marks) FROM online_test_questions WHERE test_id = ot.id) as total_marks FROM online_tests ot JOIN classes c ON ot.class_id = c.id JOIN subjects s ON ot.subject_id = s.id WHERE ot.id = ? AND ot.teacher_id = ?";
    if ($stmt_t = mysqli_prepare($link, $sql_test)) {
        mysqli_stmt_bind_param($stmt_t, "ii", $test_id, $teacher_id);
        mysqli_stmt_execute($stmt_t);
        $test_details = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_t));
        mysqli_stmt_close($stmt_t);
    }

    if ($test_details) {
        // Fetch attempts
        $sql_attempts = "SELECT sta.*, st.first_name, st.middle_name, st.last_name, st.roll_number FROM student_test_attempts sta JOIN students st ON sta.student_id = st.id WHERE sta.test_id = ? ORDER BY st.roll_number";
        if ($stmt_a = mysqli_prepare($link, $sql_attempts)) {
            mysqli_stmt_bind_param($stmt_a, "i", $test_id);
            mysqli_stmt_execute($stmt_a);
            $attempts = mysqli_fetch_all(mysqli_stmt_get_result($stmt_a), MYSQLI_ASSOC);
            mysqli_stmt_close($stmt_a);
        }
        
        // Fetch stats
        $sql_total_students = "SELECT COUNT(id) as total FROM students WHERE class_id = ?";
        if($stmt_ts = mysqli_prepare($link, $sql_total_students)) {
            mysqli_stmt_bind_param($stmt_ts, "i", $test_details['class_id']);
            mysqli_stmt_execute($stmt_ts);
            $stats['total_students'] = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_ts))['total'];
            mysqli_stmt_close($stmt_ts);
        }
        
        $stats['attempts'] = count($attempts);
        if ($stats['attempts'] > 0) {
            $total_score = array_sum(array_column($attempts, 'score'));
            $stats['average_score'] = round($total_score / $stats['attempts'], 2);
        }
    } else {
        // Test not found or not owned by this teacher, redirect back
        $_SESSION['flash_message_error'] = "Test not found or you are not authorized to view it.";
        header("Location: teacher_manage_tests.php");
        exit;
    }
} else {
    // --- List View: Fetch all tests created by this teacher ---
    $sql_tests = "SELECT ot.id, ot.title, ot.created_at, ot.status, c.class_name, c.section_name, s.subject_name FROM online_tests ot JOIN classes c ON ot.class_id = c.id JOIN subjects s ON ot.subject_id = s.id WHERE ot.teacher_id = ? ORDER BY ot.created_at DESC";
    if ($stmt = mysqli_prepare($link, $sql_tests)) {
        mysqli_stmt_bind_param($stmt, "i", $teacher_id);
        mysqli_stmt_execute($stmt);
        $tests = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);
    }
}

require_once './teacher_header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Online Tests - Teacher Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Custom Styles -->
    <style>
        @keyframes gradientAnimation { 0%{background-position:0% 50%} 50%{background-position:100% 50%} 100%{background-position:0% 50%} }
        body { 
            font-family: 'Inter', sans-serif; 
            background: linear-gradient(-45deg, #e0f2f7, #e3f2fd, #bbdefb, #90caf9); /* Light Blue/Azure theme */
            background-size: 400% 400%; 
            animation: gradientAnimation 15s ease infinite; 
            color: #333;
        }
        .dashboard-container { max-width: 1600px; margin: auto; padding: 20px; margin-top: 80px; margin-bottom: 100px;}
        .page-header { background: rgba(255, 255, 255, 0.7); backdrop-filter: blur(10px); border-radius: 15px; padding: 2.5rem 2rem; margin-bottom: 2rem; box-shadow: 0 4px 15px rgba(0,0,0,0.1); border: 1px solid rgba(255, 255, 255, 0.5); text-align: center; }
        .page-header h1 { font-weight: 700; color: #1a2a4b; text-shadow: 1px 1px 2px rgba(0,0,0,0.1); margin-bottom: 1rem; font-size: 2.5rem; display: flex; align-items: center; justify-content: center; gap: 15px; }
        .welcome-info-block { padding: 1rem; background: rgba(255, 255, 255, 0.5); border-radius: 0.5rem; display: inline-block; margin-top: 1rem; border: 1px solid rgba(255, 255, 255, 0.3); box-shadow: 0 2px 5px rgba(0,0,0,0.03); }
        .welcome-info { font-weight: 500; color: #666; margin-bottom: 0; font-size: 0.95rem; }
        .welcome-info strong { color: #333; }
        .section-title { font-size: 1.5rem; font-weight: 600; color: #1a2a4b; border-bottom: 1px solid #e5e7eb; padding-bottom: 15px; margin-bottom: 15px; display: flex; align-items: center; gap: 10px; }
        .dashboard-panel { background: rgba(255, 255, 255, 0.7); backdrop-filter: blur(10px); border-radius: 15px; padding: 2rem; box-shadow: 0 4px 15px rgba(0,0,0,0.1); border: 1px solid rgba(255, 255, 255, 0.5); }
        .stat-card { background: rgba(255, 255, 255, 0.9); border-radius: 15px; padding: 25px; border: 1px solid rgba(255, 255, 255, 0.7); display: flex; align-items: center; color: #1a2a4b; box-shadow: 0 4px 10px rgba(0,0,0,0.08); transition: transform 0.2s, box-shadow 0.2s; }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 18px rgba(0,0,0,0.12); }
        .stat-card-icon { font-size: 2rem; width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 20px; flex-shrink: 0; }
        .stat-card-icon.bg-blue-dark { background: #3f51b5; color: #fff; }
        .stat-card-icon.bg-green-dark { background: #4caf50; color: #fff; }
        .stat-card-icon.bg-yellow-dark { background: #ffc107; color: #fff; }
        .stat-card-content h3 { margin: 0; font-size: 1rem; opacity: 0.9; }
        .stat-card-content p { margin: 5px 0 0; font-size: 2em; font-weight: 700; }
        .stat-card-content .sub-text { font-size: 1rem; font-weight: 500; opacity: 0.8; }
        .themed-table { width: 100%; border-collapse: separate; border-spacing: 0; background-color: rgba(255,255,255,0.4); border-radius: 10px; overflow: hidden; }
        .themed-table-header { background-color: rgba(0,0,0,0.08); }
        .themed-table-header th { padding: 12px 15px; text-align: left; font-weight: 600; color: #1a2a4b; text-transform: uppercase; font-size: 0.85rem; border-bottom: 1px solid rgba(0,0,0,0.1); }
        .themed-table-row { background-color: rgba(255,255,255,0.4); transition: background-color 0.2s ease; border-bottom: 1px solid rgba(0,0,0,0.05); }
        .themed-table-row:hover { background-color: rgba(255,255,255,0.6); }
        .themed-table-cell { padding: 12px 15px; font-size: 0.9rem; color: #333; vertical-align: middle; }
        .status-badge { display: inline-block; padding: 0.3em 0.8em; border-radius: 50px; font-size: 0.75rem; font-weight: 600; }
        .status-badge.Completed { background-color: #d4edda; color: #155724; }
        .status-badge.InProgress { background-color: #fff3cd; color: #856404; }
        .status-badge.Published { background-color: #cce5ff; color: #004085; }
        .status-badge.Draft { background-color: #e2e3e5; color: #383d41; }
        .text-success-themed { color: #28a745; }
        .text-warning-themed { color: #ffc107; }
        .text-danger-themed { color: #dc3545; }
        .back-link { display: inline-flex; align-items: center; background: rgba(255, 255, 255, 0.4); backdrop-filter: blur(5px); color: #1a2a4b; padding: 10px 15px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 0.9em; transition: background 0.3s, transform 0.2s; border: 1px solid rgba(255,255,255,0.3); }
        .back-link:hover { background: rgba(255, 255, 255, 0.6); transform: translateY(-2px); }
        .btn-themed-primary { background-color: #1a2a4b; color: #fff; font-weight: 600; padding: 10px 25px; border-radius: 10px; border: none; transition: background-color 0.2s, transform 0.2s; text-decoration: none; }
        .btn-themed-primary:hover { background-color: #0d1a33; transform: translateY(-2px); color: #fff; }
        .btn-sm-view { background-color: #007bff; color: #fff; font-size: 0.8rem; font-weight: 500; padding: 0.4rem 0.8rem; border-radius: 0.5rem; border: none; transition: background-color 0.2s; text-decoration: none; display: inline-flex; align-items: center; gap: 0.3rem; }
        .btn-sm-view:hover { background-color: #0069d9; color: #fff; }
    </style>
</head>
<body>
<div class="dashboard-container">
    <?php if ($test_id && $test_details): // --- DETAILED RESULTS VIEW --- ?>
        <a href="teacher_manage_tests.php" class="back-link mb-4"><i class="fas fa-arrow-left me-2"></i>Back to All Tests</a>
        <div class="page-header text-start">
            <h1 class="mb-1"><?php echo htmlspecialchars($test_details['title']); ?></h1>
            <p class="text-muted fs-6">Class: <?php echo htmlspecialchars($test_details['class_name'].' - '.$test_details['section_name']); ?> | Subject: <?php echo htmlspecialchars($test_details['subject_name']); ?></p>
        </div>
        
        <div class="row g-4 mb-4">
            <div class="col-md-4 d-flex">
                <div class="stat-card flex-grow-1">
                    <div class="stat-card-icon bg-blue-dark"><i class="fas fa-users"></i></div>
                    <div class="stat-card-content"><h3>Total Students</h3><p><?php echo $stats['total_students']; ?></p></div>
                </div>
            </div>
            <div class="col-md-4 d-flex">
                <div class="stat-card flex-grow-1">
                    <div class="stat-card-icon bg-green-dark"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-card-content"><h3>Attempts</h3><p><?php echo $stats['attempts']; ?> <span class="sub-text">/ <?php echo $stats['total_students']; ?></span></p></div>
                </div>
            </div>
            <div class="col-md-4 d-flex">
                <div class="stat-card flex-grow-1">
                    <div class="stat-card-icon bg-yellow-dark"><i class="fas fa-star-half-alt"></i></div>
                    <div class="stat-card-content"><h3>Average Score</h3><p><?php echo $stats['average_score']; ?> <span class="sub-text">/ <?php echo $test_details['total_marks'] ?? 'N/A'; ?></span></p></div>
                </div>
            </div>
        </div>

        <div class="dashboard-panel">
            <h2 class="section-title">Student Performance</h2>
            <div class="themed-table-wrapper">
                <table class="themed-table">
                    <thead class="themed-table-header">
                        <tr><th>Roll No.</th><th>Student Name</th><th>Status</th><th>Score</th><th>Percentage</th></tr>
                    </thead>
                    <tbody>
                        <?php if(empty($attempts)): ?>
                            <tr class="themed-table-row"><td colspan="5" class="themed-table-cell text-center text-muted py-4">No students have taken this test yet.</td></tr>
                        <?php else: foreach($attempts as $attempt): 
                            $percentage = ($attempt['total_marks'] > 0) ? round(($attempt['score'] / $attempt['total_marks']) * 100, 1) : 0;
                            $colorClass = $percentage >= 75 ? 'text-success-themed' : ($percentage >= 50 ? 'text-warning-themed' : 'text-danger-themed');
                        ?>
                            <tr class="themed-table-row">
                                <td class="themed-table-cell fw-bold"><?php echo htmlspecialchars($attempt['roll_number']); ?></td>
                                <td class="themed-table-cell"><?php echo htmlspecialchars(trim($attempt['first_name'].' '.$attempt['middle_name'].' '.$attempt['last_name'])); ?></td>
                                <td class="themed-table-cell"><span class="status-badge <?php echo htmlspecialchars($attempt['status']); ?>"><?php echo htmlspecialchars($attempt['status']); ?></span></td>
                                <td class="themed-table-cell fw-bold"><?php echo $attempt['score'] ?? 'N/A'; ?> / <?php echo $attempt['total_marks']; ?></td>
                                <td class="themed-table-cell fw-bold <?php echo $colorClass; ?>"><?php echo $percentage; ?>%</td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php else: // --- LIST OF ALL TESTS VIEW --- ?>
        <header class="page-header">
            <h1 class="page-title"><i class="fas fa-laptop-code"></i> Manage Online Tests</h1>
            <div class="welcome-info-block"><p class="welcome-info">Teacher: <strong><?php echo htmlspecialchars($teacher_name); ?></strong></p></div>
        </header>
        <div class="dashboard-panel">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="section-title mb-0">Your Created Tests</h2>
                <a href="teacher_create_test.php" class="btn btn-themed-primary"><i class="fas fa-plus me-2"></i>Create New Test</a>
            </div>

            <div class="themed-table-wrapper">
                <table class="themed-table">
                    <thead class="themed-table-header">
                        <tr><th>Test Title</th><th>Class & Subject</th><th>Status</th><th>Date Created</th><th class="text-center">Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php if(empty($tests)): ?>
                            <tr class="themed-table-row"><td colspan="5" class="themed-table-cell text-center text-muted py-4">You have not created any online tests yet.</td></tr>
                        <?php else: foreach($tests as $test): ?>
                            <tr class="themed-table-row">
                                <td class="themed-table-cell fw-bold"><?php echo htmlspecialchars($test['title']); ?></td>
                                <td class="themed-table-cell"><?php echo htmlspecialchars($test['class_name'].' / '.$test['subject_name']); ?></td>
                                <td class="themed-table-cell"><span class="status-badge <?php echo htmlspecialchars($test['status']); ?>"><?php echo htmlspecialchars($test['status']); ?></span></td>
                                <td class="themed-table-cell"><?php echo date('d M, Y', strtotime($test['created_at'])); ?></td>
                                <td class="themed-table-cell text-center">
                                    <a href="?test_id=<?php echo $test['id']; ?>" class="btn-sm-view" title="View Results"><i class="fas fa-chart-bar"></i> View Results</a>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php require_once './teacher_footer.php'; ?>