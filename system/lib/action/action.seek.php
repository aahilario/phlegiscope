<?php

/*
 * Class SeekAction
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class SeekAction extends LegiscopeBase {
  
  var $debug_memory_usage_delta = TRUE;

  function __construct() {
    parent::__construct();
    $this->register_derived_class();
  }

  function seek() {/*{{{*/

    // Perform an HTTP GET
    ob_start();

    $debug_method = FALSE;

    $json_reply      = array();
    $modifier        = $this->filter_post('modifier');
    $metalink        = $this->filter_post('metalink');
    $linktext        = $this->filter_post('linktext');
    $target_url      = $this->filter_post('url','');
    $freeze_referrer = $this->filter_post('fr');
    $cache_force     = $this->filter_post('cache');
    $referrer        = $this->filter_session('referrer');
    $url             = new UrlModel($target_url, empty($target_url) ? FALSE : TRUE);


    if ( $debug_method ) {
			$this->syslog( __FUNCTION__,__LINE__,"(marker) cache[{$cache_force}] url[{$target_url}] ------------------------------------------------------------------------------------------------");
      $this->recursive_dump($_POST,'(marker) -- - -- INPOST');
    }

    $network_fetch  = ($modifier == 'reload' || $modifier == 'true');

    $displayed_target_url = $target_url;

    $this->exit_emit_cached_content($url, $cache_force, $network_fetch);
  
    $session_has_cookie = $this->filter_session("CF{$this->subject_host_hash}");

    $json_reply = array(
      'url'            => $target_url,
      'error'          => '',
      'message'        => '', 
      'markup'         => '',
      'responseheader' => '',
      'httpcode'       => '400',
      'retainoriginal' => TRUE,
      'original'       => "<pre>NO CONTENT RETRIEVED\nURL {$target_url}</pre>",
      'defaulttab'     => 'original',
      'referrer'       => $referrer,
      'contenttype'    => 'unknown',
    );

    $faux_url       = GenericParseUtility::get_faux_url_s($url, $metalink);
    $urlhash        = $url->get_urlhash();
    $network_fetch  = ($modifier == 'reload' || $modifier == 'true') || !$url->in_database();
    $content_length = $url->get_content_length();
    $retrieved      = $url->in_database();
    $action         = $retrieved && !$network_fetch
      ? "DB Retrieved {$content_length} octets"
      : "Reloading"
      ;

    if ( $debug_method ) {/*{{{*/
      $cached_before_retrieval = $retrieved ? "existing" : "uncached";
      $this->syslog( __FUNCTION__, __LINE__, "(marker) " . ($network_fetch ? "Network Fetch" : "Parse") . " {$cached_before_retrieval} link, invoked from {$_SERVER['REMOTE_ADDR']} " . session_id() . " <- {$target_url} ('{$linktext}') [{$session_has_cookie}]" );
    }/*}}}*/

    if ( ( $debug_method ) || !is_null($faux_url) ) {/*{{{*/
      $in_db = $retrieved ? 'in DB' : 'uncached';
      if ( !is_null($faux_url) ) $this->syslog(__FUNCTION__,__LINE__,"(marker) Faux URL present - {$faux_url}");
      $this->syslog(__FUNCTION__,__LINE__, "(marker) Created fake URL ({$in_db}) {$faux_url} from components, mapping {$faux_url} <- {$target_url}" );
      $this->recursive_dump((is_array($metalink) ? $metalink : array("RAW" => $metalink)),'(marker) Metalink URL src');
    }/*}}}*/

		// FIXME: Modify DatabaseUtility to permit update of only a single attribute, rather than updating entire record.
    if ( $retrieved ) $url->increment_hits()->stow();

    if ( $network_fetch ) {/*{{{*/

      $retrieved = $this->perform_network_fetch( $url, $referrer, $target_url, $faux_url, $metalink, $debug_method );
      $action = $retrieved
        ? "Retrieved " . $url->get_content_length() . ' octet ' . $url->get_content_type()
        : "WARNING Failed to retrieve"
        ;
      if (!$retrieved) {/*{{{*/

        $json_reply['error']          = CurlUtility::$last_error_number;
        $json_reply['message']        = CurlUtility::$last_error_message;
        $json_reply['responseheader'] = CurlUtility::$last_transfer_info;
        $json_reply['defaulttab']     = 'original';
        $json_reply['retainoriginal'] = TRUE;
        $this->syslog( __FUNCTION__, __LINE__, "WARNING: Failed to retrieve {$target_url}" );
        if (TRUE || $debug_method) $this->recursive_dump(CurlUtility::$last_transfer_info,'(error) ');

      }/*}}}*/

    }/*}}}*/

    $pagecontent    = $url->get_pagecontent();
    $responseheader = '...';
    $headers        = $url->get_response_header(TRUE);
    $curl_error_no  = CurlUtility::$last_error_number;
    $curl_error_msg = CurlUtility::$last_error_message;
		$parser         = $this;
    $body_content   = NULL;

    $headers['legiscope-regular-markup'] = 0;

    if ( ( array_key_exists('http_code', $headers) && (504 == intval($headers['http_code']) ) ) || 
      (array_key_exists('http-response-code', $headers) && ($headers['http-response-code'] == 504) ) ) {/*{{{*/
      $response_kind = empty($headers['http_code']) ? "Empty response code (#{$curl_error_no}, '{$curl_error_msg}')" : "Bad response code [{$headers['http_code']}]";
      $this->recursive_dump($headers,"(marker) H --");
      $this->syslog( __FUNCTION__, __LINE__, "(marker) -- -- -- - - - -- -- -- {$response_kind}, NF " . ($network_fetch ? 'OK' : 'NO') . ", successful " . ($retrieved ? 'YES' : 'NO'));
      $retrieved = FALSE;
    }/*}}}*/

    if ( !$retrieved ) {/*{{{*/

      // Unsuccessful fetch attempt
      $cache_filename = $url->get_cache_filename();
      if ( $url->in_database() ) {
        $url_id = $url->get_id();
        $this->syslog( __FUNCTION__, __LINE__, "WARNING ********** Removing {$url} #{$url_id}. Must remove cache file {$cache_filename}" );
        $url->remove();
      }
      if ( file_exists($cache_filename) ) {
        $unlink_ok = unlink($cache_filename) ? 'OK' : 'FAILED';
        $this->syslog( __FUNCTION__, __LINE__, "WARNING ********** Removing cache file {$cache_filename} {$unlink_ok}" );
      }

    }/*}}}*/
    else {/*{{{*/

      $json_reply  = array(); // May be overridden by per-site handler, see below

      $subject_url = is_null($faux_url) ? $target_url : $faux_url;
      $this->syslog( __FUNCTION__, __LINE__, "(marker) {$action} response from {$subject_url} <- '{$referrer}' for {$_SERVER['REMOTE_ADDR']}:{$this->session_id}");

      if ( !is_null($faux_url) ) {/*{{{*/
        // If we've loaded content from an ephemeral URL, 
        // use the POST target page action for generating links,
        // rather than the fake URL.
        $url->set_url($target_url,FALSE);
      }/*}}}*/

      $responseheader = $url->get_response_header(FALSE,'<br/>');

      $linkset = array();

      if ( array_key_exists('content-type', $headers) ) {/*{{{*/

        if ( $debug_method ) $this->syslog(__FUNCTION__, __LINE__, "(marker)  - - - - Content-Type: {$headers['content-type']}");

        // Parsers only handle HTML content
        if ( 1 == preg_match('@^text/html@i', $headers['content-type']) ) {/*{{{*/

          $headers['legiscope-regular-markup'] = 1;

          // Speed up parsing by skipping generic parser
          if ( method_exists($this, 'must_custom_parse') ) {
            if ( !$url->is_custom_parse() && $this->must_custom_parse($url) ) {
              $url->ensure_custom_parse();
            }
          }

          $is_custom_parse = $url->is_custom_parse();

          if ( $is_custom_parse ) $this->syslog( __FUNCTION__, __LINE__, "(warning) Custom parse" );

					$parser = new GenericParseUtility();

          // Defer parsing by setting the URL custom_parse flag in DB
          $structure = $is_custom_parse
            ? array()
            : $parser->set_parent_url($url->get_url())->parse_html($pagecontent,$url->get_response_header())
            ;

          // Only process <body> tag content
					$body_content = $is_custom_parse
						? NULL
						:	$parser->fetch_body_generic_cleanup($pagecontent)
						;

          // Custom-parsed page handlers must generate navigation links
          $linkset = $is_custom_parse
            ? array('linkset' => array(),'urlhashes' => array(),'cluster_urls' => array())
            : $parser->generate_linkset($url->get_url())
            ;

          extract($linkset); // 'linkset', 'urlhashes', 'cluster_urls'

          $body_content = htmlspecialchars_decode($body_content, ENT_NOQUOTES );

          $handler_list = $this->get_handler_names($url, $cluster_urls);

          $matched = FALSE;

          foreach ( $handler_list as $handler_type => $method_name ) {/*{{{*/
            // Break on first match
            if ( method_exists($this, $method_name) ) {/*{{{*/

              $matched |= TRUE;

              $parser->trigger_linktext = $linktext;
              $parser->from_network     = $network_fetch;
              $parser->json_reply       = $json_reply;
              $parser->target_url       = $target_url;
              $parser->metalink_url     = $faux_url; 
              $parser->metalink_data    = array_element($metalink,'_LEGISCOPE_');

              $parser->linkset          = $linkset;
              $parser->cluster_urldefs  = $cluster_urls;
              $parser->urlhashes        = $urlhashes;

							$invocation_delta = microtime(TRUE);
              if ( $this->debug_memory_usage_delta ) {
                $this->syslog(__FUNCTION__,__LINE__,"(warning) Invoking {$method_name} - Memory usage " . memory_get_usage(TRUE) );
              }
              $this->$method_name($parser, $body_content, $url);
              if ( $this->debug_memory_usage_delta ) {
                $this->syslog(__FUNCTION__,__LINE__,"(warning)  Invoked {$method_name} - Memory usage " . memory_get_usage(TRUE) );
              }
							$invocation_delta = round(microtime(TRUE) - $invocation_delta,3);

              $linkset        = $parser->linkset;
              $json_reply     = $parser->json_reply; // Merged with response JSON
							$json_reply['timedelta'] = $invocation_delta;
              $target_url     = $parser->target_url;

              break;
            }/*}}}*/
          }/*}}}*/

          if ( !$matched ) {
            $this->syslog( __FUNCTION__, __LINE__, "No custom handler for path " . $url->get_url());
            $this->recursive_dump($handler_list,__LINE__);
          }

          $handler_list = NULL;
          unset($handler_list);

          $parser = NULL;
          unset($parser);

          gc_collect_cycles();
          if ( $debug_method ) {
            $this->syslog(__FUNCTION__,__LINE__,"(warning)  Deallocated parser; Memory usage " . memory_get_usage(TRUE) );
          }

        }/*}}}*/
        else if ( 1 == preg_match('@^application/pdf@i', $headers['content-type']) ) {/*{{{*/
          $body_content = 'PDF';
          $body_content_length = $url->get_content_length();
          $this->syslog( __FUNCTION__, __LINE__, "PDF fetch {$body_content_length} from " . $url->get_url());
          $headers['legiscope-regular-markup'] = 0;
          // Attempt to reload PDFs in an existing block container (alternate 'original' rendering block)
          $json_reply = array('retainoriginal' => 'true');
          if ( $debug_method ) $this->syslog( __FUNCTION__, __LINE__, "(warning) PDF loader, retain original frame");
        }/*}}}*/
      }/*}}}*/

      if ( is_null($freeze_referrer) ) {/*{{{*/
        $_SESSION['referrer'] = $target_url;
      }/*}}}*/
      else {/*{{{*/
        $this->syslog( __FUNCTION__, __LINE__, "(marker) Freeze referrer {$referrer} for " . $url->get_url());
      }/*}}}*/

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
          "",
          "",
        ),
        $body_content
      );

      if ( $debug_method ) {
        $this->syslog( __FUNCTION__, __LINE__, "(marker) Transmissible content length: " . strlen($body_content) );
        $this->syslog( __FUNCTION__, __LINE__, "(marker) Transmissible final length: " . strlen($final_body_content) );
      }

      $final_content = str_replace(
        array('<br/>'  , '><'  , '<'  , '>'   , " "     , '{BREAK}'),
        array('{BREAK}', ">{BREAK}<", '&lt;', '&gt;', "&nbsp;", '<br/>')  ,
        $body_content
      ); 

      $defaulttab = strtolower(array_element($headers,'content-type','')) == 'text/html' && strtolower(array_element($headers,'transfer-encoding','')) == 'chunked';
      $defaulttab = $defaulttab || array_key_exists(array_element($headers,'http-response-code',0),array_flip(array(100,200,302,301)))
        ? 'original'
        : 'responseheader'
        ;
      $json_reply = array_merge(
        array(
          'url'            => $displayed_target_url,
          'contenthash'    => $url->get_content_hash(),
          'referrer'       => $referrer,
          'contenttype'    => $url->get_content_type(),
          'linkset'        => $linkset,
          'markup'         => $headers['legiscope-regular-markup'] == 1 ? utf8_encode($final_content) : '[OBSCURED CONTENT]',
          'responseheader' => $responseheader,
          'httpcode'       => $headers['http-response-code'], 
          'original'       => C('DISPLAY_ORIGINAL') ? $final_body_content : '',
          'defaulttab'     => $defaulttab, 
        ),
        $json_reply
      );

      $member_uuid = $this->filter_post('member_uuid');
      if ( !is_null($member_uuid) && $json_reply['httpcode'] == 200 && method_exists($this, 'member_uuid_handler')) {/*{{{*/
        $this->member_uuid_handler($json_reply, $url, $member_uuid);
      }/*}}}*/

    }/*}}}*/

    $output_buffer = ob_get_clean();

    if ( 0 < strlen($output_buffer) ) {/*{{{*/
      // Dump content inadvertently generated during this operation.
      $output_buffer = explode("\n", $output_buffer);
      $this->syslog(__FUNCTION__,__LINE__,'WARNING:  System-generated warning messages trapped');
      $this->recursive_dump($output_buffer,__LINE__);
    }/*}}}*/

    $this->exit_cache_json_reply($json_reply,get_class($this));

  }/*}}}*/

}

