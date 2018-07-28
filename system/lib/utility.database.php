<?php

class DatabaseUtility extends ReflectionClass {

  // TODO:  Permit/require derived classes to maintain own copy of the database handle
  static $dbhandle               = NULL;
  // TODO:  Implement reference counter?
  protected static $obj_attrdefs = array(); // Lookup table of class attributes and names. 
  protected static $obj_ondisk   = array();
  static $force_log = TRUE;

  protected $tablename             = NULL;
  protected $query_conditions      = array();
  protected $order_by_attrs        = array();
  protected $join_props            = array();
  protected $join_attrs            = array();
  protected $join_instance_cache   = array();
  protected $join_value_cache      = array(); // Keyed with an object's name for a Join attribute
  protected $join_value_fetched    = array(); // As retrieved from DB
  protected $alias_map             = array();
  protected $subject_fields        = array(); // Restrict UPDATE, SELECT, etc. to just these attributes.
  protected $null_instance         = NULL;
  protected $limit                 = array();
  protected $query_result          = NULL;
  protected $attrlist              = NULL;
  protected $debug_operators       = FALSE;
  protected $debug_method          = FALSE;
  protected $suppress_reinitialize = FALSE; // One-shot flag: Causes calls to init_query_prereqs() and clear_query_prereqs() to be suppressed
	protected $merge_query_conditions = FALSE;
  var $debug_final_sql             = FALSE;

  protected $disable_logging = array(
    '-RawparseUtility'
  );

  protected function init_query_prereqs() {
    $this->join_value_cache = array();
    $this->join_props       = array();
    $this->join_attrs       = array();
    $this->order_by_attrs   = array();
    $this->limit            = array();
    $this->query_conditions = array();
    $this->subject_fields   = array();
  }

  protected function clear_query_prereqs() {
    $this->join_value_cache = NULL;
    $this->join_props       = NULL;
    $this->join_attrs       = NULL;
    $this->order_by_attrs   = NULL;
    $this->limit            = NULL;
    $this->query_conditions = NULL;
    $this->subject_fields   = NULL;
    gc_collect_cycles();
  }

  function __construct() {/*{{{*/
    parent::__construct($this);
    $this->debug_method = FALSE;
    $this->debug_operators = FALSE;
    $this->id = NULL;
    if (1 == preg_match('@(Model|Join)$@i',get_class($this))) { 
      $this->initialize_derived_class_state();
      if (1 == preg_match('@(Model)$@i',get_class($this))) $this->register_derived_class();
    }
  }/*}}}*/

  function __destruct() {/*{{{*/

    if ( FALSE && $this->debug_method ) {
      $this->syslog(__FUNCTION__,__LINE__,"(warning) Objects on disk array: ");
      $this->recursive_dump(self::$obj_ondisk);
      $this->syslog(__FUNCTION__,__LINE__,"(warning) Object attrdefs: ");
      $this->recursive_dump(self::$obj_attrdefs);
    }

    if ( is_array($this->join_instance_cache) ) {
      while ( 0 < count($this->join_instance_cache) ) {
        $e = array_pop($this->join_instance_cache);
        if ( array_key_exists('join', $e) && array_key_exists('obj', $e['join']) ) {
          unset($e['join']['obj']);
        }
        if ( array_key_exists('ref', $e) && array_key_exists('obj', $e['ref']) ) {
          unset($e['ref']['obj']);
        }
        unset($this->join_instance_cache[$k]);
      }
    }
    unset($this->tablename          );
    unset($this->query_conditions   );
    unset($this->order_by_attrs     );
    unset($this->join_props         );
    unset($this->join_attrs         );
    unset($this->join_instance_cache);
    unset($this->join_value_cache   );
    unset($this->join_value_fetched );
    unset($this->null_instance      );
    unset($this->limit              );
    unset($this->query_result       );
    unset($this->attrlist           );
  }/*}}}*/

  private final function initialize_db_handle() {/*{{{*/
    if ( is_null(self::$dbhandle) ) {
      $plugin_classname = C('DBTYPE') . 'DatabasePlugin'; 
      self::$dbhandle = new $plugin_classname(DBHOST, DBUSER, DBPASS, DBNAME); 
    }
    $this->query_result = NULL;
  }/*}}}*/

  function substitute($str) {/*{{{*/
    // Replace contents of string with this object's fields
    // {property:joinproperty} to refer to ModelJoin properties, and
    // {property.objproperty}  to refer to foreign table properties.
    $debug_method = C('DEBUG_'.__FUNCTION__,FALSE);
    $regex_match = array();
    $regex_replace = array();
    foreach ( $this->fetch_property_list() as $nv ) {
      $methodname = "get_{$nv['name']}";
      $propertyname = "{$nv['name']}_{$nv['type']}";
      if ( method_exists($this, $methodname) ) {
        // Privilege get_* accessors
        $regex_match[] = "@{{$nv["name"]}}@imU";
        $regex_replace[] = $this->$methodname();
      } else if ( property_exists($this,$propertyname) ) {
        $regex_match[] = "@({{$nv["name"]}})@imU";
        $regex_replace[] = $this->$propertyname;
      } else {
        $this->syslog(__FUNCTION__,__LINE__,"(marker) Unable to assign {$nv['name']}, no method {$methodname} or property {$propertyname}");
      }
    }
    // Find join attributes.
    // {property:joinproperty} to refer to ModelJoin properties, and
    // {property.objproperty}  to refer to foreign table properties.
    $compound_attr_regex = '@{([^:.}{]*)(\.|:)([^}]*)}@imU';
    $matches = array();
    $result = preg_match_all($compound_attr_regex, $str, $matches, PREG_SET_ORDER);
    foreach ( $matches as $component ) {/*{{{*/
      $joinattr = nonempty_array_element($component,1);
      $getter   = "get_{$joinattr}";
      if ( !method_exists($this, $getter) ) continue;
      $property = nonempty_array_element($component,3);
      if ( is_null($property) ) continue;
      $referent = str_replace(array(':','.'),array('join','data'), nonempty_array_element($component,2,''));
      $value    = nonempty_array_element(nonempty_array_element($this->$getter(),$referent,array()),$property);
      if ( $debug_method ) $this->syslog(__FUNCTION__,__LINE__,"(marker) -- - -- {$joinattr} {$referent} {$property} = {$value}");
      $regex_match[] = "@(" . nonempty_array_element($component,0) . ")@imU";
      // Strip HTML tag delimiters
      if ( !property_exists($this,"permit_html_tag_delimiters") || ($this->permit_html_tag_delimiters == FALSE) ) {
        $regex_replace[] = preg_replace('@[><^]@i','?',$value);
      }
      else {
        $regex_replace[] = $value;
      }
      

    }/*}}}*/
    if ( property_exists($this,"permit_html_tag_delimiters") ) $this->permit_html_tag_delimiters = FALSE;
    if ( $debug_method ) {/*{{{*/
      $this->recursive_dump($matches, "(marker) " . __METHOD__ . " (" . gettype($matches) . ")'{$result}'");
      $this->recursive_dump($this->query_result, "(marker) " . __METHOD__ . " Joins");
    }/*}}}*/
    $str = preg_replace($regex_match, $regex_replace, $str);
    return $str == FALSE ? NULL : $str;
  }/*}}}*/

  function & remove() {/*{{{*/
    if ( $this->in_database() ) {
      $sql = <<<EOS
DELETE FROM `{$this->tablename}` WHERE `id` = {$this->id}
EOS;
      $result = $this->query($sql);
      $this->syslog(__FUNCTION__,__LINE__,"(critical) Result {$sql} " . $this->error());
    }
    return $this;
  }/*}}}*/

  function log_stack() {/*{{{*/
    try {
      throw new Exception('DB');
    } catch ( Exception $e ) {
      foreach ( $e->getTrace() as $st )
      syslog( LOG_INFO, " @ {$st['line']} {$st['class']}::{$st['function']}() in {$st['file']}");
    }
  }/*}}}*/

  function fetch_property_list($include_omitted = FALSE) {/*{{{*/
    // Get list of public properties of the derived class 
    $debug_method = C('DEBUG_'.__FUNCTION__,FALSE);
    $members         = array();
    $omitted_members = array();
    $myname          = get_class($this);
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
      $attr_is_model = class_exists("{$member['type']}",FALSE) || class_exists("{$member['type']}Model",FALSE);
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
    if ( !array_key_exists($myname,self::$obj_attrdefs) ) {/*{{{*/
      if ( $debug_method ) $this->syslog(__FUNCTION__,__LINE__,"(marker) Inserting attrdefs {$myname}");
      self::$obj_attrdefs[$myname] = array_merge(
        $members,
        $omitted_members
      );
    }/*}}}*/
    $returnval = $include_omitted ? self::$obj_attrdefs[$myname] : $members;
    if ( $debug_method ) $this->recursive_dump($returnval,"(marker) Finally " . get_class($this));
    return $returnval;
  }/*}}}*/

  protected function get_attrdefs() {/*{{{*/
    return array_key_exists(get_class($this), self::$obj_attrdefs)
      ? self::$obj_attrdefs[get_class($this)]
      : array(get_class($this) => 'No Definition')
      ;
  }/*}}}*/

  private final function fetch_typemap(& $attrinfo, $mode = NULL) {/*{{{*/

    // Get type map information for a SINGLE model attribute.
    $debug_method = C('DEBUG_'.__FUNCTION__,FALSE);

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
    $is_join       = (1 == preg_match('@(.*)Join$@i', get_class($this)));
    $is_ftref      = FALSE;

    if ( $debug_method ) {
      $this->syslog(__FUNCTION__, __LINE__, "(marker) - -- - Matches"  );
      $this->recursive_dump($matches, "(marker) - -- - ");
      $this->syslog(__FUNCTION__, __LINE__, "(marker) - -- - Attribute info"  );
      $this->recursive_dump($attrinfo, "(marker) -- - - ");
    }

    if ( !array_key_exists($matches[1][0], $type_map) ) {
      // Check for class name in typespec
      $model_match = preg_match('/Model$/i',$attrinfo['type']) ? $attrinfo['type'] : "{$attrinfo['type']}Model";
      if ( class_exists($model_match,TRUE) ) {
        if ( $debug_method ) 
          $this->syslog(__FUNCTION__, __LINE__, "(marker) Forcing use of class attrname {$attrinfo['type']} => {$model_match}"  );
        $attrinfo['propername'] = "{$attrinfo['type']}Model";
        $is_ftref = TRUE;
      }
      else {
        $this->syslog(__FUNCTION__, __LINE__, "(marker) No type found matching name '{$attrinfo['type']}' => '{$model_match}'"  );
      }
    }
    else {
    }

    $attrinfo['type'] = $matches[1][0];

    if ($debug_method) {
      $modifier_desc = empty($modifier) ? "empty" : "'{$modifier}'";
      $this->syslog(__FUNCTION__, __LINE__, "(marker) Called with {$modifier_desc} array n = " . count($attrinfo)  );
      $this->recursive_dump($attrinfo, "(marker)");
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
    if ( ( $is_join || $is_ftref ) && array_key_exists('propername',$attrinfo)) {

      $typemap['fieldname'] = $attrinfo['name'];

      $join_attrdefs = $this->get_attrdefs();
      $join_attrdefs = $join_attrdefs[$fieldname];
      // Add missing foreign table reference
      if ( $is_ftref ) $join_attrdefs['propername'] = $attrinfo['propername'];

      $this->
        syslog(__FUNCTION__, __LINE__, "(marker)Join Attrdefs" )->
        recursive_dump($join_attrdefs, "(marker)")
        ;

      if ( class_exists($join_attrdefs['type']) ) {
        $ft_propername = join('_',camelcase_to_array($join_attrdefs['propername']));
        $typemap['properties'] = <<<EOS
INT(11) NOT NULL REFERENCES `{$ft_propername}` (`id`) ON UPDATE CASCADE ON DELETE CASCADE 
EOS;
        $typemap['joint_unique'] = '`' . $typemap['fieldname'] . '`'; 
      }
      if ($debug_method) {
        $this->
          syslog(__FUNCTION__,__LINE__,"(marker) -- - -- - --  Final typemap")->
          recursive_dump($typemap,'(marker) "- -- - -- -"');
      }
    } else if (array_key_exists('propername',$attrinfo)) { 
      if ( is_null($mode) ) return NULL;
      if (!(1 == preg_match('@(.*)Join$@i', get_class($this)))) return NULL;
    }

    return $typemap;
  }/*}}}*/

  private final function construct_backing_table($tablename, $members) 
  {/*{{{*/
    $debug_method = C('DEBUG_'.__FUNCTION__,FALSE);
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
      if ( $debug_method ) {
        $this->syslog( __FUNCTION__, __LINE__, "(marker) Attribute defs '{$tablename}' for " . get_class($this) );
        $this->recursive_dump($attrdef,"(marker) ----- ---- --- -- -  - -- --- ---- -----");
      }
      if ( is_null($attrdef) ) {
        continue;
      }
      if ( array_key_exists('joint_unique', $attrdef) ) {
        $unique_attrs[] = $attrdef['fieldname'];
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

  function fetch_structure_backing_diffs($tablename, $members) 
  {/*{{{*/
    $debug_method = C('DEBUG_'.__FUNCTION__,FALSE);
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
    if ( $debug_method ) $this->syslog( __FUNCTION__, __LINE__, "(marker) -- Get tableinfo {$tablename}" );
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

  protected function register_derived_class() {/*{{{*/

    // Invoked from constructors of framework classes.
    // Count instantiations of any given derived class.

    $debug_method = C('DEBUG_'.__FUNCTION__,FALSE);
    if ($debug_method) {
      $this->syslog(__FUNCTION__,__LINE__,"(marker) -- " . get_class($this));
      if ( method_exists($this, 'get_joins') ) {
        $this->recursive_dump($this->get_joins(),"(marker) -- ");
      }
    }

  }/*}}}*/

  protected final function initialize_derived_class_state() {/*{{{*/

    $debug_method = C('DEBUG_'.__FUNCTION__,FALSE);

    $this->tablename = join('_', camelcase_to_array(get_class($this)));
    $this->initialize_db_handle();
    $is_join_table   = 1 == preg_match('@(.*)Join$@i',get_class($this));
    $members         = $this->fetch_property_list($is_join_table);

    if ( wp_get_current_user()->exists() && C('LS_SYNCHRONIZE_MODEL_STRUCTURE') ) {/*{{{*/

      // Determine if the backing table exists; construct it if it does not.
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
        if ( is_array($structure_deltas) && ( 
          ( array_key_exists('added_columns', $structure_deltas) && 0 < count($structure_deltas['added_columns']) ) ||
          ( array_key_exists('removed_columns', $structure_deltas) && 0 < count($structure_deltas['added_columns']) ) ) ) {
          $this->syslog( __FUNCTION__, __LINE__, "WARNING: Structure differences" );
          $this->recursive_dump($structure_deltas,"(marker) ------ --- -- - ");
        }
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
      unset($removed_columns);
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
      unset($added_columns);
      if ( $debug_method ) {
        $this->syslog( __FUNCTION__, __LINE__, "(marker) End. --------------------------------- " );
      }
    }/*}}}*/

    unset($members);
    unset($is_join_table);
  }/*}}}*/

  function & join_all() {/*{{{*/
    $join_names = $this->get_join_names();
    if ( $this->debug_method ) {
       $this->recursive_dump($this->join_attrs,"(marker) - -- - CURRENT - - -- -");
       $this->recursive_dump($join_names,"(marker) - -- - ALL ATTRS - -- -");
    }
    return $this->join($join_names);
  }/*}}}*/

  function get_join_names($specific_item = NULL,$try_cached = FALSE) {/*{{{*/
    return array_keys($this->get_joins($specific_item,$try_cached));
  }/*}}}*/

  function get_joins($specific_item = NULL,$try_cached = FALSE) {/*{{{*/
    if ( !is_array($this->join_props) ) $this->join_props = array(); 
    if ( !$try_cached || !(0 < count($this->join_props)) || (!is_null($specific_item) && !array_key_exists($specific_item,$this->join_props)) ) {/*{{{*/
      $this->join_props = $this->get_attrdefs();
      $this->filter_nested_array($this->join_props,'propername,joinobject[joinobject*=.*|i]');
    }/*}}}*/
    return is_null($specific_item) ? $this->join_props : array_element($this->join_props,$specific_item);
  }/*}}}*/

  function get_joins_by_model($model_typename = NULL, $assume_unique_attrs = TRUE) {/*{{{*/
    // Pivot the list of Join attributes around the Join object name, return joins keyed by model name
    /*
      Source:  (array) representative =>
      Source:    (string) propername => RepresentativeDossierModel
      Source:    (string) joinobject => CongressionalCommitteeRepresentativeDossierJoin
      Result:  (array) RepresentativeDossierModel =>
      Result:    (array) "attrname" =>
      Result:      0 => representative 
      Result:    (string) joinobject => CongressionalCommitteeRepresentativeDossierJoin
    */
    $joinattrs = $this->get_joins();
    $by_modelname = array();
    foreach ( $joinattrs as $model_attrname => $attrprops ) {
      $propername = $attrprops['propername'];
      $joinobject = $attrprops['joinobject'];
      if ( !array_key_exists($propername, $by_modelname) ) $by_modelname[$propername] = array(
        'attrname' => $assume_unique_attrs ? $model_attrname : array(),
        'joinobject' => $joinobject,
      ); 
      else if (!$assume_unique_attrs) $by_modelname[$propername]['attrname'][] = $model_attrname;
    }
    return is_null($model_typename) ? $by_modelname : array_element($by_modelname,$model_typename);
  }/*}}}*/

  function create_joins( $modelname_or_attrname, & $foreign_keys, $allow_update = FALSE, $full_match = TRUE ) {/*{{{*/

    // Parameters:
    // $model_or_attrname (string):  Model foreign table
    // $foreign_keys (array): 
    //   Accept a list of foreign keys having either of the forms:
    //   Array ( fk0, fk1, ... , fkn )
    //   Array ( fk0 => Array(<join attributes>), fk1 => Array(...), ... , fkn => Array(...) )
    // $allow_update: 
    $debug_method = C('DEBUG_'.__FUNCTION__,FALSE);

    $join_table = $this->get_joins();

    $modelname = array_key_exists($modelname_or_attrname, $join_table)
      ? array_element($join_table[$modelname_or_attrname], 'propername')
      : $modelname_or_attrname;

    $join_attrdefs = $this->get_attrdefs();
    $this->filter_nested_array($join_attrdefs,'propername,joinobject[joinobject*=.*|i][propername*='.$modelname.']');

    if ( !$this->in_database() ) {
      $this->syslog( __FUNCTION__, __LINE__, "(marker) --- --- --- - - - --- --- --- WARNING: Nothing to join. ID = (" . (gettype($this->get_id())) . ")" . (0 < intval($this->get_id()) ? $this->get_id() : "")); 
      $this->recursive_dump($join_attrdefs, "(marker) - -- ---");
    } else if (!is_array($join_attrdefs)) {
      $this->syslog( __FUNCTION__, __LINE__, "(marker) --- --- --- - - - --- --- --- WARNING: No match among keys"); 
      $this->recursive_dump($this->get_attrdefs(), "(marker) - -- ---");
    } else if (0 == count($join_attrdefs)) {
      $this->syslog( __FUNCTION__, __LINE__, "(marker) --- --- --- - - - --- --- --- ERROR: Multiple or zero matches for {$modelname}. Available attributes are:");
    } else if (1 == count($join_attrdefs)) {
      $self_id = $this->get_id();
      foreach( $join_attrdefs as $attrname => $attprops ) {

        $joinobj = $attprops['joinobject'];
        $joinobj = new $joinobj();
        $joinobj_attrdefs = $joinobj->get_attrdefs();

        // BLOB attributes to exclude from SQL filter condition
        $exclude_joinattrs = $joinobj_attrdefs;
        $this->filter_nested_array($exclude_joinattrs,'name[type=blob]');
        $exclude_joinattrs = array_flip(array_keys($exclude_joinattrs));

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
          $this->syslog( __FUNCTION__, __LINE__, "(marker) Full attribute defs:");
          $this->recursive_dump($this->get_attrdefs(),"(marker) - * * get_attrdefs() * * -");
        }/*}}}*/

        // TODO: Handle larger arrays of more than a handful of foreign Joins 
        foreach ( $foreign_keys as $fk_or_dummy => $foreignkey ) {/*{{{*/
          $data = is_array($foreignkey)
            ? array_merge(
                $foreignkey,
                array(
                  $self_attrname    => $self_id,
                  $foreign_attrname => $fk_or_dummy,
                )
              )
            : array(
              $self_attrname    => $self_id,
              $foreign_attrname => $foreignkey
            );
          $data_no_blobs = array_diff_key($data, $exclude_joinattrs);
          if ( $debug_method ) {
            $this->recursive_dump($exclude_joinattrs,"(marker) --- - E ---");
            $this->recursive_dump($data_no_blobs,"(marker) --- - F ---");
          }
          if ( $full_match ) 
            $joinobj->fetch($data_no_blobs,'AND');
          else
            $joinobj->fetch(array(
              $self_attrname    => $data_no_blobs[$self_attrname],
              $foreign_attrname => $data_no_blobs[$foreign_attrname],
            ),'AND');
          $join_present = $joinobj->in_database();
          if ( !$join_present || $allow_update ) {
            // FIXME: Tidy up the duplicate check
            $join_id = $join_present ? $joinobj->get_id() : NULL;
            if ( $join_present ) {
              unset($data[$self_attrname]);
              unset($data[$foreign_attrname]);
              $joinobj->fields(array_keys($data));
              $this->syslog( __FUNCTION__, __LINE__, "(marker) - -- Restrict update to ". join(',',array_keys($data)) );
            }
            $joinobj->set_contents_from_array($data);  
            $new_joinid = $joinobj->stow();
            $foreign_keys[$fk_or_dummy]['joinid'] = $new_joinid;
            if ( $debug_method ) {
              $join_exists  = $join_present ? ("#{$join_id} in DB updated") : "created as #{$new_joinid}";
              $this->syslog( __FUNCTION__, __LINE__, "(marker) - -- JOIN ". get_class($joinobj) ." {$join_exists} to {$modelname}" );
              $this->recursive_dump($data,"(marker) --- - ---");
            }
          } else {
            // FIXME: Verify operation of $full_match, then make this conditional
            $this->syslog(__FUNCTION__,__LINE__,
              "(marker) - No DB store. Join present " . ($join_present ? 'TRUE' : 'FALSE') . ", allow update " . ($allow_update ? 'TRUE' : 'FALSE')
            );
          }
        }/*}}}*/

        $joinobj = NULL;
      }
    } else {
      $this->syslog( __FUNCTION__, __LINE__, "(marker) --- --- --- - - - --- --- --- ERROR: Multiple or zero matches for {$modelname}. Available attributes are:");
      $this->recursive_dump($join_attrdefs, "(marker) - -- ---");
    }
    return $foreign_keys;
  }/*}}}*/

  function & execute() {/*{{{*/
    $this->syslog( __FUNCTION__, __LINE__, "(warning) --- --- --- - - - --- --- --- NO-OP NO-OP NO-OP");
    return $this;
  }/*}}}*/

  function & query($sql, $bindparams = NULL) {/*{{{*/
    $this->initialize_db_handle();
    self::$dbhandle->set_alias_map($this->alias_map);
    self::$dbhandle->query($sql, $bindparams);
    $this->last_inserted_id = self::$dbhandle->last_insert_id();
    // Clear JOIN parameters, cached JOIN assignment values, etc.
    if ( $this->suppress_reinitialize ) {
      $this->suppress_reinitialize = FALSE;
    } else {
      $this->clear_query_prereqs();
      $this->init_query_prereqs();
    }
    return $this;
  }/*}}}*/

  protected function get_std_id_attrset() {/*{{{*/
    return array(
      'name' => 'id',
      'type' => 'int11',
      'value' => NULL,
      'attrs' => array(
        "fieldname" => 'id', 
        "properties" => "INT(11)",
        "quoted" => FALSE,
        "mustbind" => FALSE,
      )
    );
  }/*}}}*/

  private function full_property_list($pivot_by = NULL, $only_entry = NULL) {/*{{{*/
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
    // Allow caller to specify a new array-value key
    if ( !is_null($pivot_by) ) {
      $attrlist = array_combine(
        array_map(create_function('$a','return $a["'.$pivot_by.'"];'), $attrlist),
        $attrlist
      );
    }
    // Allow caller to specify return of just a specific element of the property list. 
    return is_null($only_entry) ? $attrlist : array_element($attrlist,$only_entry);
  }/*}}}*/

  function construct_sql_from_conditiontree(& $attrlist, $condition_branch = NULL, $depth = 0) {/*{{{*/
    // FIXME:  This probably doesn't handle conjuctions properly.
    // Parse a binary tree containing query conditions, returning the resulting
    // condition string (which must evaluate to a valid SQL query condition)
    $querystring = array();

    if ( is_null($condition_branch) ) 
      $iteration_source =& $this->query_conditions;
    else
      $iteration_source =& $condition_branch;

    if ( $this->debug_method ) $this->recursive_dump($iteration_source,"(marker) - - - - - - - - - - - - CSFC @ {$depth}");

    // Detect use of placeholder in alias name position
    $alias_placeholder_regex = '@{([^}]*)}[.]`([^`]*)`@i';

    foreach ( $iteration_source as $conj_or_attr => $operands ) {

      $alias_subst_matches = array();
      if ( 1 == preg_match($alias_placeholder_regex, $conj_or_attr, $alias_subst_matches) ) {
        $alias_name = nonempty_array_element($alias_subst_matches,1);
        $alias_name = nonempty_array_element($this->alias_map['attrmap'], $alias_name,array());
        $alias_name = nonempty_array_element($alias_name,'alias');
        if ( $this->debug_final_sql ) $this->syslog(__FUNCTION__,__LINE__,"(marker) +++++++++++++++++++  Remap {$conj_or_attr} -> `{$alias_name}`.`{$alias_subst_matches[2]}`");
        $conj_or_attr = "`{$alias_name}`.`{$alias_subst_matches[2]}`";
      }
      
      if ( array_key_exists(strtoupper($conj_or_attr), array_flip(array('AND', 'OR')))) {
        if ( $this->debug_method ) $this->syslog(__FUNCTION__,__LINE__, "(marker) A conj '{$conj_or_attr}'");
        $fragment = $this->construct_sql_from_conditiontree($attrlist, $operands, $depth + 1);
        if ( FALSE == $fragment ) {
          $this->syslog(__FUNCTION__,__LINE__, "(marker) Invalid condition parameters, SQL statement could not be constructed.");
          return FALSE;
        }
        $querystring[] = '(' . join(" {$conj_or_attr} ", $fragment) . ')';
        continue;
      }
      if ( !is_array($operands) ) {/*{{{*///  Non-array operand
        $operand_regex = '@^(LIKE|REGEXP)(.*)@';
        $operand_match = array();
        $opmatch       = preg_match($operand_regex, $operands, $operand_match);
        $operator      = '=';
        $value         = NULL;
        if ( 1 == $opmatch ) {/*{{{*/
          // Match LIKE or REGEXP condition prefix
          // FIXME: This is absolutely NOT secure, inputs need to be filtered against SQL injection.
          // $this->recursive_dump($operand_match, __LINE__);
          $operator = trim($operand_match[1]);
          $value    = trim($operand_match[2]);
          if ( $this->debug_method ) $this->syslog(__FUNCTION__,__LINE__, "(marker) B - Non-array [operand]: {$operator}({$value})");
        }/*}}}*/
        else {/*{{{*/
          $attr_match_components = array();

          $match_aliased = array_key_exists("`a`.`{$conj_or_attr}`", $attrlist);
          if ( $match_aliased ) $conj_or_attr = "`a`.`{$conj_or_attr}`";

          if ( array_key_exists($conj_or_attr, $attrlist) ) { 
            $value = $attrlist[$conj_or_attr]['attrs']['quoted'] ? ("'" . self::$dbhandle->escape_string($operands) . "'") : "{$operands}";
          } else if ( $conj_or_attr == 'id' ) {
            // This property isn't enumerated in the object attribute list, but probably should be included.
              if ( 0 < intval($operands) ) $value = intval($operands);
              else {
                $this->syslog(__FUNCTION__,__LINE__, "(marker) --- - - - --- --- - - - Hit on 'ID' '{$conj_or_attr}', using operator {$operator}, value '{$operands}'");
              }
          } else if ( 1 == preg_match("@^([a-z`]*)\.`([a-z_]*)`@i",$conj_or_attr,$attr_match_components) ) {
            // Join attributes should be treated normally, by including them in attrlist
            // The only crucial attribute property to include is 'quoted', which tells the parser
            // to wrap assigned values in quotes.
            if ( $this->debug_method ) {
              $this->syslog(__FUNCTION__,__LINE__, "(marker) --- - - - --- --- - - - Must add Join attribute '{$conj_or_attr}', using operator {$operator}, value '{$operands}'");
            }
            $test_alias = trim(array_element($attr_match_components,1),'`');
            $test_attr  = trim(array_element($attr_match_components,2),'`');
            // $test_attr  = 1 == preg_match( "@(`{$test_alias}`)@i",) ? "`{$test_alias}`.`{$test_attr}`";
            $test_attr  = "`{$test_alias}`.`{$test_attr}`";
            if ( array_key_exists($test_attr, $attrlist) ) {
              $value = $attrlist[$test_attr]['attrs']['quoted'] ? ("'" . self::$dbhandle->escape_string($operands) . "'") : "{$operands}";
              if ( !is_null($value) ) $querystring[] = "{$conj_or_attr} {$operator} {$value}"; 
              $value = NULL;
            } else {
              $this->syslog(__FUNCTION__,__LINE__, "(marker) --- - - - --- --- - - - Unable to add Join attribute '{$conj_or_attr}', using operator {$operator}, value '{$operands}', testing against '{$test_attr}': No match in attrlist");
              $this->recursive_dump(array_keys($attrlist) , "(marker) - - - ----- ->");
              $this->recursive_dump($attr_match_components, "(marker) - - ----- - ->");
              $this->recursive_dump($this->alias_map      , "(marker) - Aliasmap -->");
            }

          } else {
            $this->syslog(__FUNCTION__,__LINE__, "(marker) --- - - - --- --- - - - Attribute '{$conj_or_attr}' not found in attribute list!");
            $this->recursive_dump(array_keys($attrlist),"(marker) - - --- - ->");
            $this->log_stack();
          }
          if ( $this->debug_method ) $this->syslog(__FUNCTION__,__LINE__, "(marker) C {$conj_or_attr} {$operands} -> {$value}");
        }/*}}}*/
        if ( !is_null($value) ) $querystring[] = "{$conj_or_attr} {$operator} {$value}"; 
      }/*}}}*/
      else {/*{{{*/// Array operand, presently only generates SQL IN (...) condition fragment

        $match_aliased = array_key_exists("`a`.`{$conj_or_attr}`", $attrlist);
        $attrlist_key = $match_aliased ? "`a`.`{$conj_or_attr}`" :  $conj_or_attr;

        if ( $attrlist[$attrlist_key]['attrs']['quoted'] ) {
          array_walk($operands,create_function(
            '& $a, $k, $s', '$a = $s->escape_string($a);'
          ), self::$dbhandle);
        }
        $value_wrap   = create_function('$a', $attrlist[$attrlist_key]['attrs']['quoted'] 
          ? 'return "' . "'" . '" . $a . "' . "'" . '";' 
          : 'return $a;');
        $value        = array_map($value_wrap, $operands);
        if ( 0 < count($value) ) {
          if ( $this->debug_method ) $this->syslog(__FUNCTION__,__LINE__, "(marker) D - Array operand `{$conj_or_attr}`, n(".count($value).") values found.");
          $value        = join(',',$value);
          $querystring[] = "{$conj_or_attr} IN ({$value})"; 
        } else {
          return FALSE;
        }
      }/*}}}*/
    }
    if ( $this->debug_method ) {
      $this->syslog(__FUNCTION__,__LINE__, "(marker) - - - - - - -  - Returning query string as array:");
      $this->recursive_dump($querystring,"(marker) - - - - -");
    }
    return $querystring;
  }/*}}}*/

  function update() {/*{{{*/
    $debug_method = C('DEBUG_'.__FUNCTION__,FALSE);
    $f_attrval   = create_function('$a', 'return $a["attrs"]["mustbind"] == TRUE ? "`{$a["name"]}` = ?": "`{$a["name"]}` = {$a["value"]}";');
    $f_bindattrs = create_function('$a', 'return $a["attrs"]["mustbind"] == TRUE ? "{$a["name"]}" : NULL;');
    $attrlist    = $this->full_property_list();
    if ( 0 < count($this->subject_fields) ) {
      if ( $debug_method ) {
        $this->syslog(__FUNCTION__,__LINE__,"(marker) - - - - - - Restrictions to apply");
        $this->recursive_dump($this->subject_fields, "(marker) - - - - A -");
      }
      $attrlist = array_intersect_key($attrlist, array_flip(array_values($this->subject_fields)));
    }
    $boundattrs  = array_filter(array_map($f_bindattrs,  $attrlist));
    $attrval     = join(',', array_map($f_attrval, $attrlist));
    $sql = <<<EOS
UPDATE `{$this->tablename}` SET {$attrval} WHERE `id` = {$this->id}
EOS;
    if ($debug_method) {
      $this->syslog(__FUNCTION__, __LINE__, "(marker) Will update record #{$this->id}: {$sql}" );
      $this->recursive_dump(array_combine(
        array_keys($attrlist),
        array_map(create_function('$a','return $a["value"];'), $attrlist)
      ),'(marker) Exclude UNIQUE attrs');
    }
    $this->insert_update_common($sql, $boundattrs, $attrlist);
    if ( !empty(self::$dbhandle->error) ) {
      $this->syslog( __FUNCTION__, __LINE__, "ERROR: " . self::$dbhandle->error ); // throw new Exception("Failed to execute SQL: {$sql}");
      $this->syslog(__FUNCTION__, __LINE__, "Record #{$this->id}: {$sql}" );
    }
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

  protected function reorder_aliasmap(& $consolidated_aliasmap) {/*{{{*/
    if ( is_array($consolidated_aliasmap) ) {/*{{{*/
      if ( $this->debug_method ) $this->recursive_dump($consolidated_aliasmap,"(marker) Alias Map - - -");
      $ft_aliasmap = array();
      $aliases = array();
      foreach ( $consolidated_aliasmap as $attribute => $mapinfo ) {
        $alias = array_element($mapinfo,'alias');
        $left  = array_element($mapinfo,'left');
        $aliases[] = $alias;
        // if ( !is_null($left) ) $left = "@^{$left}_@";
        // $alias = "@^{$alias}_@";
        $ft_aliasmap[$alias] = array_filter(array(
          'attrname' => $attribute,
          'left' => $left,
        ));
      }
      $consolidated_aliasmap = array(
        'map' => $ft_aliasmap,
        'match' => '@^(' . join('|',$aliases) . ')_(.*)$@',
        'attrmap' => $consolidated_aliasmap,
      );
    }/*}}}*/

  }/*}}}*/

  protected function get_join_clauses(& $consolidated_aliasmap) {/*{{{*/

    $debug_method = C('DEBUG_'.__FUNCTION__,FALSE);
    $debug_method         |= $this->debug_method;
    $join_clauses          = array();
    $consolidated_aliasmap = NULL;

    if ( 0 < count($this->join_attrs) ) {/*{{{*/
      $consolidated_aliasmap = array();
      if ( $debug_method ) {/*{{{*/
        $this->syslog(__FUNCTION__,__LINE__,"(marker) - - - - - - Constructing JOIN .. ON clauses");
        $this->recursive_dump($this->join_attrs,"(marker) - - -");
      }/*}}}*/
      // This method updates the SELECT tablespec list to include 
      // foreign table attributes.  If any attributes of the Join subject
      // are specified, these must be aliased.
      $joins               = $this->get_joins();
      $ft_aliases          = array('b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n','p','q','r'); // Alias 'a' is always the join parent
      $ft_aliasmap         = array();
      $join_instance_table = array();

      foreach ( $this->join_attrs as $k => $jv ) {/*{{{*/
        $attribute_list_present = TRUE;
        if ( $debug_method ) {/*{{{*/
          $this->syslog(__FUNCTION__,__LINE__,"(marker) - - - - - - Clause '{$k}'");
        }/*}}}*/
        // Get SQL fragments for use in JOIN clauses
        $fm       = JoinFilterUtility::get_map_functions($jv);
        $attrname = $fm[0]['target'];
        $rtable_property_list = $this->get_join_object($attrname,'ref','obj')->full_property_list();
        // Include 'id' field 
        if ( !array_key_exists('id', $rtable_property_list) ) {
          $rtable_property_list['id'] = $this->get_std_id_attrset();
        }
        // If the attribute name exists in the list of Join objects,
        // then it hasn't been parsed for conditions at all, so that the
        // object referenced on the right side of the JOIN is not known.
        // We then have to fill in the join clause source with a list of 
        // attributes from the right side.
        if ( array_key_exists($jv,$joins) ) {/*{{{*/
          if ( $debug_method ) {
            $this->syslog(__FUNCTION__,__LINE__,"(marker) - - - - - - Simple attribute name '{$jv}' given as join clause '{$k}'");
            $this->recursive_dump($rtable_property_list,"(marker) - RSA  -");
          }
          $fm[1] = array(
            'attrlist' => $rtable_property_list,
            'fields' => array_map(create_function('$a','return "`{ftable}`.`{$a}` `{ftable}_{$a}`";'),array_keys($rtable_property_list)),
            'conditions' => array(),
          );
          $attribute_list_present = FALSE;
        }/*}}}*/
        if ( $debug_method ) {/*{{{*/
          $this->syslog(__FUNCTION__,__LINE__,"(marker) - - - - - - Join Map '{$k}'");
          $this->recursive_dump($fm, "(marker) - - JM  - -");
        }/*}}}*/
        $fm_hash = md5(json_encode($fm));

        $join_attr        = array_element($joins,$attrname);
        $join_object_name = array_element($join_attr,'joinobject');
        // Test whether a named attribute actually exists
        if ( empty($join_object_name) ) {/*{{{*/
          $this->syslog(__FUNCTION__,__LINE__,"(warning) - - - - - - No such Join attribute '{$attrname}' in this class. Check your Join conditions.");
          $this->recursive_dump($this->get_attrdefs(),"(marker) - * * get_attrdefs() * * -");
          continue;
        }/*}}}*/
        $join_table_name    = join('_',camelcase_to_array($join_object_name));
        // Get an alias for this attribute
        if ( !array_key_exists($attrname, $ft_aliasmap) ) $ft_aliasmap[$attrname] = array(
          'join'  => $join_table_name,
          'type'  => get_class($this->get_join_object($attrname,'ref','obj')),
          'alias' => $ft_aliases[count($ft_aliasmap)],
        );

        $attr_alias = $ft_aliasmap[$attrname]['alias'];

        if ( !array_key_exists($join_object_name, $join_instance_table) ) {/*{{{*/
          $join_instance_table[$join_object_name] = $this->get_join_object($attrname,'join','set');
          // This attribute list is not used here, and is intended to be returned to the caller, to be merged into the 
          // attribute list used to detect whether to wrap value assignments in quotes, etc.
          // We need only remap the names to aliased equivalents, so that there is no collision with
          // primary table attribute names.
          $join_instance_table[$join_object_name]['attrlist'] = $this->get_join_object($attrname,'join','obj')->full_property_list();
          // Include 'id' field 
          if ( !array_key_exists('id', $join_instance_table[$join_object_name]['attrlist']) ) {/*{{{*/
            $join_instance_table[$join_object_name]['attrlist']['id'] = array(
              'name' => 'id',
              'type' => 'int11',
              'value' => NULL,
              'attrs' => array(
                "fieldname" => 'id', 
                "properties" => "INT(11)",
                "quoted" => FALSE,
                "mustbind" => FALSE,
              )
            );
          }/*}}}*/
          $join_attrs_sans_refs = array_filter(array_map(create_function('$a','return 1 == preg_match("@Model\$@i",array_element($a,"propername")) ? NULL : $a;'),$join_instance_table[$join_object_name]['attrlist']));
          if ( 0 < count($join_attrs_sans_refs) ) {
            $join_instance_table[$join_object_name]['fields'] = array_combine(
              array_map(create_function('$a','return "`'.$attr_alias.'`.`{$a["name"]}` `'.$attr_alias.'_{$a["name"]}`";'), $join_attrs_sans_refs),
              $join_attrs_sans_refs
            );
          }
          $join_instance_table[$join_object_name]['attrlist'] = array_combine(
            array_map(create_function('$a','return "`'.$attr_alias.'`.`{$a["name"]}`";'), $join_instance_table[$join_object_name]['attrlist']),
            $join_instance_table[$join_object_name]['attrlist']
          );
          if ( $debug_method ) {/*{{{*/
            $this->syslog(__FUNCTION__,__LINE__,"(marker) - - - - Join instance {$join_object_name}");
            $this->recursive_dump($join_instance_table[$join_object_name],"(marker) -+-");
          }/*}}}*/
        }/*}}}*/

        $join_fkey_attname  = $join_instance_table[$join_object_name]['self'];
        array_walk($fm[0]['conditions'],create_function('& $a, $k, $s', '$a = str_replace("{join}","`{$s}`", $a);'), $attr_alias);

        // Primary join clause, and the only one if the user hasn't specified a foreign table condition
        $fm[0] = array(
          'joinclause' => "LEFT JOIN `{$join_table_name}` `{$attr_alias}` ON `a`.`id` = `{$attr_alias}`.`{$join_fkey_attname}`",
          'conditions' => $fm[0]['conditions'],
          'attrlist'   => array_element($join_instance_table[$join_object_name],'attrlist'),
        ); 
        if ( !$attribute_list_present ) {
          $fm[1]['fields'] = array_merge(
            array_keys(array_element($join_instance_table[$join_object_name],'fields',array())),
            $fm[1]['fields']
          );
        }
        ///////////////////////////////////////////////////////////////////////////////////////////////////
        // Now construct the foreign table JOIN subclause and any conditions that relate to it. 
        if ( array_key_exists(1,$fm) ) {/*{{{*/

          if ( $debug_method ) $this->syslog(__FUNCTION__,__LINE__,"(marker) - - - - Using join properties of foreign table {$join_attr['propername']}");

          $matched_ft_element = $this->get_join_object($attrname,'ref','obj')->get_joins();

          if ( $debug_method ) {
            $this->recursive_dump($matched_ft_element,"(marker) - - - ---- - - -");
          }

          $this->filter_nested_array($matched_ft_element, '#[propername*=]', 0); // We expect there to be just one entry
          $foreign_table_name = join('_',camelcase_to_array($join_attr['propername']));

          // Construct a fake lookup table attribute name
          $rtable_alias_attrname = join('_',array($attrname,$foreign_table_name)); 

          if ( !array_key_exists($rtable_alias_attrname, $ft_aliasmap) ) $ft_aliasmap[$rtable_alias_attrname] = array(
            'join'  => $join_table_name,
            'type'  => get_class($this->get_join_object($attrname,'join','obj')),
            'alias' => $ft_aliases[count($ft_aliasmap)],
            'left'  => $attr_alias, 
          );

          $rtable_attr_alias = $ft_aliasmap[$rtable_alias_attrname]['alias'];
          $join_rkey_attname = $join_instance_table[$join_object_name]['fkey'];

          // FIXME: If no attributes are specified for the right foreign table (on the other end of the Join)
          // FIXME: then we should include ALL attributes from that table.  Otherwise, every join() method invocation
          // FIXME: must include the subject table fields.
          if (!is_array($fm[1]['fields'])) {
            $this->syslog(__FUNCTION__,__LINE__,"(warning) - - - - Invalid foreign key map table entry.");
            $this->syslog(__FUNCTION__,__LINE__,"(warning) - - - - " . print_r($fm));
          }
          array_walk( $fm[1]['fields']    , create_function('& $a, $k, $s', '$a = str_replace("{ftable}",$s, $a);'), $rtable_attr_alias);
          array_walk( $fm[1]['conditions'], create_function('& $a, $k, $s', '$a = str_replace("{ftable}",$s, $a);'), $rtable_attr_alias);

          $fm[1]['joinclause'] = "LEFT JOIN `{$foreign_table_name}` `{$rtable_attr_alias}` ON `{$attr_alias}`.`{$join_rkey_attname}` = `{$rtable_attr_alias}`.`id`"; 
          $fm[1]['attrlist'] = $rtable_property_list;
          $fm[1]['attrlist'] = array_combine(
            array_map(create_function('$a','return "`'.$rtable_attr_alias.'`.`{$a["name"]}`";'), $fm[1]['attrlist']),
            $fm[1]['attrlist']
          );
        }/*}}}*/

        $join_clauses[] = $fm;

        if ( $debug_method ) {
          $this->recursive_dump($fm,"(marker) - S -");
          $this->recursive_dump($ft_aliasmap,"(marker) - Aliases -");
        }
        // This alias map is used AFTER the SQL statement has been executed.
        // It is used in e.g. DatabaseUtility::result_to_model, whenever 
        // aliased resultset names need to be mapped into the correct
        // foreign table entity.
        $consolidated_aliasmap = array_merge(
          $consolidated_aliasmap,
          $ft_aliasmap
        );

      }/*}}}*/
      //// Cleanup
      unset($this->join_attrs);
      $this->join_attrs = array();
    }/*}}}*/

    $this->reorder_aliasmap($consolidated_aliasmap);

    if ( $debug_method ) {/*{{{*/
      $this->syslog(__FUNCTION__,__LINE__,"(marker) - - - " . get_class($this) . " JOIN clause information");
      $this->recursive_dump($join_clauses, "(marker) - - -- JC -- -");
      $this->recursive_dump($consolidated_aliasmap, "(marker) - - -- AM -- -");
    }/*}}}*/

    return $join_clauses;
  }/*}}}*/

  protected function prepare_select_sql(& $sql, $exclude_blobfields = FALSE) {/*{{{*/
    // Fill in the SQL string, and return attribute list
    // TODO: Exclude *BLOB attributes from the statement generated with this method. 

    $debug_method = C('DEBUG_'.__FUNCTION__,FALSE);

    $this->initialize_db_handle();

    $key_by_varname  = create_function('$a', 'return $a["name"];');
    $attrlist        = array_merge(array('id' => $this->get_std_id_attrset()),$this->full_property_list());
    $key_map         = array_map($key_by_varname, $attrlist);
    $conditionstring = NULL;
    $join_attrlists  = array();

    if ( $debug_method ) {/*{{{*/
      $this->syslog(__FUNCTION__,__LINE__, "(marker) --- Property list (source for attrlist)");
      $this->recursive_dump( $key_map, "(marker) - - - -" );
      $this->syslog(__FUNCTION__,__LINE__, "(marker) --- Attribute list");
      $this->recursive_dump( $attrlist, "(marker) - - - -" );
    }/*}}}*/
    if ( 0 < count($attrlist) && (count($attrlist) == count($key_map)) ) {/*{{{*/
      $primary_table_alias = NULL;
      $join_clauses        = '';
      $attrnames           = array();
      /////////////////////////////////////////////////////////////////////
      // Use JOIN ... ON clauses when present; include JOIN attribute list as needed
      // This call modifies subsequent processing of the SQL condition tree,
      // by forcing use of an alias for this table, by attaching additional 
      // WHERE conditions, and by modifying the tablesource clause to include
      // JOINed table attributes, as needed
      $join_clause_source = $this->get_join_clauses($this->alias_map);
      if ( is_array($join_clause_source) && 0 < count($join_clause_source) ) {/*{{{*/
        $primary_table_alias = '`a`.';
        $join_clauses = array();
        if ( $debug_method ) {/*{{{*/
          $this->syslog(__FUNCTION__,__LINE__,"(marker) - -- - - Retrieved clauses - - - - - - - -");
          $this->recursive_dump($join_clause_source,"(marker) - - -");
        }/*}}}*/

        while ( 0 < count($join_clause_source) ) {/*{{{*/
          $clause_set = array_pop($join_clause_source);
          $remaining_elements = count($join_clause_source);
          krsort($clause_set);
          while ( 0 < count($clause_set) ) {
            $j = array_pop($clause_set); 
            if ( $debug_method ) {
              $this->syslog(__FUNCTION__,__LINE__,"(marker) {$remaining_elements} - - - - - - Extracted clauses");
              $this->recursive_dump($j,"(marker) - - -");
            }
            $join_clauses[] = array_element($j,'joinclause');
            if ( array_key_exists('fields', $j) ) $attrnames = array_merge($attrnames, $j['fields']);
            if ( array_key_exists('attrlist', $j) ) $join_attrlists = array_merge($join_attrlists, nonempty_array_element($j,'attrlist',array()));
            // FIXME: JOIN conditions generated by the JoinFilterUtility do not allow
            // FIXME: distinguishing conjunctions ('and') and disjunctions ('or').
            // FIXME: We default to treating condition terms as being joined by AND connectives.
            // FIXME: c.f. DatabaseUtility::where()
            if ( $debug_method ) {
              $this->syslog(__FUNCTION__,__LINE__,"(marker) {$remaining_elements} - - - - - - PREEXISTING QUERY CONSTRAINTS");
              $this->recursive_dump($this->query_conditions,"(marker) {$remaining_elements} - - -");
            }
            // Note that JOINs only EVER exercise the sections of construct_sql_from_conditiontree
            // that deal with scalar values; it isn't yet possible (ca. SVN #562) to directly
            // specify array conditions, i.e. to assert IN (...) or NOT IN (...) constraints
            // from the CSS-selector-like parser's output.
            $condstack = array('AND' => array());
            foreach ( array_element($j,'conditions',array()) as $condphrase ) {
              $condstack['AND'][array_element($condphrase,'attr')] = array_element($condphrase,'val');
            }
            if ( 0 < count(array_element($condstack,'AND')) ) {
              if ( $debug_method ) {
                $this->syslog(__FUNCTION__,__LINE__,"(marker) {$remaining_elements} - - - - - - MODIFIED QUERY CONSTRAINTS");
                $this->recursive_dump($this->query_conditions,"(marker) {$remaining_elements} - - -");
              }
              if ( !array_key_exists('AND',$this->query_conditions) ) {
                $this->query_conditions['AND'] = array();
              }
              $this->query_conditions['AND'] = $condstack['AND'];

              if ( $debug_method ) {
                $this->syslog(__FUNCTION__,__LINE__,"(marker) {$remaining_elements} - - - - - - FINAL SET OF QUERY CONSTRAINTS");
                $this->recursive_dump($this->query_conditions,"(marker) {$remaining_elements} - - -");
              }
            }
          }
        }/*}}}*/
        if ( $debug_method ) {/*{{{*/
          $this->syslog(__FUNCTION__,__LINE__,"(marker) - - - - - - JOIN clause list");
          $this->recursive_dump($join_clauses,"(marker) - - -");
        }/*}}}*/

        $join_clauses = join(' ', $join_clauses);
        
      }/*}}}*/
      /////////////////////////////////////////////////////////////////////
      $bindable_attrs = ( $exclude_blobfields )
        ? create_function('$a', 'return $a["attrs"]["mustbind"] ? NULL : "'.$primary_table_alias.'`" . $a["name"] . "`";')
        : create_function('$a', 'return "'.$primary_table_alias.'`{$a["name"]}`";')
        ;
      if ( !is_null($primary_table_alias) ) {
        $key_map = array_combine(
          array_map(create_function('$a','return "'.$primary_table_alias.'`{$a}`";'),array_keys($key_map)),
          array_values($key_map)
        );
      }
      $attrnames = array_merge(
        $attrnames,
        array_filter(array_map($bindable_attrs, $attrlist))
      );
      if ( !array_key_exists($primary_table_alias.'`id`',array_flip($attrnames)) )
        $attrnames[] = $primary_table_alias.'`id`'; // Mandatory to include this
      $attrnames   = join(',',$attrnames);
      $attrlist    = array_combine($key_map, $attrlist); // Pivot keys, make [name] attribute be the array lookup key

      if ( !is_null($primary_table_alias) ) {
        $attrlist = array_combine(
          array_map(create_function('$a','return "'.$primary_table_alias.'`{$a}`";'),array_keys($attrlist)),
          array_values($attrlist)
        );
      }

      if ( !is_null($primary_table_alias) ) {
        if ( $debug_method ) {
          $this->syslog(__FUNCTION__,__LINE__,"(marker) - - - Attribute name targets: {$attrnames}");
          $this->recursive_dump(array_keys($attrlist),"(marker) - - - - Pre-generate, alias present");
        }
      }

      $attrlist        = array_merge( $join_attrlists, $attrlist );
      $conditionstring = $this->construct_sql_from_conditiontree($attrlist);
      if ( FALSE == $conditionstring ) return FALSE;
      $order_by = array();
      // FIXME: Treat JOIN attributes correctly
      if ( 0 < count($this->order_by_attrs) ) {/*{{{*/
        foreach ( $this->order_by_attrs as $a => $b ) {
          if ( is_integer($a) ) {
            $order_by[] = $b;
          } else {
            $order_by[] = "{$a} {$b}";
          }
        }
      }/*}}}*/
      $this->order_by_attrs = array();
      // Use ORDER BY clause
      $order_by = ( 0 < count($order_by) )
        ? "ORDER BY " . join(',', $order_by)
        : NULL
        ;
      // Use LIMIT clause 
      $limit_by = NULL;
      if ( 0 < count($this->limit) ) {/*{{{*/
        $this->limit['n'] = intval($this->limit['n']);
        $this->limit['o'] = intval($this->limit['o']);
        if ( 0 < $this->limit['n'] ) {
          $limit_by = "LIMIT {$this->limit['o']}, {$this->limit['n']}";
        } else {
          $this->limit = array();
        }
      }/*}}}*/
      $conditionstring = join(' AND ',$conditionstring);
      $primary_table_alias = rtrim($primary_table_alias,'.');
      $sql = <<<EOS
SELECT {$attrnames} FROM `{$this->tablename}` {$primary_table_alias} {$join_clauses} WHERE {$conditionstring} {$order_by} {$limit_by}
EOS;
    }/*}}}*/

    if ( $this->debug_final_sql ) {/*{{{*/
      $this->syslog( __FUNCTION__, __LINE__, "(marker) Query: {$sql}");
      $this->syslog( __FUNCTION__, __LINE__, "(marker) Condition: {$conditionstring}");
      if ( $this->debug_final_sql ) $this->recursive_dump($this->alias_map,"(marker) - - -- Aliases -- - -");
    }/*}}}*/
    if ( $debug_method ) {/*{{{*/
      $this->syslog( __FUNCTION__, __LINE__, "(marker) Returning attribute list:");
      $this->recursive_dump($attrlist,"(marker) - - -- Attrlist -- - -");
    }/*}}}*/

    return $attrlist;

  }/*}}}*/

  function result_to_model(array $result) {/*{{{*/
    if ( is_array($result) && (0 < count($result)) && array_key_exists('attrnames', $result)) {
      unset($this->query_result);
      $this->query_result  = array();
      $assignment_failures = array();
      foreach ( $result['attrnames'] as $resattridx => $rescol ) {

        $match_aliased = array_key_exists("`a`.`{$rescol}`", $this->attrlist);

        $attrlist_key  = $match_aliased ? "`a`.`{$rescol}`" : $rescol;

        if ( !array_key_exists($attrlist_key, $this->attrlist) ) {
          // Neither the plain nor the alias-qualified attribute names match; it's probably a Join attribute 
          if ( $this->debug_method ) $this->recursive_dump($this->alias_map,"(marker) -- - -- - AliasMap for {$rescol} with {$attrlist_key} -- - -- -");
          if ( array_key_exists($rescol, array_element($this->alias_map,'attrmap',array())) ) {
            $this->attrlist[$rescol] = array('name' => $rescol, 'type' => $this->alias_map['attrmap'][$rescol]['type']);
          } else {
            $seeklist = $this->attrlist;
            $this->attrlist[$rescol] = array('name' => '_', 'type' => '_');
          }
        } else {
          $this->attrlist[$rescol] = $this->attrlist[$attrlist_key];
          if ( $rescol != $attrlist_key )
          unset( $this->attrlist[$attrlist_key] );
        }
        // Reconstruct the attribute / field name, as declared in the Model definition.
        $plain_attrname = $this->attrlist[$rescol]['name'];
        $attrname = $rescol == 'id'
          ? 'id'
          : "{$plain_attrname}_{$this->attrlist[$rescol]['type']}"
          ;
        if ( ('___' == $attrname) || ('_' == $attrname) ) {
          $this->syslog(__FUNCTION__,__LINE__,"(warning) - -- - Failed to assign value for [{$rescol}], plain_attrname = '{$plain_attrname}', attrname = '{$attrname}'");
          $assignment_failures[] = $rescol;
        } else {
          $setter = "set_{$plain_attrname}";
          $getter = "get_{$plain_attrname}";
          $value = $result['values'][0][$resattridx]; // A single-record fetch reduces nest depth by 1

          if ( !empty($plain_attrname) ) { 
            $this->$attrname = $value; 
            $this->query_result[$plain_attrname] = ( method_exists($this,$getter) ) 
              ? $this->$getter()
              : $value 
              ; 
          } else {
            $this->$attrname = $value; 
          }
          if ( $this->debug_operators ) $this->syslog(__FUNCTION__,__LINE__,"(marker) +++++ Assign {$attrname} v {$rescol} v plain({$plain_attrname}) " . gettype($value) );
        }
        if ( $this->debug_operators ) { 
          if ( strlen($this->$attrname) < 500 ) $this->syslog( __FUNCTION__, __LINE__, "> {$attrname} <- [{$this->$attrname}|{$value}]");
        }
      }
      if ( 0 < count($assignment_failures) ) {
        $this->syslog(__FUNCTION__,__LINE__,"(warning) - -- - Some attributes could not be matched to attribute list.");
        $this->recursive_dump($assignment_failures,"(warning) - ->");
      }
    } else {
      $this->syslog(__FUNCTION__,__LINE__,'(warning) - model load failure ' . join('|', $result));
    }
  }/*}}}*/

  function select() {/*{{{*/
    // Assigns a valid resultset to $this->query_result (in the call to result_to_model) 
    $this->id = NULL;
    $sql = '';
    unset($this->query_result);
    $this->query_result = NULL;
    if (FALSE == ($this->attrlist = $this->prepare_select_sql($sql))) {
      $this->syslog(__FUNCTION__,__LINE__,"(error) -- - - Failed to setup SELECT SQL {$sql}");
      return FALSE;
    }
    $result = $this->query($sql)->resultset();
    if ( is_array($result) ) {
      if ( $this->debug_method ) $this->syslog(__FUNCTION__,__LINE__,"(marker)   Invoking result_to_model"); 
      $this->result_to_model($result);
    }
    if ( $this->debug_method ) {/*{{{*/
      $this->syslog(__FUNCTION__,__LINE__,"(marker) -- - - res #{$this->id} " . gettype($result));
      $this->recursive_dump($result,"(marker) -- - - res " . gettype($result));
      $this->syslog(__FUNCTION__,__LINE__,"(marker) -- - - res query {$sql}");
    }/*}}}*/
    return $this->query_result;
  }/*}}}*/

  function & recordfetch_setup() {/*{{{*/
    $sql = '';
    if ( FALSE == ($this->attrlist = $this->prepare_select_sql($sql)) ) return FALSE;
    if ( $this->debug_method ) $this->syslog(__FUNCTION__,__LINE__,"(marker)  -- - - -- - --- - - Executing {$sql}"); 
    return $this->query($sql);
  }/*}}}*/

  function record_fetch(& $single_record)
  {/*{{{*/
    return $this->recordfetch($single_record, FALSE, TRUE);
  }/*}}}*/

  function recordfetch(& $single_record, $update_model = FALSE, $execute_setup = FALSE) {/*{{{*/
    $single_record = array();
    if ( $execute_setup ) $this->recordfetch_setup();
    $a = self::$dbhandle->recordfetch($single_record);
    if ( $a == TRUE ) {
      if ( $update_model && is_array($single_record) ) {
        $this->result_to_model(array(
          'attrnames' => $single_record['attrnames'],
          'values' => array($single_record['values']),
        ));
      }
      $single_record = array_combine($single_record['attrnames'], $single_record['values']); 
    } else {
      // Reset
      $this->alias_map = array(); 
    }
    if ( is_array($single_record) && array_key_exists('id', $single_record) ) {
      $this->id = $single_record['id'];
    }
    return $a;
  }/*}}}*/

  function fetch_single_joinrecord($document_contents = array()) {/*{{{*/

    $debug_method = C('DEBUG_'.__FUNCTION__,FALSE);

    $this->recordfetch_setup();

    // Create placeholder associative arrays to contain child Join data
    $j = $this->get_join_names();

    foreach ( $j as $attrnames ) $document_contents[$attrnames] = array();

    if ( $debug_method ) $this->recursive_dump($j,"(marker) J - - -");

    $bill = array();

    // Collect all Join attributes for a single record
    while ( $this->recordfetch($bill) ) {/*{{{*/

      if ( $debug_method ) $this->
        syslog(__FUNCTION__,__LINE__,"(marker) - - {$bill['sn']} {$bill['id']}")->
        recursive_dump($bill,"(marker) -^- {$id}");

      foreach ( $j as $attrnames ) {
        $joinattr_data = $bill[$attrnames]['data'];
        if ( !is_null(array_element($joinattr_data,'id')) ) {
          $joinattr_join = $bill[$attrnames]['join'];
          $joinattr_id = $joinattr_data['id'];
          if ( !array_key_exists($joinattr_id, $document_contents[$attrnames]) ) {
            $document_contents[$attrnames][$joinattr_id] = 
              array_merge($joinattr_data, array('join' => array()));
          }
          $document_contents[$attrnames][$joinattr_id]['join'][$joinattr_join['id']] = $bill[$attrnames]['join'];
        }
        /*
        // Join root
        $committee_data = $bill[$attrnames]['data'];
        if ( !is_null(array_element($committee_data,'id')) &&
          !array_key_exists($committee_data['id'], $document_contents[$attrnames]) )
        $document_contents[$attrnames][$committee_data['id']] = array_merge(
          $committee_data,
          array('join' => $bill[$attrnames]['join'])
        );
        */
      }

    }/*}}}*/

    return $document_contents;
  }/*}}}*/

  function fetch($attrval, $attrname = NULL) {/*{{{*/
    // See retrieve(), which invokes this method in an fcall wrapper
		// It is possible to use a compound condition e.g.
		// fetch( array(
		//   'attr1' => <val1>,
		//   'attr2' => <val2>,
		// ), 'AND' )
    $this->id = NULL;
    if ( is_null($attrname) ) {
      $this->where(array('id' => $attrval))->select();
    } else {
      $this->where(array($attrname => $attrval))->select();
    }
    // See recordfetch()
    if ( $this->debug_method ) {
      $this->syslog( __FUNCTION__, __LINE__, "(marker) - - - - - - Fetch: ({$attrname}, {$attrval}) -> ID obtained is ({$this->id})" );
      $this->recursive_dump($this->query_result, "(marker) - - - - Result" );
    }
    $this->alias_map = array(); 
    return $this->query_result;
  }/*}}}*/

	function & check_extant(& $record_present, $record_id = FALSE) {/*{{{*/
		$record_present = $record_id ? ($this->in_database() ? $this->get_id() : NULL ) : $this->in_database();
		return $this;
	}/*}}}*/

  function & retrieve($attrval, $attrname = NULL) {/*{{{*/
    // Continuation syntax call of fetch() 
    $this->fetch($attrval, $attrname);
    return $this;
  }/*}}}*/

  function retrieved_record_difference($stowable_content, $debug_method = FALSE) {
    // Return the difference between a stowable content array
    // and the result of an IMMEDIATELY preceding retrieve() call.
    //
    // A use case for calling this method is when we wish to know ONLY 
    // which record attributes have changed between previously computed
    // Model content, and the matching record stored on disk.
    $document_fetched = $this->query_result;
    if ( !is_array($document_fetched) ) $document_fetched = array();
    // Determine difference between stored and parsed documents.
    $intersection  = array_intersect_key( $stowable_content, $document_fetched );
    $difference    = array_diff( $intersection, $document_fetched );
    if ( $debug_method && (0 < count($difference)) ) {
      $this->recursive_dump($stowable_content,"(marker) - -*- -");
      $this->recursive_dump($intersection,"(marker) - -+- -");
      $this->recursive_dump($difference,"(marker) - --- -");
    }
    return $difference;
  }

  function in_database() {/*{{{*/
    // If an instance of this class has an ID, then it is assumed that
    // the record exists.
    // TODO: Handle record locking
    return property_exists($this,'id') ? intval($this->id) > 0 : FALSE;
  }/*}}}*/

  function stow($a = NULL, $b = NULL) {/*{{{*/
    $result = is_null($this->id)
      ? $this->insert()
      : $this->update()
      ;
    if ( is_int($result) && (0 < $result) ) $this->set_id($result);
    return $result;
  }/*}}}*/

  private function insert_update_common($sql, $boundattrs, $attrlist) {/*{{{*/
    // TODO: Handle Join components given as [join,data] tuples
    $debug_method = C('DEBUG_'.__FUNCTION__,FALSE);
    if ( empty($sql) ) throw new Exception(get_class($this));
    if ( 0 < count($boundattrs) ) {/*{{{*/
      if ( $debug_method ) $this->syslog(__FUNCTION__,__LINE__, "--- (marker) Currently: {$sql}");
      $bindable_attrs = array();
      foreach ( $boundattrs as $b ) {/*{{{*/
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
      }/*}}}*/
      // $this->recursive_dump($bindable_attrs,__LINE__);
      if ( 0 < count($this->join_value_cache) ) $this->suppress_reinitialize = TRUE;
      $result = $this->query($sql, $bindable_attrs);
      if ( empty(self::$dbhandle->error) ) {/*{{{*/
        $this->update_joincache_attrs();
      }/*}}}*/
      $bindable_attrs = NULL;
      return $result;
    }/*}}}*/

    if ( 0 < count($this->join_value_cache) ) { /*{{{*/
      $this->suppress_reinitialize = TRUE;
      $query_result = $this->query($sql);
      if ( empty(self::$dbhandle->error) ) {/*{{{*/
        $this->update_joincache_attrs();
      }/*}}}*/
      // Execute suppressed reinitialization calls.
      $this->clear_query_prereqs();
      $this->init_query_prereqs();
      return $query_result;
    }/*}}}*/

    $this->suppress_reinitialize = FALSE;
    return $this->query($sql);

  }/*}}}*/

  private function update_joincache_attrs() {/*{{{*/
    $debug_method = C('DEBUG_'.__FUNCTION__,FALSE);
    // Only perform Join updates if the insert or update operation succeeds
    if ( 0 < count($this->join_value_cache) ) {
      if ( $debug_method ) $this->syslog(__FUNCTION__, __LINE__, "(marker) - - - Updating joins for record #{$this->id}" );
      foreach ( $this->join_value_cache as $attr => $data_src ) {/*{{{*/
        // Suppress attributes in data_src that are NOT in the actual
        // model structure, by obtaining the intersection of the full property list

        if ( $debug_method ) $this->syslog(__FUNCTION__,__LINE__,"(marker)  For {$attr}");

        $joinprops = $this->get_join_instance($attr)->fetch_combined_property_list();

        foreach ( $data_src as $fkey => $data ) {/*{{{*/
          if ( !is_array($data) ) continue;
          $intersect = array_intersect_key(
            $data,
            $joinprops
          );
          if ($debug_method) {/*{{{*/
            $this->syslog(__FUNCTION__,__LINE__,"(marker)  Record {$fkey} - - - - - - -");
            $this->recursive_dump($intersect,"(marker) -#- --");
            $this->recursive_dump($data,"(marker) -- -#-");
          }/*}}}*/
          $data = $intersect;

          $joinattrs = $this->get_join_object($attr,'join','set');
          unset($data['fkey']);
          if ( $debug_method ) $this->recursive_dump($joinattrs,"(marker) - - JA - -");
          $data[$joinattrs['self']] = $this->id;
          $data[$joinattrs['fkey']] = $fkey;
          if ( empty($this->id) ) continue;
          if ( $fkey == 'UNCACHED' ) continue;
          if ( $fkey == 'UNMAPPED' ) continue;
          $this->get_join_instance($attr)->fetch($data,'AND');
          $this->supppress_reinitialize = TRUE;
          if ( $debug_method ) $this->recursive_dump($data,"(marker) - - J - -");
          if ( !$this->get_join_instance($attr)->in_database() ) {
            $data['create_time'] = time();
            $join_id = $this->get_join_instance($attr)->set_contents_from_array($data)->stow();
            if (empty(self::$dbhandle->error)) {
              if ( $debug_method ) $this->syslog(__FUNCTION__, __LINE__, "(marker) - - - - Added join {$attr}[{$join_id}]: " . join(',',$data) );
            } else {
              $this->syslog(__FUNCTION__, __LINE__, "(error) - - - - Failed to add join {$attr}[{$join_id}]: " . join(',',$data) );
            }
          } else {
            $join_id = $this->get_join_instance($attr)->get_id();
            if ( $debug_method ) $this->syslog(__FUNCTION__, __LINE__, "(marker) - - - - Found join {$attr}[{$join_id}]: " . join(',',$data) );
          }
          // We must do this after each call to query(),
          // otherwise $join_value_cache is cleared before we complete
          // iterating through it.
          $this->supppress_reinitialize = TRUE;
        }/*}}}*/
      }/*}}}*/

      $this->suppress_reinitialize = FALSE;
    }
  }/*}}}*/

  function insert() {/*{{{*/
    $attrlist    = $this->full_property_list();
    $this->last_inserted_id = NULL;
    $namelist   = join(',', array_map(create_function('$a', 'return "`{$a["name"]}`";'), $attrlist));
    $valueset   = join(',', array_map(create_function('$a', 'return $a["attrs"]["mustbind"] == TRUE ? "?" : "{$a["value"]}";'),  $attrlist));
    $boundattrs = array_filter(array_map(create_function('$a', 'return $a["attrs"]["mustbind"] == TRUE ? "{$a["name"]}" : NULL;'),  $attrlist));
    $sql = <<<EOS
INSERT INTO `{$this->tablename}` ({$namelist}) VALUES ({$valueset})
EOS;
    $this->insert_update_common($sql, $boundattrs, $attrlist);
    $attrlist   = NULL;
    $namelist   = NULL;
    $valueset   = NULL;
    $boundattrs = NULL;
    if ( !empty(self::$dbhandle->error) ) {
      $this->syslog( __FUNCTION__, __LINE__, "(critical) ERROR: " . self::$dbhandle->error ); // throw new Exception("Failed to execute SQL: {$sql}");
    } else {
      if ( $this->debug_final_sql ) $this->syslog(__FUNCTION__, __LINE__, "(marker) Inserted record #{$this->last_inserted_id}: {$sql}" );
    }
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

  function & limit($n, $offset = NULL) {/*{{{*/
    $this->limit = is_null($n)
      ? array()
      : array(
        'n' => intval($n),
        'o' => intval($offset)
      );
    return $this;
  }/*}}}*/

  function & join(array $ja) {/*{{{*/
    // Accept an array of either
    // - Simple Join attribute names, or
    // - Attribute selectors (see JoinFilterUtility) 
    $this->join_attrs = array_merge(
      $this->join_attrs,
      $ja
    );
    return $this;
  }/*}}}*/

  function & fields($f) {/*{{{*/
    $this->subject_fields = is_array($f) ? array_values($f) : explode(',',$f);
    return $this;
  }/*}}}*/

	function & set_merge_wherecond() {
		$this->merge_wherecond = TRUE;
		return $this;
	}

  function & where($conditions) {/*{{{*/
    // TODO: Append conditions to an existing set.  This is useful mainly in prepare_select_sql() (ca. SVN #562) where this method is used to capture JOIN conditions 
		if ( $this->merge_wherecond ) {
			$this->syslog(__FUNCTION__,__LINE__,"(marker) +++++++++ Existing conditions");
			$this->recursive_dump($this->query_conditions,"(marker) +++++");
			$this->syslog(__FUNCTION__,__LINE__,"(marker) --------- Additional conditions");
			$this->recursive_dump($conditions,"(marker) -----");
			$this->merge_wherecond = FALSE;
		}
    if ( is_array($conditions) ) { 
      $this->query_conditions = $conditions;
    }
    return $this;
  }/*}}}*/

  function resultset() {/*{{{*/
    $this->initialize_db_handle();
    return self::$dbhandle->resultset();
  }/*}}}*/

	// End-user/admin formatter methods

	function remap_url(& $url) {
		// Use a per-Model URL mapper to convert links for use by either
		// an admin (crawler) user or a site end-user.
		return method_exists($this, "remap_url_links") 
			? $this->remap_url_links($url)
			: $url;
	}

  // Experimental HTTP action adaptor methods

  function & get() { return $this; }
  function & post() { return $this; }
  function & put() { return $this; }
  function & trace() { return $this; }
  function & head() { return $this; }
  function & delete() { return $this; }

  final protected function logging_ok($prefix) {/*{{{*/
    if ( static::$force_log ) return TRUE; 
    if ( 1 == preg_match('@(ERROR|CRITICAL)@i', $prefix) ) return TRUE;
    if ( TRUE == C('DEBUG_ONLY_ADMIN') && !C('CONTEXT_ADMIN') ) return FALSE;
    if ( TRUE == C('DEBUG_ONLY_ENDUSER') && !C('CONTEXT_ENDUSER') ) return FALSE;
    if ( 1 == preg_match('@(WARNING|ERROR|MARKER)@i', $prefix) ) return TRUE;
    if ( TRUE === C('DEBUG_ALL') ) return TRUE;
    if ( 1 == preg_match('@([(]SKIP[)])@i', $prefix) ) return FALSE;
    if ( FALSE === C('DEBUG_'.get_class($this)) ) return FALSE;
    return ( (0 == count($this->disable_logging)) ||
      !array_key_exists(get_class($this), array_flip($this->disable_logging))
    );
  }/*}}}*/

  final function & syslog($fxn, $line, $message) {/*{{{*/
    if ( $this->logging_ok($message) ) { 
      $message = str_replace('(marker)','',$message);
      syslog( LOG_INFO, $this->syslog_preamble($fxn, $line) . " {$message}" );
      if ( !(FALSE === C('SLOW_DOWN_RECURSIVE_DUMP')) ) usleep(C('SLOW_DOWN_RECURSIVE_DUMP'));
    }
    return $this;
  }/*}}}*/

  final protected function syslog_preamble($fxn, $line) {/*{{{*/
    $line = is_null($line) ? "" : "({$line})";
    return (C('DEBUGLOG_FILENAME') ? join('.',array_reverse(camelcase_to_array(get_class($this)))) . '.php' : get_class($this)) . " :".(wp_get_current_user()->exists() ? "[*]" : NULL).": {$fxn}{$line}: ";
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

  final function & recursive_dump($a, $prefix = NULL) {/*{{{*/
    if ( !is_array($a) ) return;
    if ( !$this->logging_ok($prefix) ) return;
    $this->recursive_dump_worker($a, 0, $prefix);
    return $this;
  }/*}}}*/

  final private function recursive_dump_worker($a, $depth = 0, $prefix = NULL) {/*{{{*/
    if ( !(FALSE === C('SLOW_DOWN_RECURSIVE_DUMP')) ) usleep(C('SLOW_DOWN_RECURSIVE_DUMP'));
    foreach ( $a as $key => $val ) {
      $logstring = is_null($prefix) 
        ? basename(__FILE__) . "::" . __LINE__ . "::" . __FUNCTION__ . ": "
        : (C('DEBUGLOG_FILENAME') ? join('.',array_reverse(camelcase_to_array(get_class($this)))) . '.php' : get_class($this)) . " :{$prefix}: "
        ;

      $logstring = preg_replace('@\(marker\)([ ]*)@i','',$logstring);
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
        else if ( empty($val) ) {
          if ( strlen("{$val}") == 0 ) 
            $logstring .= "[EMPTY]";
          else if ( 0 === $val || "0" == "{$val}" )
            $logstring .= "0";
          else
            $logstring .= "[EMPTY]";
        }
        else
          $logstring .= substr("{$val}",0,500) . (strlen("{$val}") > 500 ? '...' : '');
        syslog( LOG_INFO, $logstring );
      }
    }
  }/*}}}*/

  function fetch_combined_property_list() {/*{{{*/
    $property_list = $this->fetch_property_list();
    if ( !(0 < count($property_list)) ) return array();
    $property_list = array_combine(
      array_map($this->slice('name'),$property_list),
      array_map($this->slice('type'),$property_list)
    );
    return $property_list;
  }/*}}}*/

  function & set_contents_from_array($document_contents, $execute_setters = TRUE) {/*{{{*/
    $debug_method = C('DEBUG_'.__FUNCTION__,FALSE);
    $property_list = $this->fetch_combined_property_list();
    if ( !(0 < count($property_list)) ) return $this;
    $joins = $this->get_joins();
    foreach ( $document_contents as $name => $value ) {
      $setter = "set_{$name}";
      if ( method_exists($this, $setter) ) {
        if ( $execute_setters ) $this->$setter($value);
      } else if ( array_key_exists($name, $joins) ) {
        // Attribute [$name] is a Join attribute
        $marker = $debug_method ? 'marker' : '-----';
        if ( $debug_method ) {
          $this->syslog(__FUNCTION__, __LINE__, <<<EOP
({$marker}) Join component {$name}
EOP
          );
        }
        if ( !array_key_exists($name,$this->join_value_cache) ) {
          $this->join_value_cache[$name] = array();
        }
        $fkey = array_element($value,'fkey');
        if ( is_null($fkey) ) {
          $this->join_value_cache[$name]['UNCACHED'] = $value;
        } else {
          $this->join_value_cache[$name][$fkey] = $value;
        }

      } else {
        // Attribute [$name] is an atomic attribute 
        $type = array_key_exists($name, $property_list) ? $property_list[$name] : NULL;
        $marker = TRUE || $debug_method ? 'marker' : '-----';
        $this->syslog(__FUNCTION__, __LINE__, <<<EOP
({$marker})
function & {$setter}(\$v) { \$this->{$name}_{$type} = \$v; return \$this; }
function get_{$name}(\$v = NULL) { if (!is_null(\$v)) \$this->{$setter}(\$v); return \$this->{$name}_{$type}; }

EOP
        );
      }
    }

    if ( $debug_method ) {
      $this->syslog(__FUNCTION__,__LINE__,"(marker)  - - - - - - - BULAGA! {$this->id} - - - - - - - ");
      $this->recursive_dump($this->join_value_cache,"({$marker}) - -"); 
    }

    return $this;
  }/*}}}*/

  function slice($s) {/*{{{*/
    // Duplicated in RawparseUtility
    return create_function('$a', 'return $a["'.$s.'"];');
  }/*}}}*/

  function dump_accessor_defs_to_syslog() {/*{{{*/
    // FIXME: Remove this debugging method pre-R1
    $debug_method = C('DEBUG_'.__FUNCTION__,FALSE);
    $data_items = array_flip(array_map($this->slice('name'), $this->fetch_property_list()));
    if ( $debug_method ) $this->recursive_dump($data_items,'(marker) ' . __METHOD__);
    $this->set_contents_from_array($data_items,FALSE);
  }/*}}}*/

  function get_id() {/*{{{*/
    return $this->in_database() ? $this->id : NULL;
  }/*}}}*/

  function get_map_functions($docpath, $d = 0) {/*{{{*/

    return ArrayFilterUtility::get_map_functions($docpath, $d);

  }/*}}}*/

  function & reorder_with_sequence_tags(& $c) {/*{{{*/
    // Reorder containers by stream context sequence number
    // If child tags in a container possess a 'seq' ordinal value key (stream/HTML rendering context sequence number),
    // then these children are reordered using that ordinal value.
    if ( is_array($c) ) {
      if ( array_key_exists('children', $c) ) return $this->reorder_with_sequence_tags($c['children']);
      $sequence_num = create_function('$a', 'return is_array($a) ? array_element($a,"seq",array_element(array_element($a,"attrs",array()),"seq")) : NULL;');
      $filter_src   = create_function('$a', '$rv = (is_array($a) && array_key_exists("seq",$a)) ? $a : (is_array($a) && is_array(array_element($a,"attrs")) && array_key_exists("seq",$a["attrs"]) ? $a : NULL); if (!is_null($rv)) { unset($rv["attrs"]["seq"]); unset($rv["seq"]); }; return $rv;');
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
        if ( is_array($a) ) {
          $a = array_filter(array_map(create_function('$a',$map), $a));
          if ( $this->debug_operators ) {/*{{{*/
            $n = count($a);
            $this->syslog(__FUNCTION__,__LINE__,"A <<<<<< (marker) N = {$n} Map: {$map}");
          }/*}}}*/
          $this->resequence_children($a);
        }
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

  /** Join model object methods **/

  function & get_join_object($property, $which, $meta = 'obj') {/*{{{*/
    $debug_method = C('DEBUG_'.__FUNCTION__,FALSE);
    $join_desc = $this->get_joins($property);
    $join_desc_key = array_element($join_desc,'joinobject');
    if ( empty($join_desc_key) || !(0 < count($join_desc)) ) {
      $this->syslog(__FUNCTION__,__LINE__, "(warning) - - - - - - - - - - Invalid Join participant class name '{$join_desc_key}' or invalid property '{$property}' given. Bailing out.");
      $this->syslog(__FUNCTION__,__LINE__, "(warning) - - - - - - - - - - This could indicate bad join() parameters");
      return $this->null_instance;
    }
    try {
      if ( !array_key_exists($join_desc_key, $this->join_instance_cache) ) {
        if ( $debug_method )
        $this->syslog(__FUNCTION__,__LINE__, "(warning) - - - - - - - - - - Instantiating objects for {$which} property '{$property}'");
        $this->join_instance_cache[$join_desc_key] = array(
          'join' => array(
            'obj' => new $join_desc['joinobject'](),
            'set' => NULL,
          ),
          'ref'  => array(
            'obj' => new $join_desc['propername'](),
          )
        );
        // Get names of Join attributes, to enable us to find the names of
        // a Join's 'left' (self) and 'right' (foreign table) members
        $joinattrs = $this->join_instance_cache[$join_desc_key]['join']['obj']->fetch_combined_property_list();
        $attrnames = array_keys($joinattrs);
        $attrtypes = array_flip(array_values($joinattrs));
        if ( get_class($this) == $join_desc['propername'] ) {
          $map = $joinattrs;
          array_walk($map,create_function(
            '& $a, $k, $s', '$a = (1 == preg_match("@^(left|right)_@i",$k)) && ($a == $s) ? preg_replace(array("@^((left_).*)@i","@^((right_).*)@i"),array("self","fkey"),$k) : NULL;'
          ), get_class($this));
          $map = array_flip(array_filter($map));
          if ( $debug_method ) {/*{{{*/
            $this->recursive_dump($map      , "(marker) --- -M- ---");
            $this->recursive_dump($joinattrs, "(marker) --- --- J-J");
            $this->recursive_dump($attrnames, "(marker) --- J-J ---");
            $this->recursive_dump($attrtypes, "(marker) J-J --- ---");
          }/*}}}*/
          $this->join_instance_cache[$join_desc_key]['join']['set'] = $map; 
          if ( $debug_method )  $this->recursive_dump($this->join_instance_cache[$join_desc_key]['join']['set'],"(marker) --- --- ---");
        } else {
          if ( $debug_method ) {/*{{{*/
            $this->recursive_dump($map      , "(marker) --- -M- ---");
            $this->recursive_dump($joinattrs, "(marker) --- --- J-J");
            $this->recursive_dump($attrnames, "(marker) --- J-J ---");
            $this->recursive_dump($attrtypes, "(marker) J-J --- ---");
          }/*}}}*/
          $this->join_instance_cache[$join_desc_key]['join']['set'] = array(
            'self' => array_element($attrnames,array_element($attrtypes,get_class($this))),
            'fkey' => array_element($attrnames,array_element($attrtypes,$join_desc['propername'])),
          ); 
          if ( $debug_method )  $this->recursive_dump($this->join_instance_cache[$join_desc_key]['join']['set'],"(marker) --- --- ---");
        }
        if ( $debug_method ) $this->recursive_dump($this->join_instance_cache[$join_desc_key]['join']['set'],"(marker) - - - - JD({$property})");
      }
    } catch ( Exception $e ) {
      $this->syslog(__FUNCTION__,__LINE__, "(warning) - - - - - - - - - - ERROR: Failed to create instance of '{$which}' property {$property} object {$join_desc[$join_desc_key]}");
      return $this->null_instance;
    }
    return $this->join_instance_cache[$join_desc_key][$which][$meta];
  }/*}}}*/

  function & get_foreign_obj_instance($property) {/*{{{*/
    return $this->get_join_object($property,'ref');
  }/*}}}*/

  function & get_join_instance($property) {/*{{{*/
    return $this->get_join_object($property,'join');
  }/*}}}*/

  function get_all_properties() {/*{{{*/
    $debug_method = C('DEBUG_'.__FUNCTION__,FALSE);
    $d = array();
    foreach ( $this->full_property_list() as $attr => $props ) {
      if ( $debug_method ) $this->syslog(__FUNCTION__,__LINE__,"(marker) - - - Get {$attr}");
      $getter = "get_{$attr}";
      if ( method_exists($this,$getter) ) {
        $d[$attr] = $this->$getter();
      } else if ( property_exists($this,$attr) ) {
        $d[$attr] = $this->$attr;
      }
    }
    // This only works if a getter method is defined for the Join attribute 
    foreach ( $this->get_joins() as $attr => $props ) {
      if ( $debug_method ) $this->syslog(__FUNCTION__,__LINE__,"(marker) - - - Get {$attr}");
      $getter = "get_{$attr}";
      if ( method_exists($this,$getter) ) {
        $d[$attr] = $this->$getter();
      } else if ( property_exists($this,$attr) ) {
        $d[$attr] = $this->$attr;
      }
    }
    return $d;
  }/*}}}*/

  function get_stuffed_join($join_attrname) {/*{{{*/
    $debug_method = C('DEBUG_'.__FUNCTION__,FALSE);
    // Return an object representing the 'far end' of a Join attribute
    if ( !is_null($joined_document = array_element($this->get_all_properties(),$join_attrname))) {
      if ( !is_null($data = array_element($joined_document,'data')) ) {/*{{{*/
        $this->
          get_foreign_obj_instance($join_attrname)->
          join_all()->
          set_contents_from_array($data,TRUE)->
          where(array('AND' => array(
            '`a`.`id`' => $data['id'],
          )))->
          recordfetch_setup();
        $joined_document = array();
        if ( $this->get_foreign_obj_instance($join_attrname)->recordfetch($joined_document,TRUE) ) {
          if ( $debug_method ) {
            $this->syslog(__FUNCTION__,__LINE__,"(marker) -- - - - J {$join_attrname} - - - -");
            $this->recursive_dump($joined_document,"(marker) -- - - -");
          }
          $setter = "set_data_{$join_attrname}";
          if ( method_exists($this,$setter) ) $this->$setter($joined_document);
          return $joined_document;
        }  
      }/*}}}*/
    }
    return NULL;
  }/*}}}*/
    
  function & set_dtm_attr($v,$attrname) {/*{{{*/
    // Set a _dtm (SQL datetime) attribute 
    $attrname = "{$attrname}_dtm";
    if ( !property_exists($this,$attrname) ) return $this;
    $datetime_attrib = strtotime($v);
    $date = new DateTime();
    $date->setTimestamp($datetime_attrib);
    $this->$attrname = $date->format(DateTime::ISO8601); 
    $date = NULL;
    unset($date);
    return $this;
  }/*}}}*/

  function get_dtm_attr($attrname,$fmt = 'F j, Y') {/*{{{*/
    // Return a datetime attribute, formatted using $fmt
    $attrname = "{$attrname}_dtm";
    if ( !property_exists($this,$attrname) ) return FALSE;
    $datetime_attrib = strtotime($this->$attrname);
    $date = new DateTime();
    $date->setTimestamp($datetime_attrib);
    $datetime_attrib = $date->format($fmt); 
    $date = NULL;
    return $datetime_attrib;
  }/*}}}*/

  function & set_id($v) { $this->id = $v; return $this; }

	function iconv($s) {/*{{{*/
		return iconv( strtoupper($this->content_type), 'UTF-8//TRANSLIT', $s );
	}/*}}}*/

	function reverse_iconv($s) {/*{{{*/
		return iconv( 'UTF-8', strtoupper($this->content_type), $s );
	}/*}}}*/

}
