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

$page_title = "Physical Assessments";

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

// Physical Assessments processing
$msg = "";
$error = "";

if (isset($_POST['add_physical_assessment'])) {
    $assessed_by = sanitize_input($conn, $_POST['assessed_by'] ?? "");
    $head_and_neck = sanitize_input($conn, $_POST['head_and_neck'] ?? "");
    $cardiovascular = sanitize_input($conn, $_POST['cardiovascular'] ?? "");
    $respiratory = sanitize_input($conn, $_POST['respiratory'] ?? "");
    $abdominal = sanitize_input($conn, $_POST['abdominal'] ?? "");
    $neurological = sanitize_input($conn, $_POST['neurological'] ?? "");
    $musculoskeletal = sanitize_input($conn, $_POST['musculoskeletal'] ?? "");
    $skin = sanitize_input($conn, $_POST['skin'] ?? "");
    $psychiatric = sanitize_input($conn, $_POST['psychiatric'] ?? "");
    $date = $_POST['date'] ?: date("Y-m-d");

    // Validate date format if provided
    if (!empty($_POST['date']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $error = "Date must be in format YYYY-MM-DD.";
    } else {
        $stmt = $conn->prepare("INSERT INTO physical_assessments (patient_id, assessed_by, head_and_neck, cardiovascular, respiratory, abdominal, neurological, musculoskeletal, skin, psychiatric, date_assessed) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param("issssssssss", $patient_id, $assessed_by, $head_and_neck, $cardiovascular, $respiratory, $abdominal, $neurological, $musculoskeletal, $skin, $psychiatric, $date);
        if ($stmt->execute()) {
            $msg = "Physical assessment added.";
        } else {
            $error = "Error: " . $stmt->error;
        }
    }
}

if (isset($_GET['delete_physical_assessment'])) {
    $id = intval($_GET['delete_physical_assessment']);
    $stmt = $conn->prepare("DELETE FROM physical_assessments WHERE id=? AND patient_id=?");
    $stmt->bind_param("ii", $id, $patient_id);
    if ($stmt->execute()) {
        header("Location: physical_assessments.php?patient_id=$patient_id");
        exit();
    }
    $stmt->close();
}

// Handle update physical_assessments
if (isset($_POST['update_physical_assessment'])) {
    $aid = intval($_POST['assessment_id']);
    $assessed_by = sanitize_input($conn, $_POST['assessed_by'] ?? "");
    $head_and_neck = sanitize_input($conn, $_POST['head_and_neck'] ?? "");
    $cardiovascular = sanitize_input($conn, $_POST['cardiovascular'] ?? "");
    $respiratory = sanitize_input($conn, $_POST['respiratory'] ?? "");
    $abdominal = sanitize_input($conn, $_POST['abdominal'] ?? "");
    $neurological = sanitize_input($conn, $_POST['neurological'] ?? "");
    $musculoskeletal = sanitize_input($conn, $_POST['musculoskeletal'] ?? "");
    $skin = sanitize_input($conn, $_POST['skin'] ?? "");
    $psychiatric = sanitize_input($conn, $_POST['psychiatric'] ?? "");
    $date = $_POST['date'] ?: date("Y-m-d");

    // Validate date format if provided
    if (!empty($_POST['date']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $error = "Date must be in format YYYY-MM-DD.";
    } else {
        $stmt = $conn->prepare("UPDATE physical_assessments SET assessed_by=?, head_and_neck=?, cardiovascular=?, respiratory=?, abdominal=?, neurological=?, musculoskeletal=?, skin=?, psychiatric=?, date_assessed=? WHERE id=? AND patient_id=?");
        $stmt->bind_param("sssssssssii", $assessed_by, $head_and_neck, $cardiovascular, $respiratory, $abdominal, $neurological, $musculoskeletal, $skin, $psychiatric, $date, $aid, $patient_id);
        if ($stmt->execute()) {
            $msg = "Physical assessment updated.";
        } else {
            $error = "Error updating physical assessment: " . $stmt->error;
        }
        $stmt->close();
    }
}

if (isset($_GET['get_physical_assessment'])) {
    $id = intval($_GET['get_physical_assessment']);
    $stmt = $conn->prepare("SELECT * FROM physical_assessments WHERE id=? AND patient_id=?");
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
                    <h5 class="mb-0"><i class="bi bi-clipboard-check me-2"></i>Physical Assessments - <?php echo htmlspecialchars($patient['fullname']); ?></h5>
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

                    <!-- Physical Assessments Form -->
                    <div class="card p-3 mb-3">
                        <form method="post">
                            <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
                            <div class="row g-2 mb-2">
                                <div class="col-md-6">
                                    <input class="form-control" name="assessed_by" placeholder="Assessed By" value="<?php echo htmlspecialchars($_POST['assessed_by'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <input class="form-control" name="date" type="date" value="<?php echo htmlspecialchars($_POST['date'] ?? date('Y-m-d')); ?>" required>
                                </div>
                            </div>
                            <div class="row g-2 mb-2">
                                <div class="col-md-6">
                                    <textarea class="form-control" name="head_and_neck" placeholder="Head and Neck" rows="3" required><?php echo htmlspecialchars($_POST['head_and_neck'] ?? ''); ?></textarea>
                                </div>
                                <div class="col-md-6">
                                    <textarea class="form-control" name="cardiovascular" placeholder="Cardiovascular" rows="3" required><?php echo htmlspecialchars($_POST['cardiovascular'] ?? ''); ?></textarea>
                                </div>
                            </div>
                            <div class="row g-2 mb-2">
                                <div class="col-md-6">
                                    <textarea class="form-control" name="respiratory" placeholder="Respiratory" rows="3" required><?php echo htmlspecialchars($_POST['respiratory'] ?? ''); ?></textarea>
                                </div>
                                <div class="col-md-6">
                                    <textarea class="form-control" name="abdominal" placeholder="Abdominal" rows="3" required><?php echo htmlspecialchars($_POST['abdominal'] ?? ''); ?></textarea>
                                </div>
                            </div>
                            <div class="row g-2 mb-2">
                                <div class="col-md-6">
                                    <textarea class="form-control" name="neurological" placeholder="Neurological" rows="3" required><?php echo htmlspecialchars($_POST['neurological'] ?? ''); ?></textarea>
                                </div>
                                <div class="col-md-6">
                                    <textarea class="form-control" name="musculoskeletal" placeholder="Musculoskeletal" rows="3" required><?php echo htmlspecialchars($_POST['musculoskeletal'] ?? ''); ?></textarea>
                                </div>
                            </div>
                            <div class="row g-2 mb-2">
                                <div class="col-md-6">
                                    <textarea class="form-control" name="skin" placeholder="Skin" rows="3" required><?php echo htmlspecialchars($_POST['skin'] ?? ''); ?></textarea>
                                </div>
                                <div class="col-md-6">
                                    <textarea class="form-control" name="psychiatric" placeholder="Psychiatric" rows="3" required><?php echo htmlspecialchars($_POST['psychiatric'] ?? ''); ?></textarea>
                                </div>
                            </div>
                            <div class="row g-2">
                                <div class="col-12">
                                    <button name="add_physical_assessment" class="btn btn-primary">Add Physical Assessment</button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Physical Assessments Table -->
                    <div class="card p-3">
                        <table class="table table-sm table-bordered">
                            <thead>
                                <tr>
                                    <th>Assessed By</th>
                                    <th>Head and Neck</th>
                                    <th>Cardiovascular</th>
                                    <th>Respiratory</th>
                                    <th>Abdominal</th>
                                    <th>Neurological</th>
                                    <th>Musculoskeletal</th>
                                    <th>Skin</th>
                                    <th>Psychiatric</th>
                                    <th>Date Assessed</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $stmt = $conn->prepare("SELECT * FROM physical_assessments WHERE patient_id = ? ORDER BY id DESC");
                                $stmt->bind_param("i", $patient_id);
                                $stmt->execute();
                                $result = $stmt->get_result();
                                while ($r = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($r['assessed_by']); ?></td>
                                        <td><?php echo htmlspecialchars($r['head_and_neck']); ?></td>
                                        <td><?php echo htmlspecialchars($r['cardiovascular']); ?></td>
                                        <td><?php echo htmlspecialchars($r['respiratory']); ?></td>
                                        <td><?php echo htmlspecialchars($r['abdominal']); ?></td>
                                        <td><?php echo htmlspecialchars($r['neurological']); ?></td>
                                        <td><?php echo htmlspecialchars($r['musculoskeletal']); ?></td>
                                        <td><?php echo htmlspecialchars($r['skin']); ?></td>
                                        <td><?php echo htmlspecialchars($r['psychiatric']); ?></td>
                                        <td><?php echo htmlspecialchars($r['date_assessed']); ?></td>
                                        <td>
                                            <a class="btn btn-sm btn-danger" href="?delete_physical_assessment=<?php echo $r['id']; ?>&patient_id=<?php echo $patient_id; ?>" onclick="return confirm('Delete?')">
                                                <i class="bi bi-trash"></i> Delete
                                            </a>
                                            <a class="btn btn-sm btn-warning" href="javascript:void(0)" onclick="editPhysicalAssessment(<?php echo $r['id']; ?>)">
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

<!-- Edit Modal for Physical Assessment -->
<div class="modal fade" id="editPhysicalAssessmentModal" tabindex="-1" aria-labelledby="editPhysicalAssessmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form method="post" id="editPhysicalAssessmentForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="editPhysicalAssessmentModalLabel">Edit Physical Assessment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="assessment_id" id="assessment_id">
                    <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label for="assessed_by_edit" class="form-label">Assessed By</label>
                            <input type="text" class="form-control" name="assessed_by" id="assessed_by_edit" placeholder="Assessed By" required>
                        </div>
                        <div class="col-md-6">
                            <label for="date_assessment_edit" class="form-label">Date Assessed</label>
                            <input type="date" class="form-control" name="date" id="date_assessment_edit" required>
                        </div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label for="head_and_neck_edit" class="form-label">Head and Neck</label>
                            <textarea class="form-control" name="head_and_neck" id="head_and_neck_edit" rows="3" placeholder="Head and Neck" required></textarea>
                        </div>
                        <div class="col-md-6">
                            <label for="cardiovascular_edit" class="form-label">Cardiovascular</label>
                            <textarea class="form-control" name="cardiovascular" id="cardiovascular_edit" rows="3" placeholder="Cardiovascular" required></textarea>
                        </div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label for="respiratory_edit" class="form-label">Respiratory</label>
                            <textarea class="form-control" name="respiratory" id="respiratory_edit" rows="3" placeholder="Respiratory" required></textarea>
                        </div>
                        <div class="col-md-6">
                            <label for="abdominal_edit" class="form-label">Abdominal</label>
                            <textarea class="form-control" name="abdominal" id="abdominal_edit" rows="3" placeholder="Abdominal" required></textarea>
                        </div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label for="neurological_edit" class="form-label">Neurological</label>
                            <textarea class="form-control" name="neurological" id="neurological_edit" rows="3" placeholder="Neurological" required></textarea>
                        </div>
                        <div class="col-md-6">
                            <label for="musculoskeletal_edit" class="form-label">Musculoskeletal</label>
                            <textarea class="form-control" name="musculoskeletal" id="musculoskeletal_edit" rows="3" placeholder="Musculoskeletal" required></textarea>
                        </div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label for="skin_edit" class="form-label">Skin</label>
                            <textarea class="form-control" name="skin" id="skin_edit" rows="3" placeholder="Skin" required></textarea>
                        </div>
                        <div class="col-md-6">
                            <label for="psychiatric_edit" class="form-label">Psychiatric</label>
                            <textarea class="form-control" name="psychiatric" id="psychiatric_edit" rows="3" placeholder="Psychiatric" required></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="update_physical_assessment" class="btn btn-primary">Save changes</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editPhysicalAssessment(id) {
    fetch('?get_physical_assessment=' + id)
        .then(response => response.json())
        .then(data => {
            document.getElementById('assessment_id').value = data.id;
            document.getElementById('assessed_by_edit').value = data.assessed_by;
            document.getElementById('head_and_neck_edit').value = data.head_and_neck;
            document.getElementById('cardiovascular_edit').value = data.cardiovascular;
            document.getElementById('respiratory_edit').value = data.respiratory;
            document.getElementById('abdominal_edit').value = data.abdominal;
            document.getElementById('neurological_edit').value = data.neurological;
            document.getElementById('musculoskeletal_edit').value = data.musculoskeletal;
            document.getElementById('skin_edit').value = data.skin;
            document.getElementById('psychiatric_edit').value = data.psychiatric;
            document.getElementById('date_assessment_edit').value = data.date_assessed;
            new bootstrap.Modal(document.getElementById('editPhysicalAssessmentModal')).show();
        })
        .catch(error => console.error('Error:', error));
}
</script>

<?php include "../footer.php"; ?>
