<?php

/*
 * Class ConstitutionArticleConstitutionSectionJoin
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 * Generated on Wed, 25 Jul 2018 09:58:33 +0000
 */

class ConstitutionArticleConstitutionSectionJoin extends DatabaseUtility {
  
  // Join table model
  var $constitution_article_ConstitutionArticleModel;
  var $constitution_section_ConstitutionSectionModel;
  var $created_dtm;
  var $updated_dtm;

  function __construct() {
    parent::__construct();
    if ( C('DEBUG_'.get_class($this)) ) {
      $this->dump_accessor_defs_to_syslog();
      $this->recursive_dump($this->get_attrdefs(),'(marker) "+++++++"');
    }
  }

  function & set_constitution_article($v) { $this->constitution_article_ConstitutionArticleModel = $v; return $this; }
  function get_constitution_article($v = NULL) { if (!is_null($v)) $this->set_constitution_article($v); return $this->constitution_article_ConstitutionArticleModel; }

  function & set_constitution_section($v) { $this->constitution_section_ConstitutionSectionModel = $v; return $this; }
  function get_constitution_section($v = NULL) { if (!is_null($v)) $this->set_constitution_section($v); return $this->constitution_section_ConstitutionSectionModel; }

}

