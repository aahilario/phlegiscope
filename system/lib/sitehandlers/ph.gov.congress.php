<?php

class CongressGovPh extends LegiscopeBase {
  
  function __construct() {
    $this->syslog( __FUNCTION__, '-', 'Using site-specific container class' );
    parent::__construct();
  }

  function seek() {
    $cache_force = $this->filter_post('cache');
    $json_reply  = parent::seek();
    $response    = json_encode($json_reply);
    header('Content-Type: application/json');
    header('Content-Length: ' . strlen($response));
    $this->flush_output_buffer();
    if ( C('ENABLE_GENERATED_CONTENT_BUFFERING') || ($cache_force == 'true') ) {
      file_put_contents($this->seek_cache_filename, $response);
    }
    echo $response;
    exit(0);
  }

  /** Named handlers **/

  function generic(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    // 
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
    $pagecontent = (join('',$common->get_filtered_doc()));
    $parser->json_reply = array('retainoriginal' => TRUE);

  }/*}}}*/

  function representative_bio_parser(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/

    $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked for " . $urlmodel->get_url() );
    // Representative bio parser

    $p    = new CongressMemberBioParseUtility();
    $m    = new RepresentativeDossierModel();
    $comm = new CongressionalCommitteeDocumentModel();
    $hb   = new HouseBillDocumentModel();
    $url  = new UrlModel();

    $p->
      set_parent_url($urlmodel->get_url())->
      parse_html(
        $urlmodel->get_pagecontent(),
        $urlmodel->get_response_header()
      );

    $urlmodel->ensure_custom_parse();

    $this->syslog( __FUNCTION__, __LINE__, "(marker) Content length before parse: " . strlen($pagecontent) );

    $pagecontent = '';

    $m->fetch($urlmodel->get_url(), 'bio_url');

    if ( !$m->in_database() || $parser->from_network ) {/*{{{*/

      $p->stow_parsed_representative_info($m,$urlmodel);

    }/*}}}*/

    $this->syslog( __FUNCTION__, __LINE__, "(marker) E ------------------------------ " );
    $member               = $p->get_member_contact_details();
    $contact_items        = $m->get_contact_json();
    $member_avatar_base64 = $m->get_avatar_image();
    $member_uuid          = $m->get_member_uuid();
    $member['fullname']   = $m->get_fullname();
    $member['avatar']     = $m->get_avatar_url();

    extract($p->extract_committee_membership_and_bills());

    $membership_role   = $p->generate_committee_membership_markup($membership_role);
    $legislation_links = array();
    $bills             = $p->generate_legislation_links_markup($m, $legislation_links, $bills);
    $legislation_links = join('',$legislation_links);

    // To trigger automated fetch, crawl this list after loading the entire dossier doc;
    // then find the next unvisited dossier entry
    $legislation_links = <<<EOH
<ul id="house-bills-by-rep" class="link-cluster">{$legislation_links}</ul>
EOH;
    if ( $urlmodel->is_custom_parse() ) {
      extract($p->generate_linkset($urlmodel->get_url()));
      $parser->linkset = $linkset; 
    }
    $parser->linkset = "{$legislation_links}{$parser->linkset}";

    $pagecontent = <<<EOH
<div class="congress-member-summary">
  <h1 class="representative-avatar-fullname">{$member['fullname']}</h1>
  <img class="representative-avatar" id="image-{$member_uuid}" src="{$member_avatar_base64}" alt="{$member['fullname']}" />
  <input type="hidden" class="representative-avatar-source" name="image-ref" id="imagesrc-{$member_uuid}" value="{$member['avatar']}" />
  <span class="representative-avatar-head">Role: {$contact_items['district']} {$contact_items['role']}</span>
  <span class="representative-avatar-head">Term: {$contact_items['term']}</span>
  <hr/>
  <span class="representative-avatar-head">Room: {$contact_items['room']}</span>
  <span class="representative-avatar-head">Phone: {$contact_items['phone']}</span>
  <span class="representative-avatar-head">Chief of Staff: {$contact_items['cos']}</span>
</div>
<hr/>

<div class="congress-member-summary">
  <hr/>
  <div class="congress-member-roles">
{$membership_role}
  </div>
  <hr/>
  <div class="congress-legislation-tally link-cluster">
{$bills}
  </div>
</div>

<script type="text/javascript">
var hits = 0;
function crawl_dossier_links() {
  if ( $('#spider').prop('checked') ) {
    $('ul[id=house-bills-by-rep]').find("a[class*=uncached]").first().each(function(){
      var self = $(this);
      load_content_window(
        $(this).attr('href'),
        $('#seek').prop('checked'),
        $(this),
        { url : $(this).attr('href'), async : true },
        { success : function (data, httpstatus, jqueryXHR) {
            std_seek_response_handler(data, httpstatus, jqueryXHR);
            $(self).removeClass('uncached').addClass('cached');
            setTimeout((function() {crawl_dossier_links();}),200);
          }
        }
      );
    });
    hits = 0;
    $('ul[id=house-bills-by-rep]').find("a[class*=uncached]").each(function(){
      hits++;
    });
    if (hits == 0) {
      $('div[id=congresista-list]')
        .find('a[class*=seek]')
        .first()
        .each(function() { 
          $('#doctitle').html('Loading '+$(this).attr('href'));
          $(this).removeClass('seek').addClass('cached'); 
          $(this).click();
        });
    } else {
      $('#doctitle').html('Remaining: '+hits);
    }
  }
}

$(function(){
  update_representatives_avatars();
  setTimeout((function(){
    crawl_dossier_links();
  }),2000);
});
</script>
EOH;

    $parser->json_reply = array('retainoriginal' => TRUE);

    $this->syslog( __FUNCTION__, __LINE__, "(marker) Final content length: " . strlen($pagecontent) );
    $this->syslog( __FUNCTION__, __LINE__, "(marker) I ------------------------------ " );

    $pagecontent = utf8_encode($pagecontent);

  }/*}}}*/

  function seek_by_pathfragment_f0923b5f3bb0f191dedd93e16d3658ff(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    // Handler for metadata path
    // http://www.congress.gov.ph/legis/search/hist_show.php?save=1&journal=J069&switch=0&bill_no=HB03933&congress=15
    $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked for " . $urlmodel->get_url() );
    $common      = new CongressCommonParseUtility();
    $common->
      set_parent_url($urlmodel->get_url())->
      parse_html($urlmodel->get_pagecontent(),$urlmodel->get_response_header());

    $hb = new HouseBillDocumentModel();

    $this->recursive_dump(($meta = $common->get_containers(
      'children[tagname*=body]{[text]}',0
    )), "(marker) --- -- --- --");

    $pagecontent = str_replace('[BR]','<br/>',join('<br/>',array_element($meta,0,array())));

    $urlmodel->ensure_custom_parse();

    $parser->json_reply = array('retainoriginal' => TRUE);
  }/*}}}*/

  function seek_congress_memberlist(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    // http://www.congress.gov.ph/members 
    $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked for " . $urlmodel->get_url() );
    $common = new CongressCommonParseUtility();
    $member = new RepresentativeDossierModel();

    $common->debug_tags = FALSE;
    $common->
      set_parent_url($urlmodel->get_url())->
      parse_html(
        $pagecontent, // FIXME: Generic parser does not break parsing
        //$urlmodel->get_pagecontent(), // FIXME: but this does
        $urlmodel->get_response_header()
      );

    $pagecontent      = array();
    $surname_initchar = NULL;
    $section_break    = NULL;
    $districts        = array();

    $this->recursive_dump(($member_list = $common->get_containers(
      'children[tagname=div][id*=content_body_right|content_body_left|i]'
    )),'Extracted rows');

    $lc_surname_initchar = '';

    foreach ( $member_list as $item ) {/*{{{*/
      foreach( $item as $tag ) {/*{{{*/
        if ( !array_key_exists('url',$tag) ) continue;
        $bio_url = $tag['url'];
        $member->fetch($bio_url,'bio_url');

        $cdata = $tag['text'];

        if ( is_null($member->get_firstname()) ) {/*{{{*/
          $parsed_name = $member->parse_name($cdata);
          $member->
            set_fullname(utf8_decode($cdata))->
            set_firstname(utf8_decode($parsed_name['given']))->
            set_mi($parsed_name['mi'])->
            set_surname(utf8_decode($parsed_name['surname']))->
            set_namesuffix(utf8_decode($parsed_name['suffix']))->
            stow();
        }/*}}}*/

        $name_regex = '@^([^,]*),(.*)(([A-Z]?)\.)*( (.*))@i';
        $name_match = array();
        preg_match($name_regex, $cdata, $name_match);
        $name_match = array(
          'first-name'     => trim($name_match[2]),
          'surname'        => trim($name_match[1]),
          'middle-initial' => trim($name_match[6]),
        );
        $name_index = "{$name_match['surname']}-" . UrlModel::get_url_hash($tag['url']);
        $district = $tag['title']; 
        if ( empty($district) ) $district = 'Zz - Party List -';
        else {
          $district_regex = '@^([^,]*),(.*)@';
          $district_match = array();
          preg_match($district_regex, $district, $district_match);
          // $this->recursive_dump($district_match,__LINE__);
          $sub_district = trim($district_match[2]);
          $district = trim($district_match[1]);
        }
        if ( strlen($district) > 0 && !array_key_exists($district, $districts) ) $districts[$district] = array('00' => "<h1>" . preg_replace('@^Zz@','', $district) . "</h1>"); 

        $fullname = "{$name_match['first-name']} {$name_match['middle-initial']} {$name_match['surname']}";
        $surname_first = trim(strtoupper(substr($name_match['surname'],0,1)));
        if ( !(strlen($surname_first) > 0) ) continue;
        ////////
        //if ( is_null($member->get_avatar_image()) ) continue;
        ////////
        if ( is_null($surname_initchar) ) $surname_initchar = $surname_first;
        if ( $surname_first != $surname_initchar ) {/*{{{*/
          // Insert a 
          $surname_initchar = $surname_first;
          $lc_surname_initchar = strtolower($surname_first);
          $section_break = <<<EOH
<br/><h1 class="surname-reset-children" id="surname-reset-{$lc_surname_initchar}">{$surname_first}</h1>
EOH;
        }/*}}}*/
        $urlhash = UrlModel::get_url_hash($bio_url);
        $link_attributes = array("human-element-dossier-trigger");
        if ( $member->in_database() ) $link_attributes[] = "cached";
        else $link_attributes[] = 'trigger';
        $link_attributes[] = "surname-cluster-{$lc_surname_initchar}";
        $link_attributes = join(' ', $link_attributes);
        $candidate_entry = <<<EOH
<span><a href="{$bio_url}" class="{$link_attributes}" id="{$urlhash}">{$fullname}</a></span>

EOH;
        $pagecontent[$name_index] = "{$section_break}{$candidate_entry}";
        $districts[$district][$name_index] = $candidate_entry; 
        $section_break = NULL;
      }/*}}}*/
    }/*}}}*/

    $districts = array_map(create_function('$a', 'ksort($a); return join("<br/>",$a);'), $districts);

    ksort($pagecontent);
    ksort($districts);

    $pagecontent = join('<br/>', $pagecontent);
    $districts = join('<br/>', $districts);

    $cache_state_reset_link = <<<EOH

EOH;
    $pagecontent = <<<EOH
<div class="congresista-dossier-list">
  <input class="reset-cached-links" type="button" value="Clear"> 
  <input class="reset-cached-links" type="button" value="Reset"> 
  <div class="float-left link-cluster" id="congresista-list">{$pagecontent}</div>
  <div class="float-left link-cluster">{$districts}</div>
</div>
<script type="text/javascript">
$(function(){
  $('input[class=reset-cached-links]').click(function(e) {
    if ($(this).val() == 'Reset') {
      $(this).parent().first().find('a').each(function(){
        $(this).removeClass('cached').removeClass('uncached').addClass("seek");
      });
    } else {
      $(this).parent().first().find('a').each(function(){
        $(this).removeClass('uncached').removeClass('seek').addClass("cached");
      });
    } 
  });
  $('h1[class=surname-reset-children]').click(function(e){
    var linkset = $(this).attr('id').replace(/^surname-reset-/,'surname-cluster-');
    if ( $(this).hasClass('on') ) {
      $(this).removeClass('on');
      $('a[class*='+linkset+']').each(function(){
        $(this).removeClass('cached').removeClass('uncached').addClass("seek");
      });
    } else {
      $(this).addClass('on');
      $('a[class*='+linkset+']').each(function(){
        $(this).removeClass('uncached').removeClass('seek').addClass("cached");
      });
    }
  });
  initialize_dossier_triggers();
});
</script>
<div id="human-element-dossier-container" class="alternate-original half-container"></div>
EOH;
/*
 * Javascript fragment to trigger cycling
 *   setTimeout(function(){
 *   $('div[class*=float-left]').first().find('a[class*=trigger]').removeClass('trigger').click();
 *   },1000);
 */

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
        default: 
          $this->syslog( __FUNCTION__, __LINE__, "(warning) Unhandled URL #" . $urlmodel->get_id() . " {$match_urlpart[1]} {$urlmodel}" );
      }
    }

    $common      = new CongressCommonParseUtility();
    $ra_linktext = $parser->trigger_linktext;
    $common->
      set_parent_url($urlmodel->get_url())->
      parse_html($urlmodel->get_pagecontent(),$urlmodel->get_response_header());
    $pagecontent = join('',$common->get_filtered_doc());

  }/*}}}*/

  function seek_postparse_jnl(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/

    $debug_method = TRUE;

    $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked for " . $urlmodel->get_url() );

    $document_parser      = new CongressJournalCatalogParseUtility();
    $document_parser      = new CongressCommonParseUtility();
    $ra_linktext = $parser->trigger_linktext;
    $document_parser->
      set_parent_url($urlmodel->get_url())->
      parse_html(
        $urlmodel->get_pagecontent(),
        $urlmodel->get_response_header()
      );

    ///////////////////////////////////////////////////////////////////////////////////////////
    // Extract list of Journal PDF URLs
    $this->recursive_dump(($registry = $document_parser->get_containers(
      'children[tagname*=div][class*=padded]'
    )),"(barker)");

    array_walk($registry,create_function(
      '& $a, $k, $s', '$s->filter_nested_array($a,"url,text[url*=download/journal|i]",0);'
    ), $document_parser);

    $registry = array_element(array_values(array_filter($registry)),0,array());

    $this->recursive_dump($registry,"(marker) -- A --");

    $u = new UrlModel();
    $ue = new UrlEdgeModel();
    while ( 0 < count($registry) ) {
      $n = 0;
      $subjects = array();
      while ( $n < 10 && (0 < count($registry)) ) {/*{{{*/// Get 10 URLs
        $e = array_pop($registry);
        $e = array_element($e,'url');
        if ( is_null($e) ) continue;
        $urlhash = UrlModel::get_url_hash($e);
        $subjects[$urlhash] = $e;
        $n++;
      }/*}}}*/
      if ( $debug_method ) {
        $this->syslog(__FUNCTION__,__LINE__,"(marker) Remaining: " . count($registry));
        $this->recursive_dump($subjects,"(marker) -- A --");
      }
      $u->where(array('AND' => array(
        'urlhash' => array_keys($subjects)
      )))->recordfetch_setup();

      // Build list of items for which to check the presence of an edge
      $extant = array();
      $url = array();
      while ( $u->recordfetch($url) ) {
        $extant[$url['id']] = array(
          'url'   => $subjects[$url['urlhash']],
          'urlhash' => $url['urlhash'],
        ); // Just the URL, so that we can store UrlEdge nodes as needed
      }
      if ( $debug_method ) {
        $this->syslog(__FUNCTION__,__LINE__,"(marker) Extant: " . count($extant));
        $this->recursive_dump($extant,"(marker) -- E --");
      }
      if ( 0 < count($extant) ) {
        ksort($extant,SORT_NUMERIC);
        // Find already existing edges
        $ue->where(array('AND' => array(
          'a' => $urlmodel->get_id(),
          'b' => array_keys($extant)
        )))->recordfetch_setup();
        $edge = array();
        while ( $ue->recordfetch($edge) ) {
          $extant_in_list = array_key_exists($edge['b'], $extant) ? "extant" : "nonexistent";
          if ( $debug_method ) {
            $this->syslog(__FUNCTION__,__LINE__,"(marker) Skipping {$extant_in_list} edge ({$edge['b']},{$edge['a']})" );
          }
          if ( array_key_exists($edge['b'], $extant) ) $extant[$edge['b']] = NULL;
        }
        $extant = array_filter($extant);
        foreach ( $extant as $urlid => $urlinfo ) {
          $edgeid = $ue->set_id(NULL)->stow($urlmodel->get_id(), $urlid);
          $this->syslog(__FUNCTION__,__LINE__,"(marker) Edge stow result ID for {$urlid} {$urlinfo['url']} = (" . gettype($edgeid) . ") {$edgeid}");
        }
      }
    }
    ///////////////////////////////////////////////////////////////////////////////////////////

    $inserted_content = join('',$document_parser->get_filtered_doc());

    $document_parser->cluster_urldefs = $document_parser->generate_linkset($urlmodel->get_url(),'cluster_urls');

    $this->syslog(__FUNCTION__,__LINE__,"(marker) Cluster URLdefs: " . count($document_parser->cluster_urldefs) );
    $this->recursive_dump($document_parser->cluster_urldefs,"(marker)");

    $url_components = UrlModel::parse_url($urlmodel->get_url());
    $url_components['query'] = 'd=journals';
    $url_components['fragment'] = NULL;
    $url_components = array_filter($url_components);
    $session_select_baseurl = new UrlModel(UrlModel::recompose_url($url_components));
    
    // Alter the $congress_select list to convert it
    // into an array of Congress switching links
    $congress_select = $document_parser->extract_house_session_select($session_select_baseurl, FALSE, '[id*=form1]');
    $document_parser->filter_nested_array($congress_select,
      'markup[faux_url*=http://www.congress.gov.ph/download/index.php\?d=journals|i][optval*=.?|i]',0
    );
    $this->syslog(__FUNCTION__,__LINE__,"(marker) ----- ---- --- -- - - - Congress Session SELECT options");
    $this->recursive_dump($congress_select,"(marker)");


    $house_journals = new HouseJournalDocumentModel();

    $batch_regex = 'http://www.congress.gov.ph/download/journals_([0-9]*)/(.*)';
    $child_collection = $this->find_incident_pages($urlmodel, $batch_regex, NULL);
    if ( $debug_method ) {
      $this->syslog(__FUNCTION__,__LINE__,"(marker) Result of incident page search: " . count($child_collection) . ", with regex {$batch_regex}");
      $this->recursive_dump($child_collection,"(marker) - - - - - -");
    }

    krsort( $child_collection, SORT_NUMERIC );

    $pagecontent = $document_parser->generate_congress_session_item_markup(
      $urlmodel,
      $child_collection,
      array(),
      NULL, // query_fragment_filter = NULL => No reordering by query fragment, link text taken from markup 
      $congress_select // Array : Links for switching Congress Session 
    );

    $pagecontent .= <<<EOH
<div class="alternate-original alternate-content" id="senate-journal-block">
{$inserted_content}
</div>
EOH;

  }/*}}}*/

  function seek_postparse_ra(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/

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

    $common        = new CongressCommonParseUtility();
    $ra_linktext   = $parser->trigger_linktext;

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
    $parent_url['query'] = 'd=ra';
    $parent_url   = UrlModel::recompose_url($parent_url,array(),FALSE);
    $test_url     = new UrlModel();

    $pagecontent = utf8_encode("{$replacement_content}<br/><hr/>");
    $urlmodel->increment_hits()->stow();

    $test_url->fetch($parent_url,'url');
    $page = $urlmodel->get_pagecontent();
    $test_url->set_pagecontent($page);
    $test_url->set_response_header($urlmodel->get_response_header())->increment_hits()->stow();

    $ra_listparser = new CongressRaListParseUtility();
    $ra_listparser->debug_tags = FALSE;
    $ra_listparser->set_parent_url($urlmodel->get_url())->parse_html(utf8_encode($page),$urlmodel->get_response_header());
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
    while ( 0 < count( $sn_stacks ) ) {
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
    }
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
    // House Bills
    $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked for " . $urlmodel->get_url() );

    // Custom content, generic parser does not handle the bills text content
    $this->syslog( __FUNCTION__, __LINE__, "(warning) EMPTY HANDLER.  Length: " . $urlmodel->get_content_length() );

    // return;
    $cache_filename = md5(__FUNCTION__ . $parser->trigger_linktext);
    $cache_filename = "./cache/{$this->subject_host_hash}-{$cache_filename}.generated";
    if ( $parser->from_network ) unlink($cache_filename);
    else if ( C('ENABLE_GENERATED_CONTENT_BUFFERING') ) {
      if ( $parser->from_network ) unlink($cache_filename);
      else if ( file_exists($cache_filename) ) {
        $this->syslog( __FUNCTION__, __LINE__, "(marker) Retrieving cached markup for " . $urlmodel->get_url() );
        $pagecontent = file_get_contents($cache_filename);
        return;
      }
    }

    $filter_url    = new UrlModel();
    $house_bill    = new HouseBillDocumentModel();
    $hb_listparser = new CongressHbListParseUtility();
    $hb_listparser->debug_tags = FALSE;
    $hb_listparser->
      set_parent_url($urlmodel->get_url())->
      parse_html(
        $urlmodel->get_pagecontent(),
        $urlmodel->get_response_header()
      );

    $this->syslog( __FUNCTION__, __LINE__, "(marker) Elements: " . count($hb_listparser->get_containers()) );
    $pagecontent = NULL;
    $house_bills = array();
    $counter = 0;

    $containers = $hb_listparser->get_containers();

    $n = 20;
    foreach ( $containers as $container_id => $container ) {/*{{{*/
      $n--;
      if ( $n == 0 ) break;
      if ( !array_key_exists('children', $container) || !is_array($container['children']) ) continue;
      $entries                  = $container['children'];
      $container[$container_id] = NULL;
      $bill_head                = NULL;

      foreach ( $entries as $entry_key => $container ) {/*{{{*/
        if ( array_key_exists('bill-head', $container) ) {/*{{{*/

          // Dump existing bill record to stream
          if ( !is_null($bill_head) && array_key_exists($bill_head, $house_bills) ) {/*{{{*/
            $hb = $house_bills[$bill_head];
            $url             = array_element($hb,'document-url');
            $urlhash         = UrlModel::get_url_hash($url);
            $hb['desc']      = array_element($hb,'title'); // $house_bill->get_description();
            $hb['bill-head'] = array_element($hb,'sn'); // $house_bill->get_sn();
            $hb['meta']      = NULL; // $house_bill->get_status(FALSE);

            $n = 0; //$house_bill->count(array('sn' => $bill_head));
            $cache_state = array('legiscope-remote');

            if ( $n == 1 ) $cache_state[] = 'cached';
            else {/*{{{*/
              $this->syslog(__FUNCTION__,__LINE__, "Stowing {$bill_head} {$url}");
              $now_time = time();
              $filter_url->fetch($urlhash,'urlhash');
              $searchable = $filter_url->in_database() ? 1 : 0; 
              $meta = array(
                'status'           => $hb['Status'],
                'principal-author' => $hb['Principal Author'],
                'main-committee'   => $hb['Main Referral'],
              );
              $house_bill->fetch($hb['sn'],'sn');
              $house_bill->
                set_url($url)->
                set_sn($bill_head)->
                set_title($hb['title'])->
                set_searchable($searchable)->
                set_create_time($now_time)->
                set_last_fetch($now_time)->
                set_status($meta)->
                stow();
            }/*}}}*/
            $cache_state = join(' ', $cache_state);
            $content = $house_bill->get_standard_listing_markup($hb['sn'], 'sn');
            if ( is_null($content) ) $content = <<<EOH
<div class="republic-act-entry">
<span class="republic-act-heading"><a href="{$url}" class="{$cache_state}" id="{$urlhash}">{$hb['bill-head']}</a></span>
<span class="republic-act-desc"><a href="{$url}" class="legiscope-remote" id="title-{$urlhash}">{$hb['desc']}</a></span>
<span class="republic-act-meta">Principal Author: {$hb['Principal Author']}</span>
<span class="republic-act-meta">Main Referral: {$hb['Main Referral']}</span>
<span class="republic-act-meta">Status: {$hb['Status']}</span>
</div>
EOH;
            // $pagecontent .= $content;
            $pagecontent .= <<<EOH
<span class="republic-act-heading"><a href="{$url}" class="{$cache_state}" id="{$urlhash}">{$hb['bill-head']}</a><br/>
EOH;
            unset($house_bills[$bill_head]);
          }/*}}}*/

          $bill_head = join('',$container['bill-head']);
          $house_bills[$bill_head] = array(
            'sn' => $bill_head,
          );
          continue;
        } /*}}}*/
        if ( is_null($bill_head) ) {
          unset($entries[$entry_key]);
          continue;
        }
        if ( array_key_exists('desc', $container) ) {
          $house_bills[$bill_head]['title'] = join('',$container['desc']);
          unset($entries[$entry_key]);
          continue;
        }
        if ( array_key_exists('meta', $container) ) {
          $matches = array();
          if ( 1 == preg_match('@^(Principal Author|Main Referral|Status):(.*)$@i',join('',$container['meta']), $matches) ) {
            $house_bills[$bill_head][$matches[1]] = $matches[2];
          }
        }
        if ( array_key_exists('attrs', $container) &&
          !array_key_exists('ONCLICK',$container['attrs']) &&
          array_key_exists('HREF',$container['attrs'])
        ) {
          $house_bills[$bill_head]['document-url'] = $container['attrs']['HREF'];
          $house_bills[$bill_head]['document-label'] = join('',$container['cdata']);
        }
        unset($entries[$entry_key]);
      }/*}}}*/

    }/*}}}*/
    // $pagecontent = join('',$hb_listparser->get_filtered_doc());
    // $this->recursive_dump($house_bills,__LINE__);

    if ( C('ENABLE_GENERATED_CONTENT_BUFFERING') ) {
      file_put_contents($cache_filename, $pagecontent);
    }

  }/*}}}*/


  /** Automatically matched parsers **/

  function seek_postparse_bypath_9f222d54cda33a330ffc7cd18e7ce27f(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    // http://www.congress.gov.ph/committees/search.php 
    $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked for " . $urlmodel->get_url() );
    $this->seek_postparse_9e8648ad99163238295d15cfa534be86($parser,$pagecontent,$urlmodel);
  }/*}}}*/

  function seek_by_pathfragment_b536fc060d348f720ee206f9d3131a5c(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    // http://www.congress.gov.ph/committees/search.php 
    $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked for " . $urlmodel->get_url() );
    $this->seek_postparse_9e8648ad99163238295d15cfa534be86($parser,$pagecontent,$urlmodel);
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
    // 
    $this->syslog( __FUNCTION__, __LINE__, "Invoked for " . $urlmodel->get_url() );
    $this->common_unhandled_page_parser($parser,$pagecontent,$urlmodel);

    $pagecontent = <<<EOH
<h1>Clearing cached links</h1>
<script type="text/javascript">
$(function(){
  $('ul[class*=link-cluster]').each(function(){
    $(this).find('a[class*=legiscope-remote]').each(function(){
      $(this).removeClass('cached');
    });
  });
  initialize_linkset_clickevents($('ul[class*=link-cluster]'),'li');
});
</script>
EOH;
  }/*}}}*/

  function seek_postparse_9e8648ad99163238295d15cfa534be86(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    // http://www.congress.gov.ph/committees/search.php?congress=15&id=A505

    $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked for " . $urlmodel->get_url() );
    $m    = new RepresentativeDossierModel();
    $p    = new CongressCommitteeListParseUtility();
    $comm = new CongressionalCommitteeDocumentModel();
    $link = new CongressionalCommitteeRepresentativeDossierJoin();

    $m->dump_accessor_defs_to_syslog();

    $p->set_parent_url($urlmodel->get_url())->parse_html($pagecontent,$urlmodel->get_response_header());

    $pagecontent = join('', $p->get_filtered_doc());

    $p->debug_operators = FALSE;
    $this->recursive_dump($containers = $p->get_containers(
      'children[tagname=div][id=main-ol]'
    ),"(----) ++ All containers");

    // We are able to extract names of committees and current chairperson.
    if ( 0 < count($containers) ) {
      $committees = array();
      $pagecontent = '';
      foreach ( $containers as $container ) {/*{{{*/
        $pagecontent .= "<div>";
        foreach ( $container as $tag ) {
          if ( array_key_exists('url', $tag) ) {
            $hash = UrlModel::get_url_hash($tag['url']);
            $pagecontent .= <<<EOH
<a href="{$tag['url']}" class="legiscope-remote">{$tag['text']}</a>
EOH;
            $committees[$hash] = array(
              'url' => $tag['url'],
              'name' => $tag['text'],
              'chairperson' => NULL,
            );
            continue;
          }
          $name = $tag['text'];
          $tag['text'] = $m->replace_legislator_names_hotlinks($name);
          $committees[$hash]['chairperson'] = $name;
          if ( is_null($name['firstname']) ) {
            $parsed_name = $m->parse_name( $name['original'] );
            $committees[$hash]['original'] = $parsed_name;
            $m->fetch($name['id'],'id');
            $m->
              set_fullname($name['original'])-> 
              set_firstname(utf8_decode($parsed_name['given']))->
              set_mi($parsed_name['mi'])->
              set_surname(utf8_decode($parsed_name['surname']))->
              set_namesuffix(utf8_decode($parsed_name['suffix']))->
              stow();
          }
          $pagecontent .= <<<EOH
<span class="representative-name">{$tag['text']}</span><br/>
EOH;
        }
        $pagecontent .= "</div>";
      }/*}}}*/
      $this->recursive_dump($committees,"(marker) -- -- --");
    }

    $pagecontent = utf8_encode($pagecontent);

    $parser->json_reply = array('retainoriginal' => TRUE);
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
      1 == preg_match('@http://www.congress.gov.ph/members/search.php\?(.*)@i', $url->get_url())  || 
      1 == preg_match('@http://www.congress.gov.ph/legis/search/hist_show.php\?@i', $url->get_url())
    );
  }/*}}}*/


}
