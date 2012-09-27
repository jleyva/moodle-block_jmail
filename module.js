/*
 * Module Javascript
 * Implements a YUI3 Module in the Moodle namespace
 * In this file is present all the javascript code needed for building the ui and subplugins system
 * 
 */

M.block_jmail = {};

M.block_jmail.Y = null;
M.block_jmail.app = {};
M.block_jmail.labels = [];
M.block_jmail.newemailOpen = false;
M.block_jmail.messageCache = {};
// keeps user filter current state
M.block_jmail.filterUser = {firstname: '', lastname: '', group: 0, role: 0}
// keeps message filter current state
M.block_jmail.filterMessage = {label: 'inbox', start: 0, sort: 'date', direction: 'DESC', searchtext: ''};
M.block_jmail.currentLabel = 0;
M.block_jmail.currentMessage = {};
M.block_jmail.searchTimeout = null;
M.block_jmail.searchText = '';
M.block_jmail.magicNumSubject = 0;
M.block_jmail.magicNumTop = 125;
M.block_jmail.magicNumDataTableWidth = 10;
M.block_jmail.currentComposeCourse = {id: 0, shortname: ''};

M.block_jmail.init = function(Y, cfg) {
    M.block_jmail.logdiv = Y.one("#loginfo");
    if (location.href.indexOf("#debugon") > 0) {
        M.block_jmail.logdiv.setStyle('display', 'block');
    }
    
    M.block_jmail.logInfo('Starting the app');
    
    M.block_jmail.Y = Y;
    M.block_jmail.cfg = cfg;
    
    M.block_jmail.magicNumSubject = (cfg.globalinbox)? 550 : 400;
    
    // Panel for composing messages, this is the first we have to do for avoid problems with the tinymce editor
    // We must render first the panel
    
    // Moodle 2.3 requires a bigger height panel
    var panelHeight = (M.block_jmail.cfg.version >= 2012062500)? "700px": "600px";
    
    Y.one('#newemailpanel').setStyle('display', 'block');
    var panel = new YAHOO.widget.Panel("newemailpanel", {
        draggable: true,
        modal: false,
        width: "800px",
        height: panelHeight,
        autofillheight: "body",
        visible: false,
        zindex:4,
        top: "50px",
        context: ['jmailui', 'tl', 'tl', null, [200, 0]]
    });
    
    panel.subscribe("hide", function (event) {
        M.block_jmail.newemailOpen = false;        
    });
    panel.render(document.body);
    M.block_jmail.app.composePanel = panel;
    
    //var fptpl = Y.one("#filepickertpl");    
    //M.block_jmail.filemanagertpl =  fptpl.get('innerHTML');
    //fptpl.remove();    
    
    // Load all the contacts users
    M.block_jmail.logInfo('Loading contacts');
    if (cfg.cansend) {
        M.block_jmail.loadContacts();
        Y.one("#selectall").on('click', function(e){
                Y.all("#contact_list_users input[type=checkbox]").set('checked', e.target.get('checked'));                
            });
        Y.all('#selbuttons input[type=button]').on('click', function(e) {
                Y.all("#contact_list_users input[type=checkbox]").each(function(node){
                        if (node.get('checked') == true) {
                            M.block_jmail.composeMessage('new');
                            var type = e.target.get('className').replace('sel','');
                            var userid = node.ancestor('div').ancestor('div').get('id').replace('user', '');
                            var fullname = node.ancestor('div').ancestor('div').one('.fullname').get('text');                            
                            M.block_jmail.addContact(userid, fullname, type);
                        }
                    });
            });
    }
    
    // Old Yui2 shortcuts 
    var Dom = YAHOO.util.Dom, Event = YAHOO.util.Event;
    
    // Sets the page height
    Y.one('#jmailui').setStyle('height', Y.one('document').get('winHeight')+'px');

    if (cfg.cansend) {
        var layoutUnits = [            
            { position: 'right', width: 300, maxWidth: 400, minWidth: 200, resize: true, scroll: true, collapse: true, body: 'jmailright', animate: true, gutter: '2px'},            
            { position: 'left', width: 180, resize: false, body: 'jmailleft', scroll: true, animate: true, gutter: '2px' },
            { position: 'center', body: 'jmailcenter' }
        ];
    } else {
        var layoutUnits = [                        
            { position: 'left', width: 200, resize: false, body: 'jmailleft', scroll: true, animate: true, gutter: '2px' },
            { position: 'center', body: 'jmailcenter' }
        ];
    }

    // Load the main layouts
    var layout = new YAHOO.widget.Layout('jmailui', {
        units: layoutUnits
    });
    
    if (cfg.cansend) {
        layout.on('resize', function() {            
            if (M.block_jmail.app.dataTable) {                                
                M.block_jmail.app.dataTable.set('width', this.getSizes().center.w - M.block_jmail.magicNumDataTableWidth + 'px');                
                M.block_jmail.app.dataTable.setColumnWidth(M.block_jmail.app.dataTable.getColumn('subject'), (this.getSizes().center.w - M.block_jmail.magicNumSubject));
                M.block_jmail.app.dataTable._syncColWidths();
                
            }
        }, layout, true);    
    }
    
    var layout2 = null;
    var layout3 = null;
    
    M.block_jmail.logInfo('Rendering layouts');
    layout.on('render', function() {
        var el = layout.getUnitByPosition('center').get('wrap');
        layout2 = new YAHOO.widget.Layout(el, {
            parent: layout,
            units: [
                { position: 'top', body: 'mailarea', height: 300, gutter: '2px', resize: true },                
                { position: 'center', body: 'mailcontents', gutter: '2px', scroll: true}
            ]
        });
        
        layout2.on('resize', function() {
            if (M.block_jmail.app.dataTable) {                                
                M.block_jmail.app.dataTable.set('height', this.getSizes().top.h - M.block_jmail.magicNumTop + 'px');                                
                M.block_jmail.app.dataTable._syncColWidths();                
            }
        }, layout2, true);  
        layout2.render();
        
        if (cfg.cansend) {
            el = layout.getUnitByPosition('right').get('wrap');
            layout3 = new YAHOO.widget.Layout(el, {
                parent: layout,
                units: [
                    { position: 'top', body: 'contact_list_filter', height: 200, gutter: '2px', resize: true },                
                    { position: 'center', body: 'contact_list', gutter: '2px', scroll: true}
                ]
            });
            layout3.render();
        }
    });
    
    layout.render();
    M.block_jmail.logInfo('Layouts rendered');
    //layout.getUnitByPosition('right').collapse();
    
    M.block_jmail.app.layout = layout;
    M.block_jmail.app.layout2 = layout2;
    M.block_jmail.app.layout3 = layout3;
    
    
    // TODO IE 7
    if (Y.UA.ie != 7) {
        Y.on('windowresize', function(e) {
                M.block_jmail.app.layout.set('height', M.block_jmail.Y.one('#jmailui').getStyle('width')); 
                M.block_jmail.app.layout.set('width', M.block_jmail.Y.one('#jmailui').getStyle('height')); 
                M.block_jmail.app.layout.resize();
            });
    }    

    // New and check mail buttons
    
    if (cfg.cansend) {
        var icon = document.createElement('span'); 
        icon.className = 'icon';
        var newmailButton = new YAHOO.widget.Button("newmail");
        newmailButton.appendChild(icon);
        Y.one("#newmail").on('click', function(e){
            M.block_jmail.composeMessage('new');
        });
    }
    
    var icon = document.createElement('span'); 
    icon.className = 'icon';
    var checkmailButton = new YAHOO.widget.Button("checkmail");
    checkmailButton.appendChild(icon);
    Y.one('#checkmail').on('click', function(e){
            M.block_jmail.checkMail('inbox');
        });

    M.block_jmail.logInfo('Creating buttons toolbar');
    // INBOX Toolbar    
    var icon = document.createElement('span'); 
    icon.className = 'icon';    
    var deleteButton = new YAHOO.widget.Button("deleteb");
    deleteButton.appendChild(icon);
    deleteButton.on("click", M.block_jmail.deleteMessage);
    
    var icon = document.createElement('span'); 
    icon.className = 'icon'; 
    var editButton = new YAHOO.widget.Button("editb");
    editButton.appendChild(icon);
    editButton.on("click", function() { M.block_jmail.composeMessage('edit', M.block_jmail.currentMessage); });
    
    if (cfg.approvemode && cfg.canapprovemessages) {
        var icon = document.createElement('span'); 
        icon.className = 'icon'; 
        var approveButton = new YAHOO.widget.Button("approveb");
        approveButton.appendChild(icon);
        approveButton.on("click", function() { M.block_jmail.approveMessage(); });
    }
    
    if (cfg.cansend) {
        var icon = document.createElement('span'); 
        icon.className = 'icon';
        var replyButton = new YAHOO.widget.Button("replyb");
        replyButton.appendChild(icon);
        replyButton.on("click", M.block_jmail.replyMessage);
        
        var icon = document.createElement('span'); 
        icon.className = 'icon';
        var replyAllButton = new YAHOO.widget.Button("replytoallb");
        replyAllButton.appendChild(icon);
        replyAllButton.on("click", M.block_jmail.replyAllMessage);
        
        var icon = document.createElement('span'); 
        icon.className = 'icon';
        var forwardButton = new YAHOO.widget.Button("forwardb");
        forwardButton.appendChild(icon);
        forwardButton.on("click", M.block_jmail.forwardMessage);
    }
    
    var icon = document.createElement('span'); 
    icon.className = 'icon';
    var moveButton = new YAHOO.widget.Button("moveb", {type: "menu", menu: "labelsmenu"});
    moveButton.appendChild(icon);
    moveButton.getMenu().subscribe("click", M.block_jmail.moveMessage);
    M.block_jmail.app.moveButton = moveButton;
    
    var icon = document.createElement('span'); 
    icon.className = 'icon';
    var moreButton = new YAHOO.widget.Button("moreb", {type: "menu", menu: "moremenu"});
    moreButton.appendChild(icon);
    moreButton.getMenu().subscribe("click", M.block_jmail.moreOptions);
    M.block_jmail.app.moreButton = moreButton;
    
    var icon = document.createElement('span'); 
    icon.className = 'icon';
    var printButton = new YAHOO.widget.Button("printb");
    printButton.appendChild(icon);
    printButton.on("click", M.block_jmail.printMessage);

    // Group and role filter buttons
    if (cfg.cansend) {
        
        if (Y.one('#rolesselectorb')) {
            var rolesselectorB = new YAHOO.widget.Button("rolesselectorb", {type: "menu", menu: "rolesselector"});
            rolesselectorB.getMenu().subscribe("click", function(p_sType, p_aArgs) {            
                    var item = p_aArgs[1];
                    rolesselectorB.set("label", item.cfg.getProperty("text"));
                    M.block_jmail.filterUser.role = item.value;
                    M.block_jmail.loadContacts();                
                });
            M.block_jmail.app.rolesselectorB = rolesselectorB;
        }
        
    
        if (Y.one('#groupselectorb')) {   
            var groupselectorB = new YAHOO.widget.Button("groupselectorb", {type: "menu", menu: "groupselector"});
        
            groupselectorB.getMenu().subscribe("click", function(p_sType, p_aArgs) {            
                var item = p_aArgs[1];
                groupselectorB.set("label", item.cfg.getProperty("text"));
                M.block_jmail.filterUser.group = item.value;
                M.block_jmail.loadContacts();
            });
            M.block_jmail.app.groupselectorB = groupselectorB;
        }
    }
    
    if (Y.one('#mailboxesb')) {        
        var mailboxesB = new YAHOO.widget.Button("mailboxesb", {type: "menu", menu: "mailboxesmenu"});
        mailboxesB.getMenu().subscribe("click", function(p_sType, p_aArgs) {            
                var item = p_aArgs[1];
                mailboxesB.set("label", item.cfg.getProperty("text"));                
                document.location.href = M.block_jmail.cfg.wwwroot+"/blocks/jmail/mailbox.php?id="+item.value;
            });
        M.block_jmail.app.mailboxesB = mailboxesB;
    }

    // Load labels (async request)
    M.block_jmail.logInfo('Loading labels');
    M.block_jmail.loadLabels();


    // Mail list table
    M.block_jmail.logInfo('Loading mail list table');
    
    var url = 'block_jmail_ajax.php?id='+cfg.courseid+'&action=get_message_headers&sesskey='+cfg.sesskey;
    
    M.block_jmail.generateRequest = null;
    
    var initDataTable = function(h, w) {
        
        var unreadFormatter = function(elCell, oRecord, oColumn, oData){
            if (oRecord.getData('read')+'' == '0') {
                oData = '<strong>'+oData+'</strong>';
            }
            if (oColumn.field == 'subject') {
                if (oRecord.getData('approved')+'' == '0') {
                    oData += ' (<span class="approvalpending">'+M.str.block_jmail.approvalpending+'</span>)';
                }
            }
            elCell.innerHTML = oData;
        }
        
        //Create the Column Definitions
        if (cfg.globalinbox) {            
            var myColumnDefs = [            
                {key:"from", 'label' : M.str.block_jmail.from, sortable:true, width: 160, formatter:  unreadFormatter},
                {key:"courseshortname", 'label' : M.str.block_jmail.mailbox, sortable: false, width: 120, formatter:  unreadFormatter},
                {key:"subject", 'label' : M.str.block_jmail.subject, sortable:true, width: (w - M.block_jmail.magicNumSubject), formatter:  unreadFormatter },
                {key:"date", 'label' : M.str.block_jmail.date,sortable:true, width: 160, formatter:  unreadFormatter }
            ];            
        } else {
            var myColumnDefs = [            
                {key:"from", 'label' : M.str.block_jmail.from, sortable:true, width: 160, formatter:  unreadFormatter},
                {key:"subject", 'label' : M.str.block_jmail.subject, sortable:true, width: (w - M.block_jmail.magicNumSubject), formatter:  unreadFormatter },
                {key:"date", 'label' : M.str.block_jmail.date,sortable:true, width: 160, formatter:  unreadFormatter }
            ];
        }
        
        
        // DataSource instance
        var myDataSource = new YAHOO.util.DataSource(url);
        myDataSource.responseType = YAHOO.util.DataSource.TYPE_JSON;
        myDataSource.responseSchema = {
            resultsList: "messages",
            fields: ["id","from","subject","date","read","approved","courseid","courseshortname"],
            // Access to values in the server response
            metaFields: {
                totalRecords: "total",
                startIndex: "start"
            }
        };
        
        // Customize request sent to server to be able to set total # of records
        M.block_jmail.generateRequest = function(oState, oSelf) {
            // Get states or use defaults
            oState = oState || { pagination: null, sortedBy: null };
            var sort = (oState.sortedBy) ? oState.sortedBy.key : M.block_jmail.filterMessage.sort;
            var dir = (oState.sortedBy && oState.sortedBy.dir === YAHOO.widget.DataTable.CLASS_ASC) ? "ASC" : M.block_jmail.filterMessage.direction;
            var startIndex = (oState.pagination) ? oState.pagination.recordOffset : M.block_jmail.filterMessage.start;
            var results = (oState.pagination) ? oState.pagination.rowsPerPage : cfg.pagesize;
   
            // Build custom request
            return  "&sort=" + sort +
                    "&direction=" + dir +
                    "&start=" + startIndex +
                    "&label=" + M.block_jmail.filterMessage.label +                    
                    "&searchtext=" + M.block_jmail.filterMessage.searchtext
                    ;
        };        


        // DataTable configuration
        var myConfigs = {
            generateRequest: M.block_jmail.generateRequest,
            initialRequest: M.block_jmail.generateRequest(), // Initial request for first page of data
            dynamicData: true, // Enables dynamic server-driven data
            paginator: new YAHOO.widget.Paginator({ rowsPerPage: cfg.pagesize}), // Enables pagination
            scrollable: true,
            height: h + 'px', width: w + 'px'
        };

        M.block_jmail.app.dataTable = new YAHOO.widget.DataTable("maillist", myColumnDefs, myDataSource, myConfigs);
        M.block_jmail.app.dataTable.set('MSG_EMPTY', M.str.block_jmail.nomessagesfound);
        
        // Subscribe to events for row selection
        M.block_jmail.app.dataTable.subscribe("rowMouseoverEvent", M.block_jmail.app.dataTable.onEventHighlightRow);
        M.block_jmail.app.dataTable.subscribe("rowMouseoutEvent", M.block_jmail.app.dataTable.onEventUnhighlightRow);
        M.block_jmail.app.dataTable.subscribe("rowClickEvent", M.block_jmail.app.dataTable.onEventSelectRow);
        M.block_jmail.app.dataTable.subscribe("rowSelectEvent", function() {
            
            Y.one('#mailcontents').setContent('<div class = "loading_big"></div>');

            // First row for displaying the first mail
            var data = this.getRecordSet().getRecord(this.getSelectedRows()[0])._oData;  

            // Ajax call for mark it as read
            M.block_jmail.markRead(data, 1);
            
            M.block_jmail.loadMessage(data.id, data.courseid);
            // All rows selected
            //console.log(this.getSelectedRows());
            
        }, M.block_jmail.app.dataTable, true);
        
        M.block_jmail.app.dataTable.doBeforeLoadData = function(oRequest, oResponse, oPayload) {
            oPayload.totalRecords = oResponse.meta.totalRecords;
            oPayload.pagination.recordOffset = oResponse.meta.startIndex;
            return oPayload;
        };
        
        M.block_jmail.app.dataTable.subscribe("postRenderEvent", function(){
            var Y = M.block_jmail.Y;        
            Y.all("#maillist .yui-pg-first").set('text', M.str.block_jmail.first);
            Y.all("#maillist .yui-pg-last").set('text', M.str.block_jmail.last);
            Y.all("#maillist .yui-pg-next").set('text', M.str.block_jmail.next);
            Y.all("#maillist .yui-pg-previous").set('text', M.str.block_jmail.previous);
        }, M.block_jmail.app.dataTable, true);
        
        M.block_jmail.app.dataTable.subscribe("tbodyKeyEvent", function(event, target){
            var cfg = M.block_jmail.cfg;            
            var keyCode = event.event.keyCode;
            
            if (keyCode == 46) {                
                M.block_jmail.deleteMessage();
            }
            else if (keyCode == 82 && cfg.cansend) {                
                M.block_jmail.replyMessage();
            }
            else if (keyCode == 70 && cfg.cansend) {                
                M.block_jmail.forwardMessage();
            }
            else if (keyCode == 80) {
                M.block_jmail.printMessage();
            }
            else if (keyCode == 65 && cfg.canapprovemessages) {
                M.block_jmail.approveMessage();
            }
        });
        
        M.block_jmail.app.dataSource = myDataSource;
    };

    initDataTable(layout2.getSizes().top.h - M.block_jmail.magicNumTop, layout2.getSizes().top.w - M.block_jmail.magicNumDataTableWidth);

    M.block_jmail.logInfo('Loading user list with filters');
    // Alphabet filter    
    if (cfg.cansend) {
        
        Y.all('#firstnamefilter .alphabet').on('click', function(e){
                Y.all('#firstnamefilter a').setStyle('font-weight', 'normal');
                e.target.setStyle('font-weight', 'bold');            
                M.block_jmail.filterUser.firstname = e.target.get('text');
                M.block_jmail.loadContacts();
                // Stop the event's default behavior
                e.preventDefault();
            });
        Y.all('#lastnamefilter .alphabet').on('click', function(e){
                Y.all('#lastnamefilter a').setStyle('font-weight', 'normal');
                e.target.setStyle('font-weight', 'bold');            
                M.block_jmail.filterUser.lastname = e.target.get('text');
                M.block_jmail.loadContacts();
                // Stop the event's default behavior
                e.preventDefault();
            });
        Y.all('#firstnamefilter .alphabetreset').on('click', function(e){
                Y.all('#firstnamefilter a').setStyle('font-weight', 'normal');
                e.target.setStyle('font-weight', 'bold');
                M.block_jmail.filterUser.firstname = '';
                M.block_jmail.loadContacts();
                // Stop the event's default behavior
                e.preventDefault();
            });
        Y.all('#lastnamefilter .alphabetreset').on('click', function(e){
                Y.all('#lastnamefilter a').setStyle('font-weight', 'normal');
                e.target.setStyle('font-weight', 'bold');
                M.block_jmail.filterUser.lastname = '';
                M.block_jmail.loadContacts();
                // Stop the event's default behavior
                e.preventDefault();
            });
    }
    
    // Labels
    var addlabel = Y.one('#addlabel');
    if (addlabel) {
        addlabel.on('click', function(e){
            M.block_jmail.processMenuButtons();
            Y.one('#newlabelpanel').setStyle('display', 'block');
            M.block_jmail.addLabel();
            e.preventDefault();
        });
    }
    
    // Build the labels action menu
    // TODO - Add rename options
    
    var labelsMenu = new YAHOO.widget.Menu("basicmenu");
    labelsMenu.addItems([{ text: "&nbsp;&nbsp;"+M.str.block_jmail.deletem, onclick: { fn: M.block_jmail.deleteLabel } }]);
    labelsMenu.render("menulabel");
    M.block_jmail.app.labelsMenu = labelsMenu;
    
    
    // Actions for fixed labels inbox, draft, bin
    
    Y.one('#label_list ul').all('a').on('click', function(e){        
        M.block_jmail.checkMail(e.target.get('id'));
        e.preventDefault();
    });
    
    // Preferences
    var preferencesPanel = new Y.Panel({
            srcNode      : '#preferencespanel',
            headerContent: M.str.block_jmail.preferences,
            width        : 400,
            zIndex       : 5,
            centered     : true,
            modal        : true,
            visible      : false,
            render       : true,
            plugins      : [Y.Plugin.Drag]
        });
    preferencesPanel.addButton({
        value  : M.str.moodle.ok,
        section: Y.WidgetStdMod.FOOTER,
        action : function (e) {
            var cfg = M.block_jmail.cfg;
            var Y = M.block_jmail.Y;
            var preferences = {
                receivecopies: (Y.one('#subscription').get('value'))? 1 : 0
            };
            preferences = Y.JSON.stringify(preferences);
            var url = 'block_jmail_ajax.php?id='+cfg.courseid+'&action=save_preferences&sesskey='+cfg.sesskey+'&preferences='+preferences;
            M.block_jmail.Y.io(url);
            this.hide();
            e.preventDefault();            
        }
    });
    preferencesPanel.addButton({
        value  : M.str.moodle.cancel,
        section: Y.WidgetStdMod.FOOTER,
        action : function (e) {
            this.hide();
            e.preventDefault();            
        }
    });
    
    var prefs = Y.one('#preferences');
    if (prefs) {
        prefs.on('click', function(e){
            var cfg = M.block_jmail.cfg;
            var Y = M.block_jmail.Y;
            
            M.block_jmail.processMenuButtons();
            
            var url = 'block_jmail_ajax.php?id='+cfg.courseid+'&action=get_preferences&sesskey='+cfg.sesskey;        
            var request = Y.io(url, {sync: true});        
            var preferences = Y.JSON.parse(request.responseText);
            if (preferences.receivecopies == '1') {
                Y.one('#subscription').set('value', 'receivecopies');
            } else {
                Y.one('#subscription').set('value', '');
            }
            Y.one('#preferencespanel').setStyle('display', 'block');
            preferencesPanel.show();
            e.preventDefault();
        });
    }
    
    // Search
    
    Y.one('#input_search').on('keyup', function(e){
        M.block_jmail.searchText = Y.Lang.trim(this.get('value'));
        if (M.block_jmail.searchText.length >= 3) {
            clearTimeout(M.block_jmail.searchTimeout);
            setTimeout(function() { M.block_jmail.checkMail('search', M.block_jmail.searchText) }, 600);
        } else if (M.block_jmail.searchText.length == 0) {
            M.block_jmail.checkMail('inbox');
        }
     });

    // Compose email fields    
    // Autocomplete
    
    var cfg = {
        resultHighlighter: 'phraseMatch',
        minQueryLength: 2,
        resultTextLocator: 'fullname',        
        source: 'block_jmail_ajax.php?id='+cfg.courseid+'&action=get_contacts_search&sesskey='+cfg.sesskey+'&search={query}'
    };
    
    cfg.on = {
            select: function(e) {
                M.block_jmail.addContact(e.details[0].result.raw.id, e.details[0].result.raw.fullname, 'to');
            }};
    Y.one('#composetoac').plug(Y.Plugin.AutoComplete, cfg);
    
    cfg.on = {
            select: function(e) {
                M.block_jmail.addContact(e.details[0].result.raw.id, e.details[0].result.raw.fullname, 'cc');
            }};
    Y.one('#composeccac').plug(Y.Plugin.AutoComplete, cfg);
    
    cfg.on = {
            select: function(e) {
                M.block_jmail.addContact(e.details[0].result.raw.id, e.details[0].result.raw.fullname,'bcc');
            }};
    Y.one('#composebccac').plug(Y.Plugin.AutoComplete, cfg);
    
    // Save and send messages buttons
    
    new YAHOO.widget.Button("savebutton");
    Y.one('#savebutton').on('click', function(e){
        M.block_jmail.saveMessage('save_message');
    });
    
    new YAHOO.widget.Button("sendbutton");
    Y.one('#sendbutton').on('click', function(e){
        M.block_jmail.saveMessage('send_message');
    });
    
    if (M.block_jmail.cfg.usertoid) {
        M.block_jmail.composeMessage('new');
        M.block_jmail.addContact(M.block_jmail.cfg.usertoid, M.block_jmail.cfg.usertoname, 'to');
    }
    
    // Toolbar
    M.block_jmail.hideToolbar();
    M.block_jmail.logInfo('Application loaded');


};

M.block_jmail.addContact = function(userId, fullName, type) {
    var cfg = M.block_jmail.cfg;
    var Y = M.block_jmail.Y;
    
    M.block_jmail.logInfo('Adding contact: ' + userId + ' ' + fullName);
    
    var hidden = Y.one('#hidden'+type);    

    if(Y.Array.indexOf(hidden.get('value').split(','), userId) < 0) {
        hidden.set('value', hidden.get('value') + userId + ',');
        Y.one('#compose'+type+'list').append('<span id="destinatary'+type+userId+'" class="destinatary">'+fullName+' <img id="delete'+type+userId+'" src="pix/delete.gif" alt="'+M.str.block_jmail.deletem+'"></span>');
        Y.one("#delete"+type+userId).on('click', function(e){
                var todelete = e.target.get("id").replace('delete'+type,'');
                var dest = hidden.get('value').split(',');
                hidden.set('value', '');
                var newdest = '';
                for (var el in dest) {
                    if (dest[el] != todelete) {
                        newdest += dest[el]+','
                    }
                }
                hidden.set('value', newdest);
                Y.one('#destinatary'+type+userId).remove();
            });
    }
    setTimeout(function() { Y.one('#compose'+type+'ac').set('value', ''); }, 100);
}

M.block_jmail.deleteLabel = function(p_sType, p_aArgs, p_oValue) {
    var cfg = M.block_jmail.cfg;
    var Y = M.block_jmail.Y;    
    var url = 'block_jmail_ajax.php?id='+cfg.courseid+'&action=delete_label&sesskey='+cfg.sesskey+'&labelid='+M.block_jmail.currentLabel;
    var cfg = {
        on: {
            complete: function(id, o, args) {
                M.block_jmail.loadLabels();
            }
        }
    };
    Y.io(url, cfg);  
}

M.block_jmail.checkMail = function(label, searchtext) {
    
    if (typeof searchtext == 'undefined') {
        searchtext = '';
    }
    
    M.block_jmail.hideToolbar();
    this.Y.one('#mailcontents').setContent('');
    
    M.block_jmail.filterMessage = {
            label: label,
            start: 0,
            sort: 'date',
            direction: 'DESC',
            searchtext: searchtext
        };
        
    if (searchtext) {
        M.block_jmail.searchTimeout = null;
    }
    
    M.block_jmail.app.dataSource.sendRequest(M.block_jmail.generateRequest(), {
        success : M.block_jmail.app.dataTable.onDataReturnSetRows,
        failure : M.block_jmail.app.dataTable.onDataReturnSetRows,
        scope : M.block_jmail.app.dataTable,
        argument: M.block_jmail.app.dataTable.getState() // data payload that will be returned to the callback function
    }); 
}

// Main function for loading the contact list based on filters

M.block_jmail.loadContacts = function() {
    var cfg = M.block_jmail.cfg;
    var Y = M.block_jmail.Y;
    
    Y.one("#selectall").set('checked', false);
    
    var params = '';
    params += '&fi='+M.block_jmail.filterUser.firstname;
    params += '&li='+M.block_jmail.filterUser.lastname;
    params += '&group='+M.block_jmail.filterUser.group;
    params += '&roleid='+M.block_jmail.filterUser.role;
    
    var actionButtons = '<br />';
    var buttonTypes = {to: 'to', cc: 'cc', bcc: 'bcc'};
    for (var el in buttonTypes) {
        actionButtons += '<input type="button" class="b'+el+'" value="'+M.str.block_jmail[buttonTypes[el]]+'">&nbsp;';
    }
    
    var url = 'block_jmail_ajax.php?id='+cfg.courseid+'&action=get_contacts&sesskey='+cfg.sesskey+params;
    var cfg = {
        on: {
            complete: function(id, o, args) {
                    var contactsHtml = '';
                    
                    contacts = Y.JSON.parse(o.responseText);
                    
                    var cssclass = 'jmail-odd';
                    
                    for(var el in contacts) {
                        cssclass = (cssclass == 'jmail-even') ? 'jmail-odd': 'jmail-even';
                        var imageHtml = contacts[el].profileimage;
                        contactsHtml += '<div id="user'+contacts[el].id+'" class="'+cssclass+' contact">';
                        contactsHtml += ' <div class="profileimage"><input type="checkbox">'+imageHtml+'</div>';
                        contactsHtml += ' <div class="fullname">'+contacts[el].fullname+actionButtons+'</div>';                        
                        contactsHtml += '</div>';;
                    }
                    
                    var cList = Y.one('#contact_list_users');
                    cList.set('text','');
                    cList.append(contactsHtml);
                    
                    Y.all('#contact_list_users input[type=button]').on('click', function(e){
                        var userid = e.target.ancestor('div').ancestor('div').get('id').replace('user', '');
                        // Detect to, cc or bcc - e.target.hasClass();
                        M.block_jmail.composeMessage('new');
                        M.block_jmail.addContact(userid, e.target.ancestor('div').get('text'),e.target.get('className').replace('b',''));
                    });
            }
        }
    };
    Y.io(url, cfg);
}

M.block_jmail.loadMessage = function(messageId, courseId) {
    var cfg = M.block_jmail.cfg;
    var Y = M.block_jmail.Y;
    
    var url = 'block_jmail_ajax.php?id='+courseId+'&action=get_message&sesskey='+cfg.sesskey+'&messageid='+messageId;
    var cfg = {
        on: {
            complete: function(id, o, args) {
                
                var cfg = M.block_jmail.cfg;
                var message = Y.JSON.parse(o.responseText);
                M.block_jmail.currentMessage = message;

                if (typeof message.error === 'undefined') {
                    M.block_jmail.showToolbar(message);
                    
                    var messageHtml = '<div id="mail_header"> \
                                  <div class="mail_from"><div class="mail_el">'+M.str.block_jmail.from+': </div><span>'+message.from+'</span></div> \
                                  <div class="mail_subject"><div class="mail_el">'+M.str.block_jmail.subject+': </div><span>'+message.subject+'</span></div>';

                    // Destinataries
                    var lang = {to : M.str.block_jmail.to, cc: M.str.block_jmail.cc, bcc: M.str.block_jmail.bcc};

                    if (message.destinataries['to'].length > 0) {
                        for (var el in message.destinataries) {
                            var dest = message.destinataries[el];
                            messageHtml += '<div class="mail_destinatary"><div class="mail_el">'+lang[el]+': </div>';
                            for (var el2 in dest) {
                                messageHtml += '<span><a href="'+cfg.wwwroot+'/user/view.php?id='+dest[el2].userid+'&course='+cfg.courseid+'" target="_blank">'+dest[el2].fullname+'</a></span>&nbsp;';
                            }
                            messageHtml += '</div>';
                        }
                    }

                    // Labels
                    if (message.labels.length > 0) {
                         messageHtml += '<div class="mail_label"><div class="mail_el">'+M.str.block_jmail.labels+': </div><span>';
                        for (var el in message.labels) {
                            var label = message.labels[el];
                            if (typeof label.id != 'undefined') {
                                messageHtml += '<span class="labelactions">'+label.name+'<img id="unlabel_'+label.id+'_'+messageId+'" src="pix/delete.gif" alt="'+M.str.block_jmail.deletem+'"></span>';
                            } else {
                                // Fixed labels, inbox, send, trash, etc...
                                messageHtml += '<span class="labelactions">'+label+'</span>';
                            }
                        }
                        messageHtml += '</span></div>';
                    }
                    
                    if (message.approved+'' == '0') {
                        messageHtml += '<div class="approvalpending">'+M.str.block_jmail.approvalpending+'</div>';
                    }
                    
                    var attachmentsHtml = '';
                    if (message.attachments.length > 0) {
                        attachmentsHtml = '<div class="attachments"><p><strong>'+M.str.block_jmail.attachments+'</strong></p>';
                        for (var el in message.attachments) {
                            var attach = message.attachments[el];
                            attachmentsHtml += '<p>'+attach.iconimage+' '+attach.filename+'  <a class="downloadattachment" href="'+attach.path+'">'+M.str.block_jmail.download+'</a> | <a href="" class="savetoprivate">'+M.str.block_jmail.savetomyprivatefiles+'</a></p>';
                        }
                        attachmentsHtml += '</div>';
                    }

                    // Messabe body
                    messageHtml +=    '</div> \
                                  <div id="mail_contents"> \
                                    <div class="attachmentstop">'+attachmentsHtml+'</div>\
                                    '+message.body+'\
                                    <div class="attachmentsbottom">'+attachmentsHtml+'</div>\
                                  </div> \
                                  ';
                    Y.one('#mailcontents').setContent(messageHtml);
                    
                    Y.all('.labelactions img').on('click', function(e){                                    
                        var data = e.target.get('id').replace('unlabel_','').split('_');
                        var url = 'block_jmail_ajax.php?id='+cfg.courseid+'&action=unlabel_message&sesskey='+cfg.sesskey+'&messageids='+data[1]+'&labelid='+data[0];
                        Y.io(url);
                        e.target.ancestor('span').remove();
                    });
                    
                    Y.all('.attachments .savetoprivate').on('click', function(e){
                        e.preventDefault();                        
                        var myfile = e.target.ancestor('p').one('.downloadattachment').get('href');
                        myfile = myfile.replace(cfg.wwwroot+'/pluginfile.php','');
                        
                        var url = 'block_jmail_ajax.php?id='+cfg.courseid+'&action=save_private&sesskey='+cfg.sesskey+'&file='+myfile;
                        Y.io(url, {sync: true});
                        M.block_jmail.showMessage(M.str.block_jmail.filesaved);
                    });
                    
                }
            }
        }
    };
    Y.io(url, cfg);
}

M.block_jmail.addLabel = function() {
    var Y = M.block_jmail.Y;
    var cfg = M.block_jmail.cfg;
    
    if (typeof M.block_jmail.app.panel == 'undefined') {
        var panel = new Y.Panel({
            srcNode      : '#newlabelpanel',
            headerContent: M.str.block_jmail.addlabel,
            width        : 250,
            zIndex       : 5,
            centered     : true,
            modal        : true,
            visible      : false,
            render       : true,
            plugins      : [Y.Plugin.Drag]
        });
        M.block_jmail.app.panel = panel;
        
        Y.one('#newlabelname').on('key', function(e) {
            M.block_jmail.addLabelAction();
            e.preventDefault();
        }, 'enter');
        
        M.block_jmail.app.panel.addButton({
            value  : M.str.moodle.add,
            section: Y.WidgetStdMod.FOOTER,
            action : function (e) {
                e.preventDefault();
                M.block_jmail.addLabelAction();                
            }
        });
    }
    
    Y.one('#newlabelname').set('value', '');
    M.block_jmail.app.panel.show();
    Y.one('#newlabelname').focus();
}

M.block_jmail.addLabelAction = function() {
    var Y = M.block_jmail.Y;
    var cfg = M.block_jmail.cfg;
    var name = Y.one('#newlabelname').get('value');
    var url = 'block_jmail_ajax.php?id='+cfg.courseid+'&action=create_label&sesskey='+cfg.sesskey+'&name='+name;
    var cfg = {
        on: {
            complete: function(id, o, args) {
                M.block_jmail.app.panel.hide();
                M.block_jmail.loadLabels();
            }
        }
    };
    Y.io(url, cfg);    
}

M.block_jmail.loadLabels = function() {
    var cfg = M.block_jmail.cfg;
    var Y = M.block_jmail.Y;
    
    var url = 'block_jmail_ajax.php?id='+cfg.courseid+'&action=get_labels&sesskey='+cfg.sesskey;
    var cfg = {
        on: {
            complete: function(id, o, args) {
                    
                    // Load left block
                    var labels = Y.JSON.parse(o.responseText);
                    
                    if (typeof labels.error != 'undefined') {
                        M.block_jmail.displayError(labels);
                        return false;
                    }
                    
                    M.block_jmail.labels = labels;
                    var labelsHtml = '';
                    var menuItems = [{text: M.str.block_jmail.inbox, value: 'inbox'}];
                    
                    for(var el in labels) {
                        var l = labels[el];
                        labelsHtml += '<li class="folder"><em></em><a href="#" id="label'+l.id+'">'+l.name+'</a><span class="labelactions" style="visibility: hidden"><img id="labelactions'+l.id+'" src="pix/menu.png"></span></li>';
                        menuItems.push({text: l.name, value: l.id})
                    }
                    
                    var cList = Y.one('#user_labels');
                    cList.set('text','');
                    cList.append('<ul>'+labelsHtml+'</ul>');
                    
                    Y.all("#user_labels li").on('mouseover', function(e){                                                
                         e.target.ancestor('li', true).one('.labelactions').setStyle('visibility', 'visible');
                    });
                    
                    Y.all("#user_labels li").on('mouseout', function(e) {
                        e.target.ancestor('li', true).one('.labelactions').setStyle('visibility', 'hidden');
                    });
                    
                    Y.all('#user_labels a').on('click', function(e){        
                        M.block_jmail.checkMail(e.target.get('id').replace("label",""));
                        e.preventDefault();
                    });
                                       
                    Y.all("#user_labels img").on('click', function(e){
                            //Y.all("#user_labels li .labelactions").setStyle('visibility', 'hidden');
                            M.block_jmail.app.labelsMenu.cfg.setProperty('context', [e.target.get('id'),'tr','tr']);
                            M.block_jmail.app.labelsMenu.cfg.setProperty('visible', true);
                            M.block_jmail.app.labelsMenu.cfg.setProperty('zindex', 70);
                            M.block_jmail.currentLabel = e.target.get('id').replace("labelactions","");
                        });
                    
                    // TODO Load move button labels                    
                    var oMenu = M.block_jmail.app.moveButton.getMenu();
                    if (YAHOO.util.Dom.inDocument(oMenu.element)) {                                                 
                        oMenu.clearContent();
                        oMenu.addItems(menuItems);
                        oMenu.render();
                    } else {                        
                        // Delete the duplicate inbox, this is due because we render in a special way the initial button                        
                        menuItems.shift();
                        oMenu.itemData = menuItems;
                    }
            }
        }
    };
    Y.io(url, cfg);
}

M.block_jmail.deleteMessage = function() {
    M.block_jmail.confirmDialog(M.str.block_jmail.confirmdelete, M.block_jmail.deleteMessageConfirm);
}

M.block_jmail.approveMessage = function() {
    var Y = M.block_jmail.Y;
    var cfg = M.block_jmail.cfg;

    var messages = M.block_jmail.app.dataTable.getSelectedRows();
    for (var el in messages) {
        var messageId = M.block_jmail.app.dataTable.getRecordSet().getRecord(messages[el])._oData.id;
        var courseid = M.block_jmail.app.dataTable.getRecordSet().getRecord(messages[el])._oData.courseid;
        var url = 'block_jmail_ajax.php?id='+courseid+'&action=approve_message&sesskey='+cfg.sesskey+'&messageid='+messageId;
        var cfgIO = {
            on: {
                complete: function(id, o, args) {                  
                    M.block_jmail.checkMail('toapprove');
                }
            }
        };
        Y.io(url, cfgIO);
    }
}

M.block_jmail.replyMessage = function() {
    M.block_jmail.composeMessage('reply', M.block_jmail.currentMessage);
}

M.block_jmail.replyAllMessage = function() {
    M.block_jmail.composeMessage('replytoall', M.block_jmail.currentMessage);
}

M.block_jmail.forwardMessage = function() {
    M.block_jmail.composeMessage('forward', M.block_jmail.currentMessage);
}

M.block_jmail.moveMessage = function(p_sType, p_aArgs) {
    var cfg = M.block_jmail.cfg;
    var Y = M.block_jmail.Y;
    
    var oMenuItem = p_aArgs[1];
    var labelid = oMenuItem.value;

    var messageids = '';
    var messages = M.block_jmail.app.dataTable.getSelectedRows();
    for (var el in messages) {
        messageids += M.block_jmail.app.dataTable.getRecordSet().getRecord(messages[el])._oData.id + ',';
    }
    
    var url = 'block_jmail_ajax.php?id='+cfg.courseid+'&action=label_message&sesskey='+cfg.sesskey+'&messageids='+messageids+'&labelid='+labelid;
    var cfg = {
        on: {
            complete: function(id, o, args) {                  
                M.block_jmail.checkMail(M.block_jmail.filterMessage.label, M.block_jmail.filterMessage.searchtext);
            }
        }
    };
    Y.io(url, cfg);
}


M.block_jmail.moreOptions = function(p_sType, p_aArgs) {
    
    var cfg = M.block_jmail.cfg;
    var oMenuItem = p_aArgs[1];
    var action = oMenuItem.value;

    var messages = M.block_jmail.app.dataTable.getSelectedRows();
    for (var el in messages) {
        var row = M.block_jmail.app.dataTable.getRecordSet().getRecord(messages[el])._oData;
        if (action == 'markread') {
            M.block_jmail.markRead(row, 1);
        }
        else if (action == 'markunread') {
            M.block_jmail.markRead(row, 0);            
        }
    }
}

M.block_jmail.printMessage = function() {
    // open the print the message window
    var cfg = M.block_jmail.cfg;
    
    var newWindow = window.open('print.php?id='+M.block_jmail.currentMessage.courseid+'&messageid='+M.block_jmail.currentMessage.id, '_blank');
    newWindow.focus();    
}


M.block_jmail.deleteMessageConfirm = function() {
    var cfg = M.block_jmail.cfg;
    var Y = M.block_jmail.Y;

    var messageids = '';
    var messages = M.block_jmail.app.dataTable.getSelectedRows();
    for (var el in messages) {
        messageids += M.block_jmail.app.dataTable.getRecordSet().getRecord(messages[el])._oData.id + ',';
    }
    
    var url = 'block_jmail_ajax.php?id='+cfg.courseid+'&action=delete_message&sesskey='+cfg.sesskey+'&messageids='+messageids;
    var cfg = {
        on: {
            complete: function(id, o, args) {                  
                M.block_jmail.checkMail(M.block_jmail.filterMessage.label, M.block_jmail.filterMessage.searchtext);
            }
        }
    };
    Y.io(url, cfg);
    
    
}

// Every time we render a panel, we should perform this function, it seems a yui bug?
M.block_jmail.processMenuButtons = function() {
    
    // TODO, imrpove to a loop
    var buttons = ['rolesselectorB','moreButton','moveButton','groupselectorB','mailboxesB'];
    
    for (var el in buttons) {
        el = buttons[el];
        if (M.block_jmail.app[el]) {
            M.block_jmail.app[el].getMenu().show();
            M.block_jmail.app[el].getMenu().hide();
        }
    }    
}

M.block_jmail.composeMessage = function(mode, message) {
    
    var cfg = M.block_jmail.cfg;
    var Y = M.block_jmail.Y;
    var messageId = 0;

    if (M.block_jmail.newemailOpen) {
        return false;
    }    
    M.block_jmail.newemailOpen = true;
    
    M.block_jmail.processMenuButtons();
    
    M.block_jmail.app.composePanel.cfg.setProperty("visible",true);
    M.block_jmail.app.composePanel.show();
    
    Y.one('#hiddento').set('value', '');
    Y.one('#hiddencc').set('value', '');
    Y.one('#hiddenbcc').set('value', '');
    Y.one('#subject').set('value', '');
    Y.one('#composetolist').setContent('');
    Y.one('#composecclist').setContent('');
    Y.one('#composebcclist').setContent('');
    Y.one('#newemailformremote').setContent('');
    
    if (typeof message == 'object') {        
        messageId = message.id;
        
        if (mode == 'forward') {
            message.subject = M.str.block_jmail.fw+message.subject;
        }
        
        if (mode == 'reply') {
            message.subject = M.str.block_jmail.re+' '+message.subject;
            M.block_jmail.addContact(message.sender, message.from, 'to');
        }

        if (mode == 'replytoall') {
            message.subject = M.str.block_jmail.re+message.subject;
            M.block_jmail.addContact(message.sender, message.from, 'to');
            for (var el in message.destinataries) {
                var dest = message.destinataries[el];            
                for (var el2 in dest) {
                    if (dest[el2].userid == cfg.userid) {
                        continue;
                    }
                    M.block_jmail.addContact(dest[el2].userid, dest[el2].fullname, el);
                }
            }
        }
        
        if (mode == 'edit') {            
            for (var el in message.destinataries) {
                var dest = message.destinataries[el];            
                for (var el2 in dest) {
                    M.block_jmail.addContact(dest[el2].userid, dest[el2].fullname, el);
                }
            }
        }
        
        Y.one('#subject').set('value', message.subject);       
    }
    
    // Preserve the M.str object (Firefox bug)
    
    var Mstr = M.str;
    
    var iocfg = {
         sync: true
     };

    if (messageId) {
        courseid = message.courseid;
        M.block_jmail.currentComposeCourse.shortname = message.courseshortname;
    } else {
        courseid = cfg.courseid;
    }
    
    M.block_jmail.currentComposeCourse.id = courseid;
    
    var uri = 'message.php?id='+courseid+'&messageid='+messageId+'&mode='+mode;
    var request = Y.io(uri, iocfg);
    
    var formHtml = request.responseText;

    Y.one('#newemailformremote').insert(formHtml);
    M.block_jmail.Y = Y;

    // So so uggly hack
    // Firefox excluded    
    // if (Y.UA.gecko <= 0) {
        var elementsToEval = ["Y.use('editor_tinymce'","Y.use('editor_tinymce'","Y.use('form_filemanager'"];
        
        // Moodle 2.3 and onwards
        if (cfg.version >= 2012062500) {
            var elementsToEval = ["Y.use(\"moodle-core-formchangechecker\"","Y.use('core_filepicker'","Y.use('editor_tinymce'","Y.use('editor_tinymce'","Y.use('form_filemanager'","Y.use('form_filemanager'"];
        }
        
        for (var el in elementsToEval) {
            console.log("Evaluating " + elementsToEval[el]);
            var startIndex = formHtml.indexOf(elementsToEval[el]);
            formHtml = formHtml.substr(startIndex);        
            var stopIndex = formHtml.indexOf("});") + 7;
            
            var js = formHtml.substring(0, stopIndex);
            js = js.replace('<!--','');
            js = js.replace('-->','');
            
            eval('try {'+js+'} catch(e) { console.log(e) }');
            formHtml = formHtml.substr(stopIndex);
        }
    // }
    M.str = Mstr;    
}

M.block_jmail.saveMessage = function(action) {
    var cfg = M.block_jmail.cfg;
    var Y = M.block_jmail.Y;
    
    var form = Y.one('#mform1');
    
    var messageid = form.get("messageid").get("value");
    var to = Y.one('#hiddento').get('value');
    var cc = Y.one('#hiddencc').get('value');
    var bcc = Y.one('#hiddenbcc').get('value');
    var subject = encodeURIComponent(Y.Lang.trim(Y.one('#subject').get('value')));
    
    var errors = '';
    
    if (action == 'send_message') {
        if (!to) {
            errors += '<p>'+M.str.block_jmail.errortorequired+'</p>';
        }
        
        if (subject.length < 1) {
            errors += '<p>'+M.str.block_jmail.errorsubjectrequired+'</p>';
        }
    }
    
    if (errors) {
        M.block_jmail.showMessage(errors, 6000);
        return false;
    }
    
    window.tinyMCE.triggerSave();
    window.tinyMCE.get('id_body').setProgressState(1);
    var body = encodeURIComponent(Y.one('#id_body').get("value"));
    var attachments = form.get("attachments").get("value");
    var editoritemid = form.get("body[itemid]").get("value");
    
    var url = 'block_jmail_ajax.php?id='+M.block_jmail.currentComposeCourse.id+'&action='+action+'&sesskey='+cfg.sesskey;
    url += '&to='+to;
    url += '&cc='+cc;
    url += '&bcc='+bcc;
    url += '&subject='+subject;
    url += '&attachments='+attachments;
    url += '&editoritemid='+editoritemid;
    url += '&messageid='+messageid;
    
    var cfg = {
        method: 'POST',
        data: 'body='+body, 
        on: {
            complete: function(id, o, args) {
                var cfg = M.block_jmail.cfg;
                window.tinyMCE.get('id_body').setProgressState(0);
                M.block_jmail.app.composePanel.hide();
                // Remove all the form elements
                // We do a cascade remove for some firefox issues
                var messageAlert = (action == 'send_message')? M.str.block_jmail.messagesent : M.str.block_jmail.messagesaved;
                var messageTime = 2000;
                
                if (action == 'send_message' && cfg.approvemode && !cfg.canapprovemessages) {
                    messageAlert += '<br/>'+M.str.block_jmail.messagehastobeapproved;
                    messageTime = 4000;
                }
                
                if (action == 'send_message' && M.block_jmail.cfg.globalinbox) {
                    if (M.block_jmail.currentComposeCourse.id != M.block_jmail.cfg.courseid) {
                        var addiotonalInfo =  M.str.block_jmail.delivertodifferentcourse +' '+M.block_jmail.currentComposeCourse.shortname;
                    } else {
                        var addiotonalInfo =  M.str.block_jmail.delivertoglobalinbox;
                    }
                    messageAlert += '<br/>'+addiotonalInfo;
                    messageTime = 6000;
                }
                
                M.block_jmail.showMessage(messageAlert, messageTime);
                window.tinyMCE.remove(window.tinyMCE.get('id_body'));
                window.tinyMCE.execCommand('mceRemoveControl',true,'id_body');                
                Y.one("#id_body_ifr").remove();
                Y.one("#mform1").remove();
                Y.one('#newemailformremote').setContent('');
            }
        }
    };
    Y.io(url, cfg);
    
}

M.block_jmail.markRead = function (row, status) {
    // Set message as read
    // Call again the render, the render will call the cell formater that will detect that the message is read
    
    if (row.read != status) {    
        row.read = status;
        M.block_jmail.app.dataTable.render();
        
        var url = 'block_jmail_ajax.php?id='+row.courseid+'&action=mark_read&status='+status+'&sesskey='+this.cfg.sesskey+'&messageid='+row.id;
        this.Y.io(url);
    }
}

M.block_jmail.hideToolbar = function() {
    this.Y.one('#jmailtoolbar').setStyle('visibility','hidden');
}

M.block_jmail.showToolbar = function(message) {
    var toHide = ['deleteb', 'editb', 'replyb', 'replytoallb', 'forwardb', 'moveb', 'moreb', 'printb'];
    var toShow = [];
    
    if (typeof message != 'undefined') {
        
        var labels = message.labels;        
        for (var el in toHide) {
            this.Y.one("#"+toHide[el]).setStyle('display', 'none');
        }
        
        
        var totaldestinataries = 0;        
        for (var type in message.destinataries) {
            for (var el in message.destinataries[type]) {
                totaldestinataries++;
            }
        }
        
        var replytoall = totaldestinataries > 1;
        
        if (this.Y.Array.indexOf(labels, 'trash') >= 0) {
            toShow = ['deleteb', 'replyb', 'forwardb', 'moveb', 'moreb', 'printb'];
            if (replytoall) {
                toShow.push('replytoallb');
            }
        } else if (this.Y.Array.indexOf(labels, 'draft') >= 0) {
            toShow = ['deleteb', 'editb', 'printb'];
        } else if (this.Y.Array.indexOf(labels, 'sent') >= 0) {
            toShow = ['deleteb', 'replyb', 'forwardb', 'printb'];
            if (replytoall) {
                toShow.push('replytoallb');
            }
        } else if (this.Y.Array.indexOf(labels, 'toapprove') >= 0) {
            toShow = ['approveb'];
        } else {
            toShow = ['deleteb', 'replyb', 'forwardb', 'moveb', 'moreb', 'printb'];
            if (replytoall) {
                toShow.push('replytoallb');
            }
        }
        
        for (var el in toShow) {
            var button = this.Y.one("#"+toShow[el]);
            if (button) {
                button.setStyle('display', 'inline');
            }
        }
    }
    this.Y.one('#jmailtoolbar').setStyle('visibility','visible');
}

M.block_jmail.confirmDialog = function(msg, callBack) {     
	var dialog = new YAHOO.widget.SimpleDialog("simpledialog", 
			 { width: "300px",
			   fixedcenter: true,
			   visible: false,
			   draggable: false,
			   close: true,
			   text: msg,
			   icon: YAHOO.widget.SimpleDialog.ICON_HELP,
			   constraintoviewport: true,
			   buttons: [ { text:"Yes", handler: function() { this.hide(); callBack();} , isDefault:true },
						  { text:"No",  handler: function(){ this.hide(); } } ]
			 } );    
    dialog.render("jmailcenter");    
    dialog.show();
}

M.block_jmail.showMessage = function(msg, timeHide) {
    var messagePanel = new this.Y.Panel({
    bodyContent: msg,
    width      : 400,
    zIndex     : 6,
    centered   : true,
    modal      : true,
    render     : '#messagepanel',
    buttons: [
        {
            value  : M.str.moodle.ok,
            section: this.Y.WidgetStdMod.FOOTER,
            action : function (e) {
                e.preventDefault();
                messagePanel.hide();
                messagePanel.destroy()
            }
        }
    ]
    });
    if (typeof timeHide == 'undefined') {
        timeHide = 2000;
    }
    setTimeout(function(){ messagePanel.hide(); messagePanel.destroy();}, timeHide);
}

M.block_jmail.logInfo = function(msg) {
    
    if (typeof console != "undefined") {
        console.log(msg);
    }    
    M.block_jmail.logdiv.set('innerHTML', msg + "<br/>" + M.block_jmail.logdiv.get('innerHTML'));
}