<?php

    /**
    * db access to defTerms table
    * 
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
require_once (dirname(__FILE__).'/dbEntityBase.php');
require_once (dirname(__FILE__).'/dbEntitySearch.php');


class DbDefTerms extends DbEntityBase
{
    private $records_all = null;
    private $labels_to_idx = null;
    /*
    'trm_OriginatingDBID'=>'int',
    'trm_NameInOriginatingDB'=>63,
    'trm_IDInOriginatingDB'=>'int',

    'trm_AddedByImport'=>'bool2',
    'trm_IsLocalExtension'=>'bool2',

    'trm_OntID'=>'int',
    'trm_ChildCount'=>'int',
    
    'trm_Depth'=>'int',
    'trm_LocallyModified'=>'bool2',
    */

    /**
    *  search user or/and groups
    * 
    *  sysUGrps.ugr_ID
    *  sysUGrps.ugr_Type
    *  sysUGrps.ugr_Name
    *  sysUGrps.ugr_Enabled
    *  sysUGrps.ugr_Modified
    *  sysUsrGrpLinks.ugl_UserID
    *  sysUsrGrpLinks.ugl_GroupID
    *  sysUsrGrpLinks.ugl_Role
    *  (omit table name)
    * 
    *  other parameters :
    *  details - id|name|list|all or list of table fields
    *  offset
    *  limit
    *  request_id
    * 
    *  @todo overwrite
    */
    public function search(){


        if(@$this->data['withimages']==1){
             $ids = $this->data['trm_ID'];
             $lib_dir = HEURIST_FILESTORE_DIR . 'term-images/';
             $files = array();
             foreach ($ids as $id){
                $filename = $lib_dir.$id.'.png';
                if(file_exists($filename)){
                    array_push($files, $id);
                }
             }
             if(count($files)==0){
                $this->data['trm_ID'] = 999999999; 
             }else{
                $this->data['trm_ID'] = $files;    
             }
             
        }
        
        if(parent::search()===false){
              return false;   
        }
        
        $orderBy = '';
        //compose WHERE 
        $where = array();    
        
        $pred = $this->searchMgr->getPredicate('trm_ID');
        if($pred!=null) array_push($where, $pred);

        $pred = $this->searchMgr->getPredicate('trm_Label');
        if($pred!=null) array_push($where, $pred);

        $pred = $this->searchMgr->getPredicate('trm_Domain');
        if($pred!=null) array_push($where, $pred);
        
        $pred = $this->searchMgr->getPredicate('trm_Status');
        if($pred!=null) array_push($where, $pred);

        $pred = $this->searchMgr->getPredicate('trm_Modified');
        if($pred!=null) array_push($where, $pred);

        $pred = $this->searchMgr->getPredicate('trm_Code');
        if($pred!=null) array_push($where, $pred);
        
        $pred = $this->searchMgr->getPredicate('trm_ParentTermID');
        if($pred!=null) array_push($where, $pred);

       
       $needCheck = false;
       
        //compose SELECT it depends on param 'details' ------------------------
        if(@$this->data['details']=='id'){
        
            $this->data['details'] = 'trm_ID';
            
        }else if(@$this->data['details']=='name'){

            $this->data['details'] = 'trm_ID,trm_Label';
            
        }else if(@$this->data['details']=='list'){

            $this->data['details'] = 'trm_ID,trm_Label,trm_InverseTermId,trm_Description,'
            .'trm_Domain,IFNULL(trm_ParentTermID, 0) as trm_ParentTermID'
            .',trm_VocabularyGroupID,trm_Code,trm_Status';
            
        }else if(@$this->data['details']=='full'){

            $this->data['details'] = 'trm_ID,trm_Label,trm_Description,trm_InverseTermId,'
            .'IFNULL(trm_ParentTermID, 0) as trm_ParentTermID'
            .',trm_VocabularyGroupID,trm_Code,trm_Status,trm_Domain,trm_SemanticReferenceURL'
            .',trm_OriginatingDBID,trm_IDInOriginatingDB, "" as trm_Parents'; //trm_Modified
            
            
            //$orderBy = ' ORDER BY trm_Label ';
            //$this->data['details'] = implode(',', array_keys($this->fields) );
        }else {
            $needCheck = true;
        }
        
        if(!is_array($this->data['details'])){ //specific list of fields
            $this->data['details'] = explode(',', $this->data['details']);
        }
            
        if($needCheck){
            //validate names of fields
            foreach($this->data['details'] as $fieldname){
                if(!@$this->fields[$fieldname]){
                    $this->system->addError(HEURIST_INVALID_REQUEST, "Invalid field name ".$fieldname);
                    return false;
                }            
            }
        }

        //ID field is mandatory and MUST be first in the list
        $idx = array_search('trm_ID', $this->data['details']);
        if($idx>0){
            unset($this->data['details'][$idx]);
            $idx = false;
        }
        if($idx===false){
            array_unshift($this->data['details'], 'trm_ID');
        }
        $is_ids_only = (count($this->data['details'])==1);
            
        //compose query
        $query = 'SELECT SQL_CALC_FOUND_ROWS  '.implode(',', $this->data['details']).' FROM defTerms';

         if(count($where)>0){
            $query = $query.' WHERE '.implode(' AND ',$where);
         }
         $query = $query.$orderBy.$this->searchMgr->getLimit().$this->searchMgr->getOffset();
        
        $res = $this->searchMgr->execute($query, $is_ids_only, 'defTerms');
        return $res;

    }

    //
    //
    //    
    public function getTermLinks(){

        $matches = array();
        
        $mysqli = $this->system->get_mysqli();    
        
        //compose query
        $query = 'SELECT trl_ParentID, trl_TermID FROM defTermsLinks ORDER BY trl_ParentID';
        
        $res = $mysqli->query($query);
        if ($res){
            while ($row = $res->fetch_row()){
                    
                if(@$matches[$row[0]]){
                    $matches[$row[0]][] = $row[1];
                }else{
                    $matches[$row[0]] = array($row[1]);
                }
            }
            $res->close();
        }
        
        
        return $matches;
        
    }

    //
    // trm_Label may have periods. Periods are taken as indicators of hierarchy.
    //
    private function saveHierarchy(){
      
        //extract records from $_REQUEST data 
        if(!$this->prepareRecords()){
                return false;    
        }
        
        //create tree array $record['trm_ParentTermID']
        if(count($this->records)>0){
            
            if(@$this->records[0]['trm_VocabularyGroupID']>0){
                return $this->save();
            }
                        
            
            //group by parent term ID
            $records_by_prent_id = array();
            foreach($this->records as $idx => $record){
                if($record['trm_ParentTermID']>0){
                    if(!@$records_by_prent_id[$record['trm_ParentTermID']]){
                        $records_by_prent_id[$record['trm_ParentTermID']] = array();
                    }
                    $records_by_prent_id[$record['trm_ParentTermID']][] = $record;
                }
            }
            
            $terms_added = array();
            
            foreach($records_by_prent_id as $parentID => $records){
            
                //root, children are record idx
                $this->records_all = array();
                
                $this->labels_to_idx = array(); //term label to records_all index
                
                //label->array(labels)
                $tree = $this->parseHierarchy( $records );

                //keep index
                foreach($records as $record_idx => $record){
                    $this->labels_to_idx[$record['trm_Label']] = $record_idx;
                }
                $this->records_all = $records;    
                
                $ret = $this->saveTree($tree, $parentID, '');
                if($ret===false){
                    return false;
                }
                if(is_array($ret))
                    $terms_added = array_merge($terms_added, $ret);
            }
        }
        return $terms_added;
    }
    
    //
    //
    //
    private function parseHierarchy($input) {
        $result = array();

        foreach ($input AS $path) {
            $path = $path['trm_Label'];
            
            $prev = &$result;

            $s = strtok($path, '.');
            //iterate path
            while (($next = strtok('.')) !== false) {
                if (!isset($prev[$s])) {
                    $prev[$s] = array();
                }

                $prev = &$prev[$s];
                $s = $next;
            }
            if (!isset($prev[$s])) {
                $prev[$s] = array();
            }

            unset($prev);
        }
        return $result;
    }    

    //
    // tree: idx->array(idx->array(),.... )
    //
    private function saveTree($tree, $parentID, $parentLabel){

        //reset array of record for save        
        $this->records = array();
        
        $mysqli = $this->system->get_mysqli();
        
        //fill records array
        foreach($tree as $label => $children)
        {
            $record_idx = @$this->labels_to_idx[$parentLabel.$label];
            if($record_idx===null){ //one of parent terms not defined - add it
                $record_idx = count($this->records_all);
                $this->labels_to_idx[$parentLabel.$label] = $record_idx;
                $this->records_all[] = array();
            }
            
            $this->records_all[$record_idx]['trm_ParentTermID'] = $parentID;
            $this->records_all[$record_idx]['trm_Label'] = $label;
            $this->records_all[$record_idx]['trm_Domain'] = $this->records_all[0]['trm_Domain'];
            
            $record = $this->records_all[$record_idx];
            
            //check for term with the same name for this parent
            if(@$record['trm_ID']>0){
                //already exists
                continue;
            }else{
                $query = 'select trm_ID from defTerms where trm_ParentTermID='
                        .$parentID.' and trm_Label="'.$mysqli->real_escape_string($label).'"';    
                $trmID = mysql__select_value($mysqli, $query);
                if($trmID>0){
                    //already exists
                    $this->records_all[$record_idx]['trm_ID'] = $trmID;
                    continue;
                }
            }
            
            $this->records[$record_idx] = $record;
        }
        
        $terms_added = array();
        
        if(count($this->records)>0){
            $ret = $this->save();
            if($ret!==false) {
                $terms_added = $ret;
            }
        }else{
            $ret = true; //all terms already in db
        }
        
        if($ret!==false){
            //assign recID from records to records_all
            foreach($this->records as $record_idx => $record){
                //$this->primaryField
                $this->records_all[$record_idx]['trm_ID'] = $record['trm_ID'];
            }
            
            //go to next level
            foreach($tree as $label => $children)
            {
                if(count($children)>0){
                    $record_idx = @$this->labels_to_idx[$parentLabel.$label];
                    $ret = $this->saveTree($children, $this->records_all[$record_idx]['trm_ID'], $parentLabel.$label.'.');
                    if($ret===false){
                        return false;
                    }
                    if(is_array($ret))
                        $terms_added = array_merge($terms_added, $ret);
                }
            }            
            return $terms_added;
        }else{
            return false;
        }
        
    }
    
    //
    //
    //    
    protected function prepareRecords(){
    
        $ret = parent::prepareRecords();

        //add specific field values
        foreach($this->records as $idx=>$record){

            //validate duplication on the same level
            $mysqli = $this->system->get_mysqli();
            
            if(@$this->records[$idx]['trm_Label']){
            
                if(@$this->records[$idx]['trm_ParentTermID']>0){
                    
                    //@todo find all labels per vocabulary in low case - check that new one is unique 
                    
                    $sWhere = ' AND (trm_ParentTermID='.$this->records[$idx]['trm_ParentTermID'].')';    
                    $s2 = 'Term';
                    
                    $s3 = 'Duplicate label ('.$this->records[$idx]['trm_Label'].') ';
                    if(@$this->records[$idx]['trm_Code']){
                        $s3 = $s3.' or code ('.$this->records[$idx]['trm_Code'].') ';
                    }
                    
                    $s3 = $s3.' at the same branch/level in the tree';
                }else{
                    $this->records[$idx]['trm_ParentTermID'] = null;
                    $sWhere = ' AND (trm_ParentTermID IS NULL OR trm_ParentTermID=0)';    
                    $s2 = 'Vocabulary';
                    $s3 = 'The provided name already exists';
                }
                
                $res = mysql__select_value($mysqli,
                        "SELECT trm_ID FROM ".$this->config['tableName']."  WHERE (trm_Label='"
                        .$mysqli->real_escape_string( $this->records[$idx]['trm_Label'])."'" 
                        .(@$this->records[$idx]['trm_Code']
                            ?' OR trm_Code="'.$mysqli->real_escape_string( $this->records[$idx]['trm_Code'] ).'"'
                            :'')
                        .') '.$sWhere );
                        
                if($res>0 && $res!=@$this->records[$idx]['trm_ID']){
                    $this->system->addError(HEURIST_ACTION_BLOCKED, $s2.' cannot be saved. '.$s3);
                    return false;
                }
            }

            $this->records[$idx]['trm_Modified'] = date('Y-m-d H:i:s'); //reset
            if(@$this->records[$idx]['trm_Domain']!='relation') $this->records[$idx]['trm_Domain'] = 'enum';
            if(!@$this->records[$idx]['trm_Status']) $this->records[$idx]['trm_Status'] = 'open';
            if(!(@$this->records[$idx]['trm_InverseTermId']>0)) $this->records[$idx]['trm_InverseTermId'] = null;
            
            $this->records[$idx]['is_new'] = (!(@$this->records[$idx]['trm_ID']>0));
        }
        
        return $ret;
    }     
    
    //
    // returns array of saved record ids or false
    //
    public function save(){
        
        
        $ret = parent::save();

        //treat thumbnail image
        if($ret!==false){
            
            $dbID = $this->system->get_system('sys_dbRegisteredID');
            if(!($dbID>0)) $dbID = 0;
            
            $mysqli = $this->system->get_mysqli();
            
            foreach($this->records as $record){
                $trm_ID = @$record['trm_ID'];
                if($trm_ID>0 && in_array($trm_ID, $ret)){
                    
                    $query = null;
                    //set dbid or update modified locally
                    if($record['is_new']){
                        
                        $query= 'UPDATE defTerms SET trm_OriginatingDBID='.$dbID
                                .', trm_NameInOriginatingDB=trm_Label'
                                .', trm_IDInOriginatingDB='.$trm_ID
                                .' WHERE (NOT trm_OriginatingDBID>0 OR trm_OriginatingDBID IS NULL) AND trm_ID='.$trm_ID;
                                   
                    }else{
                        $query = 'UPDATE defTerms SET trm_LocallyModified=IF(trm_OriginatingDBID>0,1,0)'
                                . ' WHERE trm_ID = '.$trm_ID;
                    }
                    $res = $mysqli->query($query);
                    
                    
                    $thumb_file_name = @$record['trm_Thumb'];
                    //rename it to recID.png
                    if($thumb_file_name){
                        parent::renameEntityImage($thumb_file_name, $record['trm_ID']);
                    }
                }
            }
        }
        
        return $ret;
    } 

    //
    //
    //    
    public function batch_action(){

            $mysqli = $this->system->get_mysqli();        

            $this->need_transaction = false;
            $keep_autocommit = mysql__begin_transaction($mysqli);

            $ret = true;
            
            if(@$this->data['reference'])
            {
                //add term to vocabuary by reference
                    
                $trm_IDs = prepareIds($this->data['trm_ID']);
                
                if(count($trm_IDs)==0){
                             
                    $this->system->addError(HEURIST_INVALID_REQUEST, 'Invalid set of identificators');
                    $ret = false;
                    
                }else{
                    //old_ParentTermID
                    //new_ParentTermID
                
                    foreach($trm_IDs as $trm_ID){
                    
                        if(@$this->data['new_ParentTermID']>0)
                        {   
                            //do not add child terms as reference if parent is already added as reference
                            //'SELECT trm_ID FROM defTerms WHERE trm_ParentTermID='.$trm_ID
                            
                            $res = mysql__select_value($mysqli, 
                            'SELECT trl_TermID FROM defTermsLinks WHERE trl_ParentID='
                                .$this->data['new_ParentTermID']
                                .' AND trl_TermID='.$trm_ID);   //to avoid duplication
                            if(!($res>0)){
                                $ret = $mysqli->query(
                                    'insert into defTermsLinks (trl_ParentID,trl_TermID)'
                                        .'values ('.$this->data['new_ParentTermID'].','.$trm_ID.')');
                                if(!$ret){
                                    $this->system->addError(HEURIST_DB_ERROR, 
                                        'Cannot insert to defTermsLinks table', $mysqli->error);
                                    $ret =false;
                                    break;
                                }
                            }
                        }
                        if(@$this->data['old_ParentTermID']>0){
                            
                                //can not delete reference if this is real parent
                                if(!(@$this->data['new_ParentTermID']>0))
                                {   
                                    $parent_id = mysql__select_value($mysqli, 
                                    'SELECT trm_ParentTermID FROM defTerms where trm_ID='.$trm_ID);
                                    if($parent_id == $this->data['old_ParentTermID']){
                                        $this->system->addError(HEURIST_ERROR, 
                                        'Term can not be orphaned', $mysqli->error);
                                        $ret =false;
                                        break;
                                    }
                                }
                            
                            
                                $ret = $mysqli->query(
                                    'delete from defTermsLinks where trl_ParentID='
                                    .$this->data['old_ParentTermID'].' AND trl_TermID='.$trm_ID);

                                if(!$ret){
                                    $this->system->addError(HEURIST_DB_ERROR, 
                                        'Cannot delete from defTermsLinks table', $mysqli->error);
                                    $ret =false;
                                    break;
                                }
                        }
                    }
                }
                
            }
            else if(@$this->data['merge_id']>0 && @$this->data['retain_id']>0)
            {
                //MERGE TERMS
                
                $merge_id = $this->data['merge_id'];
                $retain_id = $this->data['retain_id'];
                
                //check usage
                $ret = $this->isTermNotInUse($merge_id, true, false); //check detailtypes, do not check in records
                if(is_array($ret)){
                    $this->system->addError(HEURIST_ACTION_BLOCKED,
                            'Cannot merge '.$merge_id.'. This term has references', $ret);
                    $ret = false; 
                }
                /*if($ret===false){ //sql error
                    $ret = false; 
                }else{
                    $ret = true;
                }*/
                    
                if($ret){
                    //1. change parent id for all children terms
                    $query = "update defTerms set trm_ParentTermID = $retain_id where trm_ParentTermID = $merge_id";
                    $res = $mysqli->query($query);
                    if ($mysqli->error) {
                        $this->system->addError(HEURIST_DB_ERROR,
                            'SQL error - cannot change parent term for '.$merge_id.' from defTerms table', $mysqli->error);
                        $ret = false; 
                    }
                }
                if($ret){
                    //2. update entries in recDetails for all detail type enum or reltype
                    $query = "update recDetails, defDetailTypes set dtl_Value=".$retain_id
                    ." where (dty_ID = dtl_DetailTypeID ) and "
                    ." (dty_Type='enum' or dty_Type='relationtype') and "
                    ." (dtl_Value=".$merge_id.")";

                    $res = $mysqli->query($query);
                    if ($mysqli->error) {
                        $this->system->addError(HEURIST_DB_ERROR,
                            'SQL error in mergeTerms updating record details', $mysqli->error);
                        $ret = false; 
                    }
                }
                if($ret){
                    //3. delete term $merge_id
                    $query = "delete from defTerms where trm_ID = $merge_id";
                    $res = $mysqli->query($query);
                    if ($mysqli->error) {
                        $this->system->addError(HEURIST_DB_ERROR,
                            "SQL error deleting term $merge_id from defTerms table", $mysqli->error);
                        $ret = false; 
                    }
                }
                if($ret){
                
                    //4. update term $retain_id
                    $values = array('trm_ID'=>$retain_id);
                    if(@$this->data['trm_Code']) $values['trm_Code'] = $this->data['trm_Code'];
                    if(@$this->data['trm_Description']) $values['trm_Description'] = $this->data['trm_Description'];
                    
                    if(count($values)>1){
                    
                        $ret = mysql__insertupdate($mysqli, 
                                                $this->config['tableName'], $this->fields,
                                                $values );

                        if(!$ret){
                            $this->system->addError(HEURIST_ACTION_BLOCKED, 
                                    'Cannot save data in table '.$this->config['entityName'], $ret);
                            $ret = false;
                        }                    
                    
                    }
                    
                }
                
            }else{
                //import terms (from csv)
                $ret = $this->saveHierarchy();
            }
        
            if($ret===false){
                $mysqli->rollback();
            }else{
                $mysqli->commit();    
            }
            
            if($keep_autocommit===true) $mysqli->autocommit(TRUE);
            
        
            return $ret;
    }
    
    //
    // returns array - list of fields (where vocabulary is in use) and number of records
    // false - mysql error
    // true - term and its children are not in use
    //
    private function isTermNotInUse($trm_ID, $infield, $indetails){

        $mysqli = $this->system->get_mysqli();        
        
        $ret = array('children'=>0, 'detailtypes'=>array(), 'reccount'=>0);

        //first level children
        $query = 'SELECT count(trl_TermID) FROM defTermsLinks WHERE trl_ParentID='
                            .$trm_ID;
        $ret['children'] = mysql__select_value($mysqli, $query);
        

        if($infield){
            //find possible entries in defDetailTypes dty_JsonTermIDTree
            $query = 'SELECT dty_ID FROM defDetailTypes WHERE '
                .'(dty_JsonTermIDTree='.$trm_ID.') '
                .'AND (dty_Type=\'enum\' or dty_Type=\'relmarker\')';
            $ret['detailtypes'] = mysql__select_list2($mysqli, $query);
            
            //TODO: need to check inverseid or it will error by foreign key constraint?
        }

        //find usage in recDetails
        if($indetails && count($ret['detailtypes'])==0){
            
            //find all children terms (except terms by reference)
            $children = getTermChildren($trm_ID, $this->system, false); //see db_structure
            $children[] = $trm_ID; 
            if(count($children)>1){
                $s = 'in ('.implode(',',$children).')';
            }else{
                $s = '= '.$trm_ID;
            }

            $query = "SELECT SQL_CALC_FOUND_ROWS DISTINCT dtl_RecID FROM recDetails, defDetailTypes "
            ."WHERE (dty_ID = dtl_DetailTypeID ) AND "
            ."(dty_Type='enum' or dty_Type='relationtype') AND "  // or dty_Type='relmarker'
            .'(dtl_Value '.$s.')';
            //$ret['reccount'] = mysql__select_value($mysqli, $query);

            $total_count_rows = 0;
            $records = array();
            $res = $mysqli->query($query);
            if ($res){
                $fres = $mysqli->query('select found_rows()');
                if ($fres)     {
                    $total_count_rows = $fres->fetch_row();
                    $total_count_rows = $total_count_rows[0];
                    $fres->close();
                    
                    if($total_count_rows>0 && ($total_count_rows<10000 || $total_count_rows*10<get_php_bytes('memory_limit'))){
                
                        $records = array();
                        while ($row = $res->fetch_row())  {
                                array_push($records, (int)$row[0]);
                        }
                    }
                }
                $res->close();
           }
           if($mysqli->error){
                $system->addError(HEURIST_DB_ERROR, 
                            'Search query error (retrieving number of records)', $mysqli->error);
                return false;
           }else{
               $ret['reccount'] = $total_count_rows;
               $ret['records'] = $records;
           }
        }

        //$ret['children']>0 || 
        if(count($ret['detailtypes'])>0 || $ret['reccount']>0){
            return $ret;    
        }else{
            return true;
        }
        
        
    }

    //
    //
    //
    protected function _validatePermission()
    {
        if(@$this->data['a'] == 'delete'){
            
            if(!@$this->recordIDs){
                $this->recordIDs = prepareIds($this->data[$this->primaryField]);
            }
            
            $children = array();
            
            foreach($this->recordIDs as $trm_ID){
                $ret = $this->isTermNotInUse($trm_ID, true, true); //check both records and defs 
                if(is_array($ret)){
                    $this->system->addError(HEURIST_ACTION_BLOCKED,
                            'Cannot delete '.$trm_ID.'. This term has references', $ret); //$ret
                    return false; 
                }else if($ret===false){
                    return false; 
                }
                
                $children2 = getTermChildren($trm_ID, $this->system, false); //see db_structure
                $children = array_merge($children, $children2);
            }
            $this->recordIDs = array_merge($this->recordIDs, $children);
            
            //$this->system->addError(HEURIST_ACTION_BLOCKED, 'Temp debug block');
            //return false;
        }
        return true;
    }
    
}
?>
