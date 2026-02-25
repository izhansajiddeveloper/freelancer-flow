<?php
/**
 * Landing Page - Index
 */
include_once 'includes/header.php';
?>

<!-- Hero Section -->
<section class="hero">
    <h1>Manage Your Freelance Business <br> <span class="gradient-text">Like a Professional</span></h1>
    <p>The ultimate CRM, Proposal, and Invoice system built specifically for modern freelancers. Take control of your workflow and get paid faster.</p>
    
    <div class="hero-btns">
        <a href="auth/register.php" class="btn btn-primary">Start for Free</a>
        <a href="#demo" class="btn btn-outline">Watch Demo</a>
    </div>
</section>

<!-- Stats Ribbon -->
<section class="stats-ribbon">
    <div class="stat-item">
        <h3>5k+</h3>
        <p>Active Freelancers</p>
    </div>
    <div class="stat-item">
        <h3>$2M+</h3>
        <p>Invoices Generated</p>
    </div>
    <div class="stat-item">
        <h3>15k+</h3>
        <p>Proposals Sent</p>
    </div>
    <div class="stat-item">
        <h3>99%</h3>
        <p>Satisfaction Rate</p>
    </div>
</section>

<!-- Concept Cards -->
<div class="section-header">
    <h2 class="gradient-text">Everything you need to scale</h2>
    <p>Powerful features designed to handle the boring stuff, so you can focus on your creative work.</p>
</div>

<section class="hero-cards">
    <div class="feature-card">
        <i class="fas fa-file-invoice-dollar"></i>
        <h3>Smart Invoicing</h3>
        <p>Create professional invoices in seconds. Track payments and send automated reminders to clients with one click.</p>
    </div>

    <div class="feature-card">
        <i class="fas fa-handshake"></i>
        <h3>Proposal Master</h3>
        <p>Win more projects with stunning, customizable proposals. Convert accepted proposals into contracts automatically.</p>
    </div>

    <div class="feature-card">
        <i class="fas fa-chart-line"></i>
        <h3>Growth Tracking</h3>
        <p>Visualize your earnings, active projects, and client history in a beautiful, unified dashboard designed for clarity.</p>
    </div>

    <div class="feature-card">
        <i class="fas fa-shield-alt"></i>
        <h3>Contract Generator</h3>
        <p>Protect your work with legally binding contracts generated instantly from your project details and terms.</p>
    </div>

    <div class="feature-card">
        <i class="fas fa-bell"></i>
        <h3>Auto Reminders</h3>
        <p>Never chase a payment again. Our system automatically follows up with clients when invoices are due or overdue.</p>
    </div>

    <div class="feature-card">
        <i class="fas fa-users-cog"></i>
        <h3>Client CRM</h3>
        <p>Keep a detailed history of every client. Notes, project history, and milestones all in one centralized location.</p>
    </div>
</section>

<!-- How It Works -->
<div class="section-header">
    <h2 class="gradient-text">How it works</h2>
    <p>Three simple steps to professional freelance management.</p>
</div>

<section class="steps-grid">
    <div class="step-item">
        <div class="step-number">1</div>
        <h3>Add Your Clients</h3>
        <p>Import or manually add your client database with notes and contact details.</p>
    </div>
    <div class="step-item">
        <div class="step-number">2</div>
        <h3>Create & Send</h3>
        <p>Generate professional proposals or invoices and send them directly to your clients.</p>
    </div>
    <div class="step-item">
        <div class="step-number">3</div>
        <h3>Get Paid</h3>
        <p>Track payments and watch your business grow through real-time analytics.</p>
    </div>
</section>

<!-- Testimonials Slider -->
<div class="section-header">
    <h2 class="gradient-text">What our users say</h2>
</div>

<section class="slider-container">
    <div class="slider-track" id="sliderTrack">
        <div class="slide">
            <div class="testimonial-header">
                <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
            </div>
            <p>"FreelanceFlow changed my life. I used to spend hours on invoices, now it takes 2 minutes!"</p>
            <div class="testimonial-user">
                <img src="https://ui-avatars.com/api/?name=Alex+Johnson&background=random" alt="User">
                <div class="user-info">
                    <h4>Alex Johnson</h4>
                    <span>Graphic Designer</span>
                </div>
            </div>
        </div>
        <div class="slide">
            <div class="testimonial-header">
                <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
            </div>
            <p>"The proposal generator is a game changer. My acceptance rate has increased by 40%."</p>
            <div class="testimonial-user">
                <img src="https://ui-avatars.com/api/?name=Sarah+Smith&background=random" alt="User">
                <div class="user-info">
                    <h4>Sarah Smith</h4>
                    <span>Web Developer</span>
                </div>
            </div>
        </div>
        <div class="slide">
            <div class="testimonial-header">
                <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
            </div>
            <p>"Finally a CRM that isn't bloated. It's fast, beautiful, and does exactly what I need."</p>
            <div class="testimonial-user">
                <img src="https://ui-avatars.com/api/?name=Mike+Ross&background=random" alt="User">
                <div class="user-info">
                    <h4>Mike Ross</h4>
                    <span>Content Writer</span>
                </div>
            </div>
        </div>
        <!-- Duplicate for loop feel -->
        <div class="slide">
            <div class="testimonial-header">
                <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
            </div>
            <p>"The dark mode is just gorgeous. I love working on my dashboard at night."</p>
            <div class="testimonial-user">
                <img src="https://ui-avatars.com/api/?name=Elena+G&background=random" alt="User">
                <div class="user-info">
                    <h4>Elena G</h4>
                    <span>UI/UX Designer</span>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Theme Toggle Button -->
<div class="theme-toggle" id="themeToggle" title="Toggle Dark/Light Mode">
    <i class="fas fa-moon"></i>
</div>

<script>
    // Theme Toggle Logic
    const themeToggle = document.getElementById('themeToggle');
    const body = document.body;
    const icon = themeToggle.querySelector('i');

    // Default is light. If user previously chose dark, apply it.
    if (localStorage.getItem('theme') === 'dark') {
        body.classList.add('dark-mode');
        icon.classList.replace('fa-moon', 'fa-sun');
    }

    themeToggle.addEventListener('click', () => {
        body.classList.toggle('dark-mode');
        
        if (body.classList.contains('dark-mode')) {
            icon.classList.replace('fa-moon', 'fa-sun');
            localStorage.setItem('theme', 'dark');
        } else {
            icon.classList.replace('fa-sun', 'fa-moon');
            localStorage.setItem('theme', 'light');
        }
    });

    // Slider Logic
    const track = document.getElementById('sliderTrack');
    let index = 0;

    function moveSlider() {
        const slideWidth = document.querySelector('.slide').offsetWidth + 30; // 30 is gap
        index++;
        if (index > track.children.length - 3) { // Show 3 slides
            index = 0;
        }
        track.style.transform = `translateX(-${index * slideWidth}px)`;
    }

    setInterval(moveSlider, 3000); // Auto slide every 3 seconds
</script>

<?php 
include_once 'includes/footer.php';
?>
