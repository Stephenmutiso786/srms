<?php
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
require_once('const/rbac.php');
$visibleAdminModules = app_current_user_visible_portal_modules('admin');

function admin_menu_active($page)
{
  global $currentPage;
  return $currentPage === $page ? ' active' : '';
}

function admin_sidebar_is_active(array $module): string
{
  global $currentPage;
  $activePages = array_map('strval', (array)($module['active'] ?? []));
  if (!empty($activePages) && in_array($currentPage, $activePages, true)) {
    return ' active';
  }

  $href = (string)($module['href'] ?? '');
  if ($href !== '' && basename($href) === $currentPage) {
    return ' active';
  }

  if ($currentPage === 'index' && $href === 'admin') {
    return ' active';
  }

  return '';
}

function admin_sidebar_group_label(string $moduleKey): string
{
  $groupMap = [
    'dashboard' => 'Overview',
    'academic' => 'Academic',
    'teachers' => 'Staff',
    'classes' => 'Academic',
    'terms' => 'Academic',
    'subjects' => 'Academic',
    'teacher_allocation' => 'Academic',
    'school_timetable' => 'Academic',
    'discipline' => 'Students',
    'import_students' => 'Students',
    'manage_students' => 'Students',
    'register_students' => 'Students',
    'student_leaders' => 'Students',
    'parents' => 'Students',
    'attendance' => 'Operations',
    'staff_attendance' => 'Operations',
    'fees' => 'Finance',
    'import_export' => 'System',
    'communication' => 'Communication',
    'sms_topup' => 'Communication',
    'elearning' => 'Academic',
    'feedback' => 'Academic',
    'library' => 'Resources',
    'inventory' => 'Resources',
    'transport' => 'Resources',
    'exams' => 'Exams',
    'exam_timetable' => 'Exams',
    'marks_review' => 'Exams',
    'publish_results' => 'Exams',
    'results_analytics' => 'Exams',
    'results_locks' => 'Exams',
    'report' => 'Reports',
    'merit_list' => 'Reports',
    'report_settings' => 'Reports',
    'certificates' => 'Results',
    'promotion_rules' => 'Results',
    'promotions' => 'Results',
    'analytics_engine' => 'Reports',
    'benchmarking' => 'Reports',
    'notifications' => 'Communication',
    'online_users' => 'System',
    'audit_logs' => 'System',
    'roles' => 'Access Control',
    'role_matrix' => 'Access Control',
    'bom' => 'Governance',
    'mpesa' => 'Finance',
    'smtp' => 'System',
    'system_diagnostics' => 'System',
    'migrations' => 'System',
    'module_locks' => 'System',
    'system' => 'System',
    'how_system_works' => 'Support',
  ];

  return $groupMap[$moduleKey] ?? 'General';
}

function admin_sidebar_module_key(string $key): string
{
  return trim($key);
}

$adminSidebarGroups = [];
foreach ($visibleAdminModules as $module) {
  $moduleKey = admin_sidebar_module_key((string)($module['key'] ?? ''));
  $group = admin_sidebar_group_label($moduleKey);
  $adminSidebarGroups[$group][] = $module;
}

$adminSidebarGroupOrder = ['Overview', 'Academic', 'Students', 'Operations', 'Finance', 'Communication', 'Exams', 'Reports', 'Results', 'Access Control', 'Resources', 'Governance', 'System', 'Support', 'General'];
?>
<div class="app-sidebar__overlay" data-toggle="sidebar"></div>
<aside class="app-sidebar">
  <div class="app-sidebar__user">
    <div>
      <p class="app-sidebar__user-name"><?php echo $fname.' '.$lname; ?></p>
    <p class="app-sidebar__user-designation"><?php echo htmlspecialchars((string)($designation ?? app_level_title_label((int)($level ?? 0)))); ?></p>
    </div>
  </div>
  <ul class="app-menu">
    <?php foreach ($adminSidebarGroupOrder as $groupLabel): ?>
      <?php if (empty($adminSidebarGroups[$groupLabel])) { continue; } ?>
      <li class="px-3 pt-3 pb-1 text-uppercase" style="font-size:.7rem;letter-spacing:.12em;color:#6f7e8f;font-weight:800;"><?php echo htmlspecialchars($groupLabel); ?></li>
      <?php foreach ($adminSidebarGroups[$groupLabel] as $module): ?>
      <li>
        <a class="app-menu__item<?php echo admin_sidebar_is_active($module); ?>" href="<?php echo htmlspecialchars((string)($module['href'] ?? 'admin')); ?>">
          <i class="app-menu__icon <?php echo htmlspecialchars((string)($module['icon'] ?? 'feather icon-grid')); ?>"></i>
          <span class="app-menu__label"><?php echo htmlspecialchars((string)($module['label'] ?? 'Module')); ?></span>
        </a>
      </li>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </ul>
  <div class="app-sidebar__footer">
    <a class="app-sidebar__footer-link" href="privacy" target="_blank"><i class="bi bi-shield-lock me-2"></i>Privacy Policy</a>
    <a class="app-sidebar__footer-link" href="terms" target="_blank"><i class="bi bi-file-text me-2"></i>Terms & Conditions</a>
  </div>
</aside>
