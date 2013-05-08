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
		$this->offset_1_keys_array       = array('long title' , 'scope'       , 'legislative status', 'subject' , 'primary committee' , 'secondary committee');
		$this->offset_1_keys_replacement = array('description', 'significance', 'status'            , 'subjects', 'main_referral_comm', 'secondary_committee');
		$this->offset_2_keys_array       = array('committee report');
		$this->offset_2_keys_replacement = array('comm_report_url');
  }

  function parse_html($content, array $response_headers) {/*{{{*/
    parent::parse_html($content, $response_headers);
    // $extract_containers = create_function('$a', 'return (("table" == $a["tagname"]) && (1 == preg_match("@^(lis_table|container)@i", $a["id"]))) ? array($a["id"] => $a["children"]) : NULL;');
    // $containers         = array_values(array_filter(array_map($extract_containers, $this->get_containers())));
    $containers = $this->get_containers();
    if ( $this->debug_tags ) {
      $this->syslog( __FUNCTION__, 'FORCE', "Final structure" );
      $this->recursive_dump($containers,'(warning)');
    }
    $heading_map = array(
      'Long title'          => 'description',
      'Scope'               => 'significance',
      'Legislative status'  => 'status',
      'Subject(s)'          => 'subjects',
      'Primary committee'   => 'main_referral_comm',
      'Committee report'    => 'comm_report_info',
      'Legislative History' => NULL,
    );
    $senate_bill_recordparts = array(
      'legislative_history' => array(),
      'doc_url' => NULL,
      'comm_report_url' => NULL,
    );
    $current_heading = NULL;
    foreach ( $containers as $container ) {
      $legis_history_entry = array();
      $item_stack = array();
      $no_lis_date = TRUE;
      foreach ( $container as $table_id => $children ) {
        if ( !is_array($children) ) continue;
        foreach ( $children as $tag ) {
          if ( !is_array($tag['cdata']) ) continue;
          $text = trim(join(' ',$tag['cdata']));
          switch ( $tag['tag'] ) {
            case 'A': 
              if ( ( 1 == preg_match('@/lisdata/@i', $tag['attrs']['HREF']) ) && 
                is_null($senate_bill_recordparts['doc_url']) )
                $senate_bill_recordparts['doc_url'] = $tag['attrs']['HREF'];
              else if ( $current_heading == "comm_report_info" ) {
                $senate_bill_recordparts[$current_heading] = array("{$text}");
                $senate_bill_recordparts['comm_report_url'] = array(
                  'url' => $tag['attrs']['HREF'],
                  'text' => trim(join(' ',$tag['cdata'])),
                );
                $senate_bill_recordparts['comm_report_url'] = $tag['attrs']['HREF']; 
              }  
              break;
            case 'P':
              $item = array("{$text}");
              array_push($item_stack, $item);
              $current_heading = $heading_map[trim($text)];
              break;
            case 'BLOCKQUOTE':
              $item = array_pop($item_stack);
              $item = $heading_map[trim($item[0])];
              if ( 0 < strlen($item) ) {
                if ( array_key_exists($item, $senate_bill_recordparts) && is_array($senate_bill_recordparts[$item]) ) 
                  $senate_bill_recordparts[$item][] = $text;
                else
                  $senate_bill_recordparts[$item] = $text;
              }
              break;
            case 'TD':
              // Alternating cells contain date and legislative history events.
              if ( $table_id == "lis_table" ) {
                if ( $tag['attrs']['CLASS'] == 'lis_table_date' ) {
                  $no_lis_date = FALSE;
                }
                if ( !$no_lis_date ) {
                  if ( 1 == preg_match('@^([0-9]+)/([0-9]+)/([0-9]+)@', $text) ) {
                    $history_entry = array(
                      'date' => $text,
                      'entry' => NULL,
                    );
                    array_push($senate_bill_recordparts['legislative_history'], $history_entry);
                  } else {
                    $legis_history_entry = array_pop($senate_bill_recordparts['legislative_history']);
                    $legis_history_entry["entry"] = $text;
                    array_push($senate_bill_recordparts['legislative_history'], $legis_history_entry);
                  }
                }
              }
              break;
            default:
              break;
          }
        }
      }  
    }
    $senate_bill_recordparts = array_filter($senate_bill_recordparts);
    if ( array_key_exists('legislative_history', $senate_bill_recordparts) ) {
      $senate_bill_recordparts['legislative_history'] = json_encode($senate_bill_recordparts['legislative_history']);
    }
    foreach ( $senate_bill_recordparts as $k => $v ) {
      if ( !is_array($v) ) continue;
      if ( array_key_exists('url', $v) ) $senate_bill_recordparts[$k] = json_encode($v);
      else $senate_bill_recordparts[$k] = trim(join(' ', $v));
    }
    if ( $this->debug_tags ) $this->recursive_dump($senate_bill_recordparts,'(warning)');
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
    if ($this->debug_tags) $this->syslog( __FUNCTION__, 'FORCE', "--- {$this->current_tag['tag']} {$cdata}" );
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
    $this->current_tag['cdata'][] = trim($cdata);
    $this->push_tagstack();
    if ($this->debug_tags) $this->syslog( __FUNCTION__, 'FORCE', "--- {$this->current_tag['tag']} {$cdata}" );
    return TRUE;
  }/*}}}*/
  function ru_td_close(& $parser, $tag) {/*{{{*/
    $this->pop_tagstack();
		$content = array_filter(array(
			'text' => trim(join('',$this->current_tag['cdata'])),
			'class' => $this->current_tag['attrs']['CLASS'],
			'seq'   => $this->current_tag['attrs']['seq'],
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
