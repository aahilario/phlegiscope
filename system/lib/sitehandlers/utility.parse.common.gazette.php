<?php

/*
 * Class GazetteCommonParseUtility
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class GazetteCommonParseUtility extends GenericParseUtility {
  
  function __construct() {
    parent::__construct();
  }

  function parse_html(& $raw_html, $only_scrub = FALSE) {
    $status = !is_null(parent::parse_html($raw_html, $only_scrub));
    $this->mark_container_sequence();
    return $this;
  }

  function ru_a_open(& $parser, & $attrs, $tag) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'] = array();
		$this->current_tag['attrs']['CLASS'] = 'legiscope-remote';
    $this->update_current_tag_url('HREF');
    $this->push_tagstack();
    return TRUE;
  }/*}}}*/
  function ru_a_cdata(& $parser, & $cdata) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'][] = $cdata;
    $this->push_tagstack();
    return TRUE;
  }/*}}}*/
  function ru_a_close(& $parser, $tag) {/*{{{*/
    $this->pop_tagstack();
		array_walk($this->current_tag['cdata'],create_function(
			'& $a, $k', '$a = trim(preg_replace(array("@\s+@i","@\s+ñ@i"),array(" ","ñ"),$a));'
		));
    $link_data = $this->collapse_current_tag_link_data();
    $this->add_to_container_stack($link_data);
    $this->push_tagstack();
    return TRUE;
  }/*}}}*/

  function ru_div_open(& $parser, & $attrs, $tag) {/*{{{*/
    $this->push_container_def($tag, $attrs);
    return TRUE;
  }/*}}}*/
  function ru_div_close(& $parser, $tag) {/*{{{*/
    $this->current_tag();
    $keep = !(array_key_exists(array_element($this->current_tag['attrs'],'ID'),array_flip(array(
      'wrap-header',
      'wrap-footer',
      'tab-feedback',
      'navigation',
      'credits',
      'sidebar',
      'site-title',
      'inner',
    ))));
    $keep &= !(1 == preg_match('@(' . join('|',array(
      'menu-footer1-container',
      'entry-utility',
      'menu-judiciary-container',
      'printfriendly',
      'menu-social-media-container',
    )) . ')@', array_element($this->current_tag['attrs'],'CLASS')));
    $this->stack_to_containers();
    return $keep;
  }/*}}}*/

  function ru_meta_open(& $parser, & $attrs, $tag) {/*{{{*/
    return TRUE;
  }/*}}}*/
  function ru_meta_close(& $parser, $tag) {/*{{{*/
    return FALSE;
  }/*}}}*/

  function ru_link_open(& $parser, & $attrs, $tag) {/*{{{*/
    return TRUE;
  }/*}}}*/
  function ru_link_close(& $parser, $tag) {/*{{{*/
    return FALSE;
  }/*}}}*/

  function ru_script_open(& $parser, & $attrs, $tag) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'] = array();
    $this->push_tagstack();
    return TRUE;
  }/*}}}*/
  function ru_script_cdata(& $parser, & $cdata) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'][] = $cdata;
    $this->push_tagstack();
    return TRUE;
  }/*}}}*/
  function ru_script_close(& $parser, $tag) {/*{{{*/
		$this->current_tag();
		if ( C('DEBUG_GAZETTE_SOURCE_JAVASCRIPT') ) $this->recursive_dump($this->current_tag['cdata'], 
			"(marker) Scr");
    $this->add_to_container_stack($this->current_tag);
    return FALSE;
  }/*}}}*/

  function ru_style_open(& $parser, & $attrs, $tag) {/*{{{*/
    return TRUE;
  }/*}}}*/
  function ru_style_close(& $parser, $tag) {/*{{{*/
    return FALSE;
  }/*}}}*/

  function ru_h2_open(& $parser, & $attrs, $tag) {/*{{{*/
    // Handle A anchor/link tags
    return $this->standard_cdata_container_open();
  }  /*}}}*/
  function ru_h2_cdata(& $parser, & $cdata) {/*{{{*/
    // Attach CDATA to A tag 
    return $this->standard_cdata_container_cdata();
  }/*}}}*/

  function ru_h2_close(& $parser, $tag) {/*{{{*/
    return $this->standard_cdata_container_close();
  }/*}}}*/
  function ru_h1_open(& $parser, & $attrs, $tag) {/*{{{*/
    // Handle A anchor/link tags
    return $this->standard_cdata_container_open();
  }  /*}}}*/

  function ru_h1_cdata(& $parser, & $cdata) {/*{{{*/
    // Attach CDATA to A tag 
    return $this->standard_cdata_container_cdata();
  }/*}}}*/
  function ru_h1_close(& $parser, $tag) {/*{{{*/
    return $this->standard_cdata_container_close();
  }/*}}}*/

  function ru_b_open(& $parser, & $attrs, $tag) {/*{{{*/
    return parent::ru_p_open($parser,$attrs,$tag);
  }  /*}}}*/
  function ru_b_cdata(& $parser, & $cdata) {/*{{{*/
    // Attach CDATA to A tag 
    return parent::ru_p_cdata($parser,$cdata);
  }/*}}}*/
  function ru_b_close(& $parser, $tag) {/*{{{*/
    return $this->embed_cdata_in_parent();
  }/*}}}*/

  function ru_strong_open(& $parser, & $attrs, $tag) {/*{{{*/
    return parent::ru_p_open($parser,$attrs,$tag);
  }  /*}}}*/
  function ru_strong_cdata(& $parser, & $cdata) {/*{{{*/
    // Attach CDATA to A tag 
    return parent::ru_p_cdata($parser,$cdata);
  }/*}}}*/
  function ru_strong_close(& $parser, $tag) {/*{{{*/
    return $this->embed_cdata_in_parent();
  }/*}}}*/

  function ru_i_open(& $parser, & $attrs, $tag) {/*{{{*/
    return parent::ru_p_open($parser,$attrs,$tag);
  }  /*}}}*/
  function ru_i_cdata(& $parser, & $cdata) {/*{{{*/
    return parent::ru_p_cdata($parser,$cdata);
  }/*}}}*/
  function ru_i_close(& $parser, $tag) {/*{{{*/
    return $this->embed_cdata_in_parent();
  }/*}}}*/

  function ru_em_open(& $parser, & $attrs, $tag) {/*{{{*/
    return parent::ru_p_open($parser,$attrs,$tag);
  }  /*}}}*/
  function ru_em_cdata(& $parser, & $cdata) {/*{{{*/
    return parent::ru_p_cdata($parser,$cdata);
  }/*}}}*/
  function ru_em_close(& $parser, $tag) {/*{{{*/
    return $this->embed_cdata_in_parent();
  }/*}}}*/

  function ru_p_open(& $parser, & $attrs, $tag) {/*{{{*/
    return parent::ru_p_open($parser,$attrs,$tag);
  }/*}}}*/
  function ru_p_cdata(& $parser, & $cdata) {/*{{{*/
    if ( 1 == preg_match('@(nginx)@i', $cdata) ) {
      $this->pop_tagstack();
      $this->current_tag['discard'] = TRUE;
      $this->push_tagstack();
    }
    return parent::ru_p_cdata($parser,$cdata);
  }/*}}}*/
  function ru_p_close(& $parser, $tag) {/*{{{*/
		$this->current_tag();
		if (is_array($this->current_tag) && array_key_exists('discard',$this->current_tag) && ($this->current_tag['discard'] === TRUE)) {
      $this->pop_tagstack();
      $this->current_tag['cdata'] = array();
      $this->push_tagstack();
		}
    return parent::ru_p_close($parser,$tag);
  }/*}}}*/

  function ru_span_open(& $parser, & $attrs, $tag) {/*{{{*/
    // Handle A anchor/link tags
    return $this->standard_cdata_container_open();
  }  /*}}}*/
  function ru_span_cdata(& $parser, & $cdata) {/*{{{*/
    // Attach CDATA to A tag 
    return $this->standard_cdata_container_cdata();
  }/*}}}*/
  function ru_span_close(& $parser, $tag) {/*{{{*/
    return $this->standard_cdata_container_close();
  }/*}}}*/


}
