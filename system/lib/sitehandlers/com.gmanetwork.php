<?php

/*
 * Class GmanetworkCom
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class GmanetworkCom extends LegiscopeBase {
  
  function __construct() {
    $this->syslog( __FUNCTION__, 'FORCE', 'Using site-specific container class' );
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
    $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked for " . $urlmodel->get_url() );
    $common = new GmanetworkCommonParseUtility();
    $urlmodel->ensure_custom_parse();
    $common->set_parent_url($urlmodel->get_url())->parse_html($urlmodel->get_pagecontent(),$urlmodel->get_response_header());
    $this->recursive_dump(($containers = $common->get_containers(
    )),'(marker) A');


    $linkset = $common->generate_linkset($urlmodel->get_url());
    
    extract($linkset);
    $parser->linkset = preg_replace('@cached@i','uncached',$linkset);
    $pagecontent = preg_replace(
      array(
        "@\&\#10;@",
        "@\n@",
      ),
      array(
        "",
        "",
      ),
      join('',$common->get_filtered_doc())
    );
  }/*}}}*/

  function must_custom_parse(UrlModel & $url) {/*{{{*/
    return TRUE; 
  }/*}}}*/

}

