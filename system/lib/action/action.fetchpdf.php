<?php

/*
 * Class FetchpdfAction
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class FetchpdfAction extends LegiscopeBase {
  
  function __construct() {
    parent::__construct();
    $this->register_derived_class();
  }

  function fetchpdf($a) {/*{{{*/

    $this->syslog( __FUNCTION__,__LINE__, "(marker) -------------- - {$a} " . get_class($this));

    ob_start();

    $url = new UrlModel();
    $url->fetch($a,'urlhash');
    $target_url = $url->get_url();
    $content_type = $url->get_content_type();

    $this->syslog( __FUNCTION__, __LINE__, "(marker) Doctype '{$content_type}' passed: '{$a}', from {$_SERVER['REMOTE_ADDR']} " . session_id() . " <- {$target_url}" );

    $output_buffer = ob_get_clean();
    if ( 0 < strlen($output_buffer) ) {/*{{{*/
      // Dump content inadvertently generated during this operation.
      $output_buffer = explode("\n", $output_buffer);
      $this->syslog(__FUNCTION__,__LINE__,'WARNING:  System-generated warning messages trapped');
      $this->recursive_dump($output_buffer,__LINE__);
    }/*}}}*/

    if ( 1 == preg_match('@^application/pdf@i', $content_type) ) {/*{{{*/
      $this->syslog( __FUNCTION__, __LINE__, "(marker) Emitting PDF '{$a}' to {$_SERVER['REMOTE_ADDR']} " . session_id() . " <- {$target_url}" );
      $this->syslog( __FUNCTION__, __LINE__, "(marker) Current WD: " . SYSTEM_BASE );
      $this->write_to_ocr_queue($url);
      header("Content-Type: " . $content_type);
      header("Content-Length: " . $url->get_content_length());

      header('Content-Description: Cached PDF');
      header('Content-Disposition: attachment; filename='.basename($url->get_url()));
      header('Content-Transfer-Encoding: binary');
      header('Expires: 0');
      header('Cache-Control: must-revalidate');
      header('Pragma: public');

      echo $url->get_pagecontent();
      exit(0);
    }/*}}}*/

    header("HTTP/1.1 404 Not Found");
    exit(0);

  }/*}}}*/


}

