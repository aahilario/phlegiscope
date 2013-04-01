<?php

/*
 * Class RepresentativeDossierModel
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class RepresentativeDossierModel extends DatabaseUtility {
  
  var $fullname_vc128uniq = NULL;
  var $bio_url_vc1024 = NULL;
	var $create_time_utx = NULL;
  var $last_fetch_utx = NULL;
  var $contact_json_vc2048 = NULL;
  var $member_uuid_vc64 = NULL; // Basically a hash of the URL and full name
  var $avatar_image_blob = NULL; // Avatar image base64-encoded
  var $avatar_url_vc1024 = NULL;

  function __construct() {
    parent::__construct();
  }

  function & set_fullname($v) { $this->fullname_vc128uniq = $v; return $this; }
  function get_fullname($v = NULL) { if (!is_null($v)) $this->set_fullname($v); return $this->fullname_vc128uniq; }

  function & set_bio_url($v) { $this->bio_url_vc1024 = $v; return $this; }
  function get_bio_url($v = NULL) { if (!is_null($v)) $this->set_bio_url($v); return $this->bio_url_vc1024; }

  function & set_create_time($v) { $this->create_time_utx = $v; return $this; }
  function get_create_time($v = NULL) { if (!is_null($v)) $this->set_create_time($v); return $this->create_time_utx; }

  function & set_last_fetch($v) { $this->last_fetch_utx = $v; return $this; }
  function get_last_fetch($v = NULL) { if (!is_null($v)) $this->set_last_fetch($v); return $this->last_fetch_utx; }

  function & set_avatar_url($v) { $this->avatar_url_vc1024 = $v; return $this; }
  function get_avatar_url($v = NULL) { if (!is_null($v)) $this->set_avatar_url($v); return $this->avatar_url_vc1024; }

  function & set_member_uuid($v) { $this->member_uuid_vc64 = $v; return $this; }
  function get_member_uuid($v = NULL) { if (!is_null($v)) $this->set_member_uuid($v); return $this->member_uuid_vc64; }

  function & set_contact_json($v) { 
    $this->contact_json_vc2048 = is_array($v)
      ? json_encode($v)
      : $v
      ;
    return $this;
  }
  function get_contact_json($v = NULL) { 
    if (!is_null($v)) $this->set_contact_json($v);
    return json_decode($this->contact_json_vc2048,TRUE);
  }

  function & set_avatar_image($v) { $this->avatar_image_blob = $v; return $this; }
  function get_avatar_image($v = NULL) { if (!is_null($v)) $this->set_avatar_image($v); return $this->avatar_image_blob; }

	function replace_legislator_names_hotlinks($s) {
		$name_regex = '@([^,]{1,}),[ ]*(([^ ]*) ){1,}([ ](JR\.|II|III|IV|V|[^ ]*)*)*([ ]*)*(([^ ]*) ){1,}([A-Z]\.)*(.*)@i';
		// $name_regex = '@([^,]{1,}),[ ]*(([^ ]*)[ ]?){1,}(JR\.|II|III|IV|V)*([ ]*)*(([^ ]*) ){1,}([A-Z]\.)*(.*)@i';
		$matches = array();
		$map = array(
			1 => 'surname',
			2 => 'firstname',
			7 => 'firstname-alt',
			9 => 'middle-init',
			10 => 'middle',
		);
	  if ( 1 == preg_match_all($name_regex, $s, $matches) ) {
			$matches = array_filter(array_map(create_function('$a','return count($a) == 0 ? NULL : $a[0];'),$matches));
			$known = array_intersect_key($matches, $map);
			$keys  = array_intersect_key($map, $matches);
			$known = array_combine($keys, $known);
			unset($matches[0]);
			$s = join(' ', $matches);
			// $this->syslog( __FUNCTION__, 'FORCE', "- Matches for name '{$s}'" );
			// $this->recursive_dump($known,0,'FORCE');
			// $this->syslog( __FUNCTION__, 'FORCE', "- Source '{$s}'" );
			// $this->recursive_dump($matches,0,'FORCE');
      $this->
        where(array('AND' => array(
          'fullname' => "REGEXP '({$known['surname']})'",
          'fullname' => "REGEXP '({$known['firstname']})'",
        )))->recordfetch_setup();
      $known = array();
      $template = <<<EOH
<a class="legiscope-remote legislator-name-hotlink" href="{bio_url}" id="{urlhash}">{fullname}</a>
EOH;
      $s = NULL;
      while ( $this->recordfetch($matches) ) {
        // $this->syslog( __FUNCTION__, 'FORCE', "- Match #{$matches['id']} {$matches['fullname']}" );
        // $this->recursive_dump($matches,0,'FORCE');
        $copy = $template;
        foreach ( $matches as $k => $v ) {
          $copy = str_replace("{{$k}}", "{$v}", $copy);
        }
        if ( is_null($s) ) {
          $s = $copy;
          $this->syslog( __FUNCTION__, 'FORCE', "- Match #{$matches['id']} {$matches['fullname']} -> {$s}" );
        }
      }
		}
		return $s;
	}

  function stow() {
    return parent::stow();
  }

}

