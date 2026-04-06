<?php
if (isset($_SESSION['export_file'])) {
$file = $_SESSION['export_file'];
?>

<Script>
Swal.fire({
title: 'Export Completed',
text: 'CSV File generated, click the button below to download',
icon: 'success',
showDenyButton: false,
confirmButtonText: 'Download',
denyButtonText: 'Download'
}).then((result) => {
if (result.isDenied) {

} else if (result.Confirmed) {
<?php
$files = 'import_sheets/'.$file;
if (file_exists($files)) {
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename='.basename($files));
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
header('Content-Length: ' . filesize($files));
ob_clean();
flush();
readfile($files);
}
?>
}
});
</script>
<?php
unset($_SESSION['export_file']);
}
?>
