/**
* manageDefDetailTypeGroups.js - main widget mo manage defDetailTypeGroups
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


$.widget( "heurist.manageDefDetailTypeGroups", $.heurist.manageEntity, {
    
    _entityName:'defDetailTypeGroups',
    
    _init: function() {
        
        this.options.default_palette_class = 'ui-heurist-design';
       
        this.options.innerTitle = false;

        if(!this.options.layout_mode) this.options.layout_mode = 'short';
        this.options.use_cache = true;
        
        if(this.options.select_mode!='manager'){
            this.options.edit_mode = 'none';
            this.options.width = 300;
        }else if(this.options.edit_mode == 'inline') {
            this.options.width = 890;
        }
        
        this._super();
        
        if(this.options.select_mode!='manager'){
            //hide form 
            this.editForm.parent().hide();
            this.recordList.parent().css('width','100%');
        }

        if(this.options.isFrontUI){
            this.recordList.css('top','80px');  
        }else{
            this.recordList.css('top',0);  
        }        
        
        var that = this;

        //refresh list        
        $(window.hWin.document).on(window.hWin.HAPI4.Event.ON_STRUCTURE_CHANGE, 
            function(e, data) { 
                if(!data || 
                   (data.source != that.uuid && data.type == 'dtg'))
                {
                    that._loadData();
                }
            });
        
    },
    
    _destroy: function() {
        
       $(window.hWin.document).off(window.hWin.HAPI4.Event.ON_STRUCTURE_CHANGE);
        
       this._super(); 
    },
    
    //  
    // invoked from _init after load entity config    
    //
    _initControls: function() {

        if(!this._super()){
            return false;
        }
            
        var that = this;
        
        this.recordList.resultList({
                show_toolbar:false,
                sortable: true,
                onSortStop: function(){
                    that._onActionListener(null, 'save-order');
                    //that._toolbar.find('#btnApplyOrder').show();
                },
                droppable: function(){
                    
                    that.recordList.find('.recordDiv')  //.recordDiv, ,.recordDiv>.item
                        .droppable({
                            //accept: '.rt_draggable',
                            scope: 'dtg_change',
                            hoverClass: 'ui-drag-drop',
                            drop: function( event, ui ){

                                var trg = $(event.target).hasClass('recordDiv')
                                            ?$(event.target)
                                            :$(event.target).parents('.recordDiv');
                                            
                                var dty_ID = $(ui.draggable).parent().attr('recid');
                                var dtg_ID = trg.attr('recid');
                    
                                if(dty_ID>0 && dtg_ID>0 && that.options.reference_dt_manger){
                                        
                                        var params = {dty_ID:dty_ID, dty_DetailTypeGroupID:dtg_ID };
                                        
                                        var trash_id = $Db.getTrashGroupId('dtg');
                                        //if source group is trash - change "show in list" to true
                                        if($Db.dty(dty_ID,'dty_DetailTypeGroupID') == trash_id){
                                            //from target
                                            params['dty_ShowInLists'] = 1;
                                        }else if(dtg_ID == trash_id){
                                            params['dty_ShowInLists'] = 0;
                                        }
                                    
                                        that.options.reference_dt_manger
                                            .manageDefDetailTypes('changeDetailtypeGroup',params);
                                            
                                            
                                            
                                }
                        }});
                }
        });
        
        
        if(this.options.isFrontUI){
            //specify add new/save order buttons above record list
            var btn_array = [
                {showText:true, icons:{primary:'ui-icon-plus'},text:window.hWin.HR('Add'),
                      css:{'margin':'5px','float':'left',padding:'3px'}, id:'btnAddButton',
                      click: function() { that._onActionListener(null, 'add'); }},

                {text:window.hWin.HR('Save'),
                      css:{'margin-right':'0.5em','float':'left',display:'none'}, id:'btnApplyOrder',
                      click: function() { that._onActionListener(null, 'save-order'); }}];

            this._toolbar = this.searchForm;
            this.searchForm.css({'padding-top': '8px'}).empty();
            $('<h4>Base Fields Groups</h4>').css({'margin':5}).appendTo(this.searchForm);
            this._defineActionButton2(btn_array[0], this.searchForm);
            this._defineActionButton2(btn_array[1], this.searchForm);
            
        }

        that._loadData();

        if(this.options.select_mode == 'manager'){

            this._on(this.recordList.find('.div-result-list-content'), {'scroll': function(event){

                var $ele = $(event.target);

                if($ele.scrollLeft() !== 0){
                    $ele.scrollLeft(0);
                }
            }});
        }
        
        return true;
    },    
    
    //
    //
    //
    _loadData: function(){
        
        var that = this;

        window.hWin.HAPI4.EntityMgr.getEntityData(this._entityName, false,
            function(response){
                that.updateRecordList(null, {recordset:response});
                that.selectRecordInRecordset();
            });
        
    },
    
    //----------------------
    //
    // customized item renderer for search result list
    //
    _recordListItemRenderer: function(recordset, record){
        
        var recID   = recordset.fld(record, 'dtg_ID');
        var recName = recordset.fld(record, 'dtg_Name');
        
        var html = '<div class="recordDiv white-borderless" id="rd'+recID+'" recid="'+recID+'">'; // style="height:1.3em"
        if(this.options.select_mode=='select_multi'){
            html = html + '<div class="recordSelector"><input type="checkbox" /></div>';//<div class="recordTitle">';
        }else{
            //html = html + '<div>';
        }
        
        if(recName=='Trash'){
            html = html + '<div style="display:table-cell;vertical-align: middle;"><span class="ui-icon ui-icon-trash"></span></div>';
        }
        
        html = html + 
            '<div class="item truncate" style="font-weight:bold;display:table-cell;width:150;max-width:150;padding:6px;">'
            +window.hWin.HEURIST4.util.htmlEscape(recName)+'</div>';

        if(recName!='Trash'){        
            if(this.options.edit_mode=='popup'){
                html = html
                + this._defineActionButton({key:'edit',label:'Edit', title:'', icon:'ui-icon-pencil', class:'rec_actions_button'},
                    null,'icon_text','padding-top:9px');
            }

            var cnt = 0;//recordset.fld(record, 'dtg_FieldCount');

            html = html 
            +((cnt>0)
                ?'<div style="display:table-cell;padding:0 4px">'+cnt+'</div>'
                :this._defineActionButton({key:'delete',label:'Remove', title:'', icon:'ui-icon-delete', class:'rec_actions_button'}, 
                    null,'icon_text'));
        }
        
        html = html + '<div class="selection_pointer" style="display:table-cell">'
                    +'<span class="ui-icon ui-icon-carat-r"></span></div>';
        

        return html+'</div>';
        
    },

    //
    // update list after save (refresh)
    //
    _afterSaveEventHandler: function( recID, fieldvalues ){
        
        if(this.options.edit_mode=='editonly'){
            
                this._selection = new hRecordSet();
                this._selection.addRecord(recID, fieldvalues);
                this._currentEditID = null;
                this._selectAndClose();
        }else{
                this._super( recID, fieldvalues );
                
                if(this.it_was_insert){
                    this._onActionListener(null, 'save-order');
                    this.selectRecordInRecordset(); //select first
                }
                    
        }
    
        this._triggerRefresh();    
        
    },

    
    //
    //
    //
    _afterDeleteEvenHandler: function( recID ){
        this._super( recID );
        this._triggerRefresh();    
        //select first
        this.selectRecordInRecordset();
    },
    
    //
    // can remove group with assigned fields
    //     
    _deleteAndClose: function(unconditionally){    
        
        if(false && this._getField('dtg_FieldCount')>0){
            window.hWin.HEURIST4.msg.showMsgFlash('Can\'t remove non empty group');  
            return;                
        }

        if(unconditionally===true){
            this._super(); 

        }else{
            var that = this;
            window.hWin.HEURIST4.msg.showMsgDlg(
                'Are you sure you wish to delete this base field group?', function(){ that._deleteAndClose(true) }, 
                {title:'Warning',yes:'Proceed',no:'Cancel'},{default_palette_class:this.options.default_palette_class});        
        }
    },
    
    //
    // extend for save order
    //
    _onActionListener: function(event, action){

        var isresolved = this._super(event, action);

        if(!isresolved && action=='save-order'){

            var recordset = this.getRecordSet();
            var that = this;
            window.hWin.HEURIST4.dbs.applyOrder(recordset, 'dtg', function(res){
                that._toolbar.find('#btnApplyOrder').hide();
                that._triggerRefresh('dtg');
            });
        }

    },
    
    //
    // extend dialog button bar
    //
    /*    
    _initEditForm_step3: function(recID){
        
        if(this._toolbar){
            this._toolbar.find('.ui-dialog-buttonset').css({'width':'100%','text-align':'right'});
            this._toolbar.find('#btnRecDelete').css('display', 
                    (recID>0 && this._getField('dtg_FieldCount')==0) ?'block':'none');
        }
        
        this._super(recID);
    },
    
    onEditFormChange: function( changed_element ){
        this._super(changed_element);
            
        if(this._toolbar){
            var isChanged = this._editing.isModified();
            this._toolbar.find('#btnRecDelete').css('display', 
                    (isChanged || this._getField('dtg_FieldCount')>0)?'none':'block');
        }
            
    },  
    */
/*      
    _getEditDialogButtons: function(){
                                    
            var that = this;        
            
            var btns = [ 
                {showText:true, icons:{primary:'ui-icon-plus'},text:window.hWin.HR('Add Group'),
                      css:{'margin-right':'0.5em','float':'left',display:'none'}, id:'btnAddButton',
                      click: function() { that._onActionListener(null, 'add'); }},

                {text:window.hWin.HR('Save Order'),
                      css:{'margin-right':'0.5em','float':'left',display:'none'}, id:'btnApplyOrder',
                      click: function() { that._onActionListener(null, 'save-order'); }},
                      
                      
                {text:window.hWin.HR('Close'), 
                      css:{'margin-left':'3em','float':'right'},
                      click: function() { 
                          that.closeDialog(); 
                      }},
                {text:window.hWin.HR('Drop Changes'), id:'btnRecCancel', 
                      css:{'margin-left':'0.5em','float':'right'},
                      click: function() { that._initEditForm_step3(that._currentEditID) }},  //reload edit form
                {text:window.hWin.HR('Save'), id:'btnRecSave',
                      accesskey:"S",
                      css:{'font-weight':'bold','float':'right'},
                      click: function() { that._saveEditAndClose( null, 'none' ); }},
                      
                {text:window.hWin.HR('Delete'), id:'btnRecDelete',
                      css:{'float':'right',display:'none'},
                      click: function() { that._onActionListener(null, 'delete'); }},
                      
                      ];
        
            return btns;
    },
*/    
});
