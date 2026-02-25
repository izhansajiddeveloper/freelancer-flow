<?php
require_once 'config/config.php';
require_once 'helpers/auth_helper.php';

include_once 'includes/header.php';
?>

<section class="about-hero">
    <div class="container">
        <h1>Transforming the <span class="gradient-text">Freelance Journey</span></h1>
        <p>Empowering creators and professionals worldwide with seamless business management tools. We're on a mission to simplify the way you work.</p>
    </div>
</section>

<section class="mission-vision">
    <div class="mv-card">
        <div class="mv-icon">
            <i class="fas fa-bullseye"></i>
        </div>
        <h2>Our Mission</h2>
        <p>To provide freelancers with a comprehensive, intuitive, and powerful platform that handles the business side of their work, allowing them to focus on what they do best: creating and delivering value to their clients.</p>
    </div>
    <div class="mv-card">
        <div class="mv-icon">
            <i class="fas fa-eye"></i>
        </div>
        <h2>Our Vision</h2>
        <p>To become the world's most trusted partner for independent professionals, fostering a global ecosystem where freelancers can manage, grow, and scale their businesses with confidence and ease.</p>
    </div>
</section>

<section class="team-section">
    <div class="section-header">
        <h2>Meet the <span class="gradient-text">Genius</span> Behind It</h2>
        <p>A passionate developer committed to building solutions that make a difference in the freelance world.</p>
    </div>

    <div class="team-grid">
        <!-- Izhan Sajid Profile -->
        <div class="team-card">
            <img src="<?php echo BASE_URL; ?>assets/images/izhan-sajid.jpg" class="team-img" alt="Izhan Sajid" onerror="this.src='https://ui-avatars.com/api/?name=Izhan+Sajid&background=4f46e5&color=fff&size=512'">
            <div class="team-info">
                <h3>Izhan Sajid</h3>
                <p>Founder & Lead Developer</p>
                <div class="team-socials">
                    <a href="https://github.com/izhansajiddeveloper/" target="_blank" title="GitHub">
                        <i class="fab fa-github"></i>
                    </a>
                    <a href="https://www.linkedin.com/in/izhansajid/" target="_blank" title="LinkedIn">
                        <i class="fab fa-linkedin-in"></i>
                    </a>
                   
                </div>
            </div>
        </div>
    </div>
</section>

<section class="container" style="padding: 0 5% 100px; text-align: center;">
    <div style="background: var(--card-bg); padding: 50px; border-radius: 30px; border: 1px solid var(--border-color);">
        <h2 style="margin-bottom: 20px;">Ready to elevate your freelancing?</h2>
        <p style="color: var(--text-muted); margin-bottom: 40px;">Join thousands of others who have simplified their workflow with FreelanceFlow.</p>
        <a href="auth/register.php" class="btn btn-primary">Get Started for Free</a>
    </div>
</section>

<?php include_once 'includes/footer.php'; ?>
