<?php

/**
* Lookup third party web service to return data to client side recordLookups
* It works as a proxy to avoid cross-origin issues
* 
* Currently supporting services:
* GeoName
* TLCMap
* BnF Library
* 
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

    require_once (dirname(__FILE__).'/../System.php');
    require_once (dirname(__FILE__).'/../dbaccess/utils_db.php');

detectLargeInputs('REQUEST record_lookup', $_REQUEST);
detectLargeInputs('COOKIE record_lookup', $_COOKIE);
    
    $response = array();

    $system = new System();

    $params = $_REQUEST;

    if(!(@$params['service'])){
        $system->error_exit_api('Service parameter is not defined or has wrong value'); //exit from script
    }
    
    if( !$system->init(@$params['db']) ){  //@todo - we don't need db connection here - it is enough check the session
        //get error and response
        $system->error_exit_api(); //exit from script
    }else if ( $system->get_user_id()<1 ) {
        $system->error_exit_api(null, HEURIST_REQUEST_DENIED); 
        //$response = $system->addError(HEURIST_REQUEST_DENIED);
    }

    $system->dbclose();
    
	// Perform external lookup / API request
    $url = $params['service'];
    $remote_data = loadRemoteURLContent($url, true);    
    if($remote_data===false){
        $system->error_exit_api('Cannot connect/load data from the service: '.$url, HEURIST_ERROR);    
    }

    if(@$params['serviceType'] == 'geonames' || @$params['serviceType'] == 'tlcmap'){ // GeoName and TLCMap lookups

        json_decode($remote_data);
        if(json_last_error() == JSON_ERROR_NONE){
        }else{
    /*        
            $array = array_map("str_getcsv", explode("\n", $csv));
            $json = json_encode($array);
    */
            $hasGeo = false;
            $remote_data = str_getcsv($remote_data, "\n"); //parse the rows
            if(is_array($remote_data) && count($remote_data)>1){
                
                $header = str_getcsv(array_shift($remote_data));
                $id = 1;
                foreach($remote_data as &$line){
                    $line = str_getcsv($line);  
                    foreach($header as $idx=>$key){
                         $line[$key] = $line[$idx];
                         unset($line[$idx]);
                    }
                    if(@$line['latitude'] && @$line['longitude']){
                        $line = array('type'=>'Feature','id'=>$id, 'properties'=>$line,
                            'geometry'=>array('type'=>'Point','coordinates'=>array($line['longitude'], $line['latitude'])));
                        $hasGeo = true;
                    }
                } 
                
                if(!$hasGeo){
                    $system->error_exit_api('Service did not return data in an appropriate format');
                }
            }else if(is_array($remote_data) && count($remote_data)==1){
                    $system->error_exit_api('No records match the search criteria', HEURIST_NOT_FOUND);
            }else{
                    $system->error_exit_api('Service did not return any data');
            }
    
            $remote_data = json_encode($remote_data);
        }
    }else if(@$params['serviceType'] == 'bnflibrary_bib'){ // BnF Library Search

        $results = array();
        
        // Create xml object
        $xml_obj = simplexml_load_string($remote_data, null, LIBXML_PARSEHUGE);
        // xml namespace urls: http://www.loc.gov/zing/srw/ (srw), info:lc/xmlns/marcxchange-v2 (mxc)

        // Retrieve records from results
        $records = $xml_obj->children('http://www.loc.gov/zing/srw/', false)->records->record;

        // Move each result's details into seperate array
        foreach ($records as $key => $details) {

            $formatted_array = array();

            $author_idx = 0;

            foreach ($details->recordData->children('info:lc/xmlns/marcxchange-v2', false)->record->controlfield as $key => $cf_ele) { // controlfield elements
                $cf_tag = @$cf_ele->attributes()['tag'];

                if($cf_tag == '003') { // Record URL
                    $formatted_array['biburl'] = (string)$cf_ele[0];
                    break;
                }
            }

            foreach ($details->recordData->children('info:lc/xmlns/marcxchange-v2', false)->record->datafield as $key => $df_ele) { // datafield elements
                $df_tag = @$df_ele->attributes()['tag'];

                if(!$df_tag) {
                    continue;
                }

                if($df_tag == '245') { // Title / Type

                    foreach($df_ele->subfield as $sub_key => $sf_ele) {

                        $sf_code = @$sf_ele->attributes()['code'];

                        if($sf_code == 'a') {
                            $formatted_array['title'] = (string)$sf_ele[0];
                        }else if($sf_code == 'd'){
                            $formatted_array['type'] = (string)$sf_ele[0];
                        }else if($sf_code == 'f'){

                            if(array_key_exists('title', $formatted_array)){
                                $formatted_array['title'] .= ' ' . (string)$sf_ele[0];
                            }else{
                                $formatted_array['title'] = (string)$sf_ele[0];
                            }
                        }
                    }
                }else if($df_tag == '260') { // Publisher Location / Publisher Name / Year of Publication
                    
                    $value = '';
                    foreach ($df_ele->subfield as $sub_key => $sf_ele) {
                        $sf_code = @$sf_ele->attributes()['code'];

                        if($sf_code == 'a') {

                            $str_val = (string)$sf_ele[0];
                            if($str_val[0] == '['){
                                $formatted_array['publisher']['location'] = substr($str_val, 1, -1);
                            }else{
                                $formatted_array['publisher']['location'] = $str_val;
                            }
                        }else if($sf_code == 'c') {

                            $str_val = (string)$sf_ele[0];
                            if($str_val[0] == '['){
                                $str_val = substr($str_val, 1, -1);
                            }
                            $formatted_array['publisher']['name'] = $str_val;
                        }else if($sf_code == 'd') {
                            $formatted_array['publisher']['date'] = (string)$sf_ele[0]; //preg_replace('/[^0-9]/', '', (string)$sf_ele[0]);
                        }
                    }
                }else if($df_tag == '100' || $df_tag == '101' || $df_tag == '700') { // Creator

                    foreach ($df_ele->subfield as $sub_key => $sf_ele) {
                        $sf_code = @$sf_ele->attributes()['code'];

                        if($sf_code == '3') {
                            $formatted_array['author'][$author_idx]['id'] = (string)$sf_ele[0];
                        }else if($sf_code == 'a') {
                            $formatted_array['author'][$author_idx]['surname'] = (string)$sf_ele[0];
                        }else if($sf_code == 'm') {
                            $formatted_array['author'][$author_idx]['firstname'] = (string)$sf_ele[0];
                        }else if($sf_code == 'd') {
                            $formatted_array['author'][$author_idx]['active'] = (string)$sf_ele[0];
                        }
                    }

                    $author_idx ++;
                }else if($df_tag == '020') { // ISBN

                    foreach ($df_ele->subfield as $sub_key => $sf_ele) {
                        $sf_code = @$sf_ele->attributes()['code'];

                        if($sf_code == 'a') {
                            $formatted_array['isbn'] = (string)$sf_ele[0];
                            break;
                        }
                    }
                }else if($df_tag == '280') { // Description

                    $value = '';
                    foreach ($df_ele->subfield as $sub_key => $sf_ele) {
                        $sf_code = @$sf_ele->attributes()['code'];

                        if($df_tag == '280'){

                            if($sf_code == 'a' || $sf_code == 'c' || $sf_code == 'd'){
                                $value = ($value == '') ? (string)$sf_ele[0] : ' ' . (string)$sf_ele[0];
                            }
                        }
                    }

                    if($value != '') {

                        if($df_tag == '280') {
                            $formatted_array['description'] = $value;
                        }
                    }
                }else if($df_tag == '919') { // Language, e.g. fre or FR

                    foreach ($df_ele->subfield as $sub_key => $sf_ele) {
                        $sf_code = @$sf_ele->attributes()['code'];

                        if($sf_code == 'p') {
                            $formatted_array['language'] = (string)$sf_ele[0];
                            break;
                        }
                    }
                }else if($df_tag == '327') { // Extended Description
                    
                    foreach ($df_ele->subfield as $sub_key => $sf_ele) {
                        $sf_code = @$sf_ele->attributes()['code'];

                        if($sf_code == "a") {
                            $formatted_array['ext_description'] = (string)$sf_ele[0];
                            break;
                        }
                    }
                }
            }

            $results['result'][] = $formatted_array;
        }

        // Add other details, can be used for more calls to retrieve all results (currently retrieves 500 records at max)
        $results['numberOfRecords'] = intval($xml_obj->children('http://www.loc.gov/zing/srw/', false)->numberOfRecords);
        $results['nextStart'] = intval($xml_obj->children('http://www.loc.gov/zing/srw/', false)->nextRecordPosition);

        // Encode to json for response to JavaScript
        $remote_data = json_encode($results);
    }else if(@$params['serviceType'] == 'bnflibrary_aut'){

        $results = array();
        
        // Create xml object
        $xml_obj = simplexml_load_string($remote_data, null, LIBXML_PARSEHUGE);
        // xml namespace urls: http://www.loc.gov/zing/srw/ (srw), info:lc/xmlns/marcxchange-v2 (mxc)

        // Retrieve records from results
        $records = $xml_obj->children('http://www.loc.gov/zing/srw/', false)->records->record;

        // Move each result's details into seperate array
        foreach ($records as $key => $details) {

            $formatted_array = array();

            foreach ($details->recordData->children('info:lc/xmlns/marcxchange-v2', false)->record->controlfield as $key => $cf_ele) { // controlfield elements
                $cf_tag = @$cf_ele->attributes()['tag'];

                if($cf_tag == '003') { // Record URL
                    $formatted_array['auturl'] = (string)$cf_ele[0];
                    break;
                }
            }

            foreach ($details->recordData->children('info:lc/xmlns/marcxchange-v2', false)->record->datafield as $key => $df_ele) { // datafield elements
                $df_tag = @$df_ele->attributes()['tag'];

                if(!$df_tag) {
                    continue;
                }

                if($df_tag == '100' || $df_tag == '110' || $df_tag == '167' || $df_tag == '170') { // Name

                    foreach($df_ele->subfield as $sub_key => $sf_ele) {

                        $sf_code = @$sf_ele->attributes()['code'];

                        if($df_tag == '170' && $sf_code == 'a') {
                            $formatted_array['name'] = (string)$sf_ele[0];
                            break;
                        }else if($df_tag == '100'){

                            if($sf_code == 'a'){ // Name
                                $formatted_array['name'] = (string)$sf_ele[0];
                            }else if($sf_code == 'm'){ // Name

                                if( array_key_exists('name', $formatted_array)){
                                    $formatted_array['name'] .= ', ' . (string)$sf_ele[0];
                                }else{
                                    $formatted_array['name'] = (string)$sf_ele[0];
                                }
                            }else if($sf_code == 'd'){ // Years active

                                if( array_key_exists('name', $formatted_array)){
                                    $formatted_array['name'] .= ' (' . (string)$sf_ele[0] . ')';
                                }else{
                                    $formatted_array['name'] = 'No Name Provided';
                                }
                            }
                        }else if($df_tag == '110'){

                            if($sf_code == 'a'){ // Name
                                $formatted_array['name'] = (string)$sf_ele[0];
                            }else if($sf_code == 'c'){ // Location

                                if( array_key_exists('name', $formatted_array)){
                                    $formatted_array['name'] .= ' (' . (string)$sf_ele[0] . ')';
                                }else{
                                    $formatted_array['name'] = 'No Name Provided';
                                }
                            }
                        }else if($df_tag == '167'){

                            if($sf_code == 'a'){ // Location
                                $formatted_array['name'] = (string)$sf_ele[0];
                            }else if($sf_code == 'm'){ // Dept

                                if( array_key_exists('name', $formatted_array)){
                                    $formatted_array['name'] .= ' (' . (string)$sf_ele[0] . ')';
                                }else{
                                    $formatted_array['name'] = 'No Name Provided';
                                    break;
                                }
                            }else if($sf_code == 'x'){ // Name

                                if( array_key_exists('name', $formatted_array)){
                                    $formatted_array['name'] .= ' - ' . (string)$sf_ele[0];
                                }else{
                                    $formatted_array['name'] = (string)$sf_ele[0];
                                }
                            }
                        } 
                    }

                    break;
                }
            }

            $results['result'][] = $formatted_array;
        }

        // Add other details, can be used for more calls to retrieve all results (currently retrieves 500 records at max)
        $results['numberOfRecords'] = intval($xml_obj->children('http://www.loc.gov/zing/srw/', false)->numberOfRecords);
        $results['nextStart'] = intval($xml_obj->children('http://www.loc.gov/zing/srw/', false)->nextRecordPosition);

        // Encode to json for response to JavaScript
        $remote_data = json_encode($results);
    }else if(@$params['serviceType'] == 'nomisma_rdf'){

        //error_log(print_r($remote_data, TRUE)); //DEBUGGING
		//$remote_data = json_encode($remote_data); // getRdf currently returns an error, so this isn't used
    }

	// Return response
    header('Content-Type: application/json');
    //$json = json_encode($json);
    //header('Content-Type: application/vnd.geo+json');
    //header('Content-Disposition: attachment; filename=output.json');
    header('Content-Length: ' . strlen($remote_data));
    exit($remote_data);
?>