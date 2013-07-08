<?php

/*
 * Class SenateCommitteeListParseUtility
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class SenateCommitteeListParseUtility extends SenateCommonParseUtility {
  
  protected $have_toc = FALSE;
  var $desc_stack = array();

  function __construct() {
    parent::__construct();
  }

  function & get_desc_stack() {
    return $this->desc_stack;
  }

  /**** http://www.senate.gov.ph/committee/duties.asp ****/

	function new_entry_to_desc_stack() {/*{{{*/
		array_push(
			$this->desc_stack,
			array(
				'link' => NULL,
				'title' => NULL,
				'description' => NULL,
			)
		);
	}/*}}}*/

  function ru_div_open(& $parser, & $attrs, $tagname ) {/*{{{*/
		$accept = parent::ru_div_open($parser,$attrs,$tagname);
		$this->current_tag();
		if ( array_element($this->current_tag['attrs'],'ID') == "toc" ) {
		 	$this->syslog(__FUNCTION__,__LINE__,"(marker) -------------- TOC FOUND ------------ ");
		 	$this->have_toc = TRUE;
		}
    if ($this->debug_tags) $this->recursive_dump($attrs,__LINE__);
    return $accept;
  }/*}}}*/
  function ru_div_cdata(& $parser, & $cdata) {/*{{{*/
		$cdata = preg_replace(
			array('@[^&-:\', A-Z]@i',"@( |\t)+@"),
			array('',' '),
			$cdata
		);
		$result = parent::ru_div_cdata($parser,$cdata);
		$this->current_tag();
		// Detect 'Committee on' prefix, and add a new entry to the
		// description stack each time this entry is found
		if (!$this->have_toc) {
		}
	 	else if (array_element($this->current_tag['attrs'],'CLASS') == 'h3_uline') {
			if ( 1 == preg_match('@Committee([ ]*)on@i', $cdata) ) {
				$this->new_entry_to_desc_stack();
			} else {
				$desc = array_pop($this->desc_stack);
				if ( is_null($desc['title']) ) $desc['title'] = $cdata;
				array_push($this->desc_stack, $desc);
			}
		}
		else if ( 1 == preg_match('@^(jurisdiction:)@i', $cdata) ){
			$desc = array_pop($this->desc_stack);
			if ( is_null($desc['description']) ) $desc['description'] = $cdata;
			array_push($this->desc_stack, $desc);
		}
		if ($this->debug_tags) 	$this->syslog(__FUNCTION__,__LINE__,"(marker) ".($result ? "" : 'REJECT')." {$this->current_tag['tag']}[{$this->current_tag['attrs']['CLASS']}|{$this->current_tag['attrs']['ID']}] '{$cdata}'");
		return $result;
	}/*}}}*/
	function ru_div_close(& $parser, $tag) {/*{{{*/
		$this->current_tag();
		$result = parent::ru_div_close($parser, $tag);
		return $result;
	}/*}}}*/

  function ru_a_open(& $parser, & $attrs, $tag) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'] = array();
    $this->update_current_tag_url('HREF');
    $this->push_tagstack();

    if ( $this->have_toc && array_key_exists('NAME', $attrs) ) {
      $desc = array_pop($this->desc_stack);
			if ( !is_null($desc['link']) ) {
				array_push($this->desc_stack, $desc);
				$this->new_entry_to_desc_stack();
				$desc = array_pop($this->desc_stack);
			}
      $desc['link'] = $attrs['NAME'];
      array_push($this->desc_stack, $desc);
    }
    if ($this->debug_tags) $this->syslog( __FUNCTION__, 'FORCE', $this->get_stacktags() . " --- {$this->current_tag['tag']} {$this->current_tag['attrs']['HREF']}" );
    return TRUE;
  }/*}}}*/
  function ru_a_cdata(& $parser, & $cdata) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'][] = $cdata;
    $this->push_tagstack();
    if ($this->debug_tags) $this->syslog( __FUNCTION__, 'FORCE', "--- {$this->current_tag['tag']} {$cdata}" );
    return TRUE;
  }/*}}}*/
  function ru_a_close(& $parser, $tag) {/*{{{*/
    $this->pop_tagstack();
		array_walk($this->current_tag['cdata'],create_function(
			'& $a, $k', '$a = trim(preg_replace(array("@\s+@i","@\s+ñ@i"),array(" ","ñ"),$a));'
		));
    $link_data = $this->collapse_current_tag_link_data();
    $this->add_to_container_stack($link_data);
    $this->push_tagstack();
    if ($this->debug_tags) $this->syslog( __FUNCTION__, 'FORCE', "--- {$this->current_tag['tag']}" );
    return TRUE;
  }/*}}}*/

  function ru_i_open(& $parser, & $attrs, $tag) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'] = array();
    $this->push_tagstack();
    if ($this->debug_tags) $this->syslog( __FUNCTION__, 'FORCE', $this->get_stacktags() . " --- {$this->current_tag['tag']} {$this->current_tag['attrs']['HREF']}" );
    return TRUE;
  }/*}}}*/
  function ru_i_cdata(& $parser, & $cdata) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'][] = $cdata;
    $this->push_tagstack();
    return TRUE;
  }/*}}}*/
  function ru_i_close(& $parser, $tag) {/*{{{*/
    return $this->embed_cdata_in_parent();
  }/*}}}*/

  /**** http://www.senate.gov.ph/committee/list.asp ('List of Committees') ****/

  function ru_table_open(& $parser, & $attrs, $tagname) {/*{{{*/
    $this->push_container_def($tagname, $attrs);
    return TRUE;
  }/*}}}*/
  function ru_table_close(& $parser, $tag) {/*{{{*/
    $this->stack_to_containers();
    return TRUE;
  }/*}}}*/

  function ru_td_open(& $parser, & $attrs, $tag) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'] = array();
    $this->push_tagstack();
    if ($this->debug_tags) $this->syslog( __FUNCTION__, 'FORCE', $this->get_stacktags() . " --- {$this->current_tag['tag']} {$this->current_tag['attrs']['HREF']}" );
    return TRUE;
  }/*}}}*/
  function ru_td_cdata(& $parser, & $cdata) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'][] = trim($cdata);
    $this->push_tagstack();
    if ($this->debug_tags) $this->syslog( __FUNCTION__, 'FORCE', "--- {$this->current_tag['tag']} {$cdata}" );
    return TRUE;
  }/*}}}*/
  function ru_td_close(& $parser, $tag) {/*{{{*/
    $this->pop_tagstack();
		array_walk($this->current_tag['cdata'],create_function(
			'& $a, $k', '$a = trim(preg_replace("@\s+@i"," ",$a));'
		));
		$text = join(" ", $this->current_tag['cdata']);
		$text = str_replace(array(' ñ','[BR]'),array('ñ',''), $text);
		$text = trim($text);
		$content = array(
			'text' => empty($text) ? '{EOL}' : $text,
			'seq' => $this->current_tag['attrs']['seq'],
		);
		if ( !empty($text) ) {
			$this->add_to_container_stack($content);
		}
    $this->push_tagstack();
    if ($this->debug_tags) $this->syslog( __FUNCTION__, 'FORCE', "--- {$this->current_tag['tag']}" );
    return TRUE;
  }/*}}}*/

	/** Utility methods **/

	static function get_committee_permalink_uri($committee_name, $as_array = FALSE) {
		$permalink_tail = LegislationCommonParseUtility::permalinkify_name($committee_name);
		$committee_url = array(C('LEGISCOPE_BASE'),'senate','committee',$permalink_tail);
		return $as_array ? $committee_url : join('/', $committee_url);
	}

  static function committee_permalink_to_regex($committee_uri_fragment) {
    $name_fragment = preg_replace('@[^-a-z]@i', '', $committee_uri_fragment);
    $name_fragment = explode('-', $name_fragment);
    array_walk($name_fragment,create_function('& $a, $k', '$a = "({$a})";'));
    $name_fragment = join('(.*)', $name_fragment);
    return $name_fragment;
  } 

  /** Higher-level page parsers **/

  function parse_committee_duties($parser, $pagecontent, $urlmodel) {/*{{{*/

    $committee        = new SenateCommitteeModel();
    $senator          = new SenatorDossierModel();

    // Malformed document hacks (2013 March 5 - W3C validator failure.  Check http://validator.w3.org/check?uri=http%3A%2F%2Fwww.senate.gov.ph%2Fcommittee%2Fduties.asp&charset=%28detect+automatically%29&doctype=Inline&ss=1&group=0&verbose=1&st=1&user-agent=W3C_Validator%2F1.3+http%3A%2F%2Fvalidator.w3.org%2Fservices)
    $content = preg_replace(
      array(
        '@(\<[/]*(o:p)\>)@im',
        '@(\<[/]*(u1:p)\>)@im',
        '@(\<[/]*(font)\>)@im',
      ),
      array(
        '<br class="unidentified"/>',
        '<br class="unidentified"/>',
        '<br class="unidentified"/>',
      ),
      $urlmodel->get_pagecontent()
    );
    $pagecontent = $content;
    $this->
      set_parent_url($urlmodel->get_url())->
      parse_html($content,$urlmodel->get_response_header());

    // Container accessors are not used
    $this->recursive_dump(($committee_info = $this->get_desc_stack(
    )),'(------) Names');

    $template = <<<EOH
<div class="republic-act-entry">
<span class="republic-act-heading"><a href="{url}" class="{cache_state}" id="{urlhash}">{committee_name}</a></span>
<span class="republic-act-desc">{jurisdiction}</span>
</div>

EOH;
    $replacement_content = '';

    $this->syslog(__FUNCTION__,__LINE__, "Committee count: " . count($committee_info));

    foreach ( $committee_info as $entry ) {
      $committee_name = $committee->cleanup_committee_name(trim($entry['link'],'#'));
      $short_code = preg_replace('@[^A-Z]@','',$committee_name);
      $committee->fetch_by_committee_name($committee_name);
      $committee->
        set_committee_name($committee_name)->
        set_short_code($short_code)->
        set_jurisdiction($entry['description'])->
        set_is_permanent('TRUE')->
        set_create_time(time())->
        set_last_fetch(time())->
        fields('committee_name,short_code,jurisdiction,is_permanent,create_time,last_fetch')->
        stow();
      $replacement_content .= $committee->substitute($template);
    }

    $pagecontent = $replacement_content;


  }/*}}}*/

  function parse_committee_list(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/

    $committee_senator = new SenateCommitteeSenatorDossierJoin();
    $committee         = new SenateCommitteeModel();
    $senator           = new SenatorDossierModel();
    $url               = new UrlModel();
    $target_congress   = 15; // FIXME: Detect current Congress Session

    // $this->recursive_dump($urlmodel->get_response_header(TRUE),'(warning)');

    $debug_method      = FALSE;
    $this->debug_tags  = FALSE;
    $this->
      set_parent_url($urlmodel->get_url())->
      parse_html($urlmodel->get_pagecontent(),$urlmodel->get_response_header());

    $containers     = $this->get_containers(
      'children[attrs:CLASS*=SenTable]'
    );
    if ( $debug_method ) $this->recursive_dump($containers,"(marker) -- - -- Committees Page");

    $senator_committees = array();

    $pagecontent = '';
    foreach ( $containers as $container ) {/*{{{*/
      $committee_list  = array(array());
      $committee_entry = NULL;
      $unique_committee_names = array();
      $unique_senator_names = array();
      krsort($container);
      while ( 0 < count($container) ) {/*{{{*/
        $entry = array_pop($container);
        $committee_entry = array_pop($committee_list);
        if ( array_key_exists('url', $entry) ) {/*{{{*/// Senator bio URL
          // Senator + bio URL
          $senator_nametitle = $senator->cleanup_senator_name(utf8_decode($entry['text']));
          $senator_nameregex = LegislationCommonParseUtility::committee_name_regex($senator_nametitle);
          if ( !array_key_exists('senators', $committee_entry) ) $committee_entry['senators'] = array();
          $committee_entry['senators'][$senator_nameregex] = array(
            'id'   => 0,
            'fullname_db' => NULL,
            'fullname' => $senator_nametitle, 
            'name_regex' => $senator_nameregex,
            'url' => $entry['url'],
            'url_db' => NULL,
          );
          $unique_senator_names[$senator_nametitle] = array(
            'nameregex' => $senator_nameregex,
            'bio_url' => $entry['url'],
          ); 
        }/*}}}*/
        else {/*{{{*/// Committee name
          $committee_name = $entry['text']; 
          $name_regex =  LegislationCommonParseUtility::committee_name_regex($committee_name);
          array_push($committee_list, $committee_entry);
          if ( FALSE == $name_regex ) continue;
          unset($committee_entry);
          $committee_entry = array(
            'id'                   => 0,
            'committee_name_db'    => NULL,
            'committee_name'       => $committee_name,
            'committee_name_regex' => $name_regex,
            'jurisdiction'         => NULL,
            'senators'             => array(),
          );
          $unique_committee_names[$committee_name] = $name_regex;
        }/*}}}*/
        array_push($committee_list, $committee_entry);
      }/*}}}*/
      // Make the committee name regex be the committee list array key
      $committee_list = array_filter($committee_list);
      $committee_list = array_combine(
        array_map(create_function('$a', 'return $a["committee_name_regex"];'), $committee_list),
        $committee_list
      );
      
      krsort($unique_committee_names);
      while ( 0 < count($unique_committee_names) ) {/*{{{*/// Match committee names to extant records
        $distinct_names = array();
        while ( count($distinct_names) < 15 && 0 < count($unique_committee_names) ) {
          $distinct_names[] = array_pop($unique_committee_names);
        }
        if ( $debug_method ) {/*{{{*/
          $this->syslog(__FUNCTION__,__LINE__,"(marker) -- - -- Batch of " . count($distinct_names)); 
          $this->recursive_dump($distinct_names,"(marker) -- - --");
        }/*}}}*/
        $distinct_names = join('|', $distinct_names);
        $committee->where(array('AND' => array(
          'committee_name' => "REGEXP '^({$distinct_names})$'"
        )))->recordfetch_setup();
        $r = NULL;
        while ( $committee->recordfetch($r) ) {
          array_walk($committee_list, create_function(
            '& $a, $k, $s', 'if ( 1 == preg_match( "@{$k}@i", $s["committee_name"] ) )  { $a["committee_name_db"] = $s["committee_name"]; $a["id"] = $s["id"]; $a["jurisdiction"] = $s["jurisdiction"]; }'
          ),$r);
        }
      }/*}}}*/
      
      krsort($unique_senator_names);
      $this->recursive_dump($senator->get_joins(),"(marker) - - -");
      while ( 0 < count($unique_senator_names) ) {/*{{{*/// Match senators to extant records
        $distinct_names = array();
        while ( count($distinct_names) < 15 && 0 < count($unique_senator_names) ) {
          $distinct_names[] = array_pop($unique_senator_names);
        }
        if ( $debug_method ) {/*{{{*/
          $this->syslog(__FUNCTION__,__LINE__,"(marker) -- - -- Batch of " . count($distinct_names)); 
        }/*}}}*/
        // Cleanup the distinct_names array: Structure becomes [ regex ] => [ url ]
        $distinct_names = array_combine(
          array_map(create_function('$a','return $a["nameregex"];'),$distinct_names), // Regex as key
          array_map(create_function('$a','return $a["bio_url"];'),$distinct_names)
        );
        if ( $debug_method ) {/*{{{*/
          $this->recursive_dump($distinct_names,"(marker) -- - --");
        }/*}}}*/

        // Match either the list of name regexes or list of CV / "bio" URLs
        $senator->debug_method    = FALSE;
        $senator->debug_final_sql = TRUE;
        $senator->
          //join_all()->
          //join(array(
          //  'committee[role*=chairperson|i]{committee_name,short_code,jurisdiction[is_permanent=TRUE]}',
          //))->
          join(array('committee'))->
          where(array('OR' => array(
            'fullname' => ("REGEXP '^(".join('|', array_keys($distinct_names)).")'"),
            'bio_url' => $distinct_names
          )))->
          recordfetch_setup();
        $senator->debug_final_sql = FALSE;
        $senator->debug_method    = FALSE;

        // Mark the transient list [ regex ] => { [ id ] => id, [ fullname ] => db_fullname } }
        $r = NULL;
        while ( $senator->recordfetch($r) ) {/*{{{*/
          if ( $debug_method ) {
            $this->syslog(__FUNCTION__,__LINE__,"(marker)  -- - - -- - --- - - Found ".get_class($senator)." record #{$r['id']}"); 
            $this->recursive_dump($r,"(marker) -- - ->"); 
            $this->syslog(__FUNCTION__,__LINE__,"(marker)  -- - - -- Distinct names"); 
            $this->recursive_dump($distinct_names,"(marker) -- - ->"); 
          }
          array_walk($distinct_names, create_function(
            '& $a, $k, $s', 'if ( (1 == preg_match( "@{$k}@i", array_element($s,"fullname") )) || (array_element($s,"bio_url") == $a) ) { $a = array("id" => $s["id"], "fullname" => array_element($s,"fullname"), "url_db" => array_element($s,"bio_url") ); }'
          ),$r);
          $r = NULL;
        }/*}}}*/
        if ( $debug_method ) {/*{{{*/
          $this->syslog(__FUNCTION__,__LINE__,"(marker) -- - -- - -- Distinct Names AFTER update"); 
          $this->recursive_dump($distinct_names,"(marker) - - - - - -");
        }/*}}}*/
        // Use the transient list to update all entries
        foreach ( $committee_list as $r => $commlist ) {
          array_walk( $committee_list[$r]["senators"], create_function(
            '& $a, $k, $s', 'if ( array_key_exists($k,$s) && is_array($s[$k]) && array_key_exists("id", $s[$k]) ) { $a["id"] = $s[$k]["id"]; $a["fullname_db"] = $s[$k]["fullname"]; $a["url_db"] = $s[$k]["url_db"]; }'
          ),$distinct_names);
        }

        if ( $debug_method ) {/*{{{*/
          $this->syslog(__FUNCTION__,__LINE__,"(marker) -- - -- Patterns"); 
          $this->recursive_dump($distinct_names,"(marker) - -- -");
        }/*}}}*/
      }/*}}}*/
     
      if ( $debug_method ) {/*{{{*/
        $this->syslog(__FUNCTION__,__LINE__,"(marker) -- - -- Parsed Content"); 
        $this->recursive_dump($committee_list,"(marker) - -- -");
      }/*}}}*/

      if ( $debug_method ) $this->recursive_dump($committee_list,"(marker) - - - - - - -");

      foreach ( $committee_list as $committee_name_regex => $c ) {/*{{{*/// Generate markup
        $senator_entries = '';
        if (!(0 < intval(array_element($c,'id')))) {
          $id = $committee->
            set_id(NULL)->
            stow_committee($c['committee_name']);
          $this->syslog(__FUNCTION__,__LINE__,"(marker)  Stowed {$c['committee_name']} #{$id}");
          $committee_list[$committee_name_regex]['id'] = $id;
          $c['id'] = $id;
        }
        foreach ( $c['senators'] as $senator_index => $senator_entry ) {/*{{{*/
          $link_attribs = array('legiscope-remote');
          if ( 0 < intval($senator_entry['id']) ) $link_attribs[] = 'cached';
          $link_attribs = join(' ', $link_attribs);
          $linktext = utf8_decode(nonempty_array_element($senator_entry,'fullname_db',array_element($senator_entry,'fullname','...')));
          if ( (0 == strlen(array_element($senator_entry,'url_db'))) || 
            (0 == strlen(array_element($senator_entry,'fullname_db'))) ||
            (!(1 == preg_match("@_old@i",$senator_entry['url'])) && !($senator_entry['url_db'] == $senator_entry['url'])) ||
            (strlen($senator_entry['fullname_db']) < strlen($senator_entry['fullname'])) ) {/*{{{*/
              if (!is_null(array_element($senator_entry,'id'))) $senator->fetch(array_element($senator_entry,'id'),'id');
              $senator_id = $senator->
                set_id(array_element($senator_entry,'id'))->
                set_last_fetch(time())->
                set_fullname($senator_entry['fullname'])->
                set_bio_url($senator_entry['url'])->
                fields(array('last_fetch','fullname','bio_url'))->
                stow();
              $committee_list[$committee_name_regex]['senators'][$senator_index]['id'] = $senator_id;
              $senator_entry['id'] = $senator_id;
              if ( (0 < $senator_id) || $debug_method ) {/*{{{*/
                $this->syslog(__FUNCTION__,__LINE__,"(marker) Updated/stored #{$senator_id} Senator {$senator_entry['fullname']}");
                $this->syslog(__FUNCTION__,__LINE__,"(marker)    URL, parsed: " . $senator_entry['url']);
                $this->syslog(__FUNCTION__,__LINE__,"(marker)      URL in DB: " . $senator_entry['url_db']);
                $this->syslog(__FUNCTION__,__LINE__,"(marker)       Fullname: " . $senator_entry['fullname']);
                $this->syslog(__FUNCTION__,__LINE__,"(marker) Fullname in DB: " . $senator_entry['fullname_db']);
              }/*}}}*/
          }/*}}}*/
          $committee_senator->
            set_id(NULL)->
            fetch(array(
              'senate_committee' => $c['id'],
              'senator_dossier' => $senator_entry['id'],
            ),'AND');
          $committee_senator->
            set_role('chairperson')->
            set_senate_committee($c['id'])->
            set_senator_dossier($senator_entry['id'])->
            set_target_congress($target_congress)->
            set_last_fetch(time())->
            set_create_time(time())->
            stow();

          $senator_entries .= <<<EOH
<a href="{$senator_entry['url_db']}" class="{$link_attribs}">{$linktext}</a><br/>

EOH;
        }/*}}}*/
        $senator_entries = <<<EOH
<span class="committee-senators">
{$senator_entries}
</span>
EOH;
        $committee_desc = 0 < intval($c['id'])
          ? htmlspecialchars($c['jurisdiction'])
          : '...'
          ;
        $committee_name_link = SenateCommitteeListParseUtility::get_committee_permalink_uri($c['committee_name']);
        $committee_name_link = <<<EOH
<a href="/{$committee_name_link}" class="legiscope-remote">{$c['committee_name_db']}</a>
EOH;
        $replacement_content = <<<EOH
<div class="committee-functions-leadership">
  <div class="committee-name">{$committee_name_link}</div>
  <div class="committee-leaders">
  {$senator_entries}
  </div>
  <div class="committee-jurisdiction">{$committee_desc}</div>
</div>

EOH;
        $pagecontent .= $replacement_content;
      }/*}}}*/
    }/*}}}*/


  }/*}}}*/

}
