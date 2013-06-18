<?php

/*
 * Class GenericParseUtility
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class GenericParseUtility extends RawparseUtility {
  
	var $debug_tags = NULL;

  function __construct() {
    parent::__construct();
		$this->debug_tags = FALSE;
  }

  function common_unhandled_page_parser(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    $this->syslog( __FUNCTION__, __LINE__, "Invoked for " . $urlmodel->get_url() );
    /** SVN #418 (internal): Loading raw page content breaks processing of committee information page **/
    $common = new GenericParseUtility();
    $common->
      set_parent_url($urlmodel->get_url())->
      parse_html($urlmodel->get_pagecontent(),$urlmodel->get_response_header());
    $pagecontent = str_replace('[BR]','<br/>',join('',$common->get_filtered_doc()));
  }/*}}}*/

  // ------------ Specific HTML tag handler methods -------------

  function ru_script_open(& $parser, & $attrs) {/*{{{*/
    $tagname = strtolower(preg_replace('/^ru_([^_]*)_open/','$1', __FUNCTION__));
    return FALSE;
  }/*}}}*/
  function ru_script_cdata(& $parser, & $cdata) {/*{{{*/
    // $this->syslog(__FUNCTION__,__LINE__,"{$cdata}");
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
    $this->pop_tagstack();
    $this->current_tag['cdata'] = array();
    $this->push_tagstack();
    return TRUE;
  }  /*}}}*/
  function ru_iframe_cdata(& $parser, & $cdata) {/*{{{*/
    // Attach CDATA to IFRAME tag 
    $this->pop_tagstack();
    $this->current_tag['cdata'][] = $cdata;
    $this->push_tagstack();
    return TRUE;
  }/*}}}*/
  function ru_iframe_close(& $parser, $tag) {/*{{{*/
    // An IFRAME is similar to an anchor A tag in that it's source URL is relevant 
    $this->current_tag();
    $target = $this->current_tag['attrs']['SRC'];
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
    $attrs['ACTION'] = isset($this->current_tag['attrs']['ACTION']) ? $this->current_tag['attrs']['ACTION'] : NULL;
    $this->push_tagstack();
    $this->push_container_def($tagname, $attrs);
    return TRUE;
  }/*}}}*/
  function ru_form_cdata(& $parser, & $cdata) {/*{{{*/
    return TRUE;
  }/*}}}*/
  function ru_form_close(& $parser, $tag) {/*{{{*/
		$this->current_tag();
    $this->stack_to_containers();
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

  function ru_table_open(& $parser, & $attrs, $tagname) {/*{{{*/
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
      // $this->syslog(__FUNCTION__,__LINE__,"Adding line break to {$parent['tag']} (" . join(' ', $parent['cdata']) . ")" );
      $parent['cdata'][] = "\n[BR]";
    }
    $this->push_tagstack($parent);
    $this->push_tagstack($me);
    return FALSE;
  }/*}}}*/

  function ru_p_open(& $parser, & $attrs, $tag) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'] = array();
    $this->push_tagstack();
    return TRUE;
  }  /*}}}*/
  function ru_p_cdata(& $parser, & $cdata) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'][] = str_replace("\n"," ",trim($cdata));
    $this->push_tagstack();
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
		if (0 < strlen(trim($paragraph['text'])))
    $this->add_to_container_stack($paragraph);
		if ( $this->debug_tags ) $this->syslog(__FUNCTION__, __LINE__, "(marker) - -- - -- - {$this->current_tag['tag']} {$paragraph['text']}");
    return TRUE;
  }/*}}}*/

  function ru_a_open(& $parser, & $attrs, $tag) {/*{{{*/
    // Handle A anchor/link tags
    $this->pop_tagstack();
    $this->current_tag['cdata'] = array();
    $this->current_tag['attrs']['class'] = 'legiscope-remote';
    $this->push_tagstack();
    array_push($this->links, $this->current_tag); 
    return TRUE;
  }  /*}}}*/
  function ru_a_cdata(& $parser, & $cdata) {/*{{{*/
    // Attach CDATA to A tag 
    $this->pop_tagstack();
    $link = array_pop($this->links);
    $link['cdata'][] = $cdata; 
    $this->current_tag['cdata'][] = $cdata; 
    array_push($this->links, $link);
    $this->push_tagstack();
    return TRUE;
  }/*}}}*/
  function ru_a_close(& $parser, $tag) {/*{{{*/
    $this->current_tag();
    $target = 0 < count($this->links) ? array_pop($this->links) : NULL;
		$link_data = $this->collapse_current_tag_link_data();
    if ( !(empty($link_data['url']) && empty($link_data['text'])) ) {
      array_push($this->links, $target);
      $this->add_to_container_stack($link_data);
    }
    return TRUE;
  }/*}}}*/

  function ru_div_open(& $parser, & $attrs, & $tag) {/*{{{*/
    // Handle presentational/structural container DIV
    $this->push_container_def($tag, $attrs);
    return TRUE;
  }/*}}}*/
  function ru_div_close(& $parser, $tag) {/*{{{*/
    $this->stack_to_containers();
    return TRUE;
  }/*}}}*/

  function ru_blockquote_open(& $parser, & $attrs, $tagname) {/*{{{*/
    return TRUE;
  }/*}}}*/
  function ru_blockquote_close(& $parser, $tag) {/*{{{*/
    return TRUE;
  }/*}}}*/

  ///   UTILITY METHODS   ///

  function replace_legislative_sn_hotlinks($subject) {/*{{{*/
    $match_legislation_sn = array();
    if ( preg_match_all('@((RA|HB|SB|HR)([0]*([0-9]*)))@', "{$subject}", $match_legislation_sn) ) {
      // $this->syslog( __FUNCTION__, __LINE__, "- Status {$bill['bill']} {$subject}" );
      // $this->recursive_dump($match_legislation_sn,__LINE__);
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
          if ( $M->recordfetch($record,TRUE) ) {
            // $this->recursive_dump($record,__LINE__);
            $status_list[] = array(
              'regex' => "@({$prefix})([0]*)({$suffix})@",
              'subst' => <<<EOH
<a class="legiscope-remote cached" href="{$record['url']}" title="{$record['sn']}">{$record['sn']}</a>
EOH
            );
          } else {
            $this->syslog(__FUNCTION__,__LINE__,"- NO MATCH {$prefix}{$suffix}");
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
  function standard_cdata_container_close() {/*{{{*/
    $this->current_tag();
    $paragraph = array(
      'text' => join('', $this->current_tag['cdata']),
      'seq'  => $this->current_tag['attrs']['seq'],
    );
    if ( 0 < strlen($paragraph['text']) ) 
    $this->add_to_container_stack($paragraph);
    return TRUE;
  }/*}}}*/

	/** Miscellaneous utility methods used in one or more derived classes **/

  function reduce_containers_to_string($s) {/*{{{*/
    $s = is_array($s) ? array_values($s) : NULL;
    $s = is_array($s) && 1 == count($s) ? array_values(array_element($s,0,array())) : NULL;
    $s = is_array($s) ? trim(array_element($s,0)) : NULL;
    return $s;
  }/*}}}*/

	function get_faux_url(UrlModel & $url, & $metalink) {
		return self::get_faux_url_s($url, $metalink);
	}

	static function decode_metalink_post(& $metalink) {
		$runtime_metalink_info = filter_post('LEGISCOPE', array());
		if ( is_string($metalink) ) $metalink = json_decode(base64_decode($metalink), TRUE);
		if ( is_array($runtime_metalink_info) && is_array($metalink) ) $metalink = array_merge($metalink, $runtime_metalink_info);
		return $metalink;
	}

  static function get_faux_url_s(UrlModel & $url, & $metalink) {/*{{{*/
    $faux_url = NULL;
    if ( !is_null($metalink) ) {/*{{{*/// Modify $this->seek_cache_filename if POST data is received
      // The POST action may be a URL which permits a GET action,
      // in which case we need to use a fake URL to store the results of 
      // the POST.  We'll generate the fake URL here. 
      $metalink = static::decode_metalink_post($metalink);
      // Prepare faux metalink URL by combining the metalink components
      // with POST target URL query components. After the POST, a new cookie
      // may be returned; if so, it will be used to traverse sibling links
      // which content hasn't yet been cached in the UrlModel backing store.
      if ( $metalink == FALSE ) {
        $metalink = NULL;
      } else if ( 0 < count($metalink) ) {/*{{{*/

        $faux_url = UrlModel::construct_metalink_fake_url($url, $metalink);
				$original_url = $url->get_url();
				$url->fetch(UrlModel::get_url_hash($faux_url),'urlhash');
				$url->set_url($original_url,FALSE);
				if ( !$url->in_database() ) {
					$url->fetch(UrlModel::get_url_hash($original_url),'urlhash');
				}

      }/*}}}*/
      else $metalink = NULL;
    }/*}}}*/
    return $faux_url;
  }/*}}}*/

  function extract_pager_links(array & $links, $cluster_urldefs, $url_uuid = NULL, $parent_state = NULL, $insert_p1_link = FALSE) {/*{{{*/
    $debug_method    = FALSE;
    $check_cache     = TRUE;
    $links           = array();
    $pager_links     = array();
    $senate_bill_url = new UrlModel();

    if ( $debug_method ) $this->recursive_dump($cluster_urldefs,'(warning) ' . __METHOD__);
    //  20130401 - Typical entries found 
    //  1cb903bd644be9596931e7c368676982 =>
    //    query_base_url => http://www.senate.gov.ph/lis/pdf_sys.aspx
    //    query_template => congress=15&type=republic_act&p=({PARAMS})
    //    query_components =>
    //       93e32b68fb9806571523b93e5ca786da => 2|3|4|5|6|7|8|9|10
    //    whole_url => http://www.senate.gov.ph/lis/pdf_sys.aspx?congress=15&type=republic_act&p=({PARAMS})
    //  9f35fc4cce1f01b32697e7c34b397a99 =>
    //    query_base_url => http://www.senate.gov.ph/lis/pdf_sys.aspx
    //    query_template => type=republic_act&congress=({PARAMS})
    //    query_components =>
    //       361d558f79a9d15a277468b313f49528 => 15|14|13
    //    whole_url => http://www.senate.gov.ph/lis/pdf_sys.aspx?type=republic_act&congress=({PARAMS})

    if ( is_array($cluster_urldefs) && ( 0 < count($cluster_urldefs) ) ) {
      if ( !array_key_exists($url_uuid, $cluster_urldefs) ) {
        $this->syslog(__FUNCTION__,__LINE__,"(warning) - - - - EXCEPTION THROW {$url_uuid}");
        $this->recursive_dump($cluster_urldefs,"(marker) - - - - - - -");
        throw new Exception(__METHOD__ . ": No pager matches UUID {$url_uuid}");
      }
      foreach( $cluster_urldefs as $url_uid => $urldef ) {/*{{{*/
        if ( !is_null($url_uuid) && !($url_uid == $url_uuid ) ) continue;
        $counter = 0;
        $have_pullin_link = FALSE;
        foreach ( $urldef['query_components'] as $parameters ) {/*{{{*/// Loop over variable query components
          $parameters = array_flip(explode('|', $parameters));
        if ($insert_p1_link && !array_key_exists(1, $parameters) ) $parameters[1] = 1;
        ksort($parameters);
        $parameters = array_keys($parameters);
        foreach ( $parameters as $parameter ) {/*{{{*/
          $counter++;
          $link_class = array("legiscope-remote");
          $href = str_replace('({PARAMS})',"{$parameter}","{$urldef['whole_url']}");
          $urlhash = UrlModel::get_url_hash($href);
          if ( $check_cache ) {/*{{{*/
            $senate_bill_url->fetch($urlhash,'urlhash');
            $is_in_cache = $senate_bill_url->in_database();
            if ( $is_in_cache ) $link_class[] = 'cached';
            if ( ($counter >= 5 || !$is_in_cache) && !$have_pullin_link ) {
              $have_pullin_link = TRUE;
            }
          }/*}}}*/
          if ( !is_null($parent_state) ) {/*{{{*/
            // Client-side JS sees a selector class attribute 'fauxpost'
            $link_class = array("fauxpost");
            $link_components = UrlModel::parse_url($href);
            $query_parameters = array_merge(
              array('_' => '1'),
              $parent_state,
              UrlModel::decompose_query_parts($link_components['query'])
            ); 
            $senate_bill_url->set_url($href,FALSE);
            // $this->recursive_dump($query_parameters,"(marker) A");
            $link = $this->get_faux_url($senate_bill_url,$query_parameters);
            $links[UrlModel::get_url_hash($link)] = $link;  
            $link = UrlModel::create_metalink($parameter, $href, $query_parameters, join(' ', $link_class));
          }/*}}}*/
          else $links[$urlhash] = $href;
          $link_class = join(' ',$link_class);
          if ( is_null($parent_state) ) $link = <<<EOH
<span class="link-faux-menuitem"><a class="{$link_class}" href="{$href}" id="{$urlhash}">{$parameter}</a></span>

EOH;
          $pager_links[] = $link;
        }/*}}}*/
        }/*}}}*/
      }/*}}}*/
    }
    return $pager_links;
  }/*}}}*/

  final function generate_linkset($url, $only_key = NULL) {/*{{{*/

    $debug_method = FALSE;

		$return_value = array(
      'linkset' => array(),
      'urlhashes' => array(),
      'cluster_urls' => array(),
    );

    if ( empty($url) ) throw new Exception(get_class($this));

    $parent_page = new UrlModel($url,TRUE);

		if ( !$parent_page->in_database() ) return $return_value;

    $cluster = new UrlClusterModel();

		$containers = $this->get_containers();

    $link_generator = create_function('$a', <<<EOH
return '<li><a class="legiscope-remote {cached-' . \$a["urlhash"] . '}" id="' . \$a["urlhash"] . '" href="' . \$a["url"] . '" title="'.\$a["origpath"] . ' ' . md5(\$a["url"]) . '" target="legiscope">' . (0 < strlen(\$a["text"]) ? \$a["text"] : '[Anchor]') . '</a><span class="reload-texticon legiscope-refresh {refresh-' . \$a["urlhash"] . '}" id="refresh-' . \$a["urlhash"] . '">reload</span></li>';
EOH
    );
    $hash_extractor = create_function('$a', <<<EOH
return array( 'hash' => \$a['urlhash'], 'url' => \$a['url'] ); 
EOH
    );

    // Each container (div, table, or head)  encloses a set of tags
    // Generate clusters of links
    $linkset             = array();
    $urlhashes           = array();
    $pager_clusters      = array();
    $cluster_urls        = array();
    $container_counter   = 0;
    $parent_page_urlhash = $parent_page->get_urlhash();
		$subject_host_hash   = UrlModel::get_url_hash($parent_page->get_url(),PHP_URL_HOST);

    $cluster_list = $cluster->fetch_clusters($parent_page,TRUE);

		if ( is_array($containers) ) foreach ( $containers as $container ) {/*{{{*/

      // $this->syslog( __FUNCTION__, __LINE__, "Container #{$container_counter}");
      // $this->recursive_dump($container, __LINE__);

      if ( $container['tagname'] == 'head' ) continue;

      $raw_links            = $this->normalize_links($url, $container['children']);
      $normalized_links     = array();

      // Deduplication and pager detection (link clusters sharing query parameters)
      $skip_pager_detection = FALSE;
      $query_part_hashes    = array();
      $query_hash           = '';

      foreach ( $raw_links as $linkitem ) {/*{{{*/
        if ( array_key_exists($linkitem['urlhash'], $normalized_links) ) continue;
        // Use a PCRE regex to extract query key-value pairs
        $parsed_url = parse_url($linkitem['url']);
        $query_match = '@([^=]*)=([^&]*)&?@';
        $match_parts = array(NULL,NULL,NULL);
        if ( array_key_exists('query', $parsed_url) ) preg_match_all($query_match, $parsed_url['query'], $query_match);
        unset($parsed_url['query']);
        $query_match = array_filter(array(
          $query_match[1],
          $query_match[2],
        ));
        $linkitem['base_url'] = NULL;
        // Iterate through the nonempty set of matched key-value pairs
        if ( is_array($query_match) && ( 0 < count($query_match) ) && is_array($query_match[0]) && is_array($query_match[1]) ) {/*{{{*/
          $query_match = array_combine($query_match[0], $query_match[1]);
          ksort($query_match);
          // Hash all the keys, and count occurrences of that entire set
          $query_hash = UrlModel::get_url_hash(join('!',array_keys($query_match)));
          if ( !array_key_exists($query_hash,$query_part_hashes) ) $query_part_hashes[$query_hash] = array();
          // Then count the occurrence of value elements
          foreach ( $query_match as $key => $val ) {
            if ( !array_key_exists($key, $query_part_hashes[$query_hash]) )
              $query_part_hashes[$query_hash][$key] = array();
            if ( !array_key_exists($val, $query_part_hashes[$query_hash][$key]) )
              $query_part_hashes[$query_hash][$key][$val] = 0;
            $query_part_hashes[$query_hash][$key][$val]++;
          }
          $query_part_hashes[$query_hash]['BASE_URL'] = UrlModel::recompose_url($parsed_url);
          $linkitem['query_parts'] = $query_match;

        }/*}}}*/
        else $skip_pager_detection = TRUE;
        $normalized_links[$linkitem['urlhash']] = $linkitem;
        // $this->syslog(__FUNCTION__, __LINE__, $linkitem['url']);
      }/*}}}*/

      // $this->recursive_dump($normalized_links, __LINE__);
      if ( !($skip_pager_detection || ( 0 < strlen($query_hash)) ) ) {/*{{{*/
        // $this->syslog(__FUNCTION__, __LINE__, "- Skip container: {$query_hash}" );
        // $this->recursive_dump($query_part_hashes[$query_hash],__LINE__);
        continue;
      }/*}}}*/

      if ( $debug_method ) $this->syslog(__FUNCTION__, __LINE__, "(warning) -------- Normalized links, query hash [{$query_hash}]" );

      $linkset_class = array("link-cluster");
      if ( !$skip_pager_detection && ( 0 < strlen($query_hash) ) && is_array($query_part_hashes[$query_hash]) ) {/*{{{*/
        // Evaluate whether this set of URLs is a pager (from a hash of query components)
        $occurrence_count = NULL;
        $variable_params  = array();
        $fixed_params     = array();
        $base_url         = NULL;
        //$this->recursive_dump($pager_clusters, __LINE__);
        foreach ($query_part_hashes[$query_hash] as $query_param_name => $occurrences ) {/*{{{*/
          if ( $query_param_name == 'BASE_URL' ) {
            $base_url = $occurrences;
            // $this->syslog(__FUNCTION__, __LINE__, "-------- Base URL for this set: [{$base_url}]" );
            // $this->recursive_dump($query_part_hashes[$query_hash], __LINE__);
            continue;
          }
          if ( 1 == count($occurrences) ) {
            // If a query part has a fixed value between all links found in the link set,
            // then any other query part which also has a fixed value must 
            // occur the same number of times.
            list( $param_value, $param_occurrences ) = each( $occurrences );
            if ( is_null($occurrence_count) ) $occurrence_count = $param_occurrences;
            else if ( $occurrence_count != $param_occurrences ) {
              // One of the [more than 1] query parameters does not occur with the same frequency
              // as the other fixed query parameters for this set of links.
              // We should not treat the set of links as a pager.
            }
            // $this->syslog( __FUNCTION__, __LINE__, "- Key-value pair {$query_param_name}:{$param_value} is a fixed pager parameter occurring {$param_occurrences} times" );
            $fixed_params[] = "{$query_param_name}={$param_value}";
          } else {
            if ( 0 == count($variable_params) ) { 
              $variable_params = array(
                'key'    => $query_param_name,
                'values' => array_keys($occurrences),
              );
              // $this->syslog( __FUNCTION__, __LINE__, "- Key {$query_param_name} is a variable pager parameter with " . count($occurrences) . " keys" );
            } else {
              // At this time (SVN #332) we'll only accept a single variable parameter.
              $occurrence_count = NULL;
              break;
            }
          }
        }/*}}}*/
        if ( !is_null($occurrence_count) && (0 < count($variable_params)) ) {/*{{{*/
          $fixed_params[]  = "{$variable_params['key']}=(". join('|',$variable_params['values']) .")";
          $variable_params = join('&',$fixed_params);
          $fixed_params    = preg_replace('@\(([^)]*)\)@','({PARAMS})', $variable_params); 
          $variable_params = preg_replace('@^(.*)\(([^)]*)\)(.*)@','$2', $variable_params); 
          // Get the hash of the query template
          $component_set_hash = md5($variable_params);
          if ( array_key_exists($query_hash, $cluster_urls) ) {
            $cluster_urls[$query_hash]['query_components'][$component_set_hash] = $variable_params;
          } else {
            $cluster_urls[$query_hash] = array(
              'query_base_url' => $base_url,
              'query_template' => $fixed_params,
              'query_components' => array($component_set_hash => $variable_params),
            );
          }
          $linkset_class[] = 'linkset-pager';
        }/*}}}*/
      }/*}}}*/
      $normalized_links = array_values($normalized_links);
      // Create a unique identifier based on the sorted list of URLs contained in this cluster of links
      if ( 0 == count($normalized_links) ) continue;
      // LINK CLUSTER ID
      $contained_url_set_hash = sha1(join('-',array_filter(array_map(create_function('$a','return $a["urlhash"];'),$normalized_links))));
      $linklist       = join('',array_map($link_generator, $normalized_links));
      $linkset_class  = join(' ', $linkset_class);
      $linkset_id     = "{$subject_host_hash}-{$parent_page_urlhash}-{$contained_url_set_hash}";
      $linklist       = "<ul class=\"{$linkset_class}\" id=\"{$linkset_id}\" title=\"Cluster {$contained_url_set_hash}\">{$linklist}</ul>";
      // Reorder URLs by imposing an ordinal key on this array $linkset
      if ( !array_key_exists($contained_url_set_hash, $pager_clusters) ) {
         $linkset[$contained_url_set_hash] = "{$linklist}<hr/>";
      }
      $pager_clusters[$contained_url_set_hash] = array_key_exists($contained_url_set_hash,$cluster_list) ? $cluster_list[$contained_url_set_hash]['id'] : NULL;
      $url_hashes     = array_filter(array_map($hash_extractor, $normalized_links));
      if ( !(0 < count($url_hashes) ) ) continue;
      foreach ( $url_hashes as $url_hash_pairs ) {
        $urlhashes[$url_hash_pairs['hash']] = $url_hash_pairs['url'];
      }
      $container_counter++;
    }/*}}}*/

    // Now add clusters missing from the database
    $not_in_clusterlist = array_filter(array_map(create_function(
      '$a', 'return is_null($a) ? 1 : NULL;'
    ), $pager_clusters));

    if ( 0 < count($not_in_clusterlist) ) {/*{{{*/
      foreach ( $not_in_clusterlist as $clusterid => $nonce ) {
        $cluster->fetch($parent_page, $clusterid);
        $cluster->
          set_clusterid($clusterid)->
          set_parent_page($parent_page->get_urlhash())->
          set_position(100)->
          set_host($subject_host_hash)->
          stow();
      }
      // At this point, the list order is updated by fetching the cluster list
      $cluster_list = $cluster->fetch_clusters($parent_page,TRUE);
    }/*}}}*/

    // Now use the cluster list to obtain list position
    ksort($cluster_list);
    // Reduce the cluster list to elements that are also in the linkset on this page.
    array_walk($cluster_list,create_function(
      '& $a, $k, $s', '$a = array_key_exists($k,$s) ? $a : NULL;'),
      $linkset);
    $cluster_list = array_filter($cluster_list);
    $cluster_list = array_map(create_function('$a','return $a["position"];'),$cluster_list);
    if ( is_array($cluster_list) && is_array($linkset) && (0 < count($cluster_list)) && count($cluster_list) == count($linkset) ) {
      ksort($linkset);
      $linkset = array_combine(
        $cluster_list,
        $linkset
      );
      ksort($linkset);
    } else {
      $this->syslog( __FUNCTION__, __LINE__, "(warning) Mismatch between link and cluster link tables" );
      $this->syslog( __FUNCTION__, __LINE__, "(warning)  Linkset: " . count($linkset) );
      $this->syslog( __FUNCTION__, __LINE__, "(warning) Clusters: " . count($cluster_list) );
    }
    ksort($urlhashes);

    if ( 0 < count($cluster_urls) ) {/*{{{*/
      // $this->syslog(__FUNCTION__, __LINE__, "- Found pager query parameters" );
      // $this->recursive_dump($cluster_urls,__LINE__);
      // Normalize pager query URLs
      foreach( $cluster_urls as $cluster_url_uid => $pager_def ) {
        $urlparts = UrlModel::parse_url($pager_def['query_base_url']);
        $urlparts['query'] = $pager_def['query_template'];
        $cluster_urls[$cluster_url_uid]['whole_url'] = UrlModel::recompose_url($urlparts);
      }
    }/*}}}*/
    // Initial implementation of recordset iterator (not from in-memory array)
    $this->syslog( __FUNCTION__, __LINE__, "-- Selecting a cluster of URLs associated with {$url}" );
    $record = array();
    // FIXME: Break up long queries like this at the caller level.
    // For general-purpose use, limits to the total SQL query length
    // allow for a reasonably usable mechanism for executing 
    // SELECT ... WHERE a IN (<list>)
    // query statements with short lists, without additional code complexity.
		// Partition the list of hashes

		$partitioned_list = $this->partition_array( $urlhashes, 10 );

    $url_cache_iterator = new UrlModel();
    $idlist             = array();
    $linkset            = join("\n", $linkset);

		if ( $debug_method ) {
			$this->recursive_dump($partitioned_list,"(marker)");
			$this->syslog( __FUNCTION__, __LINE__, "(marker) Partitions: " . count($urlhashes));
		}

		$n = 0;
    foreach ( $partitioned_list as $partition_index => $partition ) {  /*{{{*/
      $url_cache_iterator->
        where(array('urlhash' => array_keys($partition)))->
        recordfetch_setup();
      $hashlist = array();
      // Collect all URL hashes.
      // Construct UrlEdgeModel join table entries for extant records. 
      while ( $url_cache_iterator->recordfetch($result) ) {/*{{{*/
        // $this->recursive_dump($result, $h > 2 ? '(skip)' : __LINE__);
        $idlist[] = $result['id'];
        $hashlist[] = $result['urlhash'];
        $n++;
      }/*}}}*/
      $hashlist = join('|', $hashlist);
      // Add 'cached' and 'refresh' class to selectors
      $linkset = preg_replace('@\{cached-('.$hashlist.')\}@','cached', $linkset);
      // TODO: Implement link aging
      $linkset = preg_replace('@\{refresh-('.$hashlist.')\}@','refresh', $linkset);
			if ( $debug_method ) $this->syslog( __FUNCTION__, __LINE__, "-- Marker {$n}/{$partition_index} links on {$url} as being 'cached'" );
    }/*}}}*/

    // Stow edges.  This should probably be performed in a stored procedure.
    if (!(TRUE == C('DISABLE_AUTOMATIC_URL_EDGES'))) {/*{{{*/
      $edge = new UrlEdgeModel();
      foreach ( $idlist as $idval ) {/*{{{*/
        $edge->fetch($parent_page->id, $idval);
        if ( !$edge->in_database() ) {
          $edge->stow($parent_page->id, $idval);
        }
      }/*}}}*/
    }/*}}}*/

    $linkset = preg_replace('@\{(cached|refresh)-([0-9a-z]*)\}@i','', $linkset);

    $return_value = array(
      'linkset' => $linkset,
      'urlhashes' => $urlhashes,
      'cluster_urls' => $cluster_urls,
    );

		return is_null($only_key) ? $return_value : $return_value[$only_key];

  }/*}}}*/

  private final function normalize_links($source_url, $container = NULL ) {/*{{{*/
    // -- Construct sitemap
    $parent_url = UrlModel::parse_url($source_url);
    $normalized_links = array();

    foreach ( is_null($container) ? $this->get_links() : $container as $link_item ) {/*{{{*/

      if ( FALSE === ($normalized_link = UrlModel::normalize_url($parent_url, $link_item)) ) continue;

      // $this->syslog( __FUNCTION__, __LINE__, "{$normalized_link} ({$link_item['text']}) <- {$link_item['url']} [" . join(',',array_keys($link_item['urlparts'])) . " -> " .join(',',$link_item['urlparts']) . "] <{$q['path']}>");

      $normalized_links[] = array(
        'url'      => $normalized_link,
        'urlhash'  => UrlModel::get_url_hash($normalized_link),
        'origpath' => $link_item['url'],
        'text'     => $link_item['text'],
      );
    }/*}}}*/

    return $normalized_links;
  }/*}}}*/

	function reorder_url_array_by_queryfragment(& $q, $fragment) { /*{{{*/
		// Reorder an array of (text,url) pairs in place,
		// using a regex-matched fragment of the URL
		array_walk($q,create_function(
			'& $a, $k', '$matches = array(); if ( 1 == preg_match("@'.$fragment.'@i", array_element($a,"url"), $matches) ) $a["seq"] = array_element($matches,1); else $a["seq"] = 0;'  
		));
		// Reorder array
		$q = array_combine(
			array_map(create_function('$a','return array_element($a,"seq");'),$q),
			array_map(create_function('$a','return array("url" => array_element($a,"url"), "text" => array_element($a,"text"), "cached" => array_element($a,"cached"));'),$q)
			// array_map(create_function('$a','return array("url" => $a["url"], "text" => $a["text"], "cached" => !array_key_exists("_", $a));'),$q)
		);
		krsort($q);
	}/*}}}*/

	function partition_array( & $k_v_array, $n ) {/*{{{*/
    $partition_index  = 0;
    $partitioned_list = array(0 => array());
    foreach ( $k_v_array as $urlhash => $url ) {/*{{{*/
      $partitioned_list[$partition_index][$urlhash] = $url;
      if ( count($partitioned_list[$partition_index]) >= $n ) {
        // $this->syslog( __FUNCTION__, __LINE__, "- Tick {$partition_index}");
        $partition_index++;
        $partitioned_list[$partition_index] = array();
      }
    }/*}}}*/
    $n = 0;

		array_walk($partitioned_list,create_function(
			'& $a, $k', 'if ( !(0 < count($a) ) ) $a = NULL;'
		));

		$partitioned_list = array_filter($partitioned_list);

		return $partitioned_list;
	}/*}}}*/

}
