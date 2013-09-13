<?php

/*
 * Class SenateHouseJointresSenateJournalJoin
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class SenateHouseJointresSenateJournalJoin extends DatabaseUtility {
  
  // Join table model
  var $senate_house_jointres_SenateHouseJointresDocumentModel;
  var $senate_journal_SenateJournalDocumentModel;

  function __construct() {
    parent::__construct();
    $this->dump_accessor_defs_to_syslog();
    $this->recursive_dump($this->get_attrdefs(),'(marker) "+++++++"');
  }

  function & set_senate_house_jointres($v) { $this->senate_house_jointres_SenateHouseJointresDocumentModel = $v; return $this; }
  function get_senate_house_jointres($v = NULL) { if (!is_null($v)) $this->set_senate_house_jointres($v); return $this->senate_house_jointres_SenateHouseJointresDocumentModel; }

  function & set_senate_journal($v) { $this->senate_journal_SenateJournalDocumentModel = $v; return $this; }
  function get_senate_journal($v = NULL) { if (!is_null($v)) $this->set_senate_journal($v); return $this->senate_journal_SenateJournalDocumentModel; }

}

