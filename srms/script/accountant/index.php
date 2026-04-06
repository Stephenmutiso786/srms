<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
if ($res == "1" && $level == "5") {}else{header("location:../"); exit;}
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title><?php echo APP_NAME; ?> - Accountant Dashboard</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
<link type="text/css" rel="stylesheet" href="loader/waitMe.css">
</head>
<body class="app sidebar-mini">

<header class="app-header"><a class="app-header__logo" href="javascript:void(0);"><?php echo APP_NAME; ?></a>
<a class="app-sidebar__toggle" href="#" data-toggle="sidebar" aria-label="Hide Sidebar"></a>

<ul class="app-nav">
<li class="dropdown"><a class="app-nav__item" href="#" data-bs-toggle="dropdown" aria-label="Open Profile Menu"><i class="bi bi-person fs-4"></i></a>
<ul class="dropdown-menu settings-menu dropdown-menu-right">
<li><a class="dropdown-item" href="logout"><i class="bi bi-box-arrow-right me-2 fs-5"></i> Logout</a></li>
</ul>
</li>
</ul>
</header>

<div class="app-sidebar__overlay" data-toggle="sidebar"></div>
<aside class="app-sidebar">
<div class="app-sidebar__user">
<div>
<p class="app-sidebar__user-name"><?php echo $fname.' '.$lname; ?></p>
<p class="app-sidebar__user-designation">Accountant</p>
</div>
</div>
<ul class="app-menu">
<li><a class="app-menu__item active" href="accountant"><i class="app-menu__icon feather icon-monitor"></i><span class="app-menu__label">Dashboard</span></a></li>
<li><a class="app-menu__item" href="accountant/fees"><i class="app-menu__icon feather icon-credit-card"></i><span class="app-menu__label">Fees & Finance</span></a></li>
<li><a class="app-menu__item" href="accountant/fee_structure"><i class="app-menu__icon feather icon-sliders"></i><span class="app-menu__label">Fee Structure</span></a></li>
<li><a class="app-menu__item" href="accountant/invoices"><i class="app-menu__icon feather icon-file-text"></i><span class="app-menu__label">Invoices</span></a></li>
</ul>
</aside>

<main class="app-content">
<div class="app-title">
<div>
<h1>Accountant Dashboard</h1>
<p>Manage fee structures, invoices, and payments.</p>
</div>
</div>

<div class="row">
  <div class="col-lg-4 mb-3">
	<div class="tile">
	  <h3 class="tile-title">Quick Links</h3>
	  <div class="d-grid gap-2">
		<a class="btn btn-primary" href="accountant/fees"><i class="bi bi-credit-card me-1"></i>Fees Overview</a>
		<a class="btn btn-outline-primary" href="accountant/fee_structure"><i class="bi bi-sliders me-1"></i>Fee Structure</a>
		<a class="btn btn-outline-primary" href="accountant/invoices"><i class="bi bi-file-text me-1"></i>Invoices & Payments</a>
	  </div>
	</div>
  </div>
</div>

</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
</body>
</html>

