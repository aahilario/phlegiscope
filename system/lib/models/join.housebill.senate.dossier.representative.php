<?php

/*
 * Class RepresentativeDossierSenateHousebillJoin
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class RepresentativeDossierSenateHousebillJoin extends ModelJoin {
  
  // Join table model
  var $representative_dossier_RepresentativeDossierModel;
  var $senate_housebill_SenateHousebillDocumentModel;

  var $relationship_vc16 = NULL; // Sponsor, author 
  var $relationship_date_dtm = NULL;
  var $create_time_utx = NULL;


  function __construct() {
    parent::__construct();
    //$this->dump_accessor_defs_to_syslog();
    //$this->recursive_dump($this->get_attrdefs(),'(marker) "+++++++"');
  }

  function & set_representative_dossier($v) { $this->representative_dossier_RepresentativeDossierModel = $v; return $this; }
  function get_representative_dossier($v = NULL) { if (!is_null($v)) $this->set_representative_dossier($v); return $this->representative_dossier_RepresentativeDossierModel; }

  function & set_senate_housebill($v) { $this->senate_housebill_SenateHousebillDocumentModel = $v; return $this; }
  function get_senate_housebill($v = NULL) { if (!is_null($v)) $this->set_senate_housebill($v); return $this->senate_housebill_SenateHousebillDocumentModel; }

  function & set_relationship($v) { $this->relationship_vc16 = $v; return $this; }
  function get_relationship($v = NULL) { if (!is_null($v)) $this->set_relationship($v); return $this->relationship_vc16; }

  function & set_relationship_date($v) { $this->relationship_date_dtm = $v; return $this; }
  function get_relationship_date($v = NULL) { if (!is_null($v)) $this->set_relationship_date($v); return $this->relationship_date_dtm; }

  function & set_create_time($v) { $this->create_time_utx = $v; return $this; }
  function get_create_time($v = NULL) { if (!is_null($v)) $this->set_create_time($v); return $this->create_time_utx; }

}

