<?php

class LegiscopeBase extends SystemUtility {

  public static $singleton;
  public static $user_id = NULL;

  var $remote_host = NULL;
  var $user_agent  = NULL;
  var $subject_host_hash = NULL;
  var $seek_cache_filename = NULL;

  function __construct() {/*{{{*/
    parent::__construct();
    $this->session_start_wrapper();

    $target_url = $this->filter_post('url');
    $this->seek_cache_filename = UrlModel::get_url_hash($target_url);
    $this->seek_cache_filename = "./cache/seek-{$this->subject_host_hash}-{$this->seek_cache_filename}.generated";

  }/*}}}*/

  static function & instantiate_by_host() {/*{{{*/
    // Instantiate a singleton, depending on the host URL. 
    // If a class definition filename of the form sub.domain.tld.class.php exists,
    // defining class SubDomainPh (derived from (www.)*sub.domain.tld),
    // then that class (which extends LegiscopeBase) is generated, 
    // instead of LegiscopeBase.
    $request_url = filter_post('url');
    if ( is_null($request_url) ) {
      self::$singleton = new LegiscopeBase();
      return self::$singleton;
    }
    $hostname    = @UrlModel::parse_url($request_url, PHP_URL_HOST);
    $matches     = array();
    $base_regex  = '/^(www\.)?(([^.]+[.]?)*)/i';
    $matchresult = preg_match_all($base_regex, $hostname, $matches);
    // syslog( LOG_INFO, "----------------- " . print_r($matches,TRUE));
    $initcaps    = create_function('$a', 'return ucfirst($a);');
    $classname   = join('',array_map($initcaps, explode('.', $matches[2][0])));
    return (1 == preg_match('/^(www\.)+((denr|dbm|senate|congress)\.)*gov\.ph/i', $hostname)) && @class_exists($classname)
      ? new $classname
      : new LegiscopeBase()
      ;
  }/*}}}*/

  function handle_stylesheet_request() {/*{{{*/
    if ( !is_null($stylesheetname = $this->filter_request('css') ) ) {
      $fn = "css/{$stylesheetname}";
      if ( file_exists( $fn ) ) {
        // $this->syslog( __FUNCTION__, __LINE__, "Emitting script {$fn}" );
        $filehandle = fopen($fn, 'rb');
        header('Content-Type: text/css');
        header('Content-Length: ' . filesize($fn));
        fpassthru($filehandle);
        exit(0);
      }
    }
  }/*}}}*/

  function handle_image_request() {/*{{{*/
    if ( !is_null($filename = $this->filter_request('images') ) ) {
      $fn = "images/{$filename}";
      if ( file_exists( $fn ) ) {
        // $this->syslog( __FUNCTION__, __LINE__, "Emitting script {$fn}" );
        $filehandle = fopen($fn, 'rb');
        header('Content-Type: application/image');
        header('Content-Length: ' . filesize($fn));
        fpassthru($filehandle);
        exit(0);
      }
    }
  }/*}}}*/

  function handle_javascript_request() {/*{{{*/
    if ( !is_null($scriptname = $this->filter_request('js') ) ) {
      $fn = "js/{$scriptname}";
      if ( file_exists( $fn ) ) {
        // $this->syslog( __FUNCTION__, __LINE__, "Emitting script {$fn}" );
        $filehandle = fopen($fn, 'rb');
        header('Content-Type: text/javascript');
        header('Content-Length: ' . filesize($fn));
        fpassthru($filehandle);
        exit(0);
      }
    }
  }/*}}}*/

  final function handle_model_action() {/*{{{*/

    $controller = $this->filter_request('p');
    $action     = $this->filter_request('q');
    $subject    = $this->filter_request('r');

    if ( is_null( $controller ) ) {
      if ( method_exists( $this, $action ) ) {
        $this->$action($subject);  
      }
    }
  }/*}}}*/

  function flush_output_buffer() {/*{{{*/
    $output_buffer = ob_get_clean();
    if ( 0 < strlen($output_buffer) ) {
      // Dump content inadvertently generated during this operation.
      $output_buffer = explode("\n", $output_buffer);
      $this->syslog(__FUNCTION__,__LINE__,'WARNING:  System-generated warning messages trapped');
      $this->recursive_dump($output_buffer,__LINE__);
    }
  }/*}}}*/

  function keyword() {/*{{{*/

    $this->syslog( '----', __LINE__, "----------------------------------");
    $this->syslog( __FUNCTION__, __LINE__, "Invoked from {$_SERVER['REMOTE_ADDR']}" );

    ob_start();

    $referrer     = $this->filter_session('referrer','http://www.congress.gov.ph');

    $hostModel = new HostModel($referrer);
    $referrers = new UrlModel();
    $hostModel->increment_hits()->stow();
    $this->subject_host_hash = UrlModel::get_url_hash($hostModel->get_url(),PHP_URL_HOST);

    $fragment     = $this->filter_post('fragment');
    $decomposer   = '@([^ ]*) ?@i';
    $components   = array();
    $match_result = preg_match_all($decomposer, $fragment, $components);
    $records      = array();

    if ( !($_SESSION['last_fragment'] == $fragment) && count($components) > 0 ) {

      $this->recursive_dump($components,__LINE__);
      $components = array_filter($components[1]);
      $components = join('(.*)', $components);

      $iterator = new RepublicActDocumentModel();
      $iterator->
        where(array('OR' => array(
          'description' => "REGEXP '({$components})'",
          'sn'     => "REGEXP '({$components})'",
        )))->
        recordfetch_setup();
      $record = array();
      $count = 0;
      while ( $iterator->recordfetch($record) ) {
        $records[] = array(
          'description' => preg_replace('@('.$components.')@i','<span class="hilight">$1</span>', $record['description']),
          'sn'          => $record['sn'],
          'hash'        => UrlModel::get_url_hash($record['url']),
          'category'    => 'Republic Acts',
          'url'         => $record['url'],
        );
        if ($count++ > 100) break;;
      }
      $this->syslog( '----', __LINE__, "Republic Acts found: {$count}");
      /////////////////////////////////////////////////
      $iterator = new HouseBillDocumentModel();
      $iterator->
        where(array('OR' => array(
          'description' => "REGEXP '({$components})'",
          'sn'     => "REGEXP '({$components})'",
        )))->
        recordfetch_setup();
      $record = array();
      $count = 0;
      while ( $iterator->recordfetch($record) ) {
        $records[] = array(
          'description' => preg_replace('@('.$components.')@i','<span class="hilight">$1</span>', $record['description']),
          'sn'          => $record['sn'],
          'hash'        => UrlModel::get_url_hash($record['url']),
          'category'    => 'House Bills',
          'url'         => $record['url'],
        );
        if ($count++ > 20) break;;
      }
      $this->syslog( '----', __LINE__, "House Bills found: {$count}");
      /////////////////////////////////////////////////
      $iterator = new SenateBillDocumentModel();
      $iterator->
        where(array('OR' => array(
          'description' => "REGEXP '({$components})'",
          'comm_report_info' => "REGEXP '({$components})'",
          'main_referral_comm' => "REGEXP '({$components})'",
          'subjects' => "REGEXP '({$components})'",
          'sn'     => "REGEXP '({$components})'",
        )))->
        recordfetch_setup();
      $record = array();
      $count = 0;
      while ( $iterator->recordfetch($record) ) {
        $records[] = array(
          'description' => preg_replace('@('.$components.')@i','<span class="hilight">$1</span>', $record['description']),
          'sn'          => $record['sn'],
          'hash'        => UrlModel::get_url_hash($record['url']),
          'category'    => 'Senate Bills',
          'url'         => $record['url'],
        );
        if ($count++ > 20) break;;
      }
      $this->syslog( '----', __LINE__, "Senate Bills found: {$count}");
      /////////////////////////////////////////////////

      foreach ( $records as $dummyindex => $record ) {
        $referrers->fetch($record['url'],'url');
        $records[$dummyindex]['referrers'] = $referrers->referrers('url');
      }
    }

    $_SESSION['last_fragment'] = $fragment;

    $json_reply = array(
      'count' => count($records),
      'records' => $records,
      'regex' => $components,
    );

    $response = json_encode($json_reply);
    header('Content-Type: application/json');
    header('Content-Length: ' . strlen($response));
    $this->flush_output_buffer();
    echo $response;
    exit(0);
  }/*}}}*/

  function reorder() {/*{{{*/

    $this->syslog( __FUNCTION__, __LINE__, "(marker) ---------------------------------- Invoked from {$_SERVER['REMOTE_ADDR']}" );

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
    $clusterid = $cluster_id_parts[3];
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
    $this->recursive_dump($json_reply,'(marker)');

    $response = json_encode($json_reply);
    header('Content-Type: application/json');
    header('Content-Length: ' . strlen($response));
    $this->flush_output_buffer();
    echo $response;
    exit(0);

  }/*}}}*/

  function preload() {/*{{{*/

    $this->syslog( '----', __LINE__, "----------------------------------");
    $this->syslog( __FUNCTION__, __LINE__, "Invoked from {$_SERVER['REMOTE_ADDR']}" );

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
    );

    $lie_table = '@' . join('|', $lie_table) . '@i';

    $hash_count = 0;

    foreach( $link_hashes as $hash ) {
      $url->fetch($hash, 'urlhash');
      $urlstring = $url->get_url();
      $in_lie_table = (1 == preg_match($lie_table,$url->get_url()));
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

    $response = json_encode($json_reply);
    header('Content-Type: application/json');
    header('Content-Length: ' . strlen($response));
    $this->flush_output_buffer();
    echo $response;
    exit(0);

  }/*}}}*/

  function fetchpdf($a) {/*{{{*/

    $this->syslog( '----', __LINE__, "----------------------------------");

    ob_start();

    $url = new UrlModel();
    $url->fetch($a,'content_hash');
    $target_url = $url->get_url();
    $content_type = $url->get_content_type();

    $this->syslog( __FUNCTION__, __LINE__, "Doctype '{$content_type}' passed: '{$a}', from {$_SERVER['REMOTE_ADDR']} " . session_id() . " <- {$target_url}" );

    $output_buffer = ob_get_clean();
    if ( 0 < strlen($output_buffer) ) {
      // Dump content inadvertently generated during this operation.
      $output_buffer = explode("\n", $output_buffer);
      $this->syslog(__FUNCTION__,__LINE__,'WARNING:  System-generated warning messages trapped');
      $this->recursive_dump($output_buffer,__LINE__);
    }

    if ( 1 == preg_match('@^application/pdf@i', $content_type) ) {
      $this->syslog( __FUNCTION__, __LINE__, "(marker) Emitting PDF '{$a}' to {$_SERVER['REMOTE_ADDR']} " . session_id() . " <- {$target_url}" );
      header("Content-Type: " . $content_type);
      header("Content-Length: " . $url->get_content_length());
      echo $url->get_pagecontent();
      exit(0);
    }

    header("HTTP/1.1 404 Not Found");
    exit(0);

  }/*}}}*/

  function exit_cache_json_reply(array & $json_reply, $class_match = 'LegiscopeBase') {/*{{{*/
    if ( get_class($this) == $class_match ) {/*{{{*/
      $pagecontent = json_encode($json_reply);
      header('Content-Type: application/json');
      header('Content-Length: ' . strlen($pagecontent));
      $this->flush_output_buffer();
      if ( C('ENABLE_GENERATED_CONTENT_BUFFERING') || ($cache_force == 'true') ) {
        file_put_contents($this->seek_cache_filename, $pagecontent);
      }
      echo $pagecontent;
      exit(0);
    }/*}}}*/
  }/*}}}*/

  function exit_emit_cached_content($target_url, $cache_force, $network_fetch) {/*{{{*/
    if ( FALSE === ( $this->subject_host_hash = UrlModel::get_url_hash($target_url,PHP_URL_HOST) ) ) {
      $this->syslog( __FUNCTION__,__LINE__,"(marker) Odd. We did not receive a 'url' POST value.  Nothing to do.");
      header('HTTP/1.0 404 Not Found');
      exit(0);
    }

    if ( (C('ENABLE_GENERATED_CONTENT_BUFFERING') || ($cache_force == 'true')) && (!$network_fetch) && is_null($metalink) ) {/*{{{*/
      if ( file_exists($this->seek_cache_filename) && !$network_fetch ) {
        $this->syslog( __FUNCTION__, __LINE__, "(marker) Emitting cached markup for {$target_url} <- {$this->seek_cache_filename}" );
        header('Content-Type: application/json');
        header('Content-Length: ' . filesize($this->seek_cache_filename));
        $this->flush_output_buffer();
        echo file_get_contents($this->seek_cache_filename);
        exit(0);
      }
    }/*}}}*/
  }/*}}}*/

  function seek() {/*{{{*/

    // Perform an HTTP GET
    ob_start();

    $json_reply      = array();
    $modifier        = $this->filter_post('modifier');
    $metalink        = $this->filter_post('metalink');
    $linktext        = $this->filter_post('linktext');
    $target_url      = $this->filter_post('url');
    $freeze_referrer = $this->filter_post('fr');
    $cache_force     = $this->filter_post('cache');
    $referrer        = $this->filter_session('referrer');
    $url             = new UrlModel($target_url, TRUE);
    $parser          = new GenericParseUtility();


    $this->syslog( __FUNCTION__,__LINE__,"(marker) Use cache = {$cache_force} ----------------------------------");

    $network_fetch  = ($modifier == 'reload' || $modifier == 'true');
    $displayed_target_url = $target_url;

    $this->exit_emit_cached_content($url, $cache_force, $network_fetch);
  
    $session_has_cookie = $this->filter_session("CF{$this->subject_host_hash}");

    $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked from {$_SERVER['REMOTE_ADDR']} " . session_id() . " <- {$target_url} ('{$linktext}') [{$session_has_cookie}]" );

    $faux_url = $parser->get_faux_url($url, $metalink);

    $this->syslog(__FUNCTION__,__LINE__, "(marker) Created fake URL ({$in_db}) {$faux_url} from components, mapping {$faux_url} <- {$target_url}" );
    $this->recursive_dump($metalink,'(marker) Metalink URL src');

    $json_reply = array(
      'url'            => $target_url,
      'error'          => '',
      'message'        => '', 
      'markup'         => '',
      'responseheader' => '',
      'httpcode'       => '400',
      'original'       => '<pre>NO CONTENT RETRIEVED</pre>',
      'defaulttab'     => 'original',
      'referrer'       => $referrer,
      'contenttype'    => 'unknown',
    );

    $urlhash        = $url->get_urlhash();
    $network_fetch  = ($modifier == 'reload' || $modifier == 'true') || !$url->in_database();
    $content_length = $url->get_content_length();
    $retrieved      = $url->in_database();
    $action         = $retrieved && !$network_fetch
      ? "DB Retrieved {$content_length} octets"
      : "Reloading"
      ;

		if ( $retrieved ) $url->increment_hits()->stow();

    if ( $network_fetch ) {/*{{{*/

      if ( !is_null($faux_url) ) $this->syslog(__FUNCTION__,__LINE__,"(marker) Faux URL present - {$faux_url}");
      $retrieved = $this->perform_network_fetch( $url, $referrer, $target_url, $faux_url, $metalink );
      $action = $retrieved
        ? "(marker) Retrieved " . $url->get_content_length() . ' octet ' . $url->get_content_type()
        : "WARNING Failed to retrieve"
        ;
      if (!$retrieved) {/*{{{*/

        $json_reply['error']          = CurlUtility::$last_error_number;
        $json_reply['message']        = CurlUtility::$last_error_message;
        $json_reply['responseheader'] = CurlUtility::$last_error_message;
        $json_reply['defaulttab']     = 'responseheader';
        $this->syslog( __FUNCTION__, __LINE__, "WARNING: Failed to retrieve {$target_url}" );
        $this->recursive_dump(CurlUtility::$last_transfer_info,'(error) ');

      }/*}}}*/

    }/*}}}*/

    $pagecontent    = $url->get_pagecontent();
    $responseheader = '...';
    $headers        = $url->get_response_header(TRUE);

    $body_content   = NULL;

    $this->syslog( __FUNCTION__, __LINE__, "(marker) Network fetch " . ($network_fetch ? 'OK' : 'NO') . ", successful " . ($retrieved ? 'YES' : 'NO'));

    $headers['legiscope-regular-markup'] = 0;

    if ( !$retrieved ) {/*{{{*/

      // Unsuccessful fetch attempt
      $cache_filename = $url->get_cache_filename();
      $this->syslog( __FUNCTION__, __LINE__, "WARNING ********** Removing {$url}. Must remove cache file {$cache_filename}" );
      if ( file_exists($cache_filename) ) unlink($cache_filename);
      $url->remove();

    }/*}}}*/
    else {/*{{{*/

      $json_reply  = array(); // May be overridden by per-site handler, see below
      $subject_url = is_null($faux_url) ? $target_url : $faux_url;

      $this->syslog( __FUNCTION__, __LINE__, "{$action} response from {$subject_url} <- '{$referrer}' for {$_SERVER['REMOTE_ADDR']}:{$this->session_id}");

      if ( !is_null($faux_url) ) {/*{{{*/// If we've loaded content from an ephemeral URL, use the POST target page action for generating links, rather than the fake URL.
        $url->set_url($target_url,FALSE);
      }/*}}}*/

      $responseheader = $url->get_response_header(FALSE,'<br/>');

      if ( array_key_exists('content-type', $headers) ) {

        if ( 1 == preg_match('@^text/html@i', $headers['content-type']) ) {/*{{{*/

          $headers['legiscope-regular-markup'] = 1;

          $is_custom_parse = $url->is_custom_parse();

          if ( $is_custom_parse ) $this->syslog( __FUNCTION__, __LINE__, "(warning) Custom parse" );

          // Defer parsing by setting the URL custom_parse flag in DB
          $structure = $is_custom_parse
            ? array()
            : $parser->set_parent_url($url->get_url())->parse_html($pagecontent,$url->get_response_header())
            ;

          // Only process <body> tag content
          $body_content = $parser->fetch_body_generic_cleanup($pagecontent);

          // Custom-parsed page handlers must generate navigation links
          $linkset = $is_custom_parse
            ? array('linkset' => array(),'urlhashes' => array(),'cluster_urls' => array())
            : $parser->generate_linkset($url->get_url())
            ;

          extract($linkset); // 'linkset', 'urlhashes', 'cluster_urls'

          $body_content = htmlspecialchars_decode($body_content, ENT_NOQUOTES | ENT_HTML401);

          $handler_list = $this->get_handler_names($url);

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

              $parser->linkset          = $linkset;
              $parser->cluster_urldefs  = $cluster_urls;
              $parser->urlhashes        = $urlhashes;

              $this->syslog(__FUNCTION__,__LINE__,"(warning) Invoking {$method_name}");
              $this->$method_name($parser, $body_content, $url);

              $linkset        = $parser->linkset;
              $json_reply     = $parser->json_reply; // Merged with response JSON
              $target_url     = $parser->target_url;

              break;
            }/*}}}*/
          }/*}}}*/

          if ( !$matched ) {
            $this->syslog( __FUNCTION__, __LINE__, "No custom handler for path " . $url->get_url());
            $this->recursive_dump($handler_list,__LINE__);
          }

        }/*}}}*/
        else if ( 1 == preg_match('@^application/pdf@i', $headers['content-type']) ) {/*{{{*/
          $body_content = 'PDF';
          $body_content_length = $url->get_content_length();
          $this->syslog( __FUNCTION__, __LINE__, "PDF fetch {$body_content_length} from " . $url->get_url());
          $headers['legiscope-regular-markup'] = 0;
          // Attempt to reload PDFs in an existing block container (alternate 'original' rendering block)
          $json_reply = array('retainoriginal' => 'true');
          $this->syslog( __FUNCTION__, __LINE__, "(warning) PDF loader, retain original frame");
        }/*}}}*/
      }

      if ( is_null($freeze_referrer) ) {/*{{{*/
        $_SESSION['referrer'] = $target_url;
      }/*}}}*/
      else {/*{{{*/
        $this->syslog( __FUNCTION__, __LINE__, "(marker) Freeze referrer {$referrer} for " . $url->get_url());
      }/*}}}*/

      // Finally, reload the model so that it reflects any parser updates
      $url->fetch($url->get_url(),'url');

      $current_linktext = $url->get_linktext();

      if ( !is_null($linktext) && (!(0 < strlen($current_linktext)) || (1 == preg_match('@^\[@',$linktext))) && ($linktext != $current_linktext)  ) {/*{{{*/
        $this->syslog( __FUNCTION__, __LINE__, "(marker) Stowing link text '{$linktext}' (currently '{$current_linktext}') for " . $url->get_url());
        $url->set_linktext($linktext)->stow();
      }/*}}}*/

      $final_body_content = preg_replace(
        array(
          '/<html([^>]*)>/imU',
          '/>([ ]*)</m',
          '/<!--(.*)-->/imUx',
          '@<noscript>(.*)</noscript>@imU',
          '@<script([^>]*)>(.*)</script>@imU',
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
          '',
          "",
          " ",
          "",
        ),
        $body_content
      );

      $this->syslog( __FUNCTION__, __LINE__, "(marker) Transmissible content length: " . strlen($body_content) );
      $this->syslog( __FUNCTION__, __LINE__, "(marker) Transmissible final length: " . strlen($final_body_content) );

      $final_content = str_replace(
        array('<br/>'  , '><'  , '<'  , '>'   , " "     , '{BREAK}'),
        array('{BREAK}', ">{BREAK}<", '&lt;', '&gt;', "&nbsp;", '<br/>')  ,
        $body_content
      ); 

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
          'defaulttab'     => array_key_exists($headers['http-response-code'],array_flip(array(100,200,302,301))) ? 'original' : 'responseheader',
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

    $this->exit_cache_json_reply($json_reply,'LegiscopeBase');

    return $json_reply;
  }/*}}}*/

  protected function get_handler_names(UrlModel & $url) {/*{{{*/

    // Construct post-processing method name from path parts
    $debug_method = TRUE;
    $urlhash     = $url->get_urlhash();
    $urlpathhash = UrlModel::parse_url($url->get_url());
    $urlpathhash_sans_script = $urlpathhash; 
    $urlpathhash_sans_script['query'] = NULL; 
    $urlpathhash_sans_script['fragment'] = NULL; 
    $url_sans_script = UrlModel::recompose_url($urlpathhash_sans_script,array(),TRUE);
    // Retain script tail, only remove query part
    if ( array_key_exists('query',$urlpathhash) ) $urlpathhash['query'] = NULL;
    $urlpathhash = UrlModel::recompose_url($urlpathhash);

    $seek_postparse_pathonly_method  = "seek_postparse_bypathonly_" . UrlModel::get_url_hash($url_sans_script);
    $seek_postparse_path_method      = "seek_postparse_bypath_" . UrlModel::get_url_hash($urlpathhash);
    $seek_postparse_method           = "seek_postparse_{$urlhash}";
    $seek_postparse_querytype_method = "seek_postparse_{$urlhash}";

    if ( 1 == count($cluster_urls) ) {/*{{{*/
      $cluster_urls = array_values($cluster_urls);
      $cluster_urls = $cluster_urls[0];
      $no_params = preg_replace('@\({PARAMS}\)@','', $cluster_urls['query_template']);
      $url_query_parts = UrlModel::decompose_query_parts($no_params);
      if ( 0 < count($url_query_parts) ) {
        $url_query_components = array();
        foreach( $url_query_parts as $p1 => $p2 ) {
          $url_query_components[] = $p1;
          $url_query_components[] = $p2;
        }
        $url_query_parts = join('_', array_filter($url_query_components));
        $seek_postparse_querytype_method = strtolower("seek_postparse_{$url_query_parts}");
      }
    }/*}}}*/
    else {/*{{{*/
      $url_query_parts = UrlModel::parse_url($url->get_url(), PHP_URL_QUERY);
      $url_query_parts = UrlModel::decompose_query_parts($url_query_parts);
      if ( (0 < count($url_query_parts)) ) {
        // Construct post-parse handler name from query parts
        $seek_postparse_querytype_method = array();
        foreach ( $url_query_parts as $query_paramname => $query_paramval ) {
          $seek_postparse_querytype_method[] = trim($query_paramname);
          $seek_postparse_querytype_method[] = trim($query_paramval);
        }
        $seek_postparse_querytype_method = array_filter($seek_postparse_querytype_method);
        $url_query_parts = join('_',$seek_postparse_querytype_method);
        $seek_postparse_querytype_method = strtolower("seek_postparse_{$url_query_parts}");
      }
    }/*}}}*/ 

    $method_map = array(
      'by-fullpath' => $seek_postparse_method,
      'by-query'    => $seek_postparse_querytype_method,
      'by-noquery'  => $seek_postparse_path_method,
      'by-path'     => $seek_postparse_pathonly_method,
    );
    // 2nd-level parse; WordPress uses URL component routing,
    // so we attempt to create a map using path components.

    $url_components = UrlModel::parse_url($url->get_url());
    $url_map = array();
    $method_name = NULL;

    foreach ( explode('/', $url_components['path']) as $path_part ) {
      $method_name = NULL;
      if ( empty($path_part) ) continue;
      $url_map[] = $path_part;
      $test_path = '/' . join('/',$url_map);
      $test_url = $url_components;
      $test_url['path'] = $test_path;
      $test_url = UrlModel::recompose_url($test_url);
      $test_path = str_replace('/','-',$test_path);
      $method_map['by-path-' . count($url_map) . "{$test_path}"] = 'seek_by_pathfragment_' . UrlModel::get_url_hash($test_url);
    }

    $method_map['generic'] = 'common_unhandled_page_parser';

    $method_list = array();

    // Do not include unimplemented methods
    foreach ( $method_map as $method_type => $method_name ) {
      if ( array_key_exists($method_name, array_flip($method_list)) ) continue;
      if ( method_exists($this, $method_name) ) {
        $method_list[$method_type] = $method_name;
        if ( $debug_method ) $this->syslog(__FUNCTION__,__LINE__,"{$method_type} handler '{$method_name}' present." );
      } else {
        if ( $debug_method ) $this->syslog(__FUNCTION__,__LINE__,"(marker) {$method_type} handler '{$method_name}' missing." );
      }
    }

    return $method_list;

  }/*}}}*/

  function extract_form($containers) {/*{{{*/
    $debug_method = FALSE;
    $extract_form   = create_function('$a', 'return array_key_exists("tagname", $a) && ("FORM" == strtoupper($a["tagname"])) ? $a : NULL;');
    $paginator_form = array_values(array_filter(array_map($extract_form, $containers)));
    if ( $debug_method ) $this->recursive_dump($paginator_form,'(marker) Old');
    return $paginator_form;
  }/*}}}*/

  function perform_network_fetch( & $url, $referrer, $target_url, $faux_url, $metalink, $debug_dump = FALSE ) {/*{{{*/

    $debug_dump = FALSE;
    $session_has_cookie = $this->filter_session("CF{$this->subject_host_hash}");
    if ( $debug_dump ) {/*{{{*/
      $this->syslog(__FUNCTION__,__LINE__,"(marker) Referrer: {$referrer}");
      $this->syslog(__FUNCTION__,__LINE__,"(marker) Target URL: {$target_url}");
      $this->syslog(__FUNCTION__,__LINE__,"(marker) Metalink URL: {$faux_url}");
      $this->syslog(__FUNCTION__,__LINE__,"(marker) Metalink data:");
      $this->recursive_dump($metalink,__LINE__);
    }/*}}}*/
    $curl_options = array(
      CURLOPT_COOKIESESSION  => is_null($session_has_cookie),
      CURLOPT_COOKIEFILE     => "./cache/legiscope.{$this->subject_host_hash}.cookies",
      CURLOPT_COOKIEJAR      => "./cache/legiscope.{$this->subject_host_hash}.cookiejar",
      CURLOPT_FOLLOWLOCATION => TRUE, // TODO:  Control redirection
      CURLOPT_REFERER        => $referrer,
      CURLINFO_HEADER_OUT    => TRUE,
      CURLOPT_MAXREDIRS      => 5,
    );
    if ( !is_null($session_has_cookie) ) {
      $curl_options[CURLOPT_COOKIE] = $session_has_cookie;
    }
    $url_copy = str_replace(' ','%20',$target_url); // CurlUtility methods modify the URL parameter (passed by ref)
    $skip_get         = FALSE;
    $successful_fetch = FALSE;

    if ( is_array($metalink) ) {/*{{{*/
      // FIXME:  SECURITY DEFECT: Don't forward user-submitted input 
      if ( $debug_dump ) {/*{{{*/
        $this->syslog( __FUNCTION__, __LINE__, "(marker) Execute POST to {$url_copy}" );
        $this->recursive_dump($metalink,'(marker)');
      }/*}}}*/
			if ( array_key_exists('_', $metalink) && $metalink['_'] == 1 ) {
				// Pager or other link whose behavior depends on antecedent state.
				// See extract_pager_links() 
				$skip_get = FALSE;
			} else {
				$response = CurlUtility::post($url_copy, $metalink, $curl_options);
				if ( array_key_exists(intval(CurlUtility::$last_transfer_info['http_code']),
					array_flip(array(100,302,301,504))))
					$skip_get = FALSE;
				else
					$skip_get = TRUE;
				$url_copy = str_replace(' ','%20',$target_url); // CurlUtility methods modify the URL parameter (passed by ref)
				$successful_fetch = CurlUtility::$last_error_number == 0;
				$this->recursive_dump(CurlUtility::$last_transfer_info, "(marker) POST");
			}
    }/*}}}*/

    if ( !$skip_get ) {/*{{{*/
      if ( $debug_dump ) {/*{{{*/
        $this->syslog( __FUNCTION__, __LINE__, "(marker) Execute GET/HEAD to {$url_copy}" );
      }/*}}}*/
      $response = $modifier == 'true'
        ? CurlUtility::head($url_copy, $curl_options)
        : CurlUtility::get($url_copy, $curl_options)
        ;
      $this->recursive_dump(CurlUtility::$last_transfer_info, "(marker) GET/HEAD");
      $successful_fetch = CurlUtility::$last_error_number == 0;
    }/*}}}*/

    if ( $successful_fetch && is_array(CurlUtility::$last_transfer_info) && array_key_exists('request_header', CurlUtility::$last_transfer_info) ) {/*{{{*/
      // Extract cookie from cURL request_header
      $cookie_extract = create_function('$a', 'return 1 == preg_match("@^Cookie:@",$a) ? trim(preg_replace("@^Cookie:(.*)$@i","$1",$a)) : NULL;');
      $cookie_lines   = array_values(array_filter(array_map($cookie_extract,CurlUtility::$last_transfer_info['request_header'])));
      if ( 0 < count($cookie_lines) ) {
        $curl_cookie = array();
        foreach ( $cookie_lines as $cookie_item ) {
          $this->syslog( __FUNCTION__, __LINE__, "(marker) Extracting cookie {$cookie_item}" );
          $cookie_parts = array();
          if (!( 0 < intval(preg_match_all('@([^=]*)=([^;]*)[; ]?@',$cookie_item,$cookie_parts,PREG_SET_ORDER)))) continue;
          // $this->recursive_dump($cookie_parts,__LINE__);
          foreach ( $cookie_parts as $cookie_components ) {
            $cookie_components[1] = trim($cookie_components[1]);
            $curl_cookie[] = "{$cookie_components[1]}={$cookie_components[2]}";
          }
        }
        if ( 0 < count($curl_cookie) ) $_SESSION["CF{$this->subject_host_hash}"] = join('; ', $curl_cookie);
      }
    }/*}}}*/

    if ( $successful_fetch ) {/*{{{*/
      // If we used a metalink to specify POST action parameters, change to the faux URL first
      if ( is_array($metalink) ) {
         $url->set_is_fake(TRUE)->set_url($faux_url,FALSE);
      }
      // Split response into header and content parts
      $transfer_info = CurlUtility::$last_transfer_info;
      $http_code = array_key_exists('http_code', $transfer_info) ? intval($transfer_info['http_code']) : 400;
      if ( 400 <= $http_code && $http_code < 500 ) {
        $this->syslog( __FUNCTION__, __LINE__, "(warning) cURL last_transfer_info" );
        $this->recursive_dump($transfer_info, "(warning) HTTP {$http_code}");
        $successful_fetch = FALSE;
      } else {
        // Store contents to disk (DB/cache file)
        $page_content_result = $url->set_pagecontent($response); // No stow() yet
        if ( $debug_dump ) {/*{{{*/
          $hash = $url->get_content_hash();
          $this->syslog( __FUNCTION__, __LINE__, "(marker) Result [{$hash}] set_pagecontent for " . $url->get_url() );
          $this->recursive_dump($page_content_result,"(marker)");
        }/*}}}*/
        $url->set_last_fetch(time());
        $url->set_linktext($linktext)->increment_hits()->stow();
        if ( is_array($metalink) ) {
          $contenthash = sha1($response);
          $faux_url_instance = new UrlModel($faux_url,TRUE);
          $faux_url_instance->set_pagecontent($response);
          $faux_stow = $faux_url_instance->set_is_fake(TRUE)->stow();
          $this->syslog( __FUNCTION__, __LINE__, "(marker) Stowing content [{$contenthash}] to fake URL {$faux_url} " );

          $url->fetch($target_url,'url');
          $url->set_pagecontent($response);
          $this->syslog( __FUNCTION__, __LINE__, "(marker) Assigned content [{$contenthash}] to target URL {$target_url}" );
        }
        // $this->syslog( __FUNCTION__, __LINE__, "Final content length: " . strlen($url->get_pagecontent()) );
        // $this->syslog( __FUNCTION__, __LINE__, "Final content SHA1: " . sha1($url->get_pagecontent()) );
      }
    }/*}}}*/

    return $successful_fetch;

  }/*}}}*/

  function get_session_cookies($host_hash, $as_array = FALSE) {/*{{{*/
    //
    // Keep a single, unique session key attribute for all test subject hosts.
    // This cookie is reused for subsequent connection attempts to the site,
    // i.e. after login, while moving across links, etc.
    //
    $session_data = $this->filter_session($host_hash);
    $session_data_present = !is_null($session_data);
    $session_cookies = NULL; 
    $cookie_array = array();

    if ( !$session_data_present || !array_key_exists('COOKIES', $session_data) ) {
      $this->syslog(__FUNCTION__, __LINE__, "No cookies for host {$host_hash}");
      $site_cookies = $this->Se()->getAllCookies();
      if (is_array($site_cookies) && (0 < count($site_cookies))) {
        $_SESSION[$host_hash]['COOKIES'] = $site_cookies;
        $session_data = $this->filter_session($host_hash);
        $session_data_present = !is_null($session_data) && is_array($session_data);
        $this->syslog(__FUNCTION__, __LINE__, "Stored session cookies " . ($session_data_present ? 'YES' : 'NO'));
      }
    }

    if ( $session_data_present && is_array($session_data) && array_key_exists('COOKIES', $session_data) ) {
      $this->syslog(__FUNCTION__, __LINE__, "Extracting cookies for host {$host_hash}");
      $extract_cookiepairs = create_function('$a', 'return $a["name"] . "=" . $a["value"];');
      $this->recursive_dump($session_data['COOKIES'],__LINE__);
      $session_cookies = join('; ', array_map($extract_cookiepairs, $session_data['COOKIES']));
    } else {
      $this->syslog(__FUNCTION__, __LINE__, "No key 'COOKIES' found for {$host_hash}");
      $this->recursive_dump($session_data,__LINE__);
    }

    $this->syslog(__FUNCTION__, __LINE__, "Current session cookies: '{$session_cookies}'");

    if ( $as_array ) {
      return is_array($session_data) && array_key_exists('COOKIES', $session_data)
        ? $session_data['COOKIES']
        : array() 
        ;
    } else return $session_cookies;
  }/*}}}*/

}
