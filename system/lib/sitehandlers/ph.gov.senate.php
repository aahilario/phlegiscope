<?php

class SenateGovPh extends SeekAction {
  
  var $method_cache_filename = NULL;

  function __construct() {
    $this->syslog( __FUNCTION__, __LINE__, 'Using site-specific container class' );
    parent::__construct();
  }

  function common_unhandled_page_parser(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked for " . $urlmodel->get_url() );
    $common = new SenateCommonParseUtility();
    $common->
      set_parent_url($urlmodel->get_url())->
      parse_html(
        $urlmodel->get_pagecontent(),
        $urlmodel->get_response_header()
      );
    $pagecontent = preg_replace(
      array(
        "@\&\#10;@",
        "@\n@",
        "@\[BR\]@",
      ),
      array(
        "",
        "",
        "<br/>",
      ),
      join('',$common->get_filtered_doc())
    );
    $parser->json_reply = array(
      'subcontent' => $pagecontent,
      'retainoriginal' => TRUE,
      'state' => 'noparser'
    );
    // Place content in subpanel
    if (0) $parser->json_reply = array(
      'subcontent' => $pagecontent,
      'retainoriginal' => TRUE,
      'state' => 'noparser'
    );

    // $this->recursive_dump($common->get_containers(),'(warning)');
  }/*}}}*/

  /** PDF OCR handler **/

  function seek_by_pathfragment_f26ae3f52382caead303ecf6885875c8(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    // http://www.senate.gov.ph/lisdata/1712514353!.pdf
    $result = $this->write_to_ocr_queue($urlmodel);
    $this->syslog(__FUNCTION__,__LINE__,"(marker) -- Enqueue result: {$result}");
  }/*}}}*/


  /** Committee Information **/

  function seek_postparse_31721d691dbebc1cee643eb73d5221e4(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    // http://www.senate.gov.ph/notice_ctte.asp
    $calparser = new SenateCommitteeNoticeParser();
    $this->common_unhandled_page_parser($parser,$pagecontent,$urlmodel);
  }/*}}}*/

  function seek_by_pathfragment_f7038930be545deccdf1472b5846c937(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    $this->republic_act_pdf_intercept($parser,$pagecontent,$urlmodel);
  }/*}}}*/

  function seek_postparse_bypathonly_f7038930be545deccdf1472b5846c937(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    $this->republic_act_pdf_intercept($parser,$pagecontent,$urlmodel);
  }/*}}}*/


  /** Journal Entries **/

  function canonical_journal_page_parser(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    $journal_parser = new SenateJournalParseUtility();
    $journal_parser->canonical_journal_page_parser($parser, $pagecontent, $urlmodel);
  }/*}}}*/

  function seek_postparse_bypath_plus_queryvars_eeb9d008cce32b17f952df3ef645a951(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    $journal_parser = new SenateJournalParseUtility();
    $journal_parser->canonical_journal_page_parser($parser, $pagecontent, $urlmodel);
  }/*}}}*/

  function seek_postparse_bypath_0930d15b5c0048e5e03af7b02f24769d(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    $journal_parser = new SenateJournalParseUtility();
    $journal_parser->canonical_journal_page_parser($parser, $pagecontent, $urlmodel);
  }/*}}}*/

  function seek_postparse_386038c1a686fd0e6aeee9a105b9580d(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    $this->seek_postparse_congress_type_journal($parser,$pagecontent,$urlmodel);
  }/*}}}*/

  function non_sn_parser($caller, $batch_regex, & $parser, & $pagecontent, & $urlmodel ) {/*{{{*/

    // Parser invoked for catalog of documents that do not have a 
    // serial number of the form 'DD-NNNNNN'
    $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked for " . $urlmodel->get_url() );

    $method_suffix   = '@seek_postparse_congress_type_(.*)$@i';
    $senatedoc       = explode('_',preg_replace($method_suffix,'$1', $caller));
    array_walk($senatedoc, create_function('& $a, $k', '$a = ucfirst(strtolower($a));'));
    $senatedoc       = join('', $senatedoc);
    $document_parser = "Senate{$senatedoc}ParseUtility";

    $this->method_cache_emit($parser);

    // FIXME: This code is similar to HouseJournalDocumentModel::fetch_session_item_source, consider using that method instead.
    $child_collection = array();
    $urls = new UrlModel();
    $urls->where(array('AND' => array(
      'url' => "REGEXP '({$batch_regex})'"
    )))->recordfetch_setup();
    $url = array();
    while($urls->recordfetch($url)) {
      $session  = UrlModel::query_element('session',$url['url']);
      $congress = UrlModel::query_element('congress',$url['url']);
      $q        = UrlModel::query_element('q',$url['url']);
      $child_collection[$congress][$session][intval($q)] = array( 
        'hash' => $url['urlhash'],
        'url'  => $url['url'],
        'text' => $url['urltext'],
      );
    }

    // $this->recursive_dump($child_collection,"(marker) - - - CC - -");

    // Iteratively generate sets of columns

    $document_parser = new $document_parser();

    // Prevent parsing this particular URL in the parent
    $urlmodel->ensure_custom_parse();

    $document_parser->
      reset()->
      set_parent_url($urlmodel->get_url())->
      parse_html(
        $urlmodel->get_pagecontent(),
        $urlmodel->get_response_header()
      );

    $inserted_content = join('',$document_parser->get_filtered_doc());

    $document_parser->cluster_urldefs = $document_parser->generate_linkset($urlmodel->get_url(),'cluster_urls');

    $session_select = $document_parser->extract_house_session_select($urlmodel, FALSE);
    // $session_select[k]['active'] == 1 indicates the currently selected
    // option on the page just retrieved and parsed.

    $pagecontent = $document_parser->generate_congress_session_item_markup(
      $urlmodel,
      $child_collection,
      $session_select
    );

    // Insert the stripped, unparsed document
    $this->syslog( __FUNCTION__, __LINE__, "(marker) Inserting document for '{$urlmodel}'");
    $pagecontent .= <<<EOH
<div class="hidden" id="inserted-content">
{$inserted_content}
</div>

<script type="text/javascript">
jQuery(document).ready(function(){
  jQuery('div[id=subcontent]').children().remove();
  jQuery('div[id=subcontent]')
    .append(jQuery('div[id=inserted-content]').children().detach());
  jQuery('div[id=inserted-content]').remove();
});
</script>

EOH;

    $this->terminal_method_cache_stow($parser, $pagecontent);

    $parser->json_reply['retainoriginal'] = TRUE;

  } /*}}}*/

  function seek_postparse_congress_type_journal(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/

    $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked for " . $urlmodel->get_url() );

    $congress_tag = $urlmodel->get_query_element('congress');

    return $this->non_sn_parser(
      __FUNCTION__, 
      //'http://www.senate.gov.ph/lis/journal.aspx\\\\?congress=([^&]*)&session=([^&]*)&q=([0-9]*)',
      'http://www.senate.gov.ph/lis/journal.aspx\\\\?congress='.$congress_tag.'&session=([^&]*)&q=([0-9]*)',
      $parser, $pagecontent, $urlmodel 
    );

  }/*}}}*/


  /** Committees **/

  function seek_postparse_7150c562d8623591da65174bd4b85eea(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    // http://www.senate.gov.ph/committee/list.asp ('List of Committees')
    $committee_parser  = new SenateCommitteeListParseUtility();
    $committee_parser->parse_committee_list($parser, $pagecontent, $urlmodel);
  }/*}}}*/

  function seek_postparse_bypath_01826c0f1e0270f7d72cb025cdcdb2fc(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    // http://www.senate.gov.ph/committee/duties.asp
    $committee_parser = new SenateCommitteeListParseUtility();
    $committee_parser->debug_tags = FALSE;
    $committee_parser->parse_committee_duties($parser, $pagecontent, $urlmodel);
  }/*}}}*/


  /** Senators **/

  function seek_postparse_4b9020442160ba8dad72e46df9b19add(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    $this->common_unhandled_page_parser($parser,$pagecontent,$urlmodel);
  }/*}}}*/

  function seek_by_pathfragment_7d6ac87a82d8d77452efa631cb69c1bf(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    // http://www.senate.gov.ph/senators/sen_bio/*
    $senator = new SenatorBioParseUtility();
    $senator->parse_senators_sen_bio($parser, $pagecontent, $urlmodel);
  }/*}}}*/

  function seek_postparse_bypathonly_582a1744b18910b951a5855e3479b6f2(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    // http://www.senate.gov.ph/senators/sen_bio/*
    $senator = new SenatorBioParseUtility();
    $senator->parse_senators_sen_bio($parser, $pagecontent, $urlmodel);
  }/*}}}*/

  function seek_by_pathfragment_71e344f075ac0a7b87998cdbf65f7b87(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    // http://www.senate.gov.ph/senators/sen16th.asp
    $senator = new SenatorBioParseUtility();
    $senator->parse_senators_fifteenth_congress($parser, $pagecontent, $urlmodel);
  }/*}}}*/

  function seek_by_pathfragment_9e6f4324e8c7a9a4b1bb539d14d6c9aa(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    // http://www.senate.gov.ph/senators/sen15th.asp
    $senator = new SenatorBioParseUtility();
    $senator->parse_senators_fifteenth_congress($parser, $pagecontent, $urlmodel);
  }/*}}}*/

  function seek_postparse_73c3022a45d05b832a96fb881f20d2c6(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    // http://www.senate.gov.ph/senators/roll.asp
    $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked for " . $urlmodel->get_url() );
    $this->common_unhandled_page_parser($parser,$pagecontent,$urlmodel);
  }/*}}}*/

  function seek_postparse_bypathonly_2e8161d636209e4cd69b7ff638f5c8f4(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    // HOME PAGE OVERRIDDEN BY NODE GRAPH
    // http://www.senate.gov.ph
    $congress = new CongressDocumentModel();
    $this->common_unhandled_page_parser($parser,$pagecontent,$urlmodel);

    $map_url = LEGISCOPE_ADMIN_IMAGES_URLBASE . "/philippines-4c.svg"; 

    $svg_inline = $this->transform_svgimage(SYSTEM_BASE . "/../images/admin/philippines-4c.svg"); 

    // Transform geomap into regular, embeddable SVG
    // Transform geomap into interactive object (clickable)
    // Create geomap animation methods
    // Generate static Legiscope object map, rooted at SenateBillDocumentModel
    // Generate node animation methods
    // Generate interactive LOM (clickable nodes)
    // Generate LOM clickable edges

    $altcontent = str_replace(
      array(
        '{alternate-content}',
        '{initial-load-content}',
      ),
      array(
        $pagecontent,
        str_replace(
          array(
            '{svg_inline}',
            '{scale}'
          ),
          array(
            $svg_inline,
            '2.4' 
          ),
          $this->get_template('map.html','global')
        ),
      ),
      $this->get_template('index.html')
    );
    $pagecontent = <<<EOH
{$altcontent}
EOH;
    $parser->json_reply = array(
      'rootpage' => TRUE
    );
  }/*}}}*/


  /** Republic acts **/

  function seek_postparse_bypathonly_47fcf9913bde652d1ecee59501b11c59(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/

    /** Router **/ 

    $pdf_sys_type = $urlmodel->get_query_element('type');
    $this->syslog( __FUNCTION__, __LINE__, "(marker) ********** Router: {$pdf_sys_type} for " . $urlmodel->get_url() );

    switch ($pdf_sys_type) {
      case 'republic_act' : 
        return $this->leg_sys_republic_act($parser,$pagecontent,$urlmodel);
        break;
      case 'adopted_res' :
        return $this->pdf_sys_adopted_res($parser,$pagecontent,$urlmodel);
        break;
      default:
        return $this->common_unhandled_page_parser($parser,$pagecontent,$urlmodel);
        break;
    }
  }/*}}}*/

  function leg_sys_republic_act(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/

    $common_model = new SenateDocCommonDocumentModel();

    return $this->non_session_linked_document_worker(
      'RepublicActDocumentModel','RepublicActParseUtility','Republic Act',
      $parser,$pagecontent,$urlmodel,
      // string: Unique tail fragment for Republic Act documents 
      // array: where() condition array
      array('sn' => "REGEXP 'RA(.*)'"), // 'ra([ ]*)([0-9]*).pdf',
      // Regex yielding 2 match fields
      'ra([o0 ]*)([0-9]*).pdf',
      // Template URL
      'http://www.senate.gov.ph/republic_acts/ra {full_sn}.pdf',
      // Pager URL fetch regex
      'congress=([0-9]*)\&type=republic_act\&p=([0-9]*)',
      // Pager template URL
      'http://www.senate.gov.ph/lis/pdf_sys.aspx?congress={congress_tag}&type=republic_act&p={page}',
      // Sequential entries
      TRUE
    );
 
  }/*}}}*/

  function pdf_sys_republic_act(& $parser, & $pagecontent, & $urlmodel) { /*{{{*/
    // http://www.senate.gov.ph/lis/pdf_sys.aspx?congress=15&type=republic_act 
    $ra_parser = new RepublicActParseUtility();
    $ra_parser->parse_pdf_sys_republic_act($parser, $pagecontent, $urlmodel);
    $ra_parser = NULL;
    unset($ra_parser);
  }/*}}}*/

  function seek_postparse_edd4db85190acf2176ca125df8fe269a(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked for " . $urlmodel->get_url() );
    $this->common_unhandled_page_parser($parser,$pagecontent,$urlmodel);
  }/*}}}*/


  /** House Bill route handler **/

  function leg_sys_bill(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/

    $listing_mode_change = $this->filter_post('metalink');

    GenericParseUtility::decode_metalink_post($listing_mode_change);

    if ( !is_array($listing_mode_change) ) {
      $this->recursive_dump($_POST,"(marker)  NO MODE CHANGE");
    } else if ( is_null(($dlBillType = array_element($listing_mode_change,'dlBillType')))) { 
      $this->recursive_dump($listing_mode_change,"(marker)  MODE CHANGE ?");
    } else {
      $this->syslog(__FUNCTION__,__LINE__,"(marker)  Switch mode to {$dlBillType}");
      $this->recursive_dump($_SESSION,"(marker) - - - -");
      $_SESSION[__FUNCTION__] = $dlBillType;
      $this->recursive_dump($_SESSION,"(marker) - -+- -");
    }

    $listing_prefix = nonempty_array_element($_SESSION,__FUNCTION__,'SBN');

    // $this->append_filtered_doc = TRUE;

    $this->stateful_child_pager_links = TRUE;

    return $this->non_session_linked_document(
      ($listing_prefix == 'HBN') ? 'leg_sys_housebill' : __FUNCTION__,
      $parser,$pagecontent,$urlmodel,
      // Unique tail fragment for Resolution URLs
      $listing_prefix.'-([0-9]*)',
      // Regex yielding 2 match fields
      'congress=([0-9]*)\&q='.$listing_prefix.'-([0-9]*)',
      // Template URL
      'http://www.senate.gov.ph/lis/bill_res.aspx?congress={congress_tag}&q={full_sn}',
      // Pager URL fetch regex
      'congress=([0-9]*)\&type=bill\&p=([0-9]*)',
      // Pager template URL
      'http://www.senate.gov.ph/lis/leg_sys.aspx?congress={congress_tag}&type=bill&p={page}'
    );

  }/*}}}*/

  function leg_sys_resolution(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/

    $listing_mode_change = $this->filter_post('metalink');

    GenericParseUtility::decode_metalink_post($listing_mode_change);

    if ( !is_array($listing_mode_change) ) {
      $this->recursive_dump($_POST,"(marker)  NO MODE CHANGE");
    } else if ( is_null(($dlBillType = array_element($listing_mode_change,'dlBillType')))) { 
      $this->recursive_dump($listing_mode_change,"(marker)  MODE CHANGE ?");
    } else {
      $this->syslog(__FUNCTION__,__LINE__,"(marker)  Switch mode to {$dlBillType}");
      $this->recursive_dump($_SESSION,"(marker) - - - -");
      $_SESSION[__FUNCTION__] = $dlBillType;
      $this->recursive_dump($_SESSION,"(marker) - -+- -");
    }

    $listing_prefix = 'SRN';

    $listing_prefix = nonempty_array_element($_SESSION,__FUNCTION__, $listing_prefix);

    // $this->append_filtered_doc = FALSE;

    // $this->stateful_child_pager_links = TRUE;

    return $this->non_session_linked_document(
      __FUNCTION__,
      $parser,$pagecontent,$urlmodel,
      // Unique tail fragment for Resolution URLs
      $listing_prefix.'-([0-9]*)',
      // Regex yielding 2 match fields
      'congress=([0-9]*)\&q='.$listing_prefix.'-([0-9]*)',
      // Template URL
      'http://www.senate.gov.ph/lis/bill_res.aspx?congress={congress_tag}&q={full_sn}',
      // Pager URL fetch regex
      'congress=([0-9]*)\&type=resolution\&p=([0-9]*)',
      // Pager template URL
      'http://www.senate.gov.ph/lis/leg_sys.aspx?congress={congress_tag}&type=resolution&p={page}'
    );
 
  }/*}}}*/

  function senate_journal_content_parser(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/

    $this->append_filtered_doc = FALSE;

    $this->session_linked_content_parser(__FUNCTION__, '', $parser, $pagecontent, $urlmodel );

  }/*}}}*/

  function leg_sys_journal(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/

    $this->senate_journal_content_parser($parser,$pagecontent,$urlmodel);

    if ( C('USE_NON_SESSION_LINKED_JOURNAL_HANDLER') ) {/*{{{*/

    $listing_mode_change = $this->filter_post('metalink');

    GenericParseUtility::decode_metalink_post($listing_mode_change);

    if ( !is_array($listing_mode_change) ) {
      $this->recursive_dump($_POST,"(marker)  NO MODE CHANGE");
    } else if ( is_null(($dlBillType = array_element($listing_mode_change,'dlBillType')))) { 
      $this->recursive_dump($listing_mode_change,"(marker)  MODE CHANGE ?");
    } else {
      $this->syslog(__FUNCTION__,__LINE__,"(marker)  Switch mode to {$dlBillType}");
      $this->recursive_dump($_SESSION,"(marker) - - - -");
      $_SESSION[__FUNCTION__] = $dlBillType;
      $this->recursive_dump($_SESSION,"(marker) - -+- -");
    }

    $listing_prefix = nonempty_array_element($_SESSION,__FUNCTION__,'SBN');

    // $this->append_filtered_doc = TRUE;

    // $this->stateful_child_pager_links = TRUE;

    return $this->non_session_linked_document(
      __FUNCTION__,
      $parser,$pagecontent,$urlmodel,
      // Unique tail fragment for Resolution URLs
      '([0-9]*)',
      // Regex yielding 2 match fields
      'congress=([0-9]*)\&q=([0-9]*)',
      // Template URL
      'http://www.senate.gov.ph/lis/journal.aspx?congress={congress_tag}&q={full_sn}',
      // Pager URL fetch regex
      'congress=([0-9]*)\&type=journal\&p=([0-9]*)',
      // Pager template URL
      'http://www.senate.gov.ph/lis/leg_sys.aspx?congress={congress_tag}&type=journal&p={page}'
    );

    }/*}}}*/

  }/*}}}*/

  function leg_sys_committee_rpt(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    $this->seek_postparse_4c2e9f440b8f89e94f6de126ef0e38c4($parser,$pagecontent,$urlmodel);
  }/*}}}*/

  function get_after_post() {/*{{{*/
    return array_key_exists(
      intval(CurlUtility::$last_transfer_info['http_code']),
      array_flip(array(100,302,301,504))
    ) ? FALSE : TRUE;
  }/*}}}*/

  function invalidate_record(& $senate_document, & $urlmodel) {/*{{{*/

    $target_congress = $urlmodel->get_query_element('congress');
    $target_sn       = $urlmodel->get_query_element('q');

    $this->syslog(__FUNCTION__,__LINE__,"(warning) - - - - Unable to parse content for {$target_sn}.{$target_congress} from " . $urlmodel->get_url());

    if ( !empty($target_congress) && !empty($target_sn) && method_exists($senate_document,'set_invalidated') ) {/*{{{*/
      // FIXME: Remove this hack.  Unsafe update of document property
      $data = array(
        'sn' => $target_sn,
        'congress_tag' => $target_congress, 
      );

      $senate_document->fetch($data,'AND');
      $action = 'Invalidating';
      if ( C('SUPPRESS_MALFORMED_DOC_INVALIDATION') ) {
      }
      else if ( !$senate_document->in_database() ) {
        $data['invalidated'] = TRUE;
        $data['create_time'] = time();
        $data['last_fetch'] = time(); 
        $data['url'] = $urlmodel->get_url();
        $invalidated_rec = $senate_document->set_contents_from_array($data)->stow();
        $action = "Created invalidated";
      }
      else {
        $invalidated_rec = $senate_document->
          set_invalidated(TRUE)->
          fields(array('invalidated'))->
          stow();
        $action = "Invalidated";
      }
      $this->syslog(__FUNCTION__,__LINE__,"(warning) - - - - {$action} " . get_class($senate_document) . " #{$invalidated_rec} {$target_sn}.{$target_congress}");
    }/*}}}*/
  }/*}}}*/

  function non_session_linked_document_worker($documents, $document_parser, $senatedoc, & $parser, & $pagecontent, & $urlmodel, $url_fetch_regex, $link_regex, $url_template, $pager_regex, $pager_template_url, $sequential_serial_numbers = FALSE ) {/*{{{*/

    $debug_method = FALSE;

    $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked for " . $urlmodel->get_url() );

    if ( !class_exists($documents) || !class_exists($document_parser) ) {/*{{{*/
      $message = "Missing class {$documents} or/and {$document_parser}";
      $this->syslog(__FUNCTION__, __LINE__, $message);
      $pagecontent = $message;
      return FALSE;
    }/*}}}*/

    $documents       = new $documents();
    $document_parser = new $document_parser();

    $this->syslog(__FUNCTION__,__LINE__,"(marker)  Documents: " . get_class($documents));
    $this->syslog(__FUNCTION__,__LINE__,"(marker)     Parser: " . get_class($document_parser));

    if ( $debug_method ) $documents->dump_accessor_defs_to_syslog();

    $this->method_cache_emit($parser);

    $target_congress = $urlmodel->get_query_element('congress');

    // Prevent parsing this particular URL in the parent class method seek() 
    $urlmodel->ensure_custom_parse();

    $document_parser->
      reset()->
      set_parent_url($urlmodel->get_url())->
      parse_html(
        $urlmodel->get_pagecontent(),
        $urlmodel->get_response_header()
      );

    // Extract URLs from pager
    $this->recursive_dump(($uncached_documents = $document_parser->get_containers(
      'children[tagname*=div][attrs:CLASS*=alight]',0
    )), $debug_method ? '(marker) Content' : '-----');

    // Collect document description fields (serial number, description, title)
    if ( method_exists($document_parser, 'non_session_linked_document_prepare_content') ) { 
      if ( FALSE === $document_parser->non_session_linked_document_prepare_content($uncached_documents) ) {
        $pagecontent = join('',$document_parser->get_filtered_doc());
        return FALSE;
      }
    } else if ( is_array($uncached_documents) ) { 
      array_walk($uncached_documents, create_function(
        '& $a, $k', 
        '$desc = explode("[BR]",trim($a["text"])); $a["title"] = trim($desc[0]); $a["desc"] = trim(array_element($desc,1,"")) ; $a["text"] = UrlModel::query_element("q", array_element($a,"url")); $a["sn_suffix"] = preg_replace("@[^0-9]@i","",array_element($a,"text")); $a["sn"] = array_element($a,"text"); $alt_title = array_element($a,"title"); if ( empty($alt_title) ) $a["title"] = array_element($a,"text"); $a["create_time"] = time();'
      ));
    } else {
      $uncached_documents = array();
    }

    // Reconstruct description data so that document SN suffix becomes the array key
    if ( is_array($uncached_documents) && 0 < count($uncached_documents) ) {
      $uncached_documents = array_filter(array_combine(
        array_map(create_function('$a', 'return intval($a["sn_suffix"]);'), $uncached_documents),
        array_map(create_function('$a', 'unset($a["sn_suffix"]); return array_key_exists("url",$a) ? $a : NULL;'), $uncached_documents)
      ));
      if ( $debug_method ) $this->recursive_dump($uncached_documents,"(marker) -- Uncached");
      krsort($uncached_documents, SORT_NUMERIC);
    }

    // Determine which of the listed records (visible on the current page, and of limited number)
    // are already present in the database of Senate Resolutions/Bills, etc., based on the
    // serial number and Congress tag (15, 14, etc.)

    $text_entries_only = array_map(create_function('$a', 'return $a["text"];'), $uncached_documents); 
    $markup_tabulation = (is_array($text_entries_only) && (count($uncached_documents) == count($text_entries_only)) && (0 < count($text_entries_only)))
      ? array_combine(
          array_keys($uncached_documents),
          $text_entries_only 
        )
      : array();

    unset($text_entries_only);

    if ( $debug_method ) $this->recursive_dump($markup_tabulation,"(marker) -- Blitter");

    $url_checker = new UrlModel();

    // Key-value pairs { SN => URL hash }
    $urls_only = array_map(create_function('$a', 'return UrlModel::get_url_hash($a["url"]);'), $uncached_documents);
    $markup_tabulation_urls = (is_array($urls_only) && (count($uncached_documents) == count($urls_only)) && (0 < count($urls_only))) 
      ? array_combine(
        array_keys($uncached_documents),
        $urls_only
      )
      : array()
      ;

    unset($urls_only);

    if (is_array($markup_tabulation) && (0 < count(array_filter(array_values($markup_tabulation))))) {/*{{{*/
      
      $scanresult = $documents->where(array("AND" => array(
        'congress_tag' => "{$target_congress}",
        'sn' => $markup_tabulation,
      )))->recordfetch_setup();
      // Flip, so that array structure is { SN => sortkey, ... }

      $markup_tabulation = array_flip($markup_tabulation);

      $document = NULL;

      if (0) {/*{{{*/
        // Remove extant elements from the tabulation obtained from markup
        $matches = 0;
        while ( $documents->recordfetch($document) ) {
          if ( $debug_method ) $this->syslog(__FUNCTION__,__LINE__,"(marker) -- - -- Omitting {$document['sn']}");
          $uncached_documents[$markup_tabulation[$document['sn']]] = NULL;
          $matches++;
        }
        if ( $debug_method ) $this->syslog(__FUNCTION__,__LINE__,"(marker) -- Omitted {$matches}");
      }/*}}}*/

      $uncached_documents = array_filter($uncached_documents);

    }/*}}}*/

    // Flip, so that array structure is { URL => sortkey, ... }
    $markup_tabulation_urls = array_flip($markup_tabulation_urls);

    $url_checker->where(array("AND" => array(
      'urlhash' => $markup_tabulation_urls,
    )))->recordfetch_setup();

    $url = NULL;
    // Remove URLs from $markup_tabulation_urls that are already cached in DB
    while ( $url_checker->recordfetch($url) ) {
      $markup_tabulation_urls[UrlModel::get_url_hash($url['url'])] = NULL;
    }
    // Remaining entries are not yet cached in UrlModel backing store
    $markup_tabulation_urls = array_filter($markup_tabulation_urls);

    // Stow these nonexistent records

    $uncached_documents = array_values($uncached_documents);

    if ( $debug_method ) $this->recursive_dump($uncached_documents,"(marker) -- Documents to store");

    if ( !property_exists($this,'stateful_child_pager_links') || (FALSE == $this->stateful_child_pager_links) ) {
      // Cache documents that aren't already in DB store IFF we aren't dealing with stateful pages
      while ( 0 < count($uncached_documents) ) {/*{{{*/
        $document = array_pop($uncached_documents);
        $document['congress_tag'] = $target_congress;
        $documents->non_session_linked_document_stow($document);
      }/*}}}*/
    }

    //////////////////////////////////////////////////
    // GENERATE LISTS OF DOCUMENTS FROM DATABASE CONTENT
    // - Also interpolate missing entries

    $skip_output_generation = FALSE;

    if ( $skip_output_generation ) {/*{{{*/
      $this->syslog( __FUNCTION__, __LINE__, "(marker) --- --- --- - - - --- --- --- Skip generation of output for " . $urlmodel->get_url() );
      $parser->json_reply = array('retainoriginal' => TRUE);
      $pagecontent = 'Fetched ' . $urlmodel->get_url();
      return;
    }/*}}}*/

    $child_collection = $document_parser->generate_links_and_bounds(
      $documents, $url_fetch_regex, $link_regex 
    );

    extract($child_collection);

    $this->syslog(__FUNCTION__, __LINE__, "(marker) --- -- --- Senate {$senatedoc}: {$total_records}");

    if ( $debug_method ) {
      $this->recursive_dump($bounds,"(marker) Bounds");
    }

    $this->syslog(__FUNCTION__, __LINE__, "(marker) --- -- --- Span: {$missing_links}");

    $paginator = '';

    if ( property_exists($this,'stateful_child_pager_links') ) {/*{{{*/

      $this->syslog(__FUNCTION__, __LINE__, "(marker) --- -- --- Not building pager, 'stateful_child_pager_links' property is set to (" . gettype($this->stateful_child_pager_links) . ") " . print_r($this->stateful_child_pager_links,TRUE));

    }/*}}}*/
    else {/*{{{*/

      ///////////////////////////////////////////////////////////////////////////////////////////////////
      // Generate pager markup from URLs in database. 
      // We're interested only in the congress tag and page number
      //
      // Obtain pager URLs, filling in intermediate, missing URLs from the 
      // lower and upper bounds of available pager numbers.

      $this->syslog(__FUNCTION__, __LINE__, "(marker) --- -- --- Build Pager: Finding URLs matching '{$pager_regex}'");

      $pagers = array();
      $datum_regex = $pager_regex; 
      $url_checker->where(array('AND' => array(
        'url' => "REGEXP '{$datum_regex}'"
      )))->recordfetch_setup();

      $linkset_links = array();
      $url = NULL;
      $datum_regex = "@{$datum_regex}@i";
      // Generate pager content table 
      while ( $url_checker->recordfetch($url) ) {/*{{{*/
        if ( $debug_method ) $this->syslog(__FUNCTION__, __LINE__, "(marker) ----- ---- --- -- - - {$url['url']}");
        $datum_parts = array();
        $url = $url['url'];
        preg_match($datum_regex, $url, $datum_parts);
        $congress = $datum_parts[1];
        $page     = intval($datum_parts[2]);
        if ( !array_key_exists($congress, $pagers) ) $pagers[$congress] = array('min' => NULL, 'max' => 0, 'pages' => array());
        // Obtain extrema, and a lookup table of pages that are present
        $pagers[$congress]['min'] = min(is_null($pagers[$congress]['min']) ? $page : $pagers[$congress]['min'], $page);
        $pagers[$congress]['max'] = max($pagers[$congress]['max'], $page);
        $pagers[$congress]['pages'][$page] = NULL;
      }/*}}}*/
      // Replace placeholder tags with displayable values 
      foreach ( $pagers as $congress_tag => $pageinfo ) {/*{{{*/
        $pagers[$congress_tag]['total_pages'] = count($pagers[$congress_tag]['pages']);
        $pagers[$congress_tag]['total_entries'] = $bounds[$congress_tag]['count'];
        for ( $p = $pageinfo['min'] ; $p <= $pageinfo['max'] ; $p++ ) {
          $link_class = array('legiscope-remote');
          $link_class[] = array_key_exists($p, $pageinfo['pages']) ? 'cached' : 'uncached';
          $link_class = join(' ', $link_class);
          $url = str_replace(
            array(
              '{congress_tag}',
              '{page}',
            ),
            array(
              $congress_tag,
              $p,
            ),
            $pager_template_url
          );
          $urlhash = UrlModel::get_url_hash($url);
          $link = <<<EOH
<a id="{$urlhash}" href="{$url}" class="{$link_class}">{$p} </a>
EOH;
          $pagers[$congress_tag]['pages'][$p] = <<<EOH
<span>{$link}</span>
EOH;
          if ( $congress_tag == $target_congress ) {
          // Also create links in control pane (prepend to $parser->linkset)
            $link = <<<EOH
<a id="{$urlhash}" href="{$url}" class="{$link_class}" target="legiscope">{$p} </a>
EOH;
            $linkset_links[$p] = <<<EOH
<li>{$link}</li>
EOH;
          }
        }
        krsort($pagers[$congress_tag]['pages']);
        $pagers[$congress_tag]['pages'] = join('<span class="wrap-content"> </span>', $pagers[$congress_tag]['pages']);
      }/*}}}*/

      if ( 0 < count($linkset_links) ) {
        krsort($linkset_links);
        $linkset_links = join("\n",$linkset_links);
        $linkset_links = <<<EOH
<ul class="link-cluster">
{$linkset_links}
</ul>

EOH;
        $parser->linkset = $linkset_links . (is_array($parser->linkset) ? join("\n",$parser->linkset) : $parser_linkset);
      }

      if ( $debug_method ) $this->recursive_dump($pagers,"(marker) -- - -- Pagers");

      $paginator = <<<EOH
<div id="senate-document-pager" class="wrap-content">{$pagers[$target_congress]['pages']}</div>
EOH;

    }/*}}}*/

    $document_parser->cluster_urldefs = $document_parser->generate_linkset($urlmodel->get_url(),'cluster_urls');

    if ( $debug_method ) $this->recursive_dump($document_parser->cluster_urldefs, "(marker) ----- ---- --- -- - - Cluster URLdefs" );

    $session_select = $document_parser->extract_house_session_select($urlmodel, FALSE);

    if ( $debug_method ) $this->recursive_dump($session_select, "(marker) ----- ---- --- -- - - SELECTs" );
    if ( $debug_method ) $this->recursive_dump($child_collection, "(marker) ----- ---- --- -- - - SELECTs" );

    $pagecontent = $document_parser->generate_congress_session_item_markup(
      $urlmodel,
      $child_collection,
      $session_select,
      is_array($url_fetch_regex) ? NULL : ('\&q=' . $url_fetch_regex)
    );

    $modify_congress_session_item_markup = 'modify_congress_session_item_markup';

    if ( method_exists($document_parser,$modify_congress_session_item_markup) ) {
      $document_parser->$modify_congress_session_item_markup($pagecontent, $session_select);
    }

    // Replace pager placeholders 
    foreach ( array_keys($pagers) as $congress_tag ) {
      $pagecontent = str_replace("[PAGER{$congress_tag}]", $bounds[$congress_tag]['count'], $pagecontent);
    }

    // Insert the stripped, unparsed document
    if ($debug_method) $this->syslog( __FUNCTION__, __LINE__, "(marker) Inserting '{$urlmodel}'");
    $links = $document_parser->get_containers(
      'children[tagname*=div][attrs:CLASS*=alight]',0
    );
    $document_parser->filter_nested_array($links,
      "#[url*=.*|i]",0
    );
    if ($debug_method) $this->recursive_dump($links,"(marker) --- - ---- --");
    $links = array_filter($links);

    $alternate_content = array();

    if ( is_array($links) && (0 < count($links)) ) {/*{{{*/
      foreach ( $links as $link ) {/*{{{*/
        $url_checker->set_url($link['url'],FALSE);
        $sbn = $url_checker->get_query_element('q');
        $urlhash = UrlModel::get_url_hash($link['url']);
        $linktext = array_filter(explode('[BR]',$link['text']));
        if ( empty($sbn) ) {
          $sbn = $linktext[0];
          $linktext = $linktext[1];
        } else {
          $linktext = join(' ', $linktext);
        }
        $alternate_content[] = <<<EOH
<p><a id="{$urlhash}" href="{$link['url']}" class="legiscope-remote">{$sbn}</a>: {$linktext}</p>
EOH;
      }/*}}}*/
    }/*}}}*/

    $append_filtered_doc = ( property_exists($this,'append_filtered_doc') && $this->append_filtered_doc );

    $inserted_content = 0 < count($alternate_content)
      ? join("\n", $alternate_content) . ($append_filtered_doc ? join('',$document_parser->get_filtered_doc()) : '')
      : join('',$document_parser->get_filtered_doc())
      ;

    // 'paginator' refers to the list of pager links (1,2,3, etc.)
    $pagecontent .= <<<EOH

<div class="hidden" id="inserted-content">
{$paginator}
{$inserted_content}
</div>

<script type="text/javascript">

function document_pager_initialize() {
  jQuery('div[id=senate-document-pager]').find('a').each(function(){
    jQuery(this).unbind('click');
    jQuery(this).unbind('mouseup');
    jQuery(this).on('contextmenu', function(){
      return false;
    }).mouseup(function(e){
      e.stopPropagation();
      var url = jQuery(this).attr('href');
      if (2 == parseInt(jQuery(e).prop('button'))) {
        jQuery('div[id=senate-document-pager]')
          .find('a[class*=uncached]')
          .first()
          .each(function(){
            jQuery('#doctitle').html("Seek: "+url);
            jQuery(this).removeClass('uncached').click();
          });
        return false;
      }
      return true;
    }).click(function(e){
      var url = jQuery(this).attr('href');
      var self = jQuery(this);
      load_content_window(jQuery(this).attr('href'),false,jQuery(this),null,{
        beforeSend : (function() {
          jQuery('#doctitle').html("Loading "+url);
          display_wait_notification();
        }),
        complete : (function(jqueryXHR, textStatus) {
          jQuery('#doctitle').html("Legiscope");
          remove_wait_notification();
        }),
        success : function(data, httpstatus, jqueryXHR) {

          remove_wait_notification();

          jQuery('div[class*=contentwindow]').each(function(){
            if (jQuery(this).attr('id') == 'issues') return;
            jQuery(this).children().remove();
          });

          replace_contentof('original',data.original);
          jQuery(self).addClass('cached');
          setTimeout((function() {
            document_pager_initialize();
            jQuery('div[id=senate-document-pager]')
              .find('a[class*=uncached]')
              .first()
              .each(function(){
                jQuery('#doctitle').html("Seek: "+jQuery(this).attr('href'));
                jQuery(this).removeClass('uncached').click();
              });
          }),2000);

        }
      });
    });
  });
}

jQuery(document).ready(function(){
  jQuery('div[id=subcontent]').children().remove();
  jQuery('div[id=subcontent]')
    .append(jQuery('div[id=inserted-content]').children().detach());
  jQuery('div[id=inserted-content]').remove();
  setTimeout((function(){
    document_pager_initialize();
    jQuery('#doctitle').html("Legiscope - Ready to traverse pages");
  }),2000);
});
</script>

EOH;

    $this->terminal_method_cache_stow($parser, $pagecontent);
    $parser->json_reply = array('retainoriginal' => TRUE);
  }/*}}}*/

  function non_session_linked_document($caller, & $parser, & $pagecontent, & $urlmodel, $url_fetch_regex, $link_regex, $url_template, $pager_regex, $pager_template_url ) {/*{{{*/

    $method_suffix = '@leg_sys_(.*)$@i';
    $senatedoc = explode('_',preg_replace($method_suffix,'$1', $caller));
    array_walk($senatedoc, create_function('& $a, $k', '$a = ucfirst(strtolower($a));'));
    $senatedoc = join('', $senatedoc);

    $documents = "Senate{$senatedoc}DocumentModel";
    $document_parser = "Senate{$senatedoc}ParseUtility";

    $this->non_session_linked_document_worker($documents, $document_parser, $senatedoc, $parser, $pagecontent, $urlmodel, $url_fetch_regex, $link_regex, $url_template, $pager_regex, $pager_template_url );

  }/*}}}*/

  function session_linked_content_parser($caller, $prefix, & $parser, & $pagecontent, & $urlmodel ) {/*{{{*/

    $debug_method = TRUE;

    $method_infix = '@^senate_(.*)_content_parser$@i';
    $senatedoc = ucfirst(strtolower(preg_replace($method_infix,'$1', $caller)));

    $documents = "Senate{$senatedoc}DocumentModel";
    $document_parser = "Senate{$senatedoc}ParseUtility";

    if ( $debug_method ) 
    $this->syslog( __FUNCTION__, __LINE__, "(marker) ----- ---- --- -- - - - {$document_parser}, {$documents}" );

    $parser->json_reply = array('retainoriginal' => TRUE);

    ////////////////////////////////////////////////////////////////////////////////////////////

    $senate_document                      = new $documents();
    $senate_document_parser               = new $document_parser();
    $senate_document_parser->debug_tags   = FALSE;
    $senate_document_parser->from_network = $parser->from_network;

    $stowable_content = $senate_document_parser->
      set_parent_url($urlmodel->get_url())->
      parse_single_document_html(
        $urlmodel->get_pagecontent(),
        $urlmodel->get_response_header()
      );

    $urlmodel->ensure_custom_parse();

    $action_url = NULL;
    $faux_url   = NULL;

    // Obtain document serial number and Congress convention number,
    // and fetch on-disk copy of document for comparison.
    $traversal_resultarray = $this->execute_document_form_traversal(
      $prefix     , $senate_document_parser, $parser    ,
      $pagecontent, $urlmodel              , $action_url,
      $faux_url   , $debug_method
    ); 

    if ( is_null($faux_url) || ( FALSE == $traversal_resultarray ) || !(2 == count($traversal_resultarray) ) ) {/*{{{*/
      // Try it again.
      $parser->from_network = TRUE;
      $parser->update_existing = TRUE;
      $faux_url = NULL;
      $ok = FALSE;
      if ( $this->perform_network_fetch( 
        $urlmodel, NULL, $urlmodel->get_url(),
        $faux_url, NULL, $debug_method
      ) ) {
        $stowable_content = $senate_document_parser->
          set_parent_url($urlmodel->get_url())->
          parse_single_document_html(
            $urlmodel->get_pagecontent(),
            $urlmodel->get_response_header()
          );
        $traversal_resultarray = $this->execute_document_form_traversal(
          $prefix     , $senate_document_parser, $parser    ,
          $pagecontent, $urlmodel              , $action_url,
          $faux_url   , $debug_method
        ); 
        $ok = !( is_null($faux_url) || ( FALSE == $traversal_resultarray ) || !(2 == count($traversal_resultarray) ));
      }

      if ( $ok ) {
        $this->syslog(__FUNCTION__,__LINE__,"(marker) - - - RELOADED " . $urlmodel->get_url());
      } else {
        $this->invalidate_record($senate_document, $urlmodel);
        $document_sn = $senate_document->get_sn();
        $pagecontent = NULL;
        $parser->json_reply['subcontent'] = <<<EOH
<span>No Content Retrieved for {$document_sn}</span>
EOH;
        return FALSE;
      }
    }/*}}}*/

    extract($traversal_resultarray); // $target_congress, $sbn_regex_result

    if ( array_key_exists('comm_report_url', $stowable_content) ) { $u = array('url' => $stowable_content['comm_report_url']); $l = UrlModel::parse_url($action_url->get_url()); $stowable_content['comm_report_url'] = UrlModel::normalize_url($l, $u); }
    if ( array_key_exists('doc_url', $stowable_content) )         { $u = array('url' => $stowable_content['doc_url'])        ; $l = UrlModel::parse_url($action_url->get_url()); $stowable_content['doc_url'] = UrlModel::normalize_url($l, $u); }

    if ( $debug_method ) {/*{{{*/
      $this->syslog(__FUNCTION__,__LINE__,"(marker) Document SN: {$sbn_regex_result}");
      $this->syslog(__FUNCTION__,__LINE__,"(marker)    Congress: {$target_congress}");
      $this->recursive_dump($stowable_content,"(marker) -- DC -- SB {$sbn_regex_result}.{$target_congress}");
    }/*}}}*/

    $stowable_content['congress_tag'] = $target_congress;
    $stowable_content['sn']           = $sbn_regex_result;

    $difference = $senate_document->
      retrieve(array(
        'sn'           => $sbn_regex_result,
        'congress_tag' => $target_congress,
      ),'AND')->
      retrieved_record_difference($stowable_content, $debug_method);

    $missing       = !$senate_document->in_database();
    $newly_fetched = $parser->from_network;
    $force_update  = $parser->update_existing;

    // Stow flat document (Join record updates/stow will need to be handled by the *DocumentModel)
    if ( $missing || $newly_fetched || ($force_update && (0 < count($difference))) ) {/*{{{*/

      $stowable_content['url'] = $action_url->get_url();
      krsort($stowable_content);
      $stowable_content = array_filter($stowable_content);

      $id = method_exists($senate_document,'stow_parsed_content')
        ? $senate_document->stow_parsed_content($stowable_content)
        : $senate_document->set_contents_from_array($stowable_content)->stow()
        ;

      if ( $debug_method || (0 < count($difference)) ) $this->syslog(__FUNCTION__, __LINE__, "(marker) --- --- --- - - - --- --- --- Stowed ".get_class($senate_document)." {$sbn_regex_result}.{$target_congress} #{$id}" );

    }/*}}}*/

    if ( $senate_document->in_database() ) {/*{{{*/

      // Join this document to Senator dossiers
      array_walk(array_element($stowable_content,'senator',array()),create_function(
        '& $a, $k, $s', '$s->senate_document_senator_dossier_join($a,TRUE,FALSE);'
      ),$senate_document);

      // This method belongs in Senate_DocumentModel
      $markup_generator = 'generate_non_session_linked_markup';

      $valid_entry = (0 < strlen($senate_document->get_sn())) && (0 < strlen($senate_document->get_description()));

      if ( !method_exists($senate_document,$markup_generator) ) {

        throw new Exception("Missing method " . get_class($senate_document) . "::{$markup_generator}");

      } else {

        $pagecontent = $senate_document->$markup_generator();

        if ( property_exists($this,'append_filtered_doc') && $this->append_filtered_doc ) {
          if ( $valid_entry ) {
            $pagecontent .= join('',$senate_document_parser->get_filtered_doc());
          } else {
            $pagecontent = <<<EOH
<span>No Valid Content Recorded (either SN or description of document is empty)</span>
EOH;
          }
        }

      }
     
     if (0) {/*{{{*/// From session_linked_content_parser

        if ( $debug_method ) $this->syslog( __FUNCTION__, __LINE__, "(marker) --- --- --- - - - --- --- --- Got #" . $senate_document->get_id() . " " . get_class($senate_document) . " {$sbn_regex_result}|" . (method_exists($senate_document,'get_sn') ? $senate_document->get_sn() : "" ) );

        $total_bills_in_system = $senate_document->count();

        $doc_url_attrs = array('legiscope-remote');
        $faux_url_hash = UrlModel::get_url_hash($senate_document->get_url());
        $faux_url->fetch($faux_url_hash,'urlhash');
        if ( $faux_url->in_database() ) $doc_url_attrs[] = 'cached';
        $doc_url_attrs = join(' ', $doc_url_attrs);

        $pagecontent = $valid_entry 
          ?  $senate_document->substitute(<<<EOH

Senate {$senatedoc}s in system: {$total_bills_in_system}
<span class="sb-match-item">{sn}.{congress_tag}</span>
<span class="sb-match-item sb-match-subjects">{title}</span>
<span class="sb-match-item sb-match-subjects">{subjects}</span>
<span class="sb-match-item sb-match-description">{description}</span>
<span class="sb-match-item sb-match-significance"><b>Scope</b>: {significance}</span>
<span class="sb-match-item sb-match-status"><b>Status</b>: {status}</span>
<span class="sb-match-item sb-match-doc-url"><b>Document</b>: <a class="{$doc_url_attrs}" href="{doc_url}">{sn}</a></span>
<span class="sb-match-item sb-match-main-referral-comm"><b>Committee</b>: {main_referral_comm}</span>
<span class="sb-match-item sb-match-main-referral-comm"><b>Secondary Committee</b>: {secondary_committee}</span>
<span class="sb-match-item sb-match-committee-report-info">Committee Report: <a class="legiscope-remote" href="{comm_report_url}">{comm_report_info}</a></span>
EOH
        )
          : <<<EOH
<span>No Content Recorded</span>
EOH;
        $pagecontent  = str_replace('[BR]','<br/>', $pagecontent);
        if ( property_exists($this,'append_filtered_doc') && $this->append_filtered_doc )
          $pagecontent .= join('',$senate_document_parser->get_filtered_doc());
      }/*}}}*/

      if (0) {/*{{{*/// From non_session_linked_content_parser_worker

        $this->syslog( __FUNCTION__, __LINE__, "(marker) ---  - - -  --- WARNING: No markup generator " . get_class($senate_document) . "::{$markup_generator}()" );
        $this->syslog( __FUNCTION__, __LINE__, "(marker) ---  - - -  --- Got #" . $senate_document->get_id() . " " . get_class($senate_document) );

        $total_bills_in_system = $senate_document->count();

        $doc_url_attrs = array('legiscope-remote');
        $faux_url_hash = UrlModel::get_url_hash($senate_document->get_url()); 
        $faux_url->fetch($faux_url_hash,'urlhash');
        if ( $faux_url->in_database() ) $doc_url_attrs[] = 'cached';
        $doc_url_attrs = join(' ', $doc_url_attrs);

        $pagecontent = $senate_document->substitute(<<<EOH
Senate {$senatedoc}s in system: {$total_bills_in_system}
<span class="sb-match-item">{sn}.{congress_tag}</span>
<span class="sb-match-item sb-match-subjects">{subjects}</span>
<span class="sb-match-item sb-match-description">{description}</span>
<span class="sb-match-item sb-match-significance">Scope: {significance}</span>
<span class="sb-match-item sb-match-status">Status: {status}</span>
<span class="sb-match-item sb-match-doc-url">Document: <a class="{$doc_url_attrs}" href="{doc_url}">{sn}</a></span>
<span class="sb-match-item sb-match-main-referral-comm">Committee: {main_referral_comm}</span>
<span class="sb-match-item sb-match-committee-report-info">Committee Report: <a class="legiscope-remote" href="{comm_report_url}">{comm_report_info}</a></span>
EOH
      );
        $pagecontent  = str_replace('[BR]','<br/>', $pagecontent);
        if ( property_exists($this,'append_filtered_doc') && $this->append_filtered_doc )
          $pagecontent .= join('',$senate_document_parser->get_filtered_doc());
      }/*}}}*/

      if ( !$valid_entry ) {/*{{{*/

        $senate_document_id = $senate_document->get_id();
        $remove_invalidated = C('REMOVE_INVALIDATED_DOCUMENTS');
        $can_invalidate = !C('SUPPRESS_MALFORMED_DOC_INVALIDATION') && method_exists($senate_document,'set_invalidated');
        $invalidation_action = $can_invalidate ? "Invalidating" : ($remove_invalidated ? "Removing" : "Retaining INVALIDATED");
        $this->syslog(__FUNCTION__,__LINE__,"(warning) - - - - - !!!!   {$invalidation_action} entry #{$senate_document_id} " . get_class($senate_document));
        if ( $can_invalidate ) {
          $senate_document->
            set_invalidated(TRUE)->
            set_last_fetch(time())->
            fields(array('invalidated','last_fetch'))->
            stow();
        } else if ($remove_invalidated) {
          $senate_document->remove();
        }

      }/*}}}*/
      else {/*{{{*/
        $senate_document->
          set_invalidated(0)->
          set_last_fetch(time())->
          fields(array('invalidated','last_fetch'))->
          stow();
      }/*}}}*/

    }/*}}}*/

    ////////////////////////////////////////////////////////////////////////////////////////////

    if ( C('ENABLE_GENERATED_CONTENT_BUFFERING') ) {/*{{{*/
      if ( !file_exists($cache_filename) || $parser->from_network )
        file_put_contents($cache_filename, $pagecontent);
    }/*}}}*/

    $parser->json_reply['subcontent'] = $pagecontent;
    $pagecontent = NULL;

  }/*}}}*/

  function seek_postparse_bypath_62f91d11784860d07dea11c53509a732(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    // 15th Congress
    $this->leg_sys_router($parser,$pagecontent,$urlmodel);
  }/*}}}*/

  function seek_postparse_bypath_plus_queryvars_490bd1508f4ae5f52823e5ba5586af10(& $parser, & $pagecontent, & $urlmodel) {
    // 16th Congress
    $this->leg_sys_router($parser,$pagecontent,$urlmodel);
  }

  function seek_postparse_bypath_plus_queryvars_609c17530429204ab1790ea5bef4f0b1(& $parser, & $pagecontent, & $urlmodel) {
    // 16th Congress
    $this->leg_sys_router($parser,$pagecontent,$urlmodel);
  }

  function leg_sys_router(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    /** Router **/ 
    // http://www.senate.gov.ph/lis/leg_sys.aspx?congress=15&type=bill 
    // 2013 March 16:  Individual Senate bills are enumerated across multiple pages.
    // Links found at this URL lead to a Javascript-triggered form; we should use the first level links available
    // on this page to immediately display the second-level link content.

    $leg_sys_type = $urlmodel->get_query_element('type');
    $target_document = $urlmodel->get_query_element('q');

    $this->syslog( __FUNCTION__, __LINE__, "(marker) ********** Router: {$leg_sys_type} for " . $urlmodel->get_url() );

    switch ($leg_sys_type) {
      case 'bill' : 
        return $this->leg_sys_bill($parser,$pagecontent,$urlmodel);
        break;
      case 'resolution':
        return $this->leg_sys_resolution($parser,$pagecontent,$urlmodel);
        break;
      case 'journal':
        if ( empty($target_document) )
          return $this->seek_postparse_congress_type_journal($parser,$pagecontent,$urlmodel);
        else
          return $this->leg_sys_journal($parser,$pagecontent,$urlmodel);
        break;
      case 'committee_rpt':
        return $this->leg_sys_committee_rpt($parser,$pagecontent,$urlmodel);
        break;
      default:
        $this->common_unhandled_page_parser($parser,$pagecontent,$urlmodel);
        break;
    }

  }/*}}}*/

  function seek_postparse_bypath_1fa0159bc56439f7a8a02d5b4f3628ff(& $parser, & $pagecontent, & $urlmodel) {
    $this->senate_document_content_parsers($parser, $pagecontent, $urlmodel);
  }

  function seek_postparse_bypath_plus_queryvars_2c1ba14d2e7fa5f538650a437bffb487(& $parser, & $pagecontent, & $urlmodel) {
    $this->senate_document_content_parsers($parser, $pagecontent, $urlmodel);
  }

  function senate_document_content_parsers(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/

    $debug_method = FALSE;

    if ( $debug_method ) $this->syslog( __FUNCTION__, __LINE__, "(marker) Level 2 Pagecontent by-path parser invocation for " . $urlmodel->get_url() );

    $target_document = $urlmodel->get_query_element('q');

    $match_components = array();

    if ( 1 == preg_match('@(.*)-([0-9]*)@i', $target_document, $match_components) ) {

      if ( $debug_method ) $this->recursive_dump($match_components,"(marker) ---- ---- --- -- - " . __METHOD__ );

      switch ( strtoupper($match_components[1]) ) {
        case 'SBN' :
          return $this->senate_bill_content_parser($parser,$pagecontent,$urlmodel);
          break;
        case 'SCR' :
          return $this->senate_concurrentres_content_parser($parser,$pagecontent,$urlmodel);
          break;
        case 'SJR' :
          return $this->senate_jointres_content_parser($parser,$pagecontent,$urlmodel);
          break;
        case 'SRN' :
          return $this->senate_resolution_content_parser($parser,$pagecontent,$urlmodel);
          break;
        case 'HBN' :
          return $this->senate_housebill_content_parser($parser,$pagecontent,$urlmodel);
          break;
        default:
          $this->common_unhandled_page_parser($parser,$pagecontent,$urlmodel);
          $pagecontent = "No content parser for document type '{$match_components[1]}'<br/><br/>" . $pagecontent;
      }
    }

    $parser->json_reply = array('retainoriginal' => TRUE);

    return FALSE;

  }/*}}}*/

  function senate_housebill_content_parser(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/

    $this->append_filtered_doc = FALSE;
    $this->debug_method        = FALSE;

    // Workaround to force POST without GET, when retrieving detail pages.
    $parser->network_fetch_skip_get = TRUE;
    if ( $parser->update_existing ) {
      $parser->from_network = TRUE;
    }


    $this->session_linked_content_parser(__FUNCTION__, 'HBN', $parser, $pagecontent, $urlmodel );

  }/*}}}*/

  function senate_jointres_content_parser(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/

    $this->append_filtered_doc = FALSE;
    $this->debug_method        = FALSE;

    // Workaround to force POST without GET, when retrieving detail pages.
    $parser->network_fetch_skip_get = TRUE;
    if ( $parser->update_existing ) {
      $parser->from_network = TRUE;
    }

    $this->non_session_linked_content_parser(__FUNCTION__, 'SJR', $parser, $pagecontent, $urlmodel );

  }/*}}}*/

  function senate_concurrentres_content_parser(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/

    $this->append_filtered_doc = FALSE;
    $this->debug_method        = FALSE;

    // Workaround to force POST without GET, when retrieving detail pages.
    $parser->network_fetch_skip_get = TRUE;
    if ( $parser->update_existing ) {
      $parser->from_network = TRUE;
    }

    $this->non_session_linked_content_parser(__FUNCTION__, 'SCR', $parser, $pagecontent, $urlmodel );

  }/*}}}*/

  function senate_resolution_content_parser(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/

    $this->append_filtered_doc = FALSE;
    $this->debug_method        = FALSE;
    $urlmodel->ensure_custom_parse();

    // Workaround to force POST without GET, when retrieving detail pages.
    $parser->network_fetch_skip_get = TRUE;
    if ( $parser->update_existing ) {
      $parser->from_network = TRUE;
    }

    $this->non_session_linked_content_parser(__FUNCTION__, 'SRN', $parser, $pagecontent, $urlmodel );

  }/*}}}*/

  function senate_bill_content_parser(& $parser, & $pagecontent, & $urlmodel) { /*{{{*/

    $this->append_filtered_doc = FALSE;
    $this->debug_method        = FALSE;
    $urlmodel->ensure_custom_parse();

    $content = new ContentDocumentModel();

    // Workaround to force POST without GET, when retrieving detail pages.
    if ( $parser->update_existing ) {
      $parser->network_fetch_skip_get = TRUE;
      $parser->from_network = TRUE;
    }

    $this->non_session_linked_content_parser(__FUNCTION__, 'SBN', $parser, $pagecontent, $urlmodel );

    $parser->json_reply['retainoriginal'] = FALSE;

  }/*}}}*/

  function non_session_linked_content_parser($caller, $prefix, & $parser, & $pagecontent, & $urlmodel ) {/*{{{*/

    $pagecontent = NULL;
    $debug_method = FALSE;

    $method_infix = '@^senate_(.*)_content_parser$@i';
    $senatedoc = ucfirst(strtolower(preg_replace($method_infix,'$1', $caller)));

    $documents = "Senate{$senatedoc}DocumentModel";
    $document_parser = "Senate{$senatedoc}ParseUtility";

    if ( $debug_method ) $this->syslog( __FUNCTION__, __LINE__, "(marker) ----- ---- --- -- - - - {$document_parser}, {$documents}" );

    return $this->non_session_linked_content_parser_worker($documents, $document_parser, $senatedoc, $prefix, & $parser, & $pagecontent, & $urlmodel );

  }/*}}}*/

  function non_session_linked_content_parser_worker($documents, $document_parser, $senatedoc, $prefix, & $parser, & $pagecontent, & $urlmodel ) {/*{{{*/

    $debug_method = (property_exists($this,'debug_method') && $this->debug_method);

    $parser->json_reply = array(
      'retainoriginal' => TRUE,
      'subcontent' => "<span>Parse failure</span>", 
    );

    $this->syslog( __FUNCTION__, __LINE__, "(marker) ----- ---- --- -- - - - {$document_parser}, {$documents}" );

    ////////////////////////////////////////////////////////////////////////////////////////////

    $senate_document                      = new $documents();
    $senate_document_parser               = new $document_parser();
    $senate_document_parser->debug_tags   = FALSE;
    $senate_document_parser->from_network = $parser->from_network;

    $stowable_content = $senate_document_parser->
      set_parent_url($urlmodel->get_url())->
      parse_single_document_html(
        $urlmodel->get_pagecontent(),
        $urlmodel->get_response_header()
      );

    $urlmodel->ensure_custom_parse();

    $action_url = NULL;
    $faux_url   = NULL;

    // Obtain document serial number and Congress convention number,
    // and fetch on-disk copy of document for comparison.
    $traversal_resultarray = $this->execute_document_form_traversal(
      $prefix     , $senate_document_parser, $parser    ,
      $pagecontent, $urlmodel              , $action_url,
      $faux_url   , $debug_method
    ); 

    $pagecontent = "";

    if ( is_null($faux_url) || (FALSE == $traversal_resultarray) || !(2 == count($traversal_resultarray) ) ) {/*{{{*/
      // Try it again.
      $parser->from_network = TRUE;
      $parser->update_existing = TRUE;
      $faux_url = NULL;
      $ok = FALSE;
      if ( $this->perform_network_fetch( 
        $urlmodel, NULL, $urlmodel->get_url(),
        $faux_url, NULL, $debug_method
      ) ) {
        $stowable_content = $senate_document_parser->
          set_parent_url($urlmodel->get_url())->
          parse_single_document_html(
            $urlmodel->get_pagecontent(),
            $urlmodel->get_response_header()
          );
        $traversal_resultarray = $this->execute_document_form_traversal(
          $prefix     , $senate_document_parser, $parser    ,
          $pagecontent, $urlmodel              , $action_url,
          $faux_url   , $debug_method
        ); 

        $pagecontent = "";

        $ok = !( is_null($faux_url) || ( FALSE == $traversal_resultarray ) || !(2 == count($traversal_resultarray) ));
      }

      if ( $ok ) {
        $this->syslog(__FUNCTION__,__LINE__,"(marker) - - - RELOADED " . $urlmodel->get_url());
      } else {
        $this->syslog(__FUNCTION__,__LINE__,"(marker) - - - EXIT " . $urlmodel->get_url());
        $this->invalidate_record($senate_document, $urlmodel);
        $document_sn = $senate_document->get_sn();
        $pagecontent = NULL;
        // unset($parser->json_reply['retainoriginal']);
        $parser->json_reply['retainoriginal'] = FALSE;
        $parser->json_reply['subcontent'] = <<<EOH
<span>No Content Retrieved for {$document_sn}</span>
EOH;
        return FALSE;
      }
    }/*}}}*/

    extract($traversal_resultarray); // $target_congress, $sbn_regex_result

    if ( array_key_exists('comm_report_url', $stowable_content) ) { $u = array('url' => $stowable_content['comm_report_url']); $l = UrlModel::parse_url($action_url->get_url()); $stowable_content['comm_report_url'] = UrlModel::normalize_url($l, $u); }
    if ( array_key_exists('doc_url', $stowable_content) )         { $u = array('url' => $stowable_content['doc_url'])        ; $l = UrlModel::parse_url($action_url->get_url()); $stowable_content['doc_url'] = UrlModel::normalize_url($l, $u); }

    if ( $debug_method ) {/*{{{*/
      $this->syslog(__FUNCTION__,__LINE__,"(marker) Document SN: {$sbn_regex_result}");
      $this->syslog(__FUNCTION__,__LINE__,"(marker)    Congress: {$target_congress}");
      $this->recursive_dump($stowable_content,"(marker) -- DC -- SB {$sbn_regex_result}.{$target_congress}");
    }/*}}}*/

    $stowable_content['congress_tag'] = $target_congress;
    $stowable_content['sn']           = $sbn_regex_result;

    $difference = $senate_document->
      retrieve(array(
        'sn'           => $sbn_regex_result,
        'congress_tag' => $target_congress,
      ),'AND')->
      retrieved_record_difference($stowable_content, $debug_method);

    $missing       = !$senate_document->in_database();
    $newly_fetched = $parser->from_network;
    $force_update  = $parser->update_existing;

    // Stow flat document (Join record updates/stow will need to be handled by the *DocumentModel)
    if ( $missing || $newly_fetched || ($force_update && (0 < count($difference))) ) {/*{{{*/

      $stowable_content['url'] = $action_url->get_url();
      krsort($stowable_content);
      $stowable_content = array_filter($stowable_content);
      $stowable_content['invalidated'] = 0;

      $id = method_exists($senate_document,'stow_parsed_content')
        ? $senate_document->stow_parsed_content($stowable_content)
        : $senate_document->set_contents_from_array($stowable_content)->stow()
        ;

      if ( $debug_method || (0 < count($difference)) ) $this->syslog(__FUNCTION__, __LINE__, "(marker) --- --- --- - - - --- --- --- Stowed ".get_class($senate_document)." {$sbn_regex_result}.{$target_congress} #{$id}" );

    }/*}}}*/

    if ( $debug_method ) $this->syslog( __FUNCTION__, __LINE__, "(marker) ----- ---- --- -- - - - - - - - - - - - - - - - - Z" );

    if ( $senate_document->in_database() ) {/*{{{*/

      // Join this document to Senator dossiers
      array_walk(array_element($stowable_content,'senator',array()),create_function(
        '& $a, $k, $s', '$s->senate_document_senator_dossier_join($a,TRUE,FALSE);'
      ),$senate_document);

      // This method belongs in Senate_DocumentModel
      $markup_generator = 'generate_non_session_linked_markup';

      $valid_entry = (0 < strlen($senate_document->get_sn())) && (0 < strlen($senate_document->get_description()));

      if ( $debug_method ) $this->syslog( __FUNCTION__, __LINE__, "(marker) ----- ---- --- -- - - - - - - - - - - - - - - - - Y" );

      if ( !method_exists($senate_document,$markup_generator) ) {

        if ( $debug_method ) $this->syslog( __FUNCTION__, __LINE__, "(marker) ----- ---- --- -- - - - - - - - - - - - - - - - - X" );

        throw new Exception("Missing method " . get_class($senate_document) . "::{$markup_generator}");

      } else {

        if ( $debug_method ) $this->syslog( __FUNCTION__, __LINE__, "(marker) ----- ---- --- -- - - - - - - - - - - - - - - - - W" );
        $pagecontent = $senate_document->$markup_generator();

        if ( property_exists($this,'append_filtered_doc') && $this->append_filtered_doc ) {
          if ( $valid_entry ) {
            $pagecontent .= '<br/><hr/>' . join('',$senate_document_parser->get_filtered_doc());
          } else {
            $pagecontent = <<<EOH
<span>No Valid Content Recorded (either SN or description of document is empty)</span>
EOH;
          }
        }

      }
     
      if ( !$valid_entry ) {/*{{{*/

        if ( $debug_method ) $this->syslog( __FUNCTION__, __LINE__, "(marker) ----- ---- --- -- - - - - - - - - - - - - - - - - V" );

        $senate_document_id = $senate_document->get_id();
        $remove_invalidated = C('REMOVE_INVALIDATED_DOCUMENTS');
        $can_invalidate = !C('SUPPRESS_MALFORMED_DOC_INVALIDATION') && method_exists($senate_document,'set_invalidated');
        $invalidation_action = $can_invalidate ? "Invalidating" : ($remove_invalidated ? "Removing" : "Retaining INVALIDATED");
        $this->syslog(__FUNCTION__,__LINE__,"(warning) - - - - - !!!!   {$invalidation_action} entry #{$senate_document_id} " . get_class($senate_document));
        if ( $can_invalidate ) {
          $senate_document->
            set_invalidated(TRUE)->
            set_last_fetch(time())->
            fields(array('invalidated','last_fetch'))->
            stow();
        } else if ($remove_invalidated) {
          $senate_document->remove();
        }

      }/*}}}*/
      else {/*{{{*/
        if ( $debug_method ) $this->syslog( __FUNCTION__, __LINE__, "(marker) ----- ---- --- -- - - - - - - - - - - - - - - - - U" );
        $senate_document->
          set_invalidated(0)->
          set_last_fetch(time())->
          fields(array('invalidated','last_fetch'))->
          stow();
      }/*}}}*/

    }/*}}}*/

    ////////////////////////////////////////////////////////////////////////////////////////////

    if ( C('ENABLE_GENERATED_CONTENT_BUFFERING') ) {/*{{{*/
      if ( $debug_method ) $this->syslog( __FUNCTION__, __LINE__, "(marker) ----- ---- --- -- - - - - - - - - - - - - - - - - T" );
      if ( !file_exists($cache_filename) || $parser->from_network ) {
        if ( $debug_method ) $this->syslog( __FUNCTION__, __LINE__, "(marker) ----- ---- --- -- - - - - - - - - - - - - - - - - S" );
        @file_put_contents($cache_filename, $pagecontent);
      }
    }/*}}}*/

    if ( $debug_method ) $this->syslog( __FUNCTION__, __LINE__, "(marker) ----- ---- --- -- - - - - - - - - - - - - - - - - R" );

    $parser->json_reply['subcontent'] = $pagecontent; 
    $pagecontent = '';

  }/*}}}*/



  /** Adopted Resolutions **/

  function pdf_sys_adopted_res(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/

    $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked for " . $urlmodel->get_url() );

    $this->method_cache_emit($parser);

    // Prevent parsing this particular URL in the parent
    $urlmodel->ensure_custom_parse();

    $resolution        = new SenateAdoptedresDocumentModel();
    $resolution_parser = new SenateAdoptedresParseUtility();

    $resolution_parser->
      reset()->
      set_parent_url($urlmodel->get_url())->
      parse_html(
        $urlmodel->get_pagecontent(),
        $urlmodel->get_response_header()
      );
    $inserted_content = join('',$resolution_parser->get_filtered_doc());

    // Insert the stripped, unparsed document
    $this->syslog( __FUNCTION__, __LINE__, "(marker) Inserting '{$urlmodel}'");
    $pagecontent = <<<EOH
<div class="senate-journal">No Parser</div>
<div class="alternate-content alternate-content" id="senate-journal-block">
{$inserted_content}
</div>
EOH;

    $this->terminal_method_cache_stow($parser, $pagecontent);

    $parser->json_reply = array('retainoriginal' => FALSE);

  }/*}}}*/

  /** Committee Reports **/

  function canonical_committee_report_page_parser(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/

    $debug_method = FALSE;

    if ( $debug_method ) $this->syslog( __FUNCTION__, __LINE__, "(marker) ----- ---- --- -- - - - Invoking parser.");

    $commreport_parser = new SenateCommitteeReportParseUtility();
    $commreport_parser->set_parent_url($urlmodel->get_url())->parse_html($urlmodel->get_pagecontent(),$urlmodel->get_response_header());

    $committee_report = array_filter($commreport_parser->activity_summary);

    if ( $debug_method ) $this->recursive_dump($committee_report,'(-----) A');

    $pagecontent = $commreport_parser->parse_activity_summary($committee_report);

    $committee_report = array_merge(
      array(
        'url'            => $urlmodel->get_url(),
      ),
      $committee_report
    );

    // Commit report as needed
    $report = new SenateCommitteeReportDocumentModel();

    if ( empty($committee_report['sn']) ) {
      $pagecontent = join('',$commreport_parser->get_filtered_doc());
    } else {
      $report->stow_activity_summary($committee_report);
    }


    // Append script to fetch missing PDFs
    if ( C('LEGISCOPE_JOURNAL_PDF_AUTOFETCH') )  $pagecontent .= $commreport_parser->jquery_seek_missing_journal_pdf();

    $parser->json_reply = array('retainoriginal' => TRUE);

  }/*}}}*/

  function seek_postparse_4c2e9f440b8f89e94f6de126ef0e38c4(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/

    $this->leg_sys_committee_report($parser,$pagecontent,$urlmodel);

  }/*}}}*/

  function leg_sys_committee_report(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/

    return $this->non_session_linked_document(
      __FUNCTION__,
      $parser,$pagecontent,$urlmodel,
      // Unique tail fragment for Resolution URLs
      '([0-9]*)',
      // Regex yielding 2 match fields
      'congress=([0-9]*)\&q=([0-9]*)',
      // Template URL
      'http://www.senate.gov.ph/lis/committee_rpt.aspx?congress={congress_tag}&q={full_sn}',
      // Pager URL fetch regex
      'congress=([0-9]*)\&type=committee_rpt\&p=([0-9]*)',
      // Pager template URL
      'http://www.senate.gov.ph/lis/leg_sys.aspx?congress={congress_tag}&type=committee_rpt&p={page}'
    );

  }/*}}}*/

  function seek_postparse_bypath_44f799b5135aac003bf23fabbe947941(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    $debug_method = FALSE;
    if ( $debug_method )
    $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked for " . $urlmodel->get_url() );
    $this->canonical_committee_report_page_parser($parser,$pagecontent,$urlmodel);
    $parser->json_reply = array('retainoriginal' => TRUE);
  }/*}}}*/

  function seek_by_pathfragment_791d51238202c15ff6456b035963dcd9(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked for " . $urlmodel->get_url() );
    $this->common_unhandled_page_parser($parser,$pagecontent,$urlmodel);
  }/*}}}*/


  /** Utilities **/

  function generic(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    $this->syslog( __FUNCTION__, __LINE__, "(marker) " . $urlmodel->get_url() );
    $this->common_unhandled_page_parser($parser,$pagecontent,$urlmodel);
  }/*}}}*/

  function extract_formaction(& $parser, & $urlmodel, $debug_method = FALSE) {/*{{{*/

    $paginator_form  = $this->extract_form($parser->get_containers());
    if ( $debug_method ) $this->syslog( __FUNCTION__, __LINE__, "(marker) Forms found: " . count($paginator_form) );
    $paginator_attrs    = $paginator_form[0]['attrs'];
    $paginator_controls = $paginator_form[0]['children'];
    if ( $debug_method ) $this->syslog( __FUNCTION__, __LINE__, "(marker) Child controls: " . count($paginator_controls) );

    $link        = array('url' => $paginator_attrs['ACTION']);
    $parent      = UrlModel::parse_url($urlmodel->get_url());
    $form_action = UrlModel::normalize_url($parent, $link);

    if ( $debug_method ) {
      $this->syslog( __FUNCTION__, __LINE__, "(marker) --- -- - - Target action: {$form_action}" );
      $this->recursive_dump($paginator_form,'(warning) - -- --- FORM --');
      $this->recursive_dump($paginator_controls,'(warning) --- --- CONTROLS');
    }
    $returnset = $parser->extract_form_controls($paginator_controls);
    if ( is_null($returnset['select_options']) ) {
      $returnset['select_options'] = array();
      $returnset['select_name'] = NULL;
    }
    // $this->recursive_dump($returnset,'(warning)');
    $returnset['action'] = $form_action;
    return $returnset;
  }/*}}}*/

  function member_uuid_handler(array & $json_reply, UrlModel & $url, $member_uuid) {/*{{{*/
    $member = new SenatorDossierModel();
    $member->fetch( $member_uuid, 'member_uuid');
    if ( $member->in_database() ) {
      $image_content_type = $url->get_content_type();
      $image_content = base64_encode($url->get_pagecontent());
      $member_avatar_base64 = "data:{$image_content_type};base64,{$image_content}";
      $json_reply['altmarkup'] = $member_avatar_base64;
      if ( 'false' == $this->filter_post('no_replace','false') ) {
        $this->syslog(__FUNCTION__,__LINE__, "(marker) Replacing {$member_uuid} avatar: {$json_reply['altmarkup']}");
        $member->set_avatar_image($member_avatar_base64)->stow();
      }
      $this->syslog(__FUNCTION__,__LINE__, "(marker) Sending member {$member_uuid} avatar: {$json_reply['altmarkup']}");
    }
  }/*}}}*/

  function method_cache_emit(& $parser) {/*{{{*/
    $this->method_cache_filename = md5(__FUNCTION__ . $parser->trigger_linktext);
    $this->method_cache_filename = "./cache/{$this->subject_host_hash}-{$this->method_cache_filename}.generated";
    if ( C('ENABLE_GENERATED_CONTENT_BUFFERING') ) {
      if ( $parser->from_network && file_exists($this->method_cache_filename) ) unlink($this->method_cache_filename);
      else if ( file_exists($this->method_cache_filename) ) {
        $this->syslog( __FUNCTION__, __LINE__, "Retrieving cached markup for " . $urlmodel->get_url() );
        $pagecontent = file_get_contents($this->method_cache_filename);
        return;
      }
    }
  }/*}}}*/

  function terminal_method_cache_stow(& $parser, & $pagecontent) {/*{{{*/
    if ( C('ENABLE_GENERATED_CONTENT_BUFFERING') ) {
      file_put_contents($this->method_cache_filename, $pagecontent);
    }
  }/*}}}*/

  function must_custom_parse(UrlModel & $url) {/*{{{*/
    return ( 
      // PDF links intercepted by the parser class
      1 == preg_match('@http://www.senate.gov.ph/republic_acts/(.*).pdf@i', $url->get_url()) ||
      1 == preg_match('@http://www.senate.gov.ph/lisdata/(.*).pdf@i', $url->get_url()) ||
      // Normal markup pages on senate.gov.ph
      1 == preg_match('@q=SBN-([0-9]*)@i', $url->get_url()) ||
      1 == preg_match('@http://www.senate.gov.ph/lis/bill_res.aspx\?congress=([0-9]*)&q=HBN-([0-9]*)@i', $url->get_url()) ||
      1 == preg_match('@http://www.senate.gov.ph/lis/leg_sys.aspx\?congress=([0-9]*)&type=(bill|resolution)&p=([0-9]*)@i', $url->get_url()) ||
      1 == preg_match('@http://www.senate.gov.ph/lis/journal.aspx\?congress=([0-9]*)&session=([0-9RS]*)&q=([0-9]*)@i', $url->get_url()) ||
      1 == preg_match('@http://www.senate.gov.ph/lis/committee_rpt.aspx\?congress=([0-9]*)&q=([0-9]*)@i', $url->get_url())
    );
  }/*}}}*/

  function execute_document_form_traversal(
    $prefix       , & $senate_document_parser, & $parser    ,
    & $pagecontent, & $urlmodel              , & $action_url,
    & $faux_url   , $debug_method = FALSE
  ) {/*{{{*/

    // Enable this block to display debugging messages when a network fetch is requested.
    if (0) if ( property_exists($parser,'from_network') && $parser->from_network ) {
      $debug_method = TRUE;
    }

    $form_attributes = $this->extract_formaction($senate_document_parser, $urlmodel, $debug_method);

    // The form action URL is assumed to be the parent URL of all relative URLs on the page
    $target_action_url      = nonempty_array_element($form_attributes,'action');

    if ( empty($target_action_url) ) {
      $this->syslog(__FUNCTION__,__LINE__,"(marker) - - No FORM action extracted from document. faux_url = '{$faux_url}', action_url = '{$action_url}', invoked for " . (is_a($urlmodel,'UrlModel') ? $urlmodel->get_url() : '<Unknown URL>')  );
      return FALSE;
    }

    $target_action = UrlModel::parse_url($target_action_url);
    $prefix_hyphen = 0 < strlen($prefix) ? '-' : '';

    if ( !(1 == preg_match('@(.*)' . $prefix . $prefix_hyphen . '([0-9]*)(.*)@', $target_action['query'])) ) {
      $this->syslog(__FUNCTION__,__LINE__,"(marker) Unable to comprehend query part '{$target_action['query']}' of URL '" . (is_a($action_url,'UrlModel') ? $action_url->get_url() : '<Unknown URL>') . "'");
      $this->recursive_dump($target_action,"(marker) -- - --");
      return FALSE;
    }

    $action_url             = new UrlModel();
    $action_url->fetch(UrlModel::get_url_hash($target_action_url),'urlhash');

    if ( !$action_url->in_database() ) $action_url->set_url($target_action_url,FALSE);

    $target_congress        = $urlmodel->get_query_element('congress');

    if ( $debug_method ) $this->syslog(__FUNCTION__,__LINE__,"(marker) Normal parse");

    $sbn_regex_result       = preg_replace('@(.*)' . $prefix . '-([0-9]*)(.*)@', $prefix . '-$2',$target_action['query']);

    $target_query           = explode('&',$target_action['query']);
    // Decorate the action URL to create a fake caching target URL name.
    $target_query[]         = "metaorigin=" . $urlmodel->get_urlhash();
    $target_action['query'] = join('&', $target_query);
    $faux_url               = UrlModel::recompose_url($target_action);
    $faux_url               = new UrlModel($faux_url,TRUE);
    $not_in_database        = !$faux_url->in_database();
    $faux_url_in_db         = $not_in_database ? "fetchable" :  "in DB";

    if ( $debug_method ) $this->syslog( __FUNCTION__, __LINE__, "(marker) Real post action {$sbn_regex_result} URL {$action_url} Faux cacheable {$faux_url_in_db} url: {$faux_url}" );

    // If the full document hasn't yet been fetched, execute a fake POST
    if ( $not_in_database || $parser->from_network ) {/*{{{*/
      if ( $debug_method ) $this->syslog( __FUNCTION__, __LINE__, "(marker) Faux cacheable url: {$faux_url} -> {$form_attributes['action']}" );
      $form_controls = $form_attributes['form_controls'];
      $form_controls['__EVENTTARGET'] = 'lbAll';
      $form_controls['__EVENTARGUMENT'] = NULL;
      if ( property_exists( $parser, 'network_fetch_skip_get' ) && $parser->network_fetch_skip_get ) {
        $form_controls['_'] = 'SKIPGET';
      }
      $save_faux_url = $faux_url->get_url();
      if ( $debug_method ) {
        $this->syslog(__FUNCTION__, __LINE__, "(marker) --- - -- FORM controls to be sent");
        $this->recursive_dump($form_controls, "(marker) --- - --");
      }
      $successful_fetch = $this->perform_network_fetch( 
        $faux_url           , $urlmodel->get_url(), $form_attributes['action'],
        $faux_url->get_url(), $form_controls      , $debug_method
      );
      if ( $successful_fetch ) {
        // Switch back to the cached URL
        if ( $debug_method ) {
          $this->syslog( __FUNCTION__, __LINE__, "(marker) Faux url after fetch: {$faux_url}" );
          $this->syslog( __FUNCTION__, __LINE__, "(marker)           Cached URL: {$save_faux_url}" );
        }
        $faux_url->set_url($save_faux_url,TRUE);
        $pagecontent = $faux_url->get_pagecontent();
      } else {
        $parser->json_reply = array('retainoriginal' => TRUE);
        $pagecontent = "Failed to fetch response to form submission to {$action_url}";
        return FALSE;
      }
    }/*}}}*/
    else {/*{{{*/
      if ( $debug_method ) $this->syslog( __FUNCTION__, __LINE__, "(marker) Skipping network fetch: {$faux_url} -> {$form_attributes['action']}" );
      $pagecontent = $faux_url->get_pagecontent();
    }/*}}}*/
    return array_filter(array(
      'sbn_regex_result' => $sbn_regex_result,
      'target_congress'  => $target_congress,
    ));
  }/*}}}*/

}
