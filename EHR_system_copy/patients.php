<?php
// Start session and check authentication first
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin'])) {
    header("Location: index.php");
    exit();
}

// Include required files AFTER session check
include "db.php";
include "audit_trail.php";

$page_title = "Patients Management";
$msg = "";
$error = "";

// CSRF token for security
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Enhanced input sanitization function
function sanitize_input($conn, $data) {
    return mysqli_real_escape_string($conn, trim(htmlspecialchars($data, ENT_QUOTES, 'UTF-8')));
}

// Validate date format
function validate_date($date) {
    if (empty($date)) return true; // Allow empty dates
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return false;
    if (strtotime($date) > time()) return false; // No future dates
    return true;
}

// Add patient with enhanced validation
if (isset($_POST['add_patient'])) {
    // CSRF protection
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Security error: Invalid request.";
    } else {
        $name = sanitize_input($conn, $_POST['name'] ?? "");
        $dob = sanitize_input($conn, $_POST['dob'] ?? "");
        $gender = sanitize_input($conn, $_POST['gender'] ?? "");
        $contact = sanitize_input($conn, $_POST['contact'] ?? "");
        $address = sanitize_input($conn, $_POST['address'] ?? "");
        $history = sanitize_input($conn, $_POST['history'] ?? "");
        
        // Enhanced validation
        if (empty($name)) {
            $error = "Patient name is required.";
        } elseif (strlen($name) < 2) {
            $error = "Patient name must be at least 2 characters.";
        } elseif (!validate_date($dob)) {
            $error = "Invalid date format for DOB. Use YYYY-MM-DD and no future dates.";
        } else {
            // Check for duplicates
            $check_stmt = $conn->prepare("SELECT id FROM patients WHERE fullname = ? AND dob = ? LIMIT 1");
            $check_stmt->bind_param("ss", $name, $dob);
            $check_stmt->execute();
            
            if ($check_stmt->get_result()->num_rows > 0) {
                $error = "A patient with this name and DOB already exists.";
                $check_stmt->close();
            } else {
                $check_stmt->close();
                
                $stmt = $conn->prepare("INSERT INTO patients (fullname, dob, gender, contact, address, history) VALUES (?,?,?,?,?,?)");
                if ($stmt && $stmt->bind_param("ssssss", $name, $dob, $gender, $contact, $address, $history) && $stmt->execute()) {
                    $patient_id = $conn->insert_id;
                    
                    // Log audit trail
                    $new_values = [
                        'fullname' => $name,
                        'dob' => $dob,
                        'gender' => $gender,
                        'contact' => $contact,
                        'address' => $address,
                        'history' => $history
                    ];
                    log_audit($conn, 'INSERT', 'patients', $patient_id, $patient_id, null, $new_values);
                    $msg = "Patient added successfully.";
                    $stmt->close();
                } else {
                    $error = "Error adding patient. Please try again.";
                }
            }
        }
        
        // Regenerate CSRF token
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

// Update patient with enhanced validation
if (isset($_POST['update_patient'])) {
    // CSRF protection
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Security error: Invalid request.";
    } else {
        $id = intval($_POST['id']);
        $name = sanitize_input($conn, $_POST['name'] ?? "");
        $dob = sanitize_input($conn, $_POST['dob'] ?? "");
        $gender = sanitize_input($conn, $_POST['gender'] ?? "");
        $contact = sanitize_input($conn, $_POST['contact'] ?? "");
        $address = sanitize_input($conn, $_POST['address'] ?? "");
        $history = sanitize_input($conn, $_POST['history'] ?? "");

        // Enhanced validation
        if (empty($name)) {
            $error = "Patient name is required.";
        } elseif (strlen($name) < 2) {
            $error = "Patient name must be at least 2 characters.";
        } elseif (!validate_date($dob)) {
            $error = "Invalid date format for DOB. Use YYYY-MM-DD and no future dates.";
        } else {
            // Get old values for audit trail
            $old_values = get_record_values($conn, 'patients', $id);
            
            $stmt = $conn->prepare("UPDATE patients SET fullname=?, dob=?, gender=?, contact=?, address=?, history=? WHERE id=?");
            if ($stmt && $stmt->bind_param("ssssssi", $name, $dob, $gender, $contact, $address, $history, $id) && $stmt->execute()) {
                // Log audit trail
                $new_values = [
                    'fullname' => $name,
                    'dob' => $dob,
                    'gender' => $gender,
                    'contact' => $contact,
                    'address' => $address,
                    'history' => $history
                ];
                log_audit($conn, 'UPDATE', 'patients', $id, $id, $old_values, $new_values);
                $msg = "Patient updated successfully.";
                $stmt->close();
            } else {
                $error = "Error updating patient. Please try again.";
            }
        }
        
        // Regenerate CSRF token
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

// Delete patient (cascades) with CSRF protection
if (isset($_GET['delete']) && isset($_GET['csrf_token']) && $_GET['csrf_token'] === $_SESSION['csrf_token']) {
    $id = intval($_GET['delete']);
    
    // Get old values for audit trail before deletion
    $old_values = get_record_values($conn, 'patients', $id);
    
    $stmt = $conn->prepare("DELETE FROM patients WHERE id=?");
    if ($stmt && $stmt->bind_param("i", $id) && $stmt->execute()) {
        // Log audit trail
        log_audit($conn, 'DELETE', 'patients', $id, $id, $old_values, null);
        $msg = "Patient deleted (and related records).";
        $stmt->close();
    } else {
        $error = "Error deleting patient.";
    }
    
    // Regenerate CSRF token
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// For edit form
$edit_patient = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT * FROM patients WHERE id=?");
    if ($stmt && $stmt->bind_param("i", $id) && $stmt->execute()) {
        $res = $stmt->get_result();
        $edit_patient = $res->fetch_assoc();
        $stmt->close();
    }
}

include "header.php";
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

<div class="container mt-4">
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-people-fill me-2"></i>Manage Patients</h5>   
        </div>
        <div class="card-body">
            <!-- Success/Error Messages -->
            <?php if ($msg): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i><?php echo htmlspecialchars($msg); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Add/Edit Patient Form -->
            <div class="card mb-3 p-3">
                <h6><?php echo $edit_patient ? "Edit Patient" : "Add Patient"; ?></h6>
                <form method="post" class="row g-3" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="id" value="<?php echo $edit_patient ? $edit_patient['id'] : ''; ?>">
                    
                    <div class="col-md-6">
                        <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input class="form-control" id="name" name="name" placeholder="Full name" required maxlength="100" 
                               value="<?php echo $edit_patient ? htmlspecialchars($edit_patient['fullname']) : ''; ?>">
                    </div>
                    
                    <div class="col-md-3">
                        <label for="dob" class="form-label">Date of Birth</label>
                        <input class="form-control" id="dob" name="dob" type="date" max="<?php echo date('Y-m-d'); ?>"
                               value="<?php echo $edit_patient ? htmlspecialchars($edit_patient['dob']) : ''; ?>">
                    </div>
                    
                    <div class="col-md-3">
                        <label for="gender" class="form-label">Gender</label>
                        <select name="gender" id="gender" class="form-select">
                            <option value="">Select Gender</option>
                            <option <?php echo (!$edit_patient || $edit_patient['gender']=='Male') ? 'selected':''; ?> value="Male">Male</option>
                            <option <?php echo ($edit_patient && $edit_patient['gender']=='Female') ? 'selected':''; ?> value="Female">Female</option>
                            <option <?php echo ($edit_patient && $edit_patient['gender']=='Other') ? 'selected':''; ?> value="Other">Other</option>
                        </select>
                    </div>
                    
                    <div class="col-md-4">
                        <label for="contact" class="form-label">Contact</label>
                        <input class="form-control" id="contact" name="contact" placeholder="Contact" maxlength="20"
                               value="<?php echo $edit_patient ? htmlspecialchars($edit_patient['contact']) : ''; ?>">
                    </div>
                    
                    <div class="col-md-8">
                        <label for="address" class="form-label">Address</label>
                        <input class="form-control" id="address" name="address" placeholder="Address" maxlength="500"
                               value="<?php echo $edit_patient ? htmlspecialchars($edit_patient['address']) : ''; ?>">
                    </div>
                    
                    <div class="col-12">
                        <label for="history" class="form-label">Medical History</label>
                        <textarea class="form-control" id="history" name="history" rows="3" maxlength="1000" 
                                  placeholder="Brief history"><?php echo $edit_patient ? htmlspecialchars($edit_patient['history']) : ''; ?></textarea>
                    </div>
                    
                    <div class="col-12 mt-3">
                        <?php if ($edit_patient): ?>
                            <button name="update_patient" class="btn btn-success">
                                <i class="bi bi-check-lg me-2"></i>Update Patient
                            </button>
                            <a href="patients.php" class="btn btn-outline-secondary">Cancel</a>
                        <?php else: ?>
                            <button name="add_patient" class="btn btn-primary">
                                <i class="bi bi-person-plus me-2"></i>Add Patient
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Patient List -->
            <div class="card p-3">
                <h6>Patient List</h6>
                <!-- Search bar -->
                <div class="mb-3">
                    <div class="input-group rounded" style="max-width: 400px;">
                        <input type="search" id="patientSearch" class="form-control rounded" placeholder="search patient name or patient id" aria-label="Search" aria-describedby="search-addon" />
                        <span class="input-group-text border-0" id="search-addon" style="background: transparent;">
                            <i class="bi bi-search"></i>
                        </span>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="patientsTable">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>DOB</th>
                                <th>Gender</th>
                                <th>Contact</th>    
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        $res = $conn->query("SELECT * FROM patients ORDER BY fullname");
                        if ($res && $res->num_rows > 0):
                            while ($r = $res->fetch_assoc()):
                                // Fetch additional medical data with prepared statements
                                $medical_data = [];
                                $tables = ['medical_history', 'medications', 'vitals', 'diagnostics', 'treatment_plans', 'lab_results', 'progress_notes'];
                                
                                foreach ($tables as $table) {
                                    $stmt = $conn->prepare("SELECT * FROM `$table` WHERE patient_id = ?");
                                    if ($stmt && $stmt->bind_param("i", $r['id']) && $stmt->execute()) {
                                        $result = $stmt->get_result();
                                        $medical_data[$table] = [];
                                        while ($row = $result->fetch_assoc()) {
                                            $medical_data[$table][] = $row;
                                        }
                                        $stmt->close();
                                    }
                                }
                        ?>
                            <tr>
                                <td class="patient-id"><?php echo htmlspecialchars($r['id']); ?></td>
                                <td class="patient-name"><?php echo htmlspecialchars($r['fullname']); ?></td>
                                <td><?php echo htmlspecialchars($r['dob'] ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($r['gender'] ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($r['contact'] ?: 'N/A'); ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a class="btn btn-outline-primary" href="patients.php?edit=<?php echo $r['id']; ?>" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <button class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#summaryModal" 
                                                data-patient='<?php echo htmlspecialchars(json_encode(array_merge($r, $medical_data)), ENT_QUOTES); ?>' title="Summary">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <a class="btn btn-outline-danger" 
                                           href="patients.php?delete=<?php echo $r['id']; ?>&csrf_token=<?php echo $_SESSION['csrf_token']; ?>" 
                                           onclick="return confirm('Delete patient and all related records?')" title="Delete">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php 
                            endwhile; 
                        else:
                        ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted">No patients found.</td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<!-- Summary Modal -->
<div class="modal fade" id="summaryModal" tabindex="-1" aria-labelledby="summaryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="summaryModalLabel">
                    <i class="bi bi-person-lines-fill me-2"></i>Patient Medical Summary
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header">
                                <h6 class="mb-0">Personal Information</h6>
                            </div>
                            <div class="card-body">
                                <div id="personalInfo"></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header">
                                <h6 class="mb-0">Medical Records</h6>
                            </div>
                            <div class="card-body">
                                <div id="medicalRecords"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-dismiss alerts
document.addEventListener('DOMContentLoaded', function() {
    // Auto-dismiss alerts after 5 seconds
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            const bsAlert = new bootstrap.Alert(alert);
            if (bsAlert) bsAlert.close();
        });
    }, 5000);

    // Form validation
    const form = document.querySelector('form');
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
});

// Enhanced summary modal
var summaryModal = document.getElementById('summaryModal');
summaryModal.addEventListener('show.bs.modal', function (event) {
    var button = event.relatedTarget;
    var patientData = JSON.parse(button.getAttribute('data-patient'));
    
    // Personal Information
    var personalInfo = `
        <p><strong>ID:</strong> ${patientData.id}</p>
        <p><strong>Name:</strong> ${patientData.fullname}</p>
        <p><strong>Date Of Birth:</strong> ${patientData.dob || 'N/A'}</p>
        <p><strong>Gender:</strong> ${patientData.gender || 'N/A'}</p>
        <p><strong>Contact:</strong> ${patientData.contact || 'N/A'}</p>
        <p><strong>Address:</strong> ${patientData.address || 'N/A'}</p>
        <p><strong>History:</strong> ${patientData.history || 'No history recorded'}</p>
    `;
    document.getElementById('personalInfo').innerHTML = personalInfo;
    
    // Medical Records
    var medicalRecords = '';
    var recordTypes = [
        {key: 'medical_history', title: 'Medical History', fields: ['condition_name', 'notes', 'date_recorded']},
        {key: 'medications', title: 'Medications', fields: ['medication', 'dose', 'start_date', 'notes']},
        {key: 'vitals', title: 'Vital Signs', fields: ['bp', 'hr', 'temp', 'height', 'weight', 'date_taken']},
        {key: 'diagnostics', title: 'Diagnostics', fields: ['problem', 'diagnosis', 'date_diagnosed']},
        {key: 'treatment_plans', title: 'Treatment Plans', fields: ['plan', 'notes', 'date_planned']},
        {key: 'lab_results', title: 'Lab Results', fields: ['test_name', 'test_result', 'date_taken']},
        {key: 'progress_notes', title: 'Progress Notes', fields: ['note', 'author', 'date_written']}
    ];
    
    recordTypes.forEach(function(recordType) {
        var records = patientData[recordType.key] || [];
        medicalRecords += `<h6 class="text-primary">${recordType.title} (${records.length})</h6>`;
        
        if (records.length > 0) {
            records.slice(0, 3).forEach(function(record) { // Show only first 3 records
                medicalRecords += '<div class="border-start border-3 border-primary ps-3 mb-2">';
                recordType.fields.forEach(function(field) {
                    if (record[field]) {
                        var fieldName = field.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                        medicalRecords += `<small><strong>${fieldName}:</strong> ${record[field]}</small><br>`;
                    }
                });
                medicalRecords += '</div>';
            });
            if (records.length > 3) {
                medicalRecords += `<small class="text-muted">... and ${records.length - 3} more</small>`;
            }
        } else {
            medicalRecords += '<small class="text-muted">No records found</small>';
        }
        medicalRecords += '<hr>';
    });
    
    document.getElementById('medicalRecords').innerHTML = medicalRecords;
});

// Live search filter for patient names and IDs
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('patientSearch');
    const table = document.getElementById('patientsTable');
    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');

    searchInput.addEventListener('input', function() {
        const filter = this.value.toLowerCase();

        Array.from(rows).forEach(row => {
            const nameCell = row.querySelector('.patient-name');
            const idCell = row.querySelector('.patient-id');
            let showRow = false;

            if (nameCell) {
                const nameText = nameCell.textContent.toLowerCase();
                if (nameText.indexOf(filter) > -1) {
                    showRow = true;
                }
            }

            if (idCell) {
                const idText = idCell.textContent.toLowerCase();
                if (idText.indexOf(filter) > -1) {
                    showRow = true;
                }
            }

            row.style.display = showRow ? '' : 'none';
        });
    });
});
</script>

<?php include "footer.php"; ?>
