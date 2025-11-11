<?php

/**
 * =================================================================
 * FOOTER CONFIGURATION
 * =================================================================
 * All content for the footer is managed here.
 * To change anything in the footer, you only need to edit this array.
 */
$footerConfig = [
    'school_name' => 'Your School Name',
    'contact' => [
        'phone' => '+91 12345 67890',
        'email' => 'info@yourschool.com',
    ],
    'link_columns' => [
        [
            'title' => 'Quick Links',
            'links' => [
                'Dashboard' => '#',
                'Admissions' => '#',
                'Staff Directory' => '#',
                'Events Calendar' => '#',
            ],
        ],
        [
            'title' => 'For Students',
            'links' => [
                'Fee Payment' => '#',
                'Exam Schedule' => '#',
                'Results' => '#',
                'Library' => '#',
            ],
        ],
    ],
    'social_links' => [
        'linkedin' => 'https://www.linkedin.com/in/',
        'instagram' => 'https://www.instagram.com/',
        'facebook' => '#', // Set to '#' or '' to hide
        'twitter' => '#',  // Set to '#' or '' to hide
    ],
    'developer' => [
        'name' => 'Sanjeev Kumar',
        'website' => 'https://github.com/',
    ],
];

/**
 * =================================================================
 * SVG ICONS
 * =================================================================
 * SVG paths for social media icons.
 * This keeps the HTML clean.
 */
$socialIconSVGs = [
    'linkedin' => '<path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/>',
    'instagram' => '<path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.85s-.011 3.585-.069 4.85c-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07s-3.585-.012-4.85-.07c-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.85s.012-3.584.07-4.85c.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.85-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948s.014 3.667.072 4.947c.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072s3.667-.014 4.947-.072c4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.947s-.014-3.667-.072-4.947c-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.689-.073-4.948-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.162 6.162 6.162 6.162-2.759 6.162-6.162-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4s1.791-4 4-4 4 1.79 4 4-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44 1.441-.645 1.441-1.44-.645-1.44-1.441-1.44z"/>',
    'facebook' => '<path d="M9 8h-3v4h3v12h5v-12h3.642l.358-4h-4v-1.667c0-.955.192-1.333 1.115-1.333h2.885v-5h-3.808c-3.596 0-5.192 1.583-5.192 4.615v3.385z"/>',
    'twitter' => '<path d="M24 4.557c-.883.392-1.832.656-2.828.775 1.017-.609 1.798-1.574 2.165-2.724-.951.564-2.005.974-3.127 1.195-.897-.957-2.178-1.555-3.594-1.555-3.179 0-5.515 2.966-4.797 6.045-4.091-.205-7.719-2.165-10.148-5.144-1.29 2.213-.669 5.108 1.523 6.574-.806-.026-1.566-.247-2.229-.616v.064c0 2.298 1.634 4.212 3.793 4.649-.65.177-1.339.239-2.049.199.616 1.921 2.441 3.238 4.542 3.28-1.727 1.35-3.896 2.16-6.262 2.133a10.95 10.95 0 005.931 1.745c7.111 0 11.002-5.893 10.688-11.353.77-.556 1.44-1.252 1.968-2.04z"/>',
];
?>

<footer class="app-footer">
    <div class="footer-container">
        <div class="footer-columns">
            <!-- Contact Us Column -->
            <div class="footer-col">
                <h4>Contact Us</h4>
                <ul>
                    <li>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="icon-sm"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.181.42l-2.36 3.54a3.752 3.752 0 01-3.75-3.75l3.54-2.36c.365-.279.53-.74.42-1.18L13.5 7.101a2.25 2.25 0 00-1.091-.852H10.5a2.25 2.25 0 00-2.25 2.25v2.25z" /></svg>
                        <a href="tel:<?php echo htmlspecialchars($footerConfig['contact']['phone']); ?>"><?php echo htmlspecialchars($footerConfig['contact']['phone']); ?></a>
                    </li>
                    <li>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="icon-sm"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" /></svg>
                        <a href="mailto:<?php echo htmlspecialchars($footerConfig['contact']['email']); ?>"><?php echo htmlspecialchars($footerConfig['contact']['email']); ?></a>
                    </li>
                </ul>
            </div>

            <!-- Dynamic Link Columns -->
            <?php foreach ($footerConfig['link_columns'] as $column): ?>
                <div class="footer-col">
                    <h4><?php echo htmlspecialchars($column['title']); ?></h4>
                    <ul>
                        <?php foreach ($column['links'] as $text => $url): ?>
                            <li><a href="<?php echo htmlspecialchars($url); ?>"><?php echo htmlspecialchars($text); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>

            <!-- Follow Us Column (Social Icons) -->
            <div class="footer-col">
                <h4>Follow Us</h4>
                <div class="social-icons">
                    <?php foreach ($footerConfig['social_links'] as $network => $url): ?>
                        <?php if (!empty($url) && $url !== '#' && isset($socialIconSVGs[$network])): ?>
                            <a href="<?php echo htmlspecialchars($url); ?>" target="_blank" aria-label="<?php echo ucfirst($network); ?>" title="<?php echo ucfirst($network); ?>">
                                <svg viewBox="0 0 24 24" fill="currentColor">
                                    <?php echo $socialIconSVGs[$network]; ?>
                                </svg>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="footer-bottom">
            <p class="copyright">© <?php echo date('Y'); ?> <?php echo htmlspecialchars($footerConfig['school_name']); ?>. All rights reserved.</p>
            <?php if (!empty($footerConfig['developer']['name'])): ?>
                <p class="developer-credit">
                    Designed & Developed by <a href="<?php echo htmlspecialchars($footerConfig['developer']['website']); ?>" target="_blank"><?php echo htmlspecialchars($footerConfig['developer']['name']); ?></a>
                </p>
            <?php endif; ?>
        </div>
    </div>
</footer>

<!-- STYLES (Your excellent CSS is preserved and slightly enhanced) -->
<style>
    :root {
        --footer-bg: #111827;
        --footer-text: #9ca3af;
        --footer-heading: #e5e7eb;
        --footer-accent: #6a82fb;
        --footer-divider: #374151;
    }
    .app-footer {
        background-color: var(--footer-bg);
        color: var(--footer-text);
        padding: 4rem 1.5rem;
        font-size: 0.9rem;
        line-height: 1.6;
    }
    .footer-container {
        max-width: 1280px;
        margin: 0 auto;
    }
    .footer-columns {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 2.5rem;
        padding-bottom: 2.5rem;
        border-bottom: 1px solid var(--footer-divider);
    }
    .footer-col h4 {
        color: var(--footer-heading);
        font-size: 1.1rem;
        margin-bottom: 1.5rem;
        font-weight: 600;
        position: relative;
    }
    .footer-col h4::after {
        content: '';
        position: absolute;
        left: 0;
        bottom: -8px;
        height: 2px;
        width: 40px;
        background: var(--footer-accent);
    }
    .footer-col ul {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    .footer-col ul li {
        margin-bottom: 0.8rem;
    }
    .footer-col ul li a {
        display: flex;
        align-items: center;
    }
    .footer-col a {
        text-decoration: none;
        color: var(--footer-text);
        transition: color 0.3s ease, padding-left 0.3s ease;
    }
    .footer-col a:hover, .footer-col a:focus {
        color: #fff;
        padding-left: 5px;
        outline: none;
    }
    .icon-sm {
        width: 1.1rem;
        height: 1.1rem;
        margin-right: 0.75rem;
        color: var(--footer-text);
        flex-shrink: 0;
    }
    .social-icons {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        margin-top: 1rem;
    }
    .social-icons a {
        display: inline-block;
        width: 38px;
        height: 38px;
        background-color: var(--footer-divider);
        color: var(--footer-text);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background-color 0.3s ease, transform 0.3s ease;
    }
    .social-icons a:hover, .social-icons a:focus {
        background-color: var(--footer-accent);
        color: #fff;
        transform: translateY(-3px);
        outline: none;
    }
    .social-icons svg {
        width: 18px;
        height: 18px;
    }
    .footer-bottom {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        padding-top: 2.5rem;
        color: #6b7280; /* gray-500 */
    }
    .developer-credit a {
        color: var(--footer-text);
        text-decoration: none;
        font-weight: 500;
        transition: color 0.3s ease;
    }
    .developer-credit a:hover, .developer-credit a:focus {
        color: var(--footer-accent);
        text-decoration: underline;
        outline: none;
    }

    @media (max-width: 768px) {
        .footer-columns {
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 2rem;
        }
        .footer-bottom {
            flex-direction: column;
            text-align: center;
        }
    }
</style>