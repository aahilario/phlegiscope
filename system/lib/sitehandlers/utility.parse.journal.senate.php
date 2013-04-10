<?php

/*
 * Class SenateJournalParseUtility
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class SenateJournalParseUtility extends SenateCommonParseUtility {
  
	var $activity_summary = array();

  function __construct() {
    parent::__construct();
  }

  function ru_td_open(& $parser, & $attrs, $tag) {/*{{{*/
		$this->pop_tagstack();
		$this->current_tag['cdata'] = array();
		$this->push_tagstack();
		$this->push_container_def($tag, $attrs);
		return TRUE;
  }/*}}}*/
  function ru_td_cdata(& $parser, & $cdata) {/*{{{*/
		$this->pop_tagstack();
		$this->current_tag['cdata'][] = str_replace("\n"," ",trim($cdata));
		$this->push_tagstack();
		return TRUE;
  }/*}}}*/
  function ru_td_close(& $parser, $tag) {/*{{{*/
		$this->pop_tagstack();
		$text = join(' ', $this->current_tag['cdata']);
		$paragraph = array(
			'text' => preg_replace("@(\n|\r|\t)@",'',$text),
			'seq' => $this->current_tag['attrs']['seq'],
		);
		if ( $this->current_tag['attrs']['ID'] == 'content' ) {
			$paragraph['metadata'] = TRUE;
			$matches = array();
			$pattern = '@^(.*) Congress - (.*)(Date:(.*))\[BR\](.*)Approved on(.*)\[BR\]@im'; 
			preg_match( $pattern, $paragraph['text'], $matches );
			if ( is_null($matches[2]) ) {
				$matches = array();
        $pattern = '@^(.*) Congress(.*)\[BR\](.*)Committee Report No. ([0-9]*) Filed on (.*)(\[BR\])*@im'; 
				preg_match( $pattern, $paragraph['text'], $matches );
				array_push($this->activity_summary,array(
					'source' => $paragraph['text'],
					'metadata' => array(
						'congress' => $matches[1],
						'report'   => $matches[4],
						'filed'    => trim($matches[5]),
						'n_filed'  => strtotime(trim($matches[5])),
					)));
			} else array_push($this->activity_summary,array(
				'source' => $paragraph['text'],
				'metadata' => array(
					'congress'   => $matches[1],
					'session'    => $matches[2],
					'date'       => trim($matches[4]),
					'n_date'     => strtotime(trim($matches[4])),
					'approved'   => trim($matches[6]),
					'n_approved' => strtotime(trim($matches[6])),
			)));
		}
		$this->add_to_container_stack($paragraph);
		if ( $this->debug_tags) 
			$this->syslog( __FUNCTION__, __LINE__, "(marker) --- {$this->current_tag['tag']} {$text}" );
		$this->push_tagstack();
		$this->stack_to_containers();
		return TRUE;
  }/*}}}*/

  function ru_div_open(& $parser, & $attrs, $tag) {/*{{{*/
    return parent::ru_div_open($parser,$attrs,$tag);
  }  /*}}}*/
  function ru_div_cdata(& $parser, & $cdata) {/*{{{*/
    return parent::ru_div_cdata($parser,$cdata);
  }/*}}}*/
  function ru_div_close(& $parser, $tag) {/*{{{*/
		$this->current_tag();
    $parent_result = parent::ru_div_close($parser,$tag);
		return $parent_result;
  }/*}}}*/

  function ru_small_open(& $parser, & $attrs, $tag) {/*{{{*/
		$this->pop_tagstack();
		$this->current_tag['cdata'] = array();
		$this->push_tagstack();
    return TRUE;
  }  /*}}}*/
  function ru_small_cdata(& $parser, & $cdata) {/*{{{*/
		$this->pop_tagstack();
		$this->current_tag['cdata'][] = str_replace("\n"," ",trim($cdata));
		$this->push_tagstack();
    return TRUE;
  }/*}}}*/
  function ru_small_close(& $parser, $tag) {/*{{{*/
		$this->pop_tagstack();
		$text = join(' ', $this->current_tag['cdata']);
		$paragraph = array(
			'text' => $text,
			'seq' => $this->current_tag['attrs']['seq'],
		);
		$this->add_to_container_stack($paragraph,'ul');
    if ( $this->debug_tags) $this->syslog( __FUNCTION__, __LINE__, "(marker) --- {$this->current_tag['tag']} {$text}" );
		$this->push_tagstack();
		return TRUE;
  }/*}}}*/

  function ru_p_open(& $parser, & $attrs, $tag) {/*{{{*/
    if ( 0 < count($this->tag_stack) ) {
      $this->pop_tagstack();
      $this->current_tag['cdata'] = array();
      $this->push_tagstack();
    }
    return TRUE;
  }  /*}}}*/
  function ru_p_cdata(& $parser, & $cdata) {/*{{{*/
    if ( !empty($cdata) && ( 0 < count($this->tag_stack) ) ) {
      $this->pop_tagstack();
      $this->current_tag['cdata'][] = str_replace("\n"," ",trim($cdata));
      $this->push_tagstack();
    }
    return TRUE;
  }/*}}}*/
  function ru_p_close(& $parser, $tag) {/*{{{*/
		$this->current_tag();
		$text = preg_replace('@[^-A-Z0-9/() .]@i','',join(' ', $this->current_tag['cdata']));
		if ( 0 < strlen($text) ) {
			$paragraph = array('text' => $text);
			array_push($this->activity_summary, array(
				'section' => "{$text}",
				'content' => NULL, 
			));
			$this->add_to_container_stack($paragraph);
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
    return $this->embed_cdata_in_parent();
  }/*}}}*/

  // For cmmittee reports only - BLOCKQUOTE tags wrap text lines
  function ru_blockquote_open(& $parser, & $attrs, $tag) {/*{{{*/
		return $this->ru_li_open($parser,$attrs,$tag);
  }  /*}}}*/
  function ru_blockquote_cdata(& $parser, & $cdata) {/*{{{*/
		return $this->ru_li_cdata($parser,$cdata);
  }/*}}}*/
  function ru_blockquote_close(& $parser, $tag) {/*{{{*/
		$this->current_tag();
		$text = join(' ', $this->current_tag['cdata']);
    $paragraph = array('text' => $text);
		if ( !empty($text) ) {
			$activity = array_pop($this->activity_summary);
			$activity['content'] = explode('[BR]',$text);
			array_push($this->activity_summary, $activity);
			$this->add_to_container_stack($paragraph);
		}
    return TRUE;
  }/*}}}*/

  function ru_li_open(& $parser, & $attrs, $tag) {/*{{{*/
    if ( 0 < count($this->tag_stack) ) {
      $this->pop_tagstack();
      $this->current_tag['cdata'] = array();
      $this->push_tagstack();
    }
    return TRUE;
  }  /*}}}*/
  function ru_li_cdata(& $parser, & $cdata) {/*{{{*/
    if ( !empty($cdata) && ( 0 < count($this->tag_stack) ) ) {
      $this->pop_tagstack();
      $this->current_tag['cdata'][] = str_replace("\n"," ",trim($cdata));
      $this->push_tagstack();
    }
    return TRUE;
  }/*}}}*/
  function ru_li_close(& $parser, $tag) {/*{{{*/
		$this->current_tag();
		$text = join(' ', $this->current_tag['cdata']);
    $paragraph = array('text' => $text);
		if ( !empty($text) ) $this->add_to_container_stack($paragraph);
    return TRUE;
  }/*}}}*/

  function ru_ul_open(& $parser, & $attrs, $tag ) {/*{{{*/
    $this->push_container_def($tag, $attrs);
    return TRUE;
  }/*}}}*/
  function ru_ul_cdata(& $parser, & $cdata) {/*{{{*/
    return TRUE;
  }/*}}}*/
  function ru_ul_close(& $parser, $tag) {/*{{{*/
		$this->current_tag();
    $this->stack_to_containers();
		if ( array_key_exists( strtolower( $this->current_tag['attrs']['CLASS'] ), 
		array_flip(array('lis_ul','lis_download')) ) || (1 == count($this->activity_summary)) ) {
			if ( 0 < count($this->activity_summary) ) {
				$section = array_pop($this->activity_summary);
				$container_id_hash = $this->current_tag['CONTAINER'];
				$section['content'] = $this->get_container_by_hashid($container_id_hash,'children');
				$this->reorder_with_sequence_tags($section['content']);
				array_push($this->activity_summary,$section);
			}
		}
    return TRUE;
  }/*}}}*/

  function ru_table_open(& $parser, & $attrs, $tag ) {/*{{{*/
    $this->push_container_def($tag, $attrs);
    return TRUE;
  }/*}}}*/
  function ru_table_cdata(& $parser, & $cdata) {/*{{{*/
    return TRUE;
  }/*}}}*/
  function ru_table_close(& $parser, $tag) {/*{{{*/
    $this->stack_to_containers();
    return TRUE;
  }/*}}}*/


}
