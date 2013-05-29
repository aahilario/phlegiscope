<?php

/*
 * Class HouseBillDocumentModel
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class HouseBillDocumentModel extends RepublicActDocumentModel {
  
  var $title_vc256uniq = NULL;
  var $sn_vc64uniq = NULL;
  var $origin_vc2048 = NULL;
  var $description_vc4096 = NULL;
  var $create_time_utx = NULL;
  var $last_fetch_utx = NULL;
  var $searchable_bool = NULL;
  var $congress_tag_vc8 = NULL;
  var $url_vc4096 = NULL; // Filed
  var $url_history_vc4096 = NULL; // Whenever available
  var $url_engrossed_vc4096 = NULL;
	var $status_vc1024 = NULL;

	var $date_read_utx = NULL;
	var $house_approval_date_utx = NULL;
	var $transmittal_date_utx = NULL;
	var $received_senate_utx = NULL;
	var $principal_author_int11 = NULL; // Join candidate
	var $main_referral_comm_vc64 = NULL; // Join candidate
	var $pending_comm_vc64 = NULL; // Join candidate
	var $pending_comm_date_utx = NULL; // Join candidate
	var $significance_vc16 = NULL;

  var $housebill_HouseBillDocumentModel = NULL; // Reference to other house bills.
  var $republic_act_RepublicActDocumentModel = NULL; // Republic Act toward which this House Bill contributed essential language and intent. 
	var $representative_RepresentativeDossierModel = NULL; // Several types of association between a house bill and representatives (authorship, etc.).
  var $content_UrlModel = NULL; // Content BLOB edges.
  var $committee_CongressionalCommitteeDocumentModel = NULL; // Reference to Congressional Committees, bearing the status of a House Bill, or the principal committee.

  private $join_instance_cache = array();
  private $null_instance = NULL;

  function __construct() {/*{{{*/
    parent::__construct();
  }/*}}}*/

  function get_parsed_housebill_template( $hb_number, $congress_tag = NULL ) {/*{{{*/
    return array(
      'sn'           => $hb_number,
      'last_fetch'   => time(),
      'description'  => NULL,
      'congress_tag' => $congress_tag,
      'url_history'  => NULL, // Document activity history
      'url'          => NULL,
      'representative' => array(
        'relation_to_bill' => NULL,
      ),
      'committee'    => array(
        'jointype' => NULL,
      ),
      'meta'         => array(),
      'links'        => array(),
    );
  }/*}}}*/

  protected function & get_join_object($property, $which) {
    $join_desc = $this->get_joins($property);
    $this->recursive_dump($join_desc,"(marker) - - - - JD({$property})");
    $join_desc_key = array_element($join_desc,'joinobject');
    try {
      if ( !array_key_exists($join_desc_key, $this->join_instance_cache) ) {
        $this->join_instance_cache[$join_desc_key] = array(
          'join' => new $join_desc['joinobject'](),
          'ref'  => new $join_desc['propername'](),
        );
      }
    } catch ( Exception $e ) {
      $this->syslog(__FUNCTION__,__LINE__, "(warning) - - - - - - - - - - ERROR: Failed to create instance of '{$which}' property {$property} object {$join_desc[$join_desc_key]}");
      return $this->null_instance;
    }
    return $this->join_instance_cache[$join_desc_key][$which];
  }

  function & get_foreign_obj_instance($property) {
    return $this->get_join_object($property,'ref');
  }

  function & get_join_instance($property) {
    return $this->get_join_object($property,'join');
  }

  function cache_parsed_housebill_records(& $bill_cache) {/*{{{*/

    $join_comm =& $this->get_join_instance('committee');
    $comm_obj  =& $this->get_foreign_obj_instance('committee');

    $join_rep =& $this->get_join_instance('representative');
    $rep_obj  =& $this->get_foreign_obj_instance('representative');

    if ( is_null($join_comm) || is_null($comm_obj) ) {
      $bill_cache = array();
      return FALSE;
    }

    $bill_sns = array_keys($bill_cache);

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
    while ( $this->recordfetch($hb,TRUE) ) {
      $sn = array_element($hb,'sn');
      if ( array_key_exists($sn,$bill_cache) ) {
        $bill_cache[$sn]['id'] = $hb['id'];
      }
    }

    $bill_cache = array_filter($bill_cache);
    // Stow Joins between each bill and main committee

    $this->syslog(__FUNCTION__,__LINE__,"(marker) Storing " . join(', ', array_keys($bill_cache)));
    // Store records not found in DB
    while ( 0 < count($bill_cache) ) {

      $cache_entry = array_pop($bill_cache); 
      $this->recursive_dump($cache_entry, "(marker) E {$cache_entry['sn']}:{$cache_entry['id']} - - - - -");

      if (!is_null(array_element($cache_entry,'id'))) continue; 
      if (is_null(array_element($cache_entry,'congress_tag'))) {
        $this->syslog(__FUNCTION__,__LINE__,"(error) No Congress number available. Cannot stow entry.");
        continue;
      }
      $searchable = FALSE; // OCRed PDF available
      if (0) $this->
        set_url($url)->
        set_sn($bill_head)->
        set_title($hb['title'])->
        set_searchable($searchable)->
        set_create_time($now_time)->
        set_last_fetch($now_time)->
        set_status($meta)->
        stow();
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

	function & set_date_read($v) { $this->date_read_utx = $v; return $this; }
	function get_date_read($v = NULL) { if (!is_null($v)) $this->set_date_read($v); return $this->date_read_utx; }

	function & set_house_approval_date($v) { $this->house_approval_date_utx = $v; return $this; }
	function get_house_approval_date($v = NULL) { if (!is_null($v)) $this->set_house_approval_date($v); return $this->house_approval_date_utx; }

	function & set_transmittal_date($v) { $this->transmittal_date_utx = $v; return $this; }
	function get_transmittal_date($v = NULL) { if (!is_null($v)) $this->set_transmittal_date($v); return $this->transmittal_date_utx; }

	function & set_received_senate($v) { $this->received_senate_utx = $v; return $this; }
	function get_received_senate($v = NULL) { if (!is_null($v)) $this->set_received_senate($v); return $this->received_senate_utx; }

	function & set_principal_author($v) { $this->principal_author_int11 = $v; return $this; }
	function get_principal_author($v = NULL) { if (!is_null($v)) $this->set_principal_author($v); return $this->principal_author_int11; }

	function & set_main_referral_comm($v) { $this->main_referral_comm_vc64 = $v; return $this; }
	function get_main_referral_comm($v = NULL) { if (!is_null($v)) $this->set_main_referral_comm($v); return $this->main_referral_comm_vc64; }

	function & set_pending_comm($v) { $this->pending_comm_vc64 = $v; return $this; }
	function get_pending_comm($v = NULL) { if (!is_null($v)) $this->set_pending_comm($v); return $this->pending_comm_vc64; }

	function & set_pending_comm_date($v) { $this->pending_comm_date_utx = $v; return $this; }
	function get_pending_comm_date($v = NULL) { if (!is_null($v)) $this->set_pending_comm_date($v); return $this->pending_comm_date_utx; }

	function & set_significance($v) { $this->significance_vc16 = $v; return $this; }
	function get_significance($v = NULL) { if (!is_null($v)) $this->set_significance($v); return $this->significance_vc16; }
}
