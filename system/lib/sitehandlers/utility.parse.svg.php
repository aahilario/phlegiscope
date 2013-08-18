<?php

/*
 * Class SvgParseUtility
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class SvgParseUtility extends RawparseUtility {
  
	var $extracted_styles = array();
  var $image_filename = NULL;
  var $dom = NULL;

  function __construct($image = NULL) {/*{{{*/
    parent::__construct();
    $this->set_image_filename($image);
    $this->initialize_dom();
  }/*}}}*/

  function __destruct() {/*{{{*/
    $this->dom = NULL;
    unset($this->dom);
  }/*}}}*/

  function ru_svg_open(& $parser, & $attrs, $tag) {/*{{{*/
		$this->add_cdata_property();
		$this->push_container_def($tag,$attrs);
    return TRUE;
  }/*}}}*/
  function ru_svg_cdata(& $parser, & $cdata) {/*{{{*/
		$this->append_cdata($cdata);
    return TRUE;
  }/*}}}*/
  function ru_svg_close(& $parser, $tag) {/*{{{*/
		// This root container should NOT be discarded
		$this->embed_container_in_parent($parser,$tag);
    return TRUE;
  }/*}}}*/

  function ru_g_open(& $parser, & $attrs, $tag) {/*{{{*/
		$this->add_cdata_property();
		$this->push_container_def($tag, $attrs);
    return TRUE;
  }/*}}}*/
  function ru_g_cdata(& $parser, & $cdata) {/*{{{*/
		$this->append_cdata($cdata);
    return TRUE;
  }/*}}}*/
  function ru_g_close(& $parser, $tag) {/*{{{*/
    return $this->embed_container_in_parent($parser,$tag);
  }/*}}}*/

  function ru_path_open(& $parser, & $attrs, $tag) {/*{{{*/
		$this->add_cdata_property();
		$this->push_container_def($tag, $attrs);
    return TRUE;
  }/*}}}*/
  function ru_path_cdata(& $parser, & $cdata) {/*{{{*/
		$this->append_cdata($cdata);
    return TRUE;
  }/*}}}*/
  function ru_path_close(& $parser, $tag) {/*{{{*/
		$this->pop_tagstack();
		$this->current_tag['attrs']['class'] = 'svg-outline';
		$this->push_tagstack();
    return $this->embed_container_in_parent($parser,$tag);
  }/*}}}*/

  function ru_polygon_open(& $parser, & $attrs, $tag) {/*{{{*/
		$this->add_cdata_property();
    return TRUE;
  }/*}}}*/
  function ru_polygon_cdata(& $parser, & $cdata) {/*{{{*/
		$this->append_cdata($cdata);
    return TRUE;
  }/*}}}*/
  function ru_polygon_close(& $parser, $tag) {/*{{{*/
 		$this->pop_tagstack();
		$this->add_to_container_stack($this->current_tag);
		$this->push_tagstack();
    return TRUE;
  }/*}}}*/

  function ru_rdf_rdf_open(& $parser, & $attrs, $tag) {/*{{{*/
		$this->add_cdata_property();
		$this->push_container_def($tag, $attrs);
    return TRUE;
  }/*}}}*/
  function ru_rdf_rdf_cdata(& $parser, & $cdata) {/*{{{*/
		$this->append_cdata($cdata);
    return TRUE;
  }/*}}}*/
  function ru_rdf_rdf_close(& $parser, $tag) {/*{{{*/
    return $this->embed_container_in_parent($parser,$tag);
  }/*}}}*/

  function ru_cc_work_open(& $parser, & $attrs, $tag) {/*{{{*/
		$this->add_cdata_property();
		$this->push_container_def($tag, $attrs);
    return TRUE;
  }/*}}}*/
  function ru_cc_work_cdata(& $parser, & $cdata) {/*{{{*/
		$this->append_cdata($cdata);
    return TRUE;
  }/*}}}*/
  function ru_cc_work_close(& $parser, $tag) {/*{{{*/
    return $this->embed_container_in_parent($parser,$tag);
  }/*}}}*/

  function ru_dc_format_open(& $parser, & $attrs, $tag) {/*{{{*/
		$this->add_cdata_property();
    return TRUE;
  }/*}}}*/
  function ru_dc_format_cdata(& $parser, & $cdata) {/*{{{*/
		$this->append_cdata($cdata);
    return TRUE;
  }/*}}}*/
  function ru_dc_format_close(& $parser, $tag) {/*{{{*/
		$this->pop_tagstack();
		$this->add_to_container_stack($this->current_tag);
		$this->push_tagstack();
    return TRUE;
  }/*}}}*/

  function ru_dc_type_open(& $parser, & $attrs, $tag) {/*{{{*/
		$this->add_cdata_property();
    return TRUE;
  }/*}}}*/
  function ru_dc_type_cdata(& $parser, & $cdata) {/*{{{*/
		return $this->append_cdata($cdata);
  }/*}}}*/
  function ru_dc_type_close(& $parser, $tag) {/*{{{*/
		$this->pop_tagstack();
		$this->add_to_container_stack($this->current_tag);
		$this->push_tagstack();
    return TRUE;
  }/*}}}*/

  function ru_dc_title_open(& $parser, & $attrs, $tag) {/*{{{*/
		$this->add_cdata_property();
    return TRUE;
  }/*}}}*/
  function ru_dc_title_cdata(& $parser, & $cdata) {/*{{{*/
		return $this->append_cdata($cdata);
  }/*}}}*/
  function ru_dc_title_close(& $parser, $tag) {/*{{{*/
		$this->pop_tagstack();
		$this->add_to_container_stack($this->current_tag);
		$this->push_tagstack();
    return TRUE;
	}/*}}}*/

  function ru_defs_open(& $parser, & $attrs, $tag) {/*{{{*/
		$this->add_cdata_property();
		$this->push_container_def($tag, $attrs);
     return TRUE;
  }/*}}}*/
  function ru_defs_cdata(& $parser, & $cdata) {/*{{{*/
		return $this->append_cdata($cdata);
  }/*}}}*/
  function ru_defs_close(& $parser, $tag) {/*{{{*/
    return $this->embed_container_in_parent($parser,$tag);
  }/*}}}*/

  function ru_metadata_open(& $parser, & $attrs, $tag) {/*{{{*/
		$this->add_cdata_property();
		$this->push_container_def($tag, $attrs);
    return TRUE;
  }/*}}}*/
  function ru_metadata_cdata(& $parser, & $cdata) {/*{{{*/
		return $this->append_cdata($cdata);
  }/*}}}*/
  function ru_metadata_close(& $parser, $tag) {/*{{{*/
    return $this->embed_container_in_parent($parser,$tag);
  }/*}}}*/

  function ru_style_open(& $parser, & $attrs, $tag) {/*{{{*/
		$this->add_cdata_property();
    if ($this->debug_tags) {
      $this->syslog( __FUNCTION__, __LINE__, "(marker) ---------- {$this->current_tag['tag']}" );
      $this->recursive_dump($this->current_tag,"(marker) ---");
    }
    return TRUE;
  }/*}}}*/
	function ru_style_cdata(& $parser, & $cdata) {/*{{{*/
		return $this->append_cdata($cdata);
	}/*}}}*/
  function ru_style_close(& $parser, $tag) {/*{{{*/
		return $this->add_current_tag_to_container_stack();
  }/*}}}*/

  function ru_title_open(& $parser, & $attrs, $tag) {/*{{{*/
		$this->add_cdata_property();
    return TRUE;
  }/*}}}*/
  function ru_title_cdata(& $parser, & $cdata) {/*{{{*/
		return $this->append_cdata($cdata);
  }/*}}}*/
  function ru_title_close(& $parser, $tag) {/*{{{*/
		// Treat <title> tags embedded in <path> parents.
    $this->pop_tagstack();
    $text = array(
      'title' => str_replace(array('[BR]',"\n"),array(''," "),join('',$this->current_tag['cdata'])),
      'seq' => $this->current_tag['attrs']['seq'],
    );
    $this->add_to_container_stack($text);
    $this->push_tagstack();
    return TRUE;
  }/*}}}*/

	function reconstruct_svg($svg, $pagecontent = NULL) {
		$debug_method = FALSE;
		if ( is_null($pagecontent) ) {
			$this->extracted_styles = array();
		}
		$pagecontent = '';
		foreach( $svg as $e ) {
			$tagname  = strtolower(nonempty_array_element($e,'tagname',nonempty_array_element($e,'tag')));
			if ( !(0 < strlen($tagname) ) ) continue;
			$attrs    = nonempty_array_element($e,'attrs',array());
			$children = array_filter(nonempty_array_element($e,'children',array()));
			$id       = nonempty_array_element($attrs,'id');
			$style    = nonempty_array_element($attrs,'style');
			if ( 0 < strlen($style) ) {/*{{{*/
				// Generate style lookup table 
				unset($attrs['style']);
				$style = explode(';',$style);
				array_walk($style,create_function(
					'& $a, $k', '$a = explode(":",$a); $a = array("attr" => $a[0], "val" => $a[1]);'
				));
				$style = array_combine(
					array_map(create_function('$a', 'return $a["attr"];'),$style),
					array_map(create_function('$a', 'return $a["val"];'),$style)
				);
				ksort($style);
				// Keep all unique style instances
				$tag_style_hash = md5(json_encode($style));
				if ( !array_key_exists($tag_style_hash, $this->extracted_styles) )
					$this->extracted_styles[$tag_style_hash] = array(
						'idents' => array($id => $id),
						'styles' => $style
					);
				else
					$this->extracted_styles[$tag_style_hash]['idents'][$id] = $id;
				if ( array_key_exists('fill',$style) ) {
					$attrs['oldfill'] = $style['fill'];
				}
			}/*}}}*/
			if ( 0 == count($children) ) {/*{{{*/
				// No children to process, skip to next SVG node
			}/*}}}*/
			else if ( 'path' == $tagname ) {/*{{{*/
				// Extract a sole title node, and use it as a title attribute
				$path_children = $children;
				$this->filter_nested_array($path_children,'title[title*=.*|i]',0);
				$attrs['title'] = nonempty_array_element($path_children,0);
				if ( $debug_method ) {
					$this->syslog(__FUNCTION__,__LINE__,"(marker) {$path_children}");
					$this->recursive_dump($path_children,"(marker) {$tagname} {$path_children}");
				}
			}/*}}}*/
			$attributes = array_filter($attrs);
			array_walk($attributes,create_function(
				'& $a, $k', '$a = "{$k}=\"{$a}\"";'
			));
			$attributes = join(' ', $attributes);
			$has_closing_tag = $tagname == 'polygon' ? FALSE : TRUE;
			$closing_tag = $has_closing_tag ? NULL : " /";
			$pagecontent .= <<<EOH
<{$tagname} {$attributes}{$closing_tag}>

EOH;
			$pagecontent .= $this->reconstruct_svg($children, $pagecontent);
			if ( $has_closing_tag ) $pagecontent .= <<<EOH
</{$tagname}>

EOH;
			// Generate tag at this nesting depth
		}
		return $pagecontent;
	}

  function transform_svgimage($image = NULL) {/*{{{*/

    $debug_method = TRUE;
    $this->debug_tags = FALSE; // $debug_method; // FALSE;
		$this->reset();

		// Retain case for all tags
    xml_parser_set_option($this->parser, XML_OPTION_CASE_FOLDING, 0 );

    if ( is_null($image) ) $image = $this->image_filename;

    if ( !file_exists($image) ) {
      $this->syslog(__FUNCTION__,__LINE__,"(marker) Missing file {$image}");
      return FALSE;
    }

    $loadresult = $this->dom->loadXML(file_get_contents($image));

    $this->syslog(__FUNCTION__,__LINE__,
      "(marker) Load result (" .gettype($loadresult) . ")" . print_r($loadresult,TRUE) .
      " {$image}");

    $this->dom->normalizeDocument();

    $full_length  = mb_strlen($this->dom->saveXML());
    $chunk_length = 16384;
    if ( $debug_method ) $this->syslog(__FUNCTION__,__LINE__, "(marker) --------------------------- mb_strlen " . $full_length );
    for ( $offset = 0 ; $offset < $full_length ; $offset += $chunk_length ) {
      $is_final = ($offset + $chunk_length) >= $full_length;
      xml_parse($this->parser, mb_substr($this->dom->saveXML(), $offset, $chunk_length), $is_final);
      if ( $this->terminated ) {
        $this->syslog(__FUNCTION__,__LINE__,"(warning) Terminated.");
        break;
      }
    }

  }/*}}}*/

  function set_image_filename($image) {/*{{{*/
    if ( !is_null($image) ) {
      $this->image_filename = file_exists($image) ? $image : NULL; 
    }
  }/*}}}*/

  private function initialize_dom() {/*{{{*/
    $this->dom                      = new DOMDocument();
    $this->dom->recover             = TRUE;
    $this->dom->resolveExternals    = TRUE;
    $this->dom->preserveWhiteSpace  = FALSE;
    $this->dom->strictErrorChecking = FALSE;
    $this->dom->substituteEntities  = TRUE;

		if (0) {
			$this->parser = xml_parser_create();
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
		}


  }/*}}}*/

}

