<?php

/*
 * Class SenatorBioParseUtility
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class SenatorBioParseUtility extends SenateCommonParseUtility {
  
  function __construct() {
    parent::__construct();
  }

  function ru_table_open(& $parser, & $attrs, $tagname) {/*{{{*/
    $this->push_container_def($tagname, $attrs);
    return TRUE;
  }/*}}}*/
  function ru_table_close(& $parser, $tag) {/*{{{*/
    $this->stack_to_containers();
    return TRUE;
  }/*}}}*/

  function ru_tr_open(& $parser, & $attrs, $tag) {/*{{{*/
    return TRUE;
  }/*}}}*/

  function ru_tr_cdata(& $parser, & $cdata) {/*{{{*/
    return TRUE;
  }/*}}}*/

  function ru_tr_close(& $parser, $tag) {/*{{{*/
    return TRUE;
  }/*}}}*/

  function ru_td_open(& $parser, & $attrs, $tag) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'] = array();
    $this->push_tagstack();
    if ( $this->have_toc && array_key_exists('NAME', $attrs) ) {
      $desc = array_pop($this->desc_stack);
      $desc['link'] = $attrs['NAME'];
      array_push($this->desc_stack, $desc);
    }
    if ($this->debug_tags) $this->syslog( __FUNCTION__, 'FORCE', $this->get_stacktags() . " --- {$this->current_tag['tag']} {$this->current_tag['attrs']['HREF']}" );
    return TRUE;
  }/*}}}*/

  function ru_td_cdata(& $parser, & $cdata) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'][] = $cdata;
    $this->push_tagstack();
    if ($this->debug_tags) $this->syslog( __FUNCTION__, 'FORCE', "--- {$this->current_tag['tag']} {$cdata}" );
    return TRUE;
  }/*}}}*/

  function ru_td_close(& $parser, $tag) {/*{{{*/
    $skip = FALSE;
    $this->pop_tagstack();
    if ($this->debug_tags) $this->syslog( __FUNCTION__, 'FORCE', "--- {$this->current_tag['tag']}" );
    if ( $this->current_tag['attrs']['ID'] == "sidepane" ) $skip = TRUE;
    if ( !$skip ) {
      $this->add_to_container_stack($this->current_tag);
    }
    $this->push_tagstack();
    return !$skip;
  }/*}}}*/

  function ru_span_open(& $parser, & $attrs, $tag) {/*{{{*/
    // Handle A anchor/link tags
    if ( 0 < count($this->tag_stack) ) {
      $this->pop_tagstack();
      $this->current_tag['cdata'] = array();
      $this->push_tagstack();
    }
    return TRUE;
  }  /*}}}*/

  function ru_span_cdata(& $parser, & $cdata) {/*{{{*/
    // Attach CDATA to A tag 
    if ( !empty($cdata) && ( 0 < count($this->tag_stack) ) ) {
      $this->pop_tagstack();
      $this->current_tag['cdata'][] = str_replace("\n"," ",trim($cdata));
      $this->push_tagstack();
    }
    return TRUE;
  }/*}}}*/

  function ru_span_close(& $parser, $tag) {/*{{{*/
    $skip = FALSE;
    $this->pop_tagstack();
    $this->push_tagstack();
    $paragraph = array('text' => join('', $this->current_tag['cdata']));
    if ( $this->current_tag['attrs']['CLASS'] == "backtotop" ) $skip = TRUE;
    if ( !$skip ) {
      $this->add_to_container_stack($paragraph);
    }
    return !$skip;
  }/*}}}*/

  function ru_p_open(& $parser, & $attrs, $tag) {/*{{{*/
    // Handle A anchor/link tags
    if ( 0 < count($this->tag_stack) ) {
      $this->pop_tagstack();
      $this->current_tag['cdata'] = array();
      $this->push_tagstack();
    }
    return TRUE;
  }  /*}}}*/

  function ru_p_cdata(& $parser, & $cdata) {/*{{{*/
    // Attach CDATA to A tag 
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
    $paragraph = array('text' => join(' ', $this->current_tag['cdata']));
    if ( $this->current_tag['attrs']['CLASS'] == "backtotop" ) $skip = TRUE;
    if ( !$skip ) {
      $this->add_to_container_stack($paragraph);
    }
    return !$skip;
  }/*}}}*/

  function ru_a_close(& $parser, $tag) {/*{{{*/
    // Override global 
    $skip = FALSE;
    $this->pop_tagstack();
    // --
    if ( $this->current_tag['attrs']['HREF'] == '#top' ) $skip = TRUE;
    // --
    if ( !$skip ) {
      $this->add_to_container_stack($this->current_tag);
    }
    $this->push_tagstack();
    if ($this->debug_tags) $this->syslog( __FUNCTION__, 'FORCE', "--- {$this->current_tag['tag']}" );
    return !$skip;
  }/*}}}*/

  function ru_img_open(& $parser, & $attrs, $tag) {/*{{{*/
    // Handle A anchor/link tags
		$this->pop_tagstack();
		$this->update_current_tag_url('SRC');
		// Add capability to cache images as well
		$faux_mem_uuid = sha1(mt_rand(10000,100000) . ' ' . $this->current_tag['attrs']['SRC']);
		$this->current_tag['attrs']['FAUXSRC'] = $this->current_tag['attrs']['SRC'];
		$this->current_tag['attrs']['SRC'] = '{REPRESENTATIVE-AVATAR('.$faux_mem_uuid.','.$this->current_tag['attrs']['SRC'].')}';//$this->current_tag['attrs']['SRC'];
    if ( !array_key_exists('CLASS', $this->current_tag['attrs']) ) {
      // $this->syslog(__FUNCTION__,__LINE__,"Setting CSS selector for '{$this->current_tag['attrs']['HREF']}'");
      $this->current_tag['attrs']['CLASS'] = 'legiscope-remote representative-avatar representative-avatar-missing';
    } else {
      // $this->syslog(__FUNCTION__,__LINE__,"Adding CSS selector for '{$this->current_tag['attrs']['HREF']}'");
      $this->current_tag['attrs']['CLASS'] .= ' legiscope-remote';
    }
		$this->current_tag['attrs']['ID'] = "image-{$faux_mem_uuid}";
		$this->current_tag['attrs']['FAKEID'] = "{$faux_mem_uuid}";
		$this->push_tagstack();
    return TRUE;
  }  /*}}}*/
  function ru_img_cdata(& $parser, & $cdata) {/*{{{*/
    return TRUE;
  }/*}}}*/
  function ru_img_close(& $parser, $tag) {/*{{{*/
		$skip = FALSE;
		$this->pop_tagstack();
		if ( 1 == preg_match('@(nav_logo)@',$this->current_tag['attrs']['CLASS']) ) $skip = TRUE;
		if ( !$skip ) {
			$image = array(
				'image' => $this->current_tag['attrs']['SRC'],
				'fauxuuid' => $this->current_tag['attrs']['FAKEID'],
			 	'realsrc' => $this->current_tag['attrs']['FAUXSRC'],
			);
			$this->add_to_container_stack($image);
		} else {
			// $this->recursive_dump($this->current_tag,__LINE__);
		}
		$this->push_tagstack();
    return !$skip;
  }/*}}}*/

}
