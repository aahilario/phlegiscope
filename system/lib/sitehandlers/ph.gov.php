<?php

class GovPh extends SeekAction {
  
  function __construct() {
    $this->syslog( __FUNCTION__, '-', 'Using site-specific container class' );
    parent::__construct();
  }

  function common_unhandled_page_parser(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/

    if ( 1 == preg_match('@(republic-act-no-)@', $urlmodel->get_url()) ) {
      return $this->parse_republic_act($parser,$pagecontent,$urlmodel);
    }

    $this->syslog( __FUNCTION__, __LINE__, "Invoked for " . $urlmodel->get_url() );

    $gazette = new GazetteCommonParseUtility();
    $gazette->set_parent_url($urlmodel->get_url())->parse_html($pagecontent,$urlmodel->get_response_header());

    // $this->mark_container_sequence(); // Not needed for GazetteCommonParseUtility::parse_html()
    $pagecontent = str_replace('[BR]','<br/>',join('',$gazette->get_filtered_doc()));

  }/*}}}*/

  function seek_by_pathfragment_6a91f58596d1557b32b1abdf5169b08c(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    // http://www.gov.ph/section/legis/page/*
    $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked for " . $urlmodel->get_url() );

    $gazette = new GazetteCommonParseUtility();
    $gazette->set_parent_url($urlmodel->get_url())->parse_html($pagecontent,$urlmodel->get_response_header());
    $test_url = new UrlModel();

    if ( is_null( $urlmodel->get_pagecontent() ) ) {
      $pagecontent = join('',$gazette->get_filtered_doc());
      $urlmodel->set_pagecontent($pagecontent);
      $urlmodel->stow();
    }
    $pagecontent = ''; // $pagecontent = join('',$gazette->get_filtered_doc());

    // $this->syslog(__FUNCTION__,__LINE__,"++ Pager");
    $this->recursive_dump($pagerlinks = $gazette->get_containers(
      'children[tagname=div][class*=nav-previous|nav-next|i]'
    ),"++ Pager");

    // $this->syslog(__FUNCTION__,__LINE__,"++ All containers");
    $this->recursive_dump($containers = $gazette->get_containers(
      'children[tagname=div][class*=category-legis|category-senate|category-republic-acts|nav-previous|nav-next|i]'
    ),"++ All containers");

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
          // $this->syslog(__FUNCTION__,__LINE__,"-- Existing entry: {$v['url']} ({$v['text']} vs {$linktext})");
          if ( !array_key_exists($c, $pagerlinks) ) {
            unset($found_links[$v['url']]);
            continue;
          }  
        } else {
          $this->syslog(__FUNCTION__,__LINE__,"!! Missing entry: {$v['url']} ({$v['text']})");
          $found_links[$v['url']]['missing'] = $c;
          $link_properties[] = 'uncached';
        }
        $link_properties = join(' ', $link_properties);
        $substitute_content .= <<<EOH
<li><a id="{$link_hash}" class="{$link_properties}" href="{$v['url']}">{$v['text']}</a></li>
EOH;
      }/*}}}*/
    }/*}}}*/

    $this->syslog(__FUNCTION__,__LINE__,"++ Matches found on this page: {$entries_found}");
    // $this->recursive_dump($found_links,__LINE__);

    if ( !($entries_found > 2) ) {/*{{{*/

      $this->recursive_dump($containers = $gazette->get_containers(
        //'children[tagname=div][class*=entry-content]{[text]}'
        //'children[tagname=div][class*=category-legis|category-republic-acts|i]'
      ),__LINE__);

      $this->recursive_dump($approval_date = $gazette->get_containers(
        'children[tagname=div][class*=entry-content]{[text*=Approved(:?)|i]}'
      ),__LINE__);

      $this->recursive_dump($ra_number = $gazette->get_containers(
        'children[tagname=div][class*=entry-content]{[text*=REPUBLIC ACT NO|i]}'
      ),__LINE__);

      $this->recursive_dump($act_title = $gazette->get_containers(
        'children[tagname=div][class*=entry-content]{[text*=AN ACT ]}'
      ),__LINE__);

    }/*}}}*/

    $gazette->debug_operators = FALSE;

    // Individual, already cached Republic Act announcements
    $pattern = '(http://www.gov.ph/(.*)/republic-act-no(.*)([/]*))';
    $matches = $test_url->count(array('url' => "REGEXP '{$pattern}'"));
    $this->syslog( __FUNCTION__, __LINE__, "- Available pages: {$matches}");
    $record = array();
    $link_properties = array('human-element-dossier-trigger'/* legiscope-remote*/,'cached');
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
    $this->syslog( __FUNCTION__, __LINE__, "------ Tracked RA announcement pages: {$matches}");
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
      // $this->syslog( __FUNCTION__, __LINE__, "------ {$matches[2]}");
      // $this->recursive_dump($matches,__LINE__);
      $matches = intval(trim($matches[2],'/'));
      $new_url = trim(str_replace('(.*)', $matches, $pattern),'()');
      $distinct_pages[$matches] = $new_url;
      // $this->syslog( __FUNCTION__, __LINE__, "------ {$new_url}");
      if ( $matches > $maximum_page_number ) $maximum_page_number = $matches;

    }/*}}}*/

    array_walk($distinct_links,
      create_function('&$a, $k, $s', '$h = UrlModel::get_url_hash($a["url"]); $a["text"] = $s->get_linktext();'),
      $test_url);

    if (0) $this->syslog(__FUNCTION__,__LINE__,"-----------------------" );
    if (0) $this->recursive_dump($distinct_pages,__LINE__);

    ksort($distinct_pages);
    $this->syslog( __FUNCTION__, __LINE__, "------ Furthest page reached: {$maximum_page_number}");
    $this->syslog( __FUNCTION__, __LINE__, "------ Earliest page: {$distinct_pages[$maximum_page_number]}");

    $link_hash = UrlModel::get_url_hash($distinct_pages[$maximum_page_number]);
    $substitute_content = <<<EOH
<li><a id="{$link_hash}" class="{$link_properties}" href="{$distinct_pages[$maximum_page_number]}">Oldest Announcement</a></li>
{$substitute_content}
EOH;
    $parser->linkset = preg_replace('@(human-element-dossier-trigger)@', 'legiscope-remote', <<<EOH
<ul class="link-cluster">
{$substitute_content}
</ul>
EOH
    );
    
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

    $this->syslog( __FUNCTION__, __LINE__, "- Finally done.");

  }/*}}}*/

	function seek_by_pathfragment_b0ccba18e303576f24c78cf054acfb4c(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/

    $gazette = new GazetteCommonParseUtility();
		$gazette->
			set_parent_url($urlmodel->get_url())->
			parse_html($pagecontent,$urlmodel->get_response_header());

		$this->recursive_dump(($containers = $gazette->get_containers(
		)),"(marker)");

	}/*}}}*/

  function parse_republic_act(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/

    $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked for " . $urlmodel->get_url() );

    $pagecontent = $urlmodel->get_pagecontent();
    $gazette = new GazetteCommonParseUtility();
    $gazette->set_parent_url($urlmodel->get_url())->parse_html($pagecontent,$urlmodel->get_response_header());

    $link = new UrlModel();
    // $link->dump_accessor_defs_to_syslog();

    $pagecontent = join('',$gazette->get_filtered_doc());

    if ( is_null( $urlmodel->get_pagecontent() ) ) {
      $urlmodel->set_pagecontent($pagecontent);
      $urlmodel->stow();
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

      $this->syslog(__FUNCTION__,__LINE__, "!! Unparseable {$urlmodel}. Leaving.");
      return;

    }
    $gazette->debug_operators = FALSE;

    // Extract R.A. serial number

    $this->recursive_dump($ra_number = $gazette->get_containers(
      'children[tagname=div][class*=entry-content]{[text*=REPUBLIC ACT NO|i]}'
    ),__LINE__);

    $ra_number = $gazette->reduce_containers_to_string($ra_number);

    if ( is_null($ra_number) || !($ra_number = preg_replace('@((\[)*([^0-9]*)([0-9]*)([^]]*)(\])*)@','$4',$ra_number)) ) {

      $this->syslog(__FUNCTION__,__LINE__, "Unable to obtain RA number from content. Skipping");
      return;

    }

    $ra_number = "RA" . str_pad($ra_number,5,'0',STR_PAD_LEFT);

    $this->syslog(__FUNCTION__,__LINE__, "RA S.N. is {$ra_number}");

    if ( strlen($ra_number) > 7 ) {
      $this->syslog(__FUNCTION__,__LINE__, "Ill-formed R.A. number. Skipping");
      return;
    }

    $republic_act = new RepublicActDocumentModel();
    // $republic_act->dump_accessor_defs_to_syslog();

    $republic_act->fetch($ra_number,'sn');

    if ( $republic_act->in_database() && !is_null($republic_act->get_content_json()) ) {

      $this->syslog(__FUNCTION__,__LINE__, "{$ra_number} already stowed. Leaving.");
      return;

    }

    $this->recursive_dump($approval_date = $gazette->get_containers(
      'children[tagname=div][class*=entry-content]{[text*=Approved:|i]}'
    ),__LINE__);

    $this->recursive_dump($act_title = $gazette->get_containers(
      'children[tagname=div][class*=entry-content]{[text*=AN ACT ]}'
    ),__LINE__);

    if ( !(1 == count($act_title)) ) {

      $this->syslog(__FUNCTION__,__LINE__, "{$ra_number} multiline title.  Double check content.");

    }

    $approval_date = $gazette->reduce_containers_to_string($approval_date);
    $act_title = $gazette->reduce_containers_to_string($act_title);

    $this->recursive_dump($containers,__LINE__);

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
    $this->syslog( __FUNCTION__, __LINE__, "Invoked for " . $urlmodel->get_url() );
    $this->common_unhandled_page_parser($parser,$pagecontent,$urlmodel);
  }/*}}}*/

}
