<?php

/*
 * Class CongressRepresentativeDossierJoin
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class CongressRepresentativeDossierJoin extends DatabaseUtility {
  
  // Join table model
  var $congress_CongressDocumentModel;
  var $representative_dossier_RepresentativeDossierModel;

  function __construct() {
    parent::__construct();
    //$this->dump_accessor_defs_to_syslog();
    //$this->recursive_dump($this->get_attrdefs(),'(marker) "+++++++"');
  }

  function & set_congress($v) { $this->congress_CongressDocumentModel = $v; return $this; }
  function get_congress($v = NULL) { if (!is_null($v)) $this->set_congress($v); return $this->congress_CongressDocumentModel; }

  function & set_representative_dossier($v) { $this->representative_dossier_RepresentativeDossierModel = $v; return $this; }
  function get_representative_dossier($v = NULL) { if (!is_null($v)) $this->set_representative_dossier($v); return $this->representative_dossier_RepresentativeDossierModel; }

}

