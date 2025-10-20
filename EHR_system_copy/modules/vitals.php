<?php
// Start session and check authentication first
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin'])) {
    header("Location: ../index.php");
    exit();
}

// Include required files AFTER session check
include "../db.php";
include "../audit_trail.php";

// Function to sanitize input
function sanitize_input($conn, $data) {
    return mysqli_real_escape_string($conn, trim($data));
}

$page_title = "Vitals";

// Get patient ID from URL
$patient_id = intval($_GET['patient_id'] ?? 0);
if ($patient_id <= 0) {
    header("Location: ../patients.php");
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
    header("Location: ../patients.php");
    exit();
}

// Vitals processing
$msg = "";
$error = "";

if (isset($_POST['add_vitals'])) {
    $recorded_by = sanitize_input($conn, $_POST['recorded_by'] ?? "");
    $bp = $_POST['bp'] ?? "";
    $hr = $_POST['hr'] ?? "";
    $temp = $_POST['temp'] ?? "";
    $height = $_POST['height'] ?? "";
    $weight = $_POST['weight'] ?? "";
    $oxygen_saturation = $_POST['oxygen_saturation'] ?? "";
    $pain_scale = $_POST['pain_scale'] ?? "";
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
    // Validate oxygen saturation (0-100%)
    elseif (!empty($oxygen_saturation) && (!is_numeric($oxygen_saturation) || $oxygen_saturation < 0 || $oxygen_saturation > 100)) {
        $error = "Oxygen saturation must be between 0-100%";
    }
    // Validate pain scale (0-10)
    elseif (!empty($pain_scale) && (!is_numeric($pain_scale) || $pain_scale < 0 || $pain_scale > 10)) {
        $error = "Pain scale must be between 0-10";
    }
    // Validate date format if provided
    elseif (!empty($_POST['date']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $error = "Date must be in format YYYY-MM-DD.";
    }
    else {
        // Calculate BMI: weight (kg) / (height (m))^2
        $height_m = $height / 100; // Convert cm to m
        $bmi = round($weight / ($height_m * $height_m), 2);
        $stmt = $conn->prepare("INSERT INTO vitals (patient_id, recorded_by, bp, hr, temp, height, weight, bmi, oxygen_saturation, pain_scale, date_taken) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param("isdddddidis", $patient_id, $recorded_by, $bp, $hr, $temp, $height, $weight, $bmi, $oxygen_saturation, $pain_scale, $date);
        if ($stmt->execute()) {
            $msg = "Vitals recorded.";
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
    $oxygen_saturation = $_POST['oxygen_saturation'] ?? "";
    $pain_scale = $_POST['pain_scale'] ?? "";
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
    elseif (!empty($oxygen_saturation) && (!is_numeric($oxygen_saturation) || $oxygen_saturation < 0 || $oxygen_saturation > 100)) {
        $error = "Oxygen saturation must be between 0-100%";
    }
    elseif (!empty($pain_scale) && (!is_numeric($pain_scale) || $pain_scale < 0 || $pain_scale > 10)) {
        $error = "Pain scale must be between 0-10";
    }
    elseif (!empty($_POST['date']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $error = "Date must be in format YYYY-MM-DD.";
    }
    else {
        // Calculate BMI: weight (kg) / (height (m))^2
        $height_m = $height / 100; // Convert cm to m
        $bmi = round($weight / ($height_m * $height_m), 2);
        $stmt = $conn->prepare("UPDATE vitals SET bp=?, hr=?, temp=?, height=?, weight=?, bmi=?, oxygen_saturation=?, pain_scale=?, date_taken=? WHERE id=? AND patient_id=?");
        $stmt->bind_param("sdddddiissii", $bp, $hr, $temp, $height, $weight, $bmi, $oxygen_saturation, $pain_scale, $date, $vid, $patient_id);
        if ($stmt->execute()) {
            $msg = "Vitals updated.";
        } else {
            $error = "Database error: " . $stmt->error;
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

include "../header.php";
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
</style>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-heart-pulse me-2"></i>Vitals - <?php echo htmlspecialchars($patient['fullname']); ?></h5>
                    <a href="../patient_dashboard.php?patient_id=<?php echo $patient_id; ?>" class="btn btn-secondary">Back to Dashboard</a>
                </div>
                <div class="card-body">
                    <!-- Feedback messages -->
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

                    <!-- Vitals Form -->
                    <div class="card p-3 mb-3">
                        <form method="post" class="row g-2">
                            <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
                            <div class="col-md-2"><input type="text" class="form-control" name="recorded_by" placeholder="Recorded By" value="<?php echo htmlspecialchars($_POST['recorded_by'] ?? ''); ?>" required></div>
                            <div class="col-md-2"><input class="form-control" name="bp" placeholder="BP (e.g., 120/80)" value="<?php echo htmlspecialchars($_POST['bp'] ?? ''); ?>" required></div>
                            <div class="col-md-2"><input type="number" class="form-control" name="hr" placeholder="HR (bpm)" value="<?php echo htmlspecialchars($_POST['hr'] ?? ''); ?>" required></div>
                            <div class="col-md-2"><input type="number" step="0.1" class="form-control" name="temp" placeholder="Temp (°C)" value="<?php echo htmlspecialchars($_POST['temp'] ?? ''); ?>" required></div>
                            <div class="col-md-2"><input type="number" step="0.1" class="form-control" name="height" placeholder="Height (cm)" value="<?php echo htmlspecialchars($_POST['height'] ?? ''); ?>" required></div>
                            <div class="col-md-2"><input type="number" step="0.1" class="form-control" name="weight" placeholder="Weight (kg)" value="<?php echo htmlspecialchars($_POST['weight'] ?? ''); ?>" required></div>
                            <div class="col-md-2"><input type="number" step="0.1" class="form-control" name="bmi" placeholder="BMI" value="<?php echo htmlspecialchars($_POST['bmi'] ?? ''); ?>" readonly></div>
                            <div class="col-md-2"><input type="number" class="form-control" name="oxygen_saturation" placeholder="O2 Sat (%)" min="0" max="100" value="<?php echo htmlspecialchars($_POST['oxygen_saturation'] ?? ''); ?>"></div>
                            <div class="col-md-2"><input type="number" class="form-control" name="pain_scale" placeholder="Pain (0-10)" min="0" max="10" value="<?php echo htmlspecialchars($_POST['pain_scale'] ?? ''); ?>"></div>
                            <div class="col-md-2"><input type="date" class="form-control" name="date" value="<?php echo htmlspecialchars($_POST['date'] ?? date('Y-m-d')); ?>"></div>
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
                                    <th>HR</th>
                                    <th>Temp</th>
                                    <th>Height</th>
                                    <th>Weight</th>
                                    <th>BMI</th>
                                    <th>Oxygen Saturation</th>
                                    <th>Pain Scale</th>
                                    <th>Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $stmt = $conn->prepare("SELECT * FROM vitals WHERE patient_id = ? ORDER BY id DESC");
                                $stmt->bind_param("i", $patient_id);
                                $stmt->execute();
                                $result = $stmt->get_result();
                                while ($r = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($r['recorded_by']); ?></td>
                                        <td><?php echo htmlspecialchars($r['bp']); ?></td>
                                        <td><?php echo htmlspecialchars($r['hr']); ?></td>
                                        <td><?php echo htmlspecialchars($r['temp']); ?></td>
                                        <td><?php echo htmlspecialchars($r['height']); ?></td>
                                        <td><?php echo htmlspecialchars($r['weight']); ?></td>
                                        <td><?php echo htmlspecialchars($r['bmi']); ?></td>
                                        <td><?php echo htmlspecialchars($r['oxygen_saturation']); ?></td>
                                        <td><?php echo htmlspecialchars($r['pain_scale']); ?></td>
                                        <td><?php echo htmlspecialchars(substr($r['date_taken'], 0, 10)); ?></td>
                                        <td>
                                            <a class="btn btn-sm btn-danger" href="?delete_vital=<?php echo $r['id']; ?>&patient_id=<?php echo $patient_id; ?>" onclick="return confirm('Delete?')">
                                                <i class="bi bi-trash"></i> Delete
                                            </a>
                                            <a class="btn btn-sm btn-warning" href="javascript:void(0)" onclick="editVital(<?php echo $r['id']; ?>)">
                                                <i class="bi bi-pencil"></i> Edit
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
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
                            <label for="oxygen_saturation_edit" class="form-label">Oxygen Saturation</label>
                            <input type="number" class="form-control" name="oxygen_saturation" id="oxygen_saturation_edit" placeholder="Oxygen Saturation (%)" min="0" max="100">
                        </div>
                        <div class="mb-3">
                            <label for="pain_scale_edit" class="form-label">Pain Scale</label>
                            <input type="number" class="form-control" name="pain_scale" id="pain_scale_edit" placeholder="Pain Scale (0-10)" min="0" max="10">
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

<script>
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
            document.getElementById('oxygen_saturation_edit').value = data.oxygen_saturation;
            document.getElementById('pain_scale_edit').value = data.pain_scale;
            document.getElementById('date_edit').value = data.date_taken.substring(0, 10);
            new bootstrap.Modal(document.getElementById('editVitalModal')).show();
        })
        .catch(error => console.error('Error:', error));
}

// Function to calculate BMI when height or weight changes
function calculateBMI() {
    const height = parseFloat(document.querySelector('input[name="height"]').value);
    const weight = parseFloat(document.querySelector('input[name="weight"]').value);
    const bmiField = document.querySelector('input[name="bmi"]');

    if (height > 0 && weight > 0) {
        const heightM = height / 100;
        const bmi = (weight / (heightM * heightM)).toFixed(2);
        bmiField.value = bmi;
    } else {
        bmiField.value = '';
    }
}

// Add event listeners to height and weight inputs
document.addEventListener('DOMContentLoaded', function() {
    const heightInput = document.querySelector('input[name="height"]');
    const weightInput = document.querySelector('input[name="weight"]');

    if (heightInput && weightInput) {
        heightInput.addEventListener('input', calculateBMI);
        weightInput.addEventListener('input', calculateBMI);
    }
});
</script>

<?php include "../footer.php"; ?>
