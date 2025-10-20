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

$page_title = "Medications";

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

// Medications processing
$msg = "";
$error = "";

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
        $error = "Patient instructions is required.";
    }
    elseif (empty($pharmacy_instructions)) {
        $error = "Pharmacy instructions is required.";
    }
    else {
        $stmt = $conn->prepare("INSERT INTO medications (patient_id, medication, indication, prescriber, dose, route, start_date, notes, status, patient_instructions, pharmacy_instructions) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param("issssssssss", $patient_id, $med, $indication, $prescriber, $dose, $route, $start, $notes, $status, $patient_instructions, $pharmacy_instructions);
        if ($stmt->execute()) {
            $msg = "Medication added.";
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
        header("Location: medications.php?patient_id=$patient_id");
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
        $error = "Patient instructions is required.";
    }
    elseif (empty($pharmacy_instructions)) {
        $error = "Pharmacy instructions is required.";
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
                    <h5 class="mb-0"><i class="bi bi-capsule me-2"></i>Medications - <?php echo htmlspecialchars($patient['fullname']); ?></h5>
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
                                    <input class="form-control" name="prescriber" placeholder="Prescriber (e.g. Dr.Name, MD)" value="<?php echo htmlspecialchars($_POST['prescriber'] ?? ''); ?>" required>
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
                                    <th>Medication</th>
                                    <th>Dose</th>
                                    <th>Start Date</th>
                                    <th>Frequency</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $stmt = $conn->prepare("SELECT * FROM medications WHERE patient_id = ? ORDER BY id DESC");
                                $stmt->bind_param("i", $patient_id);
                                $stmt->execute();
                                $result = $stmt->get_result();
                                while ($r = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($r['medication']); ?></td>
                                        <td><?php echo htmlspecialchars($r['dose']); ?></td>
                                        <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($r['start_date']))); ?></td>
                                        <td><?php echo htmlspecialchars($r['notes']); ?></td>
                                        <td>
                                            <a class="btn btn-sm btn-danger" href="?delete_med=<?php echo $r['id']; ?>&patient_id=<?php echo $patient_id; ?>" onclick="return confirm('Delete?')">
                                                <i class="bi bi-trash"></i> Delete
                                            </a>
                                            <a class="btn btn-sm btn-warning" href="javascript:void(0)" onclick="editMed(<?php echo $r['id']; ?>)">
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

<script>
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
</script>

<?php include "../footer.php"; ?>
