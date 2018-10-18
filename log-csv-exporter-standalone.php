<?php

/*

log-csv-exporter-standalone.php
===============================

Exports your conversation log in a CSV format. Standalone version with credential input. Designed for use with IBM Watson Assistant.


Metadata
--------

| Data       | Value                            |
| ----------:| -------------------------------- |
|    Version | 1.0                              |
|    Updated | 2018-10-18T12:27:10+00:00        |
|     Author | Adam Newbold, https://adam.lol   |
| Maintainer | Neatnik LLC, https://neatnik.net |
|   Requires | PHP 5.6 or 7.0+, curl            |


Changelog
---------

### 1.0

 * Initial release


License
-------

Copyright (c) 2018 Neatnik LLC

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

$workspace = isset($_REQUEST['workspace']) ? $_REQUEST['workspace'] : null;
$username = isset($_REQUEST['username']) ? $_REQUEST['username'] : null;
$password = isset($_REQUEST['password']) ? $_REQUEST['password'] : null;
$version = isset($_REQUEST['version']) ? $_REQUEST['version'] : '2018-09-20';

if(isset($_REQUEST['style'])) goto style;

function assistant_api($method) {
	global $export;
	global $version;
	global $username;
	global $password;
	$url = 'https://gateway.watsonplatform.net/assistant/api'.$method;
	$curl = curl_init();
	curl_setopt_array($curl, array(
		CURLOPT_URL => $url,
		CURLOPT_USERPWD => $username.':'.$password,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_FAILONERROR, true,
       // CURLOPT_SSL_VERIFYPEER, true,
        //CURLOPT_SSL_VERIFYHOST, 2,
        //CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1
	));
	$response = curl_exec($curl);
	if(curl_error($curl)) $error_msg = curl_error($curl);
	curl_close($curl);
	
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
	
	if(isset($object->pagination->next_cursor)) {
		$method = str_replace('?version='.$version, '?cursor='.$object->pagination->next_cursor.'&version='.$version, $method);
		assistant_api($method);
	}
}

$start = isset($_REQUEST['start']) && $_REQUEST['start'] !== '' ? $_REQUEST['start'] : '3 days ago';
$end = isset($_REQUEST['end']) && $_REQUEST['end'] !== '' ? $_REQUEST['end'] : 'now';
$response_timestamp_start = date('Y-m-d\TH:i:s\Z', strtotime($start));
$response_timestamp_end = date('Y-m-d\TH:i:s\Z', strtotime($end));
$filter = 'response_timestamp>='.$response_timestamp_start.',response_timestamp<='.$response_timestamp_end;

if(isset($_REQUEST['export'])) {
	assistant_api('/v1/workspaces/'.$workspace.'/logs?version='.$version.'&page_limit=500&export=true&include_audit=true&filter='.rawurlencode($filter), true);
	$out = "Timestamp,Conversation ID,Turn,Input,Output,Intent,Confidence,Entities\n";
	$fp = fopen('php://temp', 'w+');
	foreach ($export as $fields) {
		fputcsv($fp, $fields);
	}
	rewind($fp); // Set the pointer back to the start
	$out .= stream_get_contents($fp); // Fetch the contents of our CSV
	fclose($fp); // Close our pointer and free up memory and /tmp space
	header("Content-type: text/csv");
	header("Content-Disposition: attachment; filename=log-csv_".date('c').".csv");
	header("Pragma: no-cache");
	header("Expires: 0");
	echo $out;
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

<form action="?" method="post">

<p><label for="workspace">Workspace</label><br>
<input type="text" name="workspace" placeholder="1c7fbde9-102e-4164-b127-d3ffe2e58a04" value="<?php echo $workspace; ?>"></p>

<p><label for="username">Username</label><br>
<input type="text" name="username" placeholder="febeea03-84c4-57cb-af25-5f44b7af1f05" value="<?php echo $username; ?>"></p>

<p><label for="password">Password</label><br>
<input type="password" name="password" placeholder="xCkZnpPbxLkQ" value="<?php echo $password; ?>"></p>

<p><label for="version">API Version</label><br>
<input type="text" name="version" placeholder="2018-09-20" value="<?php echo $version; ?>"></p>

<p><button type="submit" name="export">Export to CSV</button></p>

</form>

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

input {
	border-bottom: 1px solid #666;
	border-radius: 0;
	width: 20em;
}

section {
	margin-top: 1em;
	clear: both;
	color: #888;
}
<?php
exit;
?>
