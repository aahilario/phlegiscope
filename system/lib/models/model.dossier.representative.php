<?php

/*
 * Class RepresentativeDossierModel
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class RepresentativeDossierModel extends DatabaseUtility {
  
  var $fullname_vc128uniq = NULL;
  var $firstname_vc64 = NULL;
  var $mi_vc8 = NULL;
  var $surname_vc48 = NULL;
  var $namesuffix_vc8 = NULL;
  var $bio_url_vc1024 = NULL;
  var $create_time_utx = NULL;
  var $last_fetch_utx = NULL;
  var $contact_json_vc2048 = NULL;
  var $member_uuid_vc64 = NULL; // Basically a hash of the URL and full name
  var $avatar_image_blob = NULL; // Avatar image base64-encoded
  var $avatar_url_vc1024 = NULL;

  var $committees_CongressionalCommitteeDocumentModel = NULL;
  var $housebills_HouseBillDocumentModel = NULL;

  function __construct() {
    parent::__construct();
  }

  function __destruct() {/*{{{*/
    unset($this->fullname_vc128uniq);
    unset($this->firstname_vc64);
    unset($this->mi_vc8);
    unset($this->surname_vc48);
    unset($this->namesuffix_vc8);
    unset($this->bio_url_vc1024);
    unset($this->create_time_utx);
    unset($this->last_fetch_utx);
    unset($this->contact_json_vc2048);
    unset($this->member_uuid_vc64);
    unset($this->avatar_image_blob);
    unset($this->avatar_url_vc1024);
    unset($this->committees_CongressionalCommitteeDocumentModel);
    unset($this->housebills_HouseBillDocumentModel);
  }/*}}}*/

  function & set_committees($v) { $this->committees_CongressionalCommitteeDocumentModel = $v; return $this; }
  function get_committees($v = NULL) { return is_null($v) ? $this->committees_CongressionalCommitteeDocumentModel : array_element($this->committees_CongressionalCommitteeDocumentModel,$v); }

  function & set_housebills($v) { $this->housebills_HouseBillDocumentModel = $v; return $this; }
  function get_housebills($v = NULL) { return is_null($v) ? $this->housebills_HouseBillDocumentModel : array_element($this->housebills_HouseBillDocumentModel,$v); }

  function & set_fullname($v) { $this->fullname_vc128uniq = $v; return $this; }
  function get_fullname($v = NULL) { if (!is_null($v)) $this->set_fullname($v); return $this->fullname_vc128uniq; }

  function & set_firstname($v) { $this->firstname_vc64 = $v; return $this; }
  function get_firstname($v = NULL) { if (!is_null($v)) $this->set_firstname($v); return $this->firstname_vc64; }

  function & set_mi($v) { $this->mi_vc8 = $v; return $this; }
  function get_mi($v = NULL) { if (!is_null($v)) $this->set_mi($v); return $this->mi_vc8; }

  function & set_surname($v) { $this->surname_vc48 = $v; return $this; }
  function get_surname($v = NULL) { if (!is_null($v)) $this->set_surname($v); return $this->surname_vc48; }

  function & set_namesuffix($v) { $this->namesuffix_vc8 = $v; return $this; }
  function get_namesuffix($v = NULL) { if (!is_null($v)) $this->set_namesuffix($v); return $this->namesuffix_vc8; }

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

  function & set_contact_json($v) { /*{{{*/
    $this->contact_json_vc2048 = is_array($v)
      ? json_encode($v)
      : $v
      ;
    return $this;
  }/*}}}*/
  function get_contact_json($v = NULL) { /*{{{*/
    if (!is_null($v)) $this->set_contact_json($v);
    return json_decode($this->contact_json_vc2048,TRUE);
  }/*}}}*/

  function & set_avatar_image($v) { $this->avatar_image_blob = $v; return $this; }

  function get_avatar_image($v = NULL) {/*{{{*/
    if (!is_null($v)) $this->set_avatar_image($v);

    if ( empty($this->avatar_image_blob) ) {/*{{{*/
      $url = new UrlModel();
      $url->fetch(UrlModel::get_url_hash($this->get_avatar_url()),'urlhash');
      if ( $url->in_database() ) {
        $image_content_type = $url->get_content_type();
        $this->avatar_image_blob = base64_encode($url->get_pagecontent());
        $this->avatar_image_blob = "data:{$image_content_type};base64,{$this->avatar_image_blob}";
        $this->stow();
        $this->fetch($member_uuid, 'member_uuid');
        $this->syslog(__FUNCTION__,__LINE__, "(marker) Stowed {$member['fullname']} avatar {$this->avatar_image_blob}");
      }
    }/*}}}*/

    if ( is_null($this->avatar_image_blob) || (strtoupper($this->avatar_image_blob) == 'NULL') ) $this->avatar_image_blob = '';

    return $this->avatar_image_blob;
   }/*}}}*/

  function parse_name($n) {/*{{{*/
    $cleanup = '@\((.*)\)$@i';
    $n = preg_replace($cleanup,'',$n); // Remove trailing parenthesized characters
    $namepattern = '@^(.*),(.*)@iu';
    $misuffix     = '@(.*) (Jr\.|Sr\.|III|II|IV|VII|VI|V)*([ ]?[A-Z]\.)*(.*)*$@i';
    $match = array();
    preg_match($namepattern, $n,$match);
    $nameparts = array(
      'surname' => array_element($match,1),
      'given' => trim(array_element($match,2)),
    );
    $match = array();
    preg_match($misuffix, $nameparts['given'], $match);
    $nameparts['mi']     = trim(array_element($match,3));
    $nameparts['suffix'] = trim(array_element($match,2));
    $nameparts['given']  = trim(array_element($match,1));
    $match[0] = NULL;
    $match[1] = NULL;
    $match = array_filter($match);
    $suffix = '@ (Jr\.|Sr\.|III|II|IV|VII|VI|V)*@i';
    if ( empty($nameparts['suffix']) && !empty($nameparts['given']) && 1 == preg_match($suffix,$nameparts['given'], $match) ) {
      $match[0] = NULL;
      $match = array_values(array_filter($match));
      $nameparts['given']  = str_replace($match,'',$nameparts['given']);
      $nameparts['suffix'] = array_element($match,0);
    }
    return $nameparts;

  }/*}}}*/

  function replace_legislator_names_hotlinks(& $name) {/*{{{*/
    $nameparts = $this->parse_name($name);
    if ( is_array($nameparts) ) $nameparts = array_filter($nameparts);
    if ( !is_array($nameparts) || !(0 < count($nameparts)) ) return NULL;
    $original_name = $name;
    $matches = preg_replace('@[^A-Z]@i','(.*)',"{$nameparts['surname']},{$nameparts['given']}"); 
    $template = <<<EOH
<a class="legiscope-remote legislator-name-hotlink" href="{bio_url}" id="{urlhash}">{fullname}</a>
EOH;
    $s = NULL;
    $name = array();
    $this->where(array('AND' => array('fullname' => "REGEXP '^{$matches}'")))->recordfetch_setup();
    if ( $this->recordfetch($name) ) {
      $s = $template;
      foreach ( $name as $k => $v ) {
        $s = str_replace("{{$k}}", "{$v}", $s);
      }
      $urlhash = UrlModel::get_url_hash(array_element($name,'bio_url'));
      $s = str_replace('{urlhash}',$urlhash, $s);
      $name['original'] = $original_name;
      $name['parse'] = $nameparts;
      $name['parse']['fullname'] = $name['fullname'];
    }
    return $s;
  }/*}}}*/

  function stow() {
    return parent::stow();
  }

}

