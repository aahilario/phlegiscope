<?php

/*
 * Class SenateConcurrentresSenatorDossierJoin
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class SenateConcurrentresSenatorDossierJoin extends DatabaseUtility {
  
  // Join table model
  var $senate_concurrentres_SenateConcurrentresDocumentModel;
  var $senator_dossier_SenatorDossierModel;

  function __construct() {
    parent::__construct();
    $this->dump_accessor_defs_to_syslog();
    $this->recursive_dump($this->get_attrdefs(),'(marker) "+++++++"');
  }

  function & set_senate_concurrentres($v) { $this->senate_concurrentres_SenateConcurrentresDocumentModel = $v; return $this; }
  function get_senate_concurrentres($v = NULL) { if (!is_null($v)) $this->set_senate_concurrentres($v); return $this->senate_concurrentres_SenateConcurrentresDocumentModel; }

  function & set_senator_dossier($v) { $this->senator_dossier_SenatorDossierModel = $v; return $this; }
  function get_senator_dossier($v = NULL) { if (!is_null($v)) $this->set_senator_dossier($v); return $this->senator_dossier_SenatorDossierModel; }

}

