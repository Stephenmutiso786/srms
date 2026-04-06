<?php
session_start();
chdir('../../');
require_once('db/config.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

$id = $_POST['id'];

try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $conn->prepare("SELECT * FROM tbl_subject_combinations WHERE id = ?");
$stmt->execute([$id]);
$result = $stmt->fetchAll();

foreach($result as $rowx)
{
$cls = app_unserialize($rowx[1]);
?>

<form class="app_frm" method="POST" autocomplete="OFF" action="academic/core/update_comb">


<div class="mb-2">
<label class="form-label">Select Subject</label>
<select class="form-control select3" name="subject" required style="width: 100%;">
<option selected disabled value="">Select one</option>
<?php
try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $conn->prepare("SELECT * FROM tbl_subjects ORDER BY name");
$stmt->execute();
$result = $stmt->fetchAll();

foreach($result as $row)
{
?>
<option <?php if ($rowx[2] == $row[0]) { print ' selected '; }?> value="<?php echo $row[0]; ?>"><?php echo $row[1]; ?> </option>
<?php
}

}catch(PDOException $e)
{
echo "Connection failed: " . $e->getMessage();
}
?>
</select>
</div>


<div class="mb-2">
<label class="form-label">Select Class</label>
<select multiple="true" class="form-control select3" name="class[]" required style="width: 100%;">
<?php
try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $conn->prepare("SELECT * FROM tbl_classes");
$stmt->execute();
$result = $stmt->fetchAll();

foreach($result as $row)
{
if (in_array($row[0], $cls))
{
?><option selected value="<?php echo $row[0]; ?>"><?php echo $row[1]; ?> </option><?php
}
else
{
?><option value="<?php echo $row[0]; ?>"><?php echo $row[1]; ?> </option><?php
}

?>

<?php
}

}catch(PDOException $e)
{
echo "Connection failed: " . $e->getMessage();
}
?>
</select>
</div>

<div class="mb-3">
<label class="form-label">Select Teacher</label>
<select class="form-control select3" name="teacher" required style="width: 100%;">
<option selected disabled value="">Select one</option>
<?php
try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $conn->prepare("SELECT * FROM tbl_staff WHERE level = '2'");
$stmt->execute();
$result = $stmt->fetchAll();

foreach($result as $row)
{
?>
<option <?php if ($rowx[3] == $row[0]) { print ' selected '; }?> value="<?php echo $row[0]; ?>"><?php echo $row[1].' '.$row[2]; ?> </option>
<?php
}

}catch(PDOException $e)
{
echo "Connection failed: " . $e->getMessage();
}
?>
</select>
</div>
<input type="hidden" name="id" value="<?php echo $id; ?>">
<button type="submit" name="submit" value="1" class="btn btn-primary app_btn">Save</button>
<button type="button" class="btn btn-danger" data-bs-dismiss="modal">Close</button>
</form>

<?php
}
}catch(PDOException $e)
{
echo "Connection failed: " . $e->getMessage();
}

}
?>
