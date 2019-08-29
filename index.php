<?php

/*

Watson Assistant Utilities
==========================

A suite of utilities designed for use with the IBM Watson Assistant platform.


Metadata
--------

| Data       | Value                                   |
| ----------:| ----------------------------------------|
|    Version | 1.0                                     |
|    Updated | 2019-08-28T02:37:19+00:00               |
|     Author | Adam Newbold, https://neatnik.net/adam  |
| Maintainer | Neatnik LLC, https://neatnik.net        |
|   Requires | PHP 7.0+, curl                          |


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

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

define('VERSION', '2019-02-28');

function assistant_info() {
	$url = $_SESSION['service_endpoint'].'/v1/workspaces/'.$_SESSION['skill_id'].'?version='.VERSION;
	$data = assistant_api($url);
	$description = ($data->description == '') ? '<em>no description</em>' : $data->description;
	return '<section class="attention"><h2>'.$data->name.'</h2><div style="text-align: right; float: right; margin: 0 0 0 2em; text-transform: uppercase; font-size: 80%;"><a style="border: 1px solid #fff; border-radius: .75em; text-decoration: none; padding: .25em .5em;" href="?disconnect"><i class="fas fa-plug"></i> Disconnect</a></div>'.$description.'</section>';
}


function assistant_api($url) {
	global $k;
	global $dialog_nodes;
	
	if(!isset($k)) {
		$k = 0;
		$dialog_nodes = array();
	}
	
	$curl = curl_init();
	
	curl_setopt_array($curl, array(
		CURLOPT_URL => $url,
		CURLOPT_USERPWD => $_SESSION['username'].':'.$_SESSION['api_key'],
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_FAILONERROR, true
	));
	$response = curl_exec($curl);
	$object = json_decode($response);
	
	if(isset($object->error)) {
		echo '<h1>There was a problem accessing the Watson Assistant API</h1><p>The response was:</p><pre>'.print_r($object, 1).'</pre></p>';
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_USERPWD, $_SESSION['username'].':'.$_SESSION['api_key']);
		curl_setopt($ch, CURLOPT_NOBODY, true);
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$headers = curl_exec($ch);
		curl_close($ch);
		$retry = str_replace('Retry-After: ', '', substr(substr($headers, strpos($headers, 'Retry-After: ')), 0, strpos(substr($headers, strpos($headers, 'Retry-After: ')), "\n")-1));
		if(is_numeric($retry)) echo '<p><strong>Try again in: '.floor($retry / 60).' min, '.floor((($retry / 60) - floor($retry / 60)) * 60).' sec</strong></p>';
		exit;
	}
	
	if(strpos($url, 'dialog_nodes') > 0) {
		$dialog_nodes = array_merge($dialog_nodes, $object->dialog_nodes);
	}
		
	if(isset($object->pagination->next_url)) {
		assistant_api($_SESSION['service_endpoint'].$object->pagination->next_url);
	}
	else {
		if(strpos($url, 'dialog_nodes') > 0) {
			$return['dialog_nodes'] = $dialog_nodes;
			return $return;
		}
		else {
			return $object;
		}
	}
}

function assistant_api_post($service_endpoint, $method, $data) {
	$url = $service_endpoint.$method.'?version='.VERSION;
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_USERPWD, $_SESSION['username'].':'.$_SESSION['api_key']);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
	curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
	$response = curl_exec($ch);
	curl_close($ch);
	return $response;
}

$message = null;

$service_endpoints['https://gateway.watsonplatform.net/assistant/api'] = 'Dallas';
$service_endpoints['https://gateway-wdc.watsonplatform.net/assistant/api'] = 'Washington, DC';
$service_endpoints['https://gateway-fra.watsonplatform.net/assistant/api'] = 'Frankfurt';
$service_endpoints['https://gateway-syd.watsonplatform.net/assistant/api'] = 'Sydney';
$service_endpoints['https://gateway-tok.watsonplatform.net/assistant/api'] = 'Tokyo';
$service_endpoints['https://gateway-lon.watsonplatform.net/assistant/api'] = 'London';

$service_endpoint = isset($_REQUEST['service_endpoint']) ? $_REQUEST['service_endpoint'] : 'https://gateway.watsonplatform.net/assistant/api';
$skill_id = isset($_REQUEST['skill_id']) ? $_REQUEST['skill_id'] : null;
$username = isset($_REQUEST['username']) ? $_REQUEST['username'] : 'apikey';
$api_key = isset($_REQUEST['api_key']) ? $_REQUEST['api_key'] : null;


# Search and replace text in dialog nodes

if(isset($_REQUEST['dialog-search-and-replace'])) {
	
	if(!isset($_SESSION['skill_id'])) goto connect;
	
	$search = isset($_REQUEST['search']) ? $_REQUEST['search'] : null;
	$replace = isset($_REQUEST['replace']) ? $_REQUEST['replace'] : null;
	
	$main = '<h1><a href="?main">Watson Assistant Utilities</a></h1>';
	
	$main .= assistant_info();
	
	$main .= '<section class="danger">
<h2 style="color: #fff;">Please back up your data <i style="color: #fff;" class="fas fa-skull-crossbones fa-2x fa-pull-right"></i></h2>
<p>This process uses POST API calls which will change the data in your Assistant. These operations are potentially destructive. If you don’t have a current backup of your workspace/skill, please make one now.</p>
</section>';
	
	$main .= '<section><h2>Dialog Search & Replace</h2>';
	
	$main .= '<p>This is a three step process:</p>
	<ol>
	<li>First, you’ll prepare by defining your search and replacement strings.</li>
	<li>Next, you’ll see a preview of the changes that will be made.</li>
	<li>Finally, if everything looks good, you can proceed with executing the changes.</li>
	</ol>
	
	</section>
	
	<section>
	
	<form id="connect" action="?dialog-search-and-replace" method="post">
	<fieldset>
	<legend>Prepare</legend>
	<div class="group">
	<p><label for="search">Search for</label> <input type="text" placeholder="something" name="search" id="search" value="'.$search.'"></p>
	<p><label for="replace">Replace with</label> <input type="text" placeholder="something else" name="replace" id="replace" value="'.$replace.'"></p>
	<button type="submit" name="prepare">Preview Results</button>
	</div>
	</fieldset>
	</form>';
	
	$main .= '</section>';
	
	if(isset($_REQUEST['prepare'])) {
		$i = 0;
		$main .= '<section id="results"><h2>Preview Changes</h2>';
		
		$method = '/v1/workspaces/'.$_SESSION['skill_id'].'/dialog_nodes';
		$url = $service_endpoint.$method.'?export=true&version='.VERSION;
		
		$dialog_data = assistant_api($url);
		
		$main .= '<div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); grid-gap: 1rem;">';
		
		foreach($dialog_nodes as $arr) {
			$title = isset($arr->title) ? $arr->title : 'untitled node';
			$type = $arr->type;
			if(!isset($arr->output->text->values)) continue;
			foreach($arr->output->text->values as $response) {				
				$tmp = $response;
				if (strpos($tmp, $search) !== false) {
					$main .= '<div style="background: #eee; padding: .5em;" class="grid-item">
					<strong>'.$title.'</strong>
					<span style="display: inline-block; font-size: 80%; text-transform: uppercase;">'.$type.'</span>
					<span style="display: block; margin: 1em 0 .5em 0; text-transform: uppercase; font-size: 80%;">From</span>
					'.str_replace($search, '<strong style="color: #dc267f;">'.$search.'</strong>', $tmp);
					$main .= '<span style="display: block; margin: 1em 0 .5em 0; text-transform: uppercase; font-size: 80%;">To</span>'.str_replace($search, '<strong style="color: #648fff;">'.$replace.'</strong>', $tmp).'</div>';
					$i++;
				}
			}
		}
		
		if($i == 0) {
			$main .= '<p>No matches found.</p>';
			$match_text = null;
		}
		else {
			$word = $i == 1 ? 'node' : 'nodes';
			$match_text = '<p><strong>'.number_format($i).'</strong> matching dialog '.$word.' found.</p>';
		}
		
		$main .= '</div>';
		
		$main .= $match_text;
		
		$main .= '</section>';
		
		if($i !== 0) {
			
			$main .= '<section>';
			$main .= '<h2>Confirm</h2>';
			$main .= '<form action="?dialog-search-and-replace" method="post">';
			$main .= '<p>If the changes shown above look good, click the button below to make them. Please don’t click without a good backup of your skill/workspace, though.</p>';
			$main .= '<input type="hidden" name="search" value="'.$search.'">';
			$main .= '<input type="hidden" name="replace" value="'.$replace.'">';
			$main .= '<input type="hidden" name="prepare" value="1">';
			$main .= '<button type="submit" name="confirm">Make the changes shown above</button>';
			$main .= '</form>';
			$main .= '</section>';
		}
	}
	
	if(isset($_REQUEST['confirm'])) {
		
		$method = '/v1/workspaces/'.$_SESSION['skill_id'].'/dialog_nodes';
		$dialog_data = assistant_api($service_endpoint.$method.'?export=true&version='.VERSION);
		
		foreach($dialog_nodes as $arr) {
			if(!isset($arr->output->text->values)) continue;
			foreach($arr->output->text->values as $response) {
				$tmp = $response;
				if (strpos($tmp, $search) !== false) {
					$dialog_node_id = $arr->dialog_node;
					foreach($arr->output->text->values as $i => $response) {
						$new = str_replace($search, $replace, $response);
						$arr->output->text->values[$i] = $new;
						$dialog_node_data = $arr;
						$dialog_node_data = json_encode($dialog_node_data);
						$method = '/v1/workspaces/'.$_SESSION['skill_id'].'/dialog_nodes/'.$dialog_node_id;
						$dialog_node_update = assistant_api_post($_SESSION['service_endpoint'], $method, $dialog_node_data);
					}
				}
			}
		}
		$main .= '<section class="ok"><h2 style="color: #fff;">Complete</h2><p>The items above have been renamed.</p></section>';
	}
	
	goto end;
}


# Disconnect

if(isset($_REQUEST['disconnect'])) {
	$_SESSION = null;
	session_destroy();
	goto connect;
}

# Main screen

if(isset($_REQUEST['main'])) {
	goto main;
}

# Connection screen

if(isset($_REQUEST['skill_id'])) {
	
	$_SESSION['service_endpoint'] = $_REQUEST['service_endpoint'];
	$_SESSION['skill_id'] = $_REQUEST['skill_id'];
	$_SESSION['username'] = $_REQUEST['username'];
	$_SESSION['api_key'] = $_REQUEST['api_key'];
	
	if(!isset($_REQUEST['agree'])) {
		$message = '<p class="attention" style="padding: 1em;">You need to check the box above to confirm your agreement before you can use this service.</p>';
		goto connect;
	}
		
	$method = '/v1/workspaces/'.$_SESSION['skill_id'];
	$data = assistant_api($service_endpoint.$method.'?version='.VERSION);
	
	if(isset($data->code) && $data->code == '401') {
		$json = json_encode($data, JSON_PRETTY_PRINT);
		$message = '<div class="attention" style="padding: .1em 1em;"><p>Couldn’t connect to Watson Assistant with the information provided. Double-check your configuration and try again.</p><p>The API response was:</p><pre>'.$json.'</pre></div>';
		goto connect;
	}
	
	goto main;
}
else {
	goto connect;
}


main:

if(!isset($_SESSION['skill_id'])) goto connect;

$method = '/v1/workspaces/'.$_SESSION['skill_id'];
$url = $service_endpoint.$method.'?version='.VERSION;
$main = '<h1><a href="?main">Watson Assistant Utilities</a></h1>';
$main .= assistant_info();
$main .= '<section><h2>Available Utilities</h2>';
$main .= '<ul><li><a href="?dialog-search-and-replace">Dialog Search & Replace</a></li></ul>';
$main .= '<p>More coming soon.</p>';
$main .= '</section>';
goto end;


connect:

$service_endpoints_html = null;
foreach($service_endpoints as $url => $location) {
	$selected = $url == $service_endpoint ? ' selected' : null;
	$service_endpoints_html .= '<option value="'.$url.'"'.$selected.'>'.$location.'</option>'."\n";
}

$main = <<<EOT
<h1><a href="?main">Watson Assistant Utilities</a></h1>

<p>This is a suite of utilities for use with the IBM Watson Assistant platform.</p>

<section>

<h2>About the utilities</h2>

<p>This is a collection of miscellaneous functions that enhance your use of Watson Assistant. These used to be <a href="https://github.com/neatnik/watson-assistant-utilities/tree/master/previous">standalone scripts</a> but are now being brought together within a single hosted service.</p>

<p>To use the utilities, simply connect to your Watson Assistant skill or workspace using the form below.</p>

<p>The source code is 100% free and <a href="https://github.com/neatnik/watson-assistant-utilities">open source</a>, and you’re encouraged to review the source code and run it on your own server. Always be cautious about using your IBM Watson service credentials with untrusted third parties (like this one).</p>

</section>

<section class="notice">
<h2>This is a beta service <i class="fas fa-exclamation-triangle fa-2x fa-pull-right"></i></h2>
<p>This service is in active development and may not work perfectly. Please <a style="color: #000;" href="https://github.com/neatnik/watson-assistant-utilities/issues">report any issues</a> that you encounter. By using this service, you agree that Neatnik LLC is not responsible for any issues that might arise related to its use. You should make a backup copy of your Assistant workspace/skill before using this service.</p>
</section>

<section>

<div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); grid-gap: 1rem 3rem;">

<div class="grid-item">

<form id="connect" action="?#connect" method="post">
<fieldset>
<legend>Connect to Watson</legend>
<div class="group">

<label for="service_endpoint">Service Endpoint</label>
<select name="service_endpoint">
$service_endpoints_html
</select>

<label for="skill_id">Skill / Workspace ID</label>
<input style="width: 95%;" type="text" id="skill_id" name="skill_id" placeholder="674cf8df-7ad6-9021-81f5-73abcf72053c" value="$skill_id">
<label for="username">Username</label>
<input style="width: 95%;" type="text" id="username" name="username" placeholder="apikey" value="$username">
<label for="api_key">API Key / Password</label>
<input style="width: 95%;" type="text" id="api_key" name="api_key" placeholder="tnQu1zcfs4rsXaIq7HLSG5KMpMYz00XYV3uW0IA3cS0V" value="$api_key">

</div>

<div class="group">
<input type="checkbox" name="agree" id="agree" value="agree"> <label for="agree" style="display: inline; line-height: 110%; font-size: 90%;"> I understand that Neatnik LLC is not responsible for any issues that might arise from the use of this service.</label>
</div>

$message

<button type="submit">Agree & Connect</button>
</fieldset>
</form>

</div>

<div class="grid-item">
<h3>Connection information</h3>

<p>Your service endpoint is tied to the region in which your Watson Assistant service is housed. This appears on your <a href="https://cloud.ibm.com/resources">IBM Cloud Resources</a> page in the Location column.</p> 

<p>You can find your API information in Watson Assistant:</p>
<ul>
<li>Click the Skills tab.</li>
<li>Click on the vertical ellipsis icon <span style="background: #eee;"><i class="far fa-fw fa-ellipsis-v"></i></span> beside your skill name.</li>
<li>Click <strong>View API details</strong>.</li>
</ul>

<p>If your Watson Assistant service uses IBM Cloud Identity and Access Management (IAM) authentication, then you can leave the username set to <em>apikey</em>. Otherwise, enter your Workspace’s username.</p>

</div>

</div>
EOT;

end:

?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta property="og:title" content="Watson Assistant Utilities">
<meta property="og:url" content="https://neatnik.net/watson/assistant/">
<meta property="og:description" content="A collection of utilities for use with IBM’s Watson Assistant service.">
<meta name="viewport" content="width=device-width">
<link rel="apple-touch-icon" sizes="180x180" href="/meta/icons/apple-touch-icon.png">
<link rel="icon" type="image/png" sizes="32x32" href="/meta/icons/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="/meta/icons/favicon-16x16.png">
<link rel="manifest" href="/meta/icons/site.webmanifest">
<link rel="mask-icon" href="/meta/icons/safari-pinned-tab.svg" color="#eedd00">
<link rel="shortcut icon" href="/meta/icons/favicon.ico">
<meta name="msapplication-TileColor" content="#eedd00">
<meta name="msapplication-config" content="/meta/icons/browserconfig.xml">
<meta name="theme-color" content="#ffffff">
<title>Watson Assistant Utilities</title>
<link rel="stylesheet" type="text/css" href="/style/style.css">
<style>
h1 a:link, 
h1 a:visited, 
h1 a:hover, 
h1 a:active {
	text-decoration: none;
	color: #000;
}
</style>
</head>
<body>

<header>

<div class="logo">
<a href="/"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 860 860">
<path fill="#333" d="M738 617l-20 40a71 71 0 0 1-95 32l-50-26a135 135 0 0 1-78 100l-22 44a60 60 0 0 1-80 27l-84-42-11 11a111 111 0 0 1-78 32 15 15 0 0 1-15-15v-74a75 75 0 0 1-31-101 71 71 0 0 1-52-102l20-40a71 71 0 0 1 12-17 50 50 0 0 1-11-20c-8-28 12-62 50-94l-59-30a82 82 0 0 1 44-155l-14-33a18 18 0 0 1-2-5c-9-32 75-83 187-113s208-27 217 5a18 18 0 0 1 1 5v1a85 85 0 0 1 68 83v117c58 7 98 26 106 58s-17 70-66 105c0 42-15 82-40 116a71 71 0 0 1 17-9 71 71 0 0 1 86 99z"/>
<path fill="#fff" d="M282 234l92 46q18-6 36-11c18-5 37-9 54-13v-79a559 559 0 0 0-75 15c-41 12-78 27-107 42z"/>
<path fill="#ed0" d="M226 637a114 114 0 0 0-1 74 117 117 0 0 0 5 12 45 45 0 0 1-4-87zm25 117a76 76 0 0 1-11 1h-5v49a81 81 0 0 0 43-23l4-4a115 115 0 0 1-31-23zm134-54a15 15 0 0 0 15 15h70a10 10 0 0 1 9 14l-32 64a30 30 0 0 1-40 13l-111-55a85 85 0 0 1-38-114l15-31a15 15 0 0 0-7-20l-20-10a15 15 0 0 0-20 7l-5 10a41 41 0 0 1-73-37l20-40a41 41 0 0 1 55-18l28 14a15 15 0 0 0 5 1 205 205 0 0 0 21 28 15 15 0 1 0 22-20c-24-26-38-57-43-90 0-32 74-77 170-103 91-25 173-25 195-1a159 159 0 0 1 24 83c0 68-44 130-113 159a15 15 0 1 0 12 28 229 229 0 0 0 45-25l24 12a15 15 0 0 0 20-7l5-10a41 41 0 0 1 73 37l-20 40a41 41 0 0 1-55 18l-70-35a15 15 0 0 0-21 13 105 105 0 0 1-36 79 40 40 0 0 0-40-34h-69a15 15 0 0 0-15 15zm195-295a25 25 0 1 0 25-25 25 25 0 0 0-25 25zm-160 60a15 15 0 0 0-15 15 55 55 0 0 0 95 38 55 55 0 0 0 95-38 15 15 0 0 0-30 0 25 25 0 0 1-49 6l35-35a15 15 0 1 0-21-21l-30 29-29-29a15 15 0 1 0-21 21l35 35a25 25 0 0 1-50-6 15 15 0 0 0-15-15zm-35-35a25 25 0 1 0-25-25 25 25 0 0 0 25 25zM123 247a52 52 0 0 0 23 70l74 36c31-21 71-42 115-59l-142-71a52 52 0 0 0-70 24zm482-3V130a55 55 0 1 0-110 0v122c40-7 77-9 110-8z"/>
</svg></a>
</div>
<div class="logotype">
<a href="/">Neatnik</a>
</div>

</header>

<main>

<?php echo $main; ?>

<p>IBM Watson® is a registered trademark of IBM Corporation.</p>

</main>

<footer>
<p>&copy; Neatnik LLC
<span class="row"><a href="/about/">About</a>
<!--<a href="https://status.neatnik.net">Status</a>-->
<a href="/terms-of-service/">Terms</a>
<a href="/privacy-policy/">Privacy</a>
<a href="/contact/">Contact</a></span>
</p>
</footer>

<div class="end"><i class="fad fa-sparkles"></i></div>

</body>
</html>