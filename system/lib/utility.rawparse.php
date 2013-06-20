<?php

class RawparseUtility extends SystemUtility {/*{{{*/

  protected $headerset                 = array();
  protected $parser                    = NULL;
  protected $current_tag               = NULL;
  protected $tag_stack                 = array();
  protected $links                     = array();
  protected $container_stack           = array();
  private $containers                  = array();
  protected $filtered_doc              = array();
  protected $custom_parser_needed      = FALSE;
  protected $page_url_parts            = array();
  protected $removable_containers      = array();
  private $hash_generator_counter      = 0;
  private $tag_counter                 = 0;
  protected $promise_stack             = array();
  protected $enable_filtered_doc_cache = TRUE;
	protected $content_type = NULL;
	protected $freewheel = FALSE;

  /*
   * HTML document XML parser class
   */

  function __construct() {/*{{{*/
    $this->initialize();
  }/*}}}*/

	function __destruct() {
		if ( !is_null($this->parser) ) {
      xml_parser_free($this->parser);
		}
		$this->parser = NULL;
		$this->structure_reinit();
	}
  
  protected function initialize() {/*{{{*/

    $this->parser = xml_parser_create('UTF-8');
    xml_set_object($this->parser, $this);
    xml_set_element_handler($this->parser, 'ru_tag_open', 'ru_tag_close');
    xml_set_character_data_handler($this->parser, 'ru_cdata');
    xml_set_default_handler($this->parser, 'ru_default');
    /* Diagnostics */
    xml_set_start_namespace_decl_handler($this->parser,'ru_start_namespace');
    xml_set_end_namespace_decl_handler($this->parser, 'ru_end_namespace');
    xml_set_processing_instruction_handler($this->parser, 'ru_processing_instr');
    xml_set_external_entity_ref_handler($this->parser, 'ru_external_entity_ref');

    /* Options. Do not change these XML_OPTION_CASE_FOLDING */
    xml_parser_set_option($this->parser, XML_OPTION_CASE_FOLDING, 1 );
    xml_parser_set_option($this->parser, XML_OPTION_SKIP_WHITE, 1 );
    xml_parser_set_option($this->parser, XML_OPTION_TARGET_ENCODING, 'UTF-8');

  }/*}}}*/

	function structure_reinit() {
		$this->headerset              = array();
		$this->parser                 = NULL;
		$this->current_tag            = NULL;
		$this->tag_stack              = array();
		$this->links                  = array();
		$this->container_stack        = array();
		$this->containers             = array();
		$this->filtered_doc           = array();
		$this->page_url_parts         = array();
		$this->removable_containers   = array();
		$this->hash_generator_counter = 0;
		$this->tag_counter            = 0;
		$this->promise_stack          = array();
	}

	function & clear_containers() {
		$this->containers = NULL;
		$this->filtered_containers = NULL;
		gc_collect_cycles();
		$this->structure_reinit();
		return $this;
	}

  function & enable_filtered_doc($b) {
    $this->enable_filtered_doc_cache = $b;
    return $this;
  }

  function & assign_containers(& $c, $reduce_to_element = NULL) {
    if ( is_null($reduce_to_element) )
      $this->containers = $c;
    else {
      $this->containers = $c[$reduce_to_element];
		}
		gc_collect_cycles();
    return $this->containers;
  }

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

  function & mark_container_sequence() {/*{{{*/
    $this->reorder_with_sequence_tags($this->containers);
    return $this;
  }/*}}}*/

	function & pop_from_containers(& $container) {
		$container = NULL;
		$container = array_pop($this->containers);
    reset($this->containers);
		return $this;
	}

  function & containers_r() {
    return $this->containers;
  }

	function clear_temporaries() {
		$this->filtered_containers = NULL;
		$this->removable_containers = NULL;
	}

  function get_containers($docpath = NULL, $reduce_to_element = FALSE) {/*{{{*/
    $this->filtered_containers = array();
		if ( is_array($this->removable_containers) )
    foreach ( $this->removable_containers as $remove ) {
      unset($this->containers[$remove]);
    }
    $this->removable_containers = array();

    if ( !is_null($docpath) ) {
      $this->filtered_containers = $this->containers;
			return $this->filter_nested_array($this->filtered_containers, $docpath, $reduce_to_element);
    }
    return $this->containers;
  }/*}}}*/

  function & get_headers() {/*{{{*/
    return $this->headerset;
  }/*}}}*/

  function & get_links() {/*{{{*/
    return $this->links;
  }/*}}}*/

  function & get_filtered_doc() {/*{{{*/
    return $this->filtered_doc;
  }/*}}}*/

  function slice($s) {/*{{{*/
    // Duplicated in DatabaseUtility
    return create_function('$a', 'return $a["'.$s.'"];');
  }/*}}}*/

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

  function reset($skip_alloc = FALSE, $clear_containers = TRUE) {/*{{{*/
    if ( !is_null($this->parser) ) {
      xml_parser_free($this->parser);
      $this->parser = NULL;
    }
		if ( !$skip_alloc ) $this->initialize();
    $this->current_tag     = NULL;
    $this->tag_stack       = array();
    $this->links           = array();
    $this->container_stack = array();
		if ( $clear_containers )
    $this->containers      = array();
    $this->filtered_doc    = array();
    return $this;
  }/*}}}*/

  function parse_html(& $raw_html, array $response_headers, $only_scrub = FALSE) {/*{{{*/

		$debug_method = FALSE;

    $this->reset();

		if ( empty($raw_html) ) {
			$this->syslog(__FUNCTION__,__LINE__, "(warning) Nothing to parse. Returning NULL");
			return NULL;
		}

    libxml_use_internal_errors(TRUE);
    libxml_clear_errors();

    $doctype_match = 'content-type';
    if ( !array_key_exists($doctype_match, $response_headers) ) {
      $this->syslog(__FUNCTION__,__LINE__, "(warning) Missing key '{$doctype_match}' for encoding check. Count " . count($response_headers) );
      $this->recursive_dump($response_headers,'(warning) - Must use doctype');
    }

    $doctype_match = array(); 
    $this->content_type = array_key_exists('content-type', $response_headers) &&
      1 == preg_match('@charset=([^;]*)@', $response_headers['content-type'], $doctype_match)
      ? strtolower($doctype_match[1])
      : 'iso-8859-1' // Default assumption
      ;
    if ( $debug_method ) {
      $this->syslog(__FUNCTION__,__LINE__, "(warning) Assuming encoding '{$this->content_type}' <- " . array_element($response_headers,'content-type') );
      $this->recursive_dump($doctype_match,"(marker) - --- - Matches");
      $this->recursive_dump($response_headers,"(marker) - - --- - - Headers");
    }

    $dom                      = new DOMDocument();
    $dom->recover             = TRUE;
    $dom->resolveExternals    = TRUE;
    $dom->preserveWhiteSpace  = FALSE;
    $dom->strictErrorChecking = FALSE;
    $dom->substituteEntities  = TRUE;

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
          '@(<!doctype(.*)>)@im',
          '/<html([^>]*)>/imU',
          '/>([ ]*)</m',

          '/<!--(.*)-->/imUx',
          '@<noscript>(.*)</noscript>@imU',
          '@<script([^>]*)>(.*)</script>@imU',
        ),
        array(
          '',
          '<html>',
          '><',

          '',
          '',
          '',
        ),
				$this->iconv($raw_html)
      )
    ))));

    $loadresult = $dom->loadHTML($raw_html);

    $dom->normalizeDocument();

    if ( !$loadresult ) $this->syslog( __FUNCTION__, __LINE__, "-- WARNING: Failed to filtering HTML as XML, load result FAIL" );

    $this->syslog(__FUNCTION__,__LINE__, "--------------------------- " . __LINE__ );
    xml_parse($this->parser, $dom->saveXML());
    $xml_errno = xml_get_error_code($this->parser);

    if ( $xml_errno == 0 ) {

      $dom->formatOutput = FALSE;
      $raw_html = $dom->saveHTML();
      if ($debug_method) $this->syslog(__FUNCTION__,__LINE__, "(marker) ---------- XML parse OK\n" . substr($raw_html,0,500));

    } else {

      $this->syslog(__FUNCTION__,__LINE__, "(warning) --------------------------- " . __LINE__ );
      $error_offset = xml_get_current_byte_index($this->parser);
      $xml_errstr = xml_error_string($xml_errno);
      $this->syslog( __FUNCTION__, __LINE__, 
        "PARSE ERROR #{$xml_errno}: {$xml_errstr} " . 
        "offset {$error_offset} context \n" . substr($dom->saveXML(), $error_offset - 10, 200)
      );
      $errors = libxml_get_errors();
      foreach ($errors as $error) {
        $this->syslog(__FUNCTION__,__LINE__,"(warning) - Parse err @ line {$error->line} col {$error->column}: {$error->message}");
      }

    }

    // Postprocessing

    // Add unprocessed container stack entries to $this->containers
    while ( 0 < count($this->container_stack) ) $this->stack_to_containers();

    $only_non_empty = create_function('$a', 'return 0 < count(array_element($a,"children")) ? $a : NULL;');
    $this->containers = array_filter(array_map($only_non_empty,$this->containers));
    if ( $debug_method ) $this->recursive_dump($this->containers,'(marker)');

    if ( method_exists($this, 'promise_prepare_state') ) {
      $this->promise_prepare_state();
    }

    // Process deferred tag operations ("promises")
    $this->process_promise_stack();
  
		$dom = NULL;
		unset($dom);
		$this->promise_stack = NULL;
		gc_collect_cycles();
		$this->promise_stack = array();

    return $this->containers;

  }/*}}}*/

  function process_promise_stack() {/*{{{*/
    // Process deferred tag operations ("promises")
		$debug_method = FALSE;
    if ( 0 < count($this->promise_stack) ) {
      if ( $debug_method ) $this->syslog(__FUNCTION__,__LINE__,"(marker) Handle stacked tag promises: " . count($this->promise_stack) . " for " . get_class($this));
      $containerset =& $this->get_containers();
      // Ensure that hash table contains 'seq' in keys
      $this->reorder_with_sequence_tags($containerset);
      array_walk($containerset,create_function(
        '& $a, $k, $s', '$s->reorder_with_sequence_tags($a["children"]);'
      ),$this);
      // Remove any child that shares the same sequence number as it's parent
      array_walk( $containerset, create_function(
        '& $a, $k', 'if ( is_numeric($k) && array_key_exists($k,$a) ) unset($a[$k]); if ( is_numeric($k) && array_key_exists("children", $a) && array_key_exists($k,$a["children"]) ) unset($a["children"][$k]);'
      ));
      $seq = NULL;
      $this->process_promise_stack_worker($seq,$containerset);
    } else {
			if ( $debug_method ) $this->syslog(__FUNCTION__,__LINE__,"(marker) No post-processing promises stacked for " . get_class($this));
    }
  }/*}}}*/

  function process_promise_stack_worker(& $seqno, & $containerset, $promise_item = NULL, $depth = 0) {/*{{{*/
    // Handle forward promise
    $debug_method = FALSE;
    if ( !is_null($promise_item) ) {
      foreach ( $promise_item as $ancestor => $promise ) {
        if ( array_key_exists('__TYPE__',$promise) ) {
          $promise_executor = "promise_{$promise['__TYPE__']}_executor";
          if ( method_exists($this, $promise_executor) ) {
            // This call modifies $containerset in place
            if ( $debug_method ) $this->syslog( __FUNCTION__, __LINE__, "(marker) - - - Execute {$promise_executor}({$seqno},{$ancestor}) d = {$depth}" );
            $this->$promise_executor($containerset, $promise, $seqno, $ancestor);
          } else {
            if ( $debug_method ) $this->syslog( __FUNCTION__, __LINE__, "(marker) -*-*- No executor {$promise_executor}({$seqno},{$ancestor}) d = {$depth}" );
          }
        } else if ( $debug_method ) {
          $this->syslog( __FUNCTION__, __LINE__, "(marker) -*-*- No executor for promise type '{$promise['__TYPE__']}' ({$seqno},{$ancestor}) d = {$depth}" );
        }
      }
    }
    if ( is_array($containerset) ) {
      foreach ( $containerset as $seq => $children ) {
        $promise_item_n = array();
        if ( is_integer($seq) ) {
          $promise_item_n = array_element($this->promise_stack,$seq,NULL);
          if ( !is_null($promise_item_n) ) {
            list($key, $data) = each($promise_item_n);
            $data['__ANCESTOR__'] = $seqno;
            $promise_item_n = array($key => $data);
          }
        }
        $this->process_promise_stack_worker($seq, $children, $promise_item_n, $depth + 1);
        $containerset[$seq] = $children;
      }
    } else {
      if ( $debug_method ) $this->syslog( __FUNCTION__, __LINE__, "(marker) - - - Leaf d = {$depth}, seq = [{$seqno}] (" . gettype($seqno) . ") containerset " . gettype($containerset)  );
    }
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

  function ru_processing_instr( $parser , string $target , string $data ) {
    $this->syslog(__FUNCTION__,__LINE__,"(marker) {$target} {$data}" );
  }

  function ru_start_namespace( $parser , string $prefix , string $uri ) {
    $this->syslog(__FUNCTION__,__LINE__,"(marker) {$prefix} {$uri}" );
  }

  function ru_end_namespace( $parser , string $prefix ) {
    $this->syslog(__FUNCTION__,__LINE__,"(marker) {$prefix}" );
  }

  function ru_external_entity_ref( $parser , string $open_entity_names , string $base , string $system_id , string $public_id ) {
    $this->syslog(__FUNCTION__,__LINE__,"(marker) ENs {$open_entity_names} Base {$base} SID {$system_id} PID {$public_id}" );
  }

	function tag_stack_parent(& $e) {
		$e = NULL;
		if ( empty($this->tag_stack) || 2 > count($this->tag_stack) ) return FALSE;
		$top = array_pop($this->tag_stack);
		$e = array_pop($this->tag_stack);
		array_push($this->tag_stack, $e);
		array_push($this->tag_stack, $top);
		return TRUE;
	}

  function ru_tag_open($parser, $tag, $attrs) {/*{{{*/
		if ( $this->freewheel ) return TRUE;
    $tag = strtoupper($tag);
    // $this->syslog(__FUNCTION__,__LINE__,">>>>>>>>>>>>>>>> {$tag}" );
    if ( $tag == 'HTTP:' ) return; 
    $tag_handler = strtolower("ru_{$tag}_open");
		$seq = intval($this->tag_counter);
    $current_tag = array(
      'tag' => $tag, 
      'attrs' => is_array($attrs) ? array_merge( $attrs, array('seq' => $seq) ) : array('seq' => $seq),
      'position' => NULL,
    );
    $this->current_tag = $current_tag;
    array_push($this->tag_stack, $current_tag);
    $result = ( method_exists($this, $tag_handler) )
      ? $this->$tag_handler($parser, $attrs, strtolower($tag))
      : TRUE 
      ;
    ////////////////////////
    $this->pop_tagstack();
    if ( $result) {
      if ( 0 < count(array_element($this->current_tag,'attrs',array())) ) {
        $attrs = $this->attributes_as_string($this->current_tag['attrs']);
        $tag .= " {$attrs}";
      }
      if ( $this->enable_filtered_doc_cache )
      $this->filtered_doc[] = <<<EOH
<{$tag}>
EOH;
    }
    $this->current_tag['position'] = count($this->filtered_doc);
    $this->push_tagstack();
		$this->tag_counter = intval($this->tag_counter) + 1;
    return TRUE;
  }/*}}}*/

	function get_container_by_hashid($container_id_hash, $key = NULL) {/*{{{*/
		return array_key_exists($container_id_hash,$this->containers)
			? is_null($key) 
			  ? $this->containers[$container_id_hash]
				: $this->containers[$container_id_hash][$key]
			: NULL
			;
	}/*}}}*/

  function ru_tag_close($parser, $tag) {/*{{{*/
		if ( $this->freewheel ) return TRUE;
    $debug_method = FALSE;
    $tag = strtoupper($tag);
    $tag_handler = strtolower("ru_{$tag}_close");
    $result = ( method_exists($this, $tag_handler) )
      ? $this->$tag_handler($parser, $tag)
      : TRUE 
      ;
    $this->pop_tagstack();
    if (is_array($this->current_tag) && array_key_exists('__LEGISCOPE__',$this->current_tag) ) {
      $promise_items = $this->current_tag['__LEGISCOPE__'];
      unset($this->current_tag['__LEGISCOPE__']);
      if (!is_null(array_element($promise_items,'__TYPE__'))) {
        $promise_items = array($promise_items);
      }

      foreach ( $promise_items as $promise_item ) {
        switch ($promise_item['__TYPE__']) {
          case '__POSTPROC__':
            $promise_item = NULL;
            list($k, $promise_item) = each($promise_items);
            $postproc_data = array_element($promise_item,'__POSTPROC__'); 
            $sethash = array_element($postproc_data,'__SETHASH__','- - - -');
            if ( array_key_exists($sethash, $this->containers) ) {
              unset($postproc_data['__SETHASH__']);
              $this->containers[$sethash]['__POSTPROC__'] = $postproc_data;
            }
            break;
          case 'title':
            // This tag's CDATA content is a title for a container, identified by seq __NEXT__
            $promise_properties = $promise_item;
            unset($promise_properties['__NEXT__']);
            if ( $debug_method ) {
              $this->syslog(__FUNCTION__,__LINE__,"(marker) Stack '{$promise_item['__TYPE__']}' promise for tag {$tag}");
              $this->recursive_dump($this->current_tag,"(marker) - C - - -");
              $this->recursive_dump($promise_properties,"(marker) - - P - -");
            }
            $this->promise_stack[$promise_item['__NEXT__']] = array(
              $this->current_tag['attrs']['seq'] => $promise_properties
            );
            break;
          default:
            break;
        }
      }
    }
    // The parser tag close method returns TRUE to cause this method 
    // to add the tag to the filtered markup result array.
    if ($result) {
      $tag_cdata = ( is_array($this->current_tag) && array_key_exists('cdata',$this->current_tag) && is_array($this->current_tag['cdata']) )
        ? join('',$this->current_tag['cdata'])
        : NULL
        ;
      if ( $this->enable_filtered_doc_cache )
      $this->filtered_doc[] = <<<EOH
{$tag_cdata}
</{$tag}>
EOH;
    }
    else if (is_array($this->current_tag)) {
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
		if ( $this->freewheel ) return TRUE;
    // Character data will always be contained within a parent container.
    // If there is no handler specified for a given tag, the topmost 
    // tag on the tag stack receives link content.
    $parser_result = TRUE;
    if ( 0 < count($this->tag_stack) ) {
      $stack_top = array_pop($this->tag_stack);
      array_push($this->tag_stack,$stack_top);
      $cdata_content_handler = strtolower("ru_{$stack_top['tag']}_cdata");
      if ( method_exists($this, $cdata_content_handler) ) {
        $parser_result = $this->$cdata_content_handler($parser, $cdata);
      } else {
        $stack_top = array_pop($this->tag_stack);
        if ( !array_key_exists('cdata', $stack_top) ) $stack_top['cdata'] = array();
        $stack_top['cdata'][] = $cdata;
        array_push($this->tag_stack,$stack_top);
      }
    }
    return $parser_result;
  }/*}}}*/

  function ru_default($parser, & $cdata) {/*{{{*/
		if ( $this->freewheel ) return TRUE;
    // $this->syslog( __FUNCTION__, __LINE__, "(marker) --- {$cdata}" );
    // $cdata = NULL;
    return FALSE;
  }/*}}}*/

  function add_to_container_stack(& $link_data, $target_tag = NULL) {/*{{{*/
    if ( !( 0 < count($this->container_stack) ) ) {
      $container_def = array(
        'tagname' => 'nil',
        'sethash' => $this->hash_generator_counter++,
        'class'   => NULL, 
        'id'      => '__LEGISCOPE__',
        'children' => array(),
        'seq'     => 0 
      );
      $container_sethash = sha1(base64_encode(print_r($container_def,TRUE) . ' ' . mt_rand(10000,100000)));
      $container_def['sethash'] = $container_sethash;
      array_push($this->container_stack, $container_def);
      $this->syslog( __FUNCTION__, __LINE__, "(marker) -- - -- - !! Attempt to add tag to empty stack. Added faux container [{$container_sethash}]");
    }
    if ( is_null($target_tag) ) {
      $container = array_pop($this->container_stack);
      $container['children'][] = $link_data; 
			$link_data['sethash'] = array_element($container,'sethash');
			$link_data['found_index'] = NULL;
      array_push($this->container_stack, $container);
			return TRUE;
    }
    $found_index = NULL;
    foreach( $this->container_stack as $stack_index => $stacked_element ) {
      if ( !( strtolower($stacked_element['tagname']) == strtolower($target_tag) ) ) continue;
      $found_index = $stack_index; // Continue; find the uppermost tag
    }
    if ( !is_null($found_index) ) {
			$link_data['found_index'] = $found_index;
			$link_data['sethash'] = array_element($this->container_stack[$found_index],'sethash');
      $this->container_stack[$found_index]['children'][] = $link_data;
			return TRUE;
    } else {
      if (C('DEBUG_'.get_class($this))) $this->syslog( __FUNCTION__, __LINE__, "Lost tag content" );
    }
		return FALSE;
  }/*}}}*/

  function push_container_def($tagname, & $attrs) {/*{{{*/
    // Invoked in ru_<tag>_open methods.
    $this->pop_tagstack();
    // This information is used to remove a container from the stack
    $container_def = array(
      'tagname' => $tagname,
      'sethash' => $this->hash_generator_counter++,
      'class'   => array_key_exists('CLASS', $attrs) ? "{$attrs['CLASS']}" : NULL,
      'id'      => array_key_exists('ID'   , $attrs) ? "{$attrs['ID']}"    : NULL,
      'attrs'   => $attrs,
      'children' => array(),
      'seq'     => nonempty_array_element($this->current_tag['attrs'],'seq',0),
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
    }
    return $this;
  }/*}}}*/

  function collapse_current_tag_link_data() {/*{{{*/
    $target = isset($this->current_tag['attrs']['HREF']) ? $this->current_tag['attrs']['HREF'] : NULL;
    $link_text = join('', $this->current_tag['cdata']);
    $link_data = array(
      'url'      => $target,
      'text'     => $link_text,
      'seq'      => $this->current_tag['attrs']['seq'],
    );
    return $link_data;
  }/*}}}*/

  function extract_form_controls($form_control_source) {/*{{{*/

    $form_controls        = array();
    $select_options       = array();
    $select_name          = NULL;
    $select_option        = NULL;
    $userset              = array();

    if ((is_array($form_control_source) && (0 < count($form_control_source)))) {

      $extract_hidden_input = create_function('$a','return strtoupper(array_element($a,"tag")) == "INPUT" && is_array(array_element($a,"attrs")) && strtoupper(array_element($a["attrs"],"TYPE")) == "HIDDEN" ? array("name" => array_element(array_element($a,"attrs",array()),"NAME"), "value" => array_element(array_element($a,"attrs",array()),"VALUE")) : NULL;');
      $extract_text_input   = create_function('$a','return strtoupper(array_element($a,"tag")) == "INPUT" && is_array(array_element($a,"attrs")) && strtoupper(array_element($a["attrs"],"TYPE")) == "TEXT"   ? array("name" => array_element(array_element($a,"attrs",array()),"NAME"), "value" => array_element(array_element($a,"attrs",array()),"VALUE")) : NULL;');
      $extract_select       = create_function('$a','return strtoupper(array_element($a,"tagname")) == "SELECT" ? array("name" => array_element(array_element($a,"attrs",array()),"NAME"), "keys" => array_element($a,"children")) : NULL;');
      foreach ( array_merge(
        array_values(array_filter(array_map($extract_hidden_input,$form_control_source))),
        array_values(array_filter(array_map($extract_text_input, $form_control_source)))
      ) as $form_control ) {
        $form_controls[$form_control['name']] = $form_control['value'];
      };

      $select_options = array_values(array_filter(array_map($extract_select, $form_control_source)));

      $userset = array();
			$selected = NULL;
      foreach ( $select_options as $select_option ) {
        //$this->recursive_dump($select_options,__LINE__);
        $select_name    = $select_option['name'];
        $select_option  = $select_option['keys'];
        foreach ( $select_option as $option ) {
					if ( is_null($selected) && (1 == intval(array_element($option,'selected')))) $selected = $option['value']; 
          if ( empty($option['value']) ) continue;
          $userset[$select_name][$option['value']] = $option['text'];
        }
      }
    }

    return array(
      'userset'        => $userset,
      'form_controls'  => $form_controls,
      'select_name'    => $select_name,
      'select_options' => $select_option,
			'select_active'  => $selected,
    );
  }/*}}}*/

	function fetch_body_generic_cleanup($pagecontent) {/*{{{*/
		return preg_replace(
			array(
				'@^(.*)\<body([^>]*)\>(.*)\<\/body\>(.*)@mi',
				// Remove mouse event handlers
				'@(onmouseover|onmouseout)="([^"]*)"@',
				"@(onmouseover|onmouseout)='([^']*)'@",
			),
			array(
				'$3', 
				'',
				'',
			),
			$pagecontent
		);
	}/*}}}*/
	
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

	function & set_freewheel($v = TRUE) {
		// Suspend parsing when $v := TRUE
		$this->syslog(__FUNCTION__,__LINE__,"(warning) - - - - - - Parse switch - freewheel = " . ($v ? "TRUE" : "FALSE"));
		$this->freewheel = $v;
		return $this;
	}

  function & current_tag() {
    $this->pop_tagstack();
    $this->push_tagstack();
    return $this;
  }

  function embed_cdata_in_parent() {/*{{{*/
    $i = $this->pop_tagstack();
    $content = join('',$this->current_tag['cdata']);
    $parent = $this->pop_tagstack();
    $parent['cdata'][] = " {$content} ";
    $this->push_tagstack($parent);
    $this->push_tagstack($i);
    return FALSE;
  }/*}}}*/

  function cdata_cleanup() {
    if ( is_array($this->current_tag) && array_key_exists('cdata',$this->current_tag) && is_array($this->current_tag['cdata']) ) {
      $this->current_tag['cdata'] = array_filter(explode('[BR]',preg_replace('@(&nbsp([;]*))+@i',' ',join('',$this->current_tag['cdata']))));
    }
  }

	function iconv($s) {/*{{{*/
		return iconv( strtoupper($this->content_type), 'UTF-8//TRANSLIT', $s );
	}/*}}}*/

	function reverse_iconv($s) {/*{{{*/
		return iconv( 'UTF-8', strtoupper($this->content_type), $s );
	}/*}}}*/


}/*}}}*/
