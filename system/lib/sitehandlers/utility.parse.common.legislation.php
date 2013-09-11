<?php

/*
 * Class LegislationCommonParseUtility
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class LegislationCommonParseUtility extends GenericParseUtility {
  
  var $restrict_length = NULL;

  var $column_markup_only_uncached = FALSE;

  function __construct() {
    parent::__construct();
  }

  function get_per_congress_pager(UrlModel & $urlmodel, & $session, $q, $session_select, $pager_uuid = '1cb903bd644be9596931e7c368676982') {/*{{{*/

    $debug_method = TRUE;

    if ( $debug_method ) {
      $this->syslog(__FUNCTION__,__LINE__,"(marker) - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -");
      $this->syslog(__FUNCTION__,__LINE__,"(marker) - - - - - Input: Session {$session}");
      $this->syslog(__FUNCTION__,__LINE__,"(marker) - -  Pager UUID: {$pager_uuid}");
      // $this->recursive_dump( $session_select, "(marker) - - - - -" );
    }

    $url_iterator = new UrlModel();

    $per_congress_pager = '[---]';
    // Obtain matching session record
    $session_data = method_exists($this,'session_select_to_session_data')
      ? $this->session_select_to_session_data($session_select, $session)
      : array_filter(array_map(create_function(
          '$a', 'return $a["metalink_source"]["dlBillType"] == "'.$session.'" ? $a["metalink"] : NULL;'
        ), $session_select));

    $linktext = method_exists($this,'session_select_to_linktext')
      ? $this->session_select_to_linktext($session_select, $session)
      : array_filter(array_map(create_function(
          '$a', 'return $a["metalink_source"]["dlBillType"] == "'.$session.'" ? str_replace($a["linktext"],$a["optval"],$a["markup"]) : NULL;'
        ), $session_select));

    $active = $session_select;
    $this->filter_nested_array($active,"optval[active=1]",0);
    $active = nonempty_array_element($active,0,'----') == $session;

    if ( $active ) $this->syslog(__FUNCTION__,__LINE__,"(marker) Processing active session selector now: {$session}");

    if ( $debug_method ) {
      $this->syslog(__FUNCTION__,__LINE__,"(marker) - - - + + Session data for {$session}");
      $this->recursive_dump( $session_data, "(marker) - - - + +" );
      $this->syslog(__FUNCTION__,__LINE__,"(marker) - - - * * Link text for {$session}");
      $this->recursive_dump( $linktext, "(marker) - - - * *" );
    }
    
    if ( is_array($session_data) ) {/*{{{*/
      // Extract the pager for the current Congress and Session series 
      $extracted_links = array();
      // Get zeroth element
      $session_data    = array_values($session_data);
      $session_data    = nonempty_array_element($session_data,0,$session_data);
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
        ///*{{{*/// Notes
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
        ///*}}}*/
        if ( is_array($cluster_urls) ) 
					$session_data_copy = $session_data;
					if ( $active ) {
						if ( array_key_exists('_LEGISCOPE_', $session_data) ) {
							$session_data_copy['_LEGISCOPE_']['no_skip_get'] = TRUE;
						}
					}
          $per_congress_pager = $this->extract_pager_links(
            $extracted_links,
            $cluster_urls,
            $pager_uuid,
            // Set _LEGISCOPE_['no_skip_get'] := TRUE to execute a POST and then a GET
            //     _LEGISCOPE_['skip_get']    := TRUE to execute just a POST action
            //     _ := 1 to execute just a GET action
            // array_merge($session_data,$active ? array() : array('_' => 'NOSKIPGET')),
            $session_data_copy,
            TRUE
          );
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
            $found_links = array_values(array_map(create_function('$a','return array("_" => "_", "url" => $a["url"],"text" => $a["text"], "inv" => $a["inv"]);'), $found_links));
            if ( is_array($r_hashes) && is_array($found_links) && (0 < count($r_hashes)) && (count($r_hashes) == count($found_links)) ) {
              $found_links = array_combine($r_hashes,$found_links);
              if ( is_array($found_links) && (0 < count($found_links)) ) $q = array_merge($q, $found_links);
            }
            $found_links = NULL;
          }
        }/*}}}*/
        //////////////////////////////////////////////////////////////
      }/*}}}*/
      else if ( !is_null($pager_uuid) && is_array($this->cluster_urldefs) && array_key_exists($pager_uuid,$this->cluster_urldefs) ) {/*{{{*/
        $linktext = array_values($linktext);
        $session = array_element($linktext,0,$linktext);
        $per_congress_pager = $this->extract_pager_links(
          $extracted_links,
          $this->cluster_urldefs,
          $pager_uuid,
          $session_data
        );
        $this->recursive_dump($per_congress_pager, "(marker) NI -");
        $per_congress_pager = join('', $per_congress_pager);
      }/*}}}*/
    }/*}}}*/

    return $per_congress_pager;
  }/*}}}*/

  function generate_links_and_bounds(& $documents, $url_fetch_regex, $link_regex) {/*{{{*/

    $debug_method = FALSE;
    // Now let's update the child links collection, using the database 
    if ( is_array($url_fetch_regex) ) {
      if ( $debug_method ) {
         $this->syslog(__FUNCTION__, __LINE__, "(marker) - - - - URL Match regex array:");
        $this->recursive_dump($url_fetch_regex,"(marker) - - - -");
      }
      $documents->
        where(array('AND' => $url_fetch_regex))->
        recordfetch_setup();
    } else {
      $this->syslog(__FUNCTION__, __LINE__, "(marker) - -  - - URL Match regex is [{$link_regex}]");
      $documents->where(array('AND' => array(
        'url' => 'REGEXP \''. $url_fetch_regex .'\'' 
      )))->recordfetch_setup();
    }

    $child_collection = array();
    $document         = NULL;
    $bounds           = array();
    $pagers           = array();
    $total_records    = 0;
    // Construct the nested array, compute span of serial numbers.
    // Pager links ('[PAGERx]') must be replaced 
    $can_invalidate = method_exists($documents,'get_invalidated');
    $have_inv = 0;
    while ( $documents->recordfetch($document) ) {/*{{{*/
      $datum_parts = array();
      $text        = array_element($document,'sn');
      $url_orig    = array_element($document,'url');
      $url         = urldecode($url_orig);
      preg_match("@{$link_regex}@i", $url, $datum_parts);
      $congress    = array_element($document,'congress_tag'); // $datum_parts[1];
      $srn         = intval(array_element($datum_parts,2,0));
      $alt_srn     = NULL;
      if ( $srn > C('LEGISCOPE_SENATE_DOC_SN_UBOUND') ) {/*{{{*/
        $alt_srn = ltrim(preg_replace('@[^0-9]@','',$text),'0');
        $this->syslog(__FUNCTION__, __LINE__, "(marker) Unusual boundary value found {$srn} from url {$url}. Using {$alt_srn} <- {$text}");
        $this->recursive_dump($document,"(marker) --- --- ---");
        if ( !(0 < $alt_srn) || (is_array($child_collection[$congress]['ALLSESSIONS']) && array_key_exists($alt_srn, $child_collection[$congress]['ALLSESSIONS'])) ) {
          $this->syslog(__FUNCTION__, __LINE__, "(WARNING) --- --- -- -- - - Omitting url {$url} from tabulation, no legitimate sequence number can be used.");
          continue;
        }
        $srn = $alt_srn;
      }/*}}}*/
      if ( !array_key_exists($congress, $bounds) ) {
        $bounds[$congress] = array(
          'min'   => NULL,
          'max'   => 0,
          'd'     => 0, // Span
          'count' => 0,
        );
      }
      $bounds[$congress]['min'] = min(is_null($bounds[$congress]['min']) ? $srn : $bounds[$congress]['min'], $srn);
      $bounds[$congress]['max'] = max($bounds[$congress]['max'], $srn);
      $bounds[$congress]['d']   = $bounds[$congress]['max'] - $bounds[$congress]['min'];
      $bounds[$congress]['count']++;

      $pagers[$congress] = NULL;

      // Store URL regex patterns for documents within each congress. 
      if ( !array_key_exists($congress, $child_collection) ) $child_collection[$congress] = array();
      if ( !array_key_exists('ALLSESSIONS', $child_collection[$congress]) ) $child_collection[$congress]['ALLSESSIONS'] = array();

      // $include_this = !$can_invalidate || !$documents->get_invalidated();  if ( $include_this ) 
      $invalidated = intval(nonempty_array_element($document,'invalidated',0)) == 1;

      if ( 0 < strlen($url_orig) )
      $child_collection[$congress]['ALLSESSIONS'][$srn] = array(
        'url'    => $url_orig,
        'text'   => $text,
        'cached' => TRUE,
        // This is used by generate_congress_session_column_markup()
        'inv'    => $invalidated ? 1 : 0,
      );
      $total_records++;
    }/*}}}*/

    //////////////////////////////////////////////////////////////////////
    // Insert missing links, which may lead to invalid pages (i.e. unpublished Resolutions)
    $missing_links = 0;
    ksort($bounds, SORT_NUMERIC);

    if ( $debug_method ) $this->recursive_dump(array_keys($bounds),"(marker) Bounds sort keys");

    $next_cycle_lower_bound = NULL;

    foreach ( $bounds as $congress_tag => $limits ) {/*{{{*/

      $extant_components = 0;
      $dumped_sample     = FALSE;

      if ( $sequential_serial_numbers && !is_null($next_cycle_lower_bound) ) {
        $limits['min'] = max($limits['min'],$next_cycle_lower_bound);
      }

      for ( $p = $limits['min'] ; $p <= $limits['max'] ; $p++ ) {/*{{{*/
        if ( !is_array($child_collection[$congress_tag]['ALLSESSIONS']) ) continue;
        if ( array_key_exists($p, $child_collection[$congress_tag]['ALLSESSIONS']) ) {
          $extant_components++;
          if ( $debug_method ) {
            $this->syslog(__FUNCTION__, __LINE__, "(marker) --- -- --- Extant: {$p}.{$congress_tag} {$child_collection[$congress_tag]['ALLSESSIONS'][$p]['url']}");
          }
          continue;
        }
        if ( $debug_method ) $this->syslog(__FUNCTION__, __LINE__, "(marker) --- -- --- Missing: {$p}.{$congress_tag}");
        $text = is_array($url_fetch_regex) ? "{$p}" : preg_replace('@(\(.*\))@i', $p, $url_fetch_regex);
        $url = str_replace(
          array(
            '{congress_tag}',
            '{full_sn}',
          ),
          array(
            $congress_tag,
            $text,
          ),
          $url_template
        );
        if ( 0 < strlen($url) )
        $child_collection[intval($congress_tag)]['ALLSESSIONS'][$p] = array(
          'url'       => $url,
          'text'      => $text,
          'sn_suffix' => $p,
          'cached'    => FALSE,
        );
        if ( $debug_method && ( FALSE == $dumped_sample ) ) {
          $this->syslog(__FUNCTION__, __LINE__, "(marker) --- -- --- Sample entry: {$p}.{$congress_tag}");
          $dumped_sample = array($p => $child_collection[intval($congress_tag)]['ALLSESSIONS'][$p]);
          $this->recursive_dump($dumped_sample,"(marker) - -- - Sample {$p}.{$congress_tag}");
        }
        $missing_links++;
      }/*}}}*/
      if ( $debug_method ) {
        $this->syslog(__FUNCTION__, __LINE__, "(marker) --- -- --- Treated Congress {$congress_tag} n = {$extant_components}, last entry {$p}");
      }
      if ( is_array( $child_collection[$congress_tag]['ALLSESSIONS'] ) )
        krsort($child_collection[$congress_tag]['ALLSESSIONS'], SORT_NUMERIC);
      // Get maximum value of SN suffix in this round, to serve as 
      // greatest lower bound for next iteration.
      if ( $sequential_serial_numbers )
      foreach ( $child_collection[$congress_tag]['ALLSESSIONS'] as $p => $entry ) {
        if ( $debug_method ) {
          $this->syslog(__FUNCTION__, __LINE__, "(marker) --- -- --- Bounding entry: {$p}.{$congress_tag}");
          $dumped_sample = array($p => $entry);
          $this->recursive_dump($dumped_sample,"(marker) - -- - Sample {$p}.{$congress_tag}");
        }
        $next_cycle_lower_bound = intval($p);
        break;
      }
    }/*}}}*/

    //////////////////////////////////////////////////////////////////////
    return array(
      'child_collection' => $child_collection,
      'bounds' => $bounds,
      'pagers' => $pagers,
      'total_records' => $total_records,
      'missing_links' => $missing_links,
    );
  }/*}}}*/

  function generate_congress_session_column_markup(& $q, $query_regex) {/*{{{*/
    $pagecontent = '';
    $nq = 0;
    krsort($q);
    $this->syslog(__FUNCTION__,__LINE__,"(marker) N = " . count($q) );
    foreach ( $q as $child_link ) {/*{{{*/
      if ( $this->column_markup_only_uncached ) if ( array_element($child_link,'cached') == TRUE ) continue; 
      $linktext =  $child_link[empty($child_link['text']) ? "url" : 'text'];
      $child_link['hash'] = UrlModel::get_url_hash($child_link['url']);
      $child_link['url'] = str_replace(' ','%20', $child_link['url']);
      // Typical links will have URL query components; this is assumed when
      // the caller provides a non-null regex in $query_fragment_filter.
      // If that parameter is given as NULL, then it is not possible to extract link text
      // from the URL, and we must then use the plain URL+text pair to construct child links.
      if ( !is_null($query_regex) && ($linktext == $child_link['url']) ) {
        $url_query_components = array();
        $url_query_parts      = UrlModel::parse_url($child_link['url'], PHP_URL_QUERY);
        preg_match_all($query_regex, $url_query_parts, $url_query_components);
        $url_query_parts      = array_combine($url_query_components[1],$url_query_components[2]);
        $linktext = "No. {$url_query_parts['q']}";
      }
			$child_link['text'] = $linktext;
      $link_class   = array('legiscope-remote','suppress-reorder','indent-3','matchable');
      $link_class[] = ( array_element($child_link,'cached') == TRUE ) ? "cached" : "uncached";
      $li_class     = array('no-bullets');
      $invalidated  = 1 == nonempty_array_element($child_link,'inv',0);
      if ( $invalidated ) $li_class[] = 'invalidated';
			$child_link['class'] = $link_class;
      $link_class   = join(' ', $link_class);
      $li_class     = join(' ',$li_class);
			// Permit the link markup to be overridden by any derived ParseUtility
			$link         = method_exists($this,'congress_session_column_link')
				? $this->congress_session_column_link($child_link)
				: <<<EOH
<a class="{$link_class}" id="{$child_link['hash']}" href="{$child_link['url']}">{$child_link['text']}</a>
EOH;
      $pagecontent .= $invalidated
        ? <<<EOH
<li class="{$li_class}">{$child_link['text']}</li>

EOH
        : <<<EOH
<li class="no-bullets" id="line-{$child_link['hash']}">{$link}</li>

EOH;
      if ( !is_null($this->restrict_length) ) if ( $nq++ > $this->restrict_length ) break;
    }/*}}}*/
    return $pagecontent;
  }/*}}}*/

  function construct_congress_change_link($pager_regex_uid) {/*{{{*/
    $congress_change_link = array();
    if ( is_string($pager_regex_uid) ) {
      // Extract Congress selector (15th, 14th, 13th [as of 2013 April 9])
      $extracted_links = array();
      $congress_change_link = $this->extract_pager_links(
        $extracted_links,
        $this->cluster_urldefs, // Structure as below
        $pager_regex_uid 
      );
    } else if ( is_array($pager_regex_uid) ) {
      $congress_change_link = $pager_regex_uid;
    }
    if ( $debug_method ) $this->recursive_dump($congress_change_link,'(marker) Congress Change Link');
    $congress_change_link = join('', $congress_change_link);
    /*{{{*/
    // Pager regex UIDs are taken from the parameterized URLs in $this->cluster_urldefs, as below:
    //  9f35fc4cce1f01b32697e7c34b397a99 =>
    //    query_base_url => http://www.senate.gov.ph/lis/leg_sys.aspx
    //    query_template => type=journal&congress=({PARAMS})
    //    query_components =>
    //       361d558f79a9d15a277468b313f49528 => 15|14|13
    //    whole_url => http://www.senate.gov.ph/lis/leg_sys.aspx?type=journal&congress=({PARAMS})
    //  1cb903bd644be9596931e7c368676982 =>
    //    query_base_url => http://www.senate.gov.ph/lis/leg_sys.aspx
    //    query_template => congress=15&type=journal&p=({PARAMS})
    //    query_components =>
    //       0cd3e2b45d2ebc55ca85d461abb6880c => 2|3|4|5
    //    whole_url => http://www.senate.gov.ph/lis/leg_sys.aspx?congress=15&type=journal&p=({PARAMS})
    //  acafee00836e5fbce173f8f4b22d13e1 =>
    //    query_base_url => http://www.senate.gov.ph/lis/journal.aspx
    //    query_template => congress=15&session=1R&q=({PARAMS})
    //    query_components =>
    //       40b2fa87aab9d7dfd8be00197298b532 => 94|93|92|91|90|89|88|87|86|85|84
    //       c64b23d94c86d9370fb246769579776d => 83|82|81|80|79|78|77|76|75|74|73
    //    whole_url => http://www.senate.gov.ph/lis/journal.aspx?congress=15&session=1R&q=({PARAMS})
    /*}}}*/
    return $congress_change_link;
  }/*}}}*/

  function generate_congress_session_item_markup(UrlModel & $urlmodel, array & $child_collection, $session_select, $query_fragment_filter = '\&q=([0-9]*)', $pager_regex_uid = '9f35fc4cce1f01b32697e7c34b397a99' ) {/*{{{*/

    /**
     *  There's a special complication introduced by HTTP statefulness,
     *  in that the meaning of certain Senate page URLs depends on session state
     *  that we cannot replicate in the spider application.  Senate Bills and Senate
     *  House Bills, for example, are reachable via pager URLs which meaning depends on
     *  Senate server-side session flags.
     *
     *  We can deal with this by decorating pager URLs with a locally-defined state value.
     *  This state value will be appended to the query part of all pager URLs we store locally.
     *  Because the mapping between local state and subject (i.e. Senate) state can differ
     *  from page to page, we will need to relegate processing of the state to parser callbacks rather than
     *  perform the mapping in the general-purpose markup generator.
     *
     *  This is implemented by use of 'metalinks', a mechanism to insert additional 
     *  items into client-side POST requests.
     *
     */
    $debug_method = FALSE;

    if ( !isset($this->cluster_urldefs) ) throw new Exception("Missing cluster_urldefs");

    $target_congress = $urlmodel->get_query_element('congress');

    $this->syslog( __FUNCTION__, __LINE__, "(marker) Target Congress '{$target_congress}'");

    if ( $debug_method ) $this->recursive_dump($session_select,'(marker) parameter session_select - -- -');

    $congress_change_link = $this->construct_congress_change_link($pager_regex_uid);

    // NOTE: initialize_filter is defined in spider.js
    $pagecontent = <<<EOH

<div class="senate-journal">
<h2><input type="text" class="full-width" id="listing-filter-string" /></h2>

<script type="text/javascript">
jQuery(document).ready(function(){
  initialize_filter("#listing-filter-string","span[class*=indent-1]","test");
});
</script>


EOH;

    // For priming an empty database:  Fetch child page links, use session_select metalink data

    if ( $debug_method )
      $this->recursive_dump($child_collection,'(marker) Session and item src');

    $dump_all_congress_entries = FALSE;
    $level_0_rendered = FALSE;
    $depth_2_label = 'Session';

    $active = $session_select;
    $this->filter_nested_array($active,"optval[active=1]",0);
    $active = nonempty_array_element($active,0);

    if ( !is_null($active) ) $this->syslog(__FUNCTION__,__LINE__,"(marker)  Active session: {$active}");

    foreach ( $child_collection as $congress => $session_q ) {/*{{{*/

      // Detect Senate Resolutions. 
      if ( !$dump_all_congress_entries && is_array($session_q) && 1 == count($session_q) ) {/*{{{*/
        // Test whether the document entries have a Regular/Special session partition.
        // Some documents (resolutions) are not clustered by session, and 
        // instead contain the single key 'ALLSESSIONS'.
        if ( array_element(array_keys($session_q),0) == 'ALLSESSIONS' ) {
          $this->syslog(__FUNCTION__, __LINE__, "(marker) Two-level array");
          $dump_all_congress_entries = TRUE;
        }
      }/*}}}*/

      if ( $dump_all_congress_entries ) {/*{{{*/
        if ( !$level_0_rendered ) { 
          $pagecontent .= <<<EOH

[SWITCHER]
<span class="indent-1">Congress: {$congress_change_link} <input type="button" value="Reset" id="reset-cached-links" /><br/>

EOH;
          $level_0_rendered = TRUE;
        }
        $depth_2_label = 'Congress';
        // Restructure the input child collection
        // [Congress] => 'ALLSESSIONS' => { Resolutions list }
        // [ DUMMY ] => [Congress] => 'ALL' => { Resolutions list }
        reset($child_collection);
        $session_q = array();
        foreach ( $child_collection as $congress => $resolutionset ) {
          foreach ( $resolutionset as $resolutions )
            $session_q[$congress] = $resolutions;
        }
        $child_collection = array();
      }/*}}}*/
      else {/*{{{*/
        if ( !$level_0_rendered ) { 
          $pagecontent .= <<<EOH

<span class="indent-1">Congress {$congress} {$congress_change_link} <input type="button" value="Reset" id="reset-cached-links" /><br/>

EOH;
          $level_0_rendered = TRUE;
        }

        if ( !($target_congress == $congress) ) {
          continue;
        }

        // Insert missing regular session links
        if ( 0 < count($session_select) ) {
          $sessions_extracted = $session_select; 
          $sessions_extracted = $this->filter_nested_array($sessions_extracted,'optval[linktext*=Regular Session|i]');
          if ( $debug_method ) $this->recursive_dump($sessions_extracted,"(marker) Regular Session options");
          foreach ( $sessions_extracted as $session ) {
            if ( !array_key_exists($session, $session_q) ) $session_q[$session] = array();
          }
        }
      }/*}}}*/

      krsort($session_q);

      if ($debug_method) $this->recursive_dump(array_keys($session_q),"(marker) - - - - - Session keys: ");

      foreach ( $session_q as $session => $q ) {/*{{{*/

        krsort($q);

        // $this->recursive_dump($q,"(marker) {$session} --- ");

        if ( empty($session) ) continue;

        $session_fragment = empty($session)
          ? NULL
          : "{$depth_2_label} {$session}";

        $per_congress_pager = ( $dump_all_congress_entries )
          ? "[PAGER{$session}]"
          : $this->get_per_congress_pager($urlmodel, $session, $q, $session_select);

        $per_congress_pager = empty($per_congress_pager)
          ? NULL
          : <<<EOH
<span class="link-faux-menuitem">{$per_congress_pager}</span><br/>
EOH;

        // Extract sequence position from query component in $query_fragment_filter 

        if (1) if ( !is_null($query_fragment_filter) ) {
          $this->reorder_url_array_by_queryfragment($q, $query_fragment_filter);
        }

        $query_regex = '@([^&=]*)=([^&]*)@';
        $column_entries = $this->generate_congress_session_column_markup($q, is_null($query_fragment_filter) ? NULL : $query_regex);

        $pagecontent .= <<<EOH

<div class="indent-2"><span class="link-cluster-column-heading">{$session_fragment}</span><br/>
{$per_congress_pager}
<ul class="link-cluster no-bullets">
{$column_entries}
</ul>
</div>

EOH;
      }/*}}}*/

      $pagecontent .= <<<EOH
</span>
EOH;
    }/*}}}*/

    $pagecontent .= <<<EOH
</div>

<script type="text/javascript">
jQuery(document).ready(function(){
  jQuery('input[id=reset-cached-links]').click(function(e) {
    jQuery('div[class*=indent-2]').find('a').each(function(){
      jQuery(this).removeClass('cached').addClass('uncached');
    });
  });
  initialize_linkset_clickevents(jQuery('ul[class*=link-cluster]'),'li');
  initialize_remote_links();
});
</script>

EOH;
    return $pagecontent;
  }/*}}}*/

  function extract_house_session_select(UrlModel & $urlmodel, $return_a_tags = TRUE, $form_selector = '[class*=lis_div]') {/*{{{*/

    // Extract the House Session selector
    // Each record encodes HTTP session state for a cluster of Senate 
    // journals belonging to a House session.  Child page content is
    // bound implicitly to the same HTTP session state (during live network
    // fetch), and explicitly in  

    $debug_method = FALSE;

    $paginator_form = array_values($this->get_containers(
      "children[tagname=form]{$form_selector}"
    ));

    // $this->recursive_dump(($this->get_containers()),'(marker) StructureParser');

    $paginator_form = $paginator_form[0];
    $control_set    = $this->extract_form_controls($paginator_form);
    $test_url       = new UrlModel($urlmodel->get_url(),TRUE);

    if ( $debug_method ) {
      $this->syslog(__FUNCTION__,__LINE__,"(marker) - - - - - - - - - - - - - - - - - -");
      $this->syslog(__FUNCTION__,__LINE__,"(marker) - - Extract form controls matching {$form_selector} " . $urlmodel->get_url());
      $this->recursive_dump($control_set,"(marker) - -");
    }
    extract($control_set); // form_controls, select_name, select_options, userset, selected (userset[selected] == currently active OPTION element)

    $select_options = array_filter(array_map(create_function(
      '$a', 'return empty($a["value"]) ? NULL : $a;'
    ),is_null($select_options) ? array() : $select_options));

    // Extract the Session selector (First Regular Session, etc.)
    $select_elements = array();
    if ( is_array($select_options) ) foreach ( $select_options as $select_option ) {/*{{{*/
      // Take a copy of the rest of the form controls
      $control_set               = $form_controls; // Hidden input controls
      $control_set[$select_name] = $select_option['value']; // Assign ONE select value
      if ( method_exists($this, 'session_select_option_assignments') ) {
        $this->session_select_option_assignments($control_set, $select_option, $select_name);
      }
      $generated_link = UrlModel::create_metalink(
        $select_option['text'],
        $urlmodel->get_url(),
        $control_set,
        'fauxpost'
      );

      if ( $return_a_tags ) {
        $select_elements[] = $generated_link;
      } else {
        $controlset_json_base64 = base64_encode(json_encode($control_set));
        $controlset_hash        = md5($controlset_json_base64);
        $select_elements[$controlset_hash] = array(
          'metalink'        => $controlset_json_base64,
          'metalink_source' => $control_set,
          'faux_url'        => $urlmodel->get_url(),
          'linktext'        => $select_option['text'],
          'optval'          => $select_option['value'],
          'markup'          => $generated_link,
          'url_model'       => $this->get_faux_url($test_url, $controlset_json_base64),
        );
        // The default selected option determines whether we need to execute another POST request,
        // when traversing stateful links.
        if ( $select_option['value'] == $select_active ) $select_elements[$controlset_hash]['active'] = 1;
      }
    }/*}}}*/

    if ( FALSE && $debug_method )
    foreach ( $select_elements as $metalink_hash => $s ) {
      $test_url->fetch($s['url_model'], 'url');
      $in_db = $test_url->in_database() ? 'Cached' : 'Missing';
      $this->syslog(__FUNCTION__,__LINE__,"(marker) {$in_db} URL {$s['url_model']}");
    }

    if ( $debug_method ) $this->recursive_dump($select_elements,'(marker) House Session SELECT');

    return $select_elements;
  }/*}}}*/

  static function committee_name_regex($committee_name) {/*{{{*/
    $search_name = array_filter(explode(' ',preg_replace(
      array(
        "@[^&;'A-Z0-9-ñ ]@i",
        "@[']@i",
        "@[“”]@",
        '@\&([a-z]*);@i',
        '@&@i',
      ),
      array(
        '',
        "\'",
        '"',
        ' ',
        ' ',
      ),
      $committee_name)
    ));
    array_walk($search_name,
      create_function(
        '&$a, $k, $s',
        '$a = strlen($a) > 3 ? "(" . trim($a) . ")" : NULL;'),
      $search_name
    );
    $search_name = join('(.*)',array_filter($search_name));
    if (!(0 < strlen($search_name) ) ) return FALSE;
    return $search_name;
  }/*}}}*/

  static function legislator_name_regex($human_name) {/*{{{*/
    $search_name = array_values(array_filter(explode(' ',preg_replace(
      array(
        '@([ \t\n]+)@i',
        "@[“”]@i",
        '@["][^"]*["]@i',
        "@[^&;'A-Zñ\" ]@i",

        "@[']@i",
        '@\&([a-z]*);@i',
        '@&@i',
        '@[[:space:]](IV|III|II|VII|VI|V|Jr\.|Sr\.)$@',
      ),
      array(
        ' ',
        '"',
        '',
        '',

        "\'",
        ' ',
        ' ',
        '',
      ),
      $human_name)
    )));
    array_walk(
      $search_name,
      create_function(
        '& $a, $k, $s',
        '$m = (($k == 0) || ($k + 1 == (count($s)))) ? "" : "?"; $a = (mb_strlen(preg_replace("@[^A-Zñ ]@i","",$a)) >= 3) ? "(" . trim($a) . "){$m}" : NULL;'
      ),
      $search_name
    );

    $search_name = join('(.*)',array_filter($search_name));
    if (!(0 < strlen($search_name) ) ) return FALSE;
    return $search_name;
  }/*}}}*/

  static function permalinkify_name($name) {/*{{{*/
    // Manipulate name to allow it's use as a URL component
    $name = preg_replace(array('@[^a-zñ ]@i','@ñ@i'), array('','n'),strtolower($name));
    $name = explode(' ', $name);
    array_walk($name,create_function(
      '& $a, $k', '$a = 3 < strlen($a) ? strtolower($a) : NULL;'
    ));
    $name = array_filter($name);
    return join('-', $name); 
  }/*}}}*/

	// Standard templates overridden by document parsers.
	// These next two generate markup for a single document.

  function legislative_doc_user_template() {/*{{{*/
    return <<<EOH
<h1>{sn}</h1>
<h2>{title}</h2>
<span><b>Source: </b>{url}</span>
<hr/>
<h2>{description}</h2>
<hr/>
<div>{content}</div>
<div>
<h2>OCR Content</h2>
{ocrcontent.data}
</div>
EOH;
  }/*}}}*/

  function legislative_doc_user_template_ocrcap() {/*{{{*/
    return <<<EOH
<h1>{sn}</h1>
<h2>{title}</h2>
<span><b>Source: </b>{url}</span>
<h2>{description}</h2>
<hr/>
<h2>OCR Content</h2>
<div>{ocrcontent.data}</div>
EOH;
  }/*}}}*/


  function generate_admin_content(LegislativeCommonDocumentModel & $legislative_document) {/*{{{*/

    // Convert [content] or [ocrcontent.data] field to paragraphs
		$ocrcontent = nonempty_array_element($legislative_document->get_ocrcontent(),'data',array());
		$ocrcontent = nonempty_array_element($ocrcontent,'data');
		$no_local_content = json_encode(array(
			0 => array('text' => 'No Data for ' . $legislative_document->get_sn()),
			1 => array('text' => 'Only the original document is available at {{2}}'),
			2 => array(
				'url' => $legislative_document->get_url(),
				'text' => $legislative_document->get_url(),
			),
		));	

		if ( !is_null($legislative_document->get_content()) ) {
			$this->syslog(__FUNCTION__,__LINE__,"(marker) Content found, length " . mb_strlen($legislative_document->get_content()));
			$content = @json_decode($legislative_document->get_content(),TRUE);
      if ( FALSE == $content ) {
        $content = "No parsed content.";
      }
    }
    else if ( !is_null($ocrcontent) ) {
			$this->syslog(__FUNCTION__,__LINE__,"(marker) Using OCR version");
			$content = explode("\n", str_replace(array("\r\n"),array("\n"),$ocrcontent));
    } 
    else {
			$this->syslog(__FUNCTION__,__LINE__,"(marker) NO CONTENT. Using placeholder.");
			$content = $no_local_content;
		}

    if (is_array($content) && (0 < count($content))) {
      $content = $legislative_document->format_document($content);
      $legislative_document->set_content($content);
      $content = NULL;
      unset($content);
    }  
    else {
      $legislative_document->set_content('No parsed content.');
    }

    if ( method_exists($legislative_document,'prepare_ocrcontent') ) {
      $legislative_document->prepare_ocrcontent();
      if (0) {
        $ocrcontent = $legislative_document->get_ocrcontent();
        $content = nonempty_array_element($ocrcontent,'data');
        $content = nonempty_array_element($content,'data');
        $content = $legislative_document->format_document($content);
        if ( !is_null($content) ) {
          $ocrcontent['data']['data'] = $content;
          $legislative_document->set_ocrcontent($ocrcontent);
        }
        $content = NULL;
        $ocrcontent = NULL;
      }
    }
    else {/*{{{*/
      if (!is_null($legislative_document->get_ocrcontent())) {
        $ocrcontent = $legislative_document->get_ocrcontent();
        $ocrcontent = nonempty_array_element($ocrcontent,'data');
        $ocr_record_id = nonempty_array_element($ocrcontent,'id');
        if ( is_null($ocr_record_id) ) {
          $this->syslog(__FUNCTION__,__LINE__,"(marker) No OCR result available.");
          $legislative_document->set_ocrcontent('No OCR available');
        }
        else {
          $this->syslog(__FUNCTION__,__LINE__,"(marker) OCR content record #{$ocr_record_id} available.");
        }
      }
      else {
        $legislative_document->set_ocrcontent('No OCR conversion available.');
      }
    }/*}}}*/

    $legislative_document->permit_html_tag_delimiters = TRUE;

		return $legislative_document->substitute($this->legislative_doc_user_template());
  }/*}}}*/

  function fetch_document_sn_congress_session_conds($sn,$congress_tag,$session_tag) {/*{{{*/
    $match = array();
      
    if ( 1 == preg_match('@([A-Z]{1,})[-0]*([0-9]{1,})@i',$sn,$match) ) {

      array_shift($match);
      $match    = array_values($match);
      $match[1] = ltrim($match[1],'0');
      $ra_regex = join('([-0]*)',$match);
      $this->syslog( __FUNCTION__, __LINE__, "(marker) Seek document {$sn} {$ra_regex}" );
      $conditions = array_filter(array(
        '`a`.`sn`' => "REGEXP '^({$ra_regex})$'",
        '`a`.`congress_tag`' => $congress_tag,
        '`a`.`session_tag`' => $session_tag,
      ));
      $conditions = array('AND' => $conditions);

			return $conditions;

    }
		return FALSE;
  }/*}}}*/

	function get_document_sn_match_from_urlpath(UrlModel & $urlmodel, $congress_tag = NULL, $session_tag = NULL) {/*{{{*/
		// Obtain SN filter condition from the last component of a [PDF] URL.
    $urlpath = urldecode($urlmodel->get_url());
    $urlpath = explode('/',UrlModel::parse_url($urlpath,PHP_URL_PATH));
		$sn = method_exists($this,"get_urlpath_tail_sn_filter")
			? $this->get_urlpath_tail_sn_filter($urlpath)
			: strtoupper(preg_replace('@[^RA0-9]@i','',array_pop($urlpath)))
			; 

    $this->syslog(__FUNCTION__,__LINE__,"(critical) Testing SN '{$sn}' <- " . join('/',$urlpath));

    return $this->fetch_document_sn_congress_session_conds($sn,$congress_tag,$session_tag);

	}/*}}}*/


	function generate_pagecontent_using_ocr(& $pagecontent, $record_filter_conds, $legislative_document_type = NULL) {/*{{{*/
		// Expect to retrieve exactly ONE document record (as the [sn] attribute should be unique)
		// with possibly zero or several joined records depending on the type
		// of document. 
		$debug_method = TRUE;
		$legislative_document = new $legislative_document_type();
		$legislative_document->
			join_all()->
			where($record_filter_conds)->
			recordfetch_setup();

		$record = array();
		$ocr_queue_list = array();
    $document_id = NULL;
		while ( $legislative_document->recordfetch($record,TRUE) ) {/*{{{*/
			$ra = $record['sn'];
      $congress_tag = nonempty_array_element($record,'congress_tag');
      // Hack to strip trailing slashes (REMOVE THIS; DEBUG FRAGMENT ONLY)
      $record['url'] = rtrim($record['url'],'/');
      $urlhash = UrlModel::get_url_hash($record['url']);
      if ( is_null($document_id) ) $document_id = $record['id'];
      if ( is_null($pagecontent) ) {
				$this->syslog( __FUNCTION__, __LINE__, "(marker) {$legislative_document_type} {$ra} #{$record['id']}.{$congress_tag}" );
				if ( $debug_method ) $this->recursive_dump($record,"(marker)");
        $legislative_document->set_url($record['url']);
				$pagecontent = $this->generate_admin_content($legislative_document);
			}
			if ( is_array($record['content']) ) {
				$this->syslog( __FUNCTION__, __LINE__, "(marker) Document already stored.");
				if ( $debug_method ) $this->recursive_dump($record['content'],"(marker)");
			} 
			else if ( FALSE == @json_decode(nonempty_array_element($record,'content'),TRUE) ) {
				$this->syslog( __FUNCTION__, __LINE__, "(marker) Enqueue.");
				$ocr_queue_list[UrlModel::get_url_hash($record['url'])] = $record['id'];
			}
		}/*}}}*/

		$ocr_dequeue_method = 'test_document_ocr_result';
		if ( method_exists($legislative_document,$ocr_dequeue_method) ) {/*{{{*/
      // Unspool queue, and pass each of the elements
      if ( !is_null($document_id) && is_null($legislative_document->get_id())) $legislative_document->set_id($document_id);
			if ( $debug_method ) {
				$this->syslog( __FUNCTION__, __LINE__, "(marker) OCR queue check. Elements: " . count($ocr_queue_list));
				$this->recursive_dump($ocr_queue_list,"(marker)");
			}
			while ( 0 < count($ocr_queue_list) ) {
				$ocr_item = array_shift($ocr_queue_list);
				if ( $legislative_document->set_id(NULL)->retrieve($ocr_item,'id')->in_database() ) {
					$legislative_document->$ocr_dequeue_method('get_url');
				}
			}
		}/*}}}*/
		else {
			$this->syslog( __FUNCTION__, __LINE__, "(critical) No method {$ocr_dequeue_method}.");
		}

	}/*}}}*/

	// POST wall traversal (converting POST actions to proxied GET)
	function overridden_test_form_traversal_network_fetch(UrlModel & $form_action) {/*{{{*/
		// Method invoked from execute_document_form_traversal()
		// to cause a POST action to be executed, to the given FORM action,
		// depending only upon the contents of the UrlModel container or
		// the ContentDocumentModel which stores the content of a server response. 
		$this->syslog(__FUNCTION__, __LINE__, "(critical) Unimplemented method called while processing " . $form_action->get_url() );
		return FALSE;
	}/*}}}*/

	function test_form_traversal_network_fetch(UrlModel & $form_action) {/*{{{*/

		$debug_method = $this->debug_method;
		// Determine whether or not to force a POST action to retrieve form data. 
		// Modify the UrlModel $form_action so that an existing POST response
		// is loaded into urlcontent; otherwise, return TRUE to cause
		// a POST action to be executed. 
    if ( !$form_action->in_database() ) return TRUE; 
		$url_id = $form_action->get_id();
		$form_action->
			join(array('urlcontent'))->
			where(array('AND' => array(
				'`a`.`id`' => $url_id,
				'{urlcontent}.`content_type`' => CONTENT_TYPE_RESPONSE_CONTENT,
			)))->	
			recordfetch_setup();

		$url = array('urlcontent' => NULL);

		if ( $debug_method ) $this->syslog(__FUNCTION__,__LINE__,"(critical) Seeking records.");

		$urlcontent = NULL;

		while ( $form_action->recordfetch($url,TRUE) ) {
			if ( $debug_method ) {
				$this->syslog(__FUNCTION__,__LINE__,"(critical) -- Got UrlModel #{$url['id']} {$url['url']}");
				$this->recursive_dump($url,"(critical) --");
			}
			$urlcontent = $form_action->get_urlcontent(); 
		}

		$form_action->
			join(array('urlcontent'))->
			retrieve($url_id,'`a`.`id`')->
			in_database();

		if (!is_null($urlcontent)) {
			$form_action->set_urlcontent(NULL);
			$form_action->set_pagecontent(nonempty_array_element($urlcontent['data'],'data'));
			// $this->syslog(__FUNCTION__,__LINE__,"(critical) -- Content " . nonempty_array_element($urlcontent['data'],'data') );
			// $this->syslog(__FUNCTION__,__LINE__,"(critical) -- Content " . $form_action->get_pagecontent() );
			$form_action->set_urlcontent($urlcontent);
			$form_action->content_overridden = TRUE;
			if ( $debug_method ) $this->syslog(__FUNCTION__,__LINE__,"(critical) -- Asserting document content from ContentDocument {$urlcontent['join']['id']}.{$urlcontent['data']['id']}");
			return FALSE;
		}	
		$this->syslog(__FUNCTION__,__LINE__,"(critical) -- WARNING: Returning TRUE to caller, a network fetch will be performed.");
    return TRUE;
	}/*}}}*/


	function site_form_traversal_controls(UrlModel & $action_url, $form_controls ) {
		$this->syslog(__FUNCTION__, __LINE__, "(critical) Unimplemented method called while processing " . $form_action->get_url() );
		return array();
	}


}
