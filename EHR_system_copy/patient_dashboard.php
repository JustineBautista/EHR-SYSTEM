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
$tables = ['medical_history', 'medications', 'vitals', 'diagnostics', 'treatment_plans', 'lab_results', 'progress_notes'];

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
    if ($stmt->execute()) $msg = "Deleted.";
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

    // Validate medication (required)
    if (empty($med)) {
        $error = "Medication is required.";
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

include "header.php";
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

<!-- Edit Modal for Vitals -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" id="editForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="editModalLabel">Edit Vital Signs</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="vital_id" id="vital_id">
                    <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
                    <div class="mb-3">
                        <label for="bp" class="form-label">Blood Pressure (e.g., 120/80)</label>
                        <input type="text" class="form-control" name="bp" id="bp" placeholder="BP (e.g., 120/80)">
                    </div>
                    <div class="mb-3">
                        <label for="hr" class="form-label">Heart Rate (bpm)</label>
                        <input type="number" class="form-control" name="hr" id="hr" placeholder="HR (bpm)">
                    </div>
                    <div class="mb-3">
                        <label for="temp" class="form-label">Temperature (°C)</label>
                        <input type="number" step="0.1" class="form-control" name="temp" id="temp" placeholder="Temp (°C)">
                    </div>
                    <div class="mb-3">
                        <label for="height" class="form-label">Height (cm)</label>
                        <input type="number" step="0.1" class="form-control" name="height" id="height" placeholder="Height (cm)">
                    </div>
                    <div class="mb-3">
                        <label for="weight" class="form-label">Weight (kg)</label>
                        <input type="number" step="0.1" class="form-control" name="weight" id="weight" placeholder="Weight (kg)">
                    </div>
                    <div class="mb-3">
                        <label for="date" class="form-label">Date</label>
                        <input class="form-control" name="date" id="date" placeholder="YYYY-MM-DD">
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
                        <input type="text" class="form-control" name="start_date" id="start_date" placeholder="YYYY-MM-DD">
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
                        <i class="bi bi-heart-pulse me-2"></i>Record Vitals
                    </button>
                    <button onclick="showSection('medications')" class="btn btn-outline-primary w-100 mb-2">
                        <i class="bi bi-capsule me-2"></i>Enter Medications
                    </button>
                    <a href="progress_notes.php?patient_id=<?php echo $patient_id; ?>" class="btn btn-outline-primary w-100 mb-2">
                        <i class="bi bi-pencil-square me-2"></i>Progress Notes
                    </a>
                    <a href="diagnostics.php?patient_id=<?php echo $patient_id; ?>" class="btn btn-outline-primary w-100 mb-2">
                        <i class="bi bi-search me-2"></i>Diagnostics
                    </a>
                    <a href="treatment_plans.php?patient_id=<?php echo $patient_id; ?>" class="btn btn-outline-primary w-100 mb-2">
                        <i class="bi bi-journal-text me-2"></i>Treatment Plans
                    </a>
                    <a href="lab_results.php?patient_id=<?php echo $patient_id; ?>" class="btn btn-outline-primary w-100 mb-2">
                        <i class="bi bi-test-tube me-2"></i>Lab Results
                    </a>
                    <a href="medical_history.php?patient_id=<?php echo $patient_id; ?>" class="btn btn-outline-primary w-100">
                        <i class="bi bi-clipboard-data me-2"></i>Medical History
                    </a>
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
                                        <p><strong>Name:</strong> <?php echo htmlspecialchars($patient['fullname']); ?></p>
                                        <p><strong>Date of Birth:</strong> <?php echo htmlspecialchars($patient['dob'] ?: 'N/A'); ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Gender:</strong> <?php echo htmlspecialchars($patient['gender'] ?: 'N/A'); ?></p>
                                        <p><strong>Contact:</strong> <?php echo htmlspecialchars($patient['contact'] ?: 'N/A'); ?></p>
                                        <p><strong>Address:</strong> <?php echo htmlspecialchars($patient['address'] ?: 'N/A'); ?></p>
                                    </div>
                                </div>
                                <p><strong>Medical History:</strong> <?php echo htmlspecialchars($patient['history'] ?: 'No history recorded'); ?></p>
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
                                ['key' => 'lab_results', 'title' => 'Lab Results', 'fields' => ['test_name', 'test_result', 'date_taken'], 'icon' => 'bi-test-tube'],
                                ['key' => 'progress_notes', 'title' => 'Progress Notes', 'fields' => ['note', 'author', 'date_written'], 'icon' => 'bi-pencil-square']
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
                                <div class="col-md-2"><input class="form-control" name="date" placeholder="YYYY-MM-DD (Optional)" value="<?php echo htmlspecialchars($_POST['date'] ?? date('Y-m-d')); ?>"></div>
                                <div class="col-12"><button name="add_vitals" class="btn btn-primary">Record Vitals</button></div>
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
                                                <a class="btn btn-sm btn-warning" href="#" onclick="editVital(<?php echo $r['id']; ?>)">
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
                                <div class="col-md-3"><input class="form-control" name="dose" placeholder="Dose" value="<?php echo htmlspecialchars($_POST['dose'] ?? ''); ?>"></div>
                                <div class="col-md-3"><input class="form-control" name="start_date" placeholder="Start Date (YYYY-MM-DD)" value="<?php echo htmlspecialchars($_POST['start_date'] ?? ''); ?>"></div>
                                <div class="col-md-3"><input class="form-control" name="notes" placeholder="Notes" value="<?php echo htmlspecialchars($_POST['notes'] ?? ''); ?>"></div>
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
                                                <a class="btn btn-sm btn-danger" href="?delete_med=<?php echo $r['id']; ?>&patient_id=<?php echo $patient_id; ?>" onclick="return confirm('Delete?')">
                                                    <i class="bi bi-trash"></i> Delete
                                                </a>
                                                <a class="btn btn-sm btn-warning" href="#" onclick="editMed(<?php echo $r['id']; ?>)">
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

                    <script>
                    function showSection(section) {
                        // Hide all sections
                        document.getElementById('default-content').style.display = 'none';
                        document.getElementById('vitals-content').style.display = 'none';
                        document.getElementById('medications-content').style.display = 'none';
                        // Show selected
                        if (section === 'default') {
                            document.getElementById('default-content').style.display = 'block';
                        } else if (section === 'vitals') {
                            document.getElementById('vitals-content').style.display = 'block';
                        } else if (section === 'medications') {
                            document.getElementById('medications-content').style.display = 'block';
                        }
                    }

                    function editVital(id) {
                        fetch('?get_vital=' + id)
                            .then(response => response.json())
                            .then(data => {
                                document.getElementById('vital_id').value = data.id;
                                document.getElementById('bp').value = data.bp;
                                document.getElementById('hr').value = data.hr;
                                document.getElementById('temp').value = data.temp;
                                document.getElementById('height').value = data.height;
                                document.getElementById('weight').value = data.weight;
                                document.getElementById('date').value = data.date_taken;
                                new bootstrap.Modal(document.getElementById('editModal')).show();
                            })
                            .catch(error => console.error('Error:', error));
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

                    // Initially show default content or section from URL
                    const urlParams = new URLSearchParams(window.location.search);
                    const section = urlParams.get('section') || 'default';
                    showSection(section);
                    </script>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include "footer.php"; ?>
