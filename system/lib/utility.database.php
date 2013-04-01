<?php

class DatabaseUtility extends ReflectionClass {

  static $dbhandle = NULL;

	protected $tablename = NULL;
  protected $query_conditions = array();
  protected $query_result = NULL;
	protected $attrlist = NULL;

  protected $disable_logging = array(
    '-RawparseUtility'
  );

  function __construct() {/*{{{*/
    parent::__construct($this);
    if (!(FALSE === stripos(get_class($this), 'Model'))) 
      $this->initialize_derived_class_state();
  }/*}}}*/

  private final function initialize_db_handle() {/*{{{*/
    if ( is_null(self::$dbhandle) ) {
      $plugin_classname = C('DBTYPE') . 'DatabasePlugin'; 
      self::$dbhandle = new $plugin_classname(DBHOST, DBUSER, DBPASS, DBNAME); 
    }
  }/*}}}*/

    function substitute($str) {/*{{{*/
			// Replace contents of string with this object's fields
      $regex_match = array();
      $regex_replace = array();
      foreach ( $this->fetch_property_list() as $nv ) {
        $methodname = "get_{$nv['name']}";
        $propertyname = "{$nv['name']}_{$nv['type']}";
        if ( method_exists($this, $methodname) ) {
          $regex_match[] = "@{{$nv["name"]}}@imU";
          $regex_replace[] = $this->$propertyname;
        }
      }
      $str = preg_replace($regex_match, $regex_replace, $str);
      return $str == FALSE ? NULL : $str;
    }/*}}}*/

	function & remove() {
		if ( $this->in_database() ) {
			$sql = <<<EOS
DELETE FROM `{$this->tablename}` WHERE `id` = {$this->id}
EOS;
			$this->query($sql);
		}
		return $this;
	}

  function fetch_property_list() {/*{{{*/
    // Get list of public properties of the derived class 
    $members = array();
    foreach ( $this->getProperties(ReflectionProperty::IS_PUBLIC) as $p ) {
      if ( $p->getDeclaringClass()->getName() != get_class($this) ) continue;
      $defvalue = $p->getValue($this);
      if ( strlen($defvalue) > 200 ) $defvalue = substr($defvalue,0,200) . '... (' . strlen($defvalue) . ')'; 
      $this->syslog(__FUNCTION__, '-', "- Value '{$defvalue}' type " . gettype($defvalue) );
      $nameparts = array();
      preg_match_all('/^(.*)_(.*)$/i',$p->getName(),$nameparts);
      $use_nametype = 0 < strlen($nameparts[1][0]);
      $member = array(
        'name' => $use_nametype ? $nameparts[1][0] : $p->getName(),
        'type' => $use_nametype ? $nameparts[2][0] : 'pkey',
      );
      $propername = join('_',camelcase_to_array($member['name']));
      if (!( str_replace('__','_',$propername) == $member['name'] ) && ('pkey' == $member['type'])) {
        $member['propername'] = $propername;
      }
      $members[$member['name']] = $member;
    }
    $this->recursive_dump($members,0,get_class($this));
    return $members;
  }/*}}}*/

  private final function fetch_typemap($attrs) {/*{{{*/
    $type_map = array(
      'pkey' => array('attrname' => 'pkey'              , 'quot' => FALSE),
      'utx'  => array('attrname' => 'INT(11)'           , 'quot' => FALSE),
      'int'  => array('attrname' => 'INT(s)'            , 'quot' => FALSE),
      'vc'   => array('attrname' => 'VARCHAR(s)'        , 'quot' => TRUE),
      'blob' => array('attrname' => 'MEDIUMBLOB'        , 'quot' => FALSE, 'mustbind' => TRUE, ),
      'dbl'  => array('attrname' => 'DOUBLE DEFAULT 0.0', 'quot' => FALSE),
      'flt'  => array('attrname' => 'FLOAT DEFAULT 0.0' , 'quot' => FALSE),
			'bool' => array('attrname' => 'BOOLEAN'           , 'quot' => FALSE),
    );
    $sqlmodif = array(
      'uniq'  => 'UNIQUE',
    );
    $fieldname     = $attrs['name'];
    $modifiers     = '/([A-Za-z]+)([0-9]+)?(uniq)?$/';
    $matches       = array();
    $match_result  = preg_match_all($modifiers, $attrs['type'], $matches);
    $size          = $matches[2][0];
    $modifier      = array_key_exists(3,$matches) && array_key_exists($matches[3][0],$sqlmodif) ? $sqlmodif[$matches[3][0]] : NULL;

    $attrs['type'] = $matches[1][0];
    if (C('DEBUG_' . strtoupper(get_class($this)))) {
      $this->syslog(__FUNCTION__, '-', "Type spec '{$attrs['type']}'" );
      $this->recursive_dump($matches, 0, $attrs['type']);
    }

    $quoting    = array_key_exists($attrs['type'],$type_map) ? $type_map[$attrs['type']]['quot'] : FALSE;
    $bindparam  = array_key_exists($attrs['type'],$type_map) && array_key_exists('mustbind', $type_map[$attrs['type']]) ? $type_map[$attrs['type']]['mustbind'] : FALSE;
    $properties = array_key_exists($attrs['type'],$type_map) ? $type_map[$attrs['type']]['attrname'] : 'VARCHAR(64)';
    $properties = preg_replace('/\((s)\)/',"({$size})",$type_map[$matches[1][0]]['attrname']);
    $properties = "{$properties} {$modifier}";

    // TODO: Implement model references
    if (array_key_exists('propername',$attrs)) {
      $fieldname = $attrs['propername'];
      $properties = "INT(11) REFERENCES `{$attrs['propername']}` (`id`) MATCH FULL ON UPDATE CASCADE ON DELETE RESTRICT";
    }
    return array(
      'fieldname' => $fieldname,
      'properties' => $properties,
      'quoted' => $quoting,
      'size' => empty($size) ? NULL : intval($size),
      'mustbind' => $bindparam,
    );
  }/*}}}*/

  private final function construct_backing_table($tablename, $members) {/*{{{*/
    $this->syslog( '?', '-', "Constructing backing table '{$tablename}' for " . get_class($this) );
    $attrset = array('`id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY'); 
    // Detect class names
    foreach ( $members as $attrs ) {
      extract($this->fetch_typemap($attrs));
      $attrset[] = <<<EOH
`{$fieldname}` {$properties}
EOH;
    }
    $this->recursive_dump($attrset,'-','>');
    $attrset = join(',', $attrset);
    $create_table =<<<EOH
CREATE TABLE `{$tablename}` ( {$attrset} ) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8
EOH;
    $create_result = $this->query($create_table)->resultset();
    $result_type = gettype($create_result);
    if ( is_bool($create_result) ) $create_result = $create_result ? 'TRUE' : 'FALSE';
    $this->syslog('..','-', "Create   stmt: {$create_table}" );
    $this->syslog('..','-', "Create result: {$create_result} ({$result_type}). " . $this->error() );
  }/*}}}*/

  function fetch_structure_backing_diffs($tablename, $members) {/*{{{*/
    $attrdefs        = $this->query("SHOW COLUMNS FROM `{$tablename}`")->resultset();
    $current_columns = create_function('$a', 'return isset($a["propername"]) ? $a["propername"] : ( $a["name"] == "id" ? NULL : $a["name"]);');
    $removed_columns = array_flip(array_filter(array_map($current_columns, $members))); // In DB, not in memory
    $added_columns   = $members; // Attributes in classdef, but not in backing store 
    $attribute_map   = array_flip($attrdefs['attrnames']);
    // $this->recursive_dump($removed_columns, 0, '-%');
    foreach ( $attrdefs['values'] as $attrs ) {
      $attrname   = $attrs[$attribute_map['Field']];
      // $this->syslog( __FUNCTION__, 'FORCE', "A+ {$attrname} vs " . join('',camelcase_to_array($attrname)) );
      if ( $attrname == 'id' ) continue;
      $attrname   = join('',camelcase_to_array($attrname)) == $attrname ? $attrname : join('_',camelcase_to_array($attrname));
      $attrtype   = $attrs[$attribute_map['Type']];
      // Reduce list of extant columns to those that are not in the class
      $remove_extant = create_function('$a', 'return ((array_key_exists("propername", $a) && ($a["propername"] == "'. $attrname .'")) || ($a["name"] == "'. $attrname .'")) ? NULL : $a;');
      $added_columns = array_filter(array_map($remove_extant, $added_columns));
      // Reduce list of attributes removed from the class by excluding columns that are still attributes of the on-disk table 
      $removed_columns[$attrname] = array_key_exists($attrname, $removed_columns)
        ? NULL
        : $attrname
        ;
    }
    // $this->recursive_dump($added_columns, 0, '%');
    foreach ( $added_columns as $t => $column ) {/*{{{*/
      // $this->recursive_dump($column, 0, $t . '-i');
      $removed_columns[$column[array_key_exists("propername", $column) ? "propername" : "name"]] = NULL;
    }/*}}}*/
    $removed_columns = array_values(array_filter($removed_columns));
    // $this->recursive_dump($added_columns, 0, '+');
    // $this->recursive_dump($removed_columns, 0, '-');
    return array(
      'added_columns'   => array_values($added_columns),
      'removed_columns' => $removed_columns,
    );
  }/*}}}*/

  protected final function initialize_derived_class_state() {/*{{{*/
    $this->tablename = join('_', camelcase_to_array(get_class($this)));
    $this->initialize_db_handle();
    if ( C('LS_SYNCHRONIZE_MODEL_STRUCTURE') ) {

      $members        = $this->fetch_property_list();

      // Determine if the backing table exists; construct it if it does not.
      $matched_tables = array();

      $result         = $this->query('SHOW TABLES')->resultset();
      if ( is_array($result) && array_key_exists('values',$result) ) {
        $matched_tables = array_filter(
          array_map(
            create_function('$a', 'return $a[0] == "'.$this->tablename.'" ? $a[0] : NULL;'),
            $result['values']
          )
        );
      }
      if ( count($matched_tables) == 0 ) $this->construct_backing_table($this->tablename, $members);

      // Synchronize table columns and class attributes
      extract($this->fetch_structure_backing_diffs($this->tablename, $members));
      // Remove newly undefined columns
      //$this->syslog( __FUNCTION__, 'FORCE', "Test for removable columns" );
      //$this->recursive_dump($removed_columns,0,'FORCE');
      //$this->recursive_dump($members,0,'FORCE');
      foreach ( $removed_columns as $removed_column ) {
        $sql = <<<EOH
ALTER TABLE `{$this->tablename}` DROP COLUMN `{$removed_column}`
EOH;
        $result = is_bool($result = $this->query($sql)->resultset()) ? ($result ? 'TRUE' : 'FALSE') : '---';
        $this->syslog( __FUNCTION__, 'FORCE', "Remove {$removed_column}: {$result}" );
      }
      foreach ( $added_columns as $added_column ) {
        $column_name = $added_column['name'];
        extract($this->fetch_typemap($added_column));
        $sql = <<<EOH
ALTER TABLE `{$this->tablename}` ADD COLUMN `{$column_name}` {$properties} AFTER `id`
EOH;
        $result = is_bool($result = $this->query($sql)->resultset()) ? ($result ? 'TRUE' : 'FALSE ' . $sql) : '---';
        $this->syslog( __FUNCTION__, 'FORCE', "Add {$column_name}: {$result}" );
      }
    }
  }/*}}}*/

  function & execute() {/*{{{*/
    return $this;
  }/*}}}*/

  function & query($sql, $bindparams = NULL) {/*{{{*/
    $this->initialize_db_handle();
    self::$dbhandle->query($sql, $bindparams);
    return $this;
  }/*}}}*/

  private function full_property_list() {/*{{{*/
    $attrlist     = $this->fetch_property_list();
    foreach ( $attrlist as $member_index => $attrs ) {
      $attrname = "{$attrs['name']}_{$attrs['type']}";
      $attrlist[$member_index]['attrs'] = $this->fetch_typemap($attrs);
      $value    = $this->$attrname;
      if ( is_null( $value ) ) $value = 'NULL';
      else $value = $attrlist[$member_index]['attrs']['quoted']
        ? "'" . self::$dbhandle->escape_string($value) . "'"
        : "{$value}"
        ;
      $attrlist[$member_index]['value'] = $value;
    }
    return $attrlist;
  }/*}}}*/

  function construct_sql_from_conditiontree(& $attrlist, $condition_branch = NULL) {/*{{{*/
    // FIXME:  This probably doesn't handle conjuctions properly.
    // Parse a binary tree containing query conditions, returning the resulting
    // condition string (which must evaluate to a valid SQL query condition)
    $querystring = array();
    $this->recursive_dump($this->query_conditions,0,'CSFC');
    foreach ( is_null($condition_branch) ? $this->query_conditions : $condition_branch as $conj_or_attr => $operands ) {

      if ( array_key_exists(strtoupper($conj_or_attr), array_flip(array('AND', 'OR')))) {
        $this->syslog(__FUNCTION__,__LINE__, "A conj '{$conj_or_attr}'");
        $fragment = $this->construct_sql_from_conditiontree($attrlist, $operands);
        $querystring[] = join(" {$conj_or_attr} ", $fragment);
        continue;
      }
      if ( !is_array($operands) ) {
        $operand_regex = '@^(LIKE|REGEXP)(.*)@';
        $operand_match = array();
        $opmatch       = preg_match($operand_regex, $operands, $operand_match);
        $operator      = '=';
        if ( 1 == $opmatch ) {
          // FIXME:  This is absolutely NOT secure, inputs need to be filtered against SQL injection.
          // $this->recursive_dump($operand_match, 0, 'FORCE');
          $operator = trim($operand_match[1]);
          $value    = trim($operand_match[2]);
          $this->syslog(__FUNCTION__,__LINE__, 'B');
        } else {
          $value    = $attrlist[$conj_or_attr]['attrs']['quoted'] ? "'{$operands}'" : "{$operands}";
          $this->syslog(__FUNCTION__,__LINE__, "C {$conj_or_attr} {$operands}");
        }
        $querystring[] = "`{$conj_or_attr}` {$operator} {$value}"; 
      } else {
        // $this->recursive_dump($operands,0,__FUNCTION__);
        $value_wrap   = create_function('$a', $attrlist[$conj_or_attr]['attrs']['quoted'] ? 'return "'."'".'" . $a . "'."'".'";' : 'return $a;');
        $value        = join(',',array_map($value_wrap, $operands));
        $querystring[] = "`{$conj_or_attr}` IN ({$value})"; 
        $this->syslog(__FUNCTION__,__LINE__, 'D');
      }
    }
    return $querystring;
  }/*}}}*/

  function update() {/*{{{*/
    $f_attrval   = create_function('$a', 'return $a["attrs"]["mustbind"] == TRUE ? "`{$a["name"]}` = ?": "`{$a["name"]}` = {$a["value"]}";');
    $f_bindattrs = create_function('$a', 'return $a["attrs"]["mustbind"] == TRUE ? "{$a["name"]}" : NULL;');
    $attrlist    = $this->full_property_list();
    $boundattrs  = array_filter(array_map($f_bindattrs,  $attrlist));
    // $this->recursive_dump($attrlist,0,__FUNCTION__);
    $attrval   = join(',', array_map($f_attrval, $attrlist));
    $sql = <<<EOS
UPDATE `{$this->tablename}` SET {$attrval} WHERE `id` = {$this->id}
EOS;
    $this->insert_update_common($sql, $boundattrs, $attrlist);
    if ( !empty(self::$dbhandle->error) ) {
      $this->syslog( __FUNCTION__, 'FORCE', "ERROR: " . self::$dbhandle->error ); // throw new Exception("Failed to execute SQL: {$sql}");
      $this->syslog(__FUNCTION__, 'FORCE', "Record #{$this->id}: {$sql}" );
		} else
      $this->syslog(__FUNCTION__, __LINE__, "Updated record #{$this->id}: {$sql}" );
    return $this->id;
  }/*}}}*/

  function count(array $a = array()) {
		if ( empty($a) ) 
		$sql = <<<EOS
SELECT COUNT(*) n FROM `{$this->tablename}`
EOS;
		else {
			$sql = '';
			$this->where($a)->prepare_select_sql($sql);
			$sql = preg_replace('@^SELECT \*@','SELECT COUNT(*) n', $sql);
		}
		$result = $this->query($sql)->resultset();
		$result = array_combine($result['attrnames'], $result['values'][0]);
    // $this->recursive_dump($result,0,'FORCE');
		return array_key_exists('n',$result) ? intval($result['n']) : NULL;
	}

  protected function prepare_select_sql(& $sql, $exclude_blobfields = FALSE) {/*{{{*/
    // TODO: Exclude*BLOB attributes from the statement generated with this method. 
    $this->initialize_db_handle();
    $key_by_varname = create_function('$a', 'return $a["name"];');
    $attrlist = $this->full_property_list();
		$key_map  = array_map($key_by_varname, $attrlist);
    // DEBUG
    // $this->syslog(__FUNCTION__,'FORCE', "--- Property list");
    // $this->recursive_dump( $key_map, 0, 'FORCE');
		if ( 0 < count($attrlist) && count($attrlist) == count($key_map) ) {
      $bindable_attrs = ( $exclude_blobfields )
        ? create_function('$a', 'return $a["attrs"]["mustbind"] ? NULL : "`" . $a["name"] . "`";')
        : create_function('$a', 'return "`{$a["name"]}`";')
        ;
      $attrnames = array_filter(array_map($bindable_attrs, $attrlist));
      $attrnames[] = '`id`'; // Mandatory to include this
      // DEBUG
      // $this->recursive_dump( $attrnames, 0, 'FORCE');
      $attrnames = join(',',$attrnames);
			$attrlist = array_combine($key_map, $attrlist);
      $conditionstring = $this->construct_sql_from_conditiontree($attrlist);
      // DEBUG
      // $this->recursive_dump( $conditionstring, 0, 'FORCE');
			$conditionstring = join(' ',$conditionstring);
			$sql = <<<EOS
SELECT {$attrnames} FROM `{$this->tablename}` WHERE {$conditionstring}
EOS;
      // DEBUG
			// $this->syslog( __FUNCTION__, 'FORCE', "Query: {$sql}");
		}
    return $attrlist;
  }/*}}}*/

	function result_to_model(array $result) {
    if ( is_array($result) && (0 < count($result)) && array_key_exists('attrnames', $result)) {
      $this->query_result = array();
      foreach ( $result['attrnames'] as $resattridx => $rescol ) {
        $attrname = $rescol == 'id'
          ? 'id'
          : "{$this->attrlist[$rescol]['name']}_{$this->attrlist[$rescol]['type']}"
          ;
				$value = $result['values'][0][$resattridx]; // A single-record fetch reduces nest depth by 1
        $this->query_result[$attrname] = $value; 
				$this->$attrname = $value; 
        if ( strlen($this->$attrname) < 500 )
        $this->syslog( __FUNCTION__, __LINE__, "> {$attrname} <- [{$this->$attrname}|{$value}]");
      }
		} else {
			$this->syslog(__FUNCTION__,'FORCE','- model load failure ' . join('|', $result));
		}
	}

  function select() {/*{{{*/
    $sql = '';
    $this->attrlist = $this->prepare_select_sql($sql);
    if (0) {
      // DEBUG
      $this->syslog( __FUNCTION__, 'FORCE', __LINE__ . "> {$sql}");
      $this->recursive_dump( $this->attrlist, 0, 'FORCE');
    }
    $result = $this->query($sql)->resultset();
    $this->recursive_dump($result,0,'R');
    $this->recursive_dump($this->query_conditions,0,'C');
    $this->query_result = NULL;
		//$this->syslog(__FUNCTION__,'FORCE',">>>>>>>>>>>>>>>>>>>>>>>" );
		if ( is_array($result) ) {
			//$this->recursive_dump($result,0,'FORCE');
		 	$this->result_to_model($result);
		}
		//$this->syslog(__FUNCTION__,'FORCE',"<<<<<<<<<<<<<<<<<<<<<<<" );
    return $this->query_result;
  }/*}}}*/

  function & recordfetch_setup() {/*{{{*/
    $sql = '';
		$this->attrlist = $this->prepare_select_sql($sql);
    return $this->query($sql);
  }/*}}}*/

  function recordfetch(& $single_record, $update_model = FALSE) {/*{{{*/
		$single_record = array();
    $a = self::$dbhandle->recordfetch($single_record);
		if ( $a == TRUE ) {
			if ( $update_model && is_array($single_record) ) {
				//$this->syslog(__FUNCTION__,'FORCE',">>>>>>>>>>>>>>>>>>>>>>>" );
				//$this->recursive_dump($single_record,0,'FORCE');
				$this->result_to_model(array(
					'attrnames' => $single_record['attrnames'],
				  'values' => array($single_record['values']),
				));
				//$this->syslog(__FUNCTION__,'FORCE',"<<<<<<<<<<<<<<<<<<<<<<<" );
			}
			$single_record = array_combine($single_record['attrnames'], $single_record['values']); 
		}
		return $a;
  }/*}}}*/

  function fetch($attrval, $attrname = NULL) {/*{{{*/
		$this->id = NULL;
    if ( is_null($attrname) ) {
      $this->where(array('id' => $this->id))->select();
    } else {
      $this->where(array($attrname => $attrval))->select();
    }
    // $this->syslog( __FUNCTION__, 'FORCE', "Fetch: ID obtained is ({$this->id})" );
    // $this->recursive_dump($this->query_result, 0, 'FORCE');
    return $this->query_result;
  }/*}}}*/

  function in_database() {
    return intval($this->id) > 0;
  }

  function stow() {/*{{{*/
    if ( !is_null($this->id) ) {
      $this->syslog( __FUNCTION__ , 'FORCE', "WARNING: Updating " . get_class($this) . " #{$this->id}");
      // $this->recursive_dump(explode("\n",print_r($this,TRUE)),0,'FORCE');
      // return;
    }
    return is_null($this->id)
      ? $this->insert()
      : $this->update()
      ;
  }/*}}}*/

  private function insert_update_common($sql, $boundattrs, $attrlist) {/*{{{*/
    if ( 0 < count($boundattrs) ) {
      $this->syslog(__FUNCTION__,'FORCE', "--- Currently: {$sql}");
      $bindable_attrs = array();
      foreach ( $boundattrs as $b ) {
        // TODO: Allow string, integer, and double values
        $bindable_attrs[$b] = array(
          'bindflag' => 'b',
          'data'     => $attrlist[$b]['value'], 
        );
        if ( is_null($bindable_attrs[$b]['data']) ) {
          $this->syslog(__FUNCTION__,'FORCE', "--- WARNING: Data source not provided for bindable parameter '{$b}'. Coercing to empty string ''");
          // $this->recursive_dump($boundattrs,0,'FORCE');
          $bindable_attrs[$b]['data'] = '';
        }
      }
      // $this->recursive_dump($bindable_attrs,0,'FORCE');
      $this->query($sql, $bindable_attrs);
    } else {
      $this->query($sql);
    }
  }/*}}}*/

  function insert() {/*{{{*/
    $f_attrnames = create_function('$a', 'return "`{$a["name"]}`";');
    $f_valueset  = create_function('$a', 'return $a["attrs"]["mustbind"] == TRUE ? "?" : "{$a["value"]}";');
    $f_bindattrs = create_function('$a', 'return $a["attrs"]["mustbind"] == TRUE ? "{$a["name"]}" : NULL;');
    $attrlist    = $this->full_property_list();
    // DEBUG
    // $this->syslog(__FUNCTION__,'FORCE', "--- Property list");
    // $this->recursive_dump($attrlist,0,'FORCE');
    // DEBUG
    // $this->recursive_dump( $key_map, 0, 'FORCE');
    $namelist   = join(',', array_map($f_attrnames, $attrlist));
    $valueset   = join(',', array_map($f_valueset,  $attrlist));
    $boundattrs = array_filter(array_map($f_bindattrs,  $attrlist));
    $sql = <<<EOS
INSERT INTO `{$this->tablename}` ({$namelist}) VALUES ({$valueset})
EOS;
    $this->insert_update_common($sql, $boundattrs, $attrlist);
    $this->last_inserted_id = self::$dbhandle->insert_id;
    if ( !empty(self::$dbhandle->error) )
      $this->syslog( __FUNCTION__, __LINE__, "ERROR: " . self::$dbhandle->error ); // throw new Exception("Failed to execute SQL: {$sql}");
    else
      $this->syslog(__FUNCTION__, __LINE__, "Inserted record #{$this->last_inserted_id}: {$sql}" );
    return $this->last_inserted_id;
  }/*}}}*/

  function info() {/*{{{*/
    return self::$dbhandle->info;
  }/*}}}*/

  function error() {/*{{{*/
    return self::$dbhandle->error;
  }/*}}}*/

  function & begin() {/*{{{*/
    return $this;
  }/*}}}*/

  function & commit() {/*{{{*/
    return $this;
  }/*}}}*/

  function & rollback() {/*{{{*/
    return $this;
  }/*}}}*/

  function & where($conditions) {/*{{{*/
    if ( is_array($conditions) ) 
      $this->query_conditions = $conditions;
    return $this;
  }/*}}}*/

  function & resultset() {/*{{{*/
    $this->initialize_db_handle();
    return self::$dbhandle->resultset();
  }/*}}}*/

  // Experimental HTTP action adaptor methods

  function & get() { return $this; }
  function & post() { return $this; }
  function & put() { return $this; }
  function & trace() { return $this; }
  function & head() { return $this; }
  function & delete() { return $this; }

  final protected function logging_ok() {/*{{{*/
    if ( FALSE === C('DEBUG_'.get_class($this)) ) return FALSE;
    return ( (0 == count($this->disable_logging)) ||
      !array_key_exists(get_class($this), array_flip($this->disable_logging))
    );
  }/*}}}*/

  final protected function syslog($fxn, $line, $message) {/*{{{*/
    if ( $this->logging_ok() || ($line == 'FORCE') ) 
      syslog( LOG_INFO, $this->syslog_preamble($fxn, $line) . " {$message}" );
  }/*}}}*/

  final protected function syslog_preamble($fxn, $line) {/*{{{*/
    $line = is_null($line) ? "" : "({$line})";
    return get_class($this) . ":: {$fxn}{$line}: ";
  }/*}}}*/

  protected function recursive_file_dump($filename, $a, $depth, $prefix) {/*{{{*/
    $filehandle = fopen($filename,'w');
    if ( !(FALSE == $filehandle) ) {
      $this->recursive_fdump($filehandle, $a, $depth, $prefix);
      fclose($filehandle);
    }
  }/*}}}*/

  protected function recursive_fdump(& $h, $a, $depth = 0, $prefix = '') {/*{{{*/
    foreach ( $a as $key => $val ) {
      $logstring = "{$prefix}" . str_pad(' ', $depth * 3, " ", STR_PAD_LEFT) . " {$key} => " ;
      if ( is_array($val) ) {
        fprintf($h, "%s", "{$logstring}\n");
        $this->recursive_fdump($h, $val, $depth + 1, $prefix);
      }
      else {
        $logstring .= $val;
        fprintf($h, "%s", "{$logstring}\n");
      }
    }
  }/*}}}*/

  final protected function recursive_dump($a, $depth = 0, $prefix = NULL) {/*{{{*/
    if ( !$this->logging_ok() && !($prefix == 'FORCE') ) return;
    if ( !is_array($a) ) return;
    foreach ( $a as $key => $val ) {
      $logstring = is_null($prefix) 
        ? basename(__FILE__) . "::" . __LINE__ . "::" . __FUNCTION__ . ": "
        : "{$prefix}: "
        ;
      $logstring .= str_pad(' ', $depth * 3, " ", STR_PAD_LEFT) . " {$key} => " ;
      if ( is_array($val) ) {
        syslog( LOG_INFO, $logstring );
        $this->recursive_dump($val, $depth + 1, $prefix);
      }
      else {
        if ( is_null($val) ) 
          $logstring .= 'NULL';
        else if ( is_bool($val) )
          $logstring .= ($val ? 'TRUE' : 'FALSE');
        else if ( empty($val) )
          $logstring .= '[EMPTY]';
        else
          $logstring .= $val;
        syslog( LOG_INFO, $logstring );
      }
    }
  }/*}}}*/

	function & set_contents_from_array($document_contents, $execute = TRUE) {/*{{{*/
		$property_list = $this->fetch_property_list();
		if ( !(0 < count($property_list)) ) return $this;
		$property_list = array_combine(
			array_map($this->slice('name'),$property_list),
			array_map($this->slice('type'),$property_list)
		);
		foreach ( $document_contents as $name => $value ) {
			$setter = "set_{$name}";
			if ( method_exists($this, $setter) ) {
				if ( $execute ) $this->$setter($value);
			} else {
				$type = array_key_exists($name, $property_list) ? $property_list[$name] : NULL;
				$this->syslog(__FUNCTION__, 'FORCE', <<<EOP

function & {$setter}(\$v) { \$this->{$name}_{$type} = \$v; return \$this; }
function get_{$name}(\$v = NULL) { if (!is_null(\$v)) \$this->{$setter}(\$v); return \$this->{$name}_{$type}; }

EOP
			);
			}
		}
		return $this;
	}/*}}}*/

  function slice($s) {
    // Duplicated in RawparseUtility
    return create_function('$a', 'return $a["'.$s.'"];');
  }

	function dump_accessor_defs_to_syslog() {
    $data_items = array_flip(array_map($this->slice('name'), $this->fetch_property_list()));
    $this->recursive_dump($data_items,0,'FORCE');
    $this->set_contents_from_array($data_items,FALSE);
	}	

  function get_id() {
    return $this->in_database() ? $this->id : NULL;
  }

}
