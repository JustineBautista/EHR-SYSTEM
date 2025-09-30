<?php
$page_title = "Vital Signs";
$msg = "";
$error = "";
include "db.php";



if (isset($_POST['add_vitals'])) {
    $pid = intval($_POST['patient_id']);
    $bp = $_POST['bp'] ?? "";
    $hr = $_POST['hr'] ?? "";
    $temp = $_POST['temp'] ?? "";
    $height = $_POST['height'] ?? "";
    $weight = $_POST['weight'] ?? "";
    $date = $_POST['date'] ?: date("Y-m-d H:i:s");

    // Check if patient_id is valid
    if ($pid <= 0) {
        $error = "Invalid patient selected.";
    }
    // Validate blood pressure format (systolic/diastolic)
    elseif (!empty($bp) && !preg_match('/^\d{2,3}\/\d{2,3}$/', $bp)) {
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

// Handle update vitals from modal form
if (isset($_POST['update_vitals'])) {
    $vid = intval($_POST['vital_id']);
    $pid = intval($_POST['patient_id']);
    $bp = $_POST['bp'] ?? "";
    $hr = $_POST['hr'] ?? "";
    $temp = $_POST['temp'] ?? "";
    $height = $_POST['height'] ?? "";
    $weight = $_POST['weight'] ?? "";
    $date = $_POST['date'] ?: date("Y-m-d H:i:s");

    // Check if patient_id is valid
    if ($pid <= 0) {
        $error = "Invalid patient selected.";
    }
    // Validate blood pressure format (systolic/diastolic)
    elseif (!empty($bp) && !preg_match('/^\d{2,3}\/\d{2,3}$/', $bp)) {
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
        $stmt = $conn->prepare("UPDATE vitals SET patient_id=?, bp=?, hr=?, temp=?, height=?, weight=?, date_taken=? WHERE id=?");
        $stmt->bind_param("issssssi", $pid, $bp, $hr, $temp, $height, $weight, $date, $vid);
        if ($stmt->execute()) {
            $msg = "Vitals updated.";
        } else {
            $error = "Database error: " . $stmt->error;
        }
        $stmt->close();
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
      $sql = "SELECT v.id, v.patient_id, p.fullname, v.bp, v.hr, v.temp, v.height, v.weight, v.date_taken FROM vitals v JOIN patients p ON v.patient_id=p.id ORDER BY v.date_taken DESC";
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
          <td>
            <button type="button" class="btn btn-sm btn-warning me-1 edit-btn"
              data-bs-toggle="modal" data-bs-target="#editModal"
              data-id="<?php echo $r['id'];?>"
              data-patient_id="<?php echo $r['patient_id'];?>"
              data-bp="<?php echo htmlspecialchars($r['bp']);?>"
              data-hr="<?php echo htmlspecialchars($r['hr']);?>"
              data-temp="<?php echo htmlspecialchars($r['temp']);?>"
              data-height="<?php echo htmlspecialchars($r['height']);?>"
              data-weight="<?php echo htmlspecialchars($r['weight']);?>"
              data-date="<?php echo htmlspecialchars($r['date_taken']);?>"
            >Edit</button>
            <a class="btn btn-sm btn-danger" href="vitals.php?delete=<?php echo $r['id'];?>" onclick="return confirm('Delete?')">Delete</a>
          </td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="post" id="editForm">
        <div class="modal-header">
          <h5 class="modal-title" id="editModalLabel">Edit Vital Signs</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="vital_id" id="vital_id">
          <div class="mb-3">
            <label for="patient_id" class="form-label">Patient</label>
            <select name="patient_id" id="patient_id" class="form-select" disabled>
              <option value="">Select patient</option>
              <?php
                $p = $conn->query("SELECT id,fullname FROM patients ORDER BY fullname");
                while ($pp=$p->fetch_assoc()): ?>
                <option value="<?php echo $pp['id'];?>"><?php echo htmlspecialchars($pp['fullname']);?></option>
              <?php endwhile; ?>
            </select>
            <input type="hidden" name="patient_id" id="patient_id_hidden">
          </div>
          <div class="mb-3">
            <label for="bp" class="form-label">Blood Pressure (e.g., 120/80)</label>
            <input type="text" class="form-control" name="bp" id="bp" placeholder="BP (eg.120/80)">
          </div>
          <div class="mb-3">
            <label for="hr" class="form-label">Heart Rate (bpm)</label>
            <input type="number" class="form-control" name="hr" id="hr" placeholder="HR (bpm)">
          </div>
          <div class="mb-3">
            <label for="temp" class="form-label">Temperature (°C)</label>
            <input type="number" step="0.1" class="form-control" name="temp" id="temp" placeholder="Temp (°C)">
          </div>
          <div class="mb-3">
            <label for="height" class="form-label">Height (cm)</label>
            <input type="number" step="0.1" class="form-control" name="height" id="height" placeholder="Height (cm)">
          </div>
          <div class="mb-3">
            <label for="weight" class="form-label">Weight (kg)</label>
            <input type="number" step="0.1" class="form-control" name="weight" id="weight" placeholder="Weight (kg)">
          </div>
          <div class="mb-3">
            <label for="date" class="form-label">Date/Time</label>
            <input type="text" class="form-control" name="date" id="date" placeholder="Date/time">
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" name="update_vitals" class="btn btn-primary">Save changes</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var editModal = document.getElementById('editModal');
  editModal.addEventListener('show.bs.modal', function (event) {
    var button = event.relatedTarget;
    var id = button.getAttribute('data-id');
    var patientId = button.getAttribute('data-patient_id');
    var bp = button.getAttribute('data-bp');
    var hr = button.getAttribute('data-hr');
    var temp = button.getAttribute('data-temp');
    var height = button.getAttribute('data-height');
    var weight = button.getAttribute('data-weight');
    var date = button.getAttribute('data-date');

    // Populate modal fields
    document.getElementById('vital_id').value = id;
    document.getElementById('patient_id').value = patientId;
    document.getElementById('patient_id_hidden').value = patientId;
    document.getElementById('bp').value = bp;
    document.getElementById('hr').value = hr;
    document.getElementById('temp').value = temp;
    document.getElementById('height').value = height;
    document.getElementById('weight').value = weight;
    document.getElementById('date').value = date;
  });
});
</script>

<?php include "footer.php"; ?>
