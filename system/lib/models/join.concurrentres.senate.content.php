<?php

/*
 * Class ContentSenateConcurrentresJoin
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class ContentSenateConcurrentresJoin extends ModelJoin {
  
  // Join table model
  var $content_ContentDocumentModel;
  var $senate_concurrentres_SenateConcurrentresDocumentModel;

  function __construct() {
    parent::__construct();
    $this->dump_accessor_defs_to_syslog();
    $this->recursive_dump($this->get_attrdefs(),'(marker) "+++++++"');
  }

  function & set_content($v) { $this->content_ContentDocumentModel = $v; return $this; }
  function get_content($v = NULL) { if (!is_null($v)) $this->set_content($v); return $this->content_ContentDocumentModel; }

  function & set_senate_concurrentres($v) { $this->senate_concurrentres_SenateConcurrentresDocumentModel = $v; return $this; }
  function get_senate_concurrentres($v = NULL) { if (!is_null($v)) $this->set_senate_concurrentres($v); return $this->senate_concurrentres_SenateConcurrentresDocumentModel; }

}

