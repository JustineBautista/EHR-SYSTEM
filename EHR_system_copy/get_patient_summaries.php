<?php
// Start session and check authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

include "db.php";

header('Content-Type: application/json');

// Get all patients
$patients_query = "SELECT id, fullname, dob, age, gender, contact, address, history FROM patients ORDER BY fullname";
$patients_result = $conn->query($patients_query);

$patients = [];

if ($patients_result && $patients_result->num_rows > 0) {
    while ($patient = $patients_result->fetch_assoc()) {
        $patient_id = $patient['id'];

        // Get medical records for each patient
        $medical_records = [];

        $tables = [
            'physical_assessments' => ['assessed_by', 'head_and_neck', 'cardiovascular', 'respiratory', 'Abdominal', 'neurological', 'musculoskeletal', 'skin', 'psychiatric', 'date_assessed'],
            'medical_history' => ['condition_name', 'status', 'notes', 'date_recorded'],
            'medications' => ['medication', 'indication', 'prescriber', 'dose', 'status', 'route', 'start_date', 'notes', 'patient_instructions', 'pharmacy_instructions'],
            'vitals' => ['recorded_by', 'bp', 'respiratory_rate', 'hr', 'temp', 'height', 'weight', 'oxygen_saturation', 'pain_scale', 'date_taken', 'general_appearance'],
            'diagnostics' => ['study_type', 'body_part_region', 'study_description', 'clinical_indication', 'image_quality', 'order_by', 'performed_by', 'interpreted_by', 'imaging_facility', 'radiology_findings', 'impression_conclusion', 'recommendations', 'date_diagnosed'],
            'treatment_plans' => ['plan', 'intervention', 'problems', 'frequency', 'duration', 'order_by', 'assigned_to', 'date_started', 'date_ended', 'special_instructions', 'patient_education_provided'],
            'lab_results' => ['test_name', 'test_result', 'test_category', 'test_code', 'result_status', 'units', 'reference_range', 'order_by', 'collected_by','date_taken'],
            'progress_notes' => ['focus', 'note', 'author', 'date_written']
        ];

        foreach ($tables as $table => $fields) {
            $stmt = $conn->prepare("SELECT " . implode(',', $fields) . " FROM `$table` WHERE patient_id = ? ORDER BY id DESC");
            if ($stmt) {
                $stmt->bind_param("i", $patient_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $medical_records[$table] = [];

                while ($row = $result->fetch_assoc()) {
                    $medical_records[$table][] = $row;
                }
                $stmt->close();
            }
        }

        $patient['medical_records'] = $medical_records;
        $patients[] = $patient;
    }
}

echo json_encode($patients);
?>
