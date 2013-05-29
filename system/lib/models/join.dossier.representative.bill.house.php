<?php

/*
 * Class HouseBillRepresentativeDossierJoin
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class HouseBillRepresentativeDossierJoin extends DatabaseUtility {
  
	var $relation_to_bill_vc32 = NULL;
	var $create_date_utx = NULL;

  // Join table model
  var $house_bill_HouseBillDocumentModel;
  var $representative_dossier_RepresentativeDossierModel;

  function __construct() {
    parent::__construct();
    $this->dump_accessor_defs_to_syslog();
    $this->recursive_dump($this->get_attrdefs(),'(marker) "+++++++"');
  }

  function & set_house_bill($v) { $this->house_bill_HouseBillDocumentModel = $v; return $this; }
  function get_house_bill($v = NULL) { if (!is_null($v)) $this->set_house_bill($v); return $this->house_bill_HouseBillDocumentModel; }

  function & set_representative_dossier($v) { $this->representative_dossier_RepresentativeDossierModel = $v; return $this; }
  function get_representative_dossier($v = NULL) { if (!is_null($v)) $this->set_representative_dossier($v); return $this->representative_dossier_RepresentativeDossierModel; }

	function & set_relation_to_bill($v) { $this->relation_to_bill_vc32 = $v; return $this; }
	function get_relation_to_bill($v = NULL) { if (!is_null($v)) $this->set_relation_to_bill($v); return $this->relation_to_bill_vc32; }

	function & set_create_date($v) { $this->create_date_utx = $v; return $this; }
	function get_create_date($v = NULL) { if (!is_null($v)) $this->set_create_date($v); return $this->create_date_utx; }

}

