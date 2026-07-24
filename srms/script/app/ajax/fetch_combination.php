<?php
session_start();
chdir('../../');
require_once('db/config.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

$id = $_POST['id'];

try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $conn->prepare("SELECT id, class, subject, teacher FROM tbl_subject_combinations WHERE id = ?");
$stmt->execute([$id]);
$result = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach($result as $rowx)
{
$cls = app_unserialize((string)($rowx['class'] ?? ''));
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

$stmt = $conn->prepare("SELECT id, name FROM tbl_subjects ORDER BY name");
$stmt->execute();
$result = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach($result as $row)
{
?>
<option <?php if ((string)($rowx['subject'] ?? '') === (string)$row['id']) { print ' selected '; }?> value="<?php echo (int)$row['id']; ?>"><?php echo htmlspecialchars((string)$row['name']); ?> </option>
<?php
}

}catch(PDOException $e)
{
error_log("[".__FILE__.":".__LINE__." PDO] " . $e->getMessage());
echo "Connection failed.";
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

$stmt = $conn->prepare("SELECT id, name FROM tbl_classes ORDER BY name");
$stmt->execute();
$result = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach($result as $row)
{
if (in_array((string)$row['id'], array_map('strval', $cls), true))
{
?><option selected value="<?php echo (int)$row['id']; ?>"><?php echo htmlspecialchars((string)$row['name']); ?> </option><?php
}
else
{
?><option value="<?php echo (int)$row['id']; ?>"><?php echo htmlspecialchars((string)$row['name']); ?> </option><?php
}

?>

<?php
}

}catch(PDOException $e)
{
error_log("[".__FILE__.":".__LINE__." PDO] " . $e->getMessage());
echo "Connection failed.";
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

$stmt = $conn->prepare("SELECT id, fname, lname FROM tbl_staff WHERE level = '2'");
$stmt->execute();
$result = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach($result as $row)
{
?>
<option <?php if ((string)($rowx['teacher'] ?? '') === (string)$row['id']) { print ' selected '; }?> value="<?php echo (int)$row['id']; ?>"><?php echo htmlspecialchars(trim((string)$row['fname'] . ' ' . (string)$row['lname'])); ?> </option>
<?php
}

}catch(PDOException $e)
{
error_log("[".__FILE__.":".__LINE__." PDO] " . $e->getMessage());
echo "Connection failed.";
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
error_log("[".__FILE__.":".__LINE__." PDO] " . $e->getMessage());
echo "Connection failed.";
}

}
?>
