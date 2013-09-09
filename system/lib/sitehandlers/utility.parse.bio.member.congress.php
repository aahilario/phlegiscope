<?php

/*
 * Class CongressMemberBioParseUtility
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class CongressMemberBioParseUtility extends CongressCommonParseUtility {
  
  var $complete_document_container = array();
  var $total_bills_parsed = 0;

  function __construct() {/*{{{*/
    parent::__construct();
    $this->initialize_related_source_links_parse();
  }/*}}}*/

  function get_document_sn_regex() {/*{{{*/
    return '@^([A-Z]{2,3})([-]*)([0-9]*)$@i';
    /*
    $this->document_sn_regex = '@^([A-Z]{2,3})([-]*)([0-9]*)$@i';
    return $this->document_sn_regex;
    */
  }/*}}}*/

  function ru_p_open(& $parser, & $attrs, $tagname ) {/*{{{*/
    $this->add_cdata_property();
    $this->push_container_def($tagname, $attrs);
    return TRUE;
  }/*}}}*/
  function ru_p_cdata(& $parser, & $cdata) {/*{{{*/
    return $this->append_cdata($cdata);
  }/*}}}*/
  function ru_p_close(& $parser, $tag) {/*{{{*/
    // SVN #693: Removed legacy parser code 
    return $this->embed_container_in_parent($parser,$tag);
  }/*}}}*/

  function ru_ul_open(& $parser, & $attrs, $tagname ) {/*{{{*/
    $this->push_container_def($tagname, $attrs);
    return TRUE;
  }/*}}}*/
  function ru_ul_cdata(& $parser, & $cdata) {/*{{{*/
    return TRUE;
  }/*}}}*/
  function ru_ul_close(& $parser, $tag) {/*{{{*/
    $this->stack_to_containers();
    return TRUE;
  }/*}}}*/

  function ru_article_open(& $parser, & $attrs, $tag ) {/*{{{*/
    $this->add_cdata_property();
    $this->push_container_def($tag, $attrs);
    return TRUE;
  }/*}}}*/
  function ru_article_cdata(& $parser, & $cdata) {/*{{{*/
    return $this->append_cdata($cdata);
  }/*}}}*/
  function ru_article_close(& $parser, $tag) {/*{{{*/
    if ( 0 < strlen($this->representative_name) ) {
      $this->pop_tagstack();
      $this->current_tag['attrs']['id'] = 'representative-data';
      $this->push_tagstack();
    }
    $this->embed_container_in_parent($parser,$tag); // Fills $this->current_tag
    if ( $this->current_tag['attrs']['id'] == 'representative-data' ) {
      $data = nonempty_array_element($this->current_tag,'children');
      $this->reorder_with_sequence_tags($data);
      // Extract all parseable information on this page from this <article>
      while ( 0 < count($data) ) {/*{{{*/
        $cell = array_shift($data);
        $cdata = array_filter(nonempty_array_element($cell, 'cdata',array()));
        $image = nonempty_array_element($cell,'image');
        if ( !empty($image) ) {
          $this->member_contact_details['avatar'] = $image;
          continue;
        }
        // Skip empty lines
        if ( 0 == count($cdata) ) continue;
        // FIXME: Multiline entries may contain either office or rep contact data.
        if ( 1 < count($cdata) ) {
          $this->member_contact_details['contact'] = $cdata;
          continue;
        }
        if ( !array_key_exists('extra', $this->member_contact_details) ) {
          $this->member_contact_details['extra'] = array();
        }
        $cdata = join('',$cdata);
        if ( 0 == strlen(preg_replace("@[^-A-Z0-9ñ,. ]@i","",$cdata)) ) {
        }
        else if ( 1 == preg_match('@(district representative|party list)@i', $cdata) ) {
          $this->member_contact_details['extra'][1] = $cdata;
        }
        else if ( 1 == preg_match('@^term:@i', $cdata) ) {
          $term = preg_replace('@[^0-9]@','',$cdata);
          $this->member_contact_details['extra'][2] = $term;
        }
        else {
          $this->syslog(__FUNCTION__,__LINE__,"(warning) Unparsed <article id='representative-data'>...</article> text '{$cdata}', adding to [bailiwick]");
          $this->member_contact_details['bailiwick'] = $cdata;
        }
      }/*}}}*/

    }
    return TRUE;
  }/*}}}*/

  // TR tags contain individual Representative-authored measures.

  function ru_tr_open(& $parser, & $attrs, $tag ) {/*{{{*/
    $this->add_cdata_property();
    $this->push_container_def($tag, $attrs);
    return TRUE;
  }/*}}}*/
  function ru_tr_cdata(& $parser, & $cdata) {/*{{{*/
    return $this->append_cdata($cdata);
  }/*}}}*/
  function ru_tr_close(& $parser, $tag) {/*{{{*/

    $this->embed_container_in_parent($parser,$tag); // Fills $this->current_tag['children']

    $debug_method = FALSE;

    if ( $debug_method ) {
      $this->syslog( __FUNCTION__, __LINE__, "(marker) F ----RAW-------------------------- " );
      $this->recursive_dump($this->current_tag,'(marker) - - - Main ');
    }

    $bill = array(
      'bill'             => NULL,
      'bill-url'         => NULL,
      'bill-engrossed'   => NULL,
      'history'          => NULL,
      'bill-title'       => NULL,
      'principal-author' => NULL,
      'status'           => NULL,
      'ref'              => NULL,
      'seq'              => $this->current_tag['attrs']['seq'],
    );

    while ( 0 < count(nonempty_array_element($this->current_tag,'children',array())) ) {
      $item = array_shift($this->current_tag['children']);
      $item_container_hash = nonempty_array_element($item,'sethash');
      if ( array_element($item,'tag-class') == 'history' ) {
        $bill['history'] = nonempty_array_element($item,'onclick-param');
        $this->unset_container_by_hash($item_container_hash);
        continue;
      }
      $cdata = nonempty_array_element($item,'cdata',0);
      $regex_matches = array();
      if ( 0 == count($cdata) ) {
      }
      else if ( 1 < count($cdata) ) {
      }
      else if (array_key_exists('header',$item)) {
        $bill['bill'] = $item['header'];  
      } 
      else if (1 == preg_match($this->span_content_prefix_regex, ($cdata = nonempty_array_element($cdata,0)), $regex_matches)) {
        $component = preg_replace(
          array_keys($this->span_content_prefix_map),
          array_values($this->span_content_prefix_map),
          $cdata
        );
        $bill[$component] = trim(array_element($regex_matches,2,'--'));
      }
      else {
        // FIXME: Make sure to capture other components of description in blocks above
        $bill['bill-title'] = $cdata;
      }
      $this->unset_container_by_hash($item_container_hash);
    }

    $item_container_hash = array_element($item_container_hash,'sethash');
    $this->unset_container_by_hash($item_container_hash);

    $sn = nonempty_array_element($bill,'bill');
    if ( !empty($sn) ) $this->complete_document_container[$sn] = $bill;

    if ( $debug_method )
    $this->recursive_dump($bill,'(marker) - + + Bill ');

    if (0) if ( !$signature['a'] && $signature['b'] ) {/*{{{*/// Bills
      // TODO: Consolidate this code with CongressGovPh::seek_postparse_d_billstext 
      foreach ( $container as $tag ) {/*{{{*/

        if ( !is_null(array_element($tag,'prune-to'))) {
          array_push($bills, $tag);
          continue;
        }

        if ( array_key_exists('text',$tag) && 1 == preg_match($this->get_document_sn_regex(), $tag['text']) ) {/*{{{*/

          if ( $debug_method ) $this->syslog(__FUNCTION__,__LINE__,"(marker) -- - - Found {$tag['text']}");

          if ( 0 < count($bills) ) {
            $bill = array_pop($bills);
            array_push($bills, $bill);
          }
          array_push($bills,array(
            'bill'             => $tag['text'],
            'bill-url'         => NULL,
            'bill-engrossed'   => NULL,
            'history'          => NULL,
            'bill-title'       => NULL,
            'principal-author' => NULL,
            'status'           => NULL,
            'ref'              => NULL,
          ));
          continue;
        }/*}}}*/

        if ( array_key_exists('image', $tag) ) continue;

        if ( array_key_exists('url', $tag) && ('[HISTORY]' == strtoupper(trim($tag['text']))) ) {/*{{{*/
          $clickevent = array_element($tag,'onclick');
          if ( is_null($clickevent) ) continue;
          $displayWindow_regex = "display_Window\('([^']*)','([0-9]*)'\)";
          $matches = array();
          if ( !(1 == preg_match("@{$displayWindow_regex}@i", $clickevent, $matches ) ) ) continue;
          if ( is_array($this->page_url_parts) && (0 < count($this->page_url_parts)) ) {
            $bill = array_pop($bills);
            $matches = $matches[1]; // The URL itself.
            $fixurl = array('url' => $matches);
            $tag['url'] = UrlModel::normalize_url($this->page_url_parts, $fixurl);
            if ( $debug_method ) $this->syslog(__FUNCTION__,__LINE__,"(marker) - - - Normalized {$tag['text']} URL to {$tag['url']}");
            $bill['history'] = $tag['url'];
            array_push($bills,$bill);
          }
          continue;
        }/*}}}*/

        $bill = array_pop($bills);
        if ( is_null(array_element($bill,'history')) && array_key_exists('url',$tag) && (1 == preg_match("@(history of bill)@i", $tag["title"])) ) {
          $bill['history'] = $tag['url'];
          array_push($bills, $bill); continue;
        }
        if ( is_null(array_element($bill,'bill-engrossed')) && array_key_exists('url',$tag) && (1 == preg_match("@(text of bill)@i", $tag["title"])) ) {
          $bill['bill-engrossed'] = $tag['url'];
          array_push($bills, $bill); continue;
        }
        if ( is_null(array_element($bill,'bill-url')) && array_key_exists('url',$tag) && (1 == preg_match("@(text of bill as filed)@i", $tag["title"])) ) {
          $bill['bill-url'] = $tag['url'];
          array_push($bills, $bill); continue;
        }
        if ( is_null(array_element($bill,'ref')) && array_key_exists('text',$tag) && ( 1 == preg_match('@^\[(.*)\]$@', trim($tag['text'])) ) ) {
          $bill['ref'] = $tag['text'];
          array_push($bills, $bill); continue;
        }
        if ( is_null(array_element($bill,'status')) && array_key_exists('text', $tag) && (1 == preg_match('@^Status:@i',$tag['text'])) ) {
          $bill['status'] = preg_replace('@^(Status:)([ ]*)@i','',$tag['text']);
          array_push($bills, $bill); continue;
        }
        if ( is_null(array_element($bill,'principal-author')) && array_key_exists('text', $tag) && (1 == preg_match('@^Principal author:@i',$tag['text'])) ) {
          $bill['principal-author'] = preg_replace('@^(Principal author:)([ ]*)@i','',$tag['text']);
          array_push($bills, $bill); continue;
        }
        if ( is_null(array_element($bill,'bill-title')) && array_key_exists('text', $tag) ) {
          $bill['bill-title'] = $tag['text'];
        }
        array_push($bills, $bill);
      }/*}}}*/
      continue;
    }/*}}}*/

    $this->total_bills_parsed++;

    if ( $this->total_bills_parsed > 200 ) {
      // Suspend parsing of the remainder of the document
      $this->syslog(__FUNCTION__,__LINE__,"(warning) - - - - - - - Parsing suspended - - - - - - -");
      // $this->set_freewheel();
    }

    return FALSE;
  }/*}}}*/

  function ru_strong_close(& $parser, $tag) {/*{{{*/
    $this->current_tag();
    $text = array(
      'header' => trim(join('',array_element($this->current_tag,'cdata'))),
      'seq'  => $this->current_tag['attrs']['seq'],
    );
    $this->add_to_container_stack($text);
    return TRUE;
  }/*}}}*/

  /* Span tags contain legislation items. Moved to parent class. */

  /** Rejected tags **/

  function ru_acronym_open(& $parser, & $attrs, $tagname ) {/*{{{*/
    return TRUE;
  }/*}}}*/
  function ru_acronym_cdata(& $parser, & $cdata) {/*{{{*/
    return TRUE;
  }/*}}}*/
  function ru_acronym_close(& $parser, $tag) {/*{{{*/
    return FALSE;
  }/*}}}*/

  function ru_h1_open(& $parser, & $attrs, $tagname ) {/*{{{*/
    return TRUE;
  }/*}}}*/
  function ru_h1_cdata(& $parser, & $cdata) {/*{{{*/
    return TRUE;
  }/*}}}*/
  function ru_h1_close(& $parser, $tag) {/*{{{*/
    return FALSE;
  }/*}}}*/

  /** Pre-extract committee, membership, and bill information **/
  // Parsing long pages of House Bill data is extremely memory-intensive:
  // a typical run (ca. SVN #597) consumes up to 490MB of memory with the parsed
  // document retained in memory.  We need the capability to specify parsing 
  // start and endpoint blocks (to enable partitioning of a long document), 
  // as well as the capability to preprocess blocks of bill data DURING 
  // XML parser document traversal. We will probably have to convert the
  // underlying PHP array store to make use of an SPL stack or queue,
  // to further reduce memory consumption and improve speed.
  //
  // An alternative approach (partially implemented here) transfers parsing workload to the remote client.
  // This approach entails Javascript parsing of the DOM, which is then transferred
  // to this server as pre-parsed JSON data, and should probably be used
  // for documents exceeding a certain size.  Do either 1) send the client a
  // partially scrubbed JSON representation of the data, or 2) send the source 
  // link up to the client, which then performs the necessary transformation of
  // the page to storable form.
  //
  // The latter approach will require fairly rigorous input checking, to prevent
  // a rogue user corrupting the data store.

  function ru_a_open(& $parser, & $attrs, $tag) {/*{{{*/
    parent::ru_a_open($parser,$attrs,$tag);
    return TRUE;
  }/*}}}*/
  function ru_a_close(& $parser, $tag) {/*{{{*/
    parent::ru_a_close($parser,$tag);
    return $this->generate_related_source_links($parser, $tag);
  }/*}}}*/

  function ru_h2_open(& $parser, & $attrs, $tagname ) {/*{{{*/
    $this->add_cdata_property();
  }/*}}}*/
  function ru_h2_cdata(& $parser, & $cdata) {/*{{{*/
    return $this->append_cdata($cdata);
  }/*}}}*/
  function ru_h2_close(& $parser, $tag) {/*{{{*/
    $this->current_tag();
    if ( 'mainheading' == array_element($this->current_tag['attrs'],'CLASS') ) {
      $representative_name = trim(join('',array_element($this->current_tag,'cdata')));
      $representative_name = preg_replace('@^hon.([ ]*)@i','', $representative_name);
      $this->representative_name = $representative_name;
      $this->syslog(__FUNCTION__,__LINE__,"(marker) - Representative {$representative_name}");
      $this->member_contact_details['fullname'] = $representative_name;
    }
    return TRUE;
  }/*}}}*/

  function ru_h3_open(& $parser, & $attrs, $tagname ) {/*{{{*/
    return $this->add_cdata_property();
  }/*}}}*/
  function ru_h3_cdata(& $parser, & $cdata) {/*{{{*/
    return $this->append_cdata($cdata);
  }/*}}}*/
  function ru_h3_close(& $parser, $tag) {/*{{{*/
    return $this->add_current_tag_to_container_stack();
  }/*}}}*/

  /** User markup-generating and utility methods **/

  function & get_member_contact_details() {/*{{{*/
    return $this->member_contact_details;
  }/*}}}*/

  function stow_parsed_representative_info(RepresentativeDossierModel & $m, UrlModel & $urlmodel) {/*{{{*/

    $debug_method = TRUE;

    $url  = new UrlModel();

    if ( $debug_method ) {
      $this->syslog( __FUNCTION__, __LINE__, "(marker) A ------------------------------ " );
    }
    if ( !$m->in_database() ) {
      $this->syslog( __FUNCTION__, __LINE__, "(marker) FIXME: Member {$urlmodel} not in DB" );
      return NULL;
    }

    $member = $this->get_member_contact_details();

    $this->recursive_dump($member,"(marker) A Detail --- ");

    // Extract room, phone, chief of staff (2013-03-25)
    $contact_regex = '@(((Chief of Staff|Phone):) (.*)|Rm. ([^,]*),)([^|]*)@i';
    $contact_items = array();
    if ( is_array($member) && array_key_exists('contact',$member) ) {/*{{{*/
      if ( $debug_method ) {
        $this->syslog( __FUNCTION__, __LINE__, "(marker) B ------------------------------ " );
      }
      preg_match_all($contact_regex, join('|',$member['contact']), $contact_items, PREG_SET_ORDER);
      $contact_items = array(
        'room'     => $contact_items[0][5],
        'phone'    => trim(preg_replace('@^([^:]*):@i','',trim($contact_items[0][6]))),
        'cos'      => $contact_items[1][4],
        //'xrole'    => $member['extra'][0],
        'term'     => preg_replace('@[^0-9]@','',$member['extra'][2]),
        'role'     => $member['extra'][1],
      );
      $this->recursive_dump($contact_items,"(marker) B CIs --- ");
    }/*}}}*/
    else $contact_items = NULL;

    // Determine whether the image for this representative is available in DB
    $url->fetch(UrlModel::get_url_hash($member['avatar']),'urlhash');

    if ( $url->in_database() && $m->in_database() ) {/*{{{*/
      if ( $debug_method ) {
        $this->syslog( __FUNCTION__, __LINE__, "(marker) C ------------------------------ " );
      }
      $image_content_type   = $url->get_content_type();
      $image_content        = base64_encode($url->get_pagecontent());
      $member_avatar_base64 = "data:{$image_content_type};base64,{$image_content}";
    }/*}}}*/

    $final_data = array(
      'fullname'     => $member['fullname'],
      'create_time'  => time(),
      'bio_url'      => $urlmodel->get_url(),
      'last_fetch'   => time(),
      'bailiwick'    => $member['bailiwick'],
      'avatar_url'   => $member['avatar'],
      'member_uuid'  => sha1(mt_rand(10000,100000) . ' ' . $urlmodel->get_url() . $member['fullname']),
      'contact_json' => $contact_items,
      'avatar_image' => $member_avatar_base64,
    );

    $this->syslog( __FUNCTION__, __LINE__, "(warning) D --------- DISABLED stow() --------------------- " );
    if (0) $member_id = $m->
      set_contents_from_array($final_data)->
      fields(array_keys($final_data))->
      stow();

    $this->syslog( __FUNCTION__, __LINE__, "(marker) D Parsed data, committable to DB ------------------------------ " );
    $this->recursive_dump($final_data,"(marker) --");
    $this->syslog( __FUNCTION__, __LINE__, "(marker) D ------------------------------ Returning with member #{$member_id}" );

    return $member_id;
  }/*}}}*/

  function extract_committee_membership_and_bills($selector = 'children[tagname=ul][id=nostylelist]' ) {/*{{{*/
    
    // 16th Congress: Committee membership and bill information pages are now separate links
    $debug_method = FALSE;

    if (0) {/*{{{*/
      $this->syslog( __FUNCTION__, __LINE__, "(marker) F ----RAW-------------------------- " );
      $this->recursive_dump($this->get_containers(),'(marker) - - - Main ');
    }/*}}}*/

    $this->recursive_dump(($entries = array_filter($this->get_containers(
      $selector 
    ))),(($debug_method) ? '(marker)' : '-') . ' - - - Main parser');

    $nullo = NULL;
    $this->assign_containers($nullo);

    $membership_role = array();
    $bills           = array();

    if ( !(0 < count($entries) ) ) {
      $this->syslog( __FUNCTION__, __LINE__, "(marker) F ------------------------------ " );
      $this->syslog( __FUNCTION__, __LINE__, "(marker) No records parsed, given selector '{$selector}'");
    }

    if (0) foreach ( $entries as $item => $container ) {/*{{{*/
      // The container presents committee membership as a series of URL+text+text containers
      if ( array_key_exists('image', $container) ) continue;
      // FIXME: Detection of sections should probably be relegated to the parse stage.
      $a = array_filter(array_map(create_function('$a', 'return array_key_exists("url", $a) && (1 == preg_match("@(about the committee)@i"         , $a["title"])) ? $a : NULL;'), $container));
      $b = array_filter(array_map(create_function('$a', 'return ((array_key_exists("url", $a) && (1 == preg_match("@(text of bill|history of bill)@i", $a["title"]))) || (array_key_exists("prune-to",$a))) ? $a : NULL;'), $container));
      $c = array_filter(array_map(create_function('$a', 'return array_key_exists("url", $a) && (1 == preg_match("@(about our member)@i"            , $a["title"])) ? $a : NULL;'), $container));
      $signature = array(
        'a' => 0 < count($a), 
        'b' => 0 < count($b),
        'c' => 0 < count($c),
      );
      if ( $signature['c'] && !$signature['a'] && !$signature['b'] ) {/*{{{*/// Representative item for a committee
        foreach ( $container as $tag ) {/*{{{*/
          if ( array_key_exists('text',$tag) ) $tag['text'] = $tag['text'];
          $classification = array_element($tag,'classification');
          if ( !is_null($classification) && !array_key_exists($classification,$membership_role) ) $membership_role[$classification] = array();
          if ( array_key_exists('url',$tag) ) {/*{{{*/
            $new_entry = array(
              'committee'     => $tag['text'], // Full name
              'committee-url' => $tag['url'],  // Bio URL
              'role'          => NULL, // Keep NULL, prefix to member entry 
              'ref'           => NULL, // Date when recorded
              'classification' => $classification,
            );
            array_push($membership_role, $new_entry);
            continue;
          }/*}}}*/
          $member = array_pop($membership_role);
          if ( array_key_exists('text',$tag) ) {
            if ( is_null($member['ref']) ) $member['ref'] = $tag['text'];
          }
          array_push($membership_role, $new_entry);
        }/*}}}*/
        continue;
      }/*}}}*/
      if ( $signature['a'] && !$signature['b'] ) {/*{{{*/// Committee membership
        foreach ( $container as $tag ) {
          if ( array_key_exists('text',$tag) ) $tag['text'] = $tag['text'];
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
    }/*}}}*/

    // Sort the lists

    $bills = $this->complete_document_container;

    if ( 0 < count($bills) ) {/*{{{*/
      $bills = array_combine(
        array_map(create_function('$a', 'return array_element($a,"bill");'), $bills),
        $bills
      );
      krsort($bills);
      array_walk($bills, create_function('& $a, $k', '$a = array_filter($a);'));
    }/*}}}*/

    if ( 0 == count($membership_role) ) {
      // Generate membership_role list from existing database records for this member.
    }
    else if ( 0 < count($membership_role) ) {/*{{{*/
      foreach ( $membership_role as $classification => $content ) {
        $membership_role[$classification] = array_filter(
          array_map(create_function(
            '$a', 'return array_element($a,"classification") == "'.$classification.'" ? $a : NULL;'
          ),$membership_role));
        $membership_role[$classification] = array_combine(
          array_map(create_function('$a','return array_element($a,"committee");'), $membership_role[$classification]),
          array_map(create_function('$a','unset($a["classification"]); return $a;'), $membership_role[$classification])
        );
        ksort($membership_role[$classification]);
        $membership_role[$classification] = array_values($membership_role[$classification]);
      }
    }/*}}}*/

    if ( $debug_method ) $this->recursive_dump($membership_role,"(marker) - - - Mem");

    return array(
      'resource_links'  => $this->extract_aux_information_links(),
      'membership_role' => $membership_role,
      'bills'           => $bills
    );
  }/*}}}*/

  function generate_committee_membership_markup(& $membership_role, $entry_wrap = NULL) {/*{{{*/
    $role_map = array(
      'chairperson' => 'Chairperson',
      'vice-chairperson' => 'Vice-Chairperson',
      'member-majority'  => 'Member for the majority',
      'member-minority' => 'Member for the minority',
    );
    $pagecontent = '';
    if ( !(0 < count($membership_role) ) ) {
      $this->syslog( __FUNCTION__, __LINE__, "(marker) G ------------------------------ " );
    }
    foreach ( $membership_role as $role ) {/*{{{*/
      if ( array_key_exists($role['role'],$role_map) ) $role['role'] = $role_map[$role['role']];
      $CommitteeName = ucwords(strtolower($role['committee']));
      $congress_tag = $role['congress_tag'];
      $congress_indicator = "({$congress_tag}th Congress)"; 
      $link_properties = array('legiscope-remote','no-autospider');
      $properties = array('congress-roles-committees',"congress-{$congress_tag}");
      if ( intval($congress_tag) != intval(C('LEGISCOPE_DEFAULT_CONGRESS')) ) $properties[] = 'hidden';
      $properties      = join(' ', $properties);
      $link_properties = join(' ', $link_properties);
      $pagecontent .= <<<EOH
<span class="{$properties}">{$role['role']} {$congress_indicator} <a href="{$role['committee-url']}" class="{$link_properties}">{$CommitteeName}</a> {$role['ref']}</span>
EOH;
    }/*}}}*/
    return $pagecontent;
  }/*}}}*/

  function generate_legislation_links_markup(RepresentativeDossierModel & $m, & $legislation_links, & $bills) {/*{{{*/
    $debug_method = FALSE;
    $pagecontent = array();
    if ( !(0 < count($bills) ) ) {
      $this->syslog( __FUNCTION__, __LINE__, "(marker) H ------------------------------ No bills received" );
    }
    $legislation_links = array();
    if ( $debug_method ) $this->recursive_dump($bills,"(marker) -- fooie --");
    foreach ( $bills as $bill ) {/*{{{*/

      $bill_urls = array(
        array_element($bill,'bill-url'),
        array_element($bill,'bill-engrossed'),
        array_element($bill,'history'),
      );

      $bill_urls = array_filter($bill_urls);

      if (!(0 < count($bill_urls))) continue; 

      $bill_urls = array_combine(
        array_map(create_function('$a', 'return UrlModel::get_url_hash($a);'), $bill_urls),
        $bill_urls
      );

      $active_hash = NULL;
      $bill_title = NULL;
      foreach ( $bill_urls as $hash => $url ) {
        $properties = array('congress-doc-name','legiscope-remote','no-autospider');
        if ( $url == array_element($bill,'history') ) $properties[] = 'document-meta';
        $properties = join(' ', $properties);
        $link = <<<EOH
<a id="{$hash}" class="{$properties} {cache-state}" href="{$url}">{$bill['bill']}</a>
EOH
        ;
        if ( is_null($bill_title) ) {
           $bill_title  = $link;
           $active_hash = $hash;
        }
        // Override contents of $parser->linkset
        $legislation_links[] = array(
          'seq'     => "{$bill['bill']}", // Sort key
          'url'     => $url,
          'urlhash' => $hash,
          'link'    => <<<EOH
<li>{$link}</li>
EOH
        );
      }
      if ( is_null($bill_title) ) $bill_title = $bill['bill'];

      $bill_longtitle = ucwords($bill['bill-title']);

      if ( !empty($bill['principal-author']) ) {
        $bill['principal-author'] = $m->replace_legislator_names_hotlinks($bill['principal-author']);
      }

      $principal_author = empty($bill['principal-author']) ? NULL : <<<EOH
<span class="congress-doc-element indent-1"><b>Principal author</b>: {$bill['principal-author']}</span>
EOH;

      $bill['status'] = $this->replace_legislative_sn_hotlinks($bill['status']);

      $pagecontent[$active_hash] = array(
        'seq'  => $bill['bill'],
        'link' => <<<EOH
<div class="congress-doc-item">
  <span class="congress-doc-name">{$bill_title}</span>
  <span class="congress-doc-element indent-1">{$bill_longtitle}</span>
  <span class="congress-doc-element indent-1"><b>Status</b>: {$bill['status']} {$bill['ref']}</span>
  {$principal_author}
</div>
EOH
      );
    }/*}}}*/
    $legislation_links_temp = array();
    $u = new UrlModel();

    while ( 0 < count($legislation_links) ) {/*{{{*/
      $n = 0;
      while ( ($n++ < 20) && (0 < count($legislation_links)) ) {
        $legislation_item = array_pop($legislation_links);
        if ( !is_null(array_element($legislation_item,'seq')) && !is_null(array_element($legislation_item,'url')) )
        $legislation_links_temp[$legislation_item['urlhash']] = $legislation_item; 
      }
      $u->where(array('AND' => array(
        'urlhash' => array_keys($legislation_links_temp)
      )))->recordfetch_setup();
      if ( $debug_method) $this->syslog( __FUNCTION__, __LINE__, "(marker) L ---------- Remaining: " . count($legislation_links) );

      $default_cache_state = 'cached';

      while ( $u->recordfetch($url) ) {
        $urlhash = UrlModel::get_url_hash($url['url']);
        $legislation_links_temp[$urlhash]["link"] = preg_replace(
          '@{cache-state}@',
          $default_cache_state,
          $legislation_links_temp[$urlhash]["link"]
        );
        if ( array_key_exists($urlhash, $pagecontent) ) 
        $pagecontent[$urlhash]["link"] = preg_replace(
          '@{cache-state}@',
          $default_cache_state,
          $pagecontent[$urlhash]["link"]
        );
      }
    }/*}}}*/

    array_walk($legislation_links_temp,create_function(
      '& $a, $k', '$a["link"] = preg_replace("@{cache-state}@i","uncached", $a["link"]);'
    ));
    array_walk($pagecontent,create_function(
      '& $a, $k', '$a["link"] = preg_replace("@{cache-state}@i","uncached", $a["link"]);'
    ));
    if (is_array($legislation_links_temp) && (0 < count($legislation_links_temp))) {
      $legislation_links = array_combine(
        array_map(create_function('$a', 'return "{$a["seq"]}{$a["urlhash"]}";'), $legislation_links_temp),
        array_map(create_function('$a', 'return $a["link"];'), $legislation_links_temp)
      );
      $legislation_links_temp = NULL;
       ksort($legislation_links);
    } else $legislation_links = array();
    if (is_array($pagecontent) && (0 < count($pagecontent))) {
      $pagecontent = array_combine(
        array_map(create_function('$a', 'return "{$a["seq"]}{$a["urlhash"]}";'), $pagecontent),
        array_map(create_function('$a', 'return $a["link"];'), $pagecontent)
      );
      ksort($pagecontent);
    }
    if ( $debug_method ) $this->recursive_dump($legislation_links, "(marker) - -- -");
    $this->syslog(__FUNCTION__,__LINE__,"(marker) - - - - Page content entries: " . count($pagecontent));
    return join('',$pagecontent);
  }/*}}}*/

  function representative_bio_parser(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/

    $debug_method = FALSE;

    if ( $debug_method ) {
      $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked for {$parser->trigger_linktext} " . $urlmodel->get_url() );
    }
    // Representative bio parser

    $m    = new RepresentativeDossierModel();
    $hb   = new HouseBillDocumentModel();
    $url  = new UrlModel();

    $urlmodel->ensure_custom_parse();

    $this->debug_tags = FALSE;
    $this->standard_parse($urlmodel);
    $this->debug_tags = FALSE;

    // $this->recursive_dump($this->get_containers('children[tagname=div][id=leftcontainer]'),"(marker) ++");
    // $this->reorder_with_sequence_tags($this->aux_information_links);
    // $this->recursive_dump($this->aux_information_links,"(marker) ++");

    $pagecontent = '';
    $member_record = array();

    if ( 0 < strlen($this->representative_name) ) {/*{{{*/

      $nameparts  = $m->parse_name($this->representative_name);
      $given_name = explode(' ', $nameparts['given']);
      $given_name = array_shift($given_name);
      $nameparts  = "{$nameparts['surname']}(.*){$given_name}";

      if ( $debug_method )
      $this->syslog(__FUNCTION__,__LINE__,"(marker) Using name regex '{$nameparts}'");

      $member_record = $m->fetch(array(
        'bio_url' => $urlmodel->get_url(),
        'fullname' => "REGEXP '({$nameparts})'",
      ),'OR');

      if ( !$m->in_database() || $parser->from_network || $this->update_existing ) {/*{{{*/

        $this->stow_parsed_representative_info($m,$urlmodel);

        $member_record = $m->fetch(array(
          'bio_url' => $urlmodel->get_url(),
          'fullname' => "REGEXP '({$nameparts})'",
        ),'OR');

      }/*}}}*/
      else {
        $member = $this->get_member_contact_details();
        $m->update_last_fetch();
        if ( TRUE || $debug_method ) {
          $this->syslog( __FUNCTION__, __LINE__, "(marker) Ep MCD ------------------------------ " );
          $this->recursive_dump($member,"(marker) Current Detail --- ");
        }
      }

      if ( $debug_method ) {/*{{{*/
        $this->syslog( __FUNCTION__, __LINE__, "(marker) E ------------------------------ " );
        $this->recursive_dump($member_record,"(marker) ++");
      }/*}}}*/

    }/*}}}*/

    $member               = $this->get_member_contact_details();
    $contact_items        = $m->get_contact_json();
    $member_avatar_base64 = $m->get_avatar_image();
    $member_uuid          = $m->get_member_uuid();
    $bailiwick            = $m->get_bailiwick();
    $member['avatar']     = $m->get_avatar_url();
    $member               = array_merge($member_record,$member);

    $member['fullname']   = join(' ',array_filter(array($member['firstname'],$member['mi'],$member['surname'],$member['namesuffix'])));

    extract($this->extract_committee_membership_and_bills()); // $resource_links, $membership_role, $bills 

    // Generate missing committee associations from database 
    if ( 0 == count($membership_role) && $m->in_database() ) {/*{{{*/
      // Generate list of committee memberships
      $representative_id = $m->get_id();
      $this->syslog( __FUNCTION__, __LINE__, "(marker) Fp {$representative_id} ------------------------------ " );
      $m->
        join(array('committees'))->
        where(array('AND' => array(
          'id' => $representative_id
        )))->
        recordfetch_setup();
      $r = NULL;
      while ( $m->recordfetch($r) ) {
        $committees = array_element($r,'committees');
        $join = nonempty_array_element($committees,'join');
        $data = nonempty_array_element($committees,'data');
        $membership_role["{$join['role']}{$data['committee_name']}"] = array(
          'committee' => $data['committee_name'],
          'congress_tag' => $join['congress_tag'],
          'committee-url' => $data['url'],
          'role' => $join['role'],
          'ref' => NULL,
        );
        // $this->recursive_dump($r,"(marker) -");
      }
      ksort($membership_role);

    }/*}}}*/
    else {/*{{{*/
      $this->syslog( __FUNCTION__, __LINE__, "(marker) Fp ------------------------------ " );
    }/*}}}*/

    $membership_role   = $this->generate_committee_membership_markup($membership_role);
    $legislation_links = array();
    $billsource        = $bills;
    $bills             = $this->generate_legislation_links_markup($m, $legislation_links, $billsource);
    $legislation_links = join('',$legislation_links);
    $linkset_resources = join('',array_map(create_function('$a', 'return $a["ls"];'), $resource_links)); 

    $target_page = $urlmodel->get_query_element('pg');

    if ( $urlmodel->is_custom_parse() ) {
      // Override/restore default behavior, recreate control panel links
      extract($this->generate_linkset($urlmodel->get_url()));
      $parser->linkset = $linkset; 
    }
    // Prepend legislation links
    $parser->linkset = "{$legislation_links}{$parser->linkset}";

    if ( empty($target_page) ) {/*{{{*/

      $tag_class_elements = array_map(create_function('$a','return $a["tag-class"];'), $resource_links);

      $tab_options = array();
      $link_members = array();

      if ( is_array($tag_class_elements) && (0 < count($tag_class_elements)) ) {
        $tab_options = array_combine(
          $tag_class_elements,
          array_map(create_function('$a','return $a["text"];'), $resource_links)
        );
        ksort($tab_options);
        $link_members = array_combine(
          $tag_class_elements, 
          array_map(create_function('$a','return $a["url"];'), $resource_links)
        );
        ksort($link_members);
      }

      // Trigger reload
      $tab_sources = array(
        'committee-membership' => $membership_role,
        'authorship' => $bills,
      );

      if ( 0 < count($link_members) && 0 < count($tab_options) ) 
      $tab_containers = $this->generate_committee_rep_tabs($link_members, $tab_options, $tab_sources);

      // To trigger automated fetch, crawl this list after loading the entire dossier doc;
      // then find the next unvisited dossier entry.
      $legislation_links = <<<EOH
<ul id="source-links" class="link-cluster">{$linkset_resources}</ul>
<ul id="house-bills-by-rep" class="link-cluster">{$legislation_links}</ul>
EOH;

      $pagecontent = <<<EOH
<div class="congress-member-summary clear-both">
  <h1 class="representative-avatar-fullname">{$member['fullname']}</h1>
  <img class="representative-avatar" width="120" height="120" id="image-{$member_uuid}" src="{$member_avatar_base64}" alt="{$member['fullname']}" />
  <input type="hidden" class="representative-avatar-source" name="image-ref" id="imagesrc-{$member_uuid}" value="{$member['avatar']}" />
  <span class="representative-avatar-head">Role: {$contact_items['district']} {$contact_items['role']}</span>
  <span class="representative-avatar-head">Bailiwick: {$bailiwick}</span>
  <span class="representative-avatar-head">Term: {$contact_items['term']}</span>
  <br/>
  <span class="representative-avatar-head">Room: {$contact_items['room']}</span>
  <span class="representative-avatar-head">Phone: {$contact_items['phone']}</span>
  <span class="representative-avatar-head">Chief of Staff: {$contact_items['cos']}</span>
</div>

<br/>
<div class="theme-options clear-both">
{$tab_containers}
</div>

EOH;
      $pagecontent .= $this->std_committee_detail_panel_js(); 
      //$pagecontent .= $this->trigger_default_tab('joint-author');
      $pagecontent .= $this->trigger_default_tab('authorship');

    }/*}}}*/
    else {
      switch ( $target_page ) {
        case 'auth':
          $pagecontent = $bills;
          break;
        case 'coauth':
          $pagecontent = $bills;
          break;
        case 'commem':
          $pagecontent = $membership_role;
          break; 
        default:
          $this->syslog( __FUNCTION__, __LINE__, "(marker) Ip  UNHANDLED {$target_page} ------------------------------ " );
          $pagecontent = NULL;
      }
      if ( !is_null($pagecontent) ) {/*{{{*/
        $pagecontent .= <<<EOH

<script type="text/javascript">
jQuery(document).ready(function(){
  enable_proxied_links('legiscope-remote');
  setTimeout((function(){ crawl_dossier_links(); }),1000);
});
</script>

EOH;

      }/*}}}*/
    }

    $parser->json_reply = array('retainoriginal' => TRUE);

    if ( $debug_method ) {
      $this->syslog( __FUNCTION__, __LINE__, "(marker) Final content length: " . strlen($pagecontent) );
      $this->syslog( __FUNCTION__, __LINE__, "(marker) I ------------------------------ " );
    }

  }/*}}}*/

}

