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
$tables = ['medical_history', 'medications', 'vitals', 'diagnostics', 'treatment_plans', 'progress_notes', 'lab_results'];

foreach ($tables as $table) {
    $stmt = $conn->prepare("SELECT * FROM `$table` WHERE patient_id = ? ORDER BY id DESC");
    if ($stmt && $stmt->bind_param("i", $patient_id) && $stmt->execute()) {
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
    $bp = $_POST['bp'] ?? "";
    $hr = $_POST['hr'] ?? "";
    $temp = $_POST['temp'] ?? "";
    $height = $_POST['height'] ?? "";
    $weight = $_POST['weight'] ?? "";
    $date = $_POST['date'] ?: date("Y-m-d");

    // Validate blood pressure format (systolic/diastolic)
    if (!empty($bp) && !preg_match('/^\d{2,3}\/\d{2,3}$/', $bp)) {
        $error = "Blood pressure must be in format 'systolic/diastolic' (e.g., 120/80)";
    }
    // Validate heart rate (40-220 bpm)
    elseif (!empty($hr) && (!is_numeric($hr) || $hr < 40 || $hr > 220)) {
        $error = "Heart rate must be between 40-220 bpm";
    }
    // Validate temperature (35-42°C)
    elseif (!empty($temp) && (!is_numeric($temp) || $temp < 35 || $temp > 42)) {
        $error = "Temperature must be between 35-42°C";
    }
    // Validate height (30-250 cm)
    elseif (!empty($height) && (!is_numeric($height) || $height < 30 || $height > 250)) {
        $error = "Height must be between 30-250 cm";
    }
    // Validate weight (0.5-500 kg)
    elseif (!empty($weight) && (!is_numeric($weight) || $weight < 0.5 || $weight > 500)) {
        $error = "Weight must be between 0.5-500 kg";
    }
    // Validate date format if provided
    elseif (!empty($_POST['date']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $error = "Date must be in format YYYY-MM-DD.";
    }
    else {
        $stmt = $conn->prepare("INSERT INTO vitals (patient_id, bp, hr, temp, height, weight, date_taken) VALUES (?,?,?,?,?,?,?)");
        $stmt->bind_param("isdddds", $patient_id, $bp, $hr, $temp, $height, $weight, $date);
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
        header("Location: vitals.php?patient_id=$patient_id");
        exit();
    }
    $stmt->close();
}

// Handle update vitals
if (isset($_POST['update_vitals'])) {
    $vid = intval($_POST['vital_id']);
    $bp = $_POST['bp'] ?? "";
    $hr = $_POST['hr'] ?? "";
    $temp = $_POST['temp'] ?? "";
    $height = $_POST['height'] ?? "";
    $weight = $_POST['weight'] ?? "";
    $date = $_POST['date'] ?: date("Y-m-d");

    // Validate (same as add)
    if (!empty($bp) && !preg_match('/^\d{2,3}\/\d{2,3}$/', $bp)) {
        $error = "Blood pressure must be in format 'systolic/diastolic' (e.g., 120/80)";
    }
    elseif (!empty($hr) && (!is_numeric($hr) || $hr < 40 || $hr > 220)) {
        $error = "Heart rate must be between 40-220 bpm";
    }
    elseif (!empty($temp) && (!is_numeric($temp) || $temp < 35 || $temp > 42)) {
        $error = "Temperature must be between 35-42°C";
    }
    elseif (!empty($height) && (!is_numeric($height) || $height < 30 || $height > 250)) {
        $error = "Height must be between 30-250 cm";
    }
    elseif (!empty($weight) && (!is_numeric($weight) || $weight < 0.5 || $weight > 500)) {
        $error = "Weight must be between 0.5-500 kg";
    }
    elseif (!empty($_POST['date']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $error = "Date must be in format YYYY-MM-DD.";
    }
    else {
        $stmt = $conn->prepare("UPDATE vitals SET bp=?, hr=?, temp=?, height=?, weight=?, date_taken=? WHERE id=? AND patient_id=?");
        $stmt->bind_param("sddddsii", $bp, $hr, $temp, $height, $weight, $date, $vid, $patient_id);
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



// Medications processing (adapted from medications.php)
if (isset($_POST['add_med'])) {
    $med = $_POST['medication'] ?? "";
    $dose = $_POST['dose'] ?? "";
    $start = $_POST['start_date'] ?? "";
    $notes = $_POST['notes'] ?? "";

    // Validate all fields (required)
    if (empty($med)) {
        $error = "Medication is required.";
    }
    elseif (empty($dose)) {
        $error = "Dose is required.";
    }
    elseif (empty($start)) {
        $error = "Start Date is required.";
    }
    elseif (empty($notes)) {
        $error = "Notes is required.";
    }
    else {
        $stmt = $conn->prepare("INSERT INTO medications (patient_id, medication, dose, start_date, notes) VALUES (?,?,?,?,?)");
        $stmt->bind_param("issss", $patient_id, $med, $dose, $start, $notes);
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
    if ($stmt->execute()) $msg = "Deleted.";
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

// Handle update medications
if (isset($_POST['update_med'])) {
    $mid = intval($_POST['med_id']);
    $med = $_POST['medication'] ?? "";
    $dose = $_POST['dose'] ?? "";
    $start = $_POST['start_date'] ?? "";
    $notes = $_POST['notes'] ?? "";

    // Validate medication (required)
    if (empty($med)) {
        $error = "Medication is required.";
    }
    else {
        $stmt = $conn->prepare("UPDATE medications SET medication=?, dose=?, start_date=?, notes=? WHERE id=? AND patient_id=?");
        $stmt->bind_param("ssssii", $med, $dose, $start, $notes, $mid, $patient_id);
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
    $note = sanitize_input($conn, $_POST['note'] ?? "");
    $author = sanitize_input($conn, $_POST['author'] ?? "");
    $date = $_POST['date'] ?: date("Y-m-d");

    // Validate date format if provided
    if (!empty($_POST['date']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $error = "Date must be in format YYYY-MM-DD.";
    } else {
        $stmt = $conn->prepare("INSERT INTO progress_notes (patient_id, note, author, date_written) VALUES (?,?,?,?)");
        $stmt->bind_param("isss", $patient_id, $note, $author, $date);
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
    $note = sanitize_input($conn, $_POST['note'] ?? "");
    $author = sanitize_input($conn, $_POST['author'] ?? "");
    $date = $_POST['date'] ?: date("Y-m-d H:i:s");
    if (!empty($_POST['date'])) {
        $date = str_replace('T', ' ', $date);
    }

    // Validate date format if provided
    if (!empty($_POST['date']) && !preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2})?$/', $date)) {
        $error = "Invalid date format. Use YYYY-MM-DDTHH:MM.";
    } else {
        $stmt = $conn->prepare("UPDATE progress_notes SET note=?, author=?, date_written=? WHERE id=? AND patient_id=?");
        $stmt->bind_param("sssii", $note, $author, $date, $nid, $patient_id);
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
    $problem = sanitize_input($conn, $_POST['problem'] ?? "");
    $diagnosis = sanitize_input($conn, $_POST['diagnosis'] ?? "");
    $date = $_POST['date'] ?: date("Y-m-d");

    // Validate date format if provided
    if (!empty($_POST['date']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $error = "Date must be in format YYYY-MM-DD.";
    } else {
        $stmt = $conn->prepare("INSERT INTO diagnostics (patient_id, problem, diagnosis, date_diagnosed) VALUES (?,?,?,?)");
        $stmt->bind_param("isss", $patient_id, $problem, $diagnosis, $date);
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
    $did = intval($_POST['diagnostic_id']);
    $problem = sanitize_input($conn, $_POST['problem'] ?? "");
    $diagnosis = sanitize_input($conn, $_POST['diagnosis'] ?? "");
    $date = $_POST['date'] ?: date("Y-m-d");

    // Validate date format if provided
    if (!empty($_POST['date']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $error = "Date must be in format YYYY-MM-DD.";
    } else {
        $stmt = $conn->prepare("UPDATE diagnostics SET problem=?, diagnosis=?, date_diagnosed=? WHERE id=? AND patient_id=?");
        $stmt->bind_param("sssii", $problem, $diagnosis, $date, $did, $patient_id);
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
    $notes = sanitize_input($conn, $_POST['notes'] ?? "");
    $date = $_POST['date'] ?: date("Y-m-d");

    // Validate date format if provided
    if (!empty($_POST['date']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $error = "Date must be in format YYYY-MM-DD.";
    } else {
        $stmt = $conn->prepare("INSERT INTO treatment_plans (patient_id, plan, notes, date_planned) VALUES (?,?,?,?)");
        $stmt->bind_param("isss", $patient_id, $plan, $notes, $date);
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
    $notes = sanitize_input($conn, $_POST['notes'] ?? "");
    $date = $_POST['date'] ?: date("Y-m-d");

    // Validate date format if provided
    if (!empty($_POST['date']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $error = "Date must be in format YYYY-MM-DD.";
    } else {
        $stmt = $conn->prepare("UPDATE treatment_plans SET plan=?, notes=?, date_planned=? WHERE id=? AND patient_id=?");
        $stmt->bind_param("sssii", $plan, $notes, $date, $tid, $patient_id);
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
    $date = $_POST['date'] ?: date("Y-m-d H:i:s");

    // Validate date format if provided
    if (!empty($_POST['date']) && !preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/', $date)) {
        $error = "Invalid date format. Use YYYY-MM-DD or YYYY-MM-DD HH:MM:SS.";
    }
    // Validate common lab test results with appropriate ranges
    elseif (strtolower($test) == "glucose" && is_numeric($result) && ($result < 70 || $result > 200)) {
        $error = "Warning: Glucose value ($result mg/dL) is outside normal range (70-200 mg/dL). Please verify.";
    }
    elseif (strtolower($test) == "hemoglobin" && is_numeric($result) && ($result < 7 || $result > 20)) {
        $error = "Warning: Hemoglobin value ($result g/dL) is outside normal range (7-20 g/dL). Please verify.";
    }
    elseif (strtolower($test) == "cholesterol" && is_numeric($result) && ($result < 100 || $result > 300)) {
        $error = "Warning: Cholesterol value ($result mg/dL) is outside normal range (100-300 mg/dL). Please verify.";
    }
    elseif (strtolower($test) == "wbc" && is_numeric($result) && ($result < 3 || $result > 15)) {
        $error = "Warning: White Blood Cell count ($result K/uL) is outside normal range (3-15 K/uL). Please verify.";
    }
    elseif (strtolower($test) == "platelet" && is_numeric($result) && ($result < 100 || $result > 500)) {
        $error = "Warning: Platelet count ($result K/uL) is outside normal range (100-500 K/uL). Please verify.";
    }
    elseif (strtolower($test) == "creatinine" && is_numeric($result) && ($result < 0.5 || $result > 2.0)) {
        $error = "Warning: Creatinine value ($result mg/dL) is outside normal range (0.5-2.0 mg/dL). Please verify.";
    }
    else {
        $stmt = $conn->prepare("INSERT INTO lab_results (patient_id, test_name, test_result, date_taken) VALUES (?,?,?,?)");
        $stmt->bind_param("isss", $patient_id, $test, $result, $date);
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
    $date = $_POST['date'] ?: date("Y-m-d H:i:s");

    // Validate date format if provided
    if (!empty($_POST['date']) && !preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/', $date)) {
        $error = "Invalid date format. Use YYYY-MM-DD or YYYY-MM-DD HH:MM:SS.";
    }
    // Validate common lab test results with appropriate ranges
    elseif (strtolower($test) == "glucose" && is_numeric($result) && ($result < 70 || $result > 200)) {
        $error = "Warning: Glucose value ($result mg/dL) is outside normal range (70-200 mg/dL). Please verify.";
    }
    elseif (strtolower($test) == "hemoglobin" && is_numeric($result) && ($result < 7 || $result > 20)) {
        $error = "Warning: Hemoglobin value ($result g/dL) is outside normal range (7-20 g/dL). Please verify.";
    }
    elseif (strtolower($test) == "cholesterol" && is_numeric($result) && ($result < 100 || $result > 300)) {
        $error = "Warning: Cholesterol value ($result mg/dL) is outside normal range (100-300 mg/dL). Please verify.";
    }
    elseif (strtolower($test) == "wbc" && is_numeric($result) && ($result < 3 || $result > 15)) {
        $error = "Warning: White Blood Cell count ($result K/uL) is outside normal range (3-15 K/uL). Please verify.";
    }
    elseif (strtolower($test) == "platelet" && is_numeric($result) && ($result < 100 || $result > 500)) {
        $error = "Warning: Platelet count ($result K/uL) is outside normal range (100-500 K/uL). Please verify.";
    }
    elseif (strtolower($test) == "creatinine" && is_numeric($result) && ($result < 0.5 || $result > 2.0)) {
        $error = "Warning: Creatinine value ($result mg/dL) is outside normal range (0.5-2.0 mg/dL). Please verify.";
    }
    else {
        $stmt = $conn->prepare("UPDATE lab_results SET test_name=?, test_result=?, date_taken=? WHERE id=? AND patient_id=?");
        $stmt->bind_param("sssii", $test, $result, $date, $lid, $patient_id);
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
    $notes = sanitize_input($conn, $_POST['notes'] ?? "");
    $date = $_POST['date'] ?: date("Y-m-d");

    // Validate date format if provided
    if (!empty($_POST['date']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $error = "Date must be in format YYYY-MM-DD.";
    } else {
        $stmt = $conn->prepare("INSERT INTO medical_history (patient_id, condition_name, notes, date_recorded) VALUES (?,?,?,?)");
        $stmt->bind_param("isss", $patient_id, $condition, $notes, $date);
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
                        <label for="dose" class="form-label">Dose</label>
                        <input type="text" class="form-control" name="dose" id="dose" placeholder="Dose">
                    </div>
                    <div class="mb-3">
                        <label for="start_date" class="form-label">Start Date</label>
                        <input type="date" class="form-control" name="start_date" id="start_date">
                    </div>
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" id="notes" placeholder="Notes"></textarea>
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
                            <label for="bp_edit" class="form-label">Blood Pressure</label>
                            <input type="text" class="form-control" name="bp" id="bp_edit" placeholder="BP (e.g., 120/80)" required>
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
                        <input type="datetime-local" class="form-control" name="date" id="date_note_edit" required>
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

<!-- Edit Modal for Diagnostics -->
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
                        <label for="problem_edit" class="form-label">Problem</label>
                        <input type="text" class="form-control" name="problem" id="problem_edit" placeholder="Problem" required>
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
                        <label for="plan_edit" class="form-label">Treatment Plan</label>
                        <input type="text" class="form-control" name="plan" id="plan_edit" placeholder="Treatment Plan" required>
                    </div>
                    <div class="mb-3">
                        <label for="notes_plan_edit" class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" id="notes_plan_edit" rows="3" placeholder="Notes"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="date_plan_edit" class="form-label">Date Planned</label>
                        <input type="date" class="form-control" name="date" id="date_plan_edit">
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
                <div class="modal-header">
                    <h5 class="modal-title" id="editLabModalLabel">Edit Lab Result</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="lab_id" id="lab_id">
                    <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
                    <div class="mb-3">
                        <label for="test_name_edit" class="form-label">Test Name</label>
                        <input type="text" class="form-control" name="test_name" id="test_name_edit" placeholder="Test Name" required>
                    </div>
                    <div class="mb-3">
                        <label for="result_edit" class="form-label">Result</label>
                        <input type="text" class="form-control" name="result" id="result_edit" placeholder="Result" required>
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

<div class="container-fluid mt-4">
    <div class="row">
        <!-- Sidebar for EHR Modules -->
        <div class="col-md-3">
            <!-- EHR Modules -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-grid me-2"></i>EHR Modules</h6>
                </div>
                <div class="card-body">
                    <button onclick="showSection('vitals')" class="btn btn-outline-primary w-100 mb-2">
                        <i class="bi bi-heart-pulse me-2"></i>Record Vitalization
                    </button>
                    <button onclick="showSection('medications')" class="btn btn-outline-primary w-100 mb-2">
                        <i class="bi bi-capsule me-2"></i>Enter Medications
                    </button>
                    <button onclick="showSection('progress_notes')" class="btn btn-outline-primary w-100 mb-2">
                        <i class="bi bi-pencil-square me-2"></i>Progress Notes
                    </button>
                    <button onclick="showSection('diagnostics')" class="btn btn-outline-primary w-100 mb-2">
                        <i class="bi bi-search me-2"></i>Diagnostics
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
        <div class="col-md-9">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-person-fill me-2"></i>Patient Dashboard - <?php echo htmlspecialchars($patient['fullname']); ?></h5>
                    <a href="patients.php" class="btn btn-outline-secondary">Back to Patients</a>
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
                                ['key' => 'medical_history', 'title' => 'Medical History', 'fields' => ['condition_name', 'notes', 'date_recorded'], 'icon' => 'bi-clipboard-data'],
                                ['key' => 'medications', 'title' => 'Medications', 'fields' => ['medication', 'dose', 'start_date', 'notes'], 'icon' => 'bi-capsule'],
                                ['key' => 'vitals', 'title' => 'Vital Signs', 'fields' => ['bp', 'hr', 'temp', 'height', 'weight', 'date_taken'], 'icon' => 'bi-heart-pulse'],
                                ['key' => 'diagnostics', 'title' => 'Diagnostics', 'fields' => ['problem', 'diagnosis', 'date_diagnosed'], 'icon' => 'bi-search'],
                                ['key' => 'treatment_plans', 'title' => 'Treatment Plans', 'fields' => ['plan', 'notes', 'date_planned'], 'icon' => 'bi-journal-text'],
                                ['key' => 'progress_notes', 'title' => 'Progress Notes', 'fields' => ['note', 'author', 'date_written'], 'icon' => 'bi-pencil-square'],
                                ['key' => 'lab_results', 'title' => 'Lab Results', 'fields' => ['test_name', 'test_result', 'date_taken'], 'icon' => 'bi-flask']
                            ];

                            foreach ($recordTypes as $recordType) {
                                $records = $medical_data[$recordType['key']] ?? [];
                                ?>
                                <div class="col-md-6 mb-4">
                                    <div class="card h-100">
                                        <div class="card-header d-flex justify-content-between align-items-center">
                                            <h6 class="mb-0"><i class="bi <?php echo $recordType['icon']; ?> me-2"></i><?php echo $recordType['title']; ?> (<?php echo count($records); ?>)</h6>
                                            <a href="<?php echo strtolower(str_replace(' ', '_', $recordType['title'])); ?>.php?patient_id=<?php echo $patient_id; ?>" class="btn btn-sm btn-outline-primary">View All</a>
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
                                <div class="col-md-2"><input class="form-control" name="bp" placeholder="BP (e.g., 120/80)" value="<?php echo htmlspecialchars($_POST['bp'] ?? ''); ?>" required></div>
                                <div class="col-md-2"><input type="number" class="form-control" name="hr" placeholder="HR (bpm)" value="<?php echo htmlspecialchars($_POST['hr'] ?? ''); ?>" required></div>
                                <div class="col-md-2"><input type="number" step="0.1" class="form-control" name="temp" placeholder="Temp (°C)" value="<?php echo htmlspecialchars($_POST['temp'] ?? ''); ?>" required></div>
                                <div class="col-md-2"><input type="number" step="0.1" class="form-control" name="height" placeholder="Height (cm)" value="<?php echo htmlspecialchars($_POST['height'] ?? ''); ?>" required></div>
                                <div class="col-md-2"><input type="number" step="0.1" class="form-control" name="weight" placeholder="Weight (kg)" value="<?php echo htmlspecialchars($_POST['weight'] ?? ''); ?>" required></div>
                                <div class="col-md-2"><input type="date" class="form-control" name="date" value="<?php echo htmlspecialchars($_POST['date'] ?? date('Y-m-d')); ?>"></div>
                                <div class="col-12"><button name="add_vitals" class="btn btn-primary">Add Vitals</button></div>
                            </form>
                        </div>

                        <!-- Vitals Table -->
                        <div class="card p-3">
                            <table class="table table-sm table-bordered">
                                <thead>
                                    <tr>
                                        <th>BP</th>
                                        <th>HR</th>
                                        <th>Temp</th>
                                        <th>Height</th>
                                        <th>Weight</th>
                                        <th>Date</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $vitals = $medical_data['vitals'] ?? [];
                                    foreach ($vitals as $r): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($r['bp']); ?></td>
                                            <td><?php echo htmlspecialchars($r['hr']); ?></td>
                                            <td><?php echo htmlspecialchars($r['temp']); ?></td>
                                            <td><?php echo htmlspecialchars($r['height']); ?></td>
                                            <td><?php echo htmlspecialchars($r['weight']); ?></td>
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
                            <form method="post" class="row g-2">
                                <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
                                <div class="col-md-3"><input class="form-control" name="medication" placeholder="Medication" value="<?php echo htmlspecialchars($_POST['medication'] ?? ''); ?>" required></div>
                                <div class="col-md-3"><input class="form-control" name="dose" placeholder="Dose" value="<?php echo htmlspecialchars($_POST['dose'] ?? ''); ?>" required></div>
                                <div class="col-md-3"><input class="form-control" name="start_date" type="date" value="<?php echo htmlspecialchars($_POST['start_date'] ?? date('Y-m-d')); ?>" required></div>
                                <div class="col-md-3"><input class="form-control" name="notes" placeholder="Notes" value="<?php echo htmlspecialchars($_POST['notes'] ?? ''); ?>" required></div>
                                <div class="col-12"><button name="add_med" class="btn btn-primary">Add Medication</button></div>
                            </form>
                        </div>

                        <!-- Medications Table -->
                        <div class="card p-3">
                            <table class="table table-sm table-bordered">
                                <thead>
                                    <tr>
                                        <th>Medication</th>
                                        <th>Dose</th>
                                        <th>Start Date</th>
                                        <th>Notes</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $medications = $medical_data['medications'] ?? [];
                                    foreach ($medications as $r): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($r['medication']); ?></td>
                                            <td><?php echo htmlspecialchars($r['dose']); ?></td>
                                            <td><?php echo htmlspecialchars($r['start_date']); ?></td>
                                            <td><?php echo htmlspecialchars($r['notes']); ?></td>
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
                                <div class="col-md-6"><textarea class="form-control" name="note" placeholder="Progress note" rows="3" required><?php echo htmlspecialchars($_POST['note'] ?? ''); ?></textarea></div>
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
                                            <td><?php echo htmlspecialchars($r['note']); ?></td>
                                            <td><?php echo htmlspecialchars($r['author']); ?></td>
                                            <td><?php echo htmlspecialchars(substr($r['date_written'], 0, 10)); ?></td>
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
                        <h4>Diagnostics</h4>

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
                                <div class="col-md-4"><input class="form-control" name="problem" placeholder="Problem" value="<?php echo htmlspecialchars($_POST['problem'] ?? ''); ?>" required></div>
                                <div class="col-md-4"><input class="form-control" name="diagnosis" placeholder="Diagnosis" value="<?php echo htmlspecialchars($_POST['diagnosis'] ?? ''); ?>" required></div>
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
                                        <th>Diagnosis</th>
                                        <th>Date Diagnosed</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $diagnostics = $medical_data['diagnostics'] ?? [];
                                    foreach ($diagnostics as $r): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($r['problem']); ?></td>
                                            <td><?php echo htmlspecialchars($r['diagnosis']); ?></td>
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
                        <div class="card p-3 mb-3">
                            <form method="post" class="row g-2">
                                <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
                                <div class="col-md-4"><input class="form-control" name="plan" placeholder="Treatment Plan" value="<?php echo htmlspecialchars($_POST['plan'] ?? ''); ?>" required></div>
                                <div class="col-md-4"><textarea class="form-control" name="notes" placeholder="Notes" rows="2"><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea></div>
                                <div class="col-md-4"><input class="form-control" name="date" type="date" value="<?php echo htmlspecialchars($_POST['date'] ?? date('Y-m-d')); ?>"></div>
                                <div class="col-12"><button name="add_treatment_plan" class="btn btn-primary">Add Treatment Plan</button></div>
                            </form>
                        </div>

                        <!-- Treatment Plans Table -->
                        <div class="card p-3">
                            <table class="table table-sm table-bordered">
                                <thead>
                                    <tr>
                                        <th>Plan</th>
                                        <th>Notes</th>
                                        <th>Date Planned</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $treatment_plans = $medical_data['treatment_plans'] ?? [];
                                    foreach ($treatment_plans as $r): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($r['plan']); ?></td>
                                            <td><?php echo htmlspecialchars($r['notes']); ?></td>
                                            <td><?php echo htmlspecialchars($r['date_planned']); ?></td>
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
                                <div class="col-md-4"><input class="form-control" name="result" placeholder="Result" value="<?php echo htmlspecialchars($_POST['result'] ?? ''); ?>" required></div>
                                <div class="col-md-4"><input type="date" class="form-control" name="date" value="<?php echo htmlspecialchars($_POST['date'] ?? date('Y-m-d')); ?>"></div>
                                <div class="col-12"><button name="add_lab" class="btn btn-primary">Add Lab Result</button></div>
                            </form>
                        </div>

                        <!-- Lab Results Table -->
                        <div class="card p-3">
                            <table class="table table-sm table-bordered">
                                <thead>
                                    <tr>
                                        <th>Test Name</th>
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
                                <div class="col-md-4"><input class="form-control" name="condition_name" placeholder="Condition Name" value="<?php echo htmlspecialchars($_POST['condition_name'] ?? ''); ?>" required></div>
                                <div class="col-md-4"><textarea class="form-control" name="notes" placeholder="Notes" rows="2" required><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea></div>
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
                </div>
            </div>
        </div>
    </div>
   
</div>
<script>
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
    }
}

function editMed(id) {
    fetch('?get_med=' + id)
        .then(response => response.json())
        .then(data => {
            document.getElementById('med_id').value = data.id;
            document.getElementById('medication').value = data.medication;
            document.getElementById('dose').value = data.dose;
            document.getElementById('start_date').value = data.start_date;
            document.getElementById('notes').value = data.notes;
            new bootstrap.Modal(document.getElementById('editMedModal')).show();
        })
        .catch(error => console.error('Error:', error));
}

function editVital(id) {
    fetch('?get_vital=' + id)
        .then(response => response.json())
        .then(data => {
            document.getElementById('vital_id').value = data.id;
            document.getElementById('bp_edit').value = data.bp;
            document.getElementById('hr_edit').value = data.hr;
            document.getElementById('temp_edit').value = data.temp;
            document.getElementById('height_edit').value = data.height;
            document.getElementById('weight_edit').value = data.weight;
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