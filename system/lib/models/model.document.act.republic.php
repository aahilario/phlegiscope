<?php

/*
 * Class RepublicActDocumentModel
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class RepublicActDocumentModel extends SenateDocCommonDocumentModel {
  
  var $title_vc256uniq = NULL;
  var $sn_vc64uniq = NULL;
  var $origin_vc2048 = NULL;
  var $approval_date_vc64 = NULL;
  var $description_vc4096 = NULL;
  var $create_time_utx = NULL;
  var $last_fetch_utx = NULL;
  var $searchable_bool = NULL;
  var $congress_tag_vc8 = NULL;
  var $url_vc4096 = NULL;
  var $content_json_vc65535 = NULL;
  var $content_blob = NULL;

  var $sb_precursors_SenateBillDocumentModel; // TODO: Link Republic Act records to precursor Senate Bills
  var $hb_precursors_HouseBillDocumentModel; // TODO: Link Republic Act records to precursor Congress House Bills
  var $versions_UrlModel;

  function __construct() {
    parent::__construct();
  }

	// set_data_ setters are used by get_stuffed_join()
  function & set_data_sb_precursors($v) { $this->sb_precursors_SenateBillDocumentModel['data'] = $v; return $this; }
  function & set_data_hb_precursors($v) { $this->hb_precursors_HouseBillDocumentModel['data']  = $v; return $this; }

  function get_sb_precursors() { return $this->sb_precursors_SenateBillDocumentModel; }
  function get_hb_precursors() { return $this->hb_precursors_HouseBillDocumentModel; }

  function & set_title($v) { $this->title_vc256uniq = $v; return $this; }
  function get_title($v = NULL) { if (!is_null($v)) $this->set_title($v); return $this->title_vc256uniq; }

  function & set_sn($v) { $this->sn_vc64uniq = $v; return $this; }
  function get_sn($v = NULL) { if (!is_null($v)) $this->set_sn($v); return $this->sn_vc64uniq; }

  function & set_origin($v) { $this->origin_vc2048 = $v; return $this; }
  function get_origin($v = NULL) { if (!is_null($v)) $this->set_origin($v); return $this->origin_vc2048; }

  function & set_approval_date($v) { $this->approval_date_vc64 = $v; return $this; }
  function get_approval_date($v = NULL) { if (!is_null($v)) $this->set_approval_date($v); return $this->approval_date_vc64; }

  function & set_desc($v) { $this->description_vc4096 = $v; return $this; }
  function get_desc($v = NULL) { if (!is_null($v)) $this->set_desc($v); return $this->description_vc4096; }

  function & set_description($v) { $this->description_vc4096 = $v; return $this; }
  function get_description($v = NULL) { if (!is_null($v)) $this->set_description($v); return $this->description_vc4096; }

  function & set_create_time($v) { $this->create_time_utx = $v; return $this; }
  function get_create_time($v = NULL) { if (!is_null($v)) $this->set_create_time($v); return $this->create_time_utx; }

  function & set_last_fetch($v) { $this->last_fetch_utx = $v; return $this; }
  function get_last_fetch($v = NULL) { if (!is_null($v)) $this->set_last_fetch($v); return $this->last_fetch_utx; }

  function & set_searchable($v) { $this->searchable_bool = $v; return $this; }
  function get_searchable($v = NULL) { if (!is_null($v)) $this->set_searchable($v); return $this->searchable_bool; }

  function & set_congress_tag($v) { $this->congress_tag_vc8 = $v; return $this; }
  function get_congress_tag($v = NULL) { if (!is_null($v)) $this->set_congress_tag($v); return $this->congress_tag_vc8; }

  function & set_url($v) { $this->url_vc4096 = $v; return $this; }
  function get_url($v = NULL) { if (!is_null($v)) $this->set_url($v); return $this->url_vc4096; }

  function & set_content_json($v) { $this->content_json_vc65535 = $v; return $this; }
  function get_content_json($v = NULL) { if (!is_null($v)) $this->set_content_json($v); return $this->content_json_vc65535; }

  function & set_content($v) { $this->content_blob = $v; return $this; }
  function get_content($v = NULL) { if (!is_null($v)) $this->set_content($v); return $this->content_blob; }

  function non_session_linked_document_stow($document) {/*{{{*/
    // Override parent::non_session_linked_document_stow
    unset($document['text']);
    // $this->recursive_dump($document,"(marker) --");
    $this->where(array('AND' => array(
      'sn' => trim($document['sn']),
    )))->recordfetch_setup();
    $record = array();
    $id = $this->recordfetch($record,TRUE) ? $this->get_id() : NULL;
    // $this->recursive_dump($record,"(marker) --- - {$document['sn']} - --");
    // $this->syslog( __FUNCTION__, __LINE__, "(marker) Stow #{$id} {$document['text']} {$document['url']}");
    $id = $this->set_contents_from_array($document)->stow();
    return NULL;
  } /*}}}*/

  function resolve_republic_act_consequents(& $bill_cache, $attrname) {/*{{{*/

    $debug_method = FALSE;

    $republic_act_antecedents = array_filter(array_map(create_function('$a','return is_array(nonempty_array_element($a["meta"],"status")) ? $a["meta"]["status"] : NULL;'),$bill_cache));
    $this->filter_nested_array($republic_act_antecedents,"sn[referent*=republic-act]");
    if ( $debug_method ) $this->recursive_dump($republic_act_antecedents,"(marker) " . __METHOD__);
    if ( !(0 < count($republic_act_antecedents) ) ) return;
    $this->where(array('AND' => array('sn' => $republic_act_antecedents)))->recordfetch_setup();

    $ra = array();
    while ( $this->recordfetch($ra) ) {/*{{{*/
      $sn = array_element($ra,'sn');
      $hb = array_element(array_flip($republic_act_antecedents),$sn);
      if ( empty($hb) ) continue;
      if ( !array_key_exists($hb,$bill_cache) ) continue;
      $bill_cache[$hb][$attrname] = array(
        'fkey' => $ra['id'],
      );
      $bill_cache[$hb]['meta']['status']['mapped'] = $ra['id'];
      if ( $debug_method ) $this->syslog(__FUNCTION__,__LINE__,"(marker)  Joinable {$hb} => {$sn}"); 
    }/*}}}*/

  }/*}}}*/

  function find_unresolved_origin_joins(& $ra) {/*{{{*/
    // Null out array components that are empty arrays e.g.
    // hb_precursor => array('join' => NULL, 'data' => NULL)
    array_walk($ra,create_function(
      '& $a, $k', 'if ( is_array($a) ) { $a = array_filter($a); if ( empty($a) ) $a = NULL; }'
    ));
    // If neither HB nor SB precursor Joins are empty, return NULL 
    if ( !(is_null($ra['hb_precursors']) || is_null($ra['sb_precursors'])) ) return NULL;
    $components = array();
    // Explode the [origin] string
    if ( !(1 == preg_match('@^([^(]*)\([^A-Z]*([A-Z]+)([0-9O]+)[^A-Z]*([A-Z]+)([0-9O]+)[^)]*\)@i', $ra['origin'], $components)) ) return FALSE;
    $map = array(
      'HB' => 'hb_precursors',
      'SB' => 'sb_precursors',
    );
    // Turn the regex match result into an array ( 'hb_precursors' => <hb_precursor>, 'sb_precursors' => <sb_precursor> )
    $components = array_filter(array(
      array_element($map,array_element($components,2)) => array_element($components,2) . array_element($components,3),
      array_element($map,array_element($components,4)) => array_element($components,4) . array_element($components,5),
    ));
    // Remove a precursor entry with SN suffix of all zeroes ('SN00000')
    array_walk($components,create_function(
      '& $a, $k, $s', 'if ( array_key_exists(rtrim($a,"0"),$s) ) $a = NULL;'
    ),$map);
    $components = array_filter($components);
    // Only generate a component if the existing $ra[attribute] is NULL
    array_walk($components,create_function(
      '& $a, $k, $s','$a = is_null(array_element($s,$k)) ? array("raw" => array("sn" => $a, "congress_tag" => array_element($s,"congress_tag")), "fkey" => NULL) : NULL;'
    ),$ra);
    $components = array(
      'data'       => $ra,
      'precursors' => array_filter($components),
    );
    if ( 0 < count($components['precursors']) ) $this->syslog(__FUNCTION__,__LINE__,"(marker) Precursor test for {$ra['sn']} yields matches.");
    else return NULL;
    return $components;
  }/*}}}*/

  function fix_unresolved_origin_joins(& $legislation_precursors) {/*{{{*/
    if ( !is_array($legislation_precursors) || 
      ( !count($legislation_precursors) ) ) return FALSE;

    $this->recursive_dump($legislation_precursors,"(marker) ---");

    // There are at most two precursor documents 
		$updated = 0;
    foreach ( $legislation_precursors as $id => $p ) {
      $this->set_contents_from_array($p['data'],TRUE);
      $this->syslog(__FUNCTION__,__LINE__,"(marker) {$p['data']['id']} v. " . $this->get_id());
      $precursor = array_element($p,'precursors');
      foreach ( $precursor as $precursor_attr => $precursor_data ) {/*{{{*/
        // The element $precursor_data['raw']['sn'] contains the raw document
        // serial number.  Find it's foreign key, store that, and move on...
        $raw = array_element($precursor_data,'raw');
        $objtype = get_class($this->get_foreign_obj_instance($precursor_attr));
        // FIXME:  Revert to plain string comparison after modifying EITHER
        // - Senate Bill SN format, or
        // - House Bill SN format HB{%05d}
        // Deal with inconsistent precursor doc serial number formatting:
        // Senate bills are given serial numbers SBN-{%d},
        // House bills use HBN{%05d}.  There should be no more than a few tens
        // of thousands of SB and HB records at any time, so that a regex
        // match on these prefixes is tenable (for low-traffic sites).
        $sn_parts = array();
        // Convert the SN to a simple regex expression.
        preg_match('@([A-Z]*)([0]*)([0-9]*)@i', $raw['sn'], $sn_parts);
        $raw['sn'] = "{$sn_parts[1]}(.*){$sn_parts[3]}";
        $this->recursive_dump($sn_parts,"(marker) - - - -");
        $result = $this->
          get_foreign_obj_instance($precursor_attr)->
          where(array('AND' => array(
            'sn' => "REGEXP '({$raw['sn']})'",
            'congress_tag' => $raw['congress_tag'],
          )))->recordfetch_setup();
        $result_type = gettype($result);
        $this->syslog(__FUNCTION__,__LINE__,"(marker) - Got {$objtype} ref {$precursor_data['raw']['sn']} result {$result_type}");
        // This loop should only ever execute once for each missing foreign
        // object Join referent.
        $result = array();
        while ( $this->recordfetch($result) ) {
          $fkey = array_element($result,'id');
          $this->syslog(__FUNCTION__,__LINE__,"(marker) + #{$fkey} {$result['sn']}");
          // Store the newly-identified FT foreign key, 
          if ( is_null($precursor[$precursor_attr]['fkey']) && 
            (1 == preg_match("@{$raw['sn']}@i",$result['sn'])) &&
             ($result['congress_tag'] == $raw['congress_tag']) ) {
            $precursor[$precursor_attr]['create_time'] = time();
            $precursor[$precursor_attr]['fkey']        = $fkey;
            unset($precursor[$precursor_attr]['raw']);
          }
        }
      }/*}}}*/
      $this->fetch($id);
      $result = $this->
        set_contents_from_array($precursor)->
        stow();
      if ( 0 < intval($result) ) {
        $this->syslog(__FUNCTION__,__LINE__,"(marker) - - - Commit changes to these attrs");
        $this->recursive_dump($precursor,"(marker) -+-+-+-+-");  
				$updated++;
      }
    }  
		return $updated;
  }/*}}}*/

}
