<?php
/**
 * =================================================================
 * STUDENT FOOTER CONFIGURATION
 * =================================================================
 */
$footerConfig = [
    'school_name' => 'Basic Public School',
    'contact' => [
        'phone' => '+91 12345 67890',
        'email' => 'support@yourschool.com',
    ],
    'link_columns' => [
        [
            'title' => 'Academics',
            'links' => [
                'Dashboard' => 'student_dashboard.php',
                'My Courses / Subjects' => 'student_courses.php',
                'Assignments / Homework' => 'student_assignments.php',
                'Exams / Results' => 'student_results.php',
                'Attendance' => 'student_attendance.php',
                'Timetable / Schedule' => 'student_timetable.php',
            ],
        ],
        [
            'title' => 'Student Services',
            'links' => [
                'Fee Payment / Dues' => 'student_fees.php',
                'Library' => 'student_library.php',
                'Hostel / Accommodation' => 'student_hostel.php',
                'Transport / Bus Pass' => 'student_transport.php',
                'Canteen / Meal Plan' => 'student_canteen.php',
                'Applications' => 'student_applications.php',
            ],
        ],
        [
            'title' => 'Engagement',
            'links' => [
                'Events / Activities' => 'student_events.php',
                'Sports / Clubs' => 'student_sports.php',
                'Cultural Programs' => 'student_cultural.php',
                'Competitions / Hackathons' => 'student_competitions.php',
                'Forum / Discussion Board' => 'student_forum.php',
            ],
        ],
        [
            'title' => 'Personal & Career',
            'links' => [
                'My Profile' => 'student_profile.php',
                'Progress Report' => 'student_progress.php',
                'Career Guidance' => 'student_career.php',
                'Placements / Internships' => 'student_placements.php',
                'My Documents' => 'student_documents.php',
            ],
        ],
    ],
    'social_links' => [
        'linkedin' => 'https://www.linkedin.com/school/your-school',
        'instagram' => 'https://www.instagram.com/your-school',
        'facebook' => 'https://www.facebook.com/your-school',
        'twitter' => '', // hidden
    ],
    'developer' => [
        'name' => 'Sanjeev Kumar',
        'website' => 'https://github.com/',
    ],
];

// SVG icons
$socialIconSVGs = [
    'linkedin' => '<path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/>',
    'instagram' => '<path d="M12 2.163c3.204 0 3.584.016 4.85.071 1.17.067 1.743.238 2.224.468.947.456 1.54 1.05 2.02 1.529.479.48.817 1.01.996 1.71.216.787.352 1.25.409 2.128.056 1.266.072 1.646.072 4.85s-.016 3.584-.072 4.85c-.057.878-.193 1.341-.409 2.128-.179.7-.517 1.23-.996 1.71-.48.479-1.073 1.02-2.02 1.529-.481.23-1.054.401-2.224.468-1.266.055-1.646.071-4.85.071s-3.584-.016-4.85-.071c-1.17-.067-1.743-.238-2.224-.468-.947-.456-1.54-1.05-2.02-1.529-.479-.48-.817-1.01-.996-1.71-.216-.787-.352-1.25-.409-2.128-.056-1.266-.072-1.646-.072-4.85s.016-3.584.072-4.85c.057-.878.193-1.341.409-2.128.179-.7.517-1.23.996-1.71.48-.479 1.073-1.02 2.02-1.529.481-.23 1.054-.401 2.224-.468 1.266-.055 1.646-.071 4.85-.071zm0-2.163c-3.259 0-3.666.015-4.945.072-1.34.06-2.316.294-3.125.666-1.028.462-1.857 1.11-2.67 1.921-.815.814-1.463 1.643-1.921 2.67-.372.81-.606 1.785-.666 3.125-.057 1.28-.072 1.687-.072 4.945s.015 3.666.072 4.945c.06 1.34.294 2.316.666 3.125.462 1.028 1.11 1.857 1.921 2.67.814.815 1.643 1.463 2.67 1.921.81.372 1.785.606 3.125.666 1.28.057 1.687.072 4.945.072s3.666-.015 4.945-.072c1.34-.06 2.316-.294 3.125-.666 1.028-.462 1.857-1.11 2.67-1.921.815-.814 1.463-1.643 1.921-2.67.372-.81.606-1.785.666-3.125.057-1.28.072-1.687.072-4.945s-.015-3.666-.072-4.945c-.06-1.34-.294-2.316-.666-3.125-.462-1.028-1.11-1.857-1.921-2.67-.814-.815-1.643-1.463-2.67-1.921-.81-.372-1.785-.606-3.125-.666-1.28-.057-1.687-.072-4.945-.072zm0 5.838c-3.414 0-6.182 2.767-6.182 6.182s2.768 6.182 6.182 6.182 6.182-2.767 6.182-6.182-2.768-6.182-6.182-6.182zm0 10.163c-2.209 0-3.999-1.79-3.999-3.999s1.79-3.999 3.999-3.999 3.999 1.79 3.999 3.999-1.79 3.999-3.999 3.999zm6.058-10.879c-.792 0-1.436.643-1.436 1.435s.644 1.435 1.436 1.435 1.436-.643 1.436-1.435-.644-1.435-1.436-1.435z"/>',
    'facebook' => '<path d="M9 8h-3v4h3v12h5v-12h3.642l.358-4h-4v-1.667c0-.959.192-1.333 1.587-1.333h2.413v-3.993h-3.374c-3.296 0-4.626 2.099-4.626 4.695v2.305z"/>',
    'twitter' => '<path d="M24 4.557c-.883.392-1.832.656-2.828.775 1.017-.609 1.797-1.574 2.165-2.724-.951.564-2.005.974-3.127 1.195-.897-.957-2.178-1.555-3.594-1.555-3.03 0-5.483 2.453-5.483 5.48 0 .43.048.847.138 1.245-4.552-.225-8.59-2.406-11.29-5.71-.473.812-.743 1.756-.743 2.76 0 1.902.972 3.57 2.446 4.545-.907-.029-1.765-.278-2.515-.69-.001.023-.001.047-.001.071 0 2.661 1.898 4.881 4.417 5.394-.461.124-.949.187-1.455.187-.355 0-.701-.035-1.039-.098.704 2.193 2.738 3.791 5.153 3.834-1.889 1.481-4.28 2.37-6.892 2.37-.449 0-.894-.025-1.33-.078 2.443 1.565 5.337 2.477 8.441 2.477 10.124 0 15.659-8.381 15.659-15.657 0-.232-.005-.464-.015-.695.962-.695 1.797-1.562 2.457-2.549z"/>',
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Footer</title>
    <style>
        .main-footer {
            background-color: #1a202c;
            color: #a0aec0;
            margin-top: 3rem;
        }

        .footer-container {
            max-width: 1280px;
            margin-left: auto;
            margin-right: auto;
            padding: 3rem 1.5rem;
        }

        .footer-grid {
            display: grid;
            grid-template-columns: repeat(1, minmax(0, 1fr));
            gap: 2rem;
        }
        
        @media (min-width: 640px) {
            .footer-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (min-width: 768px) {
            .footer-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (min-width: 1024px) {
            .footer-grid {
                grid-template-columns: repeat(5, minmax(0, 1fr));
            }
        }

        .footer-heading {
            font-size: 1.125rem;
            font-weight: 600;
            color: #e2e8f0;
            margin-bottom: 1rem;
        }

        .link-list {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .footer-link {
            color: #a0aec0;
            text-decoration: none;
            transition: color 0.2s ease-in-out;
        }

        .footer-link:hover {
            color: #e2e8f0;
        }

        .social-links {
            display: flex;
            gap: 1rem;
        }

        .social-icon {
            color: #a0aec0;
            transition: color 0.2s ease-in-out;
        }

        .social-icon:hover {
            color: #e2e8f0;
        }

        .social-icon svg {
            width: 1.5rem;
            height: 1.5rem;
        }

        .footer-bottom {
            border-top: 1px solid #4a5568;
            margin-top: 2.5rem;
            padding-top: 1.5rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: center;
            font-size: 0.875rem;
            color: #a0aec0;
            text-align: center;
        }

        @media (min-width: 768px) {
            .footer-bottom {
                flex-direction: row;
            }
        }

        .footer-bottom-text {
            margin-top: 0.5rem;
        }

        @media (min-width: 768px) {
            .footer-bottom-text {
                margin-top: 0;
            }
        }
    </style>
</head>
<body>

<!-- FOOTER -->
<footer class="main-footer">
    <div class="footer-container">
        
        <!-- Top Columns -->
        <div class="footer-grid">

            <!-- Contact -->
            <div>
                <h4 class="footer-heading">Contact Us</h4>
                <ul class="link-list">
                    <li>
                        📞 <a href="tel:<?php echo htmlspecialchars($footerConfig['contact']['phone']); ?>" class="footer-link">
                            <?php echo htmlspecialchars($footerConfig['contact']['phone']); ?>
                        </a>
                    </li>
                    <li>
                        📧 <a href="mailto:<?php echo htmlspecialchars($footerConfig['contact']['email']); ?>" class="footer-link">
                            <?php echo htmlspecialchars($footerConfig['contact']['email']); ?>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Dynamic Student Link Columns -->
            <?php foreach ($footerConfig['link_columns'] as $column): ?>
                <div>
                    <h4 class="footer-heading"><?php echo htmlspecialchars($column['title']); ?></h4>
                    <ul class="link-list">
                        <?php foreach ($column['links'] as $text => $url): ?>
                            <li>
                                <a href="<?php echo htmlspecialchars($url); ?>" class="footer-link">
                                    <?php echo htmlspecialchars($text); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>

            <!-- Social Media -->
            <div>
                <h4 class="footer-heading">Follow Us</h4>
                <div class="social-links">
                    <?php foreach ($footerConfig['social_links'] as $network => $url): ?>
                        <?php if (!empty($url) && isset($socialIconSVGs[$network])): ?>
                            <a href="<?php echo htmlspecialchars($url); ?>" target="_blank" class="social-icon">
                                <svg viewBox="0 0 24 24" fill="currentColor">
                                    <?php echo $socialIconSVGs[$network]; ?>
                                </svg>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>

        <!-- Bottom -->
        <div class="footer-bottom">
            <p>© <?php echo date('Y'); ?> <?php echo htmlspecialchars($footerConfig['school_name']); ?>. All rights reserved.</p>
            <?php if (!empty($footerConfig['developer']['name'])): ?>
                <p class="footer-bottom-text">
                    Designed & Developed by 
                    <a href="<?php echo htmlspecialchars($footerConfig['developer']['website']); ?>" target="_blank" class="footer-link">
                        <?php echo htmlspecialchars($footerConfig['developer']['name']); ?>
                    </a>
                </p>
            <?php endif; ?>
        </div>

    </div>
</footer>

</body>
</html>
