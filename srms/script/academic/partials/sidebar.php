<?php
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
require_once('const/rbac.php');
require_once('const/edu_ai_portal_ui.php');

function academic_sidebar_is_active(array $module): string
{
	global $currentPage;
	$activePages = array_map('strval', (array)($module['active'] ?? []));
	if (!empty($activePages) && in_array($currentPage, $activePages, true)) {
		return ' active';
	}

	$href = (string)($module['href'] ?? '');
	if ($href !== '' && basename($href, '.php') === $currentPage) {
		return ' active';
	}

	if ($currentPage === 'index' && in_array($href, ['academic', 'academic/index.php'], true)) {
		return ' active';
	}

	return '';
}

function academic_sidebar_group_label(string $moduleKey): string
{
	$groupMap = [
		'dashboard' => 'Overview',
		'terms' => 'Academic',
		'classes' => 'Academic',
		'subjects' => 'Academic',
		'teacher_control' => 'Teachers',
		'combinations' => 'Academic',
		'attendance' => 'Academic',
		'discipline' => 'Students',
		'exams' => 'Exams',
		'exam_timetable' => 'Exams',
		'marks_review' => 'Exams',
		'publish_results' => 'Exams',
		'results_analytics' => 'Exams',
		'results_locks' => 'Exams',
		'fees' => 'Finance',
		'grading_system' => 'Academic',
		'division_system' => 'Academic',
		'results_manage' => 'Results',
		'marks_entry' => 'Exams',
		'individual_results' => 'Results',
		'report_tool' => 'Results',
		'announcements' => 'Communication',
		'profile' => 'Account',
	];

	return $groupMap[$moduleKey] ?? 'General';
}

$academicModules = app_current_user_visible_portal_modules('academic');
$lastAcademicGroup = '';
?>
<div class="app-sidebar__overlay" data-toggle="sidebar"></div>
<aside class="app-sidebar">
<div class="app-sidebar__user">
<div>
<p class="app-sidebar__user-name"><?php echo htmlspecialchars((string)$fname.' '.(string)$lname); ?></p>
<p class="app-sidebar__user-designation"><?php echo htmlspecialchars((string)($designation ?? 'Academic')); ?></p>
</div>
</div>
<ul class="app-menu">
<?php foreach ($academicModules as $module): ?>
<?php
	$moduleKey = (string)($module['key'] ?? '');
	$currentGroup = academic_sidebar_group_label($moduleKey);
	$shouldRenderHeading = $currentGroup !== $lastAcademicGroup;
	if ($shouldRenderHeading) {
		$lastAcademicGroup = $currentGroup;
	}
?>
<?php if ($shouldRenderHeading): ?>
<li class="px-3 pt-3 pb-1 text-uppercase" style="font-size:.7rem;letter-spacing:.12em;color:#6f7e8f;font-weight:800;"><?php echo htmlspecialchars($currentGroup); ?></li>
<?php endif; ?>
<li><a class="app-menu__item<?php echo academic_sidebar_is_active($module); ?>" href="<?php echo htmlspecialchars((string)$module['href']); ?>"><i class="app-menu__icon <?php echo htmlspecialchars((string)$module['icon']); ?>"></i><span class="app-menu__label"><?php echo htmlspecialchars((string)$module['label']); ?></span></a></li>
<?php endforeach; ?>
</ul>
<?php app_render_portal_edu_ai('academic'); ?>
</aside>
