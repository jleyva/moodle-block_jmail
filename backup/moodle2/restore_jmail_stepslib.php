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
 * Define the complete jmail  structure for restore
 */
 
class restore_jmail_block_structure_step extends restore_structure_step {

    protected function define_structure() {

        $paths = array();
        
        // To know if we are including userinfo
        $userinfo = $this->get_setting_value('users');        
        
        if ($userinfo) {
            $paths[] = new restore_path_element('block', '/block', true);
            $paths[] = new restore_path_element('jmail', '/block/jmail');
            $paths[] = new restore_path_element('jmail_sent', '/block/jmail/sents/sent');
            $paths[] = new restore_path_element('jmail_preference', '/block/jmail/preferences/preference');
            $paths[] = new restore_path_element('jmail_label', '/block/jmail/labels/label');
            $paths[] = new restore_path_element('jmail_label_mlabel', '/block/jmail/labels/label/mlabels/mlabel');
        }

        return $paths;
    }

    public function process_block($data) {
        global $DB;

        $data = (object)$data;
        
        $this->jmailfileitems = array();

        // For any reason (non multiple, dupe detected...) block not restored, return
        if (!$this->task->get_blockid()) {
            return;
        }
        
        // To know if we are including userinfo
        $userinfo = $this->get_setting_value('users');        
        if ($userinfo) {
                        
            $jmails = $data->jmail;
            if ($jmails) {                
                foreach ($jmails as $jmail) {
                    $jmail = (object)$jmail;
                    $olditemid = $jmail->id;
                    $jmail->sender = $this->get_mappingid('user', $jmail->sender);
                    $jmail->courseid = $this->get_courseid();
                    $jmail->timesent = $this->apply_date_offset($jmail->timesent);
                    $jmail->timecreated = $this->apply_date_offset($jmail->timecreated);
                    $sents = $jmail->sents;
                    unset($jmail->sents);
                    $newitemid = $DB->insert_record('block_jmail', $jmail);
                    $this->jmailfileitems[$olditemid] = $newitemid;
                    if ($sents) {
                        foreach ($sents['sent'] as $sent) {
                            $sent = (object)$sent;
                            $oldid = $sent->id;
                            $sent->userid = $this->get_mappingid('user', $sent->userid);
                            $sent->messageid = $newitemid;
                            $newitemid = $DB->insert_record('block_jmail_sent', $sent);
                            $this->set_mapping('jmail_sent', $oldid, $newitemid, true);
                        }
                    }                  
                }
                
                // The preferences and labels are a child of every message
                // Otherwises (using a correct structure), the backup restoration fails (I dont know way)
                // This may be caused because I'm using a mod tables structures inside a block
                
                $preferences = $jmail->preferences['preference'];
                if (!empty($preferences)) {
                    foreach ($preferences as $pref) {
                        $pref = (object)$pref;
                        $pref->courseid = $this->get_courseid();
                        $pref->userid = $this->get_mappingid('user', $pref->userid);
                        $DB->insert_record('block_jmail_preferences', $pref);
                    }
                }
                
                $labels = $jmail->labels['label'];
                if (!empty($labels)) {
                    foreach ($labels as $label) {
                        $label = (object)$label;
                        $label->courseid = $this->get_courseid();
                        $label->userid = $this->get_mappingid('user', $label->userid);
                        $label->timecreated = $this->apply_date_offset($label->timecreated);
                        
                        $mlabels = $label->mlabels['mlabel'];
                        unset($label->mlabels);
                        
                        $lastlabelid = $DB->insert_record('block_jmail_label', $label);
                        
                        if (!empty($mlabels)) {
                            foreach ($mlabels as $mlabel) {
                                $mlabel = (object)$mlabel;
                                $mlabel->labelid = $lastlabelid;
                                $mlabel->messagesentid = $this->get_mappingid('jmail_sent', $mlabel->messagesentid);
                                $mlabel->timecreated = $this->apply_date_offset($mlabel->timecreated);
                                $DB->insert_record('block_jmail_m_label', $mlabel);
                            }
                        }
                    }
                }
            }
        }
    }

    protected function after_execute() {
        global $DB;
        
        // Restore the files
        // This is a little bit hacky
        // The file areas are created but the itemid has not been updated to the backup block limitations
        
        $contextid = $this->task->get_contextid();
        $fs = get_file_storage();
        
        if (!empty($this->jmailfileitems)) {
            foreach ($this->jmailfileitems as $olditem => $newitem) {
                if ($files = $DB->get_records('files', array('itemid' => $olditem,'contextid' => $contextid, 'component' => 'block_jmail'))) {
                    foreach ($files as $f) {
                        $f->itemid = $newitem;                        
                        $fullpath = $fs->get_pathname_hash($contextid, 'block_jmail', $f->filearea, $newitem, $f->filepath, $f->filename);                        
                        $f->pathnamehash = $fullpath;
                        $DB->update_record('files', $f);
                    }
                }
            }
        }
        
    }

}