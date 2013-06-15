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

	var $committee_SenateCommitteeModel = NULL;
  var $senate_bill_SenateBillDocumentModel = NULL;

  function __construct() {
    parent::__construct();
  }

  function & set_id($v) { $this->id = $v; return $this; }

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

  function cleanup_senator_name($senator_fullname) {/*{{{*/
    $senator_fullname = trim(preg_replace('@^Sen\.@i', '', $senator_fullname));
    // Deal with quotes mistakenly parsed as '?'
    $senator_fullname = str_replace(array('“',"\x09","[BR]","[EMPTY]"), array(' “',""," ",""), $senator_fullname);
    $senator_fullname = preg_replace(array('@(\?|\')([^?\']*)(\?|\')@','@([ ]+)@'),array("$2",' '), $senator_fullname);
    return $senator_fullname;
  }/*}}}*/

  function stow_senator($url, $member_fullname, array & $senator_info, UrlModel & $parent_url ) {/*{{{*/
    $parent_url_parts = UrlModel::parse_url($parent_url->get_url());
    $bio_url = array('url' => $url);
    $bio_url = ( !is_null($parent_url_parts) && is_array($parent_url_parts) )
      ? UrlModel::normalize_url($parent_url_parts, $bio_url)
      : $url
      ;
    $senator_fullname = $this->cleanup_senator_name($member_fullname);
    $senator_info = array(
      'id'       => NULL,
      'url'      => NULL, 
      'linktext' => NULL, 
    );
    $this->fetch($senator_fullname,'fullname');
    if ( $this->in_database() ) $senator_info['id'] = $this->get_id();
    if ( is_null($senator_info['id']) ) {
      $this->fetch($bio_url,'bio_url');
      if ( $this->in_database() ) $senator_info['id'] = $this->get_id();
    }
    if ( is_null($senator_info['id']) ) {
      $this->syslog(__FUNCTION__,__LINE__, "(warning) Neither name '{$senator_fullname}' nor bio URL {$bio_url} has a match in DB");
      $member_uuid = sha1(mt_rand(10000,100000) . UrlModel::get_url_hash($bio_url) . "{$senator_fullname}");
      $senator_info['id'] = $this->
        set_member_uuid($member_uuid)->
        set_fullname($senator_fullname)->
        set_bio_url($bio_url)->
        set_create_time(time())->
        set_last_fetch(time())->
        fields('member_uuid,fullname,bio_url,create_time,last_fetch')->
        stow();
    }
    if ( !is_null($senator_info['id']) ) {
      $senator_info['url'] = $this->get_bio_url();
      $senator_info['linktext'] = $this->get_fullname();
    }
    return $senator_info['id'];
  }/*}}}*/

}
