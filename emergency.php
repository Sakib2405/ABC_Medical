<?php
// No session_start() needed unless you manage login state on this specific page,
// which is usually not the case for a public emergency info page.
// If you're including a header/footer that uses sessions, then it's fine to keep.

$page_title = "Emergency Information - ABC Medical Clinic";
$clinic_name_for_emergency_page = "ABC Medical Clinic"; // Could be fetched from site_settings

// Clinic's own emergency contact (IF AVAILABLE AND APPROPRIATE)
// **IMPORTANT**: Only fill this if your clinic offers a genuine, monitored emergency line.
// Otherwise, set $clinic_emergency_phone to empty or remove this section.
$clinic_emergency_phone = ""; // e.g., "+880 17XX-XXXXXX" or "+880 96XX-XXXXXX"
$clinic_emergency_notes = "For urgent matters concerning existing patients during clinic hours, you may call this number. For all life-threatening emergencies, please call 999 or go to the nearest hospital.";

// If no dedicated clinic emergency line, this note should clearly state that.
// Example for no dedicated clinic line:
// $clinic_emergency_notes = "Our clinic does not provide 24/7 emergency services. In any emergency, please call 999 or visit the nearest hospital emergency department.";

// National Emergency Numbers for Bangladesh
$national_emergency_number = "999";
$health_helpline_dgHS = "16263"; // DGHS Health Helpline
$govt_info_service = "333";    // Government Information Service (can sometimes assist)

// Example: Major Hospitals in Patuakhali (for guidance - users should verify nearest)
// IMPORTANT: Replace these with actual, verified hospitals in Patuakhali.
$sample_hospitals_patuakhali = [
    "Patuakhali Medical College Hospital - Emergency",
    "Patuakhali General Hospital - Emergency",
    "Upazila Health Complex, Patuakhali Sadar",
    // Add more local hospitals here as needed
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
        /* General Body Styles */
        body {
            font-family: 'Roboto', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f0f4f8; /* Light background for clarity */
            color: #333;
            line-height: 1.6;
        }

        /* Container for the entire page content */
        .emergency-page-container {
            max-width: 900px;
            margin: 30px auto;
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            overflow: hidden; /* For rounded corners */
        }

        /* Header Section */
        .emergency-header {
            background-color: #dc3545; /* Emergency red */
            color: white;
            padding: 30px 20px;
            text-align: center;
            border-bottom: 5px solid #bd2130; /* Darker red border */
        }

        .emergency-icon-header {
            font-size: 4rem;
            margin-bottom: 15px;
            animation: pulseIcon 2s infinite ease-in-out; /* Subtle pulsing */
        }

        @keyframes pulseIcon {
            0% { transform: scale(1); text-shadow: none; }
            50% { transform: scale(1.05); text-shadow: 0 0 15px rgba(255,255,255,0.7); }
            100% { transform: scale(1); text-shadow: none; }
        }

        .emergency-header h1 {
            font-size: 2.5rem;
            margin: 0 0 10px;
            font-weight: 700;
        }

        .emergency-header p {
            font-size: 1.1rem;
            margin: 0;
        }

        /* Main Content Area */
        .emergency-content {
            padding: 30px;
        }

        /* Individual Sections */
        .emergency-section {
            background-color: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .emergency-section:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }

        .emergency-section h2 {
            font-size: 1.8rem;
            color: #2c3e50; /* Dark blue-gray */
            margin-top: 0;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .emergency-section h2 i {
            font-size: 1.5em;
            color: #007bff; /* Primary blue for section icons */
        }

        /* Critical Warning Section */
        .critical-warning {
            background-color: #ffebe6; /* Very light red */
            border-color: #faacac; /* Light red border */
            color: #721c24; /* Dark red text */
            font-size: 1.1em;
            font-weight: bold;
            text-align: center;
            position: relative;
            animation: fadeIn 0.5s ease-out;
        }
        .critical-warning h2 {
            color: #dc3545; /* Emergency red */
            justify-content: center;
        }
        .critical-warning h2 i {
            color: #dc3545; /* Match heading color */
        }
        .critical-warning p strong {
            font-size: 1.5em; /* Make phone number stand out */
            color: #dc3545;
            display: block;
            margin: 10px 0;
        }
        .critical-warning p strong a {
            color: #dc3545;
            text-decoration: none;
            transition: color 0.3s ease;
        }
        .critical-warning p strong a:hover {
            color: #a71d2a;
        }

        /* National Helplines Grid */
        .helpline-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .helpline-item {
            background-color: #f8fafd; /* Very light blue */
            border: 1px solid #d1e3ff; /* Light blue border */
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .helpline-item:hover {
            transform: translateY(-5px) scale(1.02);
            box-shadow: 0 6px 15px rgba(0,0,0,0.1);
        }

        .helpline-item .icon {
            font-size: 2.5rem;
            color: #007bff; /* Primary blue */
            margin-bottom: 15px;
        }

        .helpline-item h3 {
            font-size: 1.2rem;
            color: #34495e; /* Darker text */
            margin-top: 0;
            margin-bottom: 10px;
        }

        .helpline-item .phone-number {
            font-size: 1.6rem;
            font-weight: bold;
            color: #007bff;
            margin: 0 0 10px;
        }
        .helpline-item .phone-number a {
            color: #007bff;
            text-decoration: none;
            transition: color 0.3s ease;
        }
        .helpline-item .phone-number a:hover {
            color: #0056b3;
        }
        .helpline-item p {
            font-size: 0.9em;
            color: #6c757d;
        }

        /* Clinic Specific Emergency */
        .clinic-specific-emergency {
            background-color: #e6f7ff; /* Lighter blue */
            border-color: #b3d9ff;
            color: #004085;
            position: relative;
        }
        .clinic-specific-emergency h2 i {
             color: #28a745; /* Green for clinic medical icon */
        }
        .clinic-specific-emergency .clinic-emergency-phone {
            font-size: 1.4em;
            font-weight: bold;
            color: #28a745; /* Green for clinic phone */
            margin: 10px 0;
        }
        .clinic-specific-emergency .clinic-emergency-phone a {
            color: #28a745;
            text-decoration: none;
            transition: color 0.3s ease;
        }
        .clinic-specific-emergency .clinic-emergency-phone a:hover {
            color: #1e7e34;
        }
        .clinic-specific-emergency .notes {
            font-style: italic;
            color: #5a6268;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px dashed #cce5ff;
        }

        /* Hospital Info Section */
        .hospital-info ul {
            list-style: none;
            padding: 0;
        }
        .hospital-info ul li {
            background-color: #fcfcfc;
            border-left: 4px solid #007bff;
            padding: 10px 15px;
            margin-bottom: 8px;
            border-radius: 5px;
            font-weight: 500;
            color: #495057;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .hospital-info h2 i {
            color: #6f42c1; /* Purple for hospital icon */
        }

        /* Preparation Section */
        .preparation ul {
            list-style: disc;
            padding-left: 25px;
        }
        .preparation ul li {
            margin-bottom: 8px;
            color: #555;
        }
        .preparation h2 i {
            color: #ffc107; /* Yellow for info icon */
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

        /* Footer Section */
        .emergency-page-footer {
            background-color: #343a40; /* Dark gray */
            color: #adb5bd; /* Light gray text */
            padding: 20px;
            text-align: center;
            font-size: 0.85em;
            border-top: 5px solid #212529; /* Very dark gray border */
        }
        .emergency-page-footer p {
            margin: 5px 0;
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .emergency-page-container {
                margin: 15px;
                border-radius: 8px;
            }

            .emergency-header h1 {
                font-size: 2rem;
            }
            .emergency-icon-header {
                font-size: 3rem;
            }

            .emergency-content {
                padding: 20px;
            }

            .emergency-section {
                padding: 20px;
                margin-bottom: 20px;
            }
            .emergency-section h2 {
                font-size: 1.5rem;
                flex-direction: column; /* Stack icon and text on small screens */
                gap: 5px;
                text-align: center;
            }
            .emergency-section h2 i {
                font-size: 1.2em; /* Adjust icon size */
            }

            .helpline-grid {
                grid-template-columns: 1fr; /* Stack helpline items */
            }

            .critical-warning p strong {
                font-size: 1.3em;
            }

            .button-secondary {
                padding: 10px 20px;
                font-size: 0.9em;
            }
        }

        /* Removed external scripts as they often cause performance/privacy issues */
        /* .emergency-page-footer script, .emergency-page-footer div { display: none; } */

    </style>
</head>
<body>
    <div class="emergency-page-container">
        <header class="emergency-header">
            <i class="fas fa-exclamation-triangle emergency-icon-header"></i>
            <h1>Urgent & Emergency Situations</h1>
            <p>Know what to do and who to call when you need immediate help.</p>
        </header>

        <main class="emergency-content">
            <section class="emergency-section critical-warning">
                <h2><i class="fas fa-skull-crossbones"></i> If This Is a Life-Threatening Emergency:</h2>
                <p><strong>CALL <a href="tel:<?= htmlspecialchars($national_emergency_number); ?>"><?= htmlspecialchars($national_emergency_number); ?></a> IMMEDIATELY</strong> or go to the nearest hospital Emergency Room.</p>
                <p>Do not rely on information from this website or attempt to contact our clinic first for life-threatening conditions such as severe bleeding, difficulty breathing, chest pain, loss of consciousness, severe allergic reactions, or major trauma.</p>
            </section>

            <section class="emergency-section national-helplines">
                <h2><i class="fas fa-ambulance"></i> National Emergency Numbers (Bangladesh)</h2>
                <div class="helpline-grid">
                    <div class="helpline-item">
                        <i class="fas fa-phone-volume icon"></i>
                        <h3>National Emergency Service</h3>
                        <p class="phone-number"><a href="tel:<?= htmlspecialchars($national_emergency_number); ?>"><?= htmlspecialchars($national_emergency_number); ?></a></p>
                        <p>(Police, Ambulance, Fire Service)</p>
                    </div>
                    <div class="helpline-item">
                        <i class="fas fa-heartbeat icon"></i>
                        <h3>DGHS Health Helpline</h3>
                        <p class="phone-number"><a href="tel:<?= htmlspecialchars($health_helpline_dgHS); ?>"><?= htmlspecialchars($health_helpline_dgHS); ?></a></p>
                        <p>(Directorate General of Health Services)</p>
                    </div>
                    <div class="helpline-item">
                        <i class="fas fa-info-circle icon"></i>
                        <h3>Government Info Service</h3>
                        <p class="phone-number"><a href="tel:<?= htmlspecialchars($govt_info_service); ?>"><?= htmlspecialchars($govt_info_service); ?></a></p>
                        <p>(For general information, can sometimes assist)</p>
                    </div>
                </div>
            </section>

            <?php if (!empty($clinic_emergency_phone) || !empty($clinic_emergency_notes) ): ?>
            <section class="emergency-section clinic-specific-emergency">
                <h2><i class="fas fa-clinic-medical"></i> <?= htmlspecialchars($clinic_name_for_emergency_page); ?> - Urgent Contact</h2>
                <?php if (!empty($clinic_emergency_phone)): ?>
                    <p>For urgent matters that are NOT life-threatening, existing patients may contact us at:</p>
                    <p class="clinic-emergency-phone"><a href="tel:<?= htmlspecialchars($clinic_emergency_phone); ?>"><?= htmlspecialchars($clinic_emergency_phone); ?></a></p>
                <?php endif; ?>
                <p class="notes"><?= nl2br(htmlspecialchars($clinic_emergency_notes)); ?></p>
            </section>
            <?php endif; ?>

            <section class="emergency-section hospital-info">
                <h2><i class="fas fa-hospital"></i> Nearby Hospital Emergency Rooms (Examples in Patuakhali)</h2>
                <p>In a serious emergency, proceed to the nearest hospital with an emergency department. Here are some major hospitals in Patuakhali (please verify their current services and your nearest option):</p>
                <ul>
                    <?php foreach($sample_hospitals_patuakhali as $hospital): ?>
                        <li><?= htmlspecialchars($hospital); ?></li>
                    <?php endforeach; ?>
                </ul>
                <p><strong>Always confirm the best option for your specific location and situation.</strong></p>
            </section>

            <section class="emergency-section preparation">
                <h2><i class="fas fa-info-circle"></i> When Calling for Help, Be Prepared to Provide:</h2>
                <ul>
                    <li>Your exact location or the patient's location.</li>
                    <li>Your phone number.</li>
                    <li>What happened / The nature of the emergency.</li>
                    <li>How many people are hurt (if applicable).</li>
                    <li>The patient's approximate age, sex, and any known medical conditions.</li>
                    <li>Any immediate first aid already given.</li>
                </ul>
                <p>Stay on the line with the operator and follow their instructions.</p>
            </section>

             <p style="text-align:center; margin-top:30px;">
                <a href="index.php" class="button-secondary"><i class="fas fa-home"></i> Back to Homepage</a>
            </p>
        </main>

        <footer class="emergency-page-footer">
            <p>This page provides general guidance. Always prioritize immediate professional medical help in an emergency.</p>
            <p>&copy; <?= date("Y"); ?> <?= htmlspecialchars($clinic_name_for_emergency_page); ?></p>
            <?php
            // Removed potentially unwanted external scripts/divs for performance and security
            // <script type='text/javascript' src='//pl27022957.profitableratecpm.com/df/f6/6f/dff66f651ce6a7255f2a34b68a269ff8.js'></script>
            // https://www.profitableratecpm.com/yjv9z6i5?key=bdbf3f2cfdbeb88da28d9927dd0361ad
            // <div id="container-518b5bf3a8f610d01ac4771c391ef67d"></div>
            ?>
        </footer>
    </div>
</body>
</html>