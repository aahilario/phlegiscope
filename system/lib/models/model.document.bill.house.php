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

	var $date_read_utx = NULL;
	var $house_approval_date_utx = NULL;
	var $significance_vc16 = NULL;

  var $housebill_HouseBillDocumentModel = NULL; // Reference to other house bills.
  var $republic_act_RepublicActDocumentModel = NULL; // Republic Act toward which this House Bill contributed essential language and intent. 
	var $representative_RepresentativeDossierModel = NULL; // Several types of association between a house bill and representatives (authorship, etc.).
  var $content_UrlModel = NULL; // Content BLOB edges.
  var $committee_CongressionalCommitteeDocumentModel = NULL; // Reference to Congressional Committees, bearing the status of a House Bill, or the principal committee.

  function __construct() {/*{{{*/
    parent::__construct();
  }/*}}}*/

  function __destruct() {
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
  }

  function final_cleanup_parsed_housebill_cache(& $a, $k) {/*{{{*/

    // Move nested array elements into place, to allow use of 
    // set_contents_from_array()
    $links = array_element($a,"links",array());
    $meta  = array_element($a,"meta",array());

    $a["url"] = array_element($links,"filed");
    $a["url_engrossed"] = array_element($links,"engrossed");
    $a["status"] = array_element($meta,"status");
    $a["create_time"] = time();

    return TRUE;
  }/*}}}*/

  function cache_parsed_housebill_records(& $bill_cache_source) {/*{{{*/

    $debug_method = FALSE;

    $bill_cache = $bill_cache_source;
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

    $this->get_join_instance('committee')->prepare_cached_records($bill_cache);
    $this->get_join_instance('representative')->prepare_cached_records($bill_cache);
    
    // Cleanup before omitting preexisting records
    // Move 'filed' ("as filed") links into the URL attribute, further sundry cleanup
    array_walk($bill_cache,create_function(
      '& $a, $k, $s', '$s->final_cleanup_parsed_housebill_cache($a,$k);'
    ), $this);

    gc_collect_cycles();

    $bill_sns = array_keys($bill_cache);

    // Cleanup extant records

    $this->
      where(array('AND' => array(
        'sn' => $bill_sns
      )))->
      join(array(
        'committee[jointype*=main-committee]',
        'representative[relation_to_bill=principal-author]',
      ))->
      recordfetch_setup();
    $hb = array();
    while ( $this->recordfetch($hb,TRUE) ) {/*{{{*/
      // TODO: If an extant Join matches what has been passed in, then remove both
      // TODO: If no data has been modified, remove the bill_cache entry
      $sn = array_element($hb,'sn');
      if ( array_key_exists($sn,$bill_cache) ) {
        $bill_cache[$sn]['id'] = $hb['id'];
        $bill_cache[$sn]['create_time'] = NULL;
        unset($bill_cache[$sn]);
        if ( $debug_method ) {/*{{{*/
          $delta = array_diff_key(
            $bill_cache[$sn],
            $hb
          );
          $this->syslog(__FUNCTION__,__LINE__,"(warning) - - - - - - {$sn} delta backing store vs. bill cache. {$sn}");
          $this->recursive_dump($delta,"(marker) -- - --");
        }/*}}}*/
      }
    }/*}}}*/

    $bill_cache = array_filter($bill_cache);
    // Stow Joins between each bill and main committee

    if ( $debug_method ) {
      $this->recursive_dump($this->fetch_combined_property_list(), "(marker) P  - - - - -");
      $this->syslog(__FUNCTION__,__LINE__,"(marker) Storing " . join(', ', array_keys($bill_cache)));
    }

    // Store records not found in DB

    while ( 0 < count($bill_cache) ) {

      $cache_entry = array_filter(array_pop($bill_cache)); 

      unset($cache_entry['links']);
      unset($cache_entry['meta']);
      unset($cache_entry['status']['mapped']);

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
        set_contents_from_array($cache_entry,TRUE)->
        stow();

      $cache_entry = NULL;
    }
    $bill_cache = array();
  }/*}}}*/
  
	function & set_status($meta) {/*{{{*/
		$this->status_vc1024 = is_array($meta)
			?	json_encode($meta)
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

	function & set_date_read($v) { $this->date_read_utx = $v; return $this; }
	function get_date_read($v = NULL) { if (!is_null($v)) $this->set_date_read($v); return $this->date_read_utx; }

	function & set_house_approval_date($v) { $this->house_approval_date_utx = $v; return $this; }
	function get_house_approval_date($v = NULL) { if (!is_null($v)) $this->set_house_approval_date($v); return $this->house_approval_date_utx; }

	function & set_significance($v) { $this->significance_vc16 = $v; return $this; }
	function get_significance($v = NULL) { if (!is_null($v)) $this->set_significance($v); return $this->significance_vc16; }
}
