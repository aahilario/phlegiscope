<?php

/*
 * Class CongressCommitteeListParseUtility
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class CongressCommitteeListParseUtility extends CongressCommonParseUtility {
  
	var $committee_list = array();
	var $committee_sub  = NULL;

  function __construct() {
    parent::__construct();
		$this->committee_list = array();
  }

  function ru_header_close(& $parser, $tag) {/*{{{*/
		// All <header> tags are discarded by this parser
    return FALSE;
  }/*}}}*/

  function ru_footer_close(& $parser, $tag) {/*{{{*/
		// All <footer> tags are discarded by this parser
    return FALSE;
  }/*}}}*/

  function ru_style_close(& $parser, $tag) {/*{{{*/
		// All <style> tags are discarded by this parser
    return FALSE;
  }/*}}}*/

  function ru_article_close(& $parser, $tag) {/*{{{*/
		// All <article> tags are discarded by this parser
    return FALSE;
  }/*}}}*/

 function ru_tr_open(& $parser, & $attrs, $tag) {/*{{{*/
    $this->is_bill_head_container = FALSE;
    $this->pop_tagstack();
    $this->current_tag['cdata'] = array();
    $this->push_tagstack();
    $this->push_container_def($tag, $attrs);
    if ($this->debug_tags) $this->syslog( __FUNCTION__, __LINE__, "(marker) --- {$this->current_tag['tag']}" );
    return TRUE;
  }/*}}}*/
  function ru_tr_cdata(& $parser, & $cdata) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'][] = $cdata;
    $this->push_tagstack();
    if ($this->debug_tags) $this->syslog( __FUNCTION__, __LINE__, "(marker) --- {$this->current_tag['tag']}" );
    return TRUE;
  }/*}}}*/
  function ru_tr_close(& $parser, $tag) {/*{{{*/
    // Parsing of name + bailiwick rows moved into this method
    $this->current_tag();
    // Get container contents 
    $cells = NULL;
    $container_item_hash = $this->stack_to_containers();
    if ( !is_null( $container_item_hash ) ) {
      extract($container_item_hash);
      $cells = nonempty_array_element($container,'children');
    }
    if ($this->debug_tags) $this->syslog( __FUNCTION__, __LINE__, "(marker) --- {$this->current_tag['tag']}" );
    if ( is_array($cells) && ( 0 < count($cells) ) ) {
      $row = array();
      while ( 0 < count($cells) ) {
        $cell = array_shift($cells);
        if ( !is_array($cell) ) continue;
        if ( array_key_exists('url', $cell) ) {
					if ( 0 < strlen(array_element($cell,'text')) ) {
						$row['url']  = trim($cell['url']);
						$row['text'] = trim($cell['text']);
						$row['type'] = $this->committee_sub;
					}
          continue;
        }
				if ( !array_key_exists('text', $cell) ) {
				} else if ( array_key_exists('url',$row) ) {
					if ( 0 < strlen(trim($cell['text'])) && !array_key_exists('chairperson',$row) )
          $row['chairperson'] = trim($cell['text']);
				} else {
					// Test for a committee listing subsection
					$match = array();
					if ( 1 == preg_match('@(STANDING|SPECIAL) COMMITTEES([^(]*)\(([^)]*)\)@i', $cell['text'], $match) ) {
						$this->committee_sub = array_element($match,1);
					}
				}
      }
			if ( 0 < count($row) && array_key_exists('chairperson',$row) ) {
				$this->committee_list[] = $row;
				return FALSE;
			}
    }
    return TRUE;
  }/*}}}*/

  function ru_td_open(& $parser, & $attrs, $tag) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'] = array();
    $this->push_tagstack();
    if ($this->debug_tags) $this->syslog( __FUNCTION__, __LINE__, "(marker) --- {$this->current_tag['tag']}" );
    return TRUE;
  }/*}}}*/
  function ru_td_cdata(& $parser, & $cdata) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'][] = $cdata;
    $this->push_tagstack();
    if ($this->debug_tags) $this->syslog( __FUNCTION__, __LINE__, "(marker) --- {$this->current_tag['tag']}" );
    return TRUE;
  }/*}}}*/
  function ru_td_close(& $parser, $tag) {/*{{{*/
    $this->pop_tagstack();
    $cell_type  = array(
      'text' => trim(join('',array_filter($this->current_tag['cdata']))),
      'seq'  => array_element($this->current_tag['attrs'],'seq')
    );
    $this->push_tagstack();
    if ( 0 < strlen($cell_type['text']) ) $this->add_to_container_stack($cell_type);
    if ($this->debug_tags) $this->syslog( __FUNCTION__, __LINE__, "(marker) --- {$this->current_tag['tag']}" );
    return TRUE;
  }/*}}}*/

  function ru_th_open(& $parser, & $attrs, $tag) {/*{{{*/
		return $this->ru_td_open($parser, $attrs, $tag);
  }/*}}}*/
  function ru_th_cdata(& $parser, & $cdata) {/*{{{*/
		return $this->ru_td_cdata($parser, $cdata);
  }/*}}}*/
  function ru_th_close(& $parser, $tag) {/*{{{*/
		return $this->ru_td_close($parser, $tag);
  }/*}}}*/

	function congress_committee_listing(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/

		$debug_method = FALSE;
		// http://www.congress.gov.ph/committees

    $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked for " . $urlmodel->get_url() );
    $m    = new RepresentativeDossierModel();
    $comm = new CongressionalCommitteeDocumentModel();
    $link = new CongressionalCommitteeRepresentativeDossierJoin();

    $this->debug_tags = FALSE;
    $this->debug_operators = FALSE;
    $this->
      set_parent_url($urlmodel->get_url())->
      parse_html(
        $urlmodel->get_pagecontent(),
        $urlmodel->get_response_header()
      );
    $this->debug_operators = FALSE;

    $pagecontent = str_replace('[BR]','<br/>',join('',$this->get_filtered_doc()));

		if ( $debug_method ) $this->recursive_dump($this->committee_list,"(marker) +");

    // We are able to extract names of committees and current chairperson.
		$committee_leader_boxes = array();
    if ( 0 < count($this->committee_list) ) {/*{{{*/
      $pagecontent = '';
      $committees  = array();
			while ( 0 < count( $this->committee_list ) ) {/*{{{*/

				$tag         = array_shift($this->committee_list);
				$chairperson = nonempty_array_element($tag,'chairperson');
				$hash        = UrlModel::get_url_hash($tag['url']);
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
					'chairperson'    => $chairperson,
				);

				// If the chairperson striing is empty, we go without.
				if (0 < strlen($chairperson)) {/*{{{*/

					// Replace $name with legislator dossier record
					// Parameter $name contains a RepresentativeDossier entry on successful load.
					$name = $chairperson;
					$chairperson = $m->replace_legislator_names_hotlinks($name);
					if (!(0 < strlen(trim($chairperson)))) {
					 	$chairperson = nonempty_array_element($tag,'chairperson');
					}

					if (is_null(nonempty_array_element($name,'fullname')) && (0 < intval(array_element($name,'id')))) {/*{{{*/
						$this->syslog( __FUNCTION__, __LINE__, "(marker) Stowing {$name['original']}" );
						$parsed_name = $m->parse_name( $name['original'] );
						$committees[$hash]['original'] = $parsed_name;
						$data = array(
							'fullname'   => $name['original'],
							'firstname'  => $parsed_name['given'],
							'mi'         => $parsed_name['mi'],
							'surname'    => $parsed_name['surname'],
							'namesuffix' => $parsed_name['suffix'],
						);
						$this->recursive_dump($data,"(marker) ------");
            // FIXME: Ensure that member records are stowed properly
						$m->fetch($name['id'],'id');
						if (!$m->in_database()) $m->
							set_contents_from_array($data)->
							fields(array_keys($data))->
							stow();
					}/*}}}*/
				}/*}}}*/
				else {
					$chairperson = 'TBA';
				}
				$committee_leader_boxes[$committee_count]['text'] .= <<<EOH
<span id="name-span-{$hash}" class="matchable representative-name listing-committee-representative">{$chairperson}</span><br/>
EOH;
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
<div class="link-cluster suppress-reorder" id="committee-listing">
  <div><h2><input type="text" class="full-width" id="listing-filter-string" /></h2>{$pagecontent}</div>
</div>

<script type="text/javascript">
var match_timeout = null;
jQuery(document).ready(function(){
  initialize_linkset_clickevents(jQuery('div[id*=committee-listing]'),'div');
	jQuery('#listing-filter-string').unbind('click').click(function(e){
    e.stopPropagation();
    e.preventDefault();
    return false;
  });
  jQuery('#listing-filter-string').keyup(function(e){
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

