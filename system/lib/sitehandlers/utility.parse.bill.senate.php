<?php

/*
 * Class SenateBillParseUtility
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class SenateBillParseUtility extends SenateCommonParseUtility {
  
	var $filtered_content = array();

  function __construct() {
    parent::__construct();
		$this->offset_1_keys_array       = array('filed by', 'long title' , 'scope'       , 'legislative status', 'subject' , 'primary committee' , 'secondary committee');
		$this->offset_1_keys_replacement = array('senator' , 'description', 'significance', 'status'            , 'subjects', 'main_referral_comm', 'secondary_committee');
		$this->offset_2_keys_array       = array('committee report');
		$this->offset_2_keys_replacement = array('comm_report_url');
  }

  function parse_html($content, array $response_headers) {/*{{{*/
		// This method fills (array) $this->filtered_content with parsed information,
		// suitable to be passed directly to method set_contents_from_array() 
		// Return value should be the contents of $this->filtered_content 
    parent::parse_html($content, $response_headers);

		// Clean up containers
    $containers = $this->get_containers();
		$this->reorder_with_sequence_tags($containers);
		array_walk($containers,create_function('& $a, $k, $s', '$s->reorder_with_sequence_tags($a);'),$this);
		array_walk($containers,create_function('& $a, $k', 'if (is_array(array_element($a,"children"))) unset($a["children"][$k]);'));
		$this->assign_containers($containers);

		$debug_method = $this->debug_tags;
    if ( $debug_method ) {/*{{{*/
      $this->recursive_dump($containers,'(warning)');
      $this->syslog( __FUNCTION__, 'FORCE', "(marker) - - - - - - - - - - Parsed containers" );
      $this->recursive_dump($this->filtered_content,'(warning)');
    }/*}}}*/
    $heading_map = array(
			'Filed by'            => 'senator',
      'Long title'          => 'description',
      'Scope'               => 'significance',
      'Legislative status'  => 'status',
      'Subject(s)'          => 'subjects',
      'Primary committee'   => 'main_referral_comm',
      'Committee report'    => 'comm_report_info',
      'Legislative History' => NULL,
    );
    $senate_bill_recordparts = array(
			'sn' => NULL,
			'filing_date' => NULL,
      'doc_url' => NULL,
      'comm_report_url' => NULL,
      'legislative_history' => array(),
			'senator' => array(),
    );

		// Obtain URLs of downloadable content
		$downloadable = $containers;
		$this->filter_nested_array($downloadable,'children[tagname=div][id=lis_download]',0);
		$downloadable = array_element($downloadable,0,array());

		// Table rows containing extended information about Senate Bill
		$extended = $containers;
		$this->filter_nested_array($extended,'children[tagname=tr]');

		// Chainable cells (legislative history, if present and nonempty)
		$chainable = $containers;
		$this->filter_nested_array($chainable,'children[class*=alt_color_g]');
		$concatenated = array();
		foreach ( $chainable as $p => $child ) {/*{{{*/
			foreach ( $child as $seq => $elements ) {
				$concatenated[$seq] = $elements;
				if ( !is_null(($next = array_element($containers, $seq + 1))) ) {
					$next = nonempty_array_element($next,'children',$next);
					$concatenated = array_merge($concatenated,$next);
					unset($containers[$seq + 1]);
					unset($extended[$seq + 1]);
					unset($extended[$seq]);
				}
			}
			unset($extended[$p]);
		}/*}}}*/

		// Remove noise entries (line breaks, text enclosed in square brackets)
		array_walk($concatenated,create_function(
			'& $a, $k', 'if (1 == preg_match("@(back to top|\[.*\])@i",array_element($a,"text"))) $a = NULL;'
		));	
		$senate_bill_recordparts['legislative_history'] = array_values(array_filter($concatenated));
		unset($concatenated);
		unset($chainable);

		// Remove other containers that do not contain an 'sn' text entry
		array_walk($extended,create_function(
			'& $a, $k, $s', '$d = $a ; $s->filter_nested_array($d,"#[text=sn]"); if ( !(0 < count($d)) ) $a = NULL;'
		),$this);
		$extended = array_element(array_values(array_filter($extended)),0);

		// Obtain SN
		$sn = $extended;
		$this->filter_nested_array($sn,'sn[text=sn]',0);
		$sn = array_element($sn,0);
		$senate_bill_recordparts['sn'] = $sn; 

		// Obtain doc_url entry from $downloadable using SN
		$doc_url = $downloadable;
		$this->filter_nested_array($doc_url,"url[text={$sn}]",0);
		$senate_bill_recordparts['doc_url'] = nonempty_array_element($doc_url,0);

		// Obtain Senator bio URLs following 'Filed by' entry
		$filing_date = $extended;
		$this->filter_nested_array($filing_date,'#[text*=Filed by|i]');

		foreach ( $extended as $index => $elements ) {
			if ( is_null(array_element($senate_bill_recordparts,"filing_date")) ) {
				if (is_null(($filing_date = array_element($elements,"file-date")))) continue;
				$senate_bill_recordparts["filing_date"] = $filing_date;
				continue;
			}
			if ( !array_key_exists('url',$elements) ) break;
			array_push($senate_bill_recordparts["senator"], $elements);
		}

		if ( $debug_method ) {/*{{{*/
			$this->recursive_dump($doc_url,"(warning) - F - {$sn} {$doc_url}");
			$this->recursive_dump($chainable,'(warning) C - -');
			$this->recursive_dump($extended,'(warning) - - E');
		}/*}}}*/

    $senate_bill_recordparts = array_filter($senate_bill_recordparts);

		// Cleanup the result array
    foreach ( $senate_bill_recordparts as $k => $v ) {
			if ( !is_array($v) ) continue;
			if ( $k == 'senator' ) continue; // Keep unencoded, we use this later to construct Joins
      $senate_bill_recordparts[$k] = json_encode($v);
    }

		// Merge the result array with $this->filtered_content 
		$senate_bill_recordparts = array_merge(
			$this->filtered_content,
			$senate_bill_recordparts
		);

		if (is_array(array_element($senate_bill_recordparts,'comm_report_url'))) {
			$senate_bill_recordparts['comm_report_info'] = array_element($senate_bill_recordparts['comm_report_url'],'text');
			$senate_bill_recordparts['comm_report_url']  = array_element($senate_bill_recordparts['comm_report_url'],'url');
		}

		//unset($senate_bill_recordparts['legislative_history']);
		//unset($this->filtered_content['legislative_history']);
		if ( TRUE || $debug_method ) {
			$this->syslog(__FUNCTION__,__LINE__,'(warning) -- -- - -- Final parsed structure');
			//$this->syslog(__FUNCTION__,__LINE__,'(warning) -- -- - -- (removed [legislative_history] key before return)');
			$this->recursive_dump($senate_bill_recordparts,'(warning) -- - - -');
		}

		return $senate_bill_recordparts;
  }/*}}}*/

  function ru_table_open(& $parser, & $attrs, $tag) {/*{{{*/
    $this->push_container_def($tag, $attrs);
    if ($this->debug_tags) {
      $this->syslog( __FUNCTION__, 'FORCE', $this->get_stacktags() . " --- {$this->current_tag['tag']}" );
      $this->recursive_dump($attrs,'(warning)');
    }
    return TRUE;
  }/*}}}*/
  function ru_table_cdata(& $parser, & $cdata) {/*{{{*/
  }/*}}}*/
  function ru_table_close(& $parser, $tag) {/*{{{*/
    $this->current_tag();
    $attributes = $this->current_tag['attrs']; 
    if ( is_array($attributes) && array_key_exists('ID', $attributes) )
    if ( 1 == preg_match("@^(lis_table|container)@i", $attributes['ID']) ) {
			$this->stack_to_containers();
      if ($this->debug_tags) {
        $this->syslog( __FUNCTION__, 'FORCE', "Pushing this element to container stack" );
      }
    }
    if ($this->debug_tags) {
      $this->syslog( __FUNCTION__, 'FORCE', $this->get_stacktags() . " --- {$this->current_tag['tag']}" );
      $this->recursive_dump($this->current_tag,'(warning)');
    }
    return TRUE;
  }/*}}}*/

  function ru_p_open(& $parser, & $attrs, $tag) {/*{{{*/
    return parent::ru_p_open($parser,$attrs,$tag);
  }/*}}}*/
  function ru_p_cdata(& $parser, & $cdata) {/*{{{*/
		return parent::ru_p_cdata($parser,$cdata);
  }/*}}}*/
  function ru_p_close(& $parser, $tag) {/*{{{*/
		// Identify column headings
		$this->pop_tagstack();
		$text = join('',$this->current_tag['cdata']);
		$offset_1_keys = join('|',$this->offset_1_keys_array);
		if ( 1 == preg_match('@('.$offset_1_keys.')@i', $text) ) {
			$this->current_tag['__LEGISCOPE__'] = array(
				'__TYPE__'  => 'title',
				'__NEXT__'  => intval($this->current_tag['attrs']['seq']) + 1,
				'__VALUE__' => $text,
			);
		}
		$offset_2_keys = join('|',$this->offset_2_keys_array);
		if ( 1 == preg_match('@('.$offset_2_keys.')@i', $text) ) {
			$this->current_tag['__LEGISCOPE__'] = array(
				'__TYPE__'  => 'title',
				'__NEXT__'  => intval($this->current_tag['attrs']['seq']) + 2,
				'__VALUE__' => $text,
			);
		}
		$this->push_tagstack();
		$result = parent::ru_p_close($parser, $tag);
    return $result;
  }/*}}}*/

  function ru_blockquote_open(& $parser, & $attrs, $tag) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'] = array();
    $this->push_tagstack();
    if ($this->debug_tags) $this->syslog( __FUNCTION__, 'FORCE', $this->get_stacktags() . " --- {$this->current_tag['tag']}" );
    return TRUE;
  }/*}}}*/
  function ru_blockquote_cdata(& $parser, & $cdata) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'][] = trim($cdata);
    $this->push_tagstack();
    if ($this->debug_tags) $this->syslog( __FUNCTION__, 'FORCE', "--- {$this->current_tag['tag']} {$cdata}" );
    return TRUE;
  }/*}}}*/
  function ru_blockquote_close(& $parser, $tag) {/*{{{*/
    return parent::ru_p_close($parser, $tag);
  }/*}}}*/

  function ru_tr_open(& $parser, & $attrs, $tag) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'] = array();
    $this->push_tagstack();
		$this->push_container_def($tag, $attrs);
    if ($this->debug_tags) $this->syslog( __FUNCTION__, 'FORCE', $this->get_stacktags() . " --- {$this->current_tag['tag']}" );
    return TRUE;
  }/*}}}*/
  function ru_tr_cdata(& $parser, & $cdata) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'][] = trim($cdata);
    $this->push_tagstack();
    return TRUE;
  }/*}}}*/
  function ru_tr_close(& $parser, $tag) {/*{{{*/
    $this->current_tag();
    $this->add_to_container_stack($this->current_tag);
    if ($this->debug_tags) $this->syslog( __FUNCTION__, 'FORCE', "--- {$this->current_tag['tag']}" );
    return TRUE;
  }/*}}}*/

  function ru_td_open(& $parser, & $attrs, $tag) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'] = array();
    $this->push_tagstack();
    if ($this->debug_tags) $this->syslog( __FUNCTION__, 'FORCE', $this->get_stacktags() . " --- {$this->current_tag['tag']}" );
    return TRUE;
  }/*}}}*/
  function ru_td_cdata(& $parser, & $cdata) {/*{{{*/
    $this->pop_tagstack();

		$class = array_element($this->current_tag['attrs'],'CLASS','--');
		$id    = array_element($this->current_tag['attrs'],'ID','--');
		if ( $this->debug_tags ) $this->syslog(__FUNCTION__,__LINE__,"(marker) - - - - - - - [{$class},{$id} s({$this->current_tag['attrs']['seq']} | {$this->tag_counter})] {$cdata}");
		if ( $class == '--' && $id == 'content' ) {
			// Special treatment for Senate Bill detail container,
			// to allow 'Filed on ... by ... text to be placed in container order
			// along with associated Senator bio URLs 
			$text = trim(trim($cdata,','));
			if ( 0 < strlen($text) ) { 
				$date_regex = '@(.*)filed on ([a-z ]+[0-9]+,[0-9 ]+) by(.*)@i';
				$sbn_regex = '@(.*)senate bill (no. )*([0-9]*)(.*)@i';
				$match_parts = array();
				$seq = intval($this->tag_counter) + 1;
				$this->tag_counter = $seq;
				$paragraph = array(
					'text' => $text,
					'seq'  => $seq,
				);
				if ( 1 == preg_match($date_regex, $paragraph['text'], $match_parts) ) {
					// All URLs following this child node are to be taken as Senators' bio URLs
					$paragraph['text'] = 'Filed by';
					$paragraph['file-date'] = array_element($match_parts,2);
				}
				else if ( 1 == preg_match($sbn_regex, $paragraph['text'], $match_parts) ) {
					// All URLs following this child node are to be taken as Senators' bio URLs
					$paragraph['text'] = 'sn';
					$paragraph['sn'] = "SBN-" . array_element($match_parts,3);
				}
				$this->add_to_container_stack($paragraph);
			}
		} else {
			$this->current_tag['cdata'][] = trim($cdata);
		}

    $this->push_tagstack();
    return TRUE;
  }/*}}}*/
  function ru_td_close(& $parser, $tag) {/*{{{*/
    $this->pop_tagstack();
		$content = array_filter(array(
			'text' => trim(join('',$this->current_tag['cdata'])),
			'class' => array_element($this->current_tag['attrs'],'CLASS'),
			'seq'   => nonempty_array_element($this->current_tag['attrs'],'seq',0),
		));
    $this->add_to_container_stack($content);
    $this->push_tagstack();
    if ($this->debug_tags) $this->syslog( __FUNCTION__, 'FORCE', "--- {$this->current_tag['tag']}" );
    return TRUE;
  }/*}}}*/

	/** Promise handlers **/

	function promise_title_executor( & $containerset, & $promise, $seqno, $ancestor ) {/*{{{*/
		$debug_method = FALSE;
		if ( $debug_method ) {
			$this->syslog( __FUNCTION__, __LINE__, "(marker) -- -- -- Modifying containerset ({$seqno}, {$ancestor})" );
			$this->recursive_dump($promise, "(marker) - -- P -- -");
			$this->recursive_dump($containerset, "(marker) - -- - -- C");
		}
		$value = 1 == count($containerset) ? $containerset['text'] : $containerset;
		$key   = $promise['__VALUE__'];
		$remap = preg_replace(
			array_map(create_function('$a', 'return "@.*{$a}.*@i";'), array_merge($this->offset_1_keys_array, $this->offset_2_keys_array)),
			array_merge($this->offset_1_keys_replacement, $this->offset_2_keys_replacement),
			$key
		);
		$containerset = array($key => $value);
		$this->filtered_content[$remap] =  $value;
		if ($debug_method) $this->recursive_dump($containerset, "(marker) - -- F -- C");
	}/*}}}*/

}
