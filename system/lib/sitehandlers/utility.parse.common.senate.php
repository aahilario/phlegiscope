<?php

/*
 * Class SenateCommonParseUtility
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class SenateCommonParseUtility extends GenericParseUtility {
  
  function __construct() {
    parent::__construct();
  }

  function ru_head_open(& $parser, & $attrs, $tagname) {/*{{{*/
    $this->push_container_def($tagname, $attrs);
    return TRUE;
  }/*}}}*/
  function ru_head_close(& $parser, $tag) {/*{{{*/
    array_pop($this->container_stack);
    return FALSE;
  }/*}}}*/

  function ru_div_open(& $parser, & $attrs, $tagname ) {/*{{{*/
    if ($this->debug_tags) $this->syslog( __FUNCTION__, __LINE__, $this->get_stacktags() . "--- {$this->current_tag['tag']}" );
    $this->pop_tagstack();
    $this->current_tag['cdata'] = array();
    $this->push_tagstack();
    if ($this->debug_tags) $this->recursive_dump($attrs,__LINE__);
    $this->push_container_def($tagname, $attrs);
    return TRUE;
  }/*}}}*/
  function ru_div_cdata(& $parser, & $cdata) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'][] = $cdata;
    $this->push_tagstack();
    if ($this->debug_tags) $this->syslog( __FUNCTION__, __LINE__, $this->get_stacktags() . "--- {$this->current_tag['tag']} {$cdata}" );
    return TRUE;
  }/*}}}*/
  function ru_div_close(& $parser, $tag) {/*{{{*/
    $skip = FALSE;
    $this->pop_tagstack();
    if ( is_array($this->current_tag) && array_key_exists('attrs', $this->current_tag) ) {
      if ( array_key_exists($this->current_tag['attrs']['CLASS'],array_flip(array(
        'nav_dropdown',
        'div_hidden',
        'more',
        'sidepane_nav',
        'header_pane',
      )))) $skip = TRUE;
      if ( array_key_exists($this->current_tag['attrs']['ID'],array_flip(array(
        'nav_top',
        'nav_bottom',
      )))) $skip = TRUE;
      if ( $skip && $this->debug_tags ) {
        usleep(20000);
        $tag_cdata = array_key_exists('cdata', $this->current_tag) ? join('', $this->current_tag['cdata']) : '--empty--';
        $this->syslog(__FUNCTION__,__LINE__,"(marker) Warning - Rejecting tag with CDATA [{$tag_cdata}]");
        $this->recursive_dump($this->current_tag,"(warning) {$tag}" );
      }
    }
    $this->push_tagstack();
    if (is_array($this->current_tag) && !$skip ) $this->stack_to_containers();
    
    return !$skip;
  }/*}}}*/

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

  function ru_a_open(& $parser, & $attrs, $tag) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'] = array();
    $this->update_current_tag_url('HREF');
    if ( !array_key_exists('CLASS', $this->current_tag['attrs']) ) {
      // $this->syslog(__FUNCTION__,__LINE__,"Setting CSS selector for '{$this->current_tag['attrs']['HREF']}'");
      $this->current_tag['attrs']['CLASS'] = 'legiscope-remote';
      $this->current_tag['attrs']['ID'] = UrlModel::get_url_hash($this->current_tag['attrs']['HREF']);
    } else {
      // $this->syslog(__FUNCTION__,__LINE__,"Adding CSS selector for '{$this->current_tag['attrs']['HREF']}'");
      $this->current_tag['attrs']['CLASS'] .= ' legiscope-remote';
    }
    $this->push_tagstack();
    if ($this->debug_tags) $this->syslog( __FUNCTION__, __LINE__, $this->get_stacktags() . " --- {$this->current_tag['tag']} {$this->current_tag['attrs']['HREF']}" );
    return TRUE;
  }/*}}}*/
  function ru_a_cdata(& $parser, & $cdata) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'][] = $cdata;
    $this->push_tagstack();
    if ($this->debug_tags) $this->syslog( __FUNCTION__, __LINE__, "--- {$this->current_tag['tag']} {$cdata}" );
    return TRUE;
  }/*}}}*/
  function ru_a_close(& $parser, $tag) {/*{{{*/
    $this->pop_tagstack();
    $link_data = $this->collapse_current_tag_link_data();
    $this->add_to_container_stack($link_data);
    $this->push_tagstack();
    if ($this->debug_tags) $this->syslog( __FUNCTION__, __LINE__, "--- {$this->current_tag['tag']}" );
    return TRUE;
  }/*}}}*/

  function ru_link_open(& $parser, & $attrs, $tag) {/*{{{*/
    return TRUE;
  }  /*}}}*/
  function ru_link_cdata(& $parser, & $cdata) {/*{{{*/
    return TRUE;
  }/*}}}*/
  function ru_link_close(& $parser, $tag) {/*{{{*/
    return FALSE;
  }/*}}}*/

  function ru_style_open(& $parser, & $attrs, $tag) {/*{{{*/
    return TRUE;
  }  /*}}}*/
  function ru_style_cdata(& $parser, & $cdata) {/*{{{*/
    return TRUE;
  }/*}}}*/
  function ru_style_close(& $parser, $tag) {/*{{{*/
    return FALSE;
  }/*}}}*/

  function ru_img_open(& $parser, & $attrs, $tag) {/*{{{*/
    $this->pop_tagstack();
    $this->update_current_tag_url('SRC');
    $this->push_tagstack();
    return TRUE;
  }  /*}}}*/
  function ru_img_cdata(& $parser, & $cdata) {/*{{{*/
    return TRUE;
  }/*}}}*/
  function ru_img_close(& $parser, $tag) {/*{{{*/
    $this->current_tag();
    return !(1 == preg_match('@(nav_logo)@i',$this->current_tag['attrs']['CLASS']));
  }/*}}}*/

  function get_per_congress_pager(UrlModel & $urlmodel, UrlModel & $url_iterator, & $session, & $q, & $session_select) {/*{{{*/

    $per_congress_pager = '[---]';
    // Obtain matching session record
    $session_data = array_filter(array_map(create_function(
      '$a', 'return $a["metalink_source"]["dlBillType"] == "'.$session.'" ? $a["metalink"] : NULL;'
    ), $session_select));
    $linktext = array_filter(array_map(create_function(
      '$a', 'return $a["metalink_source"]["dlBillType"] == "'.$session.'" ? str_replace($a["linktext"],$a["optval"],$a["markup"]) : NULL;'
    ), $session_select));

    if ( is_array($session_data) ) {/*{{{*/
      // Extract the pager for the current Congress and Session series 
      $extracted_links = array();
      $session_data    = array_values($session_data);
      $session_data    = $session_data[0];
      $url_iterator->set_url($urlmodel->get_url(),FALSE);
      $cached_url      = $this->get_faux_url($url_iterator, $session_data);
      $in_db           = $url_iterator->set_url($cached_url,TRUE);
      if ( $in_db ) {/*{{{*/
        $this->
          reset()->
          set_parent_url($urlmodel->get_url())->
          parse_html($url_iterator->get_pagecontent(),$url_iterator->get_response_header());
        $cluster_urls = $this->generate_linkset($urlmodel->get_url());
        extract($cluster_urls);
        // These pager links are bound to the state variable "dlBillType",
        // which corresponds to the human-readable labels "First Regular Session",
        // "Second Regular Session", and so on.  We can simply extend
        // the set of state variables to include "page", so that we can
        // cache the URLs for those pages uniquely.
        //
        // The implementation here treats pager links differently when
        // using them to trigger a network fetch (live), from how these
        // links are treated to access site-local resource:  The unadorned
        // link is used in a cookie-aware request to the live site. Retrieved
        // content is stored using a fake URL to which is appended page-
        // specific query parameter values.  When
        // a spider operator requests content from the main site, the
        // unadorned link is "decorated" with the same fake URL + page
        // parameters.
        if ( is_array($cluster_urls) ) 
          $per_congress_pager = $this->extract_pager_links( $extracted_links, $cluster_urls,
            '1cb903bd644be9596931e7c368676982',
            $session_data
          );
        if ( is_null($first_page) ) $first_page = $cached_url;
        $per_congress_pager = join('', $per_congress_pager);
        $linktext = array_values($linktext);
        $session = $linktext[0];
        //////////////////////////////////////////////////////////////
        $r = array_combine(
          array_map(create_function('$a','return UrlModel::get_url_hash($a["url"]);'), $q),
          array_values($q)
        );

        if (is_array($r) && (0 < count($r)))
          foreach ( $extracted_links as $link ) {/*{{{*/
            $url_iterator->set_url($link,TRUE);
            $this->
              set_parent_url($url_iterator->get_url())->
              parse_html($url_iterator->get_pagecontent(),$url_iterator->get_response_header());
            $entries = $this->get_containers(
              'children[tagname=div][attrs:STYLE*=right|left|i]'
            );
            // Clear URL entries that exist
            foreach ( $entries as $found_links ) {
              // Null out URLs that are already in this set
              array_walk($found_links,create_function(
                '& $a, $k, $s', '$h = UrlModel::get_url_hash($a["url"]); if ( array_key_exists($h, $s) ) $a["url"] = NULL; else $a["hash"] = $h;'),
              $r
            );
              // Remove entries where the URL has been nulled out
              $found_links = array_filter(array_map(create_function(
                '$a', 'return is_null($a["url"]) ? NULL : $a;'
              ),$found_links));
              // Make the URL hash be the key for this array of links
              $r_hashes = array_map(create_function('$a','return $a["hash"];'), $found_links);
              $found_links = array_values(array_map(create_function('$a','return array("_" => "_", "url" => $a["url"],"text" => $a["text"]);'), $found_links));
              if ( is_array($r_hashes) && is_array($found_links) && (0 < count($r_hashes)) && (count($r_hashes) == count($found_links)) ) {
                $found_links = array_combine($r_hashes,$found_links);
                if ( is_array($found_links) && (0 < count($found_links)) ) $q = array_merge($q, $found_links);
              }
              $found_links = NULL;
            }
          }/*}}}*/
        //////////////////////////////////////////////////////////////
      }/*}}}*/
      else {/*{{{*/
        $linktext = array_values($linktext);
        $session = $linktext[0];
        $per_congress_pager = $this->extract_pager_links(
          $extracted_links,
          $cluster_urls,
          '1cb903bd644be9596931e7c368676982',
          $session_data
        );
        // $this->recursive_dump($extracted_links, "(marker) NI -");
        // $this->recursive_dump($session_select,'(marker) Missing entry');
        // $session = UrlModel::create_metalink($session, $session_data  
        $per_congress_pager = join('', $per_congress_pager);
      }/*}}}*/
    }/*}}}*/
    return $per_congress_pager;
  }/*}}}*/


}

