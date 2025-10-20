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

$page_title = "Diagnostics";

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

// Diagnostics processing
$msg = "";
$error = "";

if (isset($_POST['add_diagnostic'])) {
    $diagnostic_type = sanitize_input($conn, $_POST['diagnostic_type'] ?? "");
    $description = sanitize_input($conn, $_POST['description'] ?? "");
    $date_performed = $_POST['date_performed'] ?: date("Y-m-d");
    $results = sanitize_input($conn, $_POST['results'] ?? "");
    $notes = sanitize_input($conn, $_POST['notes'] ?? "");

    // Validate date format if provided
    if (!empty($_POST['date_performed']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_performed)) {
        $error = "Date performed must be in format YYYY-MM-DD.";
    } else {
        $stmt = $conn->prepare("INSERT INTO diagnostics (patient_id, diagnostic_type, description, date_performed, results, notes) VALUES (?,?,?,?,?,?)");
        $stmt->bind_param("isssss", $patient_id, $diagnostic_type, $description, $date_performed, $results, $notes);
        if ($stmt->execute()) {
            $msg = "Diagnostic added.";
        } else {
            $error = "Error: " . $stmt->error;
        }
    }
}

if (isset($_GET['delete_diagnostic'])) {
    $id = intval($_GET['delete_diagnostic']);
    $stmt = $conn->prepare("DELETE FROM diagnostics WHERE id=? AND patient_id=?");
    $stmt->bind_param("ii", $id, $patient_id);
    if ($stmt->execute()) {
        header("Location: diagnostics.php?patient_id=$patient_id");
        exit();
    }
    $stmt->close();
}

// Handle update diagnostics
if (isset($_POST['update_diagnostic'])) {
    $did = intval($_POST['diagnostic_id']);
    $diagnostic_type = sanitize_input($conn, $_POST['diagnostic_type'] ?? "");
    $description = sanitize_input($conn, $_POST['description'] ?? "");
    $date_performed = $_POST['date_performed'] ?: date("Y-m-d");
    $results = sanitize_input($conn, $_POST['results'] ?? "");
    $notes = sanitize_input($conn, $_POST['notes'] ?? "");

    // Validate date format if provided
    if (!empty($_POST['date_performed']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_performed)) {
        $error = "Date performed must be in format YYYY-MM-DD.";
    } else {
        $stmt = $conn->prepare("UPDATE diagnostics SET diagnostic_type=?, description=?, date_performed=?, results=?, notes=? WHERE id=? AND patient_id=?");
        $stmt->bind_param("sssssii", $diagnostic_type, $description, $date_performed, $results, $notes, $did, $patient_id);
        if ($stmt->execute()) {
            $msg = "Diagnostic updated.";
        } else {
            $error = "Error updating diagnostic: " . $stmt->error;
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
                    <h5 class="mb-0"><i class="bi bi-search me-2"></i>Diagnostics - <?php echo htmlspecialchars($patient['fullname']); ?></h5>
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

                    <!-- Diagnostics Form -->
                    <div class="card p-3 mb-3">
                        <form method="post" class="row g-2">
                            <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
                            <div class="col-md-4"><input class="form-control" name="diagnostic_type" placeholder="Diagnostic Type (e.g., X-Ray, MRI)" value="<?php echo htmlspecialchars($_POST['diagnostic_type'] ?? ''); ?>" required></div>
                            <div class="col-md-4"><input class="form-control" name="description" placeholder="Description" value="<?php echo htmlspecialchars($_POST['description'] ?? ''); ?>" required></div>
                            <div class="col-md-4"><input type="date" class="form-control" name="date_performed" value="<?php echo htmlspecialchars($_POST['date_performed'] ?? date('Y-m-d')); ?>" required></div>
                            <div class="col-md-6"><textarea class="form-control" name="results" placeholder="Results" rows="2" required><?php echo htmlspecialchars($_POST['results'] ?? ''); ?></textarea></div>
                            <div class="col-md-6"><textarea class="form-control" name="notes" placeholder="Notes" rows="2"><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?>"></textarea></div>
                            <div class="col-12"><button name="add_diagnostic" class="btn btn-primary">Add Diagnostic</button></div>
                        </form>
                    </div>

                    <!-- Diagnostics Table -->
                    <div class="card p-3">
                        <table class="table table-sm table-bordered">
                            <thead>
                                <tr>
                                    <th>Diagnostic Type</th>
                                    <th>Description</th>
                                    <th>Date Performed</th>
                                    <th>Results</th>
                                    <th>Notes</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $stmt = $conn->prepare("SELECT * FROM diagnostics WHERE patient_id = ? ORDER BY id DESC");
                                $stmt->bind_param("i", $patient_id);
                                $stmt->execute();
                                $result = $stmt->get_result();
                                while ($r = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($r['diagnostic_type'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($r['description'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($r['date_performed'] ?? ''))); ?></td>
                                        <td><?php echo htmlspecialchars($r['results'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($r['notes'] ?? 'N/A'); ?></td>
                                        <td>
                                            <a class="btn btn-sm btn-danger" href="?delete_diagnostic=<?php echo $r['id']; ?>&patient_id=<?php echo $patient_id; ?>" onclick="return confirm('Delete?')">
                                                <i class="bi bi-trash"></i> Delete
                                            </a>
                                            <a class="btn btn-sm btn-warning" href="javascript:void(0)" onclick="editDiagnostic(<?php echo $r['id']; ?>)">
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
                        <label for="diagnostic_type_edit" class="form-label">Diagnostic Type</label>
                        <input type="text" class="form-control" name="diagnostic_type" id="diagnostic_type_edit" placeholder="Diagnostic Type (e.g., X-Ray, MRI)" required>
                    </div>
                    <div class="mb-3">
                        <label for="description_edit" class="form-label">Description</label>
                        <input type="text" class="form-control" name="description" id="description_edit" placeholder="Description" required>
                    </div>
                    <div class="mb-3">
                        <label for="date_performed_diag_edit" class="form-label">Date Performed</label>
                        <input type="date" class="form-control" name="date_performed" id="date_performed_diag_edit" required>
                    </div>
                    <div class="mb-3">
                        <label for="results_edit" class="form-label">Results</label>
                        <textarea class="form-control" name="results" id="results_edit" placeholder="Results" rows="3" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="notes_diag_edit" class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" id="notes_diag_edit" placeholder="Notes" rows="3"></textarea>
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

<script>
function editDiagnostic(id) {
    fetch('?get_diagnostic=' + id)
        .then(response => response.json())
        .then(data => {
            document.getElementById('diagnostic_id').value = data.id;
            document.getElementById('diagnostic_type_edit').value = data.diagnostic_type;
            document.getElementById('description_edit').value = data.description;
            document.getElementById('date_performed_diag_edit').value = data.date_performed;
            document.getElementById('results_edit').value = data.results;
            document.getElementById('notes_diag_edit').value = data.notes;
            new bootstrap.Modal(document.getElementById('editDiagnosticModal')).show();
        })
        .catch(error => console.error('Error:', error));
}
</script>

<?php include "../footer.php"; ?>
