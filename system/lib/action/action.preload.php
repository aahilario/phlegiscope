<?php

/*
 * Class PreloadAction
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class PreloadAction extends LegiscopeBase {
  
  function __construct() {
    // $this->syslog( __FUNCTION__, __LINE__, '(marker) Generic action handler.' );
    parent::__construct();
  }

  function preload() {/*{{{*/

    $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked from {$_SERVER['REMOTE_ADDR']}" );

    ob_start();

    $link_hashes = $this->filter_post('links', array());
    $modifier    = $this->filter_post('modifier');

    $this->recursive_dump($link_hashes,"(marker)");

    $url = new UrlModel();

    $uncached = array();

    // URLs which are presumed to always expire
    $lie_table = array(
      'http://www.gov.ph/(.*)/republic-act-no(.*)([/]*)',
      'http://www.congress.gov.ph/download/(.*)',
      'http://www.congress.gov.ph/committees/(.*)',
      'http://www.senate.gov.ph/(.*)',
      'http://www.gmanetwork.com/news/eleksyon2013/(.*)',
    );

    $lie_table = '@' . join('|', $lie_table) . '@i';

    $hash_count = 0;

    foreach( $link_hashes as $hash ) {
      $url->fetch($hash, 'urlhash');
      $urlstring = $url->get_url();
      $in_lie_table = (1 == preg_match($lie_table,$url->get_url())) || ($modifier == 'true');
      if ( $url->in_database() && !($modifier == 'true') && !$in_lie_table ) {
        $this->syslog(__FUNCTION__,__LINE__,"- Matched hash {$hash} to {$urlstring}" );
      } else {
        $this->syslog(__FUNCTION__,__LINE__,"+ Must fetch URL corresponding to {$hash}" );
        $uncached[] = array(
          'hash' => $hash,
          'live' => !$in_lie_table,
        );
      }
      if ( $hash_count++ > 300 ) break;
    }

    $json_reply = array(
      'count' => count($uncached),
      'uncached' => $uncached,
    );

    $this->exit_cache_json_reply($json_reply,get_class($this));

  }/*}}}*/

}

