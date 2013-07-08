<?php

/*
 * Class SenatorBioParseUtility
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class SenatorBioParseUtility extends SenateCommonParseUtility {
  
  var $have_toc = FALSE;

  function __construct() {
    parent::__construct();
  }

  function ru_table_open(& $parser, & $attrs, $tagname) {/*{{{*/
    $this->push_container_def($tagname, $attrs);
    return TRUE;
  }/*}}}*/
  function ru_table_close(& $parser, $tag) {/*{{{*/
    $this->stack_to_containers();
    return TRUE;
  }/*}}}*/

  function ru_tr_open(& $parser, & $attrs, $tag) {/*{{{*/
    return TRUE;
  }/*}}}*/
  function ru_tr_cdata(& $parser, & $cdata) {/*{{{*/
    return TRUE;
  }/*}}}*/
  function ru_tr_close(& $parser, $tag) {/*{{{*/
    return TRUE;
  }/*}}}*/

  function ru_td_open(& $parser, & $attrs, $tag) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'] = array();
    $this->push_tagstack();
    if ( $this->have_toc && array_key_exists('NAME', $attrs) ) {
      $desc = array_pop($this->desc_stack);
      $desc['link'] = $attrs['NAME'];
      array_push($this->desc_stack, $desc);
    }
    if ($this->debug_tags) $this->syslog( __FUNCTION__, 'FORCE', $this->get_stacktags() . " --- {$this->current_tag['tag']} {$this->current_tag['attrs']['HREF']}" );
    return TRUE;
  }/*}}}*/
  function ru_td_cdata(& $parser, & $cdata) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'][] = $cdata;
    $this->push_tagstack();
    if ($this->debug_tags) $this->syslog( __FUNCTION__, 'FORCE', "--- {$this->current_tag['tag']} {$cdata}" );
    return TRUE;
  }/*}}}*/
  function ru_td_close(& $parser, $tag) {/*{{{*/
    $skip = FALSE;
    $this->pop_tagstack();
    if ($this->debug_tags) $this->syslog( __FUNCTION__, 'FORCE', "--- {$this->current_tag['tag']}" );
    if ( array_element($this->current_tag['attrs'],'ID') == "sidepane" ) $skip = TRUE;
    if ( !$skip ) {
      $this->add_to_container_stack($this->current_tag);
    }
    $this->push_tagstack();
    return !$skip;
  }/*}}}*/

  function ru_span_open(& $parser, & $attrs, $tag) {/*{{{*/
    // Handle A anchor/link tags
    if ( 0 < count($this->tag_stack) ) {
      $this->pop_tagstack();
      $this->current_tag['cdata'] = array();
      $this->push_tagstack();
    }
    return TRUE;
  }  /*}}}*/
  function ru_span_cdata(& $parser, & $cdata) {/*{{{*/
    // Attach CDATA to A tag 
    if ( !empty($cdata) && ( 0 < count($this->tag_stack) ) ) {
      $this->pop_tagstack();
      $this->current_tag['cdata'][] = str_replace("\n"," ",trim($cdata));
      $this->push_tagstack();
    }
    return TRUE;
  }/*}}}*/
  function ru_span_close(& $parser, $tag) {/*{{{*/
    $skip = FALSE;
    $this->pop_tagstack();
    $this->push_tagstack();
    $paragraph = array('text' => join('', $this->current_tag['cdata']));
    if ( array_element($this->current_tag['attrs'],'CLASS') == "backtotop" ) $skip = TRUE;
    if ( !$skip ) {
      $this->add_to_container_stack($paragraph);
    }
    return !$skip;
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
    $skip = FALSE;
    $this->pop_tagstack();
    $this->push_tagstack();
    $paragraph = array('text' => join(' ', $this->current_tag['cdata']));
    if ( array_element($this->current_tag['attrs'],'CLASS') == "backtotop" ) $skip = TRUE;
    if ( !$skip ) {
      $this->add_to_container_stack($paragraph);
    }
    return !$skip;
  }/*}}}*/

  function ru_a_close(& $parser, $tag) {/*{{{*/
    // Override global 
    $skip = FALSE;
    $this->pop_tagstack();
    // --
    if ( $this->current_tag['attrs']['HREF'] == '#top' ) $skip = TRUE;
    // --
    if ( !$skip ) {
      $this->add_to_container_stack($this->current_tag);
    }
    $this->push_tagstack();
    if ($this->debug_tags) $this->syslog( __FUNCTION__, 'FORCE', "--- {$this->current_tag['tag']}" );
    return !$skip;
  }/*}}}*/

  function ru_img_open(& $parser, & $attrs, $tag) {/*{{{*/
    // Handle A anchor/link tags
		$this->pop_tagstack();
		$this->update_current_tag_url('SRC');
		// Add capability to cache images as well
		$faux_mem_uuid = sha1(mt_rand(10000,100000) . ' ' . $this->current_tag['attrs']['SRC']);
		$this->current_tag['attrs']['FAUXSRC'] = $this->current_tag['attrs']['SRC'];
		$this->current_tag['attrs']['SRC'] = '{REPRESENTATIVE-AVATAR('.$faux_mem_uuid.','.$this->current_tag['attrs']['SRC'].')}';//$this->current_tag['attrs']['SRC'];
    if ( !array_key_exists('CLASS', $this->current_tag['attrs']) ) {
      // $this->syslog(__FUNCTION__,__LINE__,"Setting CSS selector for '{$this->current_tag['attrs']['HREF']}'");
      $this->current_tag['attrs']['CLASS'] = 'legiscope-remote representative-avatar representative-avatar-missing';
    } else {
      // $this->syslog(__FUNCTION__,__LINE__,"Adding CSS selector for '{$this->current_tag['attrs']['HREF']}'");
      $this->current_tag['attrs']['CLASS'] .= ' legiscope-remote';
    }
		$this->current_tag['attrs']['ID'] = "image-{$faux_mem_uuid}";
		$this->current_tag['attrs']['FAKEID'] = "{$faux_mem_uuid}";
		$this->push_tagstack();
    return TRUE;
  }  /*}}}*/
  function ru_img_cdata(& $parser, & $cdata) {/*{{{*/
    return TRUE;
  }/*}}}*/
  function ru_img_close(& $parser, $tag) {/*{{{*/
		$skip = FALSE;
		$this->pop_tagstack();
		if ( 1 == preg_match('@(nav_logo)@',$this->current_tag['attrs']['CLASS']) ) $skip = TRUE;
		if ( !$skip ) {
			$image = array(
				'image' => $this->current_tag['attrs']['SRC'],
				'fauxuuid' => $this->current_tag['attrs']['FAKEID'],
			 	'realsrc' => $this->current_tag['attrs']['FAUXSRC'],
			);
			$this->add_to_container_stack($image);
		} else {
			// $this->recursive_dump($this->current_tag,__LINE__);
		}
		$this->push_tagstack();
    return !$skip;
  }/*}}}*/

  /** Higher-level page parsers **/

  function parse_senators_fifteenth_congress(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/

    $dossier = new SenatorDossierModel();
    $url     = new UrlModel();

    // $dossier->dump_accessor_defs_to_syslog();

    $this->
      set_parent_url($urlmodel->get_url())->
      parse_html(
        $urlmodel->get_pagecontent(),
        $urlmodel->get_response_header()
      );

    ////////////////////////////////////////////////////////////////////
    //
    // Extract the image URLs on this page and use them to construct a 
    // minimal pager, by rewriting pairs of image + URL tags
    //
    ////////////////////////////////////////////////////////////////////

    $image_url = array();
    $filter_image_or_link = create_function('$a', 'return (array_key_exists("text",$a) || array_key_exists("image",$a) || ($a["tag"] == "A")) ? $a : NULL;'); 

    $containerset = $this->get_containers(); 

    foreach ( $containerset as $container_id => $container ) {/*{{{*/
      $this->syslog(__FUNCTION__,__LINE__,"(marker) Candidate structure {$container_id} - now at " . count($image_url));
      // $this->recursive_dump($container,"(marker) {$container_id}");
      if ( !("table" == $container['tagname']) ) continue;
      $children = array_filter(array_map($filter_image_or_link,$container['children']));
      // $this->recursive_dump($children,'(warning)');
      foreach( $children as $candidate_node ) {
        if (array_key_exists("image", $candidate_node)) {
          $image = array(
            "image" => $candidate_node['image'],
            "fauxuuid" => $candidate_node['fauxuuid'],
            "realsrc" => $candidate_node['realsrc'],
            "link" => array( 
              "url" => NULL,
              "urlhash" => NULL,
              "text" => NULL, 
            )
          );
          array_push($image_url, $image);
          continue;
        }
        if ( !(0 < count($image_url) ) ) continue;
        $image = array_pop($image_url);
        if ( is_null($image['link']['text']) && array_key_exists('text', $candidate_node) ) {
          $image['link']['text'] = str_replace(array('[BR]',"\n"),array(''," "),$candidate_node['text']);
          array_push($image_url, $image);
          continue;
        }
        if ( is_null($image['link']['url']) && array_key_exists('tag', $candidate_node) && ('a' == strtolower($candidate_node['tag'])) ) {
          $image['link']['url'] = $candidate_node['attrs']['HREF'];
          $image['link']['urlhash'] = UrlModel::get_url_hash($candidate_node['attrs']['HREF']);
          if ( array_key_exists('cdata', $candidate_node) ) {
            $test_name = trim(preg_replace('@[^A-Z."ñ ]@i','',join('',$candidate_node['cdata'])));
            if ( is_null($image['link']['text']) ) $image['link']['text'] = $test_name;
          }
          array_push($image_url, $image);
          continue;
        }
        array_push($image_url, $image);
      }
    }/*}}}*/

    $pagecontent = '';
    $senator_dossier = '';

    if ( 0 < count($image_url) ) { /*{{{*/
      foreach ( $image_url as $brick ) {/*{{{*/
        $bio_url = $brick['link']['url'];
        $dossier->set_id(NULL)->fetch(array(
          'bio_url' => $bio_url,
          'fullname' => $dossier->cleanup_senator_name($brick['link']['text'])
        ),'OR');
        $member_fullname      = NULL; 
        $member_uuid          = NULL; 
        $member_avatar_base64 = NULL; 
        $avatar_url           = NULL; 
        if ( !$dossier->in_database() ) {/*{{{*/
          $member_fullname = $dossier->cleanup_senator_name($brick['link']['text']);
          if (empty($member_fullname)) continue;
          $this->syslog(__FUNCTION__,__LINE__, "(marker) - - - - -- Treating {$member_fullname} {$bio_url}");
          $this->recursive_dump($brick,'(warning)');
          $member_uuid = sha1(mt_rand(10000,100000) . ' ' . $urlmodel->get_url() . $member_fullname);
          $avatar_url  = $brick['realsrc'];
          $url->fetch(UrlModel::get_url_hash($avatar_url),'urlhash');
          if ( $url->in_database() ) {
            $image_content_type   = $url->get_content_type();
            $image_content        = base64_encode($url->get_pagecontent());
            $member_avatar_base64 = "data:{$image_content_type};base64,{$image_content}";
          } else $member_avatar_base64 = NULL;
          $dossier->
            set_member_uuid($member_uuid)->
            set_fullname($member_fullname)->
            set_bio_url($bio_url)->
            set_create_time(time())->
            set_last_fetch(time())->
            set_avatar_url($avatar_url)->
            set_avatar_image($member_avatar_base64)->
            stow();
        }/*}}}*/
        else {/*{{{*/
          $member_fullname      = $dossier->get_fullname();
          $member_uuid          = $dossier->get_member_uuid();
          $member_avatar_base64 = $dossier->get_avatar_image();
          $avatar_url           = $dossier->get_avatar_url();
          $this->syslog(__FUNCTION__,__LINE__, "- Loaded {$member_fullname} {$bio_url}");
        }/*}}}*/

        $senator_dossier .= <<<EOH
<a href="{$bio_url}" class="human-element-dossier-trigger"><img class="representative-avatar" id="image-{$member_uuid}" src="{$member_avatar_base64}" alt="{$member_fullname}" /></a> 
EOH;
        if ( !(0 < strlen($member_avatar_base64)) ) $senator_dossier .= <<<EOH
<input type="hidden" class="representative-avatar-source" name="image-ref" id="imagesrc-{$member_uuid}" value="{$avatar_url}" />
EOH;
      }/*}}}*/

      $pagecontent = <<<EOH
<div class="senator-dossier-pan-bar"><div class="dossier-strip">{$senator_dossier}</div></div>
<div id="human-element-dossier-container" class="alternate-original half-container"></div>
<script type="text/javascript">
var total_image_width = 0;
var total_image_count = 0;
jQuery(document).ready(function(){
  initialize_dossier_triggers();
  jQuery("div[class=dossier-strip]").find("img[class*=representative-avatar]").each(function(){
    total_image_width += jQuery(this).outerWidth();
    total_image_count++;
  });
  if ( total_image_width < (total_image_count * 74) ) total_image_width = total_image_count * 74;
  jQuery("div[class=dossier-strip]").width(total_image_width).css({'width' : total_image_width+'px !important'});
  update_representatives_avatars();
});
</script>
EOH
      ;

    }/*}}}*/

  }/*}}}*/

  function parse_senators_sen_bio(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/

    ////////////////////////////////////////////////////////////////////
    $this->syslog( __FUNCTION__, __LINE__, "(marker) --------- SENATOR BIO PARSER Invoked for " . $urlmodel->get_url() );
    $membership  = new SenateCommitteeSenatorDossierJoin();
    $committee   = new SenateCommitteeModel();
    $dossier     = new SenatorDossierModel();
    $this->
      set_parent_url($urlmodel->get_url())->
      parse_html($urlmodel->get_pagecontent(),$urlmodel->get_response_header());
    $pagecontent = join('',$this->get_filtered_doc());
    ////////////////////////////////////////////////////////////////////

    // Find the placeholder, and extract the target URL for the image.
    // If the image (UrlModel) is cached locally, then replace the entire
    // placeholder with the base-64 encoded image.  Otherwise, replace
    // the placeholder with an empty string, and emit the markup below.
    $avatar_match = array();
    $dossier->fetch($urlmodel->get_url(), 'bio_url');
    $member_uuid = $dossier->get_member_uuid(); 

    $committee_membership_markup = ''; 

    if ( $membership->fetch_committees($dossier,TRUE) ) {/*{{{*/
      $committee_list = array();
      $committee_item = array();
      while ( $membership->recordfetch($committee_item) ) {
        $committee_list[$committee_item['senate_committee']] = array(
          'data' => $committee_item,
          'name' => NULL
        );
      }
      // TODO: Allow individual Model classes to maintain separate DB handles 
      $committee->debug_method = FALSE;
      foreach ( $committee_list as $committee_id => $c ) {
        $result = $committee->fetch($committee_id,'id');
        $committee_list[$committee_id] = $committee->get_committee_name();
        $committee_name_link = SenateCommitteeListParseUtility::get_committee_permalink_uri($committee_list[$committee_id]);
        $committee_membership_markup .= <<<EOH
<li><a class="legiscope-remote" href="/{$committee_name_link}">{$committee_list[$committee_id]}</a></li>
EOH;
      }
      $committee->debug_method = FALSE;
      $this->recursive_dump($committee_list,'(marker) - C List');
    }/*}}}*/

    if ( 0 < strlen($committee_membership_markup) ) {/*{{{*/
      $committee_membership_markup = <<<EOH
<hr class="{$member_uuid}"/>
<span class="section-title">Committee Memberships</span><br/>
<ul class="committee_membership">
{$committee_membership_markup}
</ul>
<hr/>

EOH;
    }/*}}}*/

    if (1 == preg_match('@{REPRESENTATIVE-AVATAR\((.*)\)}@i',$pagecontent,$avatar_match)) {/*{{{*/

      // $this->recursive_dump($avatar_match,'(marker) Avatar Placeholder');
      $image_markup    = array();
      preg_match('@<img(.*)fauxsrc="([^"]*)"([^>]*)>@mi',$pagecontent,$image_markup);
      // $this->recursive_dump($image_markup,'(marker) Avatar Markup');
      $bio_image_src   = new UrlModel($image_markup[2],TRUE);
      $placeholder     = $avatar_match[0];
      $avatar_uuid_url = explode(",",$avatar_match[1]);
      $avatar_url      = new UrlModel($avatar_uuid_url[1],TRUE);
      $fake_uuid       = $avatar_uuid_url[0];
      $member_fullname = $dossier->get_fullname();
      $image_base64enc = NULL;

      if ( $avatar_url->in_database() ) {
        // Fetch the image, stuff it in a base-64 encoded string, and pass
        // it along to the user - if it exists.  Otherwise.
        $image_contenttype = $avatar_url->get_content_type(TRUE);
        $this->recursive_dump($image_contenttype,'(marker) Image properties');
        $image_base64enc   = base64_encode($avatar_url->get_pagecontent());
        $image_base64enc   = is_array($image_contenttype) && array_key_exists('content-type', $image_contenttype)
          ? "data:{$image_contenttype['content-type']}:base64,{$image_base64enc}"
          : NULL
          ;
      } else if ( $bio_image_src->in_database() ) {
        $image_contenttype = $bio_image_src->get_content_type(TRUE);
        $this->recursive_dump($image_contenttype,'(marker) Image properties');
        $image_base64enc   = base64_encode($bio_image_src->get_pagecontent());
        $image_base64enc   = is_array($image_contenttype) && array_key_exists('content-type', $image_contenttype)
          ? "data:{$image_contenttype['content-type']}:base64,{$image_base64enc}"
          : NULL
          ;
      }

      // Cleanup placeholder, replace fake UUID with the Senator's real UUID.
      // Also make it ready for use in preg_replace
      $placeholder = preg_replace("@({$fake_uuid})@mi","{$member_uuid}",$placeholder);
      $placeholder = str_replace(array('(',')',),array('\(','\)',),$placeholder);

      // Ditto for markup
      $pagecontent = preg_replace('@('.$fake_uuid.')@im', $member_uuid,$pagecontent);

      // Replacement for the placeholder
      $replacement = is_null($image_base64enc)
        ? '' // Image hasn't yet been fetched.
        : $image_base64enc // Image available, and can be inserted in markup.
        ;
        
      // Remove fake content
      // Insert committee membership in place of any extant <HR> tag
      $pagecontent = preg_replace(
        array(
          "@({$placeholder})@im",
          '@(fauxsrc="(.*)")@im',
          '@(\<p (.*)class="h1_bold"([^>]*)\>)@i'),
        array(
          "{$replacement}",
          "alt=\"{$member_fullname}\" id=\"image-{$member_uuid}\" class=\"representative-avatar\"",
          "{$committee_membership_markup}<table>",
        ), 
        $pagecontent
      );

      // Test for correct replacement
      $avatar_match = array('0' => 'No Match');
      preg_match('@{REPRESENTATIVE-AVATAR\((.*)\)}@i',$pagecontent,$avatar_match);
      $this->recursive_dump($avatar_match,'(marker) After subst');

      $committee_list_match = array('0' => 'No Match');
      if (!(1 == preg_match('@\<hr class="'.$member_uuid.'"/\>@i',$pagecontent,$committee_list_match))) {
        $pagecontent = $committee_membership_markup . $pagecontent;
      }


      // Only emit the image scraper trigger if the image was empty
      $this->syslog( __FUNCTION__, __LINE__, "(marker) Avatar URL is {$avatar_url}");
      if ( is_null($image_base64enc) ) $pagecontent .= <<<EOH

<input type="hidden" class="no-replace" id="imagesrc-{$member_uuid}" value="{$avatar_url}" />
<script type="text/javascript">
jQuery(document).ready(function(){
setTimeout((function(){
  update_representatives_avatars();
}),50);
});
</script>
EOH;
    }/*}}}*/
    else {
      $this->syslog( __FUNCTION__, __LINE__, "(marker) No placeholder found!" );
    }
    $pagecontent = str_replace('[BR]','<br/>', $pagecontent);
    $parser->json_reply = array('retainoriginal' => TRUE);

  }/*}}}*/

}
