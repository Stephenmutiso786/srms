<?php
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
require_once('const/rbac.php');
function parent_menu_active($page)
{
    global $currentPage;
    return $currentPage === $page ? ' active' : '';
}

function parent_menu_is_active(array $module): string
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

function parent_sidebar_group_label(string $moduleKey): string
{
  $groupMap = [
    'attendance' => 'Child Progress',
    'report_card' => 'Child Progress',
    'discipline' => 'Child Progress',
    'elearning' => 'Learning',
    'fees' => 'Account',
    'certificates' => 'Account',
    'how_system_works' => 'Support',
  ];

  return $groupMap[$moduleKey] ?? 'General';
}

$parentModules = app_current_user_visible_portal_modules('parent');
$lastParentGroup = '';
?>
<div class="app-sidebar__overlay" data-toggle="sidebar"></div>
<aside class="app-sidebar">
  <div class="app-sidebar__user">
    <div>
      <p class="app-sidebar__user-name"><?php echo htmlspecialchars($fname.' '.$lname); ?></p>
      <p class="app-sidebar__user-designation">Parent Portal</p>
    </div>
  </div>
  <ul class="app-menu">
    <?php foreach ($parentModules as $module): ?>
    <?php
      $moduleKey = (string)($module['key'] ?? '');
      $currentGroup = parent_sidebar_group_label($moduleKey);
      $shouldRenderHeading = $currentGroup !== $lastParentGroup;
      if ($shouldRenderHeading) {
        $lastParentGroup = $currentGroup;
      }
    ?>
    <?php if ($shouldRenderHeading): ?>
    <li class="px-3 pt-3 pb-1 text-uppercase" style="font-size:.7rem;letter-spacing:.12em;color:#6f7e8f;font-weight:800;"><?php echo htmlspecialchars($currentGroup); ?></li>
    <?php endif; ?>
    <li><a class="app-menu__item<?php echo parent_menu_is_active($module); ?>" href="<?php echo htmlspecialchars((string)$module['href']); ?>"><i class="app-menu__icon <?php echo htmlspecialchars((string)$module['icon']); ?>"></i><span class="app-menu__label"><?php echo htmlspecialchars((string)$module['label']); ?></span></a></li>
    <?php endforeach; ?>
  </ul>
</aside>
