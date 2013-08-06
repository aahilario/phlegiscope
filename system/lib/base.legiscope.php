<?php

class LegiscopeBase extends SystemUtility {

  protected static $hostmodel = NULL;
  public static $singleton = NULL;
  public static $user_id = NULL;

  var $remote_host = NULL;
  var $user_agent  = NULL;
  var $subject_host_hash = NULL;
  var $seek_cache_filename = NULL;
  var $enable_proxy = TRUE;
  var $debug_handler_names = FALSE;
	var $debug_handle_model_action = FALSE;

  function __construct() {/*{{{*/
    parent::__construct();
		gc_enable();
    $this->session_start_wrapper();
    $target_url                = $this->filter_post('url');
    $this->subject_host_hash   = UrlModel::get_url_hash($target_url,PHP_URL_HOST);
    $this->seek_cache_filename = UrlModel::get_url_hash($target_url);
    $this->seek_cache_filename = SYSTEM_BASE . "/../cache/seek-{$this->subject_host_hash}-{$this->seek_cache_filename}.generated";
    $this->enable_proxy        = $this->filter_post('proxy','false') == 'true';
    $this->register_derived_class();
  }/*}}}*/

  function & get_hostmodel() {
    if ( !is_a(static::$hostmodel,'HostModel')) static::$hostmodel = new HostModel();
    return static::$hostmodel; 
  }

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

  static public function __callStatic($methodname, array $arguments) {/*{{{*/

    $arglist = join(',', array_keys($arguments));

    syslog(LOG_INFO, __METHOD__ . ": (marker) - ------- Inaccessible method {$methodname}({$arglist})");

  }/*}}}*/

  static function image_request() {/*{{{*/
    static::$singleton->handle_image_request();
  }/*}}}*/

  static function javascript_request() {/*{{{*/
    static::$singleton->handle_javascript_request();
  }/*}}}*/

  static function stylesheet_request() {/*{{{*/
    static::$singleton->handle_stylesheet_request();
  }/*}}}*/

  function transform_svgimage($image) {/*{{{*/
    $debug_method = FALSE;
    $svg = new SvgParseUtility();
    $svg->transform_svgimage($image);
    $image = $svg->get_containers('attrs,children[tagname*=svg]',0);
    $image = nonempty_array_element($image,'children',array());
    $this->reorder_with_sequence_tags($image);
    $this->filter_nested_array($image,'tagname,attrs,children[tagname*=path|g|i]');
    if ( $debug_method ) {
      $this->recursive_dump($image,"(marker)");
    }
    $transformed = $svg->reconstruct_svg($image);
    $this->recursive_dump($svg->extracted_styles,"(marker)");
    $svg = NULL;
    return $transformed;
  }/*}}}*/

  static function model_action() {/*{{{*/
    static::$singleton->handle_model_action();
  }/*}}}*/

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

    $debug_method = FALSE;
    // Modify $_REQUEST by extracting an action value from the request URI 
		if (!(C('MODE_WORDPRESS_PLUGIN') == TRUE)) {
			$this->syslog( __FUNCTION__, __LINE__, "(marker) Not a plugin context. Leaving" );
		 	return NULL;
		}
    // TODO: Permit XMLHTTPRequest GET
    // if (!('POST' == $this->filter_server('REQUEST_METHOD'))) return NULL;
    if ( $debug_method ) {
      $this->recursive_dump(UrlModel::parse_url($request_uri)   , "(marker) Q - - - ->");
      $this->recursive_dump($_POST   , "(marker) - P - - ->");
      $this->recursive_dump($_REQUEST, "(marker) - - R - ->");
      $this->recursive_dump($_SERVER , "(marker) - - - S ->");
    }
    $request_uri    = $this->filter_server('REQUEST_URI');
    $remote_addr    = $this->filter_server('REMOTE_ADDR');
    $actions_match  = array();
    $actions_lookup = array(
      'seek',
      'reorder',
      'keyword',
      'fetchpdf',
      'preload',
      'proxyform',
    );

    $request_regex  = '@/(' . join('|',array_values($actions_lookup)) . ')/(.*)@i';

    if ( 1 == preg_match($request_regex, $request_uri, $actions_match) ) {

			if (('XMLHttpRequest' == $this->filter_server('HTTP_X_REQUESTED_WITH'))) {
			}
			else if (!('fetchpdf' == array_element($actions_match,1))) {
				$this->syslog( __FUNCTION__, __LINE__, "(marker) Not an XMLHttpRequest context. Leaving." );
				return NULL;
			} else {
				$_REQUEST['r'] = array_element($actions_match,2);
			}

      if ( $debug_method ) $this->recursive_dump($actions_match,"(marker) -- -- -- REMAP -- --");
      $_REQUEST['q'] = array_element($actions_match,1);
    }

  }/*}}}*/

  final protected function handle_model_action() {/*{{{*/

		$debug_method = $this->debug_handle_model_action;

    // Extract controller, action, and subject values from server context
    $this->handle_plugin_context();

    // These request variables are normally unavailable in a WordPress plugin context
    // They are assigned by URL rewrite rules, usually set in an .htaccess file
    $host        = $this->filter_request('url');
    $request_uri = explode('/',trim(nonempty_array_element($_SERVER,'REQUEST_URI'),'/'));
    $controller  = $this->filter_request('p');
    $action      = $this->filter_request('q');
    $subject     = $this->filter_request('r');
    $base_url    = C('LEGISCOPE_PLUGIN_NAME');
    $base_url    = plugins_url("{$base_url}");

    // Update host hits

    if ( !is_null($host) && (0 < strlen($host)) ) {/*{{{*/
      $this->get_hostmodel()->set_id(NULL)->assign_hostmodel($host);
			$hostname = $this->get_hostmodel()->get_hostname();
      if (!(0 < strlen($hostname))) {
        $action = NULL;
        $this->syslog(__FUNCTION__, __LINE__, "(marker) - - - Nulling out action, process {$host} as Rootnode.");
        $request_uri = explode('/',trim($host,'/'));
			} else {
				if ( !$this->get_hostmodel()->in_database() ) {
					$this->get_hostmodel()->stow();
				} else  {
					$this->get_hostmodel()->increment_hits(TRUE);
				}
				$subject_host = $this->get_hostmodel()->get_hostname();
				$subject_hostpath = array_reverse(explode('.',preg_replace('@^www\.@i','',$subject_host)));
				define('LEGISCOPE_SUBJECT_HOSTPATH', join('.', $subject_hostpath));
				define('LEGISCOPE_SUBJECT_HOST', $subject_host);
			}
			
    }/*}}}*/

		define('LEGISCOPE_RESOURCES_URLBASE'   , $base_url);
		define('LEGISCOPE_TEMPLATES'           , LEGISCOPE_RESOURCES_URLBASE . "/templates/" . C('LEGISCOPE_SUBJECT_BASE', 'global') );
		define('LEGISCOPE_ADMIN_IMAGES_URLBASE', LEGISCOPE_RESOURCES_URLBASE . "/images/admin" );

		if ( $debug_method ) {
			$this->syslog(__FUNCTION__,__LINE__,"(marker)       Host: " . C('LEGISCOPE_SUBJECT_HOSTPATH','Undefined'));
			$this->syslog(__FUNCTION__,__LINE__,"(marker)       Base: " . C('LEGISCOPE_RESOURCES_URLBASE','Undefined'));
			$this->syslog(__FUNCTION__,__LINE__,"(marker)  Templates: " . C('LEGISCOPE_TEMPLATES','Undefined'));
			$this->syslog(__FUNCTION__,__LINE__,"(marker)     Images: " . C('LEGISCOPE_ADMIN_IMAGES_URLBASE','Undefined'));
		}

		$action_hdl = ucfirst(strtolower($action)) . "Action";

    $remote_addr = $this->filter_server('REMOTE_ADDR');

		if ( $debug_method ) {/*{{{*/
			$this->syslog( __FUNCTION__, __LINE__, "(marker) - - - - Invoked by remote host {$remote_addr}");
			$this->syslog( __FUNCTION__, __LINE__, "(marker) - - -     Subject: " . C('LEGISCOPE_SUBJECT_HOST'));
			$this->syslog( __FUNCTION__, __LINE__, "(marker) - - - Request URI: " . $_SERVER['REQUEST_URI']);
			$this->syslog( __FUNCTION__, __LINE__, "(marker) - - -  controller: " . $controller);
			$this->syslog( __FUNCTION__, __LINE__, "(marker) - - -      action: " . $action    );
			$this->syslog( __FUNCTION__, __LINE__, "(marker) - - -     subject: " . $subject   );
      if ( !is_null($host) )
      $this->syslog( __FUNCTION__, __LINE__, "(marker) - - -        host: " . $this->get_hostmodel()->get_hostname() . ', ' . $this->get_hostmodel()->get_hits() );
		}/*}}}*/

		if ( !is_null( $controller ) ) {/*{{{*/


    }/*}}}*/
    else if ( !is_null($action) && class_exists($action_hdl) ) {/*{{{*/

      if ( method_exists( $this, $action ) ) {

        $this->$action($subject);

      } else if ( class_exists($action_hdl) ) {

        $a = new $action_hdl();
        if ( method_exists($a, $action) ) {
          $a->$action($subject);
        }
        $a = NULL;
        unset($a);

      }

    }/*}}}*/
    else if (!is_null($request_uri) && (C('LEGISCOPE_BASE') == nonempty_array_element($request_uri,0,'--'))) {/*{{{*/
      //
      // The request URI will contain path parts that lead to either
      // terminal leaves, or nodes. A uniform resource identifier such as
      //
      // /{LEGISCOPE_BASE}/senate/15th-congress/bills/sb-3166
      //
      // might be able to be interpreted identically to 
      //
      // /{LEGISCOPE_BASE}/bills/senate/15th-congress/sb-3166
      //
      // or
      //
      // /{LEGISCOPE_BASE}/15th-congress/bills/senate/sb-3166
      //
      // with the terminal leaf "SB-3166" resolving unambiguously to a 
      // single document.  
      //
      // It would be useful for a leaf to have internal structure
      // that is accessed using the same URI syntax, for example
      //
      // ../sb-3166/sponsor
      // ../sb-3166/sponsor/antonio-trillanes
      //
      // This amounts to having each node contain active content. 
      // Resources appear to be reachable as nodes of a highly-connected
      // digraph. There may be multiple roots for the digraph, and these
      // roots are reachable by absolute URIs.  Immediate
      // neighbor nodes are reachable via relative URIs. 
      //
      // See RFC 3986 https://tools.ietf.org/html/rfc3986
      //
      array_shift($request_uri);
      $request_uri = array_values($request_uri);

      $action_hdl = ucfirst(strtolower(nonempty_array_element($request_uri,0))) . "Rootnode";

      $this->syslog(__FUNCTION__, __LINE__, "(marker) - - - Remap [{$action_hdl}] " . join('/',$request_uri));

      if ( class_exists($action_hdl) ) {
        array_shift($request_uri);
        $a = new $action_hdl($request_uri);
        $a->evaluate();
      }

    }/*}}}*/
    else if ( empty($controller) && empty($action) && empty($subject) ) {/*{{{*/

      define('ALLOW_LEGISCOPE_ADMIN_FRAMEWORK', TRUE);

      // Execute the default seek() action, to enable use of the full Legiscope MVC framework.
      // include_once(static::$singleton->get_template_filename('index.html','global'));

    }/*}}}*/

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

	function safe_json_encode($s) {/*{{{*/

		$pagecontent     = json_encode($s);
		$json_last_error = json_last_error();
		switch ( $json_last_error ) {
		case JSON_ERROR_NONE: break;
		case JSON_ERROR_CTRL_CHAR:
		case JSON_ERROR_UTF8:
			// Reencode every string element of the JSON response array
			array_walk($s,create_function(
				'& $a, $k', 'if ( is_string($a) ) $a = utf8_encode($a);'
			));
			$pagecontent = json_encode($s);
			$json_last_error = json_last_error();
			$this->syslog(__FUNCTION__,__LINE__,"(warning) - - - JSON UTF8 encoding error. Reencode result: {$json_last_error}" );
			break;
		default:
			$this->syslog(__FUNCTION__,__LINE__,"(warning) - - - Last JSON Error: {$json_last_error}" );
			break;
		}	
		return $pagecontent;

	}/*}}}*/

	function exit_cache_json_reply(array & $json_reply, $class_match = 'LegiscopeBase') {/*{{{*/
		if ( get_class($this) == $class_match ) {/*{{{*/
			$cache_force = $this->filter_post('cache');
			$pagecontent = $this->safe_json_encode($json_reply);
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

    if ( C('ALLOW_LEGISCOPE_ADMIN_FRAMEWORK') ) return;

    if ( FALSE == $this->subject_host_hash ) {/*{{{*/
      $this->syslog( __FUNCTION__,__LINE__,"(marker) Odd. We did not receive a 'url' POST value.  Nothing to do, exiting.");
      header('HTTP/1.0 404 Not Found');
      exit(0);
    }/*}}}*/

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
    $debug_method = $this->debug_handler_names;

    $urlhash     = $url->get_urlhash();
    $urlpathhash = UrlModel::parse_url($url->get_url());
    // Extract names of query component tuple variables
    $query_component = array_element($urlpathhash,'query');
    if ( !is_null($query_component) ) {/*{{{*/
      // Extract and sort query variable names
      $query_component = explode('&',$query_component);
      array_walk($query_component,create_function(
        '& $a, $k', '$p = explode("=",$a); $a = array("var" => array_element($p,0), "val" => array_element($p,1));'
      ));
      if ( 0 < count($query_component) ) { 
        $query_component = array_combine(
          array_map(create_function('$a', 'return $a["var"];'), $query_component),
          array_map(create_function('$a', 'return $a["val"];'), $query_component)
        );
				// This should basically contain a hash map of query parameters.
        if ( $debug_method ) $this->recursive_dump($query_component,"(marker) -- HDS");
        $query_component = array_keys($query_component);
        ksort($query_component);
        $query_component = join('|',$query_component);
      } else {
        $query_component = NULL;
      }
    }/*}}}*/
    $urlpathhash_sans_script = $urlpathhash; 
    $urlpathhash_sans_script['query'] = NULL; 
    $urlpathhash_sans_script['fragment'] = NULL; 
    $url_sans_script = UrlModel::recompose_url($urlpathhash_sans_script,array(),TRUE);
    // Retain script tail, only remove query part
    if ( array_key_exists('query',$urlpathhash) ) $urlpathhash['query'] = NULL;
    $urlpathhash = UrlModel::recompose_url($urlpathhash);

    $seek_postparse_pathonly_method  = "seek_postparse_bypathonly_" . UrlModel::get_url_hash($url_sans_script);
    $seek_postparse_path_method      = "seek_postparse_bypath_" . UrlModel::get_url_hash($urlpathhash);
    $seek_postparse_path_qvs_method  = is_null($query_component) ? NULL : ("seek_postparse_bypath_plus_queryvars_" . UrlModel::get_url_hash($urlpathhash . $query_component));
    $seek_postparse_method           = "seek_postparse_{$urlhash}";
    $seek_postparse_querytype_method = "seek_postparse_{$urlhash}";

    if ( $debug_method ) $this->syslog(__FUNCTION__, __LINE__, "(warning) ---- --- -- - - Handlers for " . $url->get_url() );

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
      'by-fullpath'  => $seek_postparse_method,
      'by-query'     => $seek_postparse_querytype_method,
      'by-noquery'   => $seek_postparse_path_method,
      'by-fullquery' => $seek_postparse_path_qvs_method,
      'by-path'      => $seek_postparse_pathonly_method,
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
    foreach ( array_filter($method_map) as $method_type => $method_name ) {/*{{{*/
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

    $modifier           = TRUE; // TRUE to execute HEAD instead of GET
    $session_cookie     = $this->filter_session("CF{$this->subject_host_hash}");
    $session_has_cookie = !is_null($session_cookie);

    if ( $debug_dump ) {/*{{{*/
      $this->syslog(__FUNCTION__,__LINE__,"(marker)          Referrer: {$referrer}");
      $this->syslog(__FUNCTION__,__LINE__,"(marker)        Target URL: {$target_url}");
      $this->syslog(__FUNCTION__,__LINE__,"(marker) Metalink/faux URL: {$faux_url}");
      $this->syslog(__FUNCTION__,__LINE__,"(marker)     Metalink data: " . gettype($metalink));
      $this->recursive_dump((is_array($metalink) ? $metalink : array("RAW" => $metalink)),'(marker) - - ->');
    }/*}}}*/

    $cookie_from_store = NULL;

    $cookiestore = SYSTEM_BASE . "/../cache/legiscope.{$this->subject_host_hash}.cookiejar";

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
      if ( 'SKIPGET' == nonempty_array_element($metalink,'_') ) {
        if ( $debug_dump ) {/*{{{*/
          $this->syslog( __FUNCTION__, __LINE__, "(marker) Execute POST to {$url_copy}" );
          $this->recursive_dump($metalink,'(marker)');
        }/*}}}*/
        $skip_get = TRUE;
        unset($metalink['_']);
      }
      if ( 'NOSKIPGET' == nonempty_array_element($metalink,'_') ) {
        if ( $debug_dump ) {/*{{{*/
          $this->syslog( __FUNCTION__, __LINE__, "(marker) Execute POST, then GET, to {$url_copy}" );
          $this->recursive_dump($metalink,'(marker)');
        }/*}}}*/
        $skip_get = FALSE;
        unset($metalink['_']);
      }
      if ( nonempty_array_element($metalink,'_') == 1 ) {
        // Pager or other link whose behavior depends on antecedent state.
        // See GenericParseUtility::extract_pager_links(): param 4, when present, causes this key to be assigned 
        if ( $debug_dump ) {/*{{{*/
          $this->syslog( __FUNCTION__, __LINE__, "(marker) Execute GET to {$url_copy}" );
          $this->recursive_dump($metalink,'(marker)');
        }/*}}}*/
        $skip_get = FALSE;
      } else {
        $response = CurlUtility::post($url_copy, $metalink, $curl_options);
        $url_copy = str_replace(' ','%20',$target_url); // Restore URL; CurlUtility methods modify the URL parameter (passed by ref)
        // $skip_get = $this->get_after_post();
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
			if ( $debug_dump ||
				!array_key_exists('http_code',CurlUtility::$last_transfer_info) ||
				!(200 == intval(CurlUtility::$last_transfer_info['http_code'])) ) {
				 	$this->recursive_dump(CurlUtility::$last_transfer_info, "(marker) GET/HEAD " . __LINE__ );
				}
      $successful_fetch = CurlUtility::$last_error_number == 0;
    }/*}}}*/

    if ( $successful_fetch ) {/*{{{*/

      // Skip updating fake UrlModel if fake and real URLs are not the same.
      $skip_fake_url_update = !($faux_url == $target_url);

      // If we used a metalink to specify POST action parameters, change to the faux URL first
      if ( is_array($metalink) && !$skip_fake_url_update && (0 < strlen($faux_url)) ) {
        // Note that the faux URL isn't validated; the second parameter indicates that
        // the URL is not to be retrieved from backing store, so that it's database id remains the same.
        $this->syslog(__FUNCTION__,__LINE__,"(warning) - - Temporarily replacing URL '" . $url->get_url() . ". with '{$faux_url}'");
        $url->set_is_fake(TRUE)->set_url($faux_url,FALSE);
      }

      // Split response into header and content parts
      $transfer_info = CurlUtility::$last_transfer_info;
      $http_code = intval(array_element($transfer_info,'http_code',400));

      if ( 400 <= $http_code && $http_code < 500 ) {/*{{{*/

        $this->syslog( __FUNCTION__, __LINE__, "(warning) cURL last_transfer_info" );
        $this->recursive_dump($transfer_info, "(warning) HTTP {$http_code}");
        $successful_fetch = FALSE;

      }/*}}}*/
      else {

        $url->
          set_pagecontent_c($response)->
          set_last_fetch(time())->
          increment_hits(); // This only updates the 'hits' attribute

        // Take cookie jar contents and place them in our per-host session cookie store
        if ( file_exists($cookiestore) ) {/*{{{*/
          $cookie_from_store = CurlUtility::parse_cookiejar($cookiestore, TRUE);
          if ( !(FALSE === $cookie_from_store) ) {
            $_SESSION["CF{$this->subject_host_hash}"] = $cookie_from_store; 
          }
        }/*}}}*/

        if ( $debug_dump ) {
          $this->syslog(__FUNCTION__, __LINE__, "(warning) --- -- - - Faux URL: {$faux_url}");
          $this->syslog(__FUNCTION__, __LINE__, "(warning) --- -- - - Real URL: {$target_url}");
        }

        if ( !(is_array($metalink) && $skip_fake_url_update) ) {
          if (!(0 < strlen($url->get_url()))) $url->set_url($target_url,FALSE);
          $url_id = $url->stow();
          $this->syslog(__FUNCTION__,__LINE__,"(marker) - - - #{$url_id} {$target_url}");
        }
        else {/*{{{*/

          $contenthash = sha1($response);
          $faux_url_instance = new UrlModel($faux_url,TRUE);
          $faux_stow = $faux_url_instance->
            set_pagecontent_c($response)->
            set_is_fake(TRUE)->
            stow();

          if ( $debug_dump ) {
            $this->syslog( __FUNCTION__, __LINE__, "(marker) Stow faux URL content " . (0 < intval($faux_stow) ? "OK (id #{$faux_stow})" : "FAILED") . " [content hash {$contenthash}] fake URL {$faux_url}" );
          }

          // Override content of target URL (possibly a FORM POST action URL) 
          // with response content.

					$prior_url_id = $url->get_id();
					$this->syslog(__FUNCTION__,__LINE__,"(marker) - - - Prior ID #{$prior_url_id} " . $url->get_url());

          // Reload content of original URL object from DB
					$url->set_id(NULL)->fetch(UrlModel::get_url_hash($target_url),'urlhash');

					if ( !$url->in_database() ) {
            $url_id = $url->set_url_c($target_url,FALSE)->set_pagecontent_c($response)->stow();
            $action = "Created";
					} else {
            $url_id = $url->
              set_url_c($target_url,FALSE)->
              set_pagecontent_c($response)->
              increment_hits()->
              fields(array('pagecontent','update_time','content_length','content_hash','last_modified','content_type','hits','response_header'))->
              stow();
            $action = "Matched";
					}
          $this->syslog(__FUNCTION__,__LINE__,"(marker) - - - {$action} #{$url_id} {$target_url}");

        }/*}}}*/
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

	/** Object Reflection Methods **/

	/*
	 * These methods support use of Legiscope framework metadata: Runtime
	 * information about available classes and methods is used for access
	 * control and to provide developers with a way to store and access
	 * information about the framework itself.
	 */

  /** View methods **/

  function get_template_filename($template_name, $for_class = NULL) {
    if ( is_null($for_class) ) {
      $for_class = get_class($this);
    } 
    $for_class = join('.',array_reverse(camelcase_to_array($for_class)));
    $template_filename = array(SYSTEM_BASE,'templates',$for_class,$template_name);
    return join('/',$template_filename); 
  }

  function get_template($template_name, $for_class = NULL) {
    $fn = $this->get_template_filename($template_name, $for_class);
    return file_exists($fn) ? @file_get_contents($fn) : NULL;
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

		// ADMIN HOME PAGE
    syslog( LOG_INFO, get_class($this) . "::" . __FUNCTION__ . '(' . __LINE__ . '): ' .
      " Loading main template " );

		// Generate map link
    $map_url = C('LEGISCOPE_PLUGIN_NAME');
    $map_url = plugins_url("{$map_url}/images/admin");
    $map_url = "{$map_url}/philippines-4c.svg"; 

		$map_image = <<<EOH
<img id="legislative-scope-map" class="legiscope-svg-fullsize" src="{$map_url}" alt="Placement" />
EOH;

		$map_image = static::$singleton->transform_svgimage(SYSTEM_BASE . "/../images/admin/philippines-4c.svg");
		$map_image = str_replace(
			array(
				'{svg_inline}',
				'{scale}',
			),
			array(
				$map_image,
				'1.4',
			),
			static::$singleton->get_template('map.html','global')
		);

    include_once(static::$singleton->get_template_filename('index.html','global'));

  }/*}}}*/

  // Filters that affect publication
  // Administrative menus
  // Administrative menus - Subscriber database 

  static function wordpress_admin_initialize() {/*{{{*/

		add_action('admin_xml_ns', array(get_class($this), 'legiscope_admin_xml_ns')); 

    if ( !current_user_can( 'manage_options' ) ) {
      wp_redirect(site_url());
      exit(0);
    }
  }/*}}}*/

	static function legiscope_admin_xml_ns() {
    syslog( LOG_INFO, __METHOD__ . ": " );
		$nsparts = array(
			'xmlns:svg="http://www.w3.org/2000/svg"',
			'xmlns:xlink="http://www.w3.org/1999/xlink"',
		);
		echo join(' ', $nsparts);
	}

  static function admin_post() {/*{{{*/
    // No need to invoke image_, javascript_, or stylesheet_request,
    // as we'll let WordPress handle dispatch of those resources.
    syslog( LOG_INFO, __METHOD__ . ": " );
    static::model_action();
    $o = @ob_get_clean();
    exit(0);
  }/*}}}*/

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

		$inline_script = <<<EOH
<script type="text/javascript">
  PDFJS.workerSrc = 'js/pdf.js';
</script>
EOH;

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
