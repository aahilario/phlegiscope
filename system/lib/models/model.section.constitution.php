<?php

/*
 * Class ConstitutionSectionModel
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 * Generated on Tue, 24 Jul 2018 15:15:18 +0000
 */

class ConstitutionSectionModel extends DatabaseUtility {
  
  var $section_content_vc8192;
  var $variant_ConstitutionSectionVariantsModel;
  var $created_dtm;
  var $updated_dtm;
  var $slug_vc255uniq;

  function __construct() {
    parent::__construct();
  }

  function & set_section_content($v) { $this->section_content_vc8192 = $v; return $this; } function get_section_content($v = NULL) { if (!is_null($v)) $this->set_section_content($v); return $this->section_content_vc8192; }
  function & set_created($v) { $this->created_dtm = $v; return $this; } function get_created($v = NULL) { if (!is_null($v)) $this->set_created($v); return $this->created_dtm; }
  function & set_updated($v) { $this->updated_dtm = $v; return $this; } function get_updated($v = NULL) { if (!is_null($v)) $this->set_updated($v); return $this->updated_dtm; }
  function & set_slug($v) { $this->slug_vc255uniq = $v; return $this; } function get_slug($v = NULL) { if (!is_null($v)) $this->set_slug($v); return $this->slug_vc255uniq; }
}

