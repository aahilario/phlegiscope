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

  function __construct() {
    parent::__construct();
  }

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

  function non_session_linked_document_stow($document) {
    // Override parent::non_session_linked_document_stow
    $this->syslog( __FUNCTION__, __LINE__, "(marker) Skip stowing {$document['text']} {$document['url']}");
    return NULL;
  } 

}
