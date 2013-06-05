<?php

/*
 * Class SenateBillSenateCommitteeReportJoin
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class SenateBillSenateCommitteeReportJoin extends ModelJoin {
  
  // Join table model
  var $senate_bill_SenateBillDocumentModel;
  var $senate_committee_report_SenateCommitteeReportDocumentModel;

  function __construct() {
    parent::__construct();
    // $this->dump_accessor_defs_to_syslog();
    // $this->recursive_dump($this->get_attrdefs(),'(marker) "+++++++"');
  }

  function & set_senate_bill($v) { $this->senate_bill_SenateBillDocumentModel = $v; return $this; }
  function get_senate_bill($v = NULL) { if (!is_null($v)) $this->set_senate_bill($v); return $this->senate_bill_SenateBillDocumentModel; }

  function & set_senate_committee_report($v) { $this->senate_committee_report_SenateCommitteeReportDocumentModel = $v; return $this; }
  function get_senate_committee_report($v = NULL) { if (!is_null($v)) $this->set_senate_committee_report($v); return $this->senate_committee_report_SenateCommitteeReportDocumentModel; }

}

