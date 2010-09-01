<?php
	/*<!-- hml.php

	Copyright 2005 - 2010 University of Sydney Digital Innovation Unit
	This file is part of the Heurist academic knowledge management system (http://HeuristScholar.org)
	mailto:info@heuristscholar.org

	Concept and direction: Ian Johnson.
	Developers: Tom Murtagh, Kim Jackson, Steve White, Steven Hayes,
				Maria Shvedova, Artem Osmakov, Maxim Nikitin.
	Design and advice: Andrew Wilson, Ireneusz Golka, Martin King.

	Heurist is free software; you can redistribute it and/or modify it under the terms of the
	GNU General Public License as published by the Free Software Foundation; either version 3
	of the License, or (at your option) any later version.

	Heurist is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without
	even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License along with this program.
	If not, see <http://www.gnu.org/licenses/>
	or write to the Free Software Foundation,Inc., 675 Mass Ave, Cambridge, MA 02139, USA.

	-->*/


header('Content-type: text/xml; charset=utf-8');

if (@$argv) {
	// handle command-line queries

	$ARGV = array();
	for ($i=1; $i < count($argv); ++$i) {
		if ($argv[$i][0] === '-') {
			$ARGV[$argv[$i]] = $argv[$i+1];
			++$i;
		} else {
			array_push($ARGV, $argv[$i]);
		}
	}

	define('HEURIST_INSTANCE', @$ARGV['-instance'] ? $ARGV['-instance'] : '');

	$_REQUEST['q'] = @$ARGV['-q'];
	$_REQUEST['w'] = @$ARGV['-w']? $ARGV['-w'] : 'a';	// default to ALL RESOURCES
	$_REQUEST['stype'] = @$ARGV['-stype'];
	$_REQUEST['style'] = '';
	$_REQUEST['depth'] = @$ARGV['-depth'];
	$_REQUEST['rev'] = @$ARGV['-rev'];
	$_REQUEST['woot'] = @$ARGV['-woot'];
}

require_once(dirname(__FILE__).'/../../common/config/friendly-servers.php');
require_once(dirname(__FILE__).'/../../common/config/heurist-instances.php');
require_once(dirname(__FILE__).'/../../common/connect/db.php');
require_once(dirname(__FILE__).'/../../search/saved/loading.php');
require_once(dirname(__FILE__).'/../../common/php/requirements-overrides.php');
require_once(dirname(__FILE__).'/../../records/relationships/relationships.php');
require_once(dirname(__FILE__).'/../../records/woot/woot.php');

mysql_connection_db_select(DATABASE);


//----------------------------------------------------------------------------//
//  Tag construction helpers
//----------------------------------------------------------------------------//

function makeTag($name, $attributes=null, $textContent=null, $close=true) {
	$tag = "<$name";
	if (is_array($attributes)) {
		foreach ($attributes as $attr => $value) {
			$tag .= ' ' . htmlspecialchars($attr) . '="' . htmlspecialchars($value) . '"';
		}
	}
	if ($close  &&  ! $textContent) {
		$tag .= '/>';
	} else {
		$tag .= '>';
	}
	if ($textContent) {
		$tag .= htmlspecialchars($textContent);
		$tag .= "</$name>";
	}
	echo $tag . "\n";
}

function openTag($name, $attributes=null) {
	makeTag($name, $attributes, null, false);
}

function closeTag($name) {
	echo "</$name>\n";
}

function openCDATA() {
	echo "<![CDATA[\n";
}

function closeCDATA() {
	echo "]]>\n";
}


//----------------------------------------------------------------------------//
//  Authentication helpers
//----------------------------------------------------------------------------//

function single_record_retrieval($q) {
   if (preg_match ('/\bids?:([0-9]+)(?!,)\b/i', $q, $matches)) {
		$query = 'select * from records where rec_id='.$matches[1];
		$res = mysql_query($query);
		if (mysql_num_rows($res) < 1) return false;
		$rec = mysql_fetch_assoc($res);
		if ($rec['rec_wg_id']  &&  $rec['rec_visibility'] === 'Hidden') {
			return false;
		}
		return true;
	}
	return false;
}


//----------------------------------------------------------------------------//
//  Retrieve record- and detail- type metadata
//----------------------------------------------------------------------------//

$RTN = array();	//record type name
$RQS = array();	//record type specific detail name
$DTN = array();	//detail type name
$DTT = array();	//detail type base type
$INV = array();	//detail type inverse
$WGN = array();	//work group name
$RDL = array();	//record detail lookup
$ONT = array();	//ontology lookup
// record type labels
$query = 'SELECT rt_id, rt_name FROM rec_types';
$res = mysql_query($query);
while ($row = mysql_fetch_assoc($res)) {
	$RTN[$row['rt_id']] = $row['rt_name'];
	foreach (getRecordRequirements($row['rt_id']) as $rdr_rdt_id => $rdr) {
	// type-specific names for detail types
		$RQS[$rdr['rdr_rec_type']][$rdr['rdr_rdt_id']] = $rdr['rdr_name'];
	}
}

// base names, varieties for detail types
$query = 'SELECT rdt_id, rdt_name, rdt_type FROM rec_detail_types';
$res = mysql_query($query);
while ($row = mysql_fetch_assoc($res)) {
	$DTN[$row['rdt_id']] = $row['rdt_name'];
	$DTT[$row['rdt_id']] = $row['rdt_type'];
}

$INV = mysql__select_assoc('rec_detail_lookups',	//saw Enum change just assoc id to related id
							'rdl_id',
							'rdl_related_rdl_id',
							'1');

// lookup for detail type enum values
$query = 'SELECT rdl_id, rdl_value, rdl_ont_id FROM rec_detail_lookups';
$res = mysql_query($query);
while ($row = mysql_fetch_assoc($res)) {
	$RDL[$row['rdl_id']] = $row;
}

// lookup for ontologies
$query = 'SELECT ont_id, ont_name, ont_refurl FROM ontologies';
$res = mysql_query($query);
while ($row = mysql_fetch_assoc($res)) {
	$ONT[$row['ont_id']] = $row;
}

// group names
mysql_connection_db_select(USERS_DATABASE) or die(mysql_error());
$WGN = mysql__select_assoc('Groups', 'grp_id', 'grp_name', '1');
mysql_connection_db_select(DATABASE) or die(mysql_error());


$GEO_TYPES = array(
	'r' => 'bounds',
	'c' => 'circle',
	'pl' => 'polygon',
	'l' => 'path',
	'p' => 'point'
);

$MAX_DEPTH = @$_REQUEST['depth'] ? intval($_REQUEST['depth']) : 0;
$REVERSE = @$_REQUEST['rev'] === 'no' ? false : true;
$WOOT = @$_REQUEST['woot'] === '1' ? true : false;



//----------------------------------------------------------------------------//
//  Authentication
//----------------------------------------------------------------------------//

if (@$ARGV) {
	function get_user_id() { return 0; }
	function get_user_name() { return ''; }
	function get_user_username() { return ''; }
	function get_group_ids() { return array(HEURIST_USER_GROUP_ID); }
	function is_admin() { return false; }
	function is_logged_in() { return true; }
	$pub_id = 0;

} else if (@$_REQUEST['pub_id']) {
	$pub_id = intval($_REQUEST['pub_id']);
	require_once(dirname(__FILE__).'/../../common/connect/publish_cred.php');

} else if (friendlyServer(@$_SERVER['SERVER_ADDR']) && !(@$_REQUEST['w'] && $_REQUEST['w']=='bookmark')) {	// internal request ... apparently we don't want to authenticate ..?
	function get_user_id() { return 0; }
	function get_user_name() { return ''; }
	function get_user_username() { return ''; }
	function get_group_ids() { return array(2); }
	function is_admin() { return false; }
	function is_logged_in() { return true; }
	$pub_id = 0;

} else {
	$pub_id = 0;
	require_once(dirname(__FILE__).'/../../common/connect/cred.php');

	if (!is_logged_in()) { // check if the record being retrieved is a single non-protected record
		if (!single_record_retrieval($_REQUEST['q'])) {
			header('Location: ' . HEURIST_URL_BASE . 'common/connect/login.php');
			return;
		}
	}
}


//----------------------------------------------------------------------------//
// Traversal functions
// The aim here is to bundle all the queries for each level of relationships
// into one query, rather than doing them all recursively.
//----------------------------------------------------------------------------//

function findPointers($rec_ids) {
	$rv = array();
	$query = 'SELECT distinct rd_val
	            FROM rec_details
	       LEFT JOIN rec_detail_types on rd_type = rdt_id
	           WHERE rd_rec_id in (' . join(',', $rec_ids) .')
	             AND rdt_type = "resource"';
	$res = mysql_query($query);
	while ($row = mysql_fetch_assoc($res)) {
		array_push($rv, $row['rd_val']);
	}
	return $rv;
}

function findReversePointers($rec_ids, &$pointers) {
	$rv = array();
	$query = 'SELECT rd_val, rd_type, rd_rec_id
	            FROM rec_details
	       LEFT JOIN rec_detail_types ON rd_type = rdt_id
	       LEFT JOIN records ON rec_id = rd_rec_id
	           WHERE rd_val IN (' . join(',', $rec_ids) .')
	             AND rdt_type = "resource"
	             AND rec_type != 52';
	$res = mysql_query($query);
	while ($row = mysql_fetch_assoc($res)) {
		if (! @$pointers[$row['rd_val']]) {
			$pointers[$row['rd_val']] = array();
		}
		$pointers[$row['rd_val']][$row['rd_rec_id']] = $row['rd_type'];
		$rv[$row['rd_rec_id']] = 1;
	}
	return array_keys($rv);
}

function findRelatedRecords($rec_ids, &$relationships) {
	$rv = array();
	$query = 'SELECT a.rd_val,
	                 rec_id,
	                 b.rd_val
	            FROM rec_details a
	       LEFT JOIN records ON rec_id = a.rd_rec_id
	       LEFT JOIN rec_details b ON b.rd_rec_id = rec_id
	           WHERE a.rd_val IN (' . join(',', $rec_ids) . ')
	             AND a.rd_type IN (202,199)
	             AND rec_type = 52
	             AND b.rd_type = IF (a.rd_type = 202, 199, 202)';
	$res = mysql_query($query);
	while ($row = mysql_fetch_row($res)) {
		if (! @$relationships[$row[0]]) {
			$relationships[$row[0]] = array();
		}
		array_push($relationships[$row[0]], $row[1]);
		if ($row[2]) {
			$rv[$row[2]] = 1;
		}
	}
	return array_keys($rv);
}


function buildTree($rec_ids, &$reverse_pointers, &$relationships) {
	global $MAX_DEPTH, $REVERSE;

	$depth = 0;

	while ($depth++ < $MAX_DEPTH  &&  count($rec_ids) > 0) {
		$p_rec_ids = findPointers($rec_ids);
		$rp_rec_ids = $REVERSE ? findReversePointers($rec_ids, $reverse_pointers) : array();
		$rel_rec_ids = findRelatedRecords($rec_ids, $relationships);
		$rec_ids = array_merge($p_rec_ids, $rp_rec_ids, $rel_rec_ids);
	}
}



//----------------------------------------------------------------------------//
//  Output functions
//----------------------------------------------------------------------------//

function outputRecord($record, &$reverse_pointers, &$relationships, $depth=0) {
	global $RTN, $DTN, $RQS, $WGN, $MAX_DEPTH, $WOOT;

	openTag('record');
	makeTag('id', null, $record['rec_id']);
	makeTag('type', array('id' => $record['rec_type']), $RTN[$record['rec_type']]);
	makeTag('title', null, $record['rec_title']);
	if ($record['rec_url']) {
		makeTag('url', null, $record['rec_url']);
	}
	if ($record['rec_scratchpad']) {
		makeTag('notes', null, $record['rec_scratchpad']);
	}
	makeTag('added', null, $record['rec_added']);
	makeTag('modified', null, $record['rec_modified']);
	makeTag('workgroup', array('id' => $record['rec_wg_id']), $record['rec_wg_id'] > 0 ? $WGN[$record['rec_wg_id']] : 'public');

	foreach ($record['details'] as $dt => $details) {
		foreach ($details as $value) {
			outputDetail($dt, $value, $record['rec_type'], $reverse_pointers, $relationships, $depth);
		}
	}

	if ($WOOT  &&  $depth === 0) {
		$result = loadWoot(array('title' => 'record:'.$record['rec_id']));
		if ($result['success']) {
			openTag('woot', array('title' => 'record:'.$record['rec_id']));
			openCDATA();
			foreach ($result['woot']['chunks'] as $chunk) {
				echo $chunk['text'] . "\n";
			}
			closeCDATA();
			closeTag('woot');
		}
	}

	if ($depth < $MAX_DEPTH) {
		if (array_key_exists($record['rec_id'], $reverse_pointers)) {
			foreach ($reverse_pointers[$record['rec_id']] as $rec_id => $dt) {
				$child = loadRecord($rec_id);
				openTag('reversePointer', array('id' => $dt, 'type' => $DTN[$dt], 'name' => $RQS[$child['rec_type']][$dt]));
				outputRecord($child, $reverse_pointers, $relationships, $depth + 1);
				closeTag('reversePointer');
			}
		}
		if (array_key_exists($record['rec_id'], $relationships)  &&  count($relationships[$record['rec_id']]) > 0) {
			openTag('relationships');
			foreach ($relationships[$record['rec_id']] as $rel_id) {
				$rel = loadRecord($rel_id);
				outputRecord($rel, $reverse_pointers, $relationships, $depth);
			}
			closeTag('relationships');
		}
	}
	closeTag('record');
}

function outputDetail($dt, $value, $rt, &$reverse_pointers, &$relationships, $depth=0) {
	global $DTN, $DTT, $RDL, $ONT, $RQS, $INV, $GEO_TYPES, $MAX_DEPTH;

	$attrs = array('id' => $dt);
	if (array_key_exists($dt, $DTN)) {
		$attrs['type'] = $DTN[$dt];
	}
	if (array_key_exists($rt, $RQS)  &&  array_key_exists($dt, $RQS[$rt])) {
		$attrs['name'] = $RQS[$rt][$dt];
	}
	if ($dt === 200  &&  array_key_exists($value, $INV) && array_key_exists($INV[$value], $RDL)) {	//saw Enum change
		$attrs['inverse'] = $RDL[$INV[$value]]['rdl_value'];
	}
	if (is_array($value)) {
		if (array_key_exists('id', $value)) {
			// record pointer
			if ($depth < $MAX_DEPTH) {
				openTag('detail', $attrs);
				outputRecord(loadRecord($value['id']), $reverse_pointers, $relationships, $depth + 1);
				closeTag('detail');
			} else {
				makeTag('detail', $attrs, $value['id']);
			}
		} else if (array_key_exists('file', $value)) {
			$file = $value['file'];
			openTag('detail', $attrs);
				openTag('file');
					makeTag('id', null, $file['id']);
					makeTag('nonce', null, $file['nonce']);
					makeTag('origName', null, $file['origName']);
					makeTag('type', null, $file['type']);
					makeTag('size', null, $file['size']);
					makeTag('date', null, $file['date']);
					makeTag('description', null, $file['description']);
					makeTag('url', null, $file['URL']);
					makeTag('thumbURL', null, $file['thumbURL']);
				closeTag('file');
			closeTag('detail');
		} else if (array_key_exists('geo', $value)) {
			openTag('detail', $attrs);
				openTag('geo');
					makeTag('type', null, $GEO_TYPES[$value['geo']['type']]);
					makeTag('wkt', null, $value['geo']['wkt']);
				closeTag('geo');
			closeTag('detail');
		}
	} else if ($DTT[$dt] === 'date') {
		openTag('detail', $attrs);
		outputDateDetail($attrs, $value);
		closeTag('detail');
	} else if ($DTT[$dt] === 'resource') {
		openTag('detail', $attrs);
		outputRecord(loadRecord($value), $reverse_pointers, $relationships, $depth + 1);
		closeTag('detail');
	} else if ($DTT[$dt] === 'enum' && array_key_exists($value,$RDL)) {
		if (array_key_exists($RDL[$value]['rdl_ont_id'],$ONT)) {
			$attrs['ontology'] = $ONT[$RDL[$value]['rdl_ont_id']]['ont_name'];
		}
		makeTag('detail', $attrs, $RDL[$value]['rdl_value']);	//saw Enum  possible change
	} else {
		makeTag('detail', $attrs, $value);
	}
}

function outputDateDetail($attrs, $value) {
	makeTag('raw', null, $value);
	if (preg_match('/^\\s*-?(\\d+)\\s*$/', $value, $matches)) { // year only
		makeTag('year', null, $matches[1]);
	} else {
		$date = strtotime($value);
		if ($date) {
			makeTag('year', null, date('Y', $date));
			makeTag('month', null, date('n', $date));
			makeTag('day', null, date('j', $date));
			if (preg_match("![ T]!", $value)) {	// looks like there's a time
				makeTag('hour', null, date('H', $date));
				makeTag('minutes', null, date('i', $date));
				makeTag('seconds', null, date('s', $date));
			}
		} else {
			// cases that strtotime doesn't catch
			if (preg_match('/^([+-]?\d\d\d\d+)-(\d\d)$/', $value, $matches)) {
				// e.g. MMMM-DD
				makeTag('year', null, intval($matches[1]));
				makeTag('month', null, intval($matches[2]));
			} else {
				@list($date, $time) = preg_split("![ T]!", $value);
				@list($y,$m,$d) = array_map("intval", preg_split("![-\/]!", $date));
				if (! (1 <= $m && $m <= 12  &&  1 <= $d && $d <= 31)) {
					@list($d,$m,$y) = array_map("intval", preg_split("![-\/]!", $date));
				}
				if (! (1 <= $m && $m <= 12  &&  1 <= $d && $d <= 31)) {
					@list($m,$d,$y) = array_map("intval", preg_split("![-\/]!", $date));
				}
				if (1 <= $m && $m <= 12  &&  1 <= $d && $d <= 31) {
					makeTag('year', null, $y);
					makeTag('month', null, $m);
					makeTag('day', null, $d);
				}

				@list($h,$m,$s) = array_map("intval", preg_split("![-:]!", $time));
				if (0 <= $h && $h <= 23) {
					makeTag('hour', null, $h);
				}
				if (0 <= $m && $m <= 59) {
					makeTag('minutes', null, $m);
				}
				if (0 <= $s && $s <= 59) {
					makeTag('seconds', null, $s);
				}
			}
		}

	}
}

function outputRecords($result) {
	$reverse_pointers = array();
	$relationships = array();

	$rec_ids = array();
	foreach ($result['records'] as $record) {
		array_push($rec_ids, $record['rec_id']);
	}

	buildTree($rec_ids, $reverse_pointers, $relationships);

	foreach ($result['records'] as $record) {
		outputRecord($record, $reverse_pointers, $relationships);
	}
}


//----------------------------------------------------------------------------//
//  Turn off output buffering
//----------------------------------------------------------------------------//

if (! @$ARGV) {
	@apache_setenv('no-gzip', 1);
}
@ini_set('zlib.output_compression', 0);
@ini_set('implicit_flush', 1);
for ($i = 0; $i < ob_get_level(); $i++) { ob_end_flush(); }
ob_implicit_flush(1);


//----------------------------------------------------------------------------//
//  Output
//----------------------------------------------------------------------------//

$result = loadSearch($_REQUEST);

echo "<?xml version='1.0' encoding='UTF-8'?>\n";
openTag('hml');
/*
openTag('hml', array(
	'xmlns' => 'http://heuristscholar.org/heurist/hml',
	'xmlns:xsi' => 'http://www.w3.org/2001/XMLSchema-instance',
	'xsi:schemaLocation' => 'http://heuristscholar.org/heurist/hml http://heuristscholar.org/heurist/schemas/hml.xsd')
);
*/
$query_attrs = array_intersect_key($_REQUEST, array('q'=>1,'w'=>1,'depth'=>1));
if ($pub_id) {
	$query_attrs['pubID'] = $pub_id;
}
makeTag('query', $query_attrs);

makeTag('dateStamp', null, date('c'));

if (array_key_exists('error', $result)) {
	makeTag('error', null, $result['error']);
} else {
	openTag('ontologies');
	foreach($ONT as $ontology){
		$attrs = array('id' => $ontology['ont_id']);
		if ($ontology['ont_refurl']) {
			$attrs['namespace'] = $ontology['ont_refurl'];
		}
		makeTag('ontology', $attrs, $ontology['ont_name']);
	}
	closeTag('ontologies');
	makeTag('resultCount', null, $result['resultCount']);
	openTag('records');
	outputRecords($result);
	closeTag('records');
}

closeTag('hml');

?>
