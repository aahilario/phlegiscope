<?php

/*
 * Class SenateHouseConcurrentresSenatorDossierJoin
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class SenateHouseConcurrentresSenatorDossierJoin extends ModelJoin {
  
  // Join table model
  var $senate_house_concurrentres_SenateHouseConcurrentresDocumentModel;
  var $senator_dossier_SenatorDossierModel;

  function __construct() {
    parent::__construct();
    $this->dump_accessor_defs_to_syslog();
    $this->recursive_dump($this->get_attrdefs(),'(marker) "+++++++"');
  }

  function & set_senate_house_concurrentres($v) { $this->senate_house_concurrentres_SenateHouseConcurrentresDocumentModel = $v; return $this; }
  function get_senate_house_concurrentres($v = NULL) { if (!is_null($v)) $this->set_senate_house_concurrentres($v); return $this->senate_house_concurrentres_SenateHouseConcurrentresDocumentModel; }

  function & set_senator_dossier($v) { $this->senator_dossier_SenatorDossierModel = $v; return $this; }
  function get_senator_dossier($v = NULL) { if (!is_null($v)) $this->set_senator_dossier($v); return $this->senator_dossier_SenatorDossierModel; }

}

