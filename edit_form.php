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
 * Form for editing block instance settings
 *
 * @package    blocks
 * @subpackage jmail
 * @copyright  2011 Juan Leyva <juanleyvadelgado@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class block_jmail_edit_form extends block_edit_form {
    protected function specific_definition($mform) {
    
        $mform->addElement('header', 'configheader', get_string('blocksettings', 'block'));

        $mform->addElement('text', 'config_title', get_string('name'));
        $mform->setType('config_title', PARAM_MULTILANG);
        $mform->setDefault('config_title', get_string('pluginname', 'block_jmail'));
        
        $mform->addElement('selectyesno', 'config_approvemode', get_string('approvemode', 'block_jmail'));
        $mform->setDefault('config_approvemode', 0);
        
        $options = array('institution', 'department', 'lang', 'city', 'country', 'theme');      
        $options = array_combine($options, $options);
        array_unshift($options, get_string('none'));
        $mform->addElement('select', 'config_filterfield', get_string('filterfield', 'block_jmail'), $options);        

    }
}