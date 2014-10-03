<?php

/* Usage

 API
  http://berea.mobi/tinydb/<db>/setavalue
    tag, value
  http://berea.mobi/tinydb/<db>/getvalue
    tag

 SETAVALUE
  curl -d "tag=fish&value=catcat" http://berea.mobi/tinydb/matt/setavalue

 GETVALUE
  curl -d "tag=fish" http://berea.mobi/tinydb/matt/getvalue
*/

// CONFIG GLOBALS ------------------
$DATA_DIRECTORY = "/home/bereamobi/tinydb/";
// END GLOBALS ---------------------


function llog ($tag, $str) {
  $current_path = realpath(dirname(__FILE__)) . DIRECTORY_SEPARATOR;
  file_put_contents($current_path . DIRECTORY_SEPARATOR . "log", $tag . ": " . $str . "\n", FILE_APPEND);
}

$request  = str_replace("/tinydb", "", $_SERVER['REQUEST_URI']); 
llog("REQ", $request);

$parts = explode ("/", $request);
$api = $parts[2];

// SQLITE3
$db  = $parts[1];
$db_path    = $DATA_DIRECTORY . $db . ".sqlite3";
$handle = false;

function subst($str, $vars, $char = '%') {
   $tmp = array();
   foreach($vars as $k => $v) {
       $tmp[$char . $k . $char] = $v;
   }
   return str_replace(array_keys($tmp), array_values($tmp), $str);
}
 
function create_db () {	
  global $db_path, $handle;
  
  
	if (!file_exists($db_path)) {
  
    $handle  = new SQLite3($db_path);
    
    $query = "
    CREATE TABLE IF NOT EXISTS tinywebdb (
      tag STRING PRIMARY KEY,
      value STRING)
      ";
  	$handle->exec($query) or die('Create db failed');
	} else {
    $handle  = new SQLite3($db_path);
	}
}

function tag_does_not_exist ($tag) {
  if (getvalue($tag) === "not found") {
    return false;
  } else {
    return true;
  }
}

function is_special_set_handler ($tag) {
	$specials = array ("delete");
	return in_array ($tag, $specials);
}

function special_set_handler ($tag, $value) {
	global $handle;
	if ($tag === 'delete') {
    $stmt = subst("DELETE FROM tinywebdb WHERE tag = '%tag%';", 
						array('tag' => json_decode($value)));
    llog("DELETE TAG", $stmt);
    $handle->exec($stmt);
		return 1;
		}

	return 0;	
}

function storeavalue ($tag, $value) {
  global $handle;

	if (is_special_set_handler ($tag)) {
		llog("IT IS SPECIAL", $tag);
		special_set_handler($tag, $value);
	} else {

  llog("EXISTS CHECK", getvalue($tag));
  
  if (tag_does_not_exist($tag)) {
    $stmt = subst("DELETE FROM tinywebdb WHERE tag = '%tag%';", array('tag' => $tag));
    llog("DELETE", $stmt);
    $handle->exec($stmt);
  }
  
  if (preg_match("/\(list (.*)\)/", $value, $m)) {
    $split = preg_split("/,/", $m[1]);
    $trimmed = array_map('trim',$split);
    $insert = json_encode($trimmed);
    llog("SPLIT TEXT TO LIST", $insert);
  } else {
    $insert = $value;
  }
  
  $stmt = subst("INSERT INTO tinywebdb(tag, value) VALUES ('%tag%', '%value%');", 
          array('tag' => $tag, 'value' => $insert));
          
  llog("INSERT", $stmt);
  
  $handle->exec($stmt);
  return 1;  
}
}

function is_special_get_handler ($tag) {
	$specials = array ("listtags");

	return in_array ($tag, $specials);		
}

function special_get_handler ($tag) {
	global $handle;
	if ($tag === 'listtags') {
		$q = "SELECT DISTINCT tag FROM tinywebdb;";
		$result = $handle->query($q);

		if ($result) {
			$result_list = array();
			# $rows = sqlite_fetch_all ($result, SQLITE_ASSOC);
    	$row = $result->fetchArray();
			while ($row) {
				array_push ($result_list, $row[0]);
    		$row = $result->fetchArray();
			}
			return $result_list;
		}	
	} 
}


function getvalue ($tag) {
  global $handle;

	if (is_special_get_handler($tag)) {
		return special_get_handler($tag);
	} else {
  	$q = subst("SELECT * FROM tinywebdb WHERE tag = '%tag%'", array('tag' => $tag));
  	llog("GETVALUE", $q);
  
  	$result = $handle->query($q);
  	if ($result) {
    
    	$row = $result->fetchArray();
    	llog("ROW", $row[0]);
    	llog("ROW", $row[1]);
    
			if ($row[1] === "") {
      	return "'not found'";
    	}  else {
      	llog("JSON", json_decode($row[1]));
      	return $row[1];
    	}
  	} else {
    	return "'lookup error'";
  	}
	}
}


// BEGINNING OF SCRIPT
create_db();

switch ($api) {
	case "getvalue":
    $tag = trim($_POST["tag"]);
		$result = getvalue ($tag);
    llog("API", "getvalue");
    llog("DB", $db_path);
    llog("TAG", $tag);
    llog("RESULT", $result);
    echo json_encode(array("VALUE", $tag, $result));
    break;
	case "storeavalue":
    llog("API", "storeavalue");
    $tag   = trim($_POST["tag"]);
    $value = trim($_POST["value"]);
		storeavalue ($tag, $value);
    echo json_encode(array("VALUE", $tag, $value));
    break;
}

?>
