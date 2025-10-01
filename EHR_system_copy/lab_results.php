  
<?php
$page_title = "Lab Results";
$msg = "";
$error = "";
include "db.php";

// Function to sanitize input
function sanitize_input($conn, $data) {
    return mysqli_real_escape_string($conn, trim($data));
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_lab'])) {
    $pid = intval($_POST['patient_id']);
    $test = sanitize_input($conn, $_POST['test_name'] ?? "");
    $result = sanitize_input($conn, $_POST['result'] ?? "");
    $date = $_POST['date'] ?: date("Y-m-d H:i:s");

    // Validate date format if provided
    if (!empty($_POST['date']) && !preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/', $date)) {
        $error = "Invalid date format. Use YYYY-MM-DD or YYYY-MM-DD HH:MM:SS.";
    }
    // Validate common lab test results with appropriate ranges
    elseif (strtolower($test) == "glucose" && is_numeric($result) && ($result < 70 || $result > 200)) {
        $error = "Warning: Glucose value ($result mg/dL) is outside normal range (70-200 mg/dL). Please verify.";
    }
    elseif (strtolower($test) == "hemoglobin" && is_numeric($result) && ($result < 7 || $result > 20)) {
        $error = "Warning: Hemoglobin value ($result g/dL) is outside normal range (7-20 g/dL). Please verify.";
    }
    elseif (strtolower($test) == "cholesterol" && is_numeric($result) && ($result < 100 || $result > 300)) {
        $error = "Warning: Cholesterol value ($result mg/dL) is outside normal range (100-300 mg/dL). Please verify.";
    }
    elseif (strtolower($test) == "wbc" && is_numeric($result) && ($result < 3 || $result > 15)) {
        $error = "Warning: White Blood Cell count ($result K/uL) is outside normal range (3-15 K/uL). Please verify.";
    }
    elseif (strtolower($test) == "platelet" && is_numeric($result) && ($result < 100 || $result > 500)) {
        $error = "Warning: Platelet count ($result K/uL) is outside normal range (100-500 K/uL). Please verify.";
    }
    elseif (strtolower($test) == "creatinine" && is_numeric($result) && ($result < 0.5 || $result > 2.0)) {
        $error = "Warning: Creatinine value ($result mg/dL) is outside normal range (0.5-2.0 mg/dL). Please verify.";
    }
    else {
        $stmt = $conn->prepare("INSERT INTO lab_results (patient_id, test_name, test_result, date_taken) VALUES (?,?,?,?)");
        $stmt->bind_param("isss", $pid, $test, $result, $date);
        if ($stmt->execute()) {
            $msg = "Lab result added.";
        } else {
            $error = "Error: " . $stmt->error;
        }
        $stmt->close();
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_lab'])) {
    $id = intval($_POST['lab_id']);
    $pid = intval($_POST['edit_patient_id']);
    $test = sanitize_input($conn, $_POST['edit_test_name'] ?? "");
    $result = sanitize_input($conn, $_POST['edit_result'] ?? "");
    $date = $_POST['edit_date'] ?: date("Y-m-d H:i:s");

    // Validate date format if provided
    if (!empty($_POST['edit_date']) && !preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/', $date)) {
        $error = "Invalid date format. Use YYYY-MM-DD or YYYY-MM-DD HH:MM:SS.";
    }
    // Validate common lab test results with appropriate ranges
    elseif (strtolower($test) == "glucose" && is_numeric($result) && ($result < 70 || $result > 200)) {
        $error = "Warning: Glucose value ($result mg/dL) is outside normal range (70-200 mg/dL). Please verify.";
    }
    elseif (strtolower($test) == "hemoglobin" && is_numeric($result) && ($result < 7 || $result > 20)) {
        $error = "Warning: Hemoglobin value ($result g/dL) is outside normal range (7-20 g/dL). Please verify.";
    }
    elseif (strtolower($test) == "cholesterol" && is_numeric($result) && ($result < 100 || $result > 300)) {
        $error = "Warning: Cholesterol value ($result mg/dL) is outside normal range (100-300 mg/dL). Please verify.";
    }
    elseif (strtolower($test) == "wbc" && is_numeric($result) && ($result < 3 || $result > 15)) {
        $error = "Warning: White Blood Cell count ($result K/uL) is outside normal range (3-15 K/uL). Please verify.";
    }
    elseif (strtolower($test) == "platelet" && is_numeric($result) && ($result < 100 || $result > 500)) {
        $error = "Warning: Platelet count ($result K/uL) is outside normal range (100-500 K/uL). Please verify.";
    }
    elseif (strtolower($test) == "creatinine" && is_numeric($result) && ($result < 0.5 || $result > 2.0)) {
        $error = "Warning: Creatinine value ($result mg/dL) is outside normal range (0.5-2.0 mg/dL). Please verify.";
    }
    else {
        $stmt = $conn->prepare("UPDATE lab_results SET patient_id=?, test_name=?, test_result=?, date_taken=? WHERE id=?");
        $stmt->bind_param("isssi", $pid, $test, $result, $date, $id);
        if ($stmt->execute()) {
            $msg = "Lab result updated.";
        } else {
            $error = "Error: " . $stmt->error;
        }
        $stmt->close();
    }
}



if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM lab_results WHERE id=?");
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
    .btn-edit, .btn-delete{
      width: 5.75rem;
      display:flex;
      flex-direction:column;
    }

    .action-btn{
      display:flex;
      flex-direction:row;
      gap:1.05rem;
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
    <h4>Lab Results</h4>
    <a class="btn btn-secondary" href="dashboard.php">Back</a>
  </div>

  <div class="row">
    <div class="col-md-6">
      <div class="card p-3">
        <h5>3D Human Anatomy</h5>
        <div id="anatomy-container" style="height: 600px; border: 1px solid #ccc; border-radius: 0.5rem; position: relative;"></div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card p-3 mb-3">
        <form method="post" class="row g-2">
          <div class="col-md-12">
            <label for="patient_id" class="form-label">Select Patient</label>
            <select id="patient_id" name="patient_id" class="form-select" required>
              <option value="">Select patient</option>
              <?php $p = $conn->query("SELECT id,fullname FROM patients ORDER BY fullname"); while($pp=$p->fetch_assoc()): ?>
                <option value="<?php echo $pp['id'];?>"><?php echo htmlspecialchars($pp['fullname']);?></option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label for="test_name" class="form-label">Test Name</label>
            <input id="test_name" class="form-control" name="test_name" placeholder="Test name" required>
          </div>
          <div class="col-md-6">
            <label for="date" class="form-label">Date (optional)</label>
            <input id="date" class="form-control" name="date" placeholder="YYYY-MM-DD">
          </div>
          <div class="col-12">
            <label for="result" class="form-label">Result</label>
            <textarea id="result" class="form-control" name="result" placeholder="Result"></textarea>
          </div>
          <div class="col-12">
            <button name="add_lab" class="btn btn-primary">Add Lab Result</button>
          </div>
        </form>
      </div>

      <div class="card p-3">
        <table class="table table-sm table-bordered table-striped">
          <thead>
            <tr><th>ID</th><th>Patient</th><th>Test</th><th>Result</th><th>Date</th><th>Action</th></tr>
          </thead>
          <tbody>
          <?php
          $sql = "SELECT l.id, l.patient_id, p.fullname, l.test_name, l.test_result, l.date_taken FROM lab_results l JOIN patients p ON l.patient_id=p.id ORDER BY l.date_taken DESC";
          $res = $conn->query($sql);
          while ($r = $res->fetch_assoc()): ?>
            <tr>
              <td><?php echo $r['id'];?></td>
              <td><?php echo htmlspecialchars($r['fullname']);?></td>
              <td><?php echo htmlspecialchars($r['test_name']);?></td>
              <td><?php echo htmlspecialchars($r['test_result']);?></td>
              <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($r['date_taken'])));?></td>
              <td class="action-btn">
                <a class="btn btn-sm btn-danger btn-delete" href="lab_results.php?delete=<?php echo $r['id'];?>" onclick="return confirm('Delete?')">
                  <i class="bi bi-trash"></i>
                  Delete
                </a>
                <a class="btn btn-sm btn-warning btn-edit" href="#" data-bs-toggle="modal" data-bs-target="#editModal" data-id="<?php echo $r['id']; ?>" data-patient-id="<?php echo $r['patient_id']; ?>" data-patient-name="<?php echo htmlspecialchars($r['fullname']); ?>" data-test-name="<?php echo htmlspecialchars($r['test_name']); ?>" data-result="<?php echo htmlspecialchars($r['test_result']); ?>" data-date="<?php echo htmlspecialchars($r['date_taken']); ?>">
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
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editModalLabel">Edit Lab Result</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="lab_id" id="edit_lab_id">
        <div class="mb-3">
          <label for="edit_patient_id" class="form-label">Select Patient</label>
          <select id="edit_patient_id" name="edit_patient_id" class="form-select" required>
            <option value="">Select patient</option>
            <?php
            $p = $conn->query("SELECT id,fullname FROM patients ORDER BY fullname");
            while($pp=$p->fetch_assoc()):
            ?>
              <option value="<?php echo $pp['id'];?>"><?php echo htmlspecialchars($pp['fullname']);?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="mb-3">
          <label for="edit_test_name" class="form-label">Test Name</label>
          <input id="edit_test_name" class="form-control" name="edit_test_name" placeholder="Test name" required>
        </div>
        <div class="mb-3">
          <label for="edit_date" class="form-label">Date (optional)</label>
          <input id="edit_date" class="form-control" name="edit_date" placeholder="YYYY-MM-DD">
        </div>
        <div class="mb-3">
          <label for="edit_result" class="form-label">Result</label>
          <textarea id="edit_result" class="form-control" name="edit_result" placeholder="Result"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" name="update_lab" class="btn btn-primary">Save changes</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
  var editModal = document.getElementById('editModal');
  editModal.addEventListener('show.bs.modal', function (event) {
    var button = event.relatedTarget;
    var id = button.getAttribute('data-id');
    var patientId = button.getAttribute('data-patient-id');
    var testName = button.getAttribute('data-test-name');
    var result = button.getAttribute('data-result');
    var date = button.getAttribute('data-date');

    // Set modal fields
    document.getElementById('edit_lab_id').value = id;
    document.getElementById('edit_patient_id').value = patientId;
    document.getElementById('edit_test_name').value = testName;
    document.getElementById('edit_result').value = result;
    document.getElementById('edit_date').value = date;
  });
</script>



<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/controls/OrbitControls.js"></script>
<script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/renderers/CSS2DRenderer.js"></script>
<script>
  // Initialize 3D scene for detailed muscular human anatomy
  const container = document.getElementById('anatomy-container');
  const scene = new THREE.Scene();
  scene.background = new THREE.Color(0xf0f0f0);
  const camera = new THREE.PerspectiveCamera(75, container.clientWidth / container.clientHeight, 0.1, 1000);
  const renderer = new THREE.WebGLRenderer({ antialias: true });
  renderer.setSize(container.clientWidth, container.clientHeight);
  renderer.shadowMap.enabled = true;
  renderer.shadowMap.type = THREE.PCFSoftShadowMap;
  container.appendChild(renderer.domElement);

  // Lighting for realism
  const ambientLight = new THREE.AmbientLight(0x404040, 0.6);
  scene.add(ambientLight);
  const directionalLight = new THREE.DirectionalLight(0xffffff, 0.8);
  directionalLight.position.set(5, 10, 5);
  directionalLight.castShadow = true;
  directionalLight.shadow.mapSize.width = 2048;
  directionalLight.shadow.mapSize.height = 2048;
  scene.add(directionalLight);

  // CSS2D Renderer for labels
  const labelRenderer = new THREE.CSS2DRenderer();
  labelRenderer.setSize(container.clientWidth, container.clientHeight);
  labelRenderer.domElement.style.position = 'absolute';
  labelRenderer.domElement.style.top = '0';
  labelRenderer.domElement.style.pointerEvents = 'none';
  container.appendChild(labelRenderer.domElement);

  // Add controls
  const controls = new THREE.OrbitControls(camera, renderer.domElement);
  controls.enableDamping = true;
  controls.dampingFactor = 0.05;

  // Raycaster for interactivity
  const raycaster = new THREE.Raycaster();
  const mouse = new THREE.Vector2();
  let INTERSECTED;
  let hoveredLabels = [];

  // Function to create labeled mesh
  function createLabeledMesh(geometry, material, labelText, position) {
    const mesh = new THREE.Mesh(geometry, material);
    mesh.position.copy(position);
    mesh.userData = { label: labelText, originalColor: material.color.getHex() };
    mesh.castShadow = true;
    mesh.receiveShadow = true;
    scene.add(mesh);

    // Create hidden label initially
    const labelDiv = document.createElement('div');
    labelDiv.className = 'label';
    labelDiv.textContent = labelText;
    labelDiv.style.color = 'white';
    labelDiv.style.backgroundColor = 'rgba(0,0,0,0.7)';
    labelDiv.style.padding = '4px 8px';
    labelDiv.style.borderRadius = '5px';
    labelDiv.style.fontSize = '14px';
    labelDiv.style.fontWeight = 'bold';
    labelDiv.style.pointerEvents = 'none';
    labelDiv.style.display = 'none'; // Hidden by default
    const label = new THREE.CSS2DObject(labelDiv);
    label.position.copy(position);
    label.position.y += 0.3; // Offset label above part
    mesh.add(label);
    mesh.userData.labelObject = label;

    return mesh;
  }

  // Muscular human anatomy parts
  // Head (skin)
  const headGeometry = new THREE.SphereGeometry(0.3, 32, 32);
  const headMaterial = new THREE.MeshPhongMaterial({ color: 0xffdbac, shininess: 100 });
  const head = createLabeledMesh(headGeometry, headMaterial, 'Head (Skin)', new THREE.Vector3(0, 1.8, 0));

  // Neck muscles
  const neckGeometry = new THREE.CylinderGeometry(0.1, 0.1, 0.3, 16);
  const neckMaterial = new THREE.MeshPhongMaterial({ color: 0xCD853F, shininess: 30 });
  const neck = createLabeledMesh(neckGeometry, neckMaterial, 'Neck Muscles', new THREE.Vector3(0, 1.5, 0));

  // Torso (chest and abs)
  const torsoGeometry = new THREE.CylinderGeometry(0.4, 0.5, 1.2, 32);
  const torsoMaterial = new THREE.MeshPhongMaterial({ color: 0xCD853F, shininess: 30 });
  const torso = createLabeledMesh(torsoGeometry, torsoMaterial, 'Torso (Chest & Abs)', new THREE.Vector3(0, 0.6, 0));

  // Pectoralis Major (chest muscles)
  const pecGeometry = new THREE.BoxGeometry(0.6, 0.2, 0.1);
  const pecMaterial = new THREE.MeshPhongMaterial({ color: 0x8B4513, shininess: 50 });
  const leftPec = createLabeledMesh(pecGeometry, pecMaterial, 'Pectoralis Major (Left)', new THREE.Vector3(-0.3, 0.9, 0.2));
  const rightPec = createLabeledMesh(pecGeometry, pecMaterial, 'Pectoralis Major (Right)', new THREE.Vector3(0.3, 0.9, 0.2));

  // Abdominal muscles (rectus abdominis)
  const absGeometry = new THREE.BoxGeometry(0.3, 0.8, 0.05);
  const absMaterial = new THREE.MeshPhongMaterial({ color: 0x8B4513, shininess: 50 });
  const abs = createLabeledMesh(absGeometry, absMaterial, 'Rectus Abdominis', new THREE.Vector3(0, 0.4, 0.25));

  // Left Arm: Biceps Brachii
  const bicepGeometry = new THREE.CylinderGeometry(0.12, 0.15, 0.6, 32);
  const bicepMaterial = new THREE.MeshPhongMaterial({ color: 0xffdbac, shininess: 50 });
  const leftBicep = createLabeledMesh(bicepGeometry, bicepMaterial, 'Biceps Brachii (Left)', new THREE.Vector3(-0.5, 1.0, 0));
  leftBicep.rotation.z = Math.PI / 2;

  // Triceps Brachii (back of arm)
  const tricepGeometry = new THREE.CylinderGeometry(0.1, 0.12, 0.6, 32);
  const tricepMaterial = new THREE.MeshPhongMaterial({ color: 0xffdbac, shininess: 50 });
  const leftTricep = createLabeledMesh(tricepGeometry, tricepMaterial, 'Triceps Brachii (Left)', new THREE.Vector3(-0.5, 1.0, -0.1));
  leftTricep.rotation.z = Math.PI / 2;

  // Forearm (flexor muscles)
  const forearmGeometry = new THREE.CylinderGeometry(0.08, 0.1, 0.5, 32);
  const forearmMaterial = new THREE.MeshPhongMaterial({ color: 0xffdbac, shininess: 50 });
  const leftForearm = createLabeledMesh(forearmGeometry, forearmMaterial, 'Forearm Flexors (Left)', new THREE.Vector3(-0.8, 0.7, 0));
  leftForearm.rotation.z = Math.PI / 2;

  // Right Arm similar
  const rightBicep = createLabeledMesh(bicepGeometry, bicepMaterial, 'Biceps Brachii (Right)', new THREE.Vector3(0.5, 1.0, 0));
  rightBicep.rotation.z = -Math.PI / 2;

  const rightTricep = createLabeledMesh(tricepGeometry, tricepMaterial, 'Triceps Brachii (Right)', new THREE.Vector3(0.5, 1.0, -0.1));
  rightTricep.rotation.z = -Math.PI / 2;

  const rightForearm = createLabeledMesh(forearmGeometry, forearmMaterial, 'Forearm Flexors (Right)', new THREE.Vector3(0.8, 0.7, 0));
  rightForearm.rotation.z = -Math.PI / 2;

  // Legs: Quadriceps (front thigh)
  const quadGeometry = new THREE.CylinderGeometry(0.18, 0.2, 0.8, 32);
  const quadMaterial = new THREE.MeshPhongMaterial({ color: 0x8B4513, shininess: 30 });
  const leftQuad = createLabeledMesh(quadGeometry, quadMaterial, 'Quadriceps (Left)', new THREE.Vector3(-0.25, -0.2, 0.1));

  // Hamstrings (back thigh)
  const hamGeometry = new THREE.CylinderGeometry(0.16, 0.18, 0.8, 32);
  const hamMaterial = new THREE.MeshPhongMaterial({ color: 0x8B4513, shininess: 30 });
  const leftHam = createLabeledMesh(hamGeometry, hamMaterial, 'Hamstrings (Left)', new THREE.Vector3(-0.25, -0.2, -0.1));

  // Calf muscles (gastrocnemius)
  const calfGeometry = new THREE.CylinderGeometry(0.12, 0.15, 0.6, 32);
  const calfMaterial = new THREE.MeshPhongMaterial({ color: 0x8B4513, shininess: 30 });
  const leftCalf = createLabeledMesh(calfGeometry, calfMaterial, 'Gastrocnemius (Left)', new THREE.Vector3(-0.25, -1.0, 0));

  // Right Leg similar
  const rightQuad = createLabeledMesh(quadGeometry, quadMaterial, 'Quadriceps (Right)', new THREE.Vector3(0.25, -0.2, 0.1));
  const rightHam = createLabeledMesh(hamGeometry, hamMaterial, 'Hamstrings (Right)', new THREE.Vector3(0.25, -0.2, -0.1));
  const rightCalf = createLabeledMesh(calfGeometry, calfMaterial, 'Gastrocnemius (Right)', new THREE.Vector3(0.25, -1.0, 0));

  // Deltoids (shoulders)
  const deltGeometry = new THREE.SphereGeometry(0.15, 16, 16);
  const deltMaterial = new THREE.MeshPhongMaterial({ color: 0xCD853F, shininess: 50 });
  const leftDelt = createLabeledMesh(deltGeometry, deltMaterial, 'Deltoid (Left)', new THREE.Vector3(-0.6, 1.3, 0));
  const rightDelt = createLabeledMesh(deltGeometry, deltMaterial, 'Deltoid (Right)', new THREE.Vector3(0.6, 1.3, 0));

  // Latissimus Dorsi (back muscles)
  const latGeometry = new THREE.BoxGeometry(0.8, 0.6, 0.05);
  const latMaterial = new THREE.MeshPhongMaterial({ color: 0x8B4513, shininess: 50 });
  const lats = createLabeledMesh(latGeometry, latMaterial, 'Latissimus Dorsi', new THREE.Vector3(0, 0.8, -0.25));

  camera.position.set(0, 0, 5);

  // Enhanced mouse interaction for hover (show labels and highlight)
  function onMouseMove(event) {
    const rect = container.getBoundingClientRect();
    mouse.x = ((event.clientX - rect.left) / rect.width) * 2 - 1;
    mouse.y = -((event.clientY - rect.top) / rect.height) * 2 + 1;

    raycaster.setFromCamera(mouse, camera);
    const intersects = raycaster.intersectObjects(scene.children, true);

    // Hide previous labels
    if (hoveredLabels.length > 0) {
      hoveredLabels.forEach(obj => {
        if (obj.userData.labelObject) obj.userData.labelObject.element.style.display = 'none';
      });
      hoveredLabels = [];
    }

    if (intersects.length > 0) {
      const intersectedObject = intersects[0].object;
      if (INTERSECTED !== intersectedObject) {
        if (INTERSECTED && INTERSECTED.material) {
          INTERSECTED.material.emissive.setHex(INTERSECTED.userData.originalEmissive || 0x000000);
        }
        INTERSECTED = intersectedObject;
        if (INTERSECTED.material) {
          INTERSECTED.userData.originalEmissive = INTERSECTED.material.emissive.getHex();
          INTERSECTED.material.emissive.setHex(0x222222);
        }
      }

      // Show label for intersected part
      if (INTERSECTED.userData.labelObject) {
        INTERSECTED.userData.labelObject.element.style.display = 'block';
        hoveredLabels.push(INTERSECTED);
      }
    } else {
      if (INTERSECTED && INTERSECTED.material) {
        INTERSECTED.material.emissive.setHex(INTERSECTED.userData.originalEmissive || 0x000000);
      }
      INTERSECTED = null;
    }
  }

  container.addEventListener('mousemove', onMouseMove, false);

  // Animation loop
  function animate() {
    requestAnimationFrame(animate);
    controls.update();
    renderer.render(scene, camera);
    labelRenderer.render(scene, camera);
  }
  animate();

  // Responsive
  window.addEventListener('resize', function() {
    const width = container.clientWidth;
    const height = container.clientHeight;
    camera.aspect = width / height;
    camera.updateProjectionMatrix();
    renderer.setSize(width, height);
    labelRenderer.setSize(width, height);
  });


</script>

<?php include "footer.php"; ?>
