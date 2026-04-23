<?php
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
require_once('const/rbac.php');

function student_menu_active($page)
{
    global $currentPage;
    return $currentPage === $page ? ' active' : '';
}

function student_menu_active_any(array $pages)
{
    global $currentPage;
    return in_array($currentPage, $pages, true) ? ' active' : '';
}

function student_menu_is_active(array $module): string
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

  if ($currentPage === 'index' && $href === 'student') {
    return ' active';
  }

  return '';
}

function student_sidebar_group_label(string $moduleKey): string
{
  $groupMap = [
    'subjects' => 'Learning',
    'elearning' => 'Learning',
    'exam_timetable' => 'Learning',
    'attendance' => 'Academic',
    'results' => 'Academic',
    'report_card' => 'Academic',
    'grading_system' => 'Academic',
    'ranking' => 'Academic',
    'discipline' => 'Welfare',
    'leadership' => 'Welfare',
    'fees' => 'Account',
    'certificates' => 'Account',
    'profile' => 'Account',
    'settings' => 'Account',
  ];

  return $groupMap[$moduleKey] ?? 'General';
}

$studentModules = app_current_user_visible_portal_modules('student');
$lastStudentGroup = '';
?>
<div class="app-sidebar__overlay" data-toggle="sidebar"></div>
<aside class="app-sidebar">
  <div class="app-sidebar__user">
    <div>
      <p class="app-sidebar__user-name"><?php echo htmlspecialchars($fname . ' ' . $lname); ?></p>
      <p class="app-sidebar__user-designation">Student</p>
    </div>
  </div>
  <ul class="app-menu">
    <?php foreach ($studentModules as $module): ?>
    <?php
      $moduleKey = (string)($module['key'] ?? '');
      $currentGroup = student_sidebar_group_label($moduleKey);
      $shouldRenderHeading = $currentGroup !== $lastStudentGroup;
      if ($shouldRenderHeading) {
        $lastStudentGroup = $currentGroup;
      }
    ?>
    <?php if ($shouldRenderHeading): ?>
    <li class="px-3 pt-3 pb-1 text-uppercase" style="font-size:.7rem;letter-spacing:.12em;color:#6f7e8f;font-weight:800;"><?php echo htmlspecialchars($currentGroup); ?></li>
    <?php endif; ?>
    <li><a class="app-menu__item<?php echo student_menu_is_active($module); ?>" href="<?php echo htmlspecialchars((string)$module['href']); ?>"><i class="app-menu__icon <?php echo htmlspecialchars((string)$module['icon']); ?>"></i><span class="app-menu__label"><?php echo htmlspecialchars((string)$module['label']); ?></span></a></li>
    <?php endforeach; ?>
  </ul>
</aside>
