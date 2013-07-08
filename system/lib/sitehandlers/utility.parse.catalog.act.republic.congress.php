<?php

/*
 * Class CongressRepublicActCatalogParseUtility
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class CongressRepublicActCatalogParseUtility extends CongressCommonParseUtility {
  
  function __construct() {
    parent::__construct();
  }

  function seek_postparse_ra(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/

    $restore_url     = NULL;
    $content_changed = FALSE;

    if ( !is_null($parser->metalink_url) ) {
      $restore_url = $urlmodel->get_urlhash();
      $urlmodel->fetch($parser->metalink_url,'url');
      $this->syslog( __FUNCTION__, __LINE__, "(warning) Switching metalink URL #" . $urlmodel->get_id() . " {$parser->metalink_url} <- {$urlmodel}" );
      $pagecontent = $urlmodel->get_pagecontent();
    }

    $this->syslog( __FUNCTION__, __LINE__, "(marker) Pagecontent postparser invocation for " . $urlmodel->get_url() );

    $cache_filename = md5(__FUNCTION__ . $parser->trigger_linktext);
    $cache_filename = "./cache/{$this->subject_host_hash}-{$cache_filename}.generated";

    if (0) {/*{{{*/
      if ( C('ENABLE_GENERATED_CONTENT_BUFFERING') || !$content_changed ) {
        if ( $parser->from_network ) unlink($cache_filename);
        else if ( file_exists($cache_filename) ) {
          $this->syslog( __FUNCTION__, __LINE__, "Retrieving cached markup for " . $urlmodel->get_url() . " from {$cache_filename}" );
          $pagecontent = file_get_contents($cache_filename);
          if ( !is_null($restore_url) ) $urlmodel->fetch($restore_url,'url');
          return;
        }
      }
    }/*}}}*/

    $ra_linktext = $parser->trigger_linktext;

    $this->set_parent_url($urlmodel->get_url())->parse_html($urlmodel->get_pagecontent(),$urlmodel->get_response_header());

    $this->recursive_dump(($target_form = array_values($this->get_containers(
      'children[attrs:ACTION*=index.php\?d=ra$]'
    ))),"(debug) Extracted content");

    if (is_null($target_form[0])) {/*{{{*/
      $this->recursive_dump($target_form,'(warning) Form Data');
      $this->recursive_dump($this->get_containers(),'(warning) Structure Change');
      $this->syslog(__FUNCTION__,__LINE__,"(warning) ------------------ STRUCTURE CHANGE ON {$urlmodel} ! ---------------------");
      if ( !is_null($restore_url) ) $urlmodel->fetch($restore_url,'url');
      return;
    }/*}}}*/

    $target_form = $target_form[0]; 

    $this->recursive_dump(($target_form = $this->extract_form_controls($target_form)),
      'Target form components');

    // $this->recursive_dump($target_form,__LINE__);
    extract($target_form);
    $this->recursive_dump($select_options,'SELECT values');

    $metalink_data = array();

    // Extract Congress selector for use as FORM submit action content
    $replacement_content = '';
    foreach ( $select_options as $select_option ) {/*{{{*/
      // Take a copy of the rest of the form controls
      if ( empty($select_option['value']) ) continue;
      $control_set = is_array($form_controls) ? $form_controls : array();
      $control_set[$select_name] = $select_option['value'];
      $control_set['Submit'] = 'submit';

      $faux_url = UrlModel::parse_url($urlmodel->get_url());
      $faux_url['query'] = "d=ra";
      $faux_url = UrlModel::recompose_url($faux_url,array(),FALSE);

      $link_class_selector = array("fauxpost");
      if ( $ra_linktext == $select_option['text'] ) {
        $link_class_selector[] = "selected";
      }
      $link_class_selector = join(' ', $link_class_selector);

			$generated_link = UrlModel::create_metalink($select_option['text'], $faux_url, $control_set, $link_class_selector);
      $replacement_content .= $generated_link;
    }/*}}}*/

    // ----------------------------------------------------------------------
    // Coerce the parent URL to index.php?d=ra
    $parent_url   = UrlModel::parse_url($urlmodel->get_url());
    $parent_url['query'] = 'd=ra';
    $parent_url   = UrlModel::recompose_url($parent_url,array(),FALSE);
    $test_url     = new UrlModel();

    $pagecontent = "{$replacement_content}<br/><hr/>";
    $urlmodel->increment_hits()->stow();

    $test_url->fetch($parent_url,'url');
    $page = $urlmodel->get_pagecontent();
    $test_url->set_pagecontent($page);
    $test_url->set_response_header($urlmodel->get_response_header())->increment_hits()->stow();

    $ra_listparser = new CongressRaListParseUtility();
    $ra_listparser->debug_tags = FALSE;
    $ra_listparser->set_parent_url($urlmodel->get_url())->parse_html($page,$urlmodel->get_response_header());
    $ra_listparser->debug_operators = FALSE;

    $this->recursive_dump(($ra_list = $ra_listparser->get_containers(
      '*[item=RA][bill-head=*]'
    )),"Extracted content");

    $replacement_content = '';

    $this->syslog(__FUNCTION__,__LINE__,"(warning) Long operation. Parsing list of republic acts. Entries: " . count($ra_list));

    $parent_url    = UrlModel::parse_url($parent_url);
    $republic_act  = new RepublicActDocumentModel();
    $republic_acts = array();
    $sn_stacks     = array(0 => array());
    $stacked_count = 0;

    foreach ( $ra_list as $ra ) {/*{{{*/

      $url           = UrlModel::normalize_url($parent_url, $ra);
      $urlhash       = UrlModel::get_url_hash($url);
      $ra_number     = join(' ',$ra['bill-head']);
      $approval_date = NULL;
      $origin        = NULL;

      if ( !array_key_exists('meta', $ra) ) {/*{{{*/
        $this->syslog(__FUNCTION__,__LINE__,"(marker) Skipping {$ra_number} {$url}");
        continue;
      }/*}}}*/

      $ra_meta = join('', $ra['meta']);
      $ra_meta = preg_replace(
        array(
          "@Origin:@iU",
          "@Approved by(.*) on (.*)@iU",
        ),
        array(
          ';ORIGIN:$1',
          ';APPROVALDATE:$2',
        ),
        $ra_meta
      );

      $match_parts = array();
      preg_match_all('@([^;:]*):([^;]*)@',$ra_meta,$match_parts);

      if ( is_array($match_parts) && (0 < count($match_parts)) ) {/*{{{*/
        $ra_meta       = array_combine($match_parts[1], $match_parts[2]);
        $approval_date = trim(array_element($ra_meta,'APPROVALDATE'));
        $origin        = trim(array_element($ra_meta,'ORIGIN'));
      }/*}}}*/

      if ( FALSE == strtotime($approval_date) ) $approval_date = NULL; 

      // There are a few hundred Republic Acts enumerated per Congress.
      // We take one memory-intensive iteration pass to stack RA series numbers,
      // and a second one to both pop nonexistent RA entries from that stack
      // and create missing records in the database.

      if ( 0 < strlen($ra_number) ) {/*{{{*/// Stow the Republic Act record

        $now_time        = time();
        $target_congress = preg_replace("@[^0-9]*@","",$parser->trigger_linktext);

        $republic_acts[$ra_number] = array(
          'congress_tag'  => $target_congress,
          'sn'            => $ra_number,
          'origin'        => $origin,
          'description'   => join(' ',$ra['desc']),
          'url'           => $url,
          'approval_date' => $approval_date,
          'searchable'    => FALSE, // $searchable,$test_url->fetch($urlhash,'urlhash'); $searchable = $test_url->in_database() ? 1 : 0;
          'last_fetch'    => $now_time,
          '__META__' => array(
            'urlhash' => $urlhash,
          ),
        );
        $sn_stacks[$stacked_count][] = $ra_number;
        if ( 20 <= count($sn_stacks[$stacked_count]) ) {
          $stacked_count++;
          $sn_stacks[$stacked_count] = array();
        }
        //$sorted_desc[$ra_number] = $republic_act->get_standard_listing_markup($ra_number,'sn');
      }/*}}}*/

    }/*}}}*/

    $sorted_desc = array();

    $this->syslog(__FUNCTION__,__LINE__,'(marker) Elements ' . count($sorted_desc));
    $this->syslog(__FUNCTION__,__LINE__,'(marker) Stacked clusters ' . count($sn_stacks));

    $ra_template = <<<EOH

<div class="republic-act-entry">
<span class="republic-act-heading"><a href="{url}" class="legiscope-remote {cache_state}" id="{urlhash}">{sn}</a></span>
<span class="republic-act-desc">{description}</span>
<span class="republic-act-meta">Origin of legislation: {origin}</span>
<span class="republic-act-meta">Passed into law: {approval_date}</span>
</div>

EOH;

    // Deplete the stack, generating markup for entries that exist
		while ( 0 < count( $sn_stacks ) ) {/*{{{*/
			$sn_stack = array_pop($sn_stacks); 
			$ra = array();
			$republic_act->where(array('AND' => array(
				'sn' => $sn_stack
			)))->recordfetch_setup(); 
			// Now remove entries from $republic_acts
			while ( $republic_act->recordfetch($ra,TRUE) ) {
				$ra_number = $ra['sn'];
				$republic_acts[$ra_number] = NULL;
				$urlhash = $republic_acts[$ra_number]['__META__']['urlhash'];
				// Generate markup for already-cached entries
				$sorted_desc[$ra_number] = preg_replace(
					array(
						'@{urlhash}@i',
						'@{cache_state}@i',
					),
					array(
						$urlhash,
						'cached',
					),
					$republic_act->substitute($ra_template)
				);
			}
		}/*}}}*/
    $republic_acts = array_filter($republic_acts);

    $this->recursive_dump(array_keys($republic_acts),"(marker) -Remainder-");

    krsort($sorted_desc,SORT_STRING);

    $pagecontent .= join("\n",$sorted_desc);

    if (1) {
      if ( $content_changed || C('ENABLE_GENERATED_CONTENT_BUFFERING') ) {
        file_put_contents($cache_filename, join('',$ra_listparser->get_filtered_doc()));
      }
    }

    if ( !is_null($restore_url) ) $urlmodel->fetch($restore_url,'urlhash');

    $this->syslog(__FUNCTION__,__LINE__,'---------- DONE ----------- ' . strlen($pagecontent));


	}/*}}}*/

}

