<?php

/*
 * Interface DatabasePlugin
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

interface DatabasePlugin {
  public function connect($dbhost, $dbuser, $dbpass, $dbname);
  public function query($sql = NULL);
  public function begin($lock = TRUE);
  public function commit();
	public function last_query_rows();
}
