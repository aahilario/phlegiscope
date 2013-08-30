<?php

/*
 * Class ContentUrlJoin
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */
define('CONTENT_TYPE_PREPARSED'       , 'preparsed');
define('CONTENT_TYPE_RESPONSE_CONTENT', 'raw');

class ContentUrlJoin extends DatabaseUtility {
  
  // Join table model
  var $content_ContentDocumentModel;
  var $url_UrlModel;
	var $content_type_vc16;
  var $urlhash_vc128 = 'md5';

	function __construct() {
		parent::__construct();
		if (0) {
			$this->dump_accessor_defs_to_syslog();
			$this->recursive_dump($this->get_attrdefs(),'(marker) "+++++++"');
		}
	}

  function & set_content($v) { $this->content_ContentDocumentModel = $v; return $this; }
  function get_content($v = NULL) { if (!is_null($v)) $this->set_content($v); return $this->content_ContentDocumentModel; }

  function & set_url($v) { $this->url_UrlModel = $v; return $this; }
  function get_url($v = NULL) { if (!is_null($v)) $this->set_url($v); return $this->url_UrlModel; }

  function & set_content_type($v) { $this->content_type_vc16 = $v; return $this; }
  function get_content_type($v = NULL) { if (!is_null($v)) $this->set_content_type($v); return $this->content_type_vc16; }

	function & set_urlhash($v) { $this->urlhash_vc128 = $v; return $this; }
	function get_urlhash($v = NULL) { if (!is_null($v)) $this->set_urlhash($v); return $this->urlhash_vc128; }

}

