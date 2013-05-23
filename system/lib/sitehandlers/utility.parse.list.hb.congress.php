<?php

/*
 * Class CongressRaListParseUtility
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class CongressHbListParseUtility extends CongressCommonParseUtility {
  
  function __construct() {
    parent::__construct();
		// House Bill listing structure:
		// <li>
		//   <span>HB00001</span>
		//   <a>[History]</a>
		//   <a>[Text As Filed 308k]</a>
		//   <span>AN ACT EXEMPTING ALL MANUFACTURERS AND IMPORTERS OF HYBRID VEHICLES FROM THE PAYMENT OF CERTAIN TAXES AND FOR OTHER PURPOSES </span>
		//   <span>Principal Author: SINGSON, RONALD VERSOZA</span>
		//   <span>Main Referral: WAYS AND MEANS</span>
		//   <span>Status: Substituted by HB05460</span>
		// </li>
  }

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
    return FALSE;
  }/*}}}*/

  function ru_a_close(& $parser, $tag) {/*{{{*/
		parent::ru_a_close($parser,$tag);
		if ( array_key_exists('ONCLICK',$this->current_tag['attrs']) )
			return FALSE;
    return TRUE;
  }/*}}}*/

}


