<?php

/*
 * Class SenatorCommitteeEdgeModel
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class SenatorCommitteeEdgeModel extends UrlEdgeModel {
  
	var $create_time_utx = NULL;
	var $validity_end_utx = NULL;
  var $last_fetch_utx = NULL;
  var $role_vc64 = NULL;
  var $b_int11 = NULL;
  var $a_int11 = NULL;

  function __construct(SenatorDossierModel & $a = NULL, SenateCommitteeModel & $b = NULL) {
    if ( is_object($a) && is_object($b) )
    parent::__construct($a->get_id(),$b->get_id());
    else
    parent::__construct(NULL,NULL);
  }

  function fetch_senators(SenateCommitteeModel & $b, $iterating = TRUE) {
		return $iterating
			? $this->where(array('AND' => array(
					'b'    => $b->get_id(),
				)))->recordfetch_setup()
		  : $this->fetch_children($b->get_id())
			;
  }

  function fetch_committees(SenatorDossierModel & $a, $iterating = TRUE) {
		return $iterating
			? $this->where(array('AND' => array(
					'a'    => $a->get_id(),
				)))->recordfetch_setup()
		  : $this->fetch_children($a->get_id())
			;
  }

	function fetch_association($role, SenatorDossierModel & $a, SenateCommitteeModel & $b, $iterator = TRUE) {
		return $iterator
			? $this->where(array('AND' => array(
					'a'    => $a->get_id(),
					'b'    => $b->get_id(),
					'role' => $role,
				)))->recordfetch_setup()
			: $this->fetch(array(
					'a'    => $a->get_id(),
					'b'    => $b->get_id(),
					'role' => $role,
				),'AND')
			;
	}

  function associate(SenatorDossierModel & $a, SenateCommitteeModel & $b) {
    return parent::stow($a->get_id(), $b->get_id());
  }

	// Mandatory accessors 
	
  function & set_create_time($v) { $this->create_time_utx = $v; return $this; }
  function get_create_time($v = NULL) { if (!is_null($v)) $this->set_create_time($v); return $this->create_time_utx; }

  function & set_last_fetch($v) { $this->last_fetch_utx = $v; return $this; }
  function get_last_fetch($v = NULL) { if (!is_null($v)) $this->set_last_fetch($v); return $this->last_fetch_utx; }

  function & set_role($v) { $this->role_vc64 = $v; return $this; }
  function get_role($v = NULL) { if (!is_null($v)) $this->set_role($v); return $this->role_vc64; }

  function & set_b($v) { $this->b_int11 = $v; return $this; }
  function get_b($v = NULL) { if (!is_null($v)) $this->set_b($v); return $this->b_int11; }

  function & set_a($v) { $this->a_int11 = $v; return $this; }
  function get_a($v = NULL) { if (!is_null($v)) $this->set_a($v); return $this->a_int11; }  

}

