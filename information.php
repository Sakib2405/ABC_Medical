<?php
// In a more complex setup, you might fetch some of this from site_settings
// For now, we'll use placeholders or values you can easily change.
$page_title = "Clinic Information - ABC Medical"; // Or dynamically set
$clinic_name = "ABC Medical Clinic"; // Could come from DB
$clinic_tagline = "Your Trusted Partner in Health & Wellness in Patuakhali.";

// Sample data that could be fetched from site_settings in a real app
$clinic_address_static = "Dumki, Patuakhali, Bangladesh";
$clinic_phone_static = "+880 1700-123456";
$clinic_email_static = "info@abcmedical.com.bd";
$operating_hours_static = "Saturday - Thursday: 9:00 AM - 8:00 PM\nFriday: Closed";

// If you have a session for logged-in users, you might include a header
// session_start();
// include 'header.php'; // Assuming you have a common header
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title); ?></title>
    <link rel="stylesheet" href="style.css"> <link rel="stylesheet" href="information.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="info-page-container">
        <header class="info-page-header">
            <h1><?= htmlspecialchars($clinic_name); ?></h1>
            <p><?= htmlspecialchars($clinic_tagline); ?></p>
        </header>

        <main class="info-content">
            <section class="info-section" id="about-us">
                <h2><i class="fas fa-hospital-symbol"></i> About Our Clinic</h2>
                <p>Welcome to <?= htmlspecialchars($clinic_name); ?>! Established in 2010, we have been dedicated to providing high-quality, compassionate medical care to the community of Dhaka and beyond. Our state-of-the-art facility is equipped with modern technology, and our team of experienced and caring healthcare professionals is committed to your well-being.</p>
                <p>We believe in a patient-centered approach, ensuring that you receive personalized care tailored to your unique health needs. From routine check-ups to specialized treatments, we are here to support you on your journey to better health.</p>
            </section>

            <section class="info-section" id="our-mission">
                <h2><i class="fas fa-bullseye"></i> Our Mission & Vision</h2>
                <div class="mission-vision-grid">
                    <div>
                        <h3>Our Mission</h3>
                        <p>To deliver exceptional and accessible healthcare services with integrity, respect, and a commitment to continuous improvement, enhancing the quality of life for all our patients.</p>
                    </div>
                    <div>
                        <h3>Our Vision</h3>
                        <p>To be a leading healthcare provider in Bangladesh, recognized for clinical excellence, innovation, and a profound dedication to patient care and community health.</p>
                    </div>
                </div>
            </section>

            <section class="info-section" id="services-overview">
                <h2><i class="fas fa-briefcase-medical"></i> Services We Offer</h2>
                <p>We provide a comprehensive range of medical services to cater to diverse healthcare needs. Some of our key services include:</p>
                <ul>
                    <li>General Physician Consultations</li>
                    <li>Specialist Consultations (Cardiology, Pediatrics, Gynecology, etc.)</li>
                    <li>Diagnostic Services (Lab tests, Imaging - specify if available)</li>
                    <li>Vaccination and Immunization Programs</li>
                    <li>Minor Surgical Procedures</li>
                    <li>Chronic Disease Management</li>
                    <li>Preventive Health Check-ups</li>
                    <li>Pharmacy Services</li>
                </ul>
                <p><a href="services.php" class="learn-more-link">Learn more about our services...</a></p> </section>

            <section class="info-section" id="contact-hours">
                <h2><i class="fas fa-address-book"></i> Contact & Hours</h2>
                <div class="contact-details-grid">
                    <div class="contact-item">
                        <h3><i class="fas fa-map-marker-alt"></i> Our Location</h3>
                        <p><?= htmlspecialchars($clinic_address_static); ?></p>
                        </div>
                    <div class="contact-item">
                        <h3><i class="fas fa-phone-alt"></i> Phone</h3>
                        <p><a href="tel:<?= htmlspecialchars($clinic_phone_static); ?>"><?= htmlspecialchars($clinic_phone_static); ?></a></p>
                        <h3><i class="fas fa-envelope"></i> Email</h3>
                        <p><a href="mailto:<?= htmlspecialchars($clinic_email_static); ?>"><?= htmlspecialchars($clinic_email_static); ?></a></p>
                    </div>
                    <div class="contact-item">
                        <h3><i class="fas fa-clock"></i> Operating Hours</h3>
                        <pre><?= htmlspecialchars($operating_hours_static); ?></pre>
                        <p>For emergencies outside these hours, please visit the nearest hospital emergency department.</p>
                    </div>
                </div>
            </section>
             <p style="text-align:center; margin-top:30px;">
                <a href="index.php" class="button-secondary"><i class="fas fa-home"></i> Back to Homepage</a>
            </p>
        </main>

        <footer class="info-page-footer">
            <p>&copy; <?= date("Y"); ?> <?= htmlspecialchars($clinic_name); ?>. All Rights Reserved.</p>
            </footer>
    </div>
    <script type='text/javascript' src='//pl27012931.profitableratecpm.com/23/01/9e/23019e8e62b0d680b7c22119518abe76.js'></script>
<script async="async" data-cfasync="false" src="//pl27013164.profitableratecpm.com/518b5bf3a8f610d01ac4771c391ef67d/invoke.js"></script>

    <?php
    // if you have a common footer
    // include 'footer.php';
    ?>
</body>
</html>