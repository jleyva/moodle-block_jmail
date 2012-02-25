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

$PAGE->set_url('/blocks/jmail/mailbox.php', array('id'=>$id));

if (! ($course = $DB->get_record('course', array('id'=>$id)))) {
    throw new moodle_exception('invalidcourseid', 'error');
}

if (! ($block = $DB->get_record('block', array('name'=>'jmail', 'visible'=>1)))) {
    throw new moodle_exception('invalidcourseid', 'error');
}

require_login($course->id);
$context = get_context_instance(CONTEXT_COURSE, $course->id, MUST_EXIST);

require_capability('block/jmail:viewmailbox', $context);

$mailbox = new block_jmail_mailbox($course, $context);

// TODO, check block disabled or instance not visible?
$instance = $DB->get_record('block_instances', array('blockname'=>'jmail', 'parentcontextid'=>$context->id));
$config = unserialize(base64_decode($instance->configdata));

$PAGE->set_context($context);
$PAGE->blocks->show_only_fake_blocks();

$renderer = $PAGE->get_renderer('block_jmail');

$strheading = (!empty($config->title))? format_string($config->title) : get_string('pluginname', 'block_jmail');

$PAGE->navbar->add($strheading);
$PAGE->set_title($strheading);
$PAGE->set_heading($course->fullname . ': '.$strheading);

$module = array(
                'name'      => 'block_jmail',
                'fullpath'  => '/blocks/jmail/module.js',
                'requires'  => array('io', 'event-custom', 'json-parse', 'base', 'node', 'tabview', 'datasource-get', 'datasource-jsonschema', 'datatable-base', 'datatable-datasource', 'datatable-sort', 'panel', 'autocomplete', 'autocomplete-highlighters'),
                'strings'   => array(array('from','block_jmail'),array('subject','block_jmail'),array('date','block_jmail'),
                                     array('removemail','block_jmail'),array('reply','block_jmail'),array('forward','block_jmail'),
                                     array('movetofolder','block_jmail'),array('print','block_jmail'), array('participants','block_jmail'),
                                     array('addlabel','block_jmail'),array('addusersto','block_jmail'),array('cc','block_jmail'),array('bcc','block_jmail'),
                                     array('for','block_jmail'),array('confirmdelete','block_jmail'),
                                     array('add','moodle'),array('delete','moodle'),array('rename','moodle'))
            );

$jmailcfg = array(
        'courseid' => $course->id,
        'sesskey' => sesskey(),
        'pagesize' => $mailbox->pagesize
                  );

$PAGE->requires->yui2_lib(array('event', 'dragdrop', 'element', 'animation', 'resize', 'layout', 'widget', 'button', 'editor', 'get', 'connection', 'datasource', 'datatable', 'container', 'utilities', 'menu', 'json', 'paginator'));
$PAGE->requires->js_init_call('M.block_jmail.init', array($jmailcfg), true, $module);
$PAGE->requires->css(new moodle_url('styles.css'));

echo $OUTPUT->header();
/*if (debugging('', DEBUG_DEVELOPER)) {
            $PAGE->requires->js('/lib/editor/tinymce/tiny_mce/'.$this->version.'/tiny_mce_src.js');
        } else {
            $PAGE->requires->js('/lib/editor/tinymce/tiny_mce/'.$this->version.'/tiny_mce.js');
        }
*/        
echo '<script type="text/javascript" src="http://192.168.1.76/moodle21/lib/editor/tinymce/tiny_mce/3.4.2/tiny_mce_src.js"></script>';

// $PAGE->requires->js_module($module);
echo "<script type=\"text/javascript\">
//<![CDATA[
M.yui.add_module({\"editor_tinymce\":{\"name\":\"editor_tinymce\",\"fullpath\":\"http:\/\/192.168.1.76\/moodle21\/lib\/editor\/tinymce\/module.js\",\"requires\":[]},\"form_filemanager\":{\"name\":\"form_filemanager\",\"fullpath\":\"http:\/\/192.168.1.76\/moodle21\/lib\/form\/filemanager.js\",\"requires\":[\"core_filepicker\",\"base\",\"io\",\"node\",\"json\",\"yui2-button\",\"yui2-container\",\"yui2-layout\",\"yui2-menu\",\"yui2-treeview\"]}});

//]]>
</script>";
echo $renderer->load_ui($mailbox);
echo $OUTPUT->footer();
