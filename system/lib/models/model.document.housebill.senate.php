<?php

/*
 * Class SenateHousebillDocumentModel
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class SenateHousebillDocumentModel extends SenateDocCommonDocumentModel {
  
  var $title_vc256 = NULL;
	var $description_vc4096 = NULL;
  var $create_time_utx = NULL;
  var $sn_vc64 = NULL;
  var $last_fetch_utx = NULL;
  var $congress_tag_vc8 = NULL;
  // var $session_tag_vc8 = NULL;
  var $url_vc4096 = NULL;
  var $urlid_int11 = NULL;
  var $doc_url_vc256 = NULL;
	var $status_vc1024 = NULL;
	var $subjects_vc1024 = NULL;
  var $comm_report_url_vc256 = NULL;
  var $comm_report_info_vc256 = NULL;
  var $invalidated_bool = NULL;
	var $legislative_history_vc8192 = NULL;

	var $main_referral_comm_vc64 = NULL;
	var $secondary_committee_vc64 = NULL;
	var $significance_vc16 = NULL;

	var $journal_SenateJournalDocumentModel = NULL;
  var $committee_SenateCommitteeModel = NULL;
	var $senator_SenatorDossierModel = NULL;

	function __construct() {
		parent::__construct();
		//$this->dump_accessor_defs_to_syslog();
		//$this->recursive_dump($this->get_attrdefs(),'(marker) "+++++++"');
	}

	function & set_invalidated($v) { $this->invalidated_bool = $v; return $this; }
	function get_invalidated($v = NULL) { if (!is_null($v)) $this->set_invalidated($v); return $this->invalidated_bool; }

	function & set_text($v) { $this->title_vc256 = $v; return $this; }
	function get_text($v = NULL) { if (!is_null($v)) $this->set_title($v); return $this->title_vc256; }

	function & set_title($v) { $this->title_vc256 = $v; return $this; }
	function get_title($v = NULL) { if (!is_null($v)) $this->set_title($v); return $this->title_vc256; }

	function & set_desc($v) { $this->description_vc4096 = $v; return $this; }
	function get_desc($v = NULL) { if (!is_null($v)) $this->set_description($v); return $this->description_vc4096; }

	function & set_description($v) { $this->description_vc4096 = $v; return $this; }
	function get_description($v = NULL) { if (!is_null($v)) $this->set_description($v); return $this->description_vc4096; }

	function & set_create_time($v) { $this->create_time_utx = $v; return $this; }
	function get_create_time($v = NULL) { if (!is_null($v)) $this->set_create_time($v); return $this->create_time_utx; }

	function & set_sn($v) { $this->sn_vc64 = $v; return $this; }
	function get_sn($v = NULL) { if (!is_null($v)) $this->set_sn($v); return $this->sn_vc64; }

	function & set_last_fetch($v) { $this->last_fetch_utx = $v; return $this; }
	function get_last_fetch($v = NULL) { if (!is_null($v)) $this->set_last_fetch($v); return $this->last_fetch_utx; }

	function & set_congress_tag($v) { $this->congress_tag_vc8 = $v; return $this; }
	function get_congress_tag($v = NULL) { if (!is_null($v)) $this->set_congress_tag($v); return $this->congress_tag_vc8; }

	function & set_session_tag($v) { $this->session_tag_vc8 = $v; return $this; }
	function get_session_tag($v = NULL) { if (!is_null($v)) $this->set_session_tag($v); return $this->session_tag_vc8; }

	function & set_url($v) { $this->url_vc4096 = $v; return $this; }
	function get_url($v = NULL) { if (!is_null($v)) $this->set_url($v); return $this->url_vc4096; }

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

	function & set_secondary_committee($v) { $this->secondary_committee_vc64 = $v; return $this; }
	function get_secondary_committee($v = NULL) { if (!is_null($v)) $this->set_secondary_committee($v); return $this->secondary_committee_vc64; }

	function & set_legislative_history($v) { $this->legislative_history_vc8192 = $v; return $this; }
	function get_legislative_history($v = NULL) { if (!is_null($v)) $this->set_legislative_history($v); return $this->legislative_history_vc8192; }


  function & set_significance($v) { $this->significance_vc16 = $v; return $this; }
  function get_significance($v = NULL) { if (!is_null($v)) $this->set_significance($v); return $this->significance_vc16; }

}
