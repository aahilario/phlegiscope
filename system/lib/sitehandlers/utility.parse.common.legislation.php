<?php

/*
 * Class LegislationCommonParseUtility
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class LegislationCommonParseUtility extends GenericParseUtility {
  
  function __construct() {
    parent::__construct();
  }

  function get_per_congress_pager(UrlModel & $urlmodel, & $session, & $q, & $session_select, $pager_uuid = '1cb903bd644be9596931e7c368676982') {/*{{{*/

    $debug_method = FALSE;

    if ( $debug_method ) {
      $this->syslog(__FUNCTION__,__LINE__,"(marker) - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -");
      $this->syslog(__FUNCTION__,__LINE__,"(marker) - - - - - Input: Session {$session}");
      $this->recursive_dump( $session_select, "(marker) - - - - -" );
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

    if ( $debug_method ) {
      $this->syslog(__FUNCTION__,__LINE__,"(marker) - - - + + Session data for {$session}");
      $this->recursive_dump( $session_data, "(marker) - - - + +" );
      $this->syslog(__FUNCTION__,__LINE__,"(marker) - - - * * Link text for {$session}");
      $this->recursive_dump( $linktext, "(marker) - - - * *" );
    }
    
    if ( is_array($session_data) ) {/*{{{*/
      // Extract the pager for the current Congress and Session series 
      $extracted_links = array();
      $session_data    = array_values($session_data);
      // Get zeroth element
      $session_data    = array_element($session_data,0,$session_data);
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
          $per_congress_pager = $this->extract_pager_links(
            $extracted_links, $cluster_urls,
            $pager_uuid,
            $session_data,
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
      else  if ( !is_null($pager_uuid) && is_array($this->cluster_urldefs) && array_key_exists($pager_uuid,$this->cluster_urldefs) ) {/*{{{*/
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

  function generate_congress_session_item_markup(UrlModel & $urlmodel, array & $child_collection, $session_select, $query_fragment_filter = '\&q=([0-9]*)', $pager_regex_uid = '9f35fc4cce1f01b32697e7c34b397a99' ) {/*{{{*/

		$debug_method = FALSE;

    if ( !isset($this->cluster_urldefs) ) throw new Exception("Missing cluster_urldefs");

    $target_congress = $urlmodel->get_query_element('congress');

    $this->syslog( __FUNCTION__, __LINE__, "(marker) Target Congress '{$target_congress}'");

    if ( $debug_method ) $this->recursive_dump($session_select,'(marker) parameter session_select - -- -');

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
    $pagecontent = <<<EOH
<div class="senate-journal">
EOH;

    // For priming an empty database:  Fetch child page links, use session_select metalink data

    if ( $debug_method ) $this->recursive_dump($child_collection,'(marker) Session and item src');

		$dump_all_congress_entries = FALSE;
		$level_0_rendered = FALSE;
		$depth_2_label = 'Session';

    foreach ( $child_collection as $congress => $session_q ) {/*{{{*/

			// Detect Senate Resolutions. 
			if ( !$dump_all_congress_entries && is_array($session_q) && 1 == count($session_q) ) {/*{{{*/
				// Test whether the document entries have a Regular/Special session partition.
				// Some documents (resolutions) are not clustered by session, and 
				// instead contain the single key 'ALLSESSIONS'.
				list( $session, $q ) = each($session_q);
				reset($session_q);
				if ( $session == 'ALLSESSIONS' ) {
					$this->syslog(__FUNCTION__, __LINE__, "(marker) Two-level array");
					$dump_all_congress_entries = TRUE;
				}
			}/*}}}*/

			if ( $dump_all_congress_entries ) {/*{{{*/
        if ( !$level_0_rendered ) { 
          $pagecontent .= <<<EOH
<span class="indent-1">Last 3 Congress Conventions {$congress_change_link}<input type="button" value="Reset" id="reset-cached-links" /><br/>

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

      $this->recursive_dump(array_keys($session_q),"(marker) - - - - - Session keys: ");

      foreach ( $session_q as $session => $q ) {/*{{{*/

				$per_congress_pager = ( $dump_all_congress_entries )
					? "[PAGER{$session}]"
					: $this->get_per_congress_pager($urlmodel, $session, $q, $session_select);

				$session_fragment = empty($session)
					? NULL
					: <<<EOH
<div class="indent-2">{$depth_2_label} {$session}<br/>
EOH;
				$per_congress_pager = empty($per_congress_pager)
					? NULL
					: <<<EOH
<span class="link-faux-menuitem">{$per_congress_pager}</span><br/>
EOH;
        $pagecontent .= <<<EOH
{$session_fragment}
{$per_congress_pager}
<ul class="link-cluster no-bullets">
EOH;
        // Extract sequence position from query component in $query_fragment_filter 
        if ( !is_null($query_fragment_filter) ) {
          $this->reorder_url_array_by_queryfragment($q, $query_fragment_filter);
        }

        $query_regex = '@([^&=]*)=([^&]*)@';

        $previous_url = NULL;
        foreach ( $q as $child_link ) {/*{{{*/
          $urlparts = UrlModel::parse_url($child_link['url'],PHP_URL_QUERY);
          $linktext =  $child_link[empty($child_link['text']) ? "url" : 'text'];
          $child_link['hash'] = UrlModel::get_url_hash($child_link['url']);
          $child_link['url'] = str_replace(' ','%20', $child_link['url']);
          // Typical links will have URL query components; this is assumed when
          // the caller provides a non-null regex in $query_fragment_filter.
          // If that parameter is given as NULL, then it is not possible to extract link text
          // from the URL, and we must then use the plain URL+text pair to construct child links.
          if ( !is_null($query_fragment_filter) && ($linktext == $child_link['url']) ) {
            $url_query_components = array();
            $url_query_parts      = UrlModel::parse_url($child_link['url'], PHP_URL_QUERY);
            preg_match_all($query_regex, $url_query_parts, $url_query_components);
            $url_query_parts      = array_combine($url_query_components[1],$url_query_components[2]);
            $linktext = "No. {$url_query_parts['q']}";
          }
          $cached = array_element($child_link,'cached') == TRUE ? "cached" : "";
          $pagecontent .= <<<EOH
<li class="no-bullets"><a class="legiscope-remote {$cached} indent-3" id="{$child_link['hash']}" href="{$child_link['url']}">{$linktext}</a></li>

EOH;
        }/*}}}*/
        $q = NULL;
        $pagecontent .= <<<EOH
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
      jQuery(this).removeClass('cached').removeClass('uncached');
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

    $this->recursive_dump(($paginator_form = array_values($this->get_containers(
      "children[tagname=form]{$form_selector}"
    ))),'(------) StructureParser');

    $paginator_form = $paginator_form[0];
    $control_set    = $this->extract_form_controls($paginator_form);
    $test_url       = new UrlModel($urlmodel->get_url(),TRUE);

    if ( $debug_method ) {
      $this->syslog(__FUNCTION__,__LINE__,"(marker) - - - - - - - - - Extract form controls matching {$form_selector} " . $urlmodel->get_url());
      $this->recursive_dump($control_set,"(marker) - - - -");
    }
    extract($control_set); // form_controls, select_name, select_options, userset

    $select_options = array_filter(array_map(create_function(
      '$a', 'return empty($a["value"]) ? NULL : $a;'
    ),$select_options));

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
    $search_name = explode(' ',preg_replace(
      array(
        "@[^&;'A-Z0-9ñ ]@i",
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
    );
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

}

