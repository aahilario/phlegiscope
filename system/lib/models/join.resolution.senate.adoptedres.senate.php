<?php

/*
 * Class SenateAdoptedresSenateResolutionJoin
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class SenateAdoptedresSenateResolutionJoin extends ModelJoin {
  
  // Join table model
  var $senate_adoptedres_SenateAdoptedresDocumentModel;
  var $senate_resolution_SenateResolutionDocumentModel;

  function __construct() {
    parent::__construct();
    $this->dump_accessor_defs_to_syslog();
    $this->recursive_dump($this->get_attrdefs(),'(marker) "+++++++"');
  }

  function & set_senate_adoptedres($v) { $this->senate_adoptedres_SenateAdoptedresDocumentModel = $v; return $this; }
  function get_senate_adoptedres($v = NULL) { if (!is_null($v)) $this->set_senate_adoptedres($v); return $this->senate_adoptedres_SenateAdoptedresDocumentModel; }

  function & set_senate_resolution($v) { $this->senate_resolution_SenateResolutionDocumentModel = $v; return $this; }
  function get_senate_resolution($v = NULL) { if (!is_null($v)) $this->set_senate_resolution($v); return $this->senate_resolution_SenateResolutionDocumentModel; }

}

