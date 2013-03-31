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
  var $url_vc4096 = NULL;
	var $status_vc1024 = NULL;

	var $date_read_utx = NULL;
	var $house_approval_date_utx = NULL;
	var $transmittal_date_utx = NULL;
	var $received_senate_utx = NULL;
	var $principal_author_int11 = NULL;
	var $main_referral_comm_vc64 = NULL;
	var $pending_comm_vc64 = NULL;
	var $pending_comm_date_utx = NULL;
	var $significance_vc16 = NULL;

  function __construct() {
    parent::__construct();
  }

	function & set_status($meta) {
		$this->status_vc1024 = is_array($meta)
			?	json_encode($meta)
			: $meta
			;
		if ( FALSE == $this->status_vc1024 ) $this->status_vc1024 = json_encode(array());
		return $this;
	}

	function get_status($as_array = TRUE) {
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
	}

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
