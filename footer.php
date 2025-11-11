<!-- footer.php -->

<!-- Start of Footer HTML -->
<footer class="site-footer">
    <div class="footer-container">
        <div class="footer-column">
            <h4>CodeCraft</h4>
            <p>Building beautiful, performant web experiences with modern technologies and a passion for design.</p>
        </div>
        <div class="footer-column">
            <h4>Quick Links</h4>
            <ul class="footer-links">
                <li><a href="./index.php">Home</a></li>
                <li><a href="./about.php">About Me</a></li>
                <li><a href="./Website.php">website</a></li>
                <li><a href="./index.php#inquary">Contact</a></li>
            </ul>
        </div>
        <div class="footer-column">
            <h4>Connect</h4>
            <p>Follow me on social media to see my latest work and thoughts.</p>
            <div class="social-links">
                <!-- Replace # with your actual links. Uses Font Awesome icons. -->
                <a href="https://github.com/Sanjeev-k-11" aria-label="GitHub"><i class="fab fa-github"></i></a>
                <a href="https://www.linkedin.com/in/sanjeevkumaryadav/" aria-label="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                <a href="#" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
            </div>
        </div>
    </div>
    <div class="footer-bottom">
        &copy; <?php echo date("Y"); ?> CodeCraft. All Rights Reserved.
    </div>
</footer>

<!-- Back to Top Button HTML -->
<button class="back-to-top" id="back-to-top" aria-label="Back to top">
    <!-- Using a simple arrow character or an icon font -->
    &#8593;
</button>

<!-- Start of JavaScript -->
<script>
document.addEventListener("DOMContentLoaded", () => {
    
    // --- PARALLAX ANIMATION ENGINE (Your original script) ---
    const container = document.getElementById('hero-parallax-container');
    const gridContainer = document.getElementById('parallax-grid-container');
    const row1 = document.getElementById('row-1');
    const row2 = document.getElementById('row-2');
    const row3 = document.getElementById('row-3');

    if (container && gridContainer) {
        const springConfig = { stiffness: 0.05, damping: 0.7 };
        let animState = {
            translateX: { current: 0, target: 0 },
            translateXReverse: { current: 0, target: 0 },
            rotateX: { current: 15, target: 15 },
            rotateZ: { current: 20, target: 20 },
            translateY: { current: -700, target: -700 },
            opacity: { current: 0.2, target: 0.2 },
        };
        const lerp = (start, end, progress) => start * (1 - progress) + end * progress;
        
        function updateAnimationTargets() {
            const scrollY = window.scrollY;
            const progress = Math.max(0, Math.min(1, (scrollY - container.offsetTop) / (container.offsetHeight - window.innerHeight)));
            const animationProgress = Math.min(progress / 0.2, 1);

            animState.translateX.target = lerp(0, 1000, progress);
            animState.translateXReverse.target = lerp(0, -1000, progress);
            animState.rotateX.target = lerp(15, 0, animationProgress);
            animState.rotateZ.target = lerp(20, 0, animationProgress);
            animState.translateY.target = lerp(-700, 500, animationProgress);
            animState.opacity.target = lerp(0.2, 1, animationProgress);
        }
        
        function animateParallax() {
            window.addEventListener('scroll', updateAnimationTargets, { passive: true });
            function tick() {
                for (const key in animState) {
                    const state = animState[key];
                    const distance = state.target - state.current;
                    state.current += distance * springConfig.stiffness;
                }
                gridContainer.style.opacity = animState.opacity.current;
                gridContainer.style.transform = `translateY(${animState.translateY.current}px) rotateX(${animState.rotateX.current}deg) rotateZ(${animState.rotateZ.current}deg)`;
                row1.style.transform = `translateX(${animState.translateX.current}px)`;
                row2.style.transform = `translateX(${animState.translateXReverse.current}px)`;
                row3.style.transform = `translateX(${animState.translateX.current}px)`;
                requestAnimationFrame(tick);
            }
            tick();
        }
        animateParallax();
    }

    // --- NAVBAR SCROLL & BACK TO TOP BUTTON LOGIC ---
    const navbar = document.getElementById('navbar');
    const backToTopButton = document.getElementById('back-to-top');

    function handleScroll() {
        // Navbar scroll effect
        if (navbar) {
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        }
        // Back to top button visibility
        if (backToTopButton) {
            if (window.scrollY > 300) {
                backToTopButton.classList.add('visible');
            } else {
                backToTopButton.classList.remove('visible');
            }
        }
    }

    window.addEventListener('scroll', handleScroll, { passive: true });

    if (backToTopButton) {
        backToTopButton.addEventListener('click', () => {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    }
});
</script>

</body>
</html>