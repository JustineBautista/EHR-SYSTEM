<?php
echo '<link rel="icon" href="IMAGES/aurora.png" type="image/png">';
?>
<?php
// Start session and check authentication first
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin'])) {
    header("Location: index.php");
    exit();
}

// Include required files AFTER session check
include "db.php";
include "audit_trail.php";

// Function to sanitize input
function sanitize_input($conn, $data) {
    return mysqli_real_escape_string($conn, trim($data));
}

$page_title = "Patient Dashboard";

// Get patient ID from URL
$patient_id = intval($_GET['patient_id'] ?? 0);
if ($patient_id <= 0) {
    header("Location: patients.php");
    exit();
}

// Get search term from URL
$search = sanitize_input($conn, $_GET['search'] ?? '');
$search_param = "%$search%";

// Fetch patient details
$patient = null;
$stmt = $conn->prepare("SELECT * FROM patients WHERE id = ?");
if ($stmt && $stmt->bind_param("i", $patient_id) && $stmt->execute()) {
    $res = $stmt->get_result();
    $patient = $res->fetch_assoc();
    $stmt->close();
}

if (!$patient) {
    header("Location: patients.php");
    exit();
}

// Fetch additional medical data with prepared statements
$medical_data = [];
$tables = ['medical_history', 'medications', 'vitals', 'diagnostics', 'treatment_plans', 'progress_notes', 'lab_results', 'physical_assessments'];


$table_fields = [
    'medical_history' => ['condition_name', 'status', 'notes'],
    'medications' => ['medication', 'indication', 'prescriber', 'dose', 'status', 'route', 'notes'],
    'vitals' => ['recorded_by', 'focus', 'bp','respiratory_rate', 'hr', 'temp', 'height', 'weight', 'oxygen_saturation', 'pain_scale', 'general_appearance'],
    'diagnostics' => ['study_type', 'body_part_region', 'study_description', 'clinical_indication', 'image_quality', 'order_by', 'performed_by', 'Interpreted_by', 'Imaging_facility'],
    'treatment_plans' => ['plan', 'intervention', 'problems', 'frequency', 'duration', 'order_by', 'assigned_to', 'date_started', 'date_ended', 'special_instructions', 'patient_education_provided'],
    'progress_notes' => ['focus', 'note', 'author'],
    'lab_results' => ['test_name', 'test_result', 'test_category', 'test_code', 'test_result', 'result_status', 'units', 'reference_range', 'order_by', 'collected_by', 'laboratory_facility', 'clinical_interpretation' ],
    'physical_assessments' => ['assessed_by', 'head_and_neck', 'cardiovascular', 'respiratory', 'abdominal', 'neurological', 'musculoskeletal', 'skin', 'psychiatric']
];

foreach ($tables as $table) {
    $fields = $table_fields[$table];
    $like_conditions = [];
    $params = [$patient_id];
    $types = 'i';
    if (!empty($search)) {
        foreach ($fields as $field) {
            $like_conditions[] = "$field LIKE ?";
            $params[] = $search_param;
            $types .= 's';
        }
    }
    $query = "SELECT * FROM `$table` WHERE patient_id = ?";
    if (!empty($search)) {
        $query .= " AND (" . implode(' OR ', $like_conditions) . ")";
    }
    $query .= " ORDER BY id DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    if ($stmt && $stmt->execute()) {
        $result = $stmt->get_result();
        $medical_data[$table] = [];
        while ($row = $result->fetch_assoc()) {
            $medical_data[$table][] = $row;
        }
        $stmt->close();
    }
}

// Vitals processing (adapted from vitals.php)
$msg = "";
$error = "";

if (isset($_POST['add_vitals'])) {
    $recorded_by = sanitize_input($conn, $_POST['recorded_by'] ?? "");
    $bp = $_POST['bp'] ?? "";
    $respiratory_rate = $_POST['respiratory_rate'] ?? "";
    $hr = $_POST['hr'] ?? "";
    $temp = $_POST['temp'] ?? "";
    $height = $_POST['height'] ?? "";
    $weight = $_POST['weight'] ?? "";
    $oxygen_saturation = $_POST['oxygen_saturation'] ?? "";
    $pain_scale = $_POST['pain_scale'] ?? "";
    $general_appearance = $_POST['general_appearance'] ?? "";
    $date = $_POST['date'] ?: date("Y-m-d");

    // Validate blood pressure format (systolic/diastolic)
    if (!empty($_POST['date']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $error = "Blood pressure must be in format 'systolic/diastolic' (e.g., 120/80)";
    }
    elseif (empty($oxygen_saturation)){
        $msg = "Oxygen Saturation is requird";
    }
    elseif (empty($pain_scale)){
        $msg = "Pain Scale is requird";
    }
    elseif (empty($general_appearance)){
        $msg = "General appearance is requird";
    }
    else {
        $stmt = $conn->prepare("INSERT INTO vitals (patient_id, recorded_by, bp, respiratory_rate, hr, temp, height, weight, oxygen_saturation, pain_scale, general_appearance, date_taken) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param("isssssssiiis", $patient_id, $recorded_by, $bp, $respiratory_rate, $hr, $temp, $height, $weight, $oxygen_saturation, $pain_scale, $general_appearance, $date);
        if ($stmt->execute()) {
            $msg = "Vitals recorded.";
            // Refresh medical_data for vitals
            $stmt = $conn->prepare("SELECT * FROM vitals WHERE patient_id = ? ORDER BY id DESC");
            $stmt->bind_param("i", $patient_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $medical_data['vitals'] = [];
            while ($row = $result->fetch_assoc()) {
                $medical_data['vitals'][] = $row;
            }
            $stmt->close();
        } else {
            $error = "Database error: " . $stmt->error;
        }
    }
}

if (isset($_GET['delete_vital'])) {
    $id = intval($_GET['delete_vital']);
    $stmt = $conn->prepare("DELETE FROM vitals WHERE id=? AND patient_id=?");
    $stmt->bind_param("ii", $id, $patient_id);
    if ($stmt->execute()) {
        header("Location: patient_dashboard.php?patient_id=$patient_id&section=vitals");
        exit();
    }
    $stmt->close();
}
if (isset($_GET['get_vital'])) {
    $id = intval($_GET['get_vital']);
    $stmt = $conn->prepare("SELECT * FROM vitals WHERE id=? AND patient_id=?");
    $stmt->bind_param("ii", $id, $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        echo json_encode($row);
    }
    $stmt->close();
    exit;
}


// Handle update vitals
if (isset($_POST['update_vitals'])) {
    $vid = intval($_POST['vital_id']);
    $recorded_by = $_POST['recorded_by'] ?? "";
    $bp = $_POST['bp'] ?? "";
    $respiratory_rate = $_POST['respiratory_rate'] ?? "";
    $hr = $_POST['hr'] ?? "";
    $temp = $_POST['temp'] ?? "";
    $height = $_POST['height'] ?? "";
    $weight = $_POST['weight'] ?? "";
    $oxygen_saturation = $_POST['oxygen_saturation'] ?? "";
    $pain_scale = $_POST['pain_scale'] ?? "";
    $general_appearance = $_POST['general_appearance'] ?? "";
    $date = $_POST['date'] ?: date("Y-m-d");

    // Validate (same as add)
    if (!empty($_POST['date']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $error = "Blood pressure must be in format 'systolic/diastolic' (e.g., 120/80)";
    }
    else {
        $stmt = $conn->prepare("UPDATE vitals SET recorded_by=?, bp=?, respiratory_rate=?, hr=?, temp=?, height=?, weight=?, oxygen_saturation=?, pain_scale=?, general_appearance=?, date_taken=? WHERE id=? AND patient_id=?");
        $stmt->bind_param("sssddddiissii",$recorded_by, $bp, $respiratory_rate, $hr, $temp, $height, $weight, $oxygen_saturation, $pain_scale, $general_appearance, $date, $vid, $patient_id);
        if ($stmt->execute()) {
            $msg = "Vitals updated.";
        } else {
            $error = "Database error: " . $stmt->error;
        }
        $stmt->close();
        // Refresh vitals data
        $stmt = $conn->prepare("SELECT * FROM vitals WHERE patient_id = ? ORDER BY id DESC");
        $stmt->bind_param("i", $patient_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $medical_data['vitals'] = [];
        while ($row = $result->fetch_assoc()) {
            $medical_data['vitals'][] = $row;
        }
        $stmt->close();
    }
}




// Medications processing (adapted from medications.php)
if (isset($_POST['add_med'])) {
    $med = $_POST['medication'] ?? "";
    $indication = $_POST['indication'] ?? "";
    $prescriber = $_POST['prescriber'] ?? "";
    $dose = $_POST['dose'] ?? "";
    $route = $_POST['route'] ?? "";
    if ($route === 'other') {
        $route = $_POST['custom_route'] ?? "";
    }
    $start = $_POST['start_date'] ?? "";
    $notes = $_POST['notes'] ?? "";
    $status = $_POST['status'] ?? "";
    $patient_instructions = $_POST['patient_instructions'] ?? "";
    $pharmacy_instructions = $_POST['pharmacy_instructions'] ?? "";

    // Validate all fields (required)
    if (empty($med)) {
        $error = "Medication is required.";
    }
    elseif (empty($indication)) {
        $error = "Indication is required.";
    }
    elseif (empty($prescriber)) {
        $error = "Prescriber is required.";
    }
    elseif (empty($dose)) {
        $error = "Dose is required.";
    }
    elseif (empty($route)) {
        $error = "Route is required.";
    }
    elseif (empty($start)) {
        $error = "Start Date is required.";
    }
    elseif (empty($notes)) {
        $error = "Notes is required.";
    }
    elseif (empty($status)) {
        $error = "Status is required.";
    }
    elseif (empty($patient_instructions)) {
        $error = "patient instructions is required.";
    }
    elseif (empty($pharmacy_instructions)) {
        $error = "pharmacy instructions is required.";
    }
    else {
        $stmt = $conn->prepare("INSERT INTO medications (patient_id, medication, indication, prescriber, dose, route, start_date, notes, status, patient_instructions, pharmacy_instructions) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param("issssssssss", $patient_id, $med, $indication, $prescriber, $dose, $route, $start, $notes, $status, $patient_instructions, $pharmacy_instructions);
        if ($stmt->execute()) {
            $msg = "Medication added.";
            // Refresh medical_data for medications
            $stmt = $conn->prepare("SELECT * FROM medications WHERE patient_id = ? ORDER BY id DESC");
            $stmt->bind_param("i", $patient_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $medical_data['medications'] = [];
            while ($row = $result->fetch_assoc()) {
                $medical_data['medications'][] = $row;
            }
            $stmt->close();
        } else {
            $error = "Database error: " . $stmt->error;
        }
    }
}

if (isset($_GET['delete_med'])) {
    $id = intval($_GET['delete_med']);
    $stmt = $conn->prepare("DELETE FROM medications WHERE id=? AND patient_id=?");
    $stmt->bind_param("ii", $id, $patient_id);
    if ($stmt->execute()) {
        header("Location: patient_dashboard.php?patient_id=$patient_id&section=medications");
        exit();
    }
    $stmt->close();
}

// Handle update medications
if (isset($_POST['update_med'])) {
    $mid = intval($_POST['med_id']);
    $med = $_POST['medication'] ?? "";
    $indication = $_POST['indication'] ?? "";
    $prescriber = $_POST['prescriber'] ?? "";
    $dose = $_POST['dose'] ?? "";
    $route = $_POST['route'] ?? "";
    if ($route === 'other') {
        $route = $_POST['custom_route'] ?? "";
    }
    $start = $_POST['start_date'] ?? "";
    $notes = $_POST['notes'] ?? "";
    $status = $_POST['status'] ?? "";
    $patient_instructions = $_POST['patient_instructions'] ?? "";
    $pharmacy_instructions = $_POST['pharmacy_instructions'] ?? "";
    // Validate all fields (required)
    if (empty($med)) {
        $error = "Medication is required.";
    }
    elseif (empty($indication)) {
        $error = "Indication is required.";
    }
    elseif (empty($prescriber)) {
        $error = "Prescriber is required.";
    }
    elseif (empty($dose)) {
        $error = "Dose is required.";
    }
    elseif (empty($route)) {
        $error = "Route is required.";
    }
    elseif (empty($start)) {
        $error = "Start Date is required.";
    }
    elseif (empty($notes)) {
        $error = "Notes is required.";
    }
    elseif (empty($status)) {
        $error = "Status is required.";
    }
    elseif (empty($patient_instructions)) {
        $error = "patient instructions is required.";
    }
    elseif (empty($pharmacy_instructions)) {
        $error = "pharmacy instructions is required.";
    }
    else {
        $stmt = $conn->prepare("UPDATE medications SET medication=?, indication=?, prescriber=?, dose=?, route=?, start_date=?, notes=?, status=?, patient_instructions=?, pharmacy_instructions=? WHERE id=? AND patient_id=?");
        $stmt->bind_param("ssssssssssii", $med, $indication, $prescriber, $dose, $route, $start, $notes, $status, $patient_instructions, $pharmacy_instructions, $mid, $patient_id);
        if ($stmt->execute()) {
            $msg = "Medication updated.";
        } else {
            $error = "Database error: " . $stmt->error;
        }
        $stmt->close();
        // Refresh medications data
        $stmt = $conn->prepare("SELECT * FROM medications WHERE patient_id = ? ORDER BY id DESC");
        $stmt->bind_param("i", $patient_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $medical_data['medications'] = [];
        while ($row = $result->fetch_assoc()) {
            $medical_data['medications'][] = $row;
        }
        $stmt->close();
    }
}

if (isset($_GET['get_med'])) {
    $id = intval($_GET['get_med']);
    $stmt = $conn->prepare("SELECT * FROM medications WHERE id=? AND patient_id=?");
    $stmt->bind_param("ii", $id, $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        echo json_encode($row);
    }
    $stmt->close();
    exit;
}

// Progress Notes processing (adapted from progress_notes.php)
if (isset($_POST['add_note'])) {        
    $focus = sanitize_input($conn, $_POST['focus'] ?? "");
    $note  = $_POST['note'] ?? "";
    $author = sanitize_input($conn, $_POST['author'] ?? "");
    $date = $_POST['date'] ?: date("Y-m-d");

    // Validate date format if provided
    if (!empty($_POST['date']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $error = "Date must be in format YYYY-MM-DD.";
    } else {
        $stmt = $conn->prepare("INSERT INTO progress_notes (patient_id, focus, note, author, date_written) VALUES (?,?,?,?,?)");
        $stmt->bind_param("issss", $patient_id, $focus, $note, $author, $date);
        if ($stmt->execute()) {
            $msg = "Note added.";
            // Refresh medical_data for progress_notes
            $stmt = $conn->prepare("SELECT * FROM progress_notes WHERE patient_id = ? ORDER BY id DESC");
            $stmt->bind_param("i", $patient_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $medical_data['progress_notes'] = [];
            while ($row = $result->fetch_assoc()) {
                $medical_data['progress_notes'][] = $row;
            }
            $stmt->close();
        } else {
            $error = "Error: " . $stmt->error;
        }
    }
}

if (isset($_GET['delete_note'])) {
    $id = intval($_GET['delete_note']);
    $stmt = $conn->prepare("DELETE FROM progress_notes WHERE id=? AND patient_id=?");
    $stmt->bind_param("ii", $id, $patient_id);
    if ($stmt->execute()) $msg = "Deleted.";
    $stmt->close();
    // Refresh progress_notes data
    $stmt = $conn->prepare("SELECT * FROM progress_notes WHERE patient_id = ? ORDER BY id DESC");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $medical_data['progress_notes'] = [];
    while ($row = $result->fetch_assoc()) {
        $medical_data['progress_notes'][] = $row;
    }
    $stmt->close();
}

// Handle update progress notes
if (isset($_POST['update_note'])) {
    $nid = intval($_POST['note_id']);
    $focus = sanitize_input($conn, $_POST['focus'] ?? "");
    $note = sanitize_input($conn, $_POST['note'] ?? "");
    $author = sanitize_input($conn, $_POST['author'] ?? "");
    $date = $_POST['date'] ?: date("Y-m-d");
    if (!empty($_POST['date'])) {
        $date = str_replace('T', ' ', $date);
    }

    // Validate date format if provided
    if (!empty($_POST['date']) && !preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2})?$/', $date)) {
        $error = "Invalid date format. Use YYYY-MM-DDTHH:MM.";
    } else {
        $stmt = $conn->prepare("UPDATE progress_notes SET focus=?, note=?, author=?, date_written=? WHERE id=? AND patient_id=?");
        $stmt->bind_param("sssii", $focus, $note, $author, $date, $nid, $patient_id);
        if ($stmt->execute()) {
            $msg = "Note updated.";
        } else {
            $error = "Error updating note: " . $stmt->error;
        }
        $stmt->close();
        // Refresh progress_notes data
        $stmt = $conn->prepare("SELECT * FROM progress_notes WHERE patient_id = ? ORDER BY id DESC");
        $stmt->bind_param("i", $patient_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $medical_data['progress_notes'] = [];
        while ($row = $result->fetch_assoc()) {
            $medical_data['progress_notes'][] = $row;
        }
        $stmt->close();
    }
}

if (isset($_GET['get_note'])) {
    $id = intval($_GET['get_note']);
    $stmt = $conn->prepare("SELECT * FROM progress_notes WHERE id=? AND patient_id=?");
    $stmt->bind_param("ii", $id, $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        echo json_encode($row);
    }
    $stmt->close();
    exit;
}

// Diagnostics processing (adapted from diagnostics.php)
if (isset($_POST['add_diagnostic'])) {
    $study_type = sanitize_input($conn, $_POST['study_type'] ?? "");
    $body_part_region = sanitize_input($conn, $_POST['body_part_region'] ?? "");
    $study_description = sanitize_input($conn, $_POST['study_description'] ?? "");
    $clinical_indication = $_POST['clinical_indication'] ?? "";
    $image_quality = sanitize_input($conn, $_POST['image_quality'] ?? "");
    $order_by = $_POST['order_by'] ?? "";
    $performed_by = $_POST['performed_by'] ?? "";
    $interpreted_by =  $_POST['Interpreted_by'] ?? "";
    $imaging_facility = $_POST['Imaging_facility'] ?? "";
    $radiology_findings = $_POST['radiology_findings'] ?? "";
    $impression_conclusion = $_POST['impression_conclusion'] ?? "";
    $recommendations = $_POST['recommendations'] ?? "";
    $date = $_POST['date'] ?: date("Y-m-d");

    // Validate date format if provided
    if (!empty($_POST['date']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $error = "Date must be in format YYYY-MM-DD.";
    } else {
        $stmt = $conn->prepare("INSERT INTO diagnostics (patient_id, study_type, body_part_region, study_description, clinical_indication, image_quality, order_by, performed_by, Interpreted_by, Imaging_facility, radiology_findings, impression_conclusion, recommendations, date_diagnosed) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param("isssssssssssss", $patient_id, $study_type, $body_part_region, $study_description, $clinical_indication, $image_quality, $order_by, $performed_by, $interpreted_by, $imaging_facility, $radiology_findings, $impression_conclusion, $recommendations, $date);
        if ($stmt->execute()) {
            $msg = "Diagnostic added.";
            // Refresh medical_data for diagnostics
            $stmt = $conn->prepare("SELECT * FROM diagnostics WHERE patient_id = ? ORDER BY id DESC");
            $stmt->bind_param("i", $patient_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $medical_data['diagnostics'] = [];
            while ($row = $result->fetch_assoc()) {
                $medical_data['diagnostics'][] = $row;
            }
            $stmt->close();
        } else {
            $error = "Error: " . $stmt->error;
        }
    }
}

if (isset($_GET['delete_diagnostic'])) {
    $id = intval($_GET['delete_diagnostic']);
    $stmt = $conn->prepare("DELETE FROM diagnostics WHERE id=? AND patient_id=?");
    $stmt->bind_param("ii", $id, $patient_id);
    if ($stmt->execute()) $msg = "Deleted.";
    $stmt->close();
    // Refresh diagnostics data
    $stmt = $conn->prepare("SELECT * FROM diagnostics WHERE patient_id = ? ORDER BY id DESC");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $medical_data['diagnostics'] = [];
    while ($row = $result->fetch_assoc()) {
        $medical_data['diagnostics'][] = $row;
    }
    $stmt->close();
}

// Handle update diagnostics
if (isset($_POST['update_diagnostic'])) {
    $study_type = sanitize_input($conn, $_POST['study_type'] ?? "");
    $body_part_region = sanitize_input($conn, $_POST['body_part_region'] ?? "");
    $study_description = sanitize_input($conn, $_POST['study_description'] ?? "");
    $clinical_indication = sanitize_input($conn, $_POST['clinical_indication'] ?? "");
    $image_quality = sanitize_input($conn, $_POST['image_quality'] ?? ""); 
    $date = $_POST['date'] ?: date("Y-m-d");

    // Validate date format if provided
    if (!empty($_POST['date']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $error = "Date must be in format YYYY-MM-DD.";
    } else {
        $stmt = $conn->prepare("UPDATE diagnostics SET study_type=?, body_part_region=?, study_description=?, clinical_indication=?, image_quality=?, date_diagnosed=? WHERE id=? AND patient_id=?");
        $stmt->bind_param("ssssssii", $study_type, $body_part_region, $study_description, $clinical_indication, $image_quality, $date, $did, $patient_id);
        if ($stmt->execute()) {
            $msg = "Diagnostic updated.";
        } else {
            $error = "Error updating diagnostic: " . $stmt->error;
        }
        $stmt->close();
        // Refresh diagnostics data
        $stmt = $conn->prepare("SELECT * FROM diagnostics WHERE patient_id = ? ORDER BY id DESC");
        $stmt->bind_param("i", $patient_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $medical_data['diagnostics'] = [];
        while ($row = $result->fetch_assoc()) {
            $medical_data['diagnostics'][] = $row;
        }
        $stmt->close();
    }
}

if (isset($_GET['get_diagnostic'])) {
    $id = intval($_GET['get_diagnostic']);
    $stmt = $conn->prepare("SELECT * FROM diagnostics WHERE id=? AND patient_id=?");
    $stmt->bind_param("ii", $id, $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        echo json_encode($row);
    }
    $stmt->close();
    exit;
}

// Treatment Plans processing (adapted from treatment_plans.php)
if (isset($_POST['add_treatment_plan'])) {
    $plan = sanitize_input($conn, $_POST['plan'] ?? "");
    $intervention = sanitize_input($conn, $_POST['intervention'] ?? "");
    $problems = sanitize_input($conn, $_POST['problems'] ?? "");
    $frequency = sanitize_input($conn, $_POST['frequency'] ?? "");
    $duration = sanitize_input($conn, $_POST['duration'] ?? "");
    $order_by = sanitize_input($conn, $_POST['order_by'] ?? "");
    $assigned_to = sanitize_input($conn, $_POST['assigned_to'] ?? "");
    $date_started = sanitize_input($conn, $_POST['date_started'] ?? "");
    $date_ended = sanitize_input($conn, $_POST['date_ended'] ?? "");
    $special_instructions =  $_POST['special_instructions'] ?? "";
    $patient_education_provided =  $_POST['patient_education_provided'] ?? "";

    // Validate date formats if provided
    if (
        (!empty($date_started) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_started)) ||
        (!empty($date_ended) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_ended))
    ) {
        $error = "Date must be in format YYYY-MM-DD.";
    } else {
        $stmt = $conn->prepare("INSERT INTO treatment_plans (patient_id, plan, intervention, problems, frequency, duration, order_by, assigned_to, date_started, date_ended, special_instructions, patient_education_provided) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param("isssssssssss", $patient_id, $plan, $intervention, $problems, $frequency, $duration, $order_by, $assigned_to, $date_started, $date_ended, $special_instructions, $patient_education_provided);

        if ($stmt->execute()) {
            $msg = "Treatment plan added.";

            // Refresh medical_data for treatment_plans
            $stmt = $conn->prepare("SELECT * FROM treatment_plans WHERE patient_id = ? ORDER BY id DESC");
            $stmt->bind_param("i", $patient_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $medical_data['treatment_plans'] = [];
            while ($row = $result->fetch_assoc()) {
                $medical_data['treatment_plans'][] = $row;
            }
            $stmt->close();
        } else {
            $error = "Error: " . $stmt->error;
        }
    }
}


if (isset($_GET['delete_treatment_plan'])) {
    $id = intval($_GET['delete_treatment_plan']);
    $stmt = $conn->prepare("DELETE FROM treatment_plans WHERE id=? AND patient_id=?");
    $stmt->bind_param("ii", $id, $patient_id);
    if ($stmt->execute()) $msg = "Deleted.";
    $stmt->close();
    // Refresh treatment_plans data
    $stmt = $conn->prepare("SELECT * FROM treatment_plans WHERE patient_id = ? ORDER BY id DESC");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $medical_data['treatment_plans'] = [];
    while ($row = $result->fetch_assoc()) {
        $medical_data['treatment_plans'][] = $row;
    }
    $stmt->close();
}

// Handle update treatment_plans
if (isset($_POST['update_treatment_plan'])) {
    $tid = intval($_POST['treatment_plan_id']);
    $plan = sanitize_input($conn, $_POST['plan'] ?? "");
    $intervention = sanitize_input($conn, $_POST['intervention'] ?? "");
    $frequency = sanitize_input($conn, $_POST['frequency'] ?? "");
    $duration = sanitize_input($conn, $_POST['duration'] ?? "");
    $order_by = sanitize_input($conn, $_POST['order_by'] ?? "");
    $assigned_to = sanitize_input($conn, $_POST['assigned_to'] ?? "");
    $date_started = sanitize_input($conn, $_POST['date_started'] ?? "");
    $date_ended = sanitize_input($conn, $_POST['date_ended'] ?? "");
    $special_instructions = sanitize_input($conn, $_POST['special_instructions'] ?? "");
    $patient_education_provided = sanitize_input($conn, $_POST['patient_education_provided'] ?? "");


    // Validate date format if provided
    if  ( (!empty($date_started) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_started)) ||
        (!empty($date_ended) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_ended))) {
        $error = "Date must be in format YYYY-MM-DD.";
    } else {
        $stmt = $conn->prepare("UPDATE treatment_plans SET plan=?, intervention=?, problems=?, frequency=?, duration=?, order_by=?, assigned_to=?, date_started=?, date_ended=?, special_instructions=?, patient_education_provided=?, WHERE id=? AND patient_id=?");
        $stmt->bind_param("sssssssssssii", $plan, $intervention, $problems,$frequency, $duration, $order_by, $assigned_to, $date_started, $date_ended, $special_instructions, $patient_education_provided, $tid, $patient_id);
        if ($stmt->execute()) {
            $msg = "Treatment plan updated.";
        } else {
            $error = "Error updating treatment plan: " . $stmt->error;
        }
        $stmt->close();
        // Refresh treatment_plans data
        $stmt = $conn->prepare("SELECT * FROM treatment_plans WHERE patient_id = ? ORDER BY id DESC");
        $stmt->bind_param("i", $patient_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $medical_data['treatment_plans'] = [];
        while ($row = $result->fetch_assoc()) {
            $medical_data['treatment_plans'][] = $row;
        }
        $stmt->close();
    }
}

if (isset($_GET['get_treatment_plan'])) {
    $id = intval($_GET['get_treatment_plan']);
    $stmt = $conn->prepare("SELECT * FROM treatment_plans WHERE id=? AND patient_id=?");
    $stmt->bind_param("ii", $id, $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        echo json_encode($row);
    }
    $stmt->close();
    exit;
}

// Lab Results processing (adapted from lab_results.php)
if (isset($_POST['add_lab'])) {
    $test = sanitize_input($conn, $_POST['test_name'] ?? "");
    $result = sanitize_input($conn, $_POST['result'] ?? "");
    $test_category = sanitize_input($conn, $_POST['test_category'] ?? "");
    $test_code = sanitize_input($conn, $_POST['test_code'] ?? "");
    $result_status = sanitize_input($conn, $_POST['result_status'] ?? "");
    $units = sanitize_input($conn, $_POST['units'] ?? "");
    $reference_range = sanitize_input($conn, $_POST['reference_range'] ?? "");
    $order_by = sanitize_input($conn, $_POST['order_by'] ?? "");
    $collected_by = sanitize_input($conn, $_POST['collected_by'] ?? "");
    $labarotary_facility = sanitize_input($conn, $_POST['labarotary_facility'] ?? "");
    $clinical_interpretation = $_POST['clinical_interpretation'] ?? "";
    $date = $_POST['date'] ?: date("Y-m-d H:i:s");

    // Validate date format if provided
    if (!empty($_POST['date']) && !preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/', $date)) {
    }
    else {
        $stmt = $conn->prepare("INSERT INTO lab_results (patient_id, test_name, test_result, test_category, test_code, result_status, units, reference_range, order_by, collected_by, labarotary_facility, clinical_interpretation, date_taken) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param("issssssssssss", $patient_id, $test, $result, $test_category, $test_code, $result_status, $units, $reference_range, $order_by, $collected_by, $labarotary_facility, $clinical_interpretation, $date);
        if ($stmt->execute()) {
            $msg = "Lab result added.";
            // Refresh medical_data for lab_results
            $stmt = $conn->prepare("SELECT * FROM lab_results WHERE patient_id = ? ORDER BY id DESC");
            $stmt->bind_param("i", $patient_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $medical_data['lab_results'] = [];
            while ($row = $result->fetch_assoc()) {
                $medical_data['lab_results'][] = $row;
            }
            $stmt->close();
        } else {
            $error = "Error: " . $stmt->error;
        }
    }
}

if (isset($_GET['delete_lab'])) {
    $id = intval($_GET['delete_lab']);
    $stmt = $conn->prepare("DELETE FROM lab_results WHERE id=? AND patient_id=?");
    $stmt->bind_param("ii", $id, $patient_id);
    if ($stmt->execute()) $msg = "Deleted.";
    $stmt->close();
    // Refresh lab_results data
    $stmt = $conn->prepare("SELECT * FROM lab_results WHERE patient_id = ? ORDER BY id DESC");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $medical_data['lab_results'] = [];
    while ($row = $result->fetch_assoc()) {
        $medical_data['lab_results'][] = $row;
    }
    $stmt->close();
}

// Handle update lab_results
if (isset($_POST['update_lab'])) {
    $lid = intval($_POST['lab_id']);
    $test = sanitize_input($conn, $_POST['test_name'] ?? "");
    $result = sanitize_input($conn, $_POST['result'] ?? "");
    $test_result = sanitize_input($conn, $_POST['test_category'] ?? "");
    $test_code = sanitize_input($conn, $_POST['test_code'] ?? "");
    $result_status = sanitize_input($conn, $_POST['result_status'] ?? "");
    $units = sanitize_input($conn, $_POST['units'] ?? "");
    $reference_range = sanitize_input($conn, $_POST['reference_range'] ?? "");
    $order_by = sanitize_input($conn, $_POST['order_by'] ?? "");
    $collected_by = sanitize_input($conn, $_POST['collected_by'] ?? "");
    $labarotary_facility = sanitize_input($conn, $_POST['labarotary_facility'] ?? "");
    $date = $_POST['date'] ?: date("Y-m-d H:i:s");

    // Validate date format if provided
    if (!empty($_POST['date']) && !preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/', $date)) {
        $error = "Invalid date format. Use YYYY-MM-DD or YYYY-MM-DD HH:MM:SS.";
    }
    else {
        $stmt = $conn->prepare("UPDATE lab_results SET test_name=?, test_category=?, test_code=?, test_result=?, result_status=?, units=?, reference_range=?, order_by=?, collected_by=?, laboratory_facility=?, date_taken=? WHERE id=? AND patient_id=?");
        $stmt->bind_param("sssssssssssii", $test, $test_category, $test_code, $result, $result_status, $units, $reference_range, $order_by, $collected_by, $labarotary_facility, $date, $lid, $patient_id);
        if ($stmt->execute()) {
            $msg = "Lab result updated.";
        } else {
            $error = "Error updating lab result: " . $stmt->error;
        }
        $stmt->close();
        // Refresh lab_results data
        $stmt = $conn->prepare("SELECT * FROM lab_results WHERE patient_id = ? ORDER BY id DESC");
        $stmt->bind_param("i", $patient_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $medical_data['lab_results'] = [];
        while ($row = $result->fetch_assoc()) {
            $medical_data['lab_results'][] = $row;
        }
        $stmt->close();
    }
}

if (isset($_GET['get_lab'])) {
    $id = intval($_GET['get_lab']);
    $stmt = $conn->prepare("SELECT * FROM lab_results WHERE id=? AND patient_id=?");
    $stmt->bind_param("ii", $id, $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        echo json_encode($row);
    }
    $stmt->close();
    exit;
}

// Medical History processing (adapted from medical_history.php)
if (isset($_POST['add_medical_history'])) {
    $condition = sanitize_input($conn, $_POST['condition_name'] ?? "");
    $status = sanitize_input($conn, $_POST['status'] ?? "");
    $notes = sanitize_input($conn, $_POST['notes'] ?? "");
    $date = $_POST['date'] ?: date("Y-m-d");

    // Validate date format if provided
    if (!empty($_POST['date']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $error = "Date must be in format YYYY-MM-DD.";
    } else {
        $stmt = $conn->prepare("INSERT INTO medical_history (patient_id, condition_name, status, notes, date_recorded) VALUES (?,?,?,?,?)");
        $stmt->bind_param("issss", $patient_id, $condition, $status, $notes, $date);
        if ($stmt->execute()) {
            $msg = "Medical history added.";
            // Refresh medical_data for medical_history
            $stmt = $conn->prepare("SELECT * FROM medical_history WHERE patient_id = ? ORDER BY id DESC");
            $stmt->bind_param("i", $patient_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $medical_data['medical_history'] = [];
            while ($row = $result->fetch_assoc()) {
                $medical_data['medical_history'][] = $row;
            }
            $stmt->close();
        } else {
            $error = "Error: " . $stmt->error;
        }
    }
}

if (isset($_GET['delete_medical_history'])) {
    $id = intval($_GET['delete_medical_history']);
    $stmt = $conn->prepare("DELETE FROM medical_history WHERE id=? AND patient_id=?");
    $stmt->bind_param("ii", $id, $patient_id);
    if ($stmt->execute()) $msg = "Deleted.";
    $stmt->close();
    // Refresh medical_history data
    $stmt = $conn->prepare("SELECT * FROM medical_history WHERE patient_id = ? ORDER BY id DESC");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $medical_data['medical_history'] = [];
    while ($row = $result->fetch_assoc()) {
        $medical_data['medical_history'][] = $row;
    }
    $stmt->close();
}

// Handle update medical_history
if (isset($_POST['update_medical_history'])) {
    $hid = intval($_POST['history_id']);
    $condition = sanitize_input($conn, $_POST['condition_name'] ?? "");
    $notes = sanitize_input($conn, $_POST['notes'] ?? "");
    $date = $_POST['date'] ?: date("Y-m-d");

    // Validate date format if provided
    if (!empty($_POST['date']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $error = "Date must be in format YYYY-MM-DD.";
    } else {
        $stmt = $conn->prepare("UPDATE medical_history SET condition_name=?, notes=?, date_recorded=? WHERE id=? AND patient_id=?");
        $stmt->bind_param("sssii", $condition, $notes, $date, $hid, $patient_id);
        if ($stmt->execute()) {
            $msg = "Medical history updated.";
        } else {
            $error = "Error updating medical history: " . $stmt->error;
        }
        $stmt->close();
        // Refresh medical_history data
        $stmt = $conn->prepare("SELECT * FROM medical_history WHERE patient_id = ? ORDER BY id DESC");
        $stmt->bind_param("i", $patient_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $medical_data['medical_history'] = [];
        while ($row = $result->fetch_assoc()) {
            $medical_data['medical_history'][] = $row;
        }
        $stmt->close();
    }
}

if (isset($_GET['get_medical_history'])) {
    $id = intval($_GET['get_medical_history']);
    $stmt = $conn->prepare("SELECT * FROM medical_history WHERE id=? AND patient_id=?");
    $stmt->bind_param("ii", $id, $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        echo json_encode($row);
    }
    $stmt->close();
    exit;
}

// Physical Assessments processing (adapted from other sections)
if (isset($_POST['add_physical_assessment'])) {
    $assessed_by = sanitize_input($conn, $_POST['assessed_by'] ?? "");
    $head_and_neck = sanitize_input($conn, $_POST['head_and_neck'] ?? "");
    $cardiovascular = sanitize_input($conn, $_POST['cardiovascular'] ?? "");
    $respiratory = sanitize_input($conn, $_POST['respiratory'] ?? "");
    $abdominal = sanitize_input($conn, $_POST['abdominal'] ?? "");
    $neurological = sanitize_input($conn, $_POST['neurological'] ?? "");
    $musculoskeletal = sanitize_input($conn, $_POST['musculoskeletal'] ?? "");
    $skin = sanitize_input($conn, $_POST['skin'] ?? "");
    $psychiatric = $_POST['psychiatric'] ?? "";
    $date = $_POST['date'] ?: date("Y-m-d");

    // Validate date format if provided
    if (!empty($_POST['date']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $error = "Date must be in format YYYY-MM-DD.";
    } else {
        $stmt = $conn->prepare("INSERT INTO physical_assessments (patient_id, assessed_by, head_and_neck, cardiovascular, respiratory, abdominal, neurological, musculoskeletal, skin, psychiatric, date_assessed) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param("issssssssss", $patient_id, $assessed_by, $head_and_neck, $cardiovascular, $respiratory, $abdominal, $neurological, $musculoskeletal, $skin, $psychiatric, $date);
        if ($stmt->execute()) {
            $msg = "Physical assessment added.";
            // Refresh medical_data for physical_assessments
            $stmt = $conn->prepare("SELECT * FROM physical_assessments WHERE patient_id = ? ORDER BY id DESC");
            $stmt->bind_param("i", $patient_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $medical_data['physical_assessments'] = [];
            while ($row = $result->fetch_assoc()) {
                $medical_data['physical_assessments'][] = $row;
            }
            $stmt->close();
        } else {
            $error = "Error: " . $stmt->error;
        }
    }
}

if (isset($_GET['delete_physical_assessment'])) {
    $id = intval($_GET['delete_physical_assessment']);
    $stmt = $conn->prepare("DELETE FROM physical_assessments WHERE id=? AND patient_id=?");
    $stmt->bind_param("ii", $id, $patient_id);
    if ($stmt->execute()) $msg = "Deleted.";
    $stmt->close();
    // Refresh physical_assessments data
    $stmt = $conn->prepare("SELECT * FROM physical_assessments WHERE patient_id = ? ORDER BY id DESC");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $medical_data['physical_assessments'] = [];
    while ($row = $result->fetch_assoc()) {
        $medical_data['physical_assessments'][] = $row;
    }
    $stmt->close();
}

// Handle update physical_assessments
if (isset($_POST['update_physical_assessment'])) {
    $aid = intval($_POST['assessment_id']);
    $assessed_by = sanitize_input($conn, $_POST['assessed_by'] ?? "");
    $head_and_neck = sanitize_input($conn, $_POST['head_and_neck'] ?? "");
    $cardiovascular = sanitize_input($conn, $_POST['cardiovascular'] ?? "");
    $respiratory = sanitize_input($conn, $_POST['respiratory'] ?? "");
    $abdominal = sanitize_input($conn, $_POST['abdominal'] ?? "");
    $neurological = sanitize_input($conn, $_POST['neurological'] ?? "");
    $musculoskeletal = sanitize_input($conn, $_POST['musculoskeletal'] ?? "");
    $skin = sanitize_input($conn, $_POST['skin'] ?? "");
    $psychiatric = sanitize_input($conn, $_POST['psychiatric'] ?? "");
    $date = $_POST['date'] ?: date("Y-m-d");

    // Validate date format if provided
    if (!empty($_POST['date']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $error = "Date must be in format YYYY-MM-DD.";
    } else {
        $stmt = $conn->prepare("UPDATE physical_assessments SET assessed_by=?, head_and_neck=?, cardiovascular=?, respiratory=?, abdominal=?, neurological=?, musculoskeletal=?, skin=?, psychiatric=?, date_assessed=? WHERE id=? AND patient_id=?");
        $stmt->bind_param("sssssssssii", $assessed_by, $head_and_neck, $cardiovascular, $respiratory, $abdominal, $neurological, $musculoskeletal, $skin, $psychiatric, $date, $aid, $patient_id);
        if ($stmt->execute()) {
            $msg = "Physical assessment updated.";
        } else {
            $error = "Error updating physical assessment: " . $stmt->error;
        }
        $stmt->close();
        // Refresh physical_assessments data
        $stmt = $conn->prepare("SELECT * FROM physical_assessments WHERE patient_id = ? ORDER BY id DESC");
        $stmt->bind_param("i", $patient_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $medical_data['physical_assessments'] = [];
        while ($row = $result->fetch_assoc()) {
            $medical_data['physical_assessments'][] = $row;
        }
        $stmt->close();
    }
}

if (isset($_GET['get_physical_assessment'])) {
    $id = intval($_GET['get_physical_assessment']);
    $stmt = $conn->prepare("SELECT * FROM physical_assessments WHERE id=? AND patient_id=?");
    $stmt->bind_param("ii", $id, $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        echo json_encode($row);
    }
    $stmt->close();
    exit;
}

include "header.php";

// Determine submitted section for JavaScript
$submitted_section = '';
if (isset($_POST['add_vitals']) || isset($_POST['update_vitals'])) $submitted_section = 'vitals';
if (isset($_POST['add_med']) || isset($_POST['update_med'])) $submitted_section = 'medications';
if (isset($_POST['add_note']) || isset($_POST['update_note'])) $submitted_section = 'progress_notes';
if (isset($_POST['add_diagnostic']) || isset($_POST['update_diagnostic'])) $submitted_section = 'diagnostics';
if (isset($_POST['add_treatment_plan']) || isset($_POST['update_treatment_plan'])) $submitted_section = 'treatment_plans';
if (isset($_POST['add_lab']) || isset($_POST['update_lab'])) $submitted_section = 'lab_results';
if (isset($_POST['add_medical_history']) || isset($_POST['update_medical_history'])) $submitted_section = 'medical_history';
if (isset($_POST['add_physical_assessment']) || isset($_POST['update_physical_assessment'])) $submitted_section = 'physical_assessment';
?>

<style>
    body {
      background-color: #ffffff;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      padding-top: 5rem;
    }

    .btn-primary {
        background-color: var(--primary-color);
        border-color: var(--primary-color);
    }
    .btn-primary:hover {
        background-color: var(--accent-color);
        border-color: var(--accent-color);
    }

    .alert {
        border-radius: 0.5rem;
        border: none;
    }

    .card {
        margin: 1rem 0 0 0;
        border-radius: 0.75rem;
        
    }

    .table-responsive {
        border-radius: 0.5rem;
        overflow: hidden;
    }

    .action-btn {
        display: flex;
        flex-direction: row;
        gap: 1.05rem;
    }

    .btn-edit, .btn-delete {
        width: 5.75rem;
        display: flex;
        flex-direction: column;
    }
    .module{
        width: 19rem;
    }
    .content{
        width: 77vw;
    }
</style>



<!-- Edit Modal for Medications -->
<div class="modal fade" id="editMedModal" tabindex="-1" aria-labelledby="editMedModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" id="editMedForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="editMedModalLabel">Edit Medication</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="med_id" id="med_id">
                    <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
                    <div class="mb-3">
                        <label for="medication" class="form-label">Medication</label>
                        <input type="text" class="form-control" name="medication" id="medication" placeholder="Medication" required>
                    </div>
                    <div class="mb-3">
                        <label for="indication" class="form-label">Indication</label>
                        <input type="text" class="form-control" name="indication" id="indication" placeholder="Indication" required>
                    </div>
                    <div class="mb-3">
                        <label for="prescriber" class="form-label">Prescriber</label>
                        <input type="text" class="form-control" name="prescriber" id="prescriber" placeholder="Prescriber (e.g. Dr.Name, MD)" required>
                    </div>
                    <div class="mb-3">
                        <label for="dose" class="form-label">Dose</label>
                        <input type="text" class="form-control" name="dose" id="dose" placeholder="Dose" required>
                    </div>
                    <div class="mb-3">
                        <label for="route_edit" class="form-label">Route</label>
                        <select class="form-control" name="route" id="route_edit" required>
                            <option value="">Select Route</option>
                            <option value="PO">PO</option>
                            <option value="IV">IV</option>
                            <option value="IM">IM</option>
                            <option value="SC">SC</option>
                            <option value="Topical">Topical</option>
                            <option value="Inhaled">Inhaled</option>
                            <option value="PR">PR</option>
                            <option value="SL">SL</option>
                            <option value="other">Other</option>
                        </select>
                        <input type="text" class="form-control mt-1" name="custom_route" id="custom_route_edit" placeholder="Specify Route" style="display: none;">
                    </div>
                    <div class="mb-3">
                        <label for="start_date" class="form-label">Start Date</label>
                        <input type="date" class="form-control" name="start_date" id="start_date" required>
                    </div>
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" id="notes" placeholder="Notes" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-control" name="status" id="status" required>
                            <option value="">Select Status</option>
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                            <option value="Discontinued">Discontinued</option>
                            <option value="On Hold">On Hold</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="patient_instructions" class="form-label">Patient Instructions</label>
                        <textarea class="form-control" name="patient_instructions" id="patient_instructions" placeholder="Patient Instructions" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="pharmacy_instructions" class="form-label">Pharmacy Instructions</label>
                        <textarea class="form-control" name="pharmacy_instructions" id="pharmacy_instructions" placeholder="Pharmacy Instructions" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="update_med" class="btn btn-primary">Save changes</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal for Vitals -->
<div class="modal fade" id="editVitalModal" tabindex="-1" aria-labelledby="editVitalModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" id="editVitalForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="editVitalModalLabel">Edit Vital Signs</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                    <div class="modal-body">
                        <input type="hidden" name="vital_id" id="vital_id">
                        <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
                        <div class="mb-3">
                            <label for="recorded_by_edit" class="form-label">Recorded By</label>
                            <input type="text" class="form-control" name="recorded_by" id="recorded_by_edit" placeholder="Recorded By" required>
                        </div>
                        <div class="mb-3">
                            <label for="bp_edit" class="form-label">Blood Pressure</label>
                            <input type="text" class="form-control" name="bp" id="bp_edit" placeholder="BP (e.g., 120/80)" required>
                        </div>
                        <div class="mb-3">
                            <label for="rr_edit" class="form-label">Respiratory Rate</label>
                            <input type="text" class="form-control" name="respiratory_rate" id="rr_edit" placeholder="CPM (e.g., 120/80)" required>
                        </div>
                        <div class="mb-3">
                            <label for="hr_edit" class="form-label">Heart Rate</label>
                            <input type="number" class="form-control" name="hr" id="hr_edit" placeholder="HR (bpm)" required>
                        </div>
                        <div class="mb-3">
                            <label for="temp_edit" class="form-label">Temperature</label>
                            <input type="number" step="0.1" class="form-control" name="temp" id="temp_edit" placeholder="Temp (°C)" required>
                        </div>
                        <div class="mb-3">
                            <label for="height_edit" class="form-label">Height</label>
                            <input type="number" step="0.1" class="form-control" name="height" id="height_edit" placeholder="Height (cm)" required>
                        </div>
                        <div class="mb-3">
                            <label for="weight_edit" class="form-label">Weight</label>
                            <input type="number" step="0.1" class="form-control" name="weight" id="weight_edit" placeholder="Weight (kg)" required>
                        </div>
                        <div class="mb-3">
                            <label for="oxygen_saturation_edit" class="form-label">Oxygen Saturation</label>
                            <input type="number" class="form-control" name="oxygen_saturation" id="oxygen_saturation_edit" placeholder="Oxygen Saturation (%)" min="0" max="100">
                        </div>
                        <div class="mb-3">
                            <label for="pain_scale_edit" class="form-label">Pain Scale</label>
                              <input type="number" class="form-control" name="pain_scale" id="pain_scale_edit" placeholder="Pain Scale (0-10)" min="0" max="10">
                        </div>
                        <div class="mb-3">
                            <label for="ga_edit" class="form-label">General Appearance</label>
                            <textarea type="text" class="form-control" name="general_appearance" id="ga_edit" placeholder="Patient appears comfortable, alert and oriented" required></textarea>
                        </div>
                        <div class="mb-3">  
                            <label for="date_edit" class="form-label">Date</label>
                            <input type="date" class="form-control" name="date" id="date_edit">
                        </div>
                    </div>
                <div class="modal-footer">
                    <button type="submit" name="update_vitals" class="btn btn-primary">Save changes</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal for Progress Notes -->
<div class="modal fade" id="editNoteModal" tabindex="-1" aria-labelledby="editNoteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" id="editNoteForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="editNoteModalLabel">Edit Progress Note</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="note_id" id="note_id">
                    <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
                    <div class="mb-3">
                        <label for="note_edit" class="form-label">Note</label>
                        <textarea class="form-control" name="note" id="note_edit" rows="4" placeholder="Progress note" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="author_edit" class="form-label">Author</label>
                        <input type="text" class="form-control" name="author" id="author_edit" placeholder="Author" required>
                    </div>
                    <div class="mb-3">
                        <label for="date_note_edit" class="form-label">Date Written</label>
                        <input type="date" class="form-control" name="date" id="date_note_edit" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="update_note" class="btn btn-primary">Save changes</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal for Diagnostics /  -->
<div class="modal fade" id="editDiagnosticModal" tabindex="-1" aria-labelledby="editDiagnosticModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" id="editDiagnosticForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="editDiagnosticModalLabel">Edit Diagnostic</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="diagnostic_id" id="diagnostic_id">
                    <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
                    <div class="mb-3">
                    <label for="study_type_edit" class="form-label">Select Study Type</label>
                        <select class="form-control" name="study_type" id="study_type_edit" required>
                        <option value="">Select Study Type</option>
                            <option value="X-RAY" <?php if (($_POST['study_type'] ?? '') == 'X-RAY') echo 'selected'; ?>>X-RAY</option>
                            <option value="CT SCAN" <?php if (($_POST['study_type'] ?? '') == 'CT SCAN') echo 'selected'; ?>>CT SCAN</option>
                            <option value="MRI" <?php if (($_POST['study_type'] ?? '') == 'MRI') echo 'selected'; ?>>MRI</option>
                            <option value="Utrasound" <?php if (($_POST['study_type'] ?? '') == 'Utrasound') echo 'selected'; ?>>Utrasound</option>
                            <option value="Nuclear Medicine" <?php if (($_POST['study_type'] ?? '') == 'Nuclear Medicine') echo 'selected'; ?>>Nuclear Medicine</option>
                            <option value="PET Scan" <?php if (($_POST['study_type'] ?? '') == 'PET Scan') echo 'selected'; ?>>PET Scan</option>
                            <option value="Mammography" <?php if (($_POST['study_type'] ?? '') == 'Mammography') echo 'selected'; ?>>Mammography</option>
                            <option value="Fluroscopy" <?php if (($_POST['study_type'] ?? '') == 'Fluroscopy') echo 'selected'; ?>>Fluroscopy</option>
                            <option value="other" <?php if (($_POST['study_type'] ?? '') == 'other') echo 'selected'; ?>>Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="diagnosis_edit" class="form-label">Diagnosis</label>
                        <input type="text" class="form-control" name="diagnosis" id="diagnosis_edit" placeholder="Diagnosis" required>
                    </div>
                    <div class="mb-3">
                        <label for="date_diagnostic_edit" class="form-label">Date Diagnosed</label>
                        <input type="date" class="form-control" name="date" id="date_diagnostic_edit" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="update_diagnostic" class="btn btn-primary">Save changes</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal for Treatment Plans -->
<div class="modal fade" id="editTreatmentPlanModal" tabindex="-1" aria-labelledby="editTreatmentPlanModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" id="editTreatmentPlanForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="editTreatmentPlanModalLabel">Edit Treatment Plan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="treatment_plan_id" id="treatment_plan_id">
                    <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
                    <div class="mb-3">
                        <label for="plan_edit" class="form-label">Plan Type</label>
                        <select class="form-control" name="plan" id="route_select" required>
                            <option value="">Select Plan Type</option>
                            <option value="Physician Order">Physician Order</option>
                            <option value="Nursing Intervention">Nursing Intervention</option>
                            <option value="Procedure">Procedure</option>
                            <option value="Therapy">Therapy</option>
                            <option value="Patient Education">Patient Education</option>
                            <option value="Referral">Referral</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="problems_plan_edit" class="form-label">Intervention Description</label>
                        <textarea class="form-control" name="problems" id="problems_plan_edit" rows="3" placeholder="Related Problems"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="frequency_plan_edit" class="form-label">Frequency</label>
                        <textarea class="form-control" name="frequency" id="frequency_plan_edit" rows="3" placeholder="e.g., 2 weeks, Until discharge"></textarea>
                    </div>
                    <div class="mb-3">  
                        <label for="duration_plan_edit" class="form-label">Duration</label>
                        <textarea class="form-control" name="duration" id="duration_plan_edit" rows="3" placeholder="e.g., Daily, BIR, PRN"></textarea>
                    </div>
                    <div class="mb-3">  
                        <label for="order_by_plan_edit" class="form-label">Order_by</label>
                        <textarea class="form-control" name="order_by" id="order_by_plan_edit" rows="3" placeholder="Healthcare provider name"></textarea>
                    </div>
                    <div class="mb-3">  
                        <label for="assigned_to_plan_edit" class="form-label">Assigned_to</label>
                        <textarea class="form-control" name="assigned_to" id="assigned_to_plan_edit" rows="3" placeholder="Responsible Healthcare provider"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="date_started_plan_edit" class="form-label">Start Date</label>
                        <input type="date" class="form-control" name="date_started" id="date_started_plan_edit">
                    </div>
                    <div class="mb-3">
                        <label for="date_ended_plan_edit" class="form-label">End Date</label>
                        <input type="date" class="form-control" name="date_ended" id="date_ended_plan_edit">
                    </div>
                    <div class="mb-3">
                        <label for="special_instructions_plan_edit" class="form-label">Special Instructions</label>
                        <textarea class="form-control" name="special_instructions" id="special_instructions_plan_edit" rows="4" placeholder="Special Instructions or instructions..." required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="patient_education_provided_plan_edit" class="form-label">Patient Education Provided</label>
                        <textarea class="form-control" name="patient_education_provided" id="patient_education_provided_plan_edit" rows="4" placeholder="Education provided to patient and/or family..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="update_treatment_plan" class="btn btn-primary">Save changes</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal for Lab Results -->
<div class="modal fade" id="editLabModal" tabindex="-1" aria-labelledby="editLabModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" id="editLabForm">
                <!-- Edit Modal Header Lab Results -->
                <div class="modal-header">
                    <h5 class="modal-title" id="editLabModalLabel">Edit Lab Result</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <!-- Edit Modal Body Lab Results -->
                <div class="modal-body">
                    <input type="hidden" name="lab_id" id="lab_id">
                    <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
                <!-- Edit Modal Test Name Lab Results -->
                <div class="mb-3">
                    <label for="test_name_edit" class="form-label">Test Name</label>
                    <input type="text" class="form-control" name="test_name" id="test_name_edit" placeholder="Test Name" required>
                </div>
                <!-- Edit Modal Result Lab Results -->
                <div class="mb-3">
                    <label for="result_edit" class="form-label">Result</label>
                    <input type="text" class="form-control" name="result" id="result_edit" placeholder="Result" required>
                </div>
                <!-- Edit Modal Header Test Category Results -->
                <div class="mb-3">
                    <label for="test_category_edit" class="form-label">Test Category</label>
                    <select class="form-control" name="test_category_edit" id="test_category_edit" required>
                            <option value="">Select Test Category</option>
                            <option value="Hematology">Physician Order</option>
                            <option value="Nursing Intervention">Nursing Intervention</option>
                            <option value="Procedure">Procedure</option>
                            <option value="Therapy">Therapy</option>
                            <option value="Patient Education">Patient Education</option>
                            <option value="Referral">Referral</option>
                    </select>
                    <input type="text" class="form-control mt-1" name="test_category_edit" id="test_category_edit" placeholder="Specify Test Category" style="display: none;">
                </div>
                <div class="mb-3">
                    <label for="test_category_edit" class="form-label">Test Category</label>
                    <input type="text" class="form-control" name="test_category" id="test_category_edit" placeholder="CBC..." required>
                </div>
                <div class="mb-3">
                    <label for="test_code_edit" class="form-label">Test Code</label>
                    <input type="text" class="form-control" name="test_code" id="test_code_edit" placeholder="CBC..." required>
                </div>
                <div class="mb-3">
                    <label for="result_status_edit" class="form-label">Result</label>
                    <input type="text" class="form-control" name="result_status" id="result_status_edit" placeholder="Result Status" required>
                </div>
                <div class="mb-3">
                    <label for="unit_edit" class="form-label">Unit</label>
                    <input type="text" class="form-control" name="unit" id="unit_edit" placeholder="mg/dl" required>
                </div>
                <div class="mb-3">
                    <label for="reference_range_edit" class="form-label">Reference Range</label>
                    <input type="text" class="form-control" name="reference_range" id="reference_range_edit" placeholder="e.g., 3.5-5.0" required>
                </div>
                <div class="mb-3">
                    <label for="order_by_edit" class="form-label">Order by</label>
                    <input type="text" class="form-control" name="order_by" id="order_by_edit" placeholder="Dr. Name, MD" required>
                </div>
                <div class="mb-3">
                    <label for="collected_by_edit" class="form-label">Collected by</label>
                    <input type="text" class="form-control" name="collected_by" id="collected_by_edit" placeholder="Phlebotomist name" required>
                </div>
                <div class="mb-3">
                    <label for="laboratory_facilty_edit" class="form-label">Laboratory facilty</label>
                    <input type="text" class="form-control" name="laboratory_facilty" id="laboratory_facilty_edit" placeholder="Lab facility name" required>
                </div>
                <div class="mb-3">
                    <label for="clinical_interpretation_provided_plan_edit" class="form-label">Clinical Interpretation</label>
                    <textarea class="form-control" name="clinical_interpretation" id="clinical_interpretation_provided_plan_edit" rows="4" placeholder="Clinical significance and interpretation" required></textarea>
                </div>
                <div class="mb-3">
                    <label for="date_lab_edit" class="form-label">Date Taken</label>
                    <input type="datetime-local" class="form-control" name="date" id="date_lab_edit" required>
                </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="update_lab" class="btn btn-primary">Save changes</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal for Medical History -->
<div class="modal fade" id="editMedicalHistoryModal" tabindex="-1" aria-labelledby="editMedicalHistoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" id="editMedicalHistoryForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="editMedicalHistoryModalLabel">Edit Medical History</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="history_id" id="history_id">
                    <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
                    <div class="mb-3">
                        <label for="condition_name_edit" class="form-label">Condition Name</label>
                        <input type="text" class="form-control" name="condition_name" id="condition_name_edit" placeholder="Condition Name" required>
                    </div>
                    <div class="mb-3">
                        <label for="notes_history_edit" class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" id="notes_history_edit" rows="3" placeholder="Notes" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="date_history_edit" class="form-label">Date Recorded</label>
                        <input type="date" class="form-control" name="date" id="date_history_edit" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="update_medical_history" class="btn btn-primary">Save changes</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal for Physical Assessment -->
<div class="modal fade" id="editPhysicalAssessmentModal" tabindex="-1" aria-labelledby="editPhysicalAssessmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" id="editPhysicalAssessmentForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="editPhysicalAssessmentModalLabel">Edit Physical Assessment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="assessment_id" id="assessment_id">
                    <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
                    <div class="mb-3">
                        <label for="assessed_by_edit" class="form-label">Assessed By</label>
                        <input type="text" class="form-control" name="assessed_by" id="assessed_by_edit" placeholder="Assessed By" required>
                    </div>
                    <div class="mb-3">
                        <label for="head_and_neck_edit" class="form-label">Head and Neck</label>
                        <textarea class="form-control" name="head_and_neck" id="head_and_neck_edit" rows="2" placeholder="Head and Neck" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="cardiovascular_edit" class="form-label">Cardiovascular</label>
                        <textarea class="form-control" name="cardiovascular" id="cardiovascular_edit" rows="2" placeholder="Cardiovascular" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="respiratory_edit" class="form-label">Respiratory</label>
                        <textarea class="form-control" name="respiratory" id="respiratory_edit" rows="2" placeholder="Respiratory" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="abdominal_edit" class="form-label">Abdominal</label>
                        <textarea class="form-control" name="abdominal" id="abdominal_edit" rows="2" placeholder="Abdominal" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="neurological_edit" class="form-label">Neurological</label>
                        <textarea class="form-control" name="neurological" id="neurological_edit" rows="2" placeholder="Neurological" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="musculoskeletal_edit" class="form-label">Musculoskeletal</label>
                        <textarea class="form-control" name="musculoskeletal" id="musculoskeletal_edit" rows="2" placeholder="Musculoskeletal" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="skin_edit" class="form-label">Skin</label>
                        <textarea class="form-control" name="skin" id="skin_edit" rows="2" placeholder="Skin" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="psychiatric_edit" class="form-label">Psychiatric</label>
                        <textarea class="form-control" name="psychiatric" id="psychiatric_edit" rows="2" placeholder="Psychiatric" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="date_assessment_edit" class="form-label">Date Assessed</label>
                        <input type="date" class="form-control" name="date" id="date_assessment_edit" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="update_physical_assessment" class="btn btn-primary">Save changes</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="container-fluid mt-4">
    <div class="row">
        <!-- Sidebar for EHR Modules -->
        <div class="col-md-3 module">
            <!-- EHR Modules -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-grid me-2"></i>EHR Modules</h6>
                </div>
                <div class="card-body">
                    <button onclick="showSection('physical_assessment')" class="btn btn-outline-primary w-100 mb-2">
                        <i class="bi bi-clipboard-check me-2"></i>Physical Assessment
                    </button>
                    <button onclick="showSection('vitals')" class="btn btn-outline-primary w-100 mb-2">
                        <i class="bi bi-heart-pulse me-2"></i>Record Vitals
                    </button>
                    <button onclick="showSection('medications')" class="btn btn-outline-primary w-100 mb-2">
                        <i class="bi bi-capsule me-2"></i>Enter Medications
                    </button>
                    <button onclick="showSection('progress_notes')" class="btn btn-outline-primary w-100 mb-2">
                        <i class="bi bi-pencil-square me-2"></i>Progress Notes
                    </button>
                    <button onclick="showSection('diagnostics')" class="btn btn-outline-primary w-100 mb-2">
                        <i class="bi bi-search me-2"></i>Diagnostics / Imaging
                    </button>
                    <button onclick="showSection('treatment_plans')" class="btn btn-outline-primary w-100 mb-2">
                        <i class="bi bi-journal-text me-2"></i>Treatment Plans
                    </button>
                    <button onclick="showSection('lab_results')" class="btn btn-outline-primary w-100 mb-2">
                        <i class="bi bi-flask me-2"></i>Lab Results
                    </button>
                    <button onclick="showSection('medical_history')" class="btn btn-outline-primary w-100 mb-2">
                        <i class="bi bi-clipboard-data me-2"></i>Medical History
                    </button>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-md-9 ">
            <div class="card mb-4 content ">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-person-fill me-2"></i>Patient Dashboard - <?php echo htmlspecialchars($patient['fullname']); ?></h5>
                </div>
                <div class="card-body">
                    <!-- Default Content: Patient Information and Records -->
                    <div id="default-content">
                        <!-- Patient Information -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h6 class="mb-0">Personal Information</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>ID:</strong> <?php echo htmlspecialchars($patient['id']); ?></p>
                                        <p><strong>Name:</strong> <?php echo htmlspecialchars(html_entity_decode($patient['fullname'], ENT_QUOTES, 'UTF-8')); ?></p>
                                        <p><strong>Date of Birth:</strong> <?php echo htmlspecialchars(html_entity_decode($patient['dob'] ?: 'N/A', ENT_QUOTES, 'UTF-8')); ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Gender:</strong> <?php echo htmlspecialchars(html_entity_decode($patient['gender'] ?: 'N/A', ENT_QUOTES, 'UTF-8')); ?></p>
                                        <p><strong>Contact:</strong> <?php echo htmlspecialchars(html_entity_decode($patient['contact'] ?: 'N/A', ENT_QUOTES, 'UTF-8')); ?></p>
                                        <p><strong>Address:</strong> <?php echo htmlspecialchars(html_entity_decode($patient['address'] ?: 'N/A', ENT_QUOTES, 'UTF-8')); ?></p>
                                    </div>
                                </div>
                                <p><strong>Medical History:</strong> <?php echo htmlspecialchars(html_entity_decode($patient['history'] ?: 'No history recorded', ENT_QUOTES, 'UTF-8')); ?></p>
                            </div>
                        </div>
                        <!-- Medical Records Overview -->
                        <div class="row">
                            <?php
                            $recordTypes = [
                                ['key' => 'medical_history', 'title' => 'Medical History', 'fields' => ['condition_name', 'notes', 'status'], 'icon' => 'bi-clipboard-data'],
                                ['key' => 'medications', 'title' => 'Medications', 'fields' => ['medication', 'dose', 'start_date', 'notes'], 'icon' => 'bi-capsule'],
                                ['key' => 'vitals', 'title' => 'Vital Signs', 'fields' => ['recorded_by', 'bp', 'respiratory_rate', 'hr', 'temp', 'date_taken'], 'icon' => 'bi-heart-pulse'],
                                ['key' => 'diagnostics', 'title' => 'Diagnostics / Imaging', 'fields' => ['study_type', 'body_part_region', 'date_diagnosed'], 'icon' => 'bi-search'],
                                ['key' => 'treatment_plans', 'title' => 'Treatment Plans', 'fields' => ['plan', 'intervention', 'problems',], 'icon' => 'bi-journal-text'],
                                ['key' => 'progress_notes', 'title' => 'Progress Notes', 'fields' => ['focus', 'note', 'author'], 'icon' => 'bi-pencil-square'],
                                ['key' => 'lab_results', 'title' => 'Lab Results', 'fields' => ['test_name', 'test_category', 'test_code', 'date_taken'], 'icon' => 'bi-flask'],
                                ['key' => 'physical_assessments', 'title' => 'Physical Assessments', 'fields' => ['assessed_by', 'head_and_neck', 'cardiovascular', 'respiratory', 'date_assessed'], 'icon' => 'bi-clipboard-check']
                            ];

                            foreach ($recordTypes as $recordType) {
                                $records = $medical_data[$recordType['key']] ?? [];
                                ?>
                                <div class="col-md-6 mb-4">
                                    <div class="card h-100">
                                        <div class="card-header">
                                            <h6 class="mb-0"><i class="bi <?php echo $recordType['icon']; ?> me-2"></i><?php echo $recordType['title']; ?> (<?php echo count($records); ?>)</h6>
                                        </div>
                                        <div class="card-body">
                                            <?php if (count($records) > 0): ?>
                                                <div class="table-responsive">
                                                    <table class="table table-sm table-striped">
                                                        <thead>
                                                            <tr>
                                                                <?php foreach (array_slice($recordType['fields'], 0, 3) as $field): ?>
                                                                    <th><?php echo ucwords(str_replace('_', ' ', $field)); ?></th>
                                                                <?php endforeach; ?>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach (array_slice($records, 0, 5) as $record): ?>
                                                                <tr>
                                                                    <?php foreach (array_slice($recordType['fields'], 0, 3) as $field): ?>
                                                                        <td><?php echo htmlspecialchars($record[$field] ?? 'N/A'); ?></td>
                                                                    <?php endforeach; ?>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                                <?php if (count($records) > 5): ?>
                                                    <div class="text-center">
                                                        <a href="<?php echo strtolower(str_replace(' ', '_', $recordType['title'])); ?>.php?patient_id=<?php echo $patient_id; ?>" class="text-primary">View More...</a>
                                                    </div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <p class="text-muted">No records found</p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>
                    </div>

                    <!-- Vitals Section (Hidden by default) -->
                    <div id="vitals-content" style="display: none;">
                        <h4>Vital Signs</h4>

                        <!-- Feedback messages for vitals -->
                        <?php if (!empty($msg)): ?>
                            <div class="alert alert-success alert-dismissible fade show">
                                <?php echo htmlspecialchars($msg); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger alert-dismissible fade show">
                                <?php echo htmlspecialchars($error); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <!-- Vitals Form (adapted, patient fixed) -->
                        <div class="card p-3 mb-3">
                            <form method="post" class="row g-2">
                                <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
                                <div class="col-md-2"><input type="text" class="form-control" name="recorded_by" placeholder="Recorded By" value="<?php echo htmlspecialchars($_POST['recorded_by'] ?? ''); ?>" required></div>
                                <div class="col-md-2"><input class="form-control" name="bp" placeholder="BP (e.g., 120/80)" value="<?php echo htmlspecialchars($_POST['bp'] ?? ''); ?>" required></div>
                                <div class="col-md-2"><input class="form-control" name="respiratory_rate" placeholder="RR (cpm)" value="<?php echo htmlspecialchars($_POST['respiratory_rate'] ?? ''); ?>" required></div>
                                <div class="col-md-2"><input class="form-control" name="hr" placeholder="HR (bpm)" value="<?php echo htmlspecialchars($_POST['hr'] ?? ''); ?>" required></div>
                                <div class="col-md-2"><input step="0.1" class="form-control" name="temp" placeholder="Temp (°C)" value="<?php echo htmlspecialchars($_POST['temp'] ?? ''); ?>" required></div>
                                <div class="col-md-2"><input  step="0.1" class="form-control" name="height" placeholder="Height (cm)" value="<?php echo htmlspecialchars($_POST['height'] ?? ''); ?>" required></div>
                                <div class="col-md-2"><input step="0.1" class="form-control" name="weight" placeholder="Weight (kg)" value="<?php echo htmlspecialchars($_POST['weight'] ?? ''); ?>" required></div>
                                <div class="col-md-2"><input class="form-control" name="oxygen_saturation" placeholder="O2 Sat (%)" min="0" max="100" value="<?php echo htmlspecialchars($_POST['oxygen_saturation'] ?? ''); ?>"></div>
                                <div class="col-md-2"><input type="number" class="form-control" name="pain_scale" placeholder="Pain (0-10)" min="0" max="10" value="<?php echo htmlspecialchars($_POST['pain_scale'] ?? ''); ?>"></div>
                                <div class="col-md-2"><input type="date" class="form-control" name="date" value="<?php echo htmlspecialchars($_POST['date'] ?? date('Y-m-d')); ?>"></div>
                                <div class="col-md-7"><textarea style="white-space: pre-wrap;" class="form-control" name="general_appearance" placeholder="General Appearance(Patient appears comfortable, alert and oriented" rows="4"><?php echo htmlspecialchars($_POST['general_appearance'] ?? ''); ?></textarea></div>
                                <div class="col-12"><button name="add_vitals" class="btn btn-primary">Add Vitals</button></div>
                            </form>
                        </div>

                        <!-- Vitals Table -->
                        <div class="card p-3">
                            <table class="table table-sm table-bordered">
                                <thead>
                                    <tr>
                                        <th>Recorded By</th>
                                        <th>BP</th>
                                        <th>RR</th>
                                        <th>HR</th>
                                        <th>Temp</th>
                                        <th>Height</th>
                                        <th>Weight</th>
                                        <th>Oxygen Saturation</th>
                                        <th>Pain Scale</th>
                                        <th>Date</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $vitals = $medical_data['vitals'] ?? [];
                                    foreach ($vitals as $r): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($r['recorded_by']); ?></td>
                                            <td><?php echo htmlspecialchars($r['bp']); ?></td>
                                            <td><?php echo htmlspecialchars($r['respiratory_rate']); ?></td>
                                            <td><?php echo htmlspecialchars($r['hr']); ?></td>
                                            <td><?php echo htmlspecialchars($r['temp']); ?></td>
                                            <td><?php echo htmlspecialchars($r['height']); ?></td>
                                            <td><?php echo htmlspecialchars($r['weight']); ?></td>
                                            <td><?php echo htmlspecialchars($r['oxygen_saturation']); ?></td>
                                            <td><?php echo htmlspecialchars($r['pain_scale']); ?></td>
                                            <td><?php echo htmlspecialchars(substr($r['date_taken'], 0, 10)); ?></td>
                                            <td>
                                                <a class="btn btn-sm btn-danger" href="?delete_vital=<?php echo $r['id']; ?>&patient_id=<?php echo $patient_id; ?>&section=vitals" onclick="return confirm('Delete?')">
                                                    <i class="bi bi-trash"></i> Delete
                                                </a>
                                                <a class="btn btn-sm btn-warning" href="javascript:void(0)" onclick="editVital(<?php echo $r['id']; ?>)">
                                                    <i class="bi bi-pencil"></i> Edit
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <button class="btn btn-secondary mt-3" onclick="showSection('default')">Back to Dashboard</button>
                    </div>

                    <!-- Medications Section (Hidden by default) -->
                    <div id="medications-content" style="display: none;">
                        <h4>Medications</h4>

                        <!-- Feedback messages for medications -->
                        <?php if (!empty($msg)): ?>
                            <div class="alert alert-success alert-dismissible fade show">
                                <?php echo htmlspecialchars($msg); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger alert-dismissible fade show">
                                <?php echo htmlspecialchars($error); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <!-- Medications Form -->
                        <div class="card p-3 mb-3">
                            <form method="post">
                                <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
                                <div class="row g-2 mb-2">
                                    <div class="col-12">
                                        <input class="form-control" name="medication" placeholder="Medication" value="<?php echo htmlspecialchars($_POST['medication'] ?? ''); ?>" required>
                                    </div>
                                </div>
                                <div class="row g-2 mb-2">
                                    <div class="col-12">
                                        <input class="form-control" name="indication" placeholder="Indication" value="<?php echo htmlspecialchars($_POST['indication'] ?? ''); ?>" required>
                                    </div>
                                </div>
                                <div class="row g-2 mb-2">
                                    <div class="col-12">
                                        <input class="form-control" name="prescriber" placeholder="Prescriber(e.g. Dr.Name, MD)" value="<?php echo htmlspecialchars($_POST['prescriber'] ?? ''); ?>" required>
                                    </div>
                                </div>
                                <div class="row g-2 mb-2">
                                    <div class="col-md-3">
                                        <input class="form-control" name="dose" placeholder="Dose" value="<?php echo htmlspecialchars($_POST['dose'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-3">
                                        <select class="form-control" name="route" id="route_select" required>
                                            <option value="">Select Route</option>
                                            <option value="PO" <?php if (($_POST['route'] ?? '') == 'PO') echo 'selected'; ?>>PO</option>
                                            <option value="IV" <?php if (($_POST['route'] ?? '') == 'IV') echo 'selected'; ?>>IV</option>
                                            <option value="IM" <?php if (($_POST['route'] ?? '') == 'IM') echo 'selected'; ?>>IM</option>
                                            <option value="SC" <?php if (($_POST['route'] ?? '') == 'SC') echo 'selected'; ?>>SC</option>
                                            <option value="Topical" <?php if (($_POST['route'] ?? '') == 'Topical') echo 'selected'; ?>>Topical</option>
                                            <option value="Inhaled" <?php if (($_POST['route'] ?? '') == 'Inhaled') echo 'selected'; ?>>Inhaled</option>
                                            <option value="PR" <?php if (($_POST['route'] ?? '') == 'PR') echo 'selected'; ?>>PR</option>
                                            <option value="SL" <?php if (($_POST['route'] ?? '') == 'SL') echo 'selected'; ?>>SL</option>
                                            <option value="other" <?php if (($_POST['route'] ?? '') == 'other') echo 'selected'; ?>>Other</option>
                                        </select>
                                        <input type="text" class="form-control mt-1" name="custom_route" id="custom_route" placeholder="Specify Route" style="display: none;" value="<?php echo htmlspecialchars($_POST['custom_route'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <select class="form-control" name="status" required>
                                            <option value="">Select Status</option>
                                            <option value="Active" <?php if (($_POST['status'] ?? '') == 'Active') echo 'selected'; ?>>Active</option>
                                            <option value="Inactive" <?php if (($_POST['status'] ?? '') == 'Inactive') echo 'selected'; ?>>Inactive</option>
                                            <option value="Discontinued" <?php if (($_POST['status'] ?? '') == 'Discontinued') echo 'selected'; ?>>Discontinued</option>
                                            <option value="On Hold" <?php if (($_POST['status'] ?? '') == 'On Hold') echo 'selected'; ?>>On Hold</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <input class="form-control" name="start_date" type="date" value="<?php echo htmlspecialchars($_POST['start_date'] ?? date('Y-m-d')); ?>" required>
                                    </div>
                                </div>
                                <div class="row g-2 mb-2">
                                    <div class="col-12">
                                        <input class="form-control" name="notes" placeholder="Frequency" value="<?php echo htmlspecialchars($_POST['notes'] ?? ''); ?>" required>
                                    </div>
                                </div>
                                <div class="row g-2 mb-2">
                                    <div class="col-12">
                                        <textarea class="form-control" name="patient_instructions" placeholder="Patient Instructions: " rows="2" required><?php echo htmlspecialchars($_POST['patient_instructions'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                                <div class="row g-2 mb-2">
                                    <div class="col-12">
                                        <textarea class="form-control" name="pharmacy_instructions" placeholder="Pharmacy Instructions: " rows="2" required><?php echo htmlspecialchars($_POST['pharmacy_instructions'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                                <div class="row g-2">
                                    <div class="col-12">
                                        <button name="add_med" class="btn btn-primary">Add Medication</button>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <!-- Medications Table -->
                        <div class="card p-3">
                            <table class="table table-sm table-bordered">
                                <thead>
                                    <tr>
                                        <th>Prescriber</th>
                                        <th>Medication</th>
                                        <th>Dose</th>
                                        <th>Start Date</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $medications = $medical_data['medications'] ?? [];
                                    foreach ($medications as $r): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($r['prescriber']); ?></td>
                                            <td><?php echo htmlspecialchars($r['medication']); ?></td>
                                            <td><?php echo htmlspecialchars($r['dose']); ?></td>
                                            <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($r['start_date']))); ?></td>
                                            <td>
                                                <a class="btn btn-sm btn-danger" href="?delete_med=<?php echo $r['id']; ?>&patient_id=<?php echo $patient_id; ?>&section=medications" onclick="return confirm('Delete?')">
                                                    <i class="bi bi-trash"></i> Delete
                                                </a>
                                                <a class="btn btn-sm btn-warning" href="javascript:void(0)" onclick="editMed(<?php echo $r['id']; ?>)">
                                                    <i class="bi bi-pencil"></i> Edit
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <button class="btn btn-secondary mt-3" onclick="showSection('default')">Back to Dashboard</button>
                    </div>

                    <!-- Progress Notes Section (Hidden by default) -->
                    <div id="progress_notes-content" style="display: none;">
                        <h4>Progress Notes</h4>

                        <!-- Feedback messages for progress notes -->
                        <?php if (!empty($msg)): ?>
                            <div class="alert alert-success alert-dismissible fade show">
                                <?php echo htmlspecialchars($msg); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger alert-dismissible fade show">
                                <?php echo htmlspecialchars($error); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <!-- Progress Notes Form -->
                        <div class="card p-3 mb-3">
                            <form method="post" class="row g-2">
                                <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
                                <div class="col-md-12"><input class="form-control" name="focus" placeholder="Focus" value="<?php echo htmlspecialchars($_POST['focus'] ?? ''); ?>" required></div>
                                <textarea class="form-control" name="note" placeholder="Progress note" rows="3" required><?php 
                                    echo htmlspecialchars($_POST['note'] ?? ''); 
                                ?></textarea>
                                <div class="col-md-3"><input class="form-control" name="author" placeholder="Author" value="<?php echo htmlspecialchars($_POST['author'] ?? ''); ?>" required></div>
                                <div class="col-md-3"><input type="date" class="form-control" name="date" value="<?php echo htmlspecialchars($_POST['date'] ?? date('Y-m-d')); ?>" required></div>
                                <div class="col-12"><button name="add_note" class="btn btn-primary">Add Note</button></div>
                            </form>
                        </div>

                        <!-- Progress Notes Table -->
                        <div class="card p-3">
                            <table class="table table-sm table-bordered">
                                <thead>
                                    <tr>
                                        <th>Focus</th>
                                        <th>Note</th>
                                        <th>Author</th>
                                        <th>Date Written</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $notes = $medical_data['progress_notes'] ?? [];
                                    foreach ($notes as $r): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($r['focus'] ?? 'N/A'); ?></td>
                                            <td style="white-space: pre-wrap;"><?php echo htmlspecialchars($r['note']); ?></td>
                                            <td><?php echo htmlspecialchars($r['author'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($r['date_written'] ?? ''))); ?></td>
                                            <td>
                                                <a class="btn btn-sm btn-danger" href="?delete_note=<?php echo $r['id']; ?>&patient_id=<?php echo $patient_id; ?>&section=progress_notes" onclick="return confirm('Delete?')">
                                                    <i class="bi bi-trash"></i> Delete
                                                </a>
                                                <a class="btn btn-sm btn-warning" href="javascript:void(0)" onclick="editNote(<?php echo $r['id']; ?>)">
                                                    <i class="bi bi-pencil"></i> Edit
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <button class="btn btn-secondary mt-3" onclick="showSection('default')">Back to Dashboard</button>
                    </div>

                    <!-- Diagnostics Section (Hidden by default) -->
                    <div id="diagnostics-content" style="display: none;">
                        <h4>Diagnostics / Imaging</h4>

                        <!-- Feedback messages for diagnostics -->
                        <?php if (!empty($msg)): ?>
                            <div class="alert alert-success alert-dismissible fade show">
                                <?php echo htmlspecialchars($msg); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger alert-dismissible fade show">
                                <?php echo htmlspecialchars($error); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <!-- Diagnostics Form -->
                        <div class="card p-3 mb-3">
                            <form method="post" class="row g-2">
                                <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
                                <div class="col-md-4"> 
                                    <select class="form-control" name="study_type" id="study_type" required>
                                            <option value="">Select Study Type</option>
                                            <option value="X-RAY" <?php if (($_POST['study_type'] ?? '') == 'X-RAY') echo 'selected'; ?>>X-RAY</option>
                                            <option value="CT SCAN" <?php if (($_POST['study_type'] ?? '') == 'CT SCAN') echo 'selected'; ?>>CT SCAN</option>
                                            <option value="MRI" <?php if (($_POST['study_type'] ?? '') == 'MRI') echo 'selected'; ?>>MRI</option>
                                            <option value="Utrasound" <?php if (($_POST['study_type'] ?? '') == 'Utrasound') echo 'selected'; ?>>Utrasound</option>
                                            <option value="Nuclear Medicine" <?php if (($_POST['study_type'] ?? '') == 'Nuclear Medicine') echo 'selected'; ?>>Nuclear Medicine</option>
                                            <option value="PET Scan" <?php if (($_POST['study_type'] ?? '') == 'PET Scan') echo 'selected'; ?>>PET Scan</option>
                                            <option value="Mammography" <?php if (($_POST['study_type'] ?? '') == 'Mammography') echo 'selected'; ?>>Mammography</option>
                                            <option value="Fluroscopy" <?php if (($_POST['study_type'] ?? '') == 'Fluroscopy') echo 'selected'; ?>>Fluroscopy</option>
                                            <option value="other" <?php if (($_POST['study_type'] ?? '') == 'other') echo 'selected'; ?>>Other</option>
                                        </select>
                                        <input type="text" class="form-control mt-1" name="custom_study_type" id="custom_study_type" placeholder="Specify Study Type" style="display: none;" value="<?php echo htmlspecialchars($_POST['custom_study_type'] ?? ''); ?>">
                                    </div>
                                        <div class="col-md-4"><input class="form-control" name="body_part_region" placeholder="Body part/region(e.g., Chest, Left Knee)" value="<?php echo htmlspecialchars($_POST['body_part_region'] ?? ''); ?>" required></div>
                                        <div class="col-md-4"><input class="form-control" name="study_description" placeholder="Study Description" value="<?php echo htmlspecialchars($_POST['study_description'] ?? ''); ?>" required></div>
                                        <div class="col-md-4"><input class="form-control" name="clinical_indication" placeholder="Clinical Indication(Reason for ordering the study)" value="<?php echo htmlspecialchars($_POST['clinical_indication'] ?? ''); ?>" required></div>
                                    <div class="col-md-4"> 
                                        <select class="form-control" name="image_quality" id="image_quality" required>
                                            <option value="">Select Image quality</option>
                                            <option value="Excellent" <?php if (($_POST['image_quality'] ?? '') == 'Excellent') echo 'selected'; ?>>Excellent</option>
                                            <option value="Good" <?php if (($_POST['image_quality'] ?? '') == 'Good') echo 'selected'; ?>>Good</option>
                                            <option value="Fair" <?php if (($_POST['image_quality'] ?? '') == 'Fair') echo 'selected'; ?>>Fair</option>
                                            <option value="Poor" <?php if (($_POST['image_quality'] ?? '') == 'Poor') echo 'selected'; ?>>Poor</option>
                                            <option value="Limited" <?php if (($_POST['image_quality'] ?? '') == 'Limited') echo 'selected'; ?>>Limited</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4"><input class="form-control" name="order_by" placeholder="Order by(Dr.Name, MD)" value="<?php echo htmlspecialchars($_POST['order_by'] ?? ''); ?>" required></div>
                                    <div class="col-md-4"><input class="form-control" name="performed_by" placeholder="Performed by(Technologist name)" value="<?php echo htmlspecialchars($_POST['performed_by'] ?? ''); ?>" required></div>
                                    <div class="col-md-4"><input class="form-control" name="Interpreted_by" placeholder="Interpreted by(Dr.Name, MD)" value="<?php echo htmlspecialchars($_POST['Interpreted_by'] ?? ''); ?>" required></div>
                                    <div class="col-md-4"><input class="form-control" name="Imaging_facility" placeholder="Imaging Facility(Facility name)" value="<?php echo htmlspecialchars($_POST['Imaging_facility'] ?? ''); ?>" required></div>
                                    <div class="col-md-12"><textarea class="form-control" name="radiology_findings" placeholder="Radiological Findings(Detailed findings from the study)" rows="4" value="<?php echo htmlspecialchars($_POST['radiology_findings'] ?? ''); ?>" required></textarea></div>
                                    <div class="col-md-12"><textarea class="form-control" name="impression_conclusion" placeholder="Impression / Conclusion(Radiologist's impression and conclusion)" rows="4" value="<?php echo htmlspecialchars($_POST['impression_conclusion'] ?? ''); ?>" required></textarea></div>
                                    <div class="col-md-12"><textarea class="form-control" name="recommendations" placeholder="Recommendations( Follow-up recommendations)" rows="4" value="<?php echo htmlspecialchars($_POST['recommendations'] ?? ''); ?>" required></textarea></div>
                                <div class="col-md-4"><input class="form-control" name="date" type="date" value="<?php echo htmlspecialchars($_POST['date'] ?? date('Y-m-d')); ?>" required></div>
                                <div class="col-12"><button name="add_diagnostic" class="btn btn-primary">Add Diagnostic</button></div>
                            </form>
                        </div>

                        <!-- Diagnostics Table -->
                        <div class="card p-3">
                            <table class="table table-sm table-bordered">
                                <thead>
                                    <tr>
                                        <th>Problem</th>
                                        <th>Body part / Region</th>
                                        <th>Date diagnosed</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $diagnostics = $medical_data['diagnostics'] ?? [];
                                    foreach ($diagnostics as $r): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($r['study_type']); ?></td>
                                            <td style="white-space: pre-wrap;"><?php echo htmlspecialchars($r['body_part_region']); ?></td>
                                            <td><?php echo htmlspecialchars($r['date_diagnosed']); ?></td>
                                            <td>
                                                <a class="btn btn-sm btn-danger" href="?delete_diagnostic=<?php echo $r['id']; ?>&patient_id=<?php echo $patient_id; ?>&section=diagnostics" onclick="return confirm('Delete?')">
                                                    <i class="bi bi-trash"></i> Delete
                                                </a>
                                                <a class="btn btn-sm btn-warning" href="javascript:void(0)" onclick="editDiagnostic(<?php echo $r['id']; ?>)">
                                                    <i class="bi bi-pencil"></i> Edit
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <button class="btn btn-secondary mt-3" onclick="showSection('default')">Back to Dashboard</button>
                    </div>

                    <!-- Treatment Plans Section (Hidden by default) -->
                    <div id="treatment_plans-content" style="display: none;">
                        <h4>Treatment Plans</h4>

                        <!-- Feedback messages for treatment plans -->
                        <?php if (!empty($msg)): ?>
                            <div class="alert alert-success alert-dismissible fade show">
                                <?php echo htmlspecialchars($msg); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger alert-dismissible fade show">
                                <?php echo htmlspecialchars($error); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <!-- Treatment Plans Form -->
                        <div class="card p-3 mb-10">
                            <form method="post" class="row g-3">
                                <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">

                                <!-- Plan Type -->
                                <div class="col-md-4">
                                    <select class="form-control" name="plan" id="route_select" required>
                                        <option value="">Select Plan Type</option>
                                        <option value="Physician Order" <?php if (($_POST['plan'] ?? '') == 'Physician Order') echo 'selected'; ?>>Physician Order</option>
                                        <option value="Nursing Intervention" <?php if (($_POST['plan'] ?? '') == 'Nursing Intervention') echo 'selected'; ?>>Nursing Intervention</option>
                                        <option value="Procedure" <?php if (($_POST['plan'] ?? '') == 'Procedure') echo 'selected'; ?>>Procedure</option>
                                        <option value="Therapy" <?php if (($_POST['plan'] ?? '') == 'Therapy') echo 'selected'; ?>>Therapy</option>
                                        <option value="Patient Education" <?php if (($_POST['plan'] ?? '') == 'Patient Education') echo 'selected'; ?>>Patient Education</option>
                                        <option value="Referral" <?php if (($_POST['plan'] ?? '') == 'Referral') echo 'selected'; ?>>Referral</option>
                                    </select>
                                </div>

                                <!-- Intervention -->
                                <div class="col-md-4">
                                    <textarea class="form-control" name="intervention" placeholder="Intervention Description" rows="1"><?php echo htmlspecialchars($_POST['intervention'] ?? ''); ?></textarea>
                                </div>

                                <!-- Related Problems -->
                                <div class="col-md-4">
                                    <textarea class="form-control" name="problems" placeholder="Related Problems:" rows="1"><?php echo htmlspecialchars($_POST['problems'] ?? ''); ?></textarea>
                                </div>

                                <!-- Frequency -->
                                <div class="col-md-4">
                                    <textarea class="form-control" name="frequency" placeholder="e.g., 2 weeks, Until discharge" rows="1"><?php echo htmlspecialchars($_POST['frequency'] ?? ''); ?></textarea>
                                </div>

                                <!-- Duration -->
                                <div class="col-md-4">
                                    <textarea class="form-control" name="duration" placeholder="e.g., Daily, BIR, PRN" rows="1"><?php echo htmlspecialchars($_POST['duration'] ?? ''); ?></textarea>
                                </div>

                                <!-- Ordered By -->
                                <div class="col-md-4">
                                    <textarea class="form-control" name="order_by" placeholder="Healthcare provider name" rows="1"><?php echo htmlspecialchars($_POST['order_by'] ?? ''); ?></textarea>
                                </div>

                                <!-- Assigned To -->
                                <div class="col-md-4">
                                    <textarea class="form-control" name="assigned_to" placeholder="Responsible Healthcare provider" rows="1"><?php echo htmlspecialchars($_POST['assigned_to'] ?? ''); ?></textarea>
                                </div>

                                <!-- Start Date -->
                                <div class="col-md-4">
                                    <label for="date_started" class="form-control">Start Date</label>
                                    <input class="form-control" name="date_started" type="date" placeholder="Start Date" value="<?php echo htmlspecialchars($_POST['date_started'] ?? date('Y-m-d')); ?>">
                                </div>

                                <!-- End Date -->
                                <div class="col-md-4">
                                <label for="date_ended" class="form-control">End Date</label>
                                    <input class="form-control" name="date_ended" type="date" placeholder="End Date" value="<?php echo htmlspecialchars($_POST['date_ended'] ?? date('Y-m-d')); ?>">
                                </div>
                                <div class="col-md-12">
                                    <textarea class="form-control" name="special_instructions" placeholder="Special Instructions or Instructions..." rows="2"><?php echo htmlspecialchars($_POST['special_instructions'] ?? ''); ?></textarea>
                                </div>

                                <div class="col-md-12">
                                    <textarea class="form-control" name="patient_education_provided" placeholder="Education provided to patient and/or family..." rows="2"><?php echo htmlspecialchars($_POST['patient_education_provided'] ?? ''); ?></textarea>
                                </div>
                                <!-- Button -->
                                <div class="col-md-4 d-flex align-items-end">
                                    <button name="add_treatment_plan" class="btn btn-success w-100">Add Treatment Plan</button>
                                </div>
                            </form>
                        </div>


                        <!-- Treatment Plans Table -->
                        <div class="card p-3">
                            <table class="table table-sm table-bordered">
                                <thead>
                                    <tr>
                                        <th>Plan</th>
                                        <th>Intervention</th>
                                        <th>Related Problems</th>
                                        <th>Frequency</th>
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                        <th>Special Instructions</th>
                                        <th>Patient Education Provided</th>
                                        <th>Action</th>
                                        
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $treatment_plans = $medical_data['treatment_plans'] ?? [];
                                    foreach ($treatment_plans as $r): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($r['plan']); ?></td>
                                            <td><?php echo htmlspecialchars($r['intervention']); ?></td>
                                            <td><?php echo htmlspecialchars($r['problems']); ?></td>
                                            <td><?php echo htmlspecialchars($r['frequency']); ?></td>
                                            <td><?php echo htmlspecialchars($r['date_started']); ?></td>
                                            <td><?php echo htmlspecialchars($r['date_ended']); ?></td>
                                            <td><?php echo htmlspecialchars($r['special_instructions']); ?></td>
                                            <td><?php echo htmlspecialchars($r['patient_education_provided']); ?></td>
                                            <td class="action-btn">
                                                <a class="btn btn-sm btn-danger btn-delete" href="?delete_treatment_plan=<?php echo $r['id']; ?>&patient_id=<?php echo $patient_id; ?>&section=treatment_plans" onclick="return confirm('Delete?')">
                                                    <i class="bi bi-trash"></i> Delete
                                                </a>
                                                <a class="btn btn-sm btn-warning btn-edit" href="javascript:void(0)" onclick="editTreatmentPlan(<?php echo $r['id']; ?>)">
                                                    <i class="bi bi-pencil"></i> Edit
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <button class="btn btn-secondary mt-3" onclick="showSection('default')">Back to Dashboard</button>
                    </div>

                    <!-- Lab Results Section (Hidden by default) -->
                    <div id="lab_results-content" style="display: none;">
                        <h4>Lab Results</h4>

                        <!-- Feedback messages for lab results -->
                        <?php if (!empty($msg)): ?>
                            <div class="alert alert-success alert-dismissible fade show">
                                <?php echo htmlspecialchars($msg); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger alert-dismissible fade show">
                                <?php echo htmlspecialchars($error); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <!-- Lab Results Form -->
                        <div class="card p-3 mb-3">
                            <form method="post" class="row g-2">
                                <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
                                <div class="col-md-4"><input class="form-control" name="test_name" placeholder="Test Name" value="<?php echo htmlspecialchars($_POST['test_name'] ?? ''); ?>" required></div>
                                <div class="col-md-4"> 
                                    <select class="form-control" name="test_category" id="test_category" required>
                                        <option value="">Select Test Category</option>
                                        <option value="Hematology" <?php if (($_POST['test_category'] ?? '') == 'Hematologyr') echo 'selected'; ?>>Hematology</option>
                                        <option value="Chemistry" <?php if (($_POST['test_category'] ?? '') == 'Chemistry') echo 'selected'; ?>>Chemistry</option>
                                        <option value="Microbiology" <?php if (($_POST['test_category'] ?? '') == 'Microbiology') echo 'selected'; ?>>Microbiology</option>
                                        <option value="Immunology" <?php if (($_POST['test_category'] ?? '') == 'Immunology') echo 'selected'; ?>>Immunology</option>
                                        <option value="Pathology" <?php if (($_POST['test_category'] ?? '') == 'Pathology') echo 'selected'; ?>>Pathology</option>
                                        <option value="Genetics" <?php if (($_POST['test_category'] ?? '') == 'Genetics') echo 'selected'; ?>>Genetics</option>
                                        <option value="Endrinology" <?php if (($_POST['test_category'] ?? '') == 'Endrinology') echo 'selected'; ?>>Endrinology</option>
                                        <option value="other" <?php if (($_POST['test_category'] ?? '') == 'other') echo 'selected'; ?>>other</option>
                                    </select>
                                    <input type="text" class="form-control mt-1" name="custom_category" id="custom_category" placeholder="Specify Category" style="display: none;" value="<?php echo htmlspecialchars($_POST['custom_category'] ?? ''); ?>">
                                </div>
                                <div class="col-md-4"><input class="form-control" name="test_code" placeholder="Test Code(e.g., CBC)" value="<?php echo htmlspecialchars($_POST['test_code'] ?? ''); ?>" required></div>
                                <div class="col-md-4"><input class="form-control" name="result" placeholder="Result(e.g., 7.2)" value="<?php echo htmlspecialchars($_POST['result'] ?? ''); ?>" required></div>
                                <div class="col-md-4"> 
                                    <select class="form-control" name="result_status" id="result_status" required>
                                        <option value="">Select Result Status</option>
                                        <option value="Normal" <?php if (($_POST['result_status'] ?? '') == 'Hematologyr') echo 'selected'; ?>>Normal</option>
                                        <option value="High" <?php if (($_POST['result_status'] ?? '') == 'Chemistry') echo 'selected'; ?>>High</option>
                                        <option value="Low" <?php if (($_POST['result_status'] ?? '') == 'Microbiology') echo 'selected'; ?>>Low</option>
                                        <option value="Critical High" <?php if (($_POST['result_status'] ?? '') == 'Immunology') echo 'selected'; ?>>Critical High</option>
                                        <option value="Critical Low" <?php if (($_POST['result_status'] ?? '') == 'Pathology') echo 'selected'; ?>>Critical Low</option>
                                        <option value="Abnormal" <?php if (($_POST['result_status'] ?? '') == 'Pathology') echo 'selected'; ?>>Abnormal</option>
                                    </select>
                                </div>
                                <div class="col-md-4"><input class="form-control" name="units" placeholder="Units (e.g., mg/dL)" value="<?php echo htmlspecialchars($_POST['units'] ?? ''); ?>" required></div>
                                <div class="col-md-4"><input class="form-control" name="reference_range" placeholder="Reference range(e.g., 3.5-5.0)" value="<?php echo htmlspecialchars($_POST['reference_range'] ?? ''); ?>" required></div>
                                <div class="col-md-4"><input class="form-control" name="order_by" placeholder="Order by(Dr. name, MD)" value="<?php echo htmlspecialchars($_POST['order_by'] ?? ''); ?>" required></div>
                                <div class="col-md-4"><input class="form-control" name="collected_by" placeholder="Phlebotomist name" value="<?php echo htmlspecialchars($_POST['collected_by'] ?? ''); ?>" required></div>
                                <div class="col-md-4"><input class="form-control" name="laboratory_facility" placeholder="Lab facility name" value="<?php echo htmlspecialchars($_POST['laboratory_facility'] ?? ''); ?>" required></div>
                                <div class="col-md-4"><input type="date" class="form-control" name="date" value="<?php echo htmlspecialchars($_POST['date'] ?? date('Y-m-d')); ?>"></div>
                                <div class="col-md-8"><textarea class="form-control" name="clinical_interpretation" placeholder="Clinical significance and interpretation" rows="3"><?php echo htmlspecialchars($_POST['clinical_interpretation'] ?? ''); ?></textarea></div>
                                <div class="col-12"><button name="add_lab" class="btn btn-primary">Add Lab Result</button></div>
                            </form>
                        </div>

                        <!-- Lab Results Table -->
                        <div class="card p-3">
                            <table class="table table-sm table-bordered">
                                <thead>
                                    <tr>
                                        <th>Test Name</th>
                                        <th>Test Category</th>
                                        <th>Test Code</th>
                                        <th>Result</th>
                                        <th>Date Taken</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $labs = $medical_data['lab_results'] ?? [];
                                    foreach ($labs as $r): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($r['test_name']); ?></td>
                                            <td><?php echo htmlspecialchars($r['test_category']); ?></td>
                                            <td><?php echo htmlspecialchars($r['test_code']); ?></td>
                                            <td><?php echo htmlspecialchars($r['test_result']); ?></td>
                                            <td><?php echo htmlspecialchars(substr($r['date_taken'], 0, 10)); ?></td>
                                            <td>
                                                <a class="btn btn-sm btn-danger" href="?delete_lab=<?php echo $r['id']; ?>&patient_id=<?php echo $patient_id; ?>&section=lab_results" onclick="return confirm('Delete?')">
                                                    <i class="bi bi-trash"></i> Delete
                                                </a>
                                                <a class="btn btn-sm btn-warning" href="javascript:void(0)" onclick="editLab(<?php echo $r['id']; ?>)">
                                                    <i class="bi bi-pencil"></i> Edit
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <button class="btn btn-secondary mt-3" onclick="showSection('default')">Back to Dashboard</button>
                    </div>

                    <!-- Medical History Section (Hidden by default) -->
                    <div id="medical_history-content" style="display: none;">
                        <h4>Medical History</h4>

                        <!-- Feedback messages for medical history -->
                        <?php if (!empty($msg)): ?>
                            <div class="alert alert-success alert-dismissible fade show">
                                <?php echo htmlspecialchars($msg); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger alert-dismissible fade show">
                                <?php echo htmlspecialchars($error); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <!-- Medical History Form -->
                        <div class="card p-3 mb-3">
                            <form method="post" class="row g-2">
                                <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
                                <div class="col-md-3"><input class="form-control" name="condition_name" placeholder="Condition Name" value="<?php echo htmlspecialchars($_POST['condition_name'] ?? ''); ?>" required></div>
                                <div class="col-md-2">
                                    <select class="form-control" name="status" required>
                                        <option value="">Select Status</option>
                                        <option value="Active" <?php if (($_POST['status'] ?? '') == 'Active') echo 'selected'; ?>>Active</option>
                                        <option value="Resolved" <?php if (($_POST['status'] ?? '') == 'Resolved') echo 'selected'; ?>>Resolved</option>
                                        <option value="Chronic" <?php if (($_POST['status'] ?? '') == 'Chronic') echo 'selected'; ?>>Chronic</option>
                                    </select>
                                </div>
                                <div class="col-md-3"><textarea class="form-control" name="notes" placeholder="Notes" rows="2" required><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea></div>
                                <div class="col-md-4"><input class="form-control" name="date" type="date" value="<?php echo htmlspecialchars($_POST['date'] ?? date('Y-m-d')); ?>" required></div>
                                <div class="col-12"><button name="add_medical_history" class="btn btn-primary">Add Medical History</button></div>
                            </form>
                        </div>

                        <!-- Medical History Table -->
                        <div class="card p-3">
                            <table class="table table-sm table-bordered">
                                <thead>
                                    <tr>
                                        <th>Condition Name</th>
                                        <th>Status</th>
                                        <th>Notes</th>
                                        <th>Date Recorded</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $history = $medical_data['medical_history'] ?? [];
                                    foreach ($history as $r): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($r['condition_name']); ?></td>
                                            <td>
                                                <span class="badge <?php
                                                    if ($r['status'] == 'Active') echo 'bg-success';
                                                    elseif ($r['status'] == 'Resolved') echo 'bg-secondary';
                                                    elseif ($r['status'] == 'Chronic') echo 'bg-warning';
                                                    else echo 'bg-primary';
                                                ?>"><?php echo htmlspecialchars($r['status']); ?></span>
                                            </td>
                                            <td><?php echo htmlspecialchars($r['notes']); ?></td>
                                            <td><?php echo htmlspecialchars($r['date_recorded']); ?></td>
                                            <td>
                                                <a class="btn btn-sm btn-danger" href="?delete_medical_history=<?php echo $r['id']; ?>&patient_id=<?php echo $patient_id; ?>&section=medical_history" onclick="return confirm('Delete?')">
                                                    <i class="bi bi-trash"></i> Delete
                                                </a>
                                                <a class="btn btn-sm btn-warning" href="javascript:void(0)" onclick="editMedicalHistory(<?php echo $r['id']; ?>)">
                                                    <i class="bi bi-pencil"></i> Edit
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <button class="btn btn-secondary mt-3" onclick="showSection('default')">Back to Dashboard</button>
                    </div>

                    <!-- Physical Assessment Section (Hidden by default) -->
                    <div id="physical_assessment-content" style="display: none;">
                        <h4>Physical Assessment</h4>

                        <!-- Feedback messages for physical assessment -->
                        <?php if (!empty($msg)): ?>
                            <div class="alert alert-success alert-dismissible fade show">
                                <?php echo htmlspecialchars($msg); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger alert-dismissible fade show">
                                <?php echo htmlspecialchars($error); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <!-- Physical Assessment Form -->
                        <div class="card p-3 mb-3">
                            <form method="post" class="row g-2">
                                <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
                                <div class="col-md-6"><input class="form-control" name="assessed_by" placeholder="Assessed By" value="<?php echo htmlspecialchars($_POST['assessed_by'] ?? ''); ?>" required></div>
                                <div class="col-md-6"><textarea class="form-control" name="head_and_neck" placeholder="Head and Neck" rows="2" required><?php echo htmlspecialchars($_POST['head_and_neck'] ?? ''); ?></textarea></div>
                                <div class="col-md-6"><textarea class="form-control" name="cardiovascular" placeholder="Cardiovascular" rows="2" required><?php echo htmlspecialchars($_POST['cardiovascular'] ?? ''); ?></textarea></div>
                                <div class="col-md-6"><textarea class="form-control" name="respiratory" placeholder="Respiratory" rows="2" required><?php echo htmlspecialchars($_POST['respiratory'] ?? ''); ?></textarea></div>
                                <div class="col-md-6"><textarea class="form-control" name="abdominal" placeholder="Abdominal" rows="2" required><?php echo htmlspecialchars($_POST['abdominal'] ?? ''); ?></textarea></div>
                                <div class="col-md-6"><textarea class="form-control" name="neurological" placeholder="Neurological" rows="2" required><?php echo htmlspecialchars($_POST['neurological'] ?? ''); ?></textarea></div>
                                <div class="col-md-6"><textarea class="form-control" name="musculoskeletal" placeholder="Musculoskeletal" rows="2" required><?php echo htmlspecialchars($_POST['musculoskeletal'] ?? ''); ?></textarea></div>
                                <div class="col-md-6"><textarea class="form-control" name="skin" placeholder="Skin" rows="2" required><?php echo htmlspecialchars($_POST['skin'] ?? ''); ?></textarea></div>
                                <div class="col-md-6"><textarea class="form-control" name="psychiatric" placeholder="Psychiatric" rows="2" required><?php echo htmlspecialchars($_POST['psychiatric'] ?? ''); ?></textarea></div>
                                <div class="col-md-6"><input class="form-control" name="date" type="date" value="<?php echo htmlspecialchars($_POST['date'] ?? date('Y-m-d')); ?>" required></div>
                                <div class="col-12"><button name="add_physical_assessment" class="btn btn-primary">Add Physical Assessment</button></div>
                            </form>
                        </div>

                        <!-- Physical Assessment Table -->
                        <div class="card p-3">
                            <table class="table table-sm table-bordered">
                                <thead>
                                    <tr>
                                        <th>Assessed By</th>
                                        <th>Head and Neck</th>
                                        <th>Cardiovascular</th>
                                        <th>Respiratory</th>
                                        <th>Date Assessed</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $assessments = $medical_data['physical_assessments'] ?? [];
                                    foreach ($assessments as $r): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($r['assessed_by']); ?></td>
                                            <td><?php echo htmlspecialchars($r['head_and_neck']); ?></td>
                                            <td><?php echo htmlspecialchars($r['cardiovascular']); ?></td>
                                            <td><?php echo htmlspecialchars($r['respiratory']); ?></td>
                                            <td><?php echo htmlspecialchars($r['date_assessed']); ?></td>
                                            <td>
                                                <a class="btn btn-sm btn-danger" href="?delete_physical_assessment=<?php echo $r['id']; ?>&patient_id=<?php echo $patient_id; ?>&section=physical_assessment" onclick="return confirm('Delete?')">
                                                    <i class="bi bi-trash"></i> Delete
                                                </a>
                                                <a class="btn btn-sm btn-warning" href="javascript:void(0)" onclick="editPhysicalAssessment(<?php echo $r['id']; ?>)">
                                                    <i class="bi bi-pencil"></i> Edit
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <button class="btn btn-secondary mt-3" onclick="showSection('default')">Back to Dashboard</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
<script>
    const patient_id = <?php echo $patient_id; ?>;

    function showSection(section) {
    // Hide all sections
    document.getElementById('default-content').style.display = 'none';
    document.getElementById('vitals-content').style.display = 'none';
    document.getElementById('medications-content').style.display = 'none';
    document.getElementById('progress_notes-content').style.display = 'none';
    document.getElementById('diagnostics-content').style.display = 'none';
    document.getElementById('treatment_plans-content').style.display = 'none';
    document.getElementById('lab_results-content').style.display = 'none';
    document.getElementById('medical_history-content').style.display = 'none';
    document.getElementById('physical_assessment-content').style.display = 'none';
    // Show selected
    if (section === 'default') {
        document.getElementById('default-content').style.display = 'block';
        // Update URL to remove section parameter
        history.pushState(null, '', '?patient_id=' + patient_id);
    } else if (section === 'vitals') {
        document.getElementById('vitals-content').style.display = 'block';
        // Update URL to include section=vitals
        history.pushState(null, '', '?patient_id=' + patient_id + '&section=vitals');
    } else if (section === 'medications') {
        document.getElementById('medications-content').style.display = 'block';
        // Update URL to include section=medications
        history.pushState(null, '', '?patient_id=' + patient_id + '&section=medications');
    } else if (section === 'progress_notes') {
        document.getElementById('progress_notes-content').style.display = 'block';
        // Update URL to include section=progress_notes
        history.pushState(null, '', '?patient_id=' + patient_id + '&section=progress_notes');
    } else if (section === 'diagnostics') {
        document.getElementById('diagnostics-content').style.display = 'block';
        // Update URL to include section=diagnostics
        history.pushState(null, '', '?patient_id=' + patient_id + '&section=diagnostics');
    } else if (section === 'treatment_plans') {
        document.getElementById('treatment_plans-content').style.display = 'block';
        // Update URL to include section=treatment_plans
        history.pushState(null, '', '?patient_id=' + patient_id + '&section=treatment_plans');
    } else if (section === 'lab_results') {
        document.getElementById('lab_results-content').style.display = 'block';
        // Update URL to include section=lab_results
        history.pushState(null, '', '?patient_id=' + patient_id + '&section=lab_results');
    } else if (section === 'medical_history') {
        document.getElementById('medical_history-content').style.display = 'block';
        // Update URL to include section=medical_history
        history.pushState(null, '', '?patient_id=' + patient_id + '&section=medical_history');
    } else if (section === 'physical_assessment') {
        document.getElementById('physical_assessment-content').style.display = 'block';
        // Update URL to include section=physical_assessment
        history.pushState(null, '', '?patient_id=' + patient_id + '&section=physical_assessment');
    }
}

function toggleCustomRoute(selectId, inputId) {
    const select = document.getElementById(selectId);
    const input = document.getElementById(inputId);
    if (select.value === 'other') {
        input.style.display = 'block';
    } else {
        input.style.display = 'none';
        input.value = '';
    }
}

// Add event listeners for route selects
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('route_select').addEventListener('change', function() {
        toggleCustomRoute('route_select', 'custom_route');
    });
    document.getElementById('route_edit').addEventListener('change', function() {
        toggleCustomRoute('route_edit', 'custom_route_edit');
    });
});

function editMed(id) {
    fetch('?get_med=' + id)
        .then(response => response.json())
        .then(data => {
            document.getElementById('med_id').value = data.id;
            document.getElementById('medication').value = data.medication;
            document.getElementById('indication').value = data.indication;
            document.getElementById('prescriber').value = data.prescriber;
            document.getElementById('dose').value = data.dose;
            document.getElementById('start_date').value = data.start_date;
            document.getElementById('notes').value = data.notes;
            document.getElementById('status').value = data.status;
            document.getElementById('patient_instructions').value = data.patient_instructions;
            document.getElementById('pharmacy_instructions').value = data.pharmacy_instructions;
            // Handle route
            const routeOptions = ['PO', 'IV', 'IM', 'SC', 'Topical', 'Inhaled', 'PR', 'SL'];
            if (routeOptions.includes(data.route)) {
                document.getElementById('route_edit').value = data.route;
                document.getElementById('custom_route_edit').style.display = 'none';
                document.getElementById('custom_route_edit').value = '';
            } else {
                document.getElementById('route_edit').value = 'other';
                document.getElementById('custom_route_edit').style.display = 'block';
                document.getElementById('custom_route_edit').value = data.route;
            }
            new bootstrap.Modal(document.getElementById('editMedModal')).show();
        })
        .catch(error => console.error('Error:', error));
}

function editVital(id) {
    fetch('&get_vital=' + id)
        .then(response => response.json())
        .then(data => {
            document.getElementById('vital_id').value = data.id;
            document.getElementById('bp_edit').value = data.bp;
            document.getElementById('respiratory_rate_edit').value = data.respiratory_rate
            document.getElementById('hr_edit').value = data.hr;
            document.getElementById('temp_edit').value = data.temp;
            document.getElementById('height_edit').value = data.height;
            document.getElementById('weight_edit').value = data.weight;
            document.getElementById('oxygen_saturation_edit').value = data.oxygen_saturation;
            document.getElementById('pain_scale_edit').value = data.pain_scale;
            document.getElementById('ga_edit').value = date.general_appearance;
            document.getElementById('date_edit').value = data.date_taken.substring(0, 10);
            new bootstrap.Modal(document.getElementById('editVitalModal')).show();
        })
        .catch(error => console.error('Error:', error));
}

function editNote(id) {
    fetch('?get_note=' + id)
        .then(response => response.json())
        .then(data => {
            document.getElementById('note_id').value = data.id;
            document.getElementById('note_edit').value = data.note;
            document.getElementById('author_edit').value = data.author;
            document.getElementById('date_note_edit').value = data.date_written.replace(' ', 'T');
            new bootstrap.Modal(document.getElementById('editNoteModal')).show();
        })
        .catch(error => console.error('Error:', error));
}

function editTreatmentPlan(id) {
    fetch('?get_treatment_plan=' + id)
        .then(response => response.json())
        .then(data => {
            document.getElementById('treatment_plan_id').value = data.id;
            document.getElementById('plan_edit').value = data.plan;
            document.getElementById('notes_plan_edit').value = data.notes;
            document.getElementById('date_plan_edit').value = data.date_planned.substring(0, 10);
            new bootstrap.Modal(document.getElementById('editTreatmentPlanModal')).show();
        })
        .catch(error => console.error('Error:', error));
}

function editLab(id) {
    fetch('?get_lab=' + id)
        .then(response => response.json())
        .then(data => {
            document.getElementById('lab_id').value = data.id;
            document.getElementById('test_name_edit').value = data.test_name;
            document.getElementById('result_edit').value = data.test_result;
            document.getElementById('date_lab_edit').value = data.date_taken.replace(' ', 'T');
            new bootstrap.Modal(document.getElementById('editLabModal')).show();
        })
        .catch(error => console.error('Error:', error));
}

function editMedicalHistory(id) {
    fetch('?get_medical_history=' + id)
        .then(response => response.json())
        .then(data => {
            document.getElementById('history_id').value = data.id;
            document.getElementById('condition_name_edit').value = data.condition_name;
            document.getElementById('notes_history_edit').value = data.notes;
            document.getElementById('date_history_edit').value = data.date_recorded.substring(0, 10);
            new bootstrap.Modal(document.getElementById('editMedicalHistoryModal')).show();
        })
        .catch(error => console.error('Error:', error));
}

function editPhysicalAssessment(id) {
    fetch('?get_physical_assessment=' + id)
        .then(response => response.json())
        .then(data => {
            document.getElementById('assessment_id').value = data.id;
            document.getElementById('assessed_by_edit').value = data.assessed_by;
            document.getElementById('head_and_neck_edit').value = data.head_and_neck;
            document.getElementById('cardiovascular_edit').value = data.cardiovascular;
            document.getElementById('respiratory_edit').value = data.respiratory;
            document.getElementById('abdominal_edit').value = data.abdominal;
            document.getElementById('neurological_edit').value = data.neurological;
            document.getElementById('musculoskeletal_edit').value = data.musculoskeletal;
            document.getElementById('skin_edit').value = data.skin;
            document.getElementById('psychiatric_edit').value = data.psychiatric;
            document.getElementById('date_assessment_edit').value = data.date_assessed.substring(0, 10);
            new bootstrap.Modal(document.getElementById('editPhysicalAssessmentModal')).show();
        })
        .catch(error => console.error('Error:', error));
}

// Initially show default content or section from URL
const urlParams = new URLSearchParams(window.location.search);
let section = urlParams.get('section') || 'default';
// If a form was submitted, stay on the submitted section
if ('<?php echo $submitted_section; ?>' !== '') {
    section = '<?php echo $submitted_section; ?>';
}
showSection(section);   
</script>
<?php include "footer.php"; ?>  