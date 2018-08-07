<?php

class LegiscopeBase extends SystemUtility {

  protected static $hostmodel = NULL;
  public static $singleton = NULL;
  public static $user_id = NULL;
  public static $enable_debug = FALSE;

  var $remote_host = NULL;
  var $user_agent  = NULL;
  var $subject_host_hash = NULL;
  var $seek_cache_filename = NULL;
  var $enable_proxy = FALSE;
  var $debug_handler_names = FALSE;
  var $debug_handle_model_action = FALSE;

  function __construct() {/*{{{*/
    parent::__construct();
    $this->session_start_wrapper();
    $target_url                = $this->filter_post('url');
    $this->subject_host_hash   = UrlModel::get_url_hash($target_url,PHP_URL_HOST);
    $this->seek_cache_filename = UrlModel::get_url_hash($target_url);
    $this->seek_cache_filename = SYSTEM_BASE . "/../cache/seek-{$this->subject_host_hash}-{$this->seek_cache_filename}.generated";
    $this->enable_proxy        = $this->filter_post('proxy','false') == 'true';
    self::$enable_debug        = $this->filter_post('debug','false') == 'true';
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
    if (self::$enable_debug)  syslog( LOG_INFO, "----------------- (" . gettype($matchresult) . " {$matchresult})" . print_r($matches,TRUE));
    $initcaps    = create_function('$a', 'return ucfirst($a);');
    $nameparts   = array_map($initcaps, explode('.', $matches[2][0]));
    if (self::$enable_debug)  syslog( LOG_INFO, "----------------- " . print_r($nameparts,TRUE));
    if ( count($nameparts > 3) ) {
      krsort($nameparts);
      $nameparts = array_values($nameparts);
      while ( count($nameparts) > 3 ) array_pop($nameparts);
      krsort($nameparts);
      $nameparts = array_values($nameparts);
    }
    if (self::$enable_debug)  syslog( LOG_INFO, "----------------- " . print_r($nameparts,TRUE));
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
    $debug_method = C('DEBUG_'.__FUNCTION__,FALSE);
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
    if ( $debug_method ) {
    $this->recursive_dump($svg->extracted_styles,"(marker)");
    }
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

    $debug_method = C('DEBUG_'.__FUNCTION__,FALSE);
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
      'link',
      'reorder',
      'keyword',
      'fetchpdf',
      'preload',
      'proxyform',
      'constitutions',
    );

    $request_regex  = '@/(' . join('|',array_values($actions_lookup)) . ')/(.*)@i';

    if ( 1 == preg_match($request_regex, $request_uri, $actions_match) ) {

      if (('XMLHttpRequest' == $this->filter_server('HTTP_X_REQUESTED_WITH'))) {
      }
      else if (!('fetchpdf' == array_element($actions_match,1))) {
        if ( $debug_method ) $this->syslog( __FUNCTION__, __LINE__, "(marker) Not an XMLHttpRequest context. Leaving." );
        return NULL;
      } else {
        $_REQUEST['r'] = array_element($actions_match,2);
      }

      if ( $debug_method ) $this->recursive_dump($actions_match,"(marker) -- -- -- REMAP -- --");
      $_REQUEST['q'] = array_element($actions_match,1);
    }

  }/*}}}*/

  final protected function handle_model_action() {/*{{{*/

    $debug_method = C('DEBUG_'.__FUNCTION__,FALSE);

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
          $this->get_hostmodel()/*->set_linkset_root(TRUE)*/->stow();
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

      $this->syslog( __FUNCTION__, __LINE__, "(marker) Controller non-empty" );

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

  static function safe_json_encode($s) {/*{{{*/

    $pagecontent     = json_encode($s,JSON_UNESCAPED_UNICODE);
    $json_last_error = json_last_error();
    switch ( $json_last_error ) {
    case JSON_ERROR_NONE: break;
    case JSON_ERROR_CTRL_CHAR:
    case JSON_ERROR_UTF8:
      // Reencode every string element of the JSON response array
      $json_last_error = json_last_error_msg();
      JoinFilterUtility::syslog(__FUNCTION__,__LINE__,"(critical) - - - Reencoding due to error: {$json_last_error}" );
      array_walk($s,create_function(
        '& $a, $k', 'if ( is_string($a) ) $a = utf8_encode($a);'
      ));
      $pagecontent = json_encode($s,JSON_UNESCAPED_UNICODE);
      $json_last_error = json_last_error_msg();
      JoinFilterUtility::syslog(__FUNCTION__,__LINE__,"(critical) - - - JSON UTF8 encoding error. Reencode result: {$json_last_error}" );
      break;
    default:
      JoinFilterUtility::syslog(__FUNCTION__,__LINE__,"(critical) - - - Last JSON Error: {$json_last_error}" );
      break;
    }  
    return $pagecontent;

  }/*}}}*/

  function raw_json_reply(array & $json_reply) {
    $pagecontent = static::safe_json_encode($json_reply);
    header('Content-Type: application/json');
    header('Content-Length: ' . strlen($pagecontent));
    echo $pagecontent;
    exit(0);
  }

  function exit_cache_json_reply(array & $json_reply, $class_match = 'LegiscopeBase') {/*{{{*/
    if ( get_class($this) == $class_match ) {/*{{{*/
      //$cache_force = $this->filter_post('cache');
      $this->raw_json_reply($json_reply);
    }/*}}}*/
  }/*}}}*/

  function exit_emit_cached_content($target_url, $cache_force, $network_fetch) {/*{{{*/

    if ( C('ALLOW_LEGISCOPE_ADMIN_FRAMEWORK',FALSE) ) return;

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
    $debug_method = C('DEBUG_'.__FUNCTION__,FALSE);

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
      'by-wpdate'    => NULL,
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
      $handler_queue = array();
      foreach ( explode('/', $test_url_components['path']) as $path_part ) {
        $method_name = NULL;
        if ( empty($path_part) ) continue;
        $url_map[] = $path_part;
        $test_path = '/' . join('/',$url_map);
        $test_url = $test_url_components;
        $test_url['path'] = $test_path;
        $test_url = UrlModel::recompose_url($test_url);
        $test_path = str_replace('/','-',$test_path);
        $handler_queue[] = array(
          'handler_alias' => 'by-path-' . count($url_map) . "{$test_path}",
          'handler_name'  => 'seek_by_pathfragment_' . UrlModel::get_url_hash($test_url),
        );
      }
      while ( 0 < count($handler_queue) ) {
        extract( array_pop($handler_queue) );
        $method_map[$handler_alias] = $handler_name;
      }
    }/*}}}*/

    // Now try to construct a Y/M/D path handler
    $dir_sans_path = explode('/',$url_components['path']);
    $tail = array_pop($dir_sans_path);
    array_walk($dir_sans_path,create_function(
      '& $a, $k', '$a = preg_replace("@([0-9]{1,})@","([0-9]{1,".strlen($a)."})",$a);'
    ));
    $pathregex = join('/', $dir_sans_path); 
    array_push($dir_sans_path, $tail);
    $test_url = $url_components;
    unset($test_url['query']);
    unset($test_url['fragment']);
    $test_url['path'] = $pathregex;
    $test_url = UrlModel::recompose_url($test_url);
    if ( 1 == preg_match("@^{$test_url}@", $url->get_url()) ) {
      $method_map['by-wpdate'] = 'seek_wpdate_' . md5($test_url);
    }

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
    $debug_method = C('DEBUG_'.__FUNCTION__,FALSE);
    $extract_form   = create_function('$a', 'return array_key_exists("tagname", $a) && ("FORM" == strtoupper($a["tagname"])) ? $a : NULL;');
    $paginator_form = array_values(array_filter(array_map($extract_form, $containers)));
    if ( $debug_method ) $this->recursive_dump($paginator_form,'(marker) Old');
    return $paginator_form;
  }/*}}}*/

  function perform_network_fetch(
    & $url   , $referrer          , $target_url,
    $metalink, $debug_dump = FALSE
  ) {/*{{{*/

    $successful_fetch   = FALSE;
    $post_response_data = array();
    $captured_response  = 'POST';
		$get_before_post    = NULL;

    // I want to be able to wrap information in the fake POST action data
    // that can be used by site-specific handlers.  This information is not
    // forwarded to the target host, but instead held back, removed from the
    // POST data before the POST is actually executed, but remains available
    // to other methods that pass around the POST data.

    $skip_get           = FALSE;
    $skip_post          = FALSE;

    if ( is_array($metalink) ) {/*{{{*/

      $metalink_parameters = nonempty_array_element($metalink,'_LEGISCOPE_');

      if ( array_key_exists('_LEGISCOPE_', $metalink) ) {/*{{{*/
        if ( $debug_dump ) {/*{{{*/
          $this->syslog( __FUNCTION__, __LINE__, "(marker) Code provides a _LEGISCOPE_ POST parameter" );
          $this->recursive_dump($metalink_parameters,'(marker) -');
        }/*}}}*/
        unset($metalink['_LEGISCOPE_']);
      }/*}}}*/
      if ( nonempty_array_element($metalink_parameters,'referrer') ) {/*{{{*/
        $referrer = $metalink_parameters['referrer'];
        if ( $debug_dump ) $this->syslog( __FUNCTION__, __LINE__, "(marker) Force referrer {$referrer} for cURL action on {$target_url}" );
      }/*}}}*/
      if ( nonempty_array_element($metalink_parameters,'add_trailing_queryslash') ) {/*{{{*/
        // The Congress CMS is sensitive to trailing slashes before query params.
        // We need to modify BOTH the target URL and referrers to ensure we get
        // the response we expect.
        if ( $debug_dump ) $this->syslog( __FUNCTION__, __LINE__, "(marker) Modifying {$target_url}" );
        $target_url = preg_replace('@([^/])\?@i','$1/?', $target_url);
        $referrer   = preg_replace('@([^/])\?@i','$1/?', $referrer);
        if ( $debug_dump ) $this->syslog( __FUNCTION__, __LINE__, "(marker) Trailing slash before query part: {$target_url}" );
      }/*}}}*/
      if ( nonempty_array_element($metalink_parameters,'get_before_post') ) {/*{{{*/
        if ( $debug_dump ) $this->syslog( __FUNCTION__, __LINE__, "(marker) Reverse click traversal: GET before doing a POST action {$target_url}" );
        $get_before_post = TRUE;
        $skip_get = FALSE;
				$skip_post = FALSE;
				unset($metalink_parameters['_']);
      }/*}}}*/
      if ( nonempty_array_element($metalink_parameters,'skip_get',FALSE) ) {/*{{{*/
        if ( $debug_dump ) $this->syslog( __FUNCTION__, __LINE__, "(marker) Execute only a POST to {$target_url}" );
        $skip_get = TRUE;
      }/*}}}*/
      if ( nonempty_array_element($metalink_parameters,'no_skip_get',FALSE) ) {/*{{{*/
        if ( $debug_dump ) $this->syslog( __FUNCTION__, __LINE__, "(marker) Execute only a POST to {$target_url}" );
        $skip_get = FALSE;
      }/*}}}*/

      // FIXME:  SECURITY DEFECT: Don't forward user-submitted input 
      if ( 'SKIPGET' == nonempty_array_element($metalink,'_') ) {/*{{{*/
				$this->syslog( __FUNCTION__, __LINE__, "(critical) DEPRECATED Execute only a POST to {$target_url}" );
        if ( $debug_dump ) $this->recursive_dump($metalink,'(marker)');
        $skip_get = TRUE;
        unset($metalink['_']);
      }/*}}}*/
      if ( 'NOSKIPGET' == nonempty_array_element($metalink,'_') ) {/*{{{*/
        if ( $debug_dump ) {/*{{{*/
          $this->syslog( __FUNCTION__, __LINE__, "(marker) Execute POST, then GET, to {$target_url}" );
          $this->recursive_dump($metalink,'(marker)');
        }/*}}}*/
        $skip_get = FALSE;
        unset($metalink['_']);
      }/*}}}*/
      if ( nonempty_array_element($metalink,'_') == 1 ) {/*{{{*/
        // If $metalink['_'] == 1 then a GET operation is executed, skipping the POST action below.
        // Pager or other link whose behavior depends on antecedent state.
        // See GenericParseUtility::extract_pager_links(): param 4, when present, causes this key to be assigned 
        if ( $debug_dump ) {/*{{{*/
          $this->syslog( __FUNCTION__, __LINE__, "(marker) Skip POST to {$target_url}" );
          $this->recursive_dump($metalink,'(marker)');
        }/*}}}*/
        $skip_get = FALSE;
        $skip_post = TRUE;
        unset($metalink['_']);
      }/*}}}*/

    }/*}}}*/

    $modifier           = TRUE; // 'true' to execute HEAD instead of GET
    $session_cookie     = $this->filter_session("CF{$this->subject_host_hash}");
    $session_has_cookie = !is_null($session_cookie);
    $cookie_from_store  = NULL;
    $cookiestore        = SYSTEM_BASE . "/../cache/legiscope.{$this->subject_host_hash}.cookiejar";

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
    if ( $session_has_cookie ) {/*{{{*/
      if ( $debug_dump ) $this->syslog( __FUNCTION__, __LINE__, "(warning) Setting cookie '{$session_cookie}'");
      $curl_options[CURLOPT_COOKIE] = $session_cookie;
    }/*}}}*/
    if ( !is_null(C('LEGISCOPE_CURLOPT_PROXY')) && $this->enable_proxy ) {/*{{{*/
      $this->syslog(__FUNCTION__,__LINE__,"(marker) --- HACK: Enable proxy " . LEGISCOPE_CURLOPT_PROXY);
      $curl_options[CURLOPT_HTTPPROXYTUNNEL] = TRUE;
      $curl_options[CURLOPT_PROXY] = LEGISCOPE_CURLOPT_PROXY;
      $curl_options[CURLOPT_PROXYPORT] = LEGISCOPE_CURLOPT_PROXYPORT;
      $curl_options[CURLOPT_PROXYTYPE] = LEGISCOPE_CURLOPT_PROXYTYPE;
    }/*}}}*/
    $url_copy = str_replace(' ','%20',$target_url); // CurlUtility methods modify the URL parameter (passed by ref)

    if ( $debug_dump ) {/*{{{*/
      $this->syslog(__FUNCTION__,__LINE__,"(critical)          Referrer: {$referrer}");
      $this->syslog(__FUNCTION__,__LINE__,"(critical)        Target URL: {$target_url}");
      $this->syslog(__FUNCTION__,__LINE__,"(critical) Metalink/faux URL: {$target_url}");
      $this->syslog(__FUNCTION__,__LINE__,"(critical)     Metalink data: " . gettype($metalink));
      $this->syslog(__FUNCTION__,__LINE__,"(critical)     Metalink data: " . print_r($metalink,TRUE));
      $this->recursive_dump($metalink,'(critical)');
    }/*}}}*/

    $post_faux_url = NULL;

    // FIRST FETCH OPERATION 
    if ( is_array($metalink) && !$skip_post ) {/*{{{*/
      $post_faux_url = GenericParseUtility::url_plus_post_parameters($url_copy, $metalink);
			$response      = NULL;
			if ( TRUE == $get_before_post ) {
				$captured_response = 'GET';
				$response = CurlUtility::get($url_copy, $curl_options);
			} else {
        $captured_response = 'POST';
				$response = CurlUtility::post($url_copy, $metalink, $curl_options);
			}
      $transfer_info = CurlUtility::$last_transfer_info;
      $http_code     = intval(array_element($transfer_info,'http_code',400));
      $url_copy      = str_replace(' ','%20',$target_url); // Restore URL; CurlUtility methods modify the URL parameter (passed by ref)
      // Capture POST response
      $post_response_data[$captured_response] = array(
        'post_target'  => str_replace(' ','%20',$target_url),
        'content_hash' => sha1($response),
        'post_faux_url' => $post_faux_url, 
        'META' => array(
          'http_code'      => $http_code,
          'content_length' => nonempty_array_element($transfer_info,'download_content_length'),
          'content_type'   => nonempty_array_element($transfer_info,'content_type'),
          'request_header' => nonempty_array_element($transfer_info,'request_header'),
        ),
        'response'     => $response, // Raw cURL output, including cURL headers 
      );
      $successful_fetch = CurlUtility::$last_error_number == 0;
      if ( $debug_dump ) {
				$next        = $get_before_post ? "POST" : "GET";
        $fetch_state = $successful_fetch ? "OK" : "FAILED";
        $skipping    = $skip_get ? "Skipping {$next}" : "Will {$next}";
        $this->recursive_dump(CurlUtility::$last_transfer_info, "(critical) {$captured_response} {$fetch_state} {$skipping} transfer info");
      }
    }/*}}}*/

    $response = NULL;
    if ( !$skip_get ) {/*{{{*/
      if ( $debug_dump ) {/*{{{*/
        $this->syslog( __FUNCTION__, __LINE__, "(marker) Execute GET/HEAD to {$url_copy}" );
      }/*}}}*/
			if ( TRUE == $get_before_post ) {
				$captured_response = 'POST';
				$response = CurlUtility::post($url_copy, $metalink, $curl_options);
			} else {
				$captured_response = 'GET';
				$response = ($modifier == 'true')
					? CurlUtility::head($url_copy, $curl_options)
					: CurlUtility::get($url_copy, $curl_options)
					;
			}
      if ( $debug_dump ||
        !array_key_exists('http_code',CurlUtility::$last_transfer_info) ||
        !(200 == intval(CurlUtility::$last_transfer_info['http_code'])) ) {
           $this->recursive_dump(CurlUtility::$last_transfer_info, "(critical) {$captured_response} " . CurlUtility::$last_transfer_info['http_code'] );
        }
      $successful_fetch = CurlUtility::$last_error_number == 0;
    }/*}}}*/

    if ( $successful_fetch ) {/*{{{*/

      $transfer_info = CurlUtility::$last_transfer_info;
      $http_code = intval(array_element($transfer_info,'http_code',400));

      // If we used a metalink to specify POST action parameteers, we need to 
      // attach the response to the action URL.
      if ( is_array($metalink) && (0 < strlen($target_url)) ) {/*{{{*/
        // $captured_response is either 'POST' or 'GET', depending on whether we do POST or POST+GET
        if ( array_key_exists($captured_response, $post_response_data) ) $captured_response = "{$captured_response}_2";
        if ( !is_null($response) )
        $post_response_data[$captured_response] = array(
          'post_target'   => $url_copy,
          'content_hash'  => sha1($response),
          'post_faux_url' => $post_faux_url,
          'get_url'       => TRUE,
          'META' => array(
            'http_code'      => $http_code,
            'content_length' => nonempty_array_element($transfer_info,'download_content_length'),
            'content_type'   => nonempty_array_element($transfer_info,'content_type'),
            'request_header' => nonempty_array_element($transfer_info,'request_header'),
          ),
          'response' => $response, // Raw cURL output, including cURL headers 
        );
        $this->syslog(__FUNCTION__,__LINE__,"(warning) - - Generated POST response data");
      }/*}}}*/

      if ( (400 <= $http_code && $http_code < 500) || (500 < $http_code && $http_code < 600)  ) {/*{{{*/

        $this->syslog( __FUNCTION__, __LINE__, "(critical) cURL last_transfer_info" );
        $this->recursive_dump($transfer_info, "(critical) HTTP {$http_code}");
        $successful_fetch = FALSE;

      }/*}}}*/
      else {

        // Take cookie jar contents and place them in our per-host session cookie store
        if ( file_exists($cookiestore) ) {/*{{{*/
          $cookie_from_store = CurlUtility::parse_cookiejar($cookiestore, TRUE);
          if ( !(FALSE === $cookie_from_store) ) {
            $_SESSION["CF{$this->subject_host_hash}"] = $cookie_from_store; 
          }
        }/*}}}*/

        if ( !is_array($metalink) ) {
          $url->set_pagecontent_c($response);
          if (!(0 < strlen($url->get_url()))) $url->set_url($target_url,FALSE);
          $url->increment_hits(); // This only sets the 'hits' and 'last_fetch' attribute
          $url_id = $url->stow();
          $this->syslog(__FUNCTION__,__LINE__,"(marker) PF - - - #{$url_id} {$target_url}");
        }
        else {/*{{{*/

          $contenthash = sha1($response);

          $action = $url->in_database() ? "Matched" : "Created";

          $url_id = $url->
            set_url_c($target_url,FALSE)->
            set_content_hash(sha1($response))->
            set_pagecontent_c($response)->
            attach_post_response($post_response_data)->
            increment_hits()->
            fields(array('pagecontent','update_time','content_length','content_hash','last_modified','content_type','hits','response_header'))->
            stow();

          $successful_fetch = ( ( 0 < intval($url_id) ) &&
            ( 0 < intval(nonempty_array_element($post_response_data,'id')) ) &&
            ( 0 < intval(nonempty_array_element($post_response_data,'join_id')) )
           );

          if ( $debug_dump || !$successful_fetch ) {
            $this->syslog(__FUNCTION__,__LINE__,"(marker) MF - - - {$action} #{$url_id} {$target_url}");
            $this->syslog(__FUNCTION__,__LINE__,"(marker) Stow URL content " . ($successful_fetch ? "OK (id #{$post_response_data['id']})" : "FAILED") . " [content hash {$contenthash}]" );
            $this->recursive_dump($post_response_data,"(marker) ?" );
          }

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

  function get_template_filename($template_name, $for_class = NULL) {/*{{{*/
    if ( is_null($for_class) ) {
      $for_class = get_class($this);
    } 
    $for_class = join('.',array_reverse(camelcase_to_array($for_class)));
    $template_filename = array(SYSTEM_BASE,'templates',$for_class,$template_name);
    return join('/',$template_filename); 
  }/*}}}*/

  function get_template($template_name, $for_class = NULL) {/*{{{*/
    $fn = $this->get_template_filename($template_name, $for_class);
    return file_exists($fn) ? @file_get_contents($fn) : NULL;
  }/*}}}*/

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
        if (C('DEBUG_PHLEGISCOPE',FALSE)) {
          syslog( LOG_INFO, get_class($this) . "::" . __FUNCTION__ . '(' . __LINE__ . '): ' .
            "Got boolean option '{$name}' = {$value}" );
        }
        update_option($name, $is_true ? 'TRUE' : 'FALSE');
      } else if (is_string($value)) {
        if (C('DEBUG_PHLEGISCOPE',FALSE)) {
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
        if (C('DEBUG_PHLEGISCOPE',FALSE)) {
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

  static function include_map() {/*{{{*/
  }/*}}}*/

  static function phlegiscope_main() {/*{{{*/

    // ADMIN HOME PAGE
    syslog( LOG_INFO, get_class($this) . "::" . __FUNCTION__ . '(' . __LINE__ . '): ' .
      " Loading main template " );

    $map_image = static::emit_basemap(1.4,TRUE);

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

  static function legiscope_admin_xml_ns() {/*{{{*/
    syslog( LOG_INFO, __METHOD__ . ": " );
    $nsparts = array(
      'xmlns:svg="http://www.w3.org/2000/svg"',
      'xmlns:xlink="http://www.w3.org/1999/xlink"',
    );
    return join(' ', $nsparts);
  }/*}}}*/

  static function admin_post() {/*{{{*/
    // No need to invoke image_, javascript_, or stylesheet_request,
    // as we'll let WordPress handle dispatch of those resources.
    syslog( LOG_INFO, __METHOD__ . ": " );
    static::model_action();
    $o = @ob_get_clean();
    exit(0);
  }/*}}}*/

  public static function emit_basemap($scale = NULL, $return = FALSE) {/*{{{*/
    $m = str_replace(
      array(
        '{svg_inline}',
        '{scale}',
      ),
      array(
        static::$singleton->transform_svgimage(SYSTEM_BASE . "/../images/admin/philippines-4c.svg"),
        is_null($scale) ? 1.4 : floatval($scale),
      ),
      static::$singleton->get_template('map.html','global')
    );
    if ( $return ) return $m;
    echo $m;
  }/*}}}*/

  static function register_userland_menus() {/*{{{*/

    register_nav_menus(
      array(
        'legiscope-header-menu' => __( 'Header Menu' ),
      )
    );

  }/*}}}*/

  static function wordpress_enqueue_scripts() {/*{{{*/

    $debug_method = C('DEBUG_'.__FUNCTION__,FALSE);

    $plugins_url   = plugins_url();
    $themes_uri    = get_template_directory_uri();
    $spider_js_url = plugins_url('legiscope.js'    , LEGISCOPE_JS_PATH . '/' . 'legiscope.js');
    $fx_js_url     = plugins_url('jquery-ui.min.js', LEGISCOPE_JS_PATH . '/ui/' . 'jquery-ui-min.js');

    wp_register_script('legiscope-spider', $spider_js_url, array('jquery'), NULL);
    wp_enqueue_script('legiscope-spider' , $spider_js_url, array('jquery'), NULL);

    wp_register_script('legiscope-fx'    , $fx_js_url    , array('jquery'), NULL);
    wp_enqueue_script('legiscope-fx'     , $fx_js_url    , array('jquery'), NULL);

    static::register_userland_menus();

    syslog( LOG_INFO, "- - - - -- - - - -- - - - -- - - - - END-USER   - - - - - " . $plugins_url);

    define('CONTEXT_ENDUSER',TRUE);

    if ( $debug_method ) {/*{{{*/
      syslog( LOG_INFO, "- - - - -  Plugin: " . LEGISCOPE_PLUGIN_NAME);
      syslog( LOG_INFO, "- - - - - Basenam: " . plugin_basename(__FILE__));
      syslog( LOG_INFO, "- - - - - Plugins: " . $plugins_url);
      syslog( LOG_INFO, "- - - - -  Themes: " . $themes_uri);
      syslog( LOG_INFO, "- - - - -  Spider: " . $spider_js_url);
      syslog( LOG_INFO, "- - - - -      JS: " . LEGISCOPE_JS_PATH);
      syslog( LOG_INFO, "- - - - -     CSS: " . LEGISCOPE_CSS_PATH);
    }/*}}}*/

  }/*}}}*/

  static function wordpress_enqueue_admin_scripts() {/*{{{*/

    $debug_method = C('DEBUG_'.__FUNCTION__,FALSE);

    $plugins_url = plugins_url();
    $themes_uri  = get_template_directory_uri(); 

    $spider_js_url = plugins_url('legiscope.js'       , LEGISCOPE_JS_PATH . '/' . 'legiscope.js');
    $pdf_js_url    = plugins_url('pdf.js'             , LEGISCOPE_JS_PATH . '/' . 'pdf.js');
    $admin_css_url = plugins_url('legiscope-admin.css', LEGISCOPE_CSS_PATH . '/legiscope-admin.css');
    $fx_js_url     = plugins_url('jquery-ui.min.js'   , LEGISCOPE_JS_PATH . '/ui/' . 'jquery-ui-min.js');

    wp_register_style( 'legiscope_wp_admin_css', $admin_css_url   , false          , '1.0.0' );
    wp_enqueue_style( 'legiscope_wp_admin_css' , $admin_css_url );

    wp_register_script('legiscope-pdf'         , $pdf_js_url      , array('jquery'), NULL);
    wp_register_script('legiscope-spider'      , $spider_js_url   , array('jquery'), NULL);

    wp_enqueue_script('legiscope-pdf'          , $pdf_js_url      , array('jquery'), NULL);
    wp_enqueue_script('legiscope-spider'       , $spider_js_url   , array('jquery'), NULL);

    $inline_script = <<<EOH
<script type="text/javascript">
  PDFJS.workerSrc = 'js/pdf.js';
</script>
EOH;

    syslog( LOG_INFO, "- - - - -- - - - -- - - - -- - - - - ADMINISTRATOR   - - - - - " . $plugins_url);

    if ( $debug_method ) {/*{{{*/
      syslog( LOG_INFO, "- - - - -  Plugin: " . LEGISCOPE_PLUGIN_NAME);
      syslog( LOG_INFO, "- - - - - Basenam: " . plugin_basename(__FILE__));
      syslog( LOG_INFO, "- - - - - Plugins: " . $plugins_url);
      syslog( LOG_INFO, "- - - - -  Themes: " . $themes_uri);
      syslog( LOG_INFO, "- - - - -  Spider: " . $spider_js_url);
      syslog( LOG_INFO, "- - - - -  Styles: " . $admin_css_url);
      syslog( LOG_INFO, "- - - - -      JS: " . LEGISCOPE_JS_PATH);
      syslog( LOG_INFO, "- - - - -     CSS: " . LEGISCOPE_CSS_PATH);
    }/*}}}*/

  }/*}}}*/

  static function handle_stash_post( & $response, $restricted_request_uri, $cache_path ) 
  {/*{{{*/

    $debug_method = C('DEBUG_'.__FUNCTION__,FALSE); 

    if ( $debug_method ) syslog( LOG_INFO, "(marker) -- {$_SERVER['REQUEST_METHOD']} REQUEST_URI: {$restricted_request_uri}");

    if (!(C('ENABLE_SECTION_STASHING') == TRUE)) 
      return;

    $user = wp_get_current_user();

    if (!($user->exists()))
      return;

    // Accept caching request
    $path_part = preg_replace('/^\/stash\//i','', $restricted_request_uri);
    $path_hash = hash('sha256', $path_part.NONCE_SALT);
    $slug      = substr($_REQUEST['slug'],0,255);
    $title     = stripcslashes(htmlspecialchars(substr($_REQUEST['title'],0,255)));
    $selected  = substr($_REQUEST['selected'],0,255);
    $summary   = substr($_REQUEST['summary'],0,4000);
    $link      = array_key_exists('link', $_REQUEST) ? substr($_REQUEST['link'],0.255) : NULL;

    $linkset   = array();
    // path_hash refers to the Article table ID; unused presently
    if ( is_array($_REQUEST['sections']) ) foreach ( $_REQUEST['sections'] as $index => $contents ) {/*{{{*/
      if ( is_array($contents['contents']) ) foreach ( $contents['contents'] as $content ) {
        // Skip empty ident
        $ident = substr($content['ident'],0,255);
        if ( 0 == strlen($ident) ) 
          continue;

        // Test for target file
        $filename = hash('sha256', $ident.NONCE_SALT);
        $filepath = "${cache_path}/{$filename}";
        if (file_exists($filepath)) {
          $response['available']++;
          continue;
        }

        // Store section text with metadata as JSON string
        $packed_section = array(
          'title'   => $title,
          'article' => $slug,
          'slug'    => $ident,
          'content' => $content['content'],
          'links'   => array(),
        );
        file_put_contents($filepath, json_encode($packed_section));
        $response['status']++;
        syslog( LOG_INFO, "(marker) -- Target file {$filename}" );
      }
    }/*}}}*/
    if ( !is_null($link) && !empty($selected) ) {

      // Collect the sections to which the submitted link applies
      $match = NULL;
      if ( is_array($_REQUEST['links']) ) foreach ( $_REQUEST['links'] as $index => $section ) {
        $match = ( $section == $selected ) ? " (THIS)" : "";
        if ( $debug_method ) syslog( LOG_INFO, "(marker) --  RQ: {$index} -> {$section}{$match}" );
      }

      $section_filename =is_null($match) 
        ? hash('sha256', $slug.NONCE_SALT)
        : hash('sha256', $selected.NONCE_SALT)
        ;

      $filepath = "${cache_path}/{$section_filename}";
      $section_file_present = file_exists($filepath);
      $section_file_mark = $section_file_present ? "Present" : "???";

      if ( $debug_method ) { 
        syslog( LOG_INFO, "(marker) --     File: {$filepath}" );
        syslog( LOG_INFO, "(marker) --  Section: {$selected} {$section_file_present}" );
        syslog( LOG_INFO, "(marker) --     Slug: {$slug}" );
        syslog( LOG_INFO, "(marker) --    Title: {$title}" );
        syslog( LOG_INFO, "(marker) --     Link: {$link}" );
        syslog( LOG_INFO, "(marker) --  Summary: {$summary}" );
      }

      // The link is associated with the selected section, stored in the 
      // same flat file as the section text.

      // If no section link is contained in the cell that triggered this event,
      // then the link will be stored in the Article's JSON file.
      $parsed_link = parse_url($link);
      if ( FALSE === $parsed_link ) {/*{{{*/
        syslog( LOG_INFO, "Unparseable URL provided '{$link}'" );
      }/*}}}*/
      else if ( !preg_match('@^https?@',$parsed_link['scheme']) ) {/*{{{*/
        syslog( LOG_INFO, "Invalid link scheme '{$parsed_link['scheme']}'" );
      }/*}}}*/
      else if ( file_exists("{$filepath}.lock") ) {/*{{{*/
        syslog( LOG_INFO, "Section file " . basename($filepath) . " is locked. Notify retry" );
        if (file_exists("{$filepath}.lock"))
          unlink("{$filepath}.lock");
      }/*}}}*/
      else if ( !$section_file_present ) {/*{{{*/
        // No operation if section file not yet recorded
        syslog( LOG_INFO, "Section '{$selected}' is not yet cached." );
      }/*}}}*/
      else if ( FALSE === ($current_json = file_get_contents($filepath)) ) {/*{{{*/
        syslog( LOG_INFO, "Unable to read cached section file " . basename($filepath) );
      }/*}}}*/
      else if ( FALSE === ($json = json_decode($current_json, TRUE)) ) {/*{{{*/
        syslog( LOG_INFO, "Corrupt section file " . basename($filepath) );
      }/*}}}*/
      else {/*{{{*/
        
        file_put_contents( "{$filepath}.lock", $current_json );

        $current_json = NULL;

        if ( $debug_method ) { 
          syslog( LOG_INFO, "Submit parsed link {$link}" );
          syslog( LOG_INFO, "Target file for section is {$filepath}" );
        }
        if ( !array_key_exists('linkset', $json) ) 
          $json['linkset'] = array(
            'links' => 0,
            'link' => array()
          ); 

        $linkhash = hash('sha256', $link);

        if ( array_key_exists( $linkhash, $json['linkset']['link'] ) ) { 
          $json['linkset']['link'][$linkhash]['updated'] = time();
          $json['linkset']['link'][$linkhash]['title']   = $title;
          $json['linkset']['link'][$linkhash]['summary'] = $summary;
        }
        else
          $json['linkset']['link'][$linkhash] = array(
            'approved' => FALSE,
            'added'    => time(),
            'updated'  => time(),
            'title'    => $title,
            'link'     => $link,
            'summary'  => $summary,
          );

        $current_json = json_encode($json);

        if ( FALSE === file_put_contents( $filepath, $current_json ) ) {
          syslog( LOG_INFO, "Unable to write section file " . basename($filepath) );
        }
        else {
          syslog( LOG_INFO, "Updated " . $filepath );
        }

        // Release file lock
        unlink("{$filepath}.lock");

        $linkset = $json['linkset']['link'];

      }/*}}}*/
      self::generate_commentary_box( $response, $linkset );
    }
  }/*}}}*/

  static function generate_commentary_box( & $response, $commentary_links ) 
  {/*{{{*/
    $test_title         = "Comment on " . date('d M Y @ H:i:s'); // Rappler Talk: Albert del Rosario on Duterte gov’t’s refusal to enforce Hague ruling
    $test_url           = "https://avahilario.net"; // https://www.rappler.com/newsbreak/rich-media/207467-interview-albert-del-rosario-duterte-arbitral-ruling
    $test_summary       = "Optional summary text";

    $commentary         = NULL;
    $commentary_linkset = array();
    $slug               = $response['slug'];

    $user = wp_get_current_user();
    if ( $user->exists() ) {
      $separator = NULL;
      if ( 0 < count($commentary_links) )
        $separator = '<hr/>';
      $commentary = <<<EOH
{$separator}
<label class="constitution-commentary" for="comment-title">Title:</label><input type="text" title="{$test_title}" id="comment-title"></input>
<label class="constitution-commentary" for="comment-url">Link:</label><input type="text" title="{$test_url}" id="comment-url"></input>
<label class="constitution-commentary" for="comment-summary">Summary:</label><input type="text" title="{$test_summary}" id="comment-summary"></input>
<input id="comment-send" value="Record" type="submit" class="button button-primary button-large" style="display: block; float: right; margin-top: 4px;"/>
EOH;
    }
    if ( 0 < count($commentary_links) ) {
      foreach( $commentary_links as $linkhash => $components ) {
        $components['title'] = stripcslashes($components['title']);
        $components['summary'] = stripcslashes($components['summary']);
        $summary_comment = NULL;
        if ( 0 < strlen($components['summary']) ) { 
          $summary_comment =<<<EOH
<div class="constitution-commentary-comment" style="font-family: Arial, Helvetica; display: block; float: right; clear: both; margin-left: 20%; margin-top: 1em; margin-bottom: 1em;">{$components['summary']}</div>
EOH;
        }
        $commentary_linkset[] =<<<EOH
<a class="external-link" href="{$components['link']}" target="_commentary" style="text-decoration: none !important;">{$components['title']}</a>
{$summary_comment}
EOH;
      }
      $commentary_linkset = join($commentary_linkset,'<br/>');
    }
    else $commentary_linkset = "";
    if ( 0 < count($commentary_links) || $user->exists() )
      $response['content'] =<<<EOH
<div id="slug-commentary-{$slug}">
{$commentary_linkset}{$commentary}
</div>
EOH;
  }/*}}}*/

  static function handle_stash_get( & $response, $restricted_request_uri, $cache_path ) 
  {/*{{{*/
    // Data received:
    // - $_REQUEST['slug']: Constitution Article table slug
    // - $_REQUEST['selected']: Constitution Section link slug
    // - $_REQUEST['sections']: Array of Section slugs from a row of comparable Section entries
    // Generate markkup showing comments attached to comparable Sections 
    //
    // Relationships:
    // Commentary -> Section -> SectionVariant -> Article -> Version
    //
    // Only one SectionVariant record is ever used in this method
    $user = wp_get_current_user();

    $debug_method = $user->exists();// C('DEBUG_'.__FUNCTION__,FALSE);

    $constitution_version    = NULL;
    $constitution_article    = NULL;
    $section_variants        = NULL;
    $constitution_section    = NULL;
    $constitution_commentary = new ConstitutionCommentaryModel();

    if ( $user->exists() ) {/*{{{*/
      $constitution_version    = new ConstitutionVersionModel();
      $constitution_article    = new ConstitutionArticleModel();
      $section_variants        = new ConstitutionSectionVariantsModel();
      $constitution_section    = new ConstitutionSectionModel();
    }/*}}}*/

    $path_part = preg_replace('/^\/stash\//i','', $restricted_request_uri);
    $slug      = substr($_REQUEST['slug'],0,255); // Article table slug
    $article   = preg_replace('/^a-/','', $slug);
    $selected  = preg_replace('/^a-/','', substr($_REQUEST['selected'],0,255)); // Section link
    $const_ver = preg_replace('@.*-([0-9]{1,})$@i','$1', $selected); // Table column number, constitution version proxy

    if ( $debug_method && $user->exists() ) {
      $constitution_section->
        syslog(  __FUNCTION__, __LINE__, "(marker) -- - --- {$selected} " . urldecode($_SERVER['REQUEST_URI']))->
        recursive_dump($_REQUEST, "(marker) -- - --- ");
    }

    $commentary_links = [];
    $version_to_article = NULL;

    $article_record = NULL;

    // Load Constitution version/revision and Article records
    if ( $user->exists() ) {/*{{{*/
      // Database reload tests and updates only occur when the remote user is authenticated.
      $constitution_record = NULL;

      $constitution_version->
        fetch_by_revision($constitution_record, $const_ver); 
        
      $article_in_db = $constitution_article->
        fetch_by_slug($article_record, $article, $constitution_record);

      if ( !$article_in_db )
        $article_in_db = $constitution_article->
          create_article_record( $article_record, $slug, $article, $constitution_record );

      $constitution_commentary->
        syslog(  __FUNCTION__, __LINE__, "(marker) --- - --- ITERATING {$selected} --- - ---" );

    }/*}}}*/

    $section_record = NULL;
    $collected_sections = [];

    if ( is_array($_REQUEST['sections']) ) foreach ( $_REQUEST['sections'] as $index => $section_slug ) {
      // Skip empty ident
      $ident = substr($section_slug,0,255);
      if ( 0 == strlen($ident) ) 
        continue;

      // Derive source file/constitution_commentary_model.linkhash
      $filename = hash('sha256', $ident.NONCE_SALT);
      $filepath = "${cache_path}/{$filename}";

      // Fetch Section record
      if ( $user->exists() ) {/*{{{*/

        $section_record = [];
        $constitution_section
          ->set_id(NULL)
          ->fetch_by_section_slug( $section_record, $section_slug );

      }/*}}}*/

      // Fetch JSON-wrapped section 
      $section_json = file_get_contents($filepath);
      $section = json_decode($section_json, TRUE);

      // Update Section JSON file
      $updated = FALSE;

      if ( !array_key_exists('constitution_version', $section) ) {
        $section['constitution_version'] = $const_ver;
        $updated = TRUE;
      }

      if ( !array_key_exists('linkset', $section) ) {
        $section['linkset'] = array(
          'links' => 0,
          'link' => array(),
        );
        $updated = TRUE;
      }

      if ( $updated ) file_put_contents($filepath, json_encode($section));

      $variants_record = NULL;

      if ( $user->exists() ) {/*{{{*/

        if ( $section_slug == $selected )
          $constitution_section->syslog(  __FUNCTION__, __LINE__, "(marker) -- MATCH Got link {$filename} {$component['link']}");

        $constitution_section->
          generate_missing_section( $section_record, $section_slug, $variants_record, $section['content'] )->
          syslog( __FUNCTION__, __LINE__, "(marker) -- Retrieved section #{$section_record['id']}" )->
          recursive_dump( $section_record, "(marker) -- S --" );

      }/*}}}*/

      if ( 0 < count($section['linkset']['link']) ) {

        foreach ( $section['linkset']['link'] as $linkhash => $component ) {

          if ( $user->exists() ) {/*{{{*/

            $commentary_record = [];

            if (!$constitution_commentary->fetch_by_linkhash($commentary_record, $linkhash)) {/*{{{*/

              $stow_data = [ 
                'section'  => $section_record['id'],
                'linkhash' => $linkhash,
                'summary'  => $component['summary'],
                'title'    => $component['title'],
                'link'     => $component['link'],
                'approved' => $component['approved'] ? "1" : "0",
                'added'    => date('Y-m-d H:i:s', $component['added']),
                'updated'  => date('Y-m-d H:i:s', $component['updated']),
              ];

              $commentary_id = $constitution_commentary
                ->set_id(NULL)
                ->syslog( __FUNCTION__, __LINE__, "(marker) -->>> {$component['summary']}" )
                ->store_commentary_record( $commentary_record, $stow_data );

              if ( 0 < $commentary_id ) 
                $collected_sections[] = [
                  'section'    => $section_record['id'],
                  'commentary' => $commentary_id,
                  'variants'   => $section_record['variant']
                ];

            }/*}}}*/
            else
              $collected_sections[] = [
                'section'    => $section_record['id'],
                'commentary' => $commentary_record['id'],
                'variants'   => $section_record['variant']
              ];

          }/*}}}*/
          else {
            if ( $debug_method && $user->exists() ) $constitution_commentary
              ->syslog( __FUNCTION__, __LINE__, "(marker) --<<< {$component['summary']}" )
              ->recursive_dump($commentary_record, "(marker) --<<<" );
          }

          $commentary_links[$linkhash] = $component;
          if ( $debug_method ) $constitution_commentary->syslog( __FUNCTION__, __LINE__, "(marker) -- Variant #{$variants_record['id']} Stash Got link {$filename} {$component['link']}");
        }
      }

      if ( $debug_method ) syslog( LOG_INFO, "(marker) -- {$filename} ({$ident}) " . strlen($section_json) );
    }

    if ( $user->exists() ) {

      // Commentary <-> Section <-> SectionVariants <-> Article
      // - SectionVariants foreign table Sections may change as comparable sections are slid about
      // - SectionVariants decouple Constitution versions from Sections:
      //   - Sections of multiple Constitution versions are joined to this record.
      $constitution_commentary
        ->syslog(  __FUNCTION__, __LINE__, "(marker) -- Final Commentary - Section map")
        ->recursive_dump($collected_sections, "(marker) --");
 
      $variant_join_obj = $constitution_section->get_join_object('variant','join');

      $joined_table = [];
      $variant_join_obj
        ->syslog(  __FUNCTION__, __LINE__, "(marker) -- Try fetching SectionVariant Join.  Type: " . get_class($variant_join_obj) . (is_array($variant_join_obj) ? " n(".count($variant_join_obj).")" : "") )
        ->fetch_by_linked_sections( $joined_table, $collected_sections )
        ->syslog(  __FUNCTION__, __LINE__, "(marker) -- Result type: " . gettype($joined_table) )
        ->recursive_dump( $joined_table, "(marker) --" );
     
      if ( is_null( $joined_table ) ) { /*{{{*/
        // Generate a single ConstitutionSectionVariant join to ConstitutionArticle
        $variants_record = [];
        $section_variants
          ->syslog(  __FUNCTION__, __LINE__, "(marker) -- Generating SectionVariant <-> Article join ..." )
          ->set_id(NULL)
          ->generate_joinwith_article( $variants_record, $article_record )
          ->syslog(  __FUNCTION__, __LINE__, "(marker) -- Generated SectionVariant <-> Article join " . gettype($variants_record) )
          ->recursive_dump( $variants_record, "(marker) -- J(A[{$article_record['id']}],S[{$section_record['id']}]) --")
          ;
        foreach ( $collected_sections as $commentaries ) {
          // Generate ConstitutionSection join to ConstitutionSectionVariant
          // VERIFIED CORRECT USAGE OF create_joins()
          // $join = [ $ft_id => [ $join_table[get_class($this)] => $this->get_id() ];
          // $this->create_joins( $ft_typename, $join );

          $section_to_variant = [ $variants_record['id'] ];
          $constitution_section
            ->set_id($commentaries['section'])
            ->create_joins('ConstitutionSectionVariantsModel', $section_to_variant);

          $create_join_result = [];
          $commentary_to_section = [ $commentaries['section'] ];
          $create_join_result = $constitution_commentary
            ->syslog( __FUNCTION__, __LINE__, "(marker) -- Recorded commentary #{$commentaries['id']}.{$commentaries['section']}.{$commentaries['variants']}." )
            ->set_id($commentaries['commentary'])
            ->create_joins('ConstitutionSectionModel',$commentary_to_section,FALSE);
          $constitution_commentary
            ->syslog(  __FUNCTION__, __LINE__, "(marker) -- - --- - Executed ConstitutionSectionVariantsModel::create_joins(), result " . gettype($result))
            ->recursive_dump($commentary_to_section, "(marker) -- - --- -");
        }
      }/*}}}*/

    }

    $response['received'] = $_REQUEST['sections'];
    $response['slug']     = $slug;

    self::generate_commentary_box( $response, $commentary_links );

  }/*}}}*/

  static function handle_constitutions_get( $restricted_request_uri ) 
  {/*{{{*/

    // Datum received is the Constitution section slug [title-section(-subsection)-column]
    // - -column encodes the Constitution version
    // Comment data stored in JSON flat files 
    // Generate markup showing comments attached to comparable Sections 
    //
    // Relationships:
    // Commentary -> Section -> SectionVariant -> Article -> Version
    //
    $user = wp_get_current_user();

    $debug_method = C('DEBUG_'.__FUNCTION__,FALSE); 

    $consti_version          = new ConstitutionVersionModel();
    $article_model           = new ConstitutionArticleModel();
    $section_variants        = new ConstitutionSectionVariantsModel();
    $section_model           = new ConstitutionSectionModel();
    $constitution_commentary = new ConstitutionCommentaryModel();

    if ( $debug_method ) $constitution_commentary->syslog( __FUNCTION__, __LINE__, "(marker) -- {$_SERVER['REQUEST_METHOD']} REQUEST_URI: {$restricted_request_uri}");

    $path_part = preg_replace('/^\/constitutions\//i','', $restricted_request_uri);
    // Screen out non-alphanumeric, non-dash characters
    $path_part = preg_replace('@[^a-z0-9-]@i','',$path_part);
    $constitution_version_n = preg_replace('@.*-([0-9]{1,})$@i','$1', $path_part); 
    $path_hash = hash('sha256', $path_part.NONCE_SALT);
    $cached_file = SYSTEM_BASE . '/../cache/' . $path_hash;

    $record = NULL;
    $constitution_commentary->debug_final_sql = FALSE;
    $constitution_version = NULL;

    if ( 1 == strlen($constitution_version_n) ) {
      $consti_version->
        where( [ 'revision' => $constitution_version_n ] )->
        record_fetch_continuation($constitution_version)->
        syslog( __FUNCTION__, __LINE__, "(marker) -- Remote {$_SERVER['HTTP_USER_AGENT']}")->
        syslog( __FUNCTION__, __LINE__, !is_null($constitution_version)
          ? "(marker) -- Have Constitution #{$constitution_version['revision']} - {$constitution_version['title']}"
          : "(marker) -- Missing Constitution #{$constitution_version_n}"
        )->
        recursive_dump($constitution_version,"(marker) --- -- -----");
    }

    if ( file_exists( $cached_file ) ) {
      // The content is wrapped in JSON: 
      $server_name     = $_SERVER['SERVER_NAME'];
      $section_content = file_get_contents($cached_file);
      $json            = json_decode($section_content, TRUE);
      $slug            = stripcslashes($json['slug']);
      $content         = stripcslashes($json['content']);

      if ( $debug_method ) {
        $constitution_commentary->syslog( __FUNCTION__, __LINE__, "(marker) -- Revision: " . $constitution_version_n);
        $article_model->          syslog( __FUNCTION__, __LINE__, "(marker) -- article: " . $json['article']);
        $article_model->          syslog( __FUNCTION__, __LINE__, "(marker) --   title: " . $json['title']);
        $constitution_commentary->syslog( __FUNCTION__, __LINE__, "(marker) --    slug: " . $json['slug']);
        $constitution_commentary->syslog( __FUNCTION__, __LINE__, "(marker) -- content: " . $json['content']);
        $consti_version->recursive_dump($json, "(marker) - -- ---"); 
      }

      $microtemplate   = NULL;

      if ( array_key_exists('linkset', $json) ) {

        // $related_records = [];
        $section_model
          ->syslog( __FUNCTION__, __LINE__, "(marker) -- Retrieving related slugs" )
          ->fetch_related_sections( $json, $path_part )
          ->recursive_dump( $json, "(marker) -- RR --" );

        foreach ( $json['linkset']['link'] as $hash => $commentary ) {

          $url     = $commentary['link'];
          $linkhash = hash('sha256', $url.NONCE_SALT.AUTH_SALT);
          $summary = stripcslashes($commentary['summary']);
          $title   = stripcslashes($commentary['title']);

          syslog( LOG_INFO, "(marker) -- {$commentary['link']}" );

          $summary = ( 0 < strlen($summary) ) 
            ? "<p>{$summary}</p>"
            : NULL;
          $microtemplate .= <<<EOH
<div class="commentary-external-links" id="{$linkhash}">
<a href="{$url}"><h3 style="font-family: Arial, Helvetica">{$title}</h3></a>
{$summary}
</div>

EOH;
        }  
      }

      $constitution_version_title = isset($constitution_version['title'])
        ? <<<EOH
<h4><span>{$constitution_version['title']}</span></h4>
EOH
        : NULL
        ;

      $timed_reload = $debug_method
        ? NULL
        : <<<EOH
<script type="text/javascript" src="/wp-content/plugins/phlegiscope/js/constitutions.js">[Scripting Disabled]</script>
EOH;
      $microtemplate = <<<EOH
<!DOCTYPE html>
<html>
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
  <meta name="viewport" content="width=device-width">
  <meta name="robots" content="noindex,follow">
  <script type="text/javascript" src="https://{$server_name}/wp-includes/js/jquery/jquery.js?ver=1.12.4"></script>
  <title>{$json['title']}</title>
  <style type="text/css">
    html {
      background: #f1f1f1;
    }
    body {
      background: #fff;
      color: #444;
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
      margin: 2em auto;
      padding: 1em 2em;
      max-width: 700px;
      -webkit-box-shadow: 0 1px 3px rgba(0,0,0,0.13);
      box-shadow: 0 1px 3px rgba(0,0,0,0.13);
    }
    h1 {
      border-bottom: 1px solid #dadada;
      clear: both;
      color: #666;
      font-size: 24px;
      margin: 30px 0 0 0;
      padding: 0;
      padding-bottom: 7px;
    }
    #error-page {
      margin-top: 50px;
    }
    #error-page p {
      font-size: 14px;
      line-height: 1.5;
      margin: 25px 0 20px;
    }
    #error-page code {
      font-family: Consolas, Monaco, monospace;
    }
    ul li {
      margin-bottom: 10px;
      font-size: 14px ;
    }
    a {
      color: #0073aa;
    }
    a:hover,
    a:active {
      color: #00a0d2;
    }
    a:focus {
      color: #124964;
        -webkit-box-shadow:
          0 0 0 1px #5b9dd9,
        0 0 2px 1px rgba(30, 140, 190, .8);
        box-shadow:
          0 0 0 1px #5b9dd9,
        0 0 2px 1px rgba(30, 140, 190, .8);
      outline: none;
    }
    .button {
      background: #f7f7f7;
      border: 1px solid #ccc;
      color: #555;
      display: inline-block;
      text-decoration: none;
      font-size: 13px;
      line-height: 26px;
      height: 28px;
      margin: 0;
      padding: 0 10px 1px;
      cursor: pointer;
      -webkit-border-radius: 3px;
      -webkit-appearance: none;
      border-radius: 3px;
      white-space: nowrap;
      -webkit-box-sizing: border-box;
      -moz-box-sizing:    border-box;
      box-sizing:         border-box;

      -webkit-box-shadow: 0 1px 0 #ccc;
      box-shadow: 0 1px 0 #ccc;
      vertical-align: top;
    }

    .button.button-large {
      height: 30px;
      line-height: 28px;
      padding: 0 12px 2px;
    }

    .button:hover,
    .button:focus {
      background: #fafafa;
      border-color: #999;
      color: #23282d;
    }

    .button:focus  {
      border-color: #5b9dd9;
      -webkit-box-shadow: 0 0 3px rgba( 0, 115, 170, .8 );
      box-shadow: 0 0 3px rgba( 0, 115, 170, .8 );
      outline: none;
    }

    .button:active {
      background: #eee;
      border-color: #999;
      -webkit-box-shadow: inset 0 2px 5px -3px rgba( 0, 0, 0, 0.5 );
      box-shadow: inset 0 2px 5px -3px rgba( 0, 0, 0, 0.5 );
      -webkit-transform: translateY(1px);
      -ms-transform: translateY(1px);
      transform: translateY(1px);
    }

    .commentary-external-links {
      margin-top: 30px;
    }

      </style>
</head>
<body>
<div class="selected_section" id="selected_section">
  <h3><a href="/#{$json['article']}">{$json['title']}</a></h3>
{$constitution_version_title}
  <p>{$content} <a id="maindoc-jump-link" href="/#{$slug}">[See in context]</a></p>
</div>
{$microtemplate}
{$timed_reload}
</body>
</html>
EOH;

      
      header('Content-Type: text/html; enctype=utf-8');
      header('Content-Length: '.strlen($microtemplate)); 
      die($microtemplate);
      exit;
    }
    wp_redirect( "/" );
    exit;
  }/*}}}*/

  static function wordpress_init()
  {/*{{{*/
    // See ../phlegiscope.php add_action(__FUNCTION__, ...)
    // Intercept GET request where REQUEST_URI contains prefix '^/constitutions/' or '^/stash/'

    closelog();
    openlog( basename(__FILE__), LOG_PID | LOG_NDELAY, LOG_LOCAL1 );
    syslog( LOG_INFO, "TICKER TICKER TICKER {$_SERVER['REMOTE_ADDR']}");
    
    if ( function_exists('wp_get_current_user') )
      syslog( LOG_INFO, "AUTHABLE AUTHABLE AUTHABLE {$_SERVER['REMOTE_ADDR']}");

    // Only accept up to 255 characters in REQUEST_URI
    $restricted_request_uri = substr($_SERVER['REQUEST_URI'], 0, 255); 
    if ( 1 === preg_match('/^\/constitutions\//i', $restricted_request_uri) ) {
      self::handle_constitutions_get( $restricted_request_uri );
    }
    else if ( 1 === preg_match('/^\/stash\//i', $restricted_request_uri) ) {

      $cache_path = SYSTEM_BASE . "/../cache";
      $method     = $_SERVER['REQUEST_METHOD'];

      if ( !file_exists( $cache_path ) ) mkdir( $cache_path );

      $response = array(
        "received"  => substr($_REQUEST['slug'],0,255),
        "available" => 0,
        "status"    => 0,
      );

      if ($method == 'POST') 
      {
        self::handle_stash_post( $response, $restricted_request_uri, $cache_path );
      }
      else if ($method == 'GET') 
      {
        self::handle_stash_get( $response, $restricted_request_uri, $cache_path );
      }

      $response_json = json_encode( $response );
      header('Content-Type: text/json');
      header('Content-Length: ' . strlen($response_json));
      die($response_json);
    }
    else {
      static::model_action();
    }
  }/*}}}*/

  static function wordpress_parse_request( $query )
  {
    // wp_die('<pre>'.print_r($query,TRUE).'</pre>');
  }


  /** OCR queue **/

  static function get_ocr_queue_stem(UrlModel & $url) {/*{{{*/
    $ocr_queue_file = $url->get_urlhash();
    $ocr_queue_base = SYSTEM_BASE . '/../cache/ocr';
    $ocr_queue_stem = "{$ocr_queue_base}/{$ocr_queue_file}/{$ocr_queue_file}";
    return preg_replace('@/([^/]*)/\.\./@i','/', $ocr_queue_stem);
  }/*}}}*/

  function test_ocr_result($fn) {/*{{{*/
    // Determine whether the OCR result file $fn contains usable text 
    $s = stat($fn);
    if ( FALSE == $s ) return FALSE;
    if ( intval($s['size']) < 512 ) return FALSE;
    return TRUE;
  }/*}}}*/

  function write_to_ocr_queue(UrlModel & $url) {/*{{{*/

    // Return 0 if a converted file exists
    //        1 if the source file was successfully placed in the OCR queue
    //        < 0 if an error occurred

    if ( !(($content_type = $url->get_content_type()) == 'application/pdf') ) {
      $urlstring = $url->get_url();
      $this->syslog(__FUNCTION__,__LINE__,"(warning) {$urlstring} file type is not 'application/pdf' (currently {$content_type}).");
      return FALSE;
    }
    $ocr_queue_stem = static::get_ocr_queue_stem($url);
    $ocr_queue_src  = "{$ocr_queue_stem}.pdf";
    $ocr_queue_targ = "{$ocr_queue_stem}.txt";
    $ocr_queue_base = dirname($ocr_queue_src);
    $this->syslog(__FUNCTION__,__LINE__,"(marker) Target path {$ocr_queue_base}");
    $this->syslog(__FUNCTION__,__LINE__,"(marker) Output path {$ocr_queue_targ}");

    if ( !file_exists($ocr_queue_base) ) mkdir($ocr_queue_base);

    if ( !file_exists($ocr_queue_base) || !is_dir($ocr_queue_base) ) {
      $this->syslog(__FUNCTION__,__LINE__,"(warning) Unable to create target output directory. Check filesystem permissions.");
      return -1;
    }
    else if ( file_exists($ocr_queue_targ) ) {
      if ( $this->test_ocr_result($ocr_queue_targ) ) {
        $this->syslog(__FUNCTION__,__LINE__,"(marker) Conversion complete. Target file {$ocr_queue_targ} exists.");
        return 0;
      }
      $this->syslog(__FUNCTION__,__LINE__,"(marker) Removing invalid conversion result file {$ocr_queue_targ}");
      unlink($ocr_queue_targ);
      return -6;
    }
    else if ( file_exists($ocr_queue_src) ) {
      $this->syslog(__FUNCTION__,__LINE__,"(marker) Waiting for conversion in {$ocr_queue_base}.");
      return -2;
    }
    else if ( FALSE == ($bytes_written = file_put_contents("{$ocr_queue_src}.tmp", $url->get_pagecontent())) ) {
      $this->syslog(__FUNCTION__,__LINE__,"(warning) Failed to write conversion source file {$ocr_queue_src}");
      return -3;
    }
    else if (FALSE == link("{$ocr_queue_src}.tmp", $ocr_queue_src)) {
      $this->syslog(__FUNCTION__,__LINE__,"(warning) Failed to write conversion source file {$ocr_queue_src}");
      return -4;
    }
    else if (FALSE == unlink("{$ocr_queue_src}.tmp")) {
      $this->syslog(__FUNCTION__,__LINE__,"(warning) Unable to unlink conversion source temporary file.");
      return -5;
    }
    else {
      $this->syslog(__FUNCTION__,__LINE__,"(marker) Wrote file {$ocr_queue_src}, {$bytes_written} written.");
      return 1;
    }  
    return TRUE;
  }/*}}}*/

  function get_doc_url_attributes(UrlModel & $faux_url) {/*{{{*/
    // Returns an array of <A> tag {class} strings used by crawler JS.
    // The UrlModel parameter is used in parent::generate_non_session_linked_markup()
    // to retrieve the document URL record (including payload, last_fetch time, etc.)
    // Data in that record is used to decorate source URL <A> tag attributes. 
    //
    // We want Senate bills to be marked "uncached" that either
    // 1) Have not yet been retrieved to local storage, or
    // 2) Do not have an OCR result record matching the PDF content.
    //
    $doc_url_attrs    = array('legiscope-remote');
    $have_ocr         = $this->get_searchable();
    $doc_url          = $this->get_doc_url();
    $faux_url_hash    = UrlModel::get_url_hash($doc_url);
    $url_stored       = $faux_url->retrieve($faux_url_hash,'urlhash')->in_database();

    if ( $this->in_database() && is_null($this->get_ocrcontent()) ) {
      $this->syslog(__FUNCTION__,__LINE__,"(warning) No [content] attribute present for {$doc_url}. Default to {uncached} state.");
    }
    $doc_url_attrs[]  = $url_stored && $have_ocr ? 'cached' : 'uncached';

    if ( !$faux_url->in_database() ) { 
      $doc_url_attrs[] = 'uncached';
    }
    else {
      if ( 0 < intval( $age = intval($faux_url->get_last_fetch()) ) ) {
        $age = time() - intval($age);
        $doc_url_attrs[] = ($age > ( 60 )) ? "uncached" : "cached";
      }
      $doc_url_attrs[] = $faux_url->get_logseconds_css();
    }
    return $doc_url_attrs;
  }/*}}}*/

}
