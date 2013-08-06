<?php

/*
 * Class SenateCommitteeSenatorDossierJoin
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class SenateCommitteeSenatorDossierJoin extends ModelJoin {
  
  // Join table model
  var $senate_committee_SenateCommitteeModel;
  var $senator_dossier_SenatorDossierModel;
	var $create_time_utx = NULL;
	var $validity_end_utx = NULL;
  var $last_fetch_utx = NULL;
  var $role_vc64 = NULL;
  var $target_congress_vc8 = NULL;

  function __construct() {
    parent::__construct();
    //$this->dump_accessor_defs_to_syslog();
    //$this->recursive_dump($this->get_attrdefs(),'(marker) "+++++++"');
  }

	function fetch_association($role, SenatorDossierModel & $a, SenateCommitteeModel & $b, $iterator = TRUE) {
		return $iterator
			? $this->where(array('AND' => array(
					'senator_dossier'  => $a->get_id(),
					'senate_committee' => $b->get_id(),
					'role'             => $role,
				)))->recordfetch_setup()
			: $this->fetch(array(
					'senator_dossier'  => $a->get_id(),
					'senate_committee' => $b->get_id(),
					'role'             => $role,
				),'AND')
			;
	}

  function fetch_committees(SenatorDossierModel & $a, $iterating = TRUE, $additional_params = array()) {
		if ( !$a->in_database() ) {
			$this->syslog(__FUNCTION__,__LINE__, "(marker) - - - - - - Unable to retrieve Committees related to the given dossier");
			return FALSE;
		}
		return $iterating
			? $this->where(array('AND' => array_merge(
					$additional_params,
					array('senator_dossier' => $a->get_id())
				)))->recordfetch_setup()
			: $this->fetch(array_merge(
					$additional_params,
					array('senator_dossier' => $a->get_id())
				),'AND')
			;
  }


  function & set_senate_committee($v) { $this->senate_committee_SenateCommitteeModel = $v; return $this; }
  function get_senate_committee($v = NULL) { if (!is_null($v)) $this->set_senate_committee($v); return $this->senate_committee_SenateCommitteeModel; }

  function & set_senator_dossier($v) { $this->senator_dossier_SenatorDossierModel = $v; return $this; }
  function get_senator_dossier($v = NULL) { if (!is_null($v)) $this->set_senator_dossier($v); return $this->senator_dossier_SenatorDossierModel; }

  function & set_create_time($v) { $this->create_time_utx = $v; return $this; }
  function get_create_time($v = NULL) { if (!is_null($v)) $this->set_create_time($v); return $this->create_time_utx; }

  function & set_last_fetch($v) { $this->last_fetch_utx = $v; return $this; }
  function get_last_fetch($v = NULL) { if (!is_null($v)) $this->set_last_fetch($v); return $this->last_fetch_utx; }

  function & set_role($v) { $this->role_vc64 = $v; return $this; }
  function get_role($v = NULL) { if (!is_null($v)) $this->set_role($v); return $this->role_vc64; }

  function & set_target_congress($v) { $this->target_congress_vc8 = $v; return $this; }
  function get_target_congress($v = NULL) { if (!is_null($v)) $this->set_target_congress($v); return $this->target_congress_vc8; }

  function & set_id($v) { $this->id = $v; return $this; }

}

