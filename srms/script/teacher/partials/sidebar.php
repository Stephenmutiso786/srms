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
    <?php if (app_current_user_has_any_permission(['timetable.manage', 'exams.manage'])): ?>
    <li><a class="app-menu__item<?php echo teacher_menu_active('exam_timetable'); ?>" href="teacher/exam_timetable"><i class="app-menu__icon feather icon-calendar"></i><span class="app-menu__label">Exam Timetable</span></a></li>
    <?php endif; ?>
    <?php if (app_current_user_has_any_permission(['exams.manage', 'academic.manage'])): ?>
    <li><a class="app-menu__item<?php echo teacher_menu_active('grading-system'); ?>" href="teacher/grading-system"><i class="app-menu__icon feather icon-award"></i><span class="app-menu__label">Grading System</span></a></li>
    <?php endif; ?>
    <?php if (app_current_user_has_permission('academic.manage')): ?>
    <li><a class="app-menu__item<?php echo teacher_menu_active('elearning'); ?>" href="teacher/elearning"><i class="app-menu__icon feather icon-book-open"></i><span class="app-menu__label">E-Learning</span></a></li>
    <?php endif; ?>
    <?php if (app_current_user_has_any_permission(['teacher.allocate', 'academic.manage'])): ?>
    <li><a class="app-menu__item<?php echo teacher_menu_active_any(['combinations', 'import_results']); ?>" href="teacher/combinations"><i class="app-menu__icon feather icon-book-open"></i><span class="app-menu__label">Subject Combinations</span></a></li>
    <?php endif; ?>
    <?php if (app_current_user_has_permission('staff.manage')): ?>
    <li><a class="app-menu__item<?php echo teacher_menu_active('roles'); ?>" href="teacher/roles"><i class="app-menu__icon feather icon-shield"></i><span class="app-menu__label">Roles</span></a></li>
    <?php endif; ?>
    <li><a class="app-menu__item<?php echo teacher_menu_active('how_system_works'); ?>" href="teacher/how_system_works"><i class="app-menu__icon feather icon-help-circle"></i><span class="app-menu__label">How The System Works</span></a></li>
  </ul>
</aside>
