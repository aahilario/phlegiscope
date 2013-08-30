<?php

/*
 * Class ContentDocumentModel
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class ContentDocumentModel extends DatabaseUtility {
  
  var $data_blob = NULL;
  var $content_hash_vc128 = 'sha1';
  // Join properties
  var $content_type_vc16 = NULL; // Referent-specific payload content identification (payload class, e.g. 'ocr','pagecontent','annotation') 
	var $content_meta_vc512 = NULL; // Referent-specific metadata. Used by Models to store additional data apart from the type tag.
  var $content_version_int11 = NULL; // Referent-specific ordinal key (e.g. revision number)
  var $last_fetch_utx = NULL;
  var $create_time_utx = NULL;

	var $url_UrlModel = NULL;

  function __construct() {
    parent::__construct();
  }

  function & set_last_fetch($v) { $this->last_fetch_utx = $v; return $this; }
  function get_last_fetch($v = NULL) { if (!is_null($v)) $this->set_last_fetch($v); return $this->last_fetch_utx; }

  function & set_create_time($v) { $this->create_time_utx = $v; return $this; }
  function get_create_time($v = NULL) { if (!is_null($v)) $this->set_create_time($v); return $this->create_time_utx; }

  function & set_data($v) { $this->data_blob = $v; return $this; }
  function get_data($v = NULL) { if (!is_null($v)) $this->set_data($v); return $this->data_blob; }

  function & set_content_hash($v) { $this->content_hash_vc128 = $v; return $this; }
  function get_content_hash($v = NULL) { if (!is_null($v)) $this->set_content_hash($v); return $this->content_hash_vc128; }

  function & set_content_type($v) { $this->content_type_vc16 = $v; return $this; }
  function get_content_type($v = NULL) { if (!is_null($v)) $this->set_content_type($v); return $this->content_type_vc16; }

	function & set_content_version($v) { $this->content_version_int11 = $v; return $this; }
	function get_content_version($v = NULL) { if (!is_null($v)) $this->set_content_version($v); return $this->content_version_int11; }

	function & set_content_meta($v) { $this->content_meta_vc512 = $v; return $this; }
	function get_content_meta($v = NULL) { if (!is_null($v)) $this->set_content_meta($v); return $this->content_meta_vc512; }

}

