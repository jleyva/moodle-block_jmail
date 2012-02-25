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
 * Handling all ajax requests
 * This works as a RPC ajax system
 *
 * @package    blocks
 * @subpackage jmail
 * @copyright  2011 Juan Leyva <juanleyvadelgado@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once('../../config.php');
require_once($CFG->dirroot.'/blocks/jmail/block_jmail_mailbox.class.php');

$id      = required_param('id', PARAM_INT); // course id
$action  = required_param('action', PARAM_ACTION);

$PAGE->set_url(new moodle_url('/blocks/jmail/block_jmail_ajax.php', array('id'=>$id)));

$course = $DB->get_record('course', array('id'=>$id), '*', MUST_EXIST);
$context = get_context_instance(CONTEXT_COURSE, $course->id, MUST_EXIST);

$PAGE->set_context($context);

require_login($course);
require_capability('block/jmail:viewmailbox', $context);
require_sesskey();

// TODO, check block disabled or instance not visible?

$err = new stdClass();
if (isguestuser()) {
    $err->error = get_string('noguest');
    die(json_encode($err));
}

$mailbox = new block_jmail_mailbox($course, $context);

echo $OUTPUT->header(); // send headers

switch ($action) {
    case 'get_message_headers':
        $label = optional_param('label', 'inbox', PARAM_ALPHANUM);
        $start = optional_param('start', 0, PARAM_INT);
        $sort = optional_param('sort', 'date', PARAM_ALPHA);
        $direction = optional_param('direction', 'DESC', PARAM_ALPHA);
        $searchtext = optional_param('searchtext', '', PARAM_NOTAGS);
        
        list($totalmessages, $messages) = $mailbox->get_message_headers($label, $start, $sort, $direction, $searchtext);
                
        $data = new stdClass;
        $data->pagesize = $mailbox->pagesize;
        $data->total = $totalmessages;
        $data->start = $start;
        $data->sort = $sort;
        $data->direction = $direction;
        $data->messages = $messages;
        
        break;
    case 'get_message':
    case 'get_message_sent':
        $messageid = required_param('messageid', PARAM_INT);
        if ($action == 'get_message') {
            $data = $mailbox->get_message($messageid);
        } else {
            $data = $mailbox->get_message_sent($messageid);
        }
        break;
    case 'send_message':
    case 'save_message':
        $messageid =      optional_param('messageid', 0,PARAM_INT);
        $to =      required_param('to', PARAM_SEQUENCE);
        $cc =      optional_param('cc', '',  PARAM_SEQUENCE);
        $bcc =     optional_param('bcc', '', PARAM_SEQUENCE);
        $subject = required_param('subject', PARAM_TEXT);
        $body = optional_param('body ', '', PARAM_RAW);
        $attachments = optional_param('attachments ', '', PARAM_RAW);
        $timesent = 0;
        if ($action == 'send_message') {
            $timesent = time();
        }
        $data = $mailbox->save_message($messageid, $to, $cc, $bcc, $subject, $body, $timesent,$attachments);
        
        break;
    case 'send_draft':
        $messageid = required_param('messageid', PARAM_INT);
        $data = $mailbox->send_draft($messageid);
        break;
    case 'delete_message':
        $messageids = required_param('messageids', PARAM_SEQUENCE);
        if ($messages = explode(',', $messageids)) {
            foreach ($messages as $messageid) {
                $data = $mailbox->delete_message($messageid);
            }
        }
        break;
    case 'label_message':
    case 'unlabel_message':
        $messageid = required_param('messageid', PARAM_INT);
        $labelid = required_param('labelid', PARAM_INT);
        if ($action == 'label_message') {
            $data = $mailbox->label_message($messageid, $labelid);
        } else {
            $data = $mailbox->unlabel_message($messageid, $labelid);
        }
        break;
    case 'get_labels':
        $data = $mailbox->get_labels();
        break;
    case 'delete_label':
        $labelid = required_param('labelid', PARAM_INT);
        $data = $mailbox->delete_label($labelid);
        break;
    case 'rename_label':
        $labelid = required_param('labelid', PARAM_INT);
        $name = required_param('name', PARAM_TEXT);
        $data = $mailbox->delete_label($labelid, $name);
        break;    
    case 'create_label':
        $name = required_param('name', PARAM_TEXT);
        $global = optional_param('global', false, PARAM_BOOL);
        $data = $mailbox->create_label($name, $global);
        break;
    case 'get_groups_roles':
        $data = $mailbox->get_groups_roles();
        break;
    case 'get_contacts':
        $group = optional_param('group', 0,PARAM_INT);        
        $roleid = optional_param('roleid', 0, PARAM_INT);
        $fi = optional_param('fi', '', PARAM_ALPHA);
        $li = optional_param('li', '', PARAM_ALPHA);
        $data = $mailbox->get_contacts($group, $fi, $li, $roleid);
        break;
    case 'get_contacts_search':
        $search = optional_param('search', '', PARAM_RAW);
        $data = array_values($mailbox->get_contacts_search($search));
        break;
    case 'refresh_contacts':
        $data = $mailbox->refresh_contacts();
        break;
}

echo json_encode($data);
die;
