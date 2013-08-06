<?php

/*
 * Class SenateJournalParseUtility
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class SenateJournalParseUtility extends SenateDocAuthorshipParseUtility {
  
  var $activity_summary = array();

  function __construct() {
    parent::__construct();
  }

  function parse_activity_summary(array & $journal_data) {/*{{{*/

    $debug_method = FALSE;
    $sn = NULL;

    // $this->activity_summary is populated by the parser
    $journal_data = array_filter($this->activity_summary);

    if ($debug_method) $this->recursive_dump($journal_data,'(marker) A - begin');

    $pagecontent = '';

		$test_urls = array();

		$recording_date = NULL;
		$approval_date  = NULL;
    foreach ( $journal_data as $n => $e ) {/*{{{*/

      if ( array_key_exists('metadata',$e) ) {/*{{{*/// Extract journal header info markup 
        $e              = $e['metadata'];
        $recording_date = $e['date'];
        $approval_date  = $e['approved'];
        $pagecontent .= <<<EOH
<br/>
<div>
Journal of the {$e['congress']} Congress, {$e['session']}<br/>
Recorded: {$recording_date}<br/>
Approved: {$approval_date}
</div>
EOH;
        $journal_data[$n]['metadata']['short_session'] = preg_replace(array(
            '@(first)[ ]*@i',
            '@(second)[ ]*@i',
            '@(third)[ ]*@i',
            '@(fourth)[ ]*@i',
            '@(fifth)[ ]*@i',
            '@([0-9]?)((R|S).*)(.*)(session)@i', 
          ),
          array(
            '1',
            '2',
            '3',
            '4',
            '5',
            '$1$3',
          ),
          $e['session']
        );
        $date = DateTime::createFromFormat('F d, Y H:i:s', "{$e['approved']} 00:00:00");
        if ( !(FALSE === $date) ) {
          $journal_data[$n]['metadata']['approved'] = $e['approved'];
          $journal_data[$n]['metadata']['approved_utx'] = $date->getTimestamp();
          $journal_data[$n]['metadata']['approved_dtm'] = $date->format(DateTime::ISO8601); 
        }
        $date = DateTime::createFromFormat('F d, Y H:i:s', "{$e['date']} 00:00:00");
        if ( !(FALSE === $date) ) {
          $journal_data[$n]['metadata']['reading'] = $e['date'];
          $journal_data[$n]['metadata']['reading_utx'] = $date->getTimestamp();
          $journal_data[$n]['metadata']['reading_dtm'] = $date->format(DateTime::ISO8601); 
        }

        continue;
      }/*}}}*/

      // Add R1, R2, R3, CR, and HEAD tags to the array
      $match = array();
      $tag = preg_replace(
        array(
          '@(.*)first reading@i',
          '@(.*)second reading(.*)@i',
          '@(.*)third reading(.*)@i',
          '@(.*)committee report(.*)@i',
          '@(.*)journal([^0-9]*)([0-9]*)(.*)@i',
        ),
        array(
          'R1',
          'R2',
          'R3',
          'CR',
          'HEAD-$3',
        ),
        $e['section']
      );

      if ( 1 == preg_match('@^(R1|R2|R3|CR|(HEAD)-([0-9]*))@i', $tag, $match) ) {/*{{{*/// Match R1-3, or CR
        if ( 2 == count($match) )
          $journal_data[$n]['tag'] = $tag; 
        else {
          // Pattern for Journal entries returns four possible matches
          // $this->recursive_dump($match,"(marker) {$tag}"); 
          $sn = $match[3];
          $tag = $match[2];
          $journal_data[$n]['tag'] = $tag; 
          $journal_data[$n]['sn'] = $sn; 
        }
      }/*}}}*/

      if ( intval($n) == 0 ) {/*{{{*/// Journal page descriptor (Title, publication date)
        if (is_array(nonempty_array_element($e,'content'))) foreach ($e['content'] as $entry) {
          if ( array_key_exists('url',$entry) ) {/*{{{*/
						$urlhash = UrlModel::get_url_hash(array_element($entry,'url'));
						$properties = array('legiscope-remote', "{urlstate-{$urlhash}}","journal-pdf");
						$properties = join(' ', $properties);
						$test_urls[] = array("{urlstate-{$urlhash}}" => array('url' => $entry['url'], 'hash' => $urlhash));
						$entry['urlhash'] = $urlhash;
            $journal_data[$n]['pdf'] = $entry;
            $pagecontent .= <<<EOH
<b>{$e['section']}</b>  (<a id="{$urlhash}" class="{$properties}" href="{$entry['url']}">PDF</a>)<br/>
Recorded: {recording_date}<br/>
Approved: {approval_date}<br/>
EOH;
            continue;
          }/*}}}*/
          if ( !(FALSE == strtotime($entry['text']) ) ) {/*{{{*/
            $journal_data[$n]['published'] = $entry['text'];
            $date = DateTime::createFromFormat('m/d/Y H:i:s', "{$entry['text']} 00:00:00");
            $journal_data[$n]['published_utx'] = $date->getTimestamp(); 
            $journal_data[$n]['published_dtm'] = $date->format(DateTime::ISO8601); 
            $pagecontent .= <<<EOH
Published {$entry['text']}<br/><br/>
EOH;
            continue;
          }/*}}}*/
        }
        continue;
      } /*}}}*/
      $pagecontent .= <<<EOH
<br/>
<b>{$e['section']}</b>
<br/>
EOH;

      $lines = array();
      $sorttype = NULL;
      if ( is_array($e) && array_key_exists('content',$e) && is_array($e['content']) ) {/*{{{*/// Sort by suffix
        // Pass 1: Obtain list of uncached URLs 
        // Pass 2:  Generate markup and update Journal element entries (serial number and prefix [SBN, SRN, etc.])
        foreach ($e['content'] as $content_idx => $entry) {/*{{{*/// Iterate through the list
          $matches = array();
          $title = $entry['text'];
          // Split these patterns:
          // SBN-3928: Description
          // No. 123 - Description
          $pattern = '@^([^:]*)(:| - )(.*)@i';
          preg_match($pattern, $title, $matches);
          $title = $matches[1];
          $desc  = $matches[3];
					$urlhash = UrlModel::get_url_hash($entry['url']);
					$properties = array('legiscope-remote', "{urlstate-{$urlhash}}");
					$properties = join(' ', $properties);
					$test_urls[] = array("{urlstate-{$urlhash}}" => array('url' => $entry['url'], 'hash' => $urlhash));
          $sortkey = preg_replace('@[^0-9]@','',$title);

          $journal_data[$n]['content'][$content_idx]['sn'] = $title;
          $journal_data[$n]['content'][$content_idx]['desc'] = $desc;
          $journal_data[$n]['content'][$content_idx]['sortkey'] = $sortkey;
          $journal_data[$n]['content'][$content_idx]['prefix'] = preg_replace("@(-{$sortkey})$@",'',$title);

          if ( is_null($sorttype) ) $sorttype = 0 < intval($sortkey) ? SORT_NUMERIC : SORT_REGULAR;
          $sortkey = 0 < intval($sortkey) ? intval($sortkey) : $title;
          $lines[$sortkey] = <<<EOH
<li><a id="{$urlhash}" class="{$properties}" href="{$entry['url']}">{$title}</a>: {$desc}</li>

EOH;
        }/*}}}*/
        ksort($lines,$sorttype);
      }/*}}}*/
      $lines = join(' ',$lines);

      // Each cluster of links should be capable of triggering content pull
      $pagecontent .= <<<EOH
<ul class="link-cluster">{$lines}</ul>
EOH;

    }/*}}}*/// END iteration through sections

		$pagecontent = str_replace(
			array("{approval_date}","{recording_date}"),
			array("{$approval_date}","{$recording_date}"),
			$pagecontent
		);

		// Iterate through test_urls[] to obtain caching state for all URLs in batches

		if ( $debug_method ) $this->syslog(__FUNCTION__,__LINE__,"(marker) - - - - - - - - - - - - Testing " . count($test_urls) . " for caching state");
		if ( $debug_method ) $this->recursive_dump($test_urls,'(marker) T - end');
    $test_url = new UrlModel();
		while ( 0 < count($test_urls) ) {
			$cached = array();
			$uncached = array();
			while ( 10 > count($cached) && 0 < count($test_urls) ) {
				list($matchpattern, $url) = each(array_pop($test_urls));
				$urlhash = $url['hash'];
				$url = $url['url'];
				$cached[$urlhash] = NULL;
				$uncached[$url] = $matchpattern;
			}
			$test_url->where(array('AND' => array('urlhash' => array_keys($cached))))->recordfetch_setup();
			while ( $test_url->recordfetch($url) ) {
				$cached[$url['urlhash']] = $uncached[$url['url']]; 
				unset($uncached[$url['url']]);
			}
			$cached = array_filter($cached); // Contains entries that are in DB 
			if ( $debug_method ) {
				$this->recursive_dump($cached  , '(marker) - - - - - -   Cached');
				$this->recursive_dump($uncached, '(marker) - - - - - - Uncached');
			}
			$pagecontent = str_replace(array_values($cached),'cached', $pagecontent);
			$pagecontent = str_replace(array_values($uncached), 'uncached', $pagecontent);
		}

		// Append script to fetch missing PDFs
		if ( C('LEGISCOPE_JOURNAL_PDF_AUTOFETCH') ) $pagecontent .= $this->jquery_seek_missing_journal_pdf();

    if ($debug_method) $this->recursive_dump($journal_data,'(marker) B - end');

    return $pagecontent;
  }/*}}}*/

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
      'seq' => array_element(array_element($this->current_tag,"attrs",array()),"seq"),
    );
    if ( array_element(array_element($this->current_tag,"attrs",array()),"ID") == 'content' ) {
      $paragraph['metadata'] = TRUE;
      $matches = array();
      $pattern = '@^(.*) Congress - (.*)(Date:(.*))\[BR\](.*)Approved on(.*)\[BR\]@im'; 
      preg_match( $pattern, array_element($paragraph,'text'), $matches );
      if ( is_null(array_element($matches,2)) ) {
        $matches = array();
        $pattern = '@^(.*) Congress(.*)\[BR\](.*)Committee Report No. ([0-9]*) Filed on (.*)(\[BR\])*@im'; 
        preg_match( $pattern, array_element($paragraph,'text'), $matches );
        array_push($this->activity_summary,array(
          'tag' => 'META',
          'source' => $paragraph['text'],
          'metadata' => array(
            'congress' => array_element($matches,1),
            'report'   => array_element($matches,4),
            'filed'    => trim(array_element($matches,5)),
            'n_filed'  => strtotime(trim(array_element($matches,5))),
          )));
      } else array_push($this->activity_summary,array(
        'tag' => 'META',
        'source' => $paragraph['text'],
        'metadata' => array(
          'congress'   => array_element($matches,1),
          'session'    => array_element($matches,2),
          'date'       => trim(array_element($matches,4)),
          'n_date'     => strtotime(trim(array_element($matches,4))),
          'approved'   => trim(array_element($matches,6)),
          'n_approved' => strtotime(trim(array_element($matches,6))),
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

  // For committee reports only - BLOCKQUOTE tags wrap text lines

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
    if ( array_key_exists( strtolower( array_element(array_element($this->current_tag,'attrs',array()),'CLASS') ), 
    array_flip(array('lis_ul','lis_download')) ) || (1 == count($this->activity_summary)) ) {
      if ( 0 < count($this->activity_summary) ) {
        $section = array_pop($this->activity_summary);
        $container_id_hash = array_element($this->current_tag,'CONTAINER');
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

  /** Higher-level page parsers **/

  function canonical_journal_page_parser(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/

    $debug_method   = FALSE;

    $report         = new SenateCommitteeReportDocumentModel();
    $journal        = new SenateJournalDocumentModel();
    $housebill      = new SenateHousebillDocumentModel();

    $hbilljournal   = new SenateHousebillSenateJournalJoin();
    $billjournal    = new SenateBillSenateJournalJoin();
    $bill           = new SenateBillDocumentModel();

    $urlmodel->ensure_custom_parse();

    if ( $debug_method ) $this->syslog( __FUNCTION__, __LINE__, "(marker) ----- ---- --- -- - - - Invoking parser.");
    $this->
      set_parent_url($urlmodel->get_url())->
      parse_html(
        $urlmodel->get_pagecontent(),
        $urlmodel->get_response_header()
      );

    $congress_number  = $urlmodel->get_query_element('congress');
    $congress_session = $urlmodel->get_query_element('session');

    if ( $debug_method ) $this->syslog( __FUNCTION__, __LINE__, "(marker) ----- ---- --- -- - - - Parsing activity summary.");
    $journal_data = array();
    $pagecontent = $this->parse_activity_summary($journal_data);

    // Store this Journal
    if ( $debug_method ) $this->syslog( __FUNCTION__, __LINE__, "(marker) ----- ---- --- -- - - - Store/fetch journal data.");
    $journal_id = $journal->store($journal_data, $urlmodel, $pagecontent);

    // Get reading date
    $journal_data_copy = $journal_data;
    $journal_meta = $this->filter_nested_array($journal_data_copy,
      'metadata[tag*=META]',0 // Return the zeroth element, there can only be one CR set 
    );
    $reading_date = $journal_meta['reading_dtm'];
    if ( $debug_method ) $this->recursive_dump($journal_meta,"(marker) -- Journal Meta Info --");

    $journal_entry_date = strtotime($journal_meta['date']); 
    if ( $debug_method ) $this->syslog( __FUNCTION__, __LINE__, "(marker) ----- ---- --- -- - - - Journal {$journal_entry_date} C. {$congress_number} Session {$congress_session}");

    $journal_data_copy = $journal_data;
    $journal_info = $this->filter_nested_array($journal_data_copy,
      '#[tag*=HEAD]',0 // Return the zeroth element, there can only be one CR set 
    );
    if ( $debug_method ) $this->recursive_dump($journal_info,"(marker) -- Journal Info --");

    // Extract committee report list from journal entry data
    $this->debug_operators = FALSE;
    $journal_data_copy = $journal_data;
    $committee_reports = $this->filter_nested_array($journal_data_copy,
      '#content[tag*=CR]',0 // Return the zeroth element, there can only be one CR set 
    );

    if ( !is_null($committee_reports) ) $report->store_uncached_reports($committee_reports, $journal_id, $debug_method);

    // Extract Bills, Resolutions and Committee Reports
    if ($debug_method) $this->recursive_dump($journal_data,'(marker) B - prefilter');
    $reading_state = array('R1','R2','R3');

    $this->debug_operators = FALSE;

    $self_join_propertyname = join('_', camelcase_to_array(str_replace('DocumentModel','',get_class($journal))));

    foreach ( $reading_state as $state ) {
      $journal_data_copy = $journal_data;
      // Extract Senate Bills.  Create Joins between the document and this journal.
      // If the reading state is one of R(1|2|3), store the reading date and reading state in the Join object. 
      $at_state = $this->filter_nested_array($journal_data_copy,
        "content[tag*={$state}]",0
      );
      if ( is_null($at_state) ) continue;
      // Determine unique prefixes (SBN, SRN, SJR, etc.)
      $distinct_prefixes = array();
      foreach ( $at_state as $entry ) {
        $distinct_prefixes[$entry['prefix']] = $entry['prefix'];
      }
      if ( $debug_method ) $this->recursive_dump($distinct_prefixes,"(marker) Prefixes");
      // Obtain list of joins between this Journal (journal_id) and each 
      // Senate document (bill, resolution, adopted resolutions, joint committee reports, etc.).
      // TODO:  Allow fetching of referenced attributes
      // TODO:  Allow specification of attributes to fetch 
      // TODO:  Parameterize fetch(), etc. to return JOIN records 
      
      $document_typelist = array(
        'SBN' => 'Bill',
        'SRN' => 'Resolution',
        'HBN' => 'Housebill',
        'SCR' => 'Concurrentres',
        'SJR' => 'Jointres',
      );
      $document_joins = array(
        'SBN' => 'SenateBillSenateJournalJoin',
        'SRN' => 'SenateJournalSenateResolutionJoin',
        'HBN' => 'SenateHousebillSenateJournalJoin',
        'SCR' => 'SenateConcurrentresSenateJournalJoin',
        'SJR' => 'SenateJointresSenateJournalJoin',
      );

      // Iterate through each distinct prefix that we know how to parse,
      // and commit nonexistent documents of that class to the database.
      foreach ( $distinct_prefixes as $prefix ) {
        $journal_data_copy = $journal_data;
        $at_state = $this->filter_nested_array($journal_data_copy,
          "#content[tag*={$state}]{#[prefix*={$prefix}]}",0
        );
        if ( is_null($at_state) ) continue;
        $at_state = array_values($at_state);

        $doctype  = array_element($document_typelist,$prefix);
        if ( is_null($doctype) ) continue;
        $document = "Senate{$doctype}DocumentModel";
        $document_join = $document_joins[$prefix];
        if ( is_null($document_join) || !class_exists($document_join) ) {
          continue;
        }
        $this->syslog( __FUNCTION__, __LINE__, "(marker) ----- ---- --- -- - - - {$prefix} Join {$document_join} Doc {$document}");
        if ( (0 < count($at_state)) || $debug_method ) {
          $this->syslog( __FUNCTION__, __LINE__, "(marker) ----- ---- --- -- - - - {$prefix} At reading state {$state}, C. {$congress_number} Session {$congress_session}");
          if ($debug_method) $this->recursive_dump($at_state,"(marker) {$state} {$prefix}");
        }
        $document              = new $document();
        $document_join         = new $document_join();
        $document_propertyname = join('_', camelcase_to_array(str_replace('DocumentModel','',get_class($document))));
        if ( $debug_method ) $this->recursive_dump($document_join->get_attrdefs(),"(marker) {$state} {$prefix} Join ATTRDEFs {$document_propertyname}");
        while ( 0 < count($at_state) ) {/*{{{*/
          // Construct SQL REGEXP operand string
          $n = 0;
          $journal_items = array();
          $sn_suffixes = array();
          // Take ten journal entries at a time
          while ( $n++ < 10 && 0 < count($at_state) ) {/*{{{*/
            $journal_item = array_pop($at_state);
            array_push($journal_items, $journal_item); 
            $sn_suffixes[$journal_item['sn']] = "{$journal_item['sortkey']}";
          } /*}}}*/

          if (!(0 < count($sn_suffixes))) continue;
          $sortkey = join('|',$sn_suffixes);
          $document->where(array('AND' => array(
            'sn' => "REGEXP '^{$prefix}-($sortkey)'",
            'congress_tag' => $congress_number,
          )))->recordfetch_setup();
          $billentry = array();
          // Null out suffixes, retaining keys
          if ( $debug_method ) $this->recursive_dump($sn_suffixes,"(marker) -- - Listed bills");
          if (0) array_walk($sn_suffixes,create_function(
            '& $a, $k', '$a = NULL;'
          ));
          // Replace sn_suffixes entries with Senate Bill DB record IDs 
          while ( $document->recordfetch($billentry) ) {/*{{{*/
            if ( array_key_exists($billentry['sn'], $sn_suffixes) ) {
              $sn_suffixes[$billentry['sn']] = $billentry['id'];
            }
            if ( $debug_method ) $this->syslog(__FUNCTION__, __LINE__, "(marker) -- - -- Bill {$billentry['sn']}.{$billentry['congress_tag']} = {$billentry['id']}" );
          }/*}}}*/
          // Filter nonexistent bills
          $sn_suffixes = array_filter($sn_suffixes);  
          // Retrieve Bill - Journal join records that already exist
          if (0 < count($sn_suffixes)) {/*{{{*/
            $query_params = array(
              $document_propertyname => array_values($sn_suffixes),
              $self_join_propertyname => $journal_id,
              'reading' => $state, 
            );
            if ( $debug_method ) $this->recursive_dump($query_params,"(marker) -- - - - - SEEK");
            $document_join->where(array('AND' => $query_params))->recordfetch_setup();
            $join = array();
            $sn_suffixes = array_flip($sn_suffixes); // { bill_id => sn }
            // Clear entries that exist
            if ( $debug_method ) $this->recursive_dump($sn_suffixes,"(marker) -- - Bills from markup");
            while ( $document_join->recordfetch($join) ) {
              if ( $debug_method ) $this->syslog(__FUNCTION__, __LINE__, "(marker) -- - -- Clearing {$join[$document_propertyname]}"); 
              if ( array_key_exists($join[$document_propertyname], $sn_suffixes) ) {
                $sn_suffixes[$join[$document_propertyname]] = NULL; 
              }
            } 
            // If no records remain (all joins accounted for), skip to next iter
            if ( $debug_method ) $this->syslog(__FUNCTION__, __LINE__, "(marker) -- + -- To store"); 
            if ( $debug_method ) $this->recursive_dump($sn_suffixes,'(marker) -- + --');
            $sn_suffixes = array_filter($sn_suffixes);
            if (!(0 < count($sn_suffixes))) continue;
            $sn_suffixes = array_values(array_flip($sn_suffixes));
            if ( $debug_method ) $this->recursive_dump($sn_suffixes,'(marker) -- + -- Filtered');
            $document_id_setter = "set_{$document_propertyname}";
            foreach ( $sn_suffixes as $bill_id ) {

              $document_join->fetch(array(
                $document_propertyname => $bill_id,
                $self_join_propertyname => $journal_id,
                'reading' => $state, 
              ), 'AND');
              $document_join_id = $document_join->
                $document_id_setter($bill_id)->
                set_senate_journal($journal_id)->
                set_reading($state)->
                set_reading_date($reading_date)->
                set_congress_tag($congress_number)->
                stow();
              $this->syslog(__FUNCTION__, __LINE__, "(marker) -- + -- Stored link #{$document_join_id} [J {$journal_id} -> $document_propertyname {$bill_id}]"); 
            }
          }/*}}}*/
        }/*}}}*/
      }
    }

    $parser->json_reply['retainoriginal'] = TRUE;
    $parser->json_reply['subcontent'] = $pagecontent;
    $pagecontent = NULL;

  }/*}}}*/

}
