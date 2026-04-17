<?php
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
function parent_menu_active($page)
{
    global $currentPage;
    return $currentPage === $page ? ' active' : '';
}
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
	<li><a class="app-menu__item<?php echo parent_menu_active('attendance'); ?>" href="parent/attendance"><i class="app-menu__icon feather icon-check-square"></i><span class="app-menu__label">Attendance</span></a></li>
    <li><a class="app-menu__item<?php echo parent_menu_active('index'); ?>" href="parent"><i class="app-menu__icon feather icon-monitor"></i><span class="app-menu__label">Dashboard</span></a></li>
	<li><a class="app-menu__item<?php echo parent_menu_active('discipline'); ?>" href="parent/discipline"><i class="app-menu__icon feather icon-alert-triangle"></i><span class="app-menu__label">Discipline</span></a></li>
	<li><a class="app-menu__item<?php echo parent_menu_active('elearning'); ?>" href="parent/elearning"><i class="app-menu__icon feather icon-laptop"></i><span class="app-menu__label">E-Learning</span></a></li>
    <li><a class="app-menu__item<?php echo parent_menu_active('fees'); ?>" href="parent/fees"><i class="app-menu__icon feather icon-credit-card"></i><span class="app-menu__label">Fees</span></a></li>
    <li><a class="app-menu__item<?php echo parent_menu_active('how_system_works'); ?>" href="how_system_works"><i class="app-menu__icon feather icon-help-circle"></i><span class="app-menu__label">How The System Works</span></a></li>
	<li><a class="app-menu__item<?php echo parent_menu_active('report_card'); ?>" href="parent/report_card"><i class="app-menu__icon feather icon-file-text"></i><span class="app-menu__label">Report Card</span></a></li>
  </ul>
</aside>
