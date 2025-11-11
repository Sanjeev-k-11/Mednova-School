<?php
session_start();
require_once "../database/config.php";

// --- Authentication Check ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Super Admin') {
    header("location: ../login.php"); exit;
}

// =================================================================================
// --- ACTION HANDLER: Process all form submissions (Create, Update, Delete) ---
// =================================================================================

$errors = [];
$fee_type_name_form = $description_form = "";
$edit_fee_type_id = 0;

// --- HANDLE POST REQUESTS ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';

    // --- Action: Create or Update a Fee Type ---
    if ($action === 'manage_fee_type') {
        $fee_type_name = trim($_POST['fee_type_name']);
        $description = trim($_POST['description']);
        $edit_id = (int)$_POST['id'];

        if (empty($fee_type_name)) $errors[] = "Fee Type Name is required.";
        else { // Check for duplicates
            $sql_check = "SELECT id FROM fee_types WHERE fee_type_name = ? AND id != ?";
            if ($stmt_check = mysqli_prepare($link, $sql_check)) {
                mysqli_stmt_bind_param($stmt_check, "si", $fee_type_name, $edit_id);
                mysqli_stmt_execute($stmt_check);
                mysqli_stmt_store_result($stmt_check);
                if (mysqli_stmt_num_rows($stmt_check) > 0) $errors[] = "This Fee Type already exists.";
                mysqli_stmt_close($stmt_check);
            }
        }
        
        if (empty($errors)) {
            if ($edit_id > 0) { // Update
                $sql = "UPDATE fee_types SET fee_type_name = ?, description = ? WHERE id = ?";
                if($stmt = mysqli_prepare($link, $sql)) mysqli_stmt_bind_param($stmt, "ssi", $fee_type_name, $description, $edit_id);
            } else { // Create
                $sql = "INSERT INTO fee_types (fee_type_name, description) VALUES (?, ?)";
                if($stmt = mysqli_prepare($link, $sql)) mysqli_stmt_bind_param($stmt, "ss", $fee_type_name, $description);
            }
            mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
            header("location: manage_fees.php#feeTypes"); exit;
        } else {
            // Repopulate form on error
            $fee_type_name_form = $fee_type_name;
            $description_form = $description;
            $edit_fee_type_id = $edit_id;
        }
    }

    // --- Action: Save the fee structure for a specific class ---
    if ($action === 'save_class_fees') {
        $class_id_to_update = (int)$_POST['class_id'];
        $amounts = $_POST['amounts'];
        $frequencies = $_POST['frequencies'];

        foreach ($amounts as $fee_type_id => $amount) {
            $frequency = $frequencies[$fee_type_id];
            $amount = (float)$amount;

            $sql_check = "SELECT id FROM class_fees WHERE class_id = ? AND fee_type_id = ?";
            if($stmt_check = mysqli_prepare($link, $sql_check)){
                mysqli_stmt_bind_param($stmt_check, "ii", $class_id_to_update, $fee_type_id);
                mysqli_stmt_execute($stmt_check);
                $existing_fee = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_check));
                mysqli_stmt_close($stmt_check);

                if ($existing_fee) {
                    if ($amount > 0) { // Update
                        $sql = "UPDATE class_fees SET amount = ?, frequency = ? WHERE id = ?";
                        if($stmt = mysqli_prepare($link, $sql)) mysqli_stmt_bind_param($stmt, "dsi", $amount, $frequency, $existing_fee['id']);
                    } else { // Delete
                        $sql = "DELETE FROM class_fees WHERE id = ?";
                        if($stmt = mysqli_prepare($link, $sql)) mysqli_stmt_bind_param($stmt, "i", $existing_fee['id']);
                    }
                } elseif ($amount > 0) { // Insert
                    $sql = "INSERT INTO class_fees (class_id, fee_type_id, amount, frequency) VALUES (?, ?, ?, ?)";
                    if($stmt = mysqli_prepare($link, $sql)) mysqli_stmt_bind_param($stmt, "iids", $class_id_to_update, $fee_type_id, $amount, $frequency);
                }
                if(isset($stmt)) { mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt); }
            }
        }
        header("location: manage_fees.php?class_id=" . $class_id_to_update . "#classFees");
        exit;
    }

    // --- Action: Save all van fees ---
    if ($action === 'save_van_fees') {
        $fees = $_POST['fees'];
        foreach ($fees as $van_id => $amount) {
            $amount = (float)$amount;
            $sql = "UPDATE vans SET fee_amount = ? WHERE id = ?";
            if($stmt = mysqli_prepare($link, $sql)){
                mysqli_stmt_bind_param($stmt, "di", $amount, $van_id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        }
        header("location: manage_fees.php#vanFees");
        exit;
    }
}

// --- HANDLE GET REQUESTS ---
// --- Action: Delete a Fee Type ---
if (isset($_GET['delete_fee_type_id'])) {
    $delete_id = (int)$_GET['delete_fee_type_id'];
    $sql_del = "DELETE FROM fee_types WHERE id = ?";
    if ($stmt = mysqli_prepare($link, $sql_del)) {
        mysqli_stmt_bind_param($stmt, "i", $delete_id);
        mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
        // Cascade delete from class_fees
        $sql_del_class = "DELETE FROM class_fees WHERE fee_type_id = ?";
        if($stmt_c = mysqli_prepare($link, $sql_del_class)){
            mysqli_stmt_bind_param($stmt_c, "i", $delete_id);
            mysqli_stmt_execute($stmt_c); mysqli_stmt_close($stmt_c);
        }
        header("location: manage_fees.php#feeTypes"); exit;
    }
}
// --- Action: Populate form for editing a Fee Type ---
if (isset($_GET['edit_fee_type_id'])) {
    $edit_fee_type_id = (int)$_GET['edit_fee_type_id'];
    $sql_edit = "SELECT * FROM fee_types WHERE id = ?";
    if ($stmt = mysqli_prepare($link, $sql_edit)) {
        mysqli_stmt_bind_param($stmt, "i", $edit_fee_type_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            $fee_type_name_form = $row['fee_type_name'];
            $description_form = $row['description'];
        }
        mysqli_stmt_close($stmt);
    }
}

// ==================================================================
// --- DATA FETCHING for displaying everything on the page ---
// ==================================================================
$selected_class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$all_classes = []; $all_fee_types = []; $all_vans = []; $existing_class_fees = [];

$sql_classes = "SELECT * FROM classes ORDER BY class_name, section_name";
if ($res = mysqli_query($link, $sql_classes)) while ($row = mysqli_fetch_assoc($res)) $all_classes[] = $row;

$sql_fee_types = "SELECT * FROM fee_types ORDER BY fee_type_name";
if ($res = mysqli_query($link, $sql_fee_types)) while ($row = mysqli_fetch_assoc($res)) $all_fee_types[] = $row;

$sql_vans = "SELECT * FROM vans ORDER BY van_number ASC";
if ($res = mysqli_query($link, $sql_vans)) while ($row = mysqli_fetch_assoc($res)) $all_vans[] = $row;

if ($selected_class_id > 0) {
    $sql_existing_fees = "SELECT fee_type_id, amount, frequency FROM class_fees WHERE class_id = ?";
    if($stmt = mysqli_prepare($link, $sql_existing_fees)){
        mysqli_stmt_bind_param($stmt, "i", $selected_class_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while($row = mysqli_fetch_assoc($res)) $existing_class_fees[$row['fee_type_id']] = $row;
        mysqli_stmt_close($stmt);
    }
}
mysqli_close($link);

require_once './admin_header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage School Fees</title>
    <style>
        @keyframes gradientAnimation { 0%{background-position:0% 50%} 50%{background-position:100% 50%} 100%{background-position:0% 50%} }
        body { font-family: 'Segoe UI', sans-serif;  background: linear-gradient(-45deg, #ee7752, #e73c7e, #23a6d5, #23d5ab); background-size: 400% 400%; animation: gradientAnimation 15s ease infinite; }
        .container { max-width: 1200px; margin: auto; margin-top: 100px; margin-bottom: 100px; background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); padding: 30px; border-radius: 15px; box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37); }
        h2 { text-align: center; color: #1a2c5a; font-weight: 700; margin-top: 0; }
        .tab-nav { overflow: hidden; border-bottom: 2px solid #ccc; margin-bottom: 20px; }
        .tab-button { background-color: inherit; float: left; border: none; outline: none; cursor: pointer; padding: 14px 16px; transition: 0.3s; font-size: 17px; font-weight: 600; color: #555; }
        .tab-button:hover { background-color: #ddd; }
        .tab-button.active { background-color: #fff; color: #1a2c5a; border-top-left-radius: 8px; border-top-right-radius: 8px; border: 2px solid #ccc; border-bottom: 2px solid #fff; }
        .tab-content { display: none; padding: 6px 12px; }
        .container-wrapper { display: flex; gap: 30px; align-items: flex-start; }
        .form-container { flex: 1; min-width: 300px; }
        .table-container { flex: 2; }
        .form-group { margin-bottom: 18px; }
        label { display: block; margin-bottom: 6px; font-weight: 600; color: #444; }
        input[type=text], input[type=number], select, textarea { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 6px; box-sizing: border-box; }
        .btn { display: block; width: 100%; padding: 14px; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 18px; font-weight: 600; background: linear-gradient(-45deg, #007bff, #00bfff, #8a2be2, #007bff); background-size: 400% 400%; animation: gradientAnimation 8s ease infinite; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th, .data-table td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        .data-table thead th { background-color: #1a2c5a; color: white; }
        .btn-action { padding: 5px 10px; margin-right: 5px; border-radius: 5px; color: white; text-decoration: none; font-size: 14px; }
        .btn-edit { background-color: #ffc107; color: #212529; }
        .btn-delete { background-color: #dc3545; }
    </style>
</head>
<body>
<div class="container">
    <h2>Fee Management</h2>
    <div class="tab-nav">
        <button class="tab-button" onclick="openTab(event, 'classFees')" id="defaultOpen">Class Fee Structure</button>
        <button class="tab-button" onclick="openTab(event, 'feeTypes')">Manage Fee Types</button>
        <button class="tab-button" onclick="openTab(event, 'vanFees')">Manage Van Fees</button>
    </div>

    <!-- TAB 1: Class Fee Structure -->
    <div id="classFees" class="tab-content">
        <h3>Assign Fees to Classes</h3>
        <form method="GET" action="manage_fees.php">
            <div class="form-group">
                <label for="class_id">Select a Class to Manage Fees</label>
                <select name="class_id" id="class_id" onchange="this.form.submit()">
                    <option value="">-- Choose a Class --</option>
                    <?php foreach ($all_classes as $class): ?>
                        <option value="<?php echo $class['id']; ?>" <?php if ($selected_class_id == $class['id']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($class['class_name'] . ' - ' . $class['section_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
        <?php if ($selected_class_id > 0): ?>
            <hr><form method="POST" action="manage_fees.php">
                <input type="hidden" name="action" value="save_class_fees">
                <input type="hidden" name="class_id" value="<?php echo $selected_class_id; ?>">
                <table class="data-table">
                    <thead><tr><th>Fee Type</th><th>Amount (₹)</th><th>Frequency</th></tr></thead>
                    <tbody>
                        <?php foreach ($all_fee_types as $type): 
                            $current_amount = $existing_class_fees[$type['id']]['amount'] ?? '';
                            $current_freq = $existing_class_fees[$type['id']]['frequency'] ?? 'Monthly'; ?>
                        <tr>
                            <td><?php echo htmlspecialchars($type['fee_type_name']); ?></td>
                            <td><input type="number" step="0.01" name="amounts[<?php echo $type['id']; ?>]" value="<?php echo htmlspecialchars($current_amount); ?>" placeholder="0.00"></td>
                            <td><select name="frequencies[<?php echo $type['id']; ?>]">
                                <option value="Monthly" <?php if ($current_freq == 'Monthly') echo 'selected'; ?>>Monthly</option>
                                <option value="Quarterly" <?php if ($current_freq == 'Quarterly') echo 'selected'; ?>>Quarterly</option>
                                <option value="Half-Yearly" <?php if ($current_freq == 'Half-Yearly') echo 'selected'; ?>>Half-Yearly</option>
                                <option value="Annually" <?php if ($current_freq == 'Annually') echo 'selected'; ?>>Annually</option>
                                <option value="One-Time" <?php if ($current_freq == 'One-Time') echo 'selected'; ?>>One-Time</option>
                            </select></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div style="margin-top: 20px;"><input type="submit" class="btn" value="Save Fee Structure"></div>
            </form>
        <?php endif; ?>
    </div>

    <!-- TAB 2: Manage Fee Types -->
    <div id="feeTypes" class="tab-content">
        <div class="container-wrapper">
            <div class="form-container">
                <h2><?php echo ($edit_fee_type_id > 0) ? 'Edit Fee Type' : 'Add New Fee Type'; ?></h2>
                <form action="manage_fees.php" method="post">
                    <input type="hidden" name="action" value="manage_fee_type">
                    <input type="hidden" name="id" value="<?php echo $edit_fee_type_id; ?>">
                    <div class="form-group">
                        <label for="fee_type_name">Fee Type Name</label>
                        <input type="text" name="fee_type_name" value="<?php echo htmlspecialchars($fee_type_name_form); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea name="description" rows="3"><?php echo htmlspecialchars($description_form); ?></textarea>
                    </div>
                    <input type="submit" class="btn" value="<?php echo ($edit_fee_type_id > 0) ? 'Update' : 'Add'; ?>">
                </form>
            </div>
            <div class="table-container">
                <h3>Existing Fee Types</h3>
                <table class="data-table">
                    <thead><tr><th>Name</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach ($all_fee_types as $type): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($type['fee_type_name']); ?></td>
                            <td>
                                <a href="manage_fees.php?edit_fee_type_id=<?php echo $type['id']; ?>#feeTypes" class="btn-action btn-edit">Edit</a>
                                <a href="manage_fees.php?delete_fee_type_id=<?php echo $type['id']; ?>" class="btn-action btn-delete" onclick="return confirm('Are you sure?');">Delete</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB 3: Manage Van Fees -->
    <div id="vanFees" class="tab-content">
        <h3>Set Transportation Fees</h3>
        <form method="POST" action="manage_fees.php">
            <input type="hidden" name="action" value="save_van_fees">
            <table class="data-table">
                <thead><tr><th>Van Number</th><th>Fee Amount (₹)</th></tr></thead>
                <tbody>
                    <?php foreach($all_vans as $van): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($van['van_number']); ?></td>
                        <td><input type="number" step="0.01" name="fees[<?php echo $van['id']; ?>]" value="<?php echo htmlspecialchars($van['fee_amount']); ?>" placeholder="0.00"></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div style="margin-top: 20px;"><input type="submit" class="btn" value="Save All Van Fees"></div>
        </form>
    </div>
</div>

<script>
    function openTab(evt, tabName) {
        var i, tabcontent, tablinks;
        tabcontent = document.getElementsByClassName("tab-content");
        for (i = 0; i < tabcontent.length; i++) {
            tabcontent[i].style.display = "none";
        }
        tablinks = document.getElementsByClassName("tab-button");
        for (i = 0; i < tablinks.length; i++) {
            tablinks[i].className = tablinks[i].className.replace(" active", "");
        }
        document.getElementById(tabName).style.display = "block";
        evt.currentTarget.className += " active";
        // Update URL hash
        window.location.hash = tabName;
    }

    // Get the element with id="defaultOpen" and click on it
    document.addEventListener('DOMContentLoaded', (event) => {
        const hash = window.location.hash.substring(1);
        let tabToOpen = 'classFees'; // Default tab
        if (hash === 'feeTypes' || hash === 'vanFees') {
            tabToOpen = hash;
        }
        document.querySelector(`.tab-button[onclick*="'${tabToOpen}'"]`).click();
    });
</script>

</body>
</html>
<?php require_once './admin_footer.php'; ?>