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
  var $constitution_ConstitutionVersionModel;
  var $variant_ConstitutionSectionVariantsModel;
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

  function & create_article_revision_join( & $article_record, $article, $constitution_record )
  {/*{{{*/
    $version_to_article = [ $constitution_record['id'] ];
    $result = $this
      ->create_joins('ConstitutionVersionModel',$version_to_article,FALSE); 
    $this->
      syslog(  __FUNCTION__, __LINE__, "(marker) -- - --- - Executed ".__FUNCTION__.", result " . gettype($result))->
      recursive_dump($result, "(marker) -- - --- -");
    $this->fetch_by_slug( $article_record, $article, $constitution_record );
    return $this;
  }/*}}}*/

  function fetch_by_slug( & $article_record, $article, $revision = NULL )
  {/*{{{*/
    $this->
      syslog(  __FUNCTION__, __LINE__, "(marker) -- -- -- Retrieving Article record slug '{$article}'")->
      join_all()->
      where(array('slug' => $article))->
      record_fetch_continuation($article_record);

    if ( !isset($article_record['constitution']['join']['id']) || 
      is_null($article_record['constitution']['join']['id']) ) {
      if ( !is_null($revision) ) {
        $this->create_article_revision_join($article_record, $article, $revision);
        return $this->fetch_by_slug( $article_record, $article );
      }
    }

    //  if ( !isset($article_record['constitution']['join']['id']) || 
    //    is_null($article_record['constitution']['join']['id']) ) {
    //    if ( !is_null($revision) ) {
    //      $this->create_article_revision_join($article_record, $article, $revision);
    //      return $this->fetch_by_slug( $article_record, $article );
    //    }
    //  }

    $this->
      recursive_dump($article_record,"(marker) -- Article -- --");
    return !is_null($article_record);
  }/*}}}*/


  function create_article_record( &$article_record, $slug, $article, $constitution_record )
  {/*{{{*/
    $article_in_db = FALSE;
    $article_record = array(
      'slug'            => $article,
      'constitution'    => isset($constitution_record['id']) ? $constitution_record['id'] : 1,
      'created'         => date('Y-m-d H:i:s', time()),
      'updated'         => date('Y-m-d H:i:s', time()),
      'revision'        => isset($constitution_record['revision']) ? $constitution_record['revision'] : 0,
      'article_content' => ' ',
      'article_title'   => $slug,
    );

    $stow_result = $this
      ->syslog(  __FUNCTION__, __LINE__, "(marker) -- - --- {$selected} Stowing article record '{$article}'")
      ->set_id(NULL)
      ->set_contents_from_array($article_record)
      ->stow();

    $this->
      syslog(  __FUNCTION__, __LINE__, "(marker) -- - --- {$selected} Stow result: " . $stow_result)
      ;

    if ( 0 < $stow_result ) {
      // Refetch record, including joins
      $this->fetch_by_slug( $article_record, $article );
      if ( is_null($article_record['constitution']['join']) ) {
        $this->create_article_revision_join($article_record, $constitution_record, $article);
      }
      $article_in_db = TRUE;
    }
    return $article_in_db;
  }/*}}}*/

}

