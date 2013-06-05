<?php

/*
 * Class CongressCommonParseUtility
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class CongressCommonParseUtility extends LegislationCommonParseUtility {/*{{{*/
  
  function __construct() {
    parent::__construct();
  }

  function ru_head_open(& $parser, & $attrs, $tagname) {/*{{{*/
    $this->push_container_def($tagname, $attrs);
    return TRUE;
  }/*}}}*/
  function ru_head_close(& $parser, $tag) {/*{{{*/
    array_pop($this->container_stack);
    return FALSE;
  }/*}}}*/

  function ru_body_open(& $parser, & $attrs, $tagname) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'] = array();
    $this->push_tagstack();
    $this->push_container_def($tagname, $attrs);
    return TRUE;
  }/*}}}*/
  function ru_body_cdata(& $parser, & $cdata) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'][] = trim($cdata);
    $this->push_tagstack();
    return TRUE;
  }/*}}}*/
  function ru_body_close(& $parser, $tag) {/*{{{*/
    $this->pop_tagstack();
    $this->cdata_cleanup();
    $this->push_tagstack();
		$cdata_lines = array(
			'text' => $this->current_tag['cdata'],
			'seq' => array_element($this->current_tag['attrs'],'seq')
		);
		if (0 < strlen(trim(join('',$cdata_lines['text']))))
    $this->add_to_container_stack($cdata_lines);
    $this->stack_to_containers();
    return TRUE;
  }/*}}}*/

  function ru_div_open(& $parser, & $attrs, $tagname ) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'] = array();
    $this->push_tagstack();
    $this->push_container_def($tagname, $attrs);
    return TRUE;
  }/*}}}*/
  function ru_div_cdata(& $parser, & $cdata) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'][] = $cdata;
    $this->push_tagstack();
    if ($this->debug_tags) $this->syslog(__FUNCTION__,__LINE__, "(marker)" .  $this->get_stacktags() . "--- {$this->current_tag['tag']} {$cdata}" );
    return TRUE;
  }/*}}}*/
  function ru_div_close(& $parser, $tag) {/*{{{*/
		// FIXME:  Figure out a better way to filter Congress site DIV tags
    $skip = FALSE;
    $this->pop_tagstack();
    if ( is_array($this->current_tag) && array_key_exists('attrs', $this->current_tag) ) {
      if ( 1 == preg_match('@(' . join('|',array(
        'footer_content',
        'subnav',
        'clearer',
        'footer',
        'footer_content',
        'main_right',
        'silver_hdr',
        'breadcrumb',
      )) . ')@', array_element($this->current_tag['attrs'],'CLASS'))) $skip = TRUE;
			$id    = array_element($this->current_tag['attrs'],'ID');
			$class = array_element($this->current_tag['attrs'],'CLASS');
      if ( empty($id) && empty($class) ) {
        $skip = FALSE;
      }
      if ( array_key_exists(array_element($this->current_tag['attrs'],'ID'),array_flip(array(
        'nav_bottom',
      )))) $skip = TRUE;
    }
		if (is_array($this->current_tag) && !$skip ) {
			$this->stack_to_containers();
			if ( array_key_exists('cdata', $this->current_tag) ) {
				$this->current_tag['cdata'] = join('', array_filter($this->current_tag['cdata']));
			}
		}
    $this->push_tagstack();
    
    return !$skip;
  }/*}}}*/

  function ru_br_open(& $parser, & $attrs, $tagname ) {/*{{{*/
    return TRUE;
  }/*}}}*/
  function ru_br_cdata(& $parser, & $cdata) {/*{{{*/
    return TRUE;
  }/*}}}*/
  function ru_br_close(& $parser, $tag) {/*{{{*/
    $me     = $this->pop_tagstack();
    $parent = $this->pop_tagstack();
    if ( array_key_exists('cdata', $parent) ) {
      // $this->syslog(__FUNCTION__,'FORCE',"Adding line break to {$parent['tag']} (" . join(' ', $parent['cdata']) . ")" );
      $parent['cdata'][] = "\n[BR]";
    }
    $this->push_tagstack($parent);
    $this->push_tagstack($me);
    return FALSE;
  }/*}}}*/

  function ru_img_open(& $parser, & $attrs, $tag) {/*{{{*/
    // Handle A anchor/link tags
    $this->pop_tagstack();
    $this->update_current_tag_url('SRC');
    // Add capability to cache images as well
    $this->current_tag['attrs']['HREF'] = $this->current_tag['attrs']['SRC'];
    if ( !array_key_exists('CLASS', $this->current_tag['attrs']) ) {
      // $this->syslog(__FUNCTION__,'FORCE',"Setting CSS selector for '{$this->current_tag['attrs']['HREF']}'");
      $this->current_tag['attrs']['CLASS'] = 'legiscope-remote';
    } else {
      // $this->syslog(__FUNCTION__,'FORCE',"Adding CSS selector for '{$this->current_tag['attrs']['HREF']}'");
      $this->current_tag['attrs']['CLASS'] .= ' legiscope-remote';
    }
    $this->push_tagstack();
    return TRUE;
  }  /*}}}*/
  function ru_img_cdata(& $parser, & $cdata) {/*{{{*/
    return TRUE;
  }/*}}}*/
  function ru_img_close(& $parser, $tag) {/*{{{*/
    $skip = FALSE;
    $this->pop_tagstack();
    if ( 1 == preg_match('@(nav_logo|faded)@',$this->current_tag['attrs']['CLASS']) ) $skip = TRUE;
    if ( !$skip ) {
			$image = array(
				'image' => $this->current_tag['attrs']['SRC'],
				'seq'  => $this->current_tag['attrs']['seq'],
			);
      $this->add_to_container_stack($image);
    } else {
      // $this->recursive_dump($this->current_tag,'(warning)');
    }
    $this->push_tagstack();
    return !$skip;
  }/*}}}*/

  function ru_a_open(& $parser, & $attrs, $tag) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'] = array();
    $this->update_current_tag_url('HREF');
    if ( !array_key_exists('CLASS', $this->current_tag['attrs']) ) {
      // $this->syslog(__FUNCTION__,'FORCE',"Setting CSS selector for '{$this->current_tag['attrs']['HREF']}'");
      $this->current_tag['attrs']['CLASS'] = 'legiscope-remote';
      $this->current_tag['attrs']['ID'] = UrlModel::get_url_hash(array_element($this->current_tag['attrs'],'HREF'));
    } else {
      // $this->syslog(__FUNCTION__,'FORCE',"Adding CSS selector for '{$this->current_tag['attrs']['HREF']}'");
      if ( FALSE == preg_match('@(legiscope-remote)@', $this->current_tag['attrs']['CLASS']) ) {
        $this->current_tag['attrs']['CLASS'] .= ' legiscope-remote';
      }
    }
    $this->push_tagstack();
    if ($this->debug_tags) $this->syslog(__FUNCTION__,__LINE__, "(marker)" .  $this->get_stacktags() . " --- {$this->current_tag['tag']} " . array_element($this->current_tag['attrs'],'HREF') );
    return TRUE;
  }/*}}}*/
  function ru_a_cdata(& $parser, & $cdata) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'][] = $cdata;
    $this->push_tagstack();
    if ($this->debug_tags) $this->syslog(__FUNCTION__,__LINE__, "(marker)" .  "--- {$this->current_tag['tag']} {$cdata}" );
    return TRUE;
  }/*}}}*/
  function ru_a_close(& $parser, $tag) {/*{{{*/
    $this->pop_tagstack();
    $link_data = $this->collapse_current_tag_link_data();
		$link_data['title'] = array_element($this->current_tag['attrs'],'TITLE');
		// Links to legislation metadata are displayed using Javascript popup
		// windows, whose source URL is embedded in a click event handler.
		// We subvert this by accessing the links directly: We intercept the
		// link event ourselves, replacing the link HREF attribute with the
		// event handler source URL.
		$onclick_event = array_element($this->current_tag['attrs'],'ONCLICK');
		if ( !is_null($onclick_event) ) {
			$event_url_match = array();
			$link_data['onclick'] = $onclick_event;
			$link_regex = '@([a-z_]*)\(([\'"]?([^\'"]*)[\'"]?)([,]*.*)\)@i';
			/* The regex above yields, given input
			 * "pop_Window('../contact/popform.php?re=sendemail&to=committee&id=C501&congress=15','param2','param3')";
			 *Array (
				 [0] => pop_Window('../contact/popform.php?re=sendemail&to=committee&id=C501&congress=15','param2','param3')
				 [1] => pop_Window
				 [2] => '../contact/popform.php?re=sendemail&to=committee&id=C501&congress=15'
				 [3] => ../contact/popform.php?re=sendemail&to=committee&id=C501&congress=15
				 [4] => ,'param2','param3'
			 )
			 */
			if ( 1 == preg_match($link_regex,$onclick_event,$event_url_match) ) {
				$fixurl = array('url' => array_element($event_url_match,3));
				$link_data['onclick-param'] = UrlModel::normalize_url($this->page_url_parts, $fixurl);
				$this->current_tag['attrs']['onclick-param-url'] = $link_data['onclick-param'];
			}
			unset($this->current_tag['attrs']['ONCLICK']);
		}
		if ( !empty($link_data['url']) ) $this->add_to_container_stack($link_data);
    $this->push_tagstack();
    if ($this->debug_tags) $this->syslog(__FUNCTION__,__LINE__, "(marker)" .  "--- {$this->current_tag['tag']}" );
    return TRUE;
  }/*}}}*/

  function ru_span_open(& $parser, & $attrs, $tagname ) {/*{{{*/
    if ( 0 < count($this->tag_stack) ) {
      $this->pop_tagstack();
      $this->current_tag['cdata'] = array();
      $this->push_tagstack();
    }
    return TRUE;
  }/*}}}*/
  function ru_span_cdata(& $parser, & $cdata) {/*{{{*/
    if ( !empty($cdata) && ( 0 < count($this->tag_stack) ) ) {
      $this->pop_tagstack();
      $this->current_tag['cdata'][] = str_replace("\n"," ",trim($cdata));
      $this->push_tagstack();
    }
    return TRUE;
  }/*}}}*/
  function ru_span_close(& $parser, $tag) {/*{{{*/
    $this->current_tag();
    $paragraph = array(
      'text' => join('', $this->current_tag['cdata']),
      'seq'  => $this->current_tag['attrs']['seq'],
    );
		if (0 < strlen(trim($paragraph['text'])))
    $this->add_to_container_stack($paragraph);
    return TRUE;
  }/*}}}*/

  /** Rejected tags **/

  function ru_link_open(& $parser, & $attrs, $tag) {/*{{{*/
    return TRUE;
  }/*}}}*/
  function ru_link_cdata(& $parser, & $cdata) {/*{{{*/
    return TRUE;
  }/*}}}*/
  function ru_link_close(& $parser, $tag) {/*{{{*/
    return FALSE;
  }/*}}}*/

	// Journal parser callbacks
  function session_select_to_session_data(& $session_select, $session) {
		if (1) {
			$session_item = $session_select;
			$this->filter_nested_array($session_item,'metalink[optval*=' . $session . ']',0);
			return array_filter($session_item);
		}
    return array_filter(array_map(create_function(
      '$a', 'return $a["metalink"];'
    ), $session_select));
  }

  function session_select_to_linktext(& $session_select, $session) {
		$session_item = $session_select;
		$this->filter_nested_array($session_item,'#[optval*=' . $session . ']',0);
		return array_filter(array_map(create_function(
      '$a', 'return str_replace($a["linktext"],$a["optval"],$a["markup"]);'
    ), $session_item));
  }


}/*}}}*/

