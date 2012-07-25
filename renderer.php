<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.


/**
 * Block renderer
 *
 * @package    blocks
 * @subpackage jmail
 * @copyright  2011 Juan Leyva <juanleyvadelgado@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

class block_jmail_renderer extends plugin_renderer_base {
    
    /**
     * Returns a plugin icon
     * @param string $icon Icon name
     * @return string
     */
    function plugin_icon($icon) {
        return '<img src="'.$this->output->pix_url($icon, 'block_jmail') . '" class="icon" alt="" />&nbsp;';        
    }
    
    /**
     * Link to the global inbox     
     * @return moodle_url
     */
    function global_inbox() {
        $url = new moodle_url('/blocks/jmail/mailbox.php', array('id'=>SITEID));
        $inboxname = get_string('viewglobalinbox', 'block_jmail');        
        return html_writer::link($url, $inboxname, array('title'=>$inboxname));
        return '';
    }
    
    /**
     * Returns the course inbox
     * @param object $course Course object
     * @return moodle_url
     */
    function course_inbox($course) {
        $url = new moodle_url('/blocks/jmail/mailbox.php', array('id'=>$course->id));
        $str = get_string('viewinbox', 'block_jmail');        
        return html_writer::link($url, $str, array('title'=>$str));
    }
    
    /**
     * Prints the link to the inbox and the unread messages
     * @param block_jmail_mailbox $mailbox Mailbox object
     * @return moodle_url
     */
    function unread_messages($mailbox) {
        $url = new moodle_url('/blocks/jmail/mailbox.php', array('id'=>$mailbox->course->id));
        $coursename = format_string($mailbox->course->shortname);
        return html_writer::link($url, "$coursename (".get_string('unreadmessages', 'block_jmail', $mailbox->unreadcount).")", array('title'=>$coursename));
    }
    
    /**
     * Load the html of the message print window
     * @param block_jmail_message $message A message object
     * @return string The html for the print window
     */
    function message_print($message) {
        $message = $message->full();
        
        $destname = array('to' => get_string('to', 'block_jmail'),
                          'cc' => get_string('cc', 'block_jmail'),
                          'bcc' => get_string('bcc', 'block_jmail'));
        
        $output = html_writer::start_tag('html');
        $output .= html_writer::start_tag('head');
        $output .= html_writer::tag('script', 'function Print(){document.body.offsetHeight;window.print()};');
        $output .= html_writer::end_tag('head');
        $output .= html_writer::start_tag('body', array('onload'=>'Print();'));
        $output .= $this->output->heading($message->subject, 2);
        $output .= $this->output->heading(get_string('from', 'block_jmail').': '.$message->from, 3);
        $output .= $this->output->heading($message->date, 4);
        
        if ($message->destinataries) {
            foreach ($message->destinataries as $type => $destinataries) {                
                foreach ($destinataries as $dest) {
                    $output .= $this->output->heading($destname[$type].': '.$dest->fullname, 5);
                }
            }
        }
        
        $output .= html_writer::tag('p', $message->body);
        $output .= html_writer::end_tag('body');
        $output .= html_writer::end_tag('html');
        
        return $output;
    }

    /**
     * Loads the user interface
     */
    function load_ui($mailbox) {
        global $CFG;
        
        $mystrings = array('subject','check','new'=>'newmail','inbox', 'drafts' => 'draft',
                           'sent'=>'sent', 'bin','toapprove','approve','delete','mymailboxes',
                           'reply','move','forward','print','addlabel','to','cc','bcc',
                           'send','save','more','preferences', 'markread', 'markunread', 'addlabel',
                           'replytoall' => 'replyall', 'receivecopies' , 'subscription', 'none', 'selected'
                           );
        
        foreach ($mystrings as $key=>$value) {
            $varname = 'str';
            $varname .= is_numeric($key)? $value : $key;
            $$varname = get_string($value, 'block_jmail');
        }
        
        $strfirstname = get_string('firstname');
        $strlastname = get_string('lastname');
        $strroles = get_string('roles');
        $strgroups = get_string('groups');
        $strall = get_string('all');
        $strname = get_string('name');
        $stredit = get_string('edit');
        
        $cansend = $mailbox->cansend;
        
        $loadingicon = $this->plugin_icon('loading');
        
        // TODO - Making it works for AJAX added new labels
        $optionslabels = '';
        if ($labels = $mailbox->get_labels()) {
            foreach ($labels as $l) {
                $optionslabels .= '<option value="'.$l->id.'">'.$l->name.'</option>';
            }
        }
        
        $alphabetfilter = '<a href="#" class="alphabetreset">'.$strall.'</a> ';        
        $alphabet = explode(',', get_string('alphabet', 'langconfig'));
        
        list($groups, $roles) = $mailbox->get_groups_roles();
        
        $groupsselect = '';
        $rolesselect = '';
        
        if (count($groups) > 0) {            
            $selector = html_writer::select($groups, 'groupselector', '', array(''=>'choosedots'), array('id'=>'groupselector'));
            $groupsselect = '<input type="submit" id="groupselectorb" name="groupselectorb" value="'.$strgroups.'">'.$selector;
        }
        
        if (count($roles) > 1) {
            $selector = html_writer::select($roles, 'rolesselector', '', null, array('id'=>'rolesselector'));
            $rolesselect = '<input type="submit" id="rolesselectorb" name="rolesselectorb" value="'.$strroles.'">'.$selector;
        }
                
        foreach ($alphabet as $letter) {
            $alphabetfilter .= '<a href="#" class="alphabet">'.$letter.'</a> ';            
        }
        
        // Main toolbar html
        $toolbar = '<button type="button" id="deleteb" name="deleteb" value="'.$strdelete.'">'.$strdelete.'</button>';
        
        $toolbar .= '<button type="button" id="editb" name="editb" value="'.$stredit.'">'.$stredit.'</button>';
        
        if (!empty($mailbox->config->approvemode) and has_capability('block/jmail:approvemessages', $mailbox->blockcontext)) {
            $toolbar .= '<button type="button" id="approveb" name="approveb" value="'.$strapprove.'">'.$strapprove.'</button>';
        }
        
        if ($cansend) {
            $toolbar .= '<button type="button" id="replyb" name="replyb" value="'.$strreply.'">'.$strreply.'</button>';
            $toolbar .= '<button type="button" id="replytoallb" name="replytoallb" value="'.$strreplytoall.'">'.$strreplytoall.'</button>';
            $toolbar .= '<button type="button" id="forwardb" name="forwardb" value="'.$strforward.'">'.$strforward.'</button>';
        }
        
        $toolbar .= '<button type="button" id="moveb" name="moveb" value="'.$strmove.'">'.$strmove.'</button>';
        $toolbar .= '    <select id="labelsmenu" name="labelsmenu"> 
                                <option value="inbox">'.$strinbox.'</option>                                
                            </select> ';
        $toolbar .= '<button type="button" id="moreb" name="moreb" value="'.$strmore.'">'.$strmore.'</button>';
        $toolbar .= '    <select id="moremenu" name="moremenu"> 
                                <option value="markread">'.$strmarkread.'</option>
                                <option value="markunread">'.$strmarkunread.'</option>                                
                            </select> ';
        $toolbar .= '<button type="button" id="printb" name="printb" value="'.$strprint.'">'.$strprint.'</button>';                            

        // Action buttons html
        $actionbuttons = '<button type="button" id="checkmail" name="checkmail" value="'.$strcheck.'">'.$strcheck.'</button>';        
        if ($cansend) {
            $actionbuttons .= '<button type="button" id="newmail" name="newmail" value="'.$strnew.'">'.$strnew.'</button>';
        }
        
        // Contacts html
        $contacts = '';
        
        if ($cansend) {
            $contacts = '
                    <div id="contact_list_filter">
                        <div>'.$groupsselect.'</div>
                        <div>'.$rolesselect.'</div>
                        <b>'.$strfirstname.'</b>
                        <div id="firstnamefilter">'.$alphabetfilter.'</div>
                        <b>'.$strlastname.'</b>
                        <div id="lastnamefilter">'.$alphabetfilter.'</div>                        
                    </div>
                    <div id="contact_list">
                        <div id="contact_list_users">'.$loadingicon.'</div>
                        <div id="selbuttons" style="clear: both">
                            <input type="checkbox" id="selectall">'.$strselected.'
                            <input type="button" class="selto" value=" '.$strto.' ">&nbsp;
                            <input type="button" class="selcc" value=" '.$strcc.' ">&nbsp;
                            <input type="button" class="selbcc" value=" '.$strbcc.' ">
                        </div>
                    </div>
                    ';
        }

        $approvelabel = '';
        if (!empty($mailbox->config->approvemode) and has_capability('block/jmail:approvemessages', $mailbox->blockcontext)) {
            $approvelabel = '
            <li class="inbox">
                <em></em>
                <a href="#" id="toapprove">'.$strtoapprove.'</a>
            </li>';                            
        }
        
        // Preferences and labels
        
        $preferences = '';

        if ($mailbox->canmanagelabels) {
            $preferences .= '<p><img src="'.$this->output->pix_url('add', 'block_jmail').'"><a href="#" id="addlabel">&nbsp;&nbsp;'.$straddlabel.'</a></p>';
        }
        
        if ($mailbox->canmanagepreferences) {
            $preferences .= '<p><img src="'.$this->output->pix_url('settings', 'block_jmail').'"><a href="#" id="preferences">&nbsp;&nbsp;'.$strpreferences.'</a></p>';
        }
        
        // My mailboxes
        
        $mymailboxes = '';
        if ($mailboxes = $mailbox::get_my_mailboxes()) {
            $mymailboxes .= '<button type="button" id="mailboxesb" name="mailboxesb" value="'.$strmymailboxes.'">'.$strmymailboxes.'</button>';
            $mymailboxes .= '<select id="mailboxesmenu" name="mailboxesmenu">';
            $counter = 0;
            foreach ($mailboxes as $box) {
                if ($box->id == $mailbox->course->id) {
                    continue;
                }
                $counter++;
                // Apply some pad to the menu elements, we fill with white spaces.
                $boxname = str_replace(' ', "&nbsp;", str_pad(format_string($box->shortname), strlen($strmymailboxes) * 2));
                $mymailboxes .= '<option value="'.$box->id.'">'.$boxname.'</option>';
            }
            $mymailboxes .= '</select> ';
            if (!$counter) {
                $mymailboxes = '';
            }
        }
        
        
        // Tinymce editor
        $editor = editors_get_preferred_editor(FORMAT_HTML);
        $editor->use_editor('body');

        return '
    
    <script type="text/javascript">
//<![CDATA[
M.yui.add_module({"editor_tinymce":{"name":"editor_tinymce","fullpath":"'.$CFG->wwwroot.'/lib\/editor\/tinymce\/module.js","requires":[]},"form_filemanager":{"name":"form_filemanager","fullpath":"'.$CFG->wwwroot.'/lib\/form\/filemanager.js","requires":["core_filepicker","base","io","node","json","yui2-button","yui2-container","yui2-layout","yui2-menu","yui2-treeview"]}});

//]]>
</script>
    
            <div id="jmailui">
                <div id="jmailleft">
                    <div id="action_buttons">                        
                        '.$actionbuttons.'
                    </div>
                    <div id="search_bar">
                        <input type="text" name="search" id="input_search"><span id="search_button"></span>
                    </div>
                    <div id="label_list">
                        <ul>
                            <li class="inbox">
                                <em></em>
                                <a href="#" id="inbox">'.$strinbox.'</a>
                            </li>
                            <li class="draft">
                                <em></em>
                                <a href="#" id="draft">'.$strdrafts.'</a>
                            </li>
                            <li class="sent">
                                <em></em>
                                <a href="#" id="sent">'.$strsent.'</a>
                            </li>
                            <li class="trash">
                                <em></em>
                                <a href="#" id="trash">'.$strbin.'</a>
                            </li>
                            '.$approvelabel.'
                        </ul>
                        <div id="user_labels">
                        '.$loadingicon.'
                        </div>
                        <div id="menulabel">
                        </div>
                        
                        '.$preferences.'
                        
                        <div id="mymailboxes">
                        '.$mymailboxes.'
                        </div>
                        
                        <div id="loginfo" style="overflow: auto; width: 100%; height: 200px; border: solid 1px red; display: none">
                        </div>
                    </div>
                </div>
                <div id="jmailcenter">
                    <div id="mailarea">
                        <div id="jmailtoolbar">
                            '.$toolbar.'
                        </div>
                        <div id="maillist">                                        
                        </div>                                    
                    </div>
                    <div id="mailcontents">                                
                    </div>                                                   
                </div>
                <div id="jmailright">
                    '.$contacts.'
                </div>
            </div>
            <div id="messagepanel"></div>
            <div id="newemailpanel" style="display: none">
                <div class="hd">'.$strnew.'</div>
                <div id="newemailform" class="bd mform">                                     
                    <div class="fitem">
                        <div class="fitemtitle">
                            <label for="composetoac">'.$strto.'</label>
                        </div>
                        <div class="felement ftext">
                            <input type="text" name="composetoac" id="composetoac" value=""  size="50">
                            <div id="composetolist"></div>
                        </div>
                    </div>                            
                    <div class="fitem">
                        <div class="fitemtitle">
                            <label for="composeccac">'.$strcc.'</label>
                        </div>
                        <div class="felement ftext">
                            <input type="text" name="composeccac" id="composeccac" value=""  size="50">
                            <div id="composecclist"></div>
                        </div>
                    </div>
                    <div class="fitem">
                        <div class="fitemtitle">
                            <label for="composebccac">'.$strbcc.'</label><br>                            
                        </div>
                        <div class="felement ftext">
                            <input type="text" name="composebccac" id="composebccac" value=""  size="50">
                            <div id="composebcclist"></div>
                        </div>
                    </div>
                    <div class="fitem">
                        <div class="fitemtitle">
                            <label for="subject">'.$strsubject.'</label><br>                            
                        </div>
                        <div class="felement ftext">
                            <input type="text" name="subject" id="subject" value=""  size="50">                                    
                        </div>
                    </div>
    
                    <div id="newemailformremote"></div>
                    
                    <div class="fitem">
                        <div class="fitemtitle">                                
                        </div>
                        <div class="felement ftext">
                            <input type="button" name="sendbutton" id="sendbutton" value="'.$strsend.'">
                            <input type="button" name="savebutton" id="savebutton" value="'.$strsave.'">
                        </div>
                    </div>
                    
                    <input type="hidden" name="to" id="hiddento">
                    <input type="hidden" name="cc" id="hiddencc">
                    <input type="hidden" name="bcc" id="hiddenbcc">
                </div>
                <div class="ft"></div>
            </div>
            <div id="newlabelpanel" style="display: none">
                <div class="yui3-widget-bd">
                    <form>
                        <fieldset>
                            <p>
                                <label for="id">'.$strname.'</label><br/>
                                <input type="text" name="newlabelname" id="newlabelname" placeholder="" maxlength="16">
                            </p>
                        </fieldset>
                    </form>
                </div>
            </div>
            <div id="preferencespanel" style="display: none">
                <div id="panelContent">
                    <div class="yui3-widget-bd">
                        <form>
                            <fieldset>
                                <p>
                                    <label for="subscription">'.$strsubscription.'</label><br/>
                                    <select name="subscription" id="subscription">
                                        <option value="">'.$strnone.'</option>
                                        <option value="receivecopies">'.$strreceivecopies.'</option>
                                    </select>
                                </p>                               
                            </fieldset>
                        </form>
                    </div>
                </div>
            </div>
            <div id="rendertarget"></div>
        ';
    }

}
