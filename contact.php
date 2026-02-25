<?php
require_once 'config/config.php';
require_once 'helpers/auth_helper.php';
require_once 'helpers/mail_helper.php';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);

    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $error = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } else {
        if (sendContactEmail($name, $email, $subject, $message)) {
            $success = 'Thank you for contacting us! We will get back to you shortly.';
        } else {
            $error = 'Failed to send your message. Please try again later.';
        }
    }
}

include_once 'includes/header.php';
?>

<section class="about-hero">
    <div class="container">
        <h1>Get in <span class="gradient-text">Touch</span></h1>
        <p>Have questions or need assistance? We're here to help you grow your freelance business.</p>
    </div>
</section>

<section class="contact-section">
    <div class="contact-grid">
        <!-- Contact Info -->
        <div class="contact-info-card">
            <h2>Contact Information</h2>
            <p style="color: var(--text-muted); margin-bottom: 40px;">Fill out the form and our team will get back to you within 24 hours.</p>

            <div class="info-item">
                <div class="info-icon">
                    <i class="fas fa-envelope"></i>
                </div>
                <div class="info-text">
                    <h3>Email Us</h3>
                    <p>support@freelanceflow.com</p>
                </div>
            </div>

            <div class="info-item">
                <div class="info-icon">
                    <i class="fas fa-phone-alt"></i>
                </div>
                <div class="info-text">
                    <h3>Call Us</h3>
                    <p>+92 317 7990549</p>
                </div>
            </div>

            <div class="info-item">
                <div class="info-icon">
                    <i class="fas fa-map-marker-alt"></i>
                </div>
                <div class="info-text">
                    <h3>Location</h3>
                    <p>Karachi, Pakistan</p>
                </div>
            </div>

            <div style="margin-top: 50px; border-top: 1px solid var(--border-color); padding-top: 30px;">
                <h3>Follow Us</h3>
                <div class="team-socials" style="justify-content: flex-start; margin-top: 15px;">
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <a href="https://github.com/izhansajiddeveloper/"><i class="fab fa-github"></i></a>
                </div>
            </div>
        </div>

        <!-- Contact Form -->
        <div class="contact-form-card">
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <form action="" method="POST" class="contact-form">
                <div class="form-group">
                    <label for="name">Full Name</label>
                    <input type="text" name="name" id="name" class="form-control" placeholder="John Doe" required>
                </div>

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" name="email" id="email" class="form-control" placeholder="john@example.com" required>
                </div>

                <div class="form-group">
                    <label for="subject">Subject</label>
                    <input type="text" name="subject" id="subject" class="form-control" placeholder="How can we help?" required>
                </div>

                <div class="form-group">
                    <label for="message">Your Message</label>
                    <textarea name="message" id="message" class="form-control" placeholder="Write your message here..." required></textarea>
                </div>

                <button type="submit" class="btn btn-primary btn-block" style="width: 100%;">Send Message</button>
            </form>
        </div>
    </div>
</section>

<?php include_once 'includes/footer.php'; ?>
