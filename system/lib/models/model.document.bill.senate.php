<?php

/*
 * Class SenateBillDocumentModel
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class SenateBillDocumentModel extends SenateDocCommonDocumentModel {
  
  var $title_vc256uniq = NULL;
  var $sn_vc16 = NULL;
  var $origin_vc2048 = NULL;
  var $description_vc4096 = NULL;
  var $create_time_utx = NULL;
  var $last_fetch_utx = NULL;
  var $searchable_bool = NULL;
  var $congress_tag_vc8 = NULL;
  var $url_vc4096 = NULL;
  var $urlid_int11 = NULL;
  var $doc_url_vc256 = NULL;
	var $status_vc1024 = NULL;
	var $subjects_vc1024 = NULL;
  var $comm_report_url_vc256 = NULL;
  var $comm_report_info_vc256 = NULL;
	var $filing_date_dtm = NULL;
	var $legislative_history_vc8192 = NULL;

	var $date_read_utx = NULL;
	var $house_approval_date_utx = NULL;
	var $transmittal_date_utx = NULL;
	var $principal_author_int11 = NULL;
  var $sponsor_int11 = NULL; // SenatorsModel
	var $main_referral_comm_vc64 = NULL;
	var $secondary_committee_vc64 = NULL;
	var $pending_comm_vc64 = NULL;
	var $pending_comm_date_utx = NULL;
	var $significance_vc16 = NULL;

  var $journal_SenateJournalDocumentModel = NULL;
  var $committee_SenateCommitteeModel = NULL;
	var $senator_SenatorDossierModel = NULL;

  function __construct() {
    parent::__construct();
  }

  function & set_text($v) { return $this; }
  function get_text($v = NULL) { if (!is_null($v)) $this->set_text($v); return NULL; }

  function & set_desc($v) { $this->description_vc4096 = $v; return $this; }
  function get_desc($v = NULL) { if (!is_null($v)) $this->set_desc($v); return $this->description_vc4096; }

  function & set_date_read($v) { $this->date_read_utx = $v; return $this; }
  function get_date_read($v = NULL) { if (!is_null($v)) $this->set_date_read($v); return $this->date_read_utx; }

  function & set_house_approval_date($v) { $this->house_approval_date_utx = $v; return $this; }
  function get_house_approval_date($v = NULL) { if (!is_null($v)) $this->set_house_approval_date($v); return $this->house_approval_date_utx; }

  function & set_transmittal_date($v) { $this->transmittal_date_utx = $v; return $this; }
  function get_transmittal_date($v = NULL) { if (!is_null($v)) $this->set_transmittal_date($v); return $this->transmittal_date_utx; }

  function & set_principal_author($v) { $this->principal_author_int11 = $v; return $this; }
  function get_principal_author($v = NULL) { if (!is_null($v)) $this->set_principal_author($v); return $this->principal_author_int11; }

  function & set_sponsor($v) { $this->sponsor_int11 = $v; return $this; }
  function get_sponsor($v = NULL) { if (!is_null($v)) $this->set_sponsor($v); return $this->sponsor_int11; }

  function & set_pending_comm($v) { $this->pending_comm_vc64 = $v; return $this; }
  function get_pending_comm($v = NULL) { if (!is_null($v)) $this->set_pending_comm($v); return $this->pending_comm_vc64; }

  function & set_pending_comm_date($v) { $this->pending_comm_date_utx = $v; return $this; }
  function get_pending_comm_date($v = NULL) { if (!is_null($v)) $this->set_pending_comm_date($v); return $this->pending_comm_date_utx; }

  function & set_url($v) { $this->url_vc4096 = $v; return $this; }
  function get_url($v = NULL) { if (!is_null($v)) $this->set_url($v); return $this->url_vc4096; }
 
  function & set_urlid($v) { $this->urlid_int11 = $v; return $this; }
  function get_urlid($v = NULL) { if (!is_null($v)) $this->set_urlid($v); return $this->urlid_int11; }
  
  function & set_doc_url($v) { $this->doc_url_vc256 = $v; return $this; }
  function get_doc_url($v = NULL) { if (!is_null($v)) $this->set_doc_url($v); return $this->doc_url_vc256; }
  
  function & set_status($v) { $this->status_vc1024 = $v; return $this; }
  function get_status($v = NULL) { if (!is_null($v)) $this->set_status($v); return $this->status_vc1024; }

  function & set_subjects($v) { $this->subjects_vc1024 = $v; return $this; }
  function get_subjects($v = NULL) { if (!is_null($v)) $this->set_subjects($v); return $this->subjects_vc1024; }

  function & set_comm_report_url($v) { $this->comm_report_url_vc256 = $v; return $this; }
  function get_comm_report_url($v = NULL) { if (!is_null($v)) $this->set_comm_report_url($v); return $this->comm_report_url_vc256; }

  function & set_comm_report_info($v) { $this->comm_report_info_vc256 = $v; return $this; }
  function get_comm_report_info($v = NULL) { if (!is_null($v)) $this->set_comm_report_info($v); return $this->comm_report_info_vc256; }

  function & set_main_referral_comm($v) { $this->main_referral_comm_vc64 = $v; return $this; }
  function get_main_referral_comm($v = NULL) { if (!is_null($v)) $this->set_main_referral_comm($v); return $this->main_referral_comm_vc64; }

  function & set_significance($v) { $this->significance_vc16 = $v; return $this; }
  function get_significance($v = NULL) { if (!is_null($v)) $this->set_significance($v); return $this->significance_vc16; }

  function & set_title($v) { $this->title_vc256uniq = $v; return $this; }
  function get_title($v = NULL) { if (!is_null($v)) $this->set_title($v); return $this->title_vc256uniq; }

  function & set_sn($v) { $this->sn_vc16 = $v; return $this; }
  function get_sn($v = NULL) { if (!is_null($v)) $this->set_sn($v); return $this->sn_vc16; }

  function & set_origin($v) { $this->origin_vc2048 = $v; return $this; }
  function get_origin($v = NULL) { if (!is_null($v)) $this->set_origin($v); return $this->origin_vc2048; }

  function & set_description($v) { $this->description_vc4096 = $v; return $this; }
  function get_description($v = NULL) { if (!is_null($v)) $this->set_description($v); return $this->description_vc4096; }

  function & set_searchable($v) { $this->searchable_bool = $v; return $this; }
  function get_searchable($v = NULL) { if (!is_null($v)) $this->set_searchable($v); return $this->searchable_bool; }

  function & set_congress_tag($v) { $this->congress_tag_vc8 = $v; return $this; }
  function get_congress_tag($v = NULL) { if (!is_null($v)) $this->set_congress_tag($v); return $this->congress_tag_vc8; }

	function & set_secondary_committee($v) { $this->secondary_committee_vc64 = $v; return $this; }
	function get_secondary_committee($v = NULL) { if (!is_null($v)) $this->set_secondary_committee($v); return $this->secondary_committee_vc64; }

	function & set_legislative_history($v) { $this->legislative_history_vc8192 = $v; return $this; }
	function get_legislative_history($v = NULL) { if (!is_null($v)) $this->set_legislative_history($v); return $this->legislative_history_vc8192; }

	function & set_filing_date($v) { $this->filing_date_dtm = $v; return $this; }
	function get_filing_date($v = NULL) { if (!is_null($v)) $this->set_filing_date($v); return $this->filing_date_dtm; }

	function generate_non_session_linked_markup() {/*{{{*/

		$faux_url = new UrlModel();

		$senatedoc = get_class($this);

		$this->syslog( __FUNCTION__, __LINE__, "(marker) --- --- --- - - - --- --- --- Got #" . $this->get_id() . " " . get_class($this) );

		$total_bills_in_system = $this->count();

		$doc_url_attrs = array('legiscope-remote');
		$faux_url_hash = UrlModel::get_url_hash($this->get_url()); 
		if( $faux_url->retrieve($faux_url_hash,'urlhash')->in_database() ) {
			$doc_url_attrs[] = 'cached';
		}
		$doc_url_attrs = join(' ', $doc_url_attrs);

		$pagecontent = $this->substitute(<<<EOH
Senate {$senatedoc}s in system: {$total_bills_in_system}
<span class="sb-match-item">{sn}.{congress_tag}</span>
<span class="sb-match-item sb-match-subjects">{subjects}</span>
<span class="sb-match-item sb-match-description">{description}</span>
<span class="sb-match-item sb-match-significance">Scope: {significance}</span>
<span class="sb-match-item sb-match-status">Status: {status}</span>
<span class="sb-match-item sb-match-doc-url">Document: <a class="{$doc_url_attrs}" href="{doc_url}">{sn}</a></span>
<span class="sb-match-item sb-match-main-referral-comm">Committee: {main_referral_comm}</span>
<span class="sb-match-item sb-match-main-referral-comm">Secondary Committee: {secondary_committee}</span>
<span class="sb-match-item sb-match-committee-report-info">Committee Report: <a class="legiscope-remote" href="{comm_report_url}">{comm_report_info}</a></span>
EOH
		);

		$congress_tag = $this->get_congress_tag();

		$this->debug_final_sql = FALSE;
		$this->
			join_all()->
			where(array(
				'`a`.`id`' => $this->get_id(),
				'`b`.`congress_tag`' => $congress_tag
			))->
			recordfetch_setup();
		$sb = array();
		$this->debug_final_sql = FALSE;

		$reading_state = array();

		$reading_replace = array(
			'@R1@' => 'First Reading',
			'@R2@' => 'Second Reading',
			'@R3@' => 'Third Reading',
		);

		while ( $this->recordfetch($sb) ) {
			$this->syslog(__FUNCTION__,__LINE__,"(marker) - - Got entry {$sb['id']}");
			$this->recursive_dump($sb['journal'],"(marker) - - - {$sb['id']} -");
			$journal      = $sb['journal'];
			$reading      = nonempty_array_element($journal['join'],'reading');
			$reading_date  = nonempty_array_element(explode(' ',nonempty_array_element($journal['join'],'reading_date',' -')),0);
			$journal_title = array_element($journal['data'],'title');
			$journal_url   = nonempty_array_element($journal['data'],'url');
			if ( is_null($reading) || is_null($journal_url) ) continue;
			$reading_lbl   = preg_replace(
				array_keys($reading_replace),
				array_values($reading_replace),
				$reading
			);
			$reading_state["{$reading}{$reading_date}"] = <<<EOH
<li><a href="{$journal_url}" class="legiscope-remote suppress-reorder">{$reading_lbl} ({$reading_date})</a> {$journal_title}</li>
EOH;
		}

		if ( 0 < count($reading_state) ) {
			krsort($reading_state);
			$reading_state = join(" ", $reading_state);
			$reading_state = <<<EOH
<ul>{$reading_state}</ul>

EOH;
			$pagecontent .= $reading_state;
		}

		$pagecontent  = str_replace('[BR]','<br/>', $pagecontent);

		return $pagecontent;

	}/*}}}*/

}
