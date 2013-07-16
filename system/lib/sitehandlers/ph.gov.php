<?php

class GovPh extends SeekAction {
  
  function __construct() {
    $this->syslog( __FUNCTION__, '-', 'Using site-specific container class' );
    parent::__construct();
  }

  function common_unhandled_page_parser(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/

    $parser->json_reply = array('retainoriginal' => TRUE);

    if ( 1 == preg_match('@(republic-act-no-)@i', $urlmodel->get_url()) ) {
      return $this->parse_republic_act($parser,$pagecontent,$urlmodel);
    }

    $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked for " . $urlmodel->get_url() );

    $gazette = new GazetteCommonParseUtility();
    $gazette->set_parent_url($urlmodel->get_url())->parse_html($pagecontent,$urlmodel->get_response_header());

    $pagecontent = $urlmodel->get_url(); // str_replace('[BR]','<br/>',join('',$gazette->get_filtered_doc()));

  }/*}}}*/

  function seek_by_pathfragment_6a91f58596d1557b32b1abdf5169b08c(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/

    // http://www.gov.ph/section/legis/page/*
    $this->emit_full_frame = TRUE;
    $this->parse_republic_act($parser, $pagecontent, $urlmodel);

  }/*}}}*/

  function parse_republic_act(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/

    $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked for " . $urlmodel->get_url() );

    $debug_method = FALSE;

    $gazette = new GazetteCommonParseUtility();
    $gazette->
      set_parent_url($urlmodel->get_url())->
      parse_html(
        $urlmodel->get_pagecontent(),
        $urlmodel->get_response_header()
      );

    // Generate pager
    $pagerlinks = $gazette->get_containers('children[tagname=div][class*=nav-previous|nav-next|i]{[url]}');
    array_walk($pagerlinks,create_function('& $a, $k', '$a = array_element(array_values($a),0);'));
    $pagerlinks = array_values($pagerlinks);
    if ( $debug_method ) $this->recursive_dump($pagerlinks,"(marker) ++ Pager");

    // Extract R.A. URLs, retaining sequence numbers.
    // Result is a simple array of URLs, with structure 
    // Array ( <stream position> => <url>, ... )
    $ra_urls = $gazette->get_containers(
      'children[tagname=div][class*=entry-meta|i]{url[url*=.*|i]}'
    );
    array_walk($ra_urls,create_function(
      '& $a, $k, $s', '$a = array("ra_id" => NULL, "url" => array_element(array_keys(array_flip($a)),0)); $a["urlhash"] = UrlModel::get_url_hash($a["url"]); $a["document"] = NULL;'
    ),$this);

    if ( $debug_method ) $this->recursive_dump($ra_urls,"(marker) ++ Republic Act URLs");

    // This filter fetches entry-meta (URL) and entry-content (R.A. text) DIVs.
    // - entry-meta:  R.A. URL
    // - entry-content: R.A. text
    // - nav-previous: Previous page
    // - nav-next: Next page
    $containers = $gazette->get_containers(
      'class,children[tagname=div][class*=category-legis|category-senate|category-republic-acts|nav-previous|nav-next|entry-meta|entry-content|i]'
    );

    while ( 0 < count($containers) ) {/*{{{*/// Extract contents of each document, and stow the JSON-encoded body in $ra_urls['document'] 
      $div      = array_shift($containers);
      $class    = array_element($div,'class');
      $children = array_element($div,'children');
      switch ( $class ) {
        case 'entry-content':
          // Extract RA serial number from first 10 lines of the document
          $header = array_slice($children,0,10);
          $this->filter_nested_array($header,'text[text*=republic.*act|i]',0);
          // Hack for encoding gaffe ('O' for '0') 
          $header = array_element($header,0);
          $sn = preg_replace('@[^RA0-9]@i','',str_replace('O','0',preg_replace('@((.*)republic(.*)act([^0-9]*)([0-9O ]*)(.*))@i','RA$5', $header)));
          if ( $debug_method ) {
            $this->syslog(__FUNCTION__,__LINE__,"(marker) Hdr --- {$header}");
            $this->syslog(__FUNCTION__,__LINE__,"(marker)  SN --- {$sn}");
          }

          // Extract Congress number, Session Number, and SB and HB precursors 
          // (where applicable) from document routing [text] lines
          $header = array_slice($children,0,10);
          // $this->recursive_dump($header,"(marker) {$sn} -+-+-+--------");
          array_walk($header,create_function(
            '& $a, $k', '$a = (1 == preg_match("@(\[BR\])@i",array_element($a,"text"))) ? explode("[BR]",array_element($a,"text")) : array(array_element($a,"text"));'
          ));
          $header = array_values(array_filter($header));
          $header = array_values(array_merge(
            array_element($header,0,array()),
            array_element($header,1,array())
          ));
          // Remove unchanged entries
          array_walk($header,create_function(
            '& $a, $k, $s', '$r = preg_replace(array_keys($s),array_values($s),trim($a)); $a = (trim($r) == trim($a)) ? NULL : explode("|",$r);'
          ),
          $s = array(
            '@(s\.(.*)no\.([^0-9]*)([0-9]*))@i' => 'sb_precursor|$4',
            '@(h\.(.*)no\.([^0-9]*)([0-9]*))@i' => 'hb_precursor|$4',
            '@Third@i'    => '3',
            '@Second@i'   => '2',
            '@First@i'    => '1',
            '@Regular@i'  => 'R',
            '@Special@i'  => 'S',
            '@((.*) (R|S) Session)@i' => 'session|$2$3',
            '@Sixteen@i'   => '16',
            '@Fifteen@i'   => '15',
            '@Fourteen@i'  => '14',
            '@Thirteen@i'  => '13',
            '@Twel(v|f)@i' => '12',
            '@Eleven@i'    => '11',
            '@Ten@i'       => '10',
            '@Nin@i'       => '9',
            '@Eigh@i'      => '8',
            '@Seven@i'     => '7',
            '@Six@i'       => '6',
            '@Fif@i'       => '5',
            '@(([0-9]*)th Congress)@i' => 'congress_tag|$2',
          ));
          $header = array_values(array_filter($header));
          // Recreate the array with four possible keys: session, congress_tag, sb_precursor, and hb_precursor.
          if ( !is_array($header) || (0 == count($header)) ) break;
          $header = array_combine(
            array_map(create_function('$a', 'return $a[0];'), $header),
            array_map(create_function('$a', 'return $a[1];'), $header)
          );

          $header = array_filter($header);
          if ( $debug_method ) { 
            $this->syslog(__FUNCTION__,__LINE__,"(marker) {$sn} -+-+-+---------------------");
            $this->recursive_dump($header,"(marker) {$sn} -+-+-+--------");
          }

          if ( $debug_method ) $this->syslog(__FUNCTION__,__LINE__,"(marker) ++ Body contains " . count($children));
          if ( !is_null($match) && array_key_exists($match, $ra_urls) ) {
            $ra_urls[$match]['document'] = json_encode($children);
            $ra_urls[$match]['sn']       = $sn;
            // Merge metadata into array
            $ra_urls[$match] = array_merge($ra_urls[$match], $header);
          }
          break;
        case 'entry-meta':
          // Obtain the R.A. source URL (on these O.G. pages)
          $this->filter_nested_array($children,'url[url*=.*|i]',0);
          $url = array_element(array_keys(array_flip($children)),0);
          $match = array_combine(
            array_map(create_function('$a','return $a["urlhash"];'),$ra_urls),
            array_keys($ra_urls)
          );
          $match = array_element($match,UrlModel::get_url_hash($url));
          break;
      }
    }/*}}}*/

    // Preload HB precursors 
    $hb_precursors = $ra_urls;
    $this->filter_nested_array($hb_precursors,'hb_precursor,congress_tag[hb_precursor*=.*|i]');
    if ( 0 < count($hb_precursors) ) {/*{{{*/
      array_walk($hb_precursors,create_function('& $a, $k', '$a["index"] = $k;'));
        
      if ( $debug_method ) {
        $this->syslog(__FUNCTION__,__LINE__,"(marker) -+-+-+--------- HB ------------");
        $this->recursive_dump($hb_precursors,"(marker) -+-+-+--------");
      }

      $regexes = array_map(create_function('$a','return preg_replace("@[^0-9]@","",$a["hb_precursor"]);'), $hb_precursors);
      $regexes = join('|',$regexes);

      // Find all matching documents
      $hb_precursor = new HouseBillDocumentModel();
      $hb_precursor->where(array('AND' => array('sn' => "REGEXP '^HB([0]*)({$regexes})'")))->recordfetch_setup();
      $record = array();
      while ( $hb_precursor->recordfetch($record) ) {
        $hb_id           = $record['id'];
        $sn              = $record['sn'];
        $suffixmatch     = preg_replace("@HB([0]*)([0-9]*)@i","$2",$sn);
        $matched_records = $hb_precursors;
        $this->filter_nested_array($matched_records,'#[hb_precursor='.$suffixmatch.'][congress_tag='.$record['congress_tag'].']',0);
        if ( 1 == count($matched_records) ) {
          $matched_records = array_element($matched_records,0);
          $ra_url_index = array_element($matched_records,'index');
          if ( $debug_method ) { /*{{{*/
            $this->syslog(__FUNCTION__,__LINE__,"(marker) {$sn} -+-+-+--------- HB-{$matched_records['hb_precursor']} ------------");
            $this->recursive_dump($matched_records,"(marker) {$sn} -+-+-*-");
          }/*}}}*/
          if ( array_key_exists($ra_url_index,$ra_urls) ) $ra_urls[$ra_url_index]['hb_precursors'] = array(
            'fkey' => $hb_id,
            'create_time' => time(),
          );
        }
      }
    }/*}}}*/

    // Preload Senate Bill precursors 
    $sb_precursors = $ra_urls;
    $this->filter_nested_array($sb_precursors,'sb_precursor,congress_tag[sb_precursor*=.*|i]');
    if ( 0 < count($sb_precursors) ) {/*{{{*/
      array_walk($sb_precursors,create_function('& $a, $k', '$a["index"] = $k;'));
        
      if ( $debug_method ) {
        $this->syslog(__FUNCTION__,__LINE__,"(marker) -+-+-+--------- SB ------------");
        $this->recursive_dump($sb_precursors,"(marker) -+-+-+--------");
      }

      $regexes = array_map(create_function('$a','return preg_replace("@[^0-9]@","",$a["sb_precursor"]);'), $sb_precursors);
      $regexes = join('|',$regexes);

      // Find all matching documents
      $sb_precursor = new SenateBillDocumentModel();
      $sb_precursor->where(array('AND' => array('sn' => "REGEXP '(SBN\-({$regexes}))'")))->recordfetch_setup();
      $record = array();
      while ( $sb_precursor->recordfetch($record) ) {
        $sb_id           = $record['id'];
        $sn              = $record['sn'];
        $suffixmatch     = preg_replace("@SBN-([0-9]*)@i","$1",$sn);
        $matched_records = $sb_precursors;
        $this->filter_nested_array($matched_records,'#[sb_precursor='.$suffixmatch.'][congress_tag='.$record['congress_tag'].']',0);
        if ( 1 == count($matched_records) ) {
          $matched_records = array_element($matched_records,0);
          $ra_url_index = array_element($matched_records,'index');
          if ( $debug_method ) { 
            $this->syslog(__FUNCTION__,__LINE__,"(marker) {$sn} -+-+-+--------- SB-{$matched_records['sb_precursor']} ------------");
          }
          if ( array_key_exists($ra_url_index,$ra_urls) ) {
            $ra_urls[$ra_url_index]['sb_precursors'] = array(
              'fkey' => $sb_id,
              'create_time' => time(),
            );
            if ( $debug_method ) { 
              $this->recursive_dump($ra_urls[$ra_url_index]['sb_precursors'],"(marker) {$sn} -+-+-*-");
            }
          }
        }
      }
    }/*}}}*/

    // Extract a mapping tuple from the list of RAs in $ra_urls.
    // This tuple [ sn, element := seq number ] maps DB records 
    // to the parsed data in $ra_urls.  
    $ra_sns = array_map(create_function('$a', 'return array("sn" => $a["sn"]);'), $ra_urls);
    array_walk($ra_sns,create_function('& $a, $k', '$a["element"] = $k;'));

    $republic_act = new RepublicActDocumentModel();

    if ( $debug_method ) {/*{{{*/
      $this->recursive_dump($republic_act->get_joins(),"(marker) --- J --- ");
    }/*}}}*/

    while ( 0 < count($ra_sns) ) {/*{{{*/// Store the parsed document JSON blob
      $sn_set = array();
      while ( (count($sn_set) < 20) && (0 < count($ra_sns)) ) {
        $set = array_pop($ra_sns); 
        $sn_set[$set["sn"]] = $set["element"];
      }
      if ( $debug_method )
          $this->recursive_dump($sn_set,"(marker) --");
      $republic_act->where(array('AND' => array(
        'sn' => array_keys($sn_set)
      )))->recordfetch_setup();
      $ra = array();
      while ( $republic_act->recordfetch($ra,TRUE) ) {/*{{{*/// Find existing R.A. records
        $ra_id                       = $ra['id'];
        $ra_index                    = $sn_set[$ra['sn']];
        $ra_urls[$ra_index]['ra_id'] = $ra_id;
        $parsed_content_hash         = md5($ra_urls[$ra_index]['document']);
        $prestored_content_hash      = md5($ra['content']);
        if ( $debug_method ) {
          $this->syslog(__FUNCTION__,__LINE__,"(marker) -- {$ra['sn']} ID = {$ra_urls[$ra_index]['ra_id']}.");
          $this->syslog(__FUNCTION__,__LINE__,"(marker) -- {$ra['sn']} Parsed content hash = " . $parsed_content_hash   );
          $this->syslog(__FUNCTION__,__LINE__,"(marker) -- {$ra['sn']}  In DB content hash = " . $prestored_content_hash);
          $this->recursive_dump($ra_urls[$ra_index],"(marker) --");
          $this->recursive_dump($ra,"(marker) ++");
        }
      }/*}}}*/
      foreach ( $sn_set as $sn => $index ) {/*{{{*/

        $data = array_element($ra_urls,$index);
        $id   = array_element($data, 'ra_id');

        if ( is_null($id) ) {/*{{{*/// Insert newly-parsed R.A. record 
          if ( 0 == intval($data['congress_tag']) ) continue;
          $data = array_filter(array(
            'sn'           => $sn,
            'congress_tag' => $data['congress_tag'],
            'url'          => $data['url'],
            'last_fetch'   => time(),
            'create_time'  => time(),
            'content'      => $data['document'],
            'sb_precursors' => array_element($data,'sb_precursor'),
            'hb_precursors' => array_element($data,'hb_precursor'),
          ));
          $id = $republic_act->
            set_id(NULL)->
            set_contents_from_array($data,TRUE)->
            stow();
          if ( 0 < intval($id) ) {
            $ra_urls[$index]['ra_id'] = $id;
            $this->syslog(__FUNCTION__,__LINE__,"(marker) -- Stowed #{$id} {$sn}.{$data['congress_tag']}");
          } else {
            $this->syslog(__FUNCTION__,__LINE__,"(marker) -- {$sn} Failed to stow record.");
            $this->recursive_dump($data,"(marker) --");
          }
          continue;
        }/*}}}*/

        $republic_act->set_id(NULL)->join_all()->where(array('id' => $id))->recordfetch_setup();
        if ( $republic_act->recordfetch($ra,TRUE) && ($sn == $republic_act->get_sn()) ) {/*{{{*/// Update 'content' JSON-encoded plaintext document
          // FIXME: Move duplicate detection into DatabaseUtility
          // Find preexisting 
          do {
            $hb_precursors = array_element($ra,'hb_precursors');
            $hb_precursor  = array_element($hb_precursors,'data');
            if ( !is_null($hb_precursor = array_element($hb_precursor,'id')) ) {
              $precursor = array_element($data,'hb_precursors');
              if ( $hb_precursor == array_element($precursor,'fkey') ) {
                unset($data['hb_precursors']);
              }
            }
            $sb_precursors = array_element($ra,'sb_precursors');
            $sb_precursor  = array_element($sb_precursors,'data');
            if ( !is_null($sb_precursor = array_element($sb_precursor,'id')) ) {
              $precursor = array_element($data,'sb_precursors');
              if ( $sb_precursor == array_element($precursor,'fkey') ) {
                unset($data['sb_precursors']);
              }
            }
            if ( md5($ra['content']) == md5(array_element($data,'document')) ) {
              unset($data['document']);
            }
          } while ( $republic_act->recordfetch($ra) );

          // FIXME: Store different versions of content as Join edge payload
          if ( $debug_method ) {
            $this->syslog(__FUNCTION__,__LINE__,"(marker) -- Reload from DB #{$ra['id']} {$sn}");
            $this->recursive_dump($ra,"(marker) --");
          }

          $data = array_filter(array(
            'content'       => array_element($data,'document'),
            'sb_precursors' => array_element($data,'sb_precursors'),
            'hb_precursors' => array_element($data,'hb_precursors'),
          ));

          if ( is_array($data) && (0 < count($data)) ) {/*{{{*/
            $data['last_fetch'] = time();
            $this->syslog(__FUNCTION__,__LINE__,"(marker) -- Updating {$sn}");
            $this->recursive_dump($data,"(marker) ++");
            $id = $republic_act->
              set_contents_from_array($data,TRUE)->
              stow();
            if ( $id != $ra_urls[$index]['ra_id'] ) {
              $this->syslog(__FUNCTION__,__LINE__,"(marker) -- {$sn} Failed to update parsed content blob.");
            }
          }/*}}}*/

        }/*}}}*/

      }/*}}}*/
    }/*}}}*/

    $substitute_content = "";
    $markup_current = NULL;

    //////////////////////////////////////////////////////////////////////
    // Done with caching, now use the content to generate markup

		if ( is_array($ra_urls) && (0 < count($ra_urls)) ) {
			$ra_urls = array_combine(
				array_map(create_function('$a', 'return $a["sn"];'), $ra_urls),
				$ra_urls
			);
			krsort($ra_urls);
		} else if ($this->emit_full_frame) {
		} else {
			$failed_url = $urlmodel->get_url();
			$this->syslog(__FUNCTION__,__LINE__,"(marker) -!-!-!--------- Nothing parsed for {$failed_url} ------------");
			$failed_url_hash = UrlModel::get_url_hash($failed_url);
			$test_url = new UrlModel();
			$test_url->fetch($failed_url_hash,'urlhash');
			if ( $test_url->in_database() ) {
				$failed_url_id = $test_url->get_id();
				$this->syslog(__FUNCTION__,__LINE__,"(marker) -!-!-!--------- Removing {$failed_url} #{$failed_url_id} ------------");
			 	$test_url->remove();
			}
		}

    if ( $debug_method ) {/*{{{*/
      $this->recursive_dump($ra_urls,"(marker) --- - --- ");
    }/*}}}*/

    foreach ( $ra_urls as $e => $ra_parsed ) {/*{{{*/
      $link_properties = array('legiscope-remote');
      $link_properties[] = (is_null($ra_parsed['ra_id']) || is_null($ra_parsed['document'])) ? 'uncached' : 'cached';
      $link_properties = join(' ', $link_properties);
      $link_hash = UrlModel::get_url_hash($ra_parsed['url']);
      $ra_urls[$e]['markup'] = <<<EOH
<li><a title="{$e}" id="{$link_hash}" class="{$link_properties}" href="{$ra_parsed['url']}">{$ra_parsed['sn']}</a></li>
EOH;
      if ( is_null($markup_current) && !is_null($document = array_element($ra_parsed,'document'))) {
        $document = json_decode($document,TRUE);
        while ( 0 < count($document) ) {
          $line = array_shift($document);
          $line = str_replace('[BR]','<br/>', array_element($line,'text'));
          $markup_current .= <<<EOH
<p>{$line}</p>
EOH;
        }
        $document = NULL;
      }
    }/*}}}*/

    if ( isset($parser->enable_linkset_update) && $parser->enable_linkset_update )
    $parser->linkset = preg_replace('@(human-element-dossier-trigger)@', 'legiscope-remote', <<<EOH
<ul class="link-cluster">
{$substitute_content}
</ul>
EOH
    );
 
    
    $test_url = new UrlModel();
    if (FALSE && $this->emit_full_frame) {/*{{{*/
      // Individual, already cached Republic Act announcement URLs
      $pattern = '(http://www.gov.ph/(.*)/republic-act-no-([0-9]*)(.*)([/]*))';
			$this->syslog( __FUNCTION__, __LINE__, "(marker) ------ Loading URLs matching regex {$pattern}");
			$test_url->debug_final_sql = TRUE;
			$test_url->
				fields(array('id','url','urltext','urlhash'))->
				where(array('url' => "REGEXP '{$pattern}'"))->
				recordfetch_setup();

			$test_url->debug_final_sql = FALSE;

      while ( $test_url->recordfetch($record,TRUE) ) {/*{{{*/
        $v = array(
          'url' => rtrim($record['url'],'/') . '/',
          'text' => $record['urltext'],
          'urlhash' => $record['urlhash'],
        );
        $matches = array();
        preg_match_all("@{$pattern}@", $v['url'], $matches);
				// FIXME: Dodgy. Better to use the RA numeric suffixes as sort key.
        $suffix = preg_replace('@[^0-9]@i','',trim($matches[3][0],'-/'));
				$suffix = 'RA' . str_pad($suffix,5,'0',STR_PAD_LEFT);
				if ( array_key_exists("{$suffix}", $ra_urls) ) {
					$this->syslog( __FUNCTION__, __LINE__, "(marker) ------ Omitting {$suffix}  {$record['url']}");
				} else {
					$link_properties = array('legiscope-remote');
					$link_properties = join(' ', $link_properties);
					$ra_urls["{$suffix}"]['markup'] = $test_url->substitute(<<<EOH
<li><a id="{urlhash}" class="{$link_properties}" href="{url}">{urltext}</a></li>

EOH
					);
				}
      }/*}}}*/

			krsort($ra_urls);

    }/*}}}*/

		// Append pager tails
    $pager_label = array('Older', 'Newer');

    krsort($pagerlinks);

    foreach ( $pagerlinks as $n => $pagerlink ) {/*{{{*/
      $link_hash = UrlModel::get_url_hash($pagerlink);
      $label = array_element($pager_label,$n);
      $link_properties = array('legiscope-remote');
      if (!$n) $link_properties[] = 'uncached';
      $link_properties = join(' ', $link_properties);
      // Link position
      array_push($ra_urls, array('markup' => <<<EOH
<li><a id="{$link_hash}" class="{$link_properties}" href="{$pagerlink}">{$label}</a></li>

EOH
      ));
    }/*}}}*/

		$substitute_content = join("\n", array_map(create_function('$a', 'return $a["markup"];'),$ra_urls)); 

    if ( C('ENABLE_OG_PAGER_AUDIT') ) {/*{{{*/
      // Obtain O.G. pagers
      $record              = array();
      $maximum_page_number = 2; // If the cache is empty, our pages start here
      $distinct_pages      = array();
      $pattern             = '(http://www.gov.ph/section/legis/republic-acts/page/(.*))';

      $test_url->where(array('url' => "REGEXP '{$pattern}'"))->recordfetch_setup();

      while ( $test_url->recordfetch($record,TRUE) ) {/*{{{*/
        $matches = array();
        $url = preg_match("@{$pattern}@",$record['url'],$matches);
        $matches = intval(trim($matches[2],'/'));
        $new_url = trim(str_replace('(.*)', $matches, $pattern),'()');
        $distinct_pages[$matches] = $new_url;
        if ( $matches > $maximum_page_number ) $maximum_page_number = $matches;
      }/*}}}*/

      ksort($distinct_pages);
      $this->syslog( __FUNCTION__, __LINE__, "(marker) ------ Furthest page reached: {$maximum_page_number}");
      $this->syslog( __FUNCTION__, __LINE__, "(marker) ------ Earliest page: {$distinct_pages[$maximum_page_number]}");
      $this->recursive_dump($distinct_pages,"(marker) - -");

      $link_hash = UrlModel::get_url_hash($distinct_pages[$maximum_page_number]);
      $link_properties = 'legiscope-remote';
      $substitute_content = <<<EOH
<li><a id="{$link_hash}" class="{$link_properties}" href="{$distinct_pages[$maximum_page_number]}">Oldest Announcement</a></li>
{$substitute_content}
EOH;
      if ( isset($parser->enable_linkset_update) && $parser->enable_linkset_update )
      $parser->linkset = preg_replace('@(human-element-dossier-trigger)@', 'legiscope-remote', <<<EOH
<ul class="link-cluster">
{$substitute_content}
</ul>
EOH
      );
    }/*}}}*/
 
    // Individual Republic Act announcements (including pager)

    $script_tail = <<<EOJ
<script type="text/javascript">
jQuery(document).ready(function(){
  initialize_dossier_triggers();
  initialize_linkset_clickevents(jQuery('ul[id=gazetteer-links]'),'li');
});
</script>
EOJ;

    $pagecontent = (isset($this->emit_full_frame) && $this->emit_full_frame) 
     ? <<<EOH
<div class="congresista-dossier-list">
  <div class="float-left link-cluster"><ul id="gazetteer-links" class="link-cluster">{$substitute_content}</ul></div>
  <div class="float-left link-cluster"></div>
</div>
<div id="human-element-dossier-container" class="alternate-original half-container">{$markup_current}</div>
{$script_tail}
EOH
      : $markup_current
      ;

    $this->syslog( __FUNCTION__, __LINE__, "- Finally done.");

  }/*}}}*/

}
