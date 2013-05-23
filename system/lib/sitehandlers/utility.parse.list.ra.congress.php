<?php

/*
 * Class CongressRaListParseUtility
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class CongressRaListParseUtility extends RawparseUtility {
  
  function __construct() {
    parent::__construct();
		// Republic Act listing structure:
		// <ul id="nostylelist">
		//   <li>
		//     <span class="bill-head">RA10148</span>
		//     <a class="sm_link_bill" target="_blank" href="../download/ra_15/RA10148.pdf" title="text of RA in PDF format">
		//     [PDF
		//     <img width="10" height="10" border="0" src="../images/pdficon_small.gif">
		//     , 30k]
		//     </a>
		//     <br>
		//     <span>AN ACT GRANTING PHILIPPINE CITIZENSHIP TO MARCUS EUGENE DOUTHIT</span>
		//     <br>
		//     <span class="meta">
		//     Approved by the President on March 12, 2011
		//     <br>
		//     Origin: House (HB02307 / SB00000)
		//     </span>
		//   </li>
  }

  function ru_ul_open(& $parser, & $attrs, $tag) {/*{{{*/
    $this->push_container_def($tag, $attrs);
    return TRUE;
  }/*}}}*/

  function ru_ul_cdata(& $parser, & $cdata) {/*{{{*/
  }/*}}}*/

  function ru_ul_close(& $parser, $tag) {/*{{{*/
    $this->stack_to_containers();
    // if ($this->debug_tags) $this->syslog( __FUNCTION__, 'FORCE', "--- {$tag}" );
    // if ($this->debug_tags) $this->recursive_dump($list_container,'(warning)');
    return TRUE;
  }/*}}}*/

  function ru_a_open(& $parser, & $attrs) {/*{{{*/
    // Handle A anchor/link tags
		$this->pop_tagstack();
		$this->current_tag['cdata'] = array();
    $this->update_current_tag_url('HREF');
    $attrs['HREF'] = array_element($this->current_tag['attrs'],'HREF');
		$this->push_tagstack();
    return TRUE;
  }/*}}}*/

  function ru_a_cdata(& $parser, & $cdata) {/*{{{*/
    // Attach CDATA to A tag 
		if ( $this->parent_tag() == 'SPAN' ) {
			$W = $this->pop_tagstack();
			$V = $this->pop_tagstack();
			$V['cdata'][] = $cdata;
			array_push($this->tag_stack,$V);
			array_push($this->tag_stack,$W);
		} else {
			$this->pop_tagstack();
			$this->current_tag['cdata'][] = $cdata;
			$this->push_tagstack();
		}
		if ($this->debug_tags) $this->syslog( __FUNCTION__, 'FORCE', $this->get_stacktags() ." --- {$this->current_tag['tag']} {$cdata}" );
    return TRUE;
  }/*}}}*/

  function ru_a_close(& $parser, $tag) {/*{{{*/
		$this->pop_tagstack();
		$this->add_to_container_stack($this->collapse_current_tag_link_data());
		$this->push_tagstack();
    return TRUE;
  }/*}}}*/

  function ru_li_open(& $parser, & $attrs, $tag) {/*{{{*/
		$this->pop_tagstack();
		$this->current_tag['cdata'] = array();
		$this->push_tagstack();
		if ($this->debug_tags) $this->syslog( __FUNCTION__, 'FORCE', "--- {$this->current_tag['tag']}" );
    $this->push_container_def($tag, $attrs);
    return TRUE;
  }/*}}}*/

  function ru_li_cdata(& $parser, & $cdata) {/*{{{*/
		$this->pop_tagstack();
		$this->current_tag['cdata'][] = trim($cdata);
		$this->push_tagstack();
		if ($this->debug_tags) $this->syslog( __FUNCTION__, 'FORCE', "--- {$this->current_tag['tag']} {$cdata}" );
    return TRUE;
  }/*}}}*/

  function ru_li_close(& $parser, $tag) {/*{{{*/
		$this->pop_tagstack();

    $list_container = array_pop($this->container_stack);
    $list_item      = array(
      'item'     => 'RA',
      'sethash'  => $list_container['sethash'],
      'children' => array(),
      'url'      => NULL,
    );
		if ( is_array($list_container['children']) )
    foreach ( $list_container['children'] as $item ) {
      if ( 1 == count($item) ) {
        list( $name, $contents ) = each( $item );
        if ( $name == 'children' ) $name = '_links'; 
        $list_item[$name] = $contents;
      } else if (0 < strlen("{$item['url']}"))  {
        if ( is_null($list_item['url']) ) $list_item['url'] = $item['url'];
        $list_item['children'][] = $item;
      }
    }
		if ($this->debug_tags) $this->recursive_dump($list_container,'(warning)');

		$this->push_tagstack();
		if ($this->debug_tags) $this->syslog( __FUNCTION__, 'FORCE', "--- {$this->current_tag['tag']} {$cdata}" );

		array_push($this->container_stack, array_filter($list_item));

		$this->stack_to_containers();
		return TRUE;
  }/*}}}*/

  function ru_span_open(& $parser, & $attrs, $tag) {/*{{{*/
		$this->pop_tagstack();
		$this->current_tag['cdata'] = array();
		$this->push_tagstack();
		if ($this->debug_tags) $this->syslog( __FUNCTION__, 'FORCE', "--- {$this->current_tag['tag']}" );
		return TRUE;
  }/*}}}*/

  function ru_span_cdata(& $parser, & $cdata) {/*{{{*/
		$this->pop_tagstack();
		$this->current_tag['cdata'][] = trim($cdata);
		$this->push_tagstack();
		if ($this->debug_tags) $this->syslog( __FUNCTION__, 'FORCE', "--- {$this->current_tag['tag']} {$cdata}" );
    return TRUE;
  }/*}}}*/

  function ru_span_close(& $parser, $tag) {/*{{{*/

		$this->pop_tagstack();
		$span_class = array_element($this->current_tag['attrs'],'CLASS');
		$span_type  = array(
			strtolower(0 < strlen($span_class) ? $span_class : 'desc')
			=> is_array($this->current_tag['cdata']) ? array_filter($this->current_tag['cdata']) : array()
		);
		$this->add_to_container_stack($span_type);
		$this->push_tagstack();
		if ($this->debug_tags) $this->syslog( __FUNCTION__, 'FORCE', "--- {$this->current_tag['tag']}" );
    return TRUE;
  }/*}}}*/

}


