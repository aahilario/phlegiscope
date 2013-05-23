<?php

/*
 * Class GmanetworkCommonParseUtility
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class GmanetworkCommonParseUtility extends GenericParseUtility {
  
  function __construct() {
    parent::__construct();
  }

  function ru_iframe_open(& $parser, & $attrs, $tag) {/*{{{*/
    return TRUE;
  }  /*}}}*/
  function ru_iframe_cdata(& $parser, & $cdata) {/*{{{*/
    return TRUE;
  }/*}}}*/
  function ru_iframe_close(& $parser, $tag) {/*{{{*/
    return FALSE;
  }/*}}}*/

  function ru_link_open(& $parser, & $attrs, $tag) {/*{{{*/
    return TRUE;
  }  /*}}}*/
  function ru_link_cdata(& $parser, & $cdata) {/*{{{*/
    return TRUE;
  }/*}}}*/
  function ru_link_close(& $parser, $tag) {/*{{{*/
    return FALSE;
  }/*}}}*/

  function ru_div_open(& $parser, & $attrs, $tagname ) {/*{{{*/
    if ($this->debug_tags) $this->syslog( __FUNCTION__, __LINE__, $this->get_stacktags() . "--- {$this->current_tag['tag']}" );
    $this->pop_tagstack();
    $this->current_tag['cdata'] = array();
    $this->push_tagstack();
    if ($this->debug_tags) $this->recursive_dump($attrs,__LINE__);
    $this->push_container_def($tagname, $attrs);
    return TRUE;
  }/*}}}*/
  function ru_div_cdata(& $parser, & $cdata) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'][] = $cdata;
    $this->push_tagstack();
    if ($this->debug_tags) $this->syslog( __FUNCTION__, __LINE__, $this->get_stacktags() . "--- {$this->current_tag['tag']} {$cdata}" );
    return TRUE;
  }/*}}}*/
  function ru_div_close(& $parser, $tag) {/*{{{*/
    $skip = FALSE;
    $this->pop_tagstack();
    if ( is_array($this->current_tag) && array_key_exists('attrs', $this->current_tag) ) {
      if ( array_key_exists(array_element($this->current_tag['attrs'],'CLASS'),array_flip(array(
        'col',
        'col2',
        'col3',
        'clear',
        'footer',
        'explore',
        'ackno',
      )))) $skip = TRUE;
      if ( array_key_exists(array_element($this->current_tag['attrs'],'ID'),array_flip(array(
        'ads',
        'adrow1',
        'adrow2',
        'imgbanner',
        'header_logo',
        'nav',
        'navbar_sections',
        'navbar_top',
        'sidebox_icons',
        'counthead',
				'header',
      )))) $skip = TRUE;
      if ( $skip && $this->debug_tags ) {
        usleep(20000);
        $tag_cdata = join('', array_element($this->current_tag,'cdata',array('--empty--')));
        $this->syslog(__FUNCTION__,__LINE__,"(marker) Warning - Rejecting tag with CDATA [{$tag_cdata}]");
        $this->recursive_dump($this->current_tag,"(warning) {$tag}" );
      }
    }
    $this->push_tagstack();
    if (is_array($this->current_tag) && !$skip ) $this->stack_to_containers();
    
    return !$skip;
  }/*}}}*/

  function ru_a_open(& $parser, & $attrs, $tag) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'] = array();
    $this->update_current_tag_url('HREF');
    if ( !array_key_exists('CLASS', $this->current_tag['attrs']) ) {
      // $this->syslog(__FUNCTION__,__LINE__,"Setting CSS selector for '{$this->current_tag['attrs']['HREF']}'");
      $this->current_tag['attrs']['CLASS'] = 'legiscope-remote';
      $this->current_tag['attrs']['ID'] = UrlModel::get_url_hash($this->current_tag['attrs']['HREF']);
    } else {
      // $this->syslog(__FUNCTION__,__LINE__,"Adding CSS selector for '{$this->current_tag['attrs']['HREF']}'");
      $this->current_tag['attrs']['CLASS'] .= ' legiscope-remote';
    }
    $this->push_tagstack();
    if ($this->debug_tags) $this->syslog( __FUNCTION__, __LINE__, $this->get_stacktags() . " --- {$this->current_tag['tag']} {$this->current_tag['attrs']['HREF']}" );
    return TRUE;
  }/*}}}*/
  function ru_a_cdata(& $parser, & $cdata) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'][] = $cdata;
    $this->push_tagstack();
    if ($this->debug_tags) $this->syslog( __FUNCTION__, __LINE__, "--- {$this->current_tag['tag']} {$cdata}" );
    return TRUE;
  }/*}}}*/
  function ru_a_close(& $parser, $tag) {/*{{{*/
    $skip = FALSE;
    $this->pop_tagstack();
    $link_data = $this->collapse_current_tag_link_data();
      if (!(1 == preg_match('@eleksyon2013@i', $link_data['url']) ) ) {
        $skip = TRUE;
      }
    $this->push_tagstack();
    if ( !$skip ) {
    $this->add_to_container_stack($link_data);
    if ($this->debug_tags) $this->syslog( __FUNCTION__, __LINE__, "--- {$this->current_tag['tag']}" );
    }
    return !$skip;
  }/*}}}*/

}

