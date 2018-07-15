<?php

/*

log-csv-exporter.php
====================

Exports your conversation log in a CSV format. Designed for use with IBM Watson Assistant. 


Metadata
--------

| Data       | Value                            |
| ----------:| -------------------------------- |
|    Version | 1.0                              |
|    Updated | 2018-07-15T18:36:42+00:00        |
|     Author | Adam Newbold, https://adam.lol   |
| Maintainer | Neatnik LLC, https://neatnik.net |
|   Requires | PHP 5.6 or 7.0+ with curl        |


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


# Configuration

// You can find the Workspace ID, Username, and Password values in your Watson Assistant workspace; click the Deploy tab, then the Credentials screen.
// The latest API version can be found here: https://www.ibm.com/watson/developercloud/assistant/api/v1/curl.html?curl#versioning

define('WORKSPACE', 'change_me'); // Usually 36 characters, e.g. 1c7fbde9-102e-4164-b127-d3ffe2e58a04
define( 'USERNAME', 'change_me'); // Usually 36 characters, e.g. febeea03-84c4-57cb-af25-5f44b7af1f05
define( 'PASSWORD', 'change_me'); // Usually 12 characters, e.g. xCkZnpPbxLkQ
define(  'VERSION', '2018-02-16'); // The ISO 8601 date of the API version; you probably don't need to change this


# Main code begins here

$http = (php_sapi_name() == "cli") ? false : true;
if(!$http) die("\nThis utility cannot be executed via the command line. Please access it via a web browser.\n\n");

if(isset($_REQUEST['style'])) goto style;

function assistant_api($method) {
	global $export;
	$url = 'https://gateway.watsonplatform.net/assistant/api'.$method;
	$curl = curl_init();
	curl_setopt_array($curl, array(
		CURLOPT_URL => $url,
		CURLOPT_USERPWD => USERNAME.':'.PASSWORD,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_FAILONERROR, true
	));
	$response = curl_exec($curl);
	if(curl_error($curl)) $error_msg = curl_error($curl);
	curl_close($curl);
	
	if(isset($error_msg)) die($error_msg);
	$object = json_decode($response);
	
	if(isset($object->error)) {
		echo '<h1>There was a problem accessing the Watson Assistant API</h1><p>The response was:</p><pre>'.print_r($object, 1).'</pre></p>';
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_USERPWD, USERNAME.':'.PASSWORD);
		curl_setopt($ch, CURLOPT_NOBODY, true);
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$headers = curl_exec($ch);
		curl_close($ch);
		$retry = str_replace('Retry-After: ', '', substr(substr($headers, strpos($headers, 'Retry-After: ')), 0, strpos(substr($headers, strpos($headers, 'Retry-After: ')), "\n")-1));
		if(is_numeric($retry)) echo '<p><strong>Try again in: '.floor($retry / 60).' min, '.floor((($retry / 60) - floor($retry / 60)) * 60).' sec</strong></p>';
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
		$method = str_replace('?version='.VERSION, '?cursor='.$object->pagination->next_cursor.'&version='.VERSION, $method);
		assistant_api($method);
	}
}

$start = isset($_REQUEST['start']) && $_REQUEST['start'] !== '' ? $_REQUEST['start'] : '3 days ago';
$end = isset($_REQUEST['end']) && $_REQUEST['end'] !== '' ? $_REQUEST['end'] : 'now';
$response_timestamp_start = date('Y-m-d\TH:i:s\Z', strtotime($start));
$response_timestamp_end = date('Y-m-d\TH:i:s\Z', strtotime($end));
$filter = 'response_timestamp>='.$response_timestamp_start.',response_timestamp<='.$response_timestamp_end;

if(isset($_REQUEST['export'])) {
	assistant_api('/v1/workspaces/'.WORKSPACE.'/logs?version='.VERSION.'&page_limit=500&export=true&include_audit=true&filter='.rawurlencode($filter), true);
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
<title>Log CSV Exporter</title>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width">
<link rel="stylesheet" type="text/css" href="?style&time=<?php echo time(); ?>">
</head>
<body>

<h1>Log CSV Exporter</h1>
<p>Click the button to export all available conversation log data from your workspace. Note that retrieving the data may take several moments.</p>
<p><a href="?export" class="button">Export to CSV</a></p>

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
	transition: all 150ms ease;
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

.button {
	display: inline-block;
	background: #5392ff;
	color: #fff;
	font-size: .9em;
	line-height: 100%;
	padding: .5em;
	margin-left: .3em;
	border-radius: .2em;
	text-decoration: none;
}

.button:hover {
	cursor: pointer;
	background: #000;
}

section {
	margin-top: 1em;
	clear: both;
	color: #888;
}
<?php
exit;
?>