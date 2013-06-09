<?php

/*
 * Class CongressCommitteeListParseUtility
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class CongressCommitteeListParseUtility extends CongressCommonParseUtility {
  
  function __construct() {
    parent::__construct();
  }

	function congress_committee_listing(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
		// http://www.congress.gov.ph/committees/search.php?congress=15&id=A505
    $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked for " . $urlmodel->get_url() );
    $m    = new RepresentativeDossierModel();
    $comm = new CongressionalCommitteeDocumentModel();
    $link = new CongressionalCommitteeRepresentativeDossierJoin();

    $this->
      set_parent_url($urlmodel->get_url())->
      parse_html(
        $pagecontent,
        $urlmodel->get_response_header()
      );

    $this->debug_operators = FALSE;

    $this->recursive_dump($containers = $this->get_containers(
      'children[tagname=div][id=main-ol]'
    ),"(----) ++ All containers");

    $target_congress = NULL;
    // We are able to extract names of committees and current chairperson.
    if ( 0 < count($containers) ) {/*{{{*/
      $committees  = array();
      $pagecontent = '';
      $containers  = array_values($containers);
      krsort($containers,SORT_NUMERIC);
      $containers  = array_values($containers);
      while ( 0 < count( $containers ) ) {/*{{{*/
        $pagecontent .= "<div>";
        $container = array_values(array_pop($containers));
        krsort($container);
        $container = array_values($container);
        while ( 0 < count( $container ) ) {/*{{{*/
          $tag = array_pop($container);
          if ( array_key_exists('url', $tag) ) {
            $hash = UrlModel::get_url_hash($tag['url']);
            $congress_tag = 
            $pagecontent .= <<<EOH
<a href="{$tag['url']}" class="legiscope-remote listing-committee-name">{$tag['text']}</a>
EOH;
            $committees[$hash] = array(
              'url'            => $tag['url'],
              'committee_name' => $tag['text'],
              'congress_tag'   => UrlModel::query_element('congress', $tag['url']), 
              'chairperson'    => NULL,
            );
            continue;
          }
          // Replace $name with legislator dossier record
          $name = $tag['text'];
          $tag['text'] = $m->replace_legislator_names_hotlinks($name);
          $committees[$hash]['chairperson'] = $name;
          if (is_null(array_element($name,'fullname'))) {
            $parsed_name = $m->parse_name( $name['original'] );
            $committees[$hash]['original'] = $parsed_name;
            $m->fetch($name['id'],'id');
            $m->
              set_fullname($name['original'])-> 
              set_firstname(utf8_decode($parsed_name['given']))->
              set_mi($parsed_name['mi'])->
              set_surname(utf8_decode($parsed_name['surname']))->
              set_namesuffix(utf8_decode($parsed_name['suffix']))->
              stow();
          }
          $pagecontent .= <<<EOH
<span class="representative-name listing-committee-representative">{$tag['text']}</span><br/>
EOH;
        }/*}}}*/
        $pagecontent .= "</div>";
      }/*}}}*/
      // At this point, the $containers stack has been depleted of entries,
      // basically being transformed into the $committees stack
      // $this->recursive_dump($committees,"(marker) -- -- --");
    }/*}}}*/
    else {/*{{{*/
      $pagecontent = join('', $this->get_filtered_doc());
    }/*}}}*/

    $committee = array();
    $updated = 0;
    $committees_found = count($committees);
    while ( 0 < count($committees) ) {/*{{{*/
      $committee = array();
      $this->pop_stack_entries($committee, $committees, 10);
      // Use 'url' and 'committee_name' keys; store missing CongressionalCommitteeDocumentModel entries. 
      $comm->mark_committee_ids($committee);
      // Extract all records marked 'UNMAPPED'
      $this->filter_nested_array($committee, '#[id*=UNMAPPED]');
      $this->recursive_dump($committee, "(marker) - -- - STOWABLE");
      foreach ( $committee as $entry ) {
        $updated++;
        $comm->fetch($entry['committee_name'],'committee_name');
        $entry = array(
          'committee_name' => array_element($entry,'committee_name'),
          'congress_tag'   => array_element($entry,'congress_tag'),
          'create_time'    => array_element($entry,'create_time'),
          'last_fetch'     => array_element($entry,'last_fetch'),
          'url'            => array_element($entry,'url'),
        );
        $id = $comm->set_contents_from_array($entry)->stow();
        $this->syslog( __FUNCTION__, __LINE__, "(marker) - - - Stowed {$id} {$entry['committee_name']}");
      }
    }/*}}}*/
    if ( $updated == 0 ) $this->syslog(__FUNCTION__, __LINE__, "(marker) - - - All {$committees_found} committee names stowed");

    $pagecontent = utf8_encode( <<<EOH
<div class="congresista-dossier-list" id="committee-listing">{$pagecontent}</div>
<div id="committee-details-container" class="alternate-original half-container"></div>
EOH
		);

    // $parser->json_reply = array('retainoriginal' => TRUE);

	}/*}}}*/

}

