<?php

/*
 * Class GenericParseUtility
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class GenericParseUtility extends RawparseUtility {
  
  function __construct() {
    parent::__construct();
  }

  // ------------ Specific HTML tag handler methods -------------

  function ru_script_open(& $parser, & $attrs) {/*{{{*/
    $tagname = strtolower(preg_replace('/^ru_([^_]*)_open/','$1', __FUNCTION__));
    return FALSE;
  }/*}}}*/

  function ru_script_cdata(& $parser, & $cdata) {/*{{{*/
    // $this->syslog(__FUNCTION__,'FORCE',"{$cdata}");
    return FALSE;
  }/*}}}*/

  function ru_script_close(& $parser, $tag) {/*{{{*/
    return FALSE;
  }/*}}}*/

  // ------- Document HEAD ---------

  function ru_head_open(& $parser, & $attrs, $tag) {/*{{{*/
    $this->push_container_def($tag, $attrs);
    return TRUE;
  }/*}}}*/

  function ru_head_close(& $parser, $tag) {/*{{{*/
    $this->stack_to_containers();
    return FALSE;
  }/*}}}*/


  function ru_link_open(& $parser, & $attrs) {/*{{{*/
    $this->current_tag['attrs'] = $attrs;
    return TRUE;
  }/*}}}*/

  function ru_link_close(& $parser, $tag) {/*{{{*/
    if ( !is_null($this->current_tag) && array_key_exists('tag', $this->current_tag) && ($tag == $this->current_tag['tag']) ) {
      $this->add_to_container_stack($this->current_tag);
    }
    return TRUE;
  }/*}}}*/

  function ru_meta_open(& $parser, & $attrs) {/*{{{*/
    $this->current_tag['attrs'] = $attrs;
    return FALSE;
  }/*}}}*/

  function ru_meta_close(& $parser, $tag) {/*{{{*/
    if ( !is_null($this->current_tag) && array_key_exists('tag', $this->current_tag) && ($tag == $this->current_tag['tag']) ) {
      $this->add_to_container_stack($this->current_tag);
    }
    return FALSE;
  }/*}}}*/

  function ru_title_open(& $parser, & $attrs) {/*{{{*/
    $this->current_tag['attrs'] = $attrs;
    return FALSE;
  }/*}}}*/

  function ru_title_cdata(& $parser, & $cdata) {/*{{{*/
    if ( !empty($cdata) && ( 0 < count($this->tag_stack) ) ) {
      $this->pop_tagstack();
      $this->current_tag['cdata'][] = str_replace("\n"," ",trim($cdata));
      $this->push_tagstack();
    } 
    return FALSE;
  }/*}}}*/

  function ru_title_close(& $parser, $tag) {/*{{{*/
		$this->pop_tagstack();
		if ( is_array($this->current_tag) && array_key_exists('cdata', $this->current_tag) ) {
			$title = array(
				'title' => join('',$this->current_tag['cdata']),
				'seq'   => 0,
			); 
			$this->add_to_container_stack($title);
		}
		$this->push_tagstack();
    return FALSE;
  }/*}}}*/

  // ------- Document BODY ---------

  // ---- Containers ----

  function ru_iframe_open(& $parser, & $attrs) {/*{{{*/
    // Handle A anchor/link tags
    $this->current_tag = array(
      'tag' => 'IFRAME',
      'attrs' => $attrs,
      'cdata' => array(),
    );
    return TRUE;
  }  /*}}}*/

  function ru_iframe_cdata(& $parser, & $cdata) {/*{{{*/
    // Attach CDATA to IFRAME tag 
    $this->current_tag['cdata'][] = $cdata;
    $tag = !is_null($this->current_tag) 
      ? $this->current_tag['tag']
      : '?IFRAME'
      ;
    $link = !is_null($this->current_tag) && ($this->current_tag['tag'] == 'IFRAME') 
      ? $this->current_tag['attrs']['HREF']
      : '???'
      ;

    if (C('DEBUG_'.get_class($this))) $this->syslog( "+ {$tag}", NULL, "{$link} {$cdata}" );
    return TRUE;
  }/*}}}*/

  function ru_iframe_close(& $parser, $tag) {/*{{{*/
    // An IFRAME is similar to an anchor A tag in that it's source URL is relevant 
    $target = !is_null($this->current_tag) &&
       array_key_exists('tag', $this->current_tag) && 
      ('IFRAME' == $this->current_tag['tag'])
      ? $this->current_tag['attrs']['SRC']
      : NULL;
    if ( !is_null($target) ) {
      $link_data = array(
        'url'      => $target,
        'urlparts' => UrlModel::parse_url($target),
        'text'     => '[IFRAME]',
      );
      $this->links[] = $link_data;
      $this->add_to_container_stack($link_data);
    }
    return TRUE;
  }/*}}}*/

  function ru_form_open(& $parser, & $attrs, $tagname) {/*{{{*/
    $this->pop_tagstack();
    $this->update_current_tag_url('ACTION');
    $this->push_tagstack();
    $this->push_container_def($tagname, $attrs);
    return TRUE;
  }/*}}}*/

  function ru_form_cdata(& $parser, & $cdata) {/*{{{*/
  }/*}}}*/

  function ru_form_close(& $parser, $tag) {/*{{{*/
    $this->stack_to_containers();
    // $this->syslog( __FUNCTION__, 'FORCE', "--- {$tag}" );
    // $this->recursive_dump($form_container,0,'FORCE');
    return TRUE;
  }/*}}}*/

  // ---- Form inputs ----

  // --- SELECT (container for OPTION tags)  ---

  function ru_select_open(& $parser, & $attrs) {/*{{{*/
    $tagname = strtolower(preg_replace('/^ru_([^_]*)_open/','$1', __FUNCTION__));
    $this->push_container_def($tagname, $attrs);
    return TRUE;
  }/*}}}*/

  function ru_select_close(& $parser, $tag) {/*{{{*/
    // Treat SELECT tags as containers on OPEN, but as tags on CLOSE 
    // $this->stack_to_containers();
    $select_contents = array_pop($this->container_stack);
    // if (C('DEBUG_'.get_class($this))) $this->recursive_dump($select_contents, 0, 'SELECT');
    $this->add_to_container_stack($select_contents, 'FORM');
    return TRUE;
  }/*}}}*/

  function ru_option_open(& $parser, & $attrs, $tagname) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'] = array();
    $this->push_tagstack();
    return TRUE;
  }/*}}}*/

  function ru_option_cdata(& $parser, & $cdata) {/*{{{*/
    // Attach CDATA to OPTION tag. This is normally the option label 
    $this->pop_tagstack();
    $this->current_tag['cdata'][] = $cdata;
    $this->push_tagstack();
    return TRUE;
  }/*}}}*/

  function ru_option_close(& $parser, $tag) {/*{{{*/
    $this->pop_tagstack();
    $target = !is_null($this->current_tag) &&
       array_key_exists('tag', $this->current_tag) && 
      ('OPTION' == $this->current_tag['tag'])
      ? $this->current_tag['attrs']['VALUE']
      : NULL;
    if ( !is_null($target) ) {
      $link_data = array(
        'value'    => $this->current_tag['attrs']['VALUE'],
        'text'     => join(' ',$this->current_tag['cdata']),
      );
      $this->add_to_container_stack($link_data);
    }
    $this->push_tagstack();
    return TRUE;
  }/*}}}*/

  // ---- Tables used as presentational, structure elements on a page  ----

  function ru_table_open(& $parser, & $attrs) {/*{{{*/
    $tagname = strtolower(preg_replace('/^ru_([^_]*)_open/','$1', __FUNCTION__));
    $this->push_container_def($tagname, $attrs);
    return TRUE;
  }/*}}}*/

  function ru_table_close(& $parser, $tag) {/*{{{*/
    $this->stack_to_containers();
    return TRUE;
  }/*}}}*/

  function ru_body_open(& $parser, & $attrs, $tagname) {/*{{{*/
    $this->push_container_def($tagname, $attrs);
    return TRUE;
  }/*}}}*/

  function ru_body_cdata(& $parser, & $cdata) {/*{{{*/
    return TRUE;
  }/*}}}*/

  function ru_body_close(& $parser, $tag) {/*{{{*/
    $this->stack_to_containers();
    return TRUE;
  }/*}}}*/

  function ru_input_open(& $parser, & $attrs) {/*{{{*/
    $this->current_tag['cdata'] = array();
    return TRUE;
  }/*}}}*/

  function ru_input_cdata(& $parser, & $cdata) {/*{{{*/
    $this->current_tag['cdata'][] = $cdata;
    return TRUE;
  }/*}}}*/

  function ru_input_close(& $parser, $tag) {/*{{{*/
    $target = !is_null($this->current_tag) &&
       array_key_exists('tag', $this->current_tag) && 
      ('INPUT' == $this->current_tag['tag'])
      ? $this->current_tag
      : NULL;
    if ( !is_null($target) ) {
      $this->add_to_container_stack($target, 'FORM');
    }
    return TRUE;
  }/*}}}*/

  // ---- Sundry tags ----

  function ru_br_open(& $parser, & $attrs, $tagname ) {/*{{{*/
    return TRUE;
  }/*}}}*/

  function ru_br_cdata(& $parser, & $cdata) {/*{{{*/
    return TRUE;
  }/*}}}*/

  function ru_br_close(& $parser, $tag) {/*{{{*/
    $me     = $this->pop_tagstack();
    $parent = $this->pop_tagstack();
    if ( array_key_exists('cdata', $parent) ) {
      // $this->syslog(__FUNCTION__,'FORCE',"Adding line break to {$parent['tag']} (" . join(' ', $parent['cdata']) . ")" );
      $parent['cdata'][] = "\n[BR]";
    }
    $this->push_tagstack($parent);
    $this->push_tagstack($me);
    return FALSE;
  }/*}}}*/

  function ru_p_open(& $parser, & $attrs, $tag) {/*{{{*/
    // Handle A anchor/link tags
    if ( 0 < count($this->tag_stack) ) {
      $this->pop_tagstack();
      $this->current_tag['cdata'] = array();
      $this->push_tagstack();
    }
    return TRUE;
  }  /*}}}*/

  function ru_p_cdata(& $parser, & $cdata) {/*{{{*/
    // Attach CDATA to A tag 
    if ( !empty($cdata) && ( 0 < count($this->tag_stack) ) ) {
      $this->pop_tagstack();
      $this->current_tag['cdata'][] = str_replace("\n"," ",trim($cdata));
      $this->push_tagstack();
    }
    return TRUE;
  }/*}}}*/

  function ru_p_close(& $parser, $tag) {/*{{{*/
    $this->current_tag();
    $paragraph = array(
      'text' => (is_array($this->current_tag) && array_key_exists('cdata', $this->current_tag) && is_array($this->current_tag['cdata'])) 
        ? join('', $this->current_tag['cdata']) 
        : NULL,
      'seq'  => $this->current_tag['attrs']['seq'],
    );
    $this->add_to_container_stack($paragraph);
    return TRUE;
  }/*}}}*/

  function ru_a_open(& $parser, & $attrs, $tag) {/*{{{*/
    // Handle A anchor/link tags
    if ( 0 < count($this->tag_stack) ) {
      $this->pop_tagstack();
      $this->current_tag['cdata'] = array();
      $this->current_tag['attrs']['class'] = 'legiscope-remote';
      $this->push_tagstack();
      array_push($this->links, $this->current_tag); 
    }
    return TRUE;
  }  /*}}}*/

  function ru_a_cdata(& $parser, & $cdata) {/*{{{*/
    // Attach CDATA to A tag 
    $this->pop_tagstack();
    $link = array_pop($this->links);
    $cdata = $cdata;
    $link['cdata'][] = $cdata; 
    $this->current_tag['cdata'][] = $cdata; 
    array_push($this->links, $link);
    $this->push_tagstack();
    return TRUE;
  }/*}}}*/

  function ru_a_close(& $parser, $tag) {/*{{{*/
    $this->current_tag();
    $target = 0 < count($this->links) ? array_pop($this->links) : NULL;
    $link_text = join('', $target['cdata']);
    $link_data = array(
      'url'  => $target['attrs']['HREF'],
      'text' => $link_text,
      'seq'  => $this->current_tag['attrs']['seq'],
    );
    if ( !(empty($link_data['url']) && empty($link_data['text'])) ) {
      array_push($this->links, $target);
      $this->add_to_container_stack($link_data);
    }
    return TRUE;
  }/*}}}*/

  function ru_div_open(& $parser, & $attrs, & $tag) {/*{{{*/
    // Handle presentational/structural container DIV
    $this->push_container_def($tag, $attrs);
    if (C('DEBUG_'.get_class($this))) $this->syslog( "<{$tagname}", NULL, join(',', array_keys($attrs)) . ' - ' . join(',', $attrs) );
    return TRUE;
  }/*}}}*/

  function ru_div_close(& $parser, $tag) {/*{{{*/
    $tagname = preg_replace('/^ru_([^_]*)_close/i','$1', __FUNCTION__);
    if (C('DEBUG_'.get_class($this))) $this->syslog( "{$tagname}>", NULL, "" );
    $this->stack_to_containers();
    return TRUE;
  }/*}}}*/

  function ru_blockquote_open(& $parser, & $attrs) {/*{{{*/
    // Handle presentational/structural container DIV
    $tagname = strtolower(preg_replace('/^ru_([^_]*)_open/','$1', __FUNCTION__));
    if (C('DEBUG_'.get_class($this))) $this->syslog( "<{$tagname}", NULL, join(',', $attrs) );
    return TRUE;
  }/*}}}*/

  function ru_blockquote_close(& $parser, $tag) {/*{{{*/
    $tagname = preg_replace('/^ru_([^_]*)_close/i','$1', __FUNCTION__);
    if (C('DEBUG_'.get_class($this))) $this->syslog( "{$tagname}>", NULL, "" );
    return TRUE;
  }/*}}}*/

  ///   UTILITY METHODS   ///

  function replace_legislative_sn_hotlinks($subject) {/*{{{*/
    $match_legislation_sn = array();
    if ( preg_match_all('@((RA|HB|SB|HR)([0]*([0-9]*)))@', "{$subject}", $match_legislation_sn) ) {
      // $this->syslog( __FUNCTION__, 'FORCE', "- Status {$bill['bill']} {$subject}" );
      // $this->recursive_dump($match_legislation_sn,0,'FORCE');
      $match_legislation_sn = array_filter(array_combine($match_legislation_sn[2],$match_legislation_sn[4]));
      $status_list = array();
      foreach ( $match_legislation_sn as $prefix => $suffix ) {
        $M = NULL;
        switch( $prefix ) {
          case 'RA': $M = 'RepublicAct'; break; 
          case 'SB': $M = 'SenateBill'; break; 
          case 'HB': $M = 'HouseBill' ; break;
        }
        if ( !is_null($M) && class_exists("{$M}DocumentModel") ) {
          $M = "{$M}DocumentModel"; 
          $M = new $M();
          $M->where(array('AND' => array(
            'sn' => "REGEXP '({$prefix})([0]*)({$suffix})'"
          )))->recordfetch_setup();
          $record = array();
          if ( $M->recordfetch($record) ) {
            // $this->recursive_dump($record,0,'FORCE');
            $status_list[] = array(
              'regex' => "@({$prefix})([0]*)({$suffix})@",
              'subst' => <<<EOH
<a class="legiscope-remote cached" href="{$record['url']}" title="{$record['sn']}">{$record['sn']}</a>
EOH
            );
          } else {
            $this->syslog(__FUNCTION__,'FORCE',"- NO MATCH {$prefix}{$suffix}");
          }
        }
      }
      $M = NULL;
      foreach ( $status_list as $subst ) {
        $subject = preg_replace($subst['regex'], $subst['subst'], $subject);
      }
    }
    return $subject;
  }/*}}}*/

  function & current_tag() {
    $this->pop_tagstack();
    $this->push_tagstack();
    return $this;
  }

  function standard_cdata_container_open() {/*{{{*/
    if ( 0 < count($this->tag_stack) ) {
      $this->pop_tagstack();
      $this->current_tag['cdata'] = array();
      $this->push_tagstack();
    }
  }/*}}}*/
  
  function standard_cdata_container_cdata() {/*{{{*/
    if ( !empty($cdata) && ( 0 < count($this->tag_stack) ) ) {
      $this->pop_tagstack();
      $this->current_tag['cdata'][] = str_replace("\n"," ",trim($cdata));
      $this->push_tagstack();
    }
    return TRUE;
  }/*}}}*/

  function standard_cdata_container_close() {
    $this->current_tag();
    $paragraph = array(
      'text' => join('', $this->current_tag['cdata']),
      'seq'  => $this->current_tag['attrs']['seq'],
    );
    if ( 0 < strlen($paragraph['text']) ) 
    $this->add_to_container_stack($paragraph);
    return TRUE;
  }

  function embed_cdata_in_parent() {
    $i = $this->pop_tagstack();
    $content = join('',$this->current_tag['cdata']);
    $parent = $this->pop_tagstack();
    $parent['cdata'][] = " {$content} ";
    $this->push_tagstack($parent);
    $this->push_tagstack($i);
    return FALSE;
  }

  function reduce_containers_to_string($s) {
    $s = is_array($s) ? array_values($s) : NULL;
    $s = is_array($s) && 1 == count($s) ? array_values($s[0]) : NULL;
    // $s = is_array($s) ? join(' ',$s) : NULL;
    $s = is_array($s) ? trim($s[0]) : NULL;
    return $s;
  }

}
