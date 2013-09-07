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
  var $doc_url_vc256 = NULL;
  var $status_vc1024 = NULL;
  var $subjects_vc1024 = NULL;
  var $comm_report_url_vc256 = NULL;
  var $comm_report_info_vc256 = NULL;
  var $invalidated_bool = NULL;
  var $filing_date_dtm = NULL;
  var $legislative_history_blob = NULL;
  var $significance_vc16 = NULL;


  var $main_referral_comm_vc64 = NULL;
  var $secondary_committee_vc128 = NULL;

  var $journal_SenateJournalDocumentModel = NULL; // Referring journals
  var $committee_SenateCommitteeModel = NULL;
  var $senator_SenatorDossierModel = NULL;
  var $ocrcontent_ContentDocumentModel = NULL; // Used here to contain OCR versions  

  var $housebill_HouseBillDocumentModel = NULL;
  var $republic_act_RepublicActDocumentModel = NULL;
  var $bill_info_SenateBillDocumentModel = NULL;

  function __construct() {
    parent::__construct();
  }

  function & set_ocrcontent($v) { $this->ocrcontent_ContentDocumentModel = $v; return $this; }
  function get_ocrcontent($v = NULL) { if (!is_null($v)) $this->set_content($v); return $this->ocrcontent_ContentDocumentModel; }

  function & set_searchable($v) { $this->searchable_bool = $v; return $this; }
  function get_searchable($v = NULL) { if (!is_null($v)) $this->set_searchable($v); return $this->searchable_bool; }

  function & set_presidential_action($v) { $this->presidential_action_vc256 = $v; return $this; }
  function get_presidential_action($v = NULL) { if (!is_null($v)) $this->set_presidential_action($v); return $this->presidential_action_vc256; }

  function & set_invalidated($v) { $this->invalidated_bool = $v; return $this; }
  function get_invalidated($v = NULL) { if (!is_null($v)) $this->set_invalidated($v); return $this->invalidated_bool; }

  function & set_text($v) { return $this; }
  function get_text($v = NULL) { if (!is_null($v)) $this->set_text($v); return NULL; }

  function & set_desc($v) { $this->description_vc4096 = $v; return $this; }
  function get_desc($v = NULL) { if (!is_null($v)) $this->set_desc($v); return $this->description_vc4096; }

  function & set_url($v) { $this->url_vc4096 = $v; return $this; }
  function get_url($v = NULL) { if (!is_null($v)) $this->set_url($v); return $this->url_vc4096; }
 
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

  function & set_congress_tag($v) { $this->congress_tag_vc8 = $v; return $this; }
  function get_congress_tag($v = NULL) { if (!is_null($v)) $this->set_congress_tag($v); return $this->congress_tag_vc8; }

  function & set_secondary_committee($v) { $this->secondary_committee_vc128 = $v; return $this; }
  function get_secondary_committee($v = NULL) { if (!is_null($v)) $this->set_secondary_committee($v); return $this->secondary_committee_vc128; }

  function & set_legislative_history($v) { $this->legislative_history_blob = $v; return $this; }
  function get_legislative_history($v = NULL) { if (!is_null($v)) $this->set_legislative_history($v); return $this->legislative_history_blob; }

  function & set_last_fetch($v) { $this->last_fetch_utx = $v; return $this; }
  function get_last_fetch($v = NULL) { if (!is_null($v)) $this->set_last_fetch($v); return $this->last_fetch_utx; }

  function & set_create_time($v) { $this->create_time_utx = $v; return $this; }
  function get_create_time($v = NULL) { if (!is_null($v)) $this->set_create_time($v); return $this->create_time_utx; }

  function single_record_markup_template_a() {/*{{{*/

    $this->prepare_ocrcontent();
    $this->permit_html_tag_delimiters = TRUE;

    $subjects = $this->get_subjects();
    if ( !(FALSE == ($subjects = @json_decode($subjects,TRUE))) ) {
      $this->recursive_dump($subjects,"(critical) + + + =");
      $subjects = join('[BR]', $subjects);
      $this->set_subjects($subjects);
    }

    $senatedoc = get_class($this);
    $total_bills_in_system = $this->count();
    return <<<EOH
{$senatedoc}s: {$total_bills_in_system}
<span class="sb-match-item">{sn}.{congress_tag}</span>
<h2>{title}</h2><span class="sb-match-item sb-match-subjects"></span>
<h3 class="sb-match-item">{subjects}</h3>
<span class="sb-match-item sb-match-significance">Scope: {significance}</span>
<span class="sb-match-item sb-match-status">Status: {status}</span>
<span class="sb-match-item sb-match-doc-url">Document: <a class="{doc_url_attrs}" href="{doc_url}" id="{doc_url_hash}">{sn}</a></span>
<span class="sb-match-item sb-match-main-referral-comm">Committee: <a class="legiscope-remote" href="{main_referral_comm_url}">{main_referral_comm}</a></span>
<span class="sb-match-item sb-match-main-referral-comm">Secondary Committee: {secondary_committees}</span>
<span class="sb-match-item sb-match-committee-report-info">Committee Report: <a class="legiscope-remote" href="{comm_report_url}">{comm_report_info}</a></span>
<span class="sb-match-item sb-match-description">{description}</span>
<hr/>
{reading_state}
{committee_referrals}
{history_tabulation}
<hr/>
<h2>OCR Content</h2>
{ocrcontent.data}
EOH;
  }/*}}}*/

  function stow_parsed_content($document_contents) {/*{{{*/
    // Accept an associative array that can be passed to 
    // DatabaseUtility::set_contents_from_array()
    // Returns the database record ID of this *Document

    $debug_method = FALSE; 

    // Generate joins between this record and
    // - Committees (sponsorship, primary responsibility)
    // - Senators (proponent)
    //
    // Note that a Join to Journals is created only when the Journal 
    // referencing this Senate Bill is accessed.

    $id           = $this->set_contents_from_array($document_contents)->fields(array_keys($document_contents))->stow();
    $bill         = $this->get_sn();
    $congress_tag = $this->get_congress_tag();

    if ( $debug_method ) $this->syslog(__FUNCTION__,__LINE__,"(marker) - - {$bill}.{$congress_tag} #{$id}");

    $this->debug_final_sql = FALSE;
    $this->debug_method    = FALSE;

    $document_contents = $this->join_all()->where(array('AND' => array(
      '`a`.`id`' => $id,
      // Do not assert additional constraints; we want all Joined properties
      //'{journal}.`congress_tag`' => $congress_tag,
      //'{journal_senate_journal_document_model}.`congress_tag`' => $congress_tag
    )))->fetch_single_joinrecord($document_contents);

    $this->debug_final_sql = FALSE;
    $this->debug_method    = FALSE;

    if ( $debug_method ) $this->recursive_dump($document_contents,"(marker) - - {$bill}.{$congress_tag} #{$id}");

    $this->move_committee_refs_to_joins($document_contents, $id);

    return $id;
  }/*}}}*/

}
