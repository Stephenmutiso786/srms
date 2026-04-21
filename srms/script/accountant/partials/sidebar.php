<?php
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
require_once('const/rbac.php');

function accountant_sidebar_is_active(array $module): string
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

	if ($currentPage === 'index' && $href === 'accountant') {
		return ' active';
	}

	return '';
}

function accountant_sidebar_group_label(string $moduleKey): string
{
	$groupMap = [
		'fees' => 'Finance',
		'fee_structure' => 'Finance',
		'invoices' => 'Finance',
		'profile' => 'Account',
	];

	return $groupMap[$moduleKey] ?? 'General';
}

$accountantModules = app_current_user_visible_portal_modules('accountant');
$lastAccountantGroup = '';
?>
<div class="app-sidebar__overlay" data-toggle="sidebar"></div>
<aside class="app-sidebar">
<div class="app-sidebar__user">
<div>
<p class="app-sidebar__user-name"><?php echo htmlspecialchars((string)$fname.' '.(string)$lname); ?></p>
<p class="app-sidebar__user-designation"><?php echo htmlspecialchars((string)($designation ?? 'Accountant')); ?></p>
</div>
</div>
<ul class="app-menu">
<?php foreach ($accountantModules as $module): ?>
<?php
	$moduleKey = (string)($module['key'] ?? '');
	$currentGroup = accountant_sidebar_group_label($moduleKey);
	$shouldRenderHeading = $currentGroup !== $lastAccountantGroup;
	if ($shouldRenderHeading) {
		$lastAccountantGroup = $currentGroup;
	}
?>
<?php if ($shouldRenderHeading): ?>
<li class="px-3 pt-3 pb-1 text-uppercase" style="font-size:.7rem;letter-spacing:.12em;color:#6f7e8f;font-weight:800;"><?php echo htmlspecialchars($currentGroup); ?></li>
<?php endif; ?>
<li><a class="app-menu__item<?php echo accountant_sidebar_is_active($module); ?>" href="<?php echo htmlspecialchars((string)$module['href']); ?>"><i class="app-menu__icon <?php echo htmlspecialchars((string)$module['icon']); ?>"></i><span class="app-menu__label"><?php echo htmlspecialchars((string)$module['label']); ?></span></a></li>
<?php endforeach; ?>
</ul>
</aside>
