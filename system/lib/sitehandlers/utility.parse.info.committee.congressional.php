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
}

