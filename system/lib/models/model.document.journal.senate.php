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

