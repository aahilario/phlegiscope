<?php

/*
 * Class SenateJournalDocumentModel
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class SenateJournalDocumentModel extends SenateDocCommonDocumentModel {
  
  var $title_vc256uniq = NULL;
  var $create_time_utx = NULL;
  var $sn_vc64 = NULL;
  var $last_fetch_utx = NULL;
  var $congress_tag_vc8 = NULL;
  var $session_tag_vc8 = NULL;
  var $url_vc4096 = NULL;
	var $recorded_date_vc32 = NULL;
	var $approved_date_dtm = NULL;
	// var $content_blob = NULL;
	var $pdf_fetch_time_utx = NULL;
	var $pdf_url_vc4096 = NULL;
	var $report_SenateCommitteeReportDocumentModel = NULL;
  var $bill_SenateBillDocumentModel = NULL;
  var $resolution_SenateResolutionDocumentModel = NULL;

	var $urlrefs_UrlModel = NULL; // Document source URLs

  function __construct() {
    parent::__construct();
    // $this->dump_accessor_defs_to_syslog();
  }

  function generate_child_collection(SenateJournalParseUtility & $document_parser) {/*{{{*/

    // Extract parsed urls, for inclusion in the child collection.
    $parsed_url_set = array_values($document_parser->filter_nested_array(
      $document_parser->child_collection_source,
      'children[tagname=div][id=lis_journal_table]'
    ));

    $parsed_url_set = nonempty_array_element($parsed_url_set,0);

    $parsed_urls = array();
    while ( 0 < count($parsed_url_set) ) {
      $children = array_shift($parsed_url_set);
      $children = nonempty_array_element($children,'children');
      while ( 0 < count($children) ) {
        array_push($parsed_urls, array_shift($children));
      }
    }

    $this->recursive_dump($parsed_urls,"(critical) -- CCI");

    // The Senate Journal parser returns only the set of tags and their children
    // containing div#lis_journal_table, which contain Journal links 
    $this->where(array('AND' => array('sn' => "REGEXP '(.*)'")))->recordfetch_setup();
    $record = array();
    $child_collection = array();
    // Two passes.  Generate list of hashes from what's in the database.
    // Then generate the final child collection array with URL state.
    while ( $this->recordfetch($record,TRUE) ) {
      extract($record); // It is safe to do this here, we control the database structure (as long as nobody has hacked in and created an attribute _SESSION in our backing store)
      $child_collection[$record['hash']] = array_merge(
        $record,
        array(
          'congress_tag' => preg_replace('@[^0-9]@i','',$congress_tag),
          'hash' => UrlModel::get_url_hash($url),
          'text' => "No. {$sn}",
          'cached' => FALSE,
        )
      );
    }

    // Include parsed records
    foreach ( $parsed_urls as $journal_entry ) {
      $url  = preg_replace('@[^a-z0-9/:.&?=]@i','',$journal_entry['url']);
      $text = $journal_entry['text'];
      $hash = UrlModel::get_url_hash($url);
      if ( !array_key_exists($hash,$child_collection) ) {
        $this->syslog(__FUNCTION__,__LINE__,"(marker) {$url}");
        $sn = UrlModel::query_element('q',$url);
        $child_collection[$hash] = array(
          'url'          => $url,
          'sn'           => "{$sn}",
          'session_tag'  => UrlModel::query_element('session',$url),
          'congress_tag' => UrlModel::query_element('congress',$url),
          'hash'         => $hash,
          'text'         => "No. {$sn}",
          'cached'       => FALSE,
          'title'        => preg_replace('@@i','',"Journal {$text}"),
          'create_time'  => time(),
        );
        $this->recursive_dump($child_collection[$hash],"(marker)");
      }
    }
    
    $window_length = 20; // Sliding window
    $n = count($child_collection);
    $test_url = new UrlModel();
    while ( $n > 0 ) {
      // FIXME: Convert your earlier uses of array_shift() to this method.
      $chunk = array_slice($child_collection,$offset,$window_length);
      if ( count($chunk) == $window_length ) $n -= $window_length;
      else {
        $n = 0;
      }
      $condition = array('urlhash' => array_keys($chunk));
      // $this->recursive_dump($condition,"(marker) {$n}");
      $test_url->
        where($condition)->
        recordfetch_setup();
      $record = array();
      while ( $test_url->recordfetch($record) ) {
        $urlhash = UrlModel::get_url_hash($record['url']);
        $child_collection[$urlhash]['cached'] = TRUE;
        $this->syslog(__FUNCTION__,__LINE__,"(marker) CACHED {$urlhash} {$record['url']}");
      }
    }
    $final_child_collection = array();
    while ( 0 < count($child_collection) ) {
      $record = array_shift($child_collection);
      extract($record);
      if ( empty($congress_tag) ) continue;
      if ( empty($session_tag) ) continue;
      if ( empty($sn) ) continue;
      $final_child_collection[trim($congress_tag)][trim($session_tag)][intval($sn)] = array(
        'url' => $record['url'],
        'hash' => $record['hash'],
        'text' => $record['text'],
        'cached' => $record['cached'],
      );
    }
    krsort($final_child_collection);
    $this->recursive_dump($final_child_collection,"(marker) J");
    return $final_child_collection; 
    // Return a nested array with this structure:
    // Array (
    //   $congress_tag => Array(
    //     $session_tag => Array(
    //       $q => Array(
    //         'url' => url,
    //         'hash' => UrlModel::get_url_hash(url),
    //         'text' => urltext
    //       )
    //     )
    //   )
    // )
  }/*}}}*/
  
	function fetch_by_congress_sn( $congress, $house_session, $sn ) {/*{{{*/

		$match = array(
			'congress_tag' => $congress,
			'session_tag' => $house_session,
			'sn' => $sn,
		);
		$this->debug_final_sql = FALSE;
		$this->fetch($match, 'AND');
		$this->debug_final_sql = FALSE;
		return $this->in_database() ? $this->id : NULL;

	}/*}}}*/

	function store(array $journal_data, UrlModel & $source_url, $pagecontent, $only_if_missing = TRUE) {/*{{{*/

    $debug_method = FALSE;

		$journal_parser = new SenateJournalParseUtility();
		$metadata       = $journal_data;
		$journal_head   = $journal_parser->filter_nested_array($journal_data,'#[tag*=HEAD]',0);
		$metadata       = $journal_parser->filter_nested_array($metadata,'#metadata[tag*=META]',0);

    if ( $debug_method ) $this->syslog(__FUNCTION__, __LINE__, "(marker) Stowing Journal No. {$journal_head['sn']}");

		$id = $this->fetch_by_congress_sn( $metadata['congress'], $metadata['short_session'], $journal_head['sn'] );

    if ( $debug_method ) $this->syslog(__FUNCTION__, __LINE__, "(marker) Record for Journal No. {$journal_head['sn']} " . (is_null($id) ? "missing" : "cached #{$id}"));

		$data = array(
			'title'         => "{$journal_head['section']}, {$metadata['congress']} Congress, {$metadata['session']}",
			'url'           => $source_url->get_url(),
			'sn'            => $journal_head['sn'],
			'session_tag'   => $metadata['short_session'],
			'congress_tag'  => $metadata['congress'],
			'last_fetch'    => time(),
			'content'       => $pagecontent,
			'pdf_url'       => $journal_head['pdf']['url'],
			'approved_date' => $metadata['approved_dtm'],
			'recorded_date' => $metadata['date'],
		);

		if ( is_null($id) || (filter_post('update') == 'true') ) {
			$id = $this->set_contents_from_array($data,TRUE)->stow();
			//if ( $debug_method ) 
				$this->syslog(__FUNCTION__, __LINE__, "(marker) Record for Journal No. {$journal_head['sn']} " . (is_null($id) ? "missing" : "cached #{$id}"));
		}
    return $id;
	}/*}}}*/

	function & set_approved_date($v) { $this->approved_date_dtm = $v; return $this; }
	function get_approved_date($v = NULL) { if (!is_null($v)) $this->set_approved_date($v); return $this->approved_date_dtm; }

	function & set_recorded_date($v) { $this->recorded_date_vc32 = $v; return $this; }
	function get_recorded_date($v = NULL) { if (!is_null($v)) $this->set_recorded_date($v); return $this->recorded_date_vc32; }

	function & set_title($v) { $this->title_vc256uniq = $v; return $this; }
	function get_title($v = NULL) { if (!is_null($v)) $this->set_title($v); return $this->title_vc256uniq; }

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

	function & set_pdf_fetch_time($v) { $this->pdf_fetch_time_utx = $v; return $this; }
	function get_pdf_fetch_time($v = NULL) { if (!is_null($v)) $this->set_pdf_fetch_time($v); return $this->pdf_fetch_time_utx; }

	function & set_pdf_url($v) { $this->pdf_url_vc4096 = $v; return $this; }
	function get_pdf_url($v = NULL) { if (!is_null($v)) $this->set_pdf_url($v); return $this->pdf_url_vc4096; }

	function & set_session_tag($v) { $this->session_tag_vc8 = $v; return $this; }
	function get_session_tag($v = NULL) { if (!is_null($v)) $this->set_session_tag($v); return $this->session_tag_vc8; }
}

