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
 * Library of interface functions and constants for module newmodule
 *
 * All the core Moodle functions, neeeded to allow the module to work
 * integrated in Moodle should be placed here.
 * All the newmodule specific functions, needed to implement all the module
 * logic, should go to locallib.php. This will help to save some memory when
 * Moodle is performing actions across all modules.
 *
 * @package   mod_lightboxgallery
 * @copyright 2011 John Kelsh
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

define('THUMB_WIDTH', 150);
define('THUMB_HEIGHT', 150);
define('MAX_IMAGE_LABEL', 13);
define('MAX_COMMENT_PREVIEW', 20);

define('AUTO_RESIZE_SCREEN', 1);
define('AUTO_RESIZE_UPLOAD', 2);
define('AUTO_RESIZE_BOTH', 3);


require_once($CFG->libdir . '/filelib.php');
require_once('imageclass.php');

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @param object $gallery An object from the form in mod_form.php
 * @return int The id of the newly inserted newmodule record
 */
function lightboxgallery_add_instance($gallery) {
    global $DB;

    $gallery->timemodified = time();

    if (!lightboxgallery_rss_enabled()) {
        $gallery->rss = 0;
    }
   
    return $DB->insert_record('lightboxgallery', $gallery);
}

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @param object $gallery An object from the form in mod_form.php
 * @return boolean Success/Fail
 */
function lightboxgallery_update_instance($gallery) {
    global $DB;

    $gallery->timemodified = time();
    $gallery->id = $gallery->instance;

    if (!lightboxgallery_rss_enabled()) {
        $gallery->rss = 0;
    }

    if (isset($gallery->autoresizedisabled)) {
        $gallery->autoresize = 0;
        $gallery->resize = 0;
    }

    return $DB->update_record('lightboxgallery', $gallery);
}

/**
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id Id of the module instance
 * @return boolean Success/Failure
 */
function lightboxgallery_delete_instance($id) {
    global $DB;

    if (!$gallery = $DB->get_record('lightboxgallery', array('id' => $id))) {
        return false;
    }

    $DB->delete_records('lightboxgallery', 'id', $gallery->id);
    $DB->delete_records('lightboxgallery_comments', 'gallery', $gallery->id);
    $DB->delete_records('lightboxgallery_image_meta', 'gallery', $gallery->id);

    return true;
}

/**
 * Return a small object with summary information about what a
 * user has done with a given particular instance of this module
 * Used for user activity reports.
 * $return->time = the time they did it
 * $return->info = a short text description
 *
 * @return null
 * @todo Finish documenting this function
 */
function lightboxgallery_user_outline($course, $user, $mod, $resource) {
    global $DB;

    $conditions = array('userid' => $user->id,  'module' => 'lightboxgallery', 'action' => 'view', 'info' => $resource->id);

    if ($logs = $DB->get_records('log', $conditions, 'time ASC', '*', '0', '1')) {
        $numviews = $DB->count_records('log', $conditions);
        $lastlog = array_pop($logs);

        $result = new object;
        $result->info = get_string('numviews', '', $numviews);
        $result->time = $lastlog->time;

        return $result;

    } else {

        return null;

    }
}

/**
 * Print a detailed representation of what a user has done with
 * a given particular instance of this module, for user activity reports.
 *
 * @return boolean
 * @todo Finish documenting this function
 */
function lightboxgallery_user_complete($course, $user, $mod, $resource) {
    global $DB, $CFG;

    $conditions = array('userid' => $user->id,  'module' => 'lightboxgallery', 'action' => 'view', 'info' => $resource->id);

    if ($logs = get_records('log', $conditions, 'time ASC', '*', '0', '1')) {
        $numviews = $DB->count_records('log', $conditions);
        $lastlog = array_pop($logs);

        $strnumviews = get_string('numviews', '', $numviews);
        $strmostrecently = get_string('mostrecently');

        echo $strnumviews.' - '.$strmostrecently.' '.userdate($lastlog->time);

        $sql = "SELECT c.*
                  FROM {$CFG->prefix}lightboxgallery_comments c
                       JOIN {$CFG->prefix}lightboxgallery l ON l.id = c.gallery
                       JOIN {$CFG->prefix}user            u ON u.id = c.userid
                 WHERE l.id = {$mod->instance} AND u.id = {$user->id}
              ORDER BY c.timemodified ASC";

        if ($comments = $DB->get_records_sql($sql)) {
            $cm = get_coursemodule_from_id('lightboxgallery', $mod->id);
            $context = get_context_instance(CONTEXT_MODULE, $cm->id);
            foreach ($comments as $comment) {
                lightboxgallery_print_comment($comment, $context);
            }
        }
    } else {
        print_string('neverseen', 'resource');
    }
}

function lightboxgallery_get_recent_mod_activity(&$activities, &$index, $timestart, $courseid, $cmid, $userid=0, $groupid=0) {
    global $DB, $CFG, $COURSE;

    if ($COURSE->id == $courseid) {
        $course = $COURSE;
    } else {
        $course = $DB->get_record('course', array('id' => $courseid));
    }

    $modinfo =& get_fast_modinfo($course);

    $cm = $modinfo->cms[$cmid];

    $sql = "SELECT c.*, l.name, u.firstname, u.lastname, u.picture
              FROM {$CFG->prefix}lightboxgallery_comments c
                   JOIN {$CFG->prefix}lightboxgallery l ON l.id = c.gallery
                   JOIN {$CFG->prefix}user            u ON u.id = c.userid
             WHERE c.timemodified > $timestart AND l.id = {$cm->instance}
                   " . ($userid ? "AND u.id = $userid" : '') . "
          ORDER BY c.timemodified ASC";

    if ($comments = $DB->get_records_sql($sql)) {
        foreach ($comments as $comment) {
            $display = lightboxgallery_resize_text(trim(strip_tags($comment->comment)), MAX_COMMENT_PREVIEW);

            $activity = new object();

            $activity->type         = 'lightboxgallery';
            $activity->cmid         = $cm->id;
            $activity->name         = format_string($cm->name, true);
            $activity->sectionnum   = $cm->sectionnum;
            $activity->timestamp    = $comment->timemodified;

            $activity->content = new object();
            $activity->content->id      = $comment->id;
            $activity->content->comment = $display;

            $activity->user = new object();
            $activity->user->id        = $comment->userid;
            $activity->user->firstname = $comment->firstname;
            $activity->user->lastname  = $comment->lastname;
            $activity->user->picture   = $comment->picture;

            $activities[$index++] = $activity;

        }
    }
    return true;
}

function lightboxgallery_print_recent_mod_activity($activity, $courseid, $detail, $modnames, $viewfullnames) {
    global $CFG;

    echo '<table border="0" cellpadding="3" cellspacing="0">'.
         '<tr><td class="userpicture" valign="top">'.print_user_picture($activity->user, $courseid, $activity->user->picture, 0, true).'</td><td>'.
         '<div class="title">'.
         ($detail ? '<img src="'.$CFG->modpixpath.'/'.$activity->type.'/icon.gif" class="icon" alt="'.s($activity->name).'" />' : '').
         '<a href="'.$CFG->wwwroot.'/mod/lightboxgallery/view.php?id='.$activity->cmid.'#c'.$activity->content->id.'">'.$activity->content->comment.'</a>'.
         '</div>'.
         '<div class="user">'.
         ' <a href="'.$CFG->wwwroot.'/user/view.php?id='.$activity->user->id.'&amp;course='.$courseid.'"> '.fullname($activity->user, $viewfullnames).'</a> - '.userdate($activity->timestamp).
         '</div>'.
         '</td></tr></table>';

    return true;
}

/**
 * Given a course and a time, this module should find recent activity
 * that has occurred in newmodule activities and print it out.
 * Return true if there was output, or false is there was none.
 *
 * @return boolean
 * @todo Finish documenting this function
 */
function lightboxgallery_print_recent_activity($course, $viewfullnames, $timestart) {
    global $DB, $CFG, $OUTPUT;

    $sql = "SELECT c.*, l.name, u.firstname, u.lastname
              FROM {$CFG->prefix}lightboxgallery_comments c
                   JOIN {$CFG->prefix}lightboxgallery l ON l.id = c.gallery
                   JOIN {$CFG->prefix}user            u ON u.id = c.userid
             WHERE c.timemodified > $timestart AND l.course = {$course->id}
          ORDER BY c.timemodified ASC";

    if ($comments = $DB->get_records_sql($sql)) {
        echo $OUTPUT->heading(get_string('newgallerycomments', 'lightboxgallery').':', 3);

        echo '<ul class="unlist">';

        foreach ($comments as $comment) {
            $display = lightboxgallery_resize_text(trim(strip_tags($comment->comment)), MAX_COMMENT_PREVIEW);

            echo '<li>'.
                 ' <div class="head">'.
                 '  <div class="date">'.userdate($comment->timemodified, get_string('strftimerecent')).'</div>'.
                 '  <div class="name">'.fullname($comment, $viewfullnames).' - '.format_string($comment->name).'</div>'.
                 ' </div>'.
                 ' <div class="info">'.
                 '  "<a href="'.$CFG->wwwroot.'/mod/lightboxgallery/view.php?l='.$comment->gallery.'#c'.$comment->id.'">'.$display.'</a>"'.
                 ' </div>'.
                 '</li>';
        }

        echo '</ul>';

    }

    return true;
}

/**
 * Must return an array of users who are participants for a given instance
 * of newmodule. Must include every user involved in the instance,
 * independient of his role (student, teacher, admin...). The returned
 * objects must contain at least id property.
 * See other modules as example.
 *
 * @param int $newmoduleid ID of an instance of this module
 * @return boolean|array false if no participants, array of objects otherwise
 */
function lightboxgallery_get_participants($galleryid) {
    global $DB, $CFG;

    return $DB->get_records_sql("SELECT DISTINCT u.id, u.id
                                   FROM {$CFG->prefix}user u,
                                        {$CFG->prefix}lightboxgallery_comments c
                                  WHERE c.gallery = $galleryid AND u.id = c.userid");
}

function lightboxgallery_get_view_actions() {
    return array('view', 'view all', 'search');
}

function lightboxgallery_get_post_actions() {
    return array('comment', 'addimage', 'editimage');
}

function lightboxgallery_get_types() {
    $types = array();

    $type = new object;
    $type->modclass = MOD_CLASS_RESOURCE;
    $type->type = 'lightboxgallery';
    $type->typestr = get_string('modulenameadd', 'lightboxgallery');
    $types[] = $type;

    return $types;
}

// Custom lightboxgallery methods

function lightboxgallery_config_defaults() {
    $defaults = array(
        'disabledplugins' => '',
        'enablerssfeeds' => 0,
        'strictfilenames' => 0,
        'overwritefiles' => 1,
        'imagelifetime' => 86400
    );

    $localcfg = get_config('lightboxgallery');

    foreach ($defaults as $name => $value) {
        if (! isset($localcfg->$name)) {
            set_config($name, $value, 'lightboxgallery');
        }
    }
}

function lightboxgallery_resize_text($text, $length) {
    $textlib = textlib_get_instance();
    return ($textlib->strlen($text) > $length ? $textlib->substr($text, 0, $length) . '...' : $text);
}

function lightboxgallery_resize_label($label) {
    return lightboxgallery_resize_text($label, MAX_IMAGE_LABEL);
}

function lightboxgallery_print_comment($comment, $context) {
    global $DB, $CFG, $COURSE, $OUTPUT;

    $user = $DB->get_record('user', array('id' => $comment->userid));

    echo '<table cellspacing="0" width="50%" class="boxaligncenter datacomment forumpost">'.
         '<tr class="header"><td class="picture left">'.$OUTPUT->user_picture($user, array('courseid' => $COURSE->id)).'</td>'.
         '<td class="topic starter" align="left"><a name="c'.$comment->id.'"></a><div class="author">'.
         '<a href="'.$CFG->wwwroot.'/user/view.php?id='.$user->id.'&amp;course='.$COURSE->id.'">'.fullname($user, has_capability('moodle/site:viewfullnames', $context)).'</a> - '.userdate($comment->timemodified).
         '</div></td></tr>'.
         '<tr><td class="left side">'.
//         ($groups = user_group($COURSE->id, $user->id) ? print_group_picture($groups, $COURSE->id, false, false, true) : '&nbsp;').
         '</td><td class="content" align="left">'.
         format_text($comment->comment, FORMAT_MOODLE).
         '<div class="commands">'.
         (has_capability('mod/lightboxgallery:edit', $context) ? '<a href="'.$CFG->wwwroot.'/mod/lightboxgallery/comment.php?id='.$comment->gallery.'&amp;delete='.$comment->id.'">'.get_string('delete').'</a>' : '').
         '</div>'.
         '</td></tr></table>';
}

function lightboxgallery_image_modified($file) {
    $timestamp = 0;
    if (function_exists('exif_read_data') && $exif = @exif_read_data($file, 0, true)) {
        if (isset($exif['IFD0']['DateTime'])) {
            $date = preg_split('/[:]|[ ]/', $exif['IFD0']['DateTime']);
            $timestamp = mktime($date[3], $date[4], $date[5], $date[1], $date[2], $date[0]);
        }
    }
    if (! $timestamp > 0) {
        $timestamp = filemtime($file);
    }
    return date('d/m/y H:i', $timestamp);
}

function lightboxgallery_edit_types($showall = false) {
    global $CFG;

    $result = array();

    $disabledplugins = explode(',', get_config('lightboxgallery', 'disabledplugins'));

    $edittypes = get_list_of_plugins('mod/lightboxgallery/edit');

    foreach ($edittypes as $edittype) {
        if ($showall || !in_array($edittype, $disabledplugins)) {
            $result[$edittype] = get_string('edit_' . $edittype, 'lightboxgallery');
        }
    }

    return $result;
}

function lightboxgallery_print_tags($heading, $tags, $courseid, $galleryid) {
    global $CFG, $OUTPUT;

    echo $OUTPUT->box_start();

    echo '<form action="search.php" style="float: right; margin-left: 4px;">'.
         ' <fieldset class="invisiblefieldset">'.
         '  <input type="hidden" name="id" value="'.$courseid.'" />'.
         '  <input type="hidden" name="l" value="'.$galleryid.'" />'.
         '  <input type="text" name="search" size="8" />'.
         '  <input type="submit" value="'.get_string('search').'" />'.
         ' </fieldset>'.
         '</form>'.
         $heading.': ';

    $tagarray = array();
    foreach ($tags as $tag) {
        $tagarray[] = '<a class="taglink" href="'.$CFG->wwwroot.'/mod/lightboxgallery/search.php?id='.$courseid.'&amp;gallery='.$galleryid.'&amp;search='.urlencode(stripslashes($tag->description)).'">'.s($tag->description).'</a>';
    }

    echo implode(', ', $tagarray);

    echo $OUTPUT->box_end();
}

function lightboxgallery_resize_options() {
    return array(1 => '1280x1024', 2 => '1024x768', 3 => '800x600', 4 => '640x480');
}

function lightboxgallery_rss_enabled() {
    global $CFG;

    return ($CFG->enablerssfeeds && get_config('lightboxgallery', 'enablerssfeeds'));
}

function lightboxgallery_resize_upload($filename, $dimensions) {
    list($width, $height) = explode('x', $dimensions);
    $image = new lightboxgallery_edit_image($filename);
    return $image->resize($width, $height);
}

/**
 * Serves gallery images and other files.
 *
 * @param object $course
 * @param object $cm
 * @param object $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @return bool false if file not found, does not return if found - just send the file
 */
function lightboxgallery_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload) {
    global $CFG, $DB, $USER;

    require_once($CFG->libdir.'/filelib.php');
    require_login($course, false, $cm);

    $relativepath = implode('/', $args);
    $fullpath = '/'.$context->id.'/mod_lightboxgallery/'.$filearea.'/'.$relativepath;

    $fs = get_file_storage();
    if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
        return false;
    }

    send_stored_file($file, 0, 0, true); // download MUST be forced - security!

    return;

}

