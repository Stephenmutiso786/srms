<?php
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
function teacher_menu_active($page)
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
      <p class="app-sidebar__user-designation"><?php echo htmlspecialchars((string)($designation ?? 'Teacher')); ?></p>
    </div>
  </div>
  <ul class="app-menu">
	<li><a class="app-menu__item<?php echo teacher_menu_active('attendance'); ?>" href="teacher/attendance"><i class="app-menu__icon feather icon-check-square"></i><span class="app-menu__label">Attendance</span></a></li>
    <li><a class="app-menu__item<?php echo teacher_menu_active('index'); ?>" href="teacher"><i class="app-menu__icon feather icon-monitor"></i><span class="app-menu__label">Dashboard</span></a></li>
	<li><a class="app-menu__item<?php echo teacher_menu_active('discipline'); ?>" href="teacher/discipline"><i class="app-menu__icon feather icon-alert-triangle"></i><span class="app-menu__label">Discipline</span></a></li>
	<li><a class="app-menu__item<?php echo teacher_menu_active('elearning'); ?>" href="teacher/elearning"><i class="app-menu__icon feather icon-book-open"></i><span class="app-menu__label">E-Learning</span></a></li>
	<li><a class="app-menu__item<?php echo teacher_menu_active('how_system_works'); ?>" href="teacher/how_system_works"><i class="app-menu__icon feather icon-help-circle"></i><span class="app-menu__label">How The System Works</span></a></li>
    <li><a class="app-menu__item<?php echo teacher_menu_active('exam_marks_entry'); ?>" href="teacher/exam_marks_entry"><i class="app-menu__icon feather icon-edit-3"></i><span class="app-menu__label">Marks Entry</span></a></li>
    <li><a class="app-menu__item<?php echo teacher_menu_active('manage_results'); ?>" href="teacher/manage_results"><i class="app-menu__icon feather icon-graph"></i><span class="app-menu__label">Results</span></a></li>
    <li><a class="app-menu__item<?php echo teacher_menu_active('students'); ?>" href="teacher/students"><i class="app-menu__icon feather icon-users"></i><span class="app-menu__label">Students</span></a></li>
  </ul>
</aside>
