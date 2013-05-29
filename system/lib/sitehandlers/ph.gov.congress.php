<?php

class CongressGovPh extends LegiscopeBase {
  
  function __construct() {
    $this->syslog( __FUNCTION__, '-', 'Using site-specific container class' );
    parent::__construct();
  }

  function seek() {/*{{{*/
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
  }/*}}}*/

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
    $pagecontent = str_replace('[BR]','<br/>',join('',$common->get_filtered_doc()));
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

    $urlmodel->ensure_custom_parse();
    $common->debug_tags = FALSE;
    $common->
      set_parent_url($urlmodel->get_url())->
      parse_html(
        //$pagecontent, // FIXME: Generic parser does not break parsing
        $urlmodel->get_pagecontent(), // FIXME: but this does
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
            set_fullname($cdata)->
            set_firstname($parsed_name['given'])->
            set_mi($parsed_name['mi'])->
            set_surname($parsed_name['surname'])->
            set_namesuffix($parsed_name['suffix'])->
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
        $surname_first = strtoupper(substr(trim($name_match['surname']),0,1));
        if ( !(strlen($surname_first) > 0) ) continue;
        if ( is_null($surname_initchar) ) $surname_initchar = $surname_first;
        if ( $surname_first != $surname_initchar ) {/*{{{*/
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

    $debug_method = FALSE;

    $urlmodel->ensure_custom_parse();

    $target_congress = $urlmodel->get_query_element('congress');

    if ( !empty($parser->metalink_url) ) {/*{{{*/
      $given_url = $urlmodel->get_url();
      $current_id = $urlmodel->get_id();
      $urlmodel->fetch($parser->metalink_url,'url');
      if ( is_null($target_congress) ) $target_congress = $urlmodel->get_query_element('congress');
      $this->syslog( __FUNCTION__, __LINE__, "(marker) Loading content of fake URL " . ($urlmodel->in_database() ? "OK" : "FAILED") . " " . $urlmodel->get_url() );
      $urlmodel->
        set_id($current_id)-> // Need this for establishing JOINs by URL id.
        set_url($given_url,FALSE);
    }/*}}}*/

    $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked for Congress '{$target_congress}' " . $urlmodel->get_url() . "; fake URL = {$parser->metalink_url}" );

    $document_parser = new CongressJournalCatalogParseUtility();

    $ra_linktext = $parser->trigger_linktext;
    $document_parser->
      set_parent_url($urlmodel->get_url())->
      parse_html(
        $urlmodel->get_pagecontent(),
        $urlmodel->get_response_header()
      );

    ///////////////////////////////////////////////////////////////////////////////////////////
    // Use parsed lists of URLs and journal SNs to generate alternate markup 

    $inserted_content = array();
    $cacheable_entries = array();
    $journal_entry_regex = '(Journal )?([^0-9]*)([0-9]*)([^,]*), (([0-9]*)-([0-9]*)-([0-9]*))';
    foreach ( $document_parser->filtered_content as $title => $entries ) {/*{{{*/
      $matches = array();
      preg_match("@{$journal_entry_regex}@i", $title, $matches);
      $sn = array_element($matches,3);
      if ( empty($sn) ) $sn = array_element($matches,2);
      $title = trim("{$matches[1]}{$matches[2]}{$matches[3]}{$matches[4]}");
      $date = trim("{$matches[6]}/{$matches[7]}/{$matches[8]}");
      $matches = array(
        $title => array(
          'entries' => array(),
          'title' => $title,
          'metadata' => array(
            'sn'            => $sn,
            'publish_date'  => array_element($matches,5),
            'publish_year'  => array_element($matches,6),
            'publish_month' => array_element($matches,7),
            'publish_day'   => array_element($matches,8),
          ),
          'm' => $matches
        )
      );
      foreach ( $entries as $typelabel => $entry ) {
        $typelabel = preg_replace('@(.*)(PDF|HTML)(.*)@i','$2',$typelabel);
        $matches[$title]['entries'][$typelabel] = $entry;
      }
      // If there is only one type of document link,
      // a single link is generated with the document type indicated.
      if ( 1 == count($matches[$title]['entries']) ) {
        list( $type, $pager_url ) = each( $matches[$title]['entries'] );
        $inserted_content[] = <<<EOH
<li><a href="{$pager_url}" class="legiscope-remote">{$title}</a> {$date} ({$type})</li>
EOH;
      }
      else {/*{{{*/
        ksort($matches[$title]['entries']);
        list( $type, $pager_url ) = each( $matches[$title]['entries'] );
        $markup = <<<EOH
<li><a href="{$pager_url}" class="legiscope-remote async">{$title}</a> {$date} ({$type}) {placeholder}</li>
EOH;
        list( $type, $pager_url ) = each( $matches[$title]['entries'] );
        $secondary = <<<EOH
(<a href="{$pager_url}" class="legiscope-remote">{$type}</a>)
EOH;
        $markup = str_replace('{placeholder}',$secondary, $markup); 
        $inserted_content[] = $markup;
      }/*}}}*/
      // unset($matches[$title]['m']);
      $cacheable_entries[$title] = $matches[$title];
    }/*}}}*/
    $inserted_content = join(' ',$inserted_content);
    $inserted_content = <<<EOH
<div class="linkset">
<ul class="link-cluster">
{$inserted_content}
</ul>
</div>
EOH;
    if ( $debug_method ) {
      $this->recursive_dump($document_parser->filtered_content,"(marker) - - - -");
    }

    ///////////////////////////////////////////////////////////////////////////////////////////
    // Test cacheable entries and store those that aren't yet recorded in
    // HouseJournalDocumentModel backing store. 

    $house_journals = new HouseJournalDocumentModel(); 

    $session_tag = $parser->trigger_linktext;

    if ( (0 < intval($target_congress)) && (0 < intval($session_tag)) && ("{$session_tag}" == trim("".intval($session_tag)."")) && (0 < count($cacheable_entries)) ) {/*{{{*/
      while ( 0 < count($cacheable_entries) ) {
        $n = 0;
        $batch = array();
        while ( $n++ < 10 && ( 0 < count($cacheable_entries) ) ) {
          $entry = array_pop($cacheable_entries);
          $batch[array_element($entry,'title')] = $entry;
        }
        $query_parameters = array('AND' => array(
          'title'           => array_keys($batch), // array_map(create_function('$a', 'return array_element($a["metadata"],"sn");'), $batch), // array_keys($batch),
          'session_tag'     => $session_tag,
          'target_congress' => $target_congress
        ));
        if ( $debug_method ) {
          $this->syslog(__FUNCTION__,__LINE__,"(marker) - - - - Query parameters (target congress {$target_congress}):");
          $this->recursive_dump($query_parameters,"(marker) - QP - - -");
        }
        $house_journals->where($query_parameters)->recordfetch_setup();
        $journal_entry = array();
        while ( $house_journals->recordfetch($journal_entry) ) {
          $key = array_element($journal_entry,'title');
          if ( array_key_exists($key, $batch) && ($session_tag == array_element($journal_entry,'session_tag','ZZ')) && (array_element($journal_entry,'sn') == array_element(array_element($batch,'metadata',array()),'sn','ZZ'))) {
            if ( $debug_method ) {
              $this->syslog(__FUNCTION__,__LINE__,"(marker) - - - - Already in DB: {$journal_entry['title']}");
              $this->recursive_dump($journal_entry,"(marker) - - - QR -");
              $this->recursive_dump($batch[$key],"(marker) - - - - ER");
            }
            $batch[$key] = NULL;
          }
        }
        $batch = array_filter($batch); // Clear omitted entries
        if ( $debug_method ) {
          $this->syslog(__FUNCTION__,__LINE__,"(marker) - - - - Remainder: " . count($cacheable_entries));
          $this->recursive_dump($batch,"(marker) - - CE - -");
        }
        foreach ( $batch as $title => $components ) {
          $journal_id = $house_journals->
            set_id(NULL)->
            set_create_time(time())->
            set_congress_tag($target_congress)->
            set_session_tag($session_tag)->
            set_pdf_url(array_element($components['entries'],'PDF'))->
            set_url(array_element($components['entries'],'HTML'))->
            set_title($title)->
            set_sn(array_element($components['metadata'],'sn'))->
            stow();
            ;
          $this->syslog(__FUNCTION__,__LINE__,"(marker) - - - Inserted HouseJournal entry {$journal_id}" );
        }
      }
    }/*}}}*/

    ///////////////////////////////////////////////////////////////////////////////////////////
    // Extract list of pagers 

    $this->recursive_dump(($pager_candidates = $document_parser->get_containers(
      'children[tagname*=div][attrs:CLASS*=padded]'
    )),"(barker) Remaining structure");

    $pagers = array();
    foreach ( $pager_candidates as $seq => $pager_candidate ) {/*{{{*/
      $document_parser->filter_nested_array($pager_candidate,
        'url,text[url*=http://www.congress.gov.ph/download/index.php\?d=journals&page=(.*)&congress=(.*)|i]',0
      );
      if ( $debug_method ) {
        $this->syslog(__FUNCTION__,__LINE__,"(marker) - - - - - - - - Candidates {$seq}");
        $this->recursive_dump($pager_candidate,"(marker)- - - -");
      }
      foreach ( $pager_candidate as $pager ) {
        $pager_url = array_element($pager,'url');
        $pagers[array_element($pager,'text')] = <<<EOH
<a class="legiscope-remote" href="{$pager_url}">{$pager['text']} </a>
EOH;
      }
    }/*}}}*/
    ksort($pagers);
    if ( $debug_method ) {
      $this->syslog(__FUNCTION__,__LINE__,"(marker) - - - - - - - - Pager links");
      $this->recursive_dump($pagers,"(marker)- - - -");
    }

    ///////////////////////////////////////////////////////////////////////////////////////////
    // Extract list of Journal PDF URLs

    $this->recursive_dump(($registry = $document_parser->get_containers(
      'children[tagname*=div][class*=padded]'
    )),"(barker)");

    array_walk($registry,create_function(
      '& $a, $k, $s', '$s->filter_nested_array($a,"url,text[url*=download/journal|i]",0);'
    ), $document_parser);

    $registry = array_element(array_values(array_filter($registry)),0,array());

    if ( $debug_method ) {
      $this->recursive_dump($registry,"(marker) -- A --");
    }

    $u  = new UrlModel();
    $ue = new UrlEdgeModel();

    ///////////////////////////////////////////////////////////////////////////////////////////
    while ( 0 < count($registry) ) {/*{{{*/
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
    }/*}}}*/
    ///////////////////////////////////////////////////////////////////////////////////////////

    $document_parser->cluster_urldefs = $document_parser->generate_linkset($urlmodel->get_url(),'cluster_urls');

    if ( $debug_method ) {
      $this->syslog(__FUNCTION__,__LINE__,"(marker) Cluster URLdefs: " . count($document_parser->cluster_urldefs) );
      $this->recursive_dump($document_parser->cluster_urldefs,"(marker)");
    }

    $congress_select_baseurl = new UrlModel($urlmodel->get_url());
    $congress_select_baseurl->add_query_element('d'       , 'journals', FALSE, TRUE);
    $congress_select_baseurl->add_query_element('page'    , NULL      , FALSE, TRUE);
    $congress_select_baseurl->add_query_element('congress', NULL      , TRUE , TRUE);
    
    $this->syslog(__FUNCTION__,__LINE__,"(marker) ----- ---- --- -- - - - Congress SELECT base URL: " . $congress_select_baseurl->get_url() );
    // This actually extracts the Congress selector, not the Session selector
    $congress_select = $document_parser->extract_house_session_select($congress_select_baseurl, FALSE, '[id*=form1]');
    if ( $debug_method ) {
      $this->syslog(__FUNCTION__,__LINE__,"(marker) ----- ---- --- -- - - - Raw Congress SELECT options");
      $this->recursive_dump($congress_select,"(marker)");
    }
    $document_parser->filter_nested_array($congress_select,
      'markup[faux_url*=http://www.congress.gov.ph/download/index.php\?(.*)(d=journals)?(.*)|i][optval*=.?|i]',0
    );
    if ( $debug_method ) {
      $this->syslog(__FUNCTION__,__LINE__,"(marker) ----- ---- --- -- - - - Congress SELECT options");
      $this->recursive_dump($congress_select,"(marker)");
    }

    $use_alternate_generator = FALSE;

    $batch_regex = 'http://www.congress.gov.ph/download/journals_([0-9]*)/(.*)';

    $session_select = array();
    $fake_url_model = new UrlModel($urlmodel->get_url());
    $fake_url_model->add_query_element('congress', $target_congress, FALSE, TRUE);
    $fake_url_model->add_query_element('page', NULL, TRUE, TRUE);
    if ( $debug_method ) {
      $this->syslog(__FUNCTION__,__LINE__,"(marker) - - - - Fake URL for recomposition: " . $fake_url_model->get_url());
    }

    $child_collection = $use_alternate_generator
      ? $this->find_incident_pages($urlmodel, $batch_regex, NULL)
      // Generate $session_select from the session item source
      : $house_journals->fetch_session_item_source($session_select, $fake_url_model, $target_congress)
      ;
    if ( $debug_method ) {
      $this->syslog(__FUNCTION__,__LINE__,"(marker) Result of incident page search: " . count($child_collection) . ($use_alternate_generator ? "" : ", with regex {$batch_regex}"));
      $this->recursive_dump($child_collection,"(marker) - - - - - -");
    }

    krsort( $child_collection, SORT_NUMERIC );

    $fake_url_model->set_id(NULL)->add_query_element('congress', $target_congress, FALSE, TRUE);

    $pagecontent = $document_parser->generate_congress_session_item_markup(
      $fake_url_model,
      $child_collection,
      $session_select,
      NULL, // query_fragment_filter = NULL => No reordering by query fragment, link text taken from markup 
      $congress_select // $session_select // Array : Links for switching Congress Session 
    );

    if ( $debug_method ) {
      $this->syslog(__FUNCTION__,__LINE__,"(marker) -- - -- - -- - -- Filtered content" );
      $this->recursive_dump($document_parser->filtered_content,"(marker)- - -");
    }

    // $inserted_content .= join('',$document_parser->get_filtered_doc());
    $pagers = join('',$pagers);
    $pagecontent .= <<<EOH
<div class="alternate-original alternate-content" id="senate-journal-block">
Session {$pagers}<br/>
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
    // House Bills
    $debug_method = FALSE;

    $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked for " . $urlmodel->get_url() );

    // Custom content, generic parser does not handle the bills text content
    $this->syslog( __FUNCTION__, __LINE__, "(warning) EMPTY HANDLER.  Length: " . $urlmodel->get_content_length() );
    $urlmodel->ensure_custom_parse();

    $filter_url    = new UrlModel();
    $hb_listparser = new CongressHbListParseUtility();
    $hb_listparser->debug_tags = FALSE;
    $hb_listparser->
      enable_filtered_doc(FALSE)-> // Conserve memory, do not store filtered doc.
      set_parent_url($urlmodel->get_url())->
      parse_html(
        $urlmodel->get_pagecontent(),
        $urlmodel->get_response_header()
      );

    // If we get this far, there is enough memory to iteratively pop
    // elements off the stack; we'll assume that there is not enough 
    // to keep the original parsed data and still generate markup.
    $this->syslog( __FUNCTION__, __LINE__, "(marker) Elements: " . count($hb_listparser->get_containers()) );

    $pagecontent = NULL;
    $house_bills = array();

    $this->syslog( __FUNCTION__, __LINE__, "(marker) Filtering in place");
    $hb_listparser->filter_nested_array($hb_listparser->containers_r(),
      'children[tagname*=div][class*=padded]{#[seq*=.*|i]}'
    );
    $this->syslog( __FUNCTION__, __LINE__, "(marker) Reordering using sequence in stream");
    array_walk($hb_listparser->containers_r(),create_function(
      '& $a, $k, $s', 'if ( 0 < count($a) ) $s->reorder_with_sequence_tags($a); else $a = NULL;'
    ),$hb_listparser);

    // Haskell me now, please. Crunch down array to just the list of bills
    $hb_listparser->assign_containers(array_values(array_filter($hb_listparser->containers_r())),0);
    $hb_listparser->assign_containers(array_values($hb_listparser->containers_r()));

    $this->syslog( __FUNCTION__, __LINE__, "(marker) Reverse stream sequence, treat array as stack.");
    // Hack to obtain Congress number from the first few entries of the array
    $congress_tag = NULL;
    $counter = 0;
    foreach ( $hb_listparser->containers_r() as $entry ) {/*{{{*/
      if ( $counter > 10 ) break;
      if ( $debug_method ) {
        $this->syslog( __FUNCTION__, __LINE__, "(marker) - - - - - - - - - - - - Testing entry {$counter}");
        $this->recursive_dump($entry,"(marker) - - - -");
      }
      $congress_line_match = array();
      $textentry = array_element($entry,'text');
      if (is_null($textentry)) continue;
      $counter++;
      if (1 == preg_match('@([0-9]*)(.*) Congress(.*)@i', $textentry, $congress_line_match) ) {
        $congress_tag = array_element($congress_line_match,1);
        $this->syslog( __FUNCTION__, __LINE__, "(marker) Apparent Congress number: {$congress_tag}");
        break;
      }
    } /*}}}*/
    reset($hb_listparser->containers_r());
    krsort($hb_listparser->containers_r());

    // $this->recursive_dump( $hb_listparser->containers_r(), "(marker) -" );

    $current_bill = array();
    $parent_url   = UrlModel::parse_url($urlmodel->get_url());

    // Parse markup stream, pop clusters of tags from stack

    $bill_cache  = array();
    $bills       = array();
    $bill_count  = 0;
    $cache_limit = 20;

    $house_bill  = new HouseBillDocumentModel();
    $committee   = new CongressionalCommitteeDocumentModel(); 
    $dossier     = new RepresentativeDossierModel();

    $hb_joins = $house_bill->get_joins();
    $this->recursive_dump($hb_joins, "(marker) HB Joins - - -");

    $meta = array(
      'status'           => 'Status',
      'principal-author' => 'Principal Author',
      'main-committee'   => 'Main Referral',
    );

    $linkmap = array(
      '@(\[(.*)as filed(.*)\])@i'  => 'filed',
      '@(\[(.*)engrossed(.*)\])@i' => 'engrossed',
    );

    $counter = 0;
    $committee_regex_lookup = array();

    while (0 < count($hb_listparser->containers_r())) {/*{{{*/

      // Test collected bills against database

      if ( $cache_limit <= count($bill_cache) ) {/*{{{*/

        $committee->update_committee_name_regex_lookup($committee_regex_lookup);

        array_walk($bill_cache,create_function(
          '& $a, $k, $s', '$raw_committee_name = $a["meta"]["main-committee"]["raw"]; $a["meta"]["main-committee"]["mapped"] = $s[$raw_committee_name]["id"];'
        ),$committee_regex_lookup);

        $house_bill->cache_parsed_housebill_records($bill_cache);
        $bills = $bill_cache;

        $bill_cache = array();

        while (0 < count($hb_listparser->containers_r())) {
          $container = array_pop($hb_listparser->containers_r());
        }
        break;

      }/*}}}*/

      // Remove bill component from stack

      $container = array_pop($hb_listparser->containers_r());

      if ( array_key_exists('bill-head', $container) ) {/*{{{*/
        // New entry causes prior entries to be flushed to stack
        $hb_number = join('', array_element($container,'bill-head'));

        if (!is_null(array_element($current_bill,'sn'))) { /*{{{*/
          if (5 > count($bills)) {
            $bills[] = $current_bill;
          }
          $bill_cache[array_element($current_bill,'sn')] = $current_bill;
        }/*}}}*/

        if ( $debug_method ) {/*{{{*/
          $this->syslog( __FUNCTION__, __LINE__, "(marker) - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -");
          $this->recursive_dump($current_bill, "(marker) - - - - - " . array_element($current_bill,'sn'));
        }/*}}}*/

        $current_bill = $house_bill->get_parsed_housebill_template($hb_number, $congress_tag); 
        $current_bill['representative']['relation_to_bill'] = 'principal-author';
        $current_bill['committee']['jointype'] = 'main-committee';

        $bill_count++;

        continue;

      }/*}}}*/

      if ( array_key_exists('meta', $container) ) {/*{{{*/
        $matches = array();
        if ( 1 == preg_match('@^('.join('|',$meta).'):(.*)$@i',join('',$container['meta']), $matches) ) {
          $matches[1] = trim(preg_replace(array_map(create_function('$a','return "@{$a}@i";'),$meta),array_keys($meta),$matches[1]));
          $matches[2] = trim($matches[2]);
          $mapped = NULL;
          switch( $matches[1] ) {
            case 'principal-author':
              $name = $matches[2];
              // FIXME: Decompose replace_legislator_names_hotlinks, factor out markup generator
              $mapped = $dossier->replace_legislator_names_hotlinks($name);
              $mapped = !is_null($mapped) ? array_element($name,'id') : NULL;
              if ( !is_null($mapped) ) $matches[2] = "{$matches[2]} ({$name['fullname']})";
              break;

            case 'main-committee':
              // Perform tree search (PHP assoc array), as it will be inefficient to 
              // try to look up each name as it recurs in the input stream.
              // We'll assume that there will be (at most) a couple hundred distinct committee names
              // dealt with here, so we can reasonably generate a lookup table
              // of committee names containing name match regexes and committee record IDs.
              $name = $matches[2];
              if ( !array_key_exists($name,$committee_regex_lookup) ) {/*{{{*/
                // Test for a full regex match against the entire lookup table 
                // before adding the committee name and regex pattern to the tree
                $committee_name_regex = LegislationCommonParseUtility::committee_name_regex($name);
                if ( 0 < count($committee_regex_lookup) ) {/*{{{*/
                  $m = array_filter(array_combine(
                    array_keys($committee_regex_lookup),
                    array_map(create_function(
                      '$a', 'return 1 == preg_match("@" . array_element($a,"regex") . "@i","' . $name . '") ? $a : NULL;'
                    ),$committee_regex_lookup)
                  )); 
                  $n = count($m);
                  if ( $n == 0 ) {
                    // No match, probably a new name, hence no ID yet found
                    $mapped = NULL;
                  } else if ( $n == 1 ) {
                    // Matched exactly one name, no need to create new entry
                    $name = NULL;
                    $mapped = array_element($m,'id');
                  } else {
                    // Matched multiple records
                    $mapped = $m;
                  }
                }/*}}}*/
                if ( !is_null($name) )
                $committee_regex_lookup[$name] = array(
                  'committee_name' => $name,
                  'regex'          => $committee_name_regex,
                  'id'             => 'UNMAPPED' // Fill this in just before invoking cache_parsed_housebill_records()
                );
              }/*}}}*/
              else {
                // Assign an existing ID
                $mapped = array_element($committee_regex_lookup[$name],'id');
              }
              break;
            default:
              break;
          }
          $current_bill['meta'][$matches[1]] = array( 'raw' => $matches[2], 'mapped' => $mapped );
        }
        continue;
      }/*}}}*/

      if ( array_key_exists('desc', $container) ) {/*{{{*/
        $current_bill['description'] = join('',$container['desc']);
        continue;
      }/*}}}*/

      if ( array_key_exists('onclick', $container) ) {/*{{{*/
        $matches = array();
        $popup_regex = "display_Window\('(.*)','(.*)'\)";
        if ( 1 == preg_match("@{$popup_regex}@i", $container['onclick'], $matches) ) {
          $matches = array_element($matches,1);
          if ( !is_null($matches) ) {
            $matches = array('url' => $matches);
            $matches = UrlModel::normalize_url($parent_url, $matches);
            $current_bill['url_history'] = $matches;
          }
        }
        continue;
      }/*}}}*/

      if ( array_key_exists('url', $container) ) {/*{{{*/
        $linktype = array_element($container,'text');
        $linktype = preg_replace(array_keys($linkmap),array_values($linkmap), $linktype); 
        $current_bill['links'][$linktype] = array_element($container,'url');
        continue;
      }/*}}}*/

    }/*}}}*/

    // Deplete remaining House Bill cache entries
    if ( 0 < count($bill_cache) ) {/*{{{*/

      $committee->update_committee_name_regex_lookup($committee_regex_lookup);

      array_walk($bill_cache,create_function(
        '& $a, $k, $s', '$raw_committee_name = $a["meta"]["main-committee"]["raw"]; $a["meta"]["main-committee"]["mapped"] = $s[$raw_committee_name]["id"];'
      ),$committee_regex_lookup);

      // $house_bill->cache_parsed_housebill_records($bill_cache);
      $bills = $bill_cache;

      $bill_cache = array();

    }/*}}}*/

    $bills[] = $current_bill;

    $this->recursive_dump($bills, "(marker) - - - - - Sample Bill Records");
    $this->syslog( __FUNCTION__, __LINE__, "(marker) - - - Total bills processed: {$bill_count}");
    if ( $debug_method ) {
      $this->syslog( __FUNCTION__, __LINE__, "(marker) - - - Committee Lookups" ); 
      $this->recursive_dump($committee_regex_lookup, "(marker) - - - - - Committee Lookup Table");
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

    $p->
      set_parent_url($urlmodel->get_url())->
      parse_html(
        $pagecontent,
        $urlmodel->get_response_header()
      );

    $p->debug_operators = FALSE;

    $this->recursive_dump($containers = $p->get_containers(
      'children[tagname=div][id=main-ol]'
    ),"(----) ++ All containers");

    $target_congress = NULL;
    // We are able to extract names of committees and current chairperson.
    if ( 0 < count($containers) ) {/*{{{*/
      $committees  = array();
      $pagecontent = '';
      $containers  = array_values($containers);
      krsort($containers,SORT_NUMERIC);
      $containers  = array_values($containers);
      while ( 0 < count( $containers ) ) {/*{{{*/
        $pagecontent .= "<div>";
        $container = array_values(array_pop($containers));
        krsort($container);
        $container = array_values($container);
        while ( 0 < count( $container ) ) {/*{{{*/
          $tag = array_pop($container);
          if ( array_key_exists('url', $tag) ) {
            $hash = UrlModel::get_url_hash($tag['url']);
            $congress_tag = 
            $pagecontent .= <<<EOH
<a href="{$tag['url']}" class="legiscope-remote">{$tag['text']}</a>
EOH;
            $committees[$hash] = array(
              'url'            => $tag['url'],
              'committee_name' => $tag['text'],
              'congress_tag'   => UrlModel::query_element('congress', $tag['url']), 
              'chairperson'    => NULL,
            );
            continue;
          }
          // Replace $name with legislator dossier record
          $name = $tag['text'];
          $tag['text'] = $m->replace_legislator_names_hotlinks($name);
          $committees[$hash]['chairperson'] = $name;
          if (is_null(array_element($name,'fullname'))) {
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
        }/*}}}*/
        $pagecontent .= "</div>";
      }/*}}}*/
      // At this point, the $containers stack has been depleted of entries,
      // basically being transformed into the $committees stack
      // $this->recursive_dump($committees,"(marker) -- -- --");
    }/*}}}*/
    else {/*{{{*/
      $pagecontent = join('', $p->get_filtered_doc());
    }/*}}}*/

    $committee = array();
    $updated = 0;
    $committees_found = count($committees);
    while ( 0 < count($committees) ) {
      $committee = array();
      $this->pop_stack_entries($committee, $committees, 10);
      // Use 'url' and 'committee_name' keys; store missing CongressionalCommitteeDocumentModel entries. 
      $comm->mark_committee_ids($committee);
      // Extract all records marked 'UNMAPPED'
      $p->filter_nested_array($committee, '#[id*=UNMAPPED]');
      $this->recursive_dump($committee, "(marker) - -- - STOWABLE");
      foreach ( $committee as $entry ) {
        $updated++;
        $comm->fetch($entry['committee_name'],'committee_name');
        $entry = array(
          'committee_name' => array_element($entry,'committee_name'),
          'congress_tag'   => array_element($entry,'congress_tag'),
          'create_time'    => array_element($entry,'create_time'),
          'last_fetch'     => array_element($entry,'last_fetch'),
          'url'            => array_element($entry,'url'),
        );
        $id = $comm->set_contents_from_array($entry)->stow();
        $this->syslog( __FUNCTION__, __LINE__, "(marker) - - - Stowed {$id} {$entry['committee_name']}");
      }
    }
    if ( $updated == 0 ) $this->syslog(__FUNCTION__, __LINE__, "(marker) - - - All {$committees_found} committee names stowed");

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
      1 == preg_match('@http://www.congress.gov.ph/download/index.php\?d=billstext@i', $url->get_url()) ||
      1 == preg_match('@http://www.congress.gov.ph/members(([/]*)(.*))*@i', $url->get_url()) ||
      1 == preg_match('@http://www.congress.gov.ph/download/index.php\?d=journals(.*)@i', $url->get_url()) ||
      1 == preg_match('@http://www.congress.gov.ph/members/search.php\?(.*)@i', $url->get_url())  || 
      1 == preg_match('@http://www.congress.gov.ph/legis/search/hist_show.php\?@i', $url->get_url())
    );
  }/*}}}*/


}
