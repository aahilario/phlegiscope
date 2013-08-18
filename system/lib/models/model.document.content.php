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
  var $contenthash_vc128uniq = 'sha1';
  // Join properties
  // var $content_type_vc16 = NULL; // Referent-specific payload content identification (payload class, e.g. 'ocr','pagecontent','annotation') 
  // var $content_version_int = NULL; // Referent-specific ordinal key (e.g. revision number)
  var $last_fetch_utx = NULL;
  var $create_time_utx = NULL;

  function __construct() {
    parent::__construct();
  }

  function & set_last_fetch($v) { $this->last_fetch_utx = $v; return $this; }
  function get_last_fetch($v = NULL) { if (!is_null($v)) $this->set_last_fetch($v); return $this->last_fetch_utx; }

  function & set_create_time($v) { $this->create_time_utx = $v; return $this; }
  function get_create_time($v = NULL) { if (!is_null($v)) $this->set_create_time($v); return $this->create_time_utx; }

  function & set_data($v) { $this->data_blob = $v; return $this; }
  function get_data($v = NULL) { if (!is_null($v)) $this->set_data($v); return $this->data_blob; }

  function & set_contenthash($v) { $this->contenthash_vc128uniq = $v; return $this; }
  function get_contenthash($v = NULL) { if (!is_null($v)) $this->set_contenthash($v); return $this->contenthash_vc128uniq; }

}

