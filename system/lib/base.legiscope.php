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

    $this->subject_host_hash = UrlModel::get_url_hash($target_url,PHP_URL_HOST);
    $hostModel = new HostModel($referrer);
    $referrers = new UrlModel();
    $hostModel->increment_hits()->stow();

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
		// Required by generate_linkset
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
			$linkset = $this->generate_linkset($parser->get_containers(), $page->get_url());
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

    $this->recursive_dump($link_hashes,__LINE__);

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

  function seek() {/*{{{*/

    // Perform an HTTP GET

    ob_start();

    $json_reply         = array();
    $modifier           = $this->filter_post('modifier');
    $metalink           = $this->filter_post('metalink');
    $linktext           = $this->filter_post('linktext');
    $target_url         = $this->filter_post('url');
    $freeze_referrer    = $this->filter_post('fr');
    $cache_force        = $this->filter_post('cache');
    $referrer           = $this->filter_session('referrer');

    $this->syslog( __FUNCTION__,__LINE__,"(marker) {$cache_force} ----------------------------------");

    if ( FALSE === ( $this->subject_host_hash = UrlModel::get_url_hash($target_url,PHP_URL_HOST) ) ) {
      // Faux message
			$this->syslog( __FUNCTION__,__LINE__,"(marker) Odd. {$target_url} hash != [$this->subject_host_hash]");
      header('HTTP/1.0 404 Not Found');
      exit(0);
    }

    $network_fetch  = ($modifier == 'reload' || $modifier == 'true');
    $displayed_target_url = $target_url;

    $this->seek_cache_filename = UrlModel::get_url_hash($target_url);
    $this->seek_cache_filename = "./cache/seek-{$this->subject_host_hash}-{$this->seek_cache_filename}.generated";

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

    $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked from {$_SERVER['REMOTE_ADDR']} " . session_id() . " <- {$target_url} ('{$linktext}') [{$session_has_cookie}]" );

		$this->recursive_dump($_POST, 
			( !is_null($modifier) && !($modifier == 'false') ? '(marker)' : '' ) . " " . __LINE__
		);

    $url      = new UrlModel($target_url, TRUE);
    $faux_url = NULL;

    if ( !is_null($metalink) ) {/*{{{*/// Modify $this->seek_cache_filename if POST data is received
      // The POST action may be a URL which permits a GET action,
      // in which case we need to use a fake URL to store the results of 
      // the POST.  We'll generate the fake URL here. 
      $metalink = base64_decode($metalink);
    $metalink = json_decode($metalink, TRUE);
    // Prepare faux metalink URL by combining the metalink components
    // with POST target URL query components. After the POST, a new cookie
    // is returned  
    if ( 0 < count($metalink) ) {/*{{{*/
      $metalink_fix     = create_function('$a', 'return (strlen($a) < 32) ? $a : md5($a);' );
      $query_components = UrlModel::parse_url($url->get_url(),PHP_URL_QUERY);
      $query_components = UrlModel::decompose_query_parts($query_components);
      $query_components = array_filter(array_map($metalink_fix,array_merge($query_components, $metalink)));
      $this->recursive_dump($query_components,__LINE__);
      $query_components = UrlModel::recompose_query_parts($query_components);
      $whole_url = UrlModel::parse_url($url->get_url());
      $whole_url['query'] = $query_components;
      $faux_url = UrlModel::recompose_url($whole_url);
      $in_db = $url->set_url($faux_url,TRUE) ? 'in DB': 'fresh'; // Try to load contents of the faux URL from DB.
      $this->syslog( __FUNCTION__, __LINE__, "Metalink {$in_db} data received len = " . count($metalink) . ", mapping {$faux_url} <- {$target_url}" );
      // Now test for presence of a cached file, now that you have a faux url
      $metalink_cache_filename = UrlModel::get_url_hash($faux_url);
      $metalink_cache_filename = "./cache/seek-{$this->subject_host_hash}-{$metalink_cache_filename}.generated";
      $this->seek_cache_filename = $metalink_cache_filename;

      if ( C('ENABLE_GENERATED_CONTENT_BUFFERING') || ($cache_force == 'true')  ) {/*{{{*/
        if ( $network_fetch ) unlink($metalink_cache_filename);
        else if ( file_exists($metalink_cache_filename) ) {/*{{{*/
          $this->syslog( __FUNCTION__, __LINE__, "Emitting cached markup for {$url} <- {$metalink_cache_filename}" );
          header('Content-Type: application/json');
          header('Content-Length: ' . filesize($metalink_cache_filename));
          $this->flush_output_buffer();
          echo file_get_contents($metalink_cache_filename);
          exit(0);
        }/*}}}*/
      }/*}}}*/
    }/*}}}*/
    else $metalink = NULL;
    }/*}}}*/

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

    $urlhash          = $url->get_urlhash();
    $network_fetch    = ($modifier == 'reload' || $modifier == 'true') || !$url->in_database();
    $content_length   = $url->get_content_length();
    $successful_fetch = $url->in_database();
    $action           = $successful_fetch && !$network_fetch
      ? "DB Retrieved {$content_length} octets"
      : "Reloading"
      ;

    //$selenium_session = $this->get_selenium_session()->session('firefox'); 
    //$this->syslog( __FUNCTION__, __LINE__, "Obtained Selenium object instance '" . get_class($selenium_session) .'"');
    //$remote_image = $selenium_session->screenshot();
    //$cache_filename = $url->get_cache_filename();
    //$success = file_put_contents("{$cache_filename}.png", base64_decode($remote_image));

    if ( $network_fetch ) {/*{{{*/

			if ( !is_null($faux_url) ) $this->syslog(__FUNCTION__,__LINE__,"(marker) Faux URL present - {$faux_url}");
      $successful_fetch = $this->perform_network_fetch( $url, $referrer, $target_url, $faux_url, $metalink );
      $action = $successful_fetch
        ? "(marker) Retrieved " . $url->get_content_length() . ' octet ' . $url->get_content_type()
        : "WARNING Failed to retrieve"
        ;
      if (!$successful_fetch) {/*{{{*/

        $json_reply['error']          = CurlUtility::$last_error_number;
        $json_reply['message']        = CurlUtility::$last_error_message;
        $json_reply['responseheader'] = CurlUtility::$last_error_message;
        $json_reply['defaulttab']     = 'responseheader';
        $this->syslog( __FUNCTION__, __LINE__, "WARNING: Failed to retrieve {$target_url}" );
        $this->recursive_dump(CurlUtility::$last_transfer_info,'(error) ');

      }/*}}}*/

    }/*}}}*/

    $pagecontent    = $url->get_pagecontent();
    $structure_html = '...';
    $responseheader = '...';
    $headers        = $url->get_response_header(TRUE);
    $body_content   = NULL;

    $this->syslog( __FUNCTION__, __LINE__, "Network fetch " . ($network_fetch ? 'OK' : 'NO') . ", successful " . ($successful_fetch ? 'YES' : 'NO'));

    if ( $network_fetch && !$successful_fetch ) {/*{{{*/

      // Unsuccessful fetch attempt
      $cache_filename = $url->get_cache_filename();
      $this->syslog( __FUNCTION__, __LINE__, "WARNING Must remove cache file {$cache_filename}" );
      $url->remove();

    }/*}}}*/
    else if ( !$network_fetch || $successful_fetch ) {/*{{{*/

      $json_reply  = array();
      $subject_url = is_null($faux_url) ? $target_url : $faux_url;

      $this->syslog( __FUNCTION__, __LINE__, "{$action} response from {$subject_url} <- '{$referrer}' cached as {$cached_content} by {$_SERVER['REMOTE_ADDR']}:{$this->session_id}");

      if ( !is_null($faux_url) ) {/*{{{*/
        // If we've loaded content from an ephemeral URL, use the POST target page action for generating links, rather than the fake URL.
        $url->set_url($target_url,FALSE);
      }/*}}}*/

      $responseheader = $url->get_response_header(FALSE,'<br/>');
      $headers['legiscope-regular-markup'] = 0;
      if ( array_key_exists('content-type', $headers) ) {
        if ( 1 == preg_match('@^text/html@i', $headers['content-type']) ) {/*{{{*/

          $parser = new GenericParseUtility();

          $linkset = array(
            'linkset' => array(),
            'urlhashes' => array(),
            'cluster_urls' => array(),
          );

          if ( $url->is_custom_parse() ) {
            // Defer parsing to per-site parsers
            $structure = array();
            $this->recursive_dump($url->get_response_header(),__LINE__);
            $this->syslog( __FUNCTION__, __LINE__, "Deferring parsing to URL-specific parser");
          } 
          else {

            // $this->recursive_dump($url->get_response_header(),__LINE__);
            $this->syslog( __FUNCTION__, __LINE__, "Response headers for {$url}");
            $structure = $parser->set_parent_url($url->get_url())->parse_html($pagecontent,$url->get_response_header()); // Pass by ref parameter; see RawparseUtility::parse_html(& $h)
            if ( $parser->needs_custom_parser() && !$url->is_custom_parse() ) {
              $this->syslog( __FUNCTION__, __LINE__, "Marking for special handling {$url}");
              $url->set_custom_parse(TRUE);
            }
          }

          $body_content = preg_replace(
            array(
              // Remove mouse event handlers
              '@^(.*)\<body([^>]*)\>(.*)\<\/body\>(.*)@mi',
              '@(onmouseover|onmouseout)="([^"]*)"@',
              "@(onmouseover|onmouseout)='([^']*)'@",
            ),
            array(
              '$3', 
              '',
              '',
            ),
            $pagecontent
          );

          $final_content = str_replace(
            array('<br/>'  , '><'  , '<'  , '>'   , " "     , '{BREAK}'),
            array('{BREAK}', ">{BREAK}<", '&lt;', '&gt;', "&nbsp;", '<br/>')  ,
            $body_content
          ); 

          if ( FALSE == $url->is_custom_parse() ) {
            // Build anchor links from normalized link array (complete with scheme and host parts)
            $linkset        = $this->generate_linkset($parser->get_containers(), $url->get_url());
          }
          // $this->syslog( __FUNCTION__, __LINE__, "Linkset");
          extract($linkset); // 'linkset', 'urlhashes', 'cluster_urls'

          $body_content = htmlspecialchars_decode($body_content, ENT_NOQUOTES | ENT_HTML401);
          $handler_list = $this->get_handler_names($url);

          $matched = FALSE;
          foreach ( $handler_list as $handler_type => $method_name ) {/*{{{*/
            if ( method_exists($this, $method_name) ) {/*{{{*/
              $matched |= TRUE;
              $parser->trigger_linktext = $linktext;
              $parser->cluster_urldefs  = $cluster_urls;
              $parser->urlhashes        = $urlhashes;
              $parser->filtered_html    = $pagecontent;
              $parser->from_network     = $network_fetch;
              $parser->structure_html   = $structure_html;
              $parser->json_reply       = $json_reply;
              $parser->cache_filename   = $this->seek_cache_filename;
              $parser->target_url       = $target_url;
              $parser->metalink_url     = $faux_url; 
              $parser->linkset          = $linkset;

              $this->syslog(__FUNCTION__,__LINE__,"(warning) Invoking {$method_name}");
              $this->$method_name($parser, $body_content, $url);
              $url->increment_hits()->stow();

              $linkset        = $parser->linkset;
              $structure_html = $parser->structure_html;
              $json_reply     = $parser->json_reply; // Merged with response JSON
              $target_url     = $parser->target_url;

              break;
            }/*}}}*/
          }/*}}}*/

          if ( C('ENABLE_STRUCTURE_DUMP') == TRUE && FALSE == $url->is_custom_parse() ) {/*{{{*/
            $seek_structure_filename = "{$this->seek_cache_filename}.structure";
            $state = "Fetch";
            if ( !file_exists($seek_structure_filename) || $network_fetch ) {
              $this->recursive_file_dump(
                $seek_structure_filename, 
                $parser->structure_html,0,'-');
              $state = "Wrote";
            }  
            $this->syslog(__FUNCTION__,__LINE__,"(marker) {$state} {$seek_structure_filename}");
            $structure_html = file_get_contents($seek_structure_filename);
          }/*}}}*/

          if ( !$matched ) {
            $this->syslog( __FUNCTION__, __LINE__, "No custom handler for path " . $url->get_url());
            $this->recursive_dump($handler_list,__LINE__);
          }
          $headers['legiscope-regular-markup'] = 1;

        }/*}}}*/
        else if ( 1 == preg_match('@^application/pdf@i', $headers['content-type']) ) {/*{{{*/
          $body_content = 'PDF';
          $body_content_length = $url->get_content_length();
          $this->syslog( __FUNCTION__, __LINE__, "PDF fetch {$body_content_length} from " . $url->get_url());
          $headers['legiscope-regular-markup'] = 0;
          // Attempt to reload PDFs in an existing block container (alternate 'original' rendering block)
          // $json_reply = array('retainoriginal' => 'true');
          $this->syslog( __FUNCTION__, __LINE__, "PDF loader, retain original frame");
        }/*}}}*/
      }

      if ( is_null($freeze_referrer) ) {/*{{{*/
        $_SESSION['referrer'] = $target_url;
      }/*}}}*/
      else {/*{{{*/
        $this->syslog( __FUNCTION__, __LINE__, "(marker) Freeze referrer {$referrer} for " . $url->get_url());
      }/*}}}*/

      $url->fetch($url->get_url(),'url');
      $current_linktext = $url->get_linktext();
      if ( !is_null($linktext) && (!(0 < strlen($current_linktext)) || (1 == preg_match('@^\[@',$linktext))) && ($linktext != $current_linktext)  ) {/*{{{*/
        $this->syslog( __FUNCTION__, __LINE__, "(marker) Stowing link text '{$linktext}' (currently '{$current_linktext}') for " . $url->get_url());
        $url->set_linktext($linktext)->stow();
      }/*}}}*/

      $this->syslog( __FUNCTION__, __LINE__, "(marker) Transmissible content length: " . strlen($body_content) );
      $final_body_content = preg_replace(
        array(
          '/<html([^>]*)>/imU',
          '/>([ ]*)</m',
          '/<!--(.*)-->/imUx',
          '@<noscript>(.*)</noscript>@imU',
          '@<script([^>]*)>(.*)</script>@imU',
        ),
        array(
          '<html>',
          '><',
          '',
          '',
          '',
          '',
        ),
        $body_content
      );
      $this->syslog( __FUNCTION__, __LINE__, "(marker) Transmissible final length: " . strlen($final_body_content) );

      $json_reply = array_merge(
        array(
          'url'            => $displayed_target_url,
          'contenthash'    => $url->get_content_hash(),
          'referrer'       => $referrer,
          'contenttype'    => $url->get_content_type(),
          'linkset'        => $linkset,
          'markup'         => $headers['legiscope-regular-markup'] == 1 ? utf8_encode($final_content) : '[OBSCURED CONTENT]',
          'structure'      => "<pre>{$structure_html}</pre>",
          'responseheader' => $responseheader,
          'httpcode'       => $headers['http-response-code'], 
          'original'       => C('DISPLAY_ORIGINAL') ? $final_body_content : '',
          'defaulttab'     => array_key_exists($headers['http-response-code'],array_flip(array(100,200,302,301))) ? 'original' : 'responseheader',
        ),
        $json_reply
      );

      // $this->recursive_dump($json_reply,__LINE__);
      // Post-processing hacks 
      $member_uuid = $this->filter_post('member_uuid');
      if ( !is_null($member_uuid) && $json_reply['httpcode'] == 200 ) {/*{{{*/
        if ( method_exists($this, 'member_uuid_handler') ) {
          $this->member_uuid_handler($json_reply, $url, $member_uuid);
        }
      }/*}}}*/
    }/*}}}*/

    $output_buffer = ob_get_clean();

    if ( 0 < strlen($output_buffer) ) {/*{{{*/
      // Dump content inadvertently generated during this operation.
      $output_buffer = explode("\n", $output_buffer);
      $this->syslog(__FUNCTION__,__LINE__,'WARNING:  System-generated warning messages trapped');
      $this->recursive_dump($output_buffer,__LINE__);
    }/*}}}*/

    if ( get_class($this) == 'LegiscopeBase' ) {/*{{{*/
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
        // $this->recursive_dump($url_query_parts,__LINE__);
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
      // $this->recursive_dump($url_query_parts,__LINE__);
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
      // $this->syslog(__FUNCTION__,__LINE__,"- Test '{$method_name}' <-- {$test_url}" );
      // if ( method_exists($this, $method_name) ) break;
      // else $method_name = NULL;
    }

    //if ( !is_null($method_name) && method_exists($this, $method_name) ) {
    //  return $this->$method_name($parser, $pagecontent, $urlmodel);
    //}

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

  final function generate_linkset($containers, $url) {/*{{{*/

    $debug_method = FALSE;
    $link_generator = create_function('$a', <<<EOH
return '<li><a class="legiscope-remote {cached-' . \$a["urlhash"] . '}" id="' . \$a["urlhash"] . '" href="' . \$a["url"] . '" title="'.\$a["origpath"] . ' ' . md5(\$a["url"]) . '" target="legiscope">' . (0 < strlen(\$a["text"]) ? \$a["text"] : '[Anchor]') . '</a><span class="reload-texticon legiscope-refresh {refresh-' . \$a["urlhash"] . '}" id="refresh-' . \$a["urlhash"] . '">reload</span></li>';
EOH
		);
    $hash_extractor = create_function('$a', <<<EOH
return array( 'hash' => \$a['urlhash'], 'url' => \$a['url'] ); 
EOH
    );

    $parent_page = new UrlModel($url,TRUE);
		$cluster = new UrlClusterModel();

    // Each container (div, table, or head)  encloses a set of tags
    // Generate clusters of links
    $linkset             = array();
    $urlhashes           = array();
    $pager_clusters      = array();
    $cluster_urls        = array();
    $container_counter   = 0;
    $parent_page_urlhash = $parent_page->get_urlhash();

		$cluster_list = $cluster->fetch_clusters($parent_page,TRUE);

		foreach ( $containers as $container ) {/*{{{*/

			// $this->syslog( __FUNCTION__, __LINE__, "Container #{$container_counter}");
			// $this->recursive_dump($container, __LINE__);

			if ( $container['tagname'] == 'head' ) continue;

			$raw_links            = $this->normalize_links($url, $parser, $container['children']);
			$normalized_links     = array();

			// Deduplication and pager detection (link clusters sharing query parameters)
			$skip_pager_detection = FALSE;
			$query_part_hashes    = array();
			$query_hash           = '';

			foreach ( $raw_links as $linkitem ) {/*{{{*/
				if ( array_key_exists($linkitem['urlhash'], $normalized_links) ) continue;
				// Use a PCRE regex to extract query key-value pairs
				$parsed_url = parse_url($linkitem['url']);
				$query_match = '@([^=]*)=([^&]*)&?@';
				$match_parts = array();
				$query_parts = preg_match_all($query_match, $parsed_url['query'], $query_match);
				unset($parsed_url['query']);
				$query_match = array_filter(array(
					$query_match[1],
					$query_match[2],
				));
				$linkitem['base_url'] = NULL;
				// Iterate through the nonempty set of matched key-value pairs
				if ( is_array($query_match) && ( 0 < count($query_match) ) ) {/*{{{*/
					$query_match = array_combine($query_match[0], $query_match[1]);
					ksort($query_match);
					// Hash all the keys, and count occurrences of that entire set
					$query_hash = UrlModel::get_url_hash(join('!',array_keys($query_match)));
					if ( !array_key_exists($query_hash,$query_part_hashes) ) $query_part_hashes[$query_hash] = array();
					// Then count the occurrence of value elements
					foreach ( $query_match as $key => $val ) {
						if ( !array_key_exists($key, $query_part_hashes[$query_hash]) )
							$query_part_hashes[$query_hash][$key] = array();
						if ( !array_key_exists($val, $query_part_hashes[$query_hash][$key]) )
							$query_part_hashes[$query_hash][$key][$val] = 0;
						$query_part_hashes[$query_hash][$key][$val]++;
					}
					$query_part_hashes[$query_hash]['BASE_URL'] = UrlModel::recompose_url($parsed_url);
					$linkitem['query_parts'] = $query_match;

				}/*}}}*/
				else $skip_pager_detection = TRUE;
				$normalized_links[$linkitem['urlhash']] = $linkitem;
				// $this->syslog(__FUNCTION__, __LINE__, $linkitem['url']);
			}/*}}}*/

			// $this->recursive_dump($normalized_links, __LINE__);
			if ( !($skip_pager_detection || ( 0 < strlen($query_hash)) ) ) {/*{{{*/
				// $this->syslog(__FUNCTION__, __LINE__, "- Skip container: {$query_hash}" );
				// $this->recursive_dump($query_part_hashes[$query_hash],__LINE__);
				continue;
			}/*}}}*/

			if ( $debug_method ) $this->syslog(__FUNCTION__, __LINE__, "(warning) -------- Normalized links, query hash [{$query_hash}]" );

			$linkset_class = array("link-cluster");
			if ( !$skip_pager_detection && ( 0 < strlen($query_hash) ) && is_array($query_part_hashes[$query_hash]) ) {/*{{{*/
				// Evaluate whether this set of URLs is a pager (from a hash of query components)
				$occurrence_count = NULL;
				$variable_params  = array();
				$fixed_params     = array();
				$base_url         = NULL;
				//$this->recursive_dump($pager_clusters, __LINE__);
				foreach ($query_part_hashes[$query_hash] as $query_param_name => $occurrences ) {/*{{{*/
					if ( $query_param_name == 'BASE_URL' ) {
						$base_url = $occurrences;
						// $this->syslog(__FUNCTION__, __LINE__, "-------- Base URL for this set: [{$base_url}]" );
						// $this->recursive_dump($query_part_hashes[$query_hash], __LINE__);
						continue;
					}
					if ( 1 == count($occurrences) ) {
						// If a query part has a fixed value between all links found in the link set,
						// then any other query part which also has a fixed value must 
						// occur the same number of times.
						list( $param_value, $param_occurrences ) = each( $occurrences );
						if ( is_null($occurence_count) ) $occurrence_count = $param_occurrences;
						else if ( $occurrence_count != $param_occurrences ) {
							// One of the [more than 1] query parameters does not occur with the same frequency
							// as the other fixed query parameters for this set of links.
							// We should not treat the set of links as a pager.
						}
						// $this->syslog( __FUNCTION__, __LINE__, "- Key-value pair {$query_param_name}:{$param_value} is a fixed pager parameter occurring {$param_occurrences} times" );
						$fixed_params[] = "{$query_param_name}={$param_value}";
					} else {
						if ( 0 == count($variable_params) ) { 
							$variable_params = array(
								'key'    => $query_param_name,
								'values' => array_keys($occurrences),
							);
							// $this->syslog( __FUNCTION__, __LINE__, "- Key {$query_param_name} is a variable pager parameter with " . count($occurrences) . " keys" );
						} else {
							// At this time (SVN #332) we'll only accept a single variable parameter.
							$occurrence_count = NULL;
							break;
						}
					}
				}/*}}}*/
				if ( !is_null($occurrence_count) && (0 < count($variable_params)) ) {/*{{{*/
					$fixed_params[]  = "{$variable_params['key']}=(". join('|',$variable_params['values']) .")";
					$variable_params = join('&',$fixed_params);
					$fixed_params    = preg_replace('@\(([^)]*)\)@','({PARAMS})', $variable_params); 
					$variable_params = preg_replace('@^(.*)\(([^)]*)\)(.*)@','$2', $variable_params); 
					// Get the hash of the query template
					$component_set_hash = md5($variable_params);
					if ( array_key_exists($query_hash, $cluster_urls) ) {
						$cluster_urls[$query_hash]['query_components'][$component_set_hash] = $variable_params;
					} else {
						$cluster_urls[$query_hash] = array(
							'query_base_url' => $base_url,
							'query_template' => $fixed_params,
							'query_components' => array($component_set_hash => $variable_params),
						);
					}
					$linkset_class[] = 'linkset-pager';
				}/*}}}*/
			}/*}}}*/
			$normalized_links = array_values($normalized_links);
			// Create a unique identifier based on the sorted list of URLs contained in this cluster of links
			if ( 0 == count($normalized_links) ) continue;
			// LINK CLUSTER ID
			$contained_url_set_hash = sha1(join('-',array_filter(array_map(create_function('$a','return $a["urlhash"];'),$normalized_links))));
			$linklist       = join('',array_map($link_generator, $normalized_links));
			$linkset_class  = join(' ', $linkset_class);
			$linkset_id     = "{$this->subject_host_hash}-{$parent_page_urlhash}-{$contained_url_set_hash}";
			$linklist       = "<ul class=\"{$linkset_class}\" id=\"{$linkset_id}\" title=\"Cluster {$contained_url_set_hash}\">{$linklist}</ul>";
			// Reorder URLs by imposing an ordinal key on this array $linkset
			if ( !array_key_exists($contained_url_set_hash, $pager_clusters) ) {
			 	$linkset[$contained_url_set_hash] = "{$linklist}<hr/>";
			}
			$pager_clusters[$contained_url_set_hash] = array_key_exists($contained_url_set_hash,$cluster_list) ? $cluster_list[$contained_url_set_hash]['id'] : NULL;
			$url_hashes     = array_filter(array_map($hash_extractor, $normalized_links));
			if ( !(0 < count($url_hashes) ) ) continue;
			foreach ( $url_hashes as $url_hash_pairs ) {
				$urlhashes[$url_hash_pairs['hash']] = $url_hash_pairs['url'];
			}
			$container_counter++;
		}/*}}}*/

		// Now add clusters missing from the database
		$not_in_clusterlist = array_filter(array_map(create_function(
			'$a', 'return is_null($a) ? 1 : NULL;'
		), $pager_clusters));

		if ( 0 < count($not_in_clusterlist) ) {
			foreach ( $not_in_clusterlist as $clusterid => $nonce ) {
				$cluster->fetch($parent_page, $clusterid);
				$cluster->
					set_clusterid($clusterid)->
					set_parent_page($parent_page->get_urlhash())->
					set_position(100)->
					set_host($this->subject_host_hash)->
					stow();
			}
			// At this point, the list order is updated by fetching the cluster list
			$cluster_list = $cluster->fetch_clusters($parent_page,TRUE);
		}

		// Now use the cluster list to obtain list position
		ksort($cluster_list);
		// Reduce the cluster list to elements that are also in the linkset on this page.
		array_walk($cluster_list,create_function(
			'& $a, $k, $s', '$a = array_key_exists($k,$s) ? $a : NULL;'),
			$linkset);
		$cluster_list = array_filter($cluster_list);
		$cluster_list = array_map(create_function('$a','return $a["position"];'),$cluster_list);
		if ( count($cluster_list) == count($linkset) ) {
			ksort($linkset);
			$linkset = array_combine(
				$cluster_list,
				$linkset
			);
			ksort($linkset);
		} else {
			$this->syslog( __FUNCTION__, __LINE__, "(warning) Mismatch between link and cluster link tables" );
			$this->syslog( __FUNCTION__, __LINE__, "(warning)  Linkset: " . count($linkset) );
			$this->syslog( __FUNCTION__, __LINE__, "(warning) Clusters: " . count($cluster_list) );
		}
    ksort($urlhashes);

    if ( 0 < count($cluster_urls) ) {/*{{{*/
      // $this->syslog(__FUNCTION__, __LINE__, "- Found pager query parameters" );
      // $this->recursive_dump($cluster_urls,__LINE__);
      // Normalize pager query URLs
      foreach( $cluster_urls as $cluster_url_uid => $pager_def ) {
        $urlparts = UrlModel::parse_url($pager_def['query_base_url']);
        $urlparts['query'] = $pager_def['query_template'];
        $cluster_urls[$cluster_url_uid]['whole_url'] = UrlModel::recompose_url($urlparts);
      }
    }/*}}}*/
    // Initial implementation of recordset iterator (not from in-memory array)
    $this->syslog( __FUNCTION__, __LINE__, "-- Selecting a cluster of URLs associated with {$url}" );
    $record = array();
    // FIXME: Break up long queries like this at the caller level.
    // For general-purpose use, limits to the total SQL query length
    // allow for a reasonably usable mechanism for executing 
    // SELECT ... WHERE a IN (<list>)
    // query statements with short lists, without additional code complexity.

    // Partition the list of hashes
    $partition_index  = 0;
    $partitioned_list = array(0 => array());
    foreach ( $urlhashes as $urlhash => $url ) {/*{{{*/
      $partitioned_list[$partition_index][$urlhash] = $url;
      if ( count($partitioned_list[$partition_index]) >= 10 ) {
        // $this->syslog( __FUNCTION__, __LINE__, "- Tick {$partition_index}");
        $partition_index++;
        $partitioned_list[$partition_index] = array();
      }
    }/*}}}*/
    $n = 0;

    $url_cache_iterator = new UrlModel();
    $idlist             = array();
    $linkset            = join("\n", $linkset);

    foreach ( $partitioned_list as $partition ) {  /*{{{*/
      $url_cache_iterator->
        where(array('urlhash' => array_keys($partition)))->
        recordfetch_setup();
      $hashlist = array();
      // Collect all URL hashes.
      // Construct UrlEdgeModel join table entries for extant records. 
      while ( $url_cache_iterator->recordfetch($result) ) {/*{{{*/
        // $this->recursive_dump($result, $h > 2 ? '(skip)' : __LINE__);
        $idlist[] = $result['id'];
        $hashlist[] = $result['urlhash'];
        $n++;
      }/*}}}*/
      $hashlist = join('|', $hashlist);
      // Add 'cached' and 'refresh' class to selectors
      $linkset = preg_replace('@\{cached-('.$hashlist.')\}@','cached', $linkset);
      // TODO: Implement link aging
      $linkset = preg_replace('@\{refresh-('.$hashlist.')\}@','refresh', $linkset);
      $this->syslog( __FUNCTION__, __LINE__, "-- Marked {$n}/{$partition_index} links on {$url} as being 'cached'" );
    }/*}}}*/

    // FIXME: Time- and space- intensive
    // Stow edges.  This should probably be performed in a stored procedure.
		if (!(TRUE == C('DISABLE_AUTOMATIC_URL_EDGES'))) {/*{{{*/
			$edge = new UrlEdgeModel();
			foreach ( $idlist as $idval ) {/*{{{*/
				$edge->fetch($parent_page->id, $idval);
				if ( !$edge->in_database() ) {
					$edge->stow($parent_page->id, $idval);
				}
			}/*}}}*/
		}/*}}}*/

    $linkset = preg_replace('@\{(cached|refresh)-([0-9a-z]*)\}@i','', $linkset);

    return array(
      'linkset' => $linkset,
      'urlhashes' => $urlhashes,
      'cluster_urls' => $cluster_urls,
    );
  }/*}}}*/

  private final function normalize_links($source_url, & $parser, $container = NULL ) {/*{{{*/
    // -- Construct sitemap
    $parent_url = UrlModel::parse_url($source_url);
    $normalized_links = array();

    foreach ( is_null($container) ? $parser->get_links() : $container as $link_item ) {/*{{{*/

      if ( FALSE === ($normalized_link = UrlModel::normalize_url($parent_url, $link_item)) ) continue;

      // $this->syslog( __FUNCTION__, __LINE__, "{$normalized_link} ({$link_item['text']}) <- {$link_item['url']} [" . join(',',array_keys($link_item['urlparts'])) . " -> " .join(',',$link_item['urlparts']) . "] <{$q['path']}>");

      $normalized_links[] = array(
        'url'      => $normalized_link,
        'urlhash'  => UrlModel::get_url_hash($normalized_link),
        'origpath' => $link_item['url'],
        'text'     => $link_item['text'],
      );
    }/*}}}*/

    return $normalized_links;
  }/*}}}*/

  function extract_form($containers) {/*{{{*/
    $extract_form   = create_function('$a', 'return array_key_exists("tagname", $a) && ("FORM" == strtoupper($a["tagname"])) ? $a : NULL;');
    $paginator_form = array_values(array_filter(array_map($extract_form, $containers)));
    $this->recursive_dump($paginator_form,'(marker) Old');
    return $paginator_form;
  }/*}}}*/

  function extract_form_controls($form_control_source) {/*{{{*/

    $form_controls        = array();
    $select_options       = array();
    $select_name          = NULL;
    $select_option        = NULL;
    $userset              = array();

    if ((is_array($form_control_source) && (0 < count($form_control_source)))) {

      $extract_hidden_input = create_function('$a','return strtoupper($a["tag"]) == "INPUT" &&  strtoupper($a["attrs"]["TYPE"]) == "HIDDEN" ? array("name" => $a["attrs"]["NAME"], "value" => $a["attrs"]["VALUE"]) : NULL;');
      $extract_text_input   = create_function('$a','return strtoupper($a["tag"]) == "INPUT" &&  strtoupper($a["attrs"]["TYPE"]) == "TEXT"   ? array("name" => $a["attrs"]["NAME"], "value" => $a["attrs"]["VALUE"]) : NULL;');
      $extract_select       = create_function('$a','return strtoupper($a["tagname"]) == "SELECT" ? array("name" => $a["attrs"]["NAME"], "keys" => $a["children"]) : NULL;');
      foreach ( array_merge(
        array_values(array_filter(array_map($extract_hidden_input,$form_control_source))),
        array_values(array_filter(array_map($extract_text_input, $form_control_source)))
      ) as $form_control ) {
        $form_controls[$form_control['name']] = $form_control['value'];
      };

      $select_options = array_values(array_filter(array_map($extract_select, $form_control_source)));

      $userset = array();
      foreach ( $select_options as $select_option ) {
        //$this->recursive_dump($select_options,__LINE__);
        $select_name    = $select_option['name'];
        $select_option  = $select_option['keys'];
        foreach ( $select_option as $option ) {
          if ( empty($option['value']) ) continue;
          $userset[$select_name][$option['value']] = $option['text'];
        }
      }
    }

    return array(
      'userset'        => $userset,
      'form_controls'  => $form_controls,
      'select_name'    => $select_name,
      'select_options' => $select_option,
    );
  }/*}}}*/

  function perform_network_fetch( & $url, $referrer, $target_url, $faux_url, $metalink, $debug_dump = FALSE ) {/*{{{*/
    // Cache response if it's length exceeds the maximum length of a varchar field. 
    $debug_dump = TRUE;
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
        $this->recursive_dump($metalink,'(marker)');
        $this->syslog( __FUNCTION__, __LINE__, "(marker) Execute POST to {$url_copy}" );
      }/*}}}*/
      $response = CurlUtility::post($url_copy, $metalink, $curl_options);
      //$this->recursive_dump(CurlUtility::$last_transfer_info, __LINE__);
      if ( array_key_exists(intval(CurlUtility::$last_transfer_info['http_code']),array_flip(array(100,302,301,504))))
        $skip_get = FALSE;
      else
        $skip_get = TRUE;
      $url_copy = str_replace(' ','%20',$target_url); // CurlUtility methods modify the URL parameter (passed by ref)
      $successful_fetch = CurlUtility::$last_error_number == 0;
    }/*}}}*/

    if ( !$skip_get ) {/*{{{*/
      if ( $debug_dump ) {/*{{{*/
        $this->syslog( __FUNCTION__, __LINE__, "Execute GET/HEAD to {$url_copy}" );
      }/*}}}*/
      $response = $modifier == 'true'
        ? CurlUtility::head($url_copy, $curl_options)
        : CurlUtility::get($url_copy, $curl_options)
        ;
      $this->recursive_dump(CurlUtility::$last_transfer_info, __LINE__);
      $successful_fetch = CurlUtility::$last_error_number == 0;
    }/*}}}*/

    if ( $successful_fetch && is_array(CurlUtility::$last_transfer_info) && array_key_exists('request_header', CurlUtility::$last_transfer_info) ) {/*{{{*/
      // Extract cookie from cURL request_header
      $cookie_extract = create_function('$a', 'return 1 == preg_match("@^Cookie:@",$a) ? trim(preg_replace("@^Cookie:(.*)$@i","$1",$a)) : NULL;');
      $cookie_lines   = array_values(array_filter(array_map($cookie_extract,CurlUtility::$last_transfer_info['request_header'])));
      if ( 0 < count($cookie_lines) ) {
        $curl_cookie = array();
        foreach ( $cookie_lines as $cookie_item ) {
          // $this->syslog( __FUNCTION__, __LINE__, "Extracting cookie {$cookie_item}" );
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
			 	$url->set_url($faux_url,FALSE);
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
					$faux_url_instance = new UrlModel($faux_url,TRUE);
					$faux_url_instance->set_pagecontent($response);
					$faux_stow = $faux_url_instance->stow();
          $this->syslog( __FUNCTION__, __LINE__, "(marker) Stowing content to fake URL {$faux_url} " );

					$url->fetch($target_url,'url');
          $url->set_pagecontent($response);
          $this->syslog( __FUNCTION__, __LINE__, "(marker) Assigned content to target URL {$target_url}" );
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
