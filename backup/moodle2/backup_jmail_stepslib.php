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

/**
 * Define the complete forum structure for backup, with file and id annotations
 */
class backup_jmail_block_structure_step extends backup_block_structure_step {

    protected function define_structure() {
        global $DB;

        // To know if we are including userinfo
        $userinfo = $this->get_setting_value('users');        
        
        if ($userinfo) {
        
            // Define each element separated
            $jmail = new backup_nested_element('jmail', array('id'), array(
                'sender', 'courseid', 'subject', 'body', 'attachment',
                'approved', 'timesent', 'timecreated'));
    
            $sents = new backup_nested_element('sents');
    
            $sent = new backup_nested_element('sent', array('id'), array(
                'userid', 'messageid', 'type', 'mread',
                'answered', 'deleted', 'labeled'));           
            
            $labels = new backup_nested_element('labels');
            $label = new backup_nested_element('label', array('id'), array(
                'name', 'userid', 'courseid', 'timecreated'));
            $mlabels = new backup_nested_element('mlabels');
            $mlabel = new backup_nested_element('mlabel', array('id'), array(
                'labelid', 'messagesentid', 'timecreated'));
            
            $preferences = new backup_nested_element('preferences');
            $preference = new backup_nested_element('preference', array('id'), array(
                'userid', 'courseid', 'name', 'value'));

    
            // Build the tree
            $jmail->add_child($sents);
            $sents->add_child($sent);
            
            $jmail->add_child($preferences);
            $preferences->add_child($preference);
            
            $jmail->add_child($labels);
            $labels->add_child($label);            
            $label->add_child($mlabels);
            $mlabels->add_child($mlabel);
    
            // Define sources
            $jmail->set_source_table('block_jmail', array('courseid' => backup::VAR_COURSEID));
            $sent->set_source_table('block_jmail_sent', array('messageid' => backup::VAR_PARENTID));
            $preference->set_source_table('block_jmail_preferences', array('courseid' => backup::VAR_COURSEID));
            $label->set_source_table('block_jmail_label', array('courseid' => backup::VAR_COURSEID));
            $mlabel->set_source_table('block_jmail_m_label', array('labelid' => backup::VAR_PARENTID));

            // Define id annotations
            $jmail->annotate_ids('course', 'courseid');
            $jmail->annotate_ids('user', 'sender');
            $sent->annotate_ids('user', 'userid');
    
            // Define file annotations
    
            $jmail->annotate_files('block_jmail', 'attachment', 'id');
            $jmail->annotate_files('block_jmail', 'body', 'id');
        } else {
            return null;
        }

        // Return the root element (jmail), wrapped into standard block structure
        return $this->prepare_block_structure($jmail);
    }
}