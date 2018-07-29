<?php

/*
 * Class ConstitutionCommentaryModel
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 * Generated on Thu, 26 Jul 2018 07:40:44 +0000
 */

class ConstitutionCommentaryModel extends DatabaseUtility {
  
  var $summary_vc8192;
  var $title_vc512;
  var $link_vc1024; // Slug
  var $linkhash_vc128; // Slug hash
  var $section_ConstitutionSectionModel;
  var $approved_bool;
  var $added_dtm;
  var $updated_dtm;

  function __construct() {
    parent::__construct();
  }

  function & set_linkhash($v) { $this->linkhash_vc128 = $v; return $this; } function get_linkhash($v = NULL) { if (!is_null($v)) $this->set_linkhash($v); return $this->linkhash_vc128; }
  function & set_summary($v) { $this->summary_vc8192 = $v; return $this; } function get_summary($v = NULL) { if (!is_null($v)) $this->set_summary($v); return $this->summary_vc8192; }
  function & set_title($v) { $this->title_vc512 = $v; return $this; } function get_title($v = NULL) { if (!is_null($v)) $this->set_title($v); return $this->title_vc512; }
  function & set_link($v) { $this->link_vc1024 = $v; return $this; } function get_link($v = NULL) { if (!is_null($v)) $this->set_link($v); return $this->link_vc1024; }
  function & set_approved($v) { $this->approved_bool = $v; return $this; } function get_approved($v = NULL) { if (!is_null($v)) $this->set_approved($v); return $this->approved_bool; }
  function & set_added($v) { $this->added_dtm = $v; return $this; } function get_added($v = NULL) { if (!is_null($v)) $this->set_added($v); return $this->added_dtm; }
  function & set_updated($v) { $this->updated_dtm = $v; return $this; } function get_updated($v = NULL) { if (!is_null($v)) $this->set_updated($v); return $this->updated_dtm; }
}

