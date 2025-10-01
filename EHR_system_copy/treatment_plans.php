<?php
$page_title = "Treatment Plans";
$msg = "";
$error = "";
include "db.php";

// Function to sanitize input
function sanitize_input($conn, $data) {
    return mysqli_real_escape_string($conn, trim($data));
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_plan'])) {
    $pid = intval($_POST['patient_id']);
    $plan = sanitize_input($conn, $_POST['plan'] ?? "");
    $notes = sanitize_input($conn, $_POST['notes'] ?? "");
    $date = $_POST['date'] ?: date("Y-m-d H:i:s");

    // Validate date format if provided
    if (!empty($_POST['date']) && !preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/', $date)) {
        $error = "Date must be in format YYYY-MM-DD or YYYY-MM-DD HH:MM:SS.";
    } else {
        $stmt = $conn->prepare("INSERT INTO treatment_plans (patient_id, plan, notes, date_planned) VALUES (?,?,?,?)");
        $stmt->bind_param("isss", $pid, $plan, $notes, $date);
        if ($stmt->execute()) {
            $msg = "Treatment plan added.";
        } else {
            $error = "Error: " . $stmt->error;
        }
        $stmt->close();
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_plan'])) {
    $id = intval($_POST['edit_id']);
    $plan = sanitize_input($conn, $_POST['edit_plan'] ?? "");
    $notes = sanitize_input($conn, $_POST['edit_notes'] ?? "");
    $date = $_POST['edit_date'] ?: date("Y-m-d H:i:s");

    // Validate date format if provided
    if (!empty($_POST['edit_date']) && !preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/', $date)) {
        $error = "Date must be in format YYYY-MM-DD or YYYY-MM-DD HH:MM:SS.";
    } else {
        $stmt = $conn->prepare("UPDATE treatment_plans SET plan=?, notes=?, date_planned=? WHERE id=?");
        $stmt->bind_param("sssi", $plan, $notes, $date, $id);
        if ($stmt->execute()) {
            $msg = "Treatment plan updated.";
        } else {
            $error = "Error: " . $stmt->error;
        }
        $stmt->close();
    }
}

if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM treatment_plans WHERE id=?");
    $stmt->bind_param("i",$id);
    if ($stmt->execute()) $msg = "Deleted.";
    $stmt->close();
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

    h4 {
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
    .btn-action{
      display:flex;
      flex-direction:row;
      gap:1rem;
    }
    .btn-edit, .btn-delete{
      width: 5.75rem;
      display:flex;
      flex-direction:column;
    }
</style>

<!-- Feedback message -->
<?php if (!empty($msg)): ?>
  <div class="container mt-3">
    <div class="alert alert-success alert-dismissible fade show">
      <?php echo htmlspecialchars($msg); ?>
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
    <h4>Treatment Plans</h4>
    <a class="btn btn-secondary" href="dashboard.php">Back</a>
  </div>

  <div class="card p-3 mb-3">
    <form method="post" class="row g-3">
      <div class="col-md-4">
        <label for="patient_id" class="form-label">Patient</label>
        <select id="patient_id" name="patient_id" class="form-select" required>
          <option value="">Select patient</option>
          <?php $p = $conn->query("SELECT id,fullname FROM patients ORDER BY fullname"); while($pp=$p->fetch_assoc()): ?>
            <option value="<?php echo $pp['id'];?>"><?php echo htmlspecialchars($pp['fullname']);?></option>
          <?php endwhile; ?>
        </select>
      </div>
      <div class="col-md-4">
        <label for="plan" class="form-label">Treatment Plan</label>
        <input id="plan" class="form-control" name="plan" placeholder="Treatment plan" required>
      </div>
      <div class="col-md-4">
        <label for="date" class="form-label">Date/Time(Optional)</label>
        <input id="date" class="form-control" name="date" placeholder="YYYY-MM-DD/HH:MM:SS">
      </div>
      <div class="col-12">
        <label for="notes" class="form-label">Notes</label> 
        <textarea id="notes" class="form-control" name="notes" placeholder="Notes" rows="3"></textarea>
      </div>
      <div class="col-12 d-flex align-items-end">
        <button name="add_plan" class="btn btn-primary">Add Plan</button>
      </div>
    </form>
  </div>

  <div class="card p-3">
    <h5 class="mb-3">Treatment Plans</h5>
    <div class="table-responsive">
      <table class="table table-hover table-bordered align-middle">
        <thead>
          <tr>
            <th>ID</th>
            <th>Patient</th>
            <th>Plan</th>
            <th>Notes</th>
            <th>Date Planned</th>
            <th style="width:100px;">Action</th>
          </tr>
        </thead>
        <tbody>
        <?php
        $sql = "SELECT t.id, p.fullname, t.plan, t.notes, t.date_planned FROM treatment_plans t JOIN patients p ON t.patient_id=p.id ORDER BY t.date_planned DESC";
        $res = $conn->query($sql);
        while ($r = $res->fetch_assoc()): ?>
          <tr>
            <td><?php echo $r['id'];?></td>
            <td><?php echo htmlspecialchars($r['fullname']);?></td>
            <td><?php echo htmlspecialchars($r['plan']);?></td>
            <td><?php echo htmlspecialchars($r['notes']);?></td>
            <td><?php echo htmlspecialchars($r['date_planned']);?></td>
            <td class="btn-action">
              <a class="btn btn-sm btn-danger btn-Delete" href="treatment_plans.php?delete=<?php echo $r['id'];?>" onclick="return confirm('Delete this plan?')">
                  <i class="bi bi-trash"></i>
                  Delete
              </a>
              <a class="btn btn-sm btn-warning me-1 btn-edit" data-bs-toggle="modal" data-bs-target="#editModal" data-id="<?php echo $r['id']; ?>" data-patient="<?php echo htmlspecialchars($r['fullname']); ?>" data-plan="<?php echo htmlspecialchars($r['plan']); ?>" data-notes="<?php echo htmlspecialchars($r['notes']); ?>" data-date="<?php echo htmlspecialchars($r['date_planned']); ?>">
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

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editModalLabel">Edit Treatment Plan</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form method="post" id="editForm">
          <input type="hidden" name="edit_id" id="edit_id">
          <div class="mb-3">
            <label for="edit_patient" class="form-label">Patient</label>
            <input type="text" class="form-control" id="edit_patient" readonly>
          </div>
          <div class="mb-3">
            <label for="edit_plan" class="form-label">Treatment Plan</label>
            <input type="text" class="form-control" name="edit_plan" id="edit_plan" required>
          </div>
          <div class="mb-3">
            <label for="edit_notes" class="form-label">Notes</label>
            <textarea class="form-control" name="edit_notes" id="edit_notes" rows="3"></textarea>
          </div>
          <div class="mb-3">
            <label for="edit_date" class="form-label">Date/Time</label>
            <input type="text" class="form-control" name="edit_date" id="edit_date">
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="submit" form="editForm" name="update_plan" class="btn btn-primary">Save changes</button>
      </div>
    </div>
  </div>
</div>

<script>
var editModal = document.getElementById('editModal');
editModal.addEventListener('show.bs.modal', function (event) {
  var button = event.relatedTarget;
  var id = button.getAttribute('data-id');
  var patient = button.getAttribute('data-patient');
  var plan = button.getAttribute('data-plan');
  var notes = button.getAttribute('data-notes');
  var date = button.getAttribute('data-date');
  document.getElementById('edit_id').value = id;
  document.getElementById('edit_patient').value = patient;
  document.getElementById('edit_plan').value = plan;
  document.getElementById('edit_notes').value = notes;
  document.getElementById('edit_date').value = date;
});
</script>

<?php include "footer.php"; ?>
