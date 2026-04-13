<?php
if (isset($_SESSION['reply'])) {
$alert_type = $_SESSION['reply'][0][0];
$alert_msg = $_SESSION['reply'][0][1];
$alert_html = isset($_SESSION['reply'][0][2]['html']) ? $_SESSION['reply'][0][2]['html'] : '';

if ($alert_type == "danger") {
$not_icon = "error";
}else{
$not_icon = $alert_type;
}
?>

<Script>
(function () {
	if (typeof Swal !== 'undefined' && Swal && typeof Swal.fire === 'function') {
		Swal.fire({
			title: <?php echo json_encode($alert_msg); ?>,
			<?php if ($alert_html !== '') { ?>
			html: <?php echo json_encode($alert_html); ?>,
			<?php } ?>
			icon: '<?php echo $not_icon; ?>',
			showDenyButton: false,
			confirmButtonText: 'Okay',
			width: '900px',
		});
		return;
	}
	window.alert(<?php echo json_encode($alert_msg); ?>);
})();
</script>
<?php
unset($_SESSION['reply']);
}
?>
