<?php

class edit_base {

    var $imageobj;

    var $gallery;
    var $image;
    var $tab;
    var $showthumb;

    function edit_base($_gallery, $_cm, $_image, $_tab, $_showthumb = true) {
        global $CFG;

        $this->gallery = $_gallery;
        $this->cm = $_cm;
        $this->image = $_image;
        $this->tab = $_tab;
        $this->showthumb = $_showthumb;
    }

    function processing() {
        return optional_param('process', false, PARAM_BOOL);
    }

    function enclose_in_form($text) {
        global $CFG, $USER;

        return '<form action="'.$CFG->wwwroot.'/mod/lightboxgallery/imageedit.php" method="post">'.
               '<fieldset class="invisiblefieldset">'.
               '<input type="hidden" name="sesskey" value="'.$USER->sesskey.'" />'.
               '<input type="hidden" name="id" value="'.$this->cm->id.'" />'.
               '<input type="hidden" name="image" value="'.$this->image.'" />'.
               '<input type="hidden" name="tab" value="'.$this->tab.'" />'.
               '<input type="hidden" name="process" value="1" />'.$text.'</fieldset></form>';
    }

    function output() {

    }

    function process_form() {

    }

}

?>
