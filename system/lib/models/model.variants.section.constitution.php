<?php

/*
 * Class ConstitutionSectionVariantsModel
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 * Generated on Sat, 28 Jul 2018 20:18:47 +0000
 */

class ConstitutionSectionVariantsModel extends DatabaseUtility {
  
  var $article_ConstitutionArticleModel;
  var $section_ConstitutionSectionModel;

  function __construct() {
    parent::__construct();
  }

  function & generate_joinwith_article( & $variants_record, $article_record ) 
  {/*{{{*/
    $variants_record = [];
    if ( is_null($this->get_id()) )
      $this->generate( $variants_record );
    else $this
      ->where([ 'id' => $this->get_id() ])
      ->record_fetch_continuation( $variants_record );

    $article_join_obj = $this->get_join_object('article','join');

    $join_attribs = [
      'constitution_article' => $article_record['id'],
      'constitution_section_variants' => $variants_record['id'],
    ];
    $article_join_obj
      ->set_contents_from_array($join_attribs)
      ->stow();

    return $this;
  }/*}}}*/

  function generate( & $variants_record )
  {/*{{{*/
    $variants_record = NULL;
    $variant_id = $this
      ->set_id(NULL)
      ->join_all()
      ->stow();
    if ( 0 < $variant_id )
      return $this
        ->join_all()
        ->where(array("id" => $variant_id))
        ->record_fetch_continuation($variants_record)
        ->syslog(  __FUNCTION__, __LINE__, is_null($variants_record)
        ? "(marker) -- Failed to retrieve newly-created variant record"
        : "(marker) -- [1] Generated variants record #{$variants_record['id']}"
      );
    return $this;
  }/*}}}*/


}

