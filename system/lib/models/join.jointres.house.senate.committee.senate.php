<?php

/*
 * Class SenateCommitteeSenateHouseJointresJoin
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class SenateCommitteeSenateHouseJointresJoin extends DatabaseUtility {
  
  // Join table model
  var $senate_committee_SenateCommitteeModel;
  var $senate_house_jointres_SenateHouseJointresDocumentModel;

  function __construct() {
    parent::__construct();
    $this->dump_accessor_defs_to_syslog();
    $this->recursive_dump($this->get_attrdefs(),'(marker) "+++++++"');
  }

  function & set_senate_committee($v) { $this->senate_committee_SenateCommitteeModel = $v; return $this; }
  function get_senate_committee($v = NULL) { if (!is_null($v)) $this->set_senate_committee($v); return $this->senate_committee_SenateCommitteeModel; }

  function & set_senate_house_jointres($v) { $this->senate_house_jointres_SenateHouseJointresDocumentModel = $v; return $this; }
  function get_senate_house_jointres($v = NULL) { if (!is_null($v)) $this->set_senate_house_jointres($v); return $this->senate_house_jointres_SenateHouseJointresDocumentModel; }

}

