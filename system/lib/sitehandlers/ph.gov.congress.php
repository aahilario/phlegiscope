<?php

class CongressGovPh extends SeekAction {
  
  private $container_buffer = NULL;

  function __construct() {
    define('RETAIN_TRAILING_URL_SLASH',TRUE);
    parent::__construct();
  }


  /** Named handlers **/

  function generic(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    $this->syslog( __FUNCTION__, __LINE__, "Invoked for " . $urlmodel->get_url() );
    $this->common_unhandled_page_parser($parser,$pagecontent,$urlmodel);
  }/*}}}*/

  function common_unhandled_page_parser(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    $this->syslog( __FUNCTION__, __LINE__, "Invoked for " . $urlmodel->get_url() );
    /** SVN #418 (internal): Loading raw page content breaks processing of committee information page **/
    $common      = new CongressCommonParseUtility();
    $common->
      set_parent_url($urlmodel->get_url())->
      parse_html($urlmodel->get_pagecontent(),$urlmodel->get_response_header());
    //$this->recursive_dump($common->get_containers(),"(marker) --");
    $pagecontent = str_replace('[BR]','<br/>',join('',$common->get_filtered_doc()));
    $parser->json_reply['retainoriginal'] = TRUE;
    $parser->json_reply['subcontent'] = $pagecontent;

  }/*}}}*/

  function seek_by_pathfragment_f0923b5f3bb0f191dedd93e16d3658ff(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    // Handler for metadata path
    // http://www.congress.gov.ph/legis/search/hist_show.php?save=1&journal=J069&switch=0&bill_no=HB03933&congress=15

    $common      = new CongressCommonParseUtility();
    $common->
      set_parent_url($urlmodel->get_url())->
      parse_html($urlmodel->get_pagecontent(),$urlmodel->get_response_header());

    $hb = new HouseBillDocumentModel();

    $this->recursive_dump(($meta = $common->get_containers(
      'children[tagname*=body]{[text]}',0
    )), "(barker) --- -- --- --");

    $pagecontent = str_replace('[BR]','<br/>',join('<br/>',array_element($meta,0,array())));

    $urlmodel->ensure_custom_parse();

    $parser->json_reply = array('retainoriginal' => TRUE);
  }/*}}}*/

  /** House Bills **/

  function seek_postparse_hb_history(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    // http://www.congress.gov.ph/legis/search/hist_show.php?congress=16&save=1&journal=&switch=0&bill_no=HB00485
    $this->syslog( __FUNCTION__, __LINE__, "Invoked for " . $urlmodel->get_url() );
    $hb_parser = new CongressHbListParseUtility();
    $hb_parser->seek_postparse_hb_history($parser,$pagecontent,$urlmodel);
    $hb_parser = NULL;
  }/*}}}*/

  function seek_by_pathfragment_43c81bac990cafa5d9a1d772f89a4488(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    // http://www.congress.gov.ph/legis/search/hist_show.php?congress=16&save=1&journal=&switch=0&bill_no=HB00485
    $this->syslog( __FUNCTION__, __LINE__, "Invoked for " . $urlmodel->get_url() );
    $this->seek_postparse_hb_history($parser,$pagecontent,$urlmodel);
  }/*}}}*/

  function seek_by_pathfragment_e4b820a97cc20aae3d312cf6d3a3c2ff(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    // http://www.congress.gov.ph/download/basic_16/HB01450.pdf
    $result = $this->write_to_ocr_queue($urlmodel);
    $hb_parser = new CongressHbListParseUtility();
    $hb_parser->generate_descriptive_markup($parser, $pagecontent, $urlmodel);
    unset($hb_parser);

  }/*}}}*/

  /** **/

  function seek_postparse_bypath_plus_queryvars_2ac6215e6619296ce3f28b184962b8a2(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    $this->syslog( __FUNCTION__, __LINE__, "Invoked for " . $urlmodel->get_url() );
    $this->seek_postparse_ra_hb($parser,$pagecontent,$urlmodel);
  }/*}}}*/

  function seek_postparse_ra_hb(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    // http://www.congress.gov.ph/download/index.php?d=ra
    $match_urlpart = array();
    $element = $urlmodel->get_query_element('d');
    if ( !is_null($element) ) {
      switch ( strtolower($element) ) {
        case 'journals': 
          return $this->seek_postparse_jnl($parser,$pagecontent,$urlmodel);
        case 'ra' :
          return $this->seek_postparse_ra($parser,$pagecontent,$urlmodel);
        case 'congrecords' :
          return $this->seek_postparse_congrecords($parser,$pagecontent,$urlmodel);
        default: 
          $this->syslog( __FUNCTION__, __LINE__, "(warning) Unhandled URL #" . $urlmodel->get_id() . " {$match_urlpart[1]} {$urlmodel}" );
          return $this->common_unhandled_page_parser($parser,$pagecontent,$urlmodel);
      }
    }


  }/*}}}*/

  function seek_postparse_jnl(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/

    $document_parser = new CongressJournalCatalogParseUtility();

    $document_parser->seek_postparse_jnl($parser,$pagecontent,$urlmodel);

    $document_parser = NULL;
    unset($document_parser);

  }/*}}}*/

  function seek_by_pathfragment_06c9f7df4253e828b9e2923ade26c602(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/

    $result = $this->write_to_ocr_queue($urlmodel);
    $ra_parser = new RepublicActParseUtility(); 
    $ra_parser->generate_descriptive_markup($parser, $pagecontent, $urlmodel);
    $ra_parser = NULL;
    if ( !(0 < mb_strlen(nonempty_array_element($parser->json_reply,'subcontent'))) ) {
      $parser->json_reply['subcontent'] = "<h2>OCR Target</h2>" . $pagecontent;
    }
    unset($ra_parser);

  }/*}}}*/

  function seek_postparse_ra(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/

    $document_parser = new CongressRepublicActCatalogParseUtility();
    $document_model  = new RepublicActDocumentModel();

    $document_parser->debug_method = TRUE;
    $document_parser->seek_postparse_ra($parser,$pagecontent,$urlmodel,$document_model);

    $document_parser = NULL;
    unset($document_parser);

  }/*}}}*/

  function seek_postparse_congrecords(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/

    $restore_url     = NULL;
    $content_changed = FALSE;

    if ( !is_null($parser->metalink_url) ) {
      $restore_url = $urlmodel->get_urlhash();
      $urlmodel->fetch($parser->metalink_url,'url');
      $this->syslog( __FUNCTION__, __LINE__, "(warning) Switching metalink URL #" . $urlmodel->get_id() . " {$parser->metalink_url} <- {$urlmodel}" );
      $pagecontent = $urlmodel->get_pagecontent();
    }

    $this->syslog( __FUNCTION__, __LINE__, "(marker) Pagecontent postparser invocation for " . $urlmodel->get_url() );

    $cache_filename = md5(__FUNCTION__ . $parser->trigger_linktext);
    $cache_filename = "./cache/{$this->subject_host_hash}-{$cache_filename}.generated";

    if (0) {/*{{{*/
      if ( C('ENABLE_GENERATED_CONTENT_BUFFERING') || !$content_changed ) {
        if ( $parser->from_network ) unlink($cache_filename);
        else if ( file_exists($cache_filename) ) {
          $this->syslog( __FUNCTION__, __LINE__, "Retrieving cached markup for " . $urlmodel->get_url() . " from {$cache_filename}" );
          $pagecontent = file_get_contents($cache_filename);
          if ( !is_null($restore_url) ) $urlmodel->fetch($restore_url,'url');
          return;
        }
      }
    }/*}}}*/

    $common      = new CongressCommonParseUtility();
    $ra_linktext = $parser->trigger_linktext;

    $common->set_parent_url($urlmodel->get_url())->parse_html($urlmodel->get_pagecontent(),$urlmodel->get_response_header());

    $this->recursive_dump(($target_form = array_values($common->get_containers(
      'children[attrs:ACTION*=download\?d=congrecords$]'
    ))),"(debug) Extracted content");

    if (is_null($target_form[0])) {/*{{{*/
      $this->recursive_dump($target_form,'(warning) Form Data');
      $this->recursive_dump($common->get_containers(),'(warning) Structure Change');
      $this->syslog(__FUNCTION__,__LINE__,"(warning) ------------------ STRUCTURE CHANGE ON {$urlmodel} ! ---------------------");
      if ( !is_null($restore_url) ) $urlmodel->fetch($restore_url,'url');
      return;
    }/*}}}*/

    $target_form = $target_form[0]; 

    $this->recursive_dump(($target_form = $common->extract_form_controls($target_form)),
      'Target form components');

    // $this->recursive_dump($target_form,__LINE__);
    extract($target_form);
    $this->recursive_dump($select_options,'SELECT values');

    $metalink_data = array();

    // Extract Congress selector for use as FORM submit action content
    $replacement_content = '';
    foreach ( $select_options as $select_option ) {/*{{{*/
      // Take a copy of the rest of the form controls
      if ( empty($select_option['value']) ) continue;
      $control_set = is_array($form_controls) ? $form_controls : array();
      $control_set[$select_name] = $select_option['value'];
      $control_set['Submit'] = 'submit';
      $controlset_json_base64 = base64_encode(json_encode($control_set));
      $controlset_hash = md5($controlset_json_base64);
      $faux_url = UrlModel::parse_url($urlmodel->get_url());
      $faux_url['query'] = "d=congrecords";
      $faux_url = UrlModel::recompose_url($faux_url,array(),FALSE);
      $link_class_selector = array("fauxpost");
      if ( $ra_linktext == $select_option['text'] ) {
        $link_class_selector[] = "selected";
      }
      $link_class_selector = join(' ', $link_class_selector);
      $metalink_data[] = <<<EOH
EOH;
      $generated_link = <<<EOH
<a href="{$faux_url}" class="{$link_class_selector}" id="switch-{$controlset_hash}">{$select_option['text']}</a>
<span id="content-{$controlset_hash}" style="display:none">{$controlset_json_base64}</span>
EOH;
      $replacement_content .= $generated_link;
    }/*}}}*/

    // ----------------------------------------------------------------------
    // Coerce the parent URL to index.php?d=congrecords
    $parent_url   = UrlModel::parse_url($urlmodel->get_url());
    $parent_url['query'] = 'd=congrecords';
    $parent_url   = UrlModel::recompose_url($parent_url,array(),FALSE);
    $test_url     = new UrlModel();

    $pagecontent = "{$replacement_content}<br/><hr/>";
    $urlmodel->increment_hits()->stow();

    $test_url->fetch($parent_url,'url');
    $page = $urlmodel->get_pagecontent();
    $test_url->set_pagecontent($page);
    $test_url->set_response_header($urlmodel->get_response_header())->increment_hits()->stow();

    $ra_listparser = new CongressRaListParseUtility();
    $ra_listparser->debug_tags = FALSE;
    $ra_listparser->set_parent_url($urlmodel->get_url())->parse_html($page,$urlmodel->get_response_header());
    $ra_listparser->debug_operators = FALSE;

    $this->recursive_dump(($ra_list = $ra_listparser->get_containers(
      '*[item=RA][bill-head=*]'
    )),"(marker) Extracted content");

    $replacement_content = '';

    $this->syslog(__FUNCTION__,__LINE__,"(warning) Long operation. Parsing list of republic acts. Entries: " . count($ra_list));

    $parent_url    = UrlModel::parse_url($parent_url);
    $republic_act  = new RepublicActDocumentModel();
    $republic_acts = array();
    $sn_stacks     = array(0 => array());
    $stacked_count = 0;

    foreach ( $ra_list as $ra ) {/*{{{*/

      $url           = UrlModel::normalize_url($parent_url, $ra);
      $urlhash       = UrlModel::get_url_hash($url);
      $ra_number     = join(' ',$ra['bill-head']);
      $approval_date = NULL;
      $origin        = NULL;

      if ( !array_key_exists('meta', $ra) ) {/*{{{*/
        $this->syslog(__FUNCTION__,__LINE__,"(marker) Skipping {$ra_number} {$url}");
        continue;
      }/*}}}*/

      $ra_meta = join('', $ra['meta']);
      $ra_meta = preg_replace(
        array(
          "@Origin:@iU",
          "@Approved by(.*) on (.*)@iU",
        ),
        array(
          ';ORIGIN:$1',
          ';APPROVALDATE:$2',
        ),
        $ra_meta
      );

      $match_parts = array();
      preg_match_all('@([^;:]*):([^;]*)@',$ra_meta,$match_parts);

      if ( is_array($match_parts) && (0 < count($match_parts)) ) {/*{{{*/
        $ra_meta       = array_combine($match_parts[1], $match_parts[2]);
        $approval_date = trim(array_element($ra_meta,'APPROVALDATE'));
        $origin        = trim(array_element($ra_meta,'ORIGIN'));
      }/*}}}*/

      if ( FALSE == strtotime($approval_date) ) $approval_date = NULL; 

      // There are a few hundred Republic Acts enumerated per Congress.
      // We take one memory-intensive iteration pass to stack RA series numbers,
      // and a second one to both pop nonexistent RA entries from that stack
      // and create missing records in the database.

      if ( 0 < strlen($ra_number) ) {/*{{{*/// Stow the Republic Act record

        $now_time        = time();
        $target_congress = preg_replace("@[^0-9]*@","",$parser->trigger_linktext);

        $republic_acts[$ra_number] = array(
          'congress_tag'  => $target_congress,
          'sn'            => $ra_number,
          'origin'        => $origin,
          'description'   => join(' ',$ra['desc']),
          'url'           => $url,
          'approval_date' => $approval_date,
          'searchable'    => FALSE, // $searchable,$test_url->fetch($urlhash,'urlhash'); $searchable = $test_url->in_database() ? 1 : 0;
          'last_fetch'    => $now_time,
          '__META__' => array(
            'urlhash' => $urlhash,
          ),
        );
        $sn_stacks[$stacked_count][] = $ra_number;
        if ( 20 <= count($sn_stacks[$stacked_count]) ) {
          $stacked_count++;
          $sn_stacks[$stacked_count] = array();
        }
        //$sorted_desc[$ra_number] = $republic_act->get_standard_listing_markup($ra_number,'sn');
      }/*}}}*/

    }/*}}}*/

    $sorted_desc = array();

    $this->syslog(__FUNCTION__,__LINE__,'(marker) Elements ' . count($sorted_desc));
    $this->syslog(__FUNCTION__,__LINE__,'(marker) Stacked clusters ' . count($sn_stacks));
    // $this->recursive_dump($sn_stacks, "(marker) SNs");

    $ra_template = <<<EOH
<div class="republic-act-entry">
<span class="republic-act-heading"><a href="{url}" class="legiscope-remote {cache_state}" id="{urlhash}">{sn}</a></span>
<span class="republic-act-desc">{description}</span>
<span class="republic-act-meta">Origin of legislation: {origin}</span>
<span class="republic-act-meta">Passed into law: {approval_date}</span>
</div>

EOH;

    // Deplete the stack, generating markup for entries that exist
    while ( 0 < count( $sn_stacks ) ) {/*{{{*/
      $sn_stack = array_pop($sn_stacks); 
      $ra = array();
      $republic_act->where(array('AND' => array(
        'sn' => $sn_stack
      )))->recordfetch_setup(); 
      // Now remove entries from $republic_acts
      while ( $republic_act->recordfetch($ra,TRUE) ) {
        $ra_number = $ra['sn'];
        $republic_acts[$ra_number] = NULL;
        $urlhash = $republic_acts[$ra_number]['__META__']['urlhash'];
        // Generate markup for already-cached entries
        $sorted_desc[$ra_number] = preg_replace(
          array(
            '@{urlhash}@i',
            '@{cache_state}@i',
          ),
          array(
            $urlhash,
            'cached',
          ),
          $republic_act->substitute($ra_template)
        );
      }
    }/*}}}*/
    $republic_acts = array_filter($republic_acts);

    $this->recursive_dump(array_keys($republic_acts),"(marker) -Remainder-");

    krsort($sorted_desc,SORT_STRING);

    $pagecontent .= join("\n",$sorted_desc);

    if (1) {
      if ( $content_changed || C('ENABLE_GENERATED_CONTENT_BUFFERING') ) {
        file_put_contents($cache_filename, join('',$ra_listparser->get_filtered_doc()));
      }
    }

    if ( !is_null($restore_url) ) $urlmodel->fetch($restore_url,'urlhash');

    $this->syslog(__FUNCTION__,__LINE__,'---------- DONE ----------- ' . strlen($pagecontent));

  }/*}}}*/

  function seek_postparse_d_billstext(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/

    $hb_listparser = new CongressHbListParseUtility();
    $hb_docmodel   = new HouseBillDocumentModel();
    $hb_listparser->seek_postparse_d_billstext($parser,$pagecontent,$urlmodel,$hb_docmodel);

  }/*}}}*/


  /** PDF OCR handler **/

  function seek_by_pathfragment_d01dbc5f89e3b7471ee48dea98fcc98e(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    // http://www.congress.gov.ph/download/ra_12/RA09332.pdf
    $result = $this->write_to_ocr_queue($urlmodel);
    $ra_parser = new RepublicActParseUtility(); 
    $ra_parser->generate_descriptive_markup($parser, $pagecontent, $urlmodel);
    $ra_parser = NULL;
    $this->syslog(__FUNCTION__,__LINE__,"(marker) -- Enqueue result: {$result}");
  }/*}}}*/

  function seek_by_pathfragment_929471d53c7813f310ddd6f92563eae0(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    // http://www.congress.gov.ph/download/ra_12/RA09332.pdf
    $result = $this->write_to_ocr_queue($urlmodel);
    $ra_parser = new RepublicActParseUtility(); 
    $ra_parser->generate_descriptive_markup($parser, $pagecontent, $urlmodel);
    $ra_parser = NULL;
    $this->syslog(__FUNCTION__,__LINE__,"(marker) -- Enqueue result: {$result}");
  }/*}}}*/

  function seek_by_pathfragment_c3e10e96490c7d13052ea4f742707931(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    // http://www.congress.gov.ph/download/journals_16/J9-1RS.pdf and similar path tail.
    $result = $this->write_to_ocr_queue($urlmodel);
    $ra_parser = new RepublicActParseUtility(); 
    $ra_parser->generate_descriptive_markup($parser, $pagecontent, $urlmodel);
    $ra_parser = NULL;
    $this->syslog(__FUNCTION__,__LINE__,"(marker) -- Enqueue result: {$result}");
  }/*}}}*/


  /** Automatically matched parsers **/

  function seek_by_pathfragment_6e242fdc8fb6d6f9eacd5ac9869f3015(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    $this->republic_act_pdf_intercept($parser,$pagecontent,$urlmodel);
  }/*}}}*/

  function seek_by_pathfragment_d89f6d777c7c648792580db32d8867b1(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    $this->republic_act_pdf_intercept($parser,$pagecontent,$urlmodel);
  }/*}}}*/

  function seek_by_pathfragment_38ba6300f99b9aff7f316d454326f418(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    $this->republic_act_pdf_intercept($parser,$pagecontent,$urlmodel);
  }/*}}}*/

  function committee_information_page(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    // Specific Committee Listing entries
    // http://www.congress.gov.ph/committees/search.php/?id=0501 
    $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked for " . $urlmodel->get_url() );
    $document_parser = new CongressionalCommitteeInfoParseUtility();
    $document_parser->update_existing = $parser->update_existing;
    $document_parser->committee_information_page(& $parser, & $pagecontent, & $urlmodel);
    $document_parser = NULL;
    unset($document_parser);

    $parser->json_reply = array(
      'retainoriginal' => TRUE,
      'subcontent' => $pagecontent,
    );

    $pagecontent = NULL;

  }/*}}}*/

  function seek_by_pathfragment_e4d1bcf92a20bcf057f690e18c95d159(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    $this->committee_information_page($parser,$pagecontent,$urlmodel);
  }/*}}}*/

  function seek_postparse_bypathonly_c129bfbe3255b997d22b73aa77edfda7(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    $this->committee_information_page($parser,$pagecontent,$urlmodel);
  }/*}}}*/

  function seek_postparse_bypath_plus_queryvars_80e9cbc1eea29170c03c8e573d231b2e(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    $this->committee_information_page($parser,$pagecontent,$urlmodel);
  }/*}}}*/

  function congress_committee_listing(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    // http://www.congress.gov.ph/committees/search.php?congress=15&id=A505
    $p = new CongressCommitteeListParseUtility();
    $p->congress_committee_listing($parser,$pagecontent,$urlmodel);
    $p = NULL;
    unset($p);

  }/*}}}*/

  function seek_postparse_bypath_plus_queryvars_e5bf63efae0afe2ae1e652dd2dd56948(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    // http://www.congress.gov.ph/committees/search.php?id=0501&pg={bills,roster}
    $target_page = $urlmodel->get_query_element('pg');
    $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked for {$target_page} " . $urlmodel->get_url() );
    switch ( $target_page ) {
      case 'bills'   : return $this->committee_information_page($parser,$pagecontent,$urlmodel); break;
      case 'roster'  : return $this->committee_information_page($parser,$pagecontent,$urlmodel); break;
      case 'related' : return $this->committee_information_page($parser,$pagecontent,$urlmodel); break;
      default:
        $this->common_unhandled_page_parser($parser,$pagecontent,$urlmodel);
        break;
    }

    $this->common_unhandled_page_parser($parser,$pagecontent,$urlmodel);
  }/*}}}*/

  function seek_postparse_bypath_plus_queryvars_a122f17e725e662e98017660664a009b(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    // House bill textual history parser
    $this->syslog( __FUNCTION__, __LINE__, "(marker) History text parser for " . $urlmodel->get_url() );

    $history = new CongressionalDocumentHistoryParseUtility();

    $history->standard_parse($urlmodel);

    $document = $history->get_containers('children[tagname*=body]',0);

    $contents = nonempty_array_element(array_values($document),0);
    $contents = nonempty_array_element($contents,'text');

    $history->parse_document_history($urlmodel, $contents);

    $parser->json_reply['subcontent'] = "History " . $this->filter_session('referrer');
    $pagecontent = NULL;

  }/*}}}*/

  function seek_postparse_bypath_plus_queryvars_83c006854a63d767ee0808c0de0e97d7(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    // seek_postparse_bypath_plus_queryvars_83c006854a63d767ee0808c0de0e97d7
    // http://www.congress.gov.ph/committees/search.php 
    $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked for " . $urlmodel->get_url() );
    $this->seek_postparse_bypath_plus_queryvars_e5bf63efae0afe2ae1e652dd2dd56948($parser,$pagecontent,$urlmodel);
  }/*}}}*/

  function seek_by_pathfragment_8d5125b7dba2f2b263f1bd6e4917b247(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    // seek_postparse_bypath_plus_queryvars_83c006854a63d767ee0808c0de0e97d7
    // http://www.congress.gov.ph/committees/
    $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked for " . $urlmodel->get_url() );
    $this->congress_committee_listing($parser,$pagecontent,$urlmodel);
  }/*}}}*/

  function seek_postparse_9f222d54cda33a330ffc7cd18e7ce27f(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    // seek_postparse_bypath_plus_queryvars_83c006854a63d767ee0808c0de0e97d7
    // http://www.congress.gov.ph/committees/search.php 
    $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked for " . $urlmodel->get_url() );
    $this->congress_committee_listing($parser,$pagecontent,$urlmodel);
  }/*}}}*/

  function seek_postparse_bypathonly_0808b3565dcaac3a9ba45c32863a4cb5(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    // HOME PAGE OVERRIDDEN BY NODE GRAPH
    // http://www.congress.gov.ph
    $this->generate_home_page($parser,$pagecontent,$urlmodel);
  }/*}}}*/
           
  function representative_bio_parser(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/

    // http://www.congress.gov.ph/members 
    $document_parser = new CongressMemberBioParseUtility();
    $document_parser->update_existing = $parser->update_existing;
    $document_parser->representative_bio_parser($parser,$pagecontent,$urlmodel);
    $document_parser = NULL;

    $parser->json_reply = array(
      'subcontent' => $pagecontent,
    );

    $pagecontent = NULL;

    unset($document_parser);

  }/*}}}*/

  function seek_postparse_bypath_plus_queryvars_86e6e638ef96a70467d08dc75ea56ef2(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    // 16th Congress: http://www.congress.gov.ph/members/search.php?id=abaya-f&pg=auth
    $target_page = $urlmodel->get_query_element('pg');
    $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked for {$target_page} " . $urlmodel->get_url() );
    switch ( $target_page ) {
      case 'auth'    : return $this->representative_bio_parser($parser,$pagecontent,$urlmodel); break;
      case 'coauth'  : return $this->representative_bio_parser($parser,$pagecontent,$urlmodel); break;
      case 'commem'  : return $this->representative_bio_parser($parser,$pagecontent,$urlmodel); break;
      case 'related' : return $this->representative_bio_parser($parser,$pagecontent,$urlmodel); break;
      default:
        $this->common_unhandled_page_parser($parser,$pagecontent,$urlmodel);
        break;
    }
  }/*}}}*/

  function seek_postparse_bypathonly_2e56f3e00b2b7764f027fe14c4910080(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked for " . $urlmodel->get_url() );
    $this->representative_bio_parser($parser,$pagecontent,$urlmodel);
  }/*}}}*/

  function seek_postparse_bypath_plus_queryvars_dfdf905bf71fd81a3044e7ca8cb59fff(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked for " . $urlmodel->get_url() );
    $this->representative_bio_parser($parser,$pagecontent,$urlmodel);
  }/*}}}*/

  function seek_postparse_15ce34758cb702cd96e0ddd3c34ee695(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked for " . $urlmodel->get_url() );
    $this->representative_bio_parser($parser,$pagecontent,$urlmodel);
  }/*}}}*/


  function seek_congress_memberlist(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/

    // http://www.congress.gov.ph/members 
    $document_parser = new CongressMemberListParseUtility();
    $document_parser->seek_congress_memberlist($parser,$pagecontent,$urlmodel);
    $document_parser = NULL;
    unset($document_parser);

  }/*}}}*/

  function seek_postparse_2e56f3e00b2b7764f027fe14c4910080(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    return $this->seek_congress_memberlist($parser,$pagecontent,$urlmodel);
  }/*}}}*/

  function seek_postparse_bypath_78a9ec5b5e869117bb2802b76bcd263e(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    return $this->seek_congress_memberlist($parser,$pagecontent,$urlmodel);
  }/*}}}*/

  function seek_by_pathfragment_f2792e9d2ac91d20240ce308f106ecea(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    $this->syslog( __FUNCTION__, __LINE__, "Invoked for " . $urlmodel->get_url() );
    $this->common_unhandled_page_parser($parser,$pagecontent,$urlmodel);

    $pagecontent = <<<EOH
<h1>Clearing cached links</h1>
<script type="text/javascript">
jQuery(document).ready(function(){
  jQuery('ul[class*=link-cluster]').each(function(){
    jQuery(this).find('a[class*=legiscope-remote]').each(function(){
      jQuery(this).removeClass('cached');
    });
  });
  initialize_linkset_clickevents(jQuery('ul[class*=link-cluster]'),'li');
});
</script>
EOH;
  }/*}}}*/

  function seek_postparse_bypath_356caa1dcd2d3a76fcc6debce13393ff(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    // Republic Act
    $this->seek_postparse_ra_hb($parser, $pagecontent, $urlmodel);
  }/*}}}*/

  function seek_by_pathfragment_8a807a96bdae8c210fd561e703c9c4b1(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    $this->seek_postparse_ra_hb($parser,$pagecontent,$urlmodel);
  }/*}}}*/

  function seek_by_pathfragment_5f248d0ca858cddb95eb58427397daa8(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    $this->common_unhandled_page_parser($parser,$pagecontent,$urlmodel);
    $pagecontent = str_replace('[BR]','<br/>', $pagecontent);
  }/*}}}*/


  /** Callbacks and utility methods **/

  function find_incident_edges_partitioner(& $urlmodel, & $child_link) {/*{{{*/
    $debug_method = FALSE;
    // Accept the bearer page URL in $urlmodel, and return an array
    // array( 
    //   'congress' => (int)[Congress #],
    //   'session'  => (string)[Session 1R|2R|3R|1S|2S|3S, etc],
    //   'q'        => (int)[Document series number]
    // )
    // or, if the document is not linked to a session,
    // array( 
    //   'congress' => (int)[Congress #],
    //   'session'  => (string)'ALLSESSIONS',
    //   'q'        => (int)[Document series number]
    // )
    $bearer = $urlmodel->get_url();
    $bearer_url_hash = $urlmodel->get_urlhash();

    $matches = array(
      '@(http://www.congress.gov.ph/download/index.php\?d=journals(&page=([0-9]*))*(&congress=([0-9]*))*)@i',
      '@(http://www.congress.gov.ph/download/journals_([0-9]*)/([^.]*))@i'
    );
    $targets = array(
      'journal_catalog',
      'journal_entries',
    );
    $partitioner_regexes = array(
      'match' => $matches,
      'subst' => $targets
    );
    $bearer_target = preg_replace(
      $partitioner_regexes['match'],
      $partitioner_regexes['subst'],
      $bearer
    );
    $lookup = array_combine(
      $targets,
      $matches
    );
    switch( $bearer_target ) {
      case 'journal_catalog':
        $match = array();
        if ( 1 == preg_match($lookup['journal_entries'], $child_link['url'], $match) ) {
          if ( $debug_method ) {
            $this->syslog(__FUNCTION__,__LINE__,"(marker) Mapping URL {$bearer}");
          }
          $urltext =  ltrim(str_replace('_','.',ltrim(array_element($match,3),'_')),'-0Jj');
          $map = array( 
            'congress' => intval(array_element($match,2)), 
            'session'  => NULL, 
            'q'        => $urltext,
          );
          $child_link['urltext'] = $urltext;
          if ( $debug_method ) {
            $this->recursive_dump($map,"(marker) - -- -");
          }
          return $map;
        } else {
          $this->syslog(__FUNCTION__,__LINE__,"(marker) Failed to map {$bearer} <- " . $child_link[''] );
        }
        break;
      default:
        $this->syslog(__FUNCTION__,__LINE__,"(marker) No map for {$bearer_target} <- {$bearer}");
        break;
    }
    return FALSE;
  }/*}}}*/
 
  function member_uuid_handler(array & $json_reply, UrlModel & $url, $member_uuid) {/*{{{*/
    $member = new RepresentativeDossierModel();
    $member->fetch( $member_uuid, 'member_uuid');
    if ( $member->in_database() ) {
      $image_content_type = $url->get_content_type();
      $image_content = $url->get_pagecontent();
      $image_content = base64_encode($image_content);
      $member_avatar_base64 = "data:{$image_content_type};base64,{$image_content}";
      $json_reply['altmarkup'] = $member_avatar_base64;
      $member->set_avatar_image($member_avatar_base64)->stow();
      $this->syslog(__FUNCTION__,__LINE__, "Sending member {$member_uuid} avatar: {$json_reply['altmarkup']}");
    }
  }/*}}}*/

  function must_custom_parse(UrlModel & $url) {/*{{{*/
    return ( 
      // PDF links intercepted by the parser class
      1 == preg_match('@http://www.congress.gov.ph/download/(.*)/(.*).pdf@i', $url->get_url()) ||
      // Normal markup pages on congress.gov.ph
      1 == preg_match('@http://www.congress.gov.ph/download/(index.php)*\?d=billstext@i', $url->get_url()) ||
      1 == preg_match('@http://www.congress.gov.ph/members(([/]*)(.*))*@i', $url->get_url()) ||
      1 == preg_match('@http://www.congress.gov.ph/download/index.php\?d=journals(.*)@i', $url->get_url()) ||
      1 == preg_match('@http://www.congress.gov.ph/members/search.php\?(.*)@i', $url->get_url())  || 
      1 == preg_match('@http://www.congress.gov.ph/legis/search/hist_show.php\?@i', $url->get_url())
    );
  }/*}}}*/


}
