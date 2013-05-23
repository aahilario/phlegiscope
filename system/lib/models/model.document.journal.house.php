<?php

/*
 * Class HouseJournalDocumentModel
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class HouseJournalDocumentModel extends UrlModel {
  
  var $title_vc256uniq = NULL;
  var $create_time_utx = NULL;
  var $sn_vc64 = NULL;
  var $last_fetch_utx = NULL;
  var $congress_tag_vc8 = NULL;
  var $session_tag_vc8 = NULL;
  var $url_vc4096 = NULL;
	var $content_blob = NULL;
	var $pdf_fetch_time_utx = NULL;
	var $pdf_url_vc4096 = NULL;

  function __construct() {
    parent::__construct();
  }

	function & set_title($v) { $this->title_vc256uniq = $v; return $this; }
	function get_title($v = NULL) { if (!is_null($v)) $this->set_title($v); return $this->title_vc256uniq; }

	function & set_sn($v) { $this->sn_vc64 = $v; return $this; }
	function get_sn($v = NULL) { if (!is_null($v)) $this->set_sn($v); return $this->sn_vc64; }

	function & set_create_time($v) { $this->create_time_utx = $v; return $this; }
	function get_create_time($v = NULL) { if (!is_null($v)) $this->set_create_time($v); return $this->create_time_utx; }

	function & set_last_fetch($v) { $this->last_fetch_utx = $v; return $this; }
	function get_last_fetch($v = NULL) { if (!is_null($v)) $this->set_last_fetch($v); return $this->last_fetch_utx; }

	function & set_congress_tag($v) { $this->congress_tag_vc8 = $v; return $this; }
	function get_congress_tag($v = NULL) { if (!is_null($v)) $this->set_congress_tag($v); return $this->congress_tag_vc8; }

	function & set_url($v) { $this->url_vc4096 = $v; return $this; }
	function get_url($v = NULL) { if (!is_null($v)) $this->set_url($v); return $this->url_vc4096; }

	function & set_content($v) { $this->content_blob = $v; return $this; }
	function get_content($v = NULL) { if (!is_null($v)) $this->set_content($v); return $this->content_blob; }

	function & set_pdf_fetch_time($v) { $this->pdf_fetch_time_utx = $v; return $this; }
	function get_pdf_fetch_time($v = NULL) { if (!is_null($v)) $this->set_pdf_fetch_time($v); return $this->pdf_fetch_time_utx; }

	function & set_pdf_url($v) { $this->pdf_url_vc4096 = $v; return $this; }
	function get_pdf_url($v = NULL) { if (!is_null($v)) $this->set_pdf_url($v); return $this->pdf_url_vc4096; }

	function & set_session_tag($v) { $this->session_tag_vc8 = $v; return $this; }
	function get_session_tag($v = NULL) { if (!is_null($v)) $this->set_session_tag($v); return $this->session_tag_vc8; }

}

