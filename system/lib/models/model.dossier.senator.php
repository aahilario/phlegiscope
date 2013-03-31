<?php

/*
 * Class SenatorDossierModel
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class SenatorDossierModel extends RepresentativeDossierModel {
  
  var $fullname_vc128uniq = NULL;
  var $contact_json_vc2048 = NULL;
  var $member_uuid_vc64 = NULL; // Basically a hash of the URL and full name
  var $avatar_image_vc8192 = NULL; // Avatar image base64-encoded
  var $avatar_url_vc1024 = NULL;
  var $bio_url_vc1024 = NULL;
	var $create_time_utx = NULL;
  var $last_fetch_utx = NULL;

  function __construct() {
    parent::__construct();
  }

  function & set_fullname($v) { $this->fullname_vc128uniq = $v; return $this; }
  function get_fullname($v = NULL) { if (!is_null($v)) $this->set_fullname($v); return $this->fullname_vc128uniq; }

  function & set_bio_url($v) { $this->bio_url_vc1024 = $v; return $this; }
  function get_bio_url($v = NULL) { if (!is_null($v)) $this->set_bio_url($v); return $this->bio_url_vc1024; }

  function & set_create_time($v) { $this->create_time_utx = $v; return $this; }
  function get_create_time($v = NULL) { if (!is_null($v)) $this->set_create_time($v); return $this->create_time_utx; }

  function & set_last_fetch($v) { $this->last_fetch_utx = $v; return $this; }
  function get_last_fetch($v = NULL) { if (!is_null($v)) $this->set_last_fetch($v); return $this->last_fetch_utx; }

  function & set_avatar_url($v) { $this->avatar_url_vc1024 = $v; return $this; }
  function get_avatar_url($v = NULL) { if (!is_null($v)) $this->set_avatar_url($v); return $this->avatar_url_vc1024; }

  function & set_member_uuid($v) { $this->member_uuid_vc64 = $v; return $this; }
  function get_member_uuid($v = NULL) { if (!is_null($v)) $this->set_member_uuid($v); return $this->member_uuid_vc64; }

  function & set_contact_json($v) { 
    $this->contact_json_vc2048 = is_array($v)
      ? json_encode($v)
      : $v
      ;
    return $this;
  }
  function get_contact_json($v = NULL) { 
    if (!is_null($v)) $this->set_contact_json($v);
    return json_decode($this->contact_json_vc2048,TRUE);
  }

  function & set_avatar_image($v) { $this->avatar_image_vc8192 = $v; return $this; }
  function get_avatar_image($v = NULL) { if (!is_null($v)) $this->set_avatar_image($v); return $this->avatar_image_vc8192; }


}
