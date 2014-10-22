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
 * Lib functions for the block.
 *
 * @package    blocks
 * @subpackage jmail
 * @copyright  2011 Juan Leyva <juanleyvadelgado@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->dirroot.'/blocks/jmail/block_jmail_mailbox.class.php');

/**
 * Serves the message attachments. Implements needed access control ;-)
 *
 * @param object $course
 * @param object $cm
 * @param object $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @return bool false if file not found, does not return if found - justsend the file
 */
function block_jmail_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload) {
    global $SCRIPT;

    if ($context->contextlevel != CONTEXT_BLOCK) {
        //send_file_not_found();
    }

    require_course_login($course);
    $coursecontext = block_jmail_get_context(CONTEXT_COURSE, $course->id, MUST_EXIST);

    // The mailbox constructor does the permission validation
    if (!$mailbox = new block_jmail_mailbox($course, $coursecontext, $context)) {
        return;
    }

    $messageid = (int)array_shift($args);

    $message = block_jmail_message::get_from_id($messageid);

    // We check if we are the senders or the receivers
    if (!$message) {
        send_file_not_found();
    }

    $pendingaprobal = !$message->approved and has_capability('block/jmail:approvemessages', $context);

    if (!$message->is_mine() and !$pendingaprobal) {
        send_file_not_found();
    }

    $fs = get_file_storage();
    $relativepath = implode('/', $args);

    $fullpath = "/$context->id/block_jmail/$filearea/$messageid/$relativepath";
    if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
        send_file_not_found();
    }

    $forcedownload = true;

    send_stored_file($file, 60*60, 0, $forcedownload);
}