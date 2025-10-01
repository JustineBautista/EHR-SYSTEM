<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$page_title = "Dashboard";
$msg = "";

include "db.php";
include "audit_trail.php";

// Check admin access
if (!isset($_SESSION['admin'])) {
    header("Location: index.php");
    exit();
}

// CSRF token for security
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Enhanced input sanitization
function sanitize_input($conn, $data) {
    return mysqli_real_escape_string($conn, trim(htmlspecialchars($data, ENT_QUOTES, 'UTF-8')));
}

// Add patient with better validation
if (isset($_POST['add_patient'])) {
    // CSRF protection
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $msg = "❌ Security error: Invalid request.";
    } else {
        $name = sanitize_input($conn, $_POST['name'] ?? "");
        $dob = sanitize_input($conn, $_POST['dob'] ?? "");
        $gender = sanitize_input($conn, $_POST['gender'] ?? "");
        $contact = sanitize_input($conn, $_POST['contact'] ?? "");
        $address = sanitize_input($conn, $_POST['address'] ?? "");
        $history = sanitize_input($conn, $_POST['history'] ?? "");

        // Enhanced validation
        if (empty($name)) {
            $msg = "❌ Patient name is required.";
        } elseif (strlen($name) < 2) {
            $msg = "❌ Patient name must be at least 2 characters.";
        } elseif (!empty($dob) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
            $msg = "❌ Invalid date format. Use YYYY-MM-DD.";
        } elseif (!empty($dob) && strtotime($dob) > time()) {
            $msg = "❌ Date of birth cannot be in the future.";
        } else {
            // Check for duplicates
            $check_stmt = $conn->prepare("SELECT id FROM patients WHERE fullname = ? AND dob = ? LIMIT 1");
            $check_stmt->bind_param("ss", $name, $dob);
            $check_stmt->execute();
            
            if ($check_stmt->get_result()->num_rows > 0) {
                $msg = "⚠️ A patient with this name and DOB already exists.";
                $check_stmt->close();
            } else {
                $check_stmt->close();
                
                $stmt = $conn->prepare("INSERT INTO patients (fullname, dob, gender, contact, address, history) VALUES (?,?,?,?,?,?)");
                if ($stmt && $stmt->bind_param("ssssss", $name, $dob, $gender, $contact, $address, $history) && $stmt->execute()) {
                    $patient_id = $conn->insert_id;
                    
                    // Log audit trail
                    $new_values = compact('name', 'dob', 'gender', 'contact', 'address', 'history');
                    log_audit($conn, 'INSERT', 'patients', $patient_id, $patient_id, null, $new_values);
                    
                    $msg = "✅ Patient added successfully.";
                    $stmt->close();
                } else {
                    $msg = "❌ Error adding patient. Please try again.";
                }
            }
        }
        
        // Regenerate CSRF token
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

// Get statistics with better error handling
$stats = [
    'patients' => 0, 'medical_history' => 0, 'medications' => 0, 'vitals' => 0,
    'diagnostics' => 0, 'treatment_plans' => 0, 'lab_results' => 0, 'progress_notes' => 0
];

foreach ($stats as $table => $value) {
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM `$table`");
        if ($stmt && $stmt->execute()) {
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $stats[$table] = intval($row['count']);
            }
            $stmt->close();
        }
    } catch (Exception $e) {
        // Keep default value of 0
    }
}

include "header.php";
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
    
    .stat-card {
      border-left: 4px solid var(--primary-color);
    }
    
    .stat-value {
      font-size: 1.75rem;
      font-weight: 700;
      color: var(--primary-color);
    }
    
    .module-btn {
      height: 100px;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      text-decoration: none;
      color: #334155;
      border-radius: 1rem;
      background: white;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
      transition: all 0.2s ease;
    }
    
    .module-btn:hover {
      background: linear-gradient(135deg, var(--warning-color), #e6a800);
      color: black;
      transform: translateY(-5px) scale(1.02);
      box-shadow: 0 8px 16px rgba(245, 158, 11, 0.3);
      text-decoration: none;
    }

    .module-btn:hover .module-icon {
      color: white;
      transform: scale(1.1);
      transition: transform 0.3s ease;
    }
    
    .module-icon {
      font-size: 2rem;
      margin-bottom: 0.5rem;
      color: var(--primary-color);
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

</style>

<!-- Feedback message -->
<?php if (!empty($msg)): ?>
  <div class="container mt-3">
    <div class="alert <?php echo strpos($msg, '✅') !== false ? 'alert-success' : (strpos($msg, '⚠️') !== false ? 'alert-warning' : 'alert-danger'); ?> alert-dismissible fade show">
      <?php echo htmlspecialchars($msg); ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  </div>
<?php endif; ?>

<div class="container mt-4">
  <!-- Stats Overview -->
  <div class="row mb-4">
    <div class="col-12">
      <div class="card">
        <div class="card-header py-3">
          <h5 class="mb-0"><i class="bi bi-graph-up me-2"></i>EHR System Overview</h5>
        </div>
        <div class="card-body">
          <div class="row g-4">
            <div class="col-md-3 col-sm-6">
              <div class="card stat-card h-100">
                <div class="card-body d-flex align-items-center">
                  <div class="me-3 text-success"><i class="bi bi-people-fill fs-2"></i></div>
                  <div>
                    <div class="stat-value"><?php echo number_format($stats['patients']); ?></div>
                    <div class="stat-label">Patients</div>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-md-3 col-sm-6">
              <div class="card stat-card h-100">
                <div class="card-body d-flex align-items-center">
                  <div class="me-3 text-success"><i class="bi bi-heart-pulse fs-2"></i></div>
                  <div>
                    <div class="stat-value"><?php echo number_format($stats['vitals']); ?></div>
                    <div class="stat-label">Vital Records</div>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-md-3 col-sm-6">
              <div class="card stat-card h-100">
                <div class="card-body d-flex align-items-center">
                  <div class="me-3 text-success"><i class="bi bi-capsule fs-2"></i></div>
                  <div>
                    <div class="stat-value"><?php echo number_format($stats['medications']); ?></div>
                    <div class="stat-label">Medications</div>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-md-3 col-sm-6">
              <div class="card stat-card h-100">
                <div class="card-body d-flex align-items-center">
                  <div class="me-3 text-success"><i class="bi bi-clipboard-data fs-2"></i></div>
                  <div>
                    <div class="stat-value"><?php echo number_format($stats['lab_results']); ?></div>
                    <div class="stat-label">Lab Results</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-4">
    <!-- Quick Actions -->
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-header py-3">
          <h5 class="mb-0"><i class="bi bi-lightning-charge me-2"></i>Quick Actions</h5>
        </div>
        <div class="card-body">
          <div class="d-grid gap-3">
            <button class="btn btn-success w-100" data-bs-toggle="modal" data-bs-target="#addPatientModal">
              <i class="bi bi-person-plus me-2"></i>Add New Patient
            </button>
            <a class="btn btn-outline-success w-100" href="patients.php">
              <i class="bi bi-people me-2"></i>Manage Patients
            </a>
            <a class="btn btn-outline-success w-100" href="vitals.php">
              <i class="bi bi-heart-pulse me-2"></i>Record Vital Signs
            </a>
            <a class="btn btn-outline-success w-100" href="lab_results.php">
              <i class="bi bi-clipboard-data me-2"></i>Enter Lab Results
            </a>
            <a class="btn btn-outline-success w-100" href="medications.php">
              <i class="bi bi-capsule me-2"></i>Manage Current Medications
            </a>
          </div>
        </div>
      </div>
    </div>

    <!-- Modules -->
    <div class="col-md-8">
      <div class="card h-100">
        <div class="card-header py-3">
          <h5 class="mb-0"><i class="bi bi-grid-3x3-gap me-2"></i>EHR Modules</h5>
        </div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-4 col-sm-6">
              <a class="module-btn p-3 h-100 w-100 d-block" href="diagnostics.php">
                <i class="bi bi-clipboard-check-fill module-icon"></i>
                <span class="fw-semibold">Diagnostics</span>
              </a>
            </div>
            <div class="col-md-4 col-sm-6">
              <a class="module-btn p-3 h-100 w-100 d-block" href="treatment_plans.php">
                <i class="bi bi-journal-text module-icon"></i>
                <span class="fw-semibold">Treatment Plans</span>
              </a>
            </div>
            <div class="col-md-4 col-sm-6">
              <a class="module-btn p-3 h-100 w-100 d-block" href="lab_diagnostic_results.php">
                <i class="bi bi-bar-chart-line-fill module-icon"></i>
                <span class="fw-semibold">Lab & Diagnostic</span>
              </a>
            </div>
            <div class="col-md-4 col-sm-6">
              <a class="module-btn p-3 h-100 w-100 d-block" href="progress_notes.php">
                <i class="bi bi-pencil-square module-icon"></i>
                <span class="fw-semibold">Progress Notes</span>
              </a>
            </div>
            <div class="col-md-4 col-sm-6">
              <a class="module-btn p-3 h-100 w-100 d-block" href="medical_history.php">
                <i class="bi bi-journal-medical module-icon"></i>
                <span class="fw-semibold">Medical History</span>
              </a>
            </div>
            <div class="col-md-4 col-sm-6">
              <a class="module-btn p-3 h-100 w-100 d-block" href="lab_results.php">
                <i class="bi bi-clipboard-data module-icon"></i>
                <span class="fw-semibold">Lab Results</span>
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Medical Summary Charts - Centered Bottom -->
  <div class="row mt-4">
    <div class="col-12">
      <div class="card text-center">
        <div class="card-header py-3">
          <h5 class="mb-0"><i class="bi bi-clipboard-data me-2"></i>Medical Summary</h5>
        </div>
        <div class="card-body">
          <div class="row">
            <div class="col-md-8 mx-auto">
              <canvas id="medicalBarChart" height="300"></canvas>
            </div>
            <div class="col-md-4 mx-auto">
              <canvas id="medicalDonutChart" height="300"></canvas>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Add Patient Modal -->
<div class="modal fade" id="addPatientModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content rounded-4">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Add New Patient</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="post" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        <div class="modal-body p-4">
          <div class="row g-3">
            <div class="col-md-12">
              <label for="name" class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-person"></i></span>
                <input type="text" class="form-control" id="name" name="name" required maxlength="100" placeholder="Enter patient's full name">
              </div>
            </div>
            <div class="col-md-6">
              <label for="dob" class="form-label fw-semibold">Date of Birth</label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-calendar"></i></span>
                <input type="date" class="form-control" id="dob" name="dob" max="<?php echo date('Y-m-d'); ?>">
              </div>
            </div>
            <div class="col-md-6">
              <label for="gender" class="form-label fw-semibold">Gender</label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-gender-ambiguous"></i></span>
                <select class="form-select" id="gender" name="gender">
                  <option value="">Select Gender</option>
                  <option value="Male">Male</option>
                  <option value="Female">Female</option>
                  <option value="Other">Other</option>
                </select>
              </div>
            </div>
            <div class="col-md-12">
              <label for="contact" class="form-label fw-semibold">Contact</label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                <input type="tel" class="form-control" id="contact" name="contact" maxlength="20" placeholder="Phone number">
              </div>
            </div>
            <div class="col-md-12">
              <label for="address" class="form-label fw-semibold">Address</label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-geo-alt"></i></span>
                <textarea class="form-control" id="address" name="address" rows="2" maxlength="500" placeholder="Patient's address"></textarea>
              </div>
            </div>
            <div class="col-md-12">
              <label for="history" class="form-label fw-semibold">Medical History</label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-journal-medical"></i></span>
                <textarea class="form-control" id="history" name="history" rows="3" maxlength="1000" placeholder="Brief medical history (optional)"></textarea>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="add_patient" class="btn btn-success">
            <i class="bi bi-person-plus me-2"></i>Add Patient
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Auto-dismiss alerts and basic form validation
document.addEventListener('DOMContentLoaded', function() {
    // Auto-dismiss alerts after 5 seconds
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            const bsAlert = new bootstrap.Alert(alert);
            if (bsAlert) bsAlert.close();
        });
    }, 5000);

    // Basic form validation
    const form = document.querySelector('#addPatientModal form');
    if (form) {
        form.addEventListener('submit', function(e) {
            const name = document.getElementById('name').value.trim();
            const dob = document.getElementById('dob').value;
            
            if (name.length < 2) {
                e.preventDefault();
                alert('Patient name must be at least 2 characters long.');
                return;
            }
            
            if (dob && new Date(dob) > new Date()) {
                e.preventDefault();
                alert('Date of birth cannot be in the future.');
                return;
            }
        });
    }

    // Medical Summary Charts
    const stats = <?php echo json_encode($stats); ?>;
    const labels = [
        'Patients',
        'Medical Histories',
        'Medications',
        'Vital Signs',
        'Diagnostics',
        'Treatment Plans',
        'Lab Results',
        'Progress Notes'
    ];
    const data = [
        stats.patients,
        stats.medical_history,
        stats.medications,
        stats.vitals,
        stats.diagnostics,
        stats.treatment_plans,
        stats.lab_results,
        stats.progress_notes
    ];
    const totalRecords = data.reduce((a, b) => a + b, 0);

    // Bar Chart with Line Overlay (Statistical Line)
    const barCtx = document.getElementById('medicalBarChart').getContext('2d');
    new Chart(barCtx, {
        data: {
            labels: labels,
            datasets: [{
                type: 'bar',
                label: 'Record Counts',
                data: data,
                backgroundColor: [
                    'rgba(16, 185, 129, 0.8)',
                    'rgba(5, 150, 105, 0.8)',
                    'rgba(4, 120, 87, 0.8)',
                    'rgba(6, 95, 70, 0.8)',
                    'rgba(245, 158, 11, 0.8)',
                    'rgba(217, 119, 6, 0.8)',
                    'rgba(180, 83, 9, 0.8)',
                    'rgba(146, 64, 14, 0.8)'
                ],
                borderColor: '#ffffff',
                borderWidth: 1
            }, {
                type: 'line',
                label: 'Trend Line',
                data: data.map((d, i) => data.slice(0, i+1).reduce((a, b) => a + b, 0)), // Cumulative for statistical trend
                borderColor: '#ef4444',
                backgroundColor: 'rgba(239, 68, 68, 0.1)',
                borderWidth: 2,
                fill: false,
                tension: 0.4,
                yAxisID: 'y'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });

    // Donut Chart for Distribution
    const donutCtx = document.getElementById('medicalDonutChart').getContext('2d');
    const donutData = data.map(d => (d / totalRecords * 100).toFixed(1));
    new Chart(donutCtx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: donutData,
                backgroundColor: [
                    '#10b981',
                    '#059669',
                    '#047857',
                    '#065f46',
                    '#f59e0b',
                    '#d97706',
                    '#b45309',
                    '#92400e'
                ],
                borderWidth: 2,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right'
                }
            }
        }
    });
});
</script>

<?php include "footer.php"; ?>
