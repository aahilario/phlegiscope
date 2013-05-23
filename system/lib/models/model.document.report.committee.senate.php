<?php

/*
 * Class SenateCommitteeReportDocumentModel
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class SenateCommitteeReportDocumentModel extends SenateDocCommonDocumentModel {
  
  var $title_vc256 = NULL;
  var $description_vc4096 = NULL;
  var $sn_vc64 = NULL;
  var $create_time_utx = NULL;
  var $last_fetch_utx = NULL;
  var $congress_tag_vc8 = NULL;
  var $url_vc4096 = NULL;
	var $urlid_int11 = NULL;
	var $doc_url_vc4096 = NULL;
	var $doc_urlid_int11 = NULL;
  var $content_blob = NULL;
	var $date_filed_utx = 0;
  var $journal_SenateJournalDocumentModel = NULL;
	var $committee_SenateCommitteeModel = NULL;
	var $bill_SenateBillDocumentModel = NULL;
  var $resolution_SenateResolutionDocumentModel = NULL;
	var $housebill_SenateHousebillDocumentModel = NULL;

  function __construct() {
    parent::__construct();
    // $this->dump_accessor_defs_to_syslog();
		// $this->recursive_dump($this->get_attrdefs(),'(marker) "Gippy"');
  }

	function & set_id($v) { if ( 0 < intval($v) ) $this->id = $v; return $this; }

  function & set_text($v) { return $this; }
  function get_text($v = NULL) { if (!is_null($v)) $this->set_text($v); return NULL; }

  function & set_title($v) { $this->title_vc256 = $v; return $this; }
  function get_title($v = NULL) { if (!is_null($v)) $this->set_title($v); return $this->title_vc256; }

  function & set_sn($v) { $this->sn_vc64 = $v; return $this; }
  function get_sn($v = NULL) { if (!is_null($v)) $this->set_sn($v); return $this->sn_vc64; }

  function & set_create_time($v) { $this->create_time_utx = $v; return $this; }
  function get_create_time($v = NULL) { if (!is_null($v)) $this->set_create_time($v); return $this->create_time_utx; }

  function & set_last_fetch($v) { $this->last_fetch_utx = $v; return $this; }
  function get_last_fetch($v = NULL) { if (!is_null($v)) $this->set_last_fetch($v); return $this->last_fetch_utx; }

  function & set_congress_tag($v) { $this->congress_tag_vc8 = $v; return $this; }
  function get_congress_tag($v = NULL) { if (!is_null($v)) $this->set_congress_tag($v); return $this->congress_tag_vc8; }

  function & set_url($v) { $this->url_vc4096 = $v; return $this; }
  function get_url($v = NULL) { if (!is_null($v)) $this->set_url($v); return $this->url_vc4096; }

  function & set_content($v) { $this->content_blob = $v; return $this; }
  function get_content($v = NULL) { if (!is_null($v)) $this->set_content($v); return $this->content_blob; }

  function & set_desc($v) { $this->description_vc4096 = $v; return $this; }
  function get_desc($v = NULL) { if (!is_null($v)) $this->set_desc($v); return $this->description_vc4096; }

	function & set_urlid($v) { $this->urlid_int11 = $v; return $this; }
	function get_urlid($v = NULL) { if (!is_null($v)) $this->set_urlid($v); return $this->urlid_int11; }

	function & set_doc_url($v) { $this->doc_url_vc4096 = $v; return $this; }
	function get_doc_url($v = NULL) { if (!is_null($v)) $this->set_doc_url($v); return $this->doc_url_vc4096; }

	function & set_doc_urlid($v) { $this->doc_urlid_int11 = $v; return $this; }
	function get_doc_urlid($v = NULL) { if (!is_null($v)) $this->set_doc_urlid($v); return $this->doc_urlid_int11; }

	function & set_date_filed($v) { $this->date_filed_utx = $v; return $this; }
	function get_date_filed($v = NULL) { if (!is_null($v)) $this->set_date_filed($v); return $this->date_filed_utx; }

  function store_uncached_reports(array & $committee_report_url_text, $journal_id = NULL, $debug_method = FALSE) {/*{{{*/
    
    // Accept an array( url => url, text => desc ) and store 
    if ($debug_method) $this->recursive_dump($committee_report_url_text,'(marker) Parsed reports F');
    while ( 0 < count($committee_report_url_text) ) {
      $n = 0;
      // Take a batch of n records
      $queue = array();
      $joinqueue = is_null($journal_id) ? NULL : $queue;
      while ( $n++ < 10 && 0 < count($committee_report_url_text) ) {
        // Pop the records from the stack 
        $url = array_pop($committee_report_url_text);
        $urlhash = UrlModel::get_url_hash($url['url']);
        $queue[$urlhash] = array_merge($url,array(
          'sn'           => preg_replace('@^http://(.*)&q=([0-9]*)@i', '$2', $url['url']),
          'congress_tag' => preg_replace('@^http://(.*)\?congress=([0-9]*)\&(.*)@i', '$2', $url['url']),
        ));
        if (!is_null($journal_id)) $joinqueue[$urlhash] = NULL;
      }
      // if ($debug_method) $this->recursive_dump($queue,'(marker) Check these');
      // Use a cursor to scan through this set, removing those from the queue
      // that are already in our database
      $urls = array_combine(
        array_map(create_function('$a', 'return preg_replace("@^http://(.*)&q=([0-9]*)@i", "$2", $a["url"]);'), $queue),
        array_map(create_function('$a', 'return $a["url"];'), $queue)
      );
      ksort($urls);
      // NULL out extant records

      $this->where(array('url' => array_values($urls)))->recordfetch_setup();
      while ( $this->recordfetch($cr) ) {/*{{{*/// Remove extant SenateCommitteeReportDocumentModel
        $urlhash = UrlModel::get_url_hash($cr['url']);
        if ($debug_method) $this->syslog( __FUNCTION__, __LINE__, "(marker) << Skipping CR #{$cr['sn']} ID {$cr['id']} C. {$queue[$urlhash]['congress_tag']} {$queue[$urlhash]['text']} ");
        $queue[$urlhash] = NULL;
        // Create Joins between existing committee report (id below) and journal record (journal_id provided by caller)
        if (!is_null($journal_id)) $joinqueue[$urlhash] = $cr['id'];
      }/*}}}*/

      if (!is_null($journal_id)) {
        $joinqueue_filtered = array_flip(array_filter($joinqueue)); // Result: array( id => urlhash, ... )
        // NULL out extant Joins
        $reportjournal = new SenateCommitteeReportSenateJournalJoin();
        $reportjournal->where(array('AND' => array(
          'senate_journal'          => $journal_id,
          'senate_committee_report' => array_values($joinqueue),
        )))->recordfetch_setup();
        $joinrecord = NULL;
        while ( $reportjournal->recordfetch($joinrecord) ) {
          // Every record encountered here is removed from the candidate list 
          $joinqueue_filtered[$joinrecord['senate_committee_report']] = NULL;
        }
        // What remains is an array of committee report ID:urlhash pairs
        $joinqueue_filtered = array_flip(array_filter($joinqueue_filtered));
        //if ($debug_method) 
        $this->recursive_dump($joinqueue_filtered,'(marker) Store Joins for these CRs');
        foreach ( $joinqueue_filtered as $senate_committee_report_id ) {
          $reportjournal->id = NULL;
          $join_id = $reportjournal->
            set_senate_committee_report($senate_committee_report_id)->
            set_senate_journal($journal_id)->
            stow(); 
          $this->syslog( __FUNCTION__, __LINE__, ( 0 < intval($join_id) )
            ? ("(marker) Created " . get_class($reportjournal) . " #{$join_id}")
            : ("(marker) Failed to create Join between CR DB#{$senate_committee_report_id} <-> Journal DB#{$journal_id}")
          );
        }
      }

      $queue = array_filter($queue);
      if ($debug_method) $this->recursive_dump($queue,'(marker) Store these');
      if ( is_array($queue) && (0 < count($queue)) )
      foreach ( $queue as $item ) {
        // Stow each of these nonexistent records
        if ( !is_array($item) || !array_key_exists('url', $item)) continue;
        $this->fetch($item['url'], 'url');
        $this->
          set_url($item['url'])->
          set_sn($item['sn'])->
          set_congress_tag($item['congress_tag'])->
          set_title($item['text'])->
          set_create_time(time())->
          stow();
      }
    }
  }/*}}}*/

}
