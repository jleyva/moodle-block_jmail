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
 * Main backup functions
 *
 * @package    blocks
 * @subpackage jmail
 * @copyright  2011 Juan Leyva <juanleyvadelgado@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->dirroot . '/blocks/jmail/backup/moodle2/backup_jmail_stepslib.php'); // We have structure steps

/**
 * Specialised backup task for the jmail block
 */
class backup_jmail_block_task extends backup_block_task {

    protected function define_my_settings() {
    }

    protected function define_my_steps() {
        $userinfo = $this->get_setting_value('users');        
        
        if ($userinfo) {
            $this->add_step(new backup_jmail_block_structure_step('jmail_structure', 'jmail.xml'));
        }
    }

    public function get_fileareas() {
        return array('attachment', 'body');
    }

    public function get_configdata_encoded_attributes() {
        return array();
    }

    static public function encode_content_links($content) {
        return $content; // No special encoding of links
    }
}