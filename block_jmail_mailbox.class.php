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
 * Mailbox class
 * Manages a course mailbox
 *
 * @package    blocks
 * @subpackage jmail
 * @copyright  2011 Juan Leyva <juanleyvadelgado@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once($CFG->dirroot.'/blocks/jmail/block_jmail_message.class.php');

class block_jmail_exception extends moodle_exception {
    /**
     * Constructor
     * @param string $errorcode The name of the string from error.php to print
     * @param string $module name of module
     * @param string $link The url where the user will be prompted to continue. If no url is provided the user will be directed to the site index page.
     * @param object $a Extra words and phrases that might be required in the error string
     * @param string $debuginfo optional debugging information
     */
    public function __construct($errorcode, $link = '', $a = NULL, $debuginfo = null) {
        parent::__construct($errorcode, 'block_jmail', $link, $a, $debuginfo);
    }
}

class block_jmail_mailbox {

    /** @var object Full course */
    public $course = null;
    
    /** @var object Full course */
    public $context = null;
    public $blockcontext = null;
    
    public $cansend = false;
    public $cansendtoall = false;
    public $cansendtomanagers = false;
    
    public $canmanagelabels = false;
    
    /** @var integer Current user unreaded messages in this mailbox */
    public $unreadcount = 0;
    
    /** @var integer Number of emails per page */
    public $pagesize = 20;
    
    public $config;
    
    public $instance;
    
    /**
     * Class constructor
     * @param object $course Full course object
     */    
    function __construct($course, $context = null, $blockcontext = null) {
        global $SESSION, $DB;
        
        // TODO - Do no instanciate mailboxes for courses without the block installed
        $this->course = $course;
        
        if (!$context) {
            $this->context = get_context_instance(CONTEXT_COURSE, $this->course->id);
        } else {
            $this->context = $context;
        }
        
        if (!$blockcontext) {
            if ($instance = $DB->get_record('block_instances', array('blockname'=>'jmail', 'parentcontextid'=>$this->context->id))) {
                $this->blockcontext = get_context_instance(CONTEXT_BLOCK, $instance->id);
            }
        } else {
            $this->blockcontext = $blockcontext;
        }       
        
        $this->instance = $DB->get_record('block_instances', array('blockname'=>'jmail', 'parentcontextid'=>$this->context->id));
        $this->config = unserialize(base64_decode($this->instance->configdata));
        
        $this->cansendtomanagers = has_capability('block/jmail:sendtomanagers', $this->blockcontext);
        $this->cansendtoall = has_capability('block/jmail:sendtoall', $this->blockcontext);
        
        $this->cansend = $this->cansendtomanagers or $this->cansendtoall;
        
        $this->canmanagelabels = has_capability('block/jmail:managelabels', $this->blockcontext);
        $this->canmanagepreferences = has_capability('block/jmail:managepreferences', $this->blockcontext);
        
        if (! isset($SESSION->jmailcache)) {
            $SESSION->jmailcache = new stdClass;
            $SESSION->jmailcache->contacts = array();
            $SESSION->jmailcache->contacts_search = array();
        }
    }
    
    /**
     * Count unread messages for the current user
     * @return integer Number of unread messages for the current user
     */  
    public function count_unread_messages() {
        global $DB, $USER;

        $sql = "SELECT COUNT('x') FROM {block_jmail} j, {block_jmail_sent} s WHERE j.id = s.messageid AND j.timesent > 0 AND s.userid = :userid AND j.courseid = :course AND s.mread = 0";        
        $this->unreadcount = (int) $DB->count_records_sql($sql, array('userid' =>$USER->id ,'course' => $this->course->id));
        
        return $this->unreadcount;
        
    }
    
    /**
     * Get current user messages headers (subject, remitent, date)
     * @param string $filter Messages filter, page, label, dates...
     * @return array Array in the first element the total messages numbert, second element an array of messages objects
     */  
    public function get_message_headers($label, $start, $sort, $direction, $searchtext) {
        global $CFG, $DB, $USER, $SESSION;

        $messagesdata = array(0, array());
        $dbmessages = array();
        
        if (!$label) {
            $label = 'inbox';
        }
        
        if ($label == 'search' and !$searchtext) {
            $label = 'inbox';
        }
        
        switch($sort) {
            case 'subject': $sort = 'subject';
                            break;
            case 'from':
                            $sort = 'firstname';
                            if (!empty($SESSION->fullnamedisplay)) {
                                $CFG->fullnamedisplay = $SESSION->fullnamedisplay;
                            }                        
                            if (!isset($CFG->fullnamedisplay) or $CFG->fullnamedisplay === 'firstname lastname') {
                                $sort = 'firstname';
                        
                            } else if ($CFG->fullnamedisplay == 'lastname firstname') {
                                $sort = 'lastname';
                        
                            } else if ($CFG->fullnamedisplay == 'firstname') {
                                $sort = 'firstname';
                            }                
                            break;
            case 'date':    if ($label == 'draft') {
                                $sort = 'm.timecreated';
                            } else {
                                $sort = 'm.timesent';
                            }
                            break;
            default:        $sort = 'timesent';
        }

        
        $select = "SELECT m.id, m.subject, m.timesent, m.timecreated, m.sender";
        $params = array('userid'=>$USER->id, 'sent'=>0, 'course'=>$this->course->id, 'deleted' => 0, 'approved' => 1, 'userdeleted' => 0);
        
        switch ($label) {
            case 'search':
                        $select .= ', s.mread';
                        $params['sender'] = $USER->id;
                        unset($params['deleted']);
                        $params['searchtext1'] = "%$searchtext%";
                        $params['searchtext2'] = "%$searchtext%";
                        $params['searchtext3'] = "%$searchtext%";
                        
                        $fullname = $DB->sql_fullname('u.firstname','u.lastname');
                        $sql = "
                            FROM {block_jmail} m
                            JOIN {block_jmail_sent} s ON m.id = s.messageid
                            JOIN {user} u ON u.id = m.sender
                            WHERE
                            ((m.timesent > :sent AND s.userid = :userid AND m.approved = :approved) OR (m.sender = :sender))
                            AND m.courseid = :course                            
                            AND u.deleted = :userdeleted
                            AND (". $DB->sql_like('subject', ':searchtext1', false, false)."
                            OR ". $DB->sql_like('body', ':searchtext2', false, false)."                            
                            OR ". $DB->sql_like($fullname, ':searchtext3', false, false)."
                            )
                            ORDER BY $sort $direction";                            
                        break;
                        
            case 'inbox':
                        $select .= ', s.mread';
                        $params['labeled'] = 0;                                               
                        $sql = "
                            FROM {block_jmail} m
                            JOIN {block_jmail_sent} s ON m.id = s.messageid
                            JOIN {user} u ON u.id = m.sender
                            WHERE
                            m.timesent > :sent AND s.userid = :userid AND m.courseid = :course
                            AND m.approved = :approved
                            AND s.deleted = :deleted AND s.labeled = :labeled
                            AND u.deleted = :userdeleted
                            ORDER BY $sort $direction";                            
                        break;
            case 'draft' :
                        $params = array('sender' => $USER->id, 'course' => $this->course->id, 'timesent' => 0, 'userdeleted' => 0);                            
                        $sql = "
                            FROM {block_jmail} m
                            JOIN {user} u ON u.id = m.sender
                            WHERE
                            timesent = :timesent AND sender = :sender AND courseid = :course
                            AND u.deleted = :userdeleted
                            ORDER BY $sort $direction";
                        break;
            case 'sent' :
                        $params = array('sender' => $USER->id, 'course' => $this->course->id, 'timesent' => 0, 'userdeleted' => 0);
                        $sql = "
                            FROM {block_jmail} m
                            JOIN {user} u ON u.id = m.sender
                            WHERE
                            sender = :sender AND courseid = :course AND timesent > :timesent
                            AND u.deleted = :userdeleted
                            ORDER BY $sort $direction";                          
                        break;
            case 'trash' :
                        $select .= ', s.mread';
                        $params['deleted'] = 1;
                        $sql = "
                            FROM {block_jmail} m
                            JOIN {block_jmail_sent} s ON m.id = s.id
                            JOIN {user} u ON u.id = m.sender
                            WHERE
                            m.timesent > :sent AND s.userid = :userid AND m.courseid = :course AND s.deleted = :deleted
                            AND m.approved = :approved
                            AND u.deleted = :userdeleted
                            ORDER BY $sort $direction";
                        
                        break;
            case 'toapprove' :
                        if (!has_capability('block/jmail:approvemessages', $this->blockcontext)) {
                            return $messagesdata;
                        }
                        $params = array('approved' => 0, 'course' => $this->course->id, 'timesent' => 0, 'userdeleted' => 0, 'deleted' => 0);
                        $sql = "
                            FROM {block_jmail} m
                            JOIN {block_jmail_sent} s ON m.id = s.id
                            JOIN {user} u ON u.id = m.sender
                            WHERE
                            m.timesent > :timesent AND m.courseid = :course AND s.deleted = :deleted
                            AND m.approved = :approved
                            AND u.deleted = :userdeleted
                            ORDER BY $sort $direction";
                        
                        break;                    
            default:
                        $select .= ', s.mread';
                        $params ['label'] = $label;                            
                        $sql = "
                            FROM {block_jmail} m
                            JOIN {block_jmail_sent} s ON m.id = s.messageid
                            JOIN {block_jmail_m_label} l ON s.id = l.messagesentid
                            JOIN {user} u ON u.id = m.sender
                            WHERE
                            m.timesent > :sent AND s.userid = :userid AND m.courseid = :course AND s.deleted = :deleted
                            AND m.approved = :approved
                            AND l.labelid = :label
                            AND u.deleted = :userdeleted
                            ORDER BY $sort $direction";

        }
        $dbmessages = $DB->get_records_sql($select.$sql, $params, $start, $this->pagesize);
        $messagesdata[0] = $DB->count_records_sql("SELECT COUNT('x')".$sql, $params);

        if ($dbmessages) {
            foreach ($dbmessages as $m) {
                $message = new block_jmail_message($m);
                $messages[] = $message->headers();
            }
            $messagesdata[1] = $messages;
        }

        return $messagesdata;
    }
    
    /**
     * Saves a message
     *     
     * @return bool True if all ok
     */  
    public function save_message($id, $to, $cc, $bcc, $subject, $body, $timesent, $attachments, $editoritemid) {
        global $USER;

        if (! $this->cansend) {
            return false;
        }

        $destinataries = array();
        // We mix all the users
        $users = array('to' => $to, 'cc' => $cc, 'bcc' => $bcc);

        foreach ($users as $type => $to) {
            if ($to) {
                foreach (explode(',', $to) as $userid) {
                    $d = new stdClass;
                    $d->type = $type;
                    $d->userid = $userid;
                    $destinataries[] = $d;
                }
            }
        }

        if ($id and $message = block_jmail_message::get_from_id($id)) {
            $message->subject = $subject;
            $message->body = $body;
            $message->timesent = $timesent;            
            $message->update($destinataries, $attachments, $editoritemid);
            
            add_to_log($this->course->id, 'jmail', 'message updated');
            $this->send_copy($message);
        } else {

            $message = new stdClass;
            $message->subject = $subject;
            $message->body = $body;
            $message->timesent = $timesent;
            $message->timecreated = time();
            $message->courseid = $this->course->id;

            $message->approved = 1;
            if (!empty($this->config->approvemode)) {
                if (! has_capability('block/jmail:approvemessages', $this->blockcontext)) {
                    $message->approved = 0;
                }
            }

            $message = new block_jmail_message($message);
            $message->save($destinataries, $attachments, $editoritemid);
            
            add_to_log($this->course->id, 'jmail', 'message saved');
            $this->send_copy($message);
        }
        return true;
    }
    
    /**
     * Send a copy to the user email according to the user's preferences
     * @param block_jmail_message $message A full message object
     * @return boolean True if the message have been sent succesfully
     */ 
    private function send_copy($message) {
        global $DB;
        
        $mailresult = true;        
        $site = get_site();
        
        // We ensure that is a message sent and approved
        if (!$message->timesent or !$message->approved) {
            return false;
        }
        
        add_to_log($this->course->id, 'jmail', 'message sent');
        
        $message = $message->full();
        
        if ($message->destinataries) {
            foreach ($message->destinataries as $type => $destinataries) {                
                foreach ($destinataries as $dest) {
                    $userprefs = $this->load_user_preferences($dest->userid);
                    if ($userprefs->receivecopies and $userto = $DB->get_record('user', array('id' => $dest->userid))) {
                        $bodytext = format_text_email($message->body, FORMAT_HTML);
                        $mailresult = email_to_user($userto, $site->shortname, $message->subject, $bodytext, '', '', '', true, '');
                    }
                }
            }
        }
        return $mailresult;
    }
    
    /**
     * Gets the current user preferences
      @return object User preferences
     */
    public function get_preferences() {
        global $USER;
        
        if (!$this->canmanagepreferences) {
            return false;
        }
        
        return $this->load_user_preferences($USER->id);
    }
    
    /**
     * Sets the current user preferences
     * @param string $preferences JSON string of the current preferences
      @return bool True if all ok
     */
    public function save_preferences($preferences) {
        global $USER, $DB;
        
        if (!$this->canmanagepreferences) {
            return false;
        }
        
        if ($preferences) {
            $preferences = json_decode($preferences);
            if (is_object($preferences)) {                
                foreach ($preferences as $key=>$val) {
                    if ($pref = $DB->get_record('block_jmail_preferences', array('userid' => $USER->id, 'courseid' => $this->course->id, 'name' => $key))) {
                        $pref->value = $val;
                        $DB->update_record('block_jmail_preferences', $pref);
                    }
                    else {
                        $pref = new stdClass;
                        $pref->userid = $USER->id;
                        $pref->courseid = $this->course->id;
                        $pref->name = $key;
                        $pref->value = $val;
                        $DB->insert_record('block_jmail_preferences', $pref);
                    }
                }
            }
        }
        
        return true;
    }    
    
    /**
     * Load the preferences for a user in the current mailbox
     * @param int $userid An user id
     * @return object User preferences
     */ 
    private function load_user_preferences($userid) {
        global $DB;
                
        $preferences = new stdClass;
        $preferences->receivecopies = false;
        
        if ($prefs = $DB->get_records('block_jmail_preferences', array('userid' => $userid, 'courseid' => $this->course->id))) {
            foreach ($prefs as $pref) {
                $prefname = $pref->name;
                $preferences->$prefname = $pref->value;
            }
        }
        
        return $preferences;
    }
    
    /**
     * Get a message for an user
     *
     * @param int $messageid Database message sent id (jmail_sent table)
     * @return object Object containing the full message
     */  
    public function get_message($messageid) {
        global $USER;

        $message = block_jmail_message::get_from_id($messageid);
        $received = $message->userid == $USER->id and $message->timesent > 0;
        $send = $message->sender == $USER->id;
        if ($received or $send) {
            return $message->full();            
        }
        return false;
    }
    
    /**
     * Get a sent or draft message for an user (jmail table)
     *
     * @param int $messageid Database message id
     * @return object Object containing the full message
     */  
    public function get_message_sent($messageid) {
        global $USER;

        $message = block_jmail_message::get_from_id($messageid);
        if ($message->sender == $USER->id) {
            return $message->full();
        }
        return false;
    } 
    
    /**
     * Delete a message for an user
     *
     * @param int $messageid Database message id
     * @return array Array of label objects
     */  
    public function delete_message($messageid) {
        global $DB, $USER;

        $message = block_jmail_message::get_from_id($messageid);
        if ($message and $message->userid == $USER->id) {            
            return $message->delete();            
        }
        return false;
    }
    
    /**
     * Approve a message for an user
     *
     * @param int $messageid Database message id
     * @return bool True if all ok
     */  
    public function approve_message($messageid) {
        global $DB, $USER;

        $message = block_jmail_message::get_from_id($messageid);
        if (!empty($this->config->approvemode) and has_capability('block/jmail:approvemessages', $this->blockcontext)) {
            return $message->approve();
        }
        return false;
    }    

    /**
     * Return the current user labels for this mailbox
     * There are cross-mailboxes labels (the field course it's 0 for this labels)
     * 
     * @return array Array of label objects
     */  
    public function get_labels() {
        global $DB, $USER;

        $params = array('userid' => $USER->id, 'course' => $this->course->id);
        return $DB->get_records_select('block_jmail_label', 'userid = :userid AND (courseid = 0 OR courseid = :course)', $params);

    }

    /**
     * Delete a label for an user
     *
     * @param int $labelid Database label id
     * @return array Array of label objects
     */  
    public function delete_label($labelid) {
        global $DB, $USER;

        if (!$this->canmanagelabels) {
            return false;
        }

        if ($DB->delete_records('block_jmail_label', array('id' => $labelid, 'userid' => $USER->id))) {
            $DB->delete_records('block_jmail_m_label', array('label' => $labelid));
            return true;
        }
        return false;
    }
    
    /**
     * Rename a label for an user
     *
     * @param int $labelid Database label id
     * @param string $name Labels name
     * @return bool True if all ok
     */  
    public function rename_label($labelid, $name) {
        global $DB, $USER;

        if (!$this->canmanagelabels) {
            return false;
        }

        if ($label = $DB->get_record('block_jmail_label', array('id' => $labelid, 'userid' => $USER->id))) {
            $label->name = $name;
            $DB->update_record('block_jmail_label');
            return true;
        }
        return false;
    }
    
    /**
     * Create a label for an user
     *
     * @param string $name Labels name
     * @param bool $global When the label is global or for a mailbox
     * @return misc False or label database id
     */  
    public function create_label($name, $global = false) {
        global $DB, $USER;
        
        if (!$this->canmanagelabels) {
            return false;
        }

        $label = new stdClass;
        $label->name = $name;
        $label->userid = $USER->id;
        $label->courseid = ($global == true) ? 0 : $this->course->id;
        $label->timecreated = time();


        if ($labelid = $DB->insert_record('block_jmail_label', $label)) {
            add_to_log($this->course->id, 'jmail', 'label created');
            return $labelid;
        }
        return false;
    }
    
    /**
     * Links a message to a label
     *
     * @param int $messageid Message db id
     * @param string $labelid Label db id or name
     * @return bool True if all ok
     */  
    public function label_message($messageid, $labelid) {
        global $DB, $USER;
        
        if (!$this->canmanagelabels) {
            return false;
        }

        if ($message = block_jmail_message::get_from_id($messageid)) {                        
            
            if (is_numeric($labelid) and $message->is_mine() and $DB->get_record('block_jmail_label', array('id'=>$labelid, 'userid'=>$USER->id))) {                
                $labelm = new stdClass;
                $labelm->labelid = $labelid;
                $labelm->messagesentid = $message->sentid;
                $labelm->timecreated = time();
                if ($DB->insert_record('block_jmail_m_label', $labelm)) {
                    $message->mark_labeled();                    
                    return true;
                }
            } else {
                if ($labelid == 'inbox') {                    
                    $this->unlabel_message_full($messageid);                    
                    // If it was deleted, undelete it
                    $message->undelete();                    
                    return true;
                }
            }            
        }
        return false;
    }
    
    /**
     * Unlinks a message to a label
     *
     * @param int $messageid Message db id
     * @param int $labelid Label db id
     * @return bool True if all ok
     */  
    public function unlabel_message($messageid, $labelid) {
        global $DB, $USER;

        if (!$this->canmanagelabels) {
            return false;
        }

        if ($message = block_jmail_message::get_from_id($messageid)) {
            if (is_numeric($labelid) and $message->is_mine() and $DB->get_record('block_jmail_label', array('id'=>$labelid, 'userid'=>$USER->id))) {                
                if ($DB->delete_records('block_jmail_m_label', array('messagesentid'=>$message->sentid, 'labelid'=>$labelid))) {
                    $message->mark_unlabeled();
                    return true;
                }
            }
        }
        return false;
    }
    
    /**
     * Unlinks a message to all labels it's linked to
     *
     * @param int $messageid Message db id
     * @return bool True if all ok
     */  
    public function unlabel_message_full($messageid) {
        global $DB, $USER;

        if (!$this->canmanagelabels) {
            return false;
        }

        if ($message = block_jmail_message::get_from_id($messageid)) {
            if ($message->is_mine()) {
                if ($DB->delete_records('block_jmail_m_label', array('messagesentid'=>$message->sentid))) {
                    $message->mark_unlabeled();
                    return true;
                }
            }
        }
        return true;
    }
    
    /**
     * Mark a message as read or unread
     *
     * @param int $messageid Message db id
     * @param int $status Message read or unread
     * @return bool True if all ok
     */  
    public function mark_read($messageid, $status) {
        global $DB, $USER;

        if ($message = block_jmail_message::get_from_id($messageid)) {
            if ($message->is_mine()) {                
                if ($status) {
                    return $message->mark_read();
                } else {
                    return $message->mark_unread();
                }
            }
        }
        return false;
    }    
    
    
    /**
     * Send a draft message
     *
     * @param int $messageid Database message id
     * @return array Array of label objects
     */  
    public function send_draf($messageid) {
        global $DB, $USER;

        if (! $this->cansend) {
            return false;
        }

        $message = block_jmail_message::get_from_id($messageid);
        if ($message->sender == $USER->id) {            
            $message->mark_sent();
        }
        return false;
    }

    /**
     * Return a list of current user contacts
     * This function checks if the current user can send messages to all the users or only to managers
     *
     * @param int $group Group to filter
     * @param string $fi Firstname initial to filter
     * @param string $li Lastname initial to filter
     * @param int $roleid Role id to filter
     * @return array Array of contacts
     */     
    public function get_contacts($group, $fi, $li, $roleid) {
        global $DB, $OUTPUT, $SESSION;
        
        if (!$this->cansend) {
            return array();
        }
        
        // Cache (see refresh cache bellow)
        $hash = "-$group-$fi-$li-$roleid-";
        
        if (isset($SESSION->jmailcache->contacts[$this->course->id][$hash])) {
            return $SESSION->jmailcache->contacts[$this->course->id][$hash];
        }        
        
        if (! has_capability('moodle/course:viewparticipants', $this->context)) {
            return array();
        }
        
        $groupmode    = groups_get_course_groupmode($this->course);   // Groups are being used
        $currentgroup = groups_get_course_group($this->course, true);
    
        if (!$currentgroup) {      // To make some other functions work better later
            $currentgroup  = NULL;
        }
    
        $isseparategroups = ($this->course->groupmode == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $this->context));
        
        if ($isseparategroups and (!$currentgroup) ) {
            return array();
        }
        
        $capability = null;
        
        // Users without cansendtoall capability cand send only to managers
        // Managers are those who can send to all messages
        if (!$this->cansendtoall and $this->cansendtomanagers) {
            $capability = "block/jmail:sendtoall";
        }
        
        list($esql, $params) = get_enrolled_sql($this->context, $capability, $currentgroup, true);
        $joins = array("FROM {user} u");
        $wheres = array();
        
        $select = "SELECT u.id, u.firstname, u.lastname, u.picture, u.email, 
                  u.lang, u.timezone, u.imagealt";
        $joins[] = "JOIN ($esql) e ON e.id = u.id"; // course enrolled users only
        
        $params['courseid'] = $this->course->id;
        
        // performance hacks - we preload user contexts together with accounts
        list($ccselect, $ccjoin) = context_instance_preload_sql('u.id', CONTEXT_USER, 'ctx');
        $select .= $ccselect;
        $joins[] = $ccjoin;
        
        if ($roleid) {
            $contextlist = get_related_contexts_string($this->context);
            $wheres[] = "u.id IN (SELECT userid FROM {role_assignments} WHERE roleid = :roleid AND contextid $contextlist)";
            $params['roleid'] = $roleid;
        }
        
        if ($fi) {
            $wheres[] = $DB->sql_like('firstname', ':search1', false, false);
            $params['search1'] = "$fi%";
        }
        
        if ($li) {
            $wheres[] = $DB->sql_like('lastname', ':search2', false, false);
            $params['search2'] = "$li%";
        }
   
        $from = implode("\n", $joins);
        if ($wheres) {
            $where = "WHERE " . implode(" AND ", $wheres);
        } else {
            $where = "";
        }
        
        $sort = '';
        $start = '';
        $end = '';
        
        $userlist = $DB->get_records_sql("$select $from $where $sort", $params, $start, $end);
        
        if ($userlist) {
            foreach ($userlist as $key=>$u) {
                $userlist[$key]->fullname = fullname($u);
                $userlist[$key]->profileimage = $OUTPUT->user_picture($u);
                unset($userlist[$key]->email);
            }
        }
        
        $SESSION->jmailcache->contacts[$this->course->id][$hash] = $userlist;
        
        return $userlist;
        
    }
    
    /**
     * Perform a search over the course users
     * @param string $search The text to be searched in firstname or lastname
     * @return array Array of user objects
     */    
    public function get_contacts_search($search) {
        // For avoid SQL queries, we use a session cache
        if (!isset($SESSION->jmailcache->contacts_search[$this->course->id])) {
            $SESSION->jmailcache->contacts_search[$this->course->id] = $this->get_contacts(0,'','',0);
        }
        $users = $SESSION->jmailcache->contacts_search[$this->course->id];

    
        if (! $search) {
            return array();
        }
    
        $this->currentsearch = $search;
        
        $users1 = array_filter($users, array($this, 'filter_contacts_initials'));
        $users2 = array_filter($users, array($this, 'filter_contacts_like'));
        
        // I can't find a core php function for doing this
        foreach ($users2 as $key=>$u) {
            if (!isset($users1[$key])) {
                $users1[] = $u;
            }
        }
        
        return $users1;
    }
    
    /**
     * Performs a text search in the user object
     * @param object $contact A user object
     * @return object A single user
     */    
    private function filter_contacts_initials($contact) {
        $textlib = textlib_get_instance();
               
        return ($textlib->strpos($contact->firstname, $this->currentsearch) === 0) or ($textlib->strpos($contact->lastname, $this->currentsearch) === 0);
        
    }
    
    /**
     * Performs a text search in the user object
     * @param object $contact A user object
     * @return object A single user
     */
    
    private function filter_contacts_like($contact) {
        $textlib = textlib_get_instance();
        
        return ($textlib->strpos($contact->firstname, $this->currentsearch) !== false) or ($textlib->strpos($contact->lastname, $this->currentsearch) !== false);
        
    }
    
    /**
     * Refresh the cached contact list
     * @return bool Allways true
     */
    public function refresh_contacts() {
        $SESSION->jmailcache->contacts[$this->course->id] = array();
        $SESSION->jmailcache->contacts_search[$this->course->id] = array();
        return true;
    }

    /**
     * Return the current user roles and groups in the current course
     * @return array Array in the first element the groups in the second the user roles
     */     

    public function get_groups_roles() {
        global $USER;
        
        $groups = array();
        $roles = array();
        
        if (! has_capability('moodle/course:viewparticipants', $this->context)) {
            return array($groups, $roles);
        }
        
        if ($groupmode = $this->course->groupmode) {
                        
            $aag = has_capability('moodle/site:accessallgroups', $this->context);
        
            if ($groupmode == VISIBLEGROUPS or $aag) {
                $allowedgroups = groups_get_all_groups($this->course->id, 0, $this->course->defaultgroupingid);
            } else {
                $allowedgroups = groups_get_all_groups($this->course->id, $USER->id, $this->course->defaultgroupingid);
            }
        
            $activegroup = groups_get_course_group($this->course, true, $allowedgroups);
        
            $groupsmenu = array();
            if (!$allowedgroups or $groupmode == VISIBLEGROUPS or $aag) {
                $groupsmenu[0] = get_string('allparticipants');
            }
        
            if ($allowedgroups) {
                foreach ($allowedgroups as $group) {
                    $groupsmenu[$group->id] = format_string($group->name);
                }
            }
            $groups = $groupsmenu;
        }

        $allroles = get_all_roles();
        $roles = get_profile_roles($this->context);
        $allrolenames = array();
        $rolenames = array(0=>get_string('allparticipants'));
        
        foreach ($allroles as $role) {
            $allrolenames[$role->id] = strip_tags(role_get_name($role, $this->context));
            if (isset($roles[$role->id])) {
                $rolenames[$role->id] = $allrolenames[$role->id];
            }
        }   
        
        $roles = $rolenames;
        
        return array($groups, $roles);
    }

}
