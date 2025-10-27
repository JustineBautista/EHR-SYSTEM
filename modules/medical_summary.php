<?php
echo '<link rel="icon" href="IMAGES/aurora.png" type="image/png">';
?>

<?php
// Start session and check authentication first
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin'])) {
    header("Location: index.php");
    exit();
}

// Include required files AFTER session check
include "db.php";
include "audit_trail.php";

$page_title = "Medical Summary";
$msg = "";

// CSRF token for security
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include "header.php";
?>

<style>
    body {
        background: linear-gradient(135deg, #f5f7fa 0%, #e2e8f0 100%);
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        padding-top: 5rem;
        color: #374151;
        min-height: 100vh;
    }

    .summary-header {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        padding: 2.85rem 0;
        margin-top:1.25rem;
        margin-bottom: 2rem;
        border-radius: 12px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    }

    .summary-title {
        font-size: 2.25rem;
        font-weight: 800;
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        margin: 0 0 0 4rem;
        letter-spacing: -0.025em;
    }

    .summary-subtitle {
        color: #6b7280;
        font-size: 1rem;
        margin: 0.5rem 0 0 4rem;
        font-weight: 500;
    }

    .patient-card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        border-radius: 16px;
        border: 1px solid rgba(255, 255, 255, 0.2);
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        margin-bottom: 1.5rem;
        overflow: hidden;
    }

    .patient-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 25px rgba(0, 0, 0, 0.1);
        border-color: rgba(16, 185, 129, 0.3);
    }

    .patient-header {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
        padding: 1.5rem;
        position: relative;
        overflow: hidden;
    }

    .patient-header::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
        transition: left 0.5s;
    }

    .patient-card:hover .patient-header::before {
        left: 100%;
    }

    .patient-name {
        font-size: 1.25rem;
        font-weight: 700;
        margin: 0;
        position: relative;
        z-index: 1;
    }

    .patient-id {
        font-size: 0.875rem;
        opacity: 0.9;
        margin: 0.25rem 0 0 0;
        position: relative;
        z-index: 1;
    }

    .patient-body {
        padding: 1.5rem;
    }

    .patient-info {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .info-item {
        background: rgba(16, 185, 129, 0.05);
        padding: 0.75rem;
        border-radius: 8px;
        border-left: 3px solid #10b981;
    }

    .info-label {
        font-size: 0.75rem;
        font-weight: 600;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 0.25rem;
    }

    .info-value {
        font-weight: 500;
        color: #374151;
        margin: 0;
    }

    .records-section {
        margin-top: 1.5rem;
    }

    .records-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1rem;
    }

    .records-title {
        font-size: 1rem;
        font-weight: 600;
        color:rgb(80, 79, 79);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .record-count {
        background: #10b981;
        color: white;
        padding: 0.25rem 0.5rem;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .records-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1rem;
    }

    .record-card {
        background: rgba(249, 250, 251, 0.8);
        border: 1px solid rgba(229, 231, 235, 0.5);
        border-radius: 8px;
        padding: 1rem;
        transition: all 0.2s ease;
    }

    .record-card:hover {
        background: rgba(255, 255, 255, 0.9);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }

    .record-type {
        font-size: 0.875rem;
        font-weight: 600;
        color: #10b981;
        margin-bottom: 0.5rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .record-details {
        font-size: 0.875rem;
        color:rgb(40, 42, 48);
        line-height: 0.4;
    }

    .record-detail {
        margin-bottom: 1.75rem;
    }

    .no-records {
        text-align: center;
        color: #9ca3af;
        font-style: italic;
        padding: 2rem;
        background: rgba(249, 250, 251, 0.5);
        border-radius: 8px;
        margin: 1rem 0;
    }

    .expand-btn {
        background: none;
        border: 1px solid #10b981;
        color: #10b981;
        padding: 0.5rem 1rem;
        border-radius: 6px;
        font-size: 0.875rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .expand-btn:hover {
        background: #10b981;
        color: white;
    }

    .records-expanded .records-grid {
        grid-template-columns: 1fr;
    }

    .records-expanded .record-card {
        max-height: none;
    }

    .search-container {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        padding: 1.5rem;
        border-radius: 12px;
        margin-bottom: 2rem;
        border: 1px solid rgba(255, 255, 255, 0.2);
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    }

    .search-input {
        border: 2px solid #e5e7eb;
        border-radius: 8px;
        padding: 0.75rem 1rem;
        font-size: 1rem;
        transition: all 0.3s ease;
        width: 100%;
        max-width: 400px;
    }

    .search-input:focus {
        outline: none;
        border-color: #10b981;
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
    }

    .stats-overview {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
    }

    .stat-item {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        padding: 1rem;
        border-radius: 12px;
        text-align: center;
        border: 1px solid rgba(255, 255, 255, 0.2);
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    }

    .stat-number {
        font-size: 1.5rem;
        font-weight: 700;
        color: #10b981;
        margin-bottom: 0.25rem;
    }

    .stat-label {
        font-size: 0.875rem;
        color: #6b7280;
        font-weight: 500;
        margin: 0;
    }

    @media (max-width: 768px) {
        .summary-title {
            font-size: 1.875rem;
        }

        .patient-info {
            grid-template-columns: 1fr;
        }

        .records-grid {
            grid-template-columns: 1fr;
        }

        .stats-overview {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    .fade-in {
        animation: fadeIn 0.6s ease-out;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .loading {
        text-align: center;
        padding: 2rem;
        color: #6b7280;
    }
</style>

<div class="container-fluid px-4 py-4">
    <!-- Header -->
    <div class="row mb-4 sum-header">
        <div class="col-12">
            <div class="summary-header">
                <h1 class="summary-title">Medical Summary</h1>
                <p class="summary-subtitle">Comprehensive overview of all patient medical records</p>
            </div>
        </div>
    </div>

    <!-- Search and Stats -->
    <div class="search-container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <input type="text" id="patientSearch" class="search-input" placeholder="Search patients by name or ID...">
            </div>
            <div class="col-md-4">
                <div class="stats-overview">
                    <?php
                    $total_patients = $conn->query("SELECT COUNT(*) as count FROM patients")->fetch_assoc()['count'];
                    $total_records = 0;
                    $tables = ['medical_history', 'medications', 'vitals', 'diagnostics', 'treatment_plans', 'lab_results', 'progress_notes'];
                    foreach ($tables as $table) {
                        $count = $conn->query("SELECT COUNT(*) as count FROM `$table`")->fetch_assoc()['count'];
                        $total_records += $count;
                    }
                    ?>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $total_patients; ?></div>
                        <p class="stat-label">Total Patients</p>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $total_records; ?></div>
                        <p class="stat-label">Medical Records</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Patients List -->
    <div id="patientsContainer">
        <div class="loading">
            <i class="bi bi-arrow-repeat fs-1 text-muted"></i>
            <p>Loading patient summaries...</p>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('patientSearch');
    const patientsContainer = document.getElementById('patientsContainer');

    // Load patients data
    loadPatients();

    async function loadPatients() {
        try {
            const response = await fetch('get_patient_summaries.php');
            const patients = await response.json();
            renderPatients(patients);
        } catch (error) {
            console.error('Error loading patients:', error);
            patientsContainer.innerHTML = '<div class="alert alert-danger">Error loading patient data. Please try again.</div>';
        }
    }

    function renderPatients(patients) {
        if (patients.length === 0) {
            patientsContainer.innerHTML = '<div class="no-records">No patients found.</div>';
            return;
        }

        const html = patients.map(patient => `
            <div class="patient-card fade-in" data-patient-id="${patient.id}" data-patient-name="${patient.fullname.toLowerCase()}">
                <div class="patient-header">
                    <h3 class="patient-name">${patient.fullname}</h3>
                    <p class="patient-id">Patient ID: ${patient.id}</p>
                </div>
                <div class="patient-body">
                    <div class="patient-info">
                        <div class="info-item">
                            <div class="info-label">Date of Birth</div>
                            <p class="info-value">${patient.dob || 'N/A'}</p>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Age</div>
                            <p class="info-value">${patient.age || 'N/A'}</p>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Gender</div>
                            <p class="info-value">${patient.gender || 'N/A'}</p>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Contact</div>
                            <p class="info-value">${patient.contact || 'N/A'}</p>
                        </div>
                    </div>

                    <div class="records-section">
                        <div class="records-header">
                            <h4 class="records-title">
                                <i class="bi bi-clipboard-data"></i>
                                Medical Records
                            </h4>
                            <button class="expand-btn" onclick="toggleRecords(this)">
                                <i class="bi bi-chevron-down"></i>
                                <span>Expand</span>
                            </button>
                        </div>
                        <div class="records-grid">
                            ${renderMedicalRecords(patient.medical_records)}
                        </div>
                    </div>
                </div>
            </div>
        `).join('');

        patientsContainer.innerHTML = html;
    }

    function renderMedicalRecords(records) {
        const recordTypes = [
            { key: 'physical_assessments', title: 'Physical Assessments', icon: 'bi-journal-medical', fields: ['assessed_by', 'head_and_neck', 'cardiovascular', 'respiratory', 'Abdominal', 'neurological', 'musculoskeletal', 'skin', 'psychiatric', 'date_assessed'] },
            { key: 'medical_history', title: 'Medical History', icon: 'bi-journal-medical', fields: ['condition_name', 'status', 'date_recorded'] },
            { key: 'medications', title: 'Medications', icon: 'bi-capsule', fields: ['prescriber','medication','dose','start_date','notes','route','indication','status','patient_instructions','pharmacy_instructions'] },
            { key: 'vitals', title: 'Vital Signs', icon: 'bi-heart-pulse', fields: ['recorded_by', 'bp', 'respiratory_rate', 'hr', 'temp', 'height', 'weight', 'oxygen_saturation', 'pain_scale', 'date_taken', 'general_appearance'] },
            { key: 'diagnostics', title: 'Diagnostics / Imaging', icon: 'bi-search', fields: ['study_type', 'body_part_region', 'study_description', 'clinical_indication', 'image_quality', 'order_by', 'performed_by', 'interpreted_by', 'imaging_facility', 'radiology_findings', 'impression_conclusion', 'recommendations', 'date_diagnosed'] },
            { key: 'treatment_plans', title: 'Treatment Plans', icon: 'bi-clipboard-check', fields: ['plan', 'intervention', 'problems', 'frequency', 'duration', 'order_by', 'assigned_to', 'date_started', 'date_ended', 'special_instructions', 'patient_education_provided'] },
            { key: 'lab_results', title: 'Lab Results', icon: 'bi-test-tube', fields: ['test_name', 'test_result', 'test_category', 'test_code', 'result_status', 'units', 'reference_range', 'order_by', 'collected_by','date_taken'] },
            { key: 'progress_notes', title: 'Progress Notes', icon: 'bi-sticky', fields: ['focus', 'author', 'note', 'date_written'] }
        ];

        return recordTypes.map(type => {
            const typeRecords = records[type.key] || [];

            // Filter out "assessed_by" ONLY for Physical Assessments
            const filteredFields = 
                type.key === 'physical_assessments'
                    ? type.fields.filter(field => field.toLowerCase() !== 'assessed_by')
                    : type.fields;

            return `
                <div class="record-card">
                    <div class="record-type">
                        <i class="bi ${type.icon} me-1"></i>
                        ${type.title}
                        <span class="record-count">${typeRecords.length}</span>
                    </div>
                    <div class="record-details">
                        ${
                            typeRecords.length > 0
                                ? typeRecords.slice(0, 2).map(record =>
                                    filteredFields.map(field => {
                                        if (!record[field]) return '';

                                        // Sanitize and preserve formatting (D-A-R, bullets, etc.)
                                        const safeText = record[field]
                                            .replace(/&/g, "&amp;")
                                            .replace(/</g, "&lt;")
                                            .replace(/>/g, "&gt;")
                                            .replace(/\n/g, "<br>")
                                            .replace(/\t/g, "&emsp;")
                                            .replace(/•/g, "•");

                                        return `
                                            <div class="record-detail">
                                                <strong>${field.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}:</strong>
                                                <div style="
                                                    white-space: pre-wrap;
                                                    line-height: 1.0;
                                                    font-family: 'Segoe UI', Arial, sans-serif;
                                                    font-size: 0.95rem;
                                                    margin-top: 0.1rem;
                                                    margin-right:3rem;
                                                ">
                                                    ${safeText}
                                                </div>
                                            </div>
                                        `;
                                    }).join('')
                                ).join('<hr style="margin: 0.5rem 0; border-color: #e5e7eb;">')
                                : '<em class="text-muted">No records</em>'
                        }
                        ${
                            typeRecords.length > 2
                                ? `<small class="text-muted">... and ${typeRecords.length - 2} more</small>`
                                : ''
                        }
                    </div>
                </div>
            `;
        }).join('');
    }

    // Search functionality
    searchInput.addEventListener('input', function() {
        const filter = this.value.toLowerCase();
        const cards = document.querySelectorAll('.patient-card');

        cards.forEach(card => {
            const name = card.dataset.patientName;
            const id = card.dataset.patientId;
            const show = name.includes(filter) || id.includes(filter);
            card.style.display = show ? '' : 'none';
        });
    });
});

function toggleRecords(btn) {
    const card = btn.closest('.patient-card');
    const icon = btn.querySelector('i');
    const span = btn.querySelector('span');

    card.classList.toggle('records-expanded');

    if (card.classList.contains('records-expanded')) {
        icon.className = 'bi bi-chevron-up';
        span.textContent = 'Collapse';
    } else {
        icon.className = 'bi bi-chevron-down';
        span.textContent = 'Expand';
    }
}
</script>


<?php include "footer.php"; ?>
