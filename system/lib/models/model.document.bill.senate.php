<?php

/*
 * Class SenateBillDocumentModel
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class SenateBillDocumentModel extends RepublicActDocumentModel {
  
  var $title_vc256uniq = NULL;
  var $sn_vc64uniq = NULL;
  var $origin_vc2048 = NULL;
  var $description_vc4096 = NULL;
  var $create_time_utx = NULL;
  var $last_fetch_utx = NULL;
  var $searchable_bool = NULL;
  var $congress_tag_vc8 = NULL;
  var $url_vc4096 = NULL;
  var $urlid_int11 = NULL;
  var $doc_url_vc256 = NULL;
	var $status_vc1024 = NULL;
	var $subjects_vc1024 = NULL;
  var $comm_report_url_vc256 = NULL;
  var $comm_report_info_vc256 = NULL;

	var $date_read_utx = NULL;
	var $house_approval_date_utx = NULL;
	var $transmittal_date_utx = NULL;
	var $principal_author_int11 = NULL;
  var $sponsor_int11 = NULL; // SenatorsModel
	var $main_referral_comm_vc64 = NULL;
	var $pending_comm_vc64 = NULL;
	var $pending_comm_date_utx = NULL;
	var $significance_vc16 = NULL;

  var $journal_SenateJournalDocumentModel = NULL;

  function __construct() {
    parent::__construct();
  }

  function & set_text($v) { return $this; }
  function get_text($v = NULL) { if (!is_null($v)) $this->set_text($v); return NULL; }

  function & set_desc($v) { $this->description_vc4096 = $v; return $this; }
  function get_desc($v = NULL) { if (!is_null($v)) $this->set_desc($v); return $this->description_vc4096; }

  function & set_date_read($v) { $this->date_read_utx = $v; return $this; }
  function get_date_read($v = NULL) { if (!is_null($v)) $this->set_date_read($v); return $this->date_read_utx; }

  function & set_house_approval_date($v) { $this->house_approval_date_utx = $v; return $this; }
  function get_house_approval_date($v = NULL) { if (!is_null($v)) $this->set_house_approval_date($v); return $this->house_approval_date_utx; }

  function & set_transmittal_date($v) { $this->transmittal_date_utx = $v; return $this; }
  function get_transmittal_date($v = NULL) { if (!is_null($v)) $this->set_transmittal_date($v); return $this->transmittal_date_utx; }

  function & set_principal_author($v) { $this->principal_author_int11 = $v; return $this; }
  function get_principal_author($v = NULL) { if (!is_null($v)) $this->set_principal_author($v); return $this->principal_author_int11; }

  function & set_sponsor($v) { $this->sponsor_int11 = $v; return $this; }
  function get_sponsor($v = NULL) { if (!is_null($v)) $this->set_sponsor($v); return $this->sponsor_int11; }

  function & set_pending_comm($v) { $this->pending_comm_vc64 = $v; return $this; }
  function get_pending_comm($v = NULL) { if (!is_null($v)) $this->set_pending_comm($v); return $this->pending_comm_vc64; }

  function & set_pending_comm_date($v) { $this->pending_comm_date_utx = $v; return $this; }
  function get_pending_comm_date($v = NULL) { if (!is_null($v)) $this->set_pending_comm_date($v); return $this->pending_comm_date_utx; }

  function & set_urlid($v) { $this->urlid_int11 = $v; return $this; }
  function get_urlid($v = NULL) { if (!is_null($v)) $this->set_urlid($v); return $this->urlid_int11; }
  
  function & set_doc_url($v) { $this->doc_url_vc256 = $v; return $this; }
  function get_doc_url($v = NULL) { if (!is_null($v)) $this->set_doc_url($v); return $this->doc_url_vc256; }
  
  function & set_status($v) { $this->status_vc1024 = $v; return $this; }
  function get_status($v = NULL) { if (!is_null($v)) $this->set_status($v); return $this->status_vc1024; }

  function & set_subjects($v) { $this->subjects_vc1024 = $v; return $this; }
  function get_subjects($v = NULL) { if (!is_null($v)) $this->set_subjects($v); return $this->subjects_vc1024; }

  function & set_comm_report_url($v) { $this->comm_report_url_vc256 = $v; return $this; }
  function get_comm_report_url($v = NULL) { if (!is_null($v)) $this->set_comm_report_url($v); return $this->comm_report_url_vc256; }

  function & set_comm_report_info($v) { $this->comm_report_info_vc256 = $v; return $this; }
  function get_comm_report_info($v = NULL) { if (!is_null($v)) $this->set_comm_report_info($v); return $this->comm_report_info_vc256; }

  function & set_main_referral_comm($v) { $this->main_referral_comm_vc64 = $v; return $this; }
  function get_main_referral_comm($v = NULL) { if (!is_null($v)) $this->set_main_referral_comm($v); return $this->main_referral_comm_vc64; }

  function & set_significance($v) { $this->significance_vc16 = $v; return $this; }
  function get_significance($v = NULL) { if (!is_null($v)) $this->set_significance($v); return $this->significance_vc16; }
}
