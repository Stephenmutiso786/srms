<?php
session_start();
chdir('../../');
require_once('db/config.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

$id = $_POST['id'];

try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $conn->prepare("SELECT * FROM tbl_announcements WHERE id = ?");
$stmt->execute([$id]);
$result = $stmt->fetchAll();

foreach($result as $row)
{
?>

<form class="app_frm" method="POST" autocomplete="OFF" action="academic/core/update_announcement">
<div class="mb-2">
<label class="form-label">Enter Title</label>
<input required value="<?php echo $row[1]; ?>" type="text" name="title" class="form-control txt-cap" placeholder="Enter Announcement Title">
</div>

<div class="mb-3">
<label class="form-label">Audience</label>
<select class="form-control" name="audience" required>
<option selected disabled value="">Select one</option>
<option <?php if ($row[4] == "1") { print ' selected '; } ?> value="1">Students Only</option>
<option <?php if ($row[4] == "0") { print ' selected '; } ?> value="0">Teachers Only</option>
<option <?php if ($row[4] == "2") { print ' selected '; } ?> value="2">Students & Teachers</option>
</select>
</div>

<div class="mb-3">
<label class="form-label">Announcement</label>
<textarea  class="form-control" name="announcement" id="summernote2" required><?php echo $row[2]; ?></textarea>
<script>

</script>
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
