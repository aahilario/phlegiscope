<?php

class LegiscopeBase extends SystemUtility {

  public static $singleton = NULL;
  public static $user_id = NULL;

  var $remote_host = NULL;
  var $user_agent  = NULL;
  var $subject_host_hash = NULL;
  var $seek_cache_filename = NULL;
  var $enable_proxy = TRUE;

  function __construct() {/*{{{*/
    parent::__construct();
		gc_enable();
    $this->session_start_wrapper();
    $target_url = $this->filter_post('url');
    $this->seek_cache_filename = UrlModel::get_url_hash($target_url);
    $this->seek_cache_filename = "./cache/seek-{$this->subject_host_hash}-{$this->seek_cache_filename}.generated";
    $this->enable_proxy = $this->filter_post('proxy','false') == 'true';
  }/*}}}*/

  static function & instantiate_by_host() {/*{{{*/
    // TODO: Implement RBAC here.
    // Instantiate a singleton, depending on the host URL. 
    // If a class definition filename of the form sub.domain.tld.class.php exists,
    // defining class SubDomainPh (derived from (www.)*sub.domain.tld),
    // then that class (which extends LegiscopeBase) is generated, 
    // instead of LegiscopeBase.
    $request_url = filter_post('url');
    $hostname    = @UrlModel::parse_url($request_url, PHP_URL_HOST);
    $matches     = array();
    $base_regex  = '/^(www\.)?(([^.]+[.]?)*)/i';
    $matchresult = preg_match_all($base_regex, $hostname, $matches);
    // syslog( LOG_INFO, "----------------- (" . gettype($matchresult) . " {$matchresult})" . print_r($matches,TRUE));
    $initcaps    = create_function('$a', 'return ucfirst($a);');
    $nameparts   = array_map($initcaps, explode('.', $matches[2][0]));
    // syslog( LOG_INFO, "----------------- " . print_r($nameparts,TRUE));
    if ( count($nameparts > 3) ) {
      krsort($nameparts);
      $nameparts = array_values($nameparts);
      while ( count($nameparts) > 3 ) array_pop($nameparts);
      krsort($nameparts);
      $nameparts = array_values($nameparts);
    }
    // syslog( LOG_INFO, "----------------- " . print_r($nameparts,TRUE));
    $classname   = join('', $nameparts);
    $hostregex   = '/^((www|ireport)\.)+((gmanetwork|sec|denr|dbm|senate|congress)\.)*(gov\.ph|com)/i';
    static::$singleton = (1 == preg_match($hostregex, $hostname)) && @class_exists($classname)
      ? new $classname
      : new LegiscopeBase()
      ;
    return static::$singleton;
  }/*}}}*/

  static public function __callStatic($methodname, array $arguments) {

    $arglist = join(',', array_keys($arguments));

    $this->syslog(__FUNCTION__,__LINE__,"(marker) - ------- Inaccessible method {$methodname}({$arglist})");

  }

  static function image_request() {
    static::$singleton->handle_image_request();
  }

  static function javascript_request() {
    static::$singleton->handle_javascript_request();
  }

  static function stylesheet_request() {
    static::$singleton->handle_stylesheet_request();
  }

  static function model_action() {
    static::$singleton->handle_model_action();
  }

  protected function handle_stylesheet_request() {/*{{{*/
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

  protected function handle_image_request() {/*{{{*/
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

  protected function handle_javascript_request() {/*{{{*/
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

  final private function handle_plugin_context() {/*{{{*/
    // Modify $_REQUEST to 
    if (!(C('MODE_WORDPRESS_PLUGIN') == TRUE)) return NULL;
    if (!('XMLHttpRequest' == $this->filter_server('HTTP_X_REQUESTED_WITH'))) return NULL;
    // TODO: Permit XMLHTTPRequest GET
    if (!('POST' == $this->filter_server('REQUEST_METHOD'))) return NULL;

    $this->recursive_dump(UrlModel::parse_url($request_uri)   , "(marker) Q - - - ->");
    $this->recursive_dump($_POST   , "(marker) - P - - ->");
    $this->recursive_dump($_REQUEST, "(marker) - - R - ->");
    $this->recursive_dump($_SERVER , "(marker) - - - S ->");

    $request_uri    = $this->filter_server('REQUEST_URI');
    $remote_addr    = $this->filter_server('REMOTE_ADDR');
    $actions_match  = array();
    $actions_lookup = array(
      'seek',
      'reorder',
      'keywords',
      'fetchpdf',
      'preload',
      'proxyform',
    );

    $request_regex  = '@/(' . join('|',array_values($actions_lookup)) . ')/(([^/]*)/)*@i';

    if ( 1 == preg_match($request_regex, $request_uri, $actions_match) ) {
      $this->recursive_dump($actions_match,"(marker) -- -- -- -- --");
      $_REQUEST['q'] = array_element($actions_match,1);
    }

  }/*}}}*/

  final protected function handle_model_action() {/*{{{*/

		$debug_method = FALSE;

    $host = $this->filter_request('url');

    // Extract controller, action, and subject values from server context
    $this->handle_plugin_context();

    // These request variables are normally unavailable in a WordPress plugin context
    // They are assigned by URL rewrite rules, usually set in an .htaccess file
    $controller     = $this->filter_request('p');
    $action         = $this->filter_request('q');
    $subject        = $this->filter_request('r');

    // Update host hits
    $hostModel  = new HostModel($host);
    if ( !is_null($host) ) {
      if ( !$hostModel->in_database() ) {
        $hostModel->stow();
      }
      $hostModel->increment_hits()->stow();
    }

		$action_hdl = ucfirst(strtolower($action)) . "Action";

		if ( $debug_method ) {
			$this->syslog( __FUNCTION__, __LINE__, "(marker) - - - - Invoked by remote host {$remote_addr}");
			$this->syslog( __FUNCTION__, __LINE__, "(marker) - - -  controller: " . $controller);
			$this->syslog( __FUNCTION__, __LINE__, "(marker) - - -      action: " . $action    );
			$this->syslog( __FUNCTION__, __LINE__, "(marker) - - -     subject: " . $subject   );
      if ( !is_null($host) )
			$this->syslog( __FUNCTION__, __LINE__, "(marker) - - -        host: " . $hostModel->get_hostname() . ', ' . $hostModel->get_hits() );
		}

		if ( is_null( $controller ) ) {
			if ( !is_null($action) && class_exists($action_hdl) ) {
				$a = new $action_hdl();
        if ( method_exists( $this, $action ) ) {
          $this->$action($subject);
        } else if ( method_exists($a, $action) ) {
          $a->$action($subject);
				}
				$a = NULL;
				unset($a);
			}
		}
		return $this;
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

  function exit_cache_json_reply(array & $json_reply, $class_match = 'LegiscopeBase') {/*{{{*/
    if ( get_class($this) == $class_match ) {/*{{{*/
			$cache_force = $this->filter_post('cache');
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
    if ( FALSE == ( $this->subject_host_hash = UrlModel::get_url_hash($target_url,PHP_URL_HOST) ) ) {
      $this->syslog( __FUNCTION__,__LINE__,"(marker) Odd. We did not receive a 'url' POST value.  Nothing to do.");
      header('HTTP/1.0 404 Not Found');
      exit(0);
    }

    if ( (C('ENABLE_GENERATED_CONTENT_BUFFERING') || ($cache_force == 'true')) && (!$network_fetch) ) {/*{{{*/
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

  protected function get_handler_names(UrlModel & $url, array $cluster_urls) {/*{{{*/

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

    if ( $debug_method ) $this->syslog(__FUNCTION__, __LINE__, "(warning) ---- --- -- - - Handlers for {$url}" );
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

		if ( array_key_exists('path', $url_components) ) {/*{{{*/
			$test_url_components = $url_components;
			unset($test_url_components['query']);
			unset($test_url_components['fragment']);
			foreach ( explode('/', $test_url_components['path']) as $path_part ) {
				$method_name = NULL;
				if ( empty($path_part) ) continue;
				$url_map[] = $path_part;
				$test_path = '/' . join('/',$url_map);
				$test_url = $test_url_components;
				$test_url['path'] = $test_path;
				$test_url = UrlModel::recompose_url($test_url);
				$test_path = str_replace('/','-',$test_path);
				$method_map['by-path-' . count($url_map) . "{$test_path}"] = 'seek_by_pathfragment_' . UrlModel::get_url_hash($test_url);
			}
		}/*}}}*/

    $method_map['generic'] = 'common_unhandled_page_parser';

    $method_list = array();

    // Do not include unimplemented methods
    foreach ( $method_map as $method_type => $method_name ) {/*{{{*/
      if ( array_key_exists($method_name, array_flip($method_list)) ) continue;
      if ( method_exists($this, $method_name) ) {
        $method_list[$method_type] = $method_name;
        if ( $debug_method ) $this->syslog(__FUNCTION__,__LINE__,"{$method_type} handler '{$method_name}' present." );
      } else {
        if ( $debug_method ) $this->syslog(__FUNCTION__,__LINE__,"(marker) {$method_type} handler '{$method_name}' missing." );
      }
    }/*}}}*/

    return $method_list;

  }/*}}}*/

  function extract_form($containers) {/*{{{*/
    $debug_method = FALSE;
    $extract_form   = create_function('$a', 'return array_key_exists("tagname", $a) && ("FORM" == strtoupper($a["tagname"])) ? $a : NULL;');
    $paginator_form = array_values(array_filter(array_map($extract_form, $containers)));
    if ( $debug_method ) $this->recursive_dump($paginator_form,'(marker) Old');
    return $paginator_form;
  }/*}}}*/

  function perform_network_fetch(
    & $url   , $referrer, $target_url        ,
    $faux_url, $metalink, $debug_dump = FALSE
  ) {/*{{{*/

    $modifier = FALSE; // TRUE to execute HEAD instead of GET
    $session_cookie = $this->filter_session("CF{$this->subject_host_hash}");
    $session_has_cookie = !is_null($session_cookie);

    if ( $debug_dump ) {/*{{{*/
      $this->syslog(__FUNCTION__,__LINE__,"(marker)          Referrer: {$referrer}");
      $this->syslog(__FUNCTION__,__LINE__,"(marker)        Target URL: {$target_url}");
      $this->syslog(__FUNCTION__,__LINE__,"(marker) Metalink/faux URL: {$faux_url}");
      $this->syslog(__FUNCTION__,__LINE__,"(marker)     Metalink data: " . gettype($metalink));
      $this->recursive_dump((is_array($metalink) ? $metalink : array("RAW" => $metalink)),'(marker) Metalink URL src');
    }/*}}}*/

    $cookie_from_store = NULL;
    $cookiestore = "./cache/legiscope.{$this->subject_host_hash}.cookiejar";

    if ( !$session_has_cookie && file_exists($cookiestore) ) {/*{{{*/
      // If a cookie has NOT yet been set, we need to make sure we don't 
      // inadvertently reuse invalid session data.
      unlink($cookiestore);
    }/*}}}*/

    $curl_options = array(
      CURLOPT_COOKIESESSION  => !$session_has_cookie, // TRUE if no cookies are available 
      CURLOPT_COOKIEJAR      => $cookiestore,
      CURLOPT_FOLLOWLOCATION => TRUE, // TODO:  Control redirection
      CURLOPT_REFERER        => $referrer,
      CURLINFO_HEADER_OUT    => TRUE,
      CURLOPT_MAXREDIRS      => 5,
    );
    if ( $session_has_cookie ) {
      if ( $debug_dump ) $this->syslog( __FUNCTION__, __LINE__, "(warning) Setting cookie '{$session_cookie}'");
      $curl_options[CURLOPT_COOKIE] = $session_cookie;
    }
    if ( !is_null(C('LEGISCOPE_CURLOPT_PROXY')) && $this->enable_proxy ) {
      $this->syslog(__FUNCTION__,__LINE__,"(marker) --- HACK: Enable proxy " . LEGISCOPE_CURLOPT_PROXY);
      $curl_options[CURLOPT_HTTPPROXYTUNNEL] = TRUE;
      $curl_options[CURLOPT_PROXY] = LEGISCOPE_CURLOPT_PROXY;
      $curl_options[CURLOPT_PROXYPORT] = LEGISCOPE_CURLOPT_PROXYPORT;
      $curl_options[CURLOPT_PROXYTYPE] = LEGISCOPE_CURLOPT_PROXYTYPE;
    }
    $url_copy = str_replace(' ','%20',$target_url); // CurlUtility methods modify the URL parameter (passed by ref)

    $skip_get         = FALSE;
    $successful_fetch = FALSE;

    // I want to be able to wrap information in the fake POST action data
    // that can be used by site-specific handlers.  This information is not
    // forwarded to the target host, but instead held back, removed from the
    // POST data before the POST is actually executed, but remains available
    // to other methods that pass around the POST data.

    if ( is_array($metalink) ) {/*{{{*/

      if ( array_key_exists('_LEGISCOPE_', $metalink) ) {
        unset($metalink['_LEGISCOPE_']);
      }
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
        $skip_get = $this->get_after_post();
        $url_copy = str_replace(' ','%20',$target_url); // CurlUtility methods modify the URL parameter (passed by ref)
        $successful_fetch = CurlUtility::$last_error_number == 0;
        if ( $debug_dump ) {
          $fetch_state = $successful_fetch ? "OK" : "FAILED";
          $skipping    = $skip_get ? "Skipping GET" : "Will GET";
          $this->recursive_dump(CurlUtility::$last_transfer_info, "(marker) POST {$fetch_state} {$skipping} transfer info");
        }
      }
    }/*}}}*/

    if ( !$skip_get ) {/*{{{*/
      if ( $debug_dump ) {/*{{{*/
        $this->syslog( __FUNCTION__, __LINE__, "(marker) Execute GET/HEAD to {$url_copy}" );
      }/*}}}*/
      $response = ($modifier == 'true')
        ? CurlUtility::head($url_copy, $curl_options)
        : CurlUtility::get($url_copy, $curl_options)
        ;
      if ( $debug_dump || !array_key_exists('http_code',CurlUtility::$last_transfer_info) || !(200 == intval(CurlUtility::$last_transfer_info['http_code'])) ) $this->recursive_dump(CurlUtility::$last_transfer_info, "(marker) GET/HEAD");
      $successful_fetch = CurlUtility::$last_error_number == 0;
    }/*}}}*/

    if ( $successful_fetch ) {/*{{{*/

      // Skip updating fake UrlModel if fake and real URLs are not the same.
      $skip_fake_url_update = !($faux_url == $target_url);

      // If we used a metalink to specify POST action parameters, change to the faux URL first
      if ( is_array($metalink) && !$skip_fake_url_update ) {
         $url->set_is_fake(TRUE)->set_url($faux_url,FALSE);
      }
      // Split response into header and content parts
      $transfer_info = CurlUtility::$last_transfer_info;
      $http_code = intval(array_element($transfer_info,'http_code',400));

      if ( 400 <= $http_code && $http_code < 500 ) {

        $this->syslog( __FUNCTION__, __LINE__, "(warning) cURL last_transfer_info" );
        $this->recursive_dump($transfer_info, "(warning) HTTP {$http_code}");
        $successful_fetch = FALSE;

      }
      else {
        // Store contents to disk (DB/cache file)
        $page_content_result = $url->set_pagecontent($response); // No stow() yet

        if ( $debug_dump ) {/*{{{*/
          $hash = $url->get_content_hash();
          $this->syslog( __FUNCTION__, __LINE__, "(marker) Result [{$hash}] set_pagecontent for " . $url->get_url() );
          $this->recursive_dump($page_content_result,"(marker)");
        }/*}}}*/

        $url->set_last_fetch(time());
        $url->increment_hits()->stow();

        // Take cookie jar contents and place them in our per-host session cookie store
        if ( file_exists($cookiestore) ) {
          $cookie_from_store = CurlUtility::parse_cookiejar($cookiestore, TRUE);
          if ( !(FALSE === $cookie_from_store) ) {
            $_SESSION["CF{$this->subject_host_hash}"] = $cookie_from_store; 
          }
        }

        if ( $debug_dump ) {
          $this->syslog(__FUNCTION__, __LINE__, "(warning) --- -- - - Faux URL: {$faux_url}");
          $this->syslog(__FUNCTION__, __LINE__, "(warning) --- -- - - Real URL: {$target_url}");
        }

        if ( is_array($metalink) && $skip_fake_url_update ) {
          $contenthash = sha1($response);
          $faux_url_instance = new UrlModel($faux_url,TRUE);
          $faux_url_instance->set_pagecontent($response);
          $faux_stow = $faux_url_instance->set_is_fake(TRUE)->stow();
          if ( $debug_dump ) {
            $this->syslog( __FUNCTION__, __LINE__, "(marker) Stow content " . (0 < intval($faux_stow) ? "OK (id #{$faux_stow})" : "FAILED") . " [{$contenthash}] to fake URL {$faux_url}" );
          }
          // Override content of target URL (possibly a FORM POST action URL) 
          // with response content.
          $url->fetch($target_url,'url');
          $url->set_pagecontent($response);
          $url->stow();
        }
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

  function get_after_post() {/*{{{*/
    return array_key_exists(
      intval(CurlUtility::$last_transfer_info['http_code']),
      array_flip(array(100,302,301,504))
    ) ? FALSE : TRUE;
  }/*}}}*/

  // Shared by SenateGovPh and CongressGovPh for catalog pages

  function find_incident_pages(UrlModel & $urlmodel, $batch_regex, $query_regex = '@([^&=]*)=([^&]*)@') { /*{{{*/
    // Find all links that originate from this page

    $debug_method     = FALSE;

    $child_link       = array();
    $child_links      = array(array());
    $child_collection = array();
    $batch_number     = 0;
    $child_count      = 0;
    $subset_size      = 20;

    // You need to traverse each of the session selector URLs 
    //  to obtain the pagers (and thus the list of journals) for that session.
    // Instead of traversing those links by submitting fake POST requests,
    //  we use URL edge lists:  We find all the URLs incident on this current
    //  page, group those by Congress and session type, and display those.
    // First collect a list of unique URLs referenced from $urlmodel,
    //  partitioned into subsets of size $subset_size 

    $tempmodel = new UrlModel();
    $edge_iterator  = new UrlEdgeModel();
    $this->syslog( __FUNCTION__, __LINE__, "(marker) -- - -- Fetching edges for URL #" . $urlmodel->get_id() . " " . $urlmodel->get_url());
    $edge_iterator->where(array('AND' => array(
      'a' => $urlmodel->get_id()
    )))->recordfetch_setup();
    $n = 0; 
    while ( $edge_iterator->recordfetch($child_link) ) {/*{{{*/
      if ( !array_key_exists($child_link['b'], $child_collection) ) {/*{{{*/
        $b = $child_link['b'];
        $child_collection[$b] = count($child_collection);
        $child_links[$batch_number][$b] = count(array_element($child_links,$batch_number,array()));
        $child_count++;
        if ( $child_count > $subset_size ) {/*{{{*/
          ksort($child_links[$batch_number]);
          $child_links[$batch_number] = array_keys($child_links[$batch_number]);
          $child_count = 0;
          $batch_number++;
        }/*}}}*/
      }/*}}}*/
      $n++;
    }/*}}}*/
    // Finally sort the remaining links
    if ( $child_count > 0 ) {
      ksort($child_links[$batch_number]);
      $child_links[$batch_number] = array_keys($child_links[$batch_number]);
    }

    $this->syslog( __FUNCTION__, __LINE__, "(marker) -- - -- Found {$n} edge sets for URL #" . $urlmodel->get_id() . " " . $urlmodel->get_url());

    $url_iterator = new UrlModel();
    $child_collection = array();

    // Iterate through URLs for journals matching any Congress, session, and series number. 
    $child_link  = array();
    $this->syslog( __FUNCTION__, __LINE__, "(marker) -- - -- Fetching URLs for URL #" . $urlmodel->get_id() . " " . $batch_regex);
    $link_batch  = "REGEXP '{$batch_regex}'";
    $url_iterator->where(array('url' => $link_batch))->recordfetch_setup();

    $n = 0;
    while ( $url_iterator->recordfetch($child_link) ) {/*{{{*/

      $url_query_parts = array(
        'congress' => NULL,
        'session'  => NULL,
        'q' => NULL,
      );

      // Nested result set: CC[ Congress ][ Session ][ Entry ]
      if ( !is_null($query_regex) ) {

        $url_query_components = array(); 
        $url_query_parts = UrlModel::parse_url($child_link['url'], PHP_URL_QUERY); 
        preg_match_all($query_regex, $url_query_parts, $url_query_components);

        if ( !is_array($url_query_components[1]) ) continue;
        if ( !is_array($url_query_components[2]) ) continue;
        if ( !(0 < count($url_query_components[1])) ) continue;
        if ( count($url_query_components[1]) != count($url_query_components[2]) ) continue;

        $url_query_parts = array_combine($url_query_components[1],$url_query_components[2]);
      } else if ( method_exists($this,'find_incident_edges_partitioner') ) {
        $url_query_parts = $this->find_incident_edges_partitioner($urlmodel, $child_link);
        if ( $url_query_parts == FALSE ) continue;
      }

      if ( empty($url_query_parts['session']) ) $url_query_parts['session'] = 'ALLSESSIONS';

      $child_collection[$url_query_parts['congress']][$url_query_parts['session']][$url_query_parts['q']] = array( 
        'hash' => $child_link['urlhash'],
        'url'  => $child_link['url'],
        'text' => $child_link['urltext'],
      );
      $n++;
    }/*}}}*/
    krsort($child_collection);
    if ( $debug_method ) {
      $this->syslog( __FUNCTION__, __LINE__, "(marker) -- - -- Found {$n} URLs matching '{$batch_regex}'");
      $this->recursive_dump($child_collection,"(marker) " . __METHOD__);
    }
    return $child_collection;
  }/*}}}*/

	/** Object Reflection Methods **/

	/*
	 * These methods support use of Legiscope framework metadata: Runtime
	 * information about available classes and methods is used for access
	 * control and to provide developers with a way to store and access
	 * information about the framework itself.
	 */

	protected function register_derived_class() {
		$this->syslog(__FUNCTION__,__LINE__,"(marker)");
	}

  //////////////////////////////////////////////////////////////////////////
  /** WordPress Plugin adapter methods **/

  static $plugin_options = array(
    'phlegiscope_client_visibility' => 'TRUE',
    'phlegiscope_client_categories' => 'FALSE',
    'phlegiscope_custom_datafields' => 'FALSE',
    'phlegiscope_client_responders' => 'FALSE',
    'phlegiscope_roundrobin_resp' => 'TRUE',
    'phlegiscope_menutitle' => 'PHLegiscope',
    'phlegiscope_allow_anonymous_submissions' => 'TRUE',
    'phlegiscope_option_2' => 'TRUE',
    'phlegiscope_option_3' => 'TRUE',
    'phlegiscope_option_4' => 'TRUE',
    'phlegiscope_option_5' => 'TRUE',
    'phlegiscope_option_6' => 'TRUE',
  );

  // Scaffolding hook handlers

  static function activate() {/*{{{*/
    add_option('phlegiscope_menutitle', self::$plugin_options['phlegiscope_menutitle'], NULL, 'yes');
    syslog( LOG_INFO, get_class($this) . "::" . __FUNCTION__ . '(' . __LINE__ . '): ' .
      'Activated' );
  }/*}}}*/

  static function deactivate() {/*{{{*/
  }/*}}}*/

  function wordpress_register_admin_menus() {/*{{{*/
    $menu_title = get_option('phlegiscope_menutitle');
    // The main PHLegiScope admin curator's page
    add_menu_page(
      'Options',
      $menu_title,
      'administrator',
      'phlegiscope-main',
      array(__CLASS__, 'phlegiscope_main'),
      NULL, // plugin_dir_url(__FILE__) . '/images/phlegiscope.png'
      NULL // $position
    );
    // PHLegiScope settings page
    add_submenu_page(
      'options-general.php',
      'Catalog',
      $menu_title,
      'administrator',
      'phlegiscope',
      array(__CLASS__, 'phlegiscope')
    );
  }/*}}}*/

  static function update_options_from_post() {/*{{{*/
    $options = self::$plugin_options;
    foreach ( $options as $name => $defvalue ) {
      $value = self::filter_post($name);
      if ( is_array($value) ) {
        if ( !is_array($defvalue) ) {
          syslog( LOG_INFO, get_class($this) . "::" . __FUNCTION__ . '(' . __LINE__ . '): ' .
            "Got unexpected array submission for option '{$name}'" );
        } else {
          syslog( LOG_INFO, get_class($this) . "::" . __FUNCTION__ . '(' . __LINE__ . '): ' .
            "Got array '{$name}'" );
        }
        self::recursive_dump($value);
      } else if (array_key_exists(strtoupper($defvalue), array_flip(array('TRUE','FALSE')))) {
        $is_true = array_key_exists(strtoupper($value), array_flip(array('ON','TRUE','1')));
        $value   = $is_true ? 'TRUE' : 'FALSE';
        if (DEBUG_PHLEGISCOPE) {
          syslog( LOG_INFO, get_class($this) . "::" . __FUNCTION__ . '(' . __LINE__ . '): ' .
            "Got boolean option '{$name}' = {$value}" );
        }
        update_option($name, $is_true ? 'TRUE' : 'FALSE');
      } else if (is_string($value)) {
        if (DEBUG_PHLEGISCOPE) {
          syslog( LOG_INFO, get_class($this) . "::" . __FUNCTION__ . '(' . __LINE__ . '): ' .
            "Got option '{$name}' = {$value}" );
        }
        update_option($name, $value);
      }
    }
  }/*}}}*/

  function handle_wordpress_admin_action(& $a) {/*{{{*/
    $scoped_vars = array(); 
    $action = $this->filter_post('action');

    syslog( LOG_INFO, get_class($this) . "::" . __FUNCTION__ . '(' . __LINE__ . '): ' .
      "{$a} - action '{$action}'" );

    switch ( $action ) {
      case 'update':
        static::update_options_from_post();
        break;
      default:
        break;
    }

    // Load settings option variables, and move them into this scope 
    $options = self::$plugin_options;
    $boolean_map = array(
      'TRUE'  => 'value="1" checked="checked"', 
      'FALSE' => 'value="1"',
    );
    foreach ( $options as $name => $defvalue ) {
      $value = get_option($name);
      if ( is_array($value) ) {
        if (DEBUG_PHLEGISCOPE) {
          syslog( LOG_INFO, get_class($this) . "::" . __FUNCTION__ . '(' . __LINE__ . '): ' .
            "Got array '{$name}'" );
          $this->recursive_dump($value);
        }
      } else if (array_key_exists(strtoupper($defvalue), array_flip(array_keys($boolean_map)))) {
        $is_true = !(FALSE === $value) && array_key_exists(strtoupper($value), array_flip(array('ON','TRUE','1')));
        $value   = $boolean_map[$is_true ? 'TRUE' : 'FALSE'];
        $scoped_vars[$name] = $value; 
      } else if (is_string($value)) {
        $scoped_vars[$name] = $value; 
      }
    }

    if (C('DEBUG_PHLEGISCOPE')) {
      $this->recursive_dump($scoped_vars,0,__FUNCTION__);
    }
    extract($scoped_vars);

    $menu_title = get_option('phlegiscope_menutitle');

    include_once(SYSTEM_BASE . '/../admin-pages/phlegiscope-settings.php');

  }/*}}}*/

  static function phlegiscope($a) {/*{{{*/

    static::$singleton->handle_wordpress_admin_action($a);

  }/*}}}*/

  static function phlegiscope_main() {/*{{{*/
    // Display client and subscriber contact database
    syslog( LOG_INFO, get_class($this) . "::" . __FUNCTION__ . '(' . __LINE__ . '): ' .
      "" );

    include_once( SYSTEM_BASE . '/../admin-pages/phlegiscope.php');
  }/*}}}*/

  // Filters that affect publication
  // Administrative menus
  // Administrative menus - Subscriber database 

  static function wordpress_admin_initialize() {/*{{{*/
    if ( !current_user_can( 'manage_options' ) ) {
      wp_redirect(site_url());
      exit(0);
    }
  }/*}}}*/

  static function admin_post() {
    // No need to invoke image_, javascript_, or stylesheet_request,
    // as we'll let WordPress handle dispatch of those resources.
    syslog( LOG_INFO, __METHOD__ . ": " );
    static::model_action();
    $o = @ob_get_clean();
    exit(0);
  }

  static function wordpress_enqueue_admin_scripts() {/*{{{*/

    $plugins_url = plugins_url();
    $themes_uri  = get_template_directory_uri(); 

    $spider_js_url        = plugins_url('spider.js'       , LEGISCOPE_JS_PATH . '/' . 'spider.js');
    $pdf_js_url           = plugins_url('pdf.js'          , LEGISCOPE_JS_PATH . '/' . 'pdf.js');
    $interactivity_js_url = plugins_url('interactivity.js', LEGISCOPE_JS_PATH . '/' . 'interactivity.js');

    $admin_css_url        = plugins_url('legiscope-admin.css',  LEGISCOPE_CSS_PATH . '/legiscope-admin.css');

    wp_register_style( 'legiscope_wp_admin_css', $admin_css_url   , false, '1.0.0' );
    wp_enqueue_style( 'legiscope_wp_admin_css' , $admin_css_url );

    wp_register_script('legiscope-pdf'          , $pdf_js_url          , array('jquery'), NULL);
    wp_register_script('legiscope-spider'       , $spider_js_url       , array('jquery'), NULL);
    wp_register_script('legiscope-interactivity', $interactivity_js_url, array('jquery','legiscope-spider'), NULL);

    wp_enqueue_script('legiscope-pdf'          , $pdf_js_url          , array('jquery'), NULL);
    wp_enqueue_script('legiscope-spider'       , $spider_js_url       , array('jquery'), NULL);
    wp_enqueue_script('legiscope-interactivity', $interactivity_js_url, array('jquery' , 'legiscope-spider'), NULL);

    syslog( LOG_INFO, "- - - - -  Plugin: " . LEGISCOPE_PLUGIN_NAME);
    syslog( LOG_INFO, "- - - - - Basenam: " . plugin_basename(__FILE__));
    syslog( LOG_INFO, "- - - - - Plugins: " . $plugins_url);
    syslog( LOG_INFO, "- - - - -  Themes: " . $themes_uri);
    syslog( LOG_INFO, "- - - - -  Spider: " . $spider_js_url);
    syslog( LOG_INFO, "- - - - -  Styles: " . $admin_css_url);
    syslog( LOG_INFO, "- - - - -      JS: " . LEGISCOPE_JS_PATH);
    syslog( LOG_INFO, "- - - - -     CSS: " . LEGISCOPE_CSS_PATH);
  }/*}}}*/

}
