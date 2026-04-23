<?php
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
require_once('const/rbac.php');

function teacher_menu_module_active(array $module)
{
  global $currentPage;
  $activePages = array_values(array_filter(array_map('strval', (array)($module['active'] ?? []))));

  if (empty($activePages)) {
    $href = (string)($module['href'] ?? '');
    $path = trim((string)parse_url($href, PHP_URL_PATH), '/');
    if ($path === 'teacher' || $path === '') {
      $activePages = ['index'];
    } else {
      $activePages = [basename($path)];
    }

    if ($currentPage === 'index' && $path === 'teacher') {
      return ' active';
    }
  }

  if (($module['key'] ?? '') === 'subject_combinations' && !in_array('import_results', $activePages, true)) {
    $activePages[] = 'import_results';
  }

  return in_array($currentPage, $activePages, true) ? ' active' : '';
}

function teacher_sidebar_group_label(string $moduleKey): string
{
  $groupMap = [
    'dashboard' => 'Overview',
    'terms' => 'Overview',
    'attendance' => 'Academic',
    'marks_entry' => 'Academic',
    'results' => 'Academic',
    'discipline' => 'Student Welfare',
    'students' => 'Student Welfare',
    'staff_attendance' => 'Staff',
    'exam_timetable' => 'Academic',
    'grading_system' => 'Academic',
    'elearning' => 'Academic',
    'subject_combinations' => 'Academic',
    'roles' => 'Staff',
    'how_system_works' => 'Support',
    'profile' => 'Account',
  ];

  return $groupMap[$moduleKey] ?? 'General';
}

$teacherModules = app_current_user_visible_portal_modules('teacher');
$teacherGroupedModules = [];
foreach ($teacherModules as $module) {
  $moduleKey = (string)($module['key'] ?? '');
  $teacherGroupedModules[teacher_sidebar_group_label($moduleKey)][] = $module;
}

$teacherSidebarGroupOrder = ['Overview', 'Academic', 'Student Welfare', 'Staff', 'Account', 'Support', 'General'];
?>
<div class="app-sidebar__overlay" data-toggle="sidebar"></div>
<aside class="app-sidebar">
  <div class="app-sidebar__user">
    <div>
      <p class="app-sidebar__user-name"><?php echo htmlspecialchars($fname.' '.$lname); ?></p>
      <p class="app-sidebar__user-designation"><?php echo htmlspecialchars((string)($designation ?? 'Teacher')); ?></p>
    </div>
  </div>
  <ul class="app-menu">
    <?php foreach ($teacherSidebarGroupOrder as $groupLabel): ?>
      <?php if (empty($teacherGroupedModules[$groupLabel])) { continue; } ?>
      <li class="px-3 pt-3 pb-1 text-uppercase" style="font-size:.7rem;letter-spacing:.12em;color:#6f7e8f;font-weight:800;"><?php echo htmlspecialchars($groupLabel); ?></li>
      <?php foreach ($teacherGroupedModules[$groupLabel] as $module): ?>
      <li>
        <a class="app-menu__item<?php echo teacher_menu_module_active($module); ?>" href="<?php echo htmlspecialchars((string)($module['href'] ?? 'teacher')); ?>">
          <i class="app-menu__icon <?php echo htmlspecialchars((string)($module['icon'] ?? 'feather icon-grid')); ?>"></i>
          <span class="app-menu__label"><?php echo htmlspecialchars((string)($module['label'] ?? 'Module')); ?></span>
        </a>
      </li>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </ul>
</aside>
