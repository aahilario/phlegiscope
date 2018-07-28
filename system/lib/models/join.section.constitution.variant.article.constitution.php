<?php

/*
 * Class ConstitutionArticleVariantConstitutionSectionJoin
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 * Generated on Sat, 28 Jul 2018 16:04:27 +0000
 */

class ConstitutionArticleVariantConstitutionSectionJoin extends DatabaseUtility {
  
  // Join table model
  var $constitution_article_variant_ConstitutionArticleVariantModel;
  var $constitution_section_ConstitutionSectionModel;

  function __construct() {
    parent::__construct();
    if (C('DEBUG_'.get_class($this))) {
      $this->dump_accessor_defs_to_syslog();
      $this->recursive_dump($this->get_attrdefs(),'(marker) "+++++++"');
    }
  }

  function & set_constitution_article_variant($v) { $this->constitution_article_variant_ConstitutionArticleVariantModel = $v; return $this; }
  function get_constitution_article_variant($v = NULL) { if (!is_null($v)) $this->set_constitution_article_variant($v); return $this->constitution_article_variant_ConstitutionArticleVariantModel; }

  function & set_constitution_section($v) { $this->constitution_section_ConstitutionSectionModel = $v; return $this; }
  function get_constitution_section($v = NULL) { if (!is_null($v)) $this->set_constitution_section($v); return $this->constitution_section_ConstitutionSectionModel; }

}

