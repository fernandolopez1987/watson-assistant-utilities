<?php session_start();

/*

dialog-exporter.php
===================

Designed for use with IBM Watson Assistant.
Use this tool to export your dialog data.


Metadata
--------

| Data       | Value                             |
| ----------:| --------------------------------- |
|    Version | 1.0                               |
|    Updated | 2019-05-221T00:02:42+00:00        |
|     Author | Adam Newbold, https://newbold.dev |
| Maintainer | Neatnik LLC, https://neatnik.net  |
|   Requires | PHP 5.6 or 7.0+ with curl         |


Changelog
---------

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


# Configuration

// You can find the Workspace ID, Username, and Password values in your Watson Assistant workspace; click the Deploy tab, then the Credentials screen.
// The latest API version can be found here: https://www.ibm.com/watson/developercloud/assistant/api/v1/curl.html?curl#versioning

define('WORKSPACE', 'change_me'); // Usually 36 characters, e.g. 1c7fbde9-102e-4164-b127-d3ffe2e58a04
define( 'USERNAME', 'change_me'); // Usually 36 characters, e.g. febeea03-84c4-57cb-af25-5f44b7af1f05
define( 'PASSWORD', 'change_me'); // Usually 12 characters, e.g. xCkZnpPbxLkQ
define(  'VERSION', '2018-07-10'); // The ISO 8601 date of the API version; you probably don't need to change this

function watson($api_call, $json = false) {
	$curl = curl_init();
	$url = 'https://gateway.watsonplatform.net/assistant/api/v1/workspaces/'.WORKSPACE.'/'.$api_call;
	curl_setopt_array($curl, array(
		CURLOPT_URL => $url,
		CURLOPT_USERPWD => USERNAME.':'.PASSWORD,
		CURLOPT_RETURNTRANSFER => true,
	));
	
	$response = curl_exec($curl);
	if($json) {
		return $response;
	}
	
	$response = json_decode($response);
	return $response;
}

$tmp = watson('dialog_nodes?version='.VERSION.'&page_limit=500&export=true&include_audit=true', true);
$tmp = json_decode($tmp);

foreach($tmp->dialog_nodes as $k => $obj) {
	$id = $obj->dialog_node;
	$types[] = $obj->type;
	$type = $obj->type;
	
	if(isset($obj->parent)) {
		$parent = $obj->parent;
	}
	else {
		$parent = '_root';
	}
	
	if(isset($obj->title)) {
		$title = $obj->title;
	}
	else {
		$title = 'Untitled Node';
	}
	
	$nodes[$id]['id'] = $id;
	$nodes[$id]['parent'] = $parent;
	$nodes[$id]['name'] = $title;
	$nodes[$id]['type'] = $type;

	$nodes[$id]['created'] = $obj->created;
	$nodes[$id]['updated'] = $obj->updated;

	if($type == 'standard') {
		if(isset($obj->output->text)) $nodes[$id]['output'] = $obj->output->text->values;
	}
	
	if(isset($obj->conditions)) {
		$nodes[$id]['conditions'] = $obj->conditions;
	}
}

$types = array_count_values($types);

$nodes['_root']['id'] = '_root';
$nodes['_root']['parent'] = 'None';
$nodes['_root']['name'] = 'Root';

function build_tree(array &$elements, $parent_id = 0) {
	$branch = array();
	
	foreach ($elements as $element) {
		if ($element['parent'] == $parent_id) {
			$children = build_tree($elements, $element['id']);
			if ($children) {
				$element['children'] = $children;
			}
			$branch[$element['id']] = $element;
			unset($elements[$element['id']]);
		}
	}
	return $branch;
}

function arr_to_csv($arr) {
	$tmp = array();
	foreach($arr as $item) {
		$tmp[] = '"'.str_replace(array('"', "\n"), array('""', ''), $item).'"';
	}
	return implode(',', $tmp)."\n";
}

$tree = build_tree($nodes, '_root');

function traverse($tree) {
	foreach($tree as $id => $arr) {
		$id = $arr['id'];
		$created = $arr['created'];
		$updated = $arr['updated'];
		$parent = $arr['parent'];
		$name = $arr['name'];
		$type = $arr['type'];
		$conditions = isset($arr['conditions']) ? $arr['conditions'] : null;
		
		$output = null;
		
		if(isset($arr['output'])) {
			if(count($arr['output']) == 1) {
				$output = $arr['output'][0];
			}
			else {
				$output = print_r($arr['output'], 1);
			}
		}
		
		echo arr_to_csv(array($id, $created, $updated, $parent, $name, $type, $conditions, $output));
		
		if(isset($arr['children'])) {
			traverse($arr['children']);
		}
	}
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=data.csv');

echo "ID,Created,Updated,Parent,Name,Type,Conditions,Output\n";
traverse($tree);

exit;

?>