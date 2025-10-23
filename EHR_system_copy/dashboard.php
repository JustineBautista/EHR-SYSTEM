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
      --primary: #10b981;
      --primary-light: #d1fae5;
      --primary-dark: #059669;
      --secondary: #047857;
      --secondary-light: #a7f3d0;
      --secondary-dark: #065f46;
      --accent: #f59e0b;
      --accent-light: #fef3c7;
      --text-dark: #111827;
      --text-light: #6b7280;
      --text-muted: #9ca3af;
      --border: #e5e7eb;
      --bg-light: #f9fafb;
      --bg-gradient: linear-gradient(135deg, #10b981 0%, #059669 100%);
      --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
      --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
      --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
      --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
    }

    body {
      background: linear-gradient(135deg, #f5f7fa 0%,rgb(222, 221, 221) 100%);
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
      padding-top: 5rem;
      color: var(--text-dark);
      min-height: 100vh;
    }

    .dashboard-header {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      padding: 2.5rem 0;
      margin-bottom: 2rem;
      border-bottom: 1px solid rgba(255, 255, 255, 0.2);
      box-shadow: var(--shadow-sm);
    }

    .dashboard-title {
      font-size: 2.25rem;
      font-weight: 800;
      background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      margin: 0;
      letter-spacing: -0.025em;
    }

    .welcome-card {
      background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(255, 255, 255, 0.9) 100%);
      backdrop-filter: blur(10px);
      padding: 2rem;
      border-radius: 16px;
      border: 1px solid rgba(255, 255, 255, 0.2);
      box-shadow: var(--shadow-md);
      transition: all 0.3s ease;
      position: relative;
      overflow: hidden;
    }

    .welcome-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 4px;
      background: linear-gradient(90deg, var(--primary) 0%, var(--secondary) 100%);
    }

    .welcome-card:hover {
      transform: translateY(-4px);
      box-shadow: var(--shadow-xl);
    }

    .welcome-text {
      color: var(--text-light);
      font-size: 1rem;
      margin: 0.5rem 0 0 0;
      font-weight: 500;
    }

    .card {
      border-radius: 16px;
      border: none;
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      margin-bottom: 1.5rem;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      box-shadow: var(--shadow-md);
    }

    .card:hover {
      transform: translateY(-4px);
      box-shadow: var(--shadow-xl);
    }

    .section-title {
      font-size: 1.5rem;
      font-weight: 700;
      color: var(--text-dark);
      margin-bottom: 1.5rem;
      padding-bottom: 1rem;
      border-bottom: 3px solid var(--primary);
      display: inline-block;
      position: relative;
    }

    .section-title::after {
      content: '';
      position: absolute;
      bottom: -3px;
      left: 0;
      width: 100%;
      height: 3px;
      background: linear-gradient(90deg, var(--primary) 0%, var(--secondary) 100%);
      border-radius: 2px;
    }

    .stat-card {
      padding: 2rem;
      background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(255, 255, 255, 0.9) 100%);
      border: 1px solid rgba(255, 255, 255, 0.2);
      height: 100%;
      border: 0.10px solid rgb(178, 178, 178);
      border-radius:1rem;
      position: relative;
      overflow: hidden;
    }

    .stat-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 4px;
      background: linear-gradient(90deg, var(--primary) 0%, var(--secondary) 100%);
    }

    .stat-card:hover {
      transform: translateY(-8px);
      box-shadow: var(--shadow-xl);
    }

    .stat-value {
      font-size: 3rem;
      font-weight: 800;
      background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      line-height: 1;
      margin-bottom: 0.75rem;
      letter-spacing: -0.025em;
    }

    .stat-label {
      color: var(--text-light);
      font-size: 0.875rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 1px;
      margin-bottom: 1rem;
    }

    .stat-icon {
      width: 64px;
      height: 64px;
      display: flex;
      align-items: center;
      justify-content: center;
      background: linear-gradient(135deg, var(--primary-light) 0%, var(--secondary-light) 100%);
      border-radius: 16px;
      color: var(--primary);
      margin-bottom: 1.5rem;
      box-shadow: var(--shadow-sm);
      transition: all 0.3s ease;
    }

    .stat-card:hover .stat-icon {
      transform: scale(1.1);
      box-shadow: var(--shadow-md);
    }

    .action-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 1.5rem;
      margin-bottom: 2rem;
    }

    .action-card {
      padding: 2rem;
      background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(255, 255, 255, 0.9) 100%);
      border: 1px solid rgba(255, 255, 255, 0.2);
      border-radius: 16px;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      cursor: pointer;
      text-align: center;
      position: relative;
      overflow: hidden;
    }

    .action-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(37, 99, 235, 0.1), transparent);
      transition: left 0.5s;
    }

    .action-card:hover::before {
      left: 100%;
    }

    .action-card:hover {
      transform: translateY(-6px);
      box-shadow: var(--shadow-xl);
      border-color: var(--primary);
    }

    .action-icon {
      width: 56px;
      height: 56px;
      background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
      color: white;
      border-radius: 14px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 1.5rem;
      box-shadow: var(--shadow-md);
      transition: all 0.3s ease;
    }

    .action-card:hover .action-icon {
      transform: scale(1.1) rotate(5deg);
      box-shadow: var(--shadow-lg);
    }

    .action-title {
      font-weight: 700;
      color: var(--text-dark);
      margin: 0;
      font-size: 1.125rem;
    }

    .chart-container {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      padding: 2.5rem;
      border-radius: 16px;
      border: 1px solid rgba(255, 255, 255, 0.2);
      box-shadow: var(--shadow-md);
      transition: all 0.3s ease;
    }

    .chart-container:hover {
      box-shadow: var(--shadow-xl);
    }

    .alert {
      border-radius: 12px;
      border: none;
      margin-bottom: 2rem;
      box-shadow: var(--shadow-sm);
      backdrop-filter: blur(10px);
      background: rgba(255, 255, 255, 0.95);
    }

    .form-control:focus, .form-select:focus {
      box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
      border-color: var(--primary);
      transform: scale(1.02);
    }

    .btn {
      border-radius: 12px;
      font-weight: 600;
      padding: 0.75rem 1.5rem;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      position: relative;
      overflow: hidden;
    }

    .btn::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
      transition: left 0.5s;
    }

    .btn:hover::before {
      left: 100%;
    }

    .btn-success {
      background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
      border: none;
      box-shadow: var(--shadow-md);
    }

    .btn-success:hover {
      transform: translateY(-2px);
      box-shadow: var(--shadow-lg);
      background: linear-gradient(135deg, var(--primary-dark) 0%, var(--secondary-dark) 100%);
    }

    .btn-outline-success {
      border: 2px solid var(--primary);
      color: var(--primary);
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
    }

    .btn-outline-success:hover {
      background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
      border-color: transparent;
      color: white;
      transform: translateY(-2px);
      box-shadow: var(--shadow-lg);
    }

    .modal-content {
      border-radius: 20px;
      border: none;
      box-shadow: var(--shadow-xl);
      backdrop-filter: blur(20px);
      background: rgba(255, 255, 255, 0.95);
    }

    .modal-header {
      background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
      color: white;
      border-radius: 20px 20px 0 0;
      border-bottom: none;
      padding: 2rem;
    }

    .modal-body {
      padding: 2rem;
    }

    .input-group-text {
      background: linear-gradient(135deg, var(--bg-light) 0%, rgba(255, 255, 255, 0.8) 100%);
      border-right: none;
      color: var(--text-light);
      border-radius: 12px 0 0 12px;
    }

    .form-control, .form-select {
      border-left: none;
      border-radius: 0 12px 12px 0;
      background: rgba(255, 255, 255, 0.8);
      backdrop-filter: blur(5px);
    }

    .input-group .form-control:focus,
    .input-group .form-select:focus {
      border-left: none;
      background: rgba(255, 255, 255, 0.95);
    }

    .input-group:focus-within .input-group-text {
      border-color: var(--primary);
      color: var(--primary);
      background: linear-gradient(135deg, var(--primary-light) 0%, var(--secondary-light) 100%);
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
      .dashboard-title {
        font-size: 1.875rem;
      }

      .stat-value {
        font-size: 2.5rem;
      }

      .action-grid {
        grid-template-columns: 1fr;
      }

      .chart-container {
        padding: 1.5rem;
      }
    }

    /* Animation keyframes */
    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .card {
      animation: fadeInUp 0.6s ease-out;
    }

    .card:nth-child(1) { animation-delay: 0.1s; }
    .card:nth-child(2) { animation-delay: 0.2s; }
    .card:nth-child(3) { animation-delay: 0.3s; }
    .card:nth-child(4) { animation-delay: 0.4s; }

    .stat-trend {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      font-size: 0.875rem;
      margin-bottom: 0.5rem;
    }

    .stat-link {
      display: flex;
      align-items: center;
      justify-content: flex-end;
      font-size: 0.75rem;
      font-weight: 600;
      color: var(--primary);
      text-transform: uppercase;
      letter-spacing: 0.5px;
      opacity: 0.8;
      transition: all 0.3s ease;
    }

    .stat-card:hover .stat-link {
      opacity: 1;
      transform: translateX(2px);
    }

    .stat-card-patients .stat-icon {
      background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
      color: #2563eb;
    }

    .stat-card-vitals .stat-icon {
      background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
      color: #dc2626;
    }

    .stat-card-medications .stat-icon {
      background: linear-gradient(135deg, #e0e7ff 0%, #c7d2fe 100%);
      color: #4f46e5;
    }

    .stat-card-lab .stat-icon {
      background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
      color: #d97706;
    }

    /* Counter animation */
    @keyframes countUp {
      from {
        opacity: 0;
        transform: scale(0.8);
      }
      to {
        opacity: 1;
        transform: scale(1);
      }
    }

    .stat-value {
      animation: countUp 0.8s ease-out forwards;
    }

</style>

<!-- Feedback message -->
<link rel="icon" href="IMAGES/aurora.png" type="image/png">
<?php if (!empty($msg)): ?>
  <div class="container mt-3">
    <div class="alert <?php echo strpos($msg, '✅') !== false ? 'alert-success' : (strpos($msg, '⚠️') !== false ? 'alert-warning' : 'alert-danger'); ?> alert-dismissible fade show">
      <?php echo htmlspecialchars($msg); ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  </div>
<?php endif; ?>

<div class="container-fluid px-4 py-4 br">
  <!-- Dashboard Header -->
  <div class="row mb-4">
    <div class="col-12">
      <div class="welcome-card">
        <h1 class="dashboard-title">Dashboard Overview</h1>
        <p class="welcome-text">Welcome back, <?php echo htmlspecialchars($_SESSION['admin'] ?? 'Admin'); ?>! Here's your system overview.</p>
      </div>
    </div>
  </div>

  <!-- Stats Grid -->
  <div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
      <div class="stat-card stat-card-patients">
        <div class="stat-icon">
          <i class="bi bi-people-fill fs-3"></i>
        </div>
        <div class="stat-value" data-target="<?php echo $stats['patients']; ?>">0</div>
        <div class="stat-label">Total Patients</div>
      </div>
    </div>

    <div class="col-xl-3 col-md-6">
      <div class="stat-card stat-card-vitals">
        <div class="stat-icon">
          <i class="bi bi-heart-pulse fs-3"></i>
        </div>
        <div class="stat-value" data-target="<?php echo $stats['vitals']; ?>">0</div>
        <div class="stat-label">Vital Records</div>
      </div>
    </div>

    <div class="col-xl-3 col-md-6">
      <div class="stat-card stat-card-medications">
        <div class="stat-icon">
          <i class="bi bi-capsule fs-3"></i>
        </div>
        <div class="stat-value" data-target="<?php echo $stats['medications']; ?>">0</div>
        <div class="stat-label">Medications</div>
      </div>
    </div>

    <div class="col-xl-3 col-md-6">
      <div class="stat-card stat-card-lab">
        <div class="stat-icon">
          <i class="bi bi-clipboard-data fs-3"></i>
        </div>
        <div class="stat-value" data-target="<?php echo $stats['lab_results']; ?>">0</div>
        <div class="stat-label">Lab Results</div>
      </div>
    </div>
  </div>
  <!-- Quick Actions Section -->
  <div class="row mb-4">
    <div class="col-12">
      <h2 class="section-title">Quick Actions</h2>
    </div>
  </div>

  <div class="action-grid mb-4">
    <div class="action-card" data-bs-toggle="modal" data-bs-target="#addPatientModal">
      <div class="action-icon">
        <i class="bi bi-person-plus fs-4"></i>
      </div>
      <h3 class="action-title">Add New Patient</h3>
    </div>
    <a href="patients.php" class="action-card text-decoration-none">
      <div class="action-icon">
        <i class="bi bi-people fs-4"></i>
      </div>
      <h3 class="action-title">Manage Patients</h3>
    </a>
  </div>

  <!-- Medical Summary Section -->
  <div class="row mb-4">
    <div class="col-12">
      <h2 class="section-title">Medical Summary</h2>
    </div>
  </div>

  <div class="row g-4">
    <div class="col-lg-8">
      <div class="chart-container">
        <canvas id="medicalBarChart" style="max-height: 320px;"></canvas>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="chart-container">
        <canvas id="medicalDonutChart" style="max-height: 320px;"></canvas>
      </div>
    </div>
  </div>
</div>

<!-- Add Patient Modal -->
<div class="modal fade" id="addPatientModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Add New Patient</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="post" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        <div class="modal-body p-4">
          <div class="row g-3">
            <div class="col-12">
              <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-person"></i></span>
                <input type="text" class="form-control" id="name" name="name" required maxlength="100" placeholder="Enter patient's full name">
              </div>
            </div>
            <div class="col-md-6">
              <label for="dob" class="form-label">Date of Birth</label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-calendar"></i></span>
                <input type="date" class="form-control" id="dob" name="dob" max="<?php echo date('Y-m-d'); ?>">
              </div>
            </div>
            <div class="col-md-6">
              <label for="gender" class="form-label">Gender</label>
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
            <div class="col-12">
              <label for="contact" class="form-label">Contact</label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                <input type="tel" class="form-control" id="contact" name="contact" maxlength="20" placeholder="Phone number">
              </div>
            </div>
            <div class="col-12">
              <label for="address" class="form-label">Address</label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-geo-alt"></i></span>
                <textarea class="form-control" id="address" name="address" rows="2" maxlength="500" placeholder="Patient's address"></textarea>
              </div>
            </div>
            <div class="col-12">
              <label for="history" class="form-label">Medical History</label>
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

    // Counter animation
    function animateCounter(element, target) {
        let current = 0;
        const increment = target / 100;
        const timer = setInterval(() => {
            current += increment;
            if (current >= target) {
                element.textContent = Math.floor(target);
                clearInterval(timer);
            } else {
                element.textContent = Math.floor(current);
            }
        }, 20);
    }

    // Animate all stat counters
    document.querySelectorAll('.stat-value').forEach(counter => {
        const target = parseInt(counter.getAttribute('data-target'));
        animateCounter(counter, target);
    });

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

    // Bar Chart
    const barCtx = document.getElementById('medicalBarChart').getContext('2d');
    new Chart(barCtx, {
        data: {
            labels: labels,
            datasets: [{
                type: 'bar',
                label: 'Records',
                data: data,
                backgroundColor: 'rgba(16, 185, 129, 0.85)',
                borderColor: 'rgba(16, 185, 129, 1)',
                borderWidth: 1,
                borderRadius: 6
            }, {
                type: 'line',
                label: 'Cumulative',
                data: data.map((d, i) => data.slice(0, i+1).reduce((a, b) => a + b, 0)),
                borderColor: '#ef4444',
                backgroundColor: 'rgba(239, 68, 68, 0.05)',
                borderWidth: 2,
                fill: true,
                tension: 0.3,
                pointRadius: 4,
                pointBackgroundColor: '#ef4444',
                pointBorderColor: '#fff',
                pointBorderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            interaction: {
                mode: 'index',
                intersect: false
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    labels: {
                        padding: 15,
                        usePointStyle: true,
                        font: {
                            size: 12,
                            weight: '500'
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1,
                        font: {
                            size: 11
                        }
                    },
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    }
                },
                x: {
                    ticks: {
                        font: {
                            size: 10
                        }
                    },
                    grid: {
                        display: false
                    }
                }
            }
        }
    });

    // Donut Chart
    const donutCtx = document.getElementById('medicalDonutChart').getContext('2d');
    new Chart(donutCtx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: data,
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
                borderWidth: 3,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 10,
                        usePointStyle: true,
                        font: {
                            size: 10
                        }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const value = context.parsed || 0;
                            const percentage = totalRecords > 0 ? ((value / totalRecords) * 100).toFixed(1) : 0;
                            return context.label + ': ' + value + ' (' + percentage + '%)';
                        }
                    }
                }
            }
        }
    });
});
</script>

<?php include "footer.php"; ?>