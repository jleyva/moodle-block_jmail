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
 * Local lib functions for the block.
 *
 * @package    blocks
 * @subpackage jmail
 * @copyright  2011 Juan Leyva <juanleyvadelgado@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * Returns the jmail context
 *
 * @param  int $context The context
 * @param  int $id      The context id
 * @param  int $flags   The flags to be used
 * @return stdClass     An object instance
 */
function block_jmail_get_context($context, $id = null, $flags = null) {

    if ($context == CONTEXT_SYSTEM) {
        if (class_exists('context_system')) {
            return context_system::instance();
        } else {
            return get_context_instance(CONTEXT_SYSTEM);
        }
    } else if ($context == CONTEXT_COURSE) {
        if (class_exists('context_course')) {
            return context_course::instance($id, $flags);
        } else {
            return get_context_instance($context, $id, $flags);
        }
    } else if ($context == CONTEXT_COURSECAT) {
        if (class_exists('context_coursecat')) {
            return context_coursecat::instance($id, $flags);
        } else {
            return get_context_instance($context, $id, $flags);
        }
    } else if ($context == CONTEXT_BLOCK) {
        if (class_exists('context_block')) {
            return context_block::instance($id, $flags);
        } else {
            return get_context_instance($context, $id, $flags);
        }
    }
}