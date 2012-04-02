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
 * Mailbox message class
 * Manages a single message
 *
 * @package    blocks
 * @subpackage jmail
 * @copyright  2011 Juan Leyva <juanleyvadelgado@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class block_jmail_message {

    /** @var integer  */
    public $id = 0;
    /** @var integer  Receiver*/
    public $courseid = 0;
    /** @var integer  */
    public $sentid = 0;
    /** @var integer  */
    public $subject = '';
    /** @var integer  */
    public $body = '';
    /** @var integer  */
    public $timesent = 0;
    /** @var integer  */
    public $sender = 0;
    /** @var integer  */
    public $timecreated = 0;
    /** @var integer  Receiver*/
    public $userid = 0;
    /** @var integer  */
    public $approved = 0;
    /** @var integer  */
    public $deleted = 0;
    /** @var integer  */
    public $read = 1;


    /**
     * Class constructor
     * @param object $message A message
     */    
    function __construct($message) {
        if (!empty($message->id)) {
            $this->id = $message->id;
        }
        if (!empty($message->courseid)) {
            $this->courseid = $message->courseid;
        }
        if (!empty($message->subject)) {
            $this->subject = $message->subject;
        }
        if (!empty($message->body)) {
            $this->body = $message->body;
        }
        if (!empty($message->timesent)) {
            $this->timesent = $message->timesent;
        }
        if (!empty($message->timecreated)) {
            $this->timecreated = $message->timecreated;
        }
        if (!empty($message->sender)) {            
            $this->sender = $message->sender;
        }
        if (!empty($message->approved)) {            
            $this->approved = $message->approved;
        }
        // Reference to message sent
        if (!empty($message->sentid)) {            
            $this->sentid = $message->sentid;
        }
        if (!empty($message->userid)) {            
            $this->userid = $message->userid;
        }
        if (isset($message->deleted)) {            
            $this->deleted = $message->deleted;
        }
        if (isset($message->mread)) {            
            $this->read = $message->mread;
        }       
    }

    /**
     * Return message headers
     * @return object Message header
     */ 
    public function headers() {
        global $DB;
        
        if (isset($SESSION->jmailcache->courses[$this->courseid])) {
            $course = $SESSION->jmailcache->courses[$this->courseid];
        } else {
            $course = $DB->get_record('course', array('id'=>$this->courseid),'id, fullname, shortname', MUST_EXIST);
            $SESSION->jmailcache->courses[$this->courseid] = $course;            
        }
        
        $header = new stdClass;
        $user = $DB->get_record('user', array('id' => $this->sender, 'deleted' => 0));
        $header->id = $this->id;
        $header->from = fullname($user);
        $header->subject = format_string($this->subject);
        $time = (!empty($this->timesent)) ? $this->timesent : $this->timecreated;
        $header->date = userdate($time, get_string('strftimedatetimeshort', 'langconfig'));
        $header->read = $this->read;
        $header->approved = $this->approved;
        $header->courseid = $course->id;
        $header->courseshortname = $course->shortname;
               
        return $header;
    }
    
    /**
     * Return full message
     * @return object Message
     */ 
    public function full() {
        global $DB, $USER, $OUTPUT, $CFG;
        require_once($CFG->libdir.'/filelib.php');
        
        $user = $DB->get_record('user', array('id' => $this->sender, 'deleted' => 0));
        
        $message = new stdClass;
        $message->id = $this->id;
        $message->from = fullname($user);
        $message->sender = $this->sender;
        $message->subject = format_string($this->subject);        
        if ($this->timesent) {
            $message->date = userdate($this->timesent);
        } else {
            $message->date = userdate($this->timecreated);
        }
        $message->body = $this->body;
        $message->approved = $this->approved;
        $message->deleted = $this->deleted;
        $message->destinataries = array();
        $message->attachments = array();
        $message->labels = array();
        
        if (isset($SESSION->jmailcache->courses[$this->courseid])) {
            $course = $SESSION->jmailcache->courses[$this->courseid];
        } else {
            $course = $DB->get_record('course', array('id'=>$this->courseid),'id, fullname, shortname', MUST_EXIST);
            $SESSION->jmailcache->courses[$this->courseid] = $course;            
        }
        $message->courseid = $course->id;
        $message->courseshortname = $course->shortname;
        
        // Destinataries
        if ($destinataries = $DB->get_records('block_jmail_sent', array('messageid' => $this->id))) {
            foreach ($destinataries as $dest) {
                if ($dest->type == 'bcc' and $this->sender != $USER->id) {
                    continue;
                }
                if ($user = $DB->get_record('user', array('id'=>$dest->userid, 'deleted'=>0), 'id, firstname, lastname, username')) {
                    $dest->fullname = fullname($user);                    
                    $message->destinataries[$dest->type][] = $dest;
                }
            }
        }
        
        // Attachments
        $context = get_context_instance(CONTEXT_COURSE, $this->courseid);
            
        // We need the block instance for getting the attachments
        if ($instance = $DB->get_record('block_instances', array('blockname'=>'jmail', 'parentcontextid'=>$context->id))) {
            $context = get_context_instance(CONTEXT_BLOCK, $instance->id);
            $fs = get_file_storage();
            if ($files = $fs->get_area_files($context->id, 'block_jmail', 'attachment', $this->id, "timemodified", false)) {
                foreach ($files as $file) {
                    $mimetype = $file->get_mimetype();
                    $attachment = new stdClass;                    
                    $attachment->filename = $file->get_filename();
                    $attachment->iconimage = '<img src="'.$OUTPUT->pix_url(file_mimetype_icon($mimetype)).'" class="icon" alt="'.$mimetype.'" />';
                    $attachment->path = file_encode_url($CFG->wwwroot.'/pluginfile.php', '/'.$context->id.'/block_jmail/attachment/'.$this->id.'/'.$attachment->filename);
                    $message->attachments[] = $attachment;
                }
            }
            $message->body = file_rewrite_pluginfile_urls($message->body, 'pluginfile.php', $context->id, 'block_jmail', 'body', $this->id);
            $message->body = format_text($message->body);
        }
        
        // Labels        
        $sql = "SELECT l.id, l.name
                FROM
                    {block_jmail_label} l
                    JOIN {block_jmail_m_label} m
                    ON l.id = m.labelid
                WHERE
                    m.messagesentid = ?
                ";
        if ($labels = $DB->get_records_sql($sql, array($this->sentid))) {
            foreach ($labels as $label) {
                $message->labels[] = $label;
            }
        }
        
        if ($this->timesent and $this->sender == $USER->id) {
            $message->labels[] = 'sent';
        }
        
        if (!$this->timesent and $this->sender == $USER->id) {
            $message->labels[] = 'draft';
        }
        
        if ($message->deleted) {
            $message->labels[] = 'trash';
        }
        
        if (!$message->approved) {
            $message->labels[] = 'toapprove';
        }        
        
        if (empty($message->labels)) {
            $message->labels[] = 'inbox';
        }
        
        return $message;
    }
    
    /**
     * Update a message and destinataries
     * @param array $destinataries Destinataries for the message
     * @param array $attachments File attachments
     * @return mixed Message id or false if something fails
     */ 
    public function update($destinataries, $attachments, $editoritemid) {
        global $USER, $DB, $CFG;
        
        if (!$message = $DB->get_record('block_jmail', array('id'=>$this->id))) {
            return false;
        }
        $message->sender = $USER->id;
        $message->courseid = $this->courseid;
        $message->subject = $this->subject;
        
        $message->body = $this->body;
        
        $message->timesent = $this->timesent;
        $message->timecreated = $this->timecreated;

        if ($DB->update_record('block_jmail', $message)) {
            
            $DB->delete_records('block_jmail_sent', array('messageid'=>$this->id));
            
            foreach ($destinataries as $d) {
                if (!$d->userid) {
                    continue;
                }
                $to = new stdClass;
                $to->userid = $d->userid;
                $to->messageid = $this->id;
                $to->type = $d->type;
                $to->mread = 0;
                $to->answered = 0;
                $to->deleted = 0;
                $to->labeled = 0;
                $DB->insert_record('block_jmail_sent', $to);
            }
                        
            $context = get_context_instance(CONTEXT_COURSE, $this->courseid);
            
            // We need the block instance for saving the attachments
            if ($instance = $DB->get_record('block_instances', array('blockname'=>'jmail', 'parentcontextid'=>$context->id))) {
            
                // attachments
                $context = get_context_instance(CONTEXT_BLOCK, $instance->id);
    
                require_once($CFG->libdir.'/filelib.php');

                $message->body = file_save_draft_area_files($editoritemid,  $context->id, 'block_jmail', 'body', $this->id, array('subdirs'=>true), $message->body);
                $DB->set_field('block_jmail', 'body', $message->body, array('id'=>$this->id));                

                $info = file_get_draft_area_info($attachments);
                $present = ($info['filecount']>0) ? '1' : '';
                file_save_draft_area_files($attachments, $context->id, 'block_jmail', 'attachment', $this->id);
      
                $DB->set_field('block_jmail', 'attachment', $present, array('id'=>$this->id));
            }
            
            return $this->id;
        }

        return false;        

    }

    /**
     * Save a message and destinataries
     * @param array $destinataries Destinataries for the message
     * @param array $attachments File attachments
     * @return mixed Message id or false if something fails
     */ 
    public function save($destinataries, $attachments, $editoritemid) {
        global $USER, $DB, $CFG;

        $message = new stdClass;
        $message->sender = $USER->id;
        $message->courseid = $this->courseid;
        $message->subject = $this->subject;
        
        $message->body = $this->body;
        //$post->message = file_save_draft_area_files($post->itemid, $context->id, 'mod_forum', 'post', $post->id, array('subdirs'=>true), $post->message);
        
        $message->attachment = 0;
        $message->approved = $this->approved;

        $message->timesent = $this->timesent;
        $message->timecreated = $this->timecreated;

        if ($messageid = $DB->insert_record('block_jmail', $message)) {
            foreach ($destinataries as $d) {
                if (!$d->userid) {
                    continue;
                }
                $to = new stdClass;
                $to->userid = $d->userid;
                $to->messageid = $messageid;
                $to->type = $d->type;
                $to->mread = 0;
                $to->answered = 0;
                $to->deleted = 0;
                $to->labeled = 0;
                $DB->insert_record('block_jmail_sent', $to);
            }
            $this->id = $messageid;
            
            $context = get_context_instance(CONTEXT_COURSE, $this->courseid);
            
            // We need the block instance for saving the attachments
            if ($instance = $DB->get_record('block_instances', array('blockname'=>'jmail', 'parentcontextid'=>$context->id))) {
            
                // attachments
                $context = get_context_instance(CONTEXT_BLOCK, $instance->id);    
                require_once($CFG->libdir.'/filelib.php');
                
                $message->body = file_save_draft_area_files($editoritemid,  $context->id, 'block_jmail', 'body', $messageid, array('subdirs'=>true), $message->body);
                $DB->set_field('block_jmail', 'body', $message->body, array('id'=>$messageid));
                
                $info = file_get_draft_area_info($attachments);
                $present = ($info['filecount']>0) ? '1' : '';
                file_save_draft_area_files($attachments, $context->id, 'block_jmail', 'attachment', $messageid);
      
                $DB->set_field('block_jmail', 'attachment', $present, array('id'=>$messageid));
            }

            return $messageid;
        }

        return false;
    }
      
    
    /**
     * Mark as deleted the current message in database
     * @return boolean True if the message have been deleted succesfully
     */ 
    public function delete() {
        global $DB;
        
        // TODO, there is no way for deleting drafts right now
        if (!$this->sentid) {
            return false;
        }
        
        return $DB->set_field('block_jmail_sent', 'deleted', 1, array('id' => $this->sentid));
    }
    
    /**
     * Mark as undeleted the current message in database
     * @return boolean True if the message have been undeleted succesfully
     */ 
    public function undelete() {
        global $DB;
        
        // TODO, there is no way for deleting drafts right now
        if (!$this->sentid) {
            return false;
        }
        
        return $DB->set_field('block_jmail_sent', 'deleted', 0, array('id' => $this->sentid));
    }
    
    /**
     * Mark as sent the current message in database
     * @return boolean True if the message have been sent succesfully
     */ 
    public function mark_sent() {
        global $DB;
        
        if (!$this->sentid) {
            return false;
        }
        
        return $DB->set_field('block_jmail_sent', 'timesent', time(), array('id' => $this->sentid));
    }
    
    /**
     * Mark as read the current message in database
     * @return boolean True if the message have been sent succesfully
     */ 
    public function mark_read() {
        global $DB;
        
        if (!$this->sentid) {
            return false;
        }
        
        return $DB->set_field('block_jmail_sent', 'mread', 1, array('id' => $this->sentid));
    }
    
    /**
     * Mark as unread the current message in database
     * @return boolean True if the message have been sent succesfully
     */ 
    public function mark_unread() {
        global $DB;
        
        if (!$this->sentid) {
            return false;
        }
        
        return $DB->set_field('block_jmail_sent', 'mread', 0, array('id' => $this->sentid));
    }
    
    /**
     * Mark as labeled the current message in database
     * @return boolean True if the message have been sent succesfully
     */ 
    public function mark_labeled() {
        global $DB;
        
        return $DB->set_field('block_jmail_sent', 'labeled', 1, array('id' => $this->sentid));
    }
    
    /**
     * Mark as unlabeled the current message in database
     * @return boolean True if the message have been sent succesfully
     */ 
    public function mark_unlabeled() {
        global $DB;
        
        if ($DB->count_records('block_jmail_m_label', array('messagesentid' => $this->sentid)) == 0) {
            return $DB->set_field('block_jmail_sent', 'labeled', 0, array('id' => $this->sentid));
        }
        return true;
    }
    
    /**
     * Approve a message
     * @return boolean True if the message have been approved succesfully
     */ 
    public function approve() {
        global $DB;
        
        return $DB->set_field('block_jmail', 'approved', 1, array('id' => $this->id));
    }    

    /**
     * Checks if the message has been created for the current user or has been sent to the current user
     * @return boolean True if the message is from the current user
     */ 
    public function is_mine() {
        global $USER;
        
        if ($this->sender == $USER->id) {
            return true;
        }
        
        if ($this->userid == $USER->id and $this->timesent > 0 and $this->approved) {
            return true;
        }
    }

    /**
     * Returns an object from id
     * @param integer $id Message id
     * @return block_jmail_message Message object
     */ 
    public static function get_from_id($id) {
        global $DB, $USER;
        
        if ($message = $DB->get_record('block_jmail', array('id'=>$id))) {
            
            if ($messagesent = $DB->get_record('block_jmail_sent', array('messageid'=>$id,'userid'=>$USER->id))) {
                $message->userid = $messagesent->userid;
                $message->sentid = $messagesent->id;
                $message->deleted = $messagesent->deleted;
            }
            
            return new block_jmail_message($message);
        } else {
            return false;
        }
    }
    
        /**
     * Returns an object from the id in sent messages
     * @param integer $id Message id
     * @return block_jmail_message Message object
     */ 
    public static function get_from_sentid($id) {
        global $DB, $USER;
        
        if (! $messagesent = $DB->get_record('block_jmail_sent', array('id'=>$id))) {            
            return false;
        }
        if ($message = $DB->get_record('block_jmail', array('id'=>$messagesent->messageid))) {
            $message->sentid = $messagesent->id;
            $message->userid = $messagesent->userid;
            $message->deleted = $messagesent->deleted;
            return new block_jmail_message($message);
        } else {
            return false;
        }
    }

}
