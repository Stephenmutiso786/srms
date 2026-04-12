<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/school.php');
require_once('const/rbac.php');
require_once('const/certificate_engine.php');

if ($res !== '1' || $level !== '0') { header('location:../'); exit; }
app_require_permission('report.generate', 'admin');

$students = [];
$certificates = [];
$types = app_certificate_types();

try {
    $conn = app_db();
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    app_ensure_certificates_table($conn);

    // Get students for certificate generation
    $stmt = $conn->prepare('SELECT st.id, st.school_id, st.class, concat_ws(\' \' , st.fname, st.mname, st.lname) AS student_name, c.name AS class_name
        FROM tbl_students st
        LEFT JOIN tbl_classes c ON c.id = st.class
        ORDER BY st.fname, st.lname
        LIMIT 500');
    $stmt->execute();
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get issued certificates with all new fields
    $stmt = $conn->prepare('SELECT cert.id, cert.certificate_type, cert.certificate_category, cert.title, cert.serial_no, 
            cert.issue_date, cert.status, cert.verification_code, cert.mean_score, cert.merit_grade, cert.locked,
            concat_ws(\' \' , st.fname, st.mname, st.lname) AS student_name, st.school_id, c.name AS class_name
        FROM tbl_certificates cert
        JOIN tbl_students st ON st.id = cert.student_id
        LEFT JOIN tbl_classes c ON c.id = cert.class_id
        ORDER BY cert.id DESC
        LIMIT 300');
    $stmt->execute();
    $certificates = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $_SESSION['reply'] = array(array('danger', 'Failed to load certificates module.'));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<title><?php echo APP_NAME; ?> - Certificates</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
</head>
<body class="app sidebar-mini">
<header class="app-header"><a class="app-header__logo" href="javascript:void(0);"><?php echo APP_NAME; ?></a>
<a class="app-sidebar__toggle" href="#" data-toggle="sidebar" aria-label="Hide Sidebar"></a></header>
<?php include('admin/partials/sidebar.php'); ?>
<main class="app-content">
<div class="app-title"><div><h1>🎓 School Certificates</h1><p>Generate leaving, completion, conduct, merit, and transfer certificates with CBC competency tracking.</p></div></div>

<div class="tile mb-3">
<h3 class="tile-title"><i class="bi bi-plus-circle"></i> Generate New Certificate</h3>
<form class="row g-3" method="POST" action="admin/core/generate_certificate">

<div class="col-md-3">
<label class="form-label">Certificate Type *</label>
<select class="form-control" name="certificate_type" id="certType" required onchange="toggleCertificateFields()">
<option value="" disabled selected>-- Select Type --</option>
<?php foreach ($types as $key => $label): ?>
<option value="<?php echo htmlspecialchars($key); ?>"><?php echo htmlspecialchars($label); ?></option>
<?php endforeach; ?>
</select>
<small class="form-text text-muted d-block mt-1">
📜 Primary Completion → Grade 6<br>
📜 Junior Completion → Grade 9<br>
📜 Leaving → Student departure
</small>
</div>

<div class="col-md-3">
<label class="form-label">Student *</label>
<select class="form-control" name="student_id" id="studentId" required onchange="loadStudentData()">
<option value="" disabled selected>-- Select Student --</option>
<?php foreach ($students as $student): ?>
<option value="<?php echo htmlspecialchars((string)$student['id']); ?>" data-class="<?php echo htmlspecialchars((string)$student['class']); ?>">
<?php echo htmlspecialchars((string)$student['student_name'] . ' (' . ((string)$student['school_id'] ?: (string)$student['id']) . ')'); ?>
</option>
<?php endforeach; ?>
</select>
</div>

<div class="col-md-2">
<label class="form-label">Issue Date *</label>
<input class="form-control" type="date" name="issue_date" value="<?php echo date('Y-m-d'); ?>" required>
</div>

<div class="col-md-2" id="meanScoreField" style="display:none;">
<label class="form-label">Mean Score</label>
<input class="form-control" type="number" name="mean_score" min="0" max="100" step="0.01" placeholder="e.g. 72.50">
<small class="text-muted d-block">Optional</small>
</div>

<div class="col-md-12">
<label class="form-label">Remarks / Notes</label>
<textarea class="form-control" name="notes" rows="2" placeholder="Optional remarks or achievements to include on certificate"></textarea>
</div>

<div class="col-md-12" id="competenciesField" style="display:none;">
<label class="form-label">🧩 CBC Competencies Status</label>
<div class="row g-2">
<?php 
$competencies = app_cbc_competencies();
foreach ($competencies as $key => $comp):
?>
<div class="col-md-4">
<label class="small d-block mb-1"><strong><?php echo htmlspecialchars($comp['name']); ?></strong></label>
<select class="form-control form-control-sm" name="competencies[<?php echo htmlspecialchars($key); ?>]">
<option value="">-- Not Assessed --</option>
<option value="developing">Developing</option>
<option value="proficient">Proficient</option>
<option value="advanced">Advanced</option>
<option value="excellent">Excellent</option>
</select>
</div>
<?php endforeach; ?>
</div>
</div>

<div class="col-md-12 d-grid">
<button class="btn btn-primary btn-lg" type="submit"><i class="bi bi-award me-2"></i>Generate Certificate</button>
</div>
</form>
</div>

<!-- ===== ISSUED CERTIFICATES TABLE ===== -->
<div class="tile">
<h3 class="tile-title">📋 Issued Certificates</h3>
<ul class="nav nav-tabs mb-3" role="tablist">
<li class="nav-item" role="presentation">
<button class="nav-link active" id="allTab" data-bs-toggle="tab" data-bs-target="#allCerts" type="button" role="tab">All (<?php echo count($certificates); ?>)</button>
</li>
<?php $categoryGroups = array_reduce($certificates, function($carry, $cert) {
    $cat = $cert['certificate_category'] ?? 'general';
    $carry[$cat] = ($carry[$cat] ?? 0) + 1;
    return $carry;
}, []); ?>
<?php foreach (array_keys($categoryGroups) as $cat): ?>
<li class="nav-item" role="presentation">
<button class="nav-link" data-bs-toggle="tab" data-bs-target="#cat<?php echo htmlspecialchars($cat); ?>" type="button" role="tab">
<?php echo htmlspecialchars(app_certificate_category_label($cat)); ?> (<?php echo $categoryGroups[$cat]; ?>)
</button>
</li>
<?php endforeach; ?>
</ul>

<div class="tab-content">
<div class="tab-pane fade show active" id="allCerts" role="tabpanel">
<div class="table-responsive-md">
<table class="table table-sm table-hover">
<thead><tr><th>#</th><th>Student</th><th>Class</th><th>Type</th><th>Mean</th><th>Grade</th><th>Issued</th><th>Status</th><th>Action</th></tr></thead>
<tbody>
<?php foreach ($certificates as $row): ?>
<tr<?php echo $row['locked'] ? ' class="table-secondary"' : ''; ?>>
<td><?php echo (int)$row['id']; ?></td>
<td><?php echo htmlspecialchars((string)$row['student_name']); ?><div class="small text-muted"><?php echo htmlspecialchars((string)($row['school_id'] ?: '')); ?></div></td>
<td><?php echo htmlspecialchars((string)($row['class_name'] ?? '')); ?></td>
<td><small><?php echo htmlspecialchars((string)$row['certificate_type']); ?></small></td>
<td><?php echo $row['mean_score'] !== null ? number_format((float)$row['mean_score'], 2) : '—'; ?></td>
<td><?php if ($row['merit_grade']): ?><span class="badge bg-warning text-dark"><?php echo htmlspecialchars($row['merit_grade']); ?></span><?php else: ?>—<?php endif; ?></td>
<td><?php echo htmlspecialchars((string)$row['issue_date']); ?></td>
<td>
<span class="badge bg-<?php echo $row['status'] === 'issued' ? 'success' : ($row['status'] === 'pending' ? 'warning' : 'danger'); ?>">
<?php echo ucfirst(htmlspecialchars((string)$row['status'])); ?>
</span>
<?php if ($row['locked']): ?><br><small class="text-muted">🔒 Locked</small><?php endif; ?>
</td>
<td class="text-end">
<a class="btn btn-sm btn-primary" target="_blank" href="certificate_pdf?id=<?php echo (int)$row['id']; ?>" title="Download PDF"><i class="bi bi-download"></i></a>
<button class="btn btn-sm btn-info" onclick="openEmailModal('certificate', <?php echo (int)$row['id']; ?>, '<?php echo htmlspecialchars(addslashes($row['student_name'])); ?>')" title="Send via Email"><i class="bi bi-envelope"></i></button>
<a class="btn btn-sm btn-outline-secondary" target="_blank" href="verify_certificate?code=<?php echo urlencode((string)$row['verification_code']); ?>" title="Verify"><i class="bi bi-check-circle"></i></a>
</td>
</tr>
<?php endforeach; ?>
<?php if (!$certificates): ?>
<tr><td colspan="9" class="text-center text-muted">No certificates issued yet.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
</div>

<?php foreach ($categoryGroups as $cat => $count): 
$catCerts = array_filter($certificates, function($c) use ($cat) { return ($c['certificate_category'] ?? 'general') === $cat; });
?>
<div class="tab-pane fade" id="cat<?php echo htmlspecialchars($cat); ?>" role="tabpanel">
<div class="table-responsive-md">
<table class="table table-sm table-hover">
<thead><tr><th>#</th><th>Student</th><th>Class</th><th>Mean</th><th>Grade</th><th>Issued</th><th>Action</th></tr></thead>
<tbody>
<?php foreach ($catCerts as $row): ?>
<tr<?php echo $row['locked'] ? ' class="table-secondary"' : ''; ?>>
<td><?php echo (int)$row['id']; ?></td>
<td><?php echo htmlspecialchars((string)$row['student_name']); ?></td>
<td><?php echo htmlspecialchars((string)($row['class_name'] ?? '')); ?></td>
<td><?php echo $row['mean_score'] !== null ? number_format((float)$row['mean_score'], 2) : '—'; ?></td>
<td><?php if ($row['merit_grade']): ?><strong><?php echo htmlspecialchars($row['merit_grade']); ?></strong><?php else: ?>—<?php endif; ?></td>
<td><?php echo htmlspecialchars((string)$row['issue_date']); ?></td>
<td class="text-end">
<a class="btn btn-sm btn-primary" target="_blank" href="certificate_pdf?id=<?php echo (int)$row['id']; ?>"><i class="bi bi-download"></i></a>
<button class="btn btn-sm btn-info" onclick="openEmailModal('certificate', <?php echo (int)$row['id']; ?>, '<?php echo htmlspecialchars(addslashes($row['student_name'])); ?>')"><i class="bi bi-envelope"></i></button>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>
<?php endforeach; ?>

</div>
</div>

<!-- ===== CERTIFICATE STATS ===== -->
<div class="row mt-4">
<div class="col-md-3">
<div class="tile tile-colored bg-primary">
<div class="tile-body">
<h4><?php echo count($certificates); ?></h4>
<p>Total Certificates Issued</p>
</div>
</div>
</div>
<div class="col-md-3">
<div class="tile tile-colored bg-success">
<div class="tile-body">
<h4><?php echo count(array_filter($certificates, fn($c) => $c['merit_grade'] === 'A')); ?></h4>
<p>Grade A Certificates</p>
</div>
</div>
</div>
<div class="col-md-3">
<div class="tile tile-colored bg-warning">
<div class="tile-body">
<h4><?php echo count(array_filter($certificates, fn($c) => $c['locked'])); ?></h4>
<p>Locked / Approved</p>
</div>
</div>
</div>
<div class="col-md-3">
<div class="tile tile-colored bg-info">
<div class="tile-body">
<h4><?php echo count(array_filter($certificates, fn($c) => $c['certificate_category'] === 'primary_completion')); ?></h4>
<p>Primary Completion</p>
</div>
</div>
</div>
</div>
</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<script>
function toggleCertificateFields() {
    const type = document.getElementById('certType').value;
    const meanScoreField = document.getElementById('meanScoreField');
    const competenciesField = document.getElementById('competenciesField');
    
    // Show mean score and competencies for completion certificates and merit
    const showFields = ['primary_completion', 'junior_completion', 'merit'].includes(type);
    meanScoreField.style.display = showFields ? 'block' : 'none';
    competenciesField.style.display = showFields ? 'block' : 'none';
}

function loadStudentData() {
    const select = document.getElementById('studentId');
    const selectedOption = select.options[select.selectedIndex];
    const classLevel = selectedOption.getAttribute('data-class');
    
    console.log('Student from class level:', classLevel);
}

function openEmailModal(resultType, resultId, studentName) {
    document.getElementById('emailResultType').value = resultType;
    document.getElementById('emailResultId').value = resultId;
    document.getElementById('emailStudentName').textContent = studentName;
    document.getElementById('emailModalLabel').textContent = resultType === 'certificate' ? 'Send Certificate via Email' : 'Send Report Card via Email';
    
    const modal = new bootstrap.Modal(document.getElementById('emailModal'));
    modal.show();
}

function sendEmailResult() {
    const form = document.getElementById('emailForm');
    const resultType = document.getElementById('emailResultType').value;
    const resultId = document.getElementById('emailResultId').value;
    const email = document.getElementById('emailAddress').value.trim();
    const message = document.getElementById('emailMessage').value.trim();
    
    if (!email || !email.includes('@')) {
        alert('Please enter a valid email address');
        return;
    }
    
    const formData = new FormData();
    formData.append('result_type', resultType);
    formData.append('result_id', resultId);
    formData.append('recipient_email', email);
    formData.append('message', message);
    
    fetch('admin/core/email_result', {
        method: 'POST',
        body: formData
    }).then(response => {
        if (response.ok) {
            const modal = bootstrap.Modal.getInstance(document.getElementById('emailModal'));
            modal.hide();
            location.reload();
        } else {
            throw new Error('Failed to send email');
        }
    }).catch(error => {
        alert('Error: ' + error.message);
    });
}

document.addEventListener('DOMContentLoaded', function() {
    // Initialize bootstrap tabs
    const triggerTabList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tab"]'));
    triggerTabList.forEach(function (triggerEl) {
        const tabTrigger = new bootstrap.Tab(triggerEl);
    });
});
</script>

<!-- Email Modal -->
<div class="modal fade" id="emailModal" tabindex="-1" aria-labelledby="emailModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="emailModalLabel">Send Certificate via Email</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="emailForm">
          <input type="hidden" id="emailResultType">
          <input type="hidden" id="emailResultId">
          
          <div class="mb-3">
            <label class="form-label">Student:</label>
            <p class="form-control-plaintext" id="emailStudentName"></p>
          </div>
          
          <div class="mb-3">
            <label for="emailAddress" class="form-label">Recipient Email *</label>
            <input type="email" class="form-control" id="emailAddress" placeholder="Enter recipient email address" required>
            <small class="text-muted">Send to parent, guardian, or student email</small>
          </div>
          
          <div class="mb-3">
            <label for="emailMessage" class="form-label">Message (Optional)</label>
            <textarea class="form-control" id="emailMessage" rows="3" placeholder="Add a personal message to include in the email..."></textarea>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="sendEmailResult()">
          <i class="bi bi-send"></i> Send Email
        </button>
      </div>
    </div>
  </div>
</div>

<?php require_once('const/check-reply.php'); ?>
</body>
</html>
