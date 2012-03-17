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
 * Message form
 *
 * @package    blocks
 * @subpackage jmail
 * @copyright  2011 Juan Leyva <juanleyvadelgado@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();


require_once($CFG->libdir.'/formslib.php');

class block_jmail_message_form extends moodleform {

    function definition() {

        global $CFG;
        $mform    =& $this->_form;

        $course        = $this->_customdata['course'];
        $blockcontext    = $this->_customdata['context'];

        $editoroptions = array('maxfiles' => EDITOR_UNLIMITED_FILES, 'trusttext'=>true, 'context'=>$blockcontext);
        
        $mform->addElement('editor', 'body', get_string('body', 'block_jmail'), null, $editoroptions);
        $mform->setType('body', PARAM_RAW);

        $mform->addElement('filemanager', 'attachments', get_string('attachment', 'block_jmail'), null,
            array('subdirs'=>0,
                  'accepted_types'=>'*',
                  'maxbytes'=>$course->maxbytes,
                  'return_types'=>FILE_INTERNAL));
        
        $mform->addElement('hidden', 'messageid');
        $mform->setType('messageid', PARAM_INT);

        $submit_string = get_string('send', 'block_jmail');
        //$this->add_action_buttons(false, $submit_string);
    }

    function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (empty($data['body']['text'])) {
            $errors['body'] = get_string('erroremptymessage', 'block_jmail');
        }

        return $errors;
    }
    
    public function get_html(){
        return $this->_form->toHtml();
    }
}
