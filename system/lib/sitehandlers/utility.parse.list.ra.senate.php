<?php

/*
 * Class SenateRaListParseUtility
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class SenateRaListParseUtility extends SenateCommonParseUtility {
  
  function __construct() {
    parent::__construct();
  }

  function ru_span_open(& $parser, & $attrs, $tag) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'] = array();
    $this->push_tagstack();
    if ($this->debug_tags) $this->syslog( __FUNCTION__, __LINE__, $this->get_stacktags() . " --- {$this->current_tag['tag']} {$this->current_tag['attrs']['HREF']}" );
    return TRUE;
  }/*}}}*/

  function ru_span_cdata(& $parser, & $cdata) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'][] = $cdata;
    $this->push_tagstack();
    if ($this->debug_tags) $this->syslog( __FUNCTION__, __LINE__, "--- {$this->current_tag['tag']} {$cdata}" );
    return TRUE;
  }/*}}}*/

  function ru_span_close(& $parser, $tag) {/*{{{*/
    return $this->embed_cdata_in_parent();
    $this->pop_tagstack();
		$text = array(
			'text' => str_replace(array('[BR]',"\n"),array(''," "),join('',$this->current_tag['cdata'])),
		);
		$this->add_to_container_stack($text);
    $this->push_tagstack();
    if ($this->debug_tags) $this->syslog( __FUNCTION__, __LINE__, "--- {$this->current_tag['tag']}" );
    return TRUE;
  }/*}}}*/


}

