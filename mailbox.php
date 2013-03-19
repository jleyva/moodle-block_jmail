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
 * Main block functions.
 *
 * @package    blocks
 * @subpackage jmail
 * @copyright  2011 Juan Leyva <juanleyvadelgado@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot.'/blocks/jmail/block_jmail_mailbox.class.php');

$id = optional_param('id', SITEID, PARAM_INT);
$to = optional_param('to', 0, PARAM_INT);

$PAGE->set_url('/blocks/jmail/mailbox.php', array('id'=>$id));

if (! ($course = $DB->get_record('course', array('id'=>$id)))) {
    throw new moodle_exception('invalidcourseid', 'error');
}

if (! ($block = $DB->get_record('block', array('name'=>'jmail', 'visible'=>1)))) {
    throw new moodle_exception('invalidcourseid', 'error');
}

require_login($course->id);

$context = get_context_instance(CONTEXT_COURSE, $course->id, MUST_EXIST);
$PAGE->set_context($context);

$mailbox = new block_jmail_mailbox($course, $context);

add_to_log($course->id, 'jmail', 'view mailbox');

// TODO, check block disabled or instance not visible?

$PAGE->blocks->show_only_fake_blocks();

$renderer = $PAGE->get_renderer('block_jmail');

$strheading = (!empty($mailbox->config->title))? format_string($mailbox->config->title) : get_string('pluginname', 'block_jmail');

$PAGE->navbar->add($strheading);
$PAGE->set_title($strheading);
$PAGE->set_heading($course->fullname . ': '.$strheading);

$module = array(
                'name'      => 'block_jmail',
                'fullpath'  => '/blocks/jmail/module.js',
                'requires'  => array('io', 'event-custom', 'json-parse', 'base', 'node', 'tabview', 'datasource-get', 'datasource-jsonschema', 'datatable-base', 'datatable-datasource', 'datatable-sort', 'panel', 'autocomplete', 'autocomplete-highlighters'),
                'strings'   => array(array('from','block_jmail'),array('subject','block_jmail'),array('date','block_jmail'),array('messagesent','block_jmail'),
                                     array('reply','block_jmail'),array('forward','block_jmail'),array('print','block_jmail'), array('messagesaved','block_jmail'),    
                                     array('addlabel','block_jmail'),array('cc','block_jmail'),array('bcc','block_jmail'),array('messagedeleted','block_jmail'),
                                     array('messagesdeleted','block_jmail'),array('errortorequired','block_jmail'),array('errorsubjectrequired','block_jmail'),
                                     array('to','block_jmail'),array('confirmdelete','block_jmail'), array('label','block_jmail'), array('labels','block_jmail'),
                                     array('nomessagesfound','block_jmail'),array('re','block_jmail'),array('fw','block_jmail'),array('attachments','block_jmail'),
                                     array('preferences','block_jmail'),array('approvalpending','block_jmail'),array('messagehastobeapproved','block_jmail'),
                                     array('first','block_jmail'),array('last','block_jmail'),array('next','block_jmail'),array('previous','block_jmail'),
                                     array('delete','block_jmail'), array('save','block_jmail'),array('download','block_jmail'),array('savetomyprivatefiles','block_jmail'),array('filesaved','block_jmail'),
                                     array('mailbox','block_jmail'),array('delivertodifferentcourse','block_jmail'),array('delivertoglobalinbox','block_jmail'),
                                     array('inbox','block_jmail'),
                                     array('add','moodle'),array('deletem','block_jmail'),array('rename','moodle'),array('edit','moodle'), array('ok','moodle'),
                                     array('cancel','moodle'))
            );

$usertoid = 0;
$usertoname = '';

if ($userto = $DB->get_record('user', array('id' => $to, 'deleted' => 0))) {
    $d = new stdClass;
    $d->type = 'to';
    $d->userid = $userto->id;
    $destinataries[] = $d;
    if ($mailbox->check_destinataries($destinataries)) {
        $usertoid = $userto->id;
        $usertoname = fullname($userto);
    }
}

$jmailcfg = array(
        'wwwroot' => $CFG->wwwroot,
        'courseid' => $course->id,
        'sesskey' => sesskey(),
        'pagesize' => $mailbox->pagesize,
        'cansend' => $mailbox->cansend,
        'canapprovemessages' => $mailbox->canapprovemessages,
        'canmanagelabels' => $mailbox->canmanagelabels,
        'canmanagepreferences' => $mailbox->canmanagepreferences,
        'userid' => $USER->id,
        'globalinbox' => $mailbox->globalinbox,
        'approvemode' => (! empty($mailbox->config->approvemode))? $mailbox->config->approvemode : false,
        'usertoid' => $usertoid,
        'usertoname' => $usertoname,
        'version' => $CFG->version,
        );

//$PAGE->requires->js('/lib/editor/tinymce/tiny_mce/3.4.2/tiny_mce.js');

//MDL-34741 switch to YUI2 to 2in3 (2.4 and above)
if ($CFG->version < 2012120300) {
    $PAGE->requires->yui2_lib(array('event', 'dragdrop', 'element', 'animation', 'resize', 'layout', 'widget', 'button', 'editor', 'get', 'connection', 'datasource', 'datatable', 'container', 'utilities', 'menu', 'json', 'paginator'));
}
 
//MDL-34741 switch to YUI2 to 2in3 (2.4 and above)
if ($CFG->version < 2012120300) {
    // 2.1, 2.2, 2.3
    $PAGE->requires->js_init_call('M.block_jmail.init', array($jmailcfg), true, $module);
} else {
	// 2.4 and onwards..
	$PAGE->requires->js_init_call('M.block_jmail.initYAHOO', array($jmailcfg), true, $module);
}

echo $OUTPUT->header();    
echo $renderer->load_ui($mailbox);
echo $OUTPUT->footer();
