<?php session_start();

/*

dialog-debugger.php
===================

Designed for use with IBM Watson Assistant (formerly IBM Watson Conversation Service).
Use this tool to debug your dialog.


Metadata
--------

| Data       | Value                            |
| ----------:| -------------------------------- |
|    Version | 1.0                              |
|    Updated | 2018-07-07T06:21:16+00:00        |
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
define(  'VERSION', 'change_me'); // The ISO 8601 date of the API version; you probably don't need to change this


# Main code begins here

function percentcolor($value, $brightness = 180, $max = 100, $min = 0, $thirdcolorhex = '00') {
	$first = (1 - ($value/$max)) * $brightness;
	$second = ($value / $max) * $brightness;
	$diff = abs($first - $second);    
	$influence = ($brightness - $diff)/2;     
	$first = intval($first + $influence);
	$second = intval($second + $influence);
	$firsthex = str_pad(dechex($first), 2, 0, STR_PAD_LEFT);     
	$secondhex = str_pad(dechex($second), 2, 0, STR_PAD_LEFT); 
	return '<span style="font-weight: bold; color: #'.$firsthex . $secondhex . $thirdcolorhex.';">'.$value.'%</span>';
}

$http = (php_sapi_name() == "cli") ? false : true;
if(!$http) die("\nThis utility cannot be executed via the command line. Please access it via a web browser.\n\n");

if(isset($_REQUEST['style'])) goto style;

if(!isset($_SESSION['context'])) {
	$_SESSION['context'] = null;
}
if(isset($_REQUEST['query'])) {
	$query = $_REQUEST['query'];
}
else {
	$query = null;
}

if(isset($_REQUEST['reset'])) {
	$_SESSION[] = array();
	session_destroy();
	header("Location: ".$_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF']);
	exit;
}

function assistant_api($method) {
	global $http, $entities, $conditions, $dialog_ids, $node_titles;
	$curl = curl_init();
	$url = 'https://gateway.watsonplatform.net/assistant/api'.$method;
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
		if($http) die(html('<h1>There was a problem accessing the Watson Assistant API</h1><p>The response was: <strong>'.$object->error.'</strong></p>'));
		else die("\nThere was a problem accessing the Watson Assistant API\n\nThe response was: \"".$object->error."\"\n\n");
		
	}
	
	if(isset($object->dialog_nodes)) {
		foreach($object->dialog_nodes as $node) {
			$conditions[$node->dialog_node]['type'] = $node->type;
			$conditions[$node->dialog_node]['title'] = $node->title;
			$conditions[$node->dialog_node]['conditions'] = $node->conditions;
			$conditions[$node->dialog_node]['parent'] = $node->parent;
		}
	}
	
	if(isset($object->pagination->next_url)) {
		watson($object->pagination->next_url);
	}
	else {
		return $entities;
	}
}

// Fetch and store dialog node data
if(!isset($_SESSION['dialog_nodes'])) {
	assistant_api('/v1/workspaces/'.WORKSPACE.'/dialog_nodes?version='.VERSION.'&page_limit=500&export=true&include_audit=true', true);
	$_SESSION['dialog_nodes'] = $conditions;
}

$context = json_encode($_SESSION['context']);
$data = '{"input": {"text": "'.$query.'"}, "context": '.$context.'}';

$curl = curl_init();
curl_setopt_array($curl, array(
	CURLOPT_USERPWD => USERNAME.':'.PASSWORD,
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_FAILONERROR, true,	
	CURLOPT_HTTPHEADER => array('Content-type: application/json'),
	CURLOPT_POST => true,
	CURLOPT_URL => "https://gateway.watsonplatform.net/conversation/api/v1/workspaces/".WORKSPACE."/message?version=".VERSION,
	CURLOPT_POSTFIELDS => $data,
	CURLOPT_SSL_VERIFYPEER => true
));

$response = curl_exec($curl);
curl_close($curl);

$json = json_decode($response);
$json_txt = json_encode($json, JSON_PRETTY_PRINT);

// Set context

$_SESSION['context'] = $json->context;

// Prepare entities

$input = $json->input->text;

$entities = $json->entities;
if(count($entities) > 0) {
	foreach($entities as $k => $entity_arr) {
		$entity = $entity_arr->entity;
		if(isset($entity_arr->value)) {
			$entity .= ':'.$entity_arr->value;
		}
		$from = $entity_arr->location[0];
		$to = $entity_arr->location[1];
		$str = substr($input, $from, $to - $from);
		$entity_data[$str][] = $entity;
	}
	
	$input_table = '<table>';
	
	foreach(explode(' ', $input) as $word) {
		$entity_str = null;
		if(isset($entity_data[$word])) {
			$class = 'entity-found'; 
			foreach($entity_data[$word] as $entity) {
				$entity_str .= '<span class="entity">@'.$entity.'</span> ';
			}
		}
		else {
			$class = 'entity-not-found';
			$entity_str = '<em>no matching entitiy</em>';
		}
		$entity_str = substr($entity_str, 0, -2);
		$input_table .= '<tr class="'.$class.'"><td>'.$word.'</td><td>'.$entity_str.'</td></tr>';
	}
	$input_table .= '</table>';
	$entities_output = $input_table;
}
else {
	$entities_output = '<em class="gray">no entities</em>';
}

if($input == '') $input = '<em class="gray">null</em>';
$input_output = '<div class="input">'.$input.'</div>';

$intents = $json->intents;
if(count($intents) > 0) {
	$intent = '<span class="bigger">“'.$json->input->text.'”</span><br>matches <span class="intent">#'.$json->intents[0]->intent.'</span> with '.percentcolor(round($json->intents[0]->confidence * 100)).' confidence';
}
else {
	$intent = '<em class="gray">no intent</em>';
}

// Prepare dialog path

$nodes_visited = $json->output->nodes_visited;
$path = null;
foreach($nodes_visited as $node) {
	$node_type = $_SESSION['dialog_nodes'][$node]['type'] == 'standard' ? 'dialog' : $_SESSION['dialog_nodes'][$node]['type'];
	if(isset($_SESSION['dialog_nodes'][$node]['conditions'])) {
		$condition = 'due to condition <span class="condition">'.$_SESSION['dialog_nodes'][$node]['conditions'].'</span>';
	}
	else {
		$condition = '<span class="gray">which has no conditions</span>';
	}
	$path .= '<div class="node-path"><i class="fas fa-code-branch"></i> Visited '.$node_type.' node <span class="node">'.$_SESSION['dialog_nodes'][$node]['title'].'</span> '.$condition;
	$tmp = true;
	$i = 1;
	$indent = null;
	while($tmp) {
		if(isset($_SESSION['dialog_nodes'][$node]['parent'])) {
			$node = $_SESSION['dialog_nodes'][$node]['parent'];
			$node_type = $_SESSION['dialog_nodes'][$node]['type'] == 'standard' ? 'dialog' : $_SESSION['dialog_nodes'][$node]['type'];
			if(isset($_SESSION['dialog_nodes'][$node]['conditions'])) {
				$condition = 'due to condition <span class="condition">'.$_SESSION['dialog_nodes'][$node]['conditions'].'</span>';
			}
			else {
				$condition = '<span class="gray">which has no conditions</span>';
			}
			$indent .= '<span class="indent">&bull;</span>';
			$path .= '<br>'.$indent.'<i class="fas fa-level-up-alt fa-rotate-90"></i> from '.$node_type.' node <span class="node">'.$_SESSION['dialog_nodes'][$node]['title'].'</span> '.$condition;
			$i++;
		}
		else {
			$tmp = false;
		}
	}
	$path .= '</div>';
}

// Prepare output

$output_array = $json->output->text;
$output = null;
$i = 0;
foreach($output_array as $text) {
	$output .= '<div class="output"><div>'.$text.' <span class="node"><i style="margin: 0 .25em; color: #000;" class="fas fa-code-branch"></i>'.$_SESSION['dialog_nodes'][$json->output->nodes_visited[$i]]['title'].'</node></div>';
	if(strip_tags($text) !== $text) {
		$output .= '<div><span class="html"><i class="fas fa-code"></i>'.htmlentities($text).'</span></div>';
	}
	$output .= '</div>';
	$i++;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<title>Dialog Debugger</title>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>

<style type="text/css">
</style>

<link rel="stylesheet" type="text/css" href="?style&time=<?php echo time(); ?>">
</head>
<body>

<h1>Dialog Debugger</h1>

<form action="?" method="post">
<input style="width: 25em;" type="text" name="query" placeholder="Send an input to the workspace to debug" autocomplete="off">
<input type="submit" name="send" value="Submit">
<input type="submit" name="reset" value="Reset">
</form>

<section>

<div class="floated">
<h2>Entities</h2>
<?php echo $entities_output; ?>
</div>

<div class="floated">
<h2>Intent</h2>
<?php echo $intent; ?>
</div>

<div class="floated">
<h2>Dialog</h2>
<?php echo $path; ?>
</div>

</section>

<section>

<h2>Input</h2>

<?php echo $input_output; ?>

<h2>Output</h2>

<?php echo $output; ?>

<h2>API Response</h2>
<div class="mono">
<pre><code class="language-json"><?php echo $json_txt; ?></code></pre>
</div>

</section>

<script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.15.0/prism.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.15.0/components/prism-json.js"></script>

</body>
</html>

<?php
exit;
?>
<style type="text/css"> <?php style: header("Content-type: text/css"); ?>
@import url('//fonts.googleapis.com/css?family=IBM+Plex+Mono:400,400i,700|IBM+Plex+Sans:400,400i,700');
@import url('//use.fontawesome.com/releases/v5.1.0/css/all.css');

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
}

h1, h2 {
	font-weight: normal;
	margin: 2rem 0 1rem 0;
}

h1 {
	font-size: 200%;
}

h2 {
	font-size: 140%;
}

li a:link, li a:visited, li a:hover, li a:active {
	text-decoration: none;
}

table {
	border-collapse: collapse;
}

td {
	border-top: 1px dotted #bbb;
	padding: .5em 0;
}

td:first-child {
	text-align: right;
	padding-right: .5em;
}

tr:last-child td {
	border-bottom: 1px dotted #bbb;
}

.mono * {
	font: 1em/130% 'IBM Plex Mono', monospace !important;
}

section {
	margin-top: 1em;
	clear: both;
}

.row {
	overflow: auto;
	clear: both;
}

.floated {
	float: left;
	margin: 0 2em 2em 0;
}

.bigger {
	font-size: 130%;
}

.node-path {
	margin: 0 0 1em 0;
}

.fa-code-branch {
	margin-right: .25em;
	color: #999;
}

.fa-level-up-alt {
	margin-right: .25em;
	color: #999;
}

.input, .output {
	border-radius: 0;
	padding-left: .5em;
}

.html {
	font-family: 'IBM Plex Mono'; monospace;
	font-size: 90%;
	color: #b3901f;
}

.html .fa-code {
	padding: 0 .5em 0 0;
}

.input {
	border-left: .5em solid #b4e876;
}

.output {
	border-left: .5em solid #fed500;
	margin-bottom: 1em;
}

.intent, .entity, .condition, .node {
	padding: .15em .4em .2em .2em;
}

.intent {
	background: #b4e876;
}

.node {
	background: #fed500;
}

.entity {
	background: #c8daf4;
}

.condition {
	background: #fccec7;
}

.entity-found td:first-child {
	font-weight: bold;
}

.gray {
	color: #aaa;
}

.indent {
	width: 1em;
	display: inline-block;
	color: #ddd;
}

.entity-not-found {
	color: #888;
}

.entity-not-found em {
	color: #bbb;
}

input {
	border: 1px solid #555;
	padding: .25em .5em;
	background: #fff;
}

input[type="submit"] {
	cursor: pointer;
	color: #000;
	background: #fff;
}

input[type="submit"]:hover {
	cursor: pointer;
	color: #fff;
	background: #dc267f;
	border: 1px solid #dc267f;
}

code[class*="language-"],
pre[class*="language-"] {
	color: #ccc;
	background: none;
	text-align: left;
	white-space: pre;
	word-spacing: normal;
	word-break: normal;
	word-wrap: normal;
	line-height: 1.5;
	-moz-tab-size: 4;
	-o-tab-size: 4;
	tab-size: 4;
	-webkit-hyphens: none;
	-moz-hyphens: none;
	-ms-hyphens: none;
	hyphens: none;
	white-space: pre-wrap;
	word-wrap: break-word;
	word-break: break-all;
}
pre[class*="language-"] {
	padding: 1em;
	margin: .5em 0;
	overflow: auto;
}
:not(pre) > code[class*="language-"],
pre[class*="language-"] {
	background: #fff;
}
:not(pre) > code[class*="language-"] {
	padding: .1em;
	border-radius: .3em;
	white-space: normal;
}
.token.comment,
.token.block-comment,
.token.prolog,
.token.doctype,
.token.cdata {
	color: #999;
}
.token.punctuation {
	color: #006456;
}
.token.tag,
.token.attr-name,
.token.namespace,
.token.deleted {
	color: #d6d6d6;
}
.token.function-name {
	color: #8e908c;
}
.token.boolean,
.token.number,
.token.function {
	color: #dc267f;
	background: #f5e7eb;
}
.token.property,
.token.class-name,
.token.constant,
.token.symbol {
	color: #73a22c;
}
.token.selector,
.token.important,
.token.atrule,
.token.keyword,
.token.builtin {
	color: #12a3b4;
}
.token.string,
.token.char,
.token.attr-value,
.token.regex,
.token.variable {
	color: #1f57a4;
	background: #e1ebf7;
}
.token.operator,
.token.entity,
.token.url {
	color: #282a2e;
}
.token.important,
.token.bold {
	font-weight: bold;
}
.token.italic {
	font-style: italic;
}
.token.entity {
	cursor: help;
}
.token.inserted {
	color: green;
}
<?php
exit;
?>
</style>