<?php

define('dirname(__FILE__)', dirname(__FILE__));	// this line can be removed on new versions of PHP as dirname(__FILE__) is a magic constant
require_once(dirname(__FILE__).'/../../common/connect/cred.php');
require_once(dirname(__FILE__).'/../../common/connect/db.php');
require_once(dirname(__FILE__).'/../../search/saved/loading.php');

if (! is_logged_in()  ||  ! is_admin()) return;


$fuzziness = intval($_REQUEST['fuzziness']);
if (! $fuzziness) $fuzziness = 20;

$bibs = array();
$dupes = array();
$dupekeys = array();
$recsGivenNames = array();
$dupeDifferences = array();

$recIDs = null;
$result = null;
if (@$_REQUEST['q']){
	$_REQUEST['l'] = -1;  // tell the loader we want all the ids
	$result = loadSearch($_REQUEST,false,true); //get recIDs for the search
	if ($result['resultCount'] > 0 && $result['recordCount'] > 0) {
		$recIDs = $result['recIDs'];
	}
}
// error output
if (!@$_REQUEST['q'] || array_key_exists("error", @$result) || @$result['resultCount'] != @$result['recordCount']){
	//error has occured tell the user and stop
?>
<html>
<body>
	An error occured while retrieving the set of records to compare:

<?php
	if (!@$_REQUEST['q']){
		print "You must supply a query in order to specify the search set of records.";
	} else if (array_key_exists("error", $result)){
		print "Error loading search: " . $result['error'];
	} else if (@$result['resultCount'] != @$result['recordCount']){
		print " The number of recIDs returned is not equal to the total number in the query result set.";
	}
?>
</body>
</html>

<?php
	return;
} // end of error output

mysql_connection_db_insert(DATABASE);

$res = mysql_query('select dd_hash from dupe_differences');
while ($row = mysql_fetch_assoc($res)){
	array_push($dupeDifferences,$row['dd_hash']);
}

if ($_REQUEST['dupeDiffHash']){
	foreach($_REQUEST['dupeDiffHash'] as $diffHash){
		if (! in_array($diffHash,$dupeDifferences)){
			array_push($dupeDifferences,$diffHash);
			$res = mysql_query('insert into dupe_differences values("'.$diffHash.'")');
		}
	}
}

mysql_connection_db_select(DATABASE);
//mysql_connection_db_select("`heuristdb-nyirti`");   //for debug
//FIXME  allow user to select a single record type
//$res = mysql_query('select rec_id, rec_type, rec_title, rd_val from records left join rec_details on rd_rec_id=rec_id and rd_type=160 where rec_type != 52 and rec_type != 55 and not rec_temporary order by rec_type desc');
$crosstype = false;
$personMatch = false;

if (@$_REQUEST['crosstype']){
	$crosstype = true;
}
if (@$_REQUEST['personmatch']){
    $personMatch = true;
    $res = mysql_query('select rec_id, rec_type, rec_title, rd_val from records left join rec_details on rd_rec_id=rec_id and rd_type=291 where '. (strlen($recIDs) > 0 ? "rec_id in ($recIDs) and " : "") .'rec_type = 55 and not rec_temporary order by rec_id desc');    //Given Name
    while ($row = mysql_fetch_assoc($res)) {
       $recsGivenNames[$row['rec_id']] = $row['rd_val'];
    }
    $res = mysql_query('select rec_id, rec_type, rec_title, rd_val from records left join rec_details on rd_rec_id=rec_id and rd_type=160 where '. (strlen($recIDs) > 0 ? "rec_id in ($recIDs) and " : "") .'rec_type = 55 and not rec_temporary order by rd_val asc');    //Family Name

} else{
    $res = mysql_query('select rec_id, rec_type, rec_title, rd_val from records left join rec_details on rd_rec_id=rec_id and rd_type=160 where '. (strlen($recIDs) > 0 ? "rec_id in ($recIDs) and " : "") .'rec_type != 52 and not rec_temporary order by rec_type desc');
}

$reftypes = mysql__select_assoc('rec_types', 'rt_id', 'rt_name', '1');

while ($row = mysql_fetch_assoc($res)) {
    if ($personMatch){
       if($row['rd_val']) $val = $row['rd_val'] . ($recsGivenNames[$row['rec_id']]? " ". $recsGivenNames[$row['rec_id']]: "" );
    }else {
	    if ($row['rec_title']) $val = $row['rec_title'];
	    else $val = $row['rd_val'];
    }
	$mval = metaphone(preg_replace('/^(?:a|an|the|la|il|le|die|i|les|un|der|gli|das|zur|una|ein|eine|lo|une)\\s+|^l\'\\b/i', '', $val));

	if ($crosstype || $personMatch) { //for crosstype or person matching leave off the type ID
      $key = ''.substr($mval, 0, $fuzziness);
    } else {
      $key = $row['rec_type'] . '.' . substr($mval, 0, $fuzziness);
    }

    $typekey = $reftypes[$row['rec_type']];

	if (! array_key_exists($key, $bibs)) $bibs[$key] = array(); //if the key doesn't exist then make an entry for this metaphone
	else { // it's a dupe so process it
        if (! array_key_exists($typekey, $dupes)) $dupes[$typekey] = array();
        if (!array_key_exists($key,$dupekeys))  {
            $dupekeys[$key] =  1;
            array_push($dupes[$typekey],$key);
        }
    }
	// add the record to bibs
	$bibs[$key][$row['rec_id']] = array('type' => $typekey, 'val' => $val);
}

ksort($dupes);
foreach ($dupes as $typekey => $subarr) {
    array_multisort($dupes[$typekey],SORT_ASC,SORT_STRING);
}

?><html>
<body>
<form>
Select fuzziness: <select name="fuzziness" id="fuzziness" onchange="form.submit();">
<option value=3>3</option>
<option value=4 <?= $fuzziness == 4  ? "selected" : "" ?>>4</option>
<option value=5 <?= $fuzziness == 5 ? "selected" : "" ?>>5</option>
<option value=6 <?= $fuzziness == 6 ? "selected" : "" ?>>6</option>
<option value=7 <?= $fuzziness == 7 ? "selected" : "" ?>>7</option>
<option value=8 <?= $fuzziness == 8 ? "selected" : "" ?>>8</option>
<option value=9 <?= $fuzziness == 9 ? "selected" : "" ?>>9</option>
<option value=10 <?= $fuzziness >= 10 && $fuzziness < 12 ? "selected" : "" ?>>10</option>
<option value=12 <?= $fuzziness >= 12 && $fuzziness < 15 ? "selected" : "" ?>>12</option>
<option value=15 <?= $fuzziness >= 15 && $fuzziness < 20 ? "selected" : "" ?>>15</option>
<option value=20 <?= $fuzziness >= 20 && $fuzziness < 25 ? "selected" : "" ?>>20</option>
<option value=25 <?= $fuzziness >= 25 && $fuzziness < 30 ? "selected" : "" ?>>25</option>
<option value=30 <?= $fuzziness >= 30 ? "selected" : "" ?>>30</option>
</select>
characters of metaphone must match
<br />
<br />Cross type matching will attemp to match titles of different record types. This is potentially a long search
<br />with many matching results. Increasing fuzziness will reduce the number of matches.
<br />
<br />
search string: <input type="text" name="q" id="q" value="<?= @$_REQUEST['q'] ?>" />
<br />
<input type="checkbox" name="crosstype" id="crosstype" value=1 <?= $crosstype ? "checked" : "" ?>  onclick="form.submit();"> Do Cross Type Matching
<br />
<input type="checkbox" name="personmatch" id="personmatch" value=1   onclick="form.submit();"> Do Person Matching by SurName first
<?php
if (@$_REQUEST['w']) {
?>
<input type="hidden" name="w" id="w" value="<?= $_REQUEST['w'] ?>" />
<?php
}
?>
<?php
if (@$_REQUEST['ver']) {
?>
<input type="hidden" name="ver" id="ver" value="<?= $_REQUEST['ver'] ?>" />
<?php
}
?>
<?php
if (@$_REQUEST['stype']) {
?>
<input type="hidden" name="stype" id="stype" value="<?= $_REQUEST['stype'] ?>" />
<?php
}
?>
<?php
if (@$_REQUEST['instance']) {
?>
<input type="hidden" name="instance" id="instance" value="<?= $_REQUEST['instance'] ?>" />
<?php
}

  unset($_REQUEST['personmatch']);

print '<div>' . count($dupes) . ' potential groups of duplicates</div><hr>';

foreach ($dupes as $rectype => $subarr) {
    foreach ($subarr as $index => $key) {
    	$diffHash = array_keys($bibs[$key]);
    	sort($diffHash,SORT_ASC);
    	$diffHash = join(',',$diffHash );
    	if (in_array($diffHash,$dupeDifferences)) continue;
	    print '<div style="font-weight: bold;">' . $rectype . '&nbsp;&nbsp;&nbsp;&nbsp;';
//	    print '<a target="_new" href="'.HEURIST_URL_BASE.'search/search.html?w=all&q=ids:' . join(',', array_keys($bibs[$key])) . '">search</a>&nbsp;&nbsp;&nbsp;&nbsp;';
//	    print '<a target="fix" href="fix_dupes.php?bib_ids=' . join(',', array_keys($bibs[$key])) . '">fix</a>&nbsp;&nbsp;&nbsp;&nbsp;';
	    print '<input type="checkbox" name="dupeDiffHash[] title="Check to idicate that all records in this set are unique." id="'.$key.
	    		'" value="' . $diffHash . '">&nbsp;&nbsp;';
	    print '<input type="submit" value="hide">';
	    print '</div>';
	    print '<ul>';
	    foreach ($bibs[$key] as $rec_id => $vals) {
		    $res = mysql_query('select rec_url from records where rec_id = ' . $rec_id);
		    $row = mysql_fetch_assoc($res);
		    print '<li>'.($crosstype ? $vals['type'].'&nbsp;&nbsp;' : '').
		    		'<a target="_new" href="'.HEURIST_URL_BASE.'records/viewrec/view.php?saneopen=1&bib_id='.$rec_id.'">'.$rec_id.': '.htmlspecialchars($vals['val']).'</a>';
		    if ($row['rec_url'])
			    print '&nbsp;&nbsp;&nbsp;<span style="font-size: 70%;">(<a target="_new" href="'.$row['rec_url'].'">' . $row['rec_url'] . '</a>)</span>';
		    print '</li>';
	    }
	    print '</ul>';
    }
}
?>
</form>
</body>
</html>
