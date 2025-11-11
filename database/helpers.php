<?php
// database/helpers.php
// Assumes config.php is included where these functions are used.

/**
 * Fetches all active teachers for dropdowns.
 *
 * @param mysqli $link Database connection link.
 * @return array An array of teacher objects/associative arrays.
 */
function get_all_teachers($link) {
    $teachers = [];
    $sql = "SELECT id, full_name FROM teachers ORDER BY full_name ASC";
    if ($result = mysqli_query($link, $sql)) {
        while ($row = mysqli_fetch_assoc($result)) {
            $teachers[] = $row;
        }
        mysqli_free_result($result);
    }
    return $teachers;
}

/**
 * Fetches all active students for dropdowns.
 *
 * @param mysqli $link Database connection link.
 * @return array An array of student objects/associative arrays.
 */
function get_all_students($link) {
    $students = [];
    // Assuming 'Active' status for students. Adjust if your student status column name differs.
    $sql = "SELECT id, registration_number, first_name, last_name FROM students WHERE status = 'Active' ORDER BY first_name ASC, last_name ASC";
    if ($result = mysqli_query($link, $sql)) {
        while ($row = mysqli_fetch_assoc($result)) {
            $students[] = $row;
        }
        mysqli_free_result($result);
    }
    return $students;
}

/**
 * Fetches all classes for dropdowns.
 *
 * @param mysqli $link Database connection link.
 * @return array An array of class objects/associative arrays.
 */
function get_all_classes($link) {
    $classes = [];
    $sql = "SELECT id, class_name, section_name FROM classes ORDER BY class_name ASC, section_name ASC";
    if ($result = mysqli_query($link, $sql)) {
        while ($row = mysqli_fetch_assoc($result)) {
            $classes[] = $row;
        }
        mysqli_free_result($result);
    }
    return $classes;
}

/**
 * Fetches all subjects for dropdowns.
 *
 * @param mysqli $link Database connection link.
 * @return array An array of subject objects/associative arrays.
 */
function get_all_subjects($link) {
    $subjects = [];
    $sql = "SELECT id, subject_name FROM subjects ORDER BY subject_name ASC";
    if ($result = mysqli_query($link, $sql)) {
        while ($row = mysqli_fetch_assoc($result)) {
            $subjects[] = $row;
        }
        mysqli_free_result($result);
    }
    return $subjects;
}
?>