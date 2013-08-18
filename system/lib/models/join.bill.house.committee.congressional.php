<?php

/*
 * Class CongressionalCommitteeHouseBillJoin
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class CongressionalCommitteeHouseBillJoin extends ModelJoin {
  
  var $jointype_vc64 = NULL; // Arbitrary, programmer-chosen label; should probably be an enum-like type
	var $congress_tag_vc8 = NULL; // These Joins are Congress Session-specific 
  var $create_time_utx = NULL;

  // Join table model
	var $payload_blob = NULL; // Particulars of join type
  var $congressional_committee_CongressionalCommitteeDocumentModel;
  var $house_bill_HouseBillDocumentModel;

  function __construct() {
    parent::__construct();
    //  $this->dump_accessor_defs_to_syslog();
    //  $this->recursive_dump($this->get_attrdefs(),'(marker) "+++++++"');
  }

  function __destruct() {
    unset($this->jointype_vc64);
    unset($this->create_time_utx);
    unset($this->congressional_committee_CongressionalCommitteeDocumentModel);
    unset($this->house_bill_HouseBillDocumentModel);
		if ($this->debug_final_sql) $this->syslog(__FUNCTION__,__LINE__, "(warning) - - - - - - - - - - Destroying " . get_class($this));
  }

	function & set_congress_tag($v) { $this->congress_tag_vc8 = $v; return $this; }
	function get_congress_tag($v = NULL) { if (!is_null($v)) $this->set_congress_tag($v); return $this->congress_tag_vc8; }

  function & set_congressional_committee($v) { $this->congressional_committee_CongressionalCommitteeDocumentModel = $v; return $this; }
  function get_congressional_committee($v = NULL) { if (!is_null($v)) $this->set_congressional_committee($v); return $this->congressional_committee_CongressionalCommitteeDocumentModel; }

  function & set_house_bill($v) { $this->house_bill_HouseBillDocumentModel = $v; return $this; }
  function get_house_bill($v = NULL) { if (!is_null($v)) $this->set_house_bill($v); return $this->house_bill_HouseBillDocumentModel; }

  function & set_jointype($v) { $this->jointype_vc64 = $v; return $this; }
  function get_jointype($v = NULL) { if (!is_null($v)) $this->set_jointype($v); return $this->jointype_vc64; }

  function & set_create_time($v) { $this->create_time_utx = $v; return $this; }
  function get_create_time($v = NULL) { if (!is_null($v)) $this->set_create_time($v); return $this->create_time_utx; }

  function remap_parsed_housebill_template_entries(& $a, $k) {
    $is_mapped = 0 < intval(array_element($a["meta"]["main-committee"],"mapped"));
    if ($is_mapped) { 
      // $this->recursive_dump($a["meta"]["main-committee"],"(marker) ----- {$k} ---- - -");
      $a["committee"] = array_merge(
        is_array($a["committee"]) ? $a["committee"] : array(),
        array(
          "raw"            => $a["meta"]["main-committee"]["raw"],
          "fkey"           => $a["meta"]["main-committee"]["mapped"],
          "url"            => $a["meta"]["main-committee"]["url"],
          "committee_name" => $a["meta"]["main-committee"]["committee_name"]
        )
      );
      unset($a["meta"]["main-committee"]);
    }
  }
  function prepare_cached_records(& $bill_cache) {
    // See CongressHbListParseUtility::get_parsed_housebill_template() 
    return array_walk($bill_cache,create_function(
      '& $a, $k, $s', '$s->remap_parsed_housebill_template_entries($a, $k);'
    ), $this);
  }

}

