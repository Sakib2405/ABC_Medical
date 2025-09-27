<?php
// --- SIMULATED PRESCRIPTION DATABASE ---
// In a real application, connect to your database and fetch details based on the ID.
$detailed_prescriptions = [
    701 => [
        'id' => 701,
        'patient_name' => 'John Doe',
        'patient_id' => 'P001', // Example patient identifier
        'doctor_name' => 'Dr. Smith',
        'doctor_id' => 'D025', // Example doctor identifier
        'clinic_name' => 'City Health Clinic',
        'date_issued' => '2025-05-20',
        'medication_name' => 'Amoxicillin',
        'strength_dosage' => '250mg', // e.g., 250mg, 10ml
        'form' => 'Capsule', // e.g., Tablet, Capsule, Syrup, Ointment
        'route' => 'Oral', // e.g., Oral, Topical, Inhalation
        'frequency' => 'One capsule every 8 hours',
        'duration' => '7 days',
        'quantity_prescribed' => '21 capsules',
        'refills_allowed' => 0,
        'instructions_patient' => 'Take with a full glass of water. Complete the entire course even if you feel better.',
        'pharmacy_notes' => 'Generic substitution permitted.', // Notes for the pharmacist
        'status' => 'Active'
    ],
    702 => [
        'id' => 702,
        'patient_name' => 'Jane Alam',
        'patient_id' => 'P002',
        'doctor_name' => 'Dr. Eva Rahman',
        'doctor_id' => 'D030',
        'clinic_name' => 'Central Medical Center',
        'date_issued' => '2025-05-15',
        'medication_name' => 'Paracetamol',
        'strength_dosage' => '500mg',
        'form' => 'Tablet',
        'route' => 'Oral',
        'frequency' => '1-2 tablets every 4-6 hours as needed for pain or fever',
        'duration' => 'As needed',
        'quantity_prescribed' => '30 tablets',
        'refills_allowed' => 1,
        'instructions_patient' => 'Do not exceed 8 tablets in 24 hours. If symptoms persist, consult the doctor.',
        'pharmacy_notes' => '',
        'status' => 'Completed'
    ],
    // Add more detailed sample prescriptions if you like
];

// --- GET PRESCRIPTION ID FROM URL ---
$prescription_id = null;
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $prescription_id = (int)$_GET['id'];
}

// --- FETCH THE SPECIFIC PRESCRIPTION DETAILS ---
$prescription_detail = null;
if ($prescription_id && isset($detailed_prescriptions[$prescription_id])) {
    $prescription_detail = $detailed_prescriptions[$prescription_id];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prescription Details</title>
    <link rel="stylesheet" href="view_prescription_detail.css"> </head>
<body>
    <div class="container">
        <h1>Prescription Detail</h1>

        <?php if ($prescription_detail): ?>
            <div class="prescription-detail-card">
                <div class="card-header">
                    <h2>Prescription #<?= htmlspecialchars($prescription_detail['id']); ?></h2>
                    <span class="status status-<?= strtolower(htmlspecialchars($prescription_detail['status'])); ?>"><?= htmlspecialchars($prescription_detail['status']); ?></span>
                </div>

                <div class="detail-section">
                    <h3>Patient & Provider Information</h3>
                    <p><span class="label">Patient Name:</span> <?= htmlspecialchars($prescription_detail['patient_name']); ?> (ID: <?= htmlspecialchars($prescription_detail['patient_id']); ?>)</p>
                    <p><span class="label">Issuing Doctor:</span> <?= htmlspecialchars($prescription_detail['doctor_name']); ?> (ID: <?= htmlspecialchars($prescription_detail['doctor_id']); ?>)</p>
                    <p><span class="label">Clinic:</span> <?= htmlspecialchars($prescription_detail['clinic_name']); ?></p>
                    <p><span class="label">Date Issued:</span> <?= htmlspecialchars($prescription_detail['date_issued']); ?></p>
                </div>

                <div class="detail-section">
                    <h3>Medication Details</h3>
                    <p><span class="label">Medication:</span> <?= htmlspecialchars($prescription_detail['medication_name']); ?></p>
                    <p><span class="label">Strength/Dosage:</span> <?= htmlspecialchars($prescription_detail['strength_dosage']); ?></p>
                    <p><span class="label">Form:</span> <?= htmlspecialchars($prescription_detail['form']); ?></p>
                    <p><span class="label">Route:</span> <?= htmlspecialchars($prescription_detail['route']); ?></p>
                    <p><span class="label">Frequency:</span> <?= htmlspecialchars($prescription_detail['frequency']); ?></p>
                    <p><span class="label">Duration:</span> <?= htmlspecialchars($prescription_detail['duration']); ?></p>
                    <p><span class="label">Quantity Prescribed:</span> <?= htmlspecialchars($prescription_detail['quantity_prescribed']); ?></p>
                    <p><span class="label">Refills Allowed:</span> <?= htmlspecialchars($prescription_detail['refills_allowed']); ?></p>
                </div>

                <div class="detail-section">
                    <h3>Instructions & Notes</h3>
                    <p><span class="label">Instructions for Patient:</span></p>
                    <div class="instructions"><?= nl2br(htmlspecialchars($prescription_detail['instructions_patient'])); ?></div>

                    <?php if (!empty($prescription_detail['pharmacy_notes'])): ?>
                        <p><span class="label">Pharmacy Notes:</span></p>
                        <div class="instructions"><?= nl2br(htmlspecialchars($prescription_detail['pharmacy_notes'])); ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <p style="margin-top:20px;"><a href="prescriptions.php">Back to Prescriptions List</a></p>

        <?php elseif ($prescription_id): ?>
            <p class="error-message">Prescription with ID <?= htmlspecialchars($prescription_id); ?> not found.</p>
            <p><a href="prescriptions.php">Back to Prescriptions List</a></p>
        <?php else: ?>
            <p class="error-message">No prescription ID provided or invalid ID.</p>
            <p>Please select a prescription from the <a href="prescriptions.php">prescriptions list</a> to view its details.</p>
            <p>Example: <a href="?id=701">View Sample Prescription 701</a></p>
        <?php endif; ?>

    </div>
</body>
</html>