<?php
// Start the session
session_start();

// Include configuration and Cloudinary handler
require_once "../database/config.php";
require_once "../database/cloudinary_upload_handler.php";

// --- Authentication Check ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== 'Admin') {
    header("location: ../login.php");
    exit;
}

$admin_id = $_SESSION["id"] ?? 0;
$errors = [];
$success_message = "";

// --- Shared Helper: Sanitize POST data ---
function get_post_data($fields) {
    $data = [];
    foreach ($fields as $field) {
        $data[$field] = trim($_POST[$field] ?? '');
    }
    return $data;
}

// --- Shared Helper: Handle Image Upload ---
function handle_image_upload($file_input_name, $current_image_url = '') {
    global $errors;
    if (isset($_FILES[$file_input_name]) && $_FILES[$file_input_name]['error'] == UPLOAD_ERR_OK) {
        $uploadResult = uploadToCloudinary($_FILES[$file_input_name], 'school_events_news');
        if (isset($uploadResult['error'])) {
            $errors[] = "Image upload failed: " . $uploadResult['error'];
            return $current_image_url;
        }
        return $uploadResult['secure_url'];
    }
    return $current_image_url;
}

// --- FORM SUBMISSION PROCESSING ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // --- ADD NEWS ARTICLE ---
    if (isset($_POST['add_news'])) {
        $data = get_post_data(['title', 'date', 'category', 'excerpt', 'content']);
        $data['image_url'] = handle_image_upload('image_url');
        
        if (empty($data['title']) || empty($data['date'])) {
            $errors[] = "News title and date are required.";
        } else {
            $sql = "INSERT INTO news_articles (title, date, category, image_url, excerpt, content, admin_id) VALUES (?, ?, ?, ?, ?, ?, ?)";
            if ($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param($stmt, "ssssssi", $data['title'], $data['date'], $data['category'], $data['image_url'], $data['excerpt'], $data['content'], $admin_id);
                if (mysqli_stmt_execute($stmt)) {
                    $_SESSION['success_message'] = "News article added successfully.";
                    header("Location: " . $_SERVER['PHP_SELF']); exit();
                } else $errors[] = "Failed to add news article.";
                mysqli_stmt_close($stmt);
            }
        }
    }

    // --- EDIT NEWS ARTICLE ---
    if (isset($_POST['edit_news'])) {
        $data = get_post_data(['title', 'date', 'category', 'excerpt', 'content']);
        $id = (int)$_POST['news_id'];
        $data['image_url'] = handle_image_upload('image_url', $_POST['current_image_url']);

        $sql = "UPDATE news_articles SET title=?, date=?, category=?, image_url=?, excerpt=?, content=? WHERE id=?";
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "ssssssi", $data['title'], $data['date'], $data['category'], $data['image_url'], $data['excerpt'], $data['content'], $id);
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['success_message'] = "News article updated successfully.";
                header("Location: " . $_SERVER['PHP_SELF']); exit();
            } else $errors[] = "Failed to update news article.";
            mysqli_stmt_close($stmt);
        }
    }
    
    // --- DELETE NEWS ARTICLE ---
    if (isset($_POST['delete_news'])) {
        $id = (int)$_POST['news_id'];
        $sql = "DELETE FROM news_articles WHERE id = ?";
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "i", $id);
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['success_message'] = "News article deleted successfully.";
                header("Location: " . $_SERVER['PHP_SELF']); exit();
            } else $errors[] = "Failed to delete news article.";
        }
    }

    // --- ADD EVENT ---
    if (isset($_POST['add_event'])) {
        $data = get_post_data(['title', 'event_date', 'time', 'location', 'type', 'description']);
        $data['image_url'] = handle_image_upload('image_url');
        if (empty($data['title']) || empty($data['event_date'])) {
            $errors[] = "Event title and date are required.";
        } else {
            $sql = "INSERT INTO upcoming_events (title, event_date, time, location, type, image_url, description, admin_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            if ($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param($stmt, "sssssssi", $data['title'], $data['event_date'], $data['time'], $data['location'], $data['type'], $data['image_url'], $data['description'], $admin_id);
                if (mysqli_stmt_execute($stmt)) {
                    $_SESSION['success_message'] = "Event added successfully.";
                    header("Location: " . $_SERVER['PHP_SELF']); exit();
                } else $errors[] = "Failed to add event.";
            }
        }
    }
    
    // --- EDIT EVENT ---
    if (isset($_POST['edit_event'])) {
        $data = get_post_data(['title', 'event_date', 'time', 'location', 'type', 'description']);
        $id = (int)$_POST['event_id'];
        $data['image_url'] = handle_image_upload('image_url', $_POST['current_image_url']);
        
        $sql = "UPDATE upcoming_events SET title=?, event_date=?, time=?, location=?, type=?, image_url=?, description=? WHERE id=?";
        if($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "sssssssi", $data['title'], $data['event_date'], $data['time'], $data['location'], $data['type'], $data['image_url'], $data['description'], $id);
            if(mysqli_stmt_execute($stmt)) {
                $_SESSION['success_message'] = "Event updated successfully.";
                header("Location: " . $_SERVER['PHP_SELF']); exit();
            } else $errors[] = "Failed to update event.";
        }
    }

    // --- DELETE EVENT ---
    if (isset($_POST['delete_event'])) {
        $id = (int)$_POST['event_id'];
        $sql = "DELETE FROM upcoming_events WHERE id = ?";
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "i", $id);
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['success_message'] = "Event deleted successfully.";
                header("Location: " . $_SERVER['PHP_SELF']); exit();
            } else $errors[] = "Failed to delete event.";
        }
    }
}

if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

$news_items = mysqli_query($link, "SELECT * FROM news_articles ORDER BY date DESC");
$event_items = mysqli_query($link, "SELECT * FROM upcoming_events ORDER BY created_at DESC");

require_once './admin_header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Events & News</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #3498db; --secondary-color: #2ecc71; --danger-color: #e74c3c;
            --bg-color: #f4f7f9; --card-bg: #ffffff; --text-dark: #2c3e50;
            --border-color: #e1e8ed; --shadow: 0 6px 15px rgba(0,0,0,0.07);
        }
        body { font-family: 'Poppins', sans-serif; background-color: var(--bg-color); }
        .container { max-width: 1200px; margin: 2rem auto; padding: 2rem; }
        .section-card { background: var(--card-bg); border-radius: 12px; box-shadow: var(--shadow); padding: 2rem; margin-bottom: 2rem; }
        h2 { font-family: 'Playfair Display', serif; color: var(--text-dark); text-align: center; font-size: 2.25rem; margin-bottom: 2rem; }
        h3 { font-family: 'Playfair Display', serif; font-size: 1.5rem; margin-bottom: 1.5rem; border-bottom: 2px solid var(--primary-color); padding-bottom: 0.5rem; }
        .form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem; }
        .form-group { margin-bottom: 1rem; }
        .full-width { grid-column: 1 / -1; }
        label { font-weight: 600; display: block; margin-bottom: 0.5rem; }
        input, textarea, select { width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; box-sizing: border-box; }
        .btn { display: inline-block; padding: 0.75rem 1.5rem; color: #fff; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; text-align: center; }
        .btn-primary { background-color: var(--primary-color); }
        .btn-secondary { background-color: var(--secondary-color); }
        .btn-danger { background-color: var(--danger-color); }
        .alert { padding: 1rem; margin-bottom: 1.5rem; border-radius: 8px; text-align: center; font-weight: 500; }
        .alert-danger { background-color: #f8d7da; color: #721c24; }
        .alert-success { background-color: #d4edda; color: #155724; }
        table { width: 100%; border-collapse: collapse; margin-top: 2rem; }
        th, td { padding: 0.75rem; border-bottom: 1px solid var(--border-color); text-align: left; }
        th { background-color: #f8f9fa; }
        td .actions { display: flex; gap: 0.5rem; }
        /* .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.6); }
        .modal-content { background-color: var(--card-bg); margin: 10% auto; padding: 2rem; border-radius: 12px; width: 80%; max-width: 600px; position: relative; } */
        .close-modal { position: absolute; top: 10px; right: 20px; font-size: 2rem; cursor: pointer; }
.modal {
  display: none;
  position: fixed;
  z-index: 1000;
  left: 0;
  top: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0,0,0,0.6);

  /* allow scrolling when modal content is taller */
  overflow-y: auto;
  padding: 2rem 1rem; /* spacing around modal */
}

.modal-content {
  background-color: var(--card-bg);
  margin: auto;
  padding: 2rem;
  border-radius: 12px;
  width: 90%;
  max-width: 700px;
  position: relative;

  /* allow internal scroll if needed */
  max-height: 90vh;
  overflow-y: auto;
}

        /* NEW: Styles for Collapsible Forms */
        .collapsible-header {
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 2px solid var(--primary-color);
            margin-bottom: 1.5rem;
        }
        .collapsible-header h3 {
            border: none;
            margin: 0;
            padding: 0;
        }
        .toggle-icon {
            transition: transform 0.3s ease;
            font-size: 1.5rem;
            color: var(--primary-color);
        }
        .collapsible-header.active .toggle-icon {
            transform: rotate(180deg);
        }
        .collapsible-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.5s ease-out;
        }
    </style>
</head>
<body>

<div class="container">
    <h2>Manage Events & News</h2>
    <?php if(!empty($errors)): ?><div class="alert alert-danger"><?php foreach($errors as $error) echo htmlspecialchars($error)."<br>"; ?></div><?php endif; ?>
    <?php if($success_message): ?><div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div><?php endif; ?>

    <!-- Manage News Section -->
    <div class="section-card">
        <div class="collapsible-header">
            <h3>Add New News Article</h3>
            <span class="toggle-icon">▼</span>
        </div>
        <div class="collapsible-content">
            <form action="" method="post" enctype="multipart/form-data">
                <div class="form-grid">
                    <div class="form-group"><label>Title*</label><input type="text" name="title" required></div>
                    <div class="form-group"><label>Date*</label><input type="date" name="date" required></div>
                    <div class="form-group"><label>Category</label><input type="text" name="category" placeholder="e.g., Academics"></div>
                    <div class="form-group"><label>Image</label><input type="file" name="image_url" accept="image/*"></div>
                </div>
                <div class="form-group full-width"><label>Excerpt (Short Summary)</label><textarea name="excerpt" rows="3"></textarea></div>
                <div class="form-group full-width"><label>Full Content (Optional)</label><textarea name="content" rows="6"></textarea></div>
                <button type="submit" name="add_news" class="btn btn-primary">Add News</button>
            </form>
        </div>
        
        <h3 style="margin-top: 3rem; text-align:left;">Existing News Articles</h3>
        <table>
            <thead><tr><th>Title</th><th>Date</th><th>Category</th><th>Actions</th></tr></thead>
            <tbody>
                <?php mysqli_data_seek($news_items, 0); // Reset pointer for second loop ?>
                <?php while($row = mysqli_fetch_assoc($news_items)): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['title']); ?></td>
                    <td><?php echo date('d M Y', strtotime($row['date'])); ?></td>
                    <td><?php echo htmlspecialchars($row['category']); ?></td>
                    <td class="actions">
                        <button class="btn btn-secondary" style="padding: 0.5rem 1rem;" onclick='openModal("edit-news-modal", <?php echo json_encode($row); ?>)'>Edit</button>
                        <form method="post" onsubmit="return confirm('Delete this news article?');"><input type="hidden" name="news_id" value="<?php echo $row['id']; ?>"><button type="submit" name="delete_news" class="btn btn-danger" style="padding: 0.5rem 1rem;">Delete</button></form>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <!-- Manage Events Section -->
    <div class="section-card">
        <div class="collapsible-header">
            <h3>Add New Upcoming Event</h3>
            <span class="toggle-icon">▼</span>
        </div>
        <div class="collapsible-content">
            <form action="" method="post" enctype="multipart/form-data">
                <div class="form-grid">
                    <div class="form-group"><label>Title*</label><input type="text" name="title" required></div>
                    <div class="form-group"><label>Date(s)*</label><input type="text" name="event_date" placeholder="e.g., November 10, 2023" required></div>
                    <div class="form-group"><label>Time</label><input type="text" name="time" placeholder="e.g., 10:00 AM - 4:00 PM"></div>
                    <div class="form-group"><label>Location</label><input type="text" name="location" placeholder="e.g., School Auditorium"></div>
                    <div class="form-group"><label>Type</label><input type="text" name="type" placeholder="e.g., Competition"></div>
                    <div class="form-group"><label>Image</label><input type="file" name="image_url" accept="image/*"></div>
                </div>
                <div class="form-group full-width"><label>Description</label><textarea name="description" rows="4"></textarea></div>
                <button type="submit" name="add_event" class="btn btn-primary">Add Event</button>
            </form>
        </div>

        <h3 style="margin-top: 3rem; text-align:left;">Existing Events</h3>
        <table>
            <thead><tr><th>Title</th><th>Date</th><th>Type</th><th>Actions</th></tr></thead>
            <tbody>
                <?php mysqli_data_seek($event_items, 0); // Reset pointer for second loop ?>
                <?php while($row = mysqli_fetch_assoc($event_items)): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['title']); ?></td>
                    <td><?php echo htmlspecialchars($row['event_date']); ?></td>
                    <td><?php echo htmlspecialchars($row['type']); ?></td>
                    <td class="actions">
                        <button class="btn btn-secondary" style="padding: 0.5rem 1rem;" onclick='openModal("edit-event-modal", <?php echo json_encode($row); ?>)'>Edit</button>
                        <form method="post" onsubmit="return confirm('Delete this event?');"><input type="hidden" name="event_id" value="<?php echo $row['id']; ?>"><button type="submit" name="delete_event" class="btn btn-danger" style="padding: 0.5rem 1rem;">Delete</button></form>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Edit Modals (No change needed here) -->
<div id="edit-news-modal" class="modal">
    <div class="modal-content">
        <span class="close-modal" onclick='closeModal("edit-news-modal")'>&times;</span>
        <h3>Edit News Article</h3>
        <form action="" method="post" enctype="multipart/form-data">
            <input type="hidden" name="news_id" id="edit_news_id">
            <input type="hidden" name="current_image_url" id="edit_news_current_image">
            <div class="form-grid">
                <div class="form-group"><label>Title*</label><input type="text" name="title" id="edit_news_title" required></div>
                <div class="form-group"><label>Date*</label><input type="date" name="date" id="edit_news_date" required></div>
                <div class="form-group"><label>Category</label><input type="text" name="category" id="edit_news_category"></div>
                <div class="form-group"><label>New Image (Optional)</label><input type="file" name="image_url" accept="image/*"></div>
            </div>
            <div class="form-group full-width"><label>Excerpt</label><textarea name="excerpt" id="edit_news_excerpt" rows="3"></textarea></div>
            <div class="form-group full-width"><label>Full Content</label><textarea name="content" id="edit_news_content" rows="6"></textarea></div>
            <button type="submit" name="edit_news" class="btn btn-primary">Save Changes</button>
        </form>
    </div>
</div>

<div id="edit-event-modal" class="modal">
    <div class="modal-content">
        <span class="close-modal" onclick='closeModal("edit-event-modal")'>&times;</span>
        <h3>Edit Event</h3>
        <form action="" method="post" enctype="multipart/form-data">
            <input type="hidden" name="event_id" id="edit_event_id">
            <input type="hidden" name="current_image_url" id="edit_event_current_image">
            <div class="form-grid">
                <div class="form-group"><label>Title*</label><input type="text" name="title" id="edit_event_title" required></div>
                <div class="form-group"><label>Date(s)*</label><input type="text" name="event_date" id="edit_event_date" required></div>
                <div class="form-group"><label>Time</label><input type="text" name="time" id="edit_event_time"></div>
                <div class="form-group"><label>Location</label><input type="text" name="location" id="edit_event_location"></div>
                <div class="form-group"><label>Type</label><input type="text" name="type" id="edit_event_type"></div>
                <div class="form-group"><label>New Image (Optional)</label><input type="file" name="image_url" accept="image/*"></div>
            </div>
            <div class="form-group full-width"><label>Description</label><textarea name="description" id="edit_event_description" rows="4"></textarea></div>
            <button type="submit" name="edit_event" class="btn btn-primary">Save Changes</button>
        </form>
    </div>
</div>

<script>
    // --- NEW SCRIPT FOR COLLAPSIBLE FORMS ---
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.collapsible-header').forEach(header => {
            header.addEventListener('click', () => {
                header.classList.toggle('active');
                const content = header.nextElementSibling;
                if (content.style.maxHeight) {
                    content.style.maxHeight = null;
                } else {
                    content.style.maxHeight = content.scrollHeight + "px";
                }
            });
        });
    });

    // --- EXISTING SCRIPT FOR MODALS (No change needed) ---
    function openModal(modalId, data) {
        const modal = document.getElementById(modalId);
        if (modalId === 'edit-news-modal') {
            modal.querySelector('#edit_news_id').value = data.id;
            modal.querySelector('#edit_news_title').value = data.title;
            modal.querySelector('#edit_news_date').value = data.date;
            modal.querySelector('#edit_news_category').value = data.category;
            modal.querySelector('#edit_news_excerpt').value = data.excerpt;
            modal.querySelector('#edit_news_content').value = data.content;
            modal.querySelector('#edit_news_current_image').value = data.image_url;
        } else if (modalId === 'edit-event-modal') {
            modal.querySelector('#edit_event_id').value = data.id;
            modal.querySelector('#edit_event_title').value = data.title;
            modal.querySelector('#edit_event_date').value = data.event_date;
            modal.querySelector('#edit_event_time').value = data.time;
            modal.querySelector('#edit_event_location').value = data.location;
            modal.querySelector('#edit_event_type').value = data.type;
            modal.querySelector('#edit_event_description').value = data.description;
            modal.querySelector('#edit_event_current_image').value = data.image_url;
        }
        modal.style.display = 'block';
    }

    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }

    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = 'none';
        }
    }
</script>

</body>
</html>