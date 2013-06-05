<?php

/*
 * Class KeywordAction
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class ProxyformAction extends LegiscopeBase {
  
  function __construct() {
    // $this->syslog( __FUNCTION__, __LINE__, '(marker) Generic action handler.' );
    parent::__construct();
  }

  function proxyform() {/*{{{*/

		// TODO: This duplicates SeekAction::seek(), either eliminate this or factor out common sections
    ob_start();

    $modifier                = $this->filter_post('modifier');
    $target_url              = $this->filter_post('url');
    $freeze_referrer         = $this->filter_post('fr');
    $cache_force             = $this->filter_post('cache');
    $referrer                = $this->filter_session('referrer');
    // Use the referrer host (which should be the URL containing the
    // form whose data is being submitted) to retrieve session state.
    $this->subject_host_hash = UrlModel::get_url_hash($referrer,PHP_URL_HOST);
    $network_fetch           = $modifier == 'true';
    $session_has_cookie      = $this->filter_session("CF{$this->subject_host_hash}");
    $form_data               = $this->filter_post('data',array(array('name' => NULL,'value' => NULL)));

    // The form's ACTION target URL.
    $url     = new UrlModel($target_url, TRUE);
    $generic = new GenericParseUtility();
    $in_db   = $url->in_database() ? 'in DB' : 'uncached';

    $this->syslog( __FUNCTION__, __LINE__, "(marker) ----- ---- --- -- - - Form submission from: {$_SERVER['REMOTE_ADDR']} [{$session_has_cookie}]" );
    $this->syslog( __FUNCTION__, __LINE__, "(marker) ----- ---- --- -- - -        Action target: {$target_url} ({$in_db})" );
    $this->recursive_dump($_POST,'(marker) ----- ---- --- -- - P');

    $json_reply = array();

    //////////////////////////////////////////////////////////

    $form_data = array_combine(
      array_map(create_function('$a', 'return $a["name"];'), $form_data),
      array_map(create_function('$a', 'return $a["value"];'), $form_data)
    );

    $this->recursive_dump($form_data,'(marker) ----- ---- --- -- -');

    $faux_url     = $target_url;

    $fetch_result = $network_fetch
      ? $this->perform_network_fetch( $url, $referrer, $target_url, $faux_url, $form_data, ($debug_dump = TRUE) )
      : $url->in_database()
      ;

    if ( $fetch_result ) {

      foreach ( $this->get_handler_names($url, array()) as $handler_type => $method_name ) {/*{{{*/
        // Break on first match
        if ( method_exists($this, $method_name) ) {/*{{{*/
          $matched |= TRUE;
          $parser->from_network     = $network_fetch;
          $parser->json_reply       = $json_reply;
          $parser->target_url       = $target_url;

          $this->syslog(__FUNCTION__,__LINE__,"(warning) Invoking {$method_name}");
          $this->$method_name($parser, $body_content, $url);

          $linkset        = $parser->linkset;
          $json_reply     = $parser->json_reply; // Merged with response JSON
          $target_url     = $parser->target_url;

          break;
        }/*}}}*/
      }/*}}}*/

      $this->syslog( __FUNCTION__, __LINE__, "(marker) ----- ---- --- -- - -  Generating JSON response" );
      $body_content = $url->get_pagecontent();
      $final_body_content = preg_replace(
        array(
          '/<html([^>]*)>/imU',
          '/>([ ]*)</m',
          '/<!--(.*)-->/imUx',
          '@<noscript>(.*)</noscript>@imU',
          //'@<script([^>]*)>(.*)</script>@imU',
          "@\&\#10;@",
          "@\&\#9;@",
          "@\n@",
        ),
        array(
          '<html>',
          '><',
          '',
          '',
          '',
          //'',
          "",
          " ",
          "",
        ),
        $body_content
      );
      $final_content = str_replace(
        array('<br/>'  , '><'  , '<'  , '>'   , " "     , '{BREAK}'),
        array('{BREAK}', ">{BREAK}<", '&lt;', '&gt;', "&nbsp;", '<br/>')  ,
        $body_content
      ); 
      $responseheader = $url->get_response_header(FALSE,'<br/>');
      $headers        = $url->get_response_header(TRUE);
      $headers['legiscope-regular-markup'] = (1 == preg_match('@^text/html@i', $headers['content-type'])) ? 1 : 0;
      $json_reply = array_merge(
        array(
          'url'            => $url->get_url(),
          'contenthash'    => $url->get_content_hash(),
          'referrer'       => $referrer,
          'contenttype'    => $url->get_content_type(),
          'linkset'        => NULL,
          'markup'         => $headers['legiscope-regular-markup'] == 1 ? utf8_encode($final_content) : '[OBSCURED CONTENT]',
          'responseheader' => $responseheader,
          'httpcode'       => $headers['http-response-code'], 
          'original'       => C('DISPLAY_ORIGINAL') ? $final_body_content : '',
          'defaulttab'     => array_key_exists($headers['http-response-code'],array_flip(array(100,200,302,301))) ? 'original' : 'responseheader',
        ),
        $json_reply
      );
      $this->recursive_dump($json_reply,'(marker) ----- ---- --- -- -');
    }

    //////////////////////////////////////////////////////////

    $output_buffer = ob_get_clean();

    if ( 0 < strlen($output_buffer) ) {/*{{{*/
      // Dump content inadvertently generated during this operation.
      $output_buffer = explode("\n", $output_buffer);
      $this->syslog(__FUNCTION__,__LINE__,'WARNING:  System-generated warning messages trapped');
      $this->recursive_dump($output_buffer,__LINE__);
    }/*}}}*/

    $this->exit_cache_json_reply($json_reply,'LegiscopeBase');

    return $json_reply;

  }/*}}}*/


}

