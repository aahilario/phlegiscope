<?php

class RawparseUtility extends SystemUtility {/*{{{*/

  protected $headerset            = array();
  protected $parser               = NULL;
  protected $current_tag          = NULL;
  protected $tag_stack            = array();
  protected $links                = array();
  protected $container_stack      = array();
  private $containers             = array();
  protected $filtered_doc         = array();
  protected $custom_parser_needed = FALSE;
  protected $page_url_parts       = array();
  protected $removable_containers = array();
  private $hash_generator_counter = 0;
  private $tag_counter            = 0;

  /*
   * HTML document XML parser class
   */

  function __construct() {/*{{{*/
    $this->initialize();
  }/*}}}*/
  
  protected function initialize() {/*{{{*/
    $this->parser = xml_parser_create('UTF-8');
    xml_set_object($this->parser, $this);
    xml_set_element_handler($this->parser, 'ru_tag_open', 'ru_tag_close');
    xml_set_character_data_handler($this->parser, 'ru_cdata');
    xml_set_default_handler($this->parser, 'ru_default');
    xml_parser_set_option($this->parser, XML_OPTION_CASE_FOLDING, 1 );
    xml_parser_set_option($this->parser, XML_OPTION_SKIP_WHITE, 1 );
    xml_parser_set_option($this->parser, XML_OPTION_TARGET_ENCODING, 'UTF-8');
  }/*}}}*/

  function attributes_as_string($attrs, $as_array = FALSE, $concat_with = " ") {/*{{{*/
    $attrstr = array();
    foreach ( $attrs as $key => $val ) {
      $key = strtolower($key);
      $attrstr[$key] = <<<EOH
{$key}="{$val}"
EOH;
    }
    if ( !$as_array ) $attrstr = join($concat_with, $attrstr);
    // $this->syslog(__FUNCTION__,__LINE__,"- {$attrstr}");
    return $attrstr;
  }/*}}}*/

  function needs_custom_parser() {/*{{{*/
    return $this->custom_parser_needed;
  }/*}}}*/

  function & set_parent_url($url) {/*{{{*/
    $this->page_url_parts = UrlModel::parse_url($url);
    return $this;
  }/*}}}*/

  function & reorder_with_sequence_tags(& $c) {/*{{{*/
    // Reorder containers by stream context sequence number
    // If child tags in a container possess a 'seq' ordinal value key (stream/HTML rendering context sequence number),
    // then these children are reordered using that ordinal value.
    if ( is_array($c) ) {
      if ( array_key_exists('children', $c) ) return $this->reorder_with_sequence_tags($c['children']);
      $sequence_num = create_function('$a', 'return is_array($a) && array_key_exists("seq",$a) ? $a["seq"] : (is_array($a["attrs"]) && array_key_exists("seq",$a["attrs"]) ? $a["attrs"]["seq"] : NULL);');
      $filter_src   = create_function('$a', '$rv = is_array($a) && array_key_exists("seq",$a)  ? $a        : (is_array($a["attrs"]) && array_key_exists("seq",$a["attrs"]) ? $a : NULL); if (!is_null($rv)) { unset($rv["attrs"]["seq"]); unset($rv["seq"]); }; return $rv;');
      $containers   = array_filter(array_map($sequence_num, $c));
      if ( is_array($containers) && (0 < count($containers))) {
        $containers = array_combine(
          $containers,
          array_filter(array_map($filter_src, $c))
        );
        if ( is_array($containers) ) {
          ksort($containers);
          $c = $containers;
        }
      } else {
        
      }
    }
    return $this;
  }/*}}}*/

  function & mark_container_sequence() {/*{{{*/
    $this->reorder_with_sequence_tags($this->containers);
    return $this;
  }/*}}}*/

  function get_map_functions($docpath, $d = 0) {/*{{{*/
    // A mutable alternative to XPath
    // Extract content from parse containers
    $map_functions = array();
    if ( $d > 4 ) return $map_functions;
    $selector_regex = '@({([^}]*)}|\[([^]]*)\]|(([-_0-9a-z=]*)*)[,]*)@';
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
        $returnable_map   = create_function('$a', 'return "\$a[\"{$a}\"]";');
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

  function resequence_children(& $containers) {/*{{{*/
    return array_walk(
      $containers,
      // create_function('& $a, $k, $s', 'if ( array_key_exists("children",$a) ) $s->reorder_with_sequence_tags($a["children"]);'),
      create_function('& $a, $k, & $s', '$s->reorder_with_sequence_tags($a);'),
      $this
    );
  }/*}}}*/

  function & get_containers($docpath = NULL) {
    $this->filtered_containers = array();
    foreach ( $this->removable_containers as $remove ) {
      unset($this->containers[$remove]);
    }
    $this->removable_containers = array();
    if ( !is_null($docpath) ) {
      $filter_map = $this->get_map_functions($docpath);
      if ( $this->debug_operators ) {
        $this->syslog(__FUNCTION__,__LINE__,"------ (marker) Containers to process: " . count($this->containers));
        $this->recursive_dump($filter_map,'(marker)');
      }
      $this->filtered_containers = $this->containers;
      foreach ( $filter_map as $i => $map ) {
        if ( $i == 0 ) {
          if ( $this->debug_operators ) {
            $n = count($this->filtered_containers);
            $this->syslog(__FUNCTION__,__LINE__,"A ------ (marker) N = {$n} Map: {$map}");
          }
          $this->filtered_containers = array_filter(array_map(create_function('$a',$map), $this->filtered_containers));
          if ( $this->debug_operators ) {
            $n = count($this->filtered_containers);
            $this->syslog(__FUNCTION__,__LINE__,"A <<<<<< (marker) N = {$n} Map: {$map}");
          }
          $this->resequence_children($this->filtered_containers);
        } else {
          if ( $this->debug_operators ) {
            $this->syslog(__FUNCTION__,__LINE__,"B ------ (marker) N = {$n} Map: {$a}");
          }
          foreach ( $this->filtered_containers as $seq => $m ) {
            $this->filtered_containers[$seq] = array_filter(array_map(create_function('$a',$map), $m));
          }
        }
        if ( $this->debug_operators ) {
          $this->syslog(__FUNCTION__,__LINE__,"(marker) - Map #{$i} - {$map}");
          $this->recursive_dump($this->filtered_containers,'(marker)');
        }
      }
      return $this->filtered_containers;
    }
    return $this->containers;
  }

  function & get_headers() {
    return $this->headerset;
  }

  function & get_links() {
    return $this->links;
  }

  function & get_filtered_doc() {
    return $this->filtered_doc;
  }

  function slice($s) {
    // Duplicated in DatabaseUtility
    return create_function('$a', 'return $a["'.$s.'"];');
  }

  function strip_headers($data) {/*{{{*/

    $is_headerline    = 0;
    $header_set_index = 0;
    $fragment         = ""; 
    $rawhtml          = array(); 
    $this->headerset  = array();

    // Skip response headers (storing them in headerset[] for later use)
   
    foreach ( $data as $fragment ) {
      $fragment = str_replace(array('&nbsp;','&'),array('','&amp;'),$fragment);
      $fragment = trim($fragment);
      if ( !is_null($is_headerline) ) {
        if ( 1 == preg_match('@^HTTP/1.1@', $fragment) ) {
          $is_headerline = TRUE;
        } else if ($is_headerline == FALSE) {
          // If we are no longer in a header line block, set is_headerline = NULL
          $is_headerline = NULL;
        }
        if ( !is_null($is_headerline) && $is_headerline ) {
          $this->headerset[$header_set_index][] = $fragment;
        }
        if ( 0 == strlen($fragment) ) {
          // An empty line separates groups response header lines from each other,
          // and from the main body of content.
          $header_set_index++;
          $is_headerline = FALSE;
        }
        if ( !is_null($is_headerline) ) continue;
      }
      $rawhtml[] = $fragment;
    }

    $rawhtml = join(" ", $rawhtml);

    return $rawhtml;
  }/*}}}*/


  function reset() {/*{{{*/
    if ( !is_null($this->parser) ) {
      xml_parser_free($this->parser);
      $this->parser = NULL;
    }
    $this->initialize();
    $this->current_tag     = NULL;
    $this->tag_stack       = array();
    $this->links           = array();
    $this->container_stack = array();
    $this->containers      = array();
    $this->filtered_doc    = array();
    return $this;
  }/*}}}*/

  function parse_html(& $raw_html, array $response_headers, $only_scrub = FALSE) {/*{{{*/

    $this->reset();

    if ( empty($raw_html) ) {
      $this->syslog(__FUNCTION__,__LINE__, "(warning) Nothing to parse. Returning NULL");
       return NULL;
    }

    $raw_html = join('',array_filter(explode("\n",str_replace(
      array(
        "\r",
        '><',
      ),
      array(
        "\n",
        ">\n<",
      ), 
      preg_replace(
        array(
          '@(<\!doctype(.*)>)@im',
          '/<html([^>]*)>/imU',
          '/>([ ]*)</m',

          '/<!--(.*)-->/imUx',
          '@<noscript>(.*)</noscript>@imU',
          '@<script([^>]*)>(.*)</script>@imU',

          //'@([[:cntrl:]]*)@',
        ),
        array(
          '',
          '<html>',
          '><',

          '',
          '',
          '',

          //'',
        ),
        $raw_html
      )
    ))));

    libxml_use_internal_errors(TRUE);
    libxml_clear_errors();

    $doctype_match = 'content-type';
    if ( !array_key_exists($doctype_match, $response_headers) ) {
      $this->syslog(__FUNCTION__,__LINE__, "(warning) Missing key '{$doctype_match}' for encoding check. Count " . count($response_headers) );
      $this->recursive_dump($response_headers,'(warning) - Must use doctype');
    }

    $doctype_match = array(); 
    $content_type = array_key_exists('content-type', $response_headers) &&
      1 == preg_match('@charset=([^;]*)@', $response_headers['content-type'], $doctype_match)
      ? strtolower($doctype_match[1])
      : 'iso-8859-1' // Default assumption
      ;
    $this->syslog(__FUNCTION__,__LINE__, "(warning) Assuming encoding '{$content_type}'" );
    // Parse HTML into structure
    $target_struct            = array();
    $struct_index             = NULL;
    $dom                      = new DOMDocument();
    $dom->recover             = TRUE;
    $dom->preserveWhiteSpace  = FALSE;
    $dom->strictErrorChecking = FALSE;
    $dom->substituteEntities  = FALSE;

    if ( $content_type == 'iso-8859-1' ) {
      $loadresult   = $dom->loadHTML($raw_html);
    } else if ( $content_type == 'utf-8' ) {
      $loadresult   = $dom->loadHTML($raw_html);
    } else {
      $loadresult   = $dom->loadHTML($raw_html);
    }
    $dom->normalizeDocument();

    if ( !$loadresult ) $this->syslog( __FUNCTION__, __LINE__, "-- WARNING: Failed to filtering HTML as XML, load result FAIL" );

    $this->syslog(__FUNCTION__,__LINE__, "--------------------------- " . __LINE__ );
    xml_parse($this->parser, $dom->saveXML());
    $xml_errno = xml_get_error_code($this->parser);

    if ( $xml_errno == 0 ) {

      $this->syslog(__FUNCTION__,__LINE__, "--------------------------- " . __LINE__ );
      $dom->loadXML($raw_html);
      $dom->formatOutput = TRUE;
      $raw_html = $dom->saveHTML();
      $this->syslog(__FUNCTION__,__LINE__, " - Parse OK: \n" . substr($raw_html,0,500));

    } else {

      $this->syslog(__FUNCTION__,__LINE__, "--------------------------- " . __LINE__ );
      $error_offset = xml_get_current_byte_index($this->parser);
      $xml_errstr = xml_error_string($xml_errno);
      $this->syslog( __FUNCTION__, __LINE__, 
        "PARSE ERROR #{$xml_errno}: {$xml_errstr} " . 
        "offset {$error_offset} context " . substr($dom->saveXML(), $error_offset - 100, 200)
      );
      $errors = libxml_get_errors();
      foreach ($errors as $error) {
        $this->syslog(__FUNCTION__,__LINE__,"- Parse err @ line {$error->line} col {$error->column}: {$error->message}");
      }

    }

    $only_non_empty = create_function('$a', 'return 0 < count($a["children"]) ? $a : NULL;');
    $this->containers = array_filter(array_map($only_non_empty,$this->containers));
    if ( $this->debug_tags ) $this->recursive_dump($this->containers,'(marker)');

    return $this->containers;

  }/*}}}*/

  function parse($data) {/*{{{*/
    // Expects cURL response with headers prepended
    $rawhtml = $this->strip_headers($data);
    $this->parse_html($rawhtml);
  }/*}}}*/

  // ------------- XML parser callbacks for elements of interest in an HTML document  -------------

  function & pop_tagstack() {/*{{{*/
    $this->current_tag = array_pop($this->tag_stack);
    return $this->current_tag;
  }/*}}}*/

  function push_tagstack($substitute = NULL) {/*{{{*/
    array_push($this->tag_stack, is_null($substitute) ? $this->current_tag : $substitute);
  }/*}}}*/

  function get_stacktags() {/*{{{*/
    $tagstack_tags = create_function('$a', 'return $a["tag"];');
    $tagstack_stack = array_filter(array_map($tagstack_tags, $this->tag_stack));
    $topmost = $this->tag_stack[count($this->tag_stack)-1]['tag'];
    $parent = $this->tag_stack[count($this->tag_stack)-2]['tag'];
    return "{$parent} <- {$topmost} [" . join(',',$tagstack_stack) . ']';
  }/*}}}*/

  function parent_tag() {/*{{{*/
    return 2 < count($this->tag_stack) ? $this->tag_stack[count($this->tag_stack)-2]['tag'] : NULL;
  }/*}}}*/

  function & parent_item() {/*{{{*/
    return 2 < count($this->tag_stack) ? $this->tag_stack[count($this->tag_stack)-2] : $this->current_tag;
  }/*}}}*/

  function ru_tag_open($parser, $tag, $attrs) {/*{{{*/
    $tag = strtoupper($tag);
    // $this->syslog(__FUNCTION__,__LINE__,">>>>>>>>>>>>>>>> {$tag}" );
    if ( $tag == 'HTTP:' ) return; 
    $tag_handler = strtolower("ru_{$tag}_open");
    $current_tag = array(
      'tag' => $tag, 
      'attrs' => is_array($attrs) ? array_merge( $attrs, array('seq' => $this->tag_counter) ) : array('seq' => $this->tag_counter),
      'position' => NULL,
    );
    $this->current_tag = $current_tag;
    array_push($this->tag_stack, $current_tag);
    $result = ( method_exists($this, $tag_handler) )
      ? $this->$tag_handler($parser, $attrs, strtolower($tag))
      : TRUE 
      ;
    $this->pop_tagstack();
    if ( $result) {
      if ( 0 < count($this->current_tag['attrs']) ) {
        $attrs = $this->attributes_as_string($this->current_tag['attrs']);
        $tag .= " {$attrs}";
      }
      $this->filtered_doc[] = <<<EOH
<{$tag}>
EOH;
    }
    $this->current_tag['position'] = count($this->filtered_doc);
    $this->push_tagstack();
    $this->tag_counter++;
    return TRUE;
  }/*}}}*/

  function ru_tag_close($parser, $tag) {/*{{{*/
    $tag = strtoupper($tag);
    $tag_handler = strtolower("ru_{$tag}_close");
    $result = ( method_exists($this, $tag_handler) )
      ? $this->$tag_handler($parser, $tag)
      : TRUE 
      ;
    $this->pop_tagstack();
    if ($result) {
      $tag_cdata = ( is_array($this->current_tag) && array_key_exists('cdata',$this->current_tag) && is_array($this->current_tag['cdata']) )
        ? join(' ',$this->current_tag['cdata'])
        : NULL
        ;
      $this->filtered_doc[] = <<<EOH
{$tag_cdata}
</{$tag}>
EOH;
    }
    else {
      // Remove all content added to the filtered document array
      // since the opening tag was inserted into the tag stack
      $start = $this->current_tag['position'];
      $end = count($this->filtered_doc);
      $last_removed = "[NONE]";
      // $this->syslog(__FUNCTION__,__LINE__, "Removing children of {$tag} from {$start} to {$end}");
      for ( ; $start <= $end; $start++ ) { $last_removed = array_pop($this->filtered_doc); }
      // $this->syslog(__FUNCTION__,__LINE__, "Removing children of {$tag} from {$this->current_tag['position']} to {$end} ({$last_removed})");

      // If this tag is a container, remove the container stack entry associated with this tag
      if ( array_key_exists('CONTAINER', $this->current_tag) ) {
        $container_id_hash = $this->current_tag['CONTAINER'];
        if ( array_key_exists($container_id_hash, $this->containers) ) {
          // $this->syslog(__FUNCTION__,__LINE__, "Removing container {$this->current_tag['tag']} with set hash {$container_id_hash}");
          unset($this->containers[$container_id_hash]);
        } else {
          // $this->syslog(__FUNCTION__,__LINE__, "Deferring removal of container {$container_id_hash}");
          $this->removable_containers[] = $container_id_hash;
        }
      }
    }
    return TRUE;
  }/*}}}*/

  function ru_cdata($parser, $cdata) {/*{{{*/
    // Character data will always be contained within a parent container.
    // If there is no handler specified for a given tag, the topmost 
    // tag on the tag stack receives link content.
    $parser_result = TRUE;
    /*
    if ( 1 == preg_match('@(shortlink)@', $cdata) ) {
      $this->syslog( __FUNCTION__, __LINE__, "--- Matched extradata '{$cdata}'" );
      $this->recursive_dump($this->container_stack,__LINE__);
      $this->recursive_dump($this->containers,__LINE__);
      $this->recursive_dump($this->tag_stack,__LINE__);
    }
    */
    if ( 0 < count($this->tag_stack) ) {
      $stack_top = array_pop($this->tag_stack);
      array_push($this->tag_stack,$stack_top);
      $cdata_content_handler = "ru_{$stack_top['tag']}_cdata";
      if ( method_exists($this, $cdata_content_handler) ) {
        $parser_result = $this->$cdata_content_handler($parser, $cdata);
      } else {
        $stack_top = array_pop($this->tag_stack);
        if ( !array_key_exists('cdata', $stack_top) ) $stack_top['cdata'] = array();
        $stack_top['cdata'][] = $cdata;
        array_push($this->tag_stack,$stack_top);
      }
    }
    // else $this->syslog( __FUNCTION__, __LINE__, "--- {$cdata}" );
    // if ( $parser_result === TRUE ) $this->filtered_doc[] = trim($cdata);
    return $parser_result;
  }/*}}}*/

  function ru_default($parser, & $cdata) {/*{{{*/
    $this->syslog( __FUNCTION__, __LINE__, "--- {$cdata}" );
    $cdata = NULL;
    return FALSE;
  }/*}}}*/

  function add_to_container_stack(& $link_data, $target_tag = NULL) {/*{{{*/
    if ( 0 < count($this->container_stack) ) {
      if ( is_null($target_tag) ) {
        $container = array_pop($this->container_stack);
        $container['children'][] = $link_data; 
        array_push($this->container_stack, $container);
        return;
      }
      $found_index = NULL;
      foreach( $this->container_stack as $stack_index => $stacked_element ) {
        if ( !( strtolower($stacked_element['tagname']) == strtolower($target_tag) ) ) continue;
        $found_index = $stack_index; // Continue; find the uppermost tag
      }
      if ( !is_null($found_index) ) {
        $this->container_stack[$found_index]['children'][] = $link_data;
      } else {
        if (C('DEBUG_'.get_class($this))) $this->syslog( __FUNCTION__, __LINE__, "Lost tag content" );
      }
    }
  }/*}}}*/

  function push_container_def($tagname, & $attrs) {/*{{{*/
    // Invoked in ru_<tag>_open methods.
    $this->pop_tagstack();
    // This information is used to remove a container from the stack
    $container_def = array(
      'tagname' => $tagname,
      'sethash' => $this->hash_generator_counter++,
      'class'   => "{$attrs['CLASS']}",
      'id'      => "{$attrs['ID']}",
      'attrs'   => $attrs,
      'children' => array(),
      'seq'     => $this->current_tag['attrs']['seq'],
    );
    $container_sethash = sha1(base64_encode(print_r($container_def,TRUE) . ' ' . mt_rand(10000,100000)));
    $container_def['sethash'] = $container_sethash;
    $this->current_tag['CONTAINER'] = $container_sethash;
    if (C('DEBUG_'.get_class($this))) $this->syslog( __FUNCTION__, "<{$tagname}", join(',', array_keys($attrs)) . ' - ' . join(',', $attrs) );
    $this->push_tagstack();
    array_push($this->container_stack, $container_def);
  }/*}}}*/


  function & update_current_tag_url($k) {/*{{{*/
    if ( is_array($this->page_url_parts) && (0 < count($this->page_url_parts)) && is_array($this->current_tag) && array_key_exists('attrs', $this->current_tag) && array_key_exists($k, $this->current_tag['attrs'])) {
      $fixurl = array('url' => $this->current_tag['attrs'][$k]);
      $this->current_tag['attrs'][$k] = UrlModel::normalize_url($this->page_url_parts, $fixurl);
      //  $this->syslog(__FUNCTION__, __LINE__, "- Updated {$this->current_tag['tag']} URL to '{$this->current_tag['attrs'][$k]}'" ); 
    }
    return $this;
  }/*}}}*/

  function collapse_current_tag_link_data() {
    $target = $this->current_tag['attrs']['HREF'];
    $link_text = join('', $this->current_tag['cdata']);
    $link_data = array(
      'url'      => $target,
      'text'     => $link_text,
      'seq'      => $this->current_tag['attrs']['seq'],
    );
    return $link_data;
  }

  // ------------ Specific HTML tag handler methods -------------

  function ru_x_open(& $parser, & $attrs, $tagname ) {/*{{{*/
    $this->push_container_def($tagname, $attrs);
    return TRUE;
  }/*}}}*/

  function ru_x_cdata(& $parser, & $cdata) {/*{{{*/
    $this->syslog( __FUNCTION__, __LINE__, "CDATA: {$cdata}" );
    return TRUE;
  }/*}}}*/

  function ru_x_close(& $parser, $tag) {/*{{{*/
    $this->stack_to_containers();
    return TRUE;
  }/*}}}*/

  function & stack_to_containers() {/*{{{*/
    $container = array_pop($this->container_stack);
    // array_push($this->containers,$container);
    $hash_value = array_key_exists('sethash',$container) ? $container['sethash'] : NULL;
    // $this->recursive_dump($container,__LINE__);
    if ( is_null($hash_value) ) {
      $this->syslog(__FUNCTION__, __LINE__, "- Missing container hash");
      $this->recursive_dump($container,__LINE__);
    } else if ( !is_array($container) ) {
      $this->syslog(__FUNCTION__, __LINE__, "- Got non-container item from container stack! " . print_r($containers));
    } else if ( array_key_exists($hash_value,$this->containers) ) {
      $this->syslog(__FUNCTION__, __LINE__, "- Hash collision on tag {$container['tagname']} - {$hash_value}");
    } else {
      $this->containers[$hash_value] = $container;
    }
    return $this;
  }/*}}}*/

}/*}}}*/
