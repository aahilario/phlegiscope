<?php

/*
 * Class HouseBillRepresentativeDossierJoin
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class HouseBillRepresentativeDossierJoin extends ModelJoin {
  
	var $relation_to_bill_vc32 = NULL;
	var $congress_tag_vc8 = NULL;
	var $create_time_utx = NULL;

  // Join table model
  var $house_bill_HouseBillDocumentModel;
  var $representative_dossier_RepresentativeDossierModel;

  function __construct() {
    parent::__construct();
    // $this->dump_accessor_defs_to_syslog();
    // $this->recursive_dump($this->get_attrdefs(),'(marker) "+++++++"');
  }

	function __destruct() {
		unset($this->relation_to_bill_vc32);
		unset($this->create_time_utx);
		unset($this->house_bill_HouseBillDocumentModel);
		unset($this->representative_dossier_RepresentativeDossierModel);
		$this->syslog(__FUNCTION__,__LINE__, "(warning) - - - - - - - - - - Destroying " . get_class($this));
	}

  function & set_house_bill($v) { $this->house_bill_HouseBillDocumentModel = $v; return $this; }
  function get_house_bill($v = NULL) { if (!is_null($v)) $this->set_house_bill($v); return $this->house_bill_HouseBillDocumentModel; }

  function & set_representative_dossier($v) { $this->representative_dossier_RepresentativeDossierModel = $v; return $this; }
  function get_representative_dossier($v = NULL) { if (!is_null($v)) $this->set_representative_dossier($v); return $this->representative_dossier_RepresentativeDossierModel; }

	function & set_relation_to_bill($v) { $this->relation_to_bill_vc32 = $v; return $this; }
	function get_relation_to_bill($v = NULL) { if (!is_null($v)) $this->set_relation_to_bill($v); return $this->relation_to_bill_vc32; }

	function & set_congress_tag($v) { $this->congress_tag_vc8 = $v; return $this; }
	function get_congress_tag($v = NULL) { if (!is_null($v)) $this->set_congress_tag($v); return $this->congress_tag_vc8; }

	function & set_create_time($v) { $this->create_time_utx = $v; return $this; }
	function get_create_time($v = NULL) { if (!is_null($v)) $this->set_create_time($v); return $this->create_time_utx; }

	function remap_parsed_housebill_template_entries(& $a, $k) {
		$a["representative"] = array_merge(
			$a["representative"],
			array_merge(
				is_null($a["meta"]["principal-author"]["mapped"])
				? array("raw" => $a["meta"]["principal-author"]["raw"])
				: array(),
				array("fkey" => $a["meta"]["principal-author"]["mapped"])
			)
		);
	 	unset($a["meta"]["principal-author"]);
	}
	function prepare_cached_records(& $bill_cache) {
		// See CongressHbListParseUtility::get_parsed_housebill_template() 
		$remapped = array();
		foreach ( $bill_cache as $e => $entry ) {
			$entry['representative'] = array_merge(
				$entry['representative'],
				array_merge(
					is_null($entry["meta"]["principal-author"]["mapped"])
					? array("raw" => $entry["meta"]["principal-author"]["raw"])
					: array(),
					array("fkey" => $entry["meta"]["principal-author"]["mapped"])
				)
			);
			$remapped[$e] = $entry;
		}
		$bill_cache = $remapped;
		/*
		return array_walk($bill_cache,create_function(
			'& $a, $k, $s', '$s->remap_parsed_housebill_template_entries($a, $k);'
		), $this);
		*/
	}

}

