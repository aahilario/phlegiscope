<?php

/*
 * Class SenateCommitteeListParseUtility
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class SenateCommitteeListParseUtility extends SenateCommonParseUtility {
  
  protected $have_toc = FALSE;
  var $desc_stack = array();

  function __construct() {
    parent::__construct();
  }

  function & get_desc_stack() {
    return $this->desc_stack;
  }

  /**** http://www.senate.gov.ph/committee/duties.asp ****/

	function new_entry_to_desc_stack() {/*{{{*/
		array_push(
			$this->desc_stack,
			array(
				'link' => NULL,
				'title' => NULL,
				'description' => NULL,
			)
		);
	}/*}}}*/

  function ru_div_open(& $parser, & $attrs, $tagname ) {/*{{{*/
		$accept = parent::ru_div_open($parser,$attrs,$tagname);
		$this->current_tag();
		if ( $this->current_tag['attrs']['ID'] == "toc" ) {
		 	$this->syslog(__FUNCTION__,__LINE__,"(marker) -------------- TOC FOUND ------------ ");
		 	$this->have_toc = TRUE;
		}
    if ($this->debug_tags) $this->recursive_dump($attrs,__LINE__);
    return $accept;
  }/*}}}*/

  function ru_div_cdata(& $parser, & $cdata) {/*{{{*/
		$cdata = preg_replace(
			array('@[^&-:\', A-Z]@i',"@( |\t)+@"),
			array('',' '),
			$cdata
		);
		$result = parent::ru_div_cdata($parser,$cdata);
		$this->current_tag();
		// Detect 'Committee on' prefix, and add a new entry to the
		// description stack each time this entry is found
		if (!$this->have_toc) {
		}
	 	else if ($this->current_tag['attrs']['CLASS'] == 'h3_uline') {
			if ( 1 == preg_match('@Committee([ ]*)on@i', $cdata) ) {
				$this->new_entry_to_desc_stack();
			} else {
				$desc = array_pop($this->desc_stack);
				if ( is_null($desc['title']) ) $desc['title'] = $cdata;
				array_push($this->desc_stack, $desc);
			}
		}
		else if ( 1 == preg_match('@^(jurisdiction:)@i', $cdata) ){
			$desc = array_pop($this->desc_stack);
			if ( is_null($desc['description']) ) $desc['description'] = $cdata;
			array_push($this->desc_stack, $desc);
		}
		if ($this->debug_tags) 	$this->syslog(__FUNCTION__,__LINE__,"(marker) ".($result ? "" : 'REJECT')." {$this->current_tag['tag']}[{$this->current_tag['attrs']['CLASS']}|{$this->current_tag['attrs']['ID']}] '{$cdata}'");
		return $result;
	}/*}}}*/

	function ru_div_close(& $parser, $tag) {/*{{{*/
		$this->current_tag();
		$result = parent::ru_div_close($parser, $tag);
		return $result;
	}/*}}}*/

  function ru_a_open(& $parser, & $attrs, $tag) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'] = array();
    $this->push_tagstack();

    if ( $this->have_toc && array_key_exists('NAME', $attrs) ) {
      $desc = array_pop($this->desc_stack);
			if ( !is_null($desc['link']) ) {
				array_push($this->desc_stack, $desc);
				$this->new_entry_to_desc_stack();
				$desc = array_pop($this->desc_stack);
			}
      $desc['link'] = $attrs['NAME'];
      array_push($this->desc_stack, $desc);
    }
    if ($this->debug_tags) $this->syslog( __FUNCTION__, 'FORCE', $this->get_stacktags() . " --- {$this->current_tag['tag']} {$this->current_tag['attrs']['HREF']}" );
    return TRUE;
  }/*}}}*/
  function ru_a_cdata(& $parser, & $cdata) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'][] = $cdata;
    $this->push_tagstack();
    if ($this->debug_tags) $this->syslog( __FUNCTION__, 'FORCE', "--- {$this->current_tag['tag']} {$cdata}" );
    return TRUE;
  }/*}}}*/
  function ru_a_close(& $parser, $tag) {/*{{{*/

    $this->pop_tagstack();
    $this->add_to_container_stack($this->current_tag);
    $this->push_tagstack();
    if ($this->debug_tags) $this->syslog( __FUNCTION__, 'FORCE', "--- {$this->current_tag['tag']}" );
    return TRUE;
  }/*}}}*/

  function ru_i_open(& $parser, & $attrs, $tag) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'] = array();
    $this->push_tagstack();
    if ($this->debug_tags) $this->syslog( __FUNCTION__, 'FORCE', $this->get_stacktags() . " --- {$this->current_tag['tag']} {$this->current_tag['attrs']['HREF']}" );
    return TRUE;
  }/*}}}*/
  function ru_i_cdata(& $parser, & $cdata) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'][] = $cdata;
    $this->push_tagstack();
    return TRUE;
  }/*}}}*/
  function ru_i_close(& $parser, $tag) {/*{{{*/
    return $this->embed_cdata_in_parent();
  }/*}}}*/

  /**** http://www.senate.gov.ph/committee/list.asp ('List of Committees') ****/

  function ru_table_open(& $parser, & $attrs, $tagname) {/*{{{*/
    $this->push_container_def($tagname, $attrs);
    return TRUE;
  }/*}}}*/

  function ru_table_close(& $parser, $tag) {/*{{{*/
    $this->stack_to_containers();
    return TRUE;
  }/*}}}*/

  function ru_td_open(& $parser, & $attrs, $tag) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'] = array();
    $this->push_tagstack();
    if ($this->debug_tags) $this->syslog( __FUNCTION__, 'FORCE', $this->get_stacktags() . " --- {$this->current_tag['tag']} {$this->current_tag['attrs']['HREF']}" );
    return TRUE;
  }/*}}}*/

  function ru_td_cdata(& $parser, & $cdata) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'][] = trim($cdata);
    $this->push_tagstack();
    if ($this->debug_tags) $this->syslog( __FUNCTION__, 'FORCE', "--- {$this->current_tag['tag']} {$cdata}" );
    return TRUE;
  }/*}}}*/

  function ru_td_close(& $parser, $tag) {/*{{{*/

    $this->pop_tagstack();
    $this->add_to_container_stack($this->current_tag);
    $this->push_tagstack();
    if ($this->debug_tags) $this->syslog( __FUNCTION__, 'FORCE', "--- {$this->current_tag['tag']}" );
    return TRUE;
  }/*}}}*/


}
