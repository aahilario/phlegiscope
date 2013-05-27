<?php

class DatabaseUtility extends ReflectionClass {

  static $dbhandle               = NULL;
  protected static $obj_attrdefs = array(); // Lookup table of class attributes and names
  protected static $obj_ondisk   = array();

  protected $tablename           = NULL;
  protected $query_conditions    = array();
  protected $order_by_attrs      = array();
  protected $limit               = array();
  protected $query_result        = NULL;
  protected $attrlist            = NULL;
  protected $debug_operators     = FALSE;
  protected $debug_method        = FALSE;

  protected $disable_logging = array(
    '-RawparseUtility'
  );

  protected function get_attrdefs() {
    return array_key_exists(get_class($this), self::$obj_attrdefs)
      ? self::$obj_attrdefs[get_class($this)]
      : array(get_class($this) => 'No Definition')
      ;
  }
  function __construct() {/*{{{*/
    parent::__construct($this);
		$this->debug_method = FALSE;
		$this->debug_operators = FALSE;
		$this->id = NULL;
    if (1 == preg_match('@(Model|Join)$@i',get_class($this))) 
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
          $regex_replace[] = $this->$methodname();
        } else if ( property_exists($this,$propertyname) ) {
          $regex_match[] = "@{{$nv["name"]}}@imU";
          $regex_replace[] = $this->$propertyname;
        } else {
          $this->syslog(__FUNCTION__,__LINE__,"(marker) Unable to assign {$nv['name']}, no method {$methodname} or property {$propertyname}");
        }
      }
      $str = preg_replace($regex_match, $regex_replace, $str);
      return $str == FALSE ? NULL : $str;
    }/*}}}*/

  function & remove() {/*{{{*/
    if ( $this->in_database() ) {
      $sql = <<<EOS
DELETE FROM `{$this->tablename}` WHERE `id` = {$this->id}
EOS;
      $this->query($sql);
    }
    return $this;
  }/*}}}*/

  function log_stack() {
    try {
      throw new Exception('DB');
    } catch ( Exception $e ) {
      foreach ( $e->getTrace() as $st )
      syslog( LOG_INFO, " @ {$st['line']} {$st['class']}::{$st['function']}() in {$st['file']}");
    }
  }

  function fetch_property_list($include_omitted = FALSE) {/*{{{*/
    // Get list of public properties of the derived class 
    $debug_method = FALSE;
    $members = array();
    $omitted_members = array();
    $myname = get_class($this);
    if ( array_key_exists($myname, self::$obj_attrdefs) ) {
      $returnval = $include_omitted
        ? self::$obj_attrdefs[$myname]
        : array_filter(array_map(create_function('$a', 'return array_key_exists("joinobject",$a) ? NULL : $a;'), self::$obj_attrdefs[$myname]))
        ;
      if ( $debug_method ) {
        $this->syslog(__FUNCTION__, __LINE__, "(marker) - - - - Leaving, I ({$myname}) am already recorded." );
        $this->recursive_dump($returnval,"(marker) - - - -");
      }
      return $returnval;
    }
    foreach ( $this->getProperties(ReflectionProperty::IS_PUBLIC) as $p ) {
      if ( $p->getDeclaringClass()->getName() != get_class($this) ) continue;
      $defvalue = $p->getValue($this);
      if ( strlen($defvalue) > 200 ) $defvalue = substr($defvalue,0,200) . '... (' . strlen($defvalue) . ')'; 
      // $this->syslog(__FUNCTION__, '-', "(marker) - Value '{$defvalue}' type " . gettype($defvalue) );
      $nameparts = array();
      $attrname = $p->getName();
      preg_match_all('/^(.*)_(.*)$/i',$attrname,$nameparts);
      $use_nametype = 0 < strlen($nameparts[1][0]);
      $member = array(
        'name' => $use_nametype ? $nameparts[1][0] : $p->getName(),
        'type' => $use_nametype ? $nameparts[2][0] : 'pkey',
      );
      if ( $debug_method ) {
        $this->syslog(__FUNCTION__, __LINE__, "(marker) - - - - Testing attribute {$attrname} having these properties:" );
        $this->recursive_dump($member,"(marker) - - - -");
      }
      $attr_is_model = class_exists("{$member['type']}",FALSE);
      if ( $attr_is_model || (1 == preg_match('@(.*)Model$@',$member['type'])) ) {
        // Model attribute check.  Treat object references as invisible;
        // they do not correspond to a field/column in the object's backing
        // store table. 
        if ( $debug_method ) { 
          $extant = $attr_is_model ? "Including extant" : "Excluding unavailable";
          $this->syslog(__FUNCTION__, __LINE__, "(marker) {$extant} class name {$member['type']}" );
        }
        $member['propername'] = $member['type'];
        if (1 == preg_match('@(.*)Join$@i', $myname)) {
          if ( $debug_method ) {
            $this->syslog(__FUNCTION__, __LINE__, "(marker) - --- - Skipping {$member['propername']}, as i am a Join ({$myname})" );
          }
          $members[$member['name']] = $member;
          continue;
        }
        // Compute Join object name if this isn't already a Join model
        $realnames   = array();
        $realnames[$member['type']] = $member['type'];
        // Allow for self join
        if ( array_key_exists($myname, $realnames) ) {
          $realnames["_{$myname}"] = $myname;
        } else {
          $realnames[$myname] = $myname;
          ksort($realnames); // Avoid guessing whether the join class name should be ClassAClassBJoin or ClassBClassAJoin
        }

        if ( $debug_method ) { 
          $this->syslog(__FUNCTION__, __LINE__, "(marker) Object reference attribute name parts:" );
          $this->recursive_dump($realnames, "(marker) -- - -- -");
        }
        $fakename = array_map(create_function('$a', 'return preg_replace("@(Document)*Model$@i","",$a);'), $realnames);
        // The '_PLUS_' conjunction causes the class loader to generate
        // a new class definition file with the two object names used
        // to declare the join. 
        $autoloader_trigger   = join('_Plus_',$realnames) . "Join";
        $member['joinobject'] = join('',$fakename) . "Join";
        $autoload_result      = FALSE;
        $omitted_members[$member['name']] = $member;
        $autoload_result = class_exists($autoloader_trigger);
        if ( $attr_is_model ) {
          // Cause instantiation (hence auto-generation) of the join table class.
          $autoload_result |= class_exists($member['joinobject']);
        }
        if ( $debug_method ) $this->syslog(__FUNCTION__, __LINE__, "(marker) Test for {$member['joinobject']} (trigger name '{$autoloader_trigger}'): " . ($autoload_result ? "Exists" : "Does not exist") );
        continue;
      }
      $members[$member['name']] = $member;
    }
    // Keep track of all derived class attribute definitions
    // TODO:  If we run into a race condition that cannot be remedied by reordering instance declarations, roll back to SVN #418 (internal schedule)
    if ( !array_key_exists($myname,self::$obj_attrdefs) ) {
      if ( $debug_method ) $this->syslog(__FUNCTION__,__LINE__,"(marker) Inserting attrdefs {$myname}");
      self::$obj_attrdefs[$myname] = array_merge(
        $members,
        $omitted_members
      );
    }
    $returnval = $include_omitted ? self::$obj_attrdefs[$myname] : $members;
    if ( $debug_method ) $this->recursive_dump($returnval,"(marker) Finally " . get_class($this));
    return $returnval;
  }/*}}}*/

  private final function fetch_typemap(& $attrinfo, $mode = NULL) {/*{{{*/

    // Get type map information for a SINGLE model attribute.
    $debug_method = FALSE; // 1 == preg_match('@(.*)Join$@i', get_class($this)); //  FALSE; // get_class($this) == 'SenateCommitteeReportDocumentModel' ;

    $type_map = array(
      'pkey' => array('attrname' => 'pkey'              , 'quot' => FALSE),
      'utx'  => array('attrname' => 'INT(11)'           , 'quot' => FALSE),
      'ts'   => array('attrname' => 'TIMESTAMP'         , 'quot' => TRUE),
      'dtm'  => array('attrname' => 'DATETIME'          , 'quot' => TRUE),
      'int'  => array('attrname' => 'INT(s)'            , 'quot' => FALSE),
      'vc'   => array('attrname' => 'VARCHAR(s)'        , 'quot' => TRUE),
      'blob' => array('attrname' => 'MEDIUMBLOB'        , 'quot' => FALSE, 'mustbind' => TRUE, ),
      'dbl'  => array('attrname' => 'DOUBLE DEFAULT 0.0', 'quot' => FALSE),
      'flt'  => array('attrname' => 'FLOAT DEFAULT 0.0' , 'quot' => FALSE),
      'bool' => array('attrname' => 'BOOLEAN'           , 'quot' => FALSE),
    );
    $sqlmodif = array(
      'uniq'  => 'UNIQUE',
      'jointuniq' => NULL,
    );
    $fieldname     = $attrinfo['name'];
    $modifiers     = '/([A-Za-z]+)([0-9]+)?(uniq)?$/iU';
    $matches       = array();
    $match_result  = preg_match_all($modifiers, $attrinfo['type'], $matches);
    $size          = $matches[2][0];
    $modifier      = array_key_exists(3,$matches) && array_key_exists($matches[3][0],$sqlmodif) ? $sqlmodif[$matches[3][0]] : NULL;

    $attrinfo['type'] = $matches[1][0];

    if ($debug_method) {
      $this->syslog(__FUNCTION__, __LINE__, "(marker) Called with array " . count($attrinfo)  );
      $this->recursive_dump($attrinfo, "(marker)");
      // $this->log_stack();
    }

    if ($debug_method || C('DEBUG_' . strtoupper(get_class($this)))) {
      $this->syslog(__FUNCTION__, __LINE__, "(marker) Type spec '{$attrinfo['type']}'" );
      $this->recursive_dump($matches, "(marker) {$attrinfo['type']}");
    }

    $quoting    = array_key_exists($attrinfo['type'],$type_map) ? $type_map[$attrinfo['type']]['quot'] : FALSE;
    $bindparam  = array_key_exists($attrinfo['type'],$type_map) && array_key_exists('mustbind', $type_map[$attrinfo['type']]) ? $type_map[$attrinfo['type']]['mustbind'] : FALSE;
    $properties = array_key_exists($attrinfo['type'],$type_map) ? $type_map[$attrinfo['type']]['attrname'] : 'VARCHAR(64)';
    $properties = preg_replace('/\((s)\)/',"({$size})", array_element(array_element(is_array($type_map) ? $type_map : array(),array_element(array_element($matches,1,array()),0),array()),'attrname'));
    $properties = "{$properties} {$modifier}";

    $typemap = array(
      'fieldname'  => $fieldname,
      'properties' => $properties,
      'quoted'     => $quoting,
      'unique'     => !is_null($modifier),
      'size'       => empty($size) ? NULL : intval($size),
      'mustbind'   => $bindparam,
    );

    // Implement model references
		if ((1 == preg_match('@(.*)Join$@i', get_class($this))) && array_key_exists('propername',$attrinfo)) {

      $typemap['fieldname'] = $attrinfo['name'];

      $join_attrdefs = $this->get_attrdefs();
      $join_attrdefs = $join_attrdefs[$fieldname];
      if ( class_exists($join_attrdefs['type']) ) {
        $ft_propername = join('_',camelcase_to_array($join_attrdefs['propername']));
        $typemap['properties'] = <<<EOS
INT(11) NOT NULL REFERENCES `{$ft_propername}` (`id`) MATCH FULL ON UPDATE CASCADE ON DELETE RESTRICT
EOS;
        $typemap['joint_unique'] = '`' . $typemap['fieldname'] . '`'; 
      }
      if ($debug_method) {
        $this->syslog(__FUNCTION__,__LINE__,"(marker) -- - -- - --  Final typemap");
        $this->recursive_dump($typemap,'(marker) "- -- - -- -"');
      }
    } else if (array_key_exists('propername',$attrinfo)) { 
      if ( is_null($mode) ) return NULL;
      if (!(1 == preg_match('@(.*)Join$@i', get_class($this)))) return NULL;
    }

    return $typemap;
  }/*}}}*/

  private final function construct_backing_table($tablename, $members) {/*{{{*/
    $debug_method = FALSE;
    if ( $debug_method ) {
      $this->syslog( __FUNCTION__, __LINE__, "(marker) Constructing backing table '{$tablename}' for " . get_class($this) );
      $this->recursive_dump($members,"(marker) ----- ---- --- -- -  - -- --- ---- -----");
    }

    $attrset = array('`id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY'); 
    // Detect class names
    $unique_attrs = array();
    foreach ( $members as $attrs ) {
      // GRAPHEDGE_DISCARD: Discard declaration here IF this object's name
      // does not end in Join; in that case, we replace the field name and
      // properties:
      // - $fieldname: Change to *_int11
      // - $properties: INT(11) REFERENCES `{ftable}` (`id`) MATCH FULL ON UPDATE CASCADE ON DELETE RESTRICT";  
      // Also add UNIQUE INDEX ($a,$b)  
      $attrdef = $this->fetch_typemap($attrs,'CREATE');
      if ( is_null($attrdef) ) {
        continue;
      }
      if ( array_key_exists('joint_unique', $attrdef) ) {
        $unique_attrs[] = $attrdef['fieldname'];
      }
      if ( $debug_method ) {
        $this->syslog( __FUNCTION__, __LINE__, "(marker) Attribute defs '{$tablename}' for " . get_class($this) );
        $this->recursive_dump($attrdef,"(marker) ----- ---- --- -- -  - -- --- ---- -----");
      }
      extract($attrdef);
      $attrset[] = <<<EOH
`{$fieldname}` {$properties}
EOH;
    }
    if ( 0 < count($unique_attrs) ) {
      $unique_attrs = join(',', $unique_attrs);
      $attrset[] = <<<EOH
CONSTRAINT UNIQUE KEY ({$unique_attrs})
EOH;
    } else {
      if ( $debug_method ) $this->syslog( __FUNCTION__, __LINE__, "(marker) No unique attrs for " . get_class($this) );
    }
    $attrset = join(',', $attrset);
    $create_table =<<<EOH
CREATE TABLE `{$tablename}` ( {$attrset} ) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8
EOH;
    // A link table will contain at least two attributes which must be declared
    // jointly unique, with cascadable update and delete
    $create_result = $this->query($create_table)->resultset();
    if ( $debug_method ) {
      $result_type = gettype($create_result);
      if ( is_bool($create_result) ) $create_result = $create_result ? 'TRUE' : 'FALSE';
      $this->syslog(__FUNCTION__,__LINE__, "(marker) Create   stmt: {$create_table}" );
      $this->syslog(__FUNCTION__,__LINE__, "(marker) Create result: {$create_result} ({$result_type}). " . $this->error() );
    }
    return $create_result;
  }/*}}}*/

  function fetch_structure_backing_diffs($tablename, $members) {/*{{{*/
    $debug_method = FALSE;
    if ( array_key_exists($tablename, self::$obj_ondisk) ) {
      if ( $debug_method ) $this->syslog( __FUNCTION__, __LINE__, "(marker) - - - - - Assume no diffs for {$tablename}" );
      return array(
        'added_columns'   => array(),
        'removed_columns' => array(), 
      );
    }
    $is_join_table  = 1 == preg_match('@(.*)Join$@i',get_class($this));
    if ( $debug_method ) $this->syslog( __FUNCTION__, __LINE__, "(marker) - - - - - Treating {$tablename} as " . ($is_join_table ? "join" : "regular table") );
    self::$obj_ondisk[$tablename] = $members;
    $attrdefs        = $this->query("SHOW COLUMNS FROM `{$tablename}`")->resultset();
    // TODO: Allow join tables to possess member reference attributes
    // TODO: Test individual attributes, instead of depending on the 'Join' suffix
    $current_columns = $is_join_table
      ?  create_function('$a', 'return $a["name"] == "id" ? NULL : $a["name"];')
      :  create_function('$a', 'return isset($a["propername"]) ? $a["propername"] : ( $a["name"] == "id" ? NULL : $a["name"]);')
      ;
    $removed_columns = array_flip(array_filter(array_map($current_columns, $members))); // In DB, not in memory
    $added_columns   = $members; // Attributes in classdef, but not in backing store 
    $attribute_map   = array_flip($attrdefs['attrnames']);
    // $this->recursive_dump($removed_columns, '-%');
    foreach ( $attrdefs['values'] as $attrs ) {
      $attrname   = $attrs[$attribute_map['Field']];
      if ( $debug_method ) $this->syslog( __FUNCTION__, __LINE__, "(marker) A+ {$attrname} vs " . join('',camelcase_to_array($attrname)) );
      if ( $attrname == 'id' ) continue;
      $attrname   = join('',camelcase_to_array($attrname)) == $attrname ? $attrname : join('_',camelcase_to_array($attrname));
      $attrtype   = $attrs[$attribute_map['Type']];
      // Reduce list of extant columns to those that are not in the class
      $remove_extant = $is_join_table
        ?  create_function('$a', 'return ($a["name"] == "'. $attrname .'") ? NULL : $a;')
        :  create_function('$a', 'return ((array_key_exists("propername", $a) && ($a["propername"] == "'. $attrname .'")) || ($a["name"] == "'. $attrname .'")) ? NULL : $a;')
        ;
      $added_columns = array_filter(array_map($remove_extant, $added_columns));
      // Reduce list of attributes removed from the class by excluding columns that are still attributes of the on-disk table 
      $removed_columns[$attrname] = array_key_exists($attrname, $removed_columns)
        ? NULL
        : $attrname
        ;
    }
    foreach ( $added_columns as $t => $column ) {/*{{{*/
      $omit_extant = $column[array_key_exists("propername", $column) ? "propername" : "name"];
      $this->syslog(__FUNCTION__, __LINE__, "(marker) -- - -- Omitting column {$omit_extant}");
      $removed_columns[$omit_extant] = NULL;
    }/*}}}*/
    $removed_columns = array_values(array_filter($removed_columns));
    return array(
      'added_columns'   => array_values($added_columns),
      'removed_columns' => $removed_columns,
    );
  }/*}}}*/

  protected final function initialize_derived_class_state() {/*{{{*/

    $debug_method = FALSE; // get_class($this) == 'SenateCommitteeReportDocumentModel' ;

    $this->tablename = join('_', camelcase_to_array(get_class($this)));
    $this->initialize_db_handle();

    if ( C('LS_SYNCHRONIZE_MODEL_STRUCTURE') ) {

      // Determine if the backing table exists; construct it if it does not.
      $is_join_table  = 1 == preg_match('@(.*)Join$@i',get_class($this));
      $members        = $this->fetch_property_list($is_join_table);
      if ( $debug_method ) $this->recursive_dump($members,"(marker) - From fetch_property_list");
      $matched_tables = array();
      $result         = $this->query('SHOW TABLES')->resultset();

      if ( is_array($result) && array_key_exists('values',$result) ) {
        $matched_tables = array_filter(
          array_map(
            create_function('$a', 'return $a[0] == "'.$this->tablename.'" ? $a[0] : NULL;'),
            $result['values']
          )
        );
        if ( $debug_method ) { 
          $this->syslog( __FUNCTION__, __LINE__, "(warning) Matched tables" );
          $this->recursive_dump($matched_tables,"(marker) --- -- -");
        }
      }

      if ( $debug_method ) { 
        $this->syslog( __FUNCTION__, __LINE__, "(warning) Members table before construct_backing_table" );
        $this->recursive_dump($members,"(marker) Class members as found");
      }

      if ( count($matched_tables) == 0 ) {
        $this->construct_backing_table($this->tablename, $members);
      }

      if ( $debug_method ) { 
        $this->syslog( __FUNCTION__, __LINE__, "WARNING: members table AFTER construct_backing_table" );
        $this->recursive_dump($members,"(marker) Class members as found");
      }

      // Synchronize table columns and class attributes
      $structure_deltas = $this->fetch_structure_backing_diffs($this->tablename, $members);
      if ( $debug_method ) { 
        $this->syslog( __FUNCTION__, __LINE__, "WARNING: Structure differences" );
        $this->recursive_dump($structure_deltas,"(marker) ------ --- -- - ");
      }
      extract($structure_deltas);

      // Remove newly undefined columns
      // FIXME:  Ensure that join table attributes referring to models aren't nuked
      if ( $is_join_table && $debug_method ) {
        $this->syslog( __FUNCTION__, __LINE__, "WARNING: Join table columns must be excluded from the attribute lists to follow:" );
        $this->recursive_dump($removed_columns,"(marker) Apparently Removed");
        $this->recursive_dump($added_columns  ,"(marker)   Apparently Added");
      }

      if ( is_array($removed_columns) && (0 < count($removed_columns)) )
      foreach ( $removed_columns as $removed_column ) {
        $sql = <<<EOH
ALTER TABLE `{$this->tablename}` DROP COLUMN `{$removed_column}`
EOH;
        $result = $this->query($sql)->resultset();
        $result = is_bool($result) ? ($result ? 'TRUE' : 'FALSE') : '---';
        $this->syslog( __FUNCTION__, __LINE__, "(marker) Remove {$removed_column}: {$result}" );
      }
      if ( is_array($added_columns) && (0 < count($added_columns)) )
      foreach ( $added_columns as $added_column ) {
        $column_name = $added_column['name'];
        // GRAPHEDGE_DISCARD: Allow an attribute to be discarded if it describes an edge node.
        $attrdef = $this->fetch_typemap($added_column);
        if ( is_null($attrdef) ) continue;
        extract($attrdef);
        $sql = <<<EOH
ALTER TABLE `{$this->tablename}` ADD COLUMN `{$column_name}` {$properties} AFTER `id`
EOH;
        $result = $this->query($sql)->resultset();
        $result = is_bool($result) ? ($result ? 'TRUE' : 'FALSE') : '---';
        $this->syslog( __FUNCTION__, __LINE__, "(marker) Add {$column_name}: {$result}" );
      }
      if ( $debug_method ) {
        $this->syslog( __FUNCTION__, __LINE__, "(marker) End. --------------------------------- " );
      }
    }
  }/*}}}*/

  function get_joins() {/*{{{*/
    $join_attrdefs = $this->get_attrdefs();
		// $this->filter_nested_array($join_attrdefs,'propername,joinobject[joinobject*=.*|i][propername*='.$modelname.']');
		$this->filter_nested_array($join_attrdefs,'propername,joinobject[joinobject*=.*|i]');
    return $join_attrdefs;
  }/*}}}*/

	function create_joins( $modelname, & $foreign_keys, $allow_update = FALSE ) {/*{{{*/

		$debug_method = FALSE;

		$join_attrdefs = $this->get_attrdefs();
		$this->filter_nested_array($join_attrdefs,'propername,joinobject[joinobject*=.*|i][propername*='.$modelname.']');

    if ( !$this->in_database() ) {
			$this->syslog( __FUNCTION__, __LINE__, "(marker) --- --- --- - - - --- --- --- WARNING: Nothing to join. ID = (" . (gettype($this->get_id())) . ")" . (0 < intval($this->get_id()) ? $this->get_id() : "")); 
			$this->recursive_dump($join_attrdefs, "(marker) - -- ---");
    } else if (!is_array($join_attrdefs)) {
			$this->syslog( __FUNCTION__, __LINE__, "(marker) --- --- --- - - - --- --- --- WARNING: No match among keys"); 
			$this->recursive_dump($join_attrdefs, "(marker) - -- ---");
    } else if (1 == count($join_attrdefs)) {
			$self_id = $this->get_id();
			foreach( $join_attrdefs as $attrname => $attprops ) {

				$joinobj = $attprops['joinobject'];
				$joinobj = new $joinobj();
				$joinobj_attrdefs = $joinobj->get_attrdefs();

				$self_attrname = $joinobj_attrdefs;
				$this->filter_nested_array($self_attrname,'name[type='.get_class($this).']',0);
				$self_attrname = $self_attrname[0];

				$foreign_attrname = $joinobj_attrdefs;
				$this->filter_nested_array($foreign_attrname,'name[type='.$modelname.']',0);
				$foreign_attrname = $foreign_attrname[0];

				if ( $debug_method ) {/*{{{*/
					$this->syslog( __FUNCTION__, __LINE__, "(marker) Attribute '{$attrname}' for Join {$modelname}");
					$this->recursive_dump($foreign_keys, "(marker) --- -- -");
					$this->syslog( __FUNCTION__, __LINE__, "(marker) Attribute definitions " . gettype($join_attrdefs));
					$this->recursive_dump($attprops, "(marker) - -- ---");
					$this->syslog( __FUNCTION__, __LINE__, "(marker) JOIN attributes");
					$this->recursive_dump($joinobj_attrdefs, "(marker) - -- ---");
					$this->syslog( __FUNCTION__, __LINE__, "(marker) JOIN attrname for self: " . $self_attrname);
					$this->syslog( __FUNCTION__, __LINE__, "(marker) JOIN attrname for {$modelname}: " . $foreign_attrname);
				}/*}}}*/

				// TODO: Handle larger arrays of more than a handful of foreign Joins 
				// TODO: Allow assignment of Join object properties in $foreignkey
				foreach ( $foreign_keys as $fk_or_dummy => $foreignkey ) {
					$data =  array(
						$self_attrname => $self_id,
						$foreign_attrname => $foreignkey
					);
					$joinobj->fetch($data,'AND');
					$join_present = $joinobj->in_database();
					if ( !$join_present || $allow_update ) {
						$join_id      = $join_present ? $joinobj->get_id() : NULL;
						$joinobj->set_contents_from_array($data);	
						$new_joinid = $joinobj->stow();
						$join_exists  = $join_present ? ("#{$join_id} in DB updated") : "created as #{$new_joinid}";
						$this->syslog( __FUNCTION__, __LINE__, "(marker) - -- JOIN ". get_class($joinobj) ." {$join_exists} to {$modelname}" );
					}
				}

				$joinobj = NULL;
			}
		} else {
			$this->syslog( __FUNCTION__, __LINE__, "(marker) --- --- --- - - - --- --- --- ERROR: Multiple matches for {$modelname}. Available attributes are:");
			$this->recursive_dump($join_attrdefs, "(marker) - -- ---");
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
      // GRAPHEDGE_DISCARD: Simply discard model to model edges 
      $attrdef = $this->fetch_typemap($attrs);
      if ( is_null($attrdef) ) continue;
      $attrlist[$member_index]['attrs'] = $attrdef;
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
    if ( $this->debug_method ) $this->recursive_dump($this->query_conditions,'CSFC');
    foreach ( is_null($condition_branch) ? $this->query_conditions : $condition_branch as $conj_or_attr => $operands ) {

      if ( array_key_exists(strtoupper($conj_or_attr), array_flip(array('AND', 'OR')))) {
        if ( $this->debug_method ) $this->syslog(__FUNCTION__,__LINE__, "A conj '{$conj_or_attr}'");
        $fragment = $this->construct_sql_from_conditiontree($attrlist, $operands);
				if ( FALSE === $fragment ) {
					$this->syslog(__FUNCTION__,__LINE__, "(marker) Invalid condition parameters, SQL statement could not be constructed.");
					return FALSE;
				}
        $querystring[] = join(" {$conj_or_attr} ", $fragment);
        continue;
      }
      if ( !is_array($operands) ) {
        $operand_regex = '@^(LIKE|REGEXP)(.*)@';
        $operand_match = array();
        $opmatch       = preg_match($operand_regex, $operands, $operand_match);
        $operator      = '=';
				$value         = NULL;
        if ( 1 == $opmatch ) {
          // FIXME: This is absolutely NOT secure, inputs need to be filtered against SQL injection.
          // $this->recursive_dump($operand_match, __LINE__);
          $operator = trim($operand_match[1]);
          $value    = trim($operand_match[2]);
          if ( $this->debug_method ) $this->syslog(__FUNCTION__,__LINE__, 'B');
        } else {
					if ( array_key_exists($conj_or_attr, $attrlist) ) 
          $value = $attrlist[$conj_or_attr]['attrs']['quoted'] ? "'{$operands}'" : "{$operands}";
          if ( $this->debug_method ) $this->syslog(__FUNCTION__,__LINE__, "(marker) C {$conj_or_attr} {$operands} -> {$value}");
        }
				if ( !is_null($value) ) $querystring[] = "`{$conj_or_attr}` {$operator} {$value}"; 
      } else {
        // $this->recursive_dump($operands,__FUNCTION__);
        $value_wrap   = create_function('$a', $attrlist[$conj_or_attr]['attrs']['quoted'] ? 'return "'."'".'" . $a . "'."'".'";' : 'return $a;');
				$value        = array_map($value_wrap, $operands);
				if ( 0 < count($value) ) {
					$value        = join(',',$value);
					$querystring[] = "`{$conj_or_attr}` IN ({$value})"; 
					if ( $this->debug_method ) $this->syslog(__FUNCTION__,__LINE__, 'D');
				} else return FALSE;
      }
    }
    return $querystring;
  }/*}}}*/

  function update() {/*{{{*/
    //  $a["attrs"]["unique"] ? NULL : 
    //  $a["attrs"]["unique"] ? NULL : 
    $f_attrval   = create_function('$a', 'return $a["attrs"]["mustbind"] == TRUE ? "`{$a["name"]}` = ?": "`{$a["name"]}` = {$a["value"]}";');
    $f_bindattrs = create_function('$a', 'return $a["attrs"]["mustbind"] == TRUE ? "{$a["name"]}" : NULL;');
    $attrlist    = $this->full_property_list();
    $boundattrs  = array_filter(array_map($f_bindattrs,  $attrlist));
    if (0) {
      $this->syslog(__FUNCTION__, __LINE__, "(marker) Will update record #{$this->id}: {$sql}" );
      $this->recursive_dump($attrlist,'(marker) Exclude UNIQUE attrs');
    }
    $attrval   = join(',', array_map($f_attrval, $attrlist));
    $sql = <<<EOS
UPDATE `{$this->tablename}` SET {$attrval} WHERE `id` = {$this->id}
EOS;
    $this->insert_update_common($sql, $boundattrs, $attrlist);
    if ( !empty(self::$dbhandle->error) ) {
      $this->syslog( __FUNCTION__, __LINE__, "ERROR: " . self::$dbhandle->error ); // throw new Exception("Failed to execute SQL: {$sql}");
      $this->syslog(__FUNCTION__, __LINE__, "Record #{$this->id}: {$sql}" );
    } else
      $this->syslog(__FUNCTION__, __LINE__, "Updated record #{$this->id}: {$sql}" );
    return $this->id;
  }/*}}}*/

  function count(array $a = array()) {/*{{{*/
    if ( empty($a) ) 
    $sql = <<<EOS
SELECT COUNT(*) n FROM `{$this->tablename}`
EOS;
    else {
      $sql = '';
			if ( FALSE == $this->where($a)->prepare_select_sql($sql) ) {
				$this->syslog(__FUNCTION__,__LINE__,"(marker) Unable to build an SQL statement given current parameters.");
				return NULL;
			}
      $sql = preg_replace('@^SELECT (.*) FROM @','SELECT COUNT(*) n FROM ', $sql);
      $this->syslog(__FUNCTION__, __LINE__, "(marker) SQL - {$sql}");
    }
    $result = $this->query($sql)->resultset();
    $result = array_combine($result['attrnames'], $result['values'][0]);
    // $this->recursive_dump($result,__LINE__);
    return array_key_exists('n',$result) ? intval($result['n']) : NULL;
  }/*}}}*/

  protected function prepare_select_sql(& $sql, $exclude_blobfields = FALSE) {/*{{{*/
    // Fill in the SQL string, and return attribute list
    // TODO: Exclude*BLOB attributes from the statement generated with this method. 
    $debug_method = FALSE;
    $this->initialize_db_handle();
    $key_by_varname = create_function('$a', 'return $a["name"];');
    $attrlist = $this->full_property_list();
    $key_map  = array_map($key_by_varname, $attrlist);
    $conditionstring = NULL;
    if ( $debug_method ) {
      $this->syslog(__FUNCTION__,__LINE__, "--- Property list");
      $this->recursive_dump( $key_map, __LINE__);
    }
    if ( 0 < count($attrlist) && count($attrlist) == count($key_map) ) {
      $bindable_attrs = ( $exclude_blobfields )
        ? create_function('$a', 'return $a["attrs"]["mustbind"] ? NULL : "`" . $a["name"] . "`";')
        : create_function('$a', 'return "`{$a["name"]}`";')
        ;
      $attrnames = array_filter(array_map($bindable_attrs, $attrlist));
      $attrnames[] = '`id`'; // Mandatory to include this
      // DEBUG
      // $this->recursive_dump( $attrnames, __LINE__);
      $attrnames = join(',',$attrnames);
      $attrlist = array_combine($key_map, $attrlist);
      $conditionstring = $this->construct_sql_from_conditiontree($attrlist);
			if ( FALSE == $conditionstring ) return FALSE;
      $order_by = array();
      if ( 0 < count($this->order_by_attrs) ) {
        foreach ( $this->order_by_attrs as $a => $b ) {
          if ( is_integer($a) ) {
            $order_by[] = $b;
          } else {
            $order_by[] = "{$a} {$b}";
          }
        }
      }
      $order_by = ( 0 < count($order_by) )
        ? "ORDER BY " . join(',', $order_by)
        : NULL
        ;
      $limit_by = NULL;
      if ( 0 < count($this->limit) ) {
        $this->limit['n'] = intval($this->limit['n']);
        $this->limit['o'] = intval($this->limit['o']);
        if ( 0 < $this->limit['n'] ) {
          $limit_by = "LIMIT {$this->limit['o']}, {$this->limit['n']}";
        } else {
          $this->limit = array();
        }
      }
      // DEBUG
      // $this->recursive_dump( $conditionstring, __LINE__);
      $conditionstring = join(' ',$conditionstring);
      $sql = <<<EOS
SELECT {$attrnames} FROM `{$this->tablename}` WHERE {$conditionstring} {$order_by} {$limit_by}
EOS;
      // DEBUG
    }
    if ( $debug_method ) {
      $this->syslog( __FUNCTION__, __LINE__, "(marker) Query: {$sql}");
      $this->syslog( __FUNCTION__, __LINE__, "(marker) Condition: {$conditionstring}");
    }
    return $attrlist;
  }/*}}}*/

  function result_to_model(array $result) {/*{{{*/
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
      $this->syslog(__FUNCTION__,__LINE__,'- model load failure ' . join('|', $result));
    }
  }/*}}}*/

  function select() {/*{{{*/
		$this->id = NULL;
    $sql = '';
    if ( FALSE == ($this->attrlist = $this->prepare_select_sql($sql)) ) return FALSE;
    $result = $this->query($sql)->resultset();
    /*
    $this->recursive_dump($result,'R');
    $this->recursive_dump($this->query_conditions,'C');
    //$this->syslog(__FUNCTION__,__LINE__,">>>>>>>>>>>>>>>>>>>>>>>" );
    */
    $this->query_result = NULL;
    if ( is_array($result) ) {
      //$this->recursive_dump($result,__LINE__);
       $this->result_to_model($result);
    }
    //$this->syslog(__FUNCTION__,__LINE__,"<<<<<<<<<<<<<<<<<<<<<<<" );
    return $this->query_result;
  }/*}}}*/

  function & recordfetch_setup() {/*{{{*/
    $sql = '';
    if ( FALSE == ($this->attrlist = $this->prepare_select_sql($sql)) ) return FALSE;
    return $this->query($sql);
  }/*}}}*/

  function recordfetch(& $single_record, $update_model = FALSE) {/*{{{*/
    $single_record = array();
    $a = self::$dbhandle->recordfetch($single_record);
    if ( $a == TRUE ) {
      if ( $update_model && is_array($single_record) ) {
        //$this->syslog(__FUNCTION__,__LINE__,">>>>>>>>>>>>>>>>>>>>>>>" );
        //$this->recursive_dump($single_record,__LINE__);
        $this->result_to_model(array(
          'attrnames' => $single_record['attrnames'],
          'values' => array($single_record['values']),
        ));
        //$this->syslog(__FUNCTION__,__LINE__,"<<<<<<<<<<<<<<<<<<<<<<<" );
      }
      $single_record = array_combine($single_record['attrnames'], $single_record['values']); 
    }
    return $a;
  }/*}}}*/

  function fetch($attrval, $attrname = NULL) {/*{{{*/
    $this->id = NULL;
    if ( is_null($attrname) ) {
      $this->where(array('id' => $attrval))->select();
    } else {
      $this->where(array($attrname => $attrval))->select();
    }
    if ( $this->debug_method ) {
      $this->syslog( __FUNCTION__, __LINE__, "(marker) - - - - - - Fetch: ({$attrname}, {$attrval}) -> ID obtained is ({$this->id})" );
      $this->recursive_dump($this->query_result, "(marker)" );
    }
    return $this->query_result;
  }/*}}}*/

  function in_database() {/*{{{*/
    // If an instance of this class has an ID, then it is assumed that
    // the record exists.
    // TODO: Handle record locking
    return property_exists($this,'id') ? intval($this->id) > 0 : FALSE;
  }/*}}}*/

  function stow() {/*{{{*/
    return is_null($this->id)
      ? $this->insert()
      : $this->update()
      ;
  }/*}}}*/

  private function insert_update_common($sql, $boundattrs, $attrlist) {/*{{{*/
    $debug_method = FALSE;
    if ( empty($sql) ) throw new Exception(get_class($this));
    if ( 0 < count($boundattrs) ) {
      if ( $debug_method ) $this->syslog(__FUNCTION__,__LINE__, "--- (marker) Currently: {$sql}");
      $bindable_attrs = array();
      foreach ( $boundattrs as $b ) {
        // TODO: Allow string, integer, and double values
        $bindable_attrs[$b] = array(
          'bindflag' => 'b',
          'data'     => $attrlist[$b]['value'], 
        );
        if ( is_null($bindable_attrs[$b]['data']) ) {
          if ( $debug_method ) $this->syslog(__FUNCTION__,__LINE__, "--- WARNING: Data source not provided for bindable parameter '{$b}'. Coercing to empty string ''");
          // $this->recursive_dump($boundattrs,__LINE__);
          $bindable_attrs[$b]['data'] = '';
        }
      }
      // $this->recursive_dump($bindable_attrs,__LINE__);
      return $this->query($sql, $bindable_attrs);
    }
    return  $this->query($sql);
  }/*}}}*/

  function insert() {/*{{{*/
    $f_attrnames = create_function('$a', 'return "`{$a["name"]}`";');
    $f_valueset  = create_function('$a', 'return $a["attrs"]["mustbind"] == TRUE ? "?" : "{$a["value"]}";');
    $f_bindattrs = create_function('$a', 'return $a["attrs"]["mustbind"] == TRUE ? "{$a["name"]}" : NULL;');
    $attrlist    = $this->full_property_list();
    // DEBUG
    // $this->syslog(__FUNCTION__,__LINE__, "--- Property list");
    // $this->recursive_dump($attrlist,__LINE__);
    // DEBUG
    // $this->recursive_dump( $key_map, __LINE__);
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

  function & order(array $attrs) {/*{{{*/
    // Accept either of these forms:
    // array(<attr> => "[<direction ASC | DESC>]", ...)
    // array("<attr> [ ASC | DESC ]")
    $this->order_by_attrs = $attrs;
    return $this;
  }/*}}}*/

  function & limit(int $n, int $offset = NULL) {/*{{{*/
    $this->limit = array(
      'n' => $n,
      'o' => $offset
    );
    return $this;
  }/*}}}*/

  function & where($conditions) {/*{{{*/
    if ( is_array($conditions) ) 
      $this->query_conditions = $conditions;
    return $this;
  }/*}}}*/

  function resultset() {/*{{{*/
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

  final protected function logging_ok($prefix) {/*{{{*/
    if ( 1 == preg_match('@(WARNING|ERROR|MARKER)@i', $prefix) ) return TRUE;
    if ( TRUE === C('DEBUG_ALL') ) return TRUE;
    if ( 1 == preg_match('@([(]SKIP[)])@i', $prefix) ) return FALSE;
    if ( FALSE === C('DEBUG_'.get_class($this)) ) return FALSE;
    return ( (0 == count($this->disable_logging)) ||
      !array_key_exists(get_class($this), array_flip($this->disable_logging))
    );
  }/*}}}*/

  final protected function syslog($fxn, $line, $message) {/*{{{*/
    if ( $this->logging_ok($message) ) { 
      syslog( LOG_INFO, $this->syslog_preamble($fxn, $line) . " {$message}" );
      if ( !(FALSE === C('SLOW_DOWN_RECURSIVE_DUMP')) ) usleep(C('SLOW_DOWN_RECURSIVE_DUMP'));
    }
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

  final protected function recursive_dump($a, $prefix = NULL) {/*{{{*/
    if ( !is_array($a) ) return;
    if ( !$this->logging_ok($prefix) ) return;
    $this->recursive_dump_worker($a, 0, $prefix);
  }/*}}}*/

  final private function recursive_dump_worker($a, $depth = 0, $prefix = NULL) {/*{{{*/
    if ( !(FALSE === C('SLOW_DOWN_RECURSIVE_DUMP')) ) usleep(C('SLOW_DOWN_RECURSIVE_DUMP'));
    foreach ( $a as $key => $val ) {
      $logstring = is_null($prefix) 
        ? basename(__FILE__) . "::" . __LINE__ . "::" . __FUNCTION__ . ": "
        : get_class($this) . " :{$prefix}: "
        ;
      $logstring .= str_pad(' ', $depth * 3, " ", STR_PAD_LEFT) . '('.gettype($val).')' . " {$key} => " ;
      if ( is_array($val) ) {
        syslog( LOG_INFO, $logstring );
        $this->recursive_dump_worker($val, $depth + 1, $prefix);
      }
      else {
        if ( is_null($val) ) 
          $logstring .= 'NULL';
        else if ( is_bool($val) )
          $logstring .= ($val ? 'TRUE' : 'FALSE');
        else if ( empty($val) )
          $logstring .= '[EMPTY]';
        else
          $logstring .= substr("{$val}",0,500) . (strlen("{$val}") > 500 ? '...' : '');
        syslog( LOG_INFO, $logstring );
      }
    }
  }/*}}}*/

  function & set_contents_from_array($document_contents, $execute = TRUE) {/*{{{*/
    $debug_method = TRUE;
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
        $marker = $debug_method ? 'marker' : '-----';
        $this->syslog(__FUNCTION__, __LINE__, <<<EOP
({$marker})
function & {$setter}(\$v) { \$this->{$name}_{$type} = \$v; return \$this; }
function get_{$name}(\$v = NULL) { if (!is_null(\$v)) \$this->{$setter}(\$v); return \$this->{$name}_{$type}; }

EOP
        );
      }
    }
    return $this;
  }/*}}}*/

  function slice($s) {/*{{{*/
    // Duplicated in RawparseUtility
    return create_function('$a', 'return $a["'.$s.'"];');
  }/*}}}*/

  function dump_accessor_defs_to_syslog() {/*{{{*/
		$debug_method = FALSE;
    $data_items = array_flip(array_map($this->slice('name'), $this->fetch_property_list()));
    if ( $debug_method ) $this->recursive_dump($data_items,'(marker) ' . __METHOD__);
    $this->set_contents_from_array($data_items,FALSE);
  }/*}}}*/

  function get_id() {/*{{{*/
    return $this->in_database() ? $this->id : NULL;
  }/*}}}*/

  function get_map_functions($docpath, $d = 0) {/*{{{*/
    // A mutable alternative to XPath
    // Extract content from parse containers
    $map_functions = array();
    // Disallow recursion beyond the depth we can possibly use. parse_html() returns a shallow nested array
    if ( $d > 4 ) return $map_functions;
    // A hash for the tag selector is interpreted to mean "return siblings of an element which match the selector"
    $selector_regex = '@({([^}]*)}|\[([^]]*)\]|(([-_0-9a-z=#]*)*)[,]*)@';
    // Pattern yields the selectors in match component #3,
    // and the subject item description in component #2.
    $matches = array();
    preg_match_all($selector_regex, $docpath, $matches);

    array_walk($matches,create_function('& $a, $k','$a = is_array($a) ? array_filter($a) : NULL; if (empty($a)) $a = "*";'));

    $subjects   = $matches[2]; // 
    $selectors  = $matches[3]; // Key-value match pairs (A=B, match exactly; A*=B regex match)
    $returnable = $matches[4]; // Return this key from all containers

    $conditions = array(); // Concatenate elements of this array to form the array_map condition

    foreach ( $selectors as $condition ) {

      if ( $this->debug_operators ) $this->syslog(__FUNCTION__,__LINE__,">>> Decomposing '{$condition}'");

      if ( !(1 == preg_match('@([^*=]*)(\*=|=)*(.*)@', $condition, $p)) ) {
        // A condition must take the form of an equality test.
        // The test itself is implemented as either a simple comparison or a regex match.
        $this->syslog(__FUNCTION__,__LINE__,"--- WARNING: Unparseable condition. Terminating recursion.");
        return array();
      }
      $attr = $p[1];
      $conn = $p[2]; // *= for regex match; = for equality
      $val  = $p[3];

      if ($this->debug_operators) {/*{{{*/
        $this->recursive_dump($p,"(marker) Selector components" );
      }/*}}}*/

      $attparts = '';
      if ( !empty($attr) ) {
        // Specify a match on nested arrays using [kd1:kd2] to match against
        // $a[kd1][kd2] 
        foreach ( explode(':', $attr) as $sa ) {
          $conditions[] = 'array_key_exists("'.$sa.'", $a'.$attparts.')';
          $attparts .= "['{$sa}']";
        }
      }

      if ( empty($val) ) {
        // There is only an attribute to check for.  Include source element if the attribute exists 
        if (!is_array($returnable)) $returnable = '$a["'.$attr.'"]';
      } else if ( $conn == '=' ) {
        // Allow condition '*' to stand for any matchable value; 
        // if an asterisk is specified, then the match for a specific
        // value is omitted, so only existence of the key is required.
        if ( $val != '*' ) $conditions[] = '$a["'.$attr.'"] == "'.$val.'"';
      } else if ($conn == '*=') {
        $split_val = explode('|', $val);
        $regex_modifier = NULL;
        if ( 1 < count($split_val) ) {
          $regex_modifier = $split_val[count($split_val)-1];
          array_pop($split_val);
          $val = join('|',$split_val);
        }
        $conditions[] = '1 == preg_match("@('.$val.')@'.$regex_modifier.'",$a'.$attparts.')';
        if ( $returnable == '*' ) $returnable = '$a["'.$attr.'"]';
      } else {
        $this->syslog(__FUNCTION__,__LINE__,"Unrecognized comparison operator '{$conn}'");
      }
    }

    if ( is_array($returnable) ) {
      if ( 1 == count($returnable) ) {
        // If the returnable is specified as '#', then all siblings of the matching element(s) are returned.
        $returnable_map   = create_function('$a', '$m = array(); return !(1 == preg_match("@^([#])(.*)@i", $a, $m)) ? "\$a[\"{$a}\"]" : ( 0 < strlen($m[2]) ? "\$a[\"$m[2]\"]" : "\$a" ) ;');
        $returnable_match = join(',',array_map($returnable_map, $returnable));
      } else {
        $returnable_map   = create_function('$a', 'return "\"{$a}\" => \$a[\"{$a}\"]";');
        $returnable_match = is_array($returnable) 
          ? ('array(' . join(',',array_map($returnable_map, $returnable)) .')') 
          : $returnable;
      }
    } else {
      if ( $returnable == '*' ) {
        // If the returnable attribute is given as '*', return the entire array value.
        // $this->syslog(__FUNCTION__,__LINE__,"--- WARNING: Map function will be unusable, no return value (currently '{$returnable}') in map function.  Bailing out");
        $returnable_match = '$a';
      } else {
        $returnable_match = $returnable;
      }
    }
    $map_condition   = 'return ' . join(' && ', $conditions) . ' ? ' . $returnable_match . ' : NULL;';
    $map_functions[] = $map_condition;

    if ($this->debug_operators) {/*{{{*/
      $this->syslog(__FUNCTION__,__LINE__,"- (marker) Extracting from '{$docpath}'");
      $this->recursive_dump($matches,"(marker) matches");
      $this->syslog(__FUNCTION__,__LINE__,"- (marker) Map function derived at depth {$d}: {$map_condition}");
      $this->recursive_dump($conditions,"(marker) conditions");
    }/*}}}*/

    if ( is_array($subjects) && 0 < count($subjects) ) {
      foreach ( $subjects as $subpath ) {
        if ($this->debug_operators) {/*{{{*/
          $this->syslog(__FUNCTION__,__LINE__,"(marker) - Passing sub-path at depth {$d}: {$subpath}");
        }/*}}}*/
        $submap = $this->get_map_functions($subpath, $d+1);
        if ( is_array($submap) ) $map_functions = array_merge($map_functions, $submap);
      }
    }

    return $map_functions;
  }/*}}}*/

  function & reorder_with_sequence_tags(& $c) {/*{{{*/
    // Reorder containers by stream context sequence number
    // If child tags in a container possess a 'seq' ordinal value key (stream/HTML rendering context sequence number),
    // then these children are reordered using that ordinal value.
    if ( is_array($c) ) {
      if ( array_key_exists('children', $c) ) return $this->reorder_with_sequence_tags($c['children']);
      $sequence_num = create_function('$a', 'return is_array($a) ? array_element($a,"seq",array_element(array_element($a,"attrs",array()),"seq")) : NULL;');
      $filter_src   = create_function('$a', '$rv = is_array($a) && array_key_exists("seq",$a)  ? $a : (is_array($a) && is_array(array_element($a,"attrs")) && array_key_exists("seq",$a["attrs"]) ? $a : NULL); if (!is_null($rv)) { unset($rv["attrs"]["seq"]); unset($rv["seq"]); }; return $rv;');
      $containers   = array_filter(array_map($sequence_num, $c));
      if ( is_array($containers) && (0 < count($containers))) {
				$filtered = array_map($filter_src, $c);
				if ( is_array($filtered) && count($containers) == count($filtered) ) {
					$containers = array_combine(
						$containers,
						$filtered
					);
					if ( is_array($containers) ) {
						$containers = array_filter($containers);
						ksort($containers);
						$c = $containers;
					}
				}
      } else {
        
      }
    }
    return $this;
  }/*}}}*/

  function resequence_children(& $containers) {/*{{{*/
    return array_walk(
      $containers,
      create_function('& $a, $k, & $s', '$s->reorder_with_sequence_tags($a);'),
      $this
    );
  }/*}}}*/

  function filter_nested_array(& $a, $docpath, $reduce_to_element = FALSE) {/*{{{*/
    $filter_map = $this->get_map_functions($docpath);
    if ( $this->debug_operators ) {/*{{{*/
      $this->syslog(__FUNCTION__,__LINE__,"------ (marker) Containers to process: " . count($this->containers));
      $this->recursive_dump($filter_map,'(marker)');
    }/*}}}*/
    foreach ( $filter_map as $i => $map ) {
      if ( $i == 0 ) {
        if ( $this->debug_operators ) {/*{{{*/
          $n = count($a);
          $this->syslog(__FUNCTION__,__LINE__,"A ------ (marker) N = {$n} Map: {$map}");
        }/*}}}*/
        $a = array_filter(array_map(create_function('$a',$map), $a));
        if ( $this->debug_operators ) {/*{{{*/
          $n = count($a);
          $this->syslog(__FUNCTION__,__LINE__,"A <<<<<< (marker) N = {$n} Map: {$map}");
        }/*}}}*/
        $this->resequence_children($a);
      } else {
        if ( $this->debug_operators ) {/*{{{*/
          $this->syslog(__FUNCTION__,__LINE__,"B ------ (marker) N = {$n} Map: {$a}");
        }/*}}}*/
        foreach ( $a as $seq => $m ) {
          $a[$seq] = array_filter(array_map(create_function('$a',$map), $m));
        }
      }
      if ( $this->debug_operators ) {/*{{{*/
        $this->syslog(__FUNCTION__,__LINE__,"(marker) - Map #{$i} - {$map}");
        $this->recursive_dump($a,'(marker)');
      }/*}}}*/
    }
    if ( is_numeric($reduce_to_element) ) {
      $a = is_array($a) ? array_values($a) : array(NULL);
			return array_element($a,intval($reduce_to_element));
    } else if ( FALSE === $reduce_to_element ) { 
      return $a;
    } else if ( is_null($reduce_to_element) ) {
      $a = is_array($a) ? array_values($a) : array(NULL);
      return $a[0];
    }
    return $a;
    
  }/*}}}*/

}
