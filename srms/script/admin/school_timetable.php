<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');
if ($res != "1" || $level != "0") { header("location:../"); exit; }
app_require_permission('academic.manage', 'admin');

$classes = [];
$terms = [];
$rows = [];
$classId = (int)($_GET['class_id'] ?? 0);
$termId = (int)($_GET['term_id'] ?? 0);
$error = '';
$schoolDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
$sessionLabels = [];
$slotTemplateBySession = [];
$slotMap = [];

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_school_timetable_table($conn);

	$stmt = $conn->prepare("SELECT id, name FROM tbl_classes ORDER BY name");
	$stmt->execute();
	$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$stmt = $conn->prepare("SELECT id, name FROM tbl_terms ORDER BY id DESC");
	$stmt->execute();
	$terms = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$schoolDays = app_school_days($conn);

	if ($classId > 0 && $termId > 0) {
		$stmt = $conn->prepare("SELECT st.id, st.day_name, st.session_label, st.start_time, st.end_time, st.room,
			sb.name AS subject_name, concat_ws(' ', t.fname, t.lname) AS teacher_name
			FROM tbl_school_timetable st
			JOIN tbl_subjects sb ON sb.id = st.subject_id
			JOIN tbl_staff t ON t.id = st.teacher_id
			WHERE st.class_id = ? AND st.term_id = ?
			ORDER BY CASE st.day_name
				WHEN 'Monday' THEN 1
				WHEN 'Tuesday' THEN 2
				WHEN 'Wednesday' THEN 3
				WHEN 'Thursday' THEN 4
				WHEN 'Friday' THEN 5
				WHEN 'Saturday' THEN 6
				WHEN 'Sunday' THEN 7
				ELSE 8 END, st.start_time");
		$stmt->execute([$classId, $termId]);
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
		foreach ($rows as $row) {
			$session = (string)$row['session_label'];
			if ($session === '') {
				continue;
			}
			$sessionLabels[$session] = $session;
			if (!isset($slotTemplateBySession[$session])) {
				$slotTemplateBySession[$session] = [
					'session_label' => $session,
					'start_time' => substr((string)$row['start_time'], 0, 8),
					'end_time' => substr((string)$row['end_time'], 0, 8),
				];
			}
			$slotMap[(string)$row['day_name'].'|'.$session] = $row;
		}
		$sessionLabels = array_values($sessionLabels);
		usort($sessionLabels, function ($a, $b) {
			preg_match('/(\d+)/', (string)$a, $ma);
			preg_match('/(\d+)/', (string)$b, $mb);
			$na = isset($ma[1]) ? (int)$ma[1] : PHP_INT_MAX;
			$nb = isset($mb[1]) ? (int)$mb[1] : PHP_INT_MAX;
			if ($na === $nb) {
				return strcmp((string)$a, (string)$b);
			}
			return $na <=> $nb;
		});
	}
} catch (Throwable $e) {
	error_log("[".__FILE__.":".__LINE__." Throwable] " . $e->getMessage());
	$error = "An internal error occurred.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<title><?php echo APP_NAME; ?> - School Timetable</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
<style>
.tt-grid { min-width: 940px; border-collapse: separate; border-spacing: 0; }
.tt-grid th, .tt-grid td { border: 1px solid #e6edf5; vertical-align: top; }
.tt-grid th { background: #f7fbff; font-size: .85rem; text-transform: uppercase; letter-spacing: .04em; color: #567; }
.tt-grid .session-cell { width: 180px; min-width: 180px; background: #fbfdff; }
.tt-slot-cell { min-height: 122px; position: relative; background: #fff; }
.tt-dropzone { min-height: 120px; padding: 8px; transition: background .18s ease, outline-color .18s ease; }
.tt-dropzone.is-over { background: #eef7ff; outline: 2px dashed #2f80ed; }
.tt-card {
	background: linear-gradient(145deg, #0d64b0, #1c8ad1);
	color: #fff;
	border-radius: 14px;
	padding: 10px;
	cursor: grab;
	box-shadow: 0 10px 20px rgba(13,100,176,.22);
}
.tt-card:active { cursor: grabbing; }
.tt-card .title { font-weight: 700; line-height: 1.2; }
.tt-card .meta { font-size: .78rem; opacity: .92; }
.tt-empty {
	min-height: 78px;
	border: 1px dashed #d8e4f2;
	border-radius: 12px;
	font-size: .8rem;
	color: #7b8a9c;
	display: flex;
	align-items: center;
	justify-content: center;
	text-align: center;
	padding: 6px;
}
</style>
</head>
<body class="app sidebar-mini">
<header class="app-header"><a class="app-header__logo" href="javascript:void(0);"><?php echo APP_NAME; ?></a>
<a class="app-sidebar__toggle" href="#" data-toggle="sidebar" aria-label="Hide Sidebar"></a></header>
<?php include('admin/partials/sidebar.php'); ?>
<main class="app-content">
<div class="app-title">
	<div>
		<h1>School Timetable</h1>
		<p>Generate a clash-free teaching timetable from real teacher allocations.</p>
	</div>
</div>

<?php if ($error !== '') { ?>
<div class="tile"><div class="alert alert-danger mb-0"><?php echo htmlspecialchars($error); ?></div></div>
<?php } else { ?>

<div class="tile mb-3">
	<h3 class="tile-title">Select Class & Term</h3>
	<form class="row g-3" method="GET" action="admin/school_timetable">
		<div class="col-md-5">
			<label class="form-label">Class</label>
			<select class="form-control" name="class_id" required>
				<option value="" disabled <?php echo $classId ? '' : 'selected'; ?>>Select class</option>
				<?php foreach ($classes as $class): ?>
				<option value="<?php echo (int)$class['id']; ?>" <?php echo ((int)$class['id'] === $classId) ? 'selected' : ''; ?>>
					<?php echo htmlspecialchars($class['name']); ?>
				</option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="col-md-5">
			<label class="form-label">Term</label>
			<select class="form-control" name="term_id" required>
				<option value="" disabled <?php echo $termId ? '' : 'selected'; ?>>Select term</option>
				<?php foreach ($terms as $term): ?>
				<option value="<?php echo (int)$term['id']; ?>" <?php echo ((int)$term['id'] === $termId) ? 'selected' : ''; ?>>
					<?php echo htmlspecialchars($term['name']); ?>
				</option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="col-md-2 d-grid align-items-end">
			<button class="btn btn-primary" type="submit">Load</button>
		</div>
	</form>
</div>

<?php if ($classId > 0 && $termId > 0) { ?>
<div class="tile mb-3">
	<h3 class="tile-title">Smart Auto Generate</h3>
	<form class="row g-3" method="POST" action="admin/core/auto_generate_school_timetable">
		<input type="hidden" name="class_id" value="<?php echo $classId; ?>">
		<input type="hidden" name="term_id" value="<?php echo $termId; ?>">
		<div class="col-md-4">
			<label class="form-label">Academic Year</label>
			<input class="form-control" type="number" name="year" value="<?php echo (int)date('Y'); ?>" min="2000" required>
		</div>
		<div class="col-md-4">
			<label class="form-label">School Days</label>
			<input class="form-control" name="days" value="<?php echo htmlspecialchars(implode(',', $schoolDays)); ?>" required>
		</div>
		<div class="col-md-2">
			<label class="form-label">Daily Sessions</label>
			<input class="form-control" type="number" name="sessions_per_day" min="1" max="8" value="6" required>
		</div>
		<div class="col-md-2">
			<label class="form-label">Session Minutes</label>
			<input class="form-control" type="number" name="duration_minutes" min="30" max="180" value="40" required>
		</div>
		<div class="col-md-2">
			<label class="form-label">Break Minutes</label>
			<input class="form-control" type="number" name="break_minutes" min="0" max="60" value="10" required>
		</div>
		<div class="col-md-2">
			<label class="form-label">First Start</label>
			<input class="form-control" type="time" name="first_start_time" value="08:00" required>
		</div>
		<div class="col-md-3">
			<label class="form-label">Room Prefix</label>
			<input class="form-control" name="room_prefix" value="Class Room" placeholder="Class Room">
		</div>
		<div class="col-md-5">
			<label class="form-label">Generation Mode</label>
			<select class="form-control" name="clear_existing">
				<option value="1">Replace this class timetable for the selected term</option>
				<option value="0">Append only if free slots exist</option>
			</select>
		</div>
		<div class="col-md-12 d-grid">
			<button class="btn btn-success" type="submit"><i class="bi bi-stars me-1"></i>Generate School Timetable</button>
		</div>
	</form>
	<p class="text-muted mt-2 mb-0">The generator uses teacher allocations and avoids putting one teacher in two classes during the same session.</p>
</div>

<div class="tile">
	<h3 class="tile-title">Current Timetable</h3>
	<p class="text-muted">Drag a lesson card and drop it to a new slot. Dropping onto an occupied slot swaps both lessons with full conflict validation.</p>
	<?php if (!empty($sessionLabels)) { ?>
	<div class="table-responsive mb-3">
		<table class="table tt-grid mb-0">
			<thead>
				<tr>
					<th class="session-cell">Session / Time</th>
					<?php foreach ($schoolDays as $day): ?>
					<th><?php echo htmlspecialchars($day); ?></th>
					<?php endforeach; ?>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($sessionLabels as $sessionLabel):
					$template = $slotTemplateBySession[$sessionLabel] ?? ['start_time' => '08:00:00', 'end_time' => '08:40:00'];
				?>
				<tr>
					<td class="session-cell">
						<div class="fw-semibold"><?php echo htmlspecialchars($sessionLabel); ?></div>
						<div class="small text-muted"><?php echo htmlspecialchars(substr((string)$template['start_time'], 0, 5)); ?> - <?php echo htmlspecialchars(substr((string)$template['end_time'], 0, 5)); ?></div>
					</td>
					<?php foreach ($schoolDays as $day):
						$key = $day.'|'.$sessionLabel;
						$slot = $slotMap[$key] ?? null;
					?>
					<td class="tt-slot-cell">
						<div class="tt-dropzone" data-day="<?php echo htmlspecialchars($day); ?>" data-session="<?php echo htmlspecialchars($sessionLabel); ?>" data-start="<?php echo htmlspecialchars((string)$template['start_time']); ?>" data-end="<?php echo htmlspecialchars((string)$template['end_time']); ?>" data-target-id="<?php echo $slot ? (int)$slot['id'] : 0; ?>">
							<?php if ($slot): ?>
							<div class="tt-card" draggable="true" data-id="<?php echo (int)$slot['id']; ?>" data-day="<?php echo htmlspecialchars((string)$slot['day_name']); ?>" data-session="<?php echo htmlspecialchars((string)$slot['session_label']); ?>" data-start="<?php echo htmlspecialchars(substr((string)$slot['start_time'], 0, 8)); ?>" data-end="<?php echo htmlspecialchars(substr((string)$slot['end_time'], 0, 8)); ?>" data-room="<?php echo htmlspecialchars((string)($slot['room'] ?? '')); ?>" data-target-id="<?php echo (int)$slot['id']; ?>">
								<div class="title"><?php echo htmlspecialchars((string)$slot['subject_name']); ?></div>
								<div class="meta mt-1"><?php echo htmlspecialchars((string)$slot['teacher_name']); ?></div>
								<div class="meta"><i class="bi bi-clock me-1"></i><?php echo htmlspecialchars(substr((string)$slot['start_time'], 0, 5)); ?> - <?php echo htmlspecialchars(substr((string)$slot['end_time'], 0, 5)); ?></div>
								<div class="meta"><i class="bi bi-door-open me-1"></i><?php echo htmlspecialchars((string)($slot['room'] ?? '')); ?></div>
							</div>
							<?php else: ?>
							<div class="tt-empty">Drop lesson here</div>
							<?php endif; ?>
						</div>
					</td>
					<?php endforeach; ?>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php } ?>
	<div class="table-responsive">
		<table class="table table-hover">
			<thead>
				<tr><th>Day</th><th>Session</th><th>Start</th><th>End</th><th>Subject</th><th>Teacher</th><th>Room</th></tr>
			</thead>
			<tbody>
				<?php foreach ($rows as $row): ?>
				<tr>
					<td><?php echo htmlspecialchars($row['day_name']); ?></td>
					<td><?php echo htmlspecialchars($row['session_label']); ?></td>
					<td><?php echo htmlspecialchars(substr((string)$row['start_time'], 0, 5)); ?></td>
					<td><?php echo htmlspecialchars(substr((string)$row['end_time'], 0, 5)); ?></td>
					<td><?php echo htmlspecialchars($row['subject_name']); ?></td>
					<td><?php echo htmlspecialchars($row['teacher_name']); ?></td>
					<td><?php echo htmlspecialchars((string)($row['room'] ?? '')); ?></td>
				</tr>
				<?php endforeach; ?>
				<?php if (!$rows): ?>
				<tr><td colspan="7" class="text-center text-muted">No school timetable generated yet for this class and term.</td></tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>
<?php } ?>
<?php } ?>

<form id="ttMoveForm" method="POST" action="admin/core/update_school_timetable_slot" class="d-none">
	<input type="hidden" name="id" id="tt-id">
	<input type="hidden" name="class_id" value="<?php echo (int)$classId; ?>">
	<input type="hidden" name="term_id" value="<?php echo (int)$termId; ?>">
	<input type="hidden" name="day_name" id="tt-day">
	<input type="hidden" name="session_label" id="tt-session">
	<input type="hidden" name="start_time" id="tt-start">
	<input type="hidden" name="end_time" id="tt-end">
	<input type="hidden" name="room" id="tt-room">
	<input type="hidden" name="swap_with_id" id="tt-swap-with-id" value="0">
</form>
</main>
<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<script>
(function () {
	const cards = document.querySelectorAll('.tt-card[draggable="true"]');
	const zones = document.querySelectorAll('.tt-dropzone');
	const form = document.getElementById('ttMoveForm');
	if (!cards.length || !zones.length || !form) {
		return;
	}

	let dragData = null;
	cards.forEach(function (card) {
		card.addEventListener('dragstart', function (event) {
			dragData = {
				id: card.dataset.id || '',
				day: card.dataset.day || '',
				session: card.dataset.session || '',
				start: card.dataset.start || '',
				end: card.dataset.end || '',
				room: card.dataset.room || ''
			};
			event.dataTransfer.effectAllowed = 'move';
			event.dataTransfer.setData('text/plain', dragData.id);
		});
	});

	zones.forEach(function (zone) {
		zone.addEventListener('dragover', function (event) {
			event.preventDefault();
			event.dataTransfer.dropEffect = 'move';
			zone.classList.add('is-over');
		});
		zone.addEventListener('dragleave', function () {
			zone.classList.remove('is-over');
		});
		zone.addEventListener('drop', function (event) {
			event.preventDefault();
			zone.classList.remove('is-over');
			if (!dragData || !dragData.id) {
				return;
			}
			const newDay = zone.dataset.day || '';
			const newSession = zone.dataset.session || '';
			const newStart = zone.dataset.start || '';
			const newEnd = zone.dataset.end || '';
			const targetId = zone.dataset.targetId || '0';

			if (dragData.day === newDay && dragData.session === newSession) {
				return;
			}

			document.getElementById('tt-id').value = dragData.id;
			document.getElementById('tt-day').value = newDay;
			document.getElementById('tt-session').value = newSession;
			document.getElementById('tt-start').value = newStart;
			document.getElementById('tt-end').value = newEnd;
			document.getElementById('tt-room').value = dragData.room;
			document.getElementById('tt-swap-with-id').value = (targetId && targetId !== dragData.id) ? targetId : '0';
			form.submit();
		});
	});
})();
</script>
<?php require_once('const/check-reply.php'); ?>
</body>
</html>
