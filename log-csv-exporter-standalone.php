<?php

/*

log-csv-exporter-standalone.php
===============================

Exports your conversation log in a CSV format. Standalone version with credential input. Designed for use with IBM Watson Assistant.


Metadata
--------

| Data       | Value                            |
| ----------:| -------------------------------- |
|    Version | 1.1.1                            |
|    Updated | 2019-03-25T03:11:42+00:00        |
|     Author | Adam Newbold, https://adam.lol   |
| Maintainer | Neatnik LLC, https://neatnik.net |
|   Requires | PHP 5.6 or 7.0+, curl            |


Changelog
---------

### 1.1.1

 * Adjusted data handling process to avoid memory issues (data is now appended to the export file with each API request made)

### 1.1

 * Added service endpoint selection to UI
 * Added date range selection to UI
 * Added real-time progress tracking of API operations
   (Note that different browsers will handle output buffering differently, but this has been confirmed working in Chrome 72.0.3626.121)

### 1.0

 * Initial release


License
-------

Copyright (c) 2019 Neatnik LLC

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.


Legal
-----

IBM Watson® is a registered trademark of IBM Corporation.

*/

session_start();

$_SESSION['timer'] = 0;

$workspace = isset($_REQUEST['workspace']) ? $_REQUEST['workspace'] : null;
$username = isset($_REQUEST['username']) ? $_REQUEST['username'] : null;
$password = isset($_REQUEST['password']) ? $_REQUEST['password'] : null;
$version = isset($_REQUEST['version']) ? $_REQUEST['version'] : '2018-09-20';

header('Content-type: text/html; charset=utf-8');
@apache_setenv('no-gzip', 1);
@ini_set('zlib.output_compression', 0);
@ini_set('implicit_flush', 1);
for ($i = 0; $i < ob_get_level(); $i++) { ob_end_flush(); }
ob_implicit_flush(1);

function _ago($tm, $rcs = 0) {
	$cur_tm = time(); $dif = $cur_tm-$tm;
	$pds = array('second','minute','hour','day','week','month','year','decade');
	$lngh = array(1,60,3600,86400,604800,2630880,31570560,315705600);
	for($v = sizeof($lngh)-1; ($v >= 0)&&(($no = $dif/$lngh[$v])<=1); $v--); if($v < 0) $v = 0; $_tm = $cur_tm-($dif%$lngh[$v]);
	$no = floor($no); if($no <> 1) $pds[$v] .='s'; $x=sprintf("%d %s ",$no,$pds[$v]);
	if(($rcs == 1)&&($v >= 1)&&(($cur_tm-$_tm) > 0)) $x .= time_ago($_tm);
	return $x;
}

function output($text) {
	if($_SESSION['timer'] == 0) {
		$_SESSION['timer'] = time();
		$timer = "[Starting timer]";
	}
	else {
		$elapsed = _ago($_SESSION['timer']);
		$timer = "[Elapsed time: $elapsed]";
	}
	echo "### $text\n### $timer\n\n";
	@flush();
	@ob_flush();
}

if(isset($_REQUEST['style'])) goto style;

function assistant_api($method) {
	global $export;
	global $version;
	global $username;
	global $password;
	$url = $_SESSION['service_endpoint'].$method;
	$curl = curl_init();
	curl_setopt_array($curl, array(
		CURLOPT_URL => $url,
		CURLOPT_USERPWD => $username.':'.$password,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_FAILONERROR, true,
		//CURLOPT_SSL_VERIFYPEER, true,
		//CURLOPT_SSL_VERIFYHOST, 2,
		//CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1
	));
	output('Fetching data from API...');
	$response = curl_exec($curl);
	if(curl_error($curl)) $error_msg = curl_error($curl);
	curl_close($curl);
	output('Fetched '.number_format(strlen($response)).' bytes.');
	
	if(isset($error_msg)) die('<pre>'.$error_msg);
	$object = json_decode($response);
	
	if(isset($object->error)) {
		echo '<h1>There was a problem accessing the Watson Assistant API</h1><p>The response was:</p><pre>'.print_r($object, 1).'</pre></p>';
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_USERPWD, $username.':'.$password);
		curl_setopt($ch, CURLOPT_NOBODY, true);
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$headers = curl_exec($ch);
		curl_close($ch);
		$retry = str_replace('Retry-After: ', '', substr(substr($headers, strpos($headers, 'Retry-After: ')), 0, strpos(substr($headers, strpos($headers, 'Retry-After: ')), "\n")-1));
		if(is_numeric($retry)) echo '<p><strong>API limit reached. Try again in: '.floor($retry / 60).' min, '.floor((($retry / 60) - floor($retry / 60)) * 60).' sec</strong></p>';
		exit;
	}
	
	if(isset($object->logs)) {
		foreach($object->logs as $log => $log_obj) {
			$export[$log_obj->log_id]['timestamp'] = $log_obj->request_timestamp;
			$export[$log_obj->log_id]['conversation_id'] = $log_obj->response->context->conversation_id;
			$export[$log_obj->log_id]['dialog_turn_counter'] = @$log_obj->request->context->system->dialog_turn_counter;
			$export[$log_obj->log_id]['input'] = @$log_obj->request->input->text;
			$export[$log_obj->log_id]['output'] = implode('; ', str_replace(array("\r", "\n"), '', $log_obj->response->output->text));
			$export[$log_obj->log_id]['intent'] = @$log_obj->response->intents[0]->intent;
			$export[$log_obj->log_id]['confidence'] = @$log_obj->response->intents[0]->confidence;
			$entities = array();
			foreach($log_obj->response->entities as $entity_obj) {
				$entity = isset($entity_obj->value) ? $entity_obj->entity.':'.$entity_obj->value : $entity_obj->entity;
				$entities[] = '@'.$entity;
			}
			$entities = isset($entities) ? implode(', ',$entities) : null;
			$export[$log_obj->log_id]['entities'] = $entities;
		}
	}
	
	// Write data fetched so far
	$out = null;
	$fp = fopen('php://temp', 'w+');
	foreach ($export as $fields) {
		fputcsv($fp, $fields);
	}
	rewind($fp); // Set the pointer back to the start
	$out .= stream_get_contents($fp); // Fetch the contents of our CSV
	fclose($fp); // Close our pointer and free up memory and /tmp space
	file_put_contents($_SESSION['filename'], $out, FILE_APPEND);
	$export = array(); // Clear array
	
	if(isset($object->pagination->next_cursor)) {
		output('Following API pagination cursor...');
		$method = str_replace('?version='.$version, '?cursor='.$object->pagination->next_cursor.'&version='.$version, $method);
		assistant_api($method);
	}
}

$start = isset($_REQUEST['start']) && $_REQUEST['start'] !== '' ? $_REQUEST['start'] : '1 week ago';
$end = isset($_REQUEST['end']) && $_REQUEST['end'] !== '' ? $_REQUEST['end'] : 'now';
$response_timestamp_start = date('Y-m-d\TH:i:s\Z', strtotime($start));
$response_timestamp_end = date('Y-m-d\TH:i:s\Z', strtotime($end));
$filter = 'response_timestamp>='.$response_timestamp_start.',response_timestamp<='.$response_timestamp_end;

if(isset($_REQUEST['export'])) {
	$_SESSION['filename'] = 'log-csv-export_'.date("Y-m-d-h-i-s").'.csv';
	file_put_contents($_SESSION['filename'], "Timestamp,Conversation ID,Turn,Input,Output,Intent,Confidence,Entities\n");
	
	$_SESSION['service_endpoint'] = $_REQUEST['service_endpoint'];
	echo '<pre style="background: #333; color: #ccc; padding: 1em; font-size: 1.2em; border-radius: .2em;">';
	output("Starting API calls. When all calls have finished, the button to export your data will appear below these messages.");
	assistant_api('/v1/workspaces/'.$workspace.'/logs?version='.$version.'&page_limit=500&export=true&include_audit=true&filter='.rawurlencode($filter), true);
	output("Finished fetching data. Export ready.");
	
	echo '</pre><a style="border: 2px solid #00884b; background: #cef3d1; color: #00884b; font-size: 1.2em; margin: 1em 0 1em 0; font-family: sans-serif; text-decoration: none; padding: .5em; border-radius: .2em;" href="'.$_SESSION['filename'].'">Download exported data</a><br>&nbsp;';	
	
	/*
	header("Content-type: text/csv");
	header("Content-Disposition: attachment; filename=log-csv_".date('c').".csv");
	header("Pragma: no-cache");
	header("Expires: 0");
	echo $out;
	*/
	
	exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<title>Watson Assistant Log CSV Exporter</title>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width">
<link rel="stylesheet" type="text/css" href="?style&time=<?php echo time(); ?>">
</head>
<body>

<h1>Watson Assistant Log CSV Exporter</h1>

<p>Fill in the form and click the button to export all available conversation log data from your workspace. Note that retrieving the data may take several moments.</p>

<?php

if(!is_writable(dirname(__FILE__))) {
	echo '<p class="warning">This script does not have permission to write files to the directory in which it is running. Please adjust permissions to continue.</p>';
}

?>

<form action="?" method="post">

<h2>API Connection</h2>

<p>
<label for="service_endpoint">Service Endpoint</label><br>
<select name="service_endpoint">
<option value="https://gateway.watsonplatform.net/assistant/api">Dallas</option>
<option value="https://gateway-wdc.watsonplatform.net/assistant/api">Washington, DC</option>
<option value="https://gateway-fra.watsonplatform.net/assistant/api">Frankfurt</option>
<option value="https://gateway-syd.watsonplatform.net/assistant/api">Sydney</option>
<option value="https://gateway-tok.watsonplatform.net/assistant/api">Tokyo</option>
<option value="https://gateway-lon.watsonplatform.net/assistant/api">London</option>
</select>
</p>

<p><label for="workspace">Workspace ID</label><br>
<input type="text" name="workspace" placeholder="1c7fbde9-102e-4164-b127-d3ffe2e58a04" value="<?php echo $workspace; ?>"></p>

<p><label for="username">Username</label><br>
<input type="text" name="username" placeholder="febeea03-84c4-57cb-af25-5f44b7af1f05" value="<?php echo $username; ?>"></p>

<p><label for="password">Password</label><br>
<input type="password" name="password" placeholder="xCkZnpPbxLkQ" value="<?php echo $password; ?>"></p>

<p><label for="version">API Version</label><br>
<input type="text" name="version" placeholder="2018-09-20" value="<?php echo $version; ?>"></p>

<h2>Export Options</h2>

<p><label for="version">Start Date</label><br>
<input type="text" name="start" placeholder="one week ago" value="<?php echo $start; ?>"></p>

<p><label for="version">End Date</label><br>
<input type="text" name="end" placeholder="now" value="<?php echo $end; ?>"></p>

<p><button type="submit" name="export">Fetch Data</button></p>

</form>

<a href="https://github.com/neatnik/watson-assistant-utilities/blob/master/log-csv-exporter-standalone.php" class="github-corner" aria-label="View source on Github"><svg width="80" height="80" viewBox="0 0 250 250" style="fill:#151513; color:#fff; position: absolute; top: 0; border: 0; right: 0;" aria-hidden="true"><path d="M0,0 L115,115 L130,115 L142,142 L250,250 L250,0 Z"></path><path d="M128.3,109.0 C113.8,99.7 119.0,89.6 119.0,89.6 C122.0,82.7 120.5,78.6 120.5,78.6 C119.2,72.0 123.4,76.3 123.4,76.3 C127.3,80.9 125.5,87.3 125.5,87.3 C122.9,97.6 130.6,101.9 134.4,103.2" fill="currentColor" style="transform-origin: 130px 106px;" class="octo-arm"></path><path d="M115.0,115.0 C114.9,115.1 118.7,116.5 119.8,115.4 L133.7,101.6 C136.9,99.2 139.9,98.4 142.2,98.6 C133.8,88.0 127.5,74.4 143.8,58.0 C148.5,53.4 154.0,51.2 159.7,51.0 C160.3,49.4 163.2,43.6 171.4,40.1 C171.4,40.1 176.1,42.5 178.8,56.2 C183.1,58.6 187.2,61.8 190.9,65.4 C194.5,69.0 197.7,73.2 200.1,77.6 C213.8,80.2 216.3,84.9 216.3,84.9 C212.7,93.1 206.9,96.0 205.4,96.6 C205.1,102.4 203.0,107.8 198.3,112.5 C181.9,128.9 168.3,122.5 157.7,114.1 C157.9,116.9 156.7,120.9 152.7,124.9 L141.0,136.5 C139.8,137.7 141.6,141.9 141.8,141.8 Z" fill="currentColor" class="octo-body"></path></svg></a><style>.github-corner:hover .octo-arm{animation:octocat-wave 560ms ease-in-out}@keyframes octocat-wave{0%,100%{transform:rotate(0)}20%,60%{transform:rotate(-25deg)}40%,80%{transform:rotate(10deg)}}@media (max-width:500px){.github-corner:hover .octo-arm{animation:none}.github-corner .octo-arm{animation:octocat-wave 560ms ease-in-out}}</style> <!-- http://tholman.com/github-corners/ -->

</body>
</html>

<?php
exit;
?>
<style type="text/css"><?php style: header("Content-type: text/css"); ?>
@import url('//fonts.googleapis.com/css?family=IBM+Plex+Sans:400,400i,700');

* {
	font-family: 'IBM Plex Sans', sans-serif;
	font-size: 1em;
	line-height: 175%;
	border-radius: 0.2em;
	box-sizing: border-box;
	-webkit-text-size-adjust: 100%;
	border: 0px dotted #ccc;
	-webkit-appearance: none;
	-moz-appearance: none;
	appearance: none;
	box-shadow: none;
	outline: 0;
}

body {
	margin: 2em;
	background: #fff;
	color: #000;
}

h1, h2 {
	font-weight: normal;
	margin: 2rem 0 1rem 0;
}

h1 {
	font-size: 200%;
}

h2 {
	font-size: 150%;
}

button {
	display: inline-block;
	background: #5392ff;
	color: #fff;
	line-height: 100%;
	padding: .5em;
	border-radius: .2em;
	text-decoration: none;
}

button:hover {
	cursor: pointer;
	background: #000;
}

label {
	font-weight: bold;
}

form p {
	margin-top: 2em;
}

input, select {
	border-bottom: 1px solid #666;
	border-radius: 0;
	width: 20em;
}

select {
	margin-top: .5em;
	border: 1px solid #666;
	border-radius: .2em;
	padding: .2em 0 .2em .6em;
}

select:hover {
	cursor: pointer;
}

section {
	margin-top: 1em;
	clear: both;
	color: #888;
}

.warning {
	color: red;
	background: #ffecec;
	font-weight: bold;
	border: 1px solid red;
	border-radius: .2em;
	padding: 1em;
	display: inline-block;
}

<?php
exit;
?>