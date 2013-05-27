<?php

/*
 * Class UrlClusterModel
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class UrlClusterModel extends DatabaseUtility {
  
	var $clusterid_vc40uniq = 'sha1';
	var $position_int11 = NULL;
	var $host_vc40 = 'md5';
	var $parent_page_vc40 = 'md5'; // Need just 32 chars

  function __construct() {
    parent::__construct();
  }

	function exists(UrlModel & $parent_page, $clusterid) {/*{{{*/
		// Return TRUE if the given cluster exists,
		// without regard to incident edges 
		return 1 == $this->count(array('AND' => array(
			'clusterid' => $clusterid,
			// 'parent_page' => $parent_page->get_urlhash(),
			'host' => UrlModel::get_url_hash($parent_page->get_url(),PHP_URL_HOST),
			// 'parent_page_id' => $parent_page->get_id(),
		)));
	}/*}}}*/

	function fetch(UrlModel & $parent_page, $clusterid) {/*{{{*/
		if ( $this->where(array('AND' => array(
			//'parent_page' => $parent_page->get_urlhash(),
			'host' => UrlModel::get_url_hash($parent_page->get_url(),PHP_URL_HOST),
			'clusterid' => $clusterid,
		)))->
		order(array('position' => 'ASC','id' => 'ASC'))->
		recordfetch_setup()) {
			$result = array();
			if ( !$this->recordfetch($result,TRUE) ) return FALSE;
			return $result['id'];
		}
		return FALSE;
	}/*}}}*/

	function fetch_clusters(UrlModel & $parent_page,$regularize = FALSE) {/*{{{*/
    $debug_method = TRUE;
		$hosthash = UrlModel::get_url_hash($parent_page->get_url(),PHP_URL_HOST);
		if ( $debug_method ) $this->syslog(__FUNCTION__,__LINE__, "(marker) -- - -- Fetching clusters for {$parent_page} [{$hosthash}]");
		$records = array();
		if ( $this->where(array('AND' => array(
			'host' => $hosthash,
			// 'parent_page' => $parent_page->get_urlhash(),
		)))->
		order(array('position' => 'ASC','id' => 'ASC'))->
		recordfetch_setup()) {/*{{{*/
			$result = array();
			while ( $this->recordfetch($result) ) {
				$clusterid = $result['clusterid'];
				unset($result['clusterid']);
				$records[$clusterid] = array_merge(
					$result,
					$regularize ? array('position_new' => count($records)) : array()
				);
			}
		}/*}}}*/
		// $this->recursive_dump($records, "(marker) Cluster records - - -");
		if ( ( 0 < count($records) ) && $regularize ) {/*{{{*/
			foreach ( $records as $clusterid => $record ) {
				if ( $record['position_new'] != intval($record['position']) ) {
					$id = array_element($record,'id');
					if ( is_null($id) || !(0 < intval($id)) ) continue;
					$this->order(array())->where(array('id' => $id))->select();
					if ( $this->in_database() ) $this->
						set_position($record['position_new'])->
            stow();
					$records[$clusterid]['position'] = $record['position_new'];
					unset($records[$clusterid]['position_new']);
				}
			}
		}/*}}}*/
		return $records;
	}/*}}}*/

	function reposition(& $page, $clusterid, $increment, $absolute = FALSE) {/*{{{*/
		// Retrieve all link clusters on this page, regularize positions
		$all_clusters = $this->fetch_clusters($page,TRUE);
		// Lookup table for hash given position
		$position_map = array_combine(
			array_map(create_function('$a', 'return $a["position"];'), $all_clusters),
			array_keys($all_clusters)
		);
		// Modify the position of this cluster, the cluster (if any)
		// which position in the sequence this cluster replaces,
		// as well as all intervening clusters.
		if ( array_key_exists($clusterid, $all_clusters) ) {
			// Swap positions with another cluster
			$old_position = intval($all_clusters[$clusterid]['position']);
			$new_position = $absolute ? $increment : $old_position + intval($increment);
			if ( $new_position < 0 ) $new_position = 0;
			if ( $new_position >= count($position_map) ) $new_position = count($position_map) - 1;
			$nudged_cluster = $all_clusters[$position_map[$new_position]];
			$range_min = min($old_position, $new_position);
			$range_max = max($old_position, $new_position);
			$p = $range_min;
			if ( $range_max == ($range_min + 1) ) {
				// Simply swap
				$this->fetch($page, $position_map[$range_min]);
				$this->set_position($range_max)->stow();
				$this->fetch($page, $position_map[$range_max]);
				$this->set_position($range_min)->stow();
			} else do {
				$nudged_cluster = $all_clusters[$position_map[$p]];
				$this->syslog(__FUNCTION__,__LINE__,
					"(marker) Nudging @{$p} {$range_min}:{$range_max} ({$new_position} <- {$old_position}) {$position_map[$p]}: {$nudged_cluster['position']}"
				);
				$this->recursive_dump($nudged_cluster,"(marker) Nudged cluster");
			} while ( $p++ < $range_max );
		}	else {
			// Insert into zeroth position (top of list)
			// if this cluster wasn't previously recorded
		}
	}/*}}}*/

	function & set_clusterid($v) { $this->clusterid_vc40uniq = $v; return $this; }
	function get_clusterid($v = NULL) { if (!is_null($v)) $this->set_clusterid($v); return $this->clusterid_vc40uniq; }

	function & set_parent_page($v) { $this->parent_page_vc40 = $v; return $this; }
	function get_parent_page($v = NULL) { if (!is_null($v)) $this->set_parent_page($v); return $this->parent_page_vc40; }

	function & set_position($v) { $this->position_int11 = $v; return $this; }
	function get_position($v = NULL) { if (!is_null($v)) $this->set_position($v); return $this->position_int11; }

	function & set_host($v) { $this->host_vc40 = $v; return $this; }
	function get_host($v = NULL) { if (!is_null($v)) $this->set_host($v); return $this->host_vc40; }

	function & set_parent_page_id($v) { $this->parent_page_id_int11 = $v; return $this; }
	function get_parent_page_id($v = NULL) { if (!is_null($v)) $this->set_parent_page_id($v); return $this->parent_page_id_int11; }
}

