<?php

/*
 * Class ConstitutionArticleConstitutionVersionJoin
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 * Generated on Sat, 28 Jul 2018 07:09:08 +0000
 */

class ConstitutionArticleConstitutionVersionJoin extends DatabaseUtility {
  
  // Join table model
  var $constitution_article_ConstitutionArticleModel;
  var $constitution_version_ConstitutionVersion;

  function __construct() {
    parent::__construct();
    if (C('DEBUG_'.get_class($this))) {
      $this->dump_accessor_defs_to_syslog();
      $this->recursive_dump($this->get_attrdefs(),'(marker) "+++++++"');
    }
  }

  function & set_constitution_article($v) { $this->constitution_article_ConstitutionArticleModel = $v; return $this; }
  function get_constitution_article($v = NULL) { if (!is_null($v)) $this->set_constitution_article($v); return $this->constitution_article_ConstitutionArticleModel; }

  function & set_constitution_version($v) { $this->constitution_version_ConstitutionVersion = $v; return $this; }
  function get_constitution_version($v = NULL) { if (!is_null($v)) $this->set_constitution_version($v); return $this->constitution_version_ConstitutionVersion; }

}

