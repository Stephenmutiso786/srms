<?php
if (isset($_SESSION['reply'])) {
$alert_type = $_SESSION['reply'][0][0];
$alert_msg = $_SESSION['reply'][0][1];

if ($alert_type == "danger") {
$not_icon = "error";
}else{
$not_icon = $alert_type;
}
?>

<Script>
Swal.fire({
title: '<?php echo $alert_msg; ?>',
icon: '<?php echo $not_icon; ?>',
showDenyButton: false,
confirmButtonText: 'Okay',
})
</script>
<?php
unset($_SESSION['reply']);
}
?>
