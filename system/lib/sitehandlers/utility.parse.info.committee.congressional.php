<?php

/*
 * Class CongressionalCommitteeInfoParseUtility
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class CongressionalCommitteeInfoParseUtility extends CongressCommonParseUtility {
  
	var $committee_information = array();

  function __construct() {
    parent::__construct();
  }

	function ru_div_close(& $parser, $tag) {/*{{{*/
		// Hack to mark DIV containers of membership roster URLs in 
		// CongressGovPh::committee_information_page()
		$this->pop_tagstack();
		if ('main-ol' == array_element($this->current_tag['attrs'],'ID')) {
			unset($this->current_tag['attrs']['ID']);
			unset($this->current_tag['attrs']['CLASS']);
			$this->current_tag['children'][] = array(
				'header' => '__LEGISCOPE__',
				'seq' => $this->current_tag['attrs']['seq'],
			);
		}
		$this->push_tagstack();
		return parent::ru_div_close($parser,$tag);
	}/*}}}*/

  function ru_p_open(& $parser, & $attrs, $tagname ) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'] = array();
    $this->push_tagstack();
    $this->push_container_def($tagname, $attrs);
    return TRUE;
  }/*}}}*/
  function ru_p_cdata(& $parser, & $cdata) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'][] = $cdata;
    $this->push_tagstack();
    return TRUE;
  }/*}}}*/
  function ru_p_close(& $parser, $tag) {/*{{{*/
		// FIXME:  Figure out a better way to filter Congress site DIV tags
		$ok = FALSE;
    $this->current_tag();
		$parent = NULL;
		$text = trim(join('',array_element($this->current_tag,'cdata')));
		if ( 1 == preg_match('@\[BR\]@',$text) ) {
			$text = explode('[BR]', $text);
			if ( is_array($text) ) array_walk($text,create_function(
				'& $a, $k', '$a = trim($a);'
			));
			$text = array_filter($text);
		}
		$text = array(
			'text' => $text,
			'header' => '__LEGISCOPE__',
			'seq'  => $this->current_tag['attrs']['seq'],
		);
		if ( $this->tag_stack_parent($parent) && !is_null($parent) && ('DIV' == array_element($parent,'tag')) && ('padded' == array_element($parent['attrs'],'CLASS'))) { 
			if ( !empty($text['text']) && !('meta' == array_element($this->current_tag['attrs'],'CLASS')) ) {
				$ok = TRUE;
			}
		}
		$ok |= (1 == preg_match('@(' . join('|',array(
				'silver_hdr',
				'mem_info',
			)) . ')@i', array_element($this->current_tag['attrs'],'CLASS'))
		);
		if ( $ok ) $this->add_to_container_stack($text);
    return $ok;
  }/*}}}*/

  function ru_span_close(& $parser, $tag) {/*{{{*/
    $this->current_tag();
		$text = trim(join('',array_element($this->current_tag,'cdata')));
    $paragraph = array(
      'text' => $text, 
      'seq'  => $this->current_tag['attrs']['seq'],
    );
		// Label a SPAN[class*=mem-head] for selection in
		// CongressGovPh::committee_information_page()
		if (1 == preg_match('@(' . join('|',array(
				'mem-head',
			)) . ')@i', array_element($this->current_tag['attrs'],'CLASS'))
		) {
			$paragraph['header'] = 'committee name';
		}
		if (0 < strlen(trim($paragraph['text'])))
    $this->add_to_container_stack($paragraph);
    return TRUE;
  }/*}}}*/

  function ru_strong_open(& $parser, & $attrs, $tagname ) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'] = array();
    $this->push_tagstack();
    return TRUE;
  }/*}}}*/
  function ru_strong_cdata(& $parser, & $cdata) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'][] = $cdata;
    $this->push_tagstack();
    return TRUE;
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

	function parse_committee_information_page(UrlModel & $urlmodel, CongressionalCommitteeDocumentModel & $committee) {/*{{{*/
		// Take children of DIVs with CSS class 'padded', and filter for the one
		// containing the string 'JURISDICTION' in a STRONG tag (key for entry being 'header')
		$debug_method = FALSE;

		$this->committee_information = array();

		$selector  = 'children[tagname*=p|div|i]'; 
		$structure = array_values(array_intersect_key(
			$this->get_containers($selector),
			array_flip(array_keys(array_filter($this->get_containers("{$selector}{#[header*=jurisdiction|committee office|legiscope|committee name|i]}"))))
		));
		$roster_list = array_values(array_intersect_key(
			$this->get_containers($selector),
			array_flip(array_keys(array_filter($this->get_containers("{$selector}[id*=main-ol]"))))
		));

		$all_elements = array();
		ksort($roster_list);
		while ( 0 < count($structure) )   foreach ( array_pop($structure) as $seq => $cell ) $all_elements[$seq] = $cell;
		while ( 0 < count($roster_list) ) foreach ( array_pop($roster_list) as $seq => $cell ) $all_elements[$seq] = $cell;

		unset($structure);
		unset($roster_list);

		ksort($all_elements);

		// Replace string fragments of URL title with code-friendly keys

		$map_by_title = array( // Sections in the Committee Information page
			'member-list'        => 'complete roster' , // JOIN
			'bills-referred'     => 'bills referred'  , // JOIN
			'committee-meetings' => 'view schedule'   , // Unmapped
			'chairperson'        => 'more info about' , // JOIN
			'member-entry'       => 'about our member',
			'contact-form'       => 'contact form'    ,
		);

		$map_by_membership_class = array( // Sections in the membership roster page
			'member-majority'  => 'MEMBER FOR THE MAJORITY',
			'member-minority'  => 'MEMBER FOR THE MINORITY',
			'vice-chairperson' => 'VICE CHAIRPERSON',
		);

		$map_by_header = array(
			'jurisdiction'     => 'jurisdiction',
			'committee office' => 'office_address',
		);

		$committee_information = array();

		// Extract nested array entries
		if ( $debug_method ) $this->syslog(__FUNCTION__,__LINE__,"(marker) - --- --- - - -");

		// Remap some names
		array_walk($all_elements,create_function(
			'& $a, $k, $s', 'if ( array_key_exists("title",$a) ) { $key = preg_replace(array_map(create_function(\'$a\',\'return "@((.*)({$a})(.*))@i";\'),$s),array_keys($s),$a["title"]); if (array_key_exists($key,$s)) { $a["title"] = $key; $a["text"] = "std-label"; } if ( array_key_exists("image",$a) ) $a = NULL; }'
		),$map_by_title);
		array_walk($all_elements,create_function(
			'& $a, $k, $s', 'if ( is_array($a) && array_key_exists("text",$a) && is_string($a["text"]) ) { $key = preg_replace(array_map(create_function(\'$a\',\'return "@((.*)({$a})(.*))@i";\'),$s),array_keys($s),$a["text"]); if (array_key_exists($key,$s)) { $a["title"] = $key; $a["text"] = "std-label"; } }'
		),$map_by_membership_class);

		if ( $debug_method ) {/*{{{*/
			$this->syslog(__FUNCTION__,__LINE__,"(marker) - --- --- - - ->");
			// $this->recursive_dump($this->get_containers(),"(marker) - - --");
			$this->recursive_dump($all_elements,"(marker) - - --");
		}/*}}}*/

		$roster_entry_stack = array();
		$capture_next_image = FALSE;

		while ( 0 < count($all_elements) ) {/*{{{*/
			$cell = array_pop($all_elements);
			if ( !is_array($cell) ) continue;
			// Ignore image URLs
			if ( array_key_exists('image', $cell) ) {
				if ( $capture_next_image == FALSE ) continue;
				$committee_information[$capture_next_image] = $cell['image'];
				continue;
			}
			// Process JURISDICTION and CHAIRPERSON chunks
			$header = array_element($cell,'header');
			if ( 1 == preg_match('@CHAIRPERSON@i', $header) ) continue;
			// Store jurisdiction / address data
			if ( 1 == preg_match('@^('.join('|',array_keys($map_by_header)).')@i',$header) ) {/*{{{*/
				$new_key = preg_replace(
					array_map(create_function('$a','return "@^((.*){$a}(.*))@i";'), array_keys($map_by_header)),
					array_values($map_by_header),
					$header
				);
				$text = array_pop($all_elements);
				$next = array_pop($all_elements);
				if ( array_element($next,'header') == 'committee name') {
					$committee_information['committee_name'] = array_element($next,'text');
				} else if (array_key_exists('url',$next)) {
					$committee_information['committee_name'] = array_element($next,'text');
				} else {
					array_push($all_elements, $next);
				}
				$text = array_element($text,'text');
				if ( !array_key_exists($new_key,$committee_information) && (is_array($text) || (0 < strlen($text))) )
					$committee_information[$new_key] = $text;
				continue;
			}/*}}}*/
			$title = array_element($cell,'title');
			// Store roster entries
			if ( 1 == preg_match('@^('.join('|',array_keys($map_by_membership_class)).')@i',$title) ) {/*{{{*/
				$committee_information[$title] = $roster_entry_stack;
				$roster_entry_stack = array();
				continue;
			}/*}}}*/
			// Store meta URLs
			$capture_next_image = FALSE;
			if ( array_key_exists('url',$cell) && (array_element($cell,'text') == 'std-label') ) {/*{{{*/
				switch ( array_element($cell, 'title') ) {
				case 'member-entry':
					$roster_entry_stack[] = $cell['url'];
				case 'contact-form':
					$committee_information[$title] = array_element($cell, 'onclick-param');
					break;
				case 'chairperson':
					$capture_next_image = 'chairperson-avatar';
					$committee_information[$title] = array_element($cell,'url');
					break;
				default:
					$committee_information[$title] = array_element($cell,'url');
					break;
				}
			}/*}}}*/
		}/*}}}*/

		$this->committee_information = array_filter($committee_information);

		if ( $debug_method ) $this->recursive_dump($this->committee_information,"(marker) - - - - - - -");

		if ( array_key_exists('committee_name', $this->committee_information) ) {
			$committee->fetch(array_element($this->committee_information,'committee_name'),'committee_name');
		}

		return $this;
	}/*}}}*/

	function get_committee_name() {
		$attrname = preg_replace('@^get_@i', '', __FUNCTION__);
		return array_element($this->committee_information, $attrname);
	}

	function get_chairperson() {
		$attrname = preg_replace('@^get_@i', '', __FUNCTION__);
		return array_element($this->committee_information, $attrname);
	}

	function committee_information_page(& $stdparser, & $pagecontent, & $urlmodel) {/*{{{*/

		/* Parse a committee information page to obtain these items of information:
		 * - Committee jurisdiction and contact information
		 * - List of members
		 * - Bills referred to the Committee
		 * - Committee meetings
		 */
		$debug_method = FALSE;

		$this->syslog( __FUNCTION__, __LINE__, "Invoked for " . $urlmodel->get_url() );

		$urlmodel->ensure_custom_parse();

		$committee      = new CongressionalCommitteeDocumentModel();
		$representative = new RepresentativeDossierModel();

		$this->
			set_parent_url($urlmodel->get_url())->
			parse_html(
				$urlmodel->get_pagecontent(),
				$urlmodel->get_response_header()
			);

		$pagecontent = str_replace('[BR]','<br/>',join('',$this->get_filtered_doc()));
		$stdparser->json_reply = array('retainoriginal' => TRUE);

		$this->parse_committee_information_page($urlmodel, $committee);

		// Dump XMLParser raw output
		if (0) $this->recursive_dump($this->get_containers(), "(marker) - -- - -- -");

		$committee_id   = $committee->get_id();
		$committee_name = $committee->get_committee_name();

		$this->syslog(__FUNCTION__,__LINE__,"(marker) - - - Committee Name: {$committee_name} #{$committee_id}");

		if ( !empty($committee_name) ) {/*{{{*/
			$committee_record = $committee->
				join_all()->
				fetch($committee_name,'committee_name');

			$this->syslog(__FUNCTION__,__LINE__,"(marker) - - - " . get_class($committee) . " JOIN search result");
			$this->recursive_dump($committee_record, "(marker) - - -- -- - C");

		}/*}}}*/

		$representative_record = $representative->
			fetch($this->get_chairperson(),'bio_url');

		$representative_id = array_element($representative_record,'id');
		$committee_id      = array_element($committee_record,'id');

		//////////////////////////////////////////////////////////////////////
		// Bail out if a committee ID cannot be derived from parsed content
		//
		if ( is_null($committee_id) ) {/*{{{*/
			$this->syslog(__FUNCTION__,__LINE__,"(marker) - - - Parsed attributes " . get_class($committee));
			$this->recursive_dump($this->committee_information,"(marker) - - - - -");
			$this->syslog(__FUNCTION__,__LINE__,"(marker) - - - No committee ID found, cannot proceed.");
			return;
		}/*}}}*/

		if ( !is_null($representative_id) ) {/*{{{*/
			$r = array($representative_id => array(
				'role' => 'chairperson',
				'congress_tag' => UrlModel::query_element('congress',$representative->get_bio_url())
			));
			$committee->create_joins('representative', $r); 
			$representative_record = $representative->
				join_all()->
				fetch(array(
					'bio_url' => $this->get_chairperson(),
					'`b`.`role`' => 'chairperson'
				),'AND');

		}/*}}}*/

		if ( $debug_method ) {
			$this->syslog(__FUNCTION__,__LINE__,"(marker) - - - " . get_class($representative) . " JOIN search result");
			$this->recursive_dump($representative_record, "(marker) - - -- -- - R");

			$this->syslog(__FUNCTION__,__LINE__,"(marker) - - - Parsed attributes " . get_class($committee));
			$this->recursive_dump($this->committee_information,"(marker) - - - - -");
		}

		//////////////////////////////////////////////////////////////////////
		// Create URL-independent joins between Committees and Representative records
		//
		$membership_roles = array_intersect_key($this->committee_information,array_flip(array(
			'member-minority',
			'member-majority',
			'vice-chairperson',
		)));

		// A list of Representative bio URLs results from the parsing stage above.
		if ( 0 < count($membership_roles) ) foreach ( $membership_roles as $role => $urls ) {/*{{{*/
			unset($this->committee_information[$role]);
			$rep_ids = array();
			while ( 0 < count($urls) ) {/*{{{*/
				$rep_urls = array();
				while ( count($rep_urls) < 10 && 0 < count($urls) ) {
					$url           = array_pop($urls);
					$rep_urls[]    = $url;
					$rep_ids[$url] = NULL;
				} 
				// If a Join matching the role and committee already exists, NULL out the
				// corresponding entry in $rep_urls; otherwise assign the representative record ID 
				if ( $representative->join_all()->where(array('AND' => array('bio_url' => $rep_urls)))->recordfetch_setup() ) {/*{{{*/

					$rep_urls = array();

					while ( $representative->recordfetch($rep_urls,TRUE) ) {/*{{{*/

						$bio_url = array_element($rep_urls, 'bio_url');
						$rep_id  = intval(array_element($rep_urls,'id'));

						if ( !($rep_id > 0) ) continue;

						$data_component = $representative->get_committees('data');
						$join_component = $representative->get_committees('join');

						if (
							(array_element($data_component,'committee_name') == $committee_name) && 
							(array_element($join_component,'role') == $role) ) {
								// WARNING: If URL formats change so that the 'congress' query component is lost, 
								// then this will fail to capture the Congress number for the join.
								if ( $debug_method ) $this->syslog( __FUNCTION__,__LINE__,"(marker) - - - - Omitting already-linked rep {$rep_id} ({$rep_urls['fullname']} - ".$representative->get_bio_url().")");
								unset($rep_ids[$bio_url]);
							}

						if ( is_null(array_element($rep_ids,$bio_url)) && array_key_exists($bio_url, $rep_ids) ) 
							$rep_ids[$bio_url] = array(
								'id' => $rep_id,
								'fullname' => array_element($rep_urls,'fullname','--'),
								'congress_tag' => UrlModel::query_element('congress',$bio_url)
							); 

					}/*}}}*/

					$rep_ids = array_filter($rep_ids);

					while ( 0 < count($rep_ids) ) {/*{{{*/
						// Create missing Committee - Representative joins. Pop IDs from the list generated above.
						$rep_id = array_pop($rep_ids);
						$r = array(array_element($rep_id,'id') => array(
							'role' => $role,
							'congress_tag' => array_element($rep_id,'congress_tag','--'),
						));

						// if ( $debug_method )
						$this->syslog( __FUNCTION__,__LINE__,"(marker) - - - - Adding link to {$role} representative #{$rep_id['id']} ({$rep_id['fullname']})");
						$committee->create_joins('representative',$r);
					}/*}}}*/

				}/*}}}*/
			}/*}}}*/
		}/*}}}*/


	}/*}}}*/

}

