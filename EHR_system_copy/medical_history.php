<?php
$page_title = "Medical History";
$msg = "";
include "db.php";

// Add history
if (isset($_POST['add_history'])) {
    $pid = intval($_POST['patient_id']);
    $condition = $_POST['condition'] ?? "";
    $notes = $_POST['notes'] ?? "";
    $date = $_POST['date'] ?: date("Y-m-d H:i:s");

    $stmt = $conn->prepare("INSERT INTO medical_history (patient_id, condition_name, notes, date_recorded) VALUES (?,?,?,?)");
    $stmt->bind_param("isss", $pid, $condition, $notes, $date);
    if ($stmt->execute()) $msg = "History added successfully.";
    $stmt->close();
}

// Update history
if (isset($_POST['update_history'])) {
    $id = intval($_POST['id']);
    $pid = intval($_POST['patient_id']);
    $condition = $_POST['condition'] ?? "";
    $notes = $_POST['notes'] ?? "";
    $date = $_POST['date'] ?: date("Y-m-d H:i:s");

    $stmt = $conn->prepare("UPDATE medical_history SET patient_id=?, condition_name=?, notes=?, date_recorded=? WHERE id=?");
    $stmt->bind_param("isssi", $pid, $condition, $notes, $date, $id);
    if ($stmt->execute()) $msg = "History updated successfully.";
    $stmt->close();
}

// Delete history
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM medical_history WHERE id=?");
    $stmt->bind_param("i",$id);
    if ($stmt->execute()) $msg = "History deleted.";
    $stmt->close();
}

if (isset($_GET['get_history'])) {
    $id = intval($_GET['get_history']);
    $stmt = $conn->prepare("SELECT * FROM medical_history WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        echo json_encode($row);
    }
    $stmt->close();
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
    .btn-edit{
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

<div class="container mt-4">
  <div class="d-flex justify-content-between mb-2">
    <h4>Medical History</h4>
    <a class="btn btn-secondary" href="dashboard.php">Back</a>
  </div>

  <!-- Add Form -->
  <div class="card p-4">
    <h5 class="mb-3 text-success"><i class="bi bi-plus-circle me-1"></i> Add Medical History</h5>
    <form method="post" class="row g-3">
      <div class="col-md-6">
        <label class="form-label">Patient</label>
        <select name="patient_id" class="form-select" required>
          <option value="">Select patient...</option>
          <?php 
          $ps = $conn->query("SELECT id, fullname FROM patients ORDER BY fullname");
          while ($p = $ps->fetch_assoc()): ?>
            <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['fullname']); ?></option>
          <?php endwhile; ?>
        </select>
      </div>
      <div class="col-md-6">
        <label class="form-label">Condition / Illness</label>
        <input class="form-control" name="condition" placeholder="Enter condition" required>
      </div>
      <div class="col-12">
        <label class="form-label">Notes</label>
        <textarea class="form-control" name="notes" placeholder="Additional notes..."></textarea>
      </div>
      <div class="col-md-4">
        <label class="form-label">Date</label>
        <input class="form-control" name="date" placeholder="YYYY-MM-DD HH:MM:SS (optional)">
      </div>
      <div class="col-md-8 d-flex align-items-end">
        <button name="add_history" class="btn btn-primary"><i class="bi bi-save me-1"></i> Save History</button>
      </div>
    </form>
  </div>

  <!-- Table -->
  <div class="card p-3">
    <h5 class="mb-3"><i class="bi bi-list-ul me-1"></i> History Records</h5>
    <div class="table-responsive">
      <table class="table table-hover table-bordered align-middle">
        <thead>
          <tr>
            <th>ID</th>
            <th>Patient</th>
            <th>Condition</th>
            <th>Notes</th>
            <th>Date</th>
            <th style="width:100px;">Action</th>
          </tr>
        </thead>
        <tbody>
        <?php
        $sql = "SELECT h.id, h.patient_id, p.fullname, h.condition_name, h.notes, h.date_recorded
                FROM medical_history h
                JOIN patients p ON h.patient_id=p.id
                ORDER BY h.date_recorded DESC";
        $res = $conn->query($sql);
        while ($r = $res->fetch_assoc()): ?>
          <tr>
            <td><?php echo $r['id'];?></td>
            <td><?php echo htmlspecialchars($r['fullname']);?></td>
            <td><?php echo htmlspecialchars($r['condition_name']);?></td>
            <td><?php echo htmlspecialchars($r['notes']);?></td>
            <td><?php echo htmlspecialchars($r['date_recorded']);?></td>
            <td class="action-btn">
              <a class="btn btn-sm btn-danger" href="medical_history.php?delete=<?php echo $r['id']; ?>" onclick="return confirm('Delete this record?')">
                  <i class="bi bi-trash"></i>
                  Delete
                </a>
              <a class="btn btn-sm btn-warning btn-edit" href="#" onclick="editHistory(<?php echo $r['id']; ?>)">
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
        <h5 class="modal-title" id="editModalLabel">Edit Medical History</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="post">
        <div class="modal-body">
          <input type="hidden" name="id" id="edit-id">
          <div class="mb-3">
            <label class="form-label">Patient</label>
            <select name="patient_id" id="edit-patient-id" class="form-select" required>
              <option value="">Select patient...</option>
              <?php
              $ps = $conn->query("SELECT id, fullname FROM patients ORDER BY fullname");
              while ($p = $ps->fetch_assoc()): ?>
                <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['fullname']); ?></option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Condition / Illness</label>
            <input class="form-control" name="condition" id="edit-condition" placeholder="Enter condition" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Notes</label>
            <textarea class="form-control" name="notes" id="edit-notes" placeholder="Additional notes..."></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">Date</label>
            <input class="form-control" name="date" id="edit-date" placeholder="YYYY-MM-DD HH:MM:SS (optional)">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button name="update_history" class="btn btn-primary">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function editHistory(id) {
    fetch('medical_history.php?get_history=' + id)
        .then(response => response.json())
        .then(data => {
            document.getElementById('edit-id').value = data.id;
            document.getElementById('edit-patient-id').value = data.patient_id;
            document.getElementById('edit-condition').value = data.condition_name;
            document.getElementById('edit-notes').value = data.notes;
            document.getElementById('edit-date').value = data.date_recorded;
            new bootstrap.Modal(document.getElementById('editModal')).show();
        })
        .catch(error => console.error('Error:', error));
}
</script>

<?php include "footer.php"; ?>
