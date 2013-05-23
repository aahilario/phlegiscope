<?php

/*
 * Class SenateCommitteeReportSenateHousebillJoin
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class SenateCommitteeReportSenateHousebillJoin extends DatabaseUtility {
  
  // Join table model
  var $senate_committee_report_SenateCommitteeReportDocumentModel;
  var $senate_housebill_SenateHousebillDocumentModel;

  function __construct() {
    parent::__construct();
    // $this->dump_accessor_defs_to_syslog();
    // $this->recursive_dump($this->get_attrdefs(),'(marker) "+++++++"');
  }

  function & set_senate_committee_report($v) { $this->senate_committee_report_SenateCommitteeReportDocumentModel = $v; return $this; }
  function get_senate_committee_report($v = NULL) { if (!is_null($v)) $this->set_senate_committee_report($v); return $this->senate_committee_report_SenateCommitteeReportDocumentModel; }

  function & set_senate_housebill($v) { $this->senate_housebill_SenateHousebillDocumentModel = $v; return $this; }
  function get_senate_housebill($v = NULL) { if (!is_null($v)) $this->set_senate_housebill($v); return $this->senate_housebill_SenateHousebillDocumentModel; }

}

