<?php

/*
 * Class HouseBillDocumentModel
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class HouseBillDocumentModel extends RepublicActDocumentModel {
  
  var $sn_vc64uniq = NULL;
  var $description_vc4096 = NULL;
  var $create_time_utx = NULL;
  var $last_fetch_utx = NULL;
  var $searchable_bool = NULL;
  var $congress_tag_vc8 = NULL;
  var $url_vc4096 = NULL; // Filed
  var $url_history_vc4096 = NULL; // Whenever available
  var $url_engrossed_vc4096 = NULL;
  var $status_vc1024 = NULL;

  // The content of linked documents, e.g. those referred to within House Bill source pages,
  // should probably be stored as Join edges, to facilitate revision marking.
  // That way, only immutable properties are stored in DocumentModel objects;
  // this means edges (relationships between graph nodes) carry payload data
  // aside from their name (which indicates that it is a generic relation); and
  // subclassing *Joins can then allow behaviors to be assigned that manipulate
  // that relationship.  Right now the only shared behaviors that make sense to
  // implement on generic *Joins are stow() and fetch().  I would like to implement
  // a factory subclass that allows a Pending in Committee state (which implies
  // a start date) to implement a schedule_reading($on_date) method, which marks
  // the Pending state as having ended (marked with an end date), and that additionally
  // generates a new *Join record (graph edge) with a scheduled[date] reading stage[nth reading]. 

  //var $date_read_utx = NULL;
  //var $house_approval_date_utx = NULL;
  //var $significance_vc16 = NULL;

  var $housebill_HouseBillDocumentModel = NULL; // Reference to other house bills, indicating the relationship (e.g. substitution).
  var $republic_act_RepublicActDocumentModel = NULL; // Republic Act toward which this House Bill contributed essential language and intent. 
  var $representative_RepresentativeDossierModel = NULL; // Several types of association between a house bill and representatives (authorship, etc.).
  var $content_UrlModel = NULL; // Content BLOB edges.
  var $committee_CongressionalCommitteeDocumentModel = NULL; // Reference to Congressional Committees, bearing the status of a House Bill, or the principal committee.

  function __construct() {/*{{{*/
    parent::__construct();
  }/*}}}*/

  function __destruct() {/*{{{*/
    unset($this->sn_vc64uniq);
    unset($this->description_vc4096);
    unset($this->create_time_utx);
    unset($this->last_fetch_utx);
    unset($this->searchable_bool);
    unset($this->congress_tag_vc8);
    unset($this->url_vc4096);
    unset($this->url_history_vc4096);
    unset($this->url_engrossed_vc4096);
    unset($this->status_vc1024);
    unset($this->date_read_utx);
    unset($this->house_approval_date_utx);
    unset($this->significance_vc16);
    unset($this->housebill_HouseBillDocumentModel);
    unset($this->republic_act_RepublicActDocumentModel);
    unset($this->representative_RepresentativeDossierModel);
    unset($this->content_UrlModel);
    unset($this->committee_CongressionalCommitteeDocumentModel);
  }/*}}}*/

	function & set_id($v) { $this->id = $v; return $this; }

  function & set_status($meta) {/*{{{*/
    $this->status_vc1024 = is_array($meta)
      ?  json_encode($meta)
      : $meta
      ;
    if ( FALSE == $this->status_vc1024 ) $this->status_vc1024 = json_encode(array());
    return $this;
  }/*}}}*/
  function get_status($as_array = TRUE) {/*{{{*/
    if ( is_null($this->status_vc1024) ) {
      $parsed = $as_array ? array() : NULL;
    } else if ( is_array($this->status_vc1024) ) {
      $parsed = @json_encode($this->status_vc1024);
      if ( FALSE == $parsed ) {
        $parsed = $as_array ? array() : NULL;
      } else {
        $this->status_vc1024 = $parsed;
        if ( $as_array ) $parsed = @json_decode($parsed,TRUE);
      }
    } else if ( is_string($this->status_vc1024) ) {
      $parsed = $as_array ? @json_decode($this->status_vc1024,TRUE) : $this->status_vc1024;
      if ( FALSE === $parsed ) {
        $parsed = $as_array ? array() : NULL;
      } else {
      }
    }
    return $parsed;
  }/*}}}*/

  function & set_sn($v) { $this->sn_vc64uniq = $v; return $this; }
  function get_sn($v = NULL) { if (!is_null($v)) $this->set_sn($v); return $this->sn_vc64uniq; }

  function & set_last_fetch($v) { $this->last_fetch_utx = $v; return $this; }
  function get_last_fetch($v = NULL) { if (!is_null($v)) $this->set_last_fetch($v); return $this->last_fetch_utx; }

  function & set_description($v) { $this->description_vc4096 = $v; return $this; }
  function get_description($v = NULL) { if (!is_null($v)) $this->set_description($v); return $this->description_vc4096; }

  function & set_congress_tag($v) { $this->congress_tag_vc8 = $v; return $this; }
  function get_congress_tag($v = NULL) { if (!is_null($v)) $this->set_congress_tag($v); return $this->congress_tag_vc8; }

  function & set_url($v) { $this->url_vc4096 = $v; return $this; }
  function get_url($v = NULL) { if (!is_null($v)) $this->set_url($v); return $this->url_vc4096; }  

  function & set_url_history($v) { $this->url_history_vc4096 = $v; return $this; }
  function get_url_history($v = NULL) { if (!is_null($v)) $this->set_url_history($v); return $this->url_history_vc4096; }

  function & set_url_engrossed($v) { $this->url_engrossed_vc4096 = $v; return $this; }
  function get_url_engrossed($v = NULL) { if (!is_null($v)) $this->set_url_engrossed($v); return $this->url_engrossed_vc4096; }

  // function & set_date_read($v) { $this->date_read_utx = $v; return $this; }
  // function get_date_read($v = NULL) { if (!is_null($v)) $this->set_date_read($v); return $this->date_read_utx; }

  // function & set_house_approval_date($v) { $this->house_approval_date_utx = $v; return $this; }
  // function get_house_approval_date($v = NULL) { if (!is_null($v)) $this->set_house_approval_date($v); return $this->house_approval_date_utx; }

  // function & set_significance($v) { $this->significance_vc16 = $v; return $this; }
  // function get_significance($v = NULL) { if (!is_null($v)) $this->set_significance($v); return $this->significance_vc16; }

	///////////////////////////////////////////////////////////////////////

  function final_cleanup_parsed_housebill_cache(& $a, $k) {/*{{{*/

    // Move nested array elements into place, to allow use of 
    // set_contents_from_array()
    $links = array_element($a,"links",array());
    $meta  = array_element($a,"meta",array());

    $a["url"] = array_element($links,"filed");
    $a["url_engrossed"] = array_element($links,"engrossed");
    $a["url_history"] = array_element($links,"url_history");
    $a["status"] = array_element($meta,"status");
    $a["create_time"] = time();

    // Obtain principal author record ID
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

    // Obtain primary committee record ID
    if ( !is_null(($main_committee = array_element($meta,'main-committee'))) ) {/*{{{*/

      $a['committee'] = array(
        'jointype' => 'main-committee',
      );

      // Perform tree search (PHP assoc array), as it will be inefficient to 
      // try to look up each name as it recurs in the input stream.
      // We'll assume that there will be (at most) a couple hundred distinct committee names
      // dealt with here, so we can reasonably generate a lookup table
      // of committee names containing name match regexes and committee record IDs.
      $name = $main_committee;
      if ( !array_key_exists($name,$this->committee_regex_lookup) ) {/*{{{*/
        // Test for a full regex match against the entire lookup table 
        // before adding the committee name and regex pattern to the tree
        $committee_name_regex = LegislationCommonParseUtility::committee_name_regex($name);
        if ( 0 < count($this->committee_regex_lookup) ) {/*{{{*/
          $m = array_filter(array_combine(
            array_keys($this->committee_regex_lookup),
            array_map(create_function(
              '$a', 'return 1 == preg_match("@" . array_element($a,"regex") . "@i","' . $name . '") ? $a : NULL;'
            ),$this->committee_regex_lookup)
          )); 
          $n = count($m);
          if ( $n == 0 ) {
            // No match, probably a new name, hence no ID yet found
            $mapped = NULL;
          } else if ( $n == 1 ) {
            // Matched exactly one name, no need to create new entry
            $name = NULL;
            $mapped = array_element($m,'id');
          } else {
            // Matched multiple records
            $mapped = $m;
          }
        }/*}}}*/
        if ( !is_null($name) ) $this->committee_regex_lookup[$name] = array(
          'committee_name' => $name,
          'regex'          => $committee_name_regex,
          'id'             => 'UNMAPPED'
        );
      }/*}}}*/
      else {
        // Assign an existing ID
        $mapped = array_element($this->committee_regex_lookup[$name],'id');
      }
      $a['meta']['main-committee'] = array(
        'raw' => $main_committee,
        'mapped' => $mapped,
      );
    }/*}}}*/

    return TRUE;
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

  function cache_parsed_housebill_records(& $bill_cache_source, $congress_tag, $from_network) {/*{{{*/

    $this->committee_regex_lookup = array();

    $debug_method = FALSE;

    $bill_cache = array_combine(
      array_map(create_function('$a','return array_element($a,"sn");'),$bill_cache_source),
      $bill_cache_source
    );
    // Transform [meta] records, by moving their content into appropriate
    // Join property containers.
    //
    // The reasons that we defer assigning those attributes during parse are that
    //
    // 0) The attributes we are storing / preparing for storage are tied to
    //    neither Committee nor Representative models, but in the relationship
    //    between the House Bill and the objects that wrap those two concepts;
    //
    // 1) Preparing the parsed data is a Join-specific action that may occur
    //    in contexts other than during parsing new House Bill records;  
    //    writing shorter methods will ease their reuse elsewhere; and
    //
    // 2) The parsing algorithm is simplified by deferring updating the
    //    Committee table foreign key until just before this method is called,
    //    so we can only move nested array members around here, to allow us
    //    to call set_contents_from_array($record) later.

    // Cleanup before omitting preexisting records
    // Populate the committee name lookup table during cleanup
    array_walk($bill_cache,create_function(
      '& $a, $k, $s', '$s->final_cleanup_parsed_housebill_cache($a,$k);'
    ), $this);

    // Update the lookup table
    $this->get_foreign_obj_instance('committee')->update_committee_name_regex_lookup($this->committee_regex_lookup);

    // Update bill cache with committee names, IDs, and URLs.
    array_walk($bill_cache,create_function(
      '& $a, $k, $s', '$s->cache_parsed_housebill_records_commupdate($a,$k);'
    ),$this);

    array_walk($bill_cache,create_function(
      '& $a, $k, $s', 'if ( $s["n"] ) $a["last_fetch"] = time(); if ( is_null(array_element($a,"congress_tag"))) $a["congress_tag"] = $s["c"];'
    ),array('n' => $from_network, 'c' => intval($congress_tag)));

    $this->get_join_instance('committee')->prepare_cached_records($bill_cache);
    $this->get_join_instance('representative')->prepare_cached_records($bill_cache);
    
    $bill_cache_source = $bill_cache;

    gc_collect_cycles();

    $bill_sns = array_keys($bill_cache);

    // Cleanup extant records

		if ( $debug_method )
		$this->syslog(__FUNCTION__,__LINE__,"(marker) Searching for matches to Congress {$congress_tag} " . join(',',$bill_sns));

		$this->debug_final_sql = FALSE;
    $this->
      join_all()->
      //join(array(
      //  'committee[jointype*=main-committee]',
      //  'representative[relation_to_bill=principal-author]',
      //))->
      where(array('AND' => array(
        '`a`.`congress_tag`' => intval($congress_tag),
        '`a`.`sn`' => $bill_sns
      )))->
      recordfetch_setup();
		$this->debug_final_sql = FALSE;
    $hb = array();
		$debug_checker = FALSE;
		$join_exclusions = $this->get_joins();
		if ( $debug_checker) $this->recursive_dump($join_exclusions,"(marker) -- JOINS --");
    while ( $this->recordfetch($hb,TRUE) ) {/*{{{*/
			
      // TODO: If an extant Join matches what has been passed in, then remove both
      // TODO: If no data has been modified, remove the bill_cache entry
      $sn = array_element($hb,'sn');
			if ( $debug_checker ) {/*{{{*/
				$this->syslog(__FUNCTION__,__LINE__,"(marker) --- - --- For {$hb['sn']} --- - ---");
				// $this->recursive_dump($hb,"(marker) --- DB ---");
			}/*}}}*/
      if ( array_key_exists($sn,$bill_cache) ) {
        unset($bill_cache[$sn]['links']);
        unset($bill_cache[$sn]['meta']);
        $bill_cache[$sn]['id'] = $hb['id'];
        unset($bill_cache[$sn]['create_time']);
				ksort($bill_cache[$sn]);

				// Clear empty Join attributes in the DB record
			  array_walk($hb,create_function(
					'& $a, $k, $s', 'if ( array_key_exists($k, $s) ) { $a = array_filter($a); if ( empty($a) ) $a = NULL; }'
				), $join_exclusions);	

				// Set missing congress_tag attribute in the DB Join records
			  array_walk($bill_cache[$sn],create_function(
					'& $a, $k, $s', 'if ( array_key_exists($k, $s["j"]) && is_null(array_element($a,"congress_tag"))) $a["congress_tag"] = $s["c"];'
				), array('j' => $join_exclusions, 'c' => intval($congress_tag)));	

				$hb = array_filter($hb);

				// Get keys common to structure and parsed content
				$intersection = array_intersect_key(
          $hb,
          $bill_cache[$sn]
				);

				if ( $debug_checker ) $this->recursive_dump($hb,"(marker) -- intersect - {$sn}.{$hb['congress']} #{$hb['id']} - --");
				ksort($intersection);

				// Get difference between parsed record and existing DB record
        $delta = array_diff(
          $bill_cache[$sn],
          $intersection
        );
        if ( 0 < count($delta) ) {
          if ( $debug_method ) {/*{{{*/
						$this->syslog(__FUNCTION__,__LINE__,"(marker) --- - --- For {$hb['sn']} --- - ---");
            $this->recursive_dump($bill_cache[$sn],"(marker) -- parse - {$sn}.{$hb['congress_tag']} #{$hb['id']} - --");
            $this->recursive_dump($hb,"(marker) -- inter - {$sn}.{$hb['congress_tag']} #{$hb['id']} - --");
            $this->recursive_dump($delta,"(marker) -- delta - {$sn}.{$hb['congress_tag']} #{$hb['id']} - --");
          }/*}}}*/
				}
			 	else {
					if ( $debug_checker ) $this->recursive_dump($bill_cache[$sn],"(marker) -- skip - {$sn}.{$hb['congress_tag']} #{$hb['id']} - --");
          unset($bill_cache[$sn]);
        }
      }
			else {
        $bill_cache[$sn]['create_time'] = time();
				$this->syslog(__FUNCTION__,__LINE__,"(marker) --!!! No match for {$hb['sn']}");
			}
    }/*}}}*/

		if ( $debug_checker ) $this->syslog(__FUNCTION__,__LINE__,"(marker) Matches: " . count($bill_cache));

    $bill_cache = array_filter($bill_cache);
    // Stow Joins between each bill and main committee

    if ( $debug_method ) {
      $this->recursive_dump($this->fetch_combined_property_list(), "(marker) P  - - - - -");
      $this->syslog(__FUNCTION__,__LINE__,"(marker) Storing " . join(', ', array_keys($bill_cache)));
    }

    // Store records not found in DB or that are updated

    while ( 0 < count($bill_cache) ) {

      $cache_entry = array_filter(array_pop($bill_cache)); 

      unset($cache_entry['links']);
      unset($cache_entry['meta']);
      // unset($cache_entry['status']['mapped']);

      if ((is_null(array_element($cache_entry,'url')) || empty($cache_entry['url']) ) && 
        (is_null(array_element($cache_entry,'url_history')) || empty($cache_entry['url_history']) ) &&
        (is_null(array_element($cache_entry,'url_engrossed')) || empty($cache_entry['url_engrossed']) )
      ) {
        $this->syslog(__FUNCTION__,__LINE__,"(error) No URL; entry is not traceable to a source.");
        $this->recursive_dump($cache_entry,"(marker) - - -");
        continue;
      }

      if (is_null(array_element($cache_entry,'congress_tag'))) {
        $this->syslog(__FUNCTION__,__LINE__,"(error) No Congress number available. Cannot stow entry.");
        $this->recursive_dump($cache_entry,"(marker) - - -");
        continue;
      }

      if ( $debug_method ) {
        $this->recursive_dump($cache_entry, "(marker) E {$cache_entry['sn']}:{$cache_entry['id']} - - - - -");
      }

      $bill_id = $this->
        set_id(NULL)->
        set_contents_from_array($cache_entry,TRUE)->
        stow();

      if ( 0 < intval($bill_id) ) {
				$error = $this->error();
        if ($debug_method || !empty($error)) $this->syslog(__FUNCTION__,__LINE__,"(marker) -- Stored {$bill_id} {$cache_entry['sn']}.{$cache_entry['congress_tag']}");
      } else {
        $this->syslog(__FUNCTION__,__LINE__,"(marker) -- FAILED TO STORE/UPDATE {$cache_entry['sn']}.{$cache_entry['congress_tag']}");
      }
      $cache_entry = NULL;
    }
    $bill_cache = array();
  }/*}}}*/
  	
}
