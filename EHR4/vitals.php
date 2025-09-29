<?php
$page_title = "Vital Signs";
$msg = "";
$error = "";

if (isset($_POST['add_vitals'])) {
    $pid = intval($_POST['patient_id']);
    $bp = $_POST['bp'] ?? "";
    $hr = $_POST['hr'] ?? "";
    $temp = $_POST['temp'] ?? "";
    $height = $_POST['height'] ?? "";
    $weight = $_POST['weight'] ?? "";
    $date = $_POST['date'] ?: date("Y-m-d H:i:s");
    
    // Validate blood pressure format (systolic/diastolic)
    if (!empty($bp) && !preg_match('/^\d{2,3}\/\d{2,3}$/', $bp)) {
        $error = "Blood pressure must be in format 'systolic/diastolic' (e.g., 120/80)";
    }
    // Validate heart rate (40-220 bpm)
    elseif (!empty($hr) && (!is_numeric($hr) || $hr < 40 || $hr > 220)) {
        $error = "Heart rate must be between 40-220 bpm";
    }
    // Validate temperature (35-42°C)
    elseif (!empty($temp) && (!is_numeric($temp) || $temp < 35 || $temp > 42)) {
        $error = "Temperature must be between 35-42°C";
    }
    // Validate height (30-250 cm)
    elseif (!empty($height) && (!is_numeric($height) || $height < 30 || $height > 250)) {
        $error = "Height must be between 30-250 cm";
    }
    // Validate weight (0.5-500 kg)
    elseif (!empty($weight) && (!is_numeric($weight) || $weight < 0.5 || $weight > 500)) {
        $error = "Weight must be between 0.5-500 kg";
    }
    // Validate date format
    elseif (!empty($date) && !preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/', $date)) {
        $error = "Date must be in format YYYY-MM-DD or YYYY-MM-DD HH:MM:SS";
    }
    else {
        $stmt = $conn->prepare("INSERT INTO vitals (patient_id, bp, hr, temp, height, weight, date_taken) VALUES (?,?,?,?,?,?,?)");
        $stmt->bind_param("issssss", $pid, $bp, $hr, $temp, $height, $weight, $date);
        if ($stmt->execute()) {
            $msg = "Vitals recorded.";
        } else {
            $error = "Database error: " . $stmt->error;
        }
        $stmt->close();
    }
}
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM vitals WHERE id=?");
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
    <h4>Vital Signs</h4>
    <a class="btn btn-secondary" href="dashboard.php">Back</a>
  </div>

  <div class="card p-3 mb-3">
    <form method="post" class="row g-2">
      <div class="col-md-4">
        <select name="patient_id" class="form-select" required>
          <option value="">Select patient</option>
          <?php $p = $conn->query("SELECT id,fullname FROM patients ORDER BY fullname"); while ($pp=$p->fetch_assoc()): ?>
            <option value="<?php echo $pp['id'];?>"><?php echo htmlspecialchars($pp['fullname']);?></option>
          <?php endwhile; ?>
        </select>
      </div>
      <div class="col-md-2"><input class="form-control" name="bp" placeholder="BP (eg.120/80)"></div>
      <div class="col-md-2"><input class="form-control" name="hr" placeholder="HR (bpm)"></div>
      <div class="col-md-2"><input class="form-control" name="temp" placeholder="Temp (°C)"></div>
      <div class="col-md-2"><input class="form-control" name="date" placeholder="Date/time"></div>
      <div class="col-md-3"><input class="form-control" name="height" placeholder="Height (cm)"></div>
      <div class="col-md-3"><input class="form-control" name="weight" placeholder="Weight (kg)"></div>
      <div class="col-12"><button name="add_vitals" class="btn btn-primary">Record Vitals</button></div>
    </form>
  </div>

  <div class="card p-3">
    <table class="table table-sm table-bordered">
      <thead><tr><th>ID</th><th>Patient</th><th>BP</th><th>HR</th><th>Temp</th><th>Height</th><th>Weight</th><th>Date</th><th>Action</th></tr></thead>
      <tbody>
      <?php
      $sql = "SELECT v.id, p.fullname, v.bp, v.hr, v.temp, v.height, v.weight, v.date_taken FROM vitals v JOIN patients p ON v.patient_id=p.id ORDER BY v.date_taken DESC";
      $res = $conn->query($sql);
      while ($r = $res->fetch_assoc()): ?>
        <tr>
          <td><?php echo $r['id'];?></td>
          <td><?php echo htmlspecialchars($r['fullname']);?></td>
          <td><?php echo htmlspecialchars($r['bp']);?></td>
          <td><?php echo htmlspecialchars($r['hr']);?></td>
          <td><?php echo htmlspecialchars($r['temp']);?></td>
          <td><?php echo htmlspecialchars($r['height']);?></td>
          <td><?php echo htmlspecialchars($r['weight']);?></td>
          <td><?php echo htmlspecialchars($r['date_taken']);?></td>
          <td><a class="btn btn-sm btn-danger" href="vitals.php?delete=<?php echo $r['id'];?>" onclick="return confirm('Delete?')">Delete</a></td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include "footer.php"; ?>
