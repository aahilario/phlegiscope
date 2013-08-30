<?php

/*
 * Class SenatorBioParseUtility
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class SenatorBioParseUtility extends SenateCommonParseUtility {
  
  var $senator_bricks   = array();
  var $senator_entry    = NULL;

  // 16th Congress:  Closing a table cell (TD) processes a Senator image and link 

  function __construct() {
    parent::__construct();
  }

  function ru_div_open(& $parser, & $attrs, $tag) {/*{{{*/
    $this->push_container_def($tag, $attrs);
    return TRUE;
  }/*}}}*/
  function ru_div_close(& $parser, $tag) {/*{{{*/
    $this->current_tag();
    if (1 == preg_match('@(' . join('|',array(
      'xhtml-nav',
      'nav-top',
    )) . ')@', array_element($this->current_tag['attrs'],'CLASS'))) {
      $this->pop_container_stack();
      return FALSE;
    }
    $content = $this->stack_to_containers(TRUE);
    return TRUE;
  }/*}}}*/

  function ru_br_close(& $parser, $tag) {/*{{{*/
    return FALSE;
  }/*}}}*/

  function ru_td_open(& $parser, & $attrs, $tag) {/*{{{*/
    $this->add_cdata_property();
    $this->push_container_def($tag, $attrs);
    if ( !is_null($this->senator_entry) && ( 1 == preg_match('@senators/images/@i',$this->senator_entry['realsrc']) ) ) {
      if ( $this->debug_tags ) {/*{{{*/
        $this->syslog(__FUNCTION__,__LINE__,"(marker) {$tag} +++++++++-- UNPROCESSED --+++++++" );
        $this->recursive_dump($this->senator_entry,"(marker) {$tag}");
      }/*}}}*/
      $this->senator_entry['link']['text'] = $this->senator_entry['alt'];
      $this->senator_bricks[] = $this->senator_entry;
    }
    $this->senator_entry = NULL;
    return TRUE;
  }/*}}}*/
  function ru_td_cdata(& $parser, & $cdata) {/*{{{*/
    return $this->append_cdata($cdata);
  }/*}}}*/
  function ru_td_close(& $parser, $tag) {/*{{{*/

    $debug_method = FALSE;
    $this->pop_tagstack();
    $this->embed_nesting_placeholders();
    $this->push_tagstack();
    $this->current_tag();
    $content = $this->stack_to_containers();
    $paragraph = join('', str_replace("\n"," ",$this->current_tag['cdata']));

    if ( 0 < count(nonempty_array_element($content,'children',array())) ) {
      // $content captures common Senator members' records
      $this->syslog(__FUNCTION__,__LINE__,"(marker) {$tag} ---------------------" );
      $content = nonempty_array_element($content,'children');
      $this->recursive_dump($content,"(marker) {$tag}");

      $td = array(
        'cell' => $paragraph,
        'seq' => $this->current_tag['attrs']['seq'],
      ); 
      $this->add_to_container_stack($td);
      if ( $debug_method ) {
        $this->recursive_dump($this->current_tag,"(marker) -- -");
        $this->syslog(__FUNCTION__,__LINE__,"(marker) ---------");
        $this->recursive_dump($content,"(marker) - --");
      }
    }

    return TRUE;
  }/*}}}*/

  function ru_span_open(& $parser, & $attrs, $tag) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'] = array();
    $this->push_tagstack();
    return TRUE;
  }  /*}}}*/
  function ru_span_cdata(& $parser, & $cdata) {/*{{{*/
    $this->pop_tagstack();
    $this->current_tag['cdata'][] = $cdata;
    $this->push_tagstack();
    return TRUE;
  }/*}}}*/
  function ru_span_close(& $parser, $tag) {/*{{{*/
    $this->current_tag();
    $paragraph = array(
      'text' => join('', str_replace("\n"," ",$this->current_tag['cdata'])),
      'seq'  => $this->current_tag['attrs']['seq'],
    );
    if ( !is_null($this->senator_entry) ) {
      if (!(0 < strlen($this->senator_entry['link']['text'])))
      $this->senator_entry['link']['text'] = $paragraph['text'];
    }
    $this->add_to_container_stack($paragraph);
    return TRUE;
  }/*}}}*/

  function ru_a_open(& $parser, & $attrs, $tag) {/*{{{*/
    $this->add_cdata_property();
    $this->pop_tagstack();
    $this->update_current_tag_url('HREF');
    $this->push_tagstack();
    $this->push_container_def($tag, $this->current_tag['attrs']);
    return TRUE;
  }/*}}}*/
  function ru_a_cdata(& $parser, & $cdata) {/*{{{*/
    return $this->append_cdata($cdata);
  }/*}}}*/
  function ru_a_close(& $parser, $tag) {/*{{{*/
    $this->pop_tagstack();
    $this->embed_nesting_placeholders();
    $this->current_tag['cdata'] = trim(join('', $this->current_tag['cdata']));
    $this->push_tagstack();
    $content = $this->pop_container_stack();
    // $content captures common Senator members' records
    if ( 0 < count(nonempty_array_element($content,'children',array())) ) {/*{{{*/
      $content = array_merge(
        $content['children'],
        array($content['attrs'])
      );
      $this->reorder_with_sequence_tags($content);
      $this->senator_entry = array();
      while ( 0 < count($content) ) {
        $this->senator_entry = array_merge(
          $this->senator_entry,
          array_shift($content)
        );
      }
      $this->senator_entry['link']['text']    = $this->senator_entry['text'];
      $this->senator_entry['link']['url']     = $this->senator_entry['HREF'];
      $this->senator_entry['link']['urlhash'] = UrlModel::get_url_hash($this->senator_entry['link']['text']); 
      unset($this->senator_entry['HREF']);
      unset($this->senator_entry['text']);
      $this->senator_entry = array_filter($this->senator_entry);
      if ( 1 == preg_match('@senators/images/@i',$this->senator_entry['realsrc']) ) {
        $this->senator_bricks[] = $this->senator_entry;
        if ( $this->debug_tags ) {/*{{{*/
          $this->syslog(__FUNCTION__,__LINE__,"(marker) {$tag} ---------------------" );
          $this->recursive_dump($this->senator_entry,"(marker) {$tag}");
        }/*}}}*/
      }

    }/*}}}*/
    $this->senator_entry = NULL; 
    return TRUE;
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
    $this->pop_tagstack();
    $faux_mem_uuid = sha1(mt_rand(10000,100000) . ' ' . $this->current_tag['attrs']['SRC']);
    $this->current_tag['attrs']['FAUXSRC'] = $this->current_tag['attrs']['SRC'];
    $this->current_tag['attrs']['SRC']     = '{REPRESENTATIVE-AVATAR('.$faux_mem_uuid.','.$this->current_tag['attrs']['SRC'].')}';
    $this->current_tag['attrs']['ID']      = "image-{$faux_mem_uuid}";
    $this->current_tag['attrs']['FAKEID']  = "{$faux_mem_uuid}";
    if ( !array_key_exists('CLASS', $this->current_tag['attrs']) ) {
      $this->current_tag['attrs']['CLASS'] = 'legiscope-remote representative-avatar representative-avatar-missing';
    } else {
      $this->current_tag['attrs']['CLASS'] .= ' legiscope-remote';
    }
    $image = array(
      'image'    => $this->current_tag['attrs']['SRC'],
      'fauxuuid' => $this->current_tag['attrs']['FAKEID'],
      'realsrc'  => $this->current_tag['attrs']['FAUXSRC'],
      'class'    => $this->current_tag['attrs']['CLASS'],
      'alt'      => $this->current_tag['attrs']['ALT'],
      'width'    => $this->current_tag['attrs']['WIDTH'],
      'height'   => $this->current_tag['attrs']['HEIGHT'],
      "link" => array( 
        "url"     => NULL,
        "urlhash" => NULL,
        "text"    => NULL,
      ),
      'seq' => $this->current_tag['attrs']['seq'],
    );
    $this->senator_entry = $image;
    $this->add_to_container_stack($image);
    $this->push_tagstack();
    return TRUE;
  }/*}}}*/

  /** Higher-level page parsers **/

  function parse_senators_fifteenth_congress(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/

    $debug_method = FALSE;

    $url = new UrlModel();

    $this->skip_bio_override = FALSE;
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

    $pagecontent = '';
    $senator_dossier = '';

    $dossier = new SenatorDossierModel();

    if ( $this->debug_tags ) {/*{{{*/
      $this->syslog(__FUNCTION__,__LINE__,"(marker) Parsed Senator information 'bricks'");
      $this->recursive_dump($this->senator_bricks,"(marker) --");
    }/*}}}*/

    while ( 0 < count($this->senator_bricks) ) {/*{{{*/

      $brick                 = array_shift($this->senator_bricks);
      $alt_bio_name          = array_element($brick,'bio');
      $link                  = array_element($brick,'link');
      $bio_url               = array_element($link,'url');
      $rawname               = nonempty_array_element($link,'text',array_element($alt_bio_name,0));
      $rawname               = iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $rawname);
      $text                  = $dossier->cleanup_senator_name($rawname);
      $brick['link']['text'] = $text;
      $senator_nameregex     = LegislationCommonParseUtility::legislator_name_regex($text);

      $sql_condition = array_filter(array(
        'bio_url' => $bio_url,
        'fullname' => $senator_nameregex,
      ));

      if ( array_key_exists('fullname',$sql_condition) ) $sql_condition['fullname'] = "REGEXP '({$sql_condition['fullname']})'";
      else {
        $this->syslog(__FUNCTION__,__LINE__,"(marker) -- Warning: No senator name parsed from '{$rawname}', URL = {$bio_url}");
        continue;
      }

      // Data elements that can be derived from parsing the target page
      $dossier_delta = array_filter(array(
        'bio_url' => $bio_url,
        'fullname' => $text,
        'avatar_url' => nonempty_array_element($brick,'realsrc'),
      ));

      if ( $debug_method ) {
        $this->syslog(__FUNCTION__,__LINE__,"(marker) --- --- --- --- --- --- [{$senator_nameregex}] <- '{$rawname}'");
        $this->recursive_dump($brick,"(marker) --");
        $this->recursive_dump($alt_bio_name,"(marker) ++");
        $this->recursive_dump($sql_condition,"(marker) ss");
        $this->recursive_dump($dossier_delta,"(marker) dd");
      }

      $dossier->set_id(NULL);
      $dossier_record = array();

      $member_fullname      = NULL; 
      $member_uuid          = NULL; 
      $member_avatar_base64 = NULL; 
      $avatar_url           = NULL; 

      if ( 0 < count($sql_condition) ) $dossier_record = $dossier->fetch($sql_condition,'OR');

      $difference = NULL;

      if ( is_array($dossier_record) && (0 < count($dossier_record)) ) {/*{{{*/
        ksort($dossier_record);
        ksort($dossier_delta);
        $intersection = array_intersect_key($dossier_record,$dossier_delta); // From  
        ksort($intersection);
        $difference   = array_diff_assoc($intersection,$dossier_delta);
        if ( 0 < count($difference) ) {
          $this->recursive_dump($dossier_record, "(marker) --------");
          $this->recursive_dump($intersection  , "(marker) uu DB uu");
          $this->recursive_dump($difference    , "(marker) nnnnnnnn");
        }
      }/*}}}*/

      if ( !$dossier->in_database() ) {/*{{{*/
        if (empty($rawname)) continue;
        $member_fullname = $rawname;
        $this->syslog(__FUNCTION__,__LINE__, "(marker) - - - - -- Treating {$rawname} {$bio_url}");
        $this->recursive_dump($sql_condition,'(warning) -- Cond');
        $this->recursive_dump($brick,'(warning) -- Data');
        $member_uuid = sha1(mt_rand(10000,100000) . ' ' . $urlmodel->get_url() . $member_fullname);
        $avatar_url  = $brick['realsrc'];
        $url->fetch(UrlModel::get_url_hash($avatar_url),'urlhash');
        if ( $url->in_database() ) {
          $image_content_type   = $url->get_content_type();
          $image_content        = base64_encode($url->get_pagecontent());
          $member_avatar_base64 = "data:{$image_content_type};base64,{$image_content}";
        } else $member_avatar_base64 = NULL;

        $member_id = $dossier->
          set_member_uuid($member_uuid)->
          set_fullname($rawname)->
          set_bio_url($bio_url)->
          set_create_time(time())->
          set_last_fetch(time())->
          set_avatar_url($avatar_url)->
          set_avatar_image($member_avatar_base64)->
          stow();

        if ( 0 < intval($member_id) ) $dossier->fetch($member_id);

        $this->syslog(__FUNCTION__,__LINE__, "(marker) - - - - -- Stored #{$member_id} {$member_fullname} {$bio_url} UUID " . $dossier->get_member_uuid());

      }/*}}}*/
      else {/*{{{*/
        if ( !is_null($difference) && is_array($difference) && (0 < count($difference)) ) {
          $member_id = $dossier->
            set_contents_from_array($difference)->
            fields(array_keys($difference))->
            stow();
          $this->syslog(__FUNCTION__,__LINE__, "(marker) ----- ++ Updated {$member_id}");
        }
        $member_fullname      = $dossier->get_fullname();
        $member_uuid          = $dossier->get_member_uuid();
        $member_avatar_base64 = $dossier->get_avatar_image();
        $avatar_url           = $dossier->get_avatar_url();
        $this->syslog(__FUNCTION__,__LINE__, "(marker) - Loaded {$member_fullname} {$bio_url} UUID {$member_uuid}");
      }/*}}}*/

      $image_alt_text = preg_replace('@[^A-Z ñ,.]@i', '', $member_fullname);
      $senator_dossier_meta = (0 < strlen($member_avatar_base64)) ? NULL : <<<EOH
<input type="hidden" class="representative-avatar-source" name="image-ref" id="imagesrc-{$member_uuid}" value="{$avatar_url}" />
EOH;
      if (0 < strlen($bio_url))
        $senator_dossier .= <<<EOH
<a href="{$bio_url}" class="human-element-dossier-trigger"><img class="representative-avatar" id="image-{$member_uuid}" src="{$member_avatar_base64}" alt="{$image_alt_text}" title="{$rawname}" /></a> 
{$senator_dossier_meta}

EOH;
      else
        $senator_dossier .= <<<EOH
<img class="representative-avatar" id="image-{$member_uuid}" src="{$member_avatar_base64}" alt="{$image_alt_text}" title="{$rawname}" />
{$senator_dossier_meta}

EOH;
    }/*}}}*/

    $pagecontent = <<<EOH
<div class="senator-dossier-pan-bar"><div class="dossier-strip">{$senator_dossier}</div></div>
EOH;
    $specific_dossier = <<<EOH
<div id="human-element-dossier-container" class="half-container"></div>
EOH;
    $pagecontent .= <<<EOH
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
  setTimeout((function(){ update_representatives_avatars(); }),10);
});
</script>
EOH
    ;
    $parser->json_reply = array(
      'retainoriginal' => TRUE,
    );

  }/*}}}*/

  function parse_senators_sen_bio(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/

    $debug_method = TRUE;
    ////////////////////////////////////////////////////////////////////
    $this->syslog( __FUNCTION__, __LINE__, "(marker) --------- SENATOR BIO PARSER Invoked for " . $urlmodel->get_url() );
    $membership  = new SenateCommitteeSenatorDossierJoin();
    $committee   = new SenateCommitteeModel();
    $dossier     = new SenatorDossierModel();
    $this->
      set_parent_url($urlmodel->get_url())->
      parse_html(
        $urlmodel->get_pagecontent(),
        $urlmodel->get_response_header()
      );

    $main_text          = $this->get_containers('children[tagname=td]',0);
    $pagecontent        = '';
    $have_avatar        = FALSE;
    $fullname_candidate = NULL;

    if ( !(0 < count($main_text) ) ) {/*{{{*/
      $containerset = $this->get_containers();
      $pagecontent = str_replace('[BR]','<br/>',join('',$this->get_filtered_doc()));
      $parser->json_reply = array(
        'retainoriginal' => TRUE,
        'subcontent' => $pagecontent, 
      );
      $pagecontent = NULL;
      return;
    }/*}}}*/

    // Revilla bio hack
    $stack_top = array_element($this->senator_bricks,0);
    if ( is_array( $biographic_sketch = array_element($stack_top,'bio') ) ) {
      $main_text = array_values($main_text);
      unset($stack_top['bio']);
      // Add any Senator avatar data captured from input stream.
      $main_text[] = $stack_top;
      // Transfer contents of bio to containers
      while ( 0 < count($biographic_sketch) ) {
        $line = array_shift($biographic_sketch);
        $line = array('text' => $line);
        $main_text[] = $line;
      }
      if ( $debug_method ) $this->recursive_dump($stack_top, "(marker) RH --");
    }

    if ( $debug_method ) $this->recursive_dump($main_text, "(marker) MT --");

    while ( 0 < count($main_text) ) {/*{{{*/
      // Consume containers found
      $entry = array_shift($main_text);
      if ( array_key_exists('image',$entry) ) {
        if ( $have_avatar ) break;
        $have_avatar = TRUE;
        $pagecontent .= <<<EOH

<img id="image-{$entry['fauxuuid']}" class="{$entry['class']}" src="{$entry['image']}" fakeid="{$entry['fauxuuid']}" fauxsrc="{$entry['realsrc']}" width="151" vspace="3" hspace="10" height="200" />

EOH;
        continue;
      }
      if ( array_key_exists('url', $entry) ) {
        continue;
      }
      if ( array_key_exists('text', $entry) ) {
        $line = $this->reverse_iconv($entry['text']);
        if ( is_null($fullname_candidate) ) {
          $fullname_candidate = preg_replace('@^senator([ ]*)@i','', $line);
        }
        if ( empty($line) ) $line = "[BR]";
        $pagecontent .= <<<EOH
<p>{$line}</p>

EOH;
      }
    }/*}}}*/

    ////////////////////////////////////////////////////////////////////
    // Find the placeholder, and extract the target URL for the image.
    // If the image (UrlModel) is cached locally, then replace the entire
    // placeholder with the base-64 encoded image.  Otherwise, replace
    // the placeholder with an empty string, and emit the markup below.
    $avatar_match = array();
    $fullname_regex = LegislationCommonParseUtility::legislator_name_regex($fullname_candidate);
    $dossier_match_conds = array_filter(array(
      'bio_url' => $urlmodel->get_url(),
      'fullname' => is_null($fullname_candidate) ? NULL : "REGEXP '({$fullname_regex})'",
      // '{committee}.`target_congress`' => C('LEGISCOPE_DEFAULT_CONGRESS'),
    ));

    if ( $debug_method )
    $this->recursive_dump($dossier_match_conds,"(marker) ---");

    // FIXME: WARNING: Full joins cause mysqli to fail.
    $dossier->debug_final_sql = TRUE;
    $dossier->join(array('committee'))->where(array('AND' => $dossier_match_conds))->recordfetch_setup();
    $dossier->debug_final_sql = FALSE;

    $dossier_entry  = NULL;
    $matches_found  = 0;
    $committee_list = array();
    $committee_item = array();
    $committee_membership_markup = ''; 

    while ( $dossier->recordfetch($dossier_entry,TRUE) ) {/*{{{*/
      if ( is_array($committee_entry = nonempty_array_element($dossier_entry,'committee')) ) {/*{{{*/
        // Fetch Congress-specific committee links
        $join = nonempty_array_element($committee_entry, 'join');
        $committee_congress_tag = nonempty_array_element($join,'target_congress');
        $committee_data = $committee_entry['data'];
        if ( is_null($committee_data) ) {
          $this->syslog(__FUNCTION__,__LINE__,"(warning) Invalid or missing commmittee record for join #{$join['id']}");
          continue;
        }
        $committee_id = nonempty_array_element($committee_data,'id');
        $committee_name = nonempty_array_element($committee_data,'committee_name');
        $committee_list[$committee_id] = $committee_name;
        $committee_name_link = SenateCommitteeListParseUtility::get_committee_permalink_uri($committee_name);
        $properties = array('legiscope-remote',"congress-{$committee_congress_tag}");
        $container_properties = array();
        if ( intval($committee_congress_tag) != C('LEGISCOPE_DEFAULT_CONGRESS') ) {
          $container_properties[] = 'hidden';
        }
        $properties = join(' ',$properties);
        $container_properties = join(' ',$container_properties);
        $committee_membership_markup .= <<<EOH
<li class="{$container_properties}"><a class="{$properties}" href="/{$committee_name_link}">{$committee_name}</a> ({$committee_congress_tag}th Congress)</li>
EOH;
      }/*}}}*/
      $matches_found++;
      $this->recursive_dump($dossier_entry,"(marker) --- ---");
      if ( $matches_found > 100 ) break;
    }/*}}}*/
    $this->syslog(__FUNCTION__,__LINE__,"(marker) -- Matches found: {$matches_found}.  Name regex {$fullname_regex} <- {$fullname_candidate}");

    if ( !$dossier->in_database() ) {/*{{{*/
      $this->syslog(__FUNCTION__,__LINE__,"(marker) -- UNMATCHED RECORD.  Name regex {$fullname_regex} <- {$fullname_candidate}");
      $pagecontent = NULL;
      
      $parser->json_reply = array(
        'retainoriginal' => TRUE,
        'subcontent' => join('',$this->get_filtered_doc())
      );
      return;
    }/*}}}*/

    $member_uuid = $dossier->get_member_uuid(); 

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

      if ( $debug_method ) {
        $this->syslog(__FUNCTION__,__LINE__,"(marker) Senator UUID: " . $dossier->get_member_uuid());
        $this->recursive_dump($avatar_match,'(marker) Avatar Placeholder');
      }
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
      $image_alt_text = preg_replace('@[^A-Z ñ,.]@i', '', $member_fullname);
      $pagecontent = preg_replace(
        array(
          "@({$placeholder})@im",
          '@(fauxsrc="(.*)")@im',
          '@(\<p (.*)class="h1_bold"([^>]*)\>)@i'),
        array(
          "{$replacement}",
          "alt=\"{$image_alt_text}\" id=\"image-{$member_uuid}\" class=\"representative-avatar\"",
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
    $parser->json_reply['subcontent'] = str_replace('[BR]','<br/>', $pagecontent);
    $pagecontent = NULL;

  }/*}}}*/

}
