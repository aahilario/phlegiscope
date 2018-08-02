<?php

/*
 * Class ConstitutionVersionModel
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 * Generated on Tue, 24 Jul 2018 16:26:03 +0000
 */

class ConstitutionVersionModel extends DatabaseUtility {
  
  var $article_ConstitutionArticleModel;
  var $title_vc255 = 0;
  var $short_title_vc128 = 0;
  var $notes_blob = NULL;
  var $revision_int8 = 0;
  var $created_dtm;
  var $updated_dtm;

  function __construct() {
    parent::__construct();
  }

  function fetch_by_revision(& $constitution_record, $const_ver ) 
  {
    $constitution_record = NULL;
    if ( is_int($const_ver) && ( 0 < intval($const_ver) ) )
    $this->
      syslog(  __FUNCTION__, __LINE__, "(marker) -- -- -- Retrieving Version record rev {$const_ver}")->
      join_all()->
      where(array('revision' => $const_ver))->
      record_fetch_continuation($constitution_record)->
      recursive_dump($constitution_record,"(marker) -- Version -- --");
    return !is_null($constitution_record);
  }

}

