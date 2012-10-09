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
 * Prints a particular instance of lightboxgallery
 *
 * @package   mod_lightboxgallery
 * @copyright 2011 John Kelsh
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/locallib.php');

require_once($CFG->libdir.'/completionlib.php');
require_once($CFG->libdir.'/filelib.php');
require_once($CFG->libdir.'/rsslib.php');
require_once(dirname(__FILE__).'/imageclass.php');

global $DB;

$id	 = required_param('id', PARAM_INT); // Course module id
$page	 = optional_param('page', 0, PARAM_INT);
$search  = optional_param('search', '', PARAM_TEXT);
$editing = optional_param('editing', 0, PARAM_BOOL);

$cm      = get_coursemodule_from_id('lightboxgallery', $id, 0, false, MUST_EXIST);
$course  = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$gallery = $DB->get_record('lightboxgallery', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, true, $cm);

if ($gallery->ispublic) {
    course_setup($course->id);
    $userid = (isloggedin() ? $USER->id : 0);
} else {
    require_login($course, true, $cm);
    $userid = $USER->id;
}

$context = get_context_instance(CONTEXT_MODULE, $cm->id);

if ($editing) {
    require_capability('mod/lightboxgallery:edit', $context);
}

lightboxgallery_config_defaults();

add_to_log($course->id, 'lightboxgallery', 'view', 'view.php?id='.$cm->id.'&page='.$page, $gallery->id, $cm->id, $userid);

// Mark viewed
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

$PAGE->set_url('/mod/lightboxgallery/view.php', array('id' => $cm->id));
$PAGE->set_title($gallery->name);
$PAGE->set_heading($course->shortname);
$PAGE->set_button((has_capability('mod/lightboxgallery:edit', $context) ? $OUTPUT->single_button($CFG->wwwroot.'/mod/lightboxgallery/view.php?id='.$id.'&page='.$page.'&editing='.($editing ? '0' : '1'), get_string('turnediting'.($editing ? 'off' : 'on')), 'get') : '').' '.update_module_button($cm->id, $course->id, get_string('modulename', 'lightboxgallery')));
$PAGE->requires->css('/mod/lightboxgallery/assets/skins/sam/gallery-lightbox-skin.css');
$PAGE->requires->js('/mod/lightboxgallery/gallery-lightbox-min.js');
$PAGE->requires->js('/mod/lightboxgallery/module.js');

echo $OUTPUT->header();

$allowrssfeed = (lightboxgallery_rss_enabled() && $gallery->rss);

if ($allowrssfeed) {
    $rsspath = rss_get_url($course->id, $userid, 'lightboxgallery', $gallery->id);
    $meta .= "\n".'<link rel="alternate" href="'.$rsspath.'" type="application/rss+xml" title="'.format_string($gallery->name).'" id="gallery" />';
    $heading .= ' '.rss_get_link($course->id, $userid, 'lightboxgallery', $gallery->id, get_string('rsssubscribe', 'lightboxgallery'));
}

echo $OUTPUT->heading(get_string('displayinggallery', 'lightboxgallery', $gallery->name));

if ($gallery->description && !$editing) {
    echo $OUTPUT->box(format_text($gallery->description, '', array('para' => false)));
}

echo $OUTPUT->box_start('generalbox lightbox-gallery clearfix');

$fs = get_file_storage();
$stored_files = $fs->get_area_files($cm->id,'mod_lightboxgallery','gallery_images');

$image_count = 1;

foreach ($stored_files as $stored_file) {
    if(!$stored_file->is_valid_image()) {
        continue;
    }

    if($gallery->perpage > 0 && (($image_count > (($gallery->perpage * $page) + $gallery->perpage) || ($image_count < ($gallery->perpage * $page) + 1)))) {
        $image_count++;
        continue;
    }

    $image = new lightboxgallery_image($stored_file, $gallery, $cm);

    echo $image->get_image_display_html();

    if(!is_float($image_count / $gallery->perrow)) {
        echo $OUTPUT->box('', 'clearfix');
    }

    $image_count++;
}

echo ($image_count < 1 ? print_string('errornoimages', 'lightboxgallery') : '');
echo $OUTPUT->box_end();

$pagingbar = ($gallery->perpage ? new paging_bar($image_count, $page, $gallery->perpage, $CFG->wwwroot.'/mod/lightboxgallery/view.php?id='.$cm->id.'&amp;' . ($editing ? 'editing=1&amp;' : '')) : '');
echo ($pagingbar ? $OUTPUT->render($pagingbar) : '');


$showtags = !in_array('tag', explode(',', get_config('lightboxgallery', 'disabledplugins')));

if (!$editing && $showtags) {
    $sql = "SELECT description
              FROM {$CFG->prefix}lightboxgallery_image_meta
             WHERE gallery = {$gallery->id}
               AND metatype = 'tag'
          GROUP BY description
          ORDER BY COUNT(description) DESC,
                   description ASC";
    if ($tags = $DB->get_records_sql($sql, array(), 0, 10)) {
        lightboxgallery_print_tags(get_string('tagspopular', 'lightboxgallery'), $tags, $course->id, $gallery->id);
    }
}

$options = array();

if (has_capability('mod/lightboxgallery:addimage', $context)) {
    $options[] = '<a href="'.$CFG->wwwroot.'/mod/lightboxgallery/imageadd.php?id='.$cm->id.'">'.get_string('addimage', 'lightboxgallery').'</a>';
}

if ($gallery->comments && has_capability('mod/lightboxgallery:addcomment', $context)) {
    $options[] = '<a href="'.$CFG->wwwroot.'/mod/lightboxgallery/comment.php?id='.$gallery->id.'">'.get_string('addcomment', 'lightboxgallery').'</a>';
}

if (count($options) > 0) {
    echo $OUTPUT->box(implode(' | ', $options), 'center');
}

if (!$editing && $gallery->comments && has_capability('mod/lightboxgallery:viewcomments', $context)) {
    if ($comments = $DB->get_records('lightboxgallery_comments', array('gallery' => $gallery->id), 'timemodified ASC')) {
        foreach ($comments as $comment) {
            lightboxgallery_print_comment($comment, $context);
        }
    }
}

echo $OUTPUT->footer();

