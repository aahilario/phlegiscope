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
		// if ( C('LEGISCOPE_JOURNAL_PDF_AUTOFETCH') )
		$pagecontent .= <<<EOH

<script type="text/javascript">

jQuery(document).ready(function(){
  jQuery('a[class*=journal-pdf]').each(function() {
		if ( jQuery(this).hasClass('uncached') ) {
			var self = jQuery(this);
      var linkurl = jQuery(this).attr('href');
			jQuery.ajax({
				type     : 'POST',
				url      : '/seek/',
				data     : { url : linkurl, update : jQuery('#update').prop('checked'), proxy : jQuery('#proxy').prop('checked'), modifier : jQuery('#seek').prop('checked'), fr: true },
				cache    : false,
				dataType : 'json',
				async    : false,
				beforeSend : (function() {
					display_wait_notification();
				}),
				complete : (function(jqueryXHR, textStatus) {
					remove_wait_notification();
				}),
				success  : (function(data, httpstatus, jqueryXHR) {
					jQuery(self).addClass('cached').removeClass('uncached');
					if ( data && data.original ) replace_contentof('original',data.original);
					if ( data && data.timedelta ) replace_contentof('time-delta', data.timedelta);
				})
			});
		}
  });
});

</script>
EOH;

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

}
