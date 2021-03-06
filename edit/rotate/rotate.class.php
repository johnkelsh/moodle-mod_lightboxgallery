<?php

class edit_rotate extends edit_base {

    function __construct($gallery, $image, $cm, $tab) {
        parent::edit_base($gallery, $image, $cm, $tab, true);      
    }

    function output() {
        $result = get_string('selectrotation', 'lightboxgallery').'<br /><br />'.
                  '<label><input type="radio" name="angle" value="90" />-90&#176;</label>'.
                  '<label><input type="radio" name="angle" value="180" />180&#176;</label>'.
                  '<label><input type="radio" name="angle" value="270" />90&#176;</label>'.
                  '<br /><br /><input type="submit" value="'.get_string('edit_rotate', 'lightboxgallery').'" />';

        return $this->enclose_in_form($result);        
    }

    function process_form() {
        $angle = required_param('angle', PARAM_INT);

        $fs = get_file_storage();
        $stored_file = $fs->get_file($this->cm->id, 'mod_lightboxgallery', 'gallery_images', '0', '/', $this->image);
        $image = new lightboxgallery_image($stored_file, $this->gallery, $this->cm);

        $image->rotate_image($angle);
    }

}

?>
