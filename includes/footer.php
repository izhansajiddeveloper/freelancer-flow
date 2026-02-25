<footer class="footer">
    <div class="footer-grid">
        <div class="footer-info">
            <div class="footer-logo">FreelanceFlow</div>
            <p class="footer-desc">The all-in-one solution for freelancers to manage clients, proposals, invoices, and payments. Streamline your workflow and get paid faster.</p>
            <div class="footer-socials">
                <a href="#"><i class="fab fa-facebook-f"></i></a>
                <a href="#"><i class="fab fa-twitter"></i></a>
                <a href="#"><i class="fab fa-linkedin-in"></i></a>
                <a href="#"><i class="fab fa-instagram"></i></a>
            </div>
        </div>

        <div class="footer-column">
            <h4 class="footer-title">Quick Links</h4>
            <ul class="footer-links">
                <li><a href="<?php echo BASE_URL; ?>">Home</a></li>
                <li><a href="<?php echo BASE_URL; ?>about.php">About Us</a></li>
                <li><a href="<?php echo BASE_URL; ?>features.php">Features</a></li>
                <li><a href="#">Pricing</a></li>
            </ul>
        </div>

        <div class="footer-column">
            <h4 class="footer-title">Resources</h4>
            <ul class="footer-links">
                <li><a href="#">Documentation</a></li>
                <li><a href="#">Help Center</a></li>
                <li><a href="#">Privacy Policy</a></li>
                <li><a href="#">Terms of Service</a></li>
            </ul>
        </div>

        <div class="footer-column">
            <h4 class="footer-title">Contact Us</h4>
            <ul class="footer-links" style="color: #94a3b8;">
                <li><i class="fas fa-envelope" style="margin-right: 10px;"></i> support@freelanceflow.com</li>
                <li><i class="fas fa-phone" style="margin-right: 10px;"></i> +92 317 7990549</li>
                <li><i class="fas fa-map-marker-alt" style="margin-right: 10px;"></i> Karachi, Pakistan</li>
            </ul>
        </div>
    </div>

    <div class="footer-bottom">
        <p>&copy; <?php echo date('Y'); ?> FreelanceFlow. All rights reserved. Designed with <i class="fas fa-heart" style="color: #f87171;"></i> for Freelancers.</p>
    </div>
</footer>

<!-- JS Script to handle sidebar toggle and active states if needed -->
<script>
    // Smooth scroll
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            document.querySelector(this.getAttribute('href')).scrollIntoView({
                behavior: 'smooth'
            });
        });
    });
</script>

</body>
</html>
