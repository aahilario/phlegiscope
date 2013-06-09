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



	/** seek_postparse_jnl **/

	function seek_postparse_jnl(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/

    $debug_method = FALSE;

    $urlmodel->ensure_custom_parse();

    $target_congress = $urlmodel->get_query_element('congress');

    if ( !empty($parser->metalink_url) ) {/*{{{*/
      $given_url = $urlmodel->get_url();
      $current_id = $urlmodel->get_id();
      $urlmodel->fetch($parser->metalink_url,'url');
      if ( is_null($target_congress) ) $target_congress = $urlmodel->get_query_element('congress');
      $this->syslog( __FUNCTION__, __LINE__, "(marker) Loading content of fake URL " . ($urlmodel->in_database() ? "OK" : "FAILED") . " " . $urlmodel->get_url() );
      $urlmodel->
        set_id($current_id)-> // Need this for establishing JOINs by URL id.
        set_url($given_url,FALSE);
    }/*}}}*/

    $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked for Congress '{$target_congress}' " . $urlmodel->get_url() . "; fake URL = {$parser->metalink_url}" );

    $ra_linktext = $parser->trigger_linktext;
    $this->
      set_parent_url($urlmodel->get_url())->
      parse_html(
        $urlmodel->get_pagecontent(),
        $urlmodel->get_response_header()
      );

    ///////////////////////////////////////////////////////////////////////////////////////////
    // Use parsed lists of URLs and journal SNs to generate alternate markup 

    $inserted_content = array();
    $cacheable_entries = array();
    $journal_entry_regex = '(Journal )?([^0-9]*)([0-9]*)([^,]*), (([0-9]*)-([0-9]*)-([0-9]*))';
    foreach ( $this->filtered_content as $title => $entries ) {/*{{{*/
      $matches = array();
      preg_match("@{$journal_entry_regex}@i", $title, $matches);
      $sn = array_element($matches,3);
      if ( empty($sn) ) $sn = array_element($matches,2);
      $title = trim("{$matches[1]}{$matches[2]}{$matches[3]}{$matches[4]}");
      $date = trim("{$matches[6]}/{$matches[7]}/{$matches[8]}");
      $matches = array(
        $title => array(
          'entries' => array(),
          'title' => $title,
          'metadata' => array(
            'sn'            => $sn,
            'publish_date'  => array_element($matches,5),
            'publish_year'  => array_element($matches,6),
            'publish_month' => array_element($matches,7),
            'publish_day'   => array_element($matches,8),
          ),
          'm' => $matches
        )
      );
      foreach ( $entries as $typelabel => $entry ) {
        $typelabel = preg_replace('@(.*)(PDF|HTML)(.*)@i','$2',$typelabel);
        $matches[$title]['entries'][$typelabel] = $entry;
      }
      // If there is only one type of document link,
      // a single link is generated with the document type indicated.
      if ( 1 == count($matches[$title]['entries']) ) {
        list( $type, $pager_url ) = each( $matches[$title]['entries'] );
        $inserted_content[] = <<<EOH
<li><a href="{$pager_url}" class="legiscope-remote">{$title}</a> {$date} ({$type})</li>
EOH;
      }
      else {/*{{{*/
        ksort($matches[$title]['entries']);
        list( $type, $pager_url ) = each( $matches[$title]['entries'] );
        $markup = <<<EOH
<li><a href="{$pager_url}" class="legiscope-remote async">{$title}</a> {$date} ({$type}) {placeholder}</li>
EOH;
        list( $type, $pager_url ) = each( $matches[$title]['entries'] );
        $secondary = <<<EOH
(<a href="{$pager_url}" class="legiscope-remote">{$type}</a>)
EOH;
        $markup = str_replace('{placeholder}',$secondary, $markup); 
        $inserted_content[] = $markup;
      }/*}}}*/
      // unset($matches[$title]['m']);
      $cacheable_entries[$title] = $matches[$title];
    }/*}}}*/
    $inserted_content = join(' ',$inserted_content);
    $inserted_content = <<<EOH
<div class="linkset">
<ul class="link-cluster">
{$inserted_content}
</ul>
</div>
EOH;
    if ( $debug_method ) {
      $this->recursive_dump($this->filtered_content,"(marker) - - - -");
    }

    ///////////////////////////////////////////////////////////////////////////////////////////
    // Test cacheable entries and store those that aren't yet recorded in
    // HouseJournalDocumentModel backing store. 

    $house_journals = new HouseJournalDocumentModel(); 

    $session_tag = $parser->trigger_linktext;

    if ( (0 < intval($target_congress)) && (0 < intval($session_tag)) && ("{$session_tag}" == trim("".intval($session_tag)."")) && (0 < count($cacheable_entries)) ) {/*{{{*/
      while ( 0 < count($cacheable_entries) ) {
        $batch = array();
        while ( count($batch) < 10 && ( 0 < count($cacheable_entries) ) ) {
          $entry = array_pop($cacheable_entries);
          $batch[array_element($entry,'title')] = $entry;
        }
        $query_parameters = array('AND' => array(
          'title'           => array_keys($batch), // array_map(create_function('$a', 'return array_element($a["metadata"],"sn");'), $batch), // array_keys($batch),
          'session_tag'     => $session_tag,
          'target_congress' => $target_congress
        ));
        if ( $debug_method ) {
          $this->syslog(__FUNCTION__,__LINE__,"(marker) - - - - Query parameters (target congress {$target_congress}):");
          $this->recursive_dump($query_parameters,"(marker) - QP - - -");
        }
        $house_journals->where($query_parameters)->recordfetch_setup();
        $journal_entry = array();
        while ( $house_journals->recordfetch($journal_entry) ) {
          $key = array_element($journal_entry,'title');
          if ( array_key_exists($key, $batch) && ($session_tag == array_element($journal_entry,'session_tag','ZZ')) && (array_element($journal_entry,'sn') == array_element(array_element($batch,'metadata',array()),'sn','ZZ'))) {
            if ( $debug_method ) {
              $this->syslog(__FUNCTION__,__LINE__,"(marker) - - - - Already in DB: {$journal_entry['title']}");
              $this->recursive_dump($journal_entry,"(marker) - - - QR -");
              $this->recursive_dump($batch[$key],"(marker) - - - - ER");
            }
            $batch[$key] = NULL;
          }
        }
        $batch = array_filter($batch); // Clear omitted entries
        if ( $debug_method ) {
          $this->syslog(__FUNCTION__,__LINE__,"(marker) - - - - Remainder: " . count($cacheable_entries));
          $this->recursive_dump($batch,"(marker) - - CE - -");
        }
        foreach ( $batch as $title => $components ) {
          $journal_id = $house_journals->
            set_id(NULL)->
            set_create_time(time())->
            set_congress_tag($target_congress)->
            set_session_tag($session_tag)->
            set_pdf_url(array_element($components['entries'],'PDF'))->
            set_url(array_element($components['entries'],'HTML'))->
            set_title($title)->
            set_sn(array_element($components['metadata'],'sn'))->
            stow();
            ;
          $this->syslog(__FUNCTION__,__LINE__,"(marker) - - - Inserted HouseJournal entry {$journal_id}" );
        }
      }
    }/*}}}*/

    ///////////////////////////////////////////////////////////////////////////////////////////
    // Extract list of pagers 

    $this->recursive_dump(($pager_candidates = $this->get_containers(
      'children[tagname*=div][attrs:CLASS*=padded]'
    )),"(barker) Remaining structure");

    $pagers = array();
    foreach ( $pager_candidates as $seq => $pager_candidate ) {/*{{{*/
      $this->filter_nested_array($pager_candidate,
        'url,text[url*=http://www.congress.gov.ph/download/index.php\?d=journals&page=(.*)&congress=(.*)|i]',0
      );
      if ( $debug_method ) {
        $this->syslog(__FUNCTION__,__LINE__,"(marker) - - - - - - - - Candidates {$seq}");
        $this->recursive_dump($pager_candidate,"(marker)- - - -");
      }
      foreach ( $pager_candidate as $pager ) {
        $pager_url = array_element($pager,'url');
        $pagers[array_element($pager,'text')] = <<<EOH
<a class="legiscope-remote" href="{$pager_url}">{$pager['text']} </a>
EOH;
      }
    }/*}}}*/
    ksort($pagers);
    if ( $debug_method ) {
      $this->syslog(__FUNCTION__,__LINE__,"(marker) - - - - - - - - Pager links");
      $this->recursive_dump($pagers,"(marker)- - - -");
    }

    ///////////////////////////////////////////////////////////////////////////////////////////
    // Extract list of Journal PDF URLs

    $this->recursive_dump(($registry = $this->get_containers(
      'children[tagname*=div][class*=padded]'
    )),"(barker)");

    array_walk($registry,create_function(
      '& $a, $k, $s', '$s->filter_nested_array($a,"url,text[url*=download/journal|i]",0);'
    ), $this);

    $registry = array_element(array_values(array_filter($registry)),0,array());

    if ( $debug_method ) {
      $this->recursive_dump($registry,"(marker) -- A --");
    }

    $u  = new UrlModel();
    $ue = new UrlEdgeModel();

    ///////////////////////////////////////////////////////////////////////////////////////////
    while ( 0 < count($registry) ) {/*{{{*/
      $subjects = array();
      while ( (10 < count($subjects)) && (0 < count($registry)) ) {/*{{{*/// Get 10 URLs
        $e = array_pop($registry);
        $e = array_element($e,'url');
        if ( is_null($e) ) continue;
        $urlhash = UrlModel::get_url_hash($e);
        $subjects[$urlhash] = $e;
      }/*}}}*/
      if ( $debug_method ) {
        $this->syslog(__FUNCTION__,__LINE__,"(marker) Remaining: " . count($registry));
        $this->recursive_dump($subjects,"(marker) -- A --");
      }
      $u->where(array('AND' => array(
        'urlhash' => array_keys($subjects)
      )))->recordfetch_setup();

      // Build list of items for which to check the presence of an edge
      $extant = array();
      $url = array();
      while ( $u->recordfetch($url) ) {
        $extant[$url['id']] = array(
          'url'   => $subjects[$url['urlhash']],
          'urlhash' => $url['urlhash'],
        ); // Just the URL, so that we can store UrlEdge nodes as needed
      }
      if ( $debug_method ) {
        $this->syslog(__FUNCTION__,__LINE__,"(marker) Extant: " . count($extant));
        $this->recursive_dump($extant,"(marker) -- E --");
      }
      if ( 0 < count($extant) ) {
        ksort($extant,SORT_NUMERIC);
        // Find already existing edges
        $ue->where(array('AND' => array(
          'a' => $urlmodel->get_id(),
          'b' => array_keys($extant)
        )))->recordfetch_setup();
        $edge = array();
        while ( $ue->recordfetch($edge) ) {
          $extant_in_list = array_key_exists($edge['b'], $extant) ? "extant" : "nonexistent";
          if ( $debug_method ) {
            $this->syslog(__FUNCTION__,__LINE__,"(marker) Skipping {$extant_in_list} edge ({$edge['b']},{$edge['a']})" );
          }
          if ( array_key_exists($edge['b'], $extant) ) $extant[$edge['b']] = NULL;
        }
        $extant = array_filter($extant);
        foreach ( $extant as $urlid => $urlinfo ) {
          $edgeid = $ue->set_id(NULL)->stow($urlmodel->get_id(), $urlid);
          $this->syslog(__FUNCTION__,__LINE__,"(marker) Edge stow result ID for {$urlid} {$urlinfo['url']} = (" . gettype($edgeid) . ") {$edgeid}");
        }
      }
    }/*}}}*/
    ///////////////////////////////////////////////////////////////////////////////////////////

    $this->cluster_urldefs = $this->generate_linkset($urlmodel->get_url(),'cluster_urls');

    if ( $debug_method ) {
      $this->syslog(__FUNCTION__,__LINE__,"(marker) Cluster URLdefs: " . count($this->cluster_urldefs) );
      $this->recursive_dump($this->cluster_urldefs,"(marker)");
    }

    $congress_select_baseurl = new UrlModel($urlmodel->get_url());
    $congress_select_baseurl->add_query_element('d'       , 'journals', FALSE, TRUE);
    $congress_select_baseurl->add_query_element('page'    , NULL      , FALSE, TRUE);
    $congress_select_baseurl->add_query_element('congress', NULL      , TRUE , TRUE);
    
    $this->syslog(__FUNCTION__,__LINE__,"(marker) ----- ---- --- -- - - - Congress SELECT base URL: " . $congress_select_baseurl->get_url() );
    // This actually extracts the Congress selector, not the Session selector
    $congress_select = $this->extract_house_session_select($congress_select_baseurl, FALSE, '[id*=form1]');
    if ( $debug_method ) {
      $this->syslog(__FUNCTION__,__LINE__,"(marker) ----- ---- --- -- - - - Raw Congress SELECT options");
      $this->recursive_dump($congress_select,"(marker)");
    }
    $this->filter_nested_array($congress_select,
      'markup[faux_url*=http://www.congress.gov.ph/download/index.php\?(.*)(d=journals)?(.*)|i][optval*=.?|i]',0
    );
    if ( $debug_method ) {
      $this->syslog(__FUNCTION__,__LINE__,"(marker) ----- ---- --- -- - - - Congress SELECT options");
      $this->recursive_dump($congress_select,"(marker)");
    }

    $use_alternate_generator = FALSE;

    $batch_regex = 'http://www.congress.gov.ph/download/journals_([0-9]*)/(.*)';

    $session_select = array();
    $fake_url_model = new UrlModel($urlmodel->get_url());
    $fake_url_model->add_query_element('congress', $target_congress, FALSE, TRUE);
    $fake_url_model->add_query_element('page', NULL, TRUE, TRUE);
    if ( $debug_method ) {
      $this->syslog(__FUNCTION__,__LINE__,"(marker) - - - - Fake URL for recomposition: " . $fake_url_model->get_url());
    }

    $child_collection = $use_alternate_generator
      ? $this->find_incident_pages($urlmodel, $batch_regex, NULL)
      // Generate $session_select from the session item source
      : $house_journals->fetch_session_item_source($session_select, $fake_url_model, $target_congress)
      ;
    if ( $debug_method ) {
      $this->syslog(__FUNCTION__,__LINE__,"(marker) Result of incident page search: " . count($child_collection) . ($use_alternate_generator ? "" : ", with regex {$batch_regex}"));
      $this->recursive_dump($child_collection,"(marker) - - - - - -");
    }

    krsort( $child_collection, SORT_NUMERIC );

    $fake_url_model->set_id(NULL)->add_query_element('congress', $target_congress, FALSE, TRUE);

    $pagecontent = $this->generate_congress_session_item_markup(
      $fake_url_model,
      $child_collection,
      $session_select,
      NULL, // query_fragment_filter = NULL => No reordering by query fragment, link text taken from markup 
      $congress_select // $session_select // Array : Links for switching Congress Session 
    );

    if ( $debug_method ) {
      $this->syslog(__FUNCTION__,__LINE__,"(marker) -- - -- - -- - -- Filtered content" );
      $this->recursive_dump($this->filtered_content,"(marker)- - -");
    }

    // $inserted_content .= join('',$this->get_filtered_doc());
    $pagers = join('',$pagers);
    $pagecontent .= <<<EOH
<div class="alternate-original alternate-content" id="senate-journal-block">
Session {$pagers}<br/>
{$inserted_content}
</div>
EOH;


	}/*}}}*/

}
