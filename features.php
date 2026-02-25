<?php
require_once 'config/config.php';
require_once 'helpers/auth_helper.php';

include_once 'includes/header.php';
?>

<section class="features-hero">
    <div class="container">
        <h1>Powerful Tools for <span class="gradient-text">Modern Professionals</span></h1>
        <p>Everything you need to manage your freelance business, from the first proposal to the final payment. Streamlined, automated, and professional.</p>
    </div>
</section>

<section class="feature-showcase">
    <!-- Feature 1: CRM -->
    <div class="feature-row">
        <div class="feature-content">
            <h2 class="gradient-text">Smart Client CRM</h2>
            <p>Keep all your client information in one place. Track interactions, store contact details, and view project history with ease.</p>
            <ul class="feature-list">
                <li><i class="fas fa-check-circle"></i> Complete interaction history</li>
                <li><i class="fas fa-check-circle"></i> Quick contact management</li>
                <li><i class="fas fa-check-circle"></i> Document storage per client</li>
            </ul>
            <a href="auth/register.php" class="btn btn-primary">Start Managing Clients</a>
        </div>
        <div class="feature-visual">
            <i class="fas fa-users-cog"></i>
        </div>
    </div>

    <!-- Feature 2: Proposals -->
    <div class="feature-row">
        <div class="feature-content">
            <h2 class="gradient-text">Professional Proposals</h2>
            <p>Create stunning proposals in minutes. Our builder helps you outline scope, pricing, and timelines that win clients over.</p>
            <ul class="feature-list">
                <li><i class="fas fa-check-circle"></i> Customizable templates</li>
                <li><i class="fas fa-check-circle"></i> Digital signatures</li>
                <li><i class="fas fa-check-circle"></i> Automated follow-ups</li>
            </ul>
            <a href="auth/register.php" class="btn btn-outline">Build Your First Proposal</a>
        </div>
        <div class="feature-visual">
            <i class="fas fa-file-signature"></i>
        </div>
    </div>

    <!-- Feature 3: Invoicing -->
    <div class="feature-row">
        <div class="feature-content">
            <h2 class="gradient-text">Automated Invoicing</h2>
            <p>Get paid faster with automated invoicing and reminders. Track payment status and professional billing in just a few clicks.</p>
            <ul class="feature-list">
                <li><i class="fas fa-check-circle"></i> Recurring billing</li>
                <li><i class="fas fa-check-circle"></i> Payment tracking</li>
                <li><i class="fas fa-check-circle"></i> Tax & discount handling</li>
            </ul>
            <a href="auth/register.php" class="btn btn-primary">Simplify Your Billing</a>
        </div>
        <div class="feature-visual">
            <i class="fas fa-file-invoice-dollar"></i>
        </div>
    </div>
</section>

<section class="why-choose-us">
    <div class="section-header">
        <h2 style="color: white;">Why <span class="gradient-text">FreelanceFlow?</span></h2>
        <p style="color: #94a3b8;">Built by freelancers, for freelancers. We understand your workflow and pain points.</p>
    </div>

    <div class="why-grid">
        <div class="why-card">
            <i class="fas fa-bolt"></i>
            <h3>Blazing Fast</h3>
            <p>Save hours of administrative work every week with our streamlined interface.</p>
        </div>
        <div class="why-card">
            <i class="fas fa-shield-alt"></i>
            <h3>Secure & Private</h3>
            <p>Your data and your clients' information are protected with industry-standard security.</p>
        </div>
        <div class="why-card">
            <i class="fas fa-mobile-alt"></i>
            <h3>Fully Responsive</h3>
            <p>Manage your business on the go. Works perfectly on desktop, tablet, and mobile.</p>
        </div>
    </div>
</section>

<section class="container" style="padding: 100px 5%; text-align: center;">
    <div style="background: var(--card-bg); padding: 60px; border-radius: 40px; border: 1px solid var(--border-color); box-shadow: var(--shadow-md);">
        <h2 style="font-size: 2.5rem; margin-bottom: 20px;">Ready to grow your business?</h2>
        <p style="color: var(--text-muted); margin-bottom: 40px; max-width: 600px; margin-left: auto; margin-right: auto;">Join our community of professionals and start managing your freelance business like a pro today.</p>
        <div style="display: flex; justify-content: center; gap: 20px;">
            <a href="auth/register.php" class="btn btn-primary">Start Your Free Trial</a>
            <a href="about.php" class="btn btn-outline">Meet the Developer</a>
        </div>
    </div>
</section>

<?php include_once 'includes/footer.php'; ?>
