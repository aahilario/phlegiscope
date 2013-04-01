<?php

class GovPh extends LegiscopeBase {
  
  function __construct() {
    $this->syslog( __FUNCTION__, '-', 'Using site-specific container class' );
    parent::__construct();
  }

  function seek() {
    $cache_force = $this->filter_post('cache');
    $json_reply  = parent::seek();
    $response    = json_encode($json_reply);
    header('Content-Type: application/json');
    header('Content-Length: ' . strlen($response));
    $this->flush_output_buffer();
    if ( C('ENABLE_GENERATED_CONTENT_BUFFERING') || ($cache_force == 'true') ) {
      file_put_contents($this->seek_cache_filename, $response);
    }
    echo $response;
    exit(0);
  }

  function common_unhandled_page_parser(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/

    if ( 1 == preg_match('@(republic-act-no-)@', $urlmodel->get_url()) ) {
      return $this->parse_republic_act($parser,$pagecontent,$urlmodel);
    }

    $this->syslog( __FUNCTION__, 'FORCE', "Invoked for " . $urlmodel->get_url() );

    $gazette = new GazetteCommonParseUtility();
    $gazette->set_parent_url($urlmodel->get_url())->parse_html($pagecontent);

    // $this->mark_container_sequence(); // Not needed for GazetteCommonParseUtility::parse_html()
    $pagecontent = join('',$gazette->get_filtered_doc());

    // $this->recursive_dump($gazette->get_containers(),0,'FORCE');


    // $this->recursive_dump($entry_content,0,'FORCE');
  }/*}}}*/

  function seek_by_pathfragment_6a91f58596d1557b32b1abdf5169b08c(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    // http://www.gov.ph/section/legis/page/*
    $this->syslog( __FUNCTION__, 'FORCE', "Invoked for " . $urlmodel->get_url() );

    $gazette = new GazetteCommonParseUtility();
    $gazette->set_parent_url($urlmodel->get_url())->parse_html($pagecontent);
    $test_url = new UrlModel();

    if ( is_null( $urlmodel->get_pagecontent() ) ) {
      $pagecontent = join('',$gazette->get_filtered_doc());
      $urlmodel->set_pagecontent($pagecontent)->stow();
    }
    $pagecontent = ''; // $pagecontent = join('',$gazette->get_filtered_doc());

    // $this->syslog(__FUNCTION__,'FORCE',"++ Pager");
    $this->recursive_dump($pagerlinks = $gazette->get_containers(
      'children[tagname=div][class*=nav-previous|nav-next|i]'
    ),0,"++ Pager");

    // $this->syslog(__FUNCTION__,'FORCE',"++ All containers");
    $this->recursive_dump($containers = $gazette->get_containers(
      'children[tagname=div][class*=category-legis|category-senate|category-republic-acts|nav-previous|nav-next|i]'
    ),0,"++ All containers");

    $substitute_content = '';
    $entries_found = 0;

    $found_links = array();
    foreach ( $containers as $c => $vp ) {/*{{{*/
      list($seq, $v) = each($vp);
      if ( array_key_exists('url', $v) && array_key_exists('text', $v) ) {/*{{{*/

        $v['url'] = rtrim($v['url'],'/') . '/';

        if ( array_key_exists($v['url'],$found_links) ) continue;
        $found_links[$v['url']] = $v;

        $link_hash = UrlModel::get_url_hash($v['url']);
        $test_url->fetch($link_hash, 'urlhash');
        $link_properties = array('legiscope-remote');
        if ( $test_url->in_database() && ($v['text'] == $test_url->get_linktext()) ) {
          $linktext = $test_url->get_linktext();
          $entries_found++;
          // Remove the link from the list of fetch targets (in favor of the global list),
          // unless it is one of the pager links.
					// DEBUG
          // $this->syslog(__FUNCTION__,'FORCE',"-- Existing entry: {$v['url']} ({$v['text']} vs {$linktext})");
          if ( !array_key_exists($c, $pagerlinks) ) {
            unset($found_links[$v['url']]);
            continue;
          }  
        } else {
          $this->syslog(__FUNCTION__,'FORCE',"!! Missing entry: {$v['url']} ({$v['text']})");
          $found_links[$v['url']]['missing'] = $c;
          $link_properties[] = 'uncached';
        }
        $link_properties = join(' ', $link_properties);
        $substitute_content .= <<<EOH
<li><a id="{$link_hash}" class="{$link_properties}" href="{$v['url']}">{$v['text']}</a></li>
EOH;
      }/*}}}*/
    }/*}}}*/

    $this->syslog(__FUNCTION__,'FORCE',"++ Matches found on this page: {$entries_found}");
    // $this->recursive_dump($found_links,0,'FORCE');

    if ( !($entries_found > 2) ) {/*{{{*/

      $this->recursive_dump($containers = $gazette->get_containers(
        //'children[tagname=div][class*=entry-content]{[text]}'
        //'children[tagname=div][class*=category-legis|category-republic-acts|i]'
      ),0,'FORCE');

      $this->recursive_dump($approval_date = $gazette->get_containers(
        'children[tagname=div][class*=entry-content]{[text*=Approved(:?)|i]}'
      ),0,'FORCE');

      $this->recursive_dump($ra_number = $gazette->get_containers(
        'children[tagname=div][class*=entry-content]{[text*=REPUBLIC ACT NO|i]}'
      ),0,'FORCE');

      $this->recursive_dump($act_title = $gazette->get_containers(
        'children[tagname=div][class*=entry-content]{[text*=AN ACT ]}'
      ),0,'FORCE');

    }/*}}}*/

    $gazette->debug_operators = FALSE;

    // Individual, already cached Republic Act announcements
    $pattern = '(http://www.gov.ph/(.*)/republic-act-no(.*)([/]*))';
    $matches = $test_url->count(array('url' => "REGEXP '{$pattern}'"));
    $this->syslog( __FUNCTION__, 'FORCE', "- Available pages: {$matches}");
    $record = array();
    $link_properties = array('human-element-dossier-trigger'/* legiscope-remote*/);
    $link_properties = join(' ',$link_properties);
    $test_url->where(array('url' => "REGEXP '{$pattern}'"))->recordfetch_setup();
    $republic_act_entries = array();
    while ( $test_url->recordfetch($record) ) {/*{{{*/
      $v = array(
        'url' => rtrim($record['url'],'/') . '/',
        'text' => $record['urltext'],
        'urlhash' => $record['urlhash'],
      );
      $matches = array();
      preg_match_all("@{$pattern}@", $v['url'], $matches);
      $suffix = trim($matches[3][0],'-/');
      $republic_act_entries["{$suffix}"] = <<<EOH
<li><a id="{$v['urlhash']}" class="{$link_properties}" href="{$v['url']}">{$v['text']}</a></li>
EOH;
    }/*}}}*/
    krsort($republic_act_entries);
    $substitute_content .= join('', $republic_act_entries);
    $republic_act_entries = NULL;

    $pattern = '(http://www.gov.ph/section/legis/page/(.*))';
    $matches = $test_url->count(array('url' => "REGEXP '{$pattern}'"));
    $this->syslog( __FUNCTION__, 'FORCE', "------ Tracked RA announcement pages: {$matches}");
    $test_url->where(array('url' => "REGEXP '{$pattern}'"))->recordfetch_setup();
    $record = array();
    $maximum_page_number = 2; // If the cache is empty, our pages start here
    $distinct_pages = array();
    $distinct_links = array();
    $gazette->debug_operators = FALSE;
    while ( $test_url->recordfetch($record,TRUE) ) {/*{{{*/
      $gaz = new GazetteCommonParseUtility();
      $matches = array();
      $url = preg_match("@{$pattern}@",$record['url'],$matches);
      // $this->syslog( __FUNCTION__, 'FORCE', "------ {$matches[2]}");
      // $this->recursive_dump($matches,0,'FORCE');
      $matches = intval(trim($matches[2],'/'));
      $new_url = trim(str_replace('(.*)', $matches, $pattern),'()');
      $distinct_pages[$matches] = $new_url;
      // $this->syslog( __FUNCTION__, 'FORCE', "------ {$new_url}");
      if ( $matches > $maximum_page_number ) $maximum_page_number = $matches;

      if (0) {/*{{{*/

        // Parse the markup of each of these pages to obtain [category-legis] links

        $url = $test_url->get_url();
        $markup = 1 == preg_match('@(utf)@i',$test_url->get_content_type()) 
          ? $test_url->get_pagecontent()
          : utf8_decode($test_url->get_pagecontent())
          ;

        $gaz->set_parent_url('http://www.gov.ph/');

        if ( is_null($gaz->parse_html($markup)) ) {

          if (1) $this->syslog(__FUNCTION__,'FORCE',"-----------------------" );
          if (1) $this->syslog(__FUNCTION__,'FORCE',"Nothing to process from {$url}. Method parse_html() did not return a content structure array. Content length = " . strlen($markup) . " " . substr($markup,0,500) );
          if (1) $this->recursive_dump($record,0,'FORCE');

        } else {
          $links = $gaz->get_containers(
            'children[tagname=div][class*=category-legis|category-senate|category-republic-acts|nav-previous|nav-next|i]'
          );

          if (0) $this->syslog(__FUNCTION__,'FORCE',"-----------------------" );
          if (0) $this->recursive_dump($record,0,'FORCE');

          $record['url'] = $test_url->get_url();

          $this->syslog(__FUNCTION__,'FORCE',
            "Parsed {$record['url']} for links. Found " . count($links) . '/' . count($gaz->get_containers()) . 
            " with content in " . $test_url->get_cache_filename() . 
            " of length " . strlen($markup) );

          array_walk($links,
            create_function('&$a, $k', '$a = array_values($a); $a = $a[0]; $a["hash"] = UrlModel::get_url_hash($a["url"]);'));

          foreach ( $links as $link ) $distinct_links[$link['hash']] = $link;
        }
      }/*}}}*/

    }/*}}}*/

    array_walk($distinct_links,
      create_function('&$a, $k, $s', '$h = UrlModel::get_url_hash($a["url"]); $a["text"] = $s->get_linktext();'),
      $test_url);

    if (0) $this->syslog(__FUNCTION__,'FORCE',"-----------------------" );
    if (0) $this->recursive_dump($distinct_pages,0,'FORCE');

    ksort($distinct_pages);
    $this->syslog( __FUNCTION__, 'FORCE', "------ Furthest page reached: {$maximum_page_number}");
    $this->syslog( __FUNCTION__, 'FORCE', "------ Earliest page: {$distinct_pages[$maximum_page_number]}");

    $link_hash = UrlModel::get_url_hash($distinct_pages[$maximum_page_number]);
    $substitute_content = <<<EOH
<li><a id="{$link_hash}" class="{$link_properties}" href="{$distinct_pages[$maximum_page_number]}">Oldest Announcement</a></li>
{$substitute_content}
EOH;
    
    // Individual Republic Act announcements (including pager)
    $pagecontent = <<<EOH
<div class="congresista-dossier-list">
  <div class="float-left link-cluster"><ul id="gazetteer-links" class="link-cluster">{$substitute_content}</ul></div>
  <div class="float-left link-cluster"></div>
</div>
<script type="text/javascript">
$(function(){
  initialize_dossier_triggers();
  initialize_linkset_clickevents($('ul[id=gazetteer-links]'),'li');
});
</script>
<div id="human-element-dossier-container" class="alternate-original half-container"></div>
EOH;

    $this->syslog( __FUNCTION__, 'FORCE', "- Finally done.");

  }/*}}}*/

  function parse_republic_act(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/

    $this->syslog( __FUNCTION__, 'FORCE', "Invoked for " . $urlmodel->get_url() );

    $gazette = new GazetteCommonParseUtility();
    $gazette->set_parent_url($urlmodel->get_url())->parse_html($pagecontent);

    $link = new UrlModel();
    // $link->dump_accessor_defs_to_syslog();

    $pagecontent = join('',$gazette->get_filtered_doc());

    if ( is_null( $urlmodel->get_pagecontent() ) ) {
      $urlmodel->set_pagecontent($pagecontent)->stow();
    }

    // Extract body of Republic Act document

    $gazette->debug_operators = FALSE;
    $containers = $gazette->get_containers(
      'children[tagname=div][class*=entry-content]{[text]}'
    );

    $containers = array_values($containers);
    $containers = $containers[0];

    if ( is_array($containers) )
      array_walk(
        $containers,
        create_function('& $a, $k, & $s', '$a["text"] = html_entity_decode($a["text"]);'),
        $gazette
      );
    else {

      $this->syslog(__FUNCTION__,'FORCE', "!! Unparseable {$urlmodel}. Leaving.");
      return;

    }
    $gazette->debug_operators = FALSE;

    // Extract R.A. serial number

    $this->recursive_dump($ra_number = $gazette->get_containers(
      'children[tagname=div][class*=entry-content]{[text*=REPUBLIC ACT NO|i]}'
    ),0,__LINE__);

    $ra_number = $gazette->reduce_containers_to_string($ra_number);

    if ( is_null($ra_number) || !($ra_number = preg_replace('@((\[)*([^0-9]*)([0-9]*)([^]]*)(\])*)@','$4',$ra_number)) ) {

      $this->syslog(__FUNCTION__,'FORCE', "Unable to obtain RA number from content. Skipping");
      return;

    }

    $ra_number = "RA" . str_pad($ra_number,5,'0',STR_PAD_LEFT);

    $this->syslog(__FUNCTION__,'FORCE', "RA S.N. is {$ra_number}");

    if ( strlen($ra_number) > 7 ) {
      $this->syslog(__FUNCTION__,'FORCE', "Ill-formed R.A. number. Skipping");
      return;
    }

    $republic_act = new RepublicActDocumentModel();
    // $republic_act->dump_accessor_defs_to_syslog();

    $republic_act->fetch($ra_number,'sn');

    if ( $republic_act->in_database() && !is_null($republic_act->get_content_json()) ) {

      $this->syslog(__FUNCTION__,'FORCE', "{$ra_number} already stowed. Leaving.");
      return;

    }

    $this->recursive_dump($approval_date = $gazette->get_containers(
      'children[tagname=div][class*=entry-content]{[text*=Approved:|i]}'
    ),0,__LINE__);

    $this->recursive_dump($act_title = $gazette->get_containers(
      'children[tagname=div][class*=entry-content]{[text*=AN ACT ]}'
    ),0,__LINE__);

    if ( !(1 == count($act_title)) ) {

      $this->syslog(__FUNCTION__,'FORCE', "{$ra_number} multiline title.  Double check content.");

    }

    $approval_date = $gazette->reduce_containers_to_string($approval_date);
    $act_title = $gazette->reduce_containers_to_string($act_title);

    $this->recursive_dump($containers,0,'FORCE');

    $republic_act->
      set_content(json_encode($containers))->
      set_content_json(json_encode($containers))->
      set_sn($ra_number)->
      set_description($act_title)->
      set_last_fetch(time())->
      set_url($urlmodel->get_url())->
      set_searchable(TRUE)->
      set_approval_date($approval_date)->
      stow();

  }/*}}}*/

  function generic(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    // 
    $this->syslog( __FUNCTION__, 'FORCE', "Invoked for " . $urlmodel->get_url() );
    $this->common_unhandled_page_parser($parser,$pagecontent,$urlmodel);
  }/*}}}*/

}
