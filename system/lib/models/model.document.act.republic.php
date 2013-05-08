<?php

/*
 * Class RepublicActDocumentModel
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class RepublicActDocumentModel extends DatabaseUtility {
  
  var $title_vc256uniq = NULL;
  var $sn_vc64uniq = NULL;
  var $origin_vc2048 = NULL;
  var $approval_date_vc64 = NULL;
  var $description_vc4096 = NULL;
  var $create_time_utx = NULL;
  var $last_fetch_utx = NULL;
  var $searchable_bool = NULL;
  var $congress_tag_vc8 = NULL;
  var $url_vc4096 = NULL;
	var $content_json_vc65535 = NULL;
	var $content_blob = NULL;

  function __construct() {
    parent::__construct();
  }

	/*
  function is_searchable($b = NULL) {
    if (!is_null($b)) $this->searchable_bool = $b;
    return $this->searchable_bool;
  }

  function set_title($v) {
    $this->title_vc256uniq = $v;
    return $this;
  }
  function set_sn($v) {
    $this->sn_vc64uniq = $v;
    return $this;
  }
  function set_origin($v) {
    $this->origin_vc2048 = $v;
    return $this;
  }
  function set_description($v) {
    $this->description_vc4096 = $v;
    return $this;
  }
  function set_create_time($v) {
    $this->create_time_utx = $v;
    return $this;
  }
  function set_last_fetch($v) {
    $this->last_fetch_utx = $v;
    return $this;
  }
  function set_searchable($v) {
    $this->searchable_bool = $v;
    return $this;
  }
  function set_url($v) {
    $this->url_vc4096 = $v;
    return $this;
  }
  function set_approval_date($v) {
    $this->approval_date_vc64 = $v;
    return $this;
  }
  function set_congress_tag($v) {
    $this->congress_tag_vc8 = $v;
    return $this;
  }

  function get_congress_tag($v = NULL) {
    return $this->congress_tag_vc8;
  }
  function get_title($v = NULL) {
    return $this->title_vc256uniq;
  }
  function get_sn($v = NULL) {
    return $this->sn_vc64uniq;
  }
  function get_origin($v = NULL) {
    return $this->origin_vc2048;
  }
  function get_description($v = NULL) {
    return $this->description_vc4096;
  }
  function get_create_time($v = NULL) {
    return $this->create_time_utx;
  }
  function get_last_fetch($v = NULL) {
    return $this->last_fetch_utx;
  }
  function get_searchable($v = NULL) {
    return $this->searchable_bool;
  }
  function get_url($v = NULL) {
    return $this->url_vc4096;
  }
  function get_approval_date($v = NULL) {
    return $this->approval_date_vc64;
  }
	*/

	function & set_title($v) { $this->title_vc256uniq = $v; return $this; }
	function get_title($v = NULL) { if (!is_null($v)) $this->set_title($v); return $this->title_vc256uniq; }

	function & set_sn($v) { $this->sn_vc64uniq = $v; return $this; }
	function get_sn($v = NULL) { if (!is_null($v)) $this->set_sn($v); return $this->sn_vc64uniq; }

	function & set_origin($v) { $this->origin_vc2048 = $v; return $this; }
	function get_origin($v = NULL) { if (!is_null($v)) $this->set_origin($v); return $this->origin_vc2048; }

	function & set_approval_date($v) { $this->approval_date_vc64 = $v; return $this; }
	function get_approval_date($v = NULL) { if (!is_null($v)) $this->set_approval_date($v); return $this->approval_date_vc64; }

	function & set_description($v) { $this->description_vc4096 = $v; return $this; }
	function get_description($v = NULL) { if (!is_null($v)) $this->set_description($v); return $this->description_vc4096; }

	function & set_create_time($v) { $this->create_time_utx = $v; return $this; }
	function get_create_time($v = NULL) { if (!is_null($v)) $this->set_create_time($v); return $this->create_time_utx; }

	function & set_last_fetch($v) { $this->last_fetch_utx = $v; return $this; }
	function get_last_fetch($v = NULL) { if (!is_null($v)) $this->set_last_fetch($v); return $this->last_fetch_utx; }

	function & set_searchable($v) { $this->searchable_bool = $v; return $this; }
	function get_searchable($v = NULL) { if (!is_null($v)) $this->set_searchable($v); return $this->searchable_bool; }

	function & set_congress_tag($v) { $this->congress_tag_vc8 = $v; return $this; }
	function get_congress_tag($v = NULL) { if (!is_null($v)) $this->set_congress_tag($v); return $this->congress_tag_vc8; }

	function & set_url($v) { $this->url_vc4096 = $v; return $this; }
	function get_url($v = NULL) { if (!is_null($v)) $this->set_url($v); return $this->url_vc4096; }

	function & set_content_json($v) { $this->content_json_vc65535 = $v; return $this; }
	function get_content_json($v = NULL) { if (!is_null($v)) $this->set_content_json($v); return $this->content_json_vc65535; }

	function & set_content($v) { $this->content_blob = $v; return $this; }
	function get_content($v = NULL) { if (!is_null($v)) $this->set_content($v); return $this->content_blob; }

  function get_standard_listing_markup($entry_value, $entry_name) {/*{{{*/

    $this->fetch($entry_value, $entry_name);
    if ( !$this->in_database() ) return NULL;

    if ( is_null($this->senate_bill) ) $this->senate_bill = new SenateBillDocumentModel(); 
    if ( is_null($this->house_bill) ) $this->house_bill = new HouseBillDocumentModel(); 

		$ra = array(
			'url'           => $this->get_url(),
			'desc'          => $this->get_description(),
			'bill-head'     => $this->get_sn(),
			'origin'        => $this->get_origin(),
			'approval_date' => $this->get_approval_date(),
			'congress_tag'  => $this->get_congress_tag(),
		);

    $cache_state = array('legiscope-remote');
    if ( $this->is_searchable() ) $cache_state[] = 'cached';

    // Extract origin components
    $house_bill_meta = NULL;
    $origin_parts = array();
    $origin_regex = '@^([^(]*)\((([A-Z]*)[0]*([0-9]*))[^A-Z0-9]*(([A-Z]*)[0]*([0-9]*))[^)]*\)@i';
    if (!( FALSE === preg_match_all($origin_regex, $ra['origin'], $origin_parts))) {/*{{{*/
      $origin_string = trim($origin_parts[1][0]);
      $origin_parts = array_filter(array(
        trim($origin_parts[3][0]) => trim(intval($origin_parts[4][0])),
        trim($origin_parts[6][0]) => trim(intval($origin_parts[7][0])),
      ));
      // ksort($origin_parts);
      // $this->recursive_dump($origin_parts,'(warning)');
      if ( array_key_exists('SB', $origin_parts) ) {/*{{{*/
        $record = array();
        if ( $this->senate_bill->
          where(array('AND' => array(
            'congress_tag' => $ra['congress_tag'],
            'url' => "http://www.senate.gov.ph/lis/bill_res.aspx?congress={$ra['congress_tag']}\&q=SBN-{$origin_parts['SB']}",
          )))->
          recordfetch_setup()->
          recordfetch($record) ) {
            // $this->recursive_dump($record,'(warning)');
            $origin_parts['SB'] = <<<EOH
<a class="legiscope-remote cached" href="{$record['url']}">{$record['sn']}</a>
EOH;
          }
      }/*}}}*/
      if ( array_key_exists('HB', $origin_parts) ) {/*{{{*/
        $record = array();
        if ( $this->house_bill->
          where(array('AND' => array(
            'congress_tag' => $ra['congress_tag'],
            'url' => "REGEXP '(http://www.congress.gov.ph/download/([^_]*)_{$ra['congress_tag']}/([^0-9]*)([0]*)({$origin_parts['HB']}).pdf)'",
          )))->
          recordfetch_setup()->
          recordfetch($record) ) {
            // $this->recursive_dump($record,'(warning)');
            $origin_parts['HB'] = <<<EOH
<a class="legiscope-remote cached" href="{$record['url']}">{$record['sn']}</a>
EOH;
            $hb = @json_decode($record['status'],TRUE);
            if ( !(FALSE == $hb)) {
              if ( is_null($house_bill_meta) ) 
              $house_bill_meta = <<<EOH
<span class="republic-act-meta">Principal Author: {$hb['Principal Author']}</span>
<span class="republic-act-meta">Main Referral: {$hb['Main Referral']}</span>
<span class="republic-act-meta">Status: {$hb['Status']}</span>

EOH;
            }
          }
      }/*}}}*/
      // $this->recursive_dump($origin_parts,'(warning)');
      $ra['origin'] = join('/', $origin_parts);
      $ra['origin'] = "{$origin_string} ({$ra['origin']})";
    }/*}}}*/
    $cache_state = join(' ', $cache_state);
		
		$urlhash = UrlModel::get_url_hash($ra['url']);
    if ( is_null($house_bill_meta) ) {
      $house_bill_meta = <<<EOH
<span class="republic-act-meta">Origin of legislation: {$ra['origin']}</span>
<span class="republic-act-meta">Passed into law: {$ra['approval_date']}</span>

EOH;
    }
    $replacement_line = utf8_encode(<<<EOH
<div class="republic-act-entry">
<span class="republic-act-heading"><a href="{$ra['url']}" class="{$cache_state}" id="{$urlhash}">{$ra['bill-head']}</a></span>
<span class="republic-act-desc"><a href="{$ra['url']}" class="legiscope-remote" id="title-{$urlhash}">{$ra['desc']}</a></span>
{$house_bill_meta}
</div>
EOH
    );
    return $replacement_line;

  }/*}}}*/
        

}
