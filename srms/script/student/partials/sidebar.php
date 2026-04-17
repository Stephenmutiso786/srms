<?php
$currentPage = basename($_SERVER['PHP_SELF'], '.php');

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
    <li><a class="app-menu__item<?php echo student_menu_active('attendance'); ?>" href="student/attendance"><i class="app-menu__icon feather icon-check-square"></i><span class="app-menu__label">Attendance</span></a></li>
    <li><a class="app-menu__item<?php echo student_menu_active('certificates'); ?>" href="student/certificates"><i class="app-menu__icon feather icon-award"></i><span class="app-menu__label">Certificates</span></a></li>
    <li><a class="app-menu__item<?php echo student_menu_active('index'); ?>" href="student"><i class="app-menu__icon feather icon-monitor"></i><span class="app-menu__label">Dashboard</span></a></li>
    <li><a class="app-menu__item<?php echo student_menu_active('discipline'); ?>" href="student/discipline"><i class="app-menu__icon feather icon-alert-triangle"></i><span class="app-menu__label">Discipline</span></a></li>
    <li><a class="app-menu__item<?php echo student_menu_active('division-system'); ?>" href="student/division-system"><i class="app-menu__icon feather icon-layers"></i><span class="app-menu__label">Division System</span></a></li>
    <li><a class="app-menu__item<?php echo student_menu_active('elearning'); ?>" href="student/elearning"><i class="app-menu__icon feather icon-book-open"></i><span class="app-menu__label">E-Learning</span></a></li>
    <li><a class="app-menu__item<?php echo student_menu_active('exam_timetable'); ?>" href="student/exam_timetable"><i class="app-menu__icon feather icon-calendar"></i><span class="app-menu__label">Exam Timetable</span></a></li>
    <li><a class="app-menu__item<?php echo student_menu_active('fees'); ?>" href="student/fees"><i class="app-menu__icon feather icon-credit-card"></i><span class="app-menu__label">Fees</span></a></li>
    <li><a class="app-menu__item<?php echo student_menu_active('grading-system'); ?>" href="student/grading-system"><i class="app-menu__icon feather icon-award"></i><span class="app-menu__label">Grading System</span></a></li>
    <li><a class="app-menu__item<?php echo student_menu_active('leadership'); ?>" href="student/leadership"><i class="app-menu__icon feather icon-users"></i><span class="app-menu__label">Leadership</span></a></li>
    <li><a class="app-menu__item<?php echo student_menu_active_any(['view', 'profile', 'id_card']); ?>" href="student/view"><i class="app-menu__icon feather icon-user"></i><span class="app-menu__label">Profile</span></a></li>
    <li><a class="app-menu__item<?php echo student_menu_active('ranking'); ?>" href="student/ranking"><i class="app-menu__icon feather icon-bar-chart-2"></i><span class="app-menu__label">Ranking</span></a></li>
    <li><a class="app-menu__item<?php echo student_menu_active('report_card'); ?>" href="student/report_card"><i class="app-menu__icon feather icon-file-text"></i><span class="app-menu__label">Report Card</span></a></li>
    <li><a class="app-menu__item<?php echo student_menu_active('results'); ?>" href="student/results"><i class="app-menu__icon feather icon-file-text"></i><span class="app-menu__label">Results</span></a></li>
    <li><a class="app-menu__item<?php echo student_menu_active('settings'); ?>" href="student/settings"><i class="app-menu__icon feather icon-settings"></i><span class="app-menu__label">Settings</span></a></li>
    <li><a class="app-menu__item<?php echo student_menu_active('subjects'); ?>" href="student/subjects"><i class="app-menu__icon feather icon-book"></i><span class="app-menu__label">Subjects</span></a></li>
  </ul>
</aside>
