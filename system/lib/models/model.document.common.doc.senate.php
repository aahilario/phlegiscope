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
        
  function non_session_linked_document_stow(array & $document, $allow_update = FALSE) {/*{{{*/
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
    $action = $this->in_database() ? ($allow_update ? "Updated" : "Skip updating") : "Stowed";
    if ( !$this->in_database() || $allow_update ) {
      $document_id = $this->
        set_contents_from_array($document)->
        stow();
      $this->syslog( __FUNCTION__, __LINE__, "(marker) Stowed {$document['text']} (#{$document_id})");
      $this->recursive_dump($document,"(marker) --- -- ---");
    }
    return $document_id;
  }/*}}}*/

  function senate_document_senator_dossier_join(& $senator, $allow_update = FALSE, $full_match = TRUE ) {/*{{{*/

    $debug_method = FALSE;

    if ( !$this->in_database() ) return FALSE;

    if ( !is_null($senator['id']) ) {/*{{{*/

      $join_info = array( $senator['id'] => array(
        'relationship' => array_element($senator,'relationship'),
        'relationship_date' => array_element($senator,'filing_date'),
        'create_time' => time()
      ));  

      $join_type = get_class($this);
      if ( $debug_method ) {
        $this->syslog(__FUNCTION__,__LINE__,"(warning) - - Join type '{$join_type}'");
        $this->recursive_dump($join_info, '(marker) -- - --');
      }
      $this->create_joins('SenatorDossierModel', $join_info, $allow_update, $full_match);
      return TRUE;
    }/*}}}*/

    return FALSE;

  }/*}}}*/

  function move_committee_refs_to_joins($document_contents, $id, $debug_method = FALSE ) {/*{{{*/
    
    //////////////////////////////////////////////////////////////////////////////////////
    // Hack to transform committee name fields to Join references 
    // Load matched committee IDs
    // Invoked from
    // - SenateBillDocumentModel::stow_parsed_content()
    // - SenateHousebillDocumentModel::stow_parsed_content()
    $committee_match = new SenateCommitteeModel();

    $main_referral_comm  = array_element($document_contents,'main_referral_comm',$this->get_main_referral_comm());
    // There are zero or more committees enumerated as secondary committees 
    $referring_committees = array_filter(explode('[BR]', $this->get_secondary_committee()));
    $referring_committees[] = $main_referral_comm;
    $referring_committees = array_filter($referring_committees);

    if ( 0 < count($referring_committees) ) {/*{{{*/

      // Original name => regex
      $referring_committees = array_flip($referring_committees);

      // Obtain the SQL regex for each committee name
      array_walk($referring_committees,create_function(
        '& $a, $k', '$a = LegislationCommonParseUtility::committee_name_regex($k);'
      ));

      // Select these records from the database
      $committee = array();
      $committee_match->where(array('AND' => array(
        'committee_name' => "REGEXP '(".join('|',array_filter($referring_committees)).")'"
      )))->recordfetch_setup();

      // Regex => Original name 
      $referring_committees = array_flip(array_filter($referring_committees));

      while ( $committee_match->recordfetch($committee) ) {
        $committee_id   = $committee['id'];
        $committee_name = $committee['committee_name'];
        $find_regex = LegislationCommonParseUtility::committee_name_regex($committee_name);
        if ( $debug_method ) $this->syslog( __FUNCTION__,__LINE__,"(marker) ----- Match {$committee['id']} {$committee['committee_name']} ({$find_regex})");
        // Find the single committee-regex map that matches the current recorded committee_name
        $match = array_filter(
          array_map(
            create_function('$a', 'return (is_string($a) && (1 == preg_match("@^'.$find_regex.'@i",$a))) ? $a : NULL;'),
            $referring_committees
          )
        );
        if ( 1 == count($match) ) {
          // If a single record exists, replace the name in the list of name regexes with an array.
          if ( $debug_method ) $this->recursive_dump($match,"(marker) -----");
          $match = array_element(array_keys($match),0);
          $referring_committees[$match] = array(
            'name' => $referring_committees[$match],
            'comm' => $committee
          );
        }
      }

      // Remove entries that aren't arrays
      array_walk($referring_committees,create_function('& $a, $k', 'if ( !is_array($a) ) $a = NULL;'));
      $referring_committees = array_filter($referring_committees);

      if ( is_array($referring_committees) && (0 < count($referring_committees)) ) {

        // Turn the list into an array with the original (parsed) committee names as keys, 
        // with database record entries as values.
        $referring_committees = array_filter(array_combine(
          array_map(create_function('$a', 'return trim(array_element($a,"name"),"\n");'), $referring_committees),
          array_map(create_function('$a', 'return array_element($a,"comm");'), $referring_committees)
        ));

        // Mark the sole primary committee as such
        if ( array_key_exists($main_referral_comm, $referring_committees) ) {
          $referring_committees[$main_referral_comm]['referral_mode'] = 'primary';
        }
        // And mark the remaining ones as secondary committees
        array_walk($referring_committees,create_function(
          '& $a, $k', 'if ( !array_key_exists("referral_mode", $a) ) $a["referral_mode"] = "secondary";'
        ));

        // Now determine which Joins ( this senate bill to referring committee )
        // are not yet present in the database.
        $n_joins = count($referring_committees);
        foreach ( $referring_committees as $committee_name => $committee ) {
          $committee_id = $committee['id'];
          if ( array_key_exists($committee_id, $document_contents['committee']) ) {
            if ( $debug_method )
            $this->syslog(__FUNCTION__,__LINE__,"(marker) - - - Skipping extant join to Committee {$committee_name} #{$committee_id}");
            continue;
          }
          if ( $debug_method )
          $this->syslog(__FUNCTION__,__LINE__,"(marker) - - - Creating join to Committee {$committee_name} #{$committee_id}");
          $join = array($committee_id => array(
            'referral_mode' => $committee['referral_mode']
          ));
          $result = $this->create_joins('committee', $join,TRUE);
          if ( $debug_method )
          $this->recursive_dump($result,"(marker) - - {$id}");
        }
      }

      if ( $debug_method ) {
        $this->syslog(__FUNCTION__,__LINE__,"(marker) - - {$id} - -   Primary committee: {$main_referral_comm}");
        $this->syslog(__FUNCTION__,__LINE__,"(marker) - - {$id} - - Secondary committee: {$referring_committees}");
        $this->recursive_dump($referring_committees,"(marker) - - {$id}");
      }
      //////////////////////////////////////////////////////////////////////////////////////
    }/*}}}*/
  }/*}}}*/

  function get_doc_url_attributes(UrlModel & $faux_url) {/*{{{*/
    // Returns an array of <A> tag {class} strings used by crawler JS.
    // The UrlModel parameter is used in parent::generate_non_session_linked_markup()
    // to retrieve the document URL record (including payload, last_fetch time, etc.)
    // Data in that record is used to decorate source URL <A> tag attributes. 
    //
    // We want Senate bills to be marked "uncached" that either
    // 1) Have not yet been retrieved to local storage, or
    // 2) Do not have an OCR result record matching the PDF content.
    //
    $doc_url_attrs    = array('legiscope-remote');
    $have_ocr         = $this->get_searchable();
		$doc_url          = $this->get_doc_url();
    $faux_url_hash    = UrlModel::get_url_hash($doc_url);
    $url_stored       = $faux_url->retrieve($faux_url_hash,'urlhash')->in_database();

    if ( $this->in_database() && is_null($this->get_ocrcontent()) ) {
      $this->syslog(__FUNCTION__,__LINE__,"(warning) No [content] attribute present for {$doc_url}. Default to {uncached} state.");
    }
    $doc_url_attrs[]  = $url_stored && $have_ocr ? 'cached' : 'uncached';

		if ( !$faux_url->in_database() ) { 
			$doc_url_attrs[] = 'uncached';
		}
		else {
			if ( 0 < intval( $age = intval($faux_url->get_last_fetch()) ) ) {
				$age = time() - intval($age);
				$doc_url_attrs[] = ($age > ( 60 )) ? "uncached" : "cached";
			}
			$doc_url_attrs[] = $faux_url->get_logseconds_css();
		}
    return $doc_url_attrs;
  }/*}}}*/

  function prepare_ocrcontent() {/*{{{*/
    if (!($this->in_database())) {
      $this->syslog(__FUNCTION__,__LINE__,"(warning) Uninitialized record.");
    } 
    else if (is_null($this->get_ocrcontent())) {
      $this->set_ocrcontent('No OCR conversion available.');
    } else {
      $ocrcontent = $this->get_ocrcontent();
      $ocrcontent = nonempty_array_element($ocrcontent,'data');
      $ocr_record_id = nonempty_array_element($ocrcontent,'id');
      if ( is_null($ocr_record_id) || !(0 < intval($ocr_record_id)) ) {
        $this->syslog(__FUNCTION__,__LINE__,"(marker) No OCR result available.");
        $this->set_ocrcontent('No OCR available');
        return FALSE;
      }
      if ( !(0 < mb_strlen(mb_ereg_replace('@[^-A-Z0-9;:. ]@i', '', nonempty_array_element($ocrcontent,'data')))) ) {
        $this->syslog(__FUNCTION__,__LINE__,"(warning) Invalid OCR content. Remove ContentDocument #{$ocr_record_id}");
        $ocrcontent = $this->get_ocrcontent();
        $ocrcontent['data']['data'] = 'No OCR available'; 
        $this->set_ocrcontent($ocrcontent);
        return FALSE;
      }
      $this->syslog(__FUNCTION__,__LINE__,"(marker) OCR content record #{$ocr_record_id} available.");
      $ocrcontent = NULL;
      unset($ocrcontent);
      $this->reconstitute_ocr_text();

      if (1) {
        $ocrcontent = $this->get_ocrcontent();
        $content = nonempty_array_element($ocrcontent,'data');
        $content = nonempty_array_element($content,'data');
        $content = $this->format_document($content);
        if ( !is_null($content) ) {
          $ocrcontent['data']['data'] = $content;
          $this->set_ocrcontent($ocrcontent);
        }
        $content = NULL;
        $ocrcontent = NULL;
      }

      return TRUE;
    }
    return FALSE;
  }/*}}}*/

  function generate_non_session_linked_markup() {/*{{{*/

    $debug_method = FALSE || (property_exists($this,'debug_method') && $this->debug_method);

    $faux_url = new UrlModel();

    $senatedoc = get_class($this);

    $doc_id = $this->get_id();
    $doc_sn = $this->get_sn();
    $doc_congress = $this->get_congress_tag();

    $this->syslog( __FUNCTION__, __LINE__, "(marker) --- --- --- - - - --- --- --- Got {$senatedoc} #{$doc_id} ({$doc_sn}.{$doc_congress})" );

    $total_bills_in_system = $this->count();

    if ( method_exists($this,'test_document_ocr_result') ) {
      // Expected return values:
      // TRUE: At least one OCR record associated with the current document.
      // FALSE: No OCRd version available, or document still in OCR spooling queue.
      // NULL: Unknown (possibly because this document hasn't yet been retrieved from DB)
      $this->test_document_ocr_result();
    }

    if ( method_exists($this,'get_doc_url_attributes') ) {
      $faux_url_hash = UrlModel::get_url_hash($this->get_doc_url()); 
			$faux_url->retrieve($faux_url_hash,'urlhash');
      $doc_url_attrs = $this->get_doc_url_attributes($faux_url);
    } else {
      $doc_url_attrs = array('legiscope-remote');
      $faux_url_hash = UrlModel::get_url_hash($this->get_doc_url()); 
      $doc_url_attrs[] = $faux_url->retrieve($faux_url_hash,'urlhash')->in_database() ? 'cached' : 'uncached';
      if ( $faux_url->in_database() ) {
        $doc_url_attrs[] = $faux_url->get_logseconds_css();
      }
    }
    $doc_url_attrs = join(' ', $doc_url_attrs);

    if ( method_exists($this,'single_record_markup_template_a') ) {/*{{{*/
      $template = str_replace('{doc_url_hash}',$faux_url_hash,$this->single_record_markup_template_a());
    }/*}}}*/
    else {/*{{{*/
      $senatedoc             = get_class($this);
      $total_bills_in_system = $this->count();
      $template = <<<EOH
{$senatedoc} in system: {$total_bills_in_system}
<span class="sb-match-item">{sn}.{congress_tag}</span>
<span class="sb-match-item sb-match-subjects">{subjects}</span>
<span class="sb-match-item sb-match-description">{description}</span>
<span class="sb-match-item sb-match-significance">Scope: {significance}</span>
<span class="sb-match-item sb-match-status">Status: {status}</span>
<span class="sb-match-item sb-match-doc-url">Document: <a class="{doc_url_attrs}" href="{doc_url}" id="{$faux_url_hash}">{sn}</a></span>
<span class="sb-match-item sb-match-main-referral-comm">Committee: {main_referral_comm}</span>
<span class="sb-match-item sb-match-main-referral-comm">Secondary Committee: {secondary_committee}</span>
<span class="sb-match-item sb-match-committee-report-info">Committee Report: <a class="legiscope-remote" href="{comm_report_url}">{comm_report_info}</a></span>
EOH
      ;
    }/*}}}*/

    $template       = str_replace('{doc_url_attrs}', $doc_url_attrs, $template);
    $pagecontent    = $this->substitute($template);
    $congress_tag   = $this->get_congress_tag();

    //////////////////////////////////////////////////////////////////
    $sb_sn          = $this->get_sn();
    $committee_name = $this->get_main_referral_comm();

    if ( 0 < strlen($committee_name) ) {

      $committee_model = new SenateCommitteeModel();
      $name_regex      = LegislationCommonParseUtility::committee_name_regex($committee_name);
      $committee_name  = array();

      $committee_model->where(array('AND' => array(
        'committee_name' => "REGEXP '({$name_regex})'"
      )))->recordfetch_setup();

      while ( $committee_model->recordfetch($committee_name) ) {
        // $this->recursive_dump($committee_name,"(marker) - SB {$sb_sn}.{$congress_tag}");
      }
    }
    //////////////////////////////////////////////////////////////////

    $this->debug_final_sql = FALSE;
    $this->
      join_all()->
      where(array('AND' => array(
        '`a`.`id`' => $this->get_id(),
        //'{journal}.`congress_tag`' => $congress_tag,
        //'{journal_senate_journal_document_model}.`congress_tag`' => $congress_tag

      )))->
      recordfetch_setup();
    $sb = array();
    $this->debug_final_sql = FALSE;

    $committee_referrals = array();
    $reading_state = array();

    $reading_replace = array(
      '@R1@' => 'First Reading',
      '@R2@' => 'Second Reading',
      '@R3@' => 'Third Reading',
    );

    $secondary_committees = array();
    while ( $this->recordfetch($sb) ) {/*{{{*/
      if ( $debug_method ) {
        $this->syslog(__FUNCTION__,__LINE__,"(marker) - - Got entry {$sb['id']}");
        $this->recursive_dump($sb,"(marker) - -");
      }
      $journal       = $sb['journal'];
      if ( $congress_tag == nonempty_array_element($journal,'congress_tag') ) {
        $reading       = nonempty_array_element($journal['join'],'reading');
        $reading_date  = nonempty_array_element(explode(' ',nonempty_array_element($journal['join'],'reading_date',' -')),0);
        $journal_title = array_element($journal['data'],'title');
        $journal_url   = nonempty_array_element($journal['data'],'url');
        if ( !(is_null($reading) || is_null($journal_url)) ) {/*{{{*/
          $reading_lbl   = preg_replace(
            array_keys($reading_replace),
            array_values($reading_replace),
            $reading
          );
          $reading_state["{$reading}{$reading_date}"] = <<<EOH
<li><a href="{$journal_url}" class="legiscope-remote suppress-reorder">{$reading_lbl} ({$reading_date})</a> {$journal_title}</li>

EOH;
        }/*}}}*/
      }

      $committee = $sb['committee'];
      $committee_id = nonempty_array_element($committee['data'],'id');
      if ( !is_null($committee_id) ) {

        $committee_name = array_element($committee['data'],'committee_name');

        $committee_url = SenateCommitteeListParseUtility::get_committee_permalink_uri($committee_name);

        $referral_mode = array_element($committee['join'],'referral_mode');
        $referral_mode = ($referral_mode == 'primary') ? "Primary" : "Secondary";

        $committee_referrals["{$referral_mode}{$committee_id}"] = <<<EOH
<li><a class="legiscope-remote" href="/{$committee_url}">{$committee_name}</a> ({$referral_mode})</li>

EOH;
        if ( $referral_mode == 'Secondary' )
          $secondary_committees["{$referral_mode}{$committee_id}"] = '<a class="legiscope-remote" href="/' . $committee_url . '">' . $committee_name . "</a>";
        else
          $pagecontent = preg_replace('@{main_referral_comm_url}@i', "/{$committee_url}", $pagecontent);
      }
      if ( $debug_method ) $this->recursive_dump($sb,"(marker) {$senatedoc} {$sb_sn}.{$congress_tag} #{$sb['id']}");
    }/*}}}*/

    if ( 0 < count($reading_state) ) {/*{{{*/

      krsort($reading_state);
      $reading_state = join(" ", $reading_state);
			$reading_state = <<<EOH
<br/>
<br/>
<span>Reading</span>
<ul>{$reading_state}</ul>

EOH;
			if ( 1 == preg_match('@{reading_state}@i',$pagecontent) ) {
				$pagecontent = str_replace('{reading_state}',$reading_state,$pagecontent);
			} else {
				$pagecontent .= $reading_state;
			}
    }/*}}}*/
		else {
				$pagecontent = str_replace('{reading_state}','',$pagecontent);
		}

    if ( 0 < count($secondary_committees) ) {
      $secondary_committees = join(', ', $secondary_committees);
      $pagecontent = str_replace('{secondary_committees}',"{$secondary_committees}", $pagecontent);
    } else {
      $pagecontent = preg_replace('@((.*){secondary_committees}(.*))@i','',$pagecontent);
    }

    if ( 0 < count($committee_referrals) ) {/*{{{*/
      ksort($committee_referrals);
      $committee_referrals = join(" ", $committee_referrals);
      $committee_referrals = <<<EOH
<br/>
<br/>
<span>Referred to</span>
<ul>{$committee_referrals}</ul>

EOH;
			if ( 1 == preg_match('@{committee_referrals}@i',$pagecontent) ) {
				$pagecontent = str_replace('{committee_referrals}',$committee_referrals,$pagecontent);
			} else {
				$pagecontent .= $committee_referrals;
			}

    }/*}}}*/
		else {
			$pagecontent = str_replace('{committee_referrals}','',$pagecontent);
		}


    // Generate legislative history
    if ( method_exists($this,'get_legislative_history') ) {/*{{{*/
      if ( 0 < strlen(($legislative_history =  $this->get_legislative_history())) ) {/*{{{*/
        $legislative_history = @json_decode($legislative_history,TRUE);

        if ( is_array($legislative_history) && (0 < count($legislative_history)) ) {/*{{{*/
          if ( $debug_method ) $this->recursive_dump($legislative_history,"(marker) -- FH --");
          krsort($legislative_history);
          // History entries consist of alternating date/text lines
          // The first history entry should contain a selector class name
          // 'leg_table_date'
          $have_leg_sys_entries = FALSE;
          $history = array();
          $last_date = NULL;
          while ( 0 < count($legislative_history) ) {
            $entry = array_pop($legislative_history);
            if ( !$have_leg_sys_entries ) {/*{{{*/
              if ( 'lis_table_date' == nonempty_array_element($entry,'class') ) {
                $have_leg_sys_entries = TRUE;
                $last_date = $entry['text'];
                $history[$last_date] = array();
              }
              continue;
            }/*}}}*/
            if ( array_key_exists('url', $entry) ) continue;
            $date_test = nonempty_array_element($entry,'text');
            if ( $debug_method ) $this->syslog(__FUNCTION__,__LINE__,"(marker) -- - {$date_test}");
            if ( 1 == preg_match('@(president(.*)action)@i', $date_test) ) {
              break;
            } else if ( FALSE == ($date = DateTime::createFromFormat('m/d/Y H:i:s', "{$date_test} 00:00:00")) ) {
              if ( !is_array($history[$last_date]) ) $history[$last_date] = array();
              $history[$last_date][] = $date_test;
            } else {
              $last_date = $date_test;
              if ( !array_key_exists($date_test, $history) )
                $history[$date_test] = NULL;
            }
          }
          $history = array_filter($history);
          if ( $debug_method ) $this->recursive_dump($history,"(marker) -- FH --");
        }/*}}}*/

				$history_tabulation = '';
        if ( is_array($history) ) foreach ( $history as $date => $actions ) {/*{{{*/
          $date_element = DateTime::createFromFormat('m/d/Y H:i:s', "{$date} 00:00:00");
          $timestamp    = $date_element->getTimestamp();
          $date         = $date_element->format('F j, Y');
          $day          = $date_element->format('l');
          $history_tabulation .= <<<EOH
<div class="process-date" id="ts-{$timestamp}"> 
<span class="process-date">{$date}<br/>{$day}</span>
<ul class="process-description">

EOH;
          foreach ( $actions as $action ) {
            $history_tabulation .= <<<EOH
<li class="process-actions">{$action}</li>

EOH;
          }

          $history_tabulation .= <<<EOH
</ul>
</div>
EOH;
        }/*}}}*/

				if ( 1 == preg_match('@{history_tabulation}@i',$pagecontent) ) {
					$pagecontent = str_replace('{history_tabulation}',$history_tabulation,$pagecontent);
				} else {
					$pagecontent .= $history_tabulation;
				}

      }/*}}}*/
    }/*}}}*/

    $pagecontent  = str_replace('[BR]','<br/>', $pagecontent);
    $document_hash = md5($this->get_sn() . "." . $this->get_congress_tag());
    // Generate wrapper to automatically fetch all uncached links
    $pagecontent = <<<EOH
<div class="admin-senate-document" id="senate-document-{$document_hash}">{$pagecontent}</div>

<script type="text/javascript">
function load_uncached_links() {
  jQuery('div[id=senate-document-{$document_hash}]')
    .find('a[class*=uncached]')
    .first()
    .each(function(){
      var url = jQuery(this).attr('href');
      var linktext = jQuery(this).html();
      var self = this;
      jQuery.ajax({
        type     : 'POST',
        url      : '/seek/',
        data     : { url : url, update : jQuery('#update').prop('checked'), proxy : jQuery('#proxy').prop('checked'), modifier : jQuery('#seek').prop('checked'), fr: true, linktext: linktext },
        cache    : false,
        dataType : 'json',
        async    : true,
        beforeSend : (function() {
          display_wait_notification();
        }),
        complete : (function(jqueryXHR, textStatus) {
          remove_wait_notification();
        }),
        success  : (function(data, httpstatus, jqueryXHR) {
          jQuery(self).addClass('cached').removeClass('uncached');
					if ( data && data.hoststats ) set_hoststats(data.hoststats);
          if ( data && data.lastupdate ) replace_contentof('lastupdate',data.lastupdate);
          if ( data && data.timedelta ) replace_contentof('time-delta', data.timedelta);
          update_a_age(data);
          setTimeout((function(){load_uncached_links();}),100);
        })
      });
      return true;
    });
} 
jQuery(document).ready(function(){
  load_uncached_links();
});
</script>


EOH;

    return $pagecontent;

  }/*}}}*/

  function & set_filing_date($v) { return $this->set_dtm_attr($v,'filing_date'); }
  function get_filing_date() { return $this->get_dtm_attr('filing_date'); }

  //  Committee names

  function cleanup_committee_name($committee_name) {/*{{{*/
    $committee_name = str_replace(array("\x09","[BR]",'%20'),array(""," ",' '),trim($committee_name));
    $committee_name = preg_replace(
      array("@[^,'A-Z0-9 ]@i",'@[ ]+@'),
      array('',' '),$committee_name);
    return trim($committee_name);
  }/*}}}*/

  function cursor_fetch_by_name_regex(& $search_name) {/*{{{*/
    return $this->fetch_by_name_regex($search_name, TRUE);
  }/*}}}*/

  function fetch_by_name_regex(& $search_name, $cursor = FALSE) {/*{{{*/

    $debug_method = FALSE;

    if ( !$cursor ) {
      // The limit() call is necessary and reasonable since large resultsets
      // can choke the application, and should be retrieved with a cursor anyway.
      $search_name = $this->
        limit(1,0)->
        fetch(array(
          'LOWER(committee_name)' => "REGEXP '({$search_name})'"
        ),'AND');
      $result = $this->in_database();
    } else if ( is_null($search_name) ) {
      $result = $this->recordfetch($search_name,TRUE);
    } else {
      // Return a record in the search_name parameter
      $this->
        join_all()->
        where(array('AND' => array(
        'committee_name' => "REGEXP '({$search_name})'"
      )))->recordfetch_setup();
      $result = $this->recordfetch($search_name,TRUE);
    }
    if ( $result ) {
      if ( $debug_method ) $this->syslog(__FUNCTION__,__LINE__, "(marker) Found record " . $this->get_id() . " (" . $this->get_committee_name() . ")");
    } else {
      $this->syslog(__FUNCTION__,__LINE__, 
        $cursor
        ? "(marker) No cursor match"
        : "(marker) Failed to match record using regex {$search_name}"
      );
    }
    return $result;
  }/*}}}*/

  function fetch_by_committee_name($committee_name, $cursor = FALSE) {/*{{{*/

    $debug_method = FALSE;

    $this->syslog(__FUNCTION__,__LINE__,"(marker) - - Committee name raw   {$committee_name}");

    $search_name = LegislationCommonParseUtility::committee_name_regex($committee_name);

    if ( FALSE == $search_name ) {
      $this->syslog(__FUNCTION__,__LINE__,"(error) - - - - Unparseable committee name '{$committee_name}'");
      return FALSE;
    }

    $this->syslog(__FUNCTION__,__LINE__,"(marker) - - Committee name regex {$search_name}");

    return $this->fetch_by_name_regex($search_name, $cursor);

  }/*}}}*/

  // OCR-related, document formatting methods

  function test_document_ocr_result($url_accessor = NULL) {/*{{{*/

    // Reload this SenateBillDocument and test for presence of OCR version of doc_url PDF.
    // Return TRUE if the document is associated with at least one converted record (stored as a set of ContentDocuments)
    //        NULL
    //        FALSE 

    $debug_method = FALSE;

		if ( !$this->in_database() ) {
			$this->syslog(__FUNCTION__,__LINE__,"(warning) Model not loaded. Cannot proceed.");
		 	return NULL;
		}

		if ( is_null($url_accessor) ) $url_accessor = 'get_doc_url';

    $faux_url         = new UrlModel();
		$document_source  = str_replace(' ','%20',$this->$url_accessor());
    $faux_url_hash    = UrlModel::get_url_hash($document_source);
    $url_stored       = $faux_url->retrieve($faux_url_hash,'urlhash')->in_database();
    $pdf_content_hash = $faux_url->get_content_hash();
		if ( $debug_method ) $this->syslog(__FUNCTION__,__LINE__,"(marker) Using contents of UrlModel #" . $faux_url->get_id() . " {$document_source}");
    // Retrieve all OCR content records associated with this Senate document.
    $document_id      = $this->get_id();
    $this->
      join(array('ocrcontent'))->
      where(array('AND' => array(
        'id' => $document_id,
        '{ocrcontent}.`source_contenthash`' => $pdf_content_hash,
      )))->
      recordfetch_setup();

    $matched = 0;
    $remove_entries = array();
    while ( $this->recordfetch($r,TRUE) ) {
      if ( $debug_method ) $this->syslog(__FUNCTION__,__LINE__,"(marker) -- Record #{$r['id']} {$r['sn']}.{$r['congress_tag']}");
      $ocrcontent = $this->get_ocrcontent();
      $this->recursive_dump($ocrcontent,"(marker) ->");
      $data = nonempty_array_element($ocrcontent,'data');
      $meta = nonempty_array_element($data,'content_meta');
      $data = nonempty_array_element($data,'data');
      
      if ( $debug_method ) {
        $this->syslog(__FUNCTION__,__LINE__,"(marker) -- Test data = {$data}");
        $this->syslog(__FUNCTION__,__LINE__,"(marker) -- Test meta = {$meta}");
      }
      // Both the content and content_meta document source must be nonempty
      if ( (0 < mb_strlen(preg_replace('@[^-A-Z0-9.,: ]@i', '', $data))) && (0 < strlen($meta)) ) {
        $matched++;
      }
      else {
        // Otherwise both the Join and ContentDocument records are removed.
        $remove_entry = array_filter(array(
          'join' => nonempty_array_element($ocrcontent['join'],'id'),
          'data' => nonempty_array_element($ocrcontent['data'],'id'),
        ));
        $this->syslog(__FUNCTION__,__LINE__,"(marker) -- Marking invalid Join {$r['sn']}.{$r['congress_tag']}");
        $this->recursive_dump($remove_entry,"(marker)");
        if ( 0 < count($remove_entry) ) $remove_entries[] = $remove_entry;
      }
    }
    while ( 0 < count($remove_entries) ) {
      $remove_entry = array_shift($remove_entries);
      if ( !is_null(nonempty_array_element($remove_entry,'join')))
      $this->get_join_instance('ocrcontent')->
        retrieve($remove_entry['join'],'id')->
        remove();
      if ( !is_null(nonempty_array_element($remove_entry,'data')))
      $this->get_foreign_obj_instance('ocrcontent')->
        retrieve($remove_entry['data'],'id')->
        remove();
    }
    if ( 0 == $matched ) {/*{{{*/
      // No record matched
      $ocr_result_file = LegiscopeBase::get_ocr_queue_stem($faux_url) . '.txt';
      if ( file_exists($ocr_result_file) && is_readable($ocr_result_file) ) {/*{{{*/

        $properties = stat($ocr_result_file);
        $properties['sha1'] = hash_file('sha1', $ocr_result_file);

        if ( $debug_method ) {
          $this->syslog(__FUNCTION__,__LINE__,"(warning) OCR output file {$ocr_result_file} present.");
          $this->syslog(__FUNCTION__,__LINE__,"(warning) OCR output file size: {$properties['size']}" );
          $this->syslog(__FUNCTION__,__LINE__,"(warning) OCR data model: " .
            get_class($this->get_join_instance('ocrcontent')));
        }

        $ocr_data = new ContentDocumentModel();
        $ocr_data_id = $ocr_data->retrieve($properties['sha1'],'content_hash')->get_id();

        if ( is_null($ocr_data_id) ) {/*{{{*/
          $data = array(
            'data' => file_get_contents($ocr_result_file),
            'content_hash' => $properties['sha1'],
            'content_type' => 'ocr_result',
            'content_meta' => $document_source,
            'last_fetch' => $this->get_last_fetch(),
            'create_time' => time(),
          );
          $ocr_data_id = $ocr_data->
            set_contents_from_array($data)->
            fields(array_keys($data))->
            stow();
          $this->syslog(__FUNCTION__,__LINE__,"(warning) Created OCR data record ID: #" . $ocr_data_id  );
        }/*}}}*/

        if ( 0 < intval($ocr_data_id) ) {/*{{{*/
          $join = array( $ocr_data_id => array(
            'source_contenthash' => $pdf_content_hash,
            'last_update' => time(),
            'create_time' => time(),
          ));
          $join_result = $this->create_joins('ContentDocumentModel',$join);
          $this->syslog(__FUNCTION__,__LINE__,"(warning) Created Join: #" . $join_result  );
        }/*}}}*/

      }/*}}}*/
    }/*}}}*/
    else {
      if ( !$this->get_searchable() ) {
        $this->set_searchable(TRUE)->fields(array('searchable'))->stow();
      }
      $this->syslog(__FUNCTION__,__LINE__,"(marker) Found {$matched} Join objects for OCR source " . $faux_url->get_url());
    }

    return $matched > 0;
  }/*}}}*/

  function reconstitute_ocr_text() {/*{{{*/
    $t = nonempty_array_element($this->get_ocrcontent(),'data');
    if ( is_null($t) || !is_array($t) ) return FALSE;
    $t = nonempty_array_element($t,'data');
    if ( !is_string($t) ) return FALSE;
    $t = preg_split('@(\n|\f|\r)@i', $t);
    $this->syslog( __FUNCTION__,__LINE__, "(error) Result of split: " . gettype($t));
    if ( !is_array($t) ) return FALSE;
    $final = array();

    $whole_line = array();
    $last_line  = 0;
    $current_line = 0;

    while ( 0 < count($t) ) {/*{{{*/

      $line = array_shift($t);

      // Strip trailing noise
      $line = preg_replace('@([^A-Z0-9;:,.-]{1,})$@i','', $line);

      $non_alnum        = mb_strlen(preg_replace('@[^A-Z0-9;:,. -]@i','',$line));
      $just_alnum       = mb_strlen(preg_replace('@[A-Z0-9. ]@i','',$line));
      $ratio = 1.0;
      if ( 0 < mb_strlen($line) ) {
        $ratio = ( floatval($just_alnum) / floatval(mb_strlen($line)) );
        if ( $ratio > 0.2 ) $line = "[noise]";
      }

      $maybe_pagenumber = 1 == preg_match('@^([0-9]{1,})$@', $line);
      $line_number = array();
      $is_numbered_line = 1 == preg_match('@^([0-9]{1,}[ ]{1,})@i',$line,$line_number);
      $is_end_of_line   = 1 == preg_match('@([:;. -]{1,}|([:;][ ]{1,}or)|[.][0-9]{1,})$@i', $line);
      $is_section_head  = 1 == preg_match('@^(SEC|Art)@i', $line);
      $introduction     = 1 == preg_match('@^(EXPLANATORY[ ]*NOTE)@i',$line);

      if ( $maybe_pagenumber ) {
        $replacement = ' ';//'---------- [Page $2] --------'
        $line = preg_replace('@^(([0-9]{1,})[ ]*)$@i', $replacement, $line);
      }
      else if ( $is_numbered_line ) {
        $replacement = ' ';//'[Line $2]'
        $line = preg_replace('@^(([0-9]{1,})[ ]*)@i', $replacement, $line);
      }

      if ( $line == '[noise]' ) {
        $is_end_of_line |= TRUE;
      } else
        $whole_line[] = $line;

      $add_space_beforeline = ( $is_section_head || $introduction );
      $add_space_afterline  = ( $is_end_of_line || $introduction );

      if ( $add_space_afterline || $add_space_afterline ) {
        $line = trim(join('',$whole_line));
        for ( $p = $last_line ; $p < $last_line ; $p++ ) {
          $final[$p] = NULL;
        }
        $last_line = $current_line ;
        $whole_line = array();
      }
      else $line = NULL;

      if ( $add_space_beforeline ) $final[$current_line++] = '[emptyline]';
      $final[$current_line++] = $line;
      if ( $add_space_afterline ) $final[$current_line++] = '[emptyline]';

    }/*}}}*/

    // Remove multiple adjacent [emptyline] entries.
    $final      = array_filter($final);
    $prev_empty = FALSE;
    $t          = array();
    while ( 0 < count($final) ) {
      $line = array_shift($final);
      if ( $line == '[emptyline]' ) {
        if ( $prev_empty ) continue;
        $prev_empty = TRUE;
        $line = '';
      }
      else $prev_empty = FALSE;
      $t[] = array('text' => $line);
    }
    // $this->recursive_dump($final,"(error)");
    $ocrcontent = $this->get_ocrcontent();
    $ocrcontent['data']['data'] = $t;
    $this->set_ocrcontent($ocrcontent);
  }/*}}}*/
  
  function line_formatter($s) {/*{{{*/
    return preg_replace(
      array(
        "@\s@",
        '@\[([ ]*)(.*)([ ]*)\]@i',
        '@^[ ]*(BOOK|TITLE|CHAPTER)([ ]*)(.*)@i',
        '@^[ ]*ART(ICLE)*([ ]*)(.*)@i',

        '@^[ ]*(SEC(TION|\.)([ ]*)([0-9]*)[.]*)@i',
        '@^[ ]*\(([a-z]{1}|[ivx]{1,4}|[0-9]{1,})[.]?\)([\s]*)@i',
        '@^[ ]*([0-9]{1,})([.])@i',
        '@^[ ]*([a-z])([.][\s])@i',

        '@(Republic Act N(o.|umber)([ ]*)([0-9]{4,}))@i',
        '@(Senate Bill N(o.|umber)([ ]*)([0-9]{4,}))@i',
        '@(House Bill N(o.|umber)([ ]*)([0-9]{4,}))@i',
      ),
      array(
        ' ',
        '<span class="document-section document-heading-1">$2</span>',
        '<span class="document-section document-heading-1">$1 $3</span>',
        '<span class="document-section document-heading-1">Article $3</span>',

        '<span class="document-section document-heading-2">Section $4</span>.',
        '<span class="document-section document-heading-3">($1) </span>',
        '<span class="document-section document-heading-4">$1. </span>',
        '<span class="document-section document-heading-4">$1. </span>',

        '[{RA-$4}]',
        '[{SB-$4}]',
        '[{HB-$4}]',
      ),
      $this->iconv($s)
    );
  }/*}}}*/

  function format_document($content) {/*{{{*/
    if (is_array($content) && (0 < count($content))) {
      $content = array_values(array_filter(array_map(create_function(
        '$a', 'return str_replace("[BR]","<br/>",nonempty_array_element($a,"text"));'
      ),$content)));
      // Replace line headings
      array_walk($content,create_function(
        '& $a, $k, $s', '$a = $s->line_formatter($a);'
      ), $this);
      $content = array_filter(array_map(create_function(
        '$a', 'return "<p>{$a}</p>";'
      ),$content));
      return  join("\n",$content);
    }
    return NULL;
  }/*}}}*/


}
