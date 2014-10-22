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
 * Prints a message
 *
 * @package    blocks
 * @subpackage jmail
 * @copyright  2011 Juan Leyva <juanleyvadelgado@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot.'/blocks/jmail/block_jmail_mailbox.class.php');
require_once($CFG->dirroot.'/blocks/jmail/message_form.php');

$id = required_param('id', PARAM_INT);
$messageid = required_param('messageid', PARAM_INT);

$PAGE->set_url('/blocks/jmail/message.php', array('id'=>$id, 'messageid'=>$messageid));

if (! ($course = $DB->get_record('course', array('id'=>$id)))) {
    throw new moodle_exception('invalidcourseid', 'error');
}

if (! ($block = $DB->get_record('block', array('name'=>'jmail', 'visible'=>1)))) {
    throw new moodle_exception('invalidcourseid', 'error');
}

require_login($course->id);
$context = block_jmail_get_context(CONTEXT_COURSE, $course->id, MUST_EXIST);
$PAGE->set_context($context);

if (!$mailbox = new block_jmail_mailbox($course, $context)) {
    throw new moodle_exception('Invalid mailbox');
}

$message = block_jmail_message::get_from_id($messageid);

$renderer = $PAGE->get_renderer('block_jmail');

if ($message and $message->is_mine() and $message->courseid == $course->id) {
    print $renderer->message_print($message);
}
