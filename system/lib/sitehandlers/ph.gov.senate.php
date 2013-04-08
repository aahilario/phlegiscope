<?php

class SenateGovPh extends LegiscopeBase {
  
  function __construct() {
    $this->syslog( __FUNCTION__, __LINE__, 'Using site-specific container class' );
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

  function common_unhandled_page_parser(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked for " . $urlmodel->get_url() );
    $common = new SenateCommonParseUtility();
    $common->set_parent_url($urlmodel->get_url())->parse_html($urlmodel->get_pagecontent(),$urlmodel->get_response_header());
    $pagecontent = preg_replace(
      array(
        "@\&\#10;@",
        "@\n@",
      ),
      array(
        "",
        "",
      ),
      join('',$common->get_filtered_doc())
    );
    // $this->recursive_dump($common->get_containers(),'(warning)');
  }/*}}}*/

  /** Committee Information **/

  function seek_postparse_31721d691dbebc1cee643eb73d5221e4(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    // http://www.senate.gov.ph/notice_ctte.asp
    $calparser = new SenateCommitteeNoticeParser();
    $this->common_unhandled_page_parser($parser,$pagecontent,$urlmodel);
  }/*}}}*/


  /** Journal Entries **/

  function seek_postparse_386038c1a686fd0e6aeee9a105b9580d(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    $this->seek_postparse_congress_type_journal($parser,$pagecontent,$urlmodel);
  }/*}}}*/

  function seek_postparse_congress_13_type_journal(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    $this->seek_postparse_congress_type_journal($parser,$pagecontent,$urlmodel);
  }/*}}}*/

  function seek_postparse_congress_14_type_journal(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    $this->seek_postparse_congress_type_journal($parser,$pagecontent,$urlmodel);
  }/*}}}*/

  function seek_postparse_congress_15_type_journal(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    $this->seek_postparse_congress_type_journal($parser,$pagecontent,$urlmodel);
  }/*}}}*/

  function seek_postparse_congress_type_journal(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    // http://www.senate.gov.ph/lis/leg_sys.aspx?congress=15&type=journal
    // This page contains a form that is submitted
    // with an ONCHANGE event bound to a SELECT control.
    //
    // <select name="dlBillType" onchange="javascript:setTimeout('__doPostBack(\'dlBillType\',\'\')', 0)" id="dlBillType">
    //
    // var theForm = document.forms['form1'];
    // if (!theForm) {
    //     theForm = document.form1;
    //     }
    //     function __doPostBack(eventTarget, eventArgument) {
    //       if (!theForm.onsubmit || (theForm.onsubmit() != false)) {
    //         theForm.__EVENTTARGET.value = eventTarget;
    //         theForm.__EVENTARGUMENT.value = eventArgument;
    //         theForm.submit();
    //       }
    //     }
    //
    // On this page, __EVENTTARGET = 'dlBillType', and __EVENTARGUMENT = <empty string>
    // Generate A links having abstract URLs containing link payload data which triggers a POST instead of HEAD or GET
    $cache_filename = md5(__FUNCTION__ . $parser->trigger_linktext);
    $cache_filename = "./cache/{$this->subject_host_hash}-{$cache_filename}.generated";
    if ( C('ENABLE_GENERATED_CONTENT_BUFFERING') ) {
      if ( $parser->from_network ) unlink($cache_filename);
      else if ( file_exists($cache_filename) ) {
        $this->syslog( __FUNCTION__, __LINE__, "Retrieving cached markup for " . $urlmodel->get_url() );
        $pagecontent = file_get_contents($cache_filename);
        return;
      }
    }

    $this->syslog( __FUNCTION__, __LINE__, "Pagecontent postparser invocation for " . $urlmodel->get_url() );
    $replacement_content = '';

    $paginator_form = $this->extract_form($parser->get_containers());
    $paginator_form = $paginator_form[0]['children'];
    $control_set = $this->extract_form_controls($paginator_form);

    $this->recursive_dump($paginator_form,'(warning)');

    extract($control_set); // form_controls, select_name, select_options, userset

    if ( is_array($select_options) ) foreach ( $select_options as $select_option ) {/*{{{*/
      // Take a copy of the rest of the form controls
      $control_set = $form_controls;
      $control_set[$select_name] = $select_option['value'];
      $control_set['__EVENTTARGET'] = $select_name;
      $control_set['__EVENTARGUMENT'] = NULL;
      $controlset_json_base64 = base64_encode(json_encode($control_set));
      $controlset_hash        = md5($controlset_json_base64);
      $faux_url               = $urlmodel->get_url();
      $generated_link = <<<EOH
<a href="{$faux_url}" class="fauxpost" id="switch-{$controlset_hash}">{$select_option['text']}</a>
<span id="content-{$controlset_hash}" style="display:none">{$controlset_json_base64}</span><br/>
EOH;
      $replacement_content .= $generated_link;
    }/*}}}*/

    $replacement_content .= "<hr/>";

    $replacement_content = '';

    $edge_iterator = new UrlEdgeModel();

    $edge_iterator->where(array('a' => $urlmodel->id))->recordfetch_setup();
    $child_link = array();
    $child_links = array(array());
    $child_collection = array();
    $batch_number = 0;
    $child_count = 0;
    $subset_size = 20;

    // First collect a list of unique URLs referenced from $urlmodel,
    //  partitioned into subsets of size $subset_size 
    while ( $edge_iterator->recordfetch($child_link) ) {/*{{{*/
      if ( !array_key_exists($child_link['b'], $child_collection) ) {
        $b = $child_link['b'];
        $child_collection[$b] = count($child_collection);
        $child_links[$batch_number][$b] = count($child_links[$batch_number]);
        $child_count++;
        if ( $child_count > $subset_size ) {
          ksort($child_links[$batch_number]);
          $child_links[$batch_number] = array_keys($child_links[$batch_number]);
          $child_count = 0;
          $batch_number++;
        }
      }
    }/*}}}*/

    // Finally treat the remaining links
    if ( $child_count > 0 ) {
      ksort($child_links[$batch_number]);
      $child_links[$batch_number] = array_keys($child_links[$batch_number]);
      $child_count = 0;
    }
    $url_iterator = new UrlModel();
    $child_collection = array();

    $query_regex = '@([^&=]*)=([^&]*)@';
    $link_batch = 'REGEXP \'http://www.senate.gov.ph/lis/journal.aspx\\\\?congress=([^&]*)&session=([^&]*)&q=([0-9]*)\'';
    $url_iterator->where(array('url' => $link_batch))->recordfetch_setup();
    $child_link = array();
    while ( $url_iterator->recordfetch($child_link) ) {
      $url_query_components = array();
      $url_query_parts = UrlModel::parse_url($child_link['url'], PHP_URL_QUERY);
      preg_match_all($query_regex, $url_query_parts, $url_query_components);
      $url_query_parts = array_combine($url_query_components[1],$url_query_components[2]);
      $child_collection[$url_query_parts['congress']][$url_query_parts['session']][$url_query_parts['q']] = array( 
        'hash' => $child_link['urlhash'],
        'url'  => $child_link['url'],
        'text' => $child_link['urltext'],
      );
    }
    
    // $pagecontent = "{$replacement_content}{$pagecontent}";
    // $pagecontent = $replacement_content;
    $pagecontent = '<div class="senate-journal">';

    krsort($child_collection);
    foreach ( $child_collection as $congress => $session_q ) {
      $pagecontent .= <<<EOH
<span class="indent-1">Congress {$congress}<br/>

EOH;
      krsort($session_q);
      foreach ( $session_q as $session => $q ) {
        $pagecontent .= <<<EOH
<span class="indent-2">Session {$session}<br/>

EOH;
        krsort($q);
        foreach ( $q as $child_link ) {
          $urlparts = UrlModel::parse_url($child_link['url'],PHP_URL_QUERY);
          $linktext =  $child_link[empty($child_link['text']) ? "url" : 'text'];
          if ( $linktext == $child_link['url'] ) {
            $url_query_components = array();
            $url_query_parts = UrlModel::parse_url($child_link['url'], PHP_URL_QUERY);
            preg_match_all($query_regex, $url_query_parts, $url_query_components);
            $url_query_parts = array_combine($url_query_components[1],$url_query_components[2]);
            $linktext = "No. {$url_query_parts['q']}";
          }
          $pagecontent .= <<<EOH
<a class="legiscope-remote cached indent-3" id="{$child_link['hash']}" href="{$child_link['url']}">{$linktext}</a><br/>

EOH;
        }
        $pagecontent .= <<<EOH
</span>
EOH;
      }
      $pagecontent .= <<<EOH
</span>
EOH;
    }
    $pagecontent .= <<<EOH
</div>

<div class="alternate-original alternate-content" id="senate-journal-block"></div>
EOH;


    if ( C('ENABLE_GENERATED_CONTENT_BUFFERING') ) {
      file_put_contents($cache_filename, $pagecontent);
    }

    $parser->json_reply = array('retainoriginal' => TRUE);

  }/*}}}*/

  function seek_postparse_bypath_0930d15b5c0048e5e03af7b02f24769d(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked for " . $urlmodel->get_url() );

    $journal_parser = new SenateJournalParseUtility();
    $journal_parser->set_parent_url($urlmodel->get_url())->parse_html($urlmodel->get_pagecontent(),$urlmodel->get_response_header());
    $pagecontent = preg_replace(
      array(
        "@\&\#10;@",
        "@\&\#9;@",
        "@\n@",
      ),
      array(
        "",
        " ",
        "",
      ),
      join('',$journal_parser->get_filtered_doc())
    );

    if (0) $this->recursive_dump(($journal_properties = $journal_parser->get_containers(
      //'children[tagname=div][class*=lis_doctitle]'
    )),'(marker) J');
    
    if (1) $this->recursive_dump(($journal_data = array_filter($journal_parser->activity_summary)
    ),'(marker) A');

    $pagecontent = '';

    $test_url = new UrlModel();

    foreach ( $journal_data as $n => $e ) {
      if ( array_key_exists('metadata',$e) ) {/*{{{*/
        $e = $e['metadata'];
        $pagecontent .= <<<EOH
<br/>
<div>
Journal of the {$e['congress']} Congress, {$e['session']}<br/>
Recorded: {$e['date']}<br/>
Approved: {$e['approved']}
</div>
EOH;
        continue;
      }/*}}}*/
      if ( intval($n) == 0 ) {/*{{{*/
        foreach ($e['content'] as $entry) {
          $properties = array('legiscope-remote');
          $properties[] = ( $test_url->is_cached($entry['url']) ) ? 'cached' : 'uncached';
          $properties = join(' ', $properties);
          $urlhash = UrlModel::get_url_hash($entry['url']);
          if ( array_key_exists('url',$entry) ) {/*{{{*/
            $pagecontent .= <<<EOH
<b>{$e['section']}</b>  (<a id="{$urlhash}" class="{$properties}" href="{$entry['url']}">PDF</a>)<br/>
EOH;
            continue;
          }/*}}}*/
          if ( !(FALSE == strtotime($entry['text']) ) ) {/*{{{*/
            $pagecontent .= <<<EOH
Published {$entry['text']}<br/><br/>
EOH;
            continue;
          }/*}}}*/
        }
        continue;
      } /*}}}*/
      $pagecontent .= <<<EOH
<br/>
<b>{$e['section']}</b><br/>
<br/>
EOH;
      foreach ($e['content'] as $entry) {/*{{{*/
        $properties = array('legiscope-remote');
        $matches = array();
        $title = $entry['text'];
        $pattern = '@^([^:]*)(:| - )(.*)@i';
        preg_match($pattern, $title, $matches);
        $title = $matches[1];
        $desc = $matches[3];
        $properties[] = ( $test_url->is_cached($entry['url']) ) ? 'cached' : 'uncached';
        $properties = join(' ', $properties);
        $urlhash = UrlModel::get_url_hash($entry['url']);
        $pagecontent .= <<<EOH
<span><a id="{$urlhash}" class="{$properties}" href="{$entry['url']}">{$title}</a>: {$desc}</span><br/>

EOH;
      }/*}}}*/
    }

    $parser->json_reply = array('retainoriginal' => TRUE);
  }/*}}}*/


  /** Committees **/

  function seek_postparse_bypathonly_582a1744b18910b951a5855e3479b6f2(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    // http://www.senate.gov.ph/senators/sen_bio/*
    $this->syslog( __FUNCTION__, __LINE__, "(marker) --------- SENATOR BIO PARSER Invoked for " . $urlmodel->get_url() );

    ////////////////////////////////////////////////////////////////////
    $membership  = new SenatorCommitteeEdgeModel();
    $senator     = new SenatorBioParseUtility();
    $committee   = new SenateCommitteeModel();
    $dossier     = new SenatorDossierModel();
    $senator->
      set_parent_url($urlmodel->get_url())->
      parse_html($urlmodel->get_pagecontent(),$urlmodel->get_response_header());
    $pagecontent = join('',$senator->get_filtered_doc());
    $parser->structure_html = ''; 
    ////////////////////////////////////////////////////////////////////

    $this->recursive_dump(($boundary_top = $senator->get_containers(
    )),'All');

    // Find the placeholder, and extract the target URL for the image.
    // If the image (UrlModel) is cached locally, then replace the entire
    // placeholder with the base-64 encoded image.  Otherwise, replace
    // the placeholder with an empty string, and emit the markup below.
    $avatar_match = array();
    $dossier->fetch($urlmodel->get_url(), 'bio_url');
    $member_uuid = $dossier->get_member_uuid(); 

    $committee_membership_markup = ''; 

    if ( $membership->fetch_committees($dossier,TRUE) ) {/*{{{*/
      $committee_list = array();
      $committee_item = array();
      while ( $membership->recordfetch($committee_item) ) {
        $committee_list[$committee_item['b']] = array(
          'data' => $committee_item,
          'name' => NULL
        );
      }
      // TODO: Allow individual Model classes to maintain separate DB handles 
      foreach ( $committee_list as $committee_id => $c ) {
        $committee->fetch($committee_id,'id');
        $committee_list[$committee_id] = $committee->in_database()
          ? $committee->get_committee_name()
          : 'Disappeared'
          ;
        $committee_membership_markup .= <<<EOH
<li><a class="legiscope-remote" href="http://www.senate.gov.ph/committee/list.asp">{$committee_list[$committee_id]}</a></li>
EOH;
      }
      $this->recursive_dump($committee_list,'(marker) - C List');
    }/*}}}*/

    if ( 0 < strlen($committee_membership_markup) ) {/*{{{*/
      $committee_membership_markup = <<<EOH
<hr class="{$member_uuid}"/>
<span class="section-title">Committee Memberships</span><br/>
<ul class="committee_membership">
{$committee_membership_markup}
</ul>
<hr/>

EOH;
    }/*}}}*/

    if (1 == preg_match('@{REPRESENTATIVE-AVATAR\((.*)\)}@i',$pagecontent,$avatar_match)) {/*{{{*/

      // $this->recursive_dump($avatar_match,'(marker) Avatar Placeholder');
      $image_markup    = array();
      preg_match('@<img(.*)fauxsrc="([^"]*)"([^>]*)>@mi',$pagecontent,$image_markup);
      // $this->recursive_dump($image_markup,'(marker) Avatar Markup');
      $bio_image_src   = new UrlModel($image_markup[2],TRUE);
      $placeholder     = $avatar_match[0];
      $avatar_uuid_url = explode(",",$avatar_match[1]);
      $avatar_url      = new UrlModel($avatar_uuid_url[1],TRUE);
      $fake_uuid       = $avatar_uuid_url[0];
      $member_fullname = $dossier->get_fullname();
      $image_base64enc = NULL;

      if ( $avatar_url->in_database() ) {
        // Fetch the image, stuff it in a base-64 encoded string, and pass
        // it along to the user - if it exists.  Otherwise.
        $image_contenttype = $avatar_url->get_content_type(TRUE);
        $this->recursive_dump($image_contenttype,'(marker) Image properties');
        $image_base64enc   = base64_encode($avatar_url->get_pagecontent());
        $image_base64enc   = is_array($image_contenttype) && array_key_exists('content-type', $image_contenttype)
          ? "data:{$image_contenttype['content-type']}:base64,{$image_base64enc}"
          : NULL
          ;
      } else if ( $bio_image_src->in_database() ) {
        $image_contenttype = $bio_image_src->get_content_type(TRUE);
        $this->recursive_dump($image_contenttype,'(marker) Image properties');
        $image_base64enc   = base64_encode($bio_image_src->get_pagecontent());
        $image_base64enc   = is_array($image_contenttype) && array_key_exists('content-type', $image_contenttype)
          ? "data:{$image_contenttype['content-type']}:base64,{$image_base64enc}"
          : NULL
          ;
      }

      // Cleanup placeholder, replace fake UUID with the Senator's real UUID.
      // Also make it ready for use in preg_replace
      $placeholder = preg_replace("@({$fake_uuid})@mi","{$member_uuid}",$placeholder);
      $placeholder = str_replace(array('(',')',),array('\(','\)',),$placeholder);

      // Ditto for markup
      $pagecontent = preg_replace('@('.$fake_uuid.')@im', $member_uuid,$pagecontent);

      // Replacement for the placeholder
      $replacement = is_null($image_base64enc)
        ? '' // Image hasn't yet been fetched.
        : $image_base64enc // Image available, and can be inserted in markup.
        ;
        
      // Remove fake content
      // Insert committee membership in place of any extant <HR> tag
      $pagecontent = preg_replace(
        array(
          "@({$placeholder})@im",
          '@(fauxsrc="(.*)")@im',
          '@(\<p (.*)class="h1_bold"([^>]*)\>)@i'),
        array(
          "{$replacement}",
          "alt=\"{$member_fullname}\" id=\"image-{$member_uuid}\" class=\"representative-avatar\"",
          "{$committee_membership_markup}<table>",
        ), 
        $pagecontent
      );

      // Test for correct replacement
      $avatar_match = array('0' => 'No Match');
      preg_match('@{REPRESENTATIVE-AVATAR\((.*)\)}@i',$pagecontent,$avatar_match);
      $this->recursive_dump($avatar_match,'(marker) After subst');

      $committee_list_match = array('0' => 'No Match');
      if (!(1 == preg_match('@\<hr class="'.$member_uuid.'"/\>@i',$pagecontent,$committee_list_match))) {
        $pagecontent = $committee_membership_markup . $pagecontent;
      }


      // Only emit the image scraper trigger if the image was empty
      $this->syslog( __FUNCTION__, __LINE__, "(marker) Avatar URL is {$avatar_url}");
      if ( is_null($image_base64enc) ) $pagecontent .= <<<EOH

<input type="hidden" class="no-replace" id="imagesrc-{$member_uuid}" value="{$avatar_url}" />
<script type="text/javascript">
$(function(){
setTimeout((function(){
  update_representatives_avatars();
}),50);
});
</script>
EOH;
    }/*}}}*/
    else {
      $this->syslog( __FUNCTION__, __LINE__, "(marker) No placeholder found!" );
    }
    $parser->json_reply = array('retainoriginal' => TRUE);
  }/*}}}*/

  function seek_postparse_7150c562d8623591da65174bd4b85eea(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    // http://www.senate.gov.ph/committee/list.asp ('List of Committees')

    $this->syslog( __FUNCTION__, __LINE__, "Pagecontent by-path parser invocation for " . $urlmodel->get_url() );
    $committee_parser = new SenateCommitteeListParseUtility();
    $membership       = new SenatorCommitteeEdgeModel();
    $committee        = new SenateCommitteeModel();
    $senator          = new SenatorDossierModel();
    $url              = new UrlModel();

    // $this->recursive_dump($urlmodel->get_response_header(TRUE),'(warning)');

    $committee_parser->debug_tags = FALSE;
    $committee_parser->set_parent_url($urlmodel->get_url())->parse_html($pagecontent,$urlmodel->get_response_header());
    $pagecontent = join('',$committee_parser->get_filtered_doc());

    // $senator->dump_accessor_defs_to_syslog();
    // $committee->dump_accessor_defs_to_syslog();
    $membership->dump_accessor_defs_to_syslog();

    // $committees     = $membership->fetch_committees($senator);
    $containers     = $committee_parser->get_containers();
    $extract_tables = create_function('$a', 'return $a["attrs"]["CLASS"] == "SenTable" ? $a["children"] : NULL;');
    $containers     = array_values(array_filter(array_map($extract_tables, $containers)));

    $extract_tag_cdata = create_function('$a', 'return array("tag" => $a["tag"], "attrs" => $a["attrs"], "cdata" => join(" ", array_filter($a["cdata"])));');

    foreach ( $containers as $index => $table ) {
      $containers[$index] = array_map($extract_tag_cdata, $table);
    }

    $containers = array_values($containers);

    // Tables on this page are sequences of TD, A, TD tags.
    // An empty table cell (no character data) signifies the end of a row 
    $remove_empty_cells = create_function('$a', 'return empty($a["cdata"]) ? NULL : $a;');
    $filtered_content   = $pagecontent;
    $pagecontent        = '';

    // Cache sets of Senator-committee IDs discovered
    $senator_committees = array();
    foreach ( $containers as $container ) {/*{{{*/
      $committee_list = array();
      $container = array_filter(array_map($remove_empty_cells, $container));
      // $this->recursive_dump($container,'(warning)');
      foreach ( $container as $content ) {/*{{{*/
        $tag = strtolower($content["tag"]);
        if ( $tag == 'td' ) array_push($committee_list, array('committee_name' => NULL, 'senators' => array()));
        $element = array_pop($committee_list);
        if ( $tag == 'td' ) {/*{{{*/// Committee Name
          $element['id'] = $committee->stow_committee($content['cdata']);
          if ( !is_null($element['id']) ) {
            $element['committee_name'] = $committee->get_committee_name();
            $senator_committees[$element['id']] = array();
          }
        }/*}}}*/
        else if ( $tag == 'a' ) {/*{{{*/// Senator name and bio
          $senator_info = array();
          $senator_id = $senator->stow_senator(
            $content['attrs']['HREF'], // Senator's resume URL
            $content['cdata'], // Senator's full name as obtained on the page
            $senator_info, // Empty array, into which instance data are stored
            $urlmodel // The parent page URL (this page)
          );
          if ( !is_null($senator_id) ) {
            $url->fetch($bio_url, 'url');
            $senator_info['cached'] = $url->in_database();
            $element['senators'][$senator_id] = $senator_info;
            $senator_committees[$element['id']][$senator_id] = $senator_id; 
          }
        }/*}}}*/
        array_push($committee_list,$element);
      }/*}}}*/
      // Make the committee ID be the committee list array key
      $committee_list = array_combine(
        array_map(create_function('$a', 'return $a["id"];'), $committee_list),
        $committee_list
      );
      $replacement_content = '';
      foreach ( $senator_committees as $committee_id => $senator_ids ) {/*{{{*/// Generate missing committee associations
        $membership->where(array('AND' => array('b' => $committee_id)))->recordfetch_setup();
        $edge = array();
        while ( $membership->recordfetch($edge) ) {
          // Remove already extant edges
          $senator_ids[$edge['id']] = NULL;
        }
        $senator_ids = array_filter($senator_ids);
        foreach ( $senator_ids as $senator_id => $v ) {
          $membership->fetch($senator_id,$committee_id);
          $already_in_db = $membership->in_database();
          $associate_result = !$already_in_db
            ? $membership->
              set_create_time(time())->
              set_last_fetch(time())->
              set_role('chairperson')->
              stow($senator_id, $committee_id)
            : $membership->get_id()
            ;
          $this->syslog(__FUNCTION__,__LINE__,"(marker) - Association #{$associate_result} " . ($already_in_db ? "found" : "created") );
        }
      }/*}}}*/
      foreach ( $committee_list as $c ) {/*{{{*/// Generate markup
        $senator_entries = '';
        foreach ( $c['senators'] as $senator_entry ) {/*{{{*/
          $link_attribs = array('legiscope-remote');
          if ( $senator_entry['cached'] ) $link_attribs[] = 'cached';
          $link_attribs = join(' ', $link_attribs);
          $linktext = array_key_exists('linktext', $senator_entry)
            ? utf8_encode($senator_entry['linktext'])
            : $senator_entry['url'];
          $senator_entries .= <<<EOH
<span class="committee-senators">
  <a href="{$senator_entry['url']}" class="{$link_attribs}">{$linktext}</a>
</span>

EOH;
        }/*}}}*/
        $committee->fetch_by_committee_name($c['committee_name']);
        $committee_desc = ($committee->in_database())
          ? $committee->get_jurisdiction() 
          : '...'
          ;
        $pagecontent .= <<<EOH
<div class="committee-functions-leadership">
  <div class="committee-name">{$c['committee_name']}</div>
  <div class="committee-leaders">
  {$senator_entries}
  </div>
  <div class="committee-jurisdiction">{$committee_desc}</div>
</div>

EOH;
      }/*}}}*/
    }/*}}}*/

  }/*}}}*/

  function seek_postparse_bypath_01826c0f1e0270f7d72cb025cdcdb2fc(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    // http://www.senate.gov.ph/committee/duties.asp

    $this->syslog( __FUNCTION__, __LINE__, "Pagecontent by-path parser invocation for " . $urlmodel->get_url() );
    $committee_parser = new SenateCommitteeListParseUtility();
    // $committee_parser = new SenateCommonParseUtility();
    $committee        = new SenateCommitteeModel();
    $senator          = new SenatorDossierModel();

    $committee_parser->debug_tags = FALSE;
    // Malformed document hacks (2013 March 5 - W3C validator failure.  Check http://validator.w3.org/check?uri=http%3A%2F%2Fwww.senate.gov.ph%2Fcommittee%2Fduties.asp&charset=%28detect+automatically%29&doctype=Inline&ss=1&group=0&verbose=1&st=1&user-agent=W3C_Validator%2F1.3+http%3A%2F%2Fvalidator.w3.org%2Fservices)
    $content = preg_replace(
      array(
        '@(\<[/]*(o:p)\>)@im',
        '@(\<[/]*(u1:p)\>)@im',
        '@(\<[/]*(font)\>)@im',
      ),
      array(
        '<br class="unidentified"/>',
        '<br class="unidentified"/>',
        '<br class="unidentified"/>',
      ),
      $urlmodel->get_pagecontent()
    );
    $pagecontent = utf8_encode($content);
    $committee_parser->
      set_parent_url($urlmodel->get_url())->
      parse_html($content,$urlmodel->get_response_header());

    // Container accessors are not used
    $this->recursive_dump(($committee_info = $committee_parser->get_desc_stack(
    )),'(marker) Names');

    $template = <<<EOH
<div class="republic-act-entry">
<span class="republic-act-heading"><a href="{url}" class="{cache_state}" id="{urlhash}">{committee_name}</a></span>
<span class="republic-act-desc">{jurisdiction}</span>
</div>

EOH;
    $replacement_content = '';

    $this->syslog(__FUNCTION__,__LINE__, "Committee count: " . count($committee_info));

    foreach ( $committee_info as $entry ) {
      
      //$this->syslog(__FUNCTION__,__LINE__,"++ {$entry['link']}");
      // $this->recursive_dump($entry,"(marker)");
      $committee_name = $committee->cleanup_committee_name(trim($entry['link'],'#'));
      $short_code = preg_replace('@[^A-Z]@','',$committee_name);
      $committee->fetch_by_committee_name($committee_name);
      $committee->
        set_committee_name($committee_name)->
        set_short_code($short_code)->
        set_jurisdiction($entry['description'])->
        set_is_permanent('TRUE')->
        set_create_time(time())->
        set_last_fetch(time())->
        stow();
      $replacement_content .= $committee->substitute($template);
    }

    $pagecontent = $replacement_content;

  }/*}}}*/


  /** Senators **/

  function seek_postparse_bypathonly_255b2edb0476630159f8a93cd5836b08(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    // http://www.senate.gov.ph/senators/sen15th.asp
    $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked for " . $urlmodel->get_url() );
    // $this->common_unhandled_page_parser($parser,$pagecontent,$urlmodel);

    $senator = new SenatorBioParseUtility();
    $dossier = new SenatorDossierModel();
    $url     = new UrlModel();

    // $dossier->dump_accessor_defs_to_syslog();

    $senator->set_parent_url($urlmodel->get_url())->parse_html($pagecontent,$urlmodel->get_response_header());
    // $this->recursive_dump($senator->get_filtered_doc(),'(warning)');
    $pagecontent = join('',$senator->get_filtered_doc());

    ////////////////////////////////////////////////////////////////////
    $seek_structure_filename = "{$this->seek_cache_filename}.structure";
    $containerset = $senator->get_containers();
    $this->recursive_file_dump(
      $seek_structure_filename, 
      $containerset,0,__LINE__);
    $parser->structure_html = file_get_contents($seek_structure_filename);
    $this->syslog( __FUNCTION__, __LINE__, "--------- Storing structure dump {$seek_structure_filename}, length = " . strlen($parser->structure_html) );
    ////////////////////////////////////////////////////////////////////
    //
    // Extract the image URLs on this page and use them to construct a 
    // minimal pager, by rewriting pairs of image + URL tags
    //
    ////////////////////////////////////////////////////////////////////

    $image_url = array();
    $filter_image_or_link = create_function('$a', 'return (array_key_exists("text",$a) || array_key_exists("image",$a) || ($a["tag"] == "A")) ? $a : NULL;'); 

    foreach ( $containerset as $container_id => $container ) {/*{{{*/
      $this->syslog(__FUNCTION__,__LINE__,"(marker) Candidate structure {$container_id} - now at " . count($image_url));
      // $this->recursive_dump($container,"(marker) {$container_id}");
      if ( !("table" == $container['tagname']) ) continue;
      $children = array_filter(array_map($filter_image_or_link,$container['children']));
      // $this->recursive_dump($children,'(warning)');
      foreach( $children as $candidate_node ) {
        if (array_key_exists("image", $candidate_node)) {
          $image = array(
            "image" => $candidate_node['image'],
            "fauxuuid" => $candidate_node['fauxuuid'],
            "realsrc" => $candidate_node['realsrc'],
            "link" => array( 
              "url" => NULL,
              "urlhash" => NULL,
              "text" => NULL, 
            )
          );
          array_push($image_url, $image);
          continue;
        }
        if ( !(0 < count($image_url) ) ) continue;
        $image = array_pop($image_url);
        if ( is_null($image['link']['text']) && array_key_exists('text', $candidate_node) ) {
          $image['link']['text'] = str_replace(array('[BR]',"\n"),array(''," "),$candidate_node['text']);
          array_push($image_url, $image);
          continue;
        }
        if ( is_null($image['link']['url']) && array_key_exists('tag', $candidate_node) && ('a' == strtolower($candidate_node['tag'])) ) {
          $image['link']['url'] = $candidate_node['attrs']['HREF'];
          $image['link']['urlhash'] = UrlModel::get_url_hash($candidate_node['attrs']['HREF']);
          if ( array_key_exists('cdata', $candidate_node) ) {
            $test_name = trim(preg_replace('@[^A-Z."ñ ]@i','',join('',$candidate_node['cdata'])));
            if ( is_null($image['link']['text']) ) $image['link']['text'] = $test_name;
          }
          array_push($image_url, $image);
          continue;
        }
        array_push($image_url, $image);
      }
    }/*}}}*/

    $pagecontent = '';
    $senator_dossier = '';

    if ( 0 < count($image_url) ) { /*{{{*/
      foreach ( $image_url as $brick ) {/*{{{*/
        $bio_url = $brick['link']['url'];
        $dossier->fetch($bio_url,'bio_url'); 
        $member_fullname      = NULL; 
        $member_uuid          = NULL; 
        $member_avatar_base64 = NULL; 
        $avatar_url           = NULL; 
        if ( !$dossier->in_database() ) {/*{{{*/
          $member_fullname = $dossier->cleanup_senator_name(utf8_decode($brick['link']['text']));
          if (empty($member_fullname)) continue;
          $this->syslog(__FUNCTION__,__LINE__, "(marker) - Treating {$bio_url}");
          $this->recursive_dump($brick,'(warning)');
          $member_uuid = sha1(mt_rand(10000,100000) . ' ' . $urlmodel->get_url() . $member_fullname);
          $avatar_url  = $brick['realsrc'];
          $url->fetch(UrlModel::get_url_hash($avatar_url),'urlhash');
          if ( $url->in_database() ) {
            $image_content_type   = $url->get_content_type();
            $image_content        = base64_encode($url->get_pagecontent());
            $member_avatar_base64 = "data:{$image_content_type};base64,{$image_content}";
          } else $member_avatar_base64 = NULL;
          $dossier->
            set_member_uuid($member_uuid)->
            set_fullname($member_fullname)->
            set_bio_url($bio_url)->
            set_create_time(time())->
            set_last_fetch(time())->
            // set_contact_json($contact_items)->
            set_avatar_url($avatar_url)->
            set_avatar_image($member_avatar_base64)->
            stow();
        }/*}}}*/
        else {/*{{{*/
          $member_fullname      = $dossier->get_fullname();
          $member_uuid          = $dossier->get_member_uuid();
          $member_avatar_base64 = $dossier->get_avatar_image();
          $avatar_url           = $dossier->get_avatar_url();
          $this->syslog(__FUNCTION__,__LINE__, "- Loaded {$member_fullname} {$bio_url}");
        }/*}}}*/

        $senator_dossier .= <<<EOH
<a href="{$bio_url}" class="human-element-dossier-trigger"><img class="representative-avatar" id="image-{$member_uuid}" src="{$member_avatar_base64}" alt="{$member_fullname}" /></a> 
EOH;
        if ( !(0 < strlen($member_avatar_base64)) ) $senator_dossier .= <<<EOH
<input type="hidden" class="representative-avatar-source" name="image-ref" id="imagesrc-{$member_uuid}" value="{$avatar_url}" />
EOH;
      }/*}}}*/

      $pagecontent = utf8_encode(<<<EOH
<div class="senator-dossier-pan-bar"><div class="dossier-strip">{$senator_dossier}</div></div>
<div id="human-element-dossier-container" class="alternate-original half-container"></div>
<script type="text/javascript">
var total_image_width = 0;
var total_image_count = 0;
$(function(){
  initialize_dossier_triggers();
  $("div[class=dossier-strip]").find("img[class*=representative-avatar]").each(function(){
    total_image_width += $(this).outerWidth();
    total_image_count++;
  });
  if ( total_image_width < (total_image_count * 76) ) total_image_width = total_image_count * 76;
  $("div[class=dossier-strip]").width(total_image_width).css({'width' : total_image_width+'px !important'});
  setTimeout((function(){ update_representatives_avatars(); }),500);
});
</script>
EOH
      );

    }/*}}}*/

  }/*}}}*/

  function seek_postparse_73c3022a45d05b832a96fb881f20d2c6(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    // http://www.senate.gov.ph/senators/roll.asp
    $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked for " . $urlmodel->get_url() );
    $this->common_unhandled_page_parser($parser,$pagecontent,$urlmodel);
  }/*}}}*/

  function seek_postparse_aaccd94d6d5d9466c26378fd24f6b14f(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    // http://www.senate.gov.ph/senators/composition.asp
    $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked for " . $urlmodel->get_url() );
    $this->common_unhandled_page_parser($parser,$pagecontent,$urlmodel);
  }/*}}}*/

  function seek_postparse_8b7a45d963ca5d18191d657067f14cf9(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    // http://www.senate.gov.ph/secretariat/officers.asp
    $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked for " . $urlmodel->get_url() );
    $this->common_unhandled_page_parser($parser,$pagecontent,$urlmodel);
  }/*}}}*/

  function seek_postparse_bypathonly_3eed37a7b2fbf573b46f1303b8dcc9d1(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    // http://www.senate.gov.ph/secretariat/leg.asp
    $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked for " . $urlmodel->get_url() );
    $this->common_unhandled_page_parser($parser,$pagecontent,$urlmodel);
  }/*}}}*/

  function seek_postparse_bypathonly_c9858078eb57bf106b0e5187bb2ea278(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    // http://www.senate.gov.ph/about/rulesmenu.asp 
    $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked for " . $urlmodel->get_url() );
    $this->common_unhandled_page_parser($parser,$pagecontent,$urlmodel);
  }/*}}}*/

  function seek_postparse_710a92e801107035b7786723f9a96ec8(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    // http://www.senate.gov.ph/Treaties.asp
    $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked for " . $urlmodel->get_url() );
    $this->common_unhandled_page_parser($parser,$pagecontent,$urlmodel);
  }/*}}}*/

  function seek_postparse_bypathonly_2e8161d636209e4cd69b7ff638f5c8f4(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    // http://www.senate.gov.ph
    $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked for " . $urlmodel->get_url() );
    $this->common_unhandled_page_parser($parser,$pagecontent,$urlmodel);
  }/*}}}*/

  function seek_postparse_bypathonly_818a2624f2a47cff5dc1a4b4976acc4c(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    // http://www.senate.gov.ph/photo_gallery/gallery.aspx
    $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked for " . $urlmodel->get_url() );
    $this->common_unhandled_page_parser($parser,$pagecontent,$urlmodel);
  }/*}}}*/

  function seek_postparse_5c335fa143545884a63985e3389274fa(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    // http://www.senate.gov.ph/committee/schedwk.asp
    $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked for " . $urlmodel->get_url() );
    $this->common_unhandled_page_parser($parser,$pagecontent,$urlmodel);
  }/*}}}*/

  function seek_postparse_bypathonly_f71816285af79cbebebb490d5fcf4813(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    // 
    $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked for " . $urlmodel->get_url() );
    $this->common_unhandled_page_parser($parser,$pagecontent,$urlmodel);
  }/*}}}*/

  function seek_postparse_bypathonly_572e65afda6a84c7a7a375b915ad1f68(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    // http://www.senate.gov.ph/photo_release/2013/0122_00.asp
    $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked for " . $urlmodel->get_url() );
    $this->common_unhandled_page_parser($parser,$pagecontent,$urlmodel);
  }/*}}}*/


  /** Republic acts **/

  private function fetch_legislation_links(SenateRaListParseUtility & $ra_parser, RepublicActDocumentModel & $republic_act) {/*{{{*/

    // Extract table of legislation for this page

    $test_url = new UrlModel();

    $this->recursive_dump($sub_list = array_values($ra_parser->get_containers(
      'children[tagname=div][class*=alight|i]'
    )),0,'++ sublist');

    // $this->recursive_dump($ra_parser->get_containers(),'(warning)');
    // $this->recursive_dump($sub_list,'(warning)');
    $empty_ra_def = array(
      'link'     => array(), 
      'desc'     => NULL, 
      'linktext' => NULL,
      'aux'      => array(),
    );
    $republic_acts = array($empty_ra_def);
    $current_id = NULL;

    if ( 1 == count($sub_list) ) foreach ( $sub_list[0] as $tag ) {/*{{{*/

      if ( is_null($tag) ) continue;
      if ( array_key_exists('image', $tag) ) continue; // Skip PDF icon link image; irrelevant

      $ra = array_pop($republic_acts);

      // if ( is_null($current_id) && array_key_exists('attrs', $tag) && array_key_exists('ID', $tag['attrs']) )
      //  $current_id = $tag['attrs']['ID'];

      $is_texttag = array_key_exists('text', $tag);
      $is_linktag = array_key_exists('attrs', $tag) && array_key_exists('HREF', $tag['attrs']);
      
      if ( $is_texttag && !empty($ra['aux']) ) {/*{{{*/
        // The current tag already has link text; push a new, empty def onto the stack
        // $this->syslog(__FUNCTION__,__LINE__,"-- Skip to new entry.  Record now is currently for {$ra['linktext']}:");
        array_push($republic_acts, $ra);
        // $this->recursive_dump($ra,'(warning)');
        array_push($republic_acts, $empty_ra_def);
        $ra = array_pop($republic_acts);
        // Try to find a republic act serial number in this line.
        $matches = array();
        preg_match('@(Republic Act No)*([^0-9]*)([0-9]*)(.*)@i',$tag['text'],$matches);
        if ( is_numeric($matches[3]) ) {
          $ra['linktext'] = 'RA' . str_pad(ltrim($matches[3],'0'),5,'0',STR_PAD_LEFT);
          $ra['desc'] = trim($matches[4]);
          $republic_act->fetch($ra['linktext'], 'sn');
          $found = $republic_act->in_database();
          $ra['cached_sn'] = $found ? $republic_act->get_id() : NULL;
          if ( $found ) {
            $ra['link'][] = $republic_act->get_url();
            $ra['desc'] = $republic_act->get_description();
          }
        }
      }/*}}}*/
      // $ra['components'][] = $tag;
      if ( $is_texttag ) {
        // We're expecting metadata lines, specifically something resembling
        // - Approved by ... [DATE]
        $ra['aux'][] = $tag['text'];
        array_push($republic_acts, $ra);
        continue;
      }

      if ( $is_linktag ) {
        // Extract parts of an A tag containing republic act links
        $link = $tag['attrs']['HREF'];
        $ra['desc'] = str_replace(array('[BR]',"\n"),array(''," "),join('',$tag['cdata']));
        $test_url->fetch($link, 'url');
        $id = $test_url->in_database() ? $test_url->get_id() : NULL;
        $ra['link'][$id] = $link; 
        $matches = array();
        preg_match('@(Republic Act No)*([^0-9]*)([0-9]*)(.*)@i',$ra['desc'],$matches);
        $ra['linktext'] = 'RA' . str_pad(ltrim($matches[3],'0'),5,'0',STR_PAD_LEFT);
        $ra['desc'] = trim($matches[4]);

        $republic_act->fetch($ra['linktext'], 'sn');
        $found = $republic_act->in_database();
        $ra['cached_sn'] = $found ? $republic_act->get_id() : NULL;
        $id = NULL;
        if ( $found ) {
          $test_url->fetch($republic_act->get_url(), 'url');
          $found = $test_url->in_database();
          $id = $test_url->get_id();
        }
        $ra['link'][$id] = $republic_act->get_url();
        array_push($republic_acts, $ra);
        continue;
      }
      array_push($republic_acts, $ra);
    }/*}}}*/

    // Make the RA serial number be the resultset array key
    $republic_acts = array_combine(
      array_map(create_function('$a', 'return $a["linktext"];'),$republic_acts),
      $republic_acts
    );

    return $republic_acts;
  }/*}}}*/

  function seek_postparse_bypathonly_47fcf9913bde652d1ecee59501b11c59(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/

    /** Router **/ 

    $pdf_sys_type = $this->query_extract_type_parameter($urlmodel);
    $this->syslog( __FUNCTION__, __LINE__, "(marker) Pagecontent {$pdf_sys_type} by-path parser invocation for " . $urlmodel->get_url() );

    switch ($pdf_sys_type) {
      case 'republic_act' : 
        return $this->pdf_sys_republic_act($parser,$pagecontent,$urlmodel);
        break;
      default:
        return $this->common_unhandled_page_parser($parser,$pagecontent,$urlmodel);
        break;
    }
  }/*}}}*/

  function pdf_sys_republic_act(& $parser, & $pagecontent, & $urlmodel) { /*{{{*/

    // http://www.senate.gov.ph/lis/pdf_sys.aspx?congress=15&type=republic_act 
    // Iterate through pager URLs to load 

    $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked for {$urlmodel} ---------------------------------------" );

    $republic_act = new RepublicActDocumentModel();
    $ra_parser    = new SenateRaListParseUtility();
    $test_url     = new UrlModel();

    $ra_parser->set_parent_url($urlmodel->get_url())->parse_html($pagecontent,$urlmodel->get_response_header());

    $pagetitle    = 'Republic Acts'; 
    $pagecontent  = ''; // join('',$ra_parser->get_filtered_doc());

    // Extract pager

    $pages = array();
    $pager_links = $this->extract_pager_links(
      $pages,
      $parser->cluster_urldefs,
      '1cb903bd644be9596931e7c368676982');

    $pagecontent .= join('', $pager_links);

    // Extract Congress selector

    $extracted_links = array();

    $congress_switcher = $this->extract_pager_links(
      $extracted_links,
      $parser->cluster_urldefs,
      '9f35fc4cce1f01b32697e7c34b397a99');

    $pagecontent .= "&nbsp;&nbsp;Congress: " . join('',$congress_switcher) . '<br/><br/>';

    $pages = array_flip(array_values(array_merge(
      array($urlmodel->get_url()),
      $pages
    )));

    $on_this_page = $this->fetch_legislation_links($ra_parser, $republic_act); 

    $dumped = FALSE;

    $linkset = array(); 
    foreach ( $pages as $page_url => $d ) {/*{{{*/
      $test_url->fetch($page_url,'url');
      if ( $test_url->in_database() ) {
        $url_id = $test_url->get_id();
        $this->syslog(__FUNCTION__,__LINE__, "? Testing URL #{$url_id} {$page_url}");
        $pages[$page_url] = $urlmodel->get_id() != $url_id
          ? $url_id
          : NULL // We want to skip this page
          ;
        if ( is_null($pages[$page_url]) ) {
          $this->syslog(__FUNCTION__,__LINE__, "- Skipping URL #{$url_id} {$page_url}");
          $republic_acts = $on_this_page;
        } else {
          $content_length = $test_url->get_content_length();
          $this->syslog(__FUNCTION__,__LINE__, "* Loading URL #{$url_id} {$content_length} octets {$page_url}:");
          $ra_parser->
            reset()->
            set_parent_url($urlmodel->get_url())->
            parse_html($test_url->get_pagecontent(),$test_url->get_response_header());
          $republic_acts = $this->fetch_legislation_links($ra_parser, $republic_act); 
        }
      }
      krsort($republic_acts);
      if ( !$dumped ) {
        $dumped = TRUE;
        // $this->recursive_dump($republic_acts,'(marker) -');
      }
      foreach ( $republic_acts as $ra_number => $ra ) {
        // RA10174 =>
        //   link =>
        //      0 => http://www.senate.gov.ph/republic_acts/ra%2010174.pdf
        //      1 => http://www.congress.gov.ph/download/ra_15/RA10174.pdf
        //   desc => An Act Establishing the Peoples Survival Fund to Provide Long-Term Finance Streams to Enable the Government to Effectively Address the Problem of Climate Change
        //   linktext => RA10174
        //   aux =>
        //      0 => Approved by the President on August 16, 2012
        //   cached_url => 663
        //   cached_sn => 3501
        $links = array();
        foreach ($ra['link'] as $url_id => $url) {
          $host = UrlModel::parse_url($url,PHP_URL_HOST);
          $linktext = (0 == count($links)) ?  $ra['linktext'] : $host;
          $cached = 0 < intval($url_id) ? ' cached' : ' uncached';
          $urlhash = UrlModel::get_url_hash($url);
          $actual_link = <<<EOH
<a id="{$urlhash}" class="legiscope-remote{$cached}" href="{$url}">{$linktext}</a>
EOH;
 
          $links[] .= (0 == count($links))
            ? $actual_link
            : <<<EOH
Alternate {$actual_link}
EOH
            ;

        }
        if ( 0 < count($links) ) $linkset[$ra_number] .= '<li>' . join('&nbsp;',$links) . '</li>';
      }
      $republic_acts = array();
    }/*}}}*/
    krsort($linkset);
    $linkset = join("\n",$linkset);
    $linkset = <<<EOH
<ul class="link-cluster">
{$linkset}
</ul>
EOH;

    $pagecontent .= <<<EOH

{$linkset}

<script type="text/javascript">
$(function(){ $('#doctitle').html('{$pagetitle}');});
</script>
EOH;

  }/*}}}*/

  function seek_postparse_edd4db85190acf2176ca125df8fe269a(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked for " . $urlmodel->get_url() );
    $this->common_unhandled_page_parser($parser,$pagecontent,$urlmodel);
  }/*}}}*/


  /** House Bill route handler **/

  function leg_sys_bill(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/

    $pagecontent     = '';
    $senate_bill     = new SenateBillDocumentModel();
    $senate_bill_url = new UrlModel();

    $urlmodel->set_last_fetch(time())->increment_hits()->stow();

    // Generate pager links

    $extracted_links = array();
    $pager_links = $this->extract_pager_links($extracted_links, $parser->cluster_urldefs, '1cb903bd644be9596931e7c368676982');
    $pagecontent .= join('', $pager_links);

    // Parse Senate Bill summary page markup
      
    $form_attributes = $this->extract_formaction($parser, $urlmodel);
    $parent_urlparts = UrlModel::parse_url($urlmodel->get_url());

    $filter_for_sbn_querypart = create_function('$a', 'return 1 == preg_match("@SBN-([0-9]*)@", $a["attrs"]["HREF"]) ? array("url" => $a["attrs"]["HREF"], "cdata" => $a["cdata"]) : NULL;' );
    $sbn_links = array_values(array_filter(array_map($filter_for_sbn_querypart, $parser->get_links())));

    foreach ( $sbn_links as $dummyindex => $sbn_link ) {/*{{{*/

      $link_attributes = array("legiscope-remote");

      // Normalize URL
      $url = array("url" => $sbn_link['url']);
      $sbn_links[$dummyindex]['url'] = UrlModel::normalize_url($parent_urlparts, $url);
      $url = $sbn_links[$dummyindex]['url'];

      // Construct description
      $cdata = is_array($sbn_link['cdata']) ? join('<br/>', $sbn_link['cdata']) : NULL;

      $senate_bill_url->fetch($url,'url');
      $senate_bill->fetch($url,'url');
      $cached = $senate_bill_url->in_database() && $senate_bill->in_database() && (0 < strlen($senate_bill->get_significance()));

      if ( !$cached && ($senate_bill->in_database() || $senate_bill_url->in_database()) ) {
        $senate_bill->remove();
        $senate_bill_url->remove();
      }

      if ( $senate_bill_url->in_database() ) {
        $referrers = $senate_bill_url->referrers('url');
        $links = '';
        foreach ( $referrers as $referrer_url ) {
          $links .= <<<EOH
<li>{$referrer_url}</li>
EOH;
        }
        $referrers = "<ul>{$links}</ul>"; 
      } else {
        $referrers = '';
      }

      $sbn_links[$dummyindex]['cached'] = $cached;

      $linktext = $url; 

      if ( $cached ) {
        $link_attributes[] = "cached";
        $senate_bill->fetch($senate_bill_url->get_url(),'url');
        $linktext = preg_replace('@(.*)SBN-([0-9]*)(.*)@','SB$2',$senate_bill_url->get_url());
      }

      $urlhash = UrlModel::get_url_hash($url);
      $link_attributes = join(' ', $link_attributes);
      $pagecontent .= <<<EOH

<span class="search-match-searchlisting"><hr/><a href="{$url}" class="{$link_attributes}" id="{$urlhash}">{$linktext}</a><br/>{$cdata}</span>
{$referrers}
<br/>

EOH;

    }/*}}}*/
    // $this->syslog( __FUNCTION__, __LINE__, "Target links" );
    // $this->recursive_dump($sbn_links,'(warning)');

    // $this->recursive_dump($form_attributes,'(warning)');
    if ( C('ENABLE_GENERATED_CONTENT_BUFFERING') ) {/*{{{*/
      if ( TRUE || !file_exists($cache_filename) || $parser->from_network ) {
        file_put_contents($cache_filename, $pagecontent);
      }
    }/*}}}*/

  }/*}}}*/

  function leg_sys_resolution(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    return $this->common_unhandled_page_parser($parser,$pagecontent,$urlmodel);
  }/*}}}*/

  function seek_postparse_bypath_62f91d11784860d07dea11c53509a732(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    /** Router **/ 
    // http://www.senate.gov.ph/lis/leg_sys.aspx?congress=15&type=bill 
    // 2013 March 16:  Individual Senate bills are enumerated across multiple pages.
    // Links found at this URL lead to a Javascript-triggered form; we should use the first level links available
    // on this page to immediately display the second-level link content.

    $leg_sys_type = $this->query_extract_type_parameter($urlmodel);

    $this->syslog( __FUNCTION__, __LINE__, "(marker) ********** Router: {$leg_sys_type} for " . $urlmodel->get_url() );

    switch ($leg_sys_type) {
      case 'bill' : 
        return $this->leg_sys_bill($parser,$pagecontent,$urlmodel);
        break;
      case 'resolution':
        return $this->leg_sys_resolution($parser,$pagecontent,$urlmodel);
        break;
      default:
        return $this->common_unhandled_page_parser($parser,$pagecontent,$urlmodel);
        break;
    }

  }/*}}}*/

  function seek_postparse_bypath_1fa0159bc56439f7a8a02d5b4f3628ff(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/

    $this->syslog( __FUNCTION__, __LINE__, "(marker) Level 2 Pagecontent by-path parser invocation for " . $urlmodel->get_url() );

    $senate_bill = new SenateBillDocumentModel(); 
      
    $form_attributes = $this->extract_formaction($parser, $urlmodel);
    // $this->recursive_dump($form_attributes,'(warning)');

    // The form action URL is assumed to be the parent URL of all relative URLs on the page
    $action_url             = new UrlModel($form_attributes['action']);
    $target_action          = UrlModel::parse_url($action_url->get_url());
    $sbn_regex_result       = preg_replace('@(.*)SBN-([0-9]*)(.*)@','SB$2',$target_action['query']);
    $target_query           = explode('&',$target_action['query']);
    // Decorate the action URL to create a fake caching target URL name.
    $target_query[]         = "metaorigin=" . $urlmodel->get_urlhash();
    $target_action['query'] = join('&', $target_query);
    $faux_url               = UrlModel::recompose_url($target_action);
    $faux_url               = new UrlModel($faux_url,TRUE);
    $faux_url_in_db         = $faux_url->in_database() ? "in DB" : "fetchable";

    $this->syslog( __FUNCTION__, __LINE__, "(marker) Real post action {$sbn_regex_result} URL {$action_url} Faux cacheable {$faux_url_in_db} url: {$faux_url}" );

    $not_in_database = !$faux_url->in_database();

    if ( $not_in_database || $parser->from_network ) {/*{{{*/
      $this->syslog( __FUNCTION__, __LINE__, "(marker) Faux cacheable url: {$faux_url} -> {$form_attributes['action']}" );
      $form_controls = $form_attributes['form_controls'];
      $form_controls['__EVENTTARGET'] = 'lbAll';
      $form_controls['__EVENTARGUMENT'] = NULL;
      $save_faux_url = $faux_url->get_url();
      $successful_fetch = $this->perform_network_fetch( $faux_url, $urlmodel->get_url(), $form_attributes['action'], $faux_url->get_url(), $form_controls );
      if ( $successful_fetch ) {
        // Switch back to the cached URL
        // $this->syslog( __FUNCTION__, __LINE__, "Faux url after fetch: {$faux_url}" );
        // $this->syslog( __FUNCTION__, __LINE__, "          Cached URL: {$save_faux_url}" );
        $faux_url->set_url($save_faux_url,TRUE);
        $pagecontent = $faux_url->get_pagecontent();
      } else {
        $pagecontent = "Failed to fetch response to form submission to {$action_url}";
        return;
      }
    }/*}}}*/
    else {
      $pagecontent = $faux_url->get_pagecontent();
    }

    ///////////////////////////////////////////
    $senate_bill_info = new SenateBillInfoParseUtility();
    $senate_bill_info->debug_tags = FALSE;
    $document_contents = $senate_bill_info->set_parent_url($urlmodel->get_url())->parse_html($pagecontent,$urlmodel->get_response_header());

    if ( array_key_exists('comm_report_url', $document_contents) ) { $u = array('url' => $document_contents['comm_report_url']); $l = UrlModel::parse_url($action_url->get_url()); $document_contents['comm_report_url'] = UrlModel::normalize_url($l, $u); }
    if ( array_key_exists('doc_url', $document_contents) )         { $u = array('url' => $document_contents['doc_url'])        ; $l = UrlModel::parse_url($action_url->get_url()); $document_contents['doc_url'] = UrlModel::normalize_url($l, $u); }

    $senate_bill->fetch($sbn_regex_result,'sn');
    if ( !$senate_bill->in_database() || $parser->from_network ) {/*{{{*/
      $document_contents['sn'] = $sbn_regex_result;
      $document_contents['url'] = $action_url->get_url();
      $document_contents['urlid'] = $action_url->get_id();
      krsort($document_contents);
      $senate_bill->set_contents_from_array($document_contents);
      $senate_bill->stow();
      $senate_bill->fetch($sbn_regex_result,'sn');
    }/*}}}*/
    $this->recursive_dump($document_contents,'(warning)');

    if ( $senate_bill->in_database() ) {

      $total_bills_in_system = $senate_bill->count();

      $doc_url_attrs = array('legiscope-remote');
      $faux_url->fetch($senate_bill->get_url(),'url');
      if ( $faux_url->in_database() ) $doc_url_attrs[] = 'cached';
      $doc_url_attrs = join(' ', $doc_url_attrs);

      $pagecontent = $senate_bill->substitute(<<<EOH
Senate bills in system: {$total_bills_in_system}
<span class="sb-match-item">{sn}</span>
<span class="sb-match-item sb-match-subjects">{subjects}</span>
<span class="sb-match-item sb-match-description">{description}</span>
<span class="sb-match-item sb-match-significance">Scope: {significance}</span>
<span class="sb-match-item sb-match-status">Status: {status}</span>
<span class="sb-match-item sb-match-main-referral-comm">Committee: {main_referral_comm}</span>
<span class="sb-match-item sb-match-doc-url">Document: <a class="{$doc_url_attrs}" href="{doc_url}">{sn}</a></span>
<span class="sb-match-item sb-match-committee-report-info">Committee Report: <a class="legiscope-remote" href="{comm_report_url}">{comm_report_info}</a></span>
EOH
      );
    }

    ///////////////////////////////////////////

    if ( C('ENABLE_GENERATED_CONTENT_BUFFERING') ) {
      if ( !file_exists($cache_filename) || $parser->from_network )
        file_put_contents($cache_filename, $pagecontent);
    }

  }/*}}}*/


  /** Committee Reports **/

  function seek_postparse_4c2e9f440b8f89e94f6de126ef0e38c4(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked for " . $urlmodel->get_url() );
    $this->common_unhandled_page_parser($parser,$pagecontent,$urlmodel);

    $common = new SenateCommonParseUtility();
    $common->set_parent_url($urlmodel->get_url())->parse_html($urlmodel->get_pagecontent(),$urlmodel->get_response_header());

    $this->recursive_dump(($paginator_form = $common->get_containers(
      '*[tagname=form]'
    )),'(marker) New');

    $paginator_form = $this->extract_form($parser->get_containers());
    $paginator_form = $paginator_form[0]['children'];
    $control_set = $this->extract_form_controls($paginator_form);

  }/*}}}*/

  function seek_postparse_bypath_44f799b5135aac003bf23fabbe947941(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked for " . $urlmodel->get_url() );
    $this->common_unhandled_page_parser($parser,$pagecontent,$urlmodel);
  }/*}}}*/

  function seek_by_pathfragment_791d51238202c15ff6456b035963dcd9(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked for " . $urlmodel->get_url() );
    $this->common_unhandled_page_parser($parser,$pagecontent,$urlmodel);
  }/*}}}*/


  /** Utilities **/

  function generic(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked for " . $urlmodel->get_url() );
    $this->common_unhandled_page_parser($parser,$pagecontent,$urlmodel);
  }/*}}}*/

  function extract_formaction(& $parser, & $urlmodel) {/*{{{*/
    $paginator_form  = $this->extract_form($parser->get_containers());
    $this->syslog( __FUNCTION__, __LINE__, "(marker) Forms found: " . count($paginator_form) );
    $paginator_attrs    = $paginator_form[0]['attrs'];
    $paginator_controls = $paginator_form[0]['children'];
    $this->syslog( __FUNCTION__, __LINE__, "(marker) Child controls: " . count($paginator_controls) );

    $link        = array('url' => $paginator_attrs['ACTION']);
    $parent      = UrlModel::parse_url($urlmodel->get_url());
    $form_action = UrlModel::normalize_url($parent, $link);

    // $this->syslog( __FUNCTION__, __LINE__, "Target action: {$form_action}" );

    // $this->recursive_dump($paginator_form,'(warning)');
    // $this->recursive_dump($paginator_controls,'(warning)');
    $returnset = $this->extract_form_controls($paginator_controls);
    if ( is_null($returnset['select_options']) ) {
      $returnset['select_options'] = array();
      $returnset['select_name'] = NULL;
    }
    // $this->recursive_dump($returnset,'(warning)');
    $returnset['action'] = $form_action;
    return $returnset;
  }/*}}}*/

  function extract_pager_links(array & $links, $cluster_urldefs, $url_uuid = NULL) {/*{{{*/
    $links = array();
    $pager_links = array();
    $senate_bill_url = new UrlModel();
    $this->recursive_dump($cluster_urldefs,'(warning)');
    //  20130401 - Typical entries found 
    //  1cb903bd644be9596931e7c368676982 =>
    //    query_base_url => http://www.senate.gov.ph/lis/pdf_sys.aspx
    //    query_template => congress=15&type=republic_act&p=({PARAMS})
    //    query_components =>
    //       93e32b68fb9806571523b93e5ca786da => 2|3|4|5|6|7|8|9|10
    //    whole_url => http://www.senate.gov.ph/lis/pdf_sys.aspx?congress=15&type=republic_act&p=({PARAMS})
    //  9f35fc4cce1f01b32697e7c34b397a99 =>
    //    query_base_url => http://www.senate.gov.ph/lis/pdf_sys.aspx
    //    query_template => type=republic_act&congress=({PARAMS})
    //    query_components =>
    //       361d558f79a9d15a277468b313f49528 => 15|14|13
    //    whole_url => http://www.senate.gov.ph/lis/pdf_sys.aspx?type=republic_act&congress=({PARAMS})
    foreach( $cluster_urldefs as $url_uid => $urldef ) {/*{{{*/
      if ( !is_null($url_uuid) && !($url_uid == $url_uuid ) ) continue;
      $counter = 0;
      $have_pullin_link = FALSE;
      foreach ( $urldef['query_components'] as $parameters ) {/*{{{*/// Loop over variable query components
        $parameters = array_flip(explode('|', $parameters));
        ksort($parameters);
        $parameters = array_flip($parameters);
        foreach ( $parameters as $parameter ) {/*{{{*/
          $counter++;
          $link_class = array("legiscope-remote");
          $href = str_replace('({PARAMS})',"{$parameter}","{$urldef['whole_url']}");
          $urlhash = UrlModel::get_url_hash($href);
          $senate_bill_url->fetch($urlhash,'urlhash');
          $is_in_cache = $senate_bill_url->in_database();
          if ( $is_in_cache ) $link_class[] = 'cached';
          if ( ($counter >= 5 || !$is_in_cache) && !$have_pullin_link ) {
            $have_pullin_link = TRUE;
            // $link_class[] = "pull-in"; 
          }
          $link_class = join(' ',$link_class);
          $links[$urlhash] = $href;
          $pager_links[] = <<<EOH
<span class="link-faux-menuitem"><a class="{$link_class}" href="{$href}" id="{$urlhash}">{$parameter}</a></span>

EOH;
        }/*}}}*/
      }/*}}}*/
    }/*}}}*/
    return $pager_links;
  }/*}}}*/

  function member_uuid_handler(array & $json_reply, UrlModel & $url, $member_uuid) {/*{{{*/
    $member = new SenatorDossierModel();
    $member->fetch( $member_uuid, 'member_uuid');
    if ( $member->in_database() ) {
      $image_content_type = $url->get_content_type();
      $image_content = base64_encode($url->get_pagecontent());
      $member_avatar_base64 = "data:{$image_content_type};base64,{$image_content}";
      $json_reply['altmarkup'] = utf8_encode($member_avatar_base64);
      if ( 'false' == $this->filter_post('no_replace','false') ) {
        $this->syslog(__FUNCTION__,__LINE__, "(marker) Replacing {$member_uuid} avatar: {$json_reply['altmarkup']}");
        $member->set_avatar_image($member_avatar_base64)->stow();
      }
      $this->syslog(__FUNCTION__,__LINE__, "(marker) Sending member {$member_uuid} avatar: {$json_reply['altmarkup']}");
    }
  }/*}}}*/

  function query_extract_type_parameter(& $urlmodel) {/*{{{*/
    $path_query = UrlModel::parse_url($urlmodel->get_url(),PHP_URL_QUERY);
    $query_parts = UrlModel::decompose_query_parts($path_query);
    $this->recursive_dump($query_parts,'(marker) Hdl');
    return $query_parts['type'];
  }/*}}}*/

}
