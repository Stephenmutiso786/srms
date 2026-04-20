<?php
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$studentPages = ['register_students', 'import_students', 'manage_students', 'students', 'student_leaders', 'discipline'];
$isStudentsOpen = in_array($currentPage, $studentPages, true);
$certificatePages = ['certificates', 'promotions', 'promotion_approvals'];
$isCertificatesOpen = in_array($currentPage, $certificatePages, true);
$examPages = ['exams', 'exam_timetable', 'marks_review', 'publish_results', 'results_analytics', 'results_locks', 'report', 'report_settings', 'merit_list'];
$isExamsOpen = in_array($currentPage, $examPages, true);

function app_menu_active($page)
{
  global $currentPage;
  return $currentPage === $page ? ' active' : '';
}

function app_tree_active($page)
{
  global $currentPage;
  return $currentPage === $page ? ' active' : '';
}
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
    <li><a class="app-menu__item<?php echo app_menu_active('index'); ?>" href="admin"><i class="app-menu__icon feather icon-monitor"></i><span class="app-menu__label">Dashboard</span></a></li>
    <li><a class="app-menu__item<?php echo app_menu_active('academic'); ?>" href="admin/academic"><i class="app-menu__icon feather icon-user"></i><span class="app-menu__label">Academic Account</span></a></li>
    <li><a class="app-menu__item<?php echo app_menu_active('teachers'); ?>" href="admin/teachers"><i class="app-menu__icon feather icon-user"></i><span class="app-menu__label">Teachers</span></a></li>
    <li><a class="app-menu__item<?php echo app_menu_active('classes'); ?>" href="admin/classes"><i class="app-menu__icon feather icon-home"></i><span class="app-menu__label">Class Management</span></a></li>
    <li><a class="app-menu__item<?php echo app_menu_active('terms'); ?>" href="admin/terms"><i class="app-menu__icon feather icon-folder"></i><span class="app-menu__label">Terms & Sessions</span></a></li>
    <li><a class="app-menu__item<?php echo app_menu_active('subjects'); ?>" href="admin/subjects"><i class="app-menu__icon feather icon-book"></i><span class="app-menu__label">Subject Catalog</span></a></li>
    <li><a class="app-menu__item<?php echo app_menu_active('teacher_allocation'); ?>" href="admin/teacher_allocation"><i class="app-menu__icon feather icon-users"></i><span class="app-menu__label">Subject Teachers</span></a></li>
    <li><a class="app-menu__item<?php echo app_menu_active('school_timetable'); ?>" href="admin/school_timetable"><i class="app-menu__icon feather icon-calendar"></i><span class="app-menu__label">School Timetable</span></a></li>
    <li class="treeview<?php echo $isStudentsOpen ? ' is-expanded' : ''; ?>">
      <a class="app-menu__item" href="javascript:void(0);" data-toggle="treeview"><i class="app-menu__icon feather icon-users"></i><span class="app-menu__label">Students</span><i class="treeview-indicator bi bi-chevron-right"></i></a>
      <ul class="treeview-menu">
        <li><a class="treeview-item<?php echo app_tree_active('discipline'); ?>" href="admin/discipline"><i class="icon bi bi-circle-fill"></i> Discipline Cases</a></li>
        <li><a class="treeview-item<?php echo app_tree_active('import_students'); ?>" href="admin/import_students"><i class="icon bi bi-circle-fill"></i> Import Students</a></li>
        <li><a class="treeview-item<?php echo app_tree_active('manage_students'); ?>" href="admin/manage_students"><i class="icon bi bi-circle-fill"></i> Manage Students</a></li>
        <li><a class="treeview-item<?php echo app_tree_active('register_students'); ?>" href="admin/register_students"><i class="icon bi bi-circle-fill"></i> Register Students</a></li>
        <li><a class="treeview-item<?php echo app_tree_active('student_leaders'); ?>" href="admin/student_leaders"><i class="icon bi bi-circle-fill"></i> Student Leadership</a></li>
      </ul>
    </li>
    <li><a class="app-menu__item<?php echo app_menu_active('parents'); ?>" href="admin/parents"><i class="app-menu__icon feather icon-user-plus"></i><span class="app-menu__label">Parents</span></a></li>
    <li><a class="app-menu__item<?php echo app_menu_active('attendance'); ?>" href="admin/attendance"><i class="app-menu__icon feather icon-check-square"></i><span class="app-menu__label">Attendance</span></a></li>
    <li><a class="app-menu__item<?php echo app_menu_active('staff_attendance'); ?>" href="admin/staff_attendance"><i class="app-menu__icon feather icon-clock"></i><span class="app-menu__label">Staff Attendance</span></a></li>
    <li><a class="app-menu__item<?php echo app_menu_active('fees'); ?>" href="admin/fees"><i class="app-menu__icon feather icon-credit-card"></i><span class="app-menu__label">Fees & Finance</span></a></li>
    <li><a class="app-menu__item<?php echo app_menu_active('import_export'); ?>" href="admin/import_export"><i class="app-menu__icon feather icon-upload-cloud"></i><span class="app-menu__label">Import / Export</span></a></li>
    <li><a class="app-menu__item<?php echo app_menu_active('communication'); ?>" href="admin/communication"><i class="app-menu__icon feather icon-message-circle"></i><span class="app-menu__label">Communication</span></a></li>
    <li><a class="app-menu__item<?php echo app_menu_active('sms_topup'); ?>" href="admin/sms_topup"><i class="app-menu__icon feather icon-credit-card"></i><span class="app-menu__label">SMS Tokens</span></a></li>
    <li><a class="app-menu__item<?php echo app_menu_active('elearning'); ?>" href="admin/elearning"><i class="app-menu__icon feather icon-book-open"></i><span class="app-menu__label">E-Learning</span></a></li>
    <li><a class="app-menu__item<?php echo app_menu_active('feedback'); ?>" href="admin/feedback"><i class="app-menu__icon feather icon-message-square"></i><span class="app-menu__label">AI & Feedback</span></a></li>
    <li><a class="app-menu__item<?php echo app_menu_active('library'); ?>" href="admin/library"><i class="app-menu__icon feather icon-book"></i><span class="app-menu__label">Library</span></a></li>
    <li><a class="app-menu__item<?php echo app_menu_active('inventory'); ?>" href="admin/inventory"><i class="app-menu__icon feather icon-box"></i><span class="app-menu__label">Inventory</span></a></li>
    <li><a class="app-menu__item<?php echo app_menu_active('transport'); ?>" href="admin/transport"><i class="app-menu__icon feather icon-truck"></i><span class="app-menu__label">Transport</span></a></li>
    <li class="treeview<?php echo $isExamsOpen ? ' is-expanded' : ''; ?>">
      <a class="app-menu__item" href="javascript:void(0);" data-toggle="treeview"><i class="app-menu__icon feather icon-file-text"></i><span class="app-menu__label">Exams</span><i class="treeview-indicator bi bi-chevron-right"></i></a>
      <ul class="treeview-menu">
        <li><a class="treeview-item<?php echo app_tree_active('exams'); ?>" href="admin/exams"><i class="icon bi bi-circle-fill"></i> Exams</a></li>
        <li><a class="treeview-item<?php echo app_tree_active('exam_timetable'); ?>" href="admin/exam_timetable"><i class="icon bi bi-circle-fill"></i> Exam Timetable</a></li>
        <li><a class="treeview-item<?php echo app_tree_active('marks_review'); ?>" href="admin/marks_review"><i class="icon bi bi-circle-fill"></i> Marks Review</a></li>
        <li><a class="treeview-item<?php echo app_tree_active('publish_results'); ?>" href="admin/publish_results"><i class="icon bi bi-circle-fill"></i> Publish Results</a></li>
        <li><a class="treeview-item<?php echo app_tree_active('results_analytics'); ?>" href="admin/results_analytics"><i class="icon bi bi-circle-fill"></i> Results Analytics</a></li>
        <li><a class="treeview-item<?php echo app_tree_active('results_locks'); ?>" href="admin/results_locks"><i class="icon bi bi-circle-fill"></i> Results Locks</a></li>
        <li><a class="treeview-item<?php echo app_tree_active('report'); ?>" href="admin/report"><i class="icon bi bi-circle-fill"></i> Report Tool</a></li>
        <li><a class="treeview-item<?php echo app_tree_active('merit_list'); ?>" href="admin/merit_list"><i class="icon bi bi-circle-fill"></i> Merit List</a></li>
        <li><a class="treeview-item<?php echo app_tree_active('report_settings'); ?>" href="admin/report_settings"><i class="icon bi bi-circle-fill"></i> Report Settings</a></li>
      </ul>
    </li>
    <li class="treeview<?php echo $isCertificatesOpen ? ' is-expanded' : ''; ?>">
      <a class="app-menu__item" href="javascript:void(0);" data-toggle="treeview"><i class="app-menu__icon feather icon-award"></i><span class="app-menu__label">Certificates</span><i class="treeview-indicator bi bi-chevron-right"></i></a>
      <ul class="treeview-menu">
        <li><a class="treeview-item<?php echo app_tree_active('certificates'); ?>" href="admin/certificates"><i class="icon bi bi-circle-fill"></i> Generate Certificates</a></li>
        <li><a class="treeview-item<?php echo app_tree_active('promotion_rules'); ?>" href="admin/promotion_rules"><i class="icon bi bi-circle-fill"></i> Promotion Rules</a></li>
        <li><a class="treeview-item<?php echo app_tree_active('promotions'); ?>" href="admin/promotions"><i class="icon bi bi-circle-fill"></i> Student Promotions</a></li>
      </ul>
    </li>
    <li><a class="app-menu__item<?php echo app_menu_active('analytics_engine'); ?>" href="admin/analytics_engine"><i class="app-menu__icon feather icon-activity"></i><span class="app-menu__label">Analytics Engine</span></a></li>
    <li><a class="app-menu__item<?php echo app_menu_active('benchmarking'); ?>" href="admin/benchmarking"><i class="app-menu__icon feather icon-trending-up"></i><span class="app-menu__label">Benchmarking</span></a></li>
    <li><a class="app-menu__item<?php echo app_menu_active('notifications'); ?>" href="admin/notifications"><i class="app-menu__icon feather icon-bell"></i><span class="app-menu__label">Notifications</span></a></li>
    <li><a class="app-menu__item<?php echo app_menu_active('online_users'); ?>" href="admin/online_users"><i class="app-menu__icon feather icon-wifi"></i><span class="app-menu__label">Online Users</span></a></li>
    <li><a class="app-menu__item<?php echo app_menu_active('audit_logs'); ?>" href="admin/audit_logs"><i class="app-menu__icon feather icon-shield"></i><span class="app-menu__label">Audit Logs</span></a></li>
    <li><a class="app-menu__item<?php echo app_menu_active('roles'); ?>" href="admin/roles"><i class="app-menu__icon feather icon-shield"></i><span class="app-menu__label">Roles & Permissions</span></a></li>
    <li><a class="app-menu__item<?php echo app_menu_active('role_matrix'); ?>" href="admin/role_matrix"><i class="app-menu__icon feather icon-grid"></i><span class="app-menu__label">Role Matrix</span></a></li>
    <li><a class="app-menu__item<?php echo app_menu_active('bom'); ?>" href="admin/bom"><i class="app-menu__icon feather icon-briefcase"></i><span class="app-menu__label">BOM Management</span></a></li>
    <li><a class="app-menu__item<?php echo app_menu_active('mpesa'); ?>" href="admin/mpesa"><i class="app-menu__icon feather icon-smartphone"></i><span class="app-menu__label">M-Pesa</span></a></li>
    <li><a class="app-menu__item<?php echo app_menu_active('smtp'); ?>" href="admin/smtp"><i class="app-menu__icon feather icon-mail"></i><span class="app-menu__label">SMTP Settings</span></a></li>
    <li><a class="app-menu__item<?php echo app_menu_active('system_diagnostics'); ?>" href="admin/system_diagnostics"><i class="app-menu__icon feather icon-activity"></i><span class="app-menu__label">System Diagnostics</span></a></li>
    <li><a class="app-menu__item<?php echo app_menu_active('migrations'); ?>" href="admin/migrations"><i class="app-menu__icon feather icon-database"></i><span class="app-menu__label">Migrations</span></a></li>
    <li><a class="app-menu__item<?php echo app_menu_active('module_locks'); ?>" href="admin/module_locks"><i class="app-menu__icon feather icon-lock"></i><span class="app-menu__label">Module Locks</span></a></li>
    <li><a class="app-menu__item<?php echo app_menu_active('system'); ?>" href="admin/system"><i class="app-menu__icon feather icon-settings"></i><span class="app-menu__label">System Settings</span></a></li>
    <li><a class="app-menu__item<?php echo app_menu_active('system_diagnostics'); ?>" href="admin/system_diagnostics"><i class="app-menu__icon feather icon-activity"></i><span class="app-menu__label">System Diagnostics</span></a></li>
    <li><a class="app-menu__item" href="how_system_works"><i class="app-menu__icon feather icon-help-circle"></i><span class="app-menu__label">How The System Works</span></a></li>
  </ul>
  <div class="app-sidebar__footer">
    <a class="app-sidebar__footer-link" href="privacy" target="_blank"><i class="bi bi-shield-lock me-2"></i>Privacy Policy</a>
    <a class="app-sidebar__footer-link" href="terms" target="_blank"><i class="bi bi-file-text me-2"></i>Terms & Conditions</a>
  </div>
</aside>
