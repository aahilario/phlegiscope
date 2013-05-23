<?php

/*
 * Class CongressionalCommitteeDocumentModel
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class CongressionalCommitteeDocumentModel extends DatabaseUtility {
  
  var $committee_name_vc256uniq = NULL;
  var $jurisdiction_vc1024 = NULL;
  var $create_time_utx = NULL;
  var $last_fetch_utx = NULL;
  var $url_vc4096 = NULL;
  var $office_address_vc4096 = NULL;
  var $contact_json_vc4096 = NULL; // TODO: Create contact Directory entries
  var $representative_RepresentativeDossierModel = NULL;

  function __construct() {
    parent::__construct();
  }

  function & set_committee_name($v) { $this->committee_name_vc256uniq = $v; return $this; }
  function get_committee_name($v = NULL) { if (!is_null($v)) $this->set_committee_name($v); return $this->committee_name_vc256uniq; }

  function & set_short_code($v) { $this->short_code_vc32 = $v; return $this; }
  function get_short_code($v = NULL) { if (!is_null($v)) $this->set_short_code($v); return $this->short_code_vc32; }

  function & set_jurisdiction($v) { $this->jurisdiction_vc1024 = $v; return $this; }
  function get_jurisdiction($v = NULL) { if (!is_null($v)) $this->set_jurisdiction($v); return $this->jurisdiction_vc1024; }

  function & set_is_permanent($v) { $this->is_permanent_bool = $v; return $this; }
  function get_is_permanent($v = NULL) { if (!is_null($v)) $this->set_is_permanent($v); return $this->is_permanent_bool; }

  function & set_create_time($v) { $this->create_time_utx = $v; return $this; }
  function get_create_time($v = NULL) { if (!is_null($v)) $this->set_create_time($v); return $this->create_time_utx; }

  function & set_last_fetch($v) { $this->last_fetch_utx = $v; return $this; }
  function get_last_fetch($v = NULL) { if (!is_null($v)) $this->set_last_fetch($v); return $this->last_fetch_utx; }

  function & set_url($v) { $this->url_vc4096 = $v; return $this; }
  function get_url($v = NULL) { if (!is_null($v)) $this->set_url($v); return $this->url_vc4096; }

  function & set_office_address($v) { $this->office_address_vc4096 = $v; return $this; }
  function get_office_address($v = NULL) { if (!is_null($v)) $this->set_office_address($v); return $this->office_address_vc4096; }

  function & set_contact_json($v) { $this->contact_json_vc4096 = $v; return $this; }
  function get_contact_json($v = NULL) { if (!is_null($v)) $this->set_contact_json($v); return $this->contact_json_vc4096; }
}