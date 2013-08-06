<?php

/*
 * Class SenatorDossierSenatorDossierJoin
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class SenatorDossierSenatorDossierJoin extends DatabaseUtility {
  
  // Join table model
  var $left_senator_dossier_SenatorDossierModel;
  var $right_senator_dossier_SenatorDossierModel;

	var $matchable_hash_vc128 = NULL;
	var $matchable_vc2048 = NULL;
	var $blob_hash_vc128 = NULL;
	var $blob_blob = NULL;
	var $jointype_vc16 = NULL; // Record type
	var $create_time_utx = NULL;
	var $update_time_utx = NULL;

	var $congress_CongressDocumentModel = NULL;

  function __construct() {
    parent::__construct();
    $this->dump_accessor_defs_to_syslog();
    $this->recursive_dump($this->get_attrdefs(),'(marker) "+++++++"');
  }

  function & set_left_senator_dossier($v) { $this->left_senator_dossier_SenatorDossierModel = $v; return $this; }
  function get_left_senator_dossier($v = NULL) { if (!is_null($v)) $this->set_left_senator_dossier($v); return $this->left_senator_dossier_SenatorDossierModel; }

  function & set_right_senator_dossier($v) { $this->right_senator_dossier_SenatorDossierModel = $v; return $this; }
  function get_right_senator_dossier($v = NULL) { if (!is_null($v)) $this->set_right_senator_dossier($v); return $this->right_senator_dossier_SenatorDossierModel; }

  function & set_matchable_hash($v) { $this->matchable_hash_vc128 = $v; return $this; }
  function get_matchable_hash($v = NULL) { if (!is_null($v)) $this->set_matchable_hash($v); return $this->matchable_hash_vc128; }

  function & set_matchable($v) { $this->matchable_vc2048 = $v; return $this; }
  function get_matchable($v = NULL) { if (!is_null($v)) $this->set_matchable($v); return $this->matchable_vc2048; }

  function & set_blob_hash($v) { $this->blob_hash_vc128 = $v; return $this; }
  function get_blob_hash($v = NULL) { if (!is_null($v)) $this->set_blob_hash($v); return $this->blob_hash_vc128; }

  function & set_blob($v) { $this->blob_blob = $v; return $this; }
  function get_blob($v = NULL) { if (!is_null($v)) $this->set_blob($v); return $this->blob_blob; }

  function & set_jointype($v) { $this->jointype_vc16 = $v; return $this; }
  function get_jointype($v = NULL) { if (!is_null($v)) $this->set_jointype($v); return $this->jointype_vc16; }

  function & set_create_time($v) { $this->create_time_utx = $v; return $this; }
  function get_create_time($v = NULL) { if (!is_null($v)) $this->set_create_time($v); return $this->create_time_utx; }

  function & set_update_time($v) { $this->update_time_utx = $v; return $this; }
  function get_update_time($v = NULL) { if (!is_null($v)) $this->set_update_time($v); return $this->update_time_utx; }

  function & set_congress($v) { $this->congress_CongressDocumentModel = $v; return $this; }
  function get_congress($v = NULL) { if (!is_null($v)) $this->set_congress($v); return $this->congress_CongressDocumentModel; }
}

