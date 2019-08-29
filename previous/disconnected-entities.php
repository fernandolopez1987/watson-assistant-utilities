<?php

/*

disconnected-entities.php
=========================

Designed for use with IBM Watson Assistant (formerly IBM Watson Conversation Service).
Lists dialog nodes that use entities which do not exist, and entities that are not used by any dialog nodes.


Metadata
--------

| Data       | Value                            |
| ----------:| -------------------------------- |
|    Version | 1.0.1                            |
|    Updated | 2018-07-07T06:12:52+00:00        |
|     Author | Adam Newbold, https://adam.lol   |
| Maintainer | Neatnik LLC, https://neatnik.net |
|   Requires | PHP 5.6 or 7.0+ with curl        |


Changelog
---------

### 1.0.1

 * Fixed a bug where multiple missing entities associated with the same node would not all be shown

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

// Optional: Uncomment the item below and set your Watson Assistant base URL to enable direct links to dialog nodes.
// To obtain the URL, visit your IBM Watson Assistant Workspaces page (the one that shows all of your accout's workspaces) and copy the URL in your browser's address bar.
// It should look something like this: https://assistant-region.watsonplatform.net/region/some-kind-of-GUID/workspaces

//define('ASSISTANT_URL', 'optionally_chage_me');


# Main code begins here

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
	
	if(isset($object->entities)) {
		foreach($object->entities as $entity => $ent_obj) {
			$entity_name = $ent_obj->entity;
			$entities[] = '@'.$entity_name;
			foreach($ent_obj->values as $i => $val_obj) {
				$entities[] = '@'.$entity_name.':'.$val_obj->value;
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
						$conditions[] = $bits[0];
						$conditions[] = $condition_entity;
						if(isset($node->dialog_node)) {
							$dialog_ids[$bits[0]][] = $node->dialog_node;
							$dialog_ids[$condition_entity][] = $node->dialog_node;
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

function html($html = null) {
	echo <<<EOT
<!DOCTYPE html><html lang="en"><head><title>Disconnected Entities</title><meta charset="utf-8"><meta name="viewport" content="width=device-width"><style>@import url('//fonts.googleapis.com/css?family=IBM+Plex+Sans:400,700');@import url('//use.fontawesome.com/releases/v5.1.0/css/solid.css');@import url('//use.fontawesome.com/releases/v5.1.0/css/fontawesome.css');body{font:1em/150% 'IBM Plex Sans',sans-serif;margin:2em;}h1,h2,ul{font-weight:normal;margin:2rem 0;}li a:link,li a:visited,li a:hover,li a:active{text-decoration:none;}</style></head><body>$html
EOT;
}

$http = (php_sapi_name() == "cli") ? false : true;

assistant_api('/v1/workspaces/'.WORKSPACE.'/entities?version='.VERSION.'&page_limit=500&export=true&include_audit=true', true);
assistant_api('/v1/workspaces/'.WORKSPACE.'/dialog_nodes?version='.VERSION.'&page_limit=500&export=true&include_audit=true', true);

if($http) html('<h1>Disconnected Entities</h1>');
else echo "\nDisconnected Entities";

if($http) echo '<h2>Dialog using entities that do not exist</h2>';
else echo "\n\nDialog using entities that do not exist:";

$diff = array_diff($conditions, $entities);

if($http) echo '<ul>';
else echo "\n";

foreach($diff as $entity) {
	foreach($dialog_ids[$entity] as $node) {
		$missing_entities[$node][] = $entity;
	}
}

asort($missing_entities);
foreach($missing_entities as $node => $array) {
	$node_info = defined('ASSISTANT_URL') ? '<a href="'.ASSISTANT_URL.'/'.WORKSPACE.'/build/dialog#node='.$node.'"><i class="fas fa-external-link-alt"></i></a>' : 'in '.$node;
	$array = array_count_values($array);
	foreach($array as $entity => $k) {
		if($http) echo '<li><strong>'.$node_titles[$node].'</strong> uses <strong>'.$entity.'</strong> '.$node_info.'</li>';
		else echo "\n".'  - "'.$node_titles[$node].'" uses '.$entity.' in '.$node;
	}
}

if($http) echo '</ul>';
else echo "\n";

if($http) echo '<h2>Entities not used in any dialog</h2>';
else echo "\nEntities not used in any dialog:";

$diff = array_diff($entities, $conditions);
if($http) echo '<ul>';
else echo "\n";
foreach($diff as $entity) {
	if($http) echo '<li>'.$entity.'</li>';
	else echo "\n - $entity";
}
if($http) echo '</ul>';
else echo "\n";

echo "\n";

?>