<?php

/*
 * Class ConstitutionArticleModel
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 * Generated on Tue, 24 Jul 2018 15:15:17 +0000
 */

class ConstitutionArticleModel extends DatabaseUtility {
  
  var $article_title_vc512 = NULL;
  var $article_content_vc8192 = NULL;
  var $constitution_ConstitutionVersion;
  var $slug_vc255 = '';
  var $revision_int8 = 0;
  var $created_dtm;
  var $updated_dtm;

  function __construct() {
    parent::__construct();
  }

  function & set_slug($v) { $this->slug_vc255 = $v; return $this; } function get_slug($v = NULL) { if (!is_null($v)) $this->set_slug($v); return $this->slug_vc255; }
  function & set_created($v) { $this->created_dtm = $v; return $this; } function get_created($v = NULL) { if (!is_null($v)) $this->set_created($v); return $this->created_dtm; }
  function & set_updated($v) { $this->updated_dtm = $v; return $this; } function get_updated($v = NULL) { if (!is_null($v)) $this->set_updated($v); return $this->updated_dtm; }
  function & set_revision($v) { $this->revision_int8 = $v; return $this; } function get_revision($v = NULL) { if (!is_null($v)) $this->set_revision($v); return $this->revision_int8; }
  function & set_article_title($v) { $this->article_title_vc512 = $v; return $this; } function get_article_title($v = NULL) { if (!is_null($v)) $this->set_article_title($v); return $this->article_title_vc512; }
  function & set_article_content($v) { $this->article_content_vc8192 = $v; return $this; } function get_article_content($v = NULL) { if (!is_null($v)) $this->set_article_content($v); return $this->article_content_vc8192; }
  function & set_constitution($v) { $this->constitution_ConstitutionVersion = $v; return $this; } function get_constitution($v = NULL) { if (!is_null($v)) $this->set_constitution($v); return $this->constitution_ConstitutionVersion; }
}

