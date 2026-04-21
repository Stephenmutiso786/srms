<?php
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
require_once('const/rbac.php');
function teacher_menu_active($page)
{
    global $currentPage;
    return $currentPage === $page ? ' active' : '';
}

function teacher_menu_active_any(array $pages)
{
  global $currentPage;
  return in_array($currentPage, $pages, true) ? ' active' : '';
}

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
  }

  if (($module['key'] ?? '') === 'subject_combinations' && !in_array('import_results', $activePages, true)) {
    $activePages[] = 'import_results';
  }

  return in_array($currentPage, $activePages, true) ? ' active' : '';
}

$allocatedSidebarModules = app_current_user_allocated_portal_modules('teacher');
$allocatedSidebarModulesByKey = [];
foreach ($allocatedSidebarModules as $module) {
  $allocatedSidebarModulesByKey[(string)($module['key'] ?? '')] = $module;
}

$teacherSidebarGroups = [
  [
    'label' => 'Academic',
    'keys' => ['exam_timetable', 'grading_system', 'elearning', 'subject_combinations'],
  ],
  [
    'label' => 'Staff',
    'keys' => ['roles'],
  ],
];
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
    <li><a class="app-menu__item<?php echo teacher_menu_active('index'); ?>" href="teacher"><i class="app-menu__icon feather icon-monitor"></i><span class="app-menu__label">Dashboard</span></a></li>
    <li><a class="app-menu__item<?php echo teacher_menu_active('terms'); ?>" href="teacher/terms"><i class="app-menu__icon feather icon-folder"></i><span class="app-menu__label">Academic Terms</span></a></li>
    <li><a class="app-menu__item<?php echo teacher_menu_active('attendance'); ?>" href="teacher/attendance"><i class="app-menu__icon feather icon-check-square"></i><span class="app-menu__label">Attendance</span></a></li>
    <li><a class="app-menu__item<?php echo teacher_menu_active('discipline'); ?>" href="teacher/discipline"><i class="app-menu__icon feather icon-alert-triangle"></i><span class="app-menu__label">Discipline</span></a></li>
    <li><a class="app-menu__item<?php echo teacher_menu_active('division-system'); ?>" href="teacher/division-system"><i class="app-menu__icon feather icon-layers"></i><span class="app-menu__label">Division System</span></a></li>
    <li><a class="app-menu__item<?php echo teacher_menu_active('exam_marks_entry'); ?>" href="teacher/exam_marks_entry"><i class="app-menu__icon feather icon-edit-3"></i><span class="app-menu__label">Marks Entry</span></a></li>
    <li><a class="app-menu__item<?php echo teacher_menu_active_any(['manage_results', 'results', 'report_card', 'certificates', 'published_analytics', 'print_mark_sheet']); ?>" href="teacher/manage_results"><i class="app-menu__icon feather icon-graph"></i><span class="app-menu__label">Results</span></a></li>
    <li><a class="app-menu__item<?php echo teacher_menu_active('staff_attendance'); ?>" href="teacher/staff_attendance"><i class="app-menu__icon feather icon-clock"></i><span class="app-menu__label">Staff Attendance</span></a></li>
    <li><a class="app-menu__item<?php echo teacher_menu_active('students'); ?>" href="teacher/students"><i class="app-menu__icon feather icon-users"></i><span class="app-menu__label">Students</span></a></li>
    <?php foreach ($teacherSidebarGroups as $group): ?>
      <?php
        $groupModules = [];
        foreach ((array)($group['keys'] ?? []) as $moduleKey) {
          if (isset($allocatedSidebarModulesByKey[$moduleKey])) {
            $groupModules[] = $allocatedSidebarModulesByKey[$moduleKey];
          }
        }
        if (!$groupModules) {
          continue;
        }
      ?>
      <li class="px-3 pt-3 pb-1 text-uppercase" style="font-size:.7rem;letter-spacing:.12em;color:#6f7e8f;font-weight:800;">
        <?php echo htmlspecialchars((string)$group['label']); ?>
      </li>
      <?php foreach ($groupModules as $module): ?>
      <li>
        <a class="app-menu__item<?php echo teacher_menu_module_active($module); ?>" href="<?php echo htmlspecialchars((string)($module['href'] ?? 'teacher')); ?>">
          <i class="app-menu__icon <?php echo htmlspecialchars((string)($module['icon'] ?? 'feather icon-grid')); ?>"></i>
          <span class="app-menu__label"><?php echo htmlspecialchars((string)($module['label'] ?? 'Module')); ?></span>
        </a>
      </li>
      <?php endforeach; ?>
    <?php endforeach; ?>
    <li><a class="app-menu__item<?php echo teacher_menu_active('how_system_works'); ?>" href="teacher/how_system_works"><i class="app-menu__icon feather icon-help-circle"></i><span class="app-menu__label">How The System Works</span></a></li>
  </ul>
</aside>
