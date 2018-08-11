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

  function & fetch_by_linkhash( &$commentary_record, $linkhash )
  {/*{{{*/
    $commentary_record = [];
    $result = $this
      ->set_id(NULL)
      ->syslog( __FUNCTION__, __LINE__, "(marker) -- Processing commentary {$linkhash}..." )
      ->join_all()
      ->where(array("AND" => array('linkhash' => $linkhash)))
      ->record_fetch($commentary_record);
    if ( $result )
      $this
        ->syslog( __FUNCTION__, __LINE__, "(marker) -- DB -- Retrieved commentary #{$commentary_record['id']}" )
        ->recursive_dump($commentary_record, "(marker}    DB ->");
    return $result;
  }/*}}}*/

  function store_commentary_record( & $commentary_record, $stow_data )
  {/*{{{*/
    $commentary_id = $this
      ->syslog( __FUNCTION__, __LINE__, "(marker) -- Inserting commentary for Section ID#{$stow_data['section']} {$stow_data['linkhash']}..." )
      ->set_contents_from_array($stow_data)
      ->stow();
    if ( 0 < $commentary_id ) {
      $join_id = $this
        ->get_join_object('section','join')
        ->set_id(NULL)
        ->set_constitution_commentary($commentary_id)
        ->set_constitution_section($stow_data['section'])
        ->stow();
      $this
        ->syslog( __FUNCTION__, __LINE__, "(marker) -- Created join [{$commentary_id},{$stow_data['section']}] #{$join_id}" );

      $commentary_record = $stow_data;
      $commentary_record['id'] = $commentary_id;
      $commentary_record['section'] = [
        'join' => ['id' => $join_id ],
        'data' => ['id' => $commentary_record['section'] ]
      ];
    }
    else {
      $this
        ->syslog( __FUNCTION__, __LINE__, "(marker) -- Unable to record commentary link #___.{$stow_data['section']}.{$variants_record['id']}  {$component['link']} ({$linkhash})." );

    }
    return $commentary_id;
  }/*}}}*/


}

