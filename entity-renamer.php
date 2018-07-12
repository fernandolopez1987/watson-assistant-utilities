<?php

/*

entity-renamer.php
==================

Rename your entities across all dialog nodes with ease. Designed for use with IBM Watson Assistant.


Metadata
--------

| Data       | Value                            |
| ----------:| -------------------------------- |
|    Version | 1.0                              |
|    Updated | 2018-07-12T04:15:40+00:00        |
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
if(isset($_REQUEST['scripts'])) goto scripts;

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
	
	if(isset($object->entities)) {
		foreach($object->entities as $entity => $ent_obj) {
			if(substr($ent_obj->entity, 0, 4) == 'sys-') continue; // // skip system entities
			$entities[] = $ent_obj->entity;
			foreach($ent_obj->values as $i => $val_obj) {
				$entity_values[$ent_obj->entity][] = $val_obj->value;
			}
		}
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
		watson($object->pagination->next_url);
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

// Process rename requests
if(isset($_REQUEST['rename'])) {
	$responses = array();
	$type = $_REQUEST['type'];
	$parent_entity = $_REQUEST['parent_entity'];
	$old_name = $_REQUEST['old_name'];
	$new_name = $_REQUEST['new_name'];
	$used_in = $_REQUEST['used_in'];
	$used_in = rawurldecode($used_in);
	
	$entity = $type == 'base' ? $old_name : $parent_entity;
	
	// First, rename the entity itself
	// Obtain the entity data
	$entity_data = assistant_api('/v1/workspaces/'.WORKSPACE.'/entities/'.$entity.'?version='.VERSION.'&export=true', true);
	
	// Rename it
	$entity_data = json_decode($entity_data);
	if($type == 'base') {
		$entity_data->entity = $new_name;
	}
	else {
		foreach($entity_data->values as $k => $obj) {
			if($obj->value == $old_name) {
				$entity_data->values[$k]->value = $new_name;
			}
		}
	}
	$entity_data = json_encode($entity_data, JSON_PRETTY_PRINT);
	
	// Update it
	$entity_update = assistant_api_post('/v1/workspaces/'.WORKSPACE.'/entities/'.$entity.'?version='.VERSION, $entity_data);
	$responses[] = $entity_update;
	
	// Next, fetch each node that uses it and change the condition there
	// Obtain the node data
	if($used_in !== '') { // if the node is actually used in dialog
		
		foreach(json_decode($used_in) as $node) {
			
			// Obtain the node data
			$node_data = assistant_api('/v1/workspaces/'.WORKSPACE.'/dialog_nodes/'.$node.'?version='.VERSION.'&export=true', true);
			$node_data = json_decode($node_data);
			
			// Replace the conditions
			if($type == 'base') {
				$node_data->conditions = str_replace($old_name, $new_name, $node_data->conditions);
			}
			else {
				$node_data->conditions = str_replace($parent_entity.':'.$old_name, $parent_entity.':'.$new_name, $node_data->conditions);
			}
			$node_data = json_encode($node_data, JSON_PRETTY_PRINT);
			
			// Update it
			$node_update = assistant_api_post('/v1/workspaces/'.WORKSPACE.'/dialog_nodes/'.$node.'?version='.VERSION, $node_data);
			$responses[] = $node_update;
		}
	}
	die(json_encode(array('output'=>'success', $responses)));
}

assistant_api('/v1/workspaces/'.WORKSPACE.'/entities?version='.VERSION.'&page_limit=500&export=true&include_audit=true');
assistant_api('/v1/workspaces/'.WORKSPACE.'/dialog_nodes?version='.VERSION.'&page_limit=500&export=true&include_audit=true');

$form = null;
foreach($entities as $entity) {
	if(isset($dialog_ids['@'.$entity])) {
		$word = count($dialog_ids['@'.$entity]) == 1 ? 'node' : 'nodes';
		$used = 'will rename in <strong>'.count($dialog_ids['@'.$entity]).'</strong> '.$word;
	}
	else {
		$used = 'not used directly in any nodes';
	}
	$form .= '<p>@<input type="text" name="entity" data-entity-type="base" data-entity="'.$entity.'" data-used-in="'.rawurlencode(json_encode(@$dialog_ids['@'.$entity])).'" value="'.$entity.'"> <span class="sizer">'.$entity.'</span> <span class="info">'.$used.'</span> <span class="button entity"><i class="fas fa-pencil-alt"></i> Rename</span></p>';
	if(isset($entity_values[$entity])) {
		sort($entity_values[$entity]);
		foreach($entity_values[$entity] as $entity_value) {
			if(isset($dialog_ids['@'.$entity.':'.$entity_value])) {
				$word = count($dialog_ids['@'.$entity]) == 1 ? 'node' : 'nodes';
				$used = 'will rename in <strong>'.count($dialog_ids['@'.$entity]).'</strong> '.$word;
			}
			else {
				$used = 'not used in any nodes';
			}
			$form .= '<p class="">@<span class="entity-name">'.$entity.'</span>:<input type="text" name="entity" data-entity-type="value" data-entity="'.$entity.'" data-entity-value="'.$entity_value.'" data-used-in="'.rawurlencode(json_encode(@$dialog_ids['@'.$entity.':'.$entity_value])).'" value="'.$entity_value.'"> <span class="sizer">'.$entity_value.'</span> <span class="info">'.$used.'</span> <span class="button entity_value"><i class="fas fa-pencil-alt"></i> Rename</span></p>';
		}
	}
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<title>Entity Renamer</title>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width">
<meta property="og:title" content="Entity Renamer">
<meta property="og:description" content="Rename your entities across all dialog nodes with ease. Designed for use with IBM Watson Assistant.">
<meta property="og:url" content="https://neatnik.net/watson/assistant/utilities/entity-renamer.php">
<meta property="og:image" content="https://neatnik.net/watson/assistant/utilities/neatnik-watson-assistant-utilities.png">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
<script src="?scripts&time=<?php echo time(); ?>"></script>
<link rel="stylesheet" type="text/css" href="?style&time=<?php echo time(); ?>">
</head>
<body>

<h1>Entity Renamer</h1>

<p class="warning"><i class="fas fa-exclamation-triangle fa-fw"></i> This utility will write data to your workspace. Since it probably has bugs, you should have a backup copy of your workspace (using the <em>Download as JSON</em> option in the Watson Assistant interface) before proceeding.</p>

<p><i class="fas fa-info-circle fa-fw"></i> <strong>Directions:</strong> Change the name of an entity below and press enter. Click the button to confirm, and the entity will be renamed everywhere across all dialog conditions.</p>

<section>

<?php echo $form; ?>

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

.button {
	display: inline-block;
	background: #5392ff;
	color: #fff;
	font-size: .9em;
	line-height: 100%;
	padding: .5em;
	margin-left: .3em;
	border-radius: .2em;
	display: none;
}

.button:hover {
	cursor: pointer;
}

section {
	border-top: 1px solid #555;
	margin-top: 1em;
	clear: both;
	color: #888;
}

label {
	margin: 0 .5em;
}

.intent {
	width: 10em;
}

input, .sizer {
	display: inline-block;
	border-bottom: 2px solid #5392ff;
	color: #000;
	border-radius: 0;
	padding: 0;
}

input[type="submit"] {
	cursor: pointer;
	color: #000;
	background: #fff;
	margin-left: .5em;
}

input[type="submit"]:hover {
	cursor: pointer;
	color: #fff;
	background: #dc267f;
	border: 1px solid #dc267f;
}

.sizer {
	display: none;
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
<script><?php scripts: header("Content-type: text/javascript"); ?>
$(function() {
	$.ajaxSetup ({
		cache: false
	});
	$('.button').click(function() { // entity value
		var button = $(this);
		if($(button).hasClass('entity_value')) {
			var type = 'value';
			var old_name = $(button).parent().find('input').data('entity-value');
			var new_name = $(button).parent().find('input').val();
			var used_in = $(button).parent().find('input').data('used-in');
			var parent_entity = $(button).parent().find('input').data('entity');
		}
		else { // entity base
			var type = 'base';
			var old_name = $(button).parent().find('input').data('entity');
			var new_name = $(button).parent().find('input').val();
			var used_in = $(button).parent().find('input').data('used-in');
			var parent_entity = null;
			$(button).data('entity', new_name);
			$('.entity-name:contains("'+old_name+'")').data('entity', new_name);
			$('.entity-name:contains("'+old_name+'")').html(new_name);
			$("input[data-entity]").each(function(){
				if($(this).data('entity') == old_name) {
					$(this).data('entity', new_name);
				}
			});
		}
		$(button).parent().find('input').prop('disabled', true);
		$(button).parent().find('input').css({'border-bottom':'2px dotted #ccc', 'color':'#999'});
		$(button).hide();
		$(button).parent().append('<i class="fas fa-cog fa-spin"></i>');
		$.ajax({
			url: '?rename',
			type: 'POST',
			data: {
				'type': type,
				'parent_entity': parent_entity,
				'old_name': old_name,
				'new_name': new_name,
				'used_in': used_in
			},
			dataType:'json',
			success: function(request, error) {
				//console.log('Success: '+JSON.stringify(request));
				$(button).parent().find('.fa-cog').remove();
				$(button).parent().append('<i class="fas fa-check-circle"></i>');
				$(button).parent().find('input').prop('disabled', false);
				$(button).parent().find('input').css({'background':'#fff', 'border-bottom':'2px solid #5392ff', 'color':'#000'});
				if(type == 'base') {
					$(button).parent().find('input').data('entity', new_name);
				}
				else {
					$(button).parent().find('input').data('entity', parent_entity);
					$(button).parent().find('input').data('entity-value', new_name);
				}
			},
			error: function(request, error) {
				//console.log('Failure: '+JSON.stringify(error));
				//console.log('Response: '+request.responseText);
			}
		});
	});
});

$(window).bind('load', function(){
	$('input').each(function() {
		$(this).on('keyup', function (e) {
			if (e.keyCode == 13) {
				$(this).blur();
			}
		});
		$(this).width($(this).parent().find('.sizer').width());
		$(this).focus(function() {
			$(this).parent().find('.fa-check-circle').hide();
			$(this).parent().find('.button').hide();
			$(this).parent().find('.info').show();
			$(this).css({'background': '#e9e8ff', 'border-bottom': '2px solid #9b82f3'});
			$(this).width('20em');
		});
		$(this).blur(function() {
			$(this).parent().find('.sizer').html($(this).val());
			$(this).css({'background': '#fff', 'border-bottom': '2px solid #5392ff'});
			$(this).width($(this).parent().find('.sizer').outerWidth());
			$(this).parent().find('.info').hide();
			if($(this).data('entity-type') == 'base') {
				if($(this).val()!== $(this).data('entity')) {
					$(this).parent().find('.button').show();
				}
			}
			else {
				if($(this).val()!== $(this).data('entity-value')) {
					$(this).parent().find('.button').show();
				}
			}
		});
		$(this).width($(this).parent().find('.sizer').width());
	});
});
<?php exit;
?>
</script>