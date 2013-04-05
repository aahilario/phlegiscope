<?php

/*
 * Class SenatorCommitteeEdgeModel
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class SenatorCommitteeEdgeModel extends UrlEdgeModel {
  
  function __construct(SenatorDossierModel & $a = NULL, SenateCommitteeModel & $b = NULL) {
    if ( is_object($a) && is_object($b) )
    parent::__construct($a->get_id(),$b->get_id());
    else
    parent::__construct(NULL,NULL);
  }

  function fetch_committees(SenatorDossierModel & $a) {
    return parent::fetch_children($a->get_id());
  }

  function associate(SenatorDossierModel & $a, SenateCommitteeModel & $b) {
    return parent::stow($a->get_id(), $b->get_id());
  }

}

