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
        $url = new moodle_url('/blocks/jmail/mailbox.php');
        $inboxname = get_string('view_all', 'block_jmail');        
        return html_writer::link($url, $inboxname, array('title'=>$inboxname));
    }
    
    /**
     * Returns the course inbox
     * @param object $course Course object
     * @return moodle_url
     */
    function course_inbox($course) {
        $url = new moodle_url('/blocks/jmail/mailbox.php', array('id'=>$course->id));
        $str = get_string('view_inbox', 'block_jmail');        
        return html_writer::link($url, $str, array('title'=>$str));
    }
    
    /**
     * Prints the link to the inbox and the unread messages
     * @param block_jmail_mailbox $mailbox Mailbox object
     * @return moodle_url
     */
    function unread_messages($mailbox) {
        $url = new moodle_url('/blocks/jmail/mailbox.php', array('id'=>$mailbox->course->id));
        $coursename = format_string($mailbox->course->fullname);
        return html_writer::link($url, "$coursename ({$mailbox->unreadcount})", array('title'=>$coursename));
    }

    /**
     * Loads the user interface
     */
    function load_ui($mailbox) {
        $strcheck = get_string('check', 'block_jmail');
        $strnew = get_string('newmail', 'block_jmail');
        $strinbox = get_string('inbox', 'block_jmail');
        $strdrafts = get_string('draft', 'block_jmail');
        $strsent = get_string('sendbox', 'block_jmail');
        $strtrash = get_string('trash', 'block_jmail');
        $strdelete = get_string('removemail', 'block_jmail');
        $strreply = get_string('reply', 'block_jmail');
        $strmove = get_string('move', 'block_jmail');
        $strforward = get_string('forward', 'block_jmail');
        $strprint = get_string('print', 'block_jmail');
        $straddalabel = get_string('addlabel', 'block_jmail');
        $strfirstname = get_string('firstname');
        $strlastname = get_string('lastname');
        $strroles = get_string('roles');
        $strgroups = get_string('groups');
        $strall = get_string('all');
        $strname = get_string('name');
        $strto = get_string('for','block_jmail');
        $strcc = get_string('cc','block_jmail');
        $strbcc = get_string('bcc','block_jmail');
        
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
            $groupsselect = '<input type="submit" id="groupselectorb" name="groupselectorb" value="'.$strroles.'">'.$selector;
        }
        
        if (count($roles) > 1) {
            $selector = html_writer::select($roles, 'rolesselector', '', null, array('id'=>'rolesselector'));
            $rolesselect = '<input type="submit" id="rolesselectorb" name="rolesselectorb" value="'.$strroles.'">'.$selector;
        }
                
        foreach ($alphabet as $letter) {
            $alphabetfilter .= '<a href="#" class="alphabet">'.$letter.'</a> ';            
        }
        
        return '
            <div id="jmailui">
                <div id="jmailleft">
                    <div id="action_buttons">                        
                        <button type="button" id="checkmail" name="checkmail" value="'.$strcheck.'">'.$strcheck.'</button>
                        <button type="button" id="newmail" name="newmail" value="'.$strnew.'">'.$strnew.'</button>
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
                                <a href="#" id="trash">'.$strtrash.'</a>
                            </li>
                        </ul>
                        <div id="user_labels">
                        '.$loadingicon.'
                        </div>
                        <div id="menulabel">
                        </div>
                        <img src="'.$this->output->pix_url('add', 'block_jmail').'"><a href="#" id="addlabel">&nbsp;&nbsp;'.$straddalabel.'</a>
                    </div>
                </div>
                <div id="jmailcenter">
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
                            
                            <input type="hidden" name="to" id="hiddento">
                            <input type="hidden" name="cc" id="hiddencc">
                            <input type="hidden" name="bcc" id="hiddenbcc">
                        </div>
                        <div class="ft"></div>
                    </div>
                    <div id="newlabelpanel">
                        <div class="yui3-widget-bd">
                            <form>
                                <fieldset>
                                    <p>
                                        <label for="id">'.$strname.'</label><br/>
                                        <input type="text" name="newlabelname" id="newlabelname" placeholder="">
                                    </p>
                                </fieldset>
                            </form>
                        </div>
                    </div>
                    <div id="mailarea">
                        <div id="jmailtoolbar">
                            <button type="button" id="deleteb" name="deleteb" value="'.$strdelete.'">'.$strdelete.'</button>
                            <button type="button" id="replyb" name="replyb" value="'.$strreply.'">'.$strreply.'</button>
                            <button type="button" id="forwardb" name="forwardb" value="'.$strforward.'">'.$strforward.'</button>
                            <button type="button" id="moveb" name="moveb" value="'.$strmove.'">'.$strmove.'</button>
                            <select id="labelsmenu" name="labelsmenu"> 
                                <option value="trash">'.$strtrash.'</option>
                                '.$optionslabels.'
                            </select> 
                            <button type="button" id="printb" name="printb" value="'.$strprint.'">'.$strprint.'</button>
                        </div>
                        <div id="maillist">                                        
                        </div>                                    
                    </div>
                    <div id="mailcontents">                                
                    </div>                                                   
                </div>
                <div id="jmailright">
                    <div id="contact_list_filter">
                        <div>'.$groupsselect.'</div>
                        <div>'.$rolesselect.'</div>
                        <b>'.$strfirstname.'</b>
                        <div id="firstnamefilter">'.$alphabetfilter.'</div>
                        <b>'.$strlastname.'</b>
                        <div id="lastnamefilter">'.$alphabetfilter.'</div>                        
                    </div>
                    <div id="contact_list_users">'.$loadingicon.'</div>
                </div>
            </div>
            
            <div id="demo" class="yui3-skin-sam">
  <label for="ac-input">Enter a GitHub username:</label><br>
  <input id="ac-input" type="text">
</div>

<script>
YUI().use(\'autocomplete\', \'autocomplete-highlighters\', function (Y) {
  Y.one(\'#ac-input\').plug(Y.Plugin.AutoComplete, {
    resultHighlighter: \'phraseMatch\',
    resultListLocator: \'users\',
    resultTextLocator: \'username\',
    source: \'http://github.com/api/v2/json/user/search/{query}?callback={callback}\'
  });
});
</script>
            
            
        ';
    }

}
