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
	private $ls_last_operation_sql = NULL;
	private $ls_last_operation_errdesc = NULL;
	private $ls_last_insert_id = NULL;
  private $ls_result = NULL;
	protected $alias_map = NULL;
	var $debug_model = FALSE;

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

	public function set_alias_map($a) {
		$this->alias_map = is_array($a) && (0 < count($a)) ? $a : NULL;
	}

  public function query($sql = NULL, array $bindparams = NULL) {
    // Execute a query that may or may not return a resultset
    // Close any existing result set, and either return the result of an operation
		$debug_method = FALSE;

    if ( empty($sql) ) {
      throw new Exception("Empty query in ".get_class($this));
    }

    if ( !$this->ping() ) return FALSE;
    if ( !is_null($this->ls_result) ) {
      $this->ls_result->close(); // Disregard close() return value
    }
    $this->ls_last_operation_result = NULL;
    $this->ls_last_operation_affected_rows = NULL;
		$this->ls_last_operation_errdesc = NULL;
    $this->ls_result = NULL;
		$this->ls_last_insert_id = NULL;

		if ( empty($sql) ) {
			syslog(LOG_INFO, get_class($this) . '::' . __FUNCTION__ . ": -- - -- - ERROR: Empty SQL statement, unable to proceed.");
      throw new Exception('DB');
			return FALSE;
		}

    if ( is_null($bindparams) ) {
      $resultset = parent::query( $sql, MYSQLI_STORE_RESULT ); // We wish to iterate over the resultset some time after this call is made, without incurring memory overhead of prestoring all retrieved data.
			$this->ls_last_insert_id = $this->insert_id;
    } else {
      $prepare_hdl = parent::prepare( $sql );
      $paramindex = 0;
      if ( $prepare_hdl == FALSE ) {
        syslog(LOG_INFO, get_class($this) . '::' . __FUNCTION__ . ": -- - -- - ERROR: Invalid prepared statement handle, SQL = {$sql}");
        $resultset = FALSE;
      } else {
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
            if ( $debug_method ) syslog(LOG_INFO, __METHOD__ . ": Streaming update field #{$paramindex}.");
            while (!feof($handle)) {
              $prepare_hdl->send_long_data($paramindex, fread($handle,$streamchunk));
            }
            fclose($handle);
            continue;
          }
          // Otherwise stream the string in 4K chunks
          $i = 0;
          $bindsource_length = strlen($bindsource);
          $streamchunk = min($streamchunk, $bindsource_length);
          if ( $debug_method ) syslog( LOG_INFO, __METHOD__ . ": Streaming string - Len {$bindsource_length}, chunksize = {$streamchunk}");
          $n = 0;
          do {
            $chunk = substr($bindsource, $i, $streamchunk);
            if ( $debug_method ) syslog( LOG_INFO, __METHOD__ . ": Chunk {$n} @ {$i}");
            $prepare_hdl->send_long_data($paramindex, $chunk);
            $i += $streamchunk;
            $n++;
          } while ( $i < strlen($bindsource) );
          $paramindex++;
        }
        $resultset = $prepare_hdl->execute();
				$this->ls_last_insert_id = $prepare_hdl->insert_id;
      }
    }
		$this->ls_last_operation_sql = $sql;
    // If the resultset is a boolean, return it
    if ( is_bool($resultset) ) {
			if ( $resultset == FALSE ) {
				syslog( LOG_INFO,  __METHOD__ . ": Boolean result " . ($resultset ? 'TRUE' : 'FALSE') . " query {$sql}" );  
				$this->ls_last_operation_errdesc = $this->error;
				syslog( LOG_INFO,  __METHOD__ . ": Last error: {$this->ls_last_operation_errdesc}" );  
				if ( C('DEBUG_STACKTRACE_ON_SQL_ERROR') == TRUE )
				try {
					throw new Exception('DB');
				} catch ( Exception $e ) {
					foreach ( $e->getTrace() as $st ) { 
						if ( !array_key_exists('class', $st) ) $st['class'] = NULL;
						syslog( LOG_INFO, " @ {$st['line']} {$st['class']}::{$st['function']}() in {$st['file']}");
					}
				}
			}
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

	public function escape_string($s) {
		return parent::escape_string($s);
	}

  public function begin($lock = TRUE) {
    throw new Exception(__METHOD__.":Unimplemented");
  }

  public function commit() {
    throw new Exception(__METHOD__.":Unimplemented");
  }

	public function last_insert_id() {
		return $this->ls_last_insert_id;
	}

  public function last_query_rows() {
    $result = is_null($this->ls_result) 
      ? $this->ls_last_operation_result // Tristate, NULL if last operation did not yield a boolean result
      : $this->ls_result->num_rows
      ;
    if ( is_bool($result) ) {
      syslog( LOG_INFO, get_class($this) . '::' . __METHOD__ . ": Boolean result " . ($result ? 'TRUE' : 'FALSE') . " query {$this->ls_last_operation_sql}" );
			if ( FALSE == $result ) {
				syslog( LOG_INFO,  __METHOD__ . ": Last error: {$this->ls_last_operation_errdesc}" );  
			}
    }
    return $result;
  }

	protected function recordset_combine(& $resultset, $row) {/*{{{*/
		// Return values in $record,
		// modify $resultset
		$record = array();
		$attrmatch = array();
		foreach( $row as $attr => $val ) {
			if ( !is_null($this->alias_map) && 1 == preg_match($this->alias_map['match'], $attr, $attrmatch) ) {
				// See DatabaseUtility::reorder_aliasmap()
				$alias    = $attrmatch[1];
				$attrname = $attrmatch[2];
				$map      = array_element($this->alias_map['map'],$alias);
				$left     = array_element($map,'left'); // Indicates a foreign table attribute, if present; else a Join attribute
				if ( is_null($left) ) {
					$left = array_element($map, 'attrname');
					if ( !array_key_exists($left, array_flip($resultset['attrnames'])) ) $resultset['attrnames'][] = $left;
					$index = array_flip($resultset['attrnames']);
          $attr = 'join';
				} else {
					$left = $this->alias_map['map'][$left]['attrname'];
					if ( !array_key_exists($left, array_flip($resultset['attrnames'])) ) $resultset['attrnames'][] = $left;
					$index = array_flip($resultset['attrnames']);
          $attr  = 'data';
				}
        if ( !array_key_exists($index[$left],$record) ) $record[$index[$left]] = array(
          'join' => array(),
          'data' => array(),
        );
        if ( !is_null($record[$index[$left]][$attr]) ) {
          if ( $attrname == 'id' && is_null($val) ) $record[$index[$left]][$attr] = NULL;
          else
          $record[$index[$left]][$attr] = array_merge(
            $record[$index[$left]][$attr],
            array($attrname => $val)
          );
        }
				continue;
			}

			if ( !array_key_exists($attr, array_flip($resultset['attrnames'])) ) $resultset['attrnames'][] = $attr;
			$index = array_flip($resultset['attrnames']);
			$record[$index[$attr]] = $val;

		}

		if ($this->debug_model) if ( !is_null($this->alias_map) ) {
			syslog( LOG_INFO, __METHOD__ . " Resultset: " . print_r($resultset,TRUE));
			syslog( LOG_INFO, __METHOD__ . "    Record: " . print_r($record,TRUE));
		}

		return $record;
	}/*}}}*/

  public function resultset() {
    $last_query_rows = $this->last_query_rows();
    if ( is_bool($last_query_rows) ) return $last_query_rows;
    if (!(is_integer($last_query_rows) && $last_query_rows > 0 )) return FALSE;
    if (!('mysqli_result' == get_class($this->ls_result) )) return FALSE;  
    $resultset = array(
      'attrnames' => array(),
      'values' => array(),
    );
    while ( ($row = $this->ls_result->fetch_assoc()) ) {/*{{{*/
      $resultset['values'][] = $this->recordset_combine($resultset, $row);
    }/*}}}*/
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
      $resultset['values'] = $this->recordset_combine($resultset, $row);
			return TRUE;
		}
		$this->ls_result->close();
		$this->ls_result = NULL;
		return FALSE; 
	}


}

