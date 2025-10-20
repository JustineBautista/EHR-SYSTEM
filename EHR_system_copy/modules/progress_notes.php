<?php
// Start session and check authentication first
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin'])) {
    header("Location: ../index.php");
    exit();
}

// Include required files AFTER session check
include "../db.php";
include "../audit_trail.php";

// Function to sanitize input
function sanitize_input($conn, $data) {
    return mysqli_real_escape_string($conn, trim($data));
}

$page_title = "Progress Notes";

// Get patient ID from URL
$patient_id = intval($_GET['patient_id'] ?? 0);
if ($patient_id <= 0) {
    header("Location: ../patients.php");
    exit();
}

// Fetch patient details
$patient = null;
$stmt = $conn->prepare("SELECT * FROM patients WHERE id = ?");
if ($stmt && $stmt->bind_param("i", $patient_id) && $stmt->execute()) {
    $res = $stmt->get_result();
    $patient = $res->fetch_assoc();
    $stmt->close();
}

if (!$patient) {
    header("Location: ../patients.php");
    exit();
}

// Progress Notes processing
$msg = "";
$error = "";

if (isset($_POST['add_note'])) {
    $focus = sanitize_input($conn, $_POST['focus'] ?? "");
    $note = sanitize_input($conn, $_POST['note'] ?? "");
    $author = sanitize_input($conn, $_POST['author'] ?? "");
    $date = $_POST['date'] ?: date("Y-m-d");

    // Validate date format if provided
    if (!empty($_POST['date']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $error = "Date must be in format YYYY-MM-DD.";
    } else {
        $stmt = $conn->prepare("INSERT INTO progress_notes (patient_id, focus, note, author, date_written) VALUES (?,?,?,?,?)");
        $stmt->bind_param("issss", $patient_id, $focus, $note, $author, $date);
        if ($stmt->execute()) {
            $msg = "Note added.";
        } else {
            $error = "Error: " . $stmt->error;
        }
    }
}

if (isset($_GET['delete_note'])) {
    $id = intval($_GET['delete_note']);
    $stmt = $conn->prepare("DELETE FROM progress_notes WHERE id=? AND patient_id=?");
    $stmt->bind_param("ii", $id, $patient_id);
    if ($stmt->execute()) {
        header("Location: progress_notes.php?patient_id=$patient_id");
        exit();
    }
    $stmt->close();
}

// Handle update progress notes
if (isset($_POST['update_note'])) {
    $nid = intval($_POST['note_id']);
    $focus = sanitize_input($conn, $_POST['focus'] ?? "");
    $note = sanitize_input($conn, $_POST['note'] ?? "");
    $author = sanitize_input($conn, $_POST['author'] ?? "");
    $date = $_POST['date'] ?: date("Y-m-d");
    if (!empty($_POST['date'])) {
        $date = str_replace('T', ' ', $date);
    }

    // Validate date format if provided
    if (!empty($_POST['date']) && !preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2})?$/', $date)) {
        $error = "Invalid date format. Use YYYY-MM-DDTHH:MM.";
    } else {
        $stmt = $conn->prepare("UPDATE progress_notes SET focus=?, note=?, author=?, date_written=? WHERE id=? AND patient_id=?");
        $stmt->bind_param("sssii", $focus, $note, $author, $date, $nid, $patient_id);
        if ($stmt->execute()) {
            $msg = "Note updated.";
        } else {
            $error = "Error updating note: " . $stmt->error;
        }
        $stmt->close();
    }
}

if (isset($_GET['get_note'])) {
    $id = intval($_GET['get_note']);
    $stmt = $conn->prepare("SELECT * FROM progress_notes WHERE id=? AND patient_id=?");
    $stmt->bind_param("ii", $id, $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        echo json_encode($row);
    }
    $stmt->close();
    exit;
}

include "../header.php";
?>

<style>
    body {
      background-color: #ffffff;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      padding-top: 5rem;
    }

    .btn-primary {
        background-color: var(--primary-color);
        border-color: var(--primary-color);
    }
    .btn-primary:hover {
        background-color: var(--accent-color);
        border-color: var(--accent-color);
    }

    .alert {
        border-radius: 0.5rem;
        border: none;
    }

    .card {
        border-radius: 0.75rem;
    }

    .table-responsive {
        border-radius: 0.5rem;
        overflow: hidden;
    }
</style>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-pencil-square me-2"></i>Progress Notes - <?php echo htmlspecialchars($patient['fullname']); ?></h5>
                    <a href="../patient_dashboard.php?patient_id=<?php echo $patient_id; ?>" class="btn btn-secondary">Back to Dashboard</a>
                </div>
                <div class="card-body">
                    <!-- Feedback messages -->
                    <?php if (!empty($msg)): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <?php echo htmlspecialchars($msg); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <?php echo htmlspecialchars($error); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Progress Notes Form -->
                    <div class="card p-3 mb-3">
                        <form method="post" class="row g-2">
                            <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
                            <div class="col-md-12"><input class="form-control" name="focus" placeholder="Focus" value="<?php echo htmlspecialchars($_POST['focus'] ?? ''); ?>" required></div>
                            <div class="col-md-6"><textarea class="form-control" name="note" placeholder="Progress note" rows="3" required><?php echo htmlspecialchars($_POST['note'] ?? ''); ?></textarea></div>
                            <div class="col-md-3"><input class="form-control" name="author" placeholder="Author" value="<?php echo htmlspecialchars($_POST['author'] ?? ''); ?>" required></div>
                            <div class="col-md-3"><input type="date" class="form-control" name="date" value="<?php echo htmlspecialchars($_POST['date'] ?? date('Y-m-d')); ?>" required></div>
                            <div class="col-12"><button name="add_note" class="btn btn-primary">Add Note</button></div>
                        </form>
                    </div>

                    <!-- Progress Notes Table -->
                    <div class="card p-3">
                        <table class="table table-sm table-bordered">
                            <thead>
                                <tr>
                                    <th>Focus</th>
                                    <th>Note</th>
                                    <th>Author</th>
                                    <th>Date Written</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $stmt = $conn->prepare("SELECT * FROM progress_notes WHERE patient_id = ? ORDER BY id DESC");
                                $stmt->bind_param("i", $patient_id);
                                $stmt->execute();
                                $result = $stmt->get_result();
                                while ($r = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($r['focus'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($r['note'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($r['author'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($r['date_written'] ?? ''))); ?></td>
                                        <td>
                                            <a class="btn btn-sm btn-danger" href="?delete_note=<?php echo $r['id']; ?>&patient_id=<?php echo $patient_id; ?>" onclick="return confirm('Delete?')">
                                                <i class="bi bi-trash"></i> Delete
                                            </a>
                                            <a class="btn btn-sm btn-warning" href="javascript:void(0)" onclick="editNote(<?php echo $r['id']; ?>)">
                                                <i class="bi bi-pencil"></i> Edit
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Modal for Progress Notes -->
<div class="modal fade" id="editNoteModal" tabindex="-1" aria-labelledby="editNoteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" id="editNoteForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="editNoteModalLabel">Edit Progress Note</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="note_id" id="note_id">
                    <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
                    <div class="mb-3">
                        <label for="note_edit" class="form-label">Note</label>
                        <textarea class="form-control" name="note" id="note_edit" rows="4" placeholder="Progress note" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="author_edit" class="form-label">Author</label>
                        <input type="text" class="form-control" name="author" id="author_edit" placeholder="Author" required>
                    </div>
                    <div class="mb-3">
                        <label for="date_note_edit" class="form-label">Date Written</label>
                        <input type="date" class="form-control" name="date" id="date_note_edit" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="update_note" class="btn btn-primary">Save changes</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editNote(id) {
    fetch('?get_note=' + id)
        .then(response => response.json())
        .then(data => {
            document.getElementById('note_id').value = data.id;
            document.getElementById('note_edit').value = data.note;
            document.getElementById('author_edit').value = data.author;
            document.getElementById('date_note_edit').value = data.date_written.replace(' ', 'T');
            new bootstrap.Modal(document.getElementById('editNoteModal')).show();
        })
        .catch(error => console.error('Error:', error));
}
</script>

<?php include "../footer.php"; ?>
