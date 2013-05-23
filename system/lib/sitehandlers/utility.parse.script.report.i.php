<?php

/*
 * Class iReportScriptParseUtility
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class iReportScriptParseUtility extends RawparseUtility {
  
	var $scripts = array();

  function __construct() {
    parent::__construct();
  }

  function ru_a_open(& $parser, & $attrs, $tagname) {/*{{{*/
		$this->pop_tagstack();
		$this->current_tag['cdata'] = array();
		$this->push_tagstack();
    return TRUE;
  }/*}}}*/
  function ru_a_cdata(& $parser, & $cdata) {/*{{{*/
    $this->pop_tagstack();
		$this->current_tag['cdata'][] = preg_replace('@[ ]+@',' ',str_replace(array(";","{","}"),array(";\n","{\n","}\n"),$cdata));
    $this->push_tagstack();
    return TRUE;
  }/*}}}*/
  function ru_a_close(& $parser, $tag) {/*{{{*/
    $this->current_tag();
		array_walk($this->current_tag['cdata'],create_function(
			'& $a, $k', '$a = trim($a);'
		));
		$this->scripts[] = $this->current_tag['cdata'];
		$this->add_to_container_stack($this->current_tag);
    return FALSE;
  }/*}}}*/

  function ru_script_open(& $parser, & $attrs, $tagname) {/*{{{*/
		$this->pop_tagstack();
		$this->current_tag['cdata'] = array();
		$this->push_tagstack();
    return TRUE;
  }/*}}}*/
  function ru_script_cdata(& $parser, & $cdata) {/*{{{*/
    $this->pop_tagstack();
		$this->current_tag['cdata'] = array_merge(
			$this->current_tag['cdata'],
			explode("\n",preg_replace('@[ ]+@',' ',str_replace(array(";","{","}"),array(";\n","{\n","\n}\n"),$cdata)))
		); 
    $this->push_tagstack();
    return TRUE;
  }/*}}}*/
  function ru_script_close(& $parser, $tag) {/*{{{*/
    $this->current_tag();
		array_walk($this->current_tag['cdata'],create_function(
			'& $a, $k', '$a = trim($a);'
		));
		$this->scripts[] = $this->current_tag['cdata'];
		$this->add_to_container_stack($this->current_tag);
    return FALSE;
  }/*}}}*/


}

