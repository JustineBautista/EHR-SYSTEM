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

$page_title = "Lab Diagnostic Results";

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

// Lab Diagnostic Results processing
$msg = "";
$error = "";

if (isset($_POST['add_lab_diag'])) {
    $test_type = sanitize_input($conn, $_POST['test_type'] ?? "");
    $test_name = sanitize_input($conn, $_POST['test_name'] ?? "");
    $result = sanitize_input($conn, $_POST['result'] ?? "");
    $normal_range = sanitize_input($conn, $_POST['normal_range'] ?? "");
    $date_ordered = $_POST['date_ordered'] ?: date("Y-m-d");
    $date_completed = $_POST['date_completed'] ?: null;
    $notes = sanitize_input($conn, $_POST['notes'] ?? "");

    // Validate date format if provided
    if (!empty($_POST['date_ordered']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_ordered)) {
        $error = "Date ordered must be in format YYYY-MM-DD.";
    }
    elseif (!empty($_POST['date_completed']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_completed)) {
        $error = "Date completed must be in format YYYY-MM-DD.";
    }
    elseif (!empty($date_ordered) && !empty($date_completed) && strtotime($date_ordered) > strtotime($date_completed)) {
        $error = "Date completed must be after date ordered.";
    }
    else {
        $stmt = $conn->prepare("INSERT INTO lab_diagnostic_results (patient_id, test_type, test_name, result, normal_range, date_ordered, date_completed, notes) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->bind_param("isssssss", $patient_id, $test_type, $test_name, $result, $normal_range, $date_ordered, $date_completed, $notes);
        if ($stmt->execute()) {
            $msg = "Lab diagnostic result added.";
        } else {
            $error = "Error: " . $stmt->error;
        }
    }
}

if (isset($_GET['delete_lab_diag'])) {
    $id = intval($_GET['delete_lab_diag']);
    $stmt = $conn->prepare("DELETE FROM lab_diagnostic_results WHERE id=? AND patient_id=?");
    $stmt->bind_param("ii", $id, $patient_id);
    if ($stmt->execute()) {
        header("Location: lab_diagnostic_results.php?patient_id=$patient_id");
        exit();
    }
    $stmt->close();
}

// Handle update lab diagnostic results
if (isset($_POST['update_lab_diag'])) {
    $ldid = intval($_POST['lab_diag_id']);
    $test_type = sanitize_input($conn, $_POST['test_type'] ?? "");
    $test_name = sanitize_input($conn, $_POST['test_name'] ?? "");
    $result = sanitize_input($conn, $_POST['result'] ?? "");
    $normal_range = sanitize_input($conn, $_POST['normal_range'] ?? "");
    $date_ordered = $_POST['date_ordered'] ?: date("Y-m-d");
    $date_completed = $_POST['date_completed'] ?: null;
    $notes = sanitize_input($conn, $_POST['notes'] ?? "");

    // Validate date format if provided
    if (!empty($_POST['date_ordered']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_ordered)) {
        $error = "Date ordered must be in format YYYY-MM-DD.";
    }
    elseif (!empty($_POST['date_completed']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_completed)) {
        $error = "Date completed must be in format YYYY-MM-DD.";
    }
    elseif (!empty($date_ordered) && !empty($date_completed) && strtotime($date_ordered) > strtotime($date_completed)) {
        $error = "Date completed must be after date ordered.";
    }
    else {
        $stmt = $conn->prepare("UPDATE lab_diagnostic_results SET test_type=?, test_name=?, result=?, normal_range=?, date_ordered=?, date_completed=?, notes=? WHERE id=? AND patient_id=?");
        $stmt->bind_param("sssssssii", $test_type, $test_name, $result, $normal_range, $date_ordered, $date_completed, $notes, $ldid, $patient_id);
        if ($stmt->execute()) {
            $msg = "Lab diagnostic result updated.";
        } else {
            $error = "Error updating result: " . $stmt->error;
        }
        $stmt->close();
    }
}

if (isset($_GET['get_lab_diag'])) {
    $id = intval($_GET['get_lab_diag']);
    $stmt = $conn->prepare("SELECT * FROM lab_diagnostic_results WHERE id=? AND patient_id=?");
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
                    <h5 class="mb-0"><i class="bi bi-clipboard-data me-2"></i>Lab Diagnostic Results - <?php echo htmlspecialchars($patient['fullname']); ?></h5>
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

                    <!-- Lab Diagnostic Results Form -->
                    <div class="card p-3 mb-3">
                        <form method="post" class="row g-2">
                            <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
                            <div class="col-md-3"><input class="form-control" name="test_type" placeholder="Test Type (e.g., Blood, Urine)" value="<?php echo htmlspecialchars($_POST['test_type'] ?? ''); ?>" required></div>
                            <div class="col-md-3"><input class="form-control" name="test_name" placeholder="Test Name" value="<?php echo htmlspecialchars($_POST['test_name'] ?? ''); ?>" required></div>
                            <div class="col-md-2"><input class="form-control" name="result" placeholder="Result" value="<?php echo htmlspecialchars($_POST['result'] ?? ''); ?>" required></div>
                            <div class="col-md-2"><input class="form-control" name="normal_range" placeholder="Normal Range" value="<?php echo htmlspecialchars($_POST['normal_range'] ?? ''); ?>" required></div>
                            <div class="col-md-2"><input type="date" class="form-control" name="date_ordered" value="<?php echo htmlspecialchars($_POST['date_ordered'] ?? date('Y-m-d')); ?>" required></div>
                            <div class="col-md-2"><input type="date" class="form-control" name="date_completed" value="<?php echo htmlspecialchars($_POST['date_completed'] ?? ''); ?>"></div>
                            <div class="col-md-10"><textarea class="form-control" name="notes" placeholder="Notes" rows="2"><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?>"></textarea></div>
                            <div class="col-12"><button name="add_lab_diag" class="btn btn-primary">Add Lab Diagnostic Result</button></div>
                        </form>
                    </div>

                    <!-- Lab Diagnostic Results Table -->
                    <div class="card p-3">
                        <table class="table table-sm table-bordered">
                            <thead>
                                <tr>
                                    <th>Test Type</th>
                                    <th>Test Name</th>
                                    <th>Result</th>
                                    <th>Normal Range</th>
                                    <th>Date Ordered</th>
                                    <th>Date Completed</th>
                                    <th>Notes</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $stmt = $conn->prepare("SELECT * FROM lab_diagnostic_results WHERE patient_id = ? ORDER BY id DESC");
                                $stmt->bind_param("i", $patient_id);
                                $stmt->execute();
                                $result = $stmt->get_result();
                                while ($r = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($r['test_type'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($r['test_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($r['result'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($r['normal_range'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($r['date_ordered'] ?? ''))); ?></td>
                                        <td><?php echo htmlspecialchars($r['date_completed'] ? date('Y-m-d', strtotime($r['date_completed'])) : 'Pending'); ?></td>
                                        <td><?php echo htmlspecialchars($r['notes'] ?? 'N/A'); ?></td>
                                        <td>
                                            <a class="btn btn-sm btn-danger" href="?delete_lab_diag=<?php echo $r['id']; ?>&patient_id=<?php echo $patient_id; ?>" onclick="return confirm('Delete?')">
                                                <i class="bi bi-trash"></i> Delete
                                            </a>
                                            <a class="btn btn-sm btn-warning" href="javascript:void(0)" onclick="editLabDiag(<?php echo $r['id']; ?>)">
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

<!-- Edit Modal for Lab Diagnostic Results -->
<div class="modal fade" id="editLabDiagModal" tabindex="-1" aria-labelledby="editLabDiagModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" id="editLabDiagForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="editLabDiagModalLabel">Edit Lab Diagnostic Result</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="lab_diag_id" id="lab_diag_id">
                    <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
                    <div class="mb-3">
                        <label for="test_type_edit" class="form-label">Test Type</label>
                        <input type="text" class="form-control" name="test_type" id="test_type_edit" placeholder="Test Type (e.g., Blood, Urine)" required>
                    </div>
                    <div class="mb-3">
                        <label for="test_name_edit" class="form-label">Test Name</label>
                        <input type="text" class="form-control" name="test_name" id="test_name_edit" placeholder="Test Name" required>
                    </div>
                    <div class="mb-3">
                        <label for="result_edit" class="form-label">Result</label>
                        <input type="text" class="form-control" name="result" id="result_edit" placeholder="Result" required>
                    </div>
                    <div class="mb-3">
                        <label for="normal_range_edit" class="form-label">Normal Range</label>
                        <input type="text" class="form-control" name="normal_range" id="normal_range_edit" placeholder="Normal Range" required>
                    </div>
                    <div class="mb-3">
                        <label for="date_ordered_edit" class="form-label">Date Ordered</label>
                        <input type="date" class="form-control" name="date_ordered" id="date_ordered_edit" required>
                    </div>
                    <div class="mb-3">
                        <label for="date_completed_edit" class="form-label">Date Completed</label>
                        <input type="date" class="form-control" name="date_completed" id="date_completed_edit">
                    </div>
                    <div class="mb-3">
                        <label for="notes_lab_diag_edit" class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" id="notes_lab_diag_edit" placeholder="Notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="update_lab_diag" class="btn btn-primary">Save changes</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editLabDiag(id) {
    fetch('?get_lab_diag=' + id)
        .then(response => response.json())
        .then(data => {
            document.getElementById('lab_diag_id').value = data.id;
            document.getElementById('test_type_edit').value = data.test_type;
            document.getElementById('test_name_edit').value = data.test_name;
            document.getElementById('result_edit').value = data.result;
            document.getElementById('normal_range_edit').value = data.normal_range;
            document.getElementById('date_ordered_edit').value = data.date_ordered;
            document.getElementById('date_completed_edit').value = data.date_completed;
            document.getElementById('notes_lab_diag_edit').value = data.notes;
            new bootstrap.Modal(document.getElementById('editLabDiagModal')).show();
        })
        .catch(error => console.error('Error:', error));
}
</script>

<?php include "../footer.php"; ?>
