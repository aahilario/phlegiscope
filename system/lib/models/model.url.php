<?php

class UrlException extends Exception {
  function __construct($message = NULL, int $code = NULL, $previous_exception = NULL) {
    parent::__construct($message, $code, $previous);
  }
}

class UrlModel extends DatabaseUtility {
  
  // Model fields are used to generate typed fields. See DatabaseUtility::fetch_typemap()
  // BEGIN ModelFields
  var $urlhash_vc128uniq = 'md5';
  var $url_vc4096 = NULL;
  var $urltext_vc4096 = NULL;
  var $content_length_int11 = NULL;
  var $create_time_utx = NULL;
  var $update_time_utx = NULL;
  var $last_fetch_utx = NULL;
  var $last_modified_utx = NULL;
  var $content_type_vc64 = NULL;
  var $cache_filename_vc255 = NULL;
  var $pagecontent_blob = NULL;
  var $response_header_vc32767 = NULL;
  var $hits_int11 = NULL;
  var $is_fake_bool = NULL;
  var $custom_parse_bool = NULL;
  var $prior_content_hash_vc128 = NULL;///'sha1';
  var $content_hash_vc128 = 'sha1';
  // END ModelFields

  function __construct($url = NULL, $load = FALSE) {
    parent::__construct();
    $this->set_url($url, $load);
  }

  function set_url($url, $load = TRUE) {/*{{{*/
		$this->url_vc4096 = $url;
		$this->urlhash_vc128uniq = is_null($url) ? NULL : self::get_url_hash($url);
		if ( $load && !is_null($this->urlhash_vc128uniq ) ) {
			$this->id = NULL;
			$this->fetch($this->urlhash_vc128uniq,'urlhash');
		}
    return $this->in_database();
  }/*}}}*/

	function get_query_element($component) {/*{{{*/
    $path_query = self::parse_url($this->get_url(),PHP_URL_QUERY);
    $query_parts = self::decompose_query_parts($path_query);
		return $query_parts[$component];
	}/*}}}*/

  static function get_url_hash($url, $url_component = NULL ) {/*{{{*/
    if ( !is_null($url_component) ) {
      $url = self::parse_url($url, $url_component);
      if ( $url === FALSE || empty($url) ) return FALSE;
    }
    return md5($url);
  }/*}}}*/

  static function recompose_url(array $q, $link_item = array(), $strip_query_scripttail = FALSE ) {/*{{{*/
    if ( $strip_query_scripttail ) {
      $q['query'] = NULL;
      $q['path'] = self::path_sans_script($q);
    }
    if ( !empty($q['scheme']) )   $q['scheme']   = "{$q['scheme']}://";
    if ( !empty($q['port']) )     $q['port']     = ":{$q['port']}";
    if ( !empty($q['path']) ) {
      $q['path'] = "/" . preg_replace('@([^/]*)/\.\./(.*)@','$2', $q['path']);
      $q['path'] = "/" . preg_replace('@([^/]*)/\.\./(.*)@','$2', $q['path']);
    }
    if ( !empty($q['query']) )    $q['query']    = "?{$q['query']}";
    if ( !empty($q['fragment']) || ($link_item['url'] == '#') ) $q['fragment'] = "#{$q['fragment']}";
    return "{$q['scheme']}{$q['host']}{$q['port']}/" . ltrim(preg_replace('@\/+@','/', "{$q['path']}{$q['query']}{$q['fragment']}"),'/');
  }/*}}}*/

  static function recompose_query_parts($query_components, $as_array = FALSE, $query_component_delimiter = '&') {/*{{{*/
    $query_string = array();
    foreach ( $query_components as $key => $value ) {
      $query_string[] = "{$key}={$value}";
    }
    if ( FALSE == $as_array ) $query_string = join($query_component_delimiter, $query_string);
    return $query_string;
  }/*}}}*/

  static function decompose_query_parts($url_query_parts) {/*{{{*/
    $query_regex = '@([^&=]*)=([^&]*)@';
    $url_query_components = array();
    $match_result = preg_match_all($query_regex, $url_query_parts, $url_query_components);
    if ( !(0 == $match_result || FALSE === $match_result) ) {
      $url_query_parts = array_combine($url_query_components[1],$url_query_components[2]);
      ksort($url_query_parts);
    }
    return $url_query_parts;
  }/*}}}*/

  static function path_sans_script( array $parent_url ) {
    $path_sans_script = $parent_url['path'];
    if ( 1 == preg_match('@(.*)/([^.]*)\.(.*)$@',$path_sans_script) ) {
      // If the tail of the path appears to be a script, remove it from the path
      $path_sans_script = preg_replace('@(.*)/([^./]*)\.(.*)$@','$1', $path_sans_script);
    }
    return $path_sans_script;
  }

  static function normalize_url($parent_url, & $link_item, $strip_path_and_query = FALSE) {

    if ( !array_key_exists('url', $link_item) || (FALSE === $link_item['url']) ) return FALSE;

    // Construct parent path sans trailing script component
    $path_sans_script = self::path_sans_script($parent_url);

    $q = self::parse_url($link_item['url']);

    if ( !array_key_exists('scheme', $q) ) $q['scheme'] = $parent_url['scheme'];

    // Use the containing page host name if none is specified
    if ( !array_key_exists('host', $q) ) $q['host']   = $parent_url['host'];

    $q['host'] = trim($q['host'],'/');

    if ( $q['host'] == $parent_url['host'] ) {
      // If there is no path component in the link URL (i.e. just a query or a fragment part),
      // copy the path part from the containing page's URL
      if ( empty($q['path']) ) {
        $q['path'] = $parent_url['path'];
      } else {
        // Otherwise use the parent URL and link URL to compose a full URL
        if ( !('/' == substr($q['path'],0,1) ) ) {
          // The path part of this URL is not an absolute host resource path,
          // and so should be appended to the parent page URL path 
          $q['path'] = preg_replace('@[/]{2,}@','/',"{$path_sans_script}/{$q['path']}");
        }
      }

      $q['path'] = trim($q['path'],'/');
    }

    $normalized_link = rtrim(self::recompose_url($q, $link_item),'/');
    $link_item['urlparts'] = self::parse_url($normalized_link);

    return $normalized_link;

  }

  static function parse_url($url, $url_component = NULL) {/*{{{*/
    $url_regex = '@^(((http(s)?):\/\/)*(([^:]*)(:(.*))?\@)*(([^/?#:]+)*)((:([0-9]+))*)){0,1}((\/([^?#]*)))*((\?([^#]*))*)((\#(.*))*)$@i';
    $url_parts = array(
      3  => 'scheme',
      6  => 'username',
      8  => 'password',
      10 => 'host',
      13 => 'port',
      15 => 'path',
      19 => 'query',
      22 => 'fragment',
    );
    $php_url_parts = array(
      PHP_URL_SCHEME   => 3 ,
      PHP_URL_USER     => 6 ,
      PHP_URL_PASS     => 8 ,
      PHP_URL_HOST     => 10,
      PHP_URL_PORT     => 13,
      PHP_URL_PATH     => 16,
      PHP_URL_QUERY    => 19,
      PHP_URL_FRAGMENT => 22,
    );
    $match_parts       = array();
    $result            = array();
    $preg_match_result = preg_match($url_regex, $url, $match_parts);
    if ($match_parts[1] == $match_parts[10]) {
      $match_parts[15] = "{$match_parts[10]}/{$match_parts[15]}"; 
      $match_parts[1] = NULL;
      $match_parts[9] = NULL;
      $match_parts[10] = NULL;
    }  
    foreach ( $match_parts as $part => $partname ) {
      if ( array_key_exists($part, $url_parts) )
        $result[$url_parts[$part]] = $partname; 
    }
    $match_parts = array_filter($result);
    if ( !is_null($url_component) ) $match_parts = $match_parts[$url_parts[$php_url_parts[$url_component]]];
    return $match_parts;
  }/*}}}*/

	static function construct_metalink_fake_url(UrlModel & $url, array & $metalink) {/*{{{*/
		// Reduce long metalink query parameters (truncate params exceeding 32 characters)
		$metalink_fix     = create_function('$a', 'return (strlen($a) > 32) ? "" : $a;' );
		$query_components = self::parse_url($url->get_url(),PHP_URL_QUERY);
		$query_components = self::decompose_query_parts($query_components);
		$query_components = array_map($metalink_fix,array_merge($query_components, $metalink));
		ksort($query_components); // Ensure that we are not sensitive to query part parameter order
		ksort($metalink); // Just so the caller gets a clue we've reordered the POST data URL
		$query_components = self::recompose_query_parts($query_components);

		// Create fake URL using faked query parameters 
		$whole_url = self::parse_url($url->get_url());
		$whole_url['query'] = $query_components;
		$faux_url = self::recompose_url($whole_url);
		return $faux_url;
	}/*}}}*/

  function fetch_referrers() {
    // Return a list of UrlModel referrers
  }

	function is_cached($url) {
		return TRUE;
		$this->fetch($url,'url');
		return $this->in_database();
	}

  function stow($url_or_urlarray = NULL, $parent_url = NULL) {/*{{{*/
    // FIXME:  Permit use of an array parameter (to save sets of links)
    if ( is_null($this->id) ) $this->set_create_time(time());
    else $this->content_changed();
    $stowresult = parent::stow(); 
    return $stowresult;
  }/*}}}*/

  function set_cache_filename($c) {/*{{{*/
    $this->cache_filename_vc255 = $c;
    $this->syslog(__FUNCTION__, __LINE__, "Cache filename: {$c}" );
  }/*}}}*/

  function set_content_type($c) {/*{{{*/
    $this->content_type_vc64 = $c;
    $this->syslog(__FUNCTION__, __LINE__, "Content-Type: {$c}" );
  }/*}}}*/

  function set_content_length($n) {/*{{{*/
    $this->content_length_int11 = intval($n);
    $this->syslog(__FUNCTION__, __LINE__, "Content-Length: {$this->content_length_int11}" );
  }/*}}}*/

  function get_cache_filename() {/*{{{*/
    if ( is_null($this->get_url()) || is_null($this->get_urlhash()) ) return NULL;
    $subject_host_hash = self::get_url_hash($this->get_url(),PHP_URL_HOST);
    $urlhash = $this->get_urlhash();
    return C('CACHE_PATH') . '/' . "legiscope.{$subject_host_hash}.{$urlhash}.cached";
  }/*}}}*/

  function set_pagecontent($t) {/*{{{*/

    // Accept cURL output
    $content_length          = strlen($t);
    $header_regex            = '@^HTTP/1.@';
    $response_headers        = '';
    $parsed_headers          = array();
    $response_regular_markup = FALSE;
    $http_response_code      = NULL;
    $final_headers = array();

    if ( 1 == preg_match($header_regex, $t) ) {

      $matches       = array();

      $matched_header = NULL;
      if ( 1 == preg_match('@^HTTP/([0-9.]+) ([0-9]+)@', substr($t,0,32), $matched_header) ) {
        $http_response_code = intval($matched_header[2]); 
      }

      $line_regex    = '@^([-a-z0-9_]{1,32}):(.*)$@mi';
      if ( !(FALSE == preg_match_all($line_regex, $t, $matches, PREG_SET_ORDER)) ) {
        $this->syslog( __FUNCTION__, __LINE__, "Raw header line matches" );
        foreach ( $matches as $set ) {
          $final_headers[strtolower($set[1])] = trim($set[2]);
        }
        $response_regular_markup = array_key_exists('content-type', $final_headers) && (1 == preg_match('@^text/html@i',$final_headers['content-type']));
        $final_headers['http-response-code'] = $http_response_code;
        $final_headers['legiscope-regular-markup'] = $response_regular_markup ? 1 : 0;
        $this->set_response_header($final_headers);
        // $this->recursive_dump($matches, __LINE__);
      }  
      if ( array_key_exists('content-type', $final_headers) ) {
        $this->set_content_type($final_headers['content-type']);
      }
      if ( array_key_exists('content-length', $final_headers) ) {
        $content_length = intval($final_headers['content-length']);
        $t = substr( $t, - $content_length );
        $bulk_length = strlen($t);
        $this->syslog( __FUNCTION__, __LINE__, "Stripped header, length {$bulk_length} -> {$content_length}" );
      } else {
        // FIXME: Deal with missing Content-Length key, or Transfer-Encoding: chunked 
      }
    }
    // FIXME: This goes away now that pagecontent is pagecontent_blob
    $cache_filename = $this->get_cache_filename();
    if (0) if ( 1 == preg_match('@(image/)@i', $this->get_content_type()) ) {
      $result = @file_put_contents($cache_filename,$t); 
      $this->syslog( __FUNCTION__, __LINE__, "Caching {$content_length} file {$cache_filename}: " . 
        (FALSE == $result ? 'FAIL' : 'OK')  );
      $this->set_cache_filename($cache_filename);
    }
    $this->set_content_length($content_length);
    $this->set_urlhash(UrlModel::get_url_hash($this->get_url()));
    // $this->syslog( __FUNCTION__, __LINE__, "Current content SHA1: " . sha1($t) );
    // $this->pagecontent_blob = $content_length > C('CONTENT_SIZE_THRESHOLD') ? NULL : $t;
    // $this->syslog( __FUNCTION__, __LINE__, "WARNING: Streaming {$content_length} file {$cache_filename}" );
    // $this->pagecontent_blob = "file://{$cache_filename}"; // Test streaming
    $this->pagecontent_blob = $t;
    $this->set_content_hash(sha1($t));
    $this->content_length_int11 = $content_length;
    $final_headers = $this->get_response_header();
    $result = 
      array(
        'http_response_code'      => $http_response_code,
        'parsed_headers'          => $final_headers,
        'response_headers'        => join("\n", $final_headers),
        'response_regular_markup' => $response_regular_markup ? 1 : 0,
      );
    // $this->syslog(__FUNCTION__, __LINE__, "Response data for " . $this->get_url());
    // $this->recursive_dump($result,"- ");
    return $result;
  }/*}}}*/

  function cached_to_disk() {/*{{{*/
		return !$this->in_database() &&
		 	!@file_exists($this->get_cache_filename());
  }/*}}}*/

  function content_changed($do_update = TRUE) {/*{{{*/
    $current_content_hash = $this->get_content_hash();
    $prior_content_hash = $this->get_prior_content_hash();
    if ( $current_content_hash == $prior_content_hash ) {
      return FALSE;
    } else if ($do_update) {
      $this->
        set_prior_content_hash($current_content_hash)->
        set_content_hash()->
        set_update_time(time());
    }
    return TRUE;
  }/*}}}*/

  function set_response_header($a) {/*{{{*/
    if ( is_array($a) ) $a = json_encode($a);
    $this->response_header_vc32767 = $a;
    return $this;
  }/*}}}*/

  function get_response_header($as_array = TRUE, $interline_break = "\n") {/*{{{*/
    $this->syslog( __FUNCTION__, __LINE__, "Header raw: {$this->response_header_vc32767}");
    $h = json_decode($this->response_header_vc32767,TRUE);
    if ( (FALSE == $h) || !is_array($h) ) {
      $this->syslog( __FUNCTION__, __LINE__, "Header JSON parse failure");
       return $as_array ? array() : NULL;
    }
    if ($as_array != TRUE) {
      $header_lines = array();
      foreach ( $h as $key => $val ) {
        $key = utf8_encode($key);
        $val = utf8_encode($val);
        $header_lines[] = "{$key}: {$val}";
      }
      $h = join($interline_break, $header_lines);
      $header_lines = NULL;
    }
    return $h;
  }/*}}}*/

  function referrers($attr = NULL) {/*{{{*/
    if ( !$this->in_database() ) return NULL;
    $edge = new UrlEdgeModel();
    $link = new UrlModel();
    $record = array();
    $result = array();
    $edge->where(array('b' => $this->id))->recordfetch_setup();
    $accessor = "get_{$attr}";
    while ($edge->recordfetch($record)) {
      if ( is_null($attr) ) {
        $result[] = is_null($attr) ? $record["id"] : $record[$attr];
      } else {
        $link->fetch($record["a"], 'id');
        if ( $link->in_database() && method_exists($link, $accessor) ) {
          $result[] = $link->$accessor($attr);
        }
      }
    }
    return $result;
  }/*}}}*/

  function set_content_hash($s = NULL) {
    if ( is_null($s) ) {
      $s = sha1($this->get_pagecontent());
    }
    $this->content_hash_vc128 = $s;
    return $this;
  }

  function get_content_hash() {
    return $this->content_hash_vc128;
  }

  function set_last_fetch($utx) {
    $this->last_fetch_utx = $utx;
    return $this;
  }

  function set_create_time($utx) {
    $this->create_time_utx = $utx;
  }

  function & set_last_modified($v) { $this->last_modified_utx = $v; return $this; }
  function get_last_modified($v = NULL) { if (!is_null($v)) $this->set_last_modified($v); return $this->last_modified_utx; }

  function & set_content($v) { $this->content_blob = $v; return $this; }
  function get_content($v = NULL) { if (!is_null($v)) $this->set_content($v); return $this->content_blob; }

  function & set_urltext($v) { $this->urltext_vc4096 = $v; return $this; }
  function get_urltext($v = NULL) { if (!is_null($v)) $this->set_urltext($v); return $this->urltext_vc4096; }

  function get_pagecontent() {/*{{{*/
    $headers = $this->get_response_header();
    $is_pdf  = (1 == preg_match('@^application/pdf@i',$this->get_content_type()));
    // $is_html = (1 == preg_match('@^text/html@i',$this->get_content_type()));
    $cache_filename = $this->get_cache_filename();
    if ( is_null($this->pagecontent_blob) && file_exists($cache_filename) ) {
      // $this->recursive_dump(explode("\n",__LINE__);
      $this->syslog(__FUNCTION__,__LINE__,"---------- LOADING {$cache_filename}" );
      $this->pagecontent_blob = @file_get_contents($this->get_cache_filename());
      $length = strlen($this->pagecontent_blob);
      $this->syslog(__FUNCTION__,__LINE__,"---------- LOADED {$length} octets from {$cache_filename}" );
      if ( is_null($this->pagecontent_blob) && $is_pdf ) {
        $this->syslog(__FUNCTION__, __LINE__, "PDF could not be obtained from {$cache_filename}");
      }
      $stowresult = $this->stow();
      $sr_type = gettype($stowresult);
      $this->syslog(__FUNCTION__,__LINE__,"---------- [{$sr_type} {$stowresult}] LOADED {$length} octets from {$cache_filename}" );
      if ( 'string' == $sr_type && (0 < strlen(intval($stowresult))) ) {
        $unlink_result = unlink($cache_filename);
        $ur_type = gettype($unlink_result);
        $this->syslog(__FUNCTION__,__LINE__,"---------- [{$ur_type} {$unlink_result}] WARNING: Unlinking now-unneeded cache file {$cache_filename}" );
      }
    }
    if ( (!is_array($headers) || !(0 < count($headers))) && !empty($this->pagecontent_blob) ) {
      $this->syslog(__FUNCTION__,__LINE__,"---------- WARNING: Content length " . strlen($this->pagecontent_blob) . " but empty headers" );
      $this->set_pagecontent($this->pagecontent_blob);
      $this->stow();
    }
    return $this->pagecontent_blob;
  }/*}}}*/

  function get_content_type() {
    return $this->content_type_vc64;
  }

  function get_content_length() {
    return intval($this->content_length_int11);
  }

  function & increment_hits() {
    $this->hits_int11 = intval($this->hits_int11) + 1; 
    return $this;
  }

  function get_url() {
    return $this->url_vc4096;
  }

  function get_urlhash() {
    return $this->urlhash_vc128uniq;
  }

  function & set_urlhash($t) {
    $this->urlhash_vc128uniq = $t;
    return $this;
  }

  function get_linktext() {
    return $this->urltext_vc4096;
  }

  function & set_linktext($t) {
    $this->urltext_vc4096 = $t;
    return $this;
  }

  function __toString() {
    return $this->url_vc4096;
  }

  function is_custom_parse($v = NULL) {
    if ( !is_null($v) ) $this->custom_parse_bool = $v;
    return (bool)$this->custom_parse_bool;
  }

  function set_custom_parse($v) {
    return $this->custom_parse_bool = $v;
  }

  function & set_is_fake($v) { $this->is_fake_bool = $v; return $this; }
  function get_is_fake($v = NULL) { if (!is_null($v)) $this->set_is_fake($v); return $this->is_fake_bool; }

  function & set_prior_content_hash($v) { $this->prior_content_hash_vc128 = $v; return $this; }
  function get_prior_content_hash($v = NULL) { if (!is_null($v)) $this->set_prior_content_hash($v); return $this->prior_content_hash_vc128; }

  function & set_hits($v) { $this->hits_int11 = $v; return $this; }
  function get_hits($v = NULL) { if (!is_null($v)) $this->set_hits($v); return $this->hits_int11; }

  function & set_update_time($v) { $this->update_time_utx = $v; return $this; }
  function get_update_time($v = NULL) { if (!is_null($v)) $this->set_update_time($v); return $this->update_time_utx; }
}
