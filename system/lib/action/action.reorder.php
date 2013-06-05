<?php

/*
 * Class ReorderAction
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class ReorderAction extends LegiscopeBase {
  
  function __construct() {
    // $this->syslog( __FUNCTION__, __LINE__, '(marker) Generic action handler.' );
    parent::__construct();
  }

  function reorder() {/*{{{*/

    $debug_method = FALSE; 

    $this->syslog( __FUNCTION__, __LINE__, "(marker) ---------------------------------- Invoked from {$_SERVER['REMOTE_ADDR']}" );
    $this->recursive_dump($_POST,'(marker)');

    ob_start();

    $clusterid = $this->filter_post('clusterid');
    $move      = $this->filter_post('move');
    $referrer  = $this->filter_session('referrer');
    $this->subject_host_hash = UrlModel::get_url_hash($referrer,PHP_URL_HOST);

    // Extract link cluster ID parts
    $cluster_id_parts = array();
    preg_match('@([[:xdigit:]]{32})-([[:xdigit:]]{32})-([[:xdigit:]]{40})@', $clusterid, $cluster_id_parts);

    $json_reply = array('referrer' => $referrer);

    // Retrieve and parse links on page
    $cluster   = new UrlClusterModel();
    $parser    = new GenericParseUtility();
    $page      = new UrlModel($referrer,TRUE);
    $clusterid = array_element($cluster_id_parts,3);
    $parser->
      set_parent_url($page->get_url())->
      parse_html($page->get_pagecontent(),$page->get_response_header());

    $cluster->dump_accessor_defs_to_syslog();

    $cluster->reposition($page, $clusterid, $move);

    // Regenerate the link set
    if ( FALSE == $page->is_custom_parse() ) {
      $linkset = $parser->generate_linkset($page->get_url());
      $json_reply['linkset'] = $linkset['linkset'];
    }

		// Transmit response (just the regenerated set of links for that page)
		if ( $debug_method ) {
			$this->recursive_dump($json_reply,'(marker)');
		}

    $this->exit_cache_json_reply($json_reply,get_class($this));

  }/*}}}*/


}

