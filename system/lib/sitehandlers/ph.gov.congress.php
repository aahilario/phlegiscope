<?php

class CongressGovPh extends SeekAction {
  
  private $container_buffer = NULL;

  function __construct() {
    $this->syslog( __FUNCTION__, '-', 'Using site-specific container class' );
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
    $pagecontent = str_replace('[BR]','<br/>',join('',$common->get_filtered_doc()));
    $parser->json_reply = array('retainoriginal' => TRUE);
  }/*}}}*/

  function representative_bio_parser(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/

    // http://www.congress.gov.ph/members 
    $document_parser = new CongressMemberBioParseUtility();
    $document_parser->update_existing = $parser->update_existing;
    $document_parser->representative_bio_parser($parser,$pagecontent,$urlmodel);
    $document_parser = NULL;
    unset($document_parser);

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

  function seek_congress_memberlist(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/

    // http://www.congress.gov.ph/members 
    $document_parser = new CongressMemberListParseUtility();
    $document_parser->seek_congress_memberlist($parser,$pagecontent,$urlmodel);
    $document_parser = NULL;
    unset($document_parser);

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

  function seek_postparse_ra(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/

    $document_parser = new CongressRepublicActCatalogParseUtility();

    $document_parser->seek_postparse_ra($parser,$pagecontent,$urlmodel);

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
      'children[attrs:ACTION*=index.php\?d=ra$]'
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
      $faux_url['query'] = "d=ra";
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
    // Coerce the parent URL to index.php?d=ra
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
    )),"Extracted content");

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

    $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked for " . $urlmodel->get_url() );
    // House Bills
    // Partition large list into multiple processable chunks, transmit
    // these in bulk to the client browser as JSON fragments.

    $debug_method = FALSE;

    $own_url      = $urlmodel->get_url();

    if ( $debug_method ) {/*{{{*/
      $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked for " . $urlmodel->get_url() . ". Length: " . $urlmodel->get_content_length() . ". Memory load: " . memory_get_usage(TRUE) );
      $this->recursive_dump($_POST,"(marker) --");
    }/*}}}*/

    $urlmodel->ensure_custom_parse();

    $parser->reset(TRUE)->structure_reinit();

    $pagecontent = NULL;

    // Perform actual parse operation

    $hb_listparser = new CongressHbListParseUtility();

    $congress_tag = $hb_listparser->seek_postparse_d_billstext_preprocess($parser,$pagecontent,$urlmodel);

    $urlmodel->set_content(NULL)->set_id(NULL);

    $debug_method = FALSE;
    $house_bills = new HouseBillDocumentModel();
    $cached = NULL;
    if ( 'fetch' == $this->filter_post('catalog') ) {
      $house_bills->cache_parsed_housebill_records($hb_listparser->container_buffer['parsed_bills'], $congress_tag, $parser->from_network);
      $parser->json_reply['catalog'] = nonempty_array_element($hb_listparser->container_buffer,'parsed_bills');
      if ( $debug_method ) $this->recursive_dump(nonempty_array_element($hb_listparser->container_buffer,'parsed_bills'),"(marker) --- A --");
    } else {
      $house_bills->cache_parsed_housebill_records($hb_listparser->container_buffer['parsed_bills'], $congress_tag, $parser->from_network);
      $cached = addslashes($this->safe_json_encode(nonempty_array_element($hb_listparser->container_buffer,'parsed_bills'))); 
      if ( $debug_method ) $this->recursive_dump(nonempty_array_element($hb_listparser->container_buffer,'parsed_bills'),"(marker) --- B --");
    }
    $debug_method = FALSE;

    // Store records not yet recorded in HouseBillDocumentModel backing store.

    // Generate POST links

    $actions        = nonempty_array_element($hb_listparser->container_buffer,'form');
    $action         = nonempty_array_element($actions,'action');
    $trigger_pull   = NULL;
    $emit_frame     = is_null($congress_tag);
    $generated_link = array();

    if ( $debug_method ) $this->recursive_dump(nonempty_array_element($actions,'form_controls'),"(marker) -- - --");

    foreach ( nonempty_array_element($actions,'form_controls') as $k => $v ) {/*{{{*/
      foreach ( $v as $val ) {
        $link_class_selector = array('fauxpost');
        $link_class_selector = join(' ', $link_class_selector);
        $control_set = array(
          '_' => 'SKIPGET',
          $k => $val
        );
        extract( UrlModel::create_metalink("{$val}", $action, $control_set, $link_class_selector, TRUE) );
        $generated_link[$hash] = $metalink;
        if ( is_null($congress_tag) ) $congress_tag = $val;
        if ( $congress_tag == $val ) $trigger_pull = "switch-{$hash}";
      }
    }/*}}}*/

    krsort($generated_link);
    $generated_link = join('&nbsp;', $generated_link);
    $entries        = array_element($hb_listparser->container_buffer,'entries');
    $bills          = NULL;
    $system_stats   = NULL;
    $parse_offset   = intval($this->filter_post('parse_offset',0));
    $parse_limit    = intval($this->filter_post('parse_limit',20));

    $parser->json_reply['parse_offset'] = $parse_offset + $parse_limit;

    $emit_frame     = $emit_frame || ($parse_offset == 0);

    // FIXME: Use per-method session store
    $last_fetch     = $this->filter_post('last_fetch', $parser->from_network ? time() : 'null');

    if ( $debug_method /*|| $emit_frame*/ ) $this->syslog(__FUNCTION__,__LINE__,"(marker) -- Offset: {$parse_offset}. " . ($emit_frame ? "Emitting" : "Not emitting") . " frame");

    // Send markup container and script 
    if ( $emit_frame ) $pagecontent = <<<EOH

<div class="float-left half-container" id="system-stats">
  <span>House Bills Page Loader (Congress {$congress_tag}): {$entries} {$generated_link}</span>
  <input class="reset-cached-links" type="button" value="Clear"> 
  <input class="reset-cached-links" type="button" value="Reset"> 
  {$system_stats}
</div>

<div class="float-left half-container alternate-original" id="parsed-content">
{$bills}
</div>

<script type="text/javascript">

var parse_offset = 0;
var parse_limit = 11;
var cached = jQuery.parseJSON("{$cached}");

function emit_bill_entries(entries) {
  for ( var p in entries ) {
    var entry = entries[p];
    var links = entry && entry.links ? entry.links : null;
    var representative = entry && entry.representative ? entry.representative : null;
    var committee = entry && entry.committee ? entry.committee : null;
    jQuery('#parsed-content').append(
      jQuery(document.createElement('DIV'))
        .addClass('bill-container')
        .addClass('clear-both')
        .attr('id',entry.sn)
        .append(
          jQuery(document.createElement('HR'))
        )
        .append(
          jQuery(document.createElement('SPAN'))
            .addClass('republic-act-heading')
            .addClass('clear-both')
            .append(links && links.filed
              ? jQuery(document.createElement('A'))
                .attr('href',links.filed)
                .addClass('legiscope-remote')
                .html(entry.sn)
              : jQuery(document.createElement('B')).html(entry.sn)
            )
        )
        .append(
          jQuery(document.createElement('SPAN'))
            .addClass('republic-act-desc')
            .addClass('clear-both')
            .html(entry.description)
        )
        .append(
          jQuery(document.createElement('SPAN'))
            .addClass('republic-act-meta')
            .addClass('clear-both')
            .append(jQuery(document.createElement('B')).html('Status: '))
            .append(
              entry.meta && entry.meta.status
              ? entry.meta.status.original 
                ? entry.meta.status.original
                : entry.meta.status
              : ''
            )
        )
        .append(committee
          ? jQuery(document.createElement('SPAN'))
            .addClass('republic-act-meta')
            .addClass('clear-both')
            .append(jQuery(document.createElement('B')).html('Committee: '))
            .append(jQuery(document.createElement('A'))
              .attr('href',committee.url)
              .addClass('legiscope-remote')
              .html(committee.committee_name)
            )
          : entry.meta 
              ? jQuery(entry.meta).prop('main-committee') 
              : ''
        )
        .append(
          jQuery(document.createElement('SPAN'))
            .addClass('republic-act-meta')
            .addClass('clear-both')
            .append(jQuery(document.createElement('B')).html('Principal Author: '))
            .attr('title',(representative && representative.url) ? '' : 'Representative not recorded in database.')
            .append( (representative && representative.url)
              ? jQuery(document.createElement('A'))
                .attr('href',representative.url)
                .addClass('legiscope-remote')
                .html(representative.fullname)
              : ( entry.meta
                  ? jQuery(entry.meta).prop('principal-author')
                  : ''
                )
            )
        )
      );
  }
}

function pull() {
  var active = jQuery('#{$trigger_pull}').attr('id').replace(/^switch-/,'content-');
  var linkurl = jQuery('#{$trigger_pull}').attr('href');
  if ( !jQuery('#spider').prop('checked') ) {
    emit_bill_entries(cached);
    return; 
  }
  jQuery.ajax({
    type     : 'POST',
    url      : '/seek/',
    data     : { url : linkurl, catalog : 'fetch', last_fetch : {$last_fetch}, parse_offset : parse_offset, parse_limit : parse_limit, update : jQuery('#update').prop('checked'), proxy : jQuery('#proxy').prop('checked'), modifier : jQuery('#seek').prop('checked'), fr: true, metalink : jQuery('#'+active).html() },
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
      if ( data && data.timedelta ) replace_contentof('time-delta', data.timedelta);
      if ( data && data.state ) {
        if ( 0 == parseInt(data.state) ) {
          jQuery('#spider').prop('checked',null);
        }  
      }
      parse_offset = ( data && data.parse_offset ) ? data.parse_offset : 0;
      if ( data && data.catalog ) {
        emit_bill_entries(data.catalog);
      }
      jQuery('#seek').prop('checked',null);
      if ( jQuery('#spider').prop('checked') ) {
        setTimeout((function(){pull();}),500);
      }
    })
  });
}

jQuery(document).ready(function(){
  pull();
});
</script>

EOH;

    ///////////////////////////////////////////////////////////////////////////////////////////////////

  }/*}}}*/

  /** Automatically matched parsers **/

  function seek_by_pathfragment_6e242fdc8fb6d6f9eacd5ac9869f3015(& $parser, & $pagecontent, & $urlmodel) {
		$this->republic_act_pdf_intercept($parser,$pagecontent,$urlmodel);
	}

  function seek_by_pathfragment_d89f6d777c7c648792580db32d8867b1(& $parser, & $pagecontent, & $urlmodel) {
		$this->republic_act_pdf_intercept($parser,$pagecontent,$urlmodel);
	}

   function seek_by_pathfragment_38ba6300f99b9aff7f316d454326f418(& $parser, & $pagecontent, & $urlmodel) {
		$this->republic_act_pdf_intercept($parser,$pagecontent,$urlmodel);
	}

  

  function seek_by_pathfragment_e4d1bcf92a20bcf057f690e18c95d159(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked for " . $urlmodel->get_url() );
    $document_parser = new CongressionalCommitteeInfoParseUtility();
    $document_parser->update_existing = $parser->update_existing;
    $document_parser->committee_information_page(& $parser, & $pagecontent, & $urlmodel);
    $document_parser = NULL;
    unset($document_parser);
  }/*}}}*/

  function seek_postparse_bypath_9f222d54cda33a330ffc7cd18e7ce27f(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    // http://www.congress.gov.ph/committees/search.php 
    $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked for " . $urlmodel->get_url() );
    $this->congress_committee_listing($parser,$pagecontent,$urlmodel);
  }/*}}}*/

  function seek_postparse_9e8648ad99163238295d15cfa534be86(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    // http://www.congress.gov.ph/committees/search.php 
    $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked for " . $urlmodel->get_url() );
    $this->congress_committee_listing($parser,$pagecontent,$urlmodel);
  }/*}}}*/

  function seek_by_pathfragment_b536fc060d348f720ee206f9d3131a5c(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    // http://www.congress.gov.ph/committees/search.php 
    $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked for " . $urlmodel->get_url() );
    $this->congress_committee_listing($parser,$pagecontent,$urlmodel);
  }/*}}}*/

  function seek_postparse_bypathonly_0808b3565dcaac3a9ba45c32863a4cb5(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    // http://www.congress.gov.ph
    $this->common_unhandled_page_parser($parser,$pagecontent,$urlmodel);
  }/*}}}*/
           
  function seek_postparse_bypathonly_2e56f3e00b2b7764f027fe14c4910080(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked for " . $urlmodel->get_url() );
    $this->representative_bio_parser($parser,$pagecontent,$urlmodel);
  }/*}}}*/

  function seek_postparse_bypath_78a9ec5b5e869117bb2802b76bcd263e(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    $this->seek_congress_memberlist($parser,$pagecontent,$urlmodel);
  }/*}}}*/

  function seek_postparse_2e56f3e00b2b7764f027fe14c4910080(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    $this->seek_congress_memberlist($parser,$pagecontent,$urlmodel);
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

  function congress_committee_listing(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    // http://www.congress.gov.ph/committees/search.php?congress=15&id=A505
    $p = new CongressCommitteeListParseUtility();
    $p->congress_committee_listing($parser,$pagecontent,$urlmodel);
    $p = NULL;
    unset($p);

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
      1 == preg_match('@http://www.congress.gov.ph/download/index.php\?d=billstext@i', $url->get_url()) ||
      1 == preg_match('@http://www.congress.gov.ph/members(([/]*)(.*))*@i', $url->get_url()) ||
      1 == preg_match('@http://www.congress.gov.ph/download/index.php\?d=journals(.*)@i', $url->get_url()) ||
      1 == preg_match('@http://www.congress.gov.ph/members/search.php\?(.*)@i', $url->get_url())  || 
      1 == preg_match('@http://www.congress.gov.ph/legis/search/hist_show.php\?@i', $url->get_url())
    );
  }/*}}}*/


}
