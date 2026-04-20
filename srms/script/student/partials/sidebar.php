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

  return '';
}
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
    <?php foreach (app_current_user_visible_portal_modules('student') as $module): ?>
    <li><a class="app-menu__item<?php echo student_menu_is_active($module); ?>" href="<?php echo htmlspecialchars((string)$module['href']); ?>"><i class="app-menu__icon <?php echo htmlspecialchars((string)$module['icon']); ?>"></i><span class="app-menu__label"><?php echo htmlspecialchars((string)$module['label']); ?></span></a></li>
    <?php endforeach; ?>
  </ul>
</aside>
