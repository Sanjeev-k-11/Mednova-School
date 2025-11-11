<?php
session_start();
require_once "../database/config.php";

// Auth Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Admin') {
    header("location: ../login.php"); exit;
}
$admin_id = $_SESSION['admin_id'];
$errors = [];
$title_form = $description_form = $start_date_form = $end_date_form = $event_type_form = $color_form = "";
$edit_id = 0;

// Handle Create/Update
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $start_date = $_POST['start_date'];
    $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
    $event_type = $_POST['event_type'];
    $color = $_POST['color'];
    $edit_id = (int)$_POST['id'];

    if (empty($title)) $errors[] = "Event title is required.";
    if (empty($start_date)) $errors[] = "Start date is required.";

    if (empty($errors)) {
        if ($edit_id > 0) { // Update
            $sql = "UPDATE events SET title = ?, description = ?, start_date = ?, end_date = ?, event_type = ?, color = ? WHERE id = ?";
            if($stmt = mysqli_prepare($link, $sql)) mysqli_stmt_bind_param($stmt, "ssssssi", $title, $description, $start_date, $end_date, $event_type, $color, $edit_id);
        } else { // Create
            $sql = "INSERT INTO events (title, description, start_date, end_date, event_type, color, created_by_admin_id) VALUES (?, ?, ?, ?, ?, ?, ?)";
            if($stmt = mysqli_prepare($link, $sql)) mysqli_stmt_bind_param($stmt, "ssssssi", $title, $description, $start_date, $end_date, $event_type, $color, $admin_id);
        }
        if(isset($stmt)){
            mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
            $_SESSION['message'] = $edit_id > 0 ? "Event updated successfully." : "Event created successfully.";
            $_SESSION['message_type'] = "success";
            header("location: manage_events.php"); exit;
        }
    } else { // Repopulate on error
        $title_form = $title; $description_form = $description; $start_date_form = $start_date; $end_date_form = $end_date; $event_type_form = $event_type; $color_form = $color; $edit_id = $edit_id;
    }
}

// Handle GET requests (Delete, Edit)
if(isset($_GET['delete_id'])){
    $delete_id = (int)$_GET['delete_id'];
    $sql_del = "DELETE FROM events WHERE id = ?";
    if($stmt_del = mysqli_prepare($link, $sql_del)){
        mysqli_stmt_bind_param($stmt_del, "i", $delete_id);
        mysqli_stmt_execute($stmt_del);
        $_SESSION['message'] = "Event deleted successfully."; $_SESSION['message_type'] = "success";
        header("location: manage_events.php"); exit;
    }
}
if(isset($_GET['edit_id'])){
    $edit_id = (int)$_GET['edit_id'];
    $sql_edit = "SELECT * FROM events WHERE id = ?";
    if($stmt_edit = mysqli_prepare($link, $sql_edit)){
        mysqli_stmt_bind_param($stmt_edit, "i", $edit_id);
        mysqli_stmt_execute($stmt_edit);
        if($row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_edit))){
            $title_form = $row['title']; $description_form = $row['description']; 
            $start_date_form = date('Y-m-d\TH:i', strtotime($row['start_date']));
            $end_date_form = !empty($row['end_date']) ? date('Y-m-d\TH:i', strtotime($row['end_date'])) : '';
            $event_type_form = $row['event_type']; $color_form = $row['color'];
        }
    }
}

// Fetch all events for the table
$all_events = [];
$sql_fetch = "SELECT * FROM events ORDER BY start_date DESC";
if($result = mysqli_query($link, $sql_fetch)) while($row = mysqli_fetch_assoc($result)) $all_events[] = $row;

mysqli_close($link);
require_once './admin_header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Events</title>
    <!-- (Using the same consistent styling) -->
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f4f7f6; }
        .container { max-width: 1200px; margin: auto; margin-top: 100px; margin-bottom: 100px; }
        .form-container, .table-container { background: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .table-container { margin-top: 30px; }
        h2 { text-align: center; color: #1e2a4c; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 6px; font-weight: 600; color: #444; }
        input, select, textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 6px; }
        .btn { display: block; width: 100%; padding: 14px; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 18px; font-weight: 600; background: #007bff; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th, .data-table td { padding: 12px; text-align: left; border-bottom: 1px solid #eef2f7; }
        .data-table thead th { background-color: #1e2a4c; color: white; }
        .color-preview { width: 20px; height: 20px; display: inline-block; vertical-align: middle; border-radius: 4px; border: 1px solid #ccc; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 6px; }
        .alert-success { color: #155724; background-color: #d4edda; }
        .alert-danger { color: #721c24; background-color: #f8d7da; }
    </style>
</head>
<body>
<div class="container">
    <h2>Manage School Events</h2>
    <div class="form-container">
        <h3><?php echo ($edit_id > 0) ? 'Edit Event' : 'Create New Event'; ?></h3>
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger"><ul><?php foreach ($errors as $error) echo '<li>' . htmlspecialchars($error) . '</li>'; ?></ul></div>
        <?php endif; ?>
        <form action="manage_events.php" method="post">
            <input type="hidden" name="id" value="<?php echo $edit_id; ?>">
            <div class="form-group"><label>Event Title</label><input type="text" name="title" value="<?php echo htmlspecialchars($title_form); ?>" required></div>
            <div class="form-grid">
                <div class="form-group"><label>Start Date & Time</label><input type="datetime-local" name="start_date" value="<?php echo htmlspecialchars($start_date_form); ?>" required></div>
                <div class="form-group"><label>End Date & Time (Optional)</label><input type="datetime-local" name="end_date" value="<?php echo htmlspecialchars($end_date_form); ?>"></div>
            </div>
            <div class="form-grid">
                <div class="form-group"><label>Event Type</label><select name="event_type" required>
                    <option value="School Event" <?php if($event_type_form == 'School Event') echo 'selected'; ?>>School Event</option>
                    <option value="Holiday" <?php if($event_type_form == 'Holiday') echo 'selected'; ?>>Holiday</option>
                    <option value="Exam" <?php if($event_type_form == 'Exam') echo 'selected'; ?>>Exam</option>
                    <option value="Meeting" <?php if($event_type_form == 'Meeting') echo 'selected'; ?>>Meeting</option>
                    <option value="Other" <?php if($event_type_form == 'Other') echo 'selected'; ?>>Other</option>
                </select></div>
                <div class="form-group"><label>Event Color</label><input type="color" name="color" value="<?php echo htmlspecialchars($color_form ?? '#007bff'); ?>" style="padding: 5px; height: 45px;"></div>
            </div>
            <div class="form-group"><label>Description</label><textarea name="description" rows="3"><?php echo htmlspecialchars($description_form); ?></textarea></div>
            <input type="submit" class="btn" value="<?php echo ($edit_id > 0) ? 'Update Event' : 'Create Event'; ?>">
        </form>
    </div>

    <div class="table-container">
        <h3>All Events</h3>
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-<?php echo $_SESSION['message_type']; ?>"><?php echo $_SESSION['message']; unset($_SESSION['message'], $_SESSION['message_type']); ?></div>
        <?php endif; ?>
        <table class="data-table">
            <thead><tr><th>Title</th><th>Type</th><th>Starts</th><th>Ends</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($all_events as $event): ?>
                <tr>
                    <td><span class="color-preview" style="background-color:<?php echo htmlspecialchars($event['color']); ?>;"></span> <?php echo htmlspecialchars($event['title']); ?></td>
                    <td><?php echo htmlspecialchars($event['event_type']); ?></td>
                    <td><?php echo date("M j, Y g:i A", strtotime($event['start_date'])); ?></td>
                    <td><?php echo !empty($event['end_date']) ? date("M j, Y g:i A", strtotime($event['end_date'])) : 'N/A'; ?></td>
                    <td>
                        <a href="?edit_id=<?php echo $event['id']; ?>">Edit</a> |
                        <a href="?delete_id=<?php echo $event['id']; ?>" onclick="return confirm('Are you sure?');">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
<?php require_once './admin_footer.php'; ?>