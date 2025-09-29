<?php
$page_title = "Treatment Plans";
$msg = "";
$error = "";

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
        $error = "Invalid date format. Use YYYY-MM-DD or YYYY-MM-DD HH:MM:SS.";
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
        <label for="date" class="form-label">Date/Time</label>
        <input id="date" class="form-control" name="date" placeholder="YYYY-MM-DD or YYYY-MM-DD HH:MM:SS">
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
            <td>
              <a class="btn btn-sm btn-danger" href="treatment_plans.php?delete=<?php echo $r['id'];?>" onclick="return confirm('Delete this plan?')">
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
