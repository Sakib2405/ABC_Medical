<?php
// No session_start() needed unless specific user session data is required on this page.
// If you include a common header/footer that uses sessions, it's fine.

$page_title = "Blood Donation Information - ABC Medical Clinic";
$clinic_name_for_page = "ABC Medical Clinic"; // Could be fetched from site_settings

// Information about blood donation organizations in Bangladesh (examples)
// In a real application, these could be managed in a database or config file.
$blood_donation_organizations_bd = [
    [
        'name' => "Bangladesh Red Crescent Society",
        'website' => "https://www.bdrcs.org/donate-blood", // Example, verify actual link
        'notes' => "A major organization involved in blood collection and disaster relief across Bangladesh. They organize regular camps and have permanent centers."
    ],
    [
        'name' => "Quantum Foundation (Blood Program)",
        'website' => "https://blood.quantummethod.org.bd", // Example, verify actual link
        'notes' => "Known for extensive voluntary blood donation campaigns and managing one of the largest private blood banks in Dhaka (Quantum Lab)."
    ],
    [
        'name' => "Thalassaemia Hospital Blood Bank, Dhaka",
        'website' => "http://www.thalassaemia.org.bd/", // Example, verify actual link
        'notes' => "Specialized hospital for Thalassemia patients, constantly in need of blood donations for transfusions."
    ],
    [
        'name' => "Dhaka Medical College Hospital Blood Bank",
        'notes' => "One of the largest public hospital blood banks, serving a huge number of patients daily."
    ],
    [
        'name' => "Combined Military Hospital (CMH) Blood Bank, Dhaka",
        'notes' => "Provides blood for military personnel and often supports civilian needs in emergencies."
    ]
];

// Clinic's involvement - placeholder
$clinic_blood_drive_info = "Currently, " . $clinic_name_for_page . " periodically collaborates with local blood banks for donation drives. Please check our official announcements or contact us directly for details on upcoming events and how you can participate.";
// If the clinic has its own donor registry:
// $clinic_donor_registry_link = "register_donor.php"; // Example link to a registration page

// FAQ Data
$faqs = [
    [
        'question' => "How much blood is taken during a donation?",
        'answer' => "Typically, about 450 ml (roughly one pint) of blood is collected during a whole blood donation. Your body quickly replaces this volume."
    ],
    [
        'question' => "Is blood donation safe?",
        'answer' => "Yes, blood donation is very safe. Sterile, single-use equipment is used for each donor, eliminating any risk of infection transmission. Trained medical professionals oversee the entire process."
    ],
    [
        'question' => "What should I do before donating blood?",
        'answer' => "Ensure you get a good night's sleep, eat a healthy meal (avoiding fatty foods), and drink plenty of fluids (non-alcoholic) before your donation. Bring a valid ID."
    ],
    [
        'question' => "What should I do after donating blood?",
        'answer' => "Rest for 10-15 minutes at the donation center, drink extra fluids, and avoid strenuous physical activity or heavy lifting for at least 24 hours. Keep the bandage on for a few hours."
    ],
    [
        'question' => "Does blood donation hurt?",
        'answer' => "You might feel a brief sting when the needle is inserted, but the process itself is generally not painful. Many donors report little to no discomfort."
    ]
];

// Simulate Blood Stock/Need Data (for demonstration purposes)
// In a real application, this would come from a database or external API.
$blood_stock_status = [
    'A+' => 'medium', // low, medium, high
    'A-' => 'low',
    'B+' => 'medium',
    'B-' => 'low',
    'AB+' => 'high',
    'AB-' => 'low',
    'O+' => 'critical', // Critical implies very low and urgent need
    'O-' => 'critical'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title); ?></title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* General page layout */
        body {
            font-family: 'Roboto', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f8f9fa; /* Light grey background */
            color: #333;
            line-height: 1.6;
        }

        .blood-donation-container {
            max-width: 1000px;
            margin: 40px auto;
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .page-header.blood-header {
            background: linear-gradient(135deg, #e74c3c, #c0392b); /* Red gradient */
            color: white;
            padding: 40px 20px;
            text-align: center;
            border-bottom: 5px solid #a93226;
        }

        .blood-header h1 {
            font-size: 2.8rem;
            margin: 0 0 10px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }

        .blood-header h1 .fas {
            font-size: 3.5rem;
            animation: pulseIcon 2s infinite ease-in-out; /* Subtle pulse on the icon */
        }

        .blood-header p {
            font-size: 1.2rem;
            margin: 0;
            opacity: 0.9;
        }

        @keyframes pulseIcon {
            0% { transform: scale(1); text-shadow: none; }
            50% { transform: scale(1.05); text-shadow: 0 0 15px rgba(255,255,255,0.7); }
            100% { transform: scale(1); text-shadow: none; }
        }

        /* Main content wrapper */
        .content-wrapper {
            padding: 30px;
        }

        .info-section {
            background-color: #fff;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .info-section:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }

        .info-section h2 {
            font-size: 2rem;
            color: #2c3e50; /* Dark blue-gray */
            margin-top: 0;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }

        .info-section h2 .fas {
            font-size: 1.5em;
            color: #e74c3c; /* Red accent for section icons */
        }

        .info-section ul {
            list-style: none;
            padding-left: 0;
            margin-bottom: 15px;
        }

        .info-section ul li {
            padding: 8px 0;
            border-bottom: 1px dashed #f2f2f2;
            color: #555;
            display: flex;
            align-items: flex-start;
        }

        .info-section ul li:last-child {
            border-bottom: none;
        }

        .info-section ul li::before {
            content: "\f00c"; /* Font Awesome check icon */
            font-family: "Font Awesome 5 Free";
            font-weight: 900;
            color: #28a745; /* Green checkmark */
            margin-right: 10px;
            font-size: 0.9em;
            flex-shrink: 0;
        }

        .info-section .disclaimer {
            font-style: italic;
            color: #777;
            border-left: 4px solid #ffc107; /* Yellow border */
            padding-left: 15px;
            background-color: #fffde7; /* Light yellow background */
            margin-top: 20px;
            padding: 15px;
            border-radius: 5px;
        }

        /* Process Steps */
        .process-steps {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 25px;
            margin-top: 20px;
            text-align: center;
        }

        .step {
            background-color: #f0f8ff; /* Light blue */
            border: 1px solid #d0e8ff;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .step:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 15px rgba(0,0,0,0.1);
        }

        .step-icon {
            font-size: 2.5rem;
            color: #007bff; /* Primary blue */
            margin-bottom: 15px;
        }

        .step h3 {
            font-size: 1.3rem;
            color: #34495e;
            margin-top: 0;
            margin-bottom: 10px;
        }

        .step p {
            font-size: 0.95rem;
            color: #666;
        }

        /* Organizations List */
        .organizations-list {
            list-style: none;
            padding-left: 0;
        }

        .organizations-list li {
            background-color: #fdfdfd;
            border-left: 5px solid #e74c3c; /* Red accent */
            padding: 12px 15px;
            margin-bottom: 10px;
            border-radius: 8px;
            box-shadow: 0 1px 5px rgba(0,0,0,0.03);
            transition: background-color 0.2s ease;
        }

        .organizations-list li:hover {
            background-color: #fff5f5; /* Lighter red on hover */
        }

        .organizations-list li strong {
            font-size: 1.1rem;
            color: #333;
        }

        .organizations-list li a {
            color: #007bff;
            text-decoration: none;
            font-weight: bold;
            margin-left: 10px;
        }

        .organizations-list li a:hover {
            text-decoration: underline;
        }

        .organizations-list .org-notes {
            font-size: 0.9em;
            color: #777;
            margin-top: 5px;
        }

        /* CTA Button (general) */
        .cta-button {
            display: inline-flex;
            align-items: center;
            background-color: #28a745; /* Green */
            color: white;
            padding: 12px 25px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            transition: background-color 0.3s ease, transform 0.2s ease;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            margin-top: 15px;
        }
        .cta-button:hover {
            background-color: #218838;
            transform: translateY(-2px);
        }
        .cta-button i {
            margin-left: 10px;
        }

        /* Final Call to Action */
        .final-call-to-action {
            text-align: center;
            background-color: #e9f7ef; /* Very light green */
            padding: 30px;
            border-radius: 10px;
            margin-top: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }

        .final-call-to-action p {
            font-size: 1.2rem;
            color: #155724;
            margin-bottom: 15px;
        }

        .final-call-to-action p strong {
            color: #28a745;
        }

        /* Back to Homepage Button */
        .button-secondary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background-color: #6c757d; /* Gray */
            color: white;
            padding: 12px 25px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            transition: background-color 0.3s ease, transform 0.2s ease;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .button-secondary i {
            margin-right: 10px;
        }
        .button-secondary:hover {
            background-color: #5a6268;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0,0,0,0.15);
        }

        /* Footer */
        .page-footer {
            background-color: #343a40; /* Dark gray */
            color: #adb5bd;
            padding: 20px;
            text-align: center;
            font-size: 0.85em;
            border-top: 5px solid #212529;
            margin-top: 30px;
        }

        /* Blood Stock Checker */
        .blood-stock-checker {
            text-align: center;
            background-color: #fcfcfc;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            margin-bottom: 25px;
            border: 1px solid #e9ecef;
        }
        .blood-stock-checker h2 {
            color: #007bff; /* Blue for this section */
        }
        .blood-types-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        .blood-type-item {
            background-color: #f0f8ff; /* Light blue */
            border-radius: 8px;
            padding: 10px;
            border: 1px solid #d0e8ff;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .blood-type-name {
            font-weight: bold;
            font-size: 1.2rem;
            margin-bottom: 5px;
            color: #333;
        }
        .stock-level {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.8em;
            font-weight: bold;
            color: white;
            text-transform: uppercase;
        }
        /* Stock level colors */
        .stock-level.critical { background-color: #dc3545; animation: pulseRed 1.5s infinite; }
        .stock-level.low { background-color: #ffc107; color: #333; }
        .stock-level.medium { background-color: #17a2b8; }
        .stock-level.high { background-color: #28a745; }

        @keyframes pulseRed {
            0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7); }
            70% { transform: scale(1.05); box-shadow: 0 0 0 10px rgba(220, 53, 69, 0); }
            100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }
        }

        /* Blood Compatibility Chart */
        .blood-compatibility-chart {
            background-color: #fdfefe;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            overflow-x: auto; /* For small screens */
        }
        .blood-compatibility-chart h2 {
            color: #6f42c1; /* Purple accent */
        }
        .compatibility-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            min-width: 500px; /* Ensure table doesn't get too squished */
        }
        .compatibility-table th, .compatibility-table td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: center;
            font-size: 0.95em;
        }
        .compatibility-table th {
            background-color: #f2f2f2;
            color: #333;
        }
        .compatibility-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .compatibility-table .blood-type-col {
            font-weight: bold;
            color: #007bff;
        }
        .compatibility-table .can-receive { background-color: #d4edda; color: #155724; } /* Green */
        .compatibility-table .can-donate { background-color: #ffe6cc; color: #856404; } /* Orange */
        .compatibility-table .universal-donor { background-color: #b2e6b2; font-weight: bold; } /* Darker green */
        .compatibility-table .universal-recipient { background-color: #add8e6; font-weight: bold; } /* Light blue */

        /* FAQ Section */
        .faq-section {
            margin-top: 30px;
        }
        .faq-section h2 {
            color: #17a2b8; /* Teal accent */
        }
        .faq-item {
            background-color: #fcfdfe;
            border: 1px solid #e0f2f7;
            border-radius: 8px;
            margin-bottom: 15px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .faq-question {
            padding: 18px 20px;
            background-color: #eaf6fa; /* Light teal */
            color: #333;
            cursor: pointer;
            font-weight: bold;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background-color 0.3s ease;
        }
        .faq-question:hover {
            background-color: #d9edf7;
        }
        .faq-question .toggle-icon {
            font-size: 1.2em;
            transition: transform 0.3s ease;
        }
        .faq-answer {
            padding: 0 20px;
            background-color: #ffffff;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.4s ease-out, padding 0.4s ease-out;
            font-size: 0.95em;
            color: #555;
        }
        .faq-answer p {
            padding-bottom: 15px; /* Padding inside the answer when open */
        }
        .faq-item.active .faq-answer {
            max-height: 200px; /* Adjust as needed for content height */
            padding-top: 15px;
        }
        .faq-item.active .faq-question .toggle-icon {
            transform: rotate(180deg);
        }

        /* Donor Stories/Testimonials */
        .donor-stories-section {
            background-color: #f7fff7; /* Very light green */
            border-color: #d8f5d8;
        }
        .donor-stories-section h2 i {
            color: #28a745;
        }
        .story-card {
            background-color: #ffffff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .story-card p {
            font-style: italic;
            color: #555;
            margin-bottom: 10px;
        }
        .story-card .donor-name {
            font-weight: bold;
            color: #007bff;
            text-align: right;
            display: block;
        }

        /* Preparation & Post-Donation Tips */
        .tips-section {
            background-color: #fffaf0; /* Light orange */
            border-color: #ffe0b3;
        }
        .tips-section h2 i {
            color: #ff9800; /* Orange */
        }
        .tips-list {
            list-style: none;
            padding-left: 0;
        }
        .tips-list li {
            padding: 10px 0;
            border-bottom: 1px dashed #ffd580;
            color: #555;
            display: flex;
            align-items: flex-start;
        }
        .tips-list li:last-child {
            border-bottom: none;
        }
        .tips-list li i {
            margin-right: 10px;
            color: #ff9800; /* Orange icon */
            font-size: 1.1em;
            flex-shrink: 0;
        }


        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .blood-donation-container {
                margin: 20px auto;
                border-radius: 8px;
            }
            .page-header.blood-header {
                padding: 30px 15px;
            }
            .blood-header h1 {
                font-size: 2rem;
                flex-direction: column;
                gap: 5px;
            }
            .blood-header h1 .fas {
                font-size: 2.5rem;
            }
            .blood-header p {
                font-size: 1rem;
            }

            .content-wrapper {
                padding: 20px;
            }

            .info-section {
                padding: 20px;
                margin-bottom: 20px;
            }
            .info-section h2 {
                font-size: 1.6rem;
                flex-direction: column;
                gap: 5px;
                text-align: center;
            }
            .info-section h2 .fas {
                font-size: 1.2em;
            }

            .process-steps {
                grid-template-columns: 1fr;
            }
            .blood-types-grid {
                grid-template-columns: repeat(auto-fit, minmax(80px, 1fr));
            }
            .compatibility-table {
                min-width: 300px; /* Allow smaller for mobile but ensure scroll */
            }
            .compatibility-table th, .compatibility-table td {
                padding: 8px;
                font-size: 0.85em;
            }
        }
    </style>
</head>
<body>
    <div class="blood-donation-container">
        <header class="page-header blood-header">
            <h1><i class="fas fa-hand-holding-heart"></i> Give Blood, Save Lives</h1>
            <p>Learn about the importance of blood donation and how you can contribute in Bangladesh.</p>
        </header>

        <main class="content-wrapper">
            <section class="info-section" id="why-donate">
                <h2><i class="fas fa-question-circle"></i> Why is Blood Donation Important?</h2>
                <p>Blood is a lifeline, and its availability is crucial for various medical treatments and emergencies. Your single blood donation can save up to three lives. Donated blood is used for:</p>
                <ul>
                    <li>Patients undergoing surgery or suffering from trauma/accidents.</li>
                    <li>Cancer patients and individuals with blood disorders like Thalassemia.</li>
                    <li>Women experiencing complications during childbirth.</li>
                    <li>Manufacturing essential plasma-derived medications.</li>
                </ul>
                <p>Regular voluntary blood donation by healthy individuals is vital to ensure a safe and sufficient blood supply for those in need.</p>
            </section>

            <section class="info-section blood-stock-checker" id="blood-stock-status">
                <h2><i class="fas fa-chart-bar"></i> Current Blood Stock Needs (Simulated)</h2>
                <p>This shows a simulated overview of blood group needs. Your local blood bank might have different requirements.</p>
                <div class="blood-types-grid">
                    <?php foreach ($blood_stock_status as $type => $level): ?>
                        <div class="blood-type-item">
                            <div class="blood-type-name"><?= htmlspecialchars($type); ?></div>
                            <div class="stock-level <?= htmlspecialchars($level); ?>">
                                <?= htmlspecialchars(ucfirst($level)); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p class="disclaimer" style="font-size: 0.85em; margin-top: 15px;">
                    *This data is simulated and does not reflect real-time blood stock. Please contact a blood bank for accurate, up-to-date information.
                </p>
            </section>

            <section class="info-section blood-compatibility-chart" id="compatibility">
                <h2><i class="fas fa-syringe"></i> Blood Type Compatibility Chart</h2>
                <p>Understanding blood types and compatibility is essential for safe transfusions. This chart shows who can donate to whom, and who can receive from whom.</p>
                <table class="compatibility-table">
                    <thead>
                        <tr>
                            <th>Blood Type</th>
                            <th>Can Donate To</th>
                            <th>Can Receive From</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="blood-type-col">A+</td>
                            <td>A+, AB+</td>
                            <td>A+, A-, O+, O-</td>
                        </tr>
                        <tr>
                            <td class="blood-type-col">A-</td>
                            <td>A+, A-, AB+, AB-</td>
                            <td>A-, O-</td>
                        </tr>
                        <tr>
                            <td class="blood-type-col">B+</td>
                            <td>B+, AB+</td>
                            <td>B+, B-, O+, O-</td>
                        </tr>
                        <tr>
                            <td class="blood-type-col">B-</td>
                            <td>B+, B-, AB+, AB-</td>
                            <td>B-, O-</td>
                        </tr>
                        <tr>
                            <td class="blood-type-col universal-recipient">AB+</td>
                            <td>AB+</td>
                            <td>All Blood Types (Universal Recipient)</td>
                        </tr>
                        <tr>
                            <td class="blood-type-col">AB-</td>
                            <td>AB+, AB-</td>
                            <td>A-, B-, AB-, O-</td>
                        </tr>
                        <tr>
                            <td class="blood-type-col universal-donor">O+</td>
                            <td>O+, A+, B+, AB+</td>
                            <td>O+, O-</td>
                        </tr>
                        <tr>
                            <td class="blood-type-col universal-donor">O-</td>
                            <td>All Blood Types (Universal Donor)</td>
                            <td>O-</td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <section class="info-section" id="eligibility">
                <h2><i class="fas fa-user-check"></i> Who Can Donate Blood? (General Eligibility)</h2>
                <p>While specific criteria can vary slightly between blood banks, general eligibility guidelines in Bangladesh usually include:</p>
                <ul>
                    <li><strong>Age:</strong> Typically 18 to 60 years.</li>
                    <li><strong>Weight:</strong> Minimum of 45-50 kg (around 100-110 lbs).</li>
                    <li><strong>Health:</strong> Must be in good general health at the time of donation. Free from acute respiratory infections (cold, flu), fever, or any recent major illness.</li>
                    <li><strong>Hemoglobin:</strong> Minimum level is usually required (e.g., 12.0 g/dL for females, 12.5-13.0 g/dL for males). This will be checked at the donation center.</li>
                    <li><strong>Donation Interval:</strong> Usually every 3-4 months for whole blood donation.</li>
                </ul>
                <p class="disclaimer"><strong>Important:</strong> Always consult with the blood donation center for their specific eligibility criteria before donating. Certain medical conditions, medications, or recent travel history might temporarily or permanently defer you from donating.</p>
            </section>

            <section class="info-section tips-section" id="preparation-tips">
                <h2><i class="fas fa-hand-sparkles"></i> Essential Tips for Donors</h2>
                <ul class="tips-list">
                    <li><i class="fas fa-utensils"></i> **Before Donation:** Eat a healthy meal, avoid fatty foods.</li>
                    <li><i class="fas fa-glass-water"></i> **Before Donation:** Drink plenty of non-alcoholic fluids (water, juice).</li>
                    <li><i class="fas fa-moon"></i> **Before Donation:** Get a good night's sleep.</li>
                    <li><i class="fas fa-id-card"></i> **During Donation:** Bring a valid photo ID.</li>
                    <li><i class="fas fa-chair"></i> **After Donation:** Rest for 10-15 minutes and have refreshments.</li>
                    <li><i class="fas fa-dumbbell-bicep"></i> **After Donation:** Avoid strenuous physical activity or heavy lifting for at least 24 hours.</li>
                    <li><i class="fas fa-bandage"></i> **After Donation:** Keep the bandage on for several hours.</li>
                </ul>
            </section>

            <section class="info-section" id="process">
                <h2><i class="fas fa-spinner"></i> The Donation Process: What to Expect</h2>
                <div class="process-steps">
                    <div class="step">
                        <div class="step-icon"><i class="fas fa-clipboard-list"></i></div>
                        <h3>1. Registration</h3>
                        <p>You'll fill out a form with your details and medical history.</p>
                    </div>
                    <div class="step">
                        <div class="step-icon"><i class="fas fa-stethoscope"></i></div>
                        <h3>2. Mini-Health Check</h3>
                        <p>A quick check of your temperature, pulse, blood pressure, and hemoglobin level.</p>
                    </div>
                    <div class="step">
                        <div class="step-icon"><i class="fas fa-tint"></i></div>
                        <h3>3. Donation</h3>
                        <p>The actual blood draw usually takes about 8-12 minutes for whole blood.</p>
                    </div>
                    <div class="step">
                        <div class="step-icon"><i class="fas fa-mug-hot"></i></div>
                        <h3>4. Refreshments & Rest</h3>
                        <p>After donating, you'll rest for a short period and have some refreshments.</p>
                    </div>
                </div>
                <p>The entire process usually takes about an hour. Remember to eat well and hydrate before donating, and avoid strenuous activity immediately after.</p>
            </section>

            <section class="info-section" id="where-to-donate">
                <h2><i class="fas fa-hospital"></i> Where to Donate Blood in Bangladesh</h2>
                <p>There are several reputable organizations and hospital-based blood banks where you can donate blood in Bangladesh. Some include:</p>
                <ul class="organizations-list">
                    <?php foreach ($blood_donation_organizations_bd as $org): ?>
                        <li>
                            <strong><?= htmlspecialchars($org['name']); ?></strong>
                            <?php if (!empty($org['website'])): ?>
                                - <a href="<?= htmlspecialchars($org['website']); ?>" target="_blank" rel="noopener noreferrer">Visit Website</a>
                            <?php endif; ?>
                            <?php if (!empty($org['notes'])): ?>
                                <p class="org-notes"><?= htmlspecialchars($org['notes']); ?></p>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p>You can also often find blood donation camps organized in your community, university, or workplace. Look out for announcements or contact these organizations for information on nearby donation centers or drives.</p>
            </section>

            <section class="info-section donor-stories-section" id="donor-stories">
                <h2><i class="fas fa-heart"></i> Inspiring Donor Stories</h2>
                <div class="story-card">
                    <p>"I've been donating blood for over 10 years. It's a small act that makes a huge difference. Knowing my donation can save a life motivates me every time."</p>
                    <span class="donor-name">- Ayesha Begum, Dedicated Donor</span>
                </div>
                <div class="story-card">
                    <p>"My cousin needed a blood transfusion urgently. I saw firsthand the critical need. Since then, I've become a regular donor, hoping to help others as my family was helped."</p>
                    <span class="donor-name">- Rahim Khan, Grateful Donor</span>
                </div>
                <p style="text-align:center; margin-top: 15px; font-style: italic; color: #777;">
                    (These are illustrative stories. Real testimonials would be collected with consent.)
                </p>
            </section>

            <section class="info-section" id="clinic-involvement">
                <h2><i class="fas fa-clinic-medical"></i> Our Clinic & Blood Donation</h2>
                <p><?= htmlspecialchars($clinic_blood_drive_info); ?></p>
                <?php if (isset($clinic_donor_registry_link)): ?>
                    <p><a href="<?= htmlspecialchars($clinic_donor_registry_link); ?>" class="cta-button">Join Our Donor Registry <i class="fas fa-arrow-right"></i></a></p>
                <?php endif; ?>
                <p>We encourage all eligible individuals in our community to become regular blood donors. Your contribution is invaluable.</p>
            </section>

            <div class="final-call-to-action">
                <p><strong>Be a hero. Your blood can give someone a second chance at life.</strong></p>
                <p>If you have questions or need more information, please contact one of the blood banks listed above or consult with our medical staff during your next visit.</p>
            </div>

            <p style="text-align:center; margin-top:30px;">
                <a href="index.php" class="button-secondary"><i class="fas fa-home"></i> Back to Homepage</a>
            </p>
        </main>

        <footer class="page-footer">
            <p>&copy; <?= date("Y"); ?> <?= htmlspecialchars($clinic_name_for_page); ?>. Promoting Health, Saving Lives.</p>
            <?php
            // Removed potentially unwanted external scripts for performance and privacy
            // <script type='text/javascript' src='//pl27012931.profitableratecpm.com/23/01/9e/23019e8e62b0d680b7c22119518abe76.js'></script>
            // <script async="async" data-cfasync="false" src="//pl27013164.profitableratecpm.com/518b5bf3a8f610d01ac4771c391ef67d/invoke.js"></script>
            ?>
        </footer>
    </div>

    <script>
        // JavaScript for FAQ Accordion
        document.addEventListener('DOMContentLoaded', () => {
            const faqQuestions = document.querySelectorAll('.faq-question');

            faqQuestions.forEach(question => {
                question.addEventListener('click', () => {
                    const faqItem = question.closest('.faq-item');
                    faqItem.classList.toggle('active');

                    const answer = faqItem.querySelector('.faq-answer');
                    if (faqItem.classList.contains('active')) {
                        answer.style.maxHeight = answer.scrollHeight + "px"; // Set max-height to natural height
                    } else {
                        answer.style.maxHeight = "0"; // Collapse
                    }
                });
            });
        });
    </script>
</body>
</html>