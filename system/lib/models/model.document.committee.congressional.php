<?php

/*
 * Class CongressionalCommitteeDocumentModel
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class CongressionalCommitteeDocumentModel extends DatabaseUtility {
  
  var $committee_name_vc256 = NULL;
  var $jurisdiction_vc1024 = NULL;
  var $congress_tag_vc8 = NULL;
  var $create_time_utx = NULL;
  var $last_fetch_utx = NULL;
  var $url_vc4096 = NULL; // TODO: Replace with UrlModel[ {} ]
  var $office_address_vc4096 = NULL;

  var $representative_RepresentativeDossierModel = NULL; // Relationship between a committee and a representative (role, mainly: e.g. chairman, member)
  var $master_document_links_UrlModel = NULL; // Note that this also supersedes the Committee URL

  function __construct() {/*{{{*/
    parent::__construct();
  }/*}}}*/

  function __destruct() {/*{{{*/
    unset($this->committee_name_vc256);
    unset($this->jurisdiction_vc1024);
    unset($this->congress_tag_vc8);
    unset($this->create_time_utx);
    unset($this->last_fetch_utx);
    unset($this->url_vc4096);
    unset($this->office_address_vc4096);
    unset($this->contact_json_vc4096);
    unset($this->representative_RepresentativeDossierModel);
  }/*}}}*/

  function & set_representative($v) { $this->representative_RepresentativeDossierModel = $v; return $this; }
  function get_representative($v = NULL) { return is_null($v) ? $this->representative_RepresentativeDossierModel : array_element($this->representative_RepresentativeDossierModel,$v); }

  function & set_committee_name($v) { $this->committee_name_vc256 = $v; return $this; }
  function get_committee_name($v = NULL) { if (!is_null($v)) $this->set_committee_name($v); return $this->committee_name_vc256; }

  function & set_short_code($v) { $this->short_code_vc32 = $v; return $this; }
  function get_short_code($v = NULL) { if (!is_null($v)) $this->set_short_code($v); return $this->short_code_vc32; }

  function & set_jurisdiction($v) { $this->jurisdiction_vc1024 = $v; return $this; }
  function get_jurisdiction($v = NULL) { if (!is_null($v)) $this->set_jurisdiction($v); return $this->jurisdiction_vc1024; }

  function & set_is_permanent($v) { $this->is_permanent_bool = $v; return $this; }
  function get_is_permanent($v = NULL) { if (!is_null($v)) $this->set_is_permanent($v); return $this->is_permanent_bool; }

  function & set_create_time($v) { $this->create_time_utx = $v; return $this; }
  function get_create_time($v = NULL) { if (!is_null($v)) $this->set_create_time($v); return $this->create_time_utx; }

  function & set_last_fetch($v) { $this->last_fetch_utx = $v; return $this; }
  function get_last_fetch($v = NULL) { if (!is_null($v)) $this->set_last_fetch($v); return $this->last_fetch_utx; }

  function & set_url($v) { $this->url_vc4096 = $v; return $this; }
  function get_url($v = NULL) { if (!is_null($v)) $this->set_url($v); return $this->url_vc4096; }

  function & set_office_address($v) {
    $this->office_address_vc4096 = is_array($v) ? json_encode($v) : $v;
    return $this;
   }
  function get_office_address($v = NULL) { 
    if (!is_null($v)) $this->set_office_address($v);
    return is_string($this->office_address_vc4096) ? json_decode($this->office_address_vc4096,TRUE) : $this->office_address_vc4096;
   }

  function & set_contact_json($v) { $this->contact_json_vc4096 = $v; return $this; }
  function get_contact_json($v = NULL) { if (!is_null($v)) $this->set_contact_json($v); return $this->contact_json_vc4096; }

  function & set_congress_tag($v) { $this->congress_tag_vc8 = $v; return $this; }
  function get_congress_tag($v = NULL) { if (!is_null($v)) $this->set_congress_tag($v); return $this->congress_tag_vc8; }

  function mark_committee_ids_setup(& $a, $k) {/*{{{*/
    if ( !array_key_exists("regex", $a) && array_key_exists("committee_name", $a) ) {
      $a["regex"] = LegislationCommonParseUtility::committee_name_regex($a["committee_name"]);
    }
    if (!array_key_exists("id",$a)) {
      $a["id"] = "UNMAPPED";
    }
    return TRUE;
  }/*}}}*/

  function mark_committee_ids(& $committee_name_list) {/*{{{*/

    $debug_method = FALSE;

    array_walk($committee_name_list,create_function(
      '& $a, $k, $s', 'return $s->mark_committee_ids_setup($a,$k);'
    ), $this);
    $regex_fragments = "REGEXP '(".join('|',array_map(create_function('$a', 'return $a["regex"];'),$committee_name_list)).")'";
    $this->where(array('AND' => array(
      'committee_name' => $regex_fragments
    )))->recordfetch_setup();
    $comm = NULL;
    $n = 0;
    if ( $debug_method ) $this->recursive_dump(array_combine(
      array_keys($committee_name_list),
      array_map(create_function('$a', 'return array("committee_name" => array_element($a,"committee_name"), "regex" => array_element($a,"regex"));'),$committee_name_list)
    ),"(marker) - - - ---- -- -- -- - Before marking");
    while ($this->recordfetch($comm)) {
      // Since we can't match committee names directly, we simply 
      // walk through the result set
      array_walk($committee_name_list,create_function(
        '& $a, $k, $s',
        'if ( 1 == preg_match("@" . $a["regex"] . "@i", $s["committee_name"], $matches) ) { $a["id"] = $s["id"]; $a["committee_name"] = $s["committee_name"]; $a["url"] = $s["url"]; }'
      ),$comm);
      $n++;
      $comm = NULL;
      unset($comm);
    }
    if ( $debug_method ) {
      $this->syslog( __FUNCTION__, __LINE__,"(marker) - - - - Matched {$n} entries using {$regex_fragments}");
      $this->recursive_dump( $committee_name_list, "(marker) - - - - - -");
      $this->syslog( __FUNCTION__, __LINE__,"(marker) - - - - DONE -- -- -- -- -- -- -- -- --");
    }
    return $n;
  }/*}}}*/

  function cache_parsed_housebill_records_commupdate(& $a, $k) {/*{{{*/
    $committee_name = $a["meta"]["main-committee"]["raw"];
    $map_entry      = array_element($this->committee_regex_lookup,$committee_name,array());
    $a["meta"]["main-committee"]["mapped"] = intval(array_element($map_entry,"id"));
    if (0 == $a["meta"]["main-committee"]["mapped"]) {
      unset($a["committee"]);
    }
    else {
      $a["meta"]["main-committee"]["url"]            = $map_entry['url'];
      $a["meta"]["main-committee"]["committee_name"] = $map_entry['committee_name'];
    } 
  }/*}}}*/

  function update_committee_name_regex_lookup(& $bill_cache, & $committee_regex_lookup) {/*{{{*/

    $unmapped_committee_entries = array_filter(array_map(create_function(
      '$a', 'return "UNMAPPED" == array_element($a,"id") ? $a : NULL;'
    ),$committee_regex_lookup));

    gc_collect_cycles();

    $fixed_mapping = 0;

    if ( 0 < count($unmapped_committee_entries) ) {

      $this->mark_committee_ids($unmapped_committee_entries);

      foreach( $unmapped_committee_entries as $name => $entry ) {
        if ( !(array_element($entry,'id','UNMAPPED') == 'UNMAPPED') ) { 
          $fixed_mapping++;
          $committee_regex_lookup[$name]['id'] = $entry['id'];
          $committee_regex_lookup[$name]['name'] = $entry['committee_name'];
          $committee_regex_lookup[$name]['url'] = $entry['url'];
        }
      }
      ksort($committee_regex_lookup);

			$this->committee_regex_lookup = $committee_regex_lookup;
      // Update bill cache with committee names, IDs, and URLs.
      array_walk($bill_cache,create_function(
        '& $a, $k, $s', '$s->cache_parsed_housebill_records_commupdate($a,$k);'
      ),$this);
			$this->committee_regex_lookup = NULL; 
    }

    return $fixed_mapping;
  }/*}}}*/

}
