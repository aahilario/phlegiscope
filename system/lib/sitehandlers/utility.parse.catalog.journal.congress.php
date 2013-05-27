<?php

/*
 * Class CongressJournalCatalogParseUtility
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class CongressJournalCatalogParseUtility extends CongressCommonParseUtility {
  
  var $filtered_content = array();

  function __construct() {
    parent::__construct();
  }

  function ru_li_open(& $parser, & $attrs, $tag) {/*{{{*/
		// Treat LI tags as paragraph containers
    $this->pop_tagstack();
    $this->current_tag['cdata'] = array();
    $this->push_tagstack();
    $this->push_container_def($tag, $attrs);
    return TRUE;
  }/*}}}*/
  function ru_li_cdata(& $parser, & $cdata) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'][] = trim($cdata);
    $this->push_tagstack();
		return TRUE; 
  }/*}}}*/
  function ru_li_close(& $parser, $tag) {/*{{{*/

    $this->pop_tagstack();
    $text = trim(join('',$this->current_tag['cdata']));
    $this->current_tag['__LEGISCOPE__'] = array(
      array(
        '__TYPE__'     => '__POSTPROC__',
        '__POSTPROC__' => array(
          '__LABEL__' => $text,
          '__SETHASH__' => array_element($this->current_tag,'CONTAINER'),
        ),
        // Defer generating 'title' promises until after entire document is parsed
        // This is done in promise_prepare_state() 
        #array(
        #  '__TYPE__' => 'title',
        #),
      )
    );
    $this->stack_to_containers();
    $this->push_tagstack();

    return TRUE;
  }/*}}}*/

  function ru_p_close(& $parser, $tag) {/*{{{*/
		$this->current_tag();
		if (($tag == 'P') &&  ('meta' == array_element($this->current_tag['attrs'],'CLASS'))) {
			return FALSE;
		}
		return parent::ru_p_close($parser, $tag);
	}/*}}}*/

	/** Promise handlers **/

  function generate_filtered_content_promise_labels(& $li) {/*{{{*/

    $debug_method = FALSE;

		$text = array_element(array_element($li,'__POSTPROC__',array()),'__LABEL__');
    unset($li['__POSTPROC__']['__LABEL__']);
    unset($li['__POSTPROC__']);

    if (array_key_exists('children', $li)) {
      $journal_entries = array();
      foreach ( $li['children'] as $seq => $e ) {/*{{{*/
        $url = array_element($e,'url');
        if ( is_null($url) ) continue;
        $match = preg_replace(
          array(
            '@(^http://www.congress.gov.ph/legis/print_journal.php\?congress=([0-9]*)(.*))@i',
            '@(^http://www.congress.gov.ph/download/journals_([0-9]*)(.*))@i',
          ),
          array(
            'journal',
            'journal',
          ),
          $url
        );
        if ( $debug_method ) {
          $this->syslog(__FUNCTION__,__LINE__,"(marker) - - - {$seq} {$match} Record {$text}");
          $this->recursive_dump($li,"(marker) >");
        }
        if ( !($match == 'journal') ) {
          $li['children'][$seq] = NULL;
          $li['class'] = 'MISMATCH';
          continue;
        }
        $li['children'][$seq]['title'] = $text;
        $li['children'][$seq]['__ANCESTOR__'] = $li['seq'];
        $journal_entries[] = $seq; 
      }/*}}}*/
      $li['children'] = array_filter($li['children']);
      if ( 0 < count($journal_entries) ) {
        unset($li['attrs']);
        $li['class'] = 'journal-entry';
      }
    }
    if ( 'MISMATCH' == array_element($li,'class') ) {
      $li = NULL;
    }

  }/*}}}*/

  function promise_prepare_state() {/*{{{*/
    //
    // Hook used in {LegiscopeBase}
    //
    // Create 'title' entries in $this->promise_stack that could not be created 
    // during normal XML parsing (because the LI tag CDATA text is associated with
    // at least one sibling A tag (possibly two), 
    // and those siblings' sequence (seq) attributes are not accessible during the
    // XML parser forward pass).
    //
    // We don't populate the filtered_content queue here,
    // instead merely populating the stack, so that work is done
    // in promise_title_executor() in much the same way that it is done in
    // the SenateBillParseUtility. 
    $debug_method = FALSE;
    if ( 0 < count($this->promise_stack) ) return TRUE;
    $listitems = $this->get_containers('#[tagname=li]');
    array_walk($listitems,create_function(
      '& $a, $k, $s', '$s->generate_filtered_content_promise_labels($a);'
    ),$this);
    $listitems = array_filter($listitems);
    $this->filter_nested_array($listitems,'children[class*=journal-entry]');
    // $this->recursive_dump($listitems,"(marker) Containers - - -");
    foreach ( $listitems as $container_id => $links ) {
      if (!is_array($links) || !(0 < count($links))) continue;
      foreach ( $links as $seq => $link ) {
        $this->promise_stack[$seq] = array(
          $link['__ANCESTOR__'] => array(
            '__TYPE__'  => 'title',
            '__VALUE__' => $link['title'],
          )
        );
      }
    }
    if ( $debug_method ) {
      $this->syslog(__FUNCTION__,__LINE__,"(marker) - - - - - - - Final promise stack - - - - - - -");
      $this->recursive_dump($listitems,"(marker) Promises - - -");
    }
    return TRUE;
  }/*}}}*/

	function promise_title_executor( & $containerset, & $promise, $seqno, $ancestor ) {/*{{{*/
		$debug_method = FALSE;
		if ( $debug_method ) {
			$this->syslog( __FUNCTION__, __LINE__, "(marker) -- -- -- Modifying containerset ({$seqno}, {$ancestor})" );
			$this->recursive_dump($promise     , "(marker) - -- P -- -");
			$this->recursive_dump($containerset, "(marker) - -- - -- C");
		}
		$key   = $promise['__VALUE__'];
		$value = 1 == count($containerset) ? $containerset['text'] : $containerset;
    if ( !array_key_exists($key, $this->filtered_content) ) $this->filtered_content[$key] = array();
		$this->filtered_content[$key][array_element($containerset,'text')] = $containerset['url'];
	}/*}}}*/


}
