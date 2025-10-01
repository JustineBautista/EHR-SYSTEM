<?php
$page_title = "Diagnostics";
$error = "";
$success = "";
include "db.php";

// Sanitize function
function sanitize_input($conn, $data) {
    return mysqli_real_escape_string($conn, trim($data));
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $patient_id = sanitize_input($conn, $_POST['patient_id']);
    $problem    = sanitize_input($conn, $_POST['problem']);
    $diagnosis  = sanitize_input($conn, $_POST['diagnosis']);
    $date       = isset($_POST['date_diagnosed']) ? sanitize_input($conn, $_POST['date_diagnosed']) : '';

    // If date is empty, set to current date
    if (empty($date)) {
        $date = date('Y-m-d');
    }

    // Validate date format if provided
    if (!empty($date) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $error = "Invalid date format. Use YYYY-MM-DD.";
    } else {
        if (isset($_POST['edit_id']) && !empty($_POST['edit_id'])) {
            $edit_id = intval($_POST['edit_id']);
            $sql = "UPDATE diagnostics SET patient_id='$patient_id', problem='$problem', diagnosis='$diagnosis', date_diagnosed='$date' WHERE id=$edit_id";
            if (mysqli_query($conn, $sql)) {
                $success = "Diagnosis updated successfully.";
            } else {
                $error = "Error updating diagnosis.";
            }
        } else {
            $sql = "INSERT INTO diagnostics (patient_id, problem, diagnosis, date_diagnosed)
                    VALUES ('$patient_id', '$problem', '$diagnosis', '$date')";
            if (mysqli_query($conn, $sql)) {
                $success = "Diagnosis added successfully.";
            } else {
                $error = "Error: " . mysqli_error($conn);
            }
        }
    }
}

if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $sql = "DELETE FROM diagnostics WHERE id=$id";
    if (mysqli_query($conn, $sql)) {
        $success = "Diagnosis deleted successfully.";
    } else {
        $error = "Error deleting diagnosis.";
    }
}

if (isset($_GET['get_diagnosis'])) {
    $id = intval($_GET['get_diagnosis']);
    $sql = "SELECT * FROM diagnostics WHERE id=$id";
    $result = mysqli_query($conn, $sql);
    if ($row = mysqli_fetch_assoc($result)) {
        echo json_encode($row);
    }
    exit;
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
    .btn-edit{
      width: 5.75rem;
      display:flex;
      flex-direction:column;
    }

    h4, h5 {
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
    .action-btn{
      display:flex;
      flex-direction:row;
      gap:1.05rem;
    }

</style>

<!-- Feedback message -->
<?php if (!empty($success)): ?>
  <div class="container mt-3">
    <div class="alert alert-success alert-dismissible fade show">
      <?php echo htmlspecialchars($success); ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  </div>
<?php endif; ?>

<?php if (!empty($error)): ?>
  <div class="container mt-3">
    <div class="alert alert-danger alert-dismissible fade show">
      <?php echo htmlspecialchars($error); ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  </div>
<?php endif; ?>

<div class="container mt-4">
  <div class="d-flex justify-content-between mb-2">
    <h4>Diagnostics</h4>
    <a class="btn btn-secondary" href="dashboard.php">Back</a>
  </div>

  <!-- Add Diagnosis Form -->
  <div class="card p-3 mb-3">
    <form method="POST" class="row g-3">
      <div class="col-md-6">
        <label class="form-label">Patient</label>
        <select id="patient_id" name="patient_id" class="form-select" required>
            <option value="">Select Patient</option>
            <?php
            $patients = mysqli_query($conn, "SELECT id, fullname FROM patients ORDER BY fullname");
            while ($p = mysqli_fetch_assoc($patients)) {
                echo "<option value='{$p['id']}'>{$p['fullname']}</option>";
            }
            ?>
        </select>
      </div>
      <div class="col-md-6">
        <label class="form-label">Problem</label>
        <input type="text" id="problem" name="problem" class="form-control" placeholder="Problem" required>
      </div>
      <div class="col-12">
        <label class="form-label">Diagnosis</label>
        <textarea id="diagnosis" name="diagnosis" class="form-control" placeholder="Diagnosis" rows="4" required></textarea>
      </div>
      <div class="col-md-4">
        <label class="form-label">Date Diagnosed(Optional)</label>
        <input type="date" id="date_diagnosed" name="date_diagnosed" class="form-control">
      </div>
      <div class="col-md-8 d-flex align-items-end">
        <button type="submit" class="btn btn-primary">Save Diagnosis</button>
      </div>
    </form>
  </div>

  <!-- Diagnosis Records -->
  <div class="card p-3">
    <h5 class="mb-3">Diagnosis Records</h5>
    <div class="table-responsive">
      <table class="table table-hover table-bordered align-middle">
        <thead>
          <tr>
            <th>ID</th>
            <th>Patient</th>
            <th>Problem</th>
            <th>Diagnosis</th>
            <th>Date Diagnosed</th>
            <th style="width:100px;">Action</th>
          </tr>
        </thead>
        <tbody>
        <?php
        $result = mysqli_query($conn, "SELECT d.id, p.fullname AS patient_name, d.problem, d.diagnosis, d.date_diagnosed
                                       FROM diagnostics d
                                       JOIN patients p ON d.patient_id = p.id
                                       ORDER BY d.id DESC");
        while ($row = mysqli_fetch_assoc($result)): ?>
          <tr>
            <td><?php echo $row['id']; ?></td>
            <td><?php echo htmlspecialchars($row['patient_name']); ?></td>
            <td><?php echo htmlspecialchars($row['problem']); ?></td>
            <td><?php echo htmlspecialchars($row['diagnosis']); ?></td>
            <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($row['date_diagnosed']))); ?></td>
            <td class="action-btn">
              <a class="btn btn-sm btn-danger" href="diagnostics.php?delete=<?php echo $row['id']; ?>" onclick="return confirm('Delete this diagnosis?')">
                  <i class="bi bi-trash"></i>
                  Delete
                </a>
              <a class="btn btn-sm btn-warning btn-edit" href="#" onclick="editDiagnosis(<?php echo $row['id']; ?>)">
                <i class="bi bi-pencil"></i>
                Edit
              </a>
            </td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Edit Diagnosis Modal -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editModalLabel">Edit Diagnosis</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="editForm" method="POST" class="row g-3">
          <input type="hidden" name="edit_id" id="edit_id">
          <div class="col-md-6">
            <label class="form-label">Patient</label>
            <select id="edit_patient_id" name="patient_id" class="form-select" required>
                <option value="">Select Patient</option>
                <?php
                $patients = mysqli_query($conn, "SELECT id, fullname FROM patients ORDER BY fullname");
                while ($p = mysqli_fetch_assoc($patients)) {
                    echo "<option value='{$p['id']}'>{$p['fullname']}</option>";
                }
                ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Problem</label>
            <input type="text" id="edit_problem" name="problem" class="form-control" placeholder="Problem" required>
          </div>
          <div class="col-12">
            <label class="form-label">Diagnosis</label>
            <textarea id="edit_diagnosis" name="diagnosis" class="form-control" placeholder="Diagnosis" rows="4" required></textarea>
          </div>
          <div class="col-md-12">
            <label class="form-label">Date Diagnosed</label>
            <input type="date" id="edit_date_diagnosed" name="date_diagnosed" class="form-control">
          </div>  
        </form>  
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="submit" form="editForm" class="btn btn-primary">Save Changes</button>
      </div>
    </div>
  </div>
</div>

<script>
function editDiagnosis(id) {
    fetch('diagnostics.php?get_diagnosis=' + id)
        .then(response => response.json())
        .then(data => {
            document.getElementById('edit_id').value = data.id;
            document.getElementById('edit_patient_id').value = data.patient_id;
            document.getElementById('edit_problem').value = data.problem;
            document.getElementById('edit_diagnosis').value = data.diagnosis;
            document.getElementById('edit_date_diagnosed').value = data.date_diagnosed;
            new bootstrap.Modal(document.getElementById('editModal')).show();
        })
        .catch(error => console.error('Error:', error));
}
</script>

<?php include "footer.php"; ?>
