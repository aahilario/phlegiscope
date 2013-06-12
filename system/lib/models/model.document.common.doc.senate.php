<?php

/*
 * Class SenateDocCommonDocumentModel
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class SenateDocCommonDocumentModel extends UrlModel {
  
  protected $senate_bill = NULL;
  protected $house_bill  = NULL;

  function __construct() {
    parent::__construct();
  }

  function is_searchable() {
    return property_exists($this,'searchable') ? ($this->searchable) : FALSE;
  }

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
    $origin_parts    = array();
    $origin_regex    = '@^([^(]*)\((([A-Z]*)[0]*([0-9]*))[^A-Z0-9]*(([A-Z]*)[0]*([0-9]*))[^)]*\)@i';

    if (!( FALSE === preg_match_all($origin_regex, $ra['origin'], $origin_parts))) {/*{{{*/
      $origin_string = trim(array_element(array_element($origin_parts,1,array()),0));
      $origin_parts = array_filter(array(
        trim(array_element(array_element($origin_parts,3,array()),0)) => trim(intval(array_element(array_element($origin_parts,4,array()),0))),
        trim(array_element(array_element($origin_parts,6,array()),0)) => trim(intval(array_element(array_element($origin_parts,7,array()),0))),
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
    $replacement_line = <<<EOH
<div class="republic-act-entry">
<span class="republic-act-heading"><a href="{$ra['url']}" class="{$cache_state}" id="{$urlhash}">{$ra['bill-head']}</a></span>
<span class="republic-act-desc"><a href="{$ra['url']}" class="legiscope-remote" id="title-{$urlhash}">{$ra['desc']}</a></span>
{$house_bill_meta}
</div>
EOH
    ;
    return $replacement_line;

  }/*}}}*/
        
  function non_session_linked_document_stow(array & $document) {/*{{{*/
    // Flush ID causes overwrite
    if ( !array_key_exists('url', $document) ) {
      $this->syslog( __FUNCTION__, __LINE__, "(marker) Missing URL");
      $this->recursive_dump($document,"(marker) - - - - - - ");
      return NULL;
    }
    $this->fetch(array(
      'sn'           => $document['text'],
      'congress_tag' => $document['congress_tag'],
    ),'AND');
    if ( !is_null(array_element($document,'text')) && is_null(array_element($document,'sn')) ) {
      $document['sn'] = $document['text'];
    }
    if ( !is_null(array_element($document,'desc')) && is_null(array_element($document,'description')) ) {
      $document['description'] = $document['desc'];
    }
    $document_id = $this->
      set_contents_from_array($document)->
      stow();
    $this->syslog( __FUNCTION__, __LINE__, "(marker) Stowed {$document['text']} (#{$document_id})");
    $this->recursive_dump($document,"(marker) --- -- ---");
    return $document_id;
  }/*}}}*/

}

