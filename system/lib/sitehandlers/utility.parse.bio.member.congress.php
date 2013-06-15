<?php

/*
 * Class CongressMemberBioParseUtility
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class CongressMemberBioParseUtility extends CongressCommonParseUtility {
  
	var $member_contact_details = NULL;
  var $current_member_classification = NULL;
  var $link_title_classif_map = array();
  var $span_content_prefix_map = array();

  var $temporary_document_container = array();
  var $complete_document_container = NULL;
  var $total_bills_parsed = 0;

  function __construct() {/*{{{*/
    parent::__construct();
    $this->temporary_document_container = array();
    $this->link_title_classif_map = array(
      'about the committee' => 'committee',
      'text of bill'        => 'bill-url',
      'history of bill'     => 'history',
      'about our member'    => 'representative',
    );
    $this->link_title_classif_regex = '@(' . join('|',array_keys($this->link_title_classif_map)) . ')@i';
    $this->link_title_classif_map = array_combine(
      array_map(create_function('$a','return "@(.*)({$a})(.*)@i";'), array_keys($this->link_title_classif_map)),
      array_values($this->link_title_classif_map)
    );

    $this->span_content_prefix_map = array(
      'status' => 'status',
      'principal author' => 'principal-author',
    );
    $this->span_content_prefix_regex = '@^(' . join('|',array_keys($this->span_content_prefix_map)) . '):(.*)@i';
    $this->span_content_prefix_map = array_combine(
      array_map(create_function('$a','return "@^(({$a}):(.*))@i";'), array_keys($this->span_content_prefix_map)),
      array_values($this->span_content_prefix_map)
    );
  }/*}}}*/

  function get_document_sn_regex() {/*{{{*/
    return '@^([A-Z]{2,3})([-]*)([0-9]*)$@i';
    /*
    $this->document_sn_regex = '@^([A-Z]{2,3})([-]*)([0-9]*)$@i';
    return $this->document_sn_regex;
    */
  }/*}}}*/

  function ru_p_open(& $parser, & $attrs, $tagname ) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'] = array();
    $this->push_tagstack();
    if (!('silver_hdr' == array_element($this->current_tag['attrs'],'CLASS')))
    $this->push_container_def($tagname, $attrs);
    return TRUE;
  }/*}}}*/
  function ru_p_cdata(& $parser, & $cdata) {/*{{{*/
		if ( !empty($cdata) && ( 0 < count($this->tag_stack) ) ) {
      $this->pop_tagstack();
      $this->current_tag['cdata'][] = str_replace("\n"," ",trim($cdata));
      $this->push_tagstack();
    }
    return TRUE;
  }/*}}}*/
  function ru_p_close(& $parser, $tag) {/*{{{*/
		$skip = FALSE;
    $is_container = TRUE;
		$this->current_tag();
    $text = trim(join('',array_element($this->current_tag,'cdata')));
		if ( array_element($this->current_tag['attrs'],'CLASS') == 'meta' ) $skip = TRUE;
		else
		if ( array_element($this->current_tag['attrs'],'CLASS') == 'mem_info' ) {
			$container = array_pop($this->container_stack);
			array_push($this->container_stack, $container);
			if ( is_array($container) ) {
				// $this->recursive_dump($container,__LINE__);
				$images  = array_values(array_filter(array_map(create_function('$a', 'return array_key_exists("image", $a) ? $a["image"] : NULL;'), $container['children'])));
				$strings = array_values(array_filter(array_map(create_function('$a', 'return array_key_exists("text", $a) ? $a["text"] : NULL;'), $container['children']))); 
				$this->member_contact_details = array(
					'avatar'   => $images[0],
					'fullname' => $strings[0],
					'contact'  => explode("[BR]",preg_replace('@(\s)*\[BR\]$@','',trim($strings[1]))),
					'extra'    => explode("[BR]",preg_replace('@(\s)*\[BR\]$@','',$text)),
				);
			}
			$skip = TRUE;
    }
    else if ( ( array_element($this->current_tag['attrs'],'CLASS') == 'silver_hdr' ) && (1 == preg_match('@(member for the|chairperson)@i',$text)) ) {
      $this->current_member_classification = $text;
      $skip = FALSE;
      $is_container = FALSE;
      if ( $this->debug_tags ) $this->syslog( __FUNCTION__, __LINE__, "(marker) {$tag} - - - {$text} " );
    }
    if ( $this->debug_tags && (0 < count(array_element($this->current_tag,'cdata',array()))) ) $this->recursive_dump($this->current_tag,"(marker) " . ($skip ? "REJ" : "ACC"));
    if ( !$skip && $is_container ) $this->stack_to_containers();
    return !$skip;
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

  function ru_span_close(& $parser, $tag) {/*{{{*/
    $this->current_tag();
    $paragraph = array(
      'text' => trim(join('', $this->current_tag['cdata'])),
      'seq'  => $this->current_tag['attrs']['seq'],
    );
    $regex_matches = array();

    if (1 == preg_match($this->span_content_prefix_regex, $paragraph['text'], $regex_matches)) {
      $paragraph['indicator'] = preg_replace(
        array_keys($this->span_content_prefix_map),
        array_values($this->span_content_prefix_map),
        $paragraph['text']
      );
      $paragraph['content'] = trim(array_element($regex_matches,2,'--'));

      $this->temporary_document_container = array_merge(
        $this->temporary_document_container,
        array($paragraph['indicator'] => $paragraph['content']) 
      );

      $paragraph['tag-class'] = 'bill-content';
    }
    else if ( (1 == preg_match($this->get_document_sn_regex(), $paragraph['text'],$regex_matches)) &&
      (0 < strlen(($prefix = array_element($regex_matches,1)))) &&
      (0 < strlen(($suffix = array_element($regex_matches,3))))
    ) {
      // Detect a document serial number next (a document SN may be embedded in a status line) 
      unset($regex_matches[0]);
      if ( join('',$regex_matches) == $paragraph['text'] ) {
        $paragraph['tag-class']         = 'bill-head'; 
        $paragraph['sequence-position'] = $this->current_tag['attrs']['seq'];
        $this->complete_document_container = $this->temporary_document_container;
        $this->temporary_document_container = array(
          'seq'              => $this->current_tag['attrs']['seq'],
          'bill'             => $paragraph['text'],
          'bill-url'         => NULL,
          'history'          => NULL,
          'bill-title'       => NULL,
          'principal-author' => NULL,
          'status'           => NULL,
          'ref'              => NULL,
          'prune-to'         => $this->current_tag['attrs']['seq'],
        );
        if (!is_null(($prune_to = array_element($this->complete_document_container,'prune-to')))) {
          $this->complete_document_container['prune-from'] = $this->current_tag['attrs']['seq'];
          // $this->recursive_dump($this->complete_document_container,"(marker) -- - -- - Cricker");
          array_walk($this->container_stack,create_function(
            '& $a, $k, $s', 'if ( array_element($a,"sethash") == "'.array_element($this->complete_document_container,'container').'" ) $s->flush_document_container_range($k);'
          ),$this);
          //unset($this->complete_document_container['prune-to']);
          unset($this->complete_document_container['prune-from']);
          unset($this->complete_document_container['container']);
          $this->add_to_container_stack($this->complete_document_container);
          $this->total_bills_parsed++;
          if ( $this->total_bills_parsed > 200 ) {
            // Suspend parsing of the remainder of the document
            $this->syslog(__FUNCTION__,__LINE__,"(warning) - - - - - - - Parsing suspended - - - - - - -");
            $this->set_freewheel();
          }
        }
      }
    }
    else if ( !is_null(array_element($this->temporary_document_container,'bill')) && is_null(array_element($this->temporary_document_container,'bill-title')) ) {
      $key = 1 == preg_match('@Journal@i', $paragraph['text']) ? 'ref' : 'bill-title';
      $this->temporary_document_container = array_merge(
        $this->temporary_document_container,
        array($key => $paragraph['text']) 
      );
    }
    if (0 < strlen(trim($paragraph['text']))) {
      $this->add_to_container_stack($paragraph);
      if ( array_element($this->current_tag['attrs'],'seq') == array_element($this->temporary_document_container,'seq') )
        $this->temporary_document_container['container'] = array_element($paragraph,'sethash');
    }
    return TRUE;
  }/*}}}*/

  function flush_document_container_range($k) {/*{{{*/
    // $this->recursive_dump($this->container_stack[$k]["children"],"(marker) - - Subj");
    array_walk($this->container_stack[$k]['children'],create_function(
      '& $a, $k, $s', 
      //'$a = $s->flush_document_container_range_worker($a,$k);'
      '$n = array_element($a,"seq"); if (($s->complete_document_container["prune-to"] <= $n) && ($n < $s->complete_document_container["prune-from"])) $a = NULL;'
    ), $this);
    $this->container_stack[$k]['children'] = array_filter($this->container_stack[$k]['children']);
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
    if ( is_null(array_element($this->current_tag,'sethash')) ) {

    } else if ( 1 == preg_match($this->link_title_classif_regex, ($classification = array_element($this->current_tag['attrs'],'TITLE'))) ) {
      // If the 'title' attribute matches telltale strings (above), we try to
      // match it's content to one of three categories - bill, representative, or
      // committee - and label the URL with that classification.
      $classification = preg_replace(
        array_keys($this->link_title_classif_map),
        array_values($this->link_title_classif_map),
        $classification
      );
      switch ( $classification ) {
        case 'history'  : $key = 'onclick-param'; break;
        case 'bill-url' : $key = 'url'; break;
      }
      $add_to_tempdoc = NULL;
      if ( is_null(($found_index = array_element($this->current_tag,'found_index'))) ) {
        $cstack_top = array_pop($this->container_stack);
        if ( is_array(($lasttag = array_element($cstack_top,'children'))) && (0 < count($lasttag)) ) {
          $lasttag = array_pop($cstack_top['children']);
          $lasttag['tag-class'] = $classification;
          if ( !is_null($key) && array_key_exists($key, $lasttag) ) {
            $add_to_tempdoc = array($classification => array_element($lasttag,$key,'--'));
          }
          if ($this->debug_tags) $this->syslog(__FUNCTION__,__LINE__, "(marker) - - - - Tag class '{$classification}'" );
          array_push($cstack_top['children'],$lasttag);
        }
        array_push($this->container_stack, $cstack_top);
      } else {
        $link_data = array_pop($this->container_stack[$found_index]['children']);
        $link_data['tag-class'] = $classification;
        if ( !is_null($key) && array_key_exists($key, $link_data) ) {
          $add_to_tempdoc = array($classification => array_element($link_data,$key,'--'));
        }
        if ($this->debug_tags) $this->syslog(__FUNCTION__,__LINE__, "(marker) - - - - Tag class '{$classification}'" );
        array_push($this->container_stack[$found_index]['children'], $link_data);
      }
      if ( !is_null($add_to_tempdoc) ) {
        $this->temporary_document_container = array_merge(
          $this->temporary_document_container,
          $add_to_tempdoc
        );
      }
    }
    return TRUE;
  }/*}}}*/

	/** User markup-generating and utility methods **/

	function & get_member_contact_details() {/*{{{*/
		return $this->member_contact_details;
	}/*}}}*/

	function stow_parsed_representative_info(RepresentativeDossierModel & $m, UrlModel & $urlmodel) {/*{{{*/

    $url  = new UrlModel();

		$this->syslog( __FUNCTION__, __LINE__, "(marker) A ------------------------------ " );
		if ( !$m->in_database() ) {
			$this->syslog( __FUNCTION__, __LINE__, "(marker) Member {$urlmodel} not in DB" );
		}
		$member = $this->get_member_contact_details();
		// $this->recursive_dump($member,__LINE__);
		// Extract room, phone, chief of staff (2013-03-25)
		// $this->recursive_dump($this->get_containers(),__LINE__);
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
		$member_avatar_base64 = NULL;
		$url->fetch(UrlModel::get_url_hash($member['avatar']),'urlhash');
		if ( $url->in_database() ) {
			$this->syslog( __FUNCTION__, __LINE__, "(marker) C ------------------------------ " );
			$image_content_type   = $url->get_content_type();
			$image_content        = base64_encode($url->get_pagecontent());
			$member_avatar_base64 = "data:{$image_content_type};base64,{$image_content}";
			// $this->syslog(__FUNCTION__,__LINE__, "{$member['fullname']} avatar: {$member_avatar_base64}");
		}
		$member_id = $m->set_fullname($member['fullname'])->
			set_create_time(time())->
			set_bio_url($urlmodel->get_url())->
			set_last_fetch(time())->
			set_avatar_url($member['avatar'])->
			set_member_uuid(sha1(mt_rand(10000,100000) . ' ' . $urlmodel->get_url() . $member['fullname']))->
			set_contact_json($contact_items)->
			set_avatar_image($member_avatar_base64)->
			stow();
		$this->syslog( __FUNCTION__, __LINE__, "(marker) D ------------------------------ Returning with member #{$member_id}" );
		return $member_id;
	}/*}}}*/

  function extract_committee_membership_and_bills($selector = 'children[tagname=ul][id=nostylelist]' ) {/*{{{*/
    
		$debug_method = FALSE;

    $this->recursive_dump(($entries = array_filter($this->get_containers(
      $selector 
    ))),(($debug_method) ? '(marker)' : '-') . ' - - - Main parser');

    $nullo = NULL;
    $this->assign_containers($nullo);

    $classifications = array();
    $membership_role = array();
    $bills           = array();

    if ( !(0 < count($entries) ) ) {
      $this->syslog( __FUNCTION__, __LINE__, "(marker) F ------------------------------ " );
    }
    foreach ( $entries as $item => $container ) {/*{{{*/
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
          if ( !is_null($classification) && !array_key_exists($classification,$classifications) ) $classifications[$classification] = array();
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
      if ( !$signature['a'] && $signature['b'] ) {/*{{{*/// Bills
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
          if ( is_null(array_element($bill,'bill-url')) && array_key_exists('url',$tag) && (1 == preg_match("@(history of bill)@i", $tag["title"])) ) {
            $bill['history'] = $tag['url'];
            array_push($bills, $bill); continue;
          }
          if ( is_null(array_element($bill,'bill-url')) && array_key_exists('url',$tag) && (1 == preg_match("@(text of bill)@i", $tag["title"])) ) {
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
    }/*}}}*/

    // Sort the list
    if ( 0 < count($bills) ) {/*{{{*/
      $bills = array_combine(
        array_map(create_function('$a', 'return array_element($a,"bill");'), $bills),
        $bills
      );
      krsort($bills);
    }/*}}}*/

    if ( 0 < count($classifications) ) {/*{{{*/
      foreach ( $classifications as $classification => $content ) {
        $classifications[$classification] = array_filter(
          array_map(create_function(
            '$a', 'return array_element($a,"classification") == "'.$classification.'" ? $a : NULL;'
          ),$membership_role));
        $classifications[$classification] = array_combine(
          array_map(create_function('$a','return array_element($a,"committee");'), $classifications[$classification]),
          array_map(create_function('$a','unset($a["classification"]); return $a;'), $classifications[$classification])
        );
        ksort($classifications[$classification]);
        $classifications[$classification] = array_values($classifications[$classification]);
      }
      $membership_role = $classifications;
    }/*}}}*/

    if ( $debug_method ) $this->recursive_dump($membership_role,"(marker) - - - Mem");

		return array(
			'membership_role' => $membership_role,
			'bills'           => $bills
		);
  }/*}}}*/

  function generate_committee_membership_markup(& $membership_role, $entry_wrap = NULL) {/*{{{*/
		$pagecontent = '';
    if ( !(0 < count($membership_role) ) ) {
      $this->syslog( __FUNCTION__, __LINE__, "(marker) G ------------------------------ " );
    }
    foreach ( $membership_role as $role ) {/*{{{*/
      $CommitteeName = ucwords(strtolower($role['committee']));
      $pagecontent .= <<<EOH
<span class="congress-roles-committees">{$role['role']} <a href="{$role['committee-url']}" class="legiscope-remote">{$CommitteeName}</a> {$role['ref']}</span>
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
    foreach ( $bills as $bill ) {/*{{{*/
      $bill_url_hash         = UrlModel::get_url_hash($bill['bill-url']);
      $bill_history          = array_element($bill,'history');
      $bill_history_url_hash = UrlModel::get_url_hash($bill_history);
      $active_hash           = is_null($bill['bill-url']) ? $bill_history_url_hash : $bill_url_hash;
      $bill_title            = is_null($bill['bill-url'])
				? ( is_null($bill_history) ?
			 	<<<EOH
{$bill['bill']}
EOH
			: <<<EOH
<a title="Document Metadata" id="{$bill_history_url_hash}" href="{$bill_history}" class="congress-doc-name legiscope-remote document-meta {cache-state}">{$bill['bill']}</a>
EOH
			)
      : <<<EOH
<a id="{$bill_url_hash}" href="{$bill['bill-url']}" class="congress-doc-name legiscope-remote {cache-state}">{$bill['bill']}</a>
EOH
      ;
      $bill_longtitle = ucwords($bill['bill-title']);

      if ( !empty($bill['principal-author']) ) {
        $bill['principal-author'] = $m->replace_legislator_names_hotlinks($bill['principal-author']);
      }

      $principal_author = empty($bill['principal-author']) ? NULL : <<<EOH
<span class="congress-doc-element indent-1">Principal author: {$bill['principal-author']}</span>
EOH;

      $bill['status'] = $this->replace_legislative_sn_hotlinks($bill['status']);

      // Override contents of $parser->linkset
			$legislation_links[] = array(
				'seq'     => "{$bill['bill']}", // Sort key
				'url'     => "{$bill['bill-url']}",
				'urlhash' => $active_hash,
				'link'    => <<<EOH
<li>{$bill_title}</li>
EOH
			);

			$pagecontent[$active_hash] = array(
				'seq'  => $bill['bill'],
			  'link' => <<<EOH
<div class="congress-doc-item">
<span class="congress-doc-name">{$bill_title}</span>
<span class="congress-doc-element indent-1">{$bill_longtitle}</span>
<span class="congress-doc-element indent-1">Status: {$bill['status']} {$bill['ref']}</span>
{$principal_author}
</div>
EOH
			);
    }/*}}}*/
		$legislation_links_temp = array();
		$u = new UrlModel();

		while ( 0 < count($legislation_links) ) {/*{{{*/
			$n = 0;
			while ( $n++ < 20 ) {
				$legislation_item = array_pop($legislation_links);
        if ( !is_null(array_element($legislation_item,'seq')) && !is_null(array_element($legislation_item,'url')) )
        $legislation_links_temp[$legislation_item['urlhash']] = $legislation_item; 
			}
			$u->where(array('AND' => array(
				'urlhash' => array_keys($legislation_links_temp)
			)))->recordfetch_setup();
      $this->syslog( __FUNCTION__, __LINE__, "(marker) L ---------- Remaining: " . count($legislation_links) );
			while ( $u->recordfetch($url) ) {
				$urlhash = UrlModel::get_url_hash($url['url']);
          $legislation_links_temp[$urlhash]["link"] = preg_replace(
            '@{cache-state}@',
            'cached',
            $legislation_links_temp[$urlhash]["link"]
          );
				if ( array_key_exists($urlhash, $pagecontent) ) 
				$pagecontent[$urlhash]["link"] = preg_replace(
					'@{cache-state}@',
					'cached',
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
				array_map(create_function('$a', 'return $a["seq"];'), $legislation_links_temp),
				array_map(create_function('$a', 'return $a["link"];'), $legislation_links_temp)
			);
			$legislation_links_temp = NULL;
		 	ksort($legislation_links);
		} else $legislation_links = array();
		if (is_array($pagecontent) && (0 < count($pagecontent))) {
			$pagecontent = array_combine(
				array_map(create_function('$a', 'return $a["seq"];'), $pagecontent),
				array_map(create_function('$a', 'return $a["link"];'), $pagecontent)
			);
			ksort($pagecontent);
		}
		if ( $debug_method ) $this->recursive_dump($legislation_links, "(marker) - -- -");
    $this->syslog(__FUNCTION__,__LINE__,"(marker) - - - - Page content entries: " . count($pagecontent));
    return join('',$pagecontent);
  }/*}}}*/

  function representative_bio_parser(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/

    $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked for " . $urlmodel->get_url() );
    // Representative bio parser

    $m    = new RepresentativeDossierModel();
    $hb   = new HouseBillDocumentModel();
    $url  = new UrlModel();

    $this->debug_tags = FALSE;
    $this->
      set_parent_url($urlmodel->get_url())->
      parse_html(
        $urlmodel->get_pagecontent(),
        $urlmodel->get_response_header()
      );
    $this->debug_tags = FALSE;

    $urlmodel->ensure_custom_parse();

    $pagecontent = '';

    $m->fetch($urlmodel->get_url(), 'bio_url');

    if ( !$m->in_database() || $parser->from_network ) {/*{{{*/

      $this->stow_parsed_representative_info($m,$urlmodel);

    }/*}}}*/

    $this->syslog( __FUNCTION__, __LINE__, "(marker) E ------------------------------ " );
    $member               = $this->get_member_contact_details();
    $contact_items        = $m->get_contact_json();
    $member_avatar_base64 = $m->get_avatar_image();
    $member_uuid          = $m->get_member_uuid();
    $member['fullname']   = $m->get_fullname();
    $member['avatar']     = $m->get_avatar_url();

    extract($this->extract_committee_membership_and_bills());

    $membership_role   = $this->generate_committee_membership_markup($membership_role);
    $legislation_links = array();
    $bills             = $this->generate_legislation_links_markup($m, $legislation_links, $bills);
    $legislation_links = join('',$legislation_links);

    // To trigger automated fetch, crawl this list after loading the entire dossier doc;
    // then find the next unvisited dossier entry
    $legislation_links = <<<EOH
<ul id="house-bills-by-rep" class="link-cluster">{$legislation_links}</ul>
EOH;
    if ( $urlmodel->is_custom_parse() ) {
      extract($this->generate_linkset($urlmodel->get_url()));
      $parser->linkset = $linkset; 
    }
    $parser->linkset = "{$legislation_links}{$parser->linkset}";

    $pagecontent = <<<EOH
<div class="congress-member-summary clear-both">
  <h1 class="representative-avatar-fullname">{$member['fullname']}</h1>
  <img class="representative-avatar" id="image-{$member_uuid}" src="{$member_avatar_base64}" alt="{$member['fullname']}" />
  <input type="hidden" class="representative-avatar-source" name="image-ref" id="imagesrc-{$member_uuid}" value="{$member['avatar']}" />
  <span class="representative-avatar-head">Role: {$contact_items['district']} {$contact_items['role']}</span>
  <span class="representative-avatar-head">Term: {$contact_items['term']}</span>
  <br/>
  <span class="representative-avatar-head">Room: {$contact_items['room']}</span>
  <span class="representative-avatar-head">Phone: {$contact_items['phone']}</span>
  <span class="representative-avatar-head">Chief of Staff: {$contact_items['cos']}</span>
</div>

<div class="congress-member-summary">
  <hr/>
  <div class="congress-member-roles">
{$membership_role}
  </div>
  <div class="congress-legislation-tally link-cluster clear-both">
  &nbsp;<br/>
  <hr/>
{$bills}
  </div>
</div>

<script type="text/javascript">
var hits = 0;
function crawl_dossier_links() {
  if ( jQuery('#spider').prop('checked') ) {
    jQuery('ul[id=house-bills-by-rep]').find("a[class*=uncached]").first().each(function(){
      var self = jQuery(this);
      load_content_window(
        jQuery(this).attr('href'),
        jQuery('#seek').prop('checked'),
        jQuery(this),
        { url : jQuery(this).attr('href'), async : true },
        { success : function (data, httpstatus, jqueryXHR) {
            std_seek_response_handler(data, httpstatus, jqueryXHR);
            jQuery(self).removeClass('uncached').addClass('cached');
            if ( data && data.timedelta ) replace_contentof('time-delta', data.timedelta);
            setTimeout((function() {crawl_dossier_links();}),200);
          }
        }
      );
    });
    hits = 0;
    jQuery('ul[id=house-bills-by-rep]').find("a[class*=uncached]").each(function(){
      hits++;
    });
    if (hits == 0) {
      jQuery('div[id=congresista-list]')
        .find('a[class*=seek]')
        .first()
        .each(function() { 
          jQuery('#doctitle').html('Loading '+jQuery(this).attr('href'));
          jQuery(this).removeClass('seek').addClass('cached'); 
          jQuery(this).click();
        });
    } else {
      jQuery('#doctitle').html('Remaining: '+hits);
    }
  }
}

jQuery(document).ready(function(){
  update_representatives_avatars();
  setTimeout((function(){
    crawl_dossier_links();
  }),2000);
});
</script>
EOH;
    // PDFJS.workerSrc = 'wp-content/plugins/phlegiscope/js/pdf.js';

    $parser->json_reply = array('retainoriginal' => TRUE);

    $this->syslog( __FUNCTION__, __LINE__, "(marker) Final content length: " . strlen($pagecontent) );
    $this->syslog( __FUNCTION__, __LINE__, "(marker) I ------------------------------ " );

	}/*}}}*/

}

