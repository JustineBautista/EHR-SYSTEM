<?php
$page_title = "Lab & Diagnostic Results";
// Include header (this already has session + db connection)
include "header.php";

// Get patient ID
$patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;

if ($patient_id > 0) {
    // Fetch patient details
    $patient_stmt = $conn->prepare("SELECT fullname, dob, gender, contact, address FROM patients WHERE id=?");
    $patient_stmt->bind_param("i", $patient_id);
    $patient_stmt->execute();
    $patient_result = $patient_stmt->get_result();
    $patient = $patient_result->fetch_assoc();
    $patient_stmt->close();

    if (!$patient) {
        echo "<div class='alert alert-danger mt-3'>Patient not found.</div>";
        exit;
    }

    // Fetch lab results
    $lab_sql = "SELECT test_name, test_result, date_taken FROM lab_results WHERE patient_id=? ORDER BY date_taken DESC";
    $lab_stmt = $conn->prepare($lab_sql);
    $lab_stmt->bind_param("i", $patient_id);
    $lab_stmt->execute();
    $lab_results = $lab_stmt->get_result();
    $lab_stmt->close();

    // Fetch diagnostics
    $diag_sql = "SELECT problem, diagnosis, date_diagnosed FROM diagnostics WHERE patient_id=? ORDER BY date_diagnosed DESC";
    $diag_stmt = $conn->prepare($diag_sql);
    $diag_stmt->bind_param("i", $patient_id);
    $diag_stmt->execute();
    $diag_results = $diag_stmt->get_result();
    $diag_stmt->close();
}
?>

<style>
    :root {
      --primary-color: #10b981;
      --success-color: #10b981;
      --warning-color: #f59e0b;
      --danger-color: #ef4444;
    }

    body {
      background-color: #ffffff;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      padding-top: 5rem;
    }
    
    .card {
      border-radius: 1rem;
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
      border: none;
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    
    .card:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 12px -1px rgba(0, 0, 0, 0.15);
    }

    .alert {
      border-radius: 0.75rem;
      border: none;
    }

    .form-control:focus, .form-select:focus {
      box-shadow: 0 0 0 0.2rem rgba(16, 185, 129, 0.25);
      border-color: var(--primary-color);
    }
    
    .btn {
      border-radius: 0.5rem;
    }

    h4, h6 {
      font-weight: 700;
      color: #343a40;
    }

    .btn-secondary {
      border-radius: 8px;
      font-weight: 600;
      padding: 10px 15px;
    }
    
    .btn-primary {
      background-color: var(--primary-color);
      border-color: var(--primary-color);
      border-radius: 8px;
      font-weight: 600;
      padding: 10px 15px;
    }
    
    .btn-primary:hover {
      background-color: var(--warning-color);
      border-color: var(--warning-color);
    }

</style>
<div class="container mt-4">
    <h4 class="text-success fw-bold text-center mb-4">
        <i class="bi bi-clipboard2-pulse me-2"></i> Laboratory & Diagnostic Results
    </h4>

    <div class="card p-3 mb-3">
        <form method="get" class="row g-2">
            <div class="col-md-6">
                <label for="patient_id" class="form-label fw-bold">Select Patient</label>
                <select id="patient_id" name="patient_id" class="form-select" onchange="this.form.submit()">
                    <option value="">Select patient</option>
                    <?php
                    $patients = $conn->query("SELECT id, fullname FROM patients ORDER BY fullname");
                    while ($p = $patients->fetch_assoc()) {
                        $selected = ($patient_id == $p['id']) ? 'selected' : '';
                        echo "<option value='{$p['id']}' {$selected}>{$p['fullname']}</option>";
                    }
                    ?>
                </select>
            </div>
        </form>
    </div>

    <?php if ($patient_id == 0): ?>
        <div class="alert alert-info">Please select a patient to view their results.</div>
    <?php else: ?>
        <!-- Patient Info -->
        <div class="card p-3 mb-3">
            <h6 class="fw-bold text-success mb-3">Patient Details</h6>
            <div class="row">
                <div class="col-md-6"><strong>Name:</strong> <?= htmlspecialchars($patient['fullname']); ?></div>
                <div class="col-md-6"><strong>Gender:</strong> <?= htmlspecialchars($patient['gender'] ?? ''); ?></div>
            </div>
            <div class="row mt-2">
                <div class="col-md-6"><strong>Date of Birth:</strong> <?= htmlspecialchars($patient['dob']); ?></div>
                <div class="col-md-6"><strong>Contact:</strong> <?= htmlspecialchars($patient['contact'] ?? ''); ?></div>
            </div>
            <div class="row mt-2">
                <div class="col-12"><strong>Address:</strong> <?= htmlspecialchars($patient['address']); ?></div>
            </div>
        </div>

        <div class="row">
            <!-- Lab Results -->
            <div class="col-md-6">
                <div class="card p-3">
                    <h6 class="fw-bold text-success mb-3">
                        <i class="bi bi-flask me-2"></i> Lab Results
                    </h6>
                    <?php if ($lab_results->num_rows > 0): ?>
                        <table class="table table-bordered table-sm">
                            <thead>
                                <tr>
                                    <th>Test Name</th>
                                    <th>Result</th>
                                    <th>Date Taken</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $lab_results->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['test_name']); ?></td>
                                    <td><?= htmlspecialchars($row['test_result']); ?></td>
                                    <td><?= htmlspecialchars($row['date_taken']); ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="text-muted">No lab results found.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Diagnostic Results -->
            <div class="col-md-6">
                <div class="card p-3">
                    <h6 class="fw-bold text-success mb-3">
                        <i class="bi bi-activity me-2"></i> Diagnostics
                    </h6>
                    <?php if ($diag_results->num_rows > 0): ?>
                        <?php while ($diag = $diag_results->fetch_assoc()): ?>
                            <div class="border-bottom pb-2 mb-2">
                                <div class="fw-bold text-success"><?= htmlspecialchars($diag['date_diagnosed']); ?></div>
                                <div><strong>Problem:</strong> <?= htmlspecialchars($diag['problem']); ?></div>
                                <div><strong>Diagnosis:</strong> <?= nl2br(htmlspecialchars($diag['diagnosis'])); ?></div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p class="text-muted">No diagnostic results found.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include "footer.php"; ?>
