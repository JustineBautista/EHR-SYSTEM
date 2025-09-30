<?php
session_start();
$page_title = "Medical Records";
include "db.php";
include "header.php";
if (!isset($_SESSION["admin"])) {
    header("Location: index.php");
    exit();
}

$msg = "";
$error = "";

// Add record
if (isset($_POST["add"])) {
    $patient_id = intval($_POST["patient_id"]);
    $details = $_POST["details"] ?? "";
    
    $stmt = $conn->prepare("INSERT INTO records (patient_id, details) VALUES (?, ?)");
    $stmt->bind_param("is", $patient_id, $details);
    if ($stmt->execute()) {
        $msg = "Record added successfully.";
    } else {
        $error = "Error adding record: " . $stmt->error;
    }
    $stmt->close();
}

// Delete record
if (isset($_GET["delete"])) {
    $id = intval($_GET["delete"]);
    $stmt = $conn->prepare("DELETE FROM records WHERE id=?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $msg = "Record deleted successfully.";
    } else {
        $error = "Error deleting record: " . $stmt->error;
    }
    $stmt->close();
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
    <h4>Medical Records</h4>
    <a class="btn btn-secondary" href="dashboard.php">Back</a>
  </div>

  <div class="card p-3 mb-3">
    <form method="POST" class="row g-3">
      <div class="col-md-6">
        <label class="form-label">Patient</label>
        <select name="patient_id" class="form-select" required>
          <option value="">Select patient</option>
          <?php 
          $patients = $conn->query("SELECT id, fullname FROM patients ORDER BY fullname");
          while ($p = $patients->fetch_assoc()): ?>
            <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['fullname']); ?></option>
          <?php endwhile; ?>
        </select>
      </div>
      <div class="col-md-6">
        <label class="form-label">Record Details</label>
        <textarea class="form-control" name="details" placeholder="Enter record details" rows="3" required></textarea>
      </div>
      <div class="col-12 d-flex align-items-end">
        <button name="add" class="btn btn-primary">Add Record</button>
      </div>
    </form>
  </div>

  <div class="card p-3">
    <h5 class="mb-3">Records</h5>
    <div class="table-responsive">
      <table class="table table-hover table-bordered align-middle">
        <thead>
          <tr>
            <th>ID</th>
            <th>Patient</th>
            <th>Details</th>
            <th style="width:100px;">Action</th>
          </tr>
        </thead>
        <tbody>
        <?php
        $result = $conn->query("SELECT r.id, p.fullname, r.details FROM records r JOIN patients p ON r.patient_id = p.id ORDER BY r.id DESC");
        while ($row = $result->fetch_assoc()): ?>
          <tr>
            <td><?php echo $row['id']; ?></td>
            <td><?php echo htmlspecialchars($row['fullname']); ?></td>
            <td><?php echo htmlspecialchars($row['details']); ?></td>
            <td>
              <a class="btn btn-sm btn-danger" href="records.php?delete=<?php echo $row['id']; ?>" onclick="return confirm('Delete this record?')">
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
