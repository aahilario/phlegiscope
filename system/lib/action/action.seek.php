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
  var $update_existing = FALSE;

  function __construct() {
    parent::__construct();
    $this->debug_handler_names = C('DEBUG_HANDLER_NAMES');
  }

  function seek() {/*{{{*/

    // Perform an HTTP GET
    ob_start();

    $invocation_delta = microtime(TRUE);

    $json_reply   = array();
    $this->update_existing = $this->filter_post('update') == 'true'; 
    $modifier        = $this->filter_post('modifier');
    $metalink        = $this->filter_post('metalink');
    $linktext        = $this->filter_post('linktext');
    $debug_method    = $this->filter_post('debug') == 'true';
    $target_url      = $this->filter_post('url','');
    $target_url_hash = UrlModel::get_url_hash($target_url); 
    $freeze_referrer = $this->filter_post('fr'); // Freeze referrer
    $cache_force     = NULL;
    $force_log       = $this->filter_post('cache');
    if ( $force_log == 'true' ) DatabaseUtility::$force_log = TRUE;
    $referrer        = $this->filter_session('referrer');
    $url             = new UrlModel();

    if ( !is_null($metalink) ) {
      $metalink = @json_decode(base64_decode($metalink),TRUE);
      if ( DatabaseUtility::$force_log ) $this->recursive_dump($metalink,'(critical) -- - - - metalink ');
    }

    if ( $debug_method ) {
      $this->syslog( __FUNCTION__,__LINE__,"(marker) cache[{$cache_force}] url[{$target_url}] ------------------------------------------------------------------------------------------------");
      $this->recursive_dump($_POST,'(marker) -- - -- INPOST');
    }

    if ( 0 < strlen($target_url) ) {
      $url->fetch($target_url_hash,'urlhash');
      $url->set_url($target_url,FALSE); // fetch() clears the URL string if the UrlModel is not yet in DB.
    }

    $force_custom_parse = method_exists($this, 'must_custom_parse') && $this->must_custom_parse($url); 

    $network_fetch  = ($modifier == 'reload' || $modifier == 'true');

    $displayed_target_url = $target_url;

    $this->exit_emit_cached_content($url, $cache_force, $network_fetch);
  
    $session_has_cookie = $this->filter_session("CF{$this->subject_host_hash}");

    $json_reply = array(
      'url'            => $target_url,
      'error'          => '',
      'message'        => '', 
      'httpcode'       => '400',
      'retainoriginal' => TRUE,
      'subcontent'     => "<pre>NO CONTENT RETRIEVED\nURL {$target_url}</pre>",
      'defaulttab'     => 'content',
      'referrer'       => $referrer,
      'contenttype'    => 'unknown',
    );

    $content_hash   = $url->get_content_hash();
    $age            = intval($url->get_last_fetch());
    $urlhash        = $url->get_urlhash();
    $content_length = $url->get_content_length();
    $network_fetch  = $network_fetch || ((0 < strlen($target_url)) && !$url->in_database());
    $retrieved      = $url->in_database();
    $action         = $retrieved && !$network_fetch
      ? "DB Retrieved {$content_length} octets"
      : "Reloading"
      ;

    if ( $debug_method ) $this->syslog( __FUNCTION__, __LINE__, "(critical) ". ($retrieved ? "In Database" : "MISSING") . " {$target_url}" );

    $process_despite_fetch_failure = FALSE;

    if ( $network_fetch ) {/*{{{*/

      $this->syslog( __FUNCTION__, __LINE__, "(warning) Network fetch {$target_url}" );

      $retrieved = $this->perform_network_fetch( 
        $url     , $referrer, $target_url  ,
        $metalink, $debug_method
			);

      $action = $retrieved
        ? "Retrieved " . $url->get_content_length() . ' octet ' . $url->get_content_type()
        : "WARNING Failed to retrieve"
        ;

			$process_despite_fetch_failure = FALSE;

			if (!$retrieved) {/*{{{*/

				$json_reply['error']          = CurlUtility::$last_error_number;
				$json_reply['message']        = CurlUtility::$last_error_message;
				$json_reply['responseheader'] = CurlUtility::$last_transfer_info;
				$json_reply['defaulttab']     = 'content';
				$json_reply['retainoriginal'] = TRUE;
				$this->syslog( __FUNCTION__, __LINE__, "WARNING: Failed to retrieve {$target_url}" );
				$this->recursive_dump(CurlUtility::$last_transfer_info,'(error) ');

				$process_despite_fetch_failure = ($url->is_custom_parse() || $force_custom_parse);

				if ( $process_despite_fetch_failure ) {
					$this->syslog( __FUNCTION__, __LINE__, "WARNING: Invoking handlers despite failure to retrieve {$target_url}" );
				}
				else {
					$this->syslog( __FUNCTION__, __LINE__, "WARNING: Retrieve failure. Not invoking handlers {$target_url}. " . 
						" Custom_parse " . ($url->is_custom_parse ? "TRUE" : "FALSE") .
						" Force parse " . ($force_custom_parse ? "TRUE" : "FALSE") .
						" Retrieved " . ($retrieved ? "TRUE" : "FALSE")
					);
				}
			}/*}}}*/

    }/*}}}*/

    if ( $debug_method ) {/*{{{*/

      $cached_before_retrieval = $retrieved ? "existing" : "uncached";
      $this->syslog( __FUNCTION__, __LINE__, "(marker) " . ($network_fetch ? "Network Fetch" : "Parse") . " {$cached_before_retrieval} link, invoked from {$_SERVER['REMOTE_ADDR']} " . session_id() . " <- {$target_url} ('{$linktext}') [{$session_has_cookie}]" );

      $in_db = $retrieved ? 'in DB' : 'uncached';

      $this->syslog(__FUNCTION__,__LINE__, "(marker)          Real URL {$target_url}" );
      $this->syslog(__FUNCTION__,__LINE__, "(marker) Instance contains " . $url->get_url() );
      $this->syslog(__FUNCTION__,__LINE__, "(marker)  Instance content " . $url->get_content_hash() );
      $this->syslog(__FUNCTION__,__LINE__, "(marker)  Original content " . $content_hash );
      $this->recursive_dump((is_array($metalink) ? $metalink : array("RAW" => $metalink)),'(marker) Metalink URL src');

    }/*}}}*/

    $pagecontent    = $url->get_pagecontent();
    $responseheader = '...';
    $headers        = $url->get_response_header(TRUE);
    $curl_error_no  = CurlUtility::$last_error_number;
    $curl_error_msg = CurlUtility::$last_error_message;
    $parser         = $this;
    $body_content   = NULL;

    $headers['legiscope-regular-markup'] = 0;

    if ( 504 == array_element($headers,'http_code') || 504 == array_element($headers,'http-response-code') ) {/*{{{*/
      $response_kind = empty($headers['http_code']) ? "Empty response code (#{$curl_error_no}, '{$curl_error_msg}')" : "Bad response code [{$headers['http_code']}]";
      $this->recursive_dump($headers,"(marker) H --");
      $this->syslog( __FUNCTION__, __LINE__, "(marker) -- -- -- - - - -- -- -- {$response_kind}, NF " . ($network_fetch ? 'OK' : 'NO') . ", successful " . ($retrieved ? 'YES' : 'NO'));
      $retrieved = FALSE;
    }/*}}}*/

    if ( !$retrieved && !$process_despite_fetch_failure ) {/*{{{*/

      // Unsuccessful fetch attempt
      $cache_filename = $url->get_cache_filename();
      if ( $url->in_database() ) {
        $url_id = $url->get_id();
        if ( TRUE == C('DELETE_UNREACHABLE_URLS') ) {
          $this->syslog( __FUNCTION__, __LINE__, "WARNING ********** Removing {$url} #{$url_id}. Must remove cache file {$cache_filename}" );
          $url->remove();
        } else {
          $url->increment_hits(TRUE);
          $url->fetch($url_id);
        }
      }
      if ( file_exists($cache_filename) ) {
        $unlink_ok = unlink($cache_filename) ? 'OK' : 'FAILED';
        $this->syslog( __FUNCTION__, __LINE__, "WARNING ********** Removing cache file {$cache_filename} {$unlink_ok}" );
      }

    }/*}}}*/
    else {/*{{{*/

      $json_reply  = array(); // May be overridden by per-site handler, see below

      $contenttype = array_element($headers,'content-type','unspecified');

      if ( $debug_method || ( $network_fetch && !$retrieved ) ) {/*{{{*/
        $this->syslog( __FUNCTION__, __LINE__, "(marker) {$action} response (content-type: {$contenttype}) from {$target_url} <- '{$referrer}' for {$_SERVER['REMOTE_ADDR']}:{$this->session_id}");
        $this->syslog( __FUNCTION__, __LINE__, "(marker) - Subject URL: {$target_url}"); 
        $this->syslog( __FUNCTION__, __LINE__, "(marker) - Working URL: " . $url->get_url());
      }/*}}}*/

      $responseheader = $url->get_response_header(FALSE,'<br/>');

      $linkset = array();

      $url->increment_hits(TRUE);

      if ( array_key_exists('content-type', $headers) || $process_despite_fetch_failure ) {/*{{{*/

        if ( $debug_method ) $this->syslog(__FUNCTION__, __LINE__, "(marker)  - - - - Content-Type: {$headers['content-type']}");

        // Parsers only handle HTML content; PDF content may be intercepted
        // by parsers, as in the case of Republic Act documents, where the PDF
        // documents are of secondary importance to end-users. 

        // Speed up parsing by skipping generic parser
        if ( $force_custom_parse ) $url->ensure_custom_parse();

        $is_custom_parse = $url->is_custom_parse();

        // Handle PDF and HTML content.  See interaction between these
        // content types with the is_custom_parse flag.
        $is_pdf  = (1 == preg_match('@^application/pdf@i', $headers['content-type']));
        $is_html = (1 == preg_match('@^text/html@i', $headers['content-type']));

        if ( $is_pdf && !$is_custom_parse && !$force_custom_parse ) {/*{{{*/
          $body_content = 'PDF';
          $body_content_length = $url->get_content_length();
          $this->syslog( __FUNCTION__, __LINE__, "(marker) PDF fetch {$body_content_length} from " . $url->get_url());
          $this->syslog( __FUNCTION__, __LINE__, "(marker) PDF fetch URL hash " . $url->get_urlhash());

          $this->write_to_ocr_queue($url);

          $headers['legiscope-regular-markup'] = 0;
          // Attempt to reload PDFs in an existing block container (alternate 'original' rendering block)
          $json_reply = array('retainoriginal' => 'true');
          if ( $debug_method ) $this->syslog( __FUNCTION__, __LINE__, "(warning) PDF loader, retain original frame");
        }/*}}}*/
        else if ( $is_html || ($is_pdf && $is_custom_parse) || $force_custom_parse) {/*{{{*/

          $headers['legiscope-regular-markup'] = $is_html;

          if ( $is_custom_parse && $debug_method ) $this->syslog( __FUNCTION__, __LINE__, "(warning) Custom parse" );

          $parser = new GenericParseUtility();

          // Defer parsing by setting the URL custom_parse flag in DB
          $structure = $is_custom_parse
            ? array()
            : $parser->set_parent_url($url->get_url())->parse_html($pagecontent,$url->get_response_header())
            ;

          // Only process <body> tag content
          $body_content = $is_custom_parse
            ? NULL
            :  $parser->fetch_body_generic_cleanup($pagecontent)
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

          //Moved up in the execution foc
          //$invocation_delta = microtime(TRUE);

          foreach ( $handler_list as $handler_type => $method_name ) {/*{{{*/
            // Break on first match
            if ( method_exists($this, $method_name) ) {/*{{{*/

              $matched |= TRUE;

              $parser->trigger_linktext = $linktext;
              $parser->from_network     = $network_fetch;
              $parser->update_existing  = $this->update_existing;
              $parser->json_reply       = $json_reply;
              $parser->target_url       = $target_url;
              $parser->metalink_url     = $target_url; 
              $parser->metalink_data    = array_element($metalink,'_LEGISCOPE_');

              $parser->linkset          = $linkset;
              $parser->cluster_urldefs  = $cluster_urls;
              $parser->urlhashes        = $urlhashes;

              if ( $this->debug_memory_usage_delta ) {
                $this->syslog(__FUNCTION__,__LINE__,"(marker) Invoking {$method_name} - Memory usage " . memory_get_usage(TRUE) . ' for ' . $url->get_url() );
              }
              $this->$method_name($parser, $body_content, $url);

              $linkset    = $parser->linkset;
              $json_reply = $parser->json_reply; // Merged with response JSON
              $target_url = $parser->target_url;

              break;
            }/*}}}*/
          }/*}}}*/

          $invocation_delta = round(microtime(TRUE) - $invocation_delta,3);
          $json_reply['timedelta'] = $invocation_delta;

          if ( $this->debug_memory_usage_delta ) {
            $this->syslog(__FUNCTION__,__LINE__,"(marker)  Invoked {$method_name} - Memory usage " . memory_get_usage(TRUE) . " delta {$invocation_delta}" );
          }

          if ( !$matched ) {
            $this->syslog( __FUNCTION__, __LINE__, "No custom handler for path " . $url->get_url());
          }

          if ( $debug_method ) {
            $this->syslog(__FUNCTION__,__LINE__,"(warning)  Deallocated parser; Memory usage " . memory_get_usage(TRUE) );
          }

        }/*}}}*/

      }/*}}}*/

      if ( is_null($freeze_referrer) ) {/*{{{*/
        $_SESSION['referrer'] = $target_url;
      }/*}}}*/
      else {/*{{{*/
        if ( $debug_method ) $this->syslog( __FUNCTION__, __LINE__, "(marker) Freeze referrer {$referrer} for " . $url->get_url());
      }/*}}}*/

      $final_body_content = preg_replace(
        array(
          '@<[/]*(body|html|style)([^>]*)>@imU',
          '/>([ ]*)</m',
          '/<!--(.*)-->/imUx',
          '@<noscript>(.*)</noscript>@imU',
          //'@<script([^>]*)>(.*)</script>@imU',
          "@\&\#10;@",
          "@\&\#9;@",
          "@\n@",
        ),
        array(
          '',
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
        ? 'processed'
        : 'responseheader'
        ;
      if ( 0 < ($age) ) {
        $age = time() - $age; // Seconds delta
        $minutes = intval($age / 60);
        $hours   = intval($minutes / 60);
        $days    = intval($hours / 24);

        $minutes = $minutes % 60;
        $hours   = $hours % 24;
        $age = "{$days}d {$hours}h {$minutes}m";
      } else {
        $age = '--';
      }

      $json_reply = array_merge(
        array(
          'url'            => $displayed_target_url,
          'referrer'       => $referrer,
          'httpcode'       => $headers['http-response-code'], 
          'contenthash'    => $url->get_content_hash(),
          'urlhash'        => $url->get_urlhash(),
          'contenttype'    => $url->get_content_type(),
          'linkset'        => $linkset,
          'defaulttab'     => $defaulttab, 
          'clicked'        => $target_url_hash,
          'content'        => C('DISPLAY_ORIGINAL') ? $final_body_content : '',
          'age'            => $url->get_logseconds_css(), 
          'lastupdate'     => $age,
          'hoststats'      => $this->get_hostmodel()->substitute(<<<EOH

<ul id="wp-admin-bar-root-legiscope" class="ab-top-secondary ab-top-menu">
<li class="wp-admin-bar-indicator"><b>Host</b>: {hostname}</li>
<li class="wp-admin-bar-indicator"><b>Hits</b>: {hits}</li>
<li class="wp-admin-bar-indicator"><b>Last Update</b>: {$age}</li>
</ul>

EOH
          )
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
      $this->syslog(__FUNCTION__,__LINE__,'(critical)  System-generated warning messages trapped');
      $this->recursive_dump($output_buffer,'(critical)');
    }/*}}}*/

    $this->exit_cache_json_reply($json_reply,get_class($this));

  }/*}}}*/

  function common_unhandled_page_parser(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    $this->syslog( __FUNCTION__, __LINE__, "Invoked for " . $urlmodel->get_url() );
    /** SVN #418 (internal): Loading raw page content breaks processing of committee information page **/
    $common = new GenericParseUtility();
    $common->
      set_parent_url($urlmodel->get_url())->
      parse_html($urlmodel->get_pagecontent(),$urlmodel->get_response_header());
    $pagecontent = str_replace('[BR]','<br/>',join('',$common->get_filtered_doc()));
    // $parser->json_reply = array('retainoriginal' => TRUE);
  }/*}}}*/

  /** Standard home page **/

  function generate_home_page(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    // HOME PAGE OVERRIDDEN BY NODE GRAPH
    // http://www.senate.gov.ph
    $this->common_unhandled_page_parser($parser,$pagecontent,$urlmodel);

    $map_url = LEGISCOPE_ADMIN_IMAGES_URLBASE . "/philippines-4c.svg"; 

    $svg_inline = $this->transform_svgimage(SYSTEM_BASE . "/../images/admin/philippines-4c.svg"); 

    // Transform geomap into regular, embeddable SVG
    // Transform geomap into interactive object (clickable)
    // Create geomap animation methods
    // Generate static Legiscope object map, rooted at SenateBillDocumentModel
    // Generate node animation methods
    // Generate interactive LOM (clickable nodes)
    // Generate LOM clickable edges

    $altcontent = str_replace(
      array(
        '{alternate-content}',
        '{initial-load-content}',
      ),
      array(
        $pagecontent,
        str_replace(
          array(
            '{svg_inline}',
            '{scale}'
          ),
          array(
            $svg_inline,
            '2.4' 
          ),
          $this->get_template('map.html','global')
        ),
      ),
      $this->get_template('index.html')
    );
    $pagecontent = <<<EOH
{$altcontent}
EOH;
    $parser->json_reply = array(
      'rootpage' => TRUE
    );
  }/*}}}*/

  /** Republic Act PDF intercept **/

  // FIXME: Consider adding an intermediate inheritance hierarchy successor node

  function republic_act_pdf_intercept(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/

    $linktext    = $parser->trigger_linktext;
    $pagecontent = "Republic Act Meta Information Page [{$linktext}]";

    // TODO: Create separate View container classes
    $republic_act = new RepublicActDocumentModel();
    $republic_act->
      join_all()->
      where(array('AND' => array('sn' => $linktext)))->
      recordfetch_setup();

    if ( $republic_act->recordfetch($ra,TRUE) ) {

      $legislation_precursors = array();
      do {/*{{{*/
        // Only generate precursor entries if at least one of
        // - hb_precursors
        // - sb_precursors
        // is NULL, and the [origin] attribute names valid precursor docs.
        if (is_array($precursors = $republic_act->find_unresolved_origin_joins($ra))) {
          $legislation_precursors[$ra['id']] = $precursors;
        }
      }/*}}}*/
      while ( $republic_act->recordfetch($ra,TRUE) );

      if ( 0 < ($republic_act->fix_unresolved_origin_joins($legislation_precursors))) {
        // Some entries in the precursor list have been stowed to DB.
      }

      $recorded_time = gmdate('Y/m/d H:i:s',$republic_act->get_create_time());

      $republic_act->get_stuffed_join('sb_precursors');
      $republic_act->get_stuffed_join('hb_precursors');

      $republic_act->set_id(NULL);

      if ( !is_null($content = $republic_act->get_content()) ) {
        $content = json_decode($content,TRUE);
        if ( !is_null($content) && is_array($content) ) {
          array_walk($content,create_function(
            '& $a, $k', '$a = array_key_exists("url",$a) ? "<a href=\"{$a["url"]}\" class=\"legiscope-remote\">{$a["text"]}</a>" : "<p>" . str_replace("[BR]","<br/>",$a["text"]) . "</p>";'
          ));
          if ( 0 < count($content) ) {
            $content = join("\n", $content);
            $republic_act->set_content($content);
          }
          $content = NULL;
        } else {
          // Null out content just before calling $republic_act->substitute()
          $republic_act->set_content('');
        }
      }

      // FIXME: Consider implementing lock-on-substitute Model semantics, 
      // FIXME: to require a programmer to explicitly enable  
      // FIXME: autocommit after contents of the model are modified.
      // FIXME: Behavior would be to throw a (catchable) "SubstituteLocked" exception 
      // FIXME: when an attempt is made to execute either update() or insert().
      // WARNING: Do NOT attempt to update the R.A. record after this point.
      // Modifications have been made to the record to make it suitable for
      // markup substitution.
      $this->recursive_dump($republic_act->get_all_properties(),"(marker) -- GAP --");
 
      // This template uses Join attributes in e.g. sb_precursors.url
      $template = <<<EOH
<h1>{sn}</h1>
<p>{description}</p>
<p><b>Origin</b>: <a class="legiscope-remote" href="{sb_precursors.url}">{sb_precursors.sn}</a> &nbsp; <a class="legiscope-remote" href="{hb_precursors.url}">{hb_precursors.sn}</a></p>
<hr/>
<span><b>Recorded</b>: {$recorded_time}</span><br/>
<span><b>Source</b>: <a href="{url}" target="_legiscope">{url}</a></span><br/>
{content}
<hr/>
<span>Republic Act Meta Information Page [{sn}]</span><br/>
<script type="text/javascript">
jQuery(document).ready(function(){
  jQuery('title').html('{$linktext}');
});
</script>


EOH;
      $pagecontent = $republic_act->substitute($template);
    } else {
      $pagecontent = 'PDF';
    }

    // Mandatory content type override
    $parser->json_reply = array(
      'retainoriginal' => TRUE,
      'contenttype' => 'text/html'
    );
  }/*}}}*/

}
