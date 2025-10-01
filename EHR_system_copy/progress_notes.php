<?php
$page_title = "Progress Notes";
$msg = "";
$error = "";
include "db.php";

// Function to sanitize input
function sanitize_input($conn, $data) {
    return mysqli_real_escape_string($conn, trim($data));
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_note'])) {
    $pid = intval($_POST['patient_id']);
    $note = sanitize_input($conn, $_POST['note'] ?? "");
    $author = sanitize_input($conn, $_POST['author'] ?? "");
    $date = $_POST['date'] ?: date("Y-m-d H:i:s");

    // Validate date format if provided
    if (!empty($_POST['date']) && !preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/', $date)) {
        $error = "Invalid date format. Use YYYY-MM-DD or YYYY-MM-DD HH:MM:SS.";
    } else {
        $stmt = $conn->prepare("INSERT INTO progress_notes (patient_id, note, author, date_written) VALUES (?,?,?,?)");
        $stmt->bind_param("isss", $pid, $note, $author, $date);
        if ($stmt->execute()) {
            $msg = "Note added.";
        } else {
            $error = "Error: " . $stmt->error;
        }
        $stmt->close();
    }
}

if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM progress_notes WHERE id=?");
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
    <h4>Progress Notes</h4>
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
        <label for="author" class="form-label">Author</label>
        <input id="author" class="form-control" name="author" placeholder="Author (e.g., Nurse A)">
      </div>
      <div class="col-md-4">
        <label for="date" class="form-label">Date/Time</label>
        <input id="date" class="form-control" name="date" placeholder="YYYY-MM-DD or YYYY-MM-DD HH:MM:SS">
      </div>
      <div class="col-12">
        <label for="note" class="form-label">Progress Note</label>
        <textarea id="note" class="form-control" name="note" placeholder="Enter progress note" rows="4" required></textarea>
      </div>
      <div class="col-12 d-flex align-items-end">
        <button name="add_note" class="btn btn-primary">Add Note</button>
      </div>
    </form>
  </div>

  <div class="card p-3">
    <h5 class="mb-3">Progress Notes</h5>
    <div class="table-responsive">
      <table class="table table-hover table-bordered align-middle">
        <thead>
          <tr>
            <th>ID</th>
            <th>Patient</th>
            <th>Note</th>
            <th>Author</th>
            <th>Date Written</th>
            <th style="width:100px;">Action</th>
          </tr>
        </thead>
        <tbody>
        <?php
        $sql = "SELECT n.id, p.fullname, n.note, n.author, n.date_written FROM progress_notes n JOIN patients p ON n.patient_id=p.id ORDER BY n.date_written DESC";
        $res = $conn->query($sql);
        while ($r = $res->fetch_assoc()): ?>
          <tr>
            <td><?php echo $r['id'];?></td>
            <td><?php echo htmlspecialchars($r['fullname']);?></td>
            <td><?php echo htmlspecialchars($r['note']);?></td>
            <td><?php echo htmlspecialchars($r['author']);?></td>
            <td><?php echo htmlspecialchars($r['date_written']);?></td>
            <td class="btn-action">
              <a class="btn btn-sm btn-danger btn-Delete" href="progress_notes.php?delete=<?php echo $r['id'];?>" onclick="return confirm('Delete this note?')">
                <i class="bi bi-trash"></i>
                Delete
              </a>
              <a class="btn btn-sm btn-warning me-1 btn-edit" data-bs-toggle="modal" data-bs-target="#editNoteModal" data-id="<?php echo $r['id']; ?>" data-patient="<?php echo htmlspecialchars($r['fullname']); ?>" data-note="<?php echo htmlspecialchars($r['note']); ?>" data-author="<?php echo htmlspecialchars($r['author']); ?>" data-date="<?php echo htmlspecialchars($r['date_written']); ?>">
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

<!-- Edit Note Modal -->
<div class="modal fade" id="editNoteModal" tabindex="-1" aria-labelledby="editNoteModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editNoteModalLabel">Edit Progress Note</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form method="post" id="editNoteForm">
          <input type="hidden" name="edit_note_id" id="edit_note_id">
          <div class="mb-3">
            <label for="edit_patient" class="form-label">Patient</label>
            <input type="text" class="form-control" id="edit_patient" readonly>
          </div>
          <div class="mb-3">
            <label for="edit_author" class="form-label">Author</label>
            <input type="text" class="form-control" name="edit_author" id="edit_author" required>
          </div>
          <div class="mb-3">
            <label for="edit_date" class="form-label">Date/Time</label>
            <input type="text" class="form-control" name="edit_date" id="edit_date" placeholder="YYYY-MM-DD or YYYY-MM-DD HH:MM:SS" required>
          </div>
          <div class="mb-3">
            <label for="edit_note" class="form-label">Progress Note</label>
            <textarea class="form-control" name="edit_note" id="edit_note" rows="4" required></textarea>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="submit" form="editNoteForm" name="save_edit" class="btn btn-primary">Save changes</button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  var editButtons = document.querySelectorAll('.edit-btn');
  var editModal = new bootstrap.Modal(document.getElementById('editNoteModal'));
  var editNoteForm = document.getElementById('editNoteForm');

  editButtons.forEach(function(button) {
    button.addEventListener('click', function() {
      document.getElementById('edit_note_id').value = this.getAttribute('data-id');
      document.getElementById('edit_patient').value = this.getAttribute('data-patient');
      document.getElementById('edit_author').value = this.getAttribute('data-author');
      document.getElementById('edit_date').value = this.getAttribute('data-date');
      document.getElementById('edit_note').value = this.getAttribute('data-note');
      editModal.show();
    });
  });
});
</script>

<?php
// Handle edit note POST request
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_edit'])) {
    $edit_id = intval($_POST['edit_note_id']);
    $edit_author = sanitize_input($conn, $_POST['edit_author'] ?? "");
    $edit_date = $_POST['edit_date'] ?: date("Y-m-d H:i:s");
    $edit_note = sanitize_input($conn, $_POST['edit_note'] ?? "");

    // Validate date format if provided
    if (!empty($_POST['edit_date']) && !preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/', $edit_date)) {
        $error = "Invalid date format. Use YYYY-MM-DD or YYYY-MM-DD HH:MM:SS.";
    } else {
        $stmt = $conn->prepare("UPDATE progress_notes SET author=?, date_written=?, note=? WHERE id=?");
        $stmt->bind_param("sssi", $edit_author, $edit_date, $edit_note, $edit_id);
        if ($stmt->execute()) {
            $msg = "Note updated.";
            // Redirect to avoid form resubmission
            header("Location: progress_notes.php");
            exit;
        } else {
            $error = "Error updating note: " . $stmt->error;
        }
        $stmt->close();
    }
}
?>

<?php include "footer.php"; ?>
