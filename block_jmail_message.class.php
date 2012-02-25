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
    public $course = 0;
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


    /**
     * Class constructor
     * @param object $message A message
     */    
    function __construct($message) {
        if (!empty($message->id)) {
            $this->id = $message->id;
        }
        if (!empty($message->course)) {
            $this->courseid = $message->course;
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
        // Reference to message sent
        if (!empty($message->sentid)) {            
            $this->sentid = $message->sentid;
        }
        if (!empty($message->userid)) {            
            $this->userid = $message->userid;
        }
    }

    /**
     * Return message headers
     * @return object Message header
     */ 
    public function headers() {
        global $DB;
        
        $header = new stdClass;
        $user = $DB->get_record('user', array('id' => $this->sender, 'deleted' => 0));
        $header->id = $this->id;
        $header->from = fullname($user);
        $header->subject = format_string($this->subject);
        $header->date = userdate($this->timesent, get_string('strftimedatetimeshort', 'langconfig'));
        
        return $header;
    }
    
    /**
     * Return full message
     * @return object Message
     */ 
    public function full() {
        global $DB, $USER;
        
        $message = new stdClass;
        $user = $DB->get_record('user', array('id' => $this->sender, 'deleted' => 0));
        $message->from = fullname($user);
        $message->subject = format_string($this->subject);        
        if ($this->timesent) {
            $message->date = userdate($this->timesent);
        } else {
            $message->date = userdate($this->timecreated);
        }
        $message->body = format_text($this->body);
        $message->destinataries = array();
        $message->attachments = array();
        
        // Destinataries
        if ($destinataries = $DB->get_records('block_jmail_sent', array('messageid' => $this->id))) {
            foreach ($destinataries as $dest) {
                if ($dest->type == 'bcc' and $this->sender != $USER->id) {
                    continue;
                }
                if ($user = $DB->get_record('user', array('id'=>$dest->userid, 'deleted'=>0))) {
                    $dest->fullname = fullname($user);                    
                    $message->destinataries[] = $dest;
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
                    $attachment->path = file_encode_url($CFG->wwwroot.'/pluginfile.php', '/'.$context->id.'/block_jmail/attachment/'.$this->id.'/'.$filename);
                    $message->attachments[] = $attachment;
                }
            }
        }
        
        return $message;
    }
    
    /**
     * Update a message and destinataries
     * @param array $destinataries Destinataries for the message
     * @param array $attachments File attachments
     * @return mixed Message id or false if something fails
     */ 
    public function update($destinataries, $attachments) {
        global $USER, $DB;
        
        if (!$message = $DB->get_record('block_jmail', array('id'=>$this->id))) {
            return false;
        }
        $message->sender = $USER->id;
        $message->course = $this->courseid;
        $message->subject = $this->subject;
        
        $message->body = $this->body;
        //$post->message = file_save_draft_area_files($post->itemid, $context->id, 'mod_forum', 'post', $post->id, array('subdirs'=>true), $post->message);
        
        $message->timesent = $this->timesent;
        $message->timecreated = $this->timecreated;

        if ($DB->update_record('block_jmail', $message)) {
            
            $DB->delete_records('block_jmail_sent', array('messageid'=>$this->id));
            
            foreach ($destinataries as $d) {
                $to = new stdClass;
                $to->userid = $d->userid;
                $to->messageid = $this->id;
                $to->type = $d->type;
                $to->read = 0;
                $to->answered = 0;
                $DB->insert_record('block_jmail_sent', $to);
            }
            
            
            $context = get_context_instance(CONTEXT_COURSE, $this->courseid);
            
            // We need the block instance for saving the attachments
            if ($instance = $DB->get_record('block_instances', array('blockname'=>'jmail', 'parentcontextid'=>$context->id))) {
            
                // attachments
                $context = get_context_instance(CONTEXT_BLOCK, $instance->id);
    
                $info = file_get_draft_area_info($attachments);
                $present = ($info['filecount']>0) ? '1' : '';
                file_save_draft_area_files($attachments, $context->id, 'block_jmail', 'attachment', $this->id);
      
                $DB->set_field('block_jmail', 'attachment', $present, array('id'=>$this->id));
            }
            return $messageid;
        }

        return false;        
        
    }

    /**
     * Save a message and destinataries
     * @param array $destinataries Destinataries for the message
     * @param array $attachments File attachments
     * @return mixed Message id or false if something fails
     */ 
    public function save($destinataries, $attachments) {
        global $USER, $DB;

        $message = new stdClass;
        $message->sender = $USER->id;
        $message->course = $this->courseid;
        $message->subject = $this->subject;
        
        $message->body = $this->body;
        //$post->message = file_save_draft_area_files($post->itemid, $context->id, 'mod_forum', 'post', $post->id, array('subdirs'=>true), $post->message);
        
        $message->timesent = $this->timesent;
        $message->timecreated = $this->timecreated;

        if ($messageid = $DB->insert_record('block_jmail', $message)) {
            foreach ($destinataries as $d) {
                $to = new stdClass;
                $to->userid = $d->userid;
                $to->messageid = $messageid;
                $to->type = $d->type;
                $to->read = 0;
                $to->answered = 0;
                $DB->insert_record('block_jmail_sent', $to);
            }
            $this->id = $messageid;
            
            $context = get_context_instance(CONTEXT_COURSE, $this->courseid);
            
            // We need the block instance for saving the attachments
            if ($instance = $DB->get_record('block_instances', array('blockname'=>'jmail', 'parentcontextid'=>$context->id))) {
            
                // attachments
                $context = get_context_instance(CONTEXT_BLOCK, $instance->id);
    
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
     * Mark as sent the current message in database
     * @return boolean True if the message have been sent succesfully
     */ 
    public function mark_sent() {
        return $DB->set_field('block_jmail_sent', 'timesent', time(), array('id' => $this->sentid));
    }
    
    /**
     * Mark as labeled the current message in database
     * @return boolean True if the message have been sent succesfully
     */ 
    public function mark_labeled() {
        return $DB->set_field('block_jmail_sent', 'labeled', 1, array('id' => $this->sentid));
    }
    
    /**
     * Mark as unlabeled the current message in database
     * @return boolean True if the message have been sent succesfully
     */ 
    public function mark_unlabeled() {
        if ($DB->count_records('block_jmail_m_label', array('messagesentid' => $this->sentid)) <= 1) {
            return $DB->set_field('block_jmail_sent', 'labeled', 0, array('id' => $this->sentid));
        }
        return true;
    }

    /**
     * Checks if the message has been created for the current user or has been sent to the current user
     * @return boolean True if the message is from the current user
     */ 
    public function is_mine() {
        global $USER;
        
        return $this->sender == $USER->id or ($this->userid == $USER->id and $this->timesent > 0);
    }

    /**
     * Returns an object from id
     * @param integer $id Message id
     * @return block_jmail_message Message object
     */ 
    public static function get_from_id($id) {
        global $DB, $USER;
        
        if ($message = $DB->get_record('block_jmail', array('id'=>$id))) {
            // We are loading a message not send by this user
            if ($message->sender != $USER->id) {
                if ($messagesent = $DB->get_record('block_jmail_sent', array('messageid'=>$id,'userid'=>$USER->id))) {
                    $message->userid = $messagesent->userid;
                    $message->sentid = $messagesent->id;
                }
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
            return new block_jmail_message($message);
        } else {
            return false;
        }
    }

}
