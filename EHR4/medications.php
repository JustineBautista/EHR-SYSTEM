<?php
$page_title = "Medications";
$msg = "";

if (isset($_POST['add_med'])) {
    $pid = intval($_POST['patient_id']);
    $med = $_POST['medication'] ?? "";
    $dose = $_POST['dose'] ?? "";
    $start = $_POST['start_date'] ?? "";
    $notes = $_POST['notes'] ?? "";
    $stmt = $conn->prepare("INSERT INTO medications (patient_id, medication, dose, start_date, notes) VALUES (?,?,?,?,?)");
    $stmt->bind_param("issss", $pid, $med, $dose, $start, $notes);
    if ($stmt->execute()) $msg = "Medication added.";
    $stmt->close();
}

if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM medications WHERE id=?");
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
    <h4>Medications</h4>
    <a class="btn btn-secondary" href="dashboard.php">Back</a>
  </div>

  <div class="card p-3 mb-3">
    <form method="post" class="row g-3">
      <div class="col-md-4">
        <label class="form-label">Patient</label>
        <select name="patient_id" class="form-select" required>
          <option value="">Select patient</option>
          <?php $p = $conn->query("SELECT id,fullname FROM patients ORDER BY fullname"); while ($pp=$p->fetch_assoc()): ?>
            <option value="<?php echo $pp['id'];?>"><?php echo htmlspecialchars($pp['fullname']);?></option>
          <?php endwhile; ?>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label">Medication</label>
        <input class="form-control" name="medication" placeholder="Medication" required>
      </div>
      <div class="col-md-4">
        <label class="form-label">Dose</label>
        <input class="form-control" name="dose" placeholder="Dose">
      </div>
      <div class="col-md-6">
        <label class="form-label">Start Date</label>
        <input class="form-control" name="start_date" placeholder="YYYY-MM-DD">
      </div>
      <div class="col-md-6">
        <label class="form-label">Notes</label>
        <input class="form-control" name="notes" placeholder="Notes">
      </div>
      <div class="col-12 d-flex align-items-end">
        <button name="add_med" class="btn btn-primary">Add Medication</button>
      </div>
    </form>
  </div>

  <div class="card p-3">
    <h5 class="mb-3">Medication Records</h5>
    <div class="table-responsive">
      <table class="table table-hover table-bordered align-middle">
        <thead>
          <tr>
            <th>ID</th>
            <th>Patient</th>
            <th>Medication</th>
            <th>Dose</th>
            <th>Start Date</th>
            <th>Notes</th>
            <th style="width:100px;">Action</th>
          </tr>
        </thead>
        <tbody>
        <?php
        $sql = "SELECT m.id, p.fullname, m.medication, m.dose, m.start_date, m.notes FROM medications m JOIN patients p ON m.patient_id=p.id ORDER BY m.start_date DESC";
        $res = $conn->query($sql);
        while ($r = $res->fetch_assoc()): ?>
          <tr>
            <td><?php echo $r['id'];?></td>
            <td><?php echo htmlspecialchars($r['fullname']);?></td>
            <td><?php echo htmlspecialchars($r['medication']);?></td>
            <td><?php echo htmlspecialchars($r['dose']);?></td>
            <td><?php echo htmlspecialchars($r['start_date']);?></td>
            <td><?php echo htmlspecialchars($r['notes']);?></td>
            <td>
              <a class="btn btn-sm btn-danger" href="medications.php?delete=<?php echo $r['id'];?>" onclick="return confirm('Delete this record?')">
                <i class="bi bi-trash"></i>
              </a>
            </td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include "footer.php"; ?>
