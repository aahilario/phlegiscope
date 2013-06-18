<?php

class CurlUtility {/*{{{*/

  public static $cookiejar_parsed = NULL;
  public static $last_transfer_info = NULL;
  public static $last_error_number = NULL;
  public static $last_error_message = NULL;

  public static function parse_cookiejar($cookiejar, $as_string = TRUE) {# {{{
    self::$cookiejar_parsed = array();
    if ( !is_null($cookiejar) && file_exists($cookiejar) && !(FALSE === ($cookiejar = file_get_contents($cookiejar))) ) {
      $keys = array(
          'host',
          'session',
          'path',
          'secure',
          'age',
          'name',
          'value',
          );
      $bz_235_replace_httponly_pattern = '/^#HttpOnly_/';
      foreach( explode("\n", $cookiejar) as $line ) {
        $line = trim($line);
        // Deal with Bugzilla #235 issue with HttpOnly headers
        $line = preg_replace($bz_235_replace_httponly_pattern,'',$line);
        if ( !(0 < strlen($line)) || $line[0] == '#' ) continue;
        $line = explode("\t", $line);
        $line = array_combine($keys, $line);
        self::$cookiejar_parsed[$line['name']] = $line;
      }
      return self::get_cookiejar_parsed($as_string);
    }
    return FALSE;
  }# }}}

  public static function get_cookiejar_parsed($as_string = TRUE) {
    if ( !$as_string ) return self::$cookiejar_parsed; // Return the raw array
    $cookiedata_to_cookiestring = create_function('$a', 'return "{$a["name"]}={$a["value"]}";');
    return join('; ', array_filter(array_map($cookiedata_to_cookiestring, self::$cookiejar_parsed))); 
  }

  public static function execute_curl_method(& $url, $data, $method = 'POST', $additional_curl_options = array() ) {/*{{{*/

    $url_parts = UrlModel::parse_url($url);
    $is_https  = array_key_exists('scheme', $url_parts) && strtolower($url_parts['scheme']) == 'https';
    $curl_hdl  = curl_init();

    self::$last_transfer_info = NULL;
    self::$last_error_message = NULL;
    self::$cookiejar_parsed   = NULL;

    $options   = array(
      CURLOPT_FRESH_CONNECT  => TRUE,
      CURLOPT_FORBID_REUSE   => TRUE,
      CURLOPT_URL            => $url,
      CURLOPT_HEADER         => TRUE, // Include the header in the output
      CURLOPT_CONNECTTIMEOUT => LEGISCOPE_CURLOPT_CONNECTTIMEOUT,
      CURLOPT_TIMEOUT        => LEGISCOPE_CURLOPT_TIMEOUT,
      CURLOPT_RETURNTRANSFER => TRUE,
      CURLOPT_USERAGENT      => LEGISCOPE_USER_AGENT,
    );
    if ($method == 'POST') {
      syslog( LOG_INFO, "POST Action requested, target URL {$url}" );
      $options[CURLOPT_POST]         = TRUE;
      $options[CURLOPT_POSTFIELDS]   = $data;
    }
    if ($is_https) {
      // See PHP manual section "cURL Functions: curl_setopt"
      $options[CURLOPT_SSL_VERIFYPEER] = FALSE;
      $options[CURLOPT_SSL_VERIFYHOST] = FALSE;
    }
    foreach( $additional_curl_options as $key => $value ) {
      $options[$key] = $value;
    }

    $cookiejar = array_key_exists(CURLOPT_COOKIEJAR, $options)
      ? $options[CURLOPT_COOKIEJAR]
      : NULL
      ;

    if (FALSE === curl_setopt_array( $curl_hdl, $options )) {
      syslog( LOG_INFO, "::" . __FUNCTION__ . ":" . __LINE__ . ": " .
        " Fatal error, curl_setopt_array() passed unusable parameters." );
      return FALSE;
    }

    $duration = microtime(TRUE);
    $response = curl_exec($curl_hdl);
    $duration = microtime(TRUE) - $duration;

    $url = NULL;

    $curl_err = curl_errno($curl_hdl);

    self::$last_error_number = $curl_err;
    switch ( $curl_err ) {/*{{{*/
      case CURLE_OK                       : //  (0) All fine. Proceed as usual.
        self::$last_error_number = 0;
        break;
      case CURLE_UNSUPPORTED_PROTOCOL     : //  (1) The URL you passed to libcurl used a protocol that this libcurl does not support. The support might be a compile-time option that you didn't use, it can be a misspelled protocol string or just a protocol libcurl has no code for.
      case CURLE_FAILED_INIT              : //  (2) Very early initialization code failed. This is likely to be an internal error or problem, or a resource problem where something fundamental couldn't get done at init time.
      case CURLE_URL_MALFORMAT            : //  (3) The URL was not properly formatted.
      case CURLE_NOT_BUILT_IN             : //  (4) A requested feature, protocol or option was not found built-in in this libcurl due to a build-time decision. This means that a feature or option was not enabled or explicitly disabled when libcurl was built and in order to get it to function you have to get a rebuilt libcurl.
      case CURLE_COULDNT_RESOLVE_PROXY    : //  (5) Couldn't resolve proxy. The given proxy host could not be resolved.
        $response = FALSE;
        break;
      case CURLE_COULDNT_RESOLVE_HOST     : //  (6) Couldn't resolve host. The given remote host was not resolved.
      case CURLE_COULDNT_CONNECT          : //  (7) Failed to connect() to host or proxy.
      case CURLE_FTP_WEIRD_SERVER_REPLY   : //  (8) After connecting to a FTP server, libcurl expects to get a certain reply back. This error code implies that it got a strange or bad reply. The given remote server is probably not an OK FTP server.
      case CURLE_REMOTE_ACCESS_DENIED     : //  (9) We were denied access to the resource given in the URL. For FTP, this occurs while trying to change to the remote directory.
      case CURLE_FTP_ACCEPT_FAILED        : //  (10) While waiting for the server to connect back when an active FTP session is used, an error code was sent over the control connection or similar.
      case CURLE_FTP_WEIRD_PASS_REPLY     : //  (11) After having sent the FTP password to the server, libcurl expects a proper reply. This error code indicates that an unexpected code was returned.
      case CURLE_FTP_ACCEPT_TIMEOUT       : //  (12) During an active FTP session while waiting for the server to connect, the CURLOPT_ACCEPTTIMOUT_MS (or the internal default) timeout expired.
      case CURLE_FTP_WEIRD_PASV_REPLY     : //  (13) libcurl failed to get a sensible result back from the server as a response to either a PASV or a EPSV command. The server is flawed.
      case CURLE_FTP_WEIRD_227_FORMAT     : //  (14) FTP servers return a 227-line as a response to a PASV command. If libcurl fails to parse that line, this return code is passed back.
      case CURLE_FTP_PRET_FAILED          : //  (84) The FTP server does not understand the PRET command at all or does not support the given argument. Be careful when using CURLOPT_CUSTOMREQUEST, a custom LIST command will be sent with PRET CMD before PASV as well. (Added in 7.20.0)
      case CURLE_FTP_CANT_GET_HOST        : //  (15) An internal failure to lookup the host used for the new connection.
      case CURLE_FTP_COULDNT_SET_TYPE     : //  (17) Received an error when trying to set the transfer mode to binary or ASCII.
      case CURLE_PARTIAL_FILE             : //  (18) A file transfer was shorter or larger than expected. This happens when the server first reports an expected transfer size, and then delivers data that doesn't match the previously given size.
      case CURLE_FTP_COULDNT_RETR_FILE    : //  (19) This was either a weird reply to a 'RETR' command or a zero byte transfer complete.
      case CURLE_QUOTE_ERROR              : //  (21) When sending custom "QUOTE" commands to the remote server, one of the commands returned an error code that was 400 or higher (for FTP) or otherwise indicated unsuccessful completion of the command.
      case CURLE_HTTP_RETURNED_ERROR      : //  (22) This is returned if CURLOPT_FAILONERROR is set TRUE and the HTTP server returns an error code that is >= 400.
      case CURLE_WRITE_ERROR              : //  (23) An error occurred when writing received data to a local file, or an error was returned to libcurl from a write callback.
      case CURLE_UPLOAD_FAILED            : //  (25) Failed starting the upload. For FTP, the server typically denied the STOR command. The error buffer usually contains the server's explanation for this.
      case CURLE_READ_ERROR               : //  (26) There was a problem reading a local file or an error returned by the read callback.
      case CURLE_OUT_OF_MEMORY            : //  (27) A memory allocation request failed. This is serious badness and things are severely screwed up if this ever occurs.
        $response = FALSE;
        break;
      case CURLE_OPERATION_TIMEDOUT       : //  (28) Operation timeout. The specified time-out period was reached according to the conditions.
        $response = FALSE;
        break;
      case CURLE_FTP_PORT_FAILED          : //  (30) The FTP PORT command returned error. This mostly happens when you haven't specified a good enough address for libcurl to use. See CURLOPT_FTPPORT.
      case CURLE_FTP_COULDNT_USE_REST     : //  (31) The FTP REST command returned error. This should never happen if the server is sane.
      case CURLE_RANGE_ERROR              : //  (33) The server does not support or accept range requests.
      case CURLE_HTTP_POST_ERROR          : //  (34) This is an odd error that mainly occurs due to internal confusion.
      case CURLE_SSL_CONNECT_ERROR        : //  (35) A problem occurred somewhere in the SSL/TLS handshake. You really want the error buffer and read the message there as it pinpoints the problem slightly more. Could be certificates (file formats, paths, permissions), passwords, and others.
      case CURLE_BAD_DOWNLOAD_RESUME      : //  (36) The download could not be resumed because the specified offset was out of the file boundary.
      case CURLE_FILE_COULDNT_READ_FILE   : //  (37) A file given with FILE                                                                                                                                                                                                                                                              : // couldn't be opened. Most likely because the file path doesn't identify an existing file. Did you check file permissions?
      case CURLE_LDAP_CANNOT_BIND         : //  (38) LDAP cannot bind. LDAP bind operation failed.
      case CURLE_LDAP_SEARCH_FAILED       : //  (39) LDAP search failed.
      case CURLE_FUNCTION_NOT_FOUND       : //  (41) Function not found. A required zlib function was not found.
      case CURLE_ABORTED_BY_CALLBACK      : //  (42) Aborted by callback. A callback returned "abort" to libcurl.
      case CURLE_BAD_FUNCTION_ARGUMENT    : //  (43) Internal error. A function was called with a bad parameter.
      case CURLE_INTERFACE_FAILED         : //  (45) Interface error. A specified outgoing interface could not be used. Set which interface to use for outgoing connections' source IP address with CURLOPT_INTERFACE.
      case CURLE_TOO_MANY_REDIRECTS       : //  (47) Too many redirects. When following redirects, libcurl hit the maximum amount. Set your limit with CURLOPT_MAXREDIRS.
      case CURLE_UNKNOWN_OPTION           : //  (48) An option passed to libcurl is not recognized/known. Refer to the appropriate documentation. This is most likely a problem in the program that uses libcurl. The error buffer might contain more specific information about which exact option it concerns.
      case CURLE_TELNET_OPTION_SYNTAX     : //  (49) A telnet option string was Illegally formatted.
      case CURLE_PEER_FAILED_VERIFICATION : //  (51) The remote server's SSL certificate or SSH md5 fingerprint was deemed not OK.
      case CURLE_GOT_NOTHING              : //  (52) Nothing was returned from the server, and under the circumstances, getting nothing is considered an error.
      case CURLE_SSL_ENGINE_NOTFOUND      : //  (53) The specified crypto engine wasn't found.
      case CURLE_SSL_ENGINE_SETFAILED     : //  (54) Failed setting the selected SSL crypto engine as default!
      case CURLE_SEND_ERROR               : //  (55) Failed sending network data.
      case CURLE_RECV_ERROR               : //  (56) Failure with receiving network data.
      case CURLE_SSL_CERTPROBLEM          : //  (58) problem with the local client certificate.
      case CURLE_SSL_CIPHER               : //  (59) Couldn't use specified cipher.
      case CURLE_SSL_CACERT               : //  (60) Peer certificate cannot be authenticated with known CA certificates.
      case CURLE_BAD_CONTENT_ENCODING     : //  (61) Unrecognized transfer encoding.
      case CURLE_LDAP_INVALID_URL         : //  (62) Invalid LDAP URL.
      case CURLE_FILESIZE_EXCEEDED        : //  (63) Maximum file size exceeded.
      case CURLE_USE_SSL_FAILED           : //  (64) Requested FTP SSL level failed.
      case CURLE_SEND_FAIL_REWIND         : //  (65) When doing a send operation curl had to rewind the data to retransmit, but the rewinding operation failed.
      case CURLE_SSL_ENGINE_INITFAILED    : //  (66) Initiating the SSL Engine failed.
      case CURLE_LOGIN_DENIED             : //  (67) The remote server denied curl to login (Added in 7.13.1)
      case CURLE_TFTP_NOTFOUND            : //  (68) File not found on TFTP server.
      case CURLE_TFTP_PERM                : //  (69) Permission problem on TFTP server.
      case CURLE_REMOTE_DISK_FULL         : //  (70) Out of disk space on the server.
      case CURLE_TFTP_ILLEGAL             : //  (71) Illegal TFTP operation.
      case CURLE_TFTP_UNKNOWNID           : //  (72) Unknown TFTP transfer ID.
      case CURLE_REMOTE_FILE_EXISTS       : //  (73) File already exists and will not be overwritten.
      case CURLE_TFTP_NOSUCHUSER          : //  (74) This error should never be returned by a properly functioning TFTP server.
      case CURLE_CONV_FAILED              : //  (75) Character conversion failed.
      case CURLE_CONV_REQD                : //  (76) Caller must register conversion callbacks.
      case CURLE_SSL_CACERT_BADFILE       : //  (77) Problem with reading the SSL CA cert (path? access rights?)
      case CURLE_REMOTE_FILE_NOT_FOUND    : //  (78) The resource referenced in the URL does not exist.
      case CURLE_SSH                      : //  (79) An unspecified error occurred during the SSH session.
      case CURLE_SSL_SHUTDOWN_FAILED      : //  (80) Failed to shut down the SSL connection.
      case CURLE_AGAIN                    : //  (81) Socket is not ready for send/recv wait till it's ready and try again. This return code is only returned from curl_easy_recv(3) and curl_easy_send(3) (Added in 7.18.2)
      case CURLE_SSL_CRL_BADFILE          : //  (82) Failed to load CRL file (Added in 7.19.0)
      case CURLE_SSL_ISSUER_ERROR         : //  (83) Issuer check failed (Added in 7.19.0)
      case CURLE_FTP_PRET_FAILED          : //  (84) PRET command failed
      case CURLE_RTSP_CSEQ_ERROR          : //  (85) Mismatch of RTSP CSeq numbers.
      case CURLE_RTSP_SESSION_ERROR       : //  (86) Mismatch of RTSP Session Identifiers.
      case CURLE_FTP_BAD_FILE_LIST        : //  (87) Unable to parse FTP file list (during FTP wildcard downloading).
      case CURLE_CHUNK_FAILED             : //  (88) Chunk callback reported error.
      default                             : // i.e. case CURLE_OBSOLETE*                                                                                                                                                                                                                                                                 : // Error codes never returned
        $response = FALSE;
        break;
    }/*}}}*/

    if ($response === FALSE) {

      $url = curl_error($curl_hdl); // Return the error in the inout parameter $url 
      
      self::$last_error_message = $url;

      syslog( LOG_INFO, "::" . __FUNCTION__ . ":" . __LINE__ . ": Error #{$curl_err}: ({$url})" );
      syslog( LOG_INFO, "::" . __FUNCTION__ . ":" . __LINE__ . ": Post-Execute" );

    }

    self::$last_transfer_info = curl_getinfo($curl_hdl);
    if (is_array(self::$last_transfer_info)) ksort(self::$last_transfer_info);

    foreach ( self::$last_transfer_info as $key => $val ) {
      if ( $key == 'request_header' ) {
        $trim_newlines = create_function('$a', 'return trim($a,"\n\r");');
        self::$last_transfer_info[$key] = array_filter(array_map($trim_newlines,explode("\n", $val)));
      }
    }

    curl_close( $curl_hdl );

    // If there are any cookies to save, i.e. because the CURL option CURLOPT_COOKIEJAR was used, load them here.
    if ( !is_null($cookiejar) ) self::parse_cookiejar($cookiejar);

    return $response;
  }/*}}}*/

  public static function post(& $url, $data, $curlopts = array()) {/*{{{*/

    self::$last_error_number = NULL;
    return self::execute_curl_method($url, $data, 'POST', $curlopts);

  }/*}}}*/

  public static function get(& $url, $curlopts = array()) {/*{{{*/

    self::$last_error_number = NULL;
    return self::execute_curl_method($url,NULL,'GET',$curlopts);

  }/*}}}*/

  public static function head(& $url, $curlopts = array()) {/*{{{*/

    self::$last_error_number = NULL;
    return self::execute_curl_method($url,NULL,'HEAD',$curlopts);

  }/*}}}*/

}
/*}}}*/
