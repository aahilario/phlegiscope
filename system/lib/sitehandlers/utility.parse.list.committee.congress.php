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

    $containers = $this->get_containers('children[tagname=div][id=main-ol]');

    $target_congress = NULL;
    // We are able to extract names of committees and current chairperson.
		$committee_leader_boxes = array();
    if ( 0 < count($containers) ) {/*{{{*/
      $committees  = array();
      $pagecontent = '';
      $containers  = array_values($containers);
      krsort($containers,SORT_NUMERIC);
      $containers = array_values($containers);
			$committee_count = 0;
      while ( 0 < count( $containers ) ) {/*{{{*/
        $container = array_values(array_pop($containers));
        krsort($container);
        $container = array_values($container);
        while ( 0 < count( $container ) ) {/*{{{*/
          $tag = array_pop($container);
          if ( array_key_exists('url', $tag) ) {
            $hash = UrlModel::get_url_hash($tag['url']);
						$committee_count++;
						$committee_leader_boxes[$committee_count] = array(
							'hash' => $hash,
							'text' => <<<EOH
<a href="{$tag['url']}" id="{$hash}" class="matchable legiscope-remote listing-committee-name">{$tag['text']}</a>
EOH
						);
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
            $data = array(
              'fullname'   => $name['original'],
              'firstname'  => $parsed_name['given'],
              'mi'         => $parsed_name['mi'],
              'surname'    => $parsed_name['surname'],
              'namesuffix' => $parsed_name['suffix'],
            );
            $m->
              set_contents_from_array($data)->
              fields(array_keys($data))->
              stow();
          }
					$committee_leader_boxes[$committee_count]['text'] .= <<<EOH
<span id="name-span-{$hash}" class="matchable representative-name listing-committee-representative">{$tag['text']}</span><br/>
EOH;
        }/*}}}*/
      }/*}}}*/
      // At this point, the $containers stack has been depleted of entries,
      // basically being transformed into the $committees stack
      // $this->recursive_dump($committees,"(marker) -- -- --");
    }/*}}}*/
    else {/*{{{*/
      $pagecontent = join('', $this->get_filtered_doc());
    }/*}}}*/

		$pagecontent = '';
		while ( 0 < count($committee_leader_boxes) ) {
			$cl = array_shift($committee_leader_boxes);
			$pagecontent .= <<<EOH
<div class="committee-leader-box" id="line-{$cl['hash']}">
{$cl['text']}
</div>

EOH;
		}
		$pagecontent = <<<EOH
<div>{$pagecontent}</div>
EOH;

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

    $pagecontent = <<<EOH
<div class="congresista-dossier-list link-cluster suppress-reorder" id="committee-listing">
  <div><h2><input type="text" class="full-width" id="filter-committees" /></h2>{$pagecontent}</div>
</div>
<div id="committee-details-container" class="alternate-original half-container"></div>
<script type="text/javascript">
var match_timeout = null;
jQuery(document).ready(function(){
  initialize_linkset_clickevents(jQuery('div[id*=committee-listing]'),'div');
	jQuery('#filter-committees').unbind('clock').click(function(e){
    e.stopPropagation();
    e.preventDefault();
    return false;
  });
  jQuery('#filter-committees').keyup(function(e){
    if ( typeof match_timeout != 'null' ) {
      clearTimeout(match_timeout);
      match_timeout = null;
    }
    var empty_match = jQuery(this).val().length == 0;
    var current_re = new RegExp(jQuery(this).val(),'gi');
    jQuery('div[id=committee-listing]').find('[class*=matchable]').each(function(){
      jQuery(this).removeClass('unmatched').removeClass('matched');
      if (empty_match || current_re.test(jQuery(this).text().replace(/ñ/gi,'n')) || current_re.test(jQuery(this).find('a').text().replace(/ñ/gi,'n'))) {
        jQuery(this).addClass('matched');
      } else {
        jQuery(this).addClass('unmatched');
      }
    });
    match_timeout = setTimeout((function(){
      jQuery('div[id=committee-listing]').find('[class*=matchable]').each(function(){ 
        if ( jQuery(this).hasClass('matched') || jQuery(this).hasClass('unmatched') ) {

          var selfid = jQuery(this).attr('id');
          var peer = /^name-span-.*/g.test(selfid) ? selfid.replace(/^name-span-/,'') : selfid;
					if ( jQuery('[id='+selfid+']').hasClass('unmatched') && jQuery('[id='+peer+']').hasClass('unmatched') ) {
            jQuery('[id=line-'+selfid.replace(/^name-span-/,'')+']').slideUp();
          } else 
					if ( jQuery('[id='+selfid+']').hasClass('matched') | jQuery('[id='+peer+']').hasClass('matched') ) {
            jQuery('[id=line-'+selfid.replace(/^name-span-/,'')+']').slideDown();
          }

        }
      });
    }),700);
  });
});
</script>

EOH
		;

    // $parser->json_reply = array('retainoriginal' => TRUE);

	}/*}}}*/

}

