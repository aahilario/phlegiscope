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

  function __construct() {
    parent::__construct();
  }

  function ru_p_open(& $parser, & $attrs, $tagname ) {/*{{{*/
    if ( 0 < count($this->tag_stack) ) {
      $this->pop_tagstack();
      $this->current_tag['cdata'] = array();
      $this->push_tagstack();
    }
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
		$this->pop_tagstack();
		$this->push_tagstack();
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
					'extra'    => explode("[BR]",preg_replace('@(\s)*\[BR\]$@','',trim(join('',$this->current_tag['cdata'])))),
				);
			}
			$skip = TRUE;
		}
    $this->stack_to_containers();
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

  function extract_committee_membership_and_bills() {/*{{{*/
    
		$debug_method = FALSE;

    $this->recursive_dump(($entries = array_filter($this->get_containers(
      'children[tagname=ul][id=nostylelist]'
    ))),'Main parser');

    $membership_role = array();
    $bills           = array();

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
          if ( array_key_exists('image', $tag) ) continue;
					if ( array_key_exists('url', $tag) && ('[HISTORY]' == strtoupper(trim($tag['text']))) ) {
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
					}
          $bill = array_pop($bills);
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

		return array(
			'membership_role' => $membership_role,
			'bills'           => $bills
		);
  }/*}}}*/

  function generate_committee_membership_markup(& $membership_role) {/*{{{*/
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
      $this->syslog( __FUNCTION__, __LINE__, "(marker) H ------------------------------ " );
    }
		$legislation_links = array();
    foreach ( $bills as $bill ) {/*{{{*/
      $bill_url_hash = UrlModel::get_url_hash($bill['bill-url']);
			$bill_history = array_element($bill,'history');
			$bill_history_url_hash = UrlModel::get_url_hash($bill_history);
			$active_hash = is_null($bill['bill-url']) ? $bill_history_url_hash : $bill_url_hash;
      $bill_title = is_null($bill['bill-url']) 
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
				'seq'      => "{$bill['bill']}", // Sort key
				'url'      => "{$bill['bill-url']}",
				'urlhash'  => $active_hash,
				'link'     => <<<EOH
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
			while ( $n++ < 10 ) {
				$legislation_item = array_pop($legislation_links);
        if ( !is_null(array_element($legislation_item,'seq')) && !is_null(array_element($legislation_item,'url')) )
        $legislation_links_temp[$legislation_item['urlhash']] = $legislation_item; 
			}
			$u->where(array('AND' => array(
				'urlhash' => array_keys($legislation_links_temp)
			)))->recordfetch_setup();
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
    return join('',$pagecontent);
  }/*}}}*/

}

