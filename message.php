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
 * AJAX script for creating the new message form.
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

$PAGE->set_url('/blocks/jmail/message.php', array('id'=>$id));

if (! ($course = $DB->get_record('course', array('id'=>$id)))) {
    throw new moodle_exception('invalidcourseid', 'error');
}

if (! ($block = $DB->get_record('block', array('name'=>'jmail', 'visible'=>1)))) {
    throw new moodle_exception('invalidcourseid', 'error');
}


require_login($course->id);
$context = get_context_instance(CONTEXT_COURSE, $course->id, MUST_EXIST);

require_capability('block/jmail:sendmessages', $context);

// TODO, check block disabled or instance not visible?

$PAGE->set_context($context);

$instance = $DB->get_record('block_instances', array('blockname'=>'jmail', 'parentcontextid'=>$context->id));
$blockcontext = get_context_instance(CONTEXT_BLOCK, $instance->id);

$mform = new block_jmail_message_form(null, array('course'=>$course, 'context'=>$blockcontext));

//echo $OUTPUT->standard_head_html();
echo $mform->get_html();
echo $PAGE->requires->get_end_code();
