<?php

class SessionException extends Exception {

  function __construct($message, $code, $previous = NULL) {
    parent::__construct($message, $code, $previous);
  }
}

function C($constant_name, $if_unset = FALSE ) {
  return defined($constant_name) ? constant($constant_name) : $if_unset;
}

function filter_request($v, $if_unset = NULL, $maxlen = 255, $filter_regex = NULL, $filter_repl = NULL)
{
  return isset($_REQUEST[$v]) && (is_array($_REQUEST[$v]) || 0 < strlen(trim($_REQUEST[$v]))) 
    ? (is_array($_REQUEST[$v]) 
      ? $_REQUEST[$v] 
      : is_null($filter_regex) 
        ? substr(trim($_REQUEST[$v]),0,$maxlen)
        : preg_replace($regex, $filter_repl, substr($_REQUEST[$v],0,$maxlen)))
    : $if_unset; 
}

function filter_post($v, $if_unset = NULL) {
  return isset($_POST[$v]) && (is_array($_POST[$v]) || 0 < strlen(trim($_POST[$v]))) 
    ? (is_array($_POST[$v]) ? $_POST[$v] : trim($_POST[$v]))
    : $if_unset; 
}

function filter_server($v, $if_unset = NULL) {
  return isset($_SERVER[$v]) && (is_array($_SERVER[$v]) || 0 < strlen(trim($_SERVER[$v]))) 
    ? (is_array($_SERVER[$v]) ? $_SERVER[$v] : trim($_SERVER[$v]))
    : $if_unset; 
}


require_once( SYSTEM_BASE . "/../PHPWebDriver/WebDriver.php");

class SystemUtility extends DatabaseUtility {

  protected $session_id = NULL;
  protected static $selenium_webdriver = NULL;
  protected static $selenium_session = NULL;

  function __construct() {
  }

  function & get_selenium_session($selenium_session_url = NULL) {/*{{{*/

		$preload_session = $this->filter_session($this->subject_host_hash,array());

    if ( is_null($selenium_session_url) && array_key_exists('SELENIUM_SESSION_URL',$preload_session) ) {
      $selenium_session_url = $preload_session['SELENIUM_SESSION_URL'];
    } 

    // $selenium_session_url = NULL;

    if ( is_null(self::$selenium_webdriver) ) {
      $this->syslog(__FUNCTION__, 'FORCE', "Spider session data (Se URL: {$selenium_session_url})");
      $this->recursive_dump($preload_session,'(warning)');
      // self::$selenium_webdriver = new PHPWebDriver_WebDriver(is_null($selenium_session_url) ? C('SELENIUM_WEBDRIVER') : $selenium_session_url);
      self::$selenium_webdriver = new PHPWebDriver_WebDriver(C('SELENIUM_WEBDRIVER'));
      $additional_capabilities = array();
      if ( !is_null($selenium_session_url) ) {
        $url_parts = UrlModel::parse_url($selenium_session_url);
        $path = $url_parts['path'];
        $basename = basename($path);
        $additional_capabilities['webdriver.remote.sessionid'] = $basename;
      }
      self::$selenium_session = self::$selenium_webdriver->session('firefox', $additional_capabilities); 
      $this->syslog( __FUNCTION__, 'FORCE', "Initialized Selenium Webdriver. In session - {$_SESSION[$this->subject_host_hash]['SELENIUM_SESSION_URL']}");
      $this->recursive_dump($additional_capabilities,'(warning)');
      $this->syslog(__FUNCTION__, 'FORCE', "   Selenium sessions");
      $sessions = self::$selenium_webdriver->sessions();
      $this->recursive_dump($sessions,'(warning)');

      if ( is_null($selenium_session_url) ) {
        $capabilities = self::$selenium_session->capabilities();
        $this->syslog(__FUNCTION__, 'FORCE', "   Selenium sesscaps");
        $this->recursive_dump($capabilities,'(warning)');
        $get_matching_session_url = create_function('$a', 'return FALSE == strpos($a, "'.$capabilities['webdriver.remote.sessionid'].'") ? NULL : $a;');
        $matched = array_values(array_filter(array_map($get_matching_session_url, $sessions)));
        if ( is_array($matched) && (1 == count($matched)) ) {
          $_SESSION[$this->subject_host_hash]['SELENIUM_SESSION_URL'] = $matched[0];
          $this->syslog(__FUNCTION__, 'FORCE', "   Selenium session: {$matched[0]}. Remembering  Remembering {$_SESSION[$this->subject_host_hash]['SELENIUM_SESSION_URL']}");
        }
      }
    }

    return self::$selenium_session;
  }/*}}}*/

  function & get_selenium_webdriver() {/*{{{*/
    $this->get_selenium_session();
    return self::$selenium_webdriver;
  }/*}}}*/

  function & SeF() {/*{{{*/
    // Selenium flouride:  Get the WebDriver itself
    return $this->get_selenium_webdriver();
  }/*}}}*/

  function & Se($selenium_session_url = NULL) {/*{{{*/
    return $this->get_selenium_session($selenium_session_url);
  }/*}}}*/

  function & se_send_keys($attr, $attrval, $value) {/*{{{*/
    return $this->Se()->element($attr, $attrval)->value(array(
      "value" => preg_split("//u", $value, PREG_SPLIT_NO_EMPTY)
    ));
  }/*}}}*/

  final protected function session_start_wrapper() {/*{{{*/
    session_name(LEGISCOPE_SESSION_NAME);
		if ( !session_id() ) {
			if ( !session_start() ) throw new SessionException(__FUNCTION__);
		}
    $this->session_id = session_id();
    return $this->session_id;
  }/*}}}*/

  final protected function filter_server($v, $if_unset = NULL) { /*{{{*/
    return is_array($_SERVER) && array_key_exists($v,$_SERVER) ? $_SERVER[$v] : $if_unset; 
  }/*}}}*/

  final protected function filter_session($v, $if_unset = NULL) { /*{{{*/
    return is_array($_SESSION) && array_key_exists($v,$_SESSION) ? $_SESSION[$v] : $if_unset; 
  }/*}}}*/

  final protected function filter_request($v, $if_unset = NULL) { /*{{{*/
    return isset($_REQUEST[$v]) && 0 < strlen(trim($_REQUEST[$v])) ? trim($_REQUEST[$v]) : $if_unset; 
  }  /*}}}*/

  final protected function filter_post($v, $if_unset = NULL) {/*{{{*/
    return filter_post($v, $if_unset);
  }/*}}}*/

	function extract_key_value_table(& $kv, $key, $value) {/*{{{*/
		$key = array_map(create_function('$a', 'return $a["'.$key.'"];'), $kv);
		$value = array_map(create_function('$a', 'return $a["'.$value.'"];'), $kv);
		return is_array($key) && is_array($value) && (count($key) == count($value)) && (count($key) > 0)
			?	array_combine($key,$value)
			: array()
			;
	}/*}}}*/

	function pop_stack_entries(& $target, & $source, $m = NULL, $per_element_callback = NULL ) {/*{{{*/
		if ( is_null($m) ) $m = 10;
		$n = 0;
		$target = array();
		while ( (0 < count($source)) && ($n++ < $m) ) {
			$target[] = array_pop($source);
		}
		return $n;
	}/*}}}*/

}
