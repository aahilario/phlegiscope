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

	function & get_member_contact_details() {
		return $this->member_contact_details;
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
		if ( $this->current_tag['attrs']['CLASS'] == 'meta' ) $skip = TRUE;
		else
		if ( $this->current_tag['attrs']['CLASS'] == 'mem_info' ) {
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

}

