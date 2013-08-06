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
        'committee_name' => "REGEXP '(".join('|',$referring_committees).")'"
      )))->recordfetch_setup();

      // Regex => Original name 
      $referring_committees = array_flip($referring_committees);

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

  function generate_non_session_linked_markup() {/*{{{*/

    $debug_method = FALSE || (property_exists($this,'debug_method') && $this->debug_method);

    $faux_url = new UrlModel();

    $senatedoc = get_class($this);

    if ( $debug_method ) $this->syslog( __FUNCTION__, __LINE__, "(marker) --- --- --- - - - --- --- --- Got #" . $this->get_id() . " " . get_class($this) );

    $total_bills_in_system = $this->count();

    $doc_url_attrs = array('legiscope-remote');
    $faux_url_hash = UrlModel::get_url_hash($this->get_url()); 
    if( $faux_url->retrieve($faux_url_hash,'urlhash')->in_database() ) {
      $doc_url_attrs[] = 'cached';
    }
    $doc_url_attrs = join(' ', $doc_url_attrs);

    if ( method_exists($this,'single_record_markup_template_a') ) {
      $template = $this->single_record_markup_template_a();
    }
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
<span class="sb-match-item sb-match-doc-url">Document: <a class="{doc_url_attrs}" href="{doc_url}">{sn}</a></span>
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
      $pagecontent .= <<<EOH
<br/>
<br/>
<span>Reading</span>
<ul>{$reading_state}</ul>

EOH;
    }/*}}}*/

    if ( 0 < count($secondary_committees) ) {
      $secondary_committees = join(', ', $secondary_committees);
      $pagecontent = str_replace('{secondary_committees}',"{$secondary_committees}", $pagecontent);
    } else {
      $pagecontent = preg_replace('@((.*){secondary_committees}(.*))@i','',$pagecontent);
    }

    if ( 0 < count($committee_referrals) ) {/*{{{*/
      ksort($committee_referrals);
      $committee_referrals = join(" ", $committee_referrals);
      $pagecontent .= <<<EOH
<br/>
<br/>
<span>Referred to</span>
<ul>{$committee_referrals}</ul>

EOH;
    }/*}}}*/

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

        if ( is_array($history) ) foreach ( $history as $date => $actions ) {/*{{{*/
          $date_element = DateTime::createFromFormat('m/d/Y H:i:s', "{$date} 00:00:00");
          $timestamp    = $date_element->getTimestamp();
          $date         = $date_element->format('F j, Y');
          $day          = $date_element->format('l');
          $pagecontent .= <<<EOH
<div class="process-date" id="ts-{$timestamp}"> 
<span class="process-date">{$date}<br/>{$day}</span>
<ul class="process-description">

EOH;
          foreach ( $actions as $action ) {
            $pagecontent .= <<<EOH
<li class="process-actions">{$action}</li>

EOH;
          }

          $pagecontent .= <<<EOH
</ul>
</div>
EOH;
        }/*}}}*/

      }/*}}}*/
    }/*}}}*/

    $pagecontent  = str_replace('[BR]','<br/>', $pagecontent);

    return $pagecontent;

  }/*}}}*/

  function & set_filing_date($v) { return $this->set_dtm_attr($v,'filing_date'); }
  function get_filing_date() { return $this->get_dtm_attr('filing_date'); }

}
