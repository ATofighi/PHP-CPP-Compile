<?php
error_reporting(E_ALL^E_NOTICE);
function kill($pid){ 
    return stripos(php_uname('s'), 'win')>-1  ? exec("taskkill /F /T /PID $pid") : exec("kill -9 $pid");
}

function removeFiles() {
	global $cwd;
	return;
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

	if (file_exists($cwd.'/myfile.exe')) {
		@unlink($cwd.'/myfile.exe');
	}

	if (file_exists($cwd.'/output.txt')) {
		@unlink($cwd.'/output.txt');
	}
	@rmdir($cwd);
}

$hash = md5(uniqid(microtime(true), true));

$cwd = dirname(__FILE__).'/_compile/'.$hash.'/';
@mkdir($cwd);
file_put_contents($cwd.'file.cpp', $_POST['code']);
file_put_contents($cwd.'input.txt', $_POST['input']);
// Time limit for the current program to execute
$time_limit = min((int)$_POST['time_limit'], 10000);
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

if(file_exists($cwd.'myfile.exe')) {
	@unlink($cwd.'myfile.exe');
}

$process = proc_open('g++ file.cpp -O2 -o myfile.exe -ftime-report -fmem-report', $compiler_descriptorspec, $pipes, $cwd);
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


// Start the Counter
$start_time = time();

// Start the program execution
$process = proc_open('myfile.exe', $descriptorspec, $pipes, $cwd, $env);

// Time to sleep, for the program to complete
usleep($time_limit*1000);

// Now awake up to see if the execution is complete
$status = proc_get_status($process);

if($status['running']) {
	// If the process is still running, terminate it
	kill($status['pid']);
	proc_terminate($process);

	@unlink($cwd.'myfile.exe');
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
	
	if($return_value == 0) {
		$command_output = str_replace("\r\n", "\n", rtrim(trim(file_get_contents($cwd.'output.txt'))));
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
