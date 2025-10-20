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

$page_title = "Medical History";

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

// Medical History processing
$msg = "";
$error = "";

if (isset($_POST['add_history'])) {
    $condition = sanitize_input($conn, $_POST['condition'] ?? "");
    $diagnosis_date = $_POST['diagnosis_date'] ?: date("Y-m-d");
    $status = sanitize_input($conn, $_POST['status'] ?? "");
    $notes = sanitize_input($conn, $_POST['notes'] ?? "");

    // Validate date format if provided
    if (!empty($_POST['diagnosis_date']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $diagnosis_date)) {
        $error = "Diagnosis date must be in format YYYY-MM-DD.";
    } else {
        $stmt = $conn->prepare("INSERT INTO medical_history (patient_id, condition, diagnosis_date, status, notes) VALUES (?,?,?,?,?)");
        $stmt->bind_param("issss", $patient_id, $condition, $diagnosis_date, $status, $notes);
        if ($stmt->execute()) {
            $msg = "Medical history added.";
        } else {
            $error = "Error: " . $stmt->error;
        }
    }
}

if (isset($_GET['delete_history'])) {
    $id = intval($_GET['delete_history']);
    $stmt = $conn->prepare("DELETE FROM medical_history WHERE id=? AND patient_id=?");
    $stmt->bind_param("ii", $id, $patient_id);
    if ($stmt->execute()) {
        header("Location: medical_history.php?patient_id=$patient_id");
        exit();
    }
    $stmt->close();
}

// Handle update medical history
if (isset($_POST['update_history'])) {
    $hid = intval($_POST['history_id']);
    $condition = sanitize_input($conn, $_POST['condition'] ?? "");
    $diagnosis_date = $_POST['diagnosis_date'] ?: date("Y-m-d");
    $status = sanitize_input($conn, $_POST['status'] ?? "");
    $notes = sanitize_input($conn, $_POST['notes'] ?? "");

    // Validate date format if provided
    if (!empty($_POST['diagnosis_date']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $diagnosis_date)) {
        $error = "Diagnosis date must be in format YYYY-MM-DD.";
    } else {
        $stmt = $conn->prepare("UPDATE medical_history SET condition=?, diagnosis_date=?, status=?, notes=? WHERE id=? AND patient_id=?");
        $stmt->bind_param("ssssii", $condition, $diagnosis_date, $status, $notes, $hid, $patient_id);
        if ($stmt->execute()) {
            $msg = "Medical history updated.";
        } else {
            $error = "Error updating history: " . $stmt->error;
        }
        $stmt->close();
    }
}

if (isset($_GET['get_history'])) {
    $id = intval($_GET['get_history']);
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
                    <h5 class="mb-0"><i class="bi bi-clipboard-data me-2"></i>Medical History - <?php echo htmlspecialchars($patient['fullname']); ?></h5>
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

                    <!-- Medical History Form -->
                    <div class="card p-3 mb-3">
                        <form method="post" class="row g-2">
                            <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
                            <div class="col-md-6"><input class="form-control" name="condition" placeholder="Condition/Diagnosis" value="<?php echo htmlspecialchars($_POST['condition'] ?? ''); ?>" required></div>
                            <div class="col-md-3"><input type="date" class="form-control" name="diagnosis_date" value="<?php echo htmlspecialchars($_POST['diagnosis_date'] ?? date('Y-m-d')); ?>" required></div>
                            <div class="col-md-3">
                                <select class="form-control" name="status" required>
                                    <option value="">Select Status</option>
                                    <option value="Active" <?php if (($_POST['status'] ?? '') == 'Active') echo 'selected'; ?>>Active</option>
                                    <option value="Resolved" <?php if (($_POST['status'] ?? '') == 'Resolved') echo 'selected'; ?>>Resolved</option>
                                    <option value="Chronic" <?php if (($_POST['status'] ?? '') == 'Chronic') echo 'selected'; ?>>Chronic</option>
                                    <option value="Remission" <?php if (($_POST['status'] ?? '') == 'Remission') echo 'selected'; ?>>Remission</option>
                                </select>
                            </div>
                            <div class="col-md-12"><textarea class="form-control" name="notes" placeholder="Notes" rows="2"><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea></div>
                            <div class="col-12"><button name="add_history" class="btn btn-primary">Add Medical History</button></div>
                        </form>
                    </div>

                    <!-- Medical History Table -->
                    <div class="card p-3">
                        <table class="table table-sm table-bordered">
                            <thead>
                                <tr>
                                    <th>Condition</th>
                                    <th>Diagnosis Date</th>
                                    <th>Status</th>
                                    <th>Notes</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $stmt = $conn->prepare("SELECT * FROM medical_history WHERE patient_id = ? ORDER BY id DESC");
                                $stmt->bind_param("i", $patient_id);
                                $stmt->execute();
                                $result = $stmt->get_result();
                                while ($r = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($r['condition'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($r['diagnosis_date'] ?? ''))); ?></td>
                                        <td><?php echo htmlspecialchars($r['status'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($r['notes'] ?? 'N/A'); ?></td>
                                        <td>
                                            <a class="btn btn-sm btn-danger" href="?delete_history=<?php echo $r['id']; ?>&patient_id=<?php echo $patient_id; ?>" onclick="return confirm('Delete?')">
                                                <i class="bi bi-trash"></i> Delete
                                            </a>
                                            <a class="btn btn-sm btn-warning" href="javascript:void(0)" onclick="editHistory(<?php echo $r['id']; ?>)">
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

<!-- Edit Modal for Medical History -->
<div class="modal fade" id="editHistoryModal" tabindex="-1" aria-labelledby="editHistoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" id="editHistoryForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="editHistoryModalLabel">Edit Medical History</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="history_id" id="history_id">
                    <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
                    <div class="mb-3">
                        <label for="condition_edit" class="form-label">Condition</label>
                        <input type="text" class="form-control" name="condition" id="condition_edit" placeholder="Condition/Diagnosis" required>
                    </div>
                    <div class="mb-3">
                        <label for="diagnosis_date_edit" class="form-label">Diagnosis Date</label>
                        <input type="date" class="form-control" name="diagnosis_date" id="diagnosis_date_edit" required>
                    </div>
                    <div class="mb-3">
                        <label for="status_edit" class="form-label">Status</label>
                        <select class="form-control" name="status" id="status_edit" required>
                            <option value="">Select Status</option>
                            <option value="Active">Active</option>
                            <option value="Resolved">Resolved</option>
                            <option value="Chronic">Chronic</option>
                            <option value="Remission">Remission</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="notes_edit" class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" id="notes_edit" placeholder="Notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="update_history" class="btn btn-primary">Save changes</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editHistory(id) {
    fetch('?get_history=' + id)
        .then(response => response.json())
        .then(data => {
            document.getElementById('history_id').value = data.id;
            document.getElementById('condition_edit').value = data.condition;
            document.getElementById('diagnosis_date_edit').value = data.diagnosis_date;
            document.getElementById('status_edit').value = data.status;
            document.getElementById('notes_edit').value = data.notes;
            new bootstrap.Modal(document.getElementById('editHistoryModal')).show();
        })
        .catch(error => console.error('Error:', error));
}
</script>

<?php include "../footer.php"; ?>
