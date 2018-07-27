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
  var $constitution_ConstitutionVersion = NULL;
  var $slug_vc255 = '';
  var $created_dtm;
  var $updated_dtm;

  function __construct() {
    parent::__construct();
  }

}

