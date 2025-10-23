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
$tables = ['medical_history', 'medications', 'vitals', 'diagnostics', 'treatment_plans', 'progress_notes', 'lab_results', 'physical_assessments', 'surgeries', 'allergies', 'family_history', 'lifestyle_info'];

$table_fields = [
    'medical_history' => ['condition_name', 'status', 'notes'],
    'medications' => ['medication', 'indication', 'prescriber', 'dose', 'status', 'route', 'notes'],
    'vitals' => ['recorded_by', 'focus', 'bp','respiratory_rate', 'hr', 'temp', 'height', 'weight', 'oxygen_saturation', 'pain_scale', 'general_appearance'],
    'diagnostics' => ['study_type', 'body_part_region', 'study_description', 'clinical_indication', 'image_quality', 'order_by', 'performed_by', 'Interpreted_by', 'Imaging_facility'],
    'treatment_plans' => ['plan', 'intervention', 'problems', 'frequency', 'duration', 'order_by', 'assigned_to', 'date_started', 'date_ended', 'special_instructions', 'patient_education_provided'],
    'progress_notes' => ['focus', 'note', 'author'],
    'lab_results' => ['test_name', 'test_result', 'test_category', 'test_code', 'result_status', 'units', 'reference_range', 'order_by', 'collected_by', 'laboratory_facility', 'clinical_interpretation'],
    'physical_assessments' => ['assessed_by', 'head_and_neck', 'cardiovascular', 'respiratory', 'abdominal', 'neurological', 'musculoskeletal', 'skin', 'psychiatric'],
    'surgeries' => ['procedure_name', 'hospital', 'surgeon', 'complications'],
    'allergies' => ['allergen', 'reaction', 'severity'],
    'family_history' => ['relationship', 'condition', 'age_at_diagnosis', 'current_status'],
    'lifestyle_info' => ['smoking_status', 'smoking_details', 'alcohol_use', 'alcohol_details', 'exercise', 'diet', 'recreational_drug_use']
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
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            $medical_data[$table] = [];
            while ($row = $result->fetch_assoc()) {
                $medical_data[$table][] = $row;
            }
        }
        $stmt->close();
    }
}

$msg = "";
$error = "";

// ===== SURGERY PROCESSING =====
if (isset($_POST['add_surgery'])) {
    $procedure = sanitize_input($conn, $_POST['procedure_name'] ?? "");
    $date_surgery = $_POST['date_surgery'] ?: date("Y-m-d");
    $hospital = sanitize_input($conn, $_POST['hospital'] ?? "");
    $surgeon = sanitize_input($conn, $_POST['surgeon'] ?? "");
    $complications = $_POST['complications'] ?? "";

    if (empty($procedure)) {
        $error = "Procedure name is required.";
    } else {
        $stmt = $conn->prepare("INSERT INTO surgeries (patient_id, procedure_name, date_surgery, hospital, surgeon, complications) VALUES (?,?,?,?,?,?)");
        $stmt->bind_param("isssss", $patient_id, $procedure, $date_surgery, $hospital, $surgeon, $complications);
        if ($stmt->execute()) {
            $msg = "Surgery added.";
            // Refresh data
            $stmt = $conn->prepare("SELECT * FROM surgeries WHERE patient_id = ? ORDER BY id DESC");
            $stmt->bind_param("i", $patient_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $medical_data['surgeries'] = [];
            while ($row = $result->fetch_assoc()) {
                $medical_data['surgeries'][] = $row;
            }
            $stmt->close();
        } else {
            $error = "Database error: " . $stmt->error;
        }
    }
}

if (isset($_GET['delete_surgery'])) {
    $id = intval($_GET['delete_surgery']);
    $stmt = $conn->prepare("DELETE FROM surgeries WHERE id=? AND patient_id=?");
    $stmt->bind_param("ii", $id, $patient_id);
    if ($stmt->execute()) {
        header("Location: patient_dashboard.php?patient_id=$patient_id&section=surgeries");
        exit();
    }
    $stmt->close();
}

if (isset($_GET['get_surgery'])) {
    $id = intval($_GET['get_surgery']);
    $stmt = $conn->prepare("SELECT * FROM surgeries WHERE id=? AND patient_id=?");
    $stmt->bind_param("ii", $id, $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        echo json_encode($row);
    }
    $stmt->close();
    exit;
}

if (isset($_POST['update_surgery'])) {
    $sid = intval($_POST['surgery_id']);
    $procedure = sanitize_input($conn, $_POST['procedure_name'] ?? "");
    $date_surgery = $_POST['date_surgery'] ?: date("Y-m-d");
    $hospital = sanitize_input($conn, $_POST['hospital'] ?? "");
    $surgeon = sanitize_input($conn, $_POST['surgeon'] ?? "");
    $complications = $_POST['complications'] ?? "";

    if (empty($procedure)) {
        $error = "Procedure name is required.";
    } else {
        $stmt = $conn->prepare("UPDATE surgeries SET procedure_name=?, date_surgery=?, hospital=?, surgeon=?, complications=? WHERE id=? AND patient_id=?");
        $stmt->bind_param("sssssii", $procedure, $date_surgery, $hospital, $surgeon, $complications, $sid, $patient_id);
        if ($stmt->execute()) {
            $msg = "Surgery updated.";
        } else {
            $error = "Database error: " . $stmt->error;
        }
        $stmt->close();
        // Refresh data
        $stmt = $conn->prepare("SELECT * FROM surgeries WHERE patient_id = ? ORDER BY id DESC");
        $stmt->bind_param("i", $patient_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $medical_data['surgeries'] = [];
        while ($row = $result->fetch_assoc()) {
            $medical_data['surgeries'][] = $row;
        }
        $stmt->close();
    }
}

// ===== ALLERGY PROCESSING =====
if (isset($_POST['add_allergy'])) {
    $allergen = sanitize_input($conn, $_POST['allergen'] ?? "");
    $reaction = sanitize_input($conn, $_POST['reaction'] ?? "");
    $severity = sanitize_input($conn, $_POST['severity'] ?? "");
    $date_identified = $_POST['date_identified'] ?: date("Y-m-d");

    if (empty($allergen) || empty($reaction)) {
        $error = "Allergen and Reaction are required.";
    } else {
        $stmt = $conn->prepare("INSERT INTO allergies (patient_id, allergen, reaction, severity, date_identified) VALUES (?,?,?,?,?)");
        $stmt->bind_param("issss", $patient_id, $allergen, $reaction, $severity, $date_identified);
        if ($stmt->execute()) {
            $msg = "Allergy added.";
            // Refresh data
            $stmt = $conn->prepare("SELECT * FROM allergies WHERE patient_id = ? ORDER BY id DESC");
            $stmt->bind_param("i", $patient_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $medical_data['allergies'] = [];
            while ($row = $result->fetch_assoc()) {
                $medical_data['allergies'][] = $row;
            }
            $stmt->close();
        } else {
            $error = "Database error: " . $stmt->error;
        }
    }
}

if (isset($_GET['delete_allergy'])) {
    $id = intval($_GET['delete_allergy']);
    $stmt = $conn->prepare("DELETE FROM allergies WHERE id=? AND patient_id=?");
    $stmt->bind_param("ii", $id, $patient_id);
    if ($stmt->execute()) {
        header("Location: patient_dashboard.php?patient_id=$patient_id&section=allergies");
        exit();
    }
    $stmt->close();
}

if (isset($_GET['get_allergy'])) {
    $id = intval($_GET['get_allergy']);
    $stmt = $conn->prepare("SELECT * FROM allergies WHERE id=? AND patient_id=?");
    $stmt->bind_param("ii", $id, $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        echo json_encode($row);
    }
    $stmt->close();
    exit;
}

if (isset($_POST['update_allergy'])) {
    $aid = intval($_POST['allergy_id']);
    $allergen = sanitize_input($conn, $_POST['allergen'] ?? "");
    $reaction = sanitize_input($conn, $_POST['reaction'] ?? "");
    $severity = sanitize_input($conn, $_POST['severity'] ?? "");
    $date_identified = $_POST['date_identified'] ?: date("Y-m-d");

    if (empty($allergen) || empty($reaction)) {
        $error = "Allergen and Reaction are required.";
    } else {
        $stmt = $conn->prepare("UPDATE allergies SET allergen=?, reaction=?, severity=?, date_identified=? WHERE id=? AND patient_id=?");
        $stmt->bind_param("ssssii", $allergen, $reaction, $severity, $date_identified, $aid, $patient_id);
        if ($stmt->execute()) {
            $msg = "Allergy updated.";
        } else {
            $error = "Database error: " . $stmt->error;
        }
        $stmt->close();
        // Refresh data
        $stmt = $conn->prepare("SELECT * FROM allergies WHERE patient_id = ? ORDER BY id DESC");
        $stmt->bind_param("i", $patient_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $medical_data['allergies'] = [];
        while ($row = $result->fetch_assoc()) {
            $medical_data['allergies'][] = $row;
        }
        $stmt->close();
    }
}

// ===== FAMILY HISTORY PROCESSING =====
if (isset($_POST['add_family_history'])) {
    $relationship = sanitize_input($conn, $_POST['relationship'] ?? "");
    $condition = sanitize_input($conn, $_POST['condition'] ?? "");
    $age_at_diagnosis = sanitize_input($conn, $_POST['age_at_diagnosis'] ?? "");
    $current_status = sanitize_input($conn, $_POST['current_status'] ?? "");

    if (empty($relationship) || empty($condition)) {
        $error = "Relationship and Condition are required.";
    } else {
        $stmt = $conn->prepare("INSERT INTO family_history (patient_id, relationship, `condition`, age_at_diagnosis, current_status) VALUES (?,?,?,?,?)");
        $stmt->bind_param("issss", $patient_id, $relationship, $condition, $age_at_diagnosis, $current_status);
        if ($stmt->execute()) {
            $msg = "Family history added.";
            // Refresh data
            $stmt = $conn->prepare("SELECT * FROM family_history WHERE patient_id = ? ORDER BY id DESC");
            $stmt->bind_param("i", $patient_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $medical_data['family_history'] = [];
            while ($row = $result->fetch_assoc()) {
                $medical_data['family_history'][] = $row;
            }
            $stmt->close();
        } else {
            $error = "Database error: " . $stmt->error;
        }
    }
}

if (isset($_GET['delete_family_history'])) {
    $id = intval($_GET['delete_family_history']);
    $stmt = $conn->prepare("DELETE FROM family_history WHERE id=? AND patient_id=?");
    $stmt->bind_param("ii", $id, $patient_id);
    if ($stmt->execute()) {
        header("Location: patient_dashboard.php?patient_id=$patient_id&section=family_history");
        exit();
    }
    $stmt->close();
}

if (isset($_GET['get_family_history'])) {
    $id = intval($_GET['get_family_history']);
    $stmt = $conn->prepare("SELECT * FROM family_history WHERE id=? AND patient_id=?");
    $stmt->bind_param("ii", $id, $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        echo json_encode($row);
    }
    $stmt->close();
    exit;
}

if (isset($_POST['update_family_history'])) {
    $fid = intval($_POST['family_history_id']);
    $relationship = sanitize_input($conn, $_POST['relationship'] ?? "");
    $condition = sanitize_input($conn, $_POST['condition'] ?? "");
    $age_at_diagnosis = sanitize_input($conn, $_POST['age_at_diagnosis'] ?? "");
    $current_status = sanitize_input($conn, $_POST['current_status'] ?? "");

    if (empty($relationship) || empty($condition)) {
        $error = "Relationship and Condition are required.";
    } else {
        $stmt = $conn->prepare("UPDATE family_history SET relationship=?, `condition`=?, age_at_diagnosis=?, current_status=? WHERE id=? AND patient_id=?");
        $stmt->bind_param("ssssii", $relationship, $condition, $age_at_diagnosis, $current_status, $fid, $patient_id);
        if ($stmt->execute()) {
            $msg = "Family history updated.";
        } else {
            $error = "Database error: " . $stmt->error;
        }
        $stmt->close();
        // Refresh data
        $stmt = $conn->prepare("SELECT * FROM family_history WHERE patient_id = ? ORDER BY id DESC");
        $stmt->bind_param("i", $patient_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $medical_data['family_history'] = [];
        while ($row = $result->fetch_assoc()) {
            $medical_data['family_history'][] = $row;
        }
        $stmt->close();
    }
}

// ===== LIFESTYLE INFO PROCESSING =====
if (isset($_POST['add_lifestyle'])) {
    $smoking_status = sanitize_input($conn, $_POST['smoking_status'] ?? "");
    $smoking_details = $_POST['smoking_details'] ?? "";
    $alcohol_use = sanitize_input($conn, $_POST['alcohol_use'] ?? "");
    $alcohol_details = $_POST['alcohol_details'] ?? "";
    $exercise = $_POST['exercise'] ?? "";
    $diet = $_POST['diet'] ?? "";
    $recreational_drug_use = $_POST['recreational_drug_use'] ?? "";

    $stmt = $conn->prepare("INSERT INTO lifestyle_info (patient_id, smoking_status, smoking_details, alcohol_use, alcohol_details, exercise, diet, recreational_drug_use) VALUES (?,?,?,?,?,?,?,?)");
    $stmt->bind_param("isssssss", $patient_id, $smoking_status, $smoking_details, $alcohol_use, $alcohol_details, $exercise, $diet, $recreational_drug_use);
    if ($stmt->execute()) {
        $msg = "Lifestyle information added.";
        // Refresh data
        $stmt = $conn->prepare("SELECT * FROM lifestyle_info WHERE patient_id = ? ORDER BY id DESC");
        $stmt->bind_param("i", $patient_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $medical_data['lifestyle_info'] = [];
        while ($row = $result->fetch_assoc()) {
            $medical_data['lifestyle_info'][] = $row;
        }
        $stmt->close();
    } else {
        $error = "Database error: " . $stmt->error;
    }
}

if (isset($_GET['delete_lifestyle'])) {
    $id = intval($_GET['delete_lifestyle']);
    $stmt = $conn->prepare("DELETE FROM lifestyle_info WHERE id=? AND patient_id=?");
    $stmt->bind_param("ii", $id, $patient_id);
    if ($stmt->execute()) {
        header("Location: patient_dashboard.php?patient_id=$patient_id&section=lifestyle_info");
        exit();
    }
    $stmt->close();
}

if (isset($_GET['get_lifestyle'])) {
    $id = intval($_GET['get_lifestyle']);
    $stmt = $conn->prepare("SELECT * FROM lifestyle_info WHERE id=? AND patient_id=?");
    $stmt->bind_param("ii", $id, $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        echo json_encode($row);
    }
    $stmt->close();
    exit;
}

if (isset($_POST['update_lifestyle'])) {
    $lid = intval($_POST['lifestyle_id']);
    $smoking_status = sanitize_input($conn, $_POST['smoking_status'] ?? "");
    $smoking_details = $_POST['smoking_details'] ?? "";
    $alcohol_use = sanitize_input($conn, $_POST['alcohol_use'] ?? "");
    $alcohol_details = $_POST['alcohol_details'] ?? "";
    $exercise = $_POST['exercise'] ?? "";
    $diet = $_POST['diet'] ?? "";
    $recreational_drug_use = $_POST['recreational_drug_use'] ?? "";

    $stmt = $conn->prepare("UPDATE lifestyle_info SET smoking_status=?, smoking_details=?, alcohol_use=?, alcohol_details=?, exercise=?, diet=?, recreational_drug_use=? WHERE id=? AND patient_id=?");
    $stmt->bind_param("sssssssii", $smoking_status, $smoking_details, $alcohol_use, $alcohol_details, $exercise, $diet, $recreational_drug_use, $lid, $patient_id);
    if ($stmt->execute()) {
        $msg = "Lifestyle information updated.";
    } else {
        $error = "Database error: " . $stmt->error;
    }
    $stmt->close();
    // Refresh data
    $stmt = $conn->prepare("SELECT * FROM lifestyle_info WHERE patient_id = ? ORDER BY id DESC");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $medical_data['lifestyle_info'] = [];
    while ($row = $result->fetch_assoc()) {
        $medical_data['lifestyle_info'][] = $row;
    }
    $stmt->close();
}

// [REST OF THE EXISTING CODE CONTINUES HERE - Include all the original vitals, medications, progress notes, diagnostics, treatment plans, lab results, medical history, and physical assessments processing code]

include "header.php";

// Determine submitted section for JavaScript
$submitted_section = '';
if (isset($_POST['add_surgery']) || isset($_POST['update_surgery'])) $submitted_section = 'surgeries';
if (isset($_POST['add_allergy']) || isset($_POST['update_allergy'])) $submitted_section = 'allergies';
if (isset($_POST['add_family_history']) || isset($_POST['update_family_history'])) $submitted_section = 'family_history';
if (isset($_POST['add_lifestyle']) || isset($_POST['update_lifestyle'])) $submitted_section = 'lifestyle_info';
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
        gap: 0.5rem;
    }

    .btn-edit, .btn-delete {
        width: 5.75rem;
    }
    .module{
        width: 19rem;
    }
    .content{
        width: 77vw;
    }
</style>

<!-- Edit Modal for Surgery -->
<div class="modal fade" id="editSurgeryModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" id="editSurgeryForm">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Surgery</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="surgery_id" id="surgery_id">
                    <div class="mb-3">
                        <label class="form-label">Procedure*</label>
                        <input type="text" class="form-control" name="procedure_name" id="procedure_name_edit" placeholder="e.g., Appendectomy" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Date of Surgery</label>
                            <input type="date" class="form-control" name="date_surgery" id="date_surgery_edit">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Hospital</label>
                            <input type="text" class="form-control" name="hospital" id="hospital_edit" placeholder="e.g., General Hospital">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Surgeon</label>
                        <input type="text" class="form-control" name="surgeon" id="surgeon_edit" placeholder="e.g., Dr. Smith">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Complications</label>
                        <textarea class="form-control" name="complications" id="complications_edit" rows="3" placeholder="Describe any complications..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="update_surgery" class="btn btn-primary">Save Surgery</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal for Allergy -->
<div class="modal fade" id="editAllergyModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" id="editAllergyForm">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Allergy</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="allergy_id" id="allergy_id">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Allergen*</label>
                            <input type="text" class="form-control" name="allergen" id="allergen_edit" placeholder="e.g., Penicillin, Peanuts" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Reaction*</label>
                            <input type="text" class="form-control" name="reaction" id="reaction_edit" placeholder="e.g., Hives, Anaphylaxis" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Severity</label>
                            <select class="form-control" name="severity" id="severity_edit">
                                <option value="Mild">Mild</option>
                                <option value="Moderate">Moderate</option>
                                <option value="Severe">Severe</option>
                                <option value="Life-threatening">Life-threatening</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Date Identified</label>
                            <input type="date" class="form-control" name="date_identified" id="date_identified_edit">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="update_allergy" class="btn btn-primary">Save Allergy</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal for Family History -->
<div class="modal fade" id="editFamilyHistoryModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" id="editFamilyHistoryForm">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Family History Record</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="family_history_id" id="family_history_id">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Relationship*</label>
                            <input type="text" class="form-control" name="relationship" id="relationship_edit" placeholder="e.g., Mother, Father" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Condition*</label>
                            <input type="text" class="form-control" name="condition" id="condition_edit" placeholder="e.g., Hypertension, Diabetes" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Age at Diagnosis</label>
                            <input type="text" class="form-control" name="age_at_diagnosis" id="age_at_diagnosis_edit" placeholder="e.g., 55">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Current Status</label>
                            <input type="text" class="form-control" name="current_status" id="current_status_edit" placeholder="e.g., Living, Deceased">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="update_family_history" class="btn btn-primary">Save Record</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal for Lifestyle Info -->
<div class="modal fade" id="editLifestyleModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form method="post" id="editLifestyleForm">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Lifestyle Information</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="lifestyle_id" id="lifestyle_id">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Smoking Status</label>
                            <select class="form-control" name="smoking_status" id="smoking_status_edit">
                                <option value="Never">Never</option>
                                <option value="Former">Former</option>
                                <option value="Current">Current</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Smoking Details</label>
                            <input type="text" class="form-control" name="smoking_details" id="smoking_details_edit" placeholder="e.g., 1 pack/day for 10 years">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Alcohol Use</label>
                            <select class="form-control" name="alcohol_use" id="alcohol_use_edit">
                                <option value="None">None</option>
                                <option value="Occasional">Occasional</option>
                                <option value="Moderate">Moderate</option>
                                <option value="Heavy">Heavy</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Alcohol Details</label>
                            <input type="text" class="form-control" name="alcohol_details" id="alcohol_details_edit" placeholder="e.g., 3-4 beers on weekends">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Exercise</label>
                            <input type="text" class="form-control" name="exercise" id="exercise_edit" placeholder="e.g., 3 times a week">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Diet</label>
                            <input type="text" class="form-control" name="diet" id="diet_edit" placeholder="e.g., Balanced, Vegetarian">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Recreational Drug Use</label>
                        <textarea class="form-control" name="recreational_drug_use" id="recreational_drug_use_edit" rows="3" placeholder="Describe any recreational drug use..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="update_lifestyle" class="btn btn-primary">Save Lifestyle Info</button>
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
                        <i class="bi bi-capsule me-2"></i>Medications
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
                    <button onclick="showSection('surgeries')" class="btn btn-outline-primary w-100 mb-2">
                        <i class="bi bi-scissors me-2"></i>Surgeries
                    </button>
                    <button onclick="showSection('allergies')" class="btn btn-outline-primary w-100 mb-2">
                        <i class="bi bi-exclamation-triangle me-2"></i>Allergies
                    </button>
                    <button onclick="showSection('family_history')" class="btn btn-outline-primary w-100 mb-2">
                        <i class="bi bi-people me-2"></i>Family History
                    </button>
                    <button onclick="showSection('lifestyle_info')" class="btn btn-outline-primary w-100 mb-2">
                        <i class="bi bi-activity me-2"></i>Lifestyle Information
                    </button>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-md-9">
            <div class="card mb-4 content">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-person-fill me-2"></i>Patient Dashboard - <?php echo htmlspecialchars($patient['fullname']); ?></h5>
                </div>
                <div class="card-body">
                    
                    <!-- Feedback Messages -->
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
                                        <p><strong>Name:</strong> <?php echo htmlspecialchars($patient['fullname']); ?></p>
                                        <p><strong>Date of Birth:</strong> <?php echo htmlspecialchars($patient['dob'] ?: 'N/A'); ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Gender:</strong> <?php echo htmlspecialchars($patient['gender'] ?: 'N/A'); ?></p>
                                        <p><strong>Contact:</strong> <?php echo htmlspecialchars($patient['contact'] ?: 'N/A'); ?></p>
                                        <p><strong>Address:</strong> <?php echo htmlspecialchars($patient['address'] ?: 'N/A'); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Medical Records Overview -->
                        <div class="row">
                            <?php
                            $recordTypes = [
                                ['key' => 'medical_history', 'title' => 'Medical History', 'fields' => ['condition_name', 'status', 'notes'], 'icon' => 'bi-clipboard-data'],
                                ['key' => 'medications', 'title' => 'Medications', 'fields' => ['medication', 'dose', 'start_date'], 'icon' => 'bi-capsule'],
                                ['key' => 'vitals', 'title' => 'Vital Signs', 'fields' => ['bp', 'hr', 'temp'], 'icon' => 'bi-heart-pulse'],
                                ['key' => 'diagnostics', 'title' => 'Diagnostics', 'fields' => ['study_type', 'body_part_region', 'date_diagnosed'], 'icon' => 'bi-search'],
                                ['key' => 'treatment_plans', 'title' => 'Treatment Plans', 'fields' => ['plan', 'intervention', 'problems'], 'icon' => 'bi-journal-text'],
                                ['key' => 'progress_notes', 'title' => 'Progress Notes', 'fields' => ['focus', 'note', 'author'], 'icon' => 'bi-pencil-square'],
                                ['key' => 'lab_results', 'title' => 'Lab Results', 'fields' => ['test_name', 'test_category', 'test_code'], 'icon' => 'bi-flask'],
                                ['key' => 'physical_assessments', 'title' => 'Physical Assessments', 'fields' => ['assessed_by', 'cardiovascular', 'respiratory'], 'icon' => 'bi-clipboard-check'],
                                ['key' => 'surgeries', 'title' => 'Surgeries', 'fields' => ['procedure_name', 'hospital', 'surgeon'], 'icon' => 'bi-scissors'],
                                ['key' => 'allergies', 'title' => 'Allergies', 'fields' => ['allergen', 'reaction', 'severity'], 'icon' => 'bi-exclamation-triangle'],
                                ['key' => 'family_history', 'title' => 'Family History', 'fields' => ['relationship', 'condition', 'current_status'], 'icon' => 'bi-people'],
                                ['key' => 'lifestyle_info', 'title' => 'Lifestyle Info', 'fields' => ['smoking_status', 'alcohol_use', 'exercise'], 'icon' => 'bi-activity']
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
                                                                        <td><?php echo htmlspecialchars(substr($record[$field] ?? 'N/A', 0, 30)); ?></td>
                                                                    <?php endforeach; ?>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                                <?php if (count($records) > 5): ?>
                                                    <div class="text-center">
                                                        <button onclick="showSection('<?php echo $recordType['key']; ?>')" class="btn btn-sm btn-link">View More...</button>
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

                    <!-- Surgeries Section -->
                    <div id="surgeries-content" style="display: none;">
                        <h4>Surgeries</h4>
                        
                        <div class="card p-3 mb-3">
                            <form method="post" class="row g-2">
                                <div class="col-md-12">
                                    <label class="form-label">Procedure*</label>
                                    <input type="text" class="form-control" name="procedure_name" placeholder="e.g., Appendectomy" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Date of Surgery</label>
                                    <input type="date" class="form-control" name="date_surgery" value="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Hospital</label>
                                    <input type="text" class="form-control" name="hospital" placeholder="e.g., General Hospital">
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label">Surgeon</label>
                                    <input type="text" class="form-control" name="surgeon" placeholder="e.g., Dr. Smith">
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label">Complications</label>
                                    <textarea class="form-control" name="complications" rows="3" placeholder="Describe any complications..."></textarea>
                                </div>
                                <div class="col-12">
                                    <button name="add_surgery" class="btn btn-primary">Add Surgery</button>
                                </div>
                            </form>
                        </div>

                        <div class="card p-3">
                            <table class="table table-sm table-bordered">
                                <thead>
                                    <tr>
                                        <th>Procedure</th>
                                        <th>Date</th>
                                        <th>Hospital</th>
                                        <th>Surgeon</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $surgeries = $medical_data['surgeries'] ?? [];
                                    foreach ($surgeries as $r): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($r['procedure_name']); ?></td>
                                            <td><?php echo htmlspecialchars($r['date_surgery'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($r['hospital'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($r['surgeon'] ?? 'N/A'); ?></td>
                                            <td class="action-btn">
                                                <a class="btn btn-sm btn-danger btn-delete" href="?delete_surgery=<?php echo $r['id']; ?>&patient_id=<?php echo $patient_id; ?>&section=surgeries" onclick="return confirm('Delete?')">
                                                    <i class="bi bi-trash"></i> Delete
                                                </a>
                                                <a class="btn btn-sm btn-warning btn-edit" href="javascript:void(0)" onclick="editSurgery(<?php echo $r['id']; ?>)">
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

                    <!-- Allergies Section -->
                    <div id="allergies-content" style="display: none;">
                        <h4>Allergies</h4>
                        
                        <div class="card p-3 mb-3">
                            <form method="post" class="row g-2">
                                <div class="col-md-6">
                                    <label class="form-label">Allergen*</label>
                                    <input type="text" class="form-control" name="allergen" placeholder="e.g., Penicillin, Peanuts" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Reaction*</label>
                                    <input type="text" class="form-control" name="reaction" placeholder="e.g., Hives, Anaphylaxis" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Severity</label>
                                    <select class="form-control" name="severity">
                                        <option value="Mild">Mild</option>
                                        <option value="Moderate">Moderate</option>
                                        <option value="Severe">Severe</option>
                                        <option value="Life-threatening">Life-threatening</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Date Identified</label>
                                    <input type="date" class="form-control" name="date_identified" value="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <div class="col-12">
                                    <button name="add_allergy" class="btn btn-primary">Add Allergy</button>
                                </div>
                            </form>
                        </div>

                        <div class="card p-3">
                            <table class="table table-sm table-bordered">
                                <thead>
                                    <tr>
                                        <th>Allergen</th>
                                        <th>Reaction</th>
                                        <th>Severity</th>
                                        <th>Date Identified</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $allergies = $medical_data['allergies'] ?? [];
                                    foreach ($allergies as $r): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($r['allergen']); ?></td>
                                            <td><?php echo htmlspecialchars($r['reaction']); ?></td>
                                            <td><span class="badge bg-<?php 
                                                echo $r['severity'] == 'Mild' ? 'success' : 
                                                    ($r['severity'] == 'Moderate' ? 'warning' : 'danger'); 
                                            ?>"><?php echo htmlspecialchars($r['severity']); ?></span></td>
                                            <td><?php echo htmlspecialchars($r['date_identified'] ?? 'N/A'); ?></td>
                                            <td class="action-btn">
                                                <a class="btn btn-sm btn-danger btn-delete" href="?delete_allergy=<?php echo $r['id']; ?>&patient_id=<?php echo $patient_id; ?>&section=allergies" onclick="return confirm('Delete?')">
                                                    <i class="bi bi-trash"></i> Delete
                                                </a>
                                                <a class="btn btn-sm btn-warning btn-edit" href="javascript:void(0)" onclick="editAllergy(<?php echo $r['id']; ?>)">
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

                    <!-- Family History Section -->
                    <div id="family_history-content" style="display: none;">
                        <h4>Family History</h4>
                        
                        <div class="card p-3 mb-3">
                            <form method="post" class="row g-2">
                                <div class="col-md-6">
                                    <label class="form-label">Relationship*</label>
                                    <input type="text" class="form-control" name="relationship" placeholder="e.g., Mother, Father" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Condition*</label>
                                    <input type="text" class="form-control" name="condition" placeholder="e.g., Hypertension, Diabetes" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Age at Diagnosis</label>
                                    <input type="text" class="form-control" name="age_at_diagnosis" placeholder="e.g., 55">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Current Status</label>
                                    <input type="text" class="form-control" name="current_status" placeholder="e.g., Living, Deceased">
                                </div>
                                <div class="col-12">
                                    <button name="add_family_history" class="btn btn-primary">Add Family History</button>
                                </div>
                            </form>
                        </div>

                        <div class="card p-3">
                            <table class="table table-sm table-bordered">
                                <thead>
                                    <tr>
                                        <th>Relationship</th>
                                        <th>Condition</th>
                                        <th>Age at Diagnosis</th>
                                        <th>Current Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $family_history = $medical_data['family_history'] ?? [];
                                    foreach ($family_history as $r): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($r['relationship']); ?></td>
                                            <td><?php echo htmlspecialchars($r['condition']); ?></td>
                                            <td><?php echo htmlspecialchars($r['age_at_diagnosis'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($r['current_status'] ?? 'N/A'); ?></td>
                                            <td class="action-btn">
                                                <a class="btn btn-sm btn-danger btn-delete" href="?delete_family_history=<?php echo $r['id']; ?>&patient_id=<?php echo $patient_id; ?>&section=family_history" onclick="return confirm('Delete?')">
                                                    <i class="bi bi-trash"></i> Delete
                                                </a>
                                                <a class="btn btn-sm btn-warning btn-edit" href="javascript:void(0)" onclick="editFamilyHistory(<?php echo $r['id']; ?>)">
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

                    <!-- Lifestyle Information Section -->
                    <div id="lifestyle_info-content" style="display: none;">
                        <h4>Lifestyle Information</h4>
                        
                        <div class="card p-3 mb-3">
                            <form method="post" class="row g-2">
                                <div class="col-md-6">
                                    <label class="form-label">Smoking Status</label>
                                    <select class="form-control" name="smoking_status">
                                        <option value="Never">Never</option>
                                        <option value="Former">Former</option>
                                        <option value="Current">Current</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Smoking Details</label>
                                    <input type="text" class="form-control" name="smoking_details" placeholder="e.g., 1 pack/day for 10 years">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Alcohol Use</label>
                                    <select class="form-control" name="alcohol_use">
                                        <option value="None">None</option>
                                        <option value="Occasional">Occasional</option>
                                        <option value="Moderate">Moderate</option>
                                        <option value="Heavy">Heavy</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Alcohol Details</label>
                                    <input type="text" class="form-control" name="alcohol_details" placeholder="e.g., 3-4 beers on weekends">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Exercise</label>
                                    <input type="text" class="form-control" name="exercise" placeholder="e.g., 3 times a week">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Diet</label>
                                    <input type="text" class="form-control" name="diet" placeholder="e.g., Balanced, Vegetarian">
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label">Recreational Drug Use</label>
                                    <textarea class="form-control" name="recreational_drug_use" rows="3" placeholder="Describe any recreational drug use..."></textarea>
                                </div>
                                <div class="col-12">
                                    <button name="add_lifestyle" class="btn btn-primary">Add Lifestyle Info</button>
                                </div>
                            </form>
                        </div>

                        <div class="card p-3">
                            <table class="table table-sm table-bordered">
                                <thead>
                                    <tr>
                                        <th>Smoking Status</th>
                                        <th>Alcohol Use</th>
                                        <th>Exercise</th>
                                        <th>Diet</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $lifestyle = $medical_data['lifestyle_info'] ?? [];
                                    foreach ($lifestyle as $r): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($r['smoking_status'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($r['alcohol_use'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($r['exercise'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($r['diet'] ?? 'N/A'); ?></td>
                                            <td class="action-btn">
                                                <a class="btn btn-sm btn-danger btn-delete" href="?delete_lifestyle=<?php echo $r['id']; ?>&patient_id=<?php echo $patient_id; ?>&section=lifestyle_info" onclick="return confirm('Delete?')">
                                                    <i class="bi bi-trash"></i> Delete
                                                </a>
                                                <a class="btn btn-sm btn-warning btn-edit" href="javascript:void(0)" onclick="editLifestyle(<?php echo $r['id']; ?>)">
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

                    <!-- NOTE: Add all other existing sections (vitals, medications, etc.) here -->

                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const patient_id = <?php echo $patient_id; ?>;

    function showSection(section) {
        // Hide all sections
        const sections = ['default-content', 'vitals-content', 'medications-content', 'progress_notes-content', 
                         'diagnostics-content', 'treatment_plans-content', 'lab_results-content', 
                         'medical_history-content', 'physical_assessment-content', 'surgeries-content',
                         'allergies-content', 'family_history-content', 'lifestyle_info-content'];
        
        sections.forEach(sec => {
            const elem = document.getElementById(sec);
            if (elem) elem.style.display = 'none';
        });
        
        // Show selected section
        const sectionMap = {
            'default': 'default-content',
            'vitals': 'vitals-content',
            'medications': 'medications-content',
            'progress_notes': 'progress_notes-content',
            'diagnostics': 'diagnostics-content',
            'treatment_plans': 'treatment_plans-content',
            'lab_results': 'lab_results-content',
            'medical_history': 'medical_history-content',
            'physical_assessment': 'physical_assessment-content',
            'surgeries': 'surgeries-content',
            'allergies': 'allergies-content',
            'family_history': 'family_history-content',
            'lifestyle_info': 'lifestyle_info-content'
        };
        
        const contentId = sectionMap[section] || 'default-content';
        const elem = document.getElementById(contentId);
        if (elem) elem.style.display = 'block';
        
        // Update URL
        const url = section === 'default' 
            ? '?patient_id=' + patient_id 
            : '?patient_id=' + patient_id + '&section=' + section;
        history.pushState(null, '', url);
    }

    // Edit Surgery
    function editSurgery(id) {
        fetch('?get_surgery=' + id + '&patient_id=' + patient_id)
            .then(response => response.json())
            .then(data => {
                document.getElementById('surgery_id').value = data.id;
                document.getElementById('procedure_name_edit').value = data.procedure_name || '';
                document.getElementById('date_surgery_edit').value = data.date_surgery ? data.date_surgery.substring(0, 10) : '';
                document.getElementById('hospital_edit').value = data.hospital || '';
                document.getElementById('surgeon_edit').value = data.surgeon || '';
                document.getElementById('complications_edit').value = data.complications || '';
                new bootstrap.Modal(document.getElementById('editSurgeryModal')).show();
            })
            .catch(error => console.error('Error:', error));
    }

    // Edit Allergy
    function editAllergy(id) {
        fetch('?get_allergy=' + id + '&patient_id=' + patient_id)
            .then(response => response.json())
            .then(data => {
                document.getElementById('allergy_id').value = data.id;
                document.getElementById('allergen_edit').value = data.allergen || '';
                document.getElementById('reaction_edit').value = data.reaction || '';
                document.getElementById('severity_edit').value = data.severity || 'Mild';
                document.getElementById('date_identified_edit').value = data.date_identified ? data.date_identified.substring(0, 10) : '';
                new bootstrap.Modal(document.getElementById('editAllergyModal')).show();
            })
            .catch(error => console.error('Error:', error));
    }

    // Edit Family History
    function editFamilyHistory(id) {
        fetch('?get_family_history=' + id + '&patient_id=' + patient_id)
            .then(response => response.json())
            .then(data => {
                document.getElementById('family_history_id').value = data.id;
                document.getElementById('relationship_edit').value = data.relationship || '';
                document.getElementById('condition_edit').value = data.condition || '';
                document.getElementById('age_at_diagnosis_edit').value = data.age_at_diagnosis || '';
                document.getElementById('current_status_edit').value = data.current_status || '';
                new bootstrap.Modal(document.getElementById('editFamilyHistoryModal')).show();
            })
            .catch(error => console.error('Error:', error));
    }

    // Edit Lifestyle Info
    function editLifestyle(id) {
        fetch('?get_lifestyle=' + id + '&patient_id=' + patient_id)
            .then(response => response.json())
            .then(data => {
                document.getElementById('lifestyle_id').value = data.id;
                document.getElementById('smoking_status_edit').value = data.smoking_status || 'Never';
                document.getElementById('smoking_details_edit').value = data.smoking_details || '';
                document.getElementById('alcohol_use_edit').value = data.alcohol_use || 'None';
                document.getElementById('alcohol_details_edit').value = data.alcohol_details || '';
                document.getElementById('exercise_edit').value = data.exercise || '';
                document.getElementById('diet_edit').value = data.diet || '';
                document.getElementById('recreational_drug_use_edit').value = data.recreational_drug_use || '';
                new bootstrap.Modal(document.getElementById('editLifestyleModal')).show();
            })
            .catch(error => console.error('Error:', error));
    }

    // Initialize section on page load
    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        let section = urlParams.get('section') || 'default';
        
        // If a form was submitted, stay on the submitted section
        const submittedSection = '<?php echo $submitted_section; ?>';
        if (submittedSection !== '') {
            section = submittedSection;
        }
        
        showSection(section);
    });
</script>

<?php include "footer.php"; ?>
