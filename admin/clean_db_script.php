<?php
// clean_db_script.php
// RUN THIS SCRIPT ONLY ONCE, AFTER BACKING UP YOUR DATABASE!

require_once __DIR__ . '/../database/config.php'; // Adjust path to your config.php

if (!$link) {
    die("ERROR: Could not connect. " . mysqli_connect_error());
}

echo "Starting database cleanup...\n";

// Function to safely decode HTML entities in JSON content
function cleanJsonArrayContent($jsonString) {
    $decoded = json_decode($jsonString, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        foreach ($decoded as &$item) {
            // Apply htmlspecialchars_decode to all relevant string fields
            if (isset($item['name'])) {
                $item['name'] = htmlspecialchars_decode($item['name'], ENT_QUOTES);
            }
            if (isset($item['details'])) {
                $item['details'] = htmlspecialchars_decode($item['details'], ENT_QUOTES);
            }
            if (isset($item['title'])) {
                $item['title'] = htmlspecialchars_decode($item['title'], ENT_QUOTES);
            }
            if (isset($item['description'])) {
                $item['description'] = htmlspecialchars_decode($item['description'], ENT_QUOTES);
            }
            if (isset($item['icon'])) {
                // Ensure icon is just a class string, strip any HTML tags that might have been accidentally stored
                $icon_decoded = htmlspecialchars_decode($item['icon'], ENT_QUOTES);
                $item['icon'] = preg_replace('/[^a-zA-Z0-9\s-]/', '', $icon_decoded);
            }
        }
        return json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
    return '[]'; // Return empty array on JSON error
}

// 1. Clean direct text fields (titles, subtitles, address, phone, email)
// These fields might also contain &amp; instead of & if they were double-encoded.
// We'll decode them so the DB holds raw data, and the script handles the encoding for HTML output.
$sql_fetch_direct = "SELECT achievements_main_title, achievements_subtitle, toppers_section_title, 
                            awards_section_title, awards_subtitle, contact_section_title, 
                            contact_subtitle, contact_address, contact_phone, contact_email 
                     FROM achievements_contact_settings WHERE id = 1";
$result_direct = mysqli_query($link, $sql_fetch_direct);

if ($result_direct && $row_direct = mysqli_fetch_assoc($result_direct)) {
    $cleaned_direct_fields = [];
    foreach ($row_direct as $key => $value) {
        $cleaned_direct_fields[$key] = htmlspecialchars_decode($value, ENT_QUOTES);
    }

    $sql_update_direct = "UPDATE achievements_contact_settings SET
                            achievements_main_title = ?, achievements_subtitle = ?,
                            toppers_section_title = ?, awards_section_title = ?,
                            awards_subtitle = ?, contact_section_title = ?,
                            contact_subtitle = ?, contact_address = ?,
                            contact_phone = ?, contact_email = ?
                           WHERE id = 1";

    if ($stmt_direct = mysqli_prepare($link, $sql_update_direct)) {
        mysqli_stmt_bind_param($stmt_direct, "ssssssssss", 
            $cleaned_direct_fields['achievements_main_title'],
            $cleaned_direct_fields['achievements_subtitle'],
            $cleaned_direct_fields['toppers_section_title'],
            $cleaned_direct_fields['awards_section_title'],
            $cleaned_direct_fields['awards_subtitle'],
            $cleaned_direct_fields['contact_section_title'],
            $cleaned_direct_fields['contact_subtitle'],
            $cleaned_direct_fields['contact_address'],
            $cleaned_direct_fields['contact_phone'],
            $cleaned_direct_fields['contact_email']
        );
        if (mysqli_stmt_execute($stmt_direct)) {
            echo "Direct text fields cleaned successfully.\n";
        } else {
            echo "Error cleaning direct text fields: " . mysqli_stmt_error($stmt_direct) . "\n";
        }
        mysqli_stmt_close($stmt_direct);
    } else {
        echo "Error preparing statement for direct fields: " . mysqli_error($link) . "\n";
    }
} else {
    echo "Error fetching direct fields: " . mysqli_error($link) . "\n";
}


// 2. Clean JSON fields (toppers_json, awards_json)
$sql_fetch_json = "SELECT toppers_json, awards_json FROM achievements_contact_settings WHERE id = 1";
$result_json = mysqli_query($link, $sql_fetch_json);

if ($result_json && $row_json = mysqli_fetch_assoc($result_json)) {
    $toppers_json_db = $row_json['toppers_json'];
    $awards_json_db = $row_json['awards_json'];

    $cleaned_toppers_json = cleanJsonArrayContent($toppers_json_db);
    $cleaned_awards_json = cleanJsonArrayContent($awards_json_db);

    $sql_update_json = "UPDATE achievements_contact_settings SET toppers_json = ?, awards_json = ? WHERE id = 1";
    if ($stmt_json = mysqli_prepare($link, $sql_update_json)) {
        mysqli_stmt_bind_param($stmt_json, "ss", $cleaned_toppers_json, $cleaned_awards_json);
        if (mysqli_stmt_execute($stmt_json)) {
            echo "JSON fields cleaned successfully.\n";
        } else {
            echo "Error cleaning JSON fields: " . mysqli_stmt_error($stmt_json) . "\n";
        }
        mysqli_stmt_close($stmt_json);
    } else {
        echo "Error preparing statement for JSON fields: " . mysqli_error($link) . "\n";
    }
} else {
    echo "Error fetching JSON fields: " . mysqli_error($link) . "\n";
}

mysqli_close($link);
echo "Database cleanup complete.\n";
?>