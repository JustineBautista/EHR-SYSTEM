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

$page_title = "Lab Results";

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

// Lab Results processing
$msg = "";
$error = "";

if (isset($_POST['add_result'])) {
    $test_name = sanitize_input($conn, $_POST['test_name'] ?? "");
    $result_value = sanitize_input($conn, $_POST['result_value'] ?? "");
    $units = sanitize_input($conn, $_POST['units'] ?? "");
    $reference_range = sanitize_input($conn, $_POST['reference_range'] ?? "");
    $date_performed = $_POST['date_performed'] ?: date("Y-m-d");
    $notes = sanitize_input($conn, $_POST['notes'] ?? "");

    // Validate date format if provided
    if (!empty($_POST['date_performed']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_performed)) {
        $error = "Date performed must be in format YYYY-MM-DD.";
    } else {
        $stmt = $conn->prepare("INSERT INTO lab_results (patient_id, test_name, result_value, units, reference_range, date_performed, notes) VALUES (?,?,?,?,?,?,?)");
        $stmt->bind_param("issssss", $patient_id, $test_name, $result_value, $units, $reference_range, $date_performed, $notes);
        if ($stmt->execute()) {
            $msg = "Lab result added.";
        } else {
            $error = "Error: " . $stmt->error;
        }
    }
}

if (isset($_GET['delete_result'])) {
    $id = intval($_GET['delete_result']);
    $stmt = $conn->prepare("DELETE FROM lab_results WHERE id=? AND patient_id=?");
    $stmt->bind_param("ii", $id, $patient_id);
    if ($stmt->execute()) {
        header("Location: lab_results.php?patient_id=$patient_id");
        exit();
    }
    $stmt->close();
}

// Handle update lab results
if (isset($_POST['update_result'])) {
    $rid = intval($_POST['result_id']);
    $test_name = sanitize_input($conn, $_POST['test_name'] ?? "");
    $result_value = sanitize_input($conn, $_POST['result_value'] ?? "");
    $units = sanitize_input($conn, $_POST['units'] ?? "");
    $reference_range = sanitize_input($conn, $_POST['reference_range'] ?? "");
    $date_performed = $_POST['date_performed'] ?: date("Y-m-d");
    $notes = sanitize_input($conn, $_POST['notes'] ?? "");

    // Validate date format if provided
    if (!empty($_POST['date_performed']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_performed)) {
        $error = "Date performed must be in format YYYY-MM-DD.";
    } else {
        $stmt = $conn->prepare("UPDATE lab_results SET test_name=?, result_value=?, units=?, reference_range=?, date_performed=?, notes=? WHERE id=? AND patient_id=?");
        $stmt->bind_param("ssssssii", $test_name, $result_value, $units, $reference_range, $date_performed, $notes, $rid, $patient_id);
        if ($stmt->execute()) {
            $msg = "Lab result updated.";
        } else {
            $error = "Error updating result: " . $stmt->error;
        }
        $stmt->close();
    }
}

if (isset($_GET['get_result'])) {
    $id = intval($_GET['get_result']);
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
                    <h5 class="mb-0"><i class="bi bi-flask me-2"></i>Lab Results - <?php echo htmlspecialchars($patient['fullname']); ?></h5>
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

                    <!-- Lab Results Form -->
                    <div class="card p-3 mb-3">
                        <form method="post" class="row g-2">
                            <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
                            <div class="col-md-4"><input class="form-control" name="test_name" placeholder="Test Name" value="<?php echo htmlspecialchars($_POST['test_name'] ?? ''); ?>" required></div>
                            <div class="col-md-2"><input class="form-control" name="result_value" placeholder="Result Value" value="<?php echo htmlspecialchars($_POST['result_value'] ?? ''); ?>" required></div>
                            <div class="col-md-2"><input class="form-control" name="units" placeholder="Units" value="<?php echo htmlspecialchars($_POST['units'] ?? ''); ?>" required></div>
                            <div class="col-md-2"><input class="form-control" name="reference_range" placeholder="Reference Range" value="<?php echo htmlspecialchars($_POST['reference_range'] ?? ''); ?>" required></div>
                            <div class="col-md-2"><input type="date" class="form-control" name="date_performed" value="<?php echo htmlspecialchars($_POST['date_performed'] ?? date('Y-m-d')); ?>" required></div>
                            <div class="col-md-12"><textarea class="form-control" name="notes" placeholder="Notes" rows="2"><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?>"></textarea></div>
                            <div class="col-12"><button name="add_result" class="btn btn-primary">Add Lab Result</button></div>
                        </form>
                    </div>

                    <!-- Lab Results Table -->
                    <div class="card p-3">
                        <table class="table table-sm table-bordered">
                            <thead>
                                <tr>
                                    <th>Test Name</th>
                                    <th>Result Value</th>
                                    <th>Units</th>
                                    <th>Reference Range</th>
                                    <th>Date Performed</th>
                                    <th>Notes</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $stmt = $conn->prepare("SELECT * FROM lab_results WHERE patient_id = ? ORDER BY id DESC");
                                $stmt->bind_param("i", $patient_id);
                                $stmt->execute();
                                $result = $stmt->get_result();
                                while ($r = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($r['test_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($r['result_value'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($r['units'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($r['reference_range'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($r['date_performed'] ?? ''))); ?></td>
                                        <td><?php echo htmlspecialchars($r['notes'] ?? 'N/A'); ?></td>
                                        <td>
                                            <a class="btn btn-sm btn-danger" href="?delete_result=<?php echo $r['id']; ?>&patient_id=<?php echo $patient_id; ?>" onclick="return confirm('Delete?')">
                                                <i class="bi bi-trash"></i> Delete
                                            </a>
                                            <a class="btn btn-sm btn-warning" href="javascript:void(0)" onclick="editResult(<?php echo $r['id']; ?>)">
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

<!-- Edit Modal for Lab Results -->
<div class="modal fade" id="editResultModal" tabindex="-1" aria-labelledby="editResultModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" id="editResultForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="editResultModalLabel">Edit Lab Result</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="result_id" id="result_id">
                    <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
                    <div class="mb-3">
                        <label for="test_name_edit" class="form-label">Test Name</label>
                        <input type="text" class="form-control" name="test_name" id="test_name_edit" placeholder="Test Name" required>
                    </div>
                    <div class="mb-3">
                        <label for="result_value_edit" class="form-label">Result Value</label>
                        <input type="text" class="form-control" name="result_value" id="result_value_edit" placeholder="Result Value" required>
                    </div>
                    <div class="mb-3">
                        <label for="units_edit" class="form-label">Units</label>
                        <input type="text" class="form-control" name="units" id="units_edit" placeholder="Units" required>
                    </div>
                    <div class="mb-3">
                        <label for="reference_range_edit" class="form-label">Reference Range</label>
                        <input type="text" class="form-control" name="reference_range" id="reference_range_edit" placeholder="Reference Range" required>
                    </div>
                    <div class="mb-3">
                        <label for="date_performed_edit" class="form-label">Date Performed</label>
                        <input type="date" class="form-control" name="date_performed" id="date_performed_edit" required>
                    </div>
                    <div class="mb-3">
                        <label for="notes_result_edit" class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" id="notes_result_edit" placeholder="Notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="update_result" class="btn btn-primary">Save changes</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editResult(id) {
    fetch('?get_result=' + id)
        .then(response => response.json())
        .then(data => {
            document.getElementById('result_id').value = data.id;
            document.getElementById('test_name_edit').value = data.test_name;
            document.getElementById('result_value_edit').value = data.result_value;
            document.getElementById('units_edit').value = data.units;
            document.getElementById('reference_range_edit').value = data.reference_range;
            document.getElementById('date_performed_edit').value = data.date_performed;
            document.getElementById('notes_result_edit').value = data.notes;
            new bootstrap.Modal(document.getElementById('editResultModal')).show();
        })
        .catch(error => console.error('Error:', error));
}
</script>

<?php include "../footer.php"; ?>
