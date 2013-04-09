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

  function member_uuid_handler(array & $json_reply, UrlModel & $url, $member_uuid) {/*{{{*/
    $member = new RepresentativeDossierModel();
    $member->fetch( $member_uuid, 'member_uuid');
    if ( $member->in_database() ) {
      $image_content_type = $url->get_content_type();
      $image_content = $url->get_pagecontent();
      // $this->syslog( __FUNCTION__, __LINE__, "Fetched image content URL: " . $url->get_url() );
      // $this->syslog( __FUNCTION__, __LINE__, "Fetched image content SHA1: " . sha1($image_content) );
      // file_put_contents("./cache/member-image-{$member_uuid}", $image_content);
      $image_content = base64_encode($image_content);
      $member_avatar_base64 = "data:{$image_content_type};base64,{$image_content}";
      $json_reply['altmarkup'] = $member_avatar_base64;
      $member->set_avatar_image($member_avatar_base64)->stow();
      $this->syslog(__FUNCTION__,__LINE__, "Sending member {$member_uuid} avatar: {$json_reply['altmarkup']}");
    }
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
    $pagecontent = join('',$common->get_filtered_doc());

  }/*}}}*/

  function representative_bio_parser(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/

    $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked for " . $urlmodel->get_url() );
    // Representative bio parser

    $p   = new CongressMemberBioParseUtility();
    $m   = new RepresentativeDossierModel();
    $url = new UrlModel();

    // $m->dump_accessor_defs_to_syslog();
    $pagecontent = $urlmodel->get_pagecontent();
    $p->set_parent_url($urlmodel->get_url())->parse_html($pagecontent,$urlmodel->get_response_header());

    $this->syslog( __FUNCTION__, __LINE__, "(marker) Content length before parse: " . strlen($pagecontent) );

    $pagecontent = '';

    // If URL refers to an already cached representative bio, then
    //   Construct contact details from database record
    // else
    //   Parse contents of URL and store
    // fi
    $m->fetch($urlmodel->get_url(), 'bio_url');

    $member_uuid          = NULL;
    $member_avatar_base64 = NULL;
    $member               = array();

    if ( !$m->in_database() || $parser->from_network ) {/*{{{*/
      $this->syslog( __FUNCTION__, __LINE__, "(marker) A ------------------------------ " );
      if ( !$m->in_database() ) {
        $this->syslog( __FUNCTION__, __LINE__, "(marker) Member {$urlmodel} not in DB" );
      }
      $member = $p->get_member_contact_details();
      // $this->recursive_dump($member,__LINE__);
      // Extract room, phone, chief of staff (2013-03-25)
      // $this->recursive_dump($p->get_containers(),__LINE__);
      $contact_regex = '@(((Chief of Staff|Phone):) (.*)|Rm. ([^,]*),)([^|]*)@i';
      $contact_items = array();
      if ( is_array($member) && array_key_exists('contact',$member) ) {
        $this->syslog( __FUNCTION__, __LINE__, "(marker) B ------------------------------ " );
        preg_match_all($contact_regex, join('|',$member['contact']), $contact_items, PREG_SET_ORDER);
        $contact_items = array(
          'room'     => $contact_items[0][5],
          'phone'    => trim(preg_replace('@^([^:]*):@i','',trim($contact_items[0][6]))),
          'cos'      => $contact_items[1][4],
          'role'     => $member['extra'][0],
          'term'     => preg_replace('@[^0-9]@','',$member['extra'][2]),
          'district' => $member['extra'][1],
        );
        // $this->recursive_dump($contact_items,__LINE__);
      }
      // Determine whether the image for this representative is available in DB
      $url->fetch(UrlModel::get_url_hash($member['avatar']),'urlhash');
      if ( $url->in_database() ) {
        $this->syslog( __FUNCTION__, __LINE__, "(marker) C ------------------------------ " );
        $image_content_type   = $url->get_content_type();
        $image_content        = base64_encode($url->get_pagecontent());
        $member_avatar_base64 = "data:{$image_content_type};base64,{$image_content}";
        // $this->syslog(__FUNCTION__,__LINE__, "{$member['fullname']} avatar: {$member_avatar_base64}");
      }
      $m->set_fullname($member['fullname'])->
        set_create_time(time())->
        set_bio_url($urlmodel->get_url())->
        set_last_fetch(time())->
        set_avatar_url($member['avatar'])->
        set_member_uuid(sha1(mt_rand(10000,100000) . ' ' . $urlmodel->get_url() . $member['fullname']))->
        set_contact_json($contact_items)->
        set_avatar_image($member_avatar_base64)->
        stow();
      $member_uuid = $m->get_member_uuid();
      $this->syslog( __FUNCTION__, __LINE__, "(marker) D ------------------------------ " );
    }/*}}}*/
    else {/*{{{*/
      $this->syslog( __FUNCTION__, __LINE__, "(marker) E ------------------------------ " );
      $contact_items        = $m->get_contact_json();
      $member_avatar_base64 = $m->get_avatar_image();
      $member_uuid          = $m->get_member_uuid();
      $member['fullname']   = $m->get_fullname();
      if ( empty($member_avatar_base64) ) {/*{{{*/
        $url->fetch(UrlModel::get_url_hash($m->get_avatar_url()),'urlhash');
        if ( $url->in_database() ) {
          $image_content_type = $url->get_content_type();
          $member_avatar_base64 = base64_encode($url->get_pagecontent());
          $member_avatar_base64 = "data:{$image_content_type};base64,{$member_avatar_base64}";
          $m->set_avatar_image($member_avatar_base64);
          $m->stow();
          $m->fetch($member_uuid, 'member_uuid');
          $member_avatar_base64 = $m->get_avatar_image();
          $this->syslog(__FUNCTION__,__LINE__, "(marker) Stowed {$member['fullname']} avatar {$member_avatar_base64}");
        }
      }/*}}}*/
      // $this->syslog(__FUNCTION__,__LINE__, "[{$member['fullname']}] UUID[{$member_uuid}] avatar: {$member_avatar_base64}");
      $member['avatar']   = $m->get_avatar_url();
    }/*}}}*/

    if ( is_null($member_avatar_base64) || (strtoupper($member_avatar_base64) == 'NULL') ) $member_avatar_base64 = '';

    $summary = <<<EOH
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
EOH;

    if (0) $this->recursive_dump(
      array_filter(array_map(
        create_function('$a',
        'return strtolower($a["tagname"]) == "ul" ? $a : NULL;'
      ),
      $p->get_containers()
    )),'(marker) Container tags');

    $this->recursive_dump(($entries = array_filter($p->get_containers(
      'children[tagname=ul][id=nostylelist]'
    ))),'Main parser');

    // $pagecontent = $summary;
    // $pagecontent .= join('',$p->get_filtered_doc()); return;
    // --------------------------------------------------------
    $pagecontent = <<<EOH
{$summary}
<div class="congress-member-summary">
EOH;

    $membership_role = array();
    $bills = array();
    if ( !(0 < count($entries) ) ) {
      $this->syslog( __FUNCTION__, __LINE__, "(marker) F ------------------------------ " );
    }
    foreach ( $entries as $item => $container ) {/*{{{*/
      // The container presents committee membership as a series of URL+text+text containers
      if ( array_key_exists('image', $container) ) continue;
      $a = array_filter(array_map(create_function('$a','return array_key_exists("url", $a) && (1 == preg_match("@(about the committee)@i", $a["title"])) ? $a : NULL;'),$container));
      $b = array_filter(array_map(create_function('$a','return array_key_exists("url", $a) && (1 == preg_match("@(text of bill)@i", $a["title"])) ? $a : NULL;'),$container));
      $signature = array(
        'a' => 0 < count($a), 
        'b' => 0 < count($b),
      );
      // $this->recursive_dump($signature,__LINE__);
      if ( !$signature['a'] && !$signature['b'] ) continue;
      if ( $signature['a'] && !$signature['b'] ) {/*{{{*/// Committee membership
        foreach ( $container as $tag ) {
          if ( array_key_exists('url',$tag) ) {
            array_push($membership_role,array(
              'committee' => $tag['text'],
              'committee-url' => $tag['url'],
              'role' => NULL,
              'ref' => NULL,
            ));
            continue;
          }
          $memrole = array_pop($membership_role);
          if ( array_key_exists('text',$tag) ) {
            if ( is_null($memrole['role']) ) $memrole['role'] = $tag['text'];
            else if ( is_null($memrole['ref']) ) $memrole['ref'] = $tag['text'];
          }
          array_push($membership_role, $memrole);
        }
        // $this->recursive_dump($membership_role,__LINE__);
        //  10 =>
        //    committee => TRANSPORTATION
        //    committee-url => http://www.congress.gov.ph/committees/search.php?congress=15&id=E509
        //    role => Member for the Majority
        //    ref => (Journal #7)
        continue;
      }/*}}}*/
      if ( !$signature['a'] && $signature['b'] ) {/*{{{*/// Bills
        foreach ( $container as $tag ) {/*{{{*/
          if ( array_key_exists('text', $tag) && 1 == preg_match('@^([A-Z]{2,3})([0-9]*)$@i', $tag['text']) ) {/*{{{*/
            if ( 0 < count($bills) ) {
              $bill = array_pop($bills);
              array_push($bills, $bill);
            }
            array_push($bills,array(
              'bill'             => $tag['text'],
              'bill-url'         => NULL,
              'bill-title'       => NULL,
              'principal-author' => NULL,
              'status'           => NULL,
              'ref'              => NULL,
            ));
            continue;
          }/*}}}*/
          if ( array_key_exists('url', $tag) && ('[HISTORY]' == strtoupper(trim($tag['text']))) ) continue;
          if ( array_key_exists('image', $tag) ) continue;
          $bill = array_pop($bills);
          if ( is_null($bill['bill-url']) && array_key_exists('url',$tag) && (1 == preg_match("@(text of bill)@i", $tag["title"])) ) {
            $bill['bill-url'] = $tag['url'];
            array_push($bills, $bill); continue;
          }
          if ( is_null($bill['ref']) && array_key_exists('text',$tag) && ( 1 == preg_match('@^\[(.*)\]$@', trim($tag['text'])) ) ) {
            $bill['ref'] = $tag['text'];
            array_push($bills, $bill); continue;
          }
          if ( is_null($bill['status']) && array_key_exists('text', $tag) && (1 == preg_match('@^Status:@i',$tag['text'])) ) {
            $bill['status'] = preg_replace('@^(Status:)([ ]*)@i','',$tag['text']);
            array_push($bills, $bill); continue;
          }
          if ( is_null($bill['principal-author']) && array_key_exists('text', $tag) && (1 == preg_match('@^Principal author:@i',$tag['text'])) ) {
            $bill['principal-author'] = preg_replace('@^(Principal author:)([ ]*)@i','',$tag['text']);
            array_push($bills, $bill); continue;
          }
          if ( is_null($bill['bill-title']) && array_key_exists('text', $tag) ) {
            $bill['bill-title'] = $tag['text'];
          }
          array_push($bills, $bill);
        }/*}}}*/
      continue;
      }/*}}}*/
    }/*}}}*/

    $pagecontent .= <<<EOH
<hr/>
<div class="congress-member-roles">
EOH;
    if ( !(0 < count($membership_role) ) ) {
      $this->syslog( __FUNCTION__, __LINE__, "(marker) G ------------------------------ " );
    }
    foreach ( $membership_role as $role ) {/*{{{*/
      $CommitteeName = ucwords(strtolower($role['committee']));
      $pagecontent .= <<<EOH
<span class="congress-roles-committees">{$role['role']} <a href="{$role['committee-url']}" class="legiscope-remote">{$CommitteeName}</a> {$role['ref']}</span>
EOH;
    }/*}}}*/
    $pagecontent .= <<<EOH
</div>
EOH;

    $pagecontent .= <<<EOH
<hr/>
<div class="congress-legislation-tally link-cluster">
EOH;
    if ( !(0 < count($bills) ) ) {
      $this->syslog( __FUNCTION__, __LINE__, "(marker) H ------------------------------ " );
    }
    foreach ( $bills as $bill ) {/*{{{*/
      $bill_title = is_null($bill['bill-url']) 
        ? <<<EOH
{$bill['bill']}
EOH
      : <<<EOH
<a href="{$bill['bill-url']}" class="congress-doc-name legiscope-remote">{$bill['bill']}</a>
EOH
      ;
      $bill_longtitle = ucwords($bill['bill-title']);

      if ( !empty($bill['principal-author']) ) {
        $bill['principal-author'] = $m->replace_legislator_names_hotlinks($bill['principal-author']);
      }

      $principal_author = empty($bill['principal-author']) ? NULL : <<<EOH
<span class="congress-doc-element indent-1">Principal author: {$bill['principal-author']}</span>
EOH;

      $bill['status'] = $p->replace_legislative_sn_hotlinks($bill['status']);

      $pagecontent .= <<<EOH
<div class="congress-doc-item">
<span class="congress-doc-name">{$bill_title}</span>
<span class="congress-doc-element indent-1">{$bill_longtitle}</span>
<span class="congress-doc-element indent-1">Status: {$bill['status']} {$bill['ref']}</span>
{$principal_author}
</div>
EOH;
    }/*}}}*/
    $pagecontent .= <<<EOH
</div>
EOH;

    $pagecontent .= <<<EOH
</div>
<script type="text/javascript">
$(function(){
  update_representatives_avatars();
});
</script>
EOH;

    $parser->json_reply = array('retainoriginal' => TRUE);

    $this->syslog( __FUNCTION__, __LINE__, "(marker) Final content length: " . strlen($pagecontent) );
    $this->syslog( __FUNCTION__, __LINE__, "(marker) I ------------------------------ " );

    $pagecontent = utf8_encode($pagecontent);

  }/*}}}*/

  function seek_congress_memberlist(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    // http://www.congress.gov.ph/members 
    $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked for " . $urlmodel->get_url() );
    $common = new CongressCommonParseUtility();
    $member = new RepresentativeDossierModel();

    $common->set_parent_url($urlmodel->get_url())->parse_html($pagecontent,array('content-type' => 'charset=other'));
    $pagecontent      = array();
    $surname_initchar = null;
    $section_break    = NULL;
    $districts        = array();

    $this->recursive_dump(($member_list = $common->get_containers(
      'children[tagname=div][id*=content_body_right|content_body_left|i]'
    )),'Extracted rows');

    if (0) {
      $pagecontent = join('',$common->get_filtered_doc());
      return;
    }
  
    foreach ( $member_list as $item ) {/*{{{*/
      foreach( $item as $tag ) {
        if ( !array_key_exists('url',$tag) ) continue;
        $bio_url = $tag['url'];
        $member->fetch($bio_url,'bio_url');
        $cdata = $tag['text'];
        $name_regex = '@^([^,]*),(.*)(([A-Z]?)\.)*( (.*))@i';
        $name_match = array();
        preg_match($name_regex, $cdata, $name_match);
        $name_match = array(
          'first-name'     => trim($name_match[2]),
          'surname'        => trim($name_match[1]),
          'middle-initial' => trim($name_match[6]),
        );
        $name_index = "{$name_match['surname']}-" . UrlModel::get_url_hash($tag['attrs']['HREF']);
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
        if ( strlen($district) > 0 && !array_key_exists($district, $districts) ) $districts[$district] = array('00' => "<br/><br/><h1>" . preg_replace('@^Zz@','', $district) . "</h1>"); 

        $fullname = "{$name_match['first-name']} {$name_match['middle-initial']} {$name_match['surname']}";
        $surname_first = trim(strtoupper(substr($name_match['surname'],0,1)));
        if ( !(strlen($surname_first) > 0) ) continue;
        ////////
        //if ( is_null($member->get_avatar_image()) ) continue;
        ////////
        if ( is_null($surname_initchar) ) $surname_initchar = $surname_first;
        if ( $surname_first != $surname_initchar ) {

          $surname_initchar = $surname_first;
          $section_break = <<<EOH
<br/><br/><h1>{$surname_first}</h1><br/>
EOH;
        }
        $urlhash = UrlModel::get_url_hash($bio_url);
        $link_attributes = array("human-element-dossier-trigger");
        if ( $member->in_database() ) $link_attributes[] = "cached";
        else $link_attributes[] = 'trigger';
        $link_attributes = join(' ', $link_attributes);
        $candidate_entry = <<<EOH
<span><a href="{$bio_url}" class="{$link_attributes}" id="{$urlhash}">{$fullname}</a></span>

EOH;
        $pagecontent[$name_index] = "{$section_break}{$candidate_entry}";
        $districts[$district][$name_index] = $candidate_entry; 
        $section_break = NULL;
      }
    }/*}}}*/

    $districts = array_map(create_function('$a', 'ksort($a); return join("<br/>",$a);'), $districts);

    ksort($pagecontent);
    ksort($districts);

    $pagecontent = join('<br/>', $pagecontent);
    $districts = join(' ', $districts);

    $pagecontent = <<<EOH
<div class="congresista-dossier-list">
  <div class="float-left link-cluster">{$pagecontent}</div>
  <div class="float-left link-cluster">{$districts}</div>
</div>
<script type="text/javascript">
$(function(){
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
    if ( 1 == preg_match('@\?d=(.*)$@i',$urlmodel->get_url(),$match_urlpart) ) {
      $this->recursive_dump($match_urlpart,'(marker) URL match');
      switch ( strtolower($match_urlpart[1]) ) {
        case 'journals': return $this->seek_postparse_jnl($parser,$pagecontent,$urlmodel);
        case 'ra' : return $this->seek_postparse_ra($parser,$pagecontent,$urlmodel);
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

    $common      = new CongressCommonParseUtility();
    $ra_linktext = $parser->trigger_linktext;
    $common->
      set_parent_url($urlmodel->get_url())->
      parse_html($urlmodel->get_pagecontent(),$urlmodel->get_response_header());

    $pagecontent = join('',$common->get_filtered_doc());

  }/*}}}*/

  function seek_postparse_ra(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/

    $restore_url = NULL;

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

    $this->recursive_dump(($target_form = $this->extract_form_controls($target_form)),
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

    //$pagecontent = utf8_encode(join('',$common->get_filtered_doc()));
    //$pagecontent = utf8_encode($urlmodel->get_pagecontent());
    //$pagecontent = utf8_encode("{$replacement_content}<br/><hr/>") . "{$pagecontent}";
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

    // $pagecontent = utf8_encode("{$replacement_content}<br/><hr/>");
    $replacement_content = '';

    $this->syslog(__FUNCTION__,__LINE__,"(warning) Long operation. Parsing list of republic acts. Entries: " . count($ra_list));

    $parent_url = UrlModel::parse_url($parent_url);

    // $pagecontent .= utf8_encode($urlmodel->get_pagecontent());
    $republic_act = new RepublicActDocumentModel();

    $sorted_desc = array();
    foreach ( $ra_list as $ra ) {/*{{{*/

      $url       = UrlModel::normalize_url($parent_url, $ra);
      $urlhash   = UrlModel::get_url_hash($url);
      $ra_number = join(' ',$ra['bill-head']);
      // Fetch approval date and origin
      $approval_date = NULL;
      $origin        = NULL;
      if ( !array_key_exists('meta', $ra) ) {
        $this->syslog(__FUNCTION__,__LINE__,"(marker) Skipping {$ra_number} {$url}");
        continue;
      }

      // $this->syslog(__FUNCTION__,__LINE__,"(marker) Processing {$ra_number} {$url}");

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
      if ( is_array($match_parts) ) {
        $ra_meta = array_combine($match_parts[1], $match_parts[2]);
        $approval_date = trim($ra_meta['APPROVALDATE']);
        $origin        = trim($ra_meta['ORIGIN']);
      }

      if ( FALSE == strtotime($approval_date) ) $approval_date = NULL; 

      $ra_number_val = intval(preg_replace('@[^0-9]@','',$ra_number));

      if ( 0 < strlen($ra_number) ) {/*{{{*/// Stow the Republic Act record
        $matched_count = $republic_act->count(array('sn' => $ra_number));
        if (!(0 < $matched_count)) {/*{{{*/
          $now_time = time();
          $test_url->fetch($urlhash,'urlhash');
          $searchable = $test_url->in_database() ? 1 : 0; 
          $target_congress = preg_replace("@[^0-9]*@","",$parser->trigger_linktext);
          $this->syslog(__FUNCTION__,__LINE__, "(warning) Match {$matched_count} Stowing {$searchable} '{$ra_number}' [{$target_congress}] {$url}");
          $republic_act->fetch($ra_number,'sn');
          $republic_act->
            set_congress_tag($target_congress)->
            set_sn($ra_number)->
            set_origin($origin)->
            set_description(join(' ',$ra['desc']))->
            set_url($url)->
            set_approval_date($approval_date)->
            set_searchable($searchable)->
            set_create_time($now_time)->
            set_last_fetch($now_time)->
            stow();
          $matched_count = $republic_act->count(array('sn' => $ra_number));
          $sorted_desc[$ra_number] = $republic_act->get_standard_listing_markup($ra_number,'sn');
        }/*}}}*/
        else {/*{{{*/
          $this->syslog(__FUNCTION__,__LINE__, "(warning) No Update #{$ra_number} Match {$matched_count} Generating '{$ra_number}' {$url}");
          if ( C('DISPLAY_EXISTING_REPUBLIC_ACTS') ) {
            $sorted_desc["{$ra_number}"] = $republic_act->get_standard_listing_markup($ra_number,'sn');
          }
        }/*}}}*/
        if ( !(0 < $matched_count) ) $this->syslog(__FUNCTION__,__LINE__, "WARNING: Unable to stow {$ra_number}");
      }/*}}}*/

    }/*}}}*/

    $this->syslog(__FUNCTION__,__LINE__,'(marker) Elements ' . count($sorted_desc));
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
    $this->syslog( __FUNCTION__, __LINE__, "Invoked for " . $urlmodel->get_url() );

    // Custom content, generic parser does not handle the bills text content
    $pagecontent = $urlmodel->get_pagecontent();
    $this->syslog( __FUNCTION__, __LINE__, "(warning) EMPTY HANDLER.  Length: " . strlen($pagecontent) );

    return;
    $cache_filename = md5(__FUNCTION__ . $parser->trigger_linktext);
    $cache_filename = "./cache/{$this->subject_host_hash}-{$cache_filename}.generated";
    if ( $parser->from_network ) unlink($cache_filename);
    else if ( C('ENABLE_GENERATED_CONTENT_BUFFERING') ) {
      if ( $parser->from_network ) unlink($cache_filename);
      else if ( file_exists($cache_filename) ) {
        $this->syslog( __FUNCTION__, __LINE__, "Retrieving cached markup for " . $urlmodel->get_url() );
        $pagecontent = file_get_contents($cache_filename);
        return;
      }
    }

    $filter_url = new UrlModel();
    $house_bill = new HouseBillDocumentModel();
    $hb_listparser  = new CongressHbListParseUtility(); 
    $hb_listparser->debug_tags = FALSE;
    $hb_listparser->set_parent_url($urlmodel->get_url())->parse_html($pagecontent,$urlmodel->get_response_header());

    // $house_bill->dump_accessor_defs_to_syslog();

    $this->syslog( __FUNCTION__, __LINE__, "Elements: " . count($hb_listparser->get_containers()) );
    // $this->recursive_dump($hb_listparser->get_containers(),__LINE__);
    $pagecontent = NULL;
    $house_bills = array();
    $counter = 0;
    foreach ( $hb_listparser->get_containers() as $container_id => $container ) {/*{{{*/
      if ( !array_key_exists('children', $container) || !is_array($container['children']) ) continue;
      $entries                  = $container['children'];
      $container[$container_id] = NULL;
      $bill_head                = NULL;

      foreach ( $entries as $container ) {/*{{{*/
        if ( array_key_exists('bill-head', $container) ) {/*{{{*/

          // Dump existing bill record to stream
          if ( !is_null($bill_head) && array_key_exists($bill_head, $house_bills) ) {/*{{{*/
            $hb = $house_bills[$bill_head];
            $url             = $hb['document-url'];
            $urlhash         = UrlModel::get_url_hash($url);
            $hb['desc']      = $hb['title']; // $house_bill->get_description();
            $hb['bill-head'] = $hb['sn']; // $house_bill->get_sn();
            $hb['meta']      = NULL; // $house_bill->get_status(FALSE);

            $n = $house_bill->count(array('sn' => $bill_head));

            $cache_state     = array('legiscope-remote');
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
            if ( $counter++ < 10 )
            $content = <<<EOH
<div class="republic-act-entry">
<span class="republic-act-heading"><a href="{$url}" class="{$cache_state}" id="{$urlhash}">{$hb['bill-head']}</a></span>
<span class="republic-act-desc"><a href="{$url}" class="legiscope-remote" id="title-{$urlhash}">{$hb['desc']}</a></span>
<span class="republic-act-meta">Principal Author: {$hb['Principal Author']}</span>
<span class="republic-act-meta">Main Referral: {$hb['Main Referral']}</span>
<span class="republic-act-meta">Status: {$hb['Status']}</span>
</div>
EOH;
            $pagecontent .= $content;
            unset($house_bills[$bill_head]);
          }/*}}}*/

          $bill_head = join('',$container['bill-head']);
          $house_bills[$bill_head] = array(
            'sn' => $bill_head,
          );
          continue;
        } /*}}}*/
        if ( is_null($bill_head) ) continue;
        if ( array_key_exists('desc', $container) ) {
          $house_bills[$bill_head]['title'] = join('',$container['desc']);
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
      }/*}}}*/

    }/*}}}*/
    // $pagecontent = join('',$hb_listparser->get_filtered_doc());
    // $this->recursive_dump($house_bills,__LINE__);

    if ( C('ENABLE_GENERATED_CONTENT_BUFFERING') ) {
      file_put_contents($cache_filename, $pagecontent);
    }

  }/*}}}*/

  /** Automatically matched parsers **/

  function seek_postparse_bypathonly_0808b3565dcaac3a9ba45c32863a4cb5(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    // http://www.congress.gov.ph
    $this->common_unhandled_page_parser($parser,$pagecontent,$urlmodel);
  }/*}}}*/
           
  function seek_postparse_bypathonly_2e56f3e00b2b7764f027fe14c4910080(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked for " . $urlmodel->get_url() );
    $this->representative_bio_parser($parser,$pagecontent,$urlmodel);
  }/*}}}*/

  function seek_postparse_bypath_78a9ec5b5e869117bb2802b76bcd263e(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    $this->common_unhandled_page_parser($parser,$pagecontent,$urlmodel);
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
    $this->syslog( __FUNCTION__, __LINE__, "Invoked for " . $urlmodel->get_url() );
    $p = new CongressCommitteeListParseUtility();
    $p->set_parent_url($urlmodel->get_url())->parse_html($pagecontent,$urlmodel->get_response_header());

    $pagecontent = join('', $p->get_filtered_doc());

    $p->debug_operators = FALSE;
    $this->recursive_dump($containers = $p->get_containers(
      'children[tagname=div][id=main-ol]'
    ),"(marker) ++ All containers");

    if ( 0 < count($containers) ) {
      $pagecontent = '';
      foreach ( $containers as $container ) {
        $pagecontent .= "<div>";
        foreach ( $container as $tag ) {
          if ( array_key_exists('url', $tag) ) {
            $pagecontent .= <<<EOH
<a href="{$tag['url']}" class="legiscope-remote">{$tag['text']}</a>
EOH;
            continue;
          }
          $pagecontent .= <<<EOH
<span class="representative-name">{$tag['text']}</span><br/>
EOH;
        }
        $pagecontent .= "</div>";
      }
    }

    $parser->json_reply = array('retainoriginal' => TRUE);
  }/*}}}*/

  function seek_postparse_bypath_356caa1dcd2d3a76fcc6debce13393ff(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    // Republic Acts
    $this->seek_postparse_ra_hb($parser, $pagecontent, $urlmodel);
  }/*}}}*/

  function seek_by_pathfragment_8a807a96bdae8c210fd561e703c9c4b1(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    $this->seek_postparse_ra_hb($parser,$pagecontent,$urlmodel);
  }/*}}}*/

}
