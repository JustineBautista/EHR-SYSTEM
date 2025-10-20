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

$page_title = "Treatment Plans";

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

// Treatment Plans processing
$msg = "";
$error = "";

if (isset($_POST['add_plan'])) {
    $treatment = sanitize_input($conn, $_POST['treatment'] ?? "");
    $goal = sanitize_input($conn, $_POST['goal'] ?? "");
    $start_date = $_POST['start_date'] ?: date("Y-m-d");
    $end_date = $_POST['end_date'] ?: null;
    $status = sanitize_input($conn, $_POST['status'] ?? "");
    $notes = sanitize_input($conn, $_POST['notes'] ?? "");

    // Validate date format if provided
    if (!empty($_POST['start_date']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date)) {
        $error = "Start date must be in format YYYY-MM-DD.";
    }
    elseif (!empty($_POST['end_date']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
        $error = "End date must be in format YYYY-MM-DD.";
    }
    elseif (!empty($start_date) && !empty($end_date) && strtotime($start_date) > strtotime($end_date)) {
        $error = "End date must be after start date.";
    }
    else {
        $stmt = $conn->prepare("INSERT INTO treatment_plans (patient_id, treatment, goal, start_date, end_date, status, notes) VALUES (?,?,?,?,?,?,?)");
        $stmt->bind_param("issssss", $patient_id, $treatment, $goal, $start_date, $end_date, $status, $notes);
        if ($stmt->execute()) {
            $msg = "Treatment plan added.";
        } else {
            $error = "Error: " . $stmt->error;
        }
    }
}

if (isset($_GET['delete_plan'])) {
    $id = intval($_GET['delete_plan']);
    $stmt = $conn->prepare("DELETE FROM treatment_plans WHERE id=? AND patient_id=?");
    $stmt->bind_param("ii", $id, $patient_id);
    if ($stmt->execute()) {
        header("Location: treatment_plans.php?patient_id=$patient_id");
        exit();
    }
    $stmt->close();
}

// Handle update treatment plans
if (isset($_POST['update_plan'])) {
    $pid = intval($_POST['plan_id']);
    $treatment = sanitize_input($conn, $_POST['treatment'] ?? "");
    $goal = sanitize_input($conn, $_POST['goal'] ?? "");
    $start_date = $_POST['start_date'] ?: date("Y-m-d");
    $end_date = $_POST['end_date'] ?: null;
    $status = sanitize_input($conn, $_POST['status'] ?? "");
    $notes = sanitize_input($conn, $_POST['notes'] ?? "");

    // Validate date format if provided
    if (!empty($_POST['start_date']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date)) {
        $error = "Start date must be in format YYYY-MM-DD.";
    }
    elseif (!empty($_POST['end_date']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
        $error = "End date must be in format YYYY-MM-DD.";
    }
    elseif (!empty($start_date) && !empty($end_date) && strtotime($start_date) > strtotime($end_date)) {
        $error = "End date must be after start date.";
    }
    else {
        $stmt = $conn->prepare("UPDATE treatment_plans SET treatment=?, goal=?, start_date=?, end_date=?, status=?, notes=? WHERE id=? AND patient_id=?");
        $stmt->bind_param("ssssssii", $treatment, $goal, $start_date, $end_date, $status, $notes, $pid, $patient_id);
        if ($stmt->execute()) {
            $msg = "Treatment plan updated.";
        } else {
            $error = "Error updating plan: " . $stmt->error;
        }
        $stmt->close();
    }
}

if (isset($_GET['get_plan'])) {
    $id = intval($_GET['get_plan']);
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
                    <h5 class="mb-0"><i class="bi bi-clipboard-check me-2"></i>Treatment Plans - <?php echo htmlspecialchars($patient['fullname']); ?></h5>
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

                    <!-- Treatment Plans Form -->
                    <div class="card p-3 mb-3">
                        <form method="post" class="row g-2">
                            <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
                            <div class="col-md-6"><input class="form-control" name="treatment" placeholder="Treatment" value="<?php echo htmlspecialchars($_POST['treatment'] ?? ''); ?>" required></div>
                            <div class="col-md-6"><input class="form-control" name="goal" placeholder="Goal" value="<?php echo htmlspecialchars($_POST['goal'] ?? ''); ?>" required></div>
                            <div class="col-md-3"><input type="date" class="form-control" name="start_date" value="<?php echo htmlspecialchars($_POST['start_date'] ?? date('Y-m-d')); ?>" required></div>
                            <div class="col-md-3"><input type="date" class="form-control" name="end_date" value="<?php echo htmlspecialchars($_POST['end_date'] ?? ''); ?>"></div>
                            <div class="col-md-3">
                                <select class="form-control" name="status" required>
                                    <option value="">Select Status</option>
                                    <option value="Active" <?php if (($_POST['status'] ?? '') == 'Active') echo 'selected'; ?>>Active</option>
                                    <option value="Completed" <?php if (($_POST['status'] ?? '') == 'Completed') echo 'selected'; ?>>Completed</option>
                                    <option value="On Hold" <?php if (($_POST['status'] ?? '') == 'On Hold') echo 'selected'; ?>>On Hold</option>
                                    <option value="Discontinued" <?php if (($_POST['status'] ?? '') == 'Discontinued') echo 'selected'; ?>>Discontinued</option>
                                </select>
                            </div>
                            <div class="col-md-3"><input class="form-control" name="notes" placeholder="Notes" value="<?php echo htmlspecialchars($_POST['notes'] ?? ''); ?>"></div>
                            <div class="col-12"><button name="add_plan" class="btn btn-primary">Add Treatment Plan</button></div>
                        </form>
                    </div>

                    <!-- Treatment Plans Table -->
                    <div class="card p-3">
                        <table class="table table-sm table-bordered">
                            <thead>
                                <tr>
                                    <th>Treatment</th>
                                    <th>Goal</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Status</th>
                                    <th>Notes</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $stmt = $conn->prepare("SELECT * FROM treatment_plans WHERE patient_id = ? ORDER BY id DESC");
                                $stmt->bind_param("i", $patient_id);
                                $stmt->execute();
                                $result = $stmt->get_result();
                                while ($r = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($r['treatment'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($r['goal'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($r['start_date'] ?? ''))); ?></td>
                                        <td><?php echo htmlspecialchars($r['end_date'] ? date('Y-m-d', strtotime($r['end_date'])) : 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($r['status'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($r['notes'] ?? 'N/A'); ?></td>
                                        <td>
                                            <a class="btn btn-sm btn-danger" href="?delete_plan=<?php echo $r['id']; ?>&patient_id=<?php echo $patient_id; ?>" onclick="return confirm('Delete?')">
                                                <i class="bi bi-trash"></i> Delete
                                            </a>
                                            <a class="btn btn-sm btn-warning" href="javascript:void(0)" onclick="editPlan(<?php echo $r['id']; ?>)">
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

<!-- Edit Modal for Treatment Plans -->
<div class="modal fade" id="editPlanModal" tabindex="-1" aria-labelledby="editPlanModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" id="editPlanForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="editPlanModalLabel">Edit Treatment Plan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="plan_id" id="plan_id">
                    <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
                    <div class="mb-3">
                        <label for="treatment_edit" class="form-label">Treatment</label>
                        <input type="text" class="form-control" name="treatment" id="treatment_edit" placeholder="Treatment" required>
                    </div>
                    <div class="mb-3">
                        <label for="goal_edit" class="form-label">Goal</label>
                        <input type="text" class="form-control" name="goal" id="goal_edit" placeholder="Goal" required>
                    </div>
                    <div class="mb-3">
                        <label for="start_date_edit" class="form-label">Start Date</label>
                        <input type="date" class="form-control" name="start_date" id="start_date_edit" required>
                    </div>
                    <div class="mb-3">
                        <label for="end_date_edit" class="form-label">End Date</label>
                        <input type="date" class="form-control" name="end_date" id="end_date_edit">
                    </div>
                    <div class="mb-3">
                        <label for="status_plan_edit" class="form-label">Status</label>
                        <select class="form-control" name="status" id="status_plan_edit" required>
                            <option value="">Select Status</option>
                            <option value="Active">Active</option>
                            <option value="Completed">Completed</option>
                            <option value="On Hold">On Hold</option>
                            <option value="Discontinued">Discontinued</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="notes_plan_edit" class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" id="notes_plan_edit" placeholder="Notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="update_plan" class="btn btn-primary">Save changes</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editPlan(id) {
    fetch('?get_plan=' + id)
        .then(response => response.json())
        .then(data => {
            document.getElementById('plan_id').value = data.id;
            document.getElementById('treatment_edit').value = data.treatment;
            document.getElementById('goal_edit').value = data.goal;
            document.getElementById('start_date_edit').value = data.start_date;
            document.getElementById('end_date_edit').value = data.end_date;
            document.getElementById('status_plan_edit').value = data.status;
            document.getElementById('notes_plan_edit').value = data.notes;
            new bootstrap.Modal(document.getElementById('editPlanModal')).show();
        })
        .catch(error => console.error('Error:', error));
}
</script>

<?php include "../footer.php"; ?>
