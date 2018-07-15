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
  var $public_title_vc256 = NULL;

  var $sb_precursors_SenateBillDocumentModel;
  var $hb_precursors_HouseBillDocumentModel;
  var $ocrcontent_ContentDocumentModel = NULL; // Used here to contain OCR versions  

  function __construct() {
    parent::__construct();
  }

  function & set_ocrcontent($v) { $this->ocrcontent_ContentDocumentModel = $v; return $this; }
  function get_ocrcontent($v = NULL) { if (!is_null($v)) $this->set_content($v); return $this->ocrcontent_ContentDocumentModel; }

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

  function non_session_linked_document_stow($document, $allow_update = false) {/*{{{*/
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
    $id = $this->
      set_contents_from_array($document)->
      fields(array_keys($document))->
      stow();
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

  function final_cleanup_parsed_housebill_cache(& $a, $k) {/*{{{*/

    // Document-specific attribute remapping (parsed -> displayed) 
    // Must generate output suitable for use in CongressCommonParseUtility::seek_postparse() 

    $debug_method = FALSE;
    // Move nested array elements into place, to allow use of 
    // set_contents_from_array()
    $links = array_element($a,"links",array());
    $meta  = array_element($a,"meta",array());

    $a["url"]           = trim(array_shift($links),'/');
    $a["origin"]        = array_element($meta,"origin");
    $a["approval_date"] = array_element($meta,"approval-date");
    $a["create_time"]   = time();
    $a["std_sn"]        = static::standard_sn($a["sn"]);

    if ( empty($a["url"]) ) $a["url"] = '--'; // Replace these with extant Republic Act document model URLs

    return TRUE;

    // Obtain principal author record ID.
    // It is possible to map this using the Republic Act [origin] attribute,
    // by finding Senate Bill / House Bill principal authors.
    if ( !is_null(($principal_author = array_element($meta,'principal-author'))) ) {/*{{{*/
      $original_name = $principal_author;
      $mapped = $this->
        get_foreign_obj_instance('representative')->
        replace_legislator_names_hotlinks($principal_author);
      $mapped = !is_null($mapped) ? array_element($principal_author,'id') : NULL;
      if ( !is_null($mapped) ) {
        $a['representative'] = array(
          'relation_to_bill' => 'principal-author',
        );
        $a['meta']['principal-author'] = array(
          'raw' => $original_name,
          'new' => "{$original_name} ({$principal_author['fullname']})",
          'parse' => $principal_author,
          'mapped' => $mapped,
        );
      }
    }/*}}}*/

    return TRUE;
  }/*}}}*/

  static function standard_sn($s) {/*{{{*/
    $c = array();
    if ( !(1 == preg_match('@^RA[0]*([0-9]*)@i',$s,$c)) ) {
      return NULL; 
    }
    else if ( !array_key_exists(1,$c) || (intval($c[1]) != intval(ltrim(preg_replace('@[^0-9]@i','',$s),'0'))) ) {
      return NULL;
    }
    return "RA" . str_pad($c[1],5,'0',STR_PAD_LEFT);
  }/*}}}*/

  function cache_parsed_records(& $bill_cache_source, $congress_tag, $from_network) {/*{{{*/

    // WARNING: Do not use this method to cache more than a handful of
    // records at a time.  The array $bill_cache_source is processed in memory,
    // and executes a database cursor to rectify missing URLs.
    $this->committee_regex_lookup = array();

    $debug_method = FALSE;

    if ( !is_array($bill_cache_source) ) {
      $this->syslog(__FUNCTION__,__LINE__,"(marker) Nothing parsed. Leaving.");
      return;
    }

    $bill_cache = array_map(create_function('$a','return array_element($a,"sn");'),$bill_cache_source);

    if (is_array($bill_cache) && (0 < count($bill_cache))) {
      $bill_cache = array_combine(
        $bill_cache,
        $bill_cache_source
      );
    } else return;

    // Add last_fetch and congress_tag attributes
    array_walk($bill_cache,create_function(
      '& $a, $k, $s', 'if ( $s["n"] ) $a["last_fetch"] = time(); if ( is_null(array_element($a,"congress_tag"))) $a["congress_tag"] = $s["c"];'
    ),array('n' => $from_network, 'c' => intval($congress_tag)));

    // Cleanup (remap array elements) before testing for omitted,
    // preexisting records
    array_walk($bill_cache,create_function(
      '& $a, $k, $s', '$s->final_cleanup_parsed_housebill_cache($a,$k);'
    ), $this);

    $sns = join('|',array_keys($bill_cache));
    $this->
      join_all()->
      where(array('AND' => array('`a`.sn' => "REGEXP '({$sns})'")))->
      recordfetch_setup();

    $bill_cache_source = $bill_cache;
    $bill_cache = NULL;

    while ( $this->recordfetch($sns,TRUE) ) {
      $std_sn = static::standard_sn($sns['sn']); // Ensure we get a key match
      // Add URLs missing from the parsed document source.
      $bill_cache_source[$std_sn]['url'] = str_replace('--' , $sns['url'], $bill_cache_source[$std_sn]['url']);
      $bill_cache_source[$std_sn]['links']['filed'] = $sns['url'];
      $this->find_unresolved_origin_joins($sns);
      $origins = array();
      if ( 1 == preg_match('@^([^ (]*)[ (]*(.*)/(.*)[)]@i', $bill_cache_source[$std_sn]['meta']['origin'], $origins)) {
        // Decompose the [origin] string, to extract the chamber name (Senate / House).
        // Then simply use the sb_precursor / hb_precursor attributes to reconstitute the string
        // with embedded URLs
        $origin = array_element($origins,1);
        $origins = array_filter(array(
          'sb_precursors' => nonempty_array_element($sns['sb_precursors'],'data'),
          'hb_precursors' => nonempty_array_element($sns['hb_precursors'],'data')
        ));
        array_walk($origins,create_function(
          '& $a, $k, $s', '$a = $s->create_sn_url($a, $k);'
        ),$this);
        $origins = join(' / ',$origins);
        $bill_cache_source[$std_sn]['meta']['origin'] = "{$origin} ({$origins})";
      }
    }

  }/*}}}*/

  function create_sn_url($a, $k) {/*{{{*/
    // <a class="legiscope-remote" href="/contents/legislative-executive-catalog/house-bills/document/{$a['sn']}">{$a['sn']}</a>
    $id = UrlModel::get_url_hash($a['url']);
    return $k == 'hb_precursors' 
      ? <<<EOH
<a class="legiscope-remote {cache-state-{$id}}" id="{$id}" href="{$a['url']}">{$a['sn']}</a>
EOH
       : <<<EOH
<a class="legiscope-remote {cache-state-{$id}}" id="{$id}" href="{$a['url']}">{$a['sn']}</a>
EOH;
  }/*}}}*/

  function remap_url($url) {
    return $url;
  }
}
