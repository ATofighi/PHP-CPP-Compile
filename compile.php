<?php
error_reporting(E_ALL^E_NOTICE);
/* ?>
<form method="post">
	<textarea name="code" style="width: 100%;height: 100px;" placeholder="CODE"><?php echo htmlspecialchars($_POST['code']); ?></textarea>
	<textarea name="input" style="width: 100%;height: 100px;" placeholder="Input"><?php echo htmlspecialchars($_POST['input']); ?></textarea>
	<input type="text" value="<?php echo htmlspecialchars($_POST['time_limit']); ?>" name="time_limit" placeholder="Time Limit">
	<input type="text" value="<?php echo htmlspecialchars($_POST['memory_limit']); ?>" name="memory_limit" placeholder="Memory Limit">
	<input type="submit">
</form>
<?php /**/
function kill($pid){ 
    return stripos(php_uname('s'), 'win')>-1  ? exec("taskkill /F /T /PID $pid") : exec("kill -9 $pid");
}

function removeFiles() {
//return;
	global $cwd;
	if (file_exists($cwd.'/error-output.txt')) {
		@unlink($cwd.'/error-output.txt');
	}

	if (file_exists($cwd.'/file.cpp')) {
		@unlink($cwd.'/file.cpp');
	}

	if (file_exists($cwd.'/input.txt')) {
		@unlink($cwd.'/input.txt');
	}

	if (file_exists($cwd.'/myfile.compile.output')) {
		@unlink($cwd.'/myfile.compile.output');
	}

	if (file_exists($cwd.'/myfile.out')) {
		@unlink($cwd.'/myfile.out');
	}

	if (file_exists($cwd.'/output.txt')) {
		@unlink($cwd.'/output.txt');
	}
	@rmdir($cwd);
}
if($_POST['code']) {
	$hash = md5(uniqid(microtime(true), true));
	$path = dirname(__FILE__);
	$cwd = $path.'/_compile/'.$hash.'/';
	@mkdir($cwd, 0777);
	file_put_contents($cwd.'file.cpp', $_POST['code']);
	chmod($cwd.'file.cpp', 0777);
	file_put_contents($cwd.'input.txt', $_POST['input']);
	chmod($cwd.'input.txt', 0777);
	// Time limit for the current program to execute
	$time_limit = min((int)$_POST['time_limit'], 10000);
	// Memory limit for the current program to execute
	$memory_limit = min((int)$_POST['memory_limit'], 1000000);
	// Environmental variable values to set for execution
	$env = array();
	// Start time of the script
	$start_time = 0;
	// End time of the script
	$end_time = 1;


	$compiler_descriptorspec = array(
	   0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
	   1 => array("file", "{$cwd}myfile.compile.output", "w"),  // stdout is a file that the child will write to
	   2 => array("file", "{$cwd}error-output.txt", "w") // stderr is a file to write to
	);


	$process = proc_open('g++ file.cpp -O2 -o myfile.out -ftime-report -fmem-report', $compiler_descriptorspec, $pipes, $cwd);
	// Validate the compiler output
	if (is_resource($process)) {
	    $return_value = proc_close($process);

		// echo "Compiler output is $return_value";
		if($return_value != 0) 
		{
			echo json_encode(array(
				'type' => 'ERROR',
				'content' => 'Compile Error'
			));removeFiles();exit;
		}
	
	}

	// Step 2 - Execute the program, only if there is no compile error
	$descriptorspec = array(
	   0 => array("file", "{$cwd}input.txt", "r"),  // stdin is a pipe that the child will read from
	   1 => array("file", "{$cwd}output.txt", "w"),  // stdout is a pipe that the child will write to
	   2 => array("file", "{$cwd}error-output.txt", "a") // stderr is a file to write to
	);

	chmod($cwd.'myfile.out', 0777);
	// Start the Counter
	$start_time = time();

	// Start the program execution
	$process = proc_open("ulimit -Sv {$memory_limit}\nLD_PRELOAD={$path}/sandbox/EasySandbox.so {$cwd}/myfile.out", $descriptorspec, $pipes, $cwd, $env);

	// Time to sleep, for the program to complete
	usleep($time_limit*1000);

	// Now awake up to see if the execution is complete
	$status = proc_get_status($process);

	if($status['running']) {
		// If the process is still running, terminate it
		kill($status['pid']);
		proc_terminate($process);

		echo json_encode(array(
				'type' => 'ERROR',
				'content' => 'Time limit exceeded'
			));removeFiles();exit;
	}

	if(is_resource($process)) {
	    // It is important that you close any pipes before calling
	    // proc_close in order to avoid a deadlock
	    $return_value = proc_close($process);
		$end_time = time();
		// echo "Total Time taken - " . date('H:m:s', $end_time - $start_time);
//		echo $return_value;
		list($enteringSECCOMPmode, $command_output) = explode("\n", str_replace("\r\n", "\n", rtrim(trim(file_get_contents($cwd.'output.txt')))), 2);
		if($return_value == 0 || $command_output) {
			echo json_encode(array(
					'type' => 'SUCCESS',
					'content' => $command_output
				));removeFiles();exit;
		} else {
			echo json_encode(array(
					'type' => 'ERROR',
					'content' => 'Run Time Error'
				));removeFiles();exit;
		}
	} else {
		echo json_encode(array(
				'type' => 'ERROR',
				'content' => 'Someting is Wrong with compiler'
			));removeFiles();exit;
	}
}
