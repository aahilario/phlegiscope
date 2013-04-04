<?php

/*
 * Class SenateBillInfoParseUtility
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class SenateBillInfoParseUtility extends GenericParseUtility {
  
  function __construct() {
    parent::__construct();
  }

  function parse_html($content, array $response_headers) {
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
  }

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
    $this->pop_tagstack();
    $this->current_tag['cdata'] = array();
    $this->push_tagstack();
    if ($this->debug_tags) $this->syslog( __FUNCTION__, 'FORCE', $this->get_stacktags() . " --- {$this->current_tag['tag']}" );
    return TRUE;
  }/*}}}*/

  function ru_p_cdata(& $parser, & $cdata) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'][] = trim($cdata);
    $this->push_tagstack();
    if ($this->debug_tags) $this->syslog( __FUNCTION__, 'FORCE', "--- {$this->current_tag['tag']} {$cdata}" );
    return TRUE;
  }/*}}}*/

  function ru_p_close(& $parser, $tag) {/*{{{*/

    $this->pop_tagstack();
    $this->add_to_container_stack($this->current_tag);
    $this->push_tagstack();
    if ($this->debug_tags) $this->syslog( __FUNCTION__, 'FORCE', "--- {$this->current_tag['tag']}" );
    return TRUE;
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

    $this->pop_tagstack();
    $this->add_to_container_stack($this->current_tag);
    $this->push_tagstack();
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
    $this->add_to_container_stack($this->current_tag);
    $this->push_tagstack();
    if ($this->debug_tags) $this->syslog( __FUNCTION__, 'FORCE', "--- {$this->current_tag['tag']}" );
    return TRUE;
  }/*}}}*/

  function ru_a_open(& $parser, & $attrs, $tag) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'] = array();
    $this->push_tagstack();
    if ($this->debug_tags) $this->syslog( __FUNCTION__, 'FORCE', $this->get_stacktags() . " --- {$this->current_tag['tag']} {$this->current_tag['attrs']['HREF']}" );
    return TRUE;
  }/*}}}*/

  function ru_a_cdata(& $parser, & $cdata) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'][] = trim($cdata);
    $this->push_tagstack();
    if ($this->debug_tags) $this->syslog( __FUNCTION__, 'FORCE', "--- {$this->current_tag['tag']} {$cdata}" );
    return TRUE;
  }/*}}}*/

  function ru_a_close(& $parser, $tag) {/*{{{*/

    $this->pop_tagstack();
    $this->add_to_container_stack($this->current_tag);
    $this->push_tagstack();
    if ($this->debug_tags) $this->syslog( __FUNCTION__, 'FORCE', "--- {$this->current_tag['tag']}" );
    return TRUE;
  }/*}}}*/

}
