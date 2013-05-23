<?php

/*
 * Class SenateCommitteeReportSenateResolutionJoin
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class SenateCommitteeReportSenateResolutionJoin extends DatabaseUtility {
  
  // Join table model
  var $senate_committee_report_SenateCommitteeReportDocumentModel;
  var $senate_resolution_SenateResolutionDocumentModel;

  function __construct() {
    parent::__construct();
    // $this->dump_accessor_defs_to_syslog();
    // $this->recursive_dump($this->get_attrdefs(),'(marker) "+++++++"');
  }

  function & set_senate_committee_report($v) { $this->senate_committee_report_SenateCommitteeReportDocumentModel = $v; return $this; }
  function get_senate_committee_report($v = NULL) { if (!is_null($v)) $this->set_senate_committee_report($v); return $this->senate_committee_report_SenateCommitteeReportDocumentModel; }

  function & set_senate_resolution($v) { $this->senate_resolution_SenateResolutionDocumentModel = $v; return $this; }
  function get_senate_resolution($v = NULL) { if (!is_null($v)) $this->set_senate_resolution($v); return $this->senate_resolution_SenateResolutionDocumentModel; }

}

