<?php

/*
 * Class MysqlDatabasePlugin
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class MysqlDatabasePlugin extends mysqli /* implements DatabasePlugin */ {

  private $ls_last_operation_result = NULL;
  private $ls_last_operation_affected_rows = NULL;
  private $ls_result = NULL;

  function __construct($dbhost = NULL, $dbuser = NULL, $dbpass = NULL, $dbname = NULL) {/*{{{*/
    parent::init();
    if ( !parent::options(MYSQLI_INIT_COMMAND, 'SET AUTOCOMMIT = 0;') ) 
      throw new Exception("Unable to set autocommit mode");
    if ( !parent::options(MYSQLI_OPT_CONNECT_TIMEOUT, 5) ) 
      throw new Exception("Unable to set connection timeout");
    if ( !is_null($dbhost) && !is_null($dbuser) && !is_null($dbpass) ) {
      $this->connect($dbhost, $dbuser, $dbpass, $dbname);
    }
  }/*}}}*/

  function __destruct() {
  }

  public function connect($dbhost, $dbuser, $dbpass, $dbname = NULL) {
    if ( !$this->real_connect($dbhost, $dbuser, $dbpass, $dbname) ) {
      $error = mysqli_connect_errno();
      $errstr = mysqli_connect_error();
      throw new Exception("Error {$error}: Unable to connect to database ({$errstr})");
    }
    return TRUE;
  }

  public function query($sql = NULL, array $bindparams = NULL) {
    // Execute a query that may or may not return a resultset
    // Close any existing result set, and either return the result of an operation
    if ( !$this->ping() ) return FALSE;
    if ( !is_null($this->ls_result) ) {
      $this->ls_result->close(); // Disregard close() return value
    }
    $this->ls_last_operation_result = NULL;
    $this->ls_last_operation_affected_rows = NULL;
    $this->ls_result = NULL;

    if ( is_null($bindparams) ) {
      $resultset = parent::query( $sql, MYSQLI_STORE_RESULT ); // We wish to iterate over the resultset some time after this call is made, without incurring memory overhead of prestoring all retrieved data.
    } else {
      $prepare_hdl = parent::prepare( $sql );
      $paramindex = 0;
      foreach ( $bindparams as $b ) {
        $bindflag   = $b['bindflag'];
        $bindsource = $b['data'];
        $sourcefile = NULL;
        if ( 1 == preg_match('@^file://@',substr($bindsource,0,8)) ) {
          $sourcefile = preg_replace('@^file://@','',$bindsource);
          if ( !file_exists($sourcefile) || !is_readable($sourcefile) ) {
            syslog(LOG_INFO, __METHOD__ . ": Warning: Unable to set up data source - file '{$sourcefile}' not found or not accessible.");
            $sourcefile = NULL;
            continue;
          }
          $bindsource = NULL;
        }
        $prepare_hdl->bind_param($bindflag, $bindsource); 
        // If a file source is used for content, stream that.
        $streamchunk = 4096;
        if ( !is_null($sourcefile) && !(FALSE == ($handle = fopen($sourcefile,'r'))) ) {
          syslog(LOG_INFO, __METHOD__ . ": Streaming update field #{$paramindex}.");
          while (!feof($handle)) {
            $prepare_hdl->send_long_data($paramindex, fread($handle,$streamchunk));
          }
          fclose($handle);
          continue;
        }
        // Otherwise stream the string in 4K chunks
        $i = 0;
        do {
          $chunk = substr($bindsource, $i, $streamchunk);
          $prepare_hdl->send_long_data($paramindex, $chunk);
          $i += $streamchunk;
        } while ( $i < strlen($bindsource) );
        $paramindex++;
      }
      $resultset = $prepare_hdl->execute();
    }
    // If the resultset is a boolean, return it
    if ( is_bool($resultset) ) {
      if (C('DEBUG_'.get_class($this))) syslog( LOG_INFO,  __METHOD__ . ": Boolean result " . ($resultset ? 'TRUE' : 'FALSE') );  
      $this->ls_last_operation_result = $resultset;
      $this->ls_last_operation_affected_rows = NULL;
      return $resultset;
    } else {
			if (C('DEBUG_'.get_class($this))) syslog( LOG_INFO, __METHOD__ . ": Store result, row count " . $resultset->num_rows );  
		}
		// Otherwise, store it for later use
    $this->ls_result = $resultset;
    return TRUE;
  }

  public function begin($lock = TRUE) {
    throw new Exception("Unimplemented");
  }

  public function commit() {
    throw new Exception("Unimplemented");
  }

  public function last_query_rows() {
    $result = is_null($this->ls_result) 
      ? $this->ls_last_operation_result // Tristate, NULL if last operation did not yield a boolean result
      : $this->ls_result->num_rows
      ;
    if ( is_bool($result) ) {
      syslog( LOG_INFO, get_class($this) . '::' . __METHOD__ . ": Boolean result " . ($result ? 'TRUE' : 'FALSE') );  
    }
    return $result;
  }

  public function resultset() {
    $last_query_rows = $this->last_query_rows();
    if ( is_bool($last_query_rows) ) return $last_query_rows;
    if (!(is_integer($last_query_rows) && $last_query_rows > 0 )) return FALSE;
    if (!('mysqli_result' == get_class($this->ls_result) )) return FALSE;  
    $resultset = array(
      'attrnames' => array(),
      'values' => array()
    );
    while ( ($row = $this->ls_result->fetch_assoc()) ) {
      $record = array();
      foreach( $row as $attr => $val ) {
        if ( !array_key_exists($attr, array_flip($resultset['attrnames'])) ) $resultset['attrnames'][] = $attr;
        $index = array_flip($resultset['attrnames']);
        $record[$index[$attr]] = $val;
      }
      $resultset['values'][] = $record;
    }
    return $resultset;
  }

	function recordfetch(& $resultset) {
		if ( !('mysqli_result' == get_class($this->ls_result) ) ) return FALSE;
		$row = $this->ls_result->fetch_assoc();
		$single_result = NULL; 
		if ( $row ) {
			$resultset = array(
				'attrnames' => array(),
				'values' => array()
			);
      $record = array();
      foreach( $row as $attr => $val ) {
        if ( !array_key_exists($attr, array_flip($resultset['attrnames'])) ) $resultset['attrnames'][] = $attr;
        $index = array_flip($resultset['attrnames']);
        $record[$index[$attr]] = $val;
      }
      $resultset['values'] = $record;
			return TRUE;
		}
		$this->ls_result->close();
		$this->ls_result = NULL;
		return FALSE; 
	}


}

