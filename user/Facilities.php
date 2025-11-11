<?php
// This is a dynamic PHP file for the Admissions section.
// It fetches content from the admissions_settings table.

// Include your database configuration
require_once __DIR__ . '/../database/config.php'; // Adjust path if necessary

// Initialize an array to hold settings, with default values or empty strings
$settings = [
    'admissions_section_title' => 'Admissions',
    'admissions_section_description' => 'Join our vibrant learning community. We welcome students who are eager to learn, grow, and contribute to our school\'s legacy of excellence.',
    'admission_process_title' => 'Admission Process',
    'admission_process_json' => '[]', // Default to empty JSON array
    'important_dates_title' => 'Important Dates - Academic Year 2024-25',
    'important_dates_json' => '[]' // Default to empty JSON array
];

// Fetch settings from the database
$sql = "SELECT * FROM admissions_settings WHERE id = 1";
if ($result = mysqli_query($link, $sql)) {
    if (mysqli_num_rows($result) == 1) {
        $db_settings = mysqli_fetch_assoc($result);
        // Overwrite default settings with database values if they exist and are not empty
        foreach ($settings as $key => $value) {
            if (isset($db_settings[$key]) && $db_settings[$key] !== NULL && $db_settings[$key] !== '') {
                $settings[$key] = $db_settings[$key];
            }
        }
    }
    mysqli_free_result($result);
} else {
    error_log("Error fetching admissions_settings: " . mysqli_error($link));
}

// Decode admission process JSON for display
$admission_process_display = [];
$decoded_process = json_decode($settings['admission_process_json'], true);
if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_process)) {
    $admission_process_display = $decoded_process;
}

// Decode important dates JSON for display
$important_dates_display = [];
$decoded_dates = json_decode($settings['important_dates_json'], true);
if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_dates)) {
    $important_dates_display = $decoded_dates;
}

// Close database connection if this is a standalone script
// If it's included in a larger application, the main script should close it.
// mysqli_close($link);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admissions Page</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f3f4f6;
            color: #1a202c;
        }

        .bg-gradient-subtle {
            background-color: #f3f4f6 !important; /* solid background */
        }
        .text-gradient-primary {
            background-image: linear-gradient(to right, #4c51bf, #667eea);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .hero-button { /* Keeping this from your original code, though not used in this section */
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 600;
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
            background-color: #4c51bf;
            color: white;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        .hero-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        .section-padding {
            padding: 4rem 1rem;
        }
        .container-padding {
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
            padding: 0 1.5rem;
        }
        .card-hover {
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        }
        .card-hover:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        /* Lucide icon styling for dynamic SVGs */
        .lucide-icon-container svg {
            width: 24px;
            height: 24px;
            color: white;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }
    </style>
</head>
<body>

    <div id="toast-container" class="fixed top-4 right-4 z-50 transition-all duration-300 transform translate-x-full opacity-0">
        <div class="bg-green-500 text-white px-6 py-4 rounded-lg shadow-lg flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="lucide-icon h-6 w-6 mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-8.15"></path>
                <path d="m9 11 3 3L22 4"></path>
            </svg>
            <div>
                <div class="font-semibold">Application Submitted!</div>
                <div class="text-sm">We'll contact you within 24 hours to schedule an interaction.</div>
            </div>
        </div>
    </div>

    <section id="admissions" class="section-padding bg-gradient-subtle">
        <div class="container-padding">
            <div class="text-center mb-16">
                <h2 class="text-4xl lg:text-5xl font-bold mb-6 text-gradient-primary">
                    <?php echo htmlspecialchars($settings['admissions_section_title']); ?>
                </h2>
                <p class="text-xl text-gray-500 max-w-3xl mx-auto">
                    <?php echo nl2br(htmlspecialchars($settings['admissions_section_description'])); ?>
                </p>
            </div>
    
            <div>
                <!-- Admission Process -->
                <div class="mb-16">
                    <h3 class="text-3xl font-bold mb-8 text-indigo-700">
                        <?php echo htmlspecialchars($settings['admission_process_title']); ?>
                    </h3>
                    <div class="space-y-6">
                        <?php foreach ($admission_process_display as $step): ?>
                            <div class="bg-white rounded-lg shadow-md p-6 card-hover">
                                <div class="flex items-start gap-4">
                                    <div class="w-12 h-12 bg-indigo-600 rounded-lg flex items-center justify-center flex-shrink-0 lucide-icon-container">
                                        <?php 
                                            // Echo SVG directly. No htmlspecialchars() here as it's raw SVG.
                                            // Make sure input sanitization prevents malicious SVG injection in admin.
                                            echo $step['icon'] ?? ''; 
                                        ?>
                                    </div>
                                    <div>
                                        <h4 class="text-xl font-semibold mb-2 text-indigo-700">
                                            <?php echo htmlspecialchars($step['title'] ?? ''); ?>
                                        </h4>
                                        <p class="text-gray-500">
                                            <?php echo nl2br(htmlspecialchars($step['description'] ?? '')); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                        <?php endforeach; ?>
                    </div>
                </div>
     
                <!-- Important Dates -->
                <div class="bg-white rounded-lg shadow-md p-6 card-hover">
                    <h3 class="text-2xl font-bold mb-4 text-indigo-700 text-center">
                        <?php echo htmlspecialchars($settings['important_dates_title']); ?>
                    </h3>
                    <div class="grid md:grid-cols-3 gap-8 text-center">
                        <?php foreach ($important_dates_display as $date_item): ?>
                            <div>
                                <div class="text-3xl font-bold text-gray-700 mb-2">
                                    <?php echo htmlspecialchars($date_item['date_text'] ?? ''); ?>
                                </div>
                                <p class="text-gray-500">
                                    <?php echo htmlspecialchars($date_item['description'] ?? ''); ?>
                                </p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Note: The form and prospectus download are placeholders
            // and require actual form elements/server-side handling to be functional.
            
            const form = document.getElementById('enquiry-form'); // Your existing form, if any
            const toastContainer = document.getElementById('toast-container');
            
            if (form) {
                form.addEventListener('submit', (e) => {
                    e.preventDefault();
                    
                    // Show toast message
                    toastContainer.classList.remove('translate-x-full', 'opacity-0');
                    toastContainer.classList.add('translate-x-0', 'opacity-100');
                    
                    setTimeout(() => {
                        toastContainer.classList.remove('translate-x-0', 'opacity-100');
                        toastContainer.classList.add('translate-x-full', 'opacity-0');
                    }, 4000);
                    
                    // Clear the form fields
                    form.reset();
                });
            }

            window.downloadProspectus = () => {
                // Placeholder for a real download
                const fakePdfContent = "This is a placeholder for the prospectus PDF. In a real application, you would link to a PDF file here.";
                const blob = new Blob([fakePdfContent], { type: 'application/pdf' });
                const link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = 'Prospectus.pdf';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(link.href); // Clean up
            }
        });

        // Initialize Lucide icons after content is loaded
        // This is necessary because dynamic SVGs added via PHP won't be processed by default
        lucide.createIcons();
    </script>

</body>
</html>