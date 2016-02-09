/**
* Search header for Entity Manager - BASE widget
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2016 University of Sydney
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


$.widget( "heurist.searchEntity", {

    // default options
    options: {
        select_mode: 'manager', //'select_single','select_multi','manager'
        
        //initial filter by title and subset of groups to search
        filter_title: null,
        filter_group_selected:null,
        filter_groups: null,
        
        // callbacks - events
        onstart:null,
        onresult:null,
        
        entity:{}
    },
    
    _need_load_content:true,

    // the widget's constructor
    _create: function() {
        // prevent double click to select text
        this.element.disableSelection();
    }, //end _create

    // Any time the widget is called with no arguments or with only an option hash, 
    // the widget is initialized; this includes when the widget is created.
    _init: function() {

            var that = this;
            
            if(this._need_load_content && this.options.entity.searchFormContent){        
                this.element.load(top.HAPI4.basePathV4+'hclient/widgets/entity/'+this.options.entity.searchFormContent+'?t'+top.HEURIST4.util.random(), 
                function(response, status, xhr){
                    that._need_load_content = false;
                    if ( status == "error" ) {
                        top.HEURIST4.msg.showMsgErr(response);
                    }else{
                        that._initControls();
                    }
                });
                return;
            }else{
                that._initControls();
            }
            
    },
    
    //
    //
    //
    _initControls: function() {
            
            //init buttons
            this.btn_search_start = this.element.find('#btn_search_start')
                //.css({'width':'6em'})
                .button({label: top.HR("Start search"), text:false, icons: {
                    secondary: "ui-icon-search"
                }});
                        
            this.input_search = this.element.find('#input_search');
            if(!top.HEURIST4.util.isempty(this.options.filter_title)) {
                this.input_search.val(this.options.filter_title);    
            }
            
            this._on( this.input_search, {
                keypress:
                function(e){
                    var code = (e.keyCode ? e.keyCode : e.which);
                        if (code == 13) {
                            top.HEURIST4.util.stopEvent(e);
                            e.preventDefault();
                            this.startSearch();
                        }
                }});
            this._on( this.btn_search_start, {
                click: this.startSearch });
                
            this._on( this.element.find('.ent_search_cb input'), {  //input[type=radio]
                change: this.startSearch });
                
            // summary button - to show various counts for entity 
            // number of group members, records by rectypes, tags usage
            this.btn_summary = this.element.find('#btn_summary')
                .button({label: top.HR("Show/refresh counts"), text:false, icons: {
                    secondary: "ui-icon-retweet"
                }});
            if(this.btn_summary.length>0){
                this._on( this.btn_summary, { click: this.startSearch });
            }
                
            // help buttons
            top.HEURIST4.ui.initHintButton(this.element.find('#btn_help_hints'));
            top.HEURIST4.ui.initHelper(this.element.find('#btn_help_content'),'Help',
                top.HAPI4.basePathV4+'context_help/'+this.options.entity.helpContent+' #content');
            
            var right_padding = top.HEURIST4.util.getScrollBarWidth()+4;
            this.element.find('#div-table-right-padding').css('min-width',right_padding);
        
        
            //EXTEND this.startSearch();
    },  
    
    //
    // public methods
    //
    startSearch: function(){
        //EXTEND        
    },
    

});
