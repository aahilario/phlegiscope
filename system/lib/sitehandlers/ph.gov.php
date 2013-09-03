<?php

class GovPh extends SeekAction {
  
  function __construct() {
    $this->syslog( __FUNCTION__, '-', 'Using site-specific container class' );
    parent::__construct();
  }

  function common_unhandled_page_parser(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/

    $parser->json_reply = array('retainoriginal' => TRUE);

    if ( 1 == preg_match('@((republic-)*act-no-)@i', $urlmodel->get_url()) ) {
      $parser->enable_linkset_update = TRUE;
      return $this->parse_republic_act($parser,$pagecontent,$urlmodel);
    }

    $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked for " . $urlmodel->get_url() );

    $gazette = new GazetteCommonParseUtility();
    $gazette->
      set_parent_url($urlmodel->get_url())->
      parse_html(
        $urlmodel->get_pagecontent(),
        $urlmodel->get_response_header()
      );

    // $pagecontent = $urlmodel->get_url(); // str_replace('[BR]','<br/>',join('',$gazette->get_filtered_doc()));
    $pagecontent = str_replace('[BR]','<br/>',join('',$gazette->get_filtered_doc()));

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

    if ( FALSE && $debug_method ) $this->recursive_dump($gazette->articles_found,
      //( 'children[tagname=div][class*=entry-content]'  ),
      "(marker) -- Raw -- ");
    // Generate pager
    
    $pagerlinks = $gazette->navigation_links; // get_containers('children[tagname=div][class*=nav-previous|nav-next|i]{[url]}');
    array_walk($pagerlinks,create_function('& $a, $k', '$a = array_element(array_values($a),0);'));
    $pagerlinks = array_values($pagerlinks);

    if ( $debug_method ) $this->recursive_dump($pagerlinks,"(marker) ++ Pager");

    if ( $debug_method ) $this->recursive_dump($gazette->articles_found,"(marker) ++ Republic Act URLs");

    // Preload HB precursors 
    $hb_precursors = $gazette->articles_found;
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
          if ( array_key_exists($ra_url_index,$gazette->articles_found) ) $gazette->articles_found[$ra_url_index]['hb_precursors'] = array(
            'fkey' => $hb_id,
            'create_time' => time(),
          );
        }
      }
    }/*}}}*/

    // Preload Senate Bill precursors 
    $sb_precursors = $gazette->articles_found;
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
          if ( array_key_exists($ra_url_index,$gazette->articles_found) ) {
            $gazette->articles_found[$ra_url_index]['sb_precursors'] = array(
              'fkey' => $sb_id,
              'create_time' => time(),
            );
            if ( $debug_method ) { 
              $this->recursive_dump($gazette->articles_found[$ra_url_index]['sb_precursors'],"(marker) {$sn} -+-+-*-");
            }
          }
        }
      }
    }/*}}}*/

    // Extract a mapping tuple from the list of RAs in $gazette->articles_found.
    // This tuple [ sn, element := seq number ] maps DB records 
    // to the parsed data in $gazette->articles_found.  
    $ra_sns = array_map(create_function('$a', 'return array("sn" => $a["sn"]);'), $gazette->articles_found);
    array_walk($ra_sns,create_function('& $a, $k', '$a["element"] = $k;'));

    $republic_act = new RepublicActDocumentModel();

    if ( $debug_method ) {/*{{{*/
      $this->recursive_dump($republic_act->get_joins(),"(marker) --- J --- ");
    }/*}}}*/

    if ( !(0 < count($ra_sns) ) ) {
      $this->syslog(__FUNCTION__,__LINE__,"(warning) Cannot stow Republic Act / Act document.");
    } else {
      if ( $debug_method ) {
        $this->recursive_dump($ra_sns,"(marker) --- Ja --- ");
      }
    }

    while ( 0 < count($ra_sns) ) {/*{{{*/// Store the parsed document JSON blob
      $sn_set = array();
      while ( (count($sn_set) < 20) && (0 < count($ra_sns)) ) {
        $set = array_pop($ra_sns); 
        $sn_set[$set["sn"]] = $set["element"];
      }
      if ( $debug_method ) $this->recursive_dump($sn_set,"(marker) --");
      $republic_act->where(array('AND' => array(
        'sn' => array_keys($sn_set)
      )))->recordfetch_setup();
      $ra = array();
      while ( $republic_act->recordfetch($ra,TRUE) ) {/*{{{*/// Find existing R.A. records
        $id                       = $ra['id'];
        $ra_index                 = $sn_set[$ra['sn']];
        $gazette->articles_found[$ra_index]['id'] = $id;
        $parsed_content_hash      = md5($gazette->articles_found[$ra_index]['document']);
        $prestored_content_hash   = md5($ra['content']);
        if ( $debug_method ) {
          $this->syslog(__FUNCTION__,__LINE__,"(marker) -- {$ra['sn']} ID = {$gazette->articles_found[$ra_index]['id']}.");
          $this->syslog(__FUNCTION__,__LINE__,"(marker) -- {$ra['sn']} Parsed content hash = " . $parsed_content_hash   );
          $this->syslog(__FUNCTION__,__LINE__,"(marker) -- {$ra['sn']}  In DB content hash = " . $prestored_content_hash);
          $this->recursive_dump($gazette->articles_found[$ra_index],"(marker) --");
          $this->recursive_dump($ra,"(marker) ++");
        }
      }/*}}}*/
      foreach ( $sn_set as $sn => $index ) {/*{{{*/

        $data = array_element($gazette->articles_found,$index);
        $id   = array_element($data, 'id');

        if ( is_null($id) ) {/*{{{*/// Insert newly-parsed R.A. record 

          if ( 0 == intval($data['congress_tag']) ) {/*{{{*/
            if ( 1 == preg_match('@^RA([0-9]{4,})@',$sn) && C('SKIP_NEWLY_PARSED_RA_NO_CONGRESSTAG') ) {
              $this->syslog(__FUNCTION__,__LINE__,"(warning) -- Skipping newly-parsed, (assumed recent) Republic Act {$sn}.");
              $this->syslog(__FUNCTION__,__LINE__,"(warning) -- Check the source document page for changes to structure.");
              continue;
            }
            $this->syslog(__FUNCTION__,__LINE__,"(warning) -- No associated Congress tag for {$sn}. Will continue to store anyway.");
          }/*}}}*/

          $data = array_filter(array(
            'sn'            => $sn,
            'congress_tag'  => nonempty_array_element($data,'congress_tag','###'),
            'url'           => $data['url'],
            'content_json'  => '{}',
            'last_fetch'    => time(),
            'create_time'   => time(),
            'content'       => nonempty_array_element($data,'document','###'),
            'title'         => nonempty_array_element($data,'title',"Republic Act No. " . preg_replace('@[^0-9]@i','',$sn)),
            'description'   => nonempty_array_element($data,'description','###'),
            'sb_precursors' => array_element($data,'sb_precursor'),
            'hb_precursors' => array_element($data,'hb_precursor'),
          ));

          $id = $republic_act->
            set_id(NULL)->
            set_contents_from_array($data,TRUE)->
            fields(array_keys($data))->
            stow();
          if ( 0 < intval($id) ) {
            $gazette->articles_found[$index]['id'] = $id;
            $this->syslog(__FUNCTION__,__LINE__,"(marker) -- Stowed #{$id} {$sn}.{$data['congress_tag']}");
            $this->recursive_dump($data,"(marker) {$id}");
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
          do {/*{{{*/
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
          }/*}}}*/
          while ( $republic_act->recordfetch($ra) );

          // FIXME: Store different versions of content as Join edge payload
          if ( $debug_method ) {/*{{{*/
            $this->syslog(__FUNCTION__,__LINE__,"(marker) -- Reload from DB #{$ra['id']} {$sn}");
            $this->recursive_dump($ra,"(marker) --");
          }/*}}}*/

          $data = array_filter(array(
            'content'       => array_element($data,'document'),
            'title'         => nonempty_array_element($data,'title'),
            'description'   => nonempty_array_element($data,'description'),
            'sb_precursors' => array_element($data,'sb_precursors'),
            'hb_precursors' => array_element($data,'hb_precursors'),
          ));

          if ( is_array($data) && (0 < count($data)) ) {/*{{{*/
            $data['last_fetch'] = time();
            if ( $debug_method ) {
              $this->syslog(__FUNCTION__,__LINE__,"(marker) -- Updating {$sn}");
              $this->recursive_dump($data,"(marker) ++");
            }
            $id = $republic_act->
              set_contents_from_array($data,TRUE)->
              fields(array_keys($data))->
              stow();
            if ( $id != $gazette->articles_found[$index]['id'] ) {
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

    if ( is_array($gazette->articles_found) && (0 < count($gazette->articles_found)) ) {
      $gazette->articles_found = array_combine(
        array_map(create_function('$a', 'return $a["sn"];'), $gazette->articles_found),
        $gazette->articles_found
      );
      krsort($gazette->articles_found);
    }
    else if ($this->emit_full_frame) {

    }
    else {
      $failed_url = $urlmodel->get_url();
      $this->syslog(__FUNCTION__,__LINE__,"(marker) -!-!-!--------- Nothing parsed for {$failed_url} ------------");
      $failed_url_hash = UrlModel::get_url_hash($failed_url);
      $test_url = new UrlModel();
      $test_url->fetch($failed_url_hash,'urlhash');
      if ( !C('REMOVE_UNPARSED_GAZETTE_CONTENTURL') ) {
      } 
      else if ( $test_url->in_database() ) {
        $failed_url_id = $test_url->get_id();
        $this->syslog(__FUNCTION__,__LINE__,"(marker) -!-!-!--------- Removing {$failed_url} #{$failed_url_id} ------------");
        $test_url->remove();
      }
    }

    if ( $debug_method ) {/*{{{*/
      $this->recursive_dump($gazette->articles_found,"(marker) --- - --- ");
    }/*}}}*/

    $final_markup_source = array();
    foreach ( $gazette->articles_found as $e => $ra_parsed ) {/*{{{*/
      $link_properties = array('legiscope-remote');
      $link_properties[] = (is_null($ra_parsed['id']) || is_null($ra_parsed['document'])) ? 'uncached' : 'cached';
      $link_properties = join(' ', $link_properties);
      $link_hash = UrlModel::get_url_hash($ra_parsed['url']);
      $final_markup_source[$e]['markup'] = <<<EOH
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
      $links = array_map(create_function('$a','return $a["markup"];'),$final_markup_source);
      $links = join("\n",$links);
      $parser->linkset = preg_replace('@(human-element-dossier-trigger)@', 'legiscope-remote', <<<EOH
<ul class="link-cluster">
{$links}
</ul>
EOH
    );

    $test_url = new UrlModel();
    if (0) if ($this->emit_full_frame) {/*{{{*/
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
        if ( FALSE && array_key_exists("{$suffix}", $final_markup_source) ) {
          $this->syslog( __FUNCTION__, __LINE__, "(marker) ------ Omitting {$suffix}  {$record['url']}");
        } else {
          $link_properties = array('legiscope-remote');
          $link_properties = join(' ', $link_properties);
          $final_markup_source["{$suffix}"]['markup'] = $test_url->substitute(<<<EOH
<li><a id="{urlhash}" class="{$link_properties}" href="{url}">{urltext}</a></li>

EOH
          );
        }
      }/*}}}*/

      krsort($final_markup_source);

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
      array_push($final_markup_source, array('markup' => <<<EOH
<li><a id="{$link_hash}" class="{$link_properties}" href="{$pagerlink}">{$label}</a></li>

EOH
      ));
    }/*}}}*/

    if ( $debug_method )
    $this->recursive_dump($final_markup_source,"(marker) F --+++");
    $substitute_content = join("\n", array_map(create_function('$a', 'return $a["markup"];'),$final_markup_source)); 

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
  <ul id="gazetteer-links" class="link-cluster">{$substitute_content}</ul></div>
  <div class="float-left link-cluster"></div>
</div>
{$script_tail}
EOH
    : $markup_current
    ;

    $this->json_reply['subcontent'] = $pagecontent;
    if ( $this->emit_full_frame )
    $this->json_reply['retainoriginal'] = TRUE;
    else {
      $pagecontent = NULL;
    }
    $this->syslog( __FUNCTION__, __LINE__, "(marker) - Finally done.");

  }/*}}}*/

  function parse_constitution_sections(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
  }/*}}}*/

  function generate_markup(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    $ra_parser = new RepublicActParseUtility(); 
    $ra_parser->generate_descriptive_markup($parser, $pagecontent, $urlmodel);
    $ra_parser = NULL;
    $this->json_reply['subcontent'] = $pagecontent;
    unset($ra_parser);
  }/*}}}*/

  /** Automatically matched parsers **/

  function seek_postparse_f32aef41a732da19e7fa639e24513fce(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    // HOME PAGE OVERRIDDEN BY NODE GRAPH
    // http://www.gov.ph
    $this->generate_home_page($parser,$pagecontent,$urlmodel);
  }/*}}}*/

  function seek_by_pathfragment_6a91f58596d1557b32b1abdf5169b08c(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    // http://www.gov.ph/section/legis/page/*
    $this->emit_full_frame = TRUE;
    $parser->enable_linkset_update = TRUE;
    $this->parse_republic_act($parser, $pagecontent, $urlmodel);
    $this->generate_markup($parser, $pagecontent, $urlmodel);
  }/*}}}*/

  function seek_by_pathfragment_ca2f1712687a2479a1c896a85ed19f33(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    // http://www.gov.ph/section/legis/republic-acts/page/59/
    $this->emit_full_frame = TRUE;
    $parser->enable_linkset_update = TRUE;
    $this->parse_republic_act($parser, $pagecontent, $urlmodel);
  }/*}}}*/

  function seek_wpdate_03520896de584ce2cc1900c9100bb7e5(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    // http://www.gov.ph/1928/01/12/act-no-3430
    $parser->enable_linkset_update = TRUE;
    $this->parse_republic_act($parser, $pagecontent, $urlmodel);
    $this->generate_markup($parser, $pagecontent, $urlmodel);
  }/*}}}*/

}
