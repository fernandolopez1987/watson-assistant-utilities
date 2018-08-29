<?php

/*

Dialog Search & Replace
=======================

Replace text universally across dialog nodes. Designed for use with IBM Watson Assistant.


Metadata
--------

| Data       | Value                            |
| ----------:| -------------------------------- |
|    Version | 1.0                              |
|    Updated | 2018-08-29T18:35:29+00:00        |
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

define('WORKSPACE', '1031bcc2-5913-4de1-9116-95e850ef3b34'); // Usually 36 characters, e.g. 1c7fbde9-102e-4164-b127-d3ffe2e58a04
define( 'USERNAME', '0780f135-3813-43a1-8407-2d33c3e5311d'); // Usually 36 characters, e.g. febeea03-84c4-57cb-af25-5f44b7af1f05
define( 'PASSWORD', 'rxpK26KjydxU');                         // Usually 12 characters, e.g. xCkZnpPbxLkQ
define(  'VERSION', '2018-07-10');                           // The ISO 8601 date of the API version; you probably don't need to change this


# Main code begins here

$http = (php_sapi_name() == "cli") ? false : true;
if(!$http) die("\nThis utility cannot be executed via the command line. Please access it via a web browser.\n\n");

if(isset($_REQUEST['style'])) goto style;

function assistant_api($method, $return_data = false) {
	global $entities, $entity_data, $entity_values, $dialog_ids, $node_titles;
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
	
	if($return_data) {
		return $response;
	}
	
	if(isset($object->dialog_nodes)) {
		foreach($object->dialog_nodes as $node) {
			if(isset($node->conditions)) {
				if(strpos($node->conditions, '@') !== false) {
					preg_match_all("/@[^\s]+\([^()]+\)|@[^\s]+/", $node->conditions, $condition_entities);
					foreach($condition_entities[0] as $condition_entity) {
						$condition_entity = str_replace(array('(', ')'), '', $condition_entity);
						$bits = explode(':', $condition_entity);
						if(isset($node->dialog_node)) {
							$dialog_ids[$bits[0]][$node->dialog_node] = $node->dialog_node;
							$dialog_ids[$condition_entity][$node->dialog_node] = $node->dialog_node;
						}
						if(isset($node->title)) {
							$node_titles[$node->dialog_node] = $node->title;
						}
					}
					
				}
			}
		}
	}
	
	if(isset($object->pagination->next_url)) {
		assistant_api($object->pagination->next_url);
	}
	else {
		return $entities;
	}
}

function assistant_api_post($method, $data) {
	$url = 'https://gateway.watsonplatform.net/assistant/api'.$method;
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_USERPWD, USERNAME.':'.PASSWORD);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
	curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
	$response = curl_exec($ch);
	curl_close($ch);
	return $response;
}

$search = isset($_REQUEST['search']) ? $_REQUEST['search'] : null;
$replace = isset($_REQUEST['replace']) ? $_REQUEST['replace'] : null;

?>
<!DOCTYPE html>
<html lang="en">
<head>
<title>Dialog Search & Replace</title>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width">
<meta property="og:title" content="Dialog Search & Replace">
<meta property="og:description" content="Replace text universally across dialog nodes. Designed for use with IBM Watson Assistant.">
<meta property="og:url" content="https://neatnik.net/watson/assistant/utilities/dialog-search-and-replace.php">
<meta property="og:image" content="https://neatnik.net/watson/assistant/utilities/dialog-search-and-replace.png">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
<link rel="stylesheet" type="text/css" href="?style&time=<?php echo time(); ?>">
</head>
<body>

<h1>Dialog Search & Replace</h1>

<p class="warning"><i class="fas fa-exclamation-triangle fa-fw"></i> This utility will write data to your workspace. Since it probably has bugs, you should have a backup copy of your workspace (using the <em>Download as JSON</em> option in the Watson Assistant interface) before proceeding.</p>

<p><i class="fas fa-info-circle fa-fw"></i> <strong>Directions:</strong> Enter the search and replacement terms, and then click to preview the results. If everything looks good, click the second button to make the changes in your workspace.</p>

<section>

<h2>Prepare</h2>

<form action="?" method="post">
<p><label for="search">Search for</label> <input type="text" placeholder="word" name="search" id="search" value="<?php echo $search; ?>"></p>
<p><label for="replace">Replace with</label> <input type="text" placeholder="word" name="replace" id="replace" value="<?php echo $replace; ?>"></p>
<p><button type="submit" name="prepare">Preview Results</button></p>
</form>

<?php

if(isset($_REQUEST['prepare'])) {
	$i = 0;
	echo '<h2>Results</h2>';
	$dialog_data = assistant_api('/v1/workspaces/'.WORKSPACE.'/dialog_nodes?version='.VERSION.'&export=true', true);
	$dialog_data = json_decode($dialog_data);
	foreach($dialog_data->dialog_nodes as $arr) {
		if(!isset($arr->output->text->values)) continue;
		foreach($arr->output->text->values as $response) {
			$tmp = htmlentities($response);
			if (strpos($tmp, $search) !== false) {
				echo '<hr>'.str_replace($search, '<strong style="color: #dc267f;">'.$search.'</strong>', $tmp);
				echo '<br>'.str_replace($search, '<strong style="color: #648fff;">'.$replace.'</strong>', $tmp);
				$i++;
			}
		}
	}
	
	echo '<hr>';
	
	if($i == 0) {
		echo '<p>No matches found.</p>';
	}
	
	echo '<h2>Confirm</h2>';
	echo '<form action="?" method="post">';
	echo '<p>If the changes shown above look good, click the button below to execute the renaming operation.</p>';
	echo '<input type="hidden" name="search" value="'.$search.'">';
	echo '<input type="hidden" name="replace" value="'.$replace.'">';
	echo '<input type="hidden" name="prepare" value="1">';
	echo '<button type="submit" name="confirm">Make the changes shown above</button>';
	echo '</form>';
}

if(isset($_REQUEST['confirm'])) {
	$dialog_data = assistant_api('/v1/workspaces/'.WORKSPACE.'/dialog_nodes?version='.VERSION.'&export=true', true);
	$dialog_data = json_decode($dialog_data);
	foreach($dialog_data->dialog_nodes as $arr) {
		if(!isset($arr->output->text->values)) continue;
		foreach($arr->output->text->values as $response) {
			$tmp = htmlentities($response);
			if (strpos($tmp, $search) !== false) {
				$dialog_node_id = $arr->dialog_node;
				foreach($arr->output->text->values as $i => $response) {
					$new = str_replace($search, $replace, $response);
					$arr->output->text->values[$i] = $new;
					$dialog_node_data = $arr;
					$dialog_node_data = json_encode($dialog_node_data);
					$dialog_node_update = assistant_api_post('/v1/workspaces/'.WORKSPACE.'/dialog_nodes/'.$dialog_node_id.'?version='.VERSION, $dialog_node_data);
				}
			}
		}
	}
	echo '<h2>Complete</h2><p>The items above have been renamed.</p>';
}

?>

</section>

<a href="https://github.com/neatnik/watson-assistant-utilities" class="github-corner" aria-label="View source on Github"><svg width="80" height="80" viewBox="0 0 250 250" style="fill:#151513; color:#fff; position: absolute; top: 0; border: 0; right: 0;" aria-hidden="true"><path d="M0,0 L115,115 L130,115 L142,142 L250,250 L250,0 Z"></path><path d="M128.3,109.0 C113.8,99.7 119.0,89.6 119.0,89.6 C122.0,82.7 120.5,78.6 120.5,78.6 C119.2,72.0 123.4,76.3 123.4,76.3 C127.3,80.9 125.5,87.3 125.5,87.3 C122.9,97.6 130.6,101.9 134.4,103.2" fill="currentColor" style="transform-origin: 130px 106px;" class="octo-arm"></path><path d="M115.0,115.0 C114.9,115.1 118.7,116.5 119.8,115.4 L133.7,101.6 C136.9,99.2 139.9,98.4 142.2,98.6 C133.8,88.0 127.5,74.4 143.8,58.0 C148.5,53.4 154.0,51.2 159.7,51.0 C160.3,49.4 163.2,43.6 171.4,40.1 C171.4,40.1 176.1,42.5 178.8,56.2 C183.1,58.6 187.2,61.8 190.9,65.4 C194.5,69.0 197.7,73.2 200.1,77.6 C213.8,80.2 216.3,84.9 216.3,84.9 C212.7,93.1 206.9,96.0 205.4,96.6 C205.1,102.4 203.0,107.8 198.3,112.5 C181.9,128.9 168.3,122.5 157.7,114.1 C157.9,116.9 156.7,120.9 152.7,124.9 L141.0,136.5 C139.8,137.7 141.6,141.9 141.8,141.8 Z" fill="currentColor" class="octo-body"></path></svg></a><style>.github-corner:hover .octo-arm{animation:octocat-wave 560ms ease-in-out}@keyframes octocat-wave{0%,100%{transform:rotate(0)}20%,60%{transform:rotate(-25deg)}40%,80%{transform:rotate(10deg)}}@media (max-width:500px){.github-corner:hover .octo-arm{animation:none}.github-corner .octo-arm{animation:octocat-wave 560ms ease-in-out}}</style> <!-- http://tholman.com/github-corners/ -->

</body>
</html>

<?php
exit;
?>
<style type="text/css"><?php style: header("Content-type: text/css"); ?>
@import url('//fonts.googleapis.com/css?family=IBM+Plex+Sans:400,400i,700');
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
	transition: all 150ms ease;
}

hr {
	border: 0;
	border-bottom: 2px dashed #ccc;
	background: #fff;
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
	font-size: 140%;
}

li a:link, li a:visited, li a:hover, li a:active {
	text-decoration: none;
}

small {
	padding-left: .5em;
	font-size: 80%;
	color: #999;
}

button {
	background: #fe6100;
	color: #fff;
	line-height: 100%;
	padding: .7em;
	border-radius: .2em;
}

button:hover {
	cursor: pointer;
}

section {
	margin-top: 1em;
	clear: both;
	color: #000;
}

label {
	margin: 0 .5em;
}

.intent {
	width: 10em;
}

input {
	display: inline-block;
	color: #000;
	border-radius: 0;
	padding: 0;
}

#search {
	border-bottom: 2px solid #dc267f;
}

#replace {
	border-bottom: 2px solid #648fff;
}

.fa-cog {
	color: #000;
	margin-left: .2em;
	font-size: 150%;
	vertical-align: middle;
}

.fa-check-circle {
	color: #34bc6e;
	margin-left: .2em;
	font-size: 150%;
	vertical-align: middle;
}


input:disabled:hover {
	cursor: not-allowed;
}

.info {
	color: #9320a2;
	display: none;
}

.warning {
	position: relative;
	background: #fed500;
	padding: 1em 1em 1em 4.5em;
}

.warning .fas {
	position: absolute;
	top: 50%;
	left: .5em;
	font-size: 2em;
	line-height: 0;
}

<?php
exit;
?>
</style>
<?php exit;
?>