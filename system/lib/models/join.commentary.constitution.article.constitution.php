<?php

/*
 * Class ConstitutionArticleConstitutionCommentaryJoin
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 * Generated on Sat, 28 Jul 2018 07:09:37 +0000
 */

class ConstitutionArticleConstitutionCommentaryJoin extends DatabaseUtility {
  
  // Join table model
  var $constitution_article_ConstitutionArticle;
  var $constitution_commentary_ConstitutionCommentaryModel;

  function __construct() {
    parent::__construct();
    if (C('DEBUG_'.get_class($this))) {
      $this->dump_accessor_defs_to_syslog();
      $this->recursive_dump($this->get_attrdefs(),'(marker) "+++++++"');
    }
  }

  function & set_constitution_article($v) { $this->constitution_article_ConstitutionArticle = $v; return $this; }
  function get_constitution_article($v = NULL) { if (!is_null($v)) $this->set_constitution_article($v); return $this->constitution_article_ConstitutionArticle; }

  function & set_constitution_commentary($v) { $this->constitution_commentary_ConstitutionCommentaryModel = $v; return $this; }
  function get_constitution_commentary($v = NULL) { if (!is_null($v)) $this->set_constitution_commentary($v); return $this->constitution_commentary_ConstitutionCommentaryModel; }

}

