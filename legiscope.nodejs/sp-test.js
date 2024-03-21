//if ( process.env.CHILD !== undefined ) {
//  process.on('message', function(o) {
//    console.log('Parent sent', o);
//    o.child = process.pid;
//    o.TARGETURL = process.env.TARGETURL;
//    process.send(o);
//  });
//  console.log("Fooie");
//}
//
//const v8 = require("v8");
const { browser, $, $$, expect } = require("@wdio/globals");
const { readFileSync, writeFile, writeFileSync, mkdirSync, existsSync, statSync } = require('node:fs');
const assert = require("assert");
const System = require("systemjs");
const cheerio = require("cheerio");
const { createHash } = require("node:crypto");
const url = require("node:url");
const http = require("node:http");
const https = require("node:https");
const { argv, pid } = require("node:process");
const { spawnSync } = require("child_process");
const controller = new AbortController();
const { signal } = controller;

const targetUrl     = process.env.TARGETURL || 'https://congress.gov.ph/';
const sampleCount   = 0;

const loggedTags = new Map([
  [ "SCRIPT", 0 ], // This allows inlined scripts to be dumped
  [ "LINK", 0 ],
  [ "A"   , 0 ]
]);

const pad = " ";

let targetFile        = '';
let visitFile         = '';
let depthPlusOneFile  = ''; 
let permittedHosts    = '';
let assetCatalogFile  = '';
let siteConfigFile    = '';
let pageConfigFile    = '';
let inlineScriptsFile = '';
let pageCookieFile    = '';
let targetDir         = '';

let loadedUrl;
let parsedUrl;
let siteParseSettings;
let pageParseSettings;
let inlineScripts     = new Array;
let pageUrls          = new Map;
let pageHosts         = new Map;
let cdpRRdata         = new Map;
let extractedUrls;
let cookies;
let root_request_header;

function fetch_reset()
{
  // Invoke to reset state before fetching a new URL
  targetFile          = '';
  visitFile           = ''; // The site-global visited URLs file is set once per domain host
  depthPlusOneFile    = '';  
  assetCatalogFile    = '';
  pageConfigFile      = ''; // Unique page parser configuration is maintained per URL 
  inlineScriptsFile   = '';
  pageCookieFile      = '';
  targetDir           = '';

  loadedUrl           = null;
  parsedUrl           = null;
  inlineScripts       = null;
  inlineScripts       = new Array;
  pageUrls.clear();
  pageUrls            = null
  pageUrls            = new Map;
  pageHosts.clear();
  pageHosts           = null;
  pageHosts           = new Map;
  extractedUrls       = null;
  cookies             = null;
  root_request_header = null;
}

function sleep( millis )
{//{{{
  return new Promise((resolve) => {
    setTimeout(() => {
      resolve(true);
    },millis);
  });
}//}}}

function normalizeUrl( u, parse_input )
{//{{{
  // URLFIX
  // Only fill in missing URL components from corresponding targetUrl parts
  let fromPage = parse_input === undefined ? url.parse(u) : parse_input;
  let q = '';
  let h = '';
  if ( !(process.env['NOISY_PARSE'] === undefined) ) console.log( "A> %s", parsedUrl.href || '' );
  if ( !(process.env['NOISY_PARSE'] === undefined) ) console.log( "B> %s", u || '' );
  try { q = fromPage.query; } catch (e) { q = fromPage.query = ''; }
  try { h = fromPage.hash; } catch (e) { h = fromPage.hash = ''; }
  if ( (fromPage.protocol || '').length == 0 && (fromPage.hostname || '').length == 0 ) {
    if ( !(process.env['NOISY_PARSE'] === undefined) ) { 
      console.log( "J> %s", parsedUrl.pathname );
      console.log( "K> %s", fromPage.pathname || '' );
    }
    // The URL is relative, containing no protocol or hostname part
    // The path may be a fragment of targetUrl.pathname

    if ( (fromPage.pathname || '').length == 0 && (parsedUrl.pathname || '').length > 0 ) {
      fromPage.pathname = parsedUrl.pathname;
    }
    else if ( !/^[\/].*/.test( fromPage.pathname ) ) {
      // fromPage.pathname has no leading slash (possibly an index page script name)
      // If fromPage.pathname (A) is the trailing part of the non-zero-length targetUrl.pathname (B), then
      //   we simply copy (B) over (A)
      if ( (parsedUrl.pathname || '').length > 0 ) {
        let tailchecker = new RegExp('.*'.concat(fromPage.pathname,'$')); // Match against tail 
        if ( tailchecker.test( parsedUrl.pathname ) ) {
          fromPage.pathname = parsedUrl.pathname;
        }
        else {
          let test_concat = [ parsedUrl.pathname, fromPage.pathname ].join('/');

          if ( !(process.env['NOISY_PARSE'] === undefined) ) console.log("N> %s", test_concat );
          if ( !(process.env['NOISY_PARSE'] === undefined) ) console.log("O> %s", test_concat.replace(/\/([^\/]{1,})\/\.\.\//,'/') );
          fromPage.pathname = test_concat.replace(/\/([^\/]{1,})\/\.\.\//,'/');

          if ( process.env['PERMIT_BROKEN'] !== undefined ) {
            let parsed_sans_tail = (parsedUrl.pathname || '').split('/');
            if ( parsed_sans_tail.length > 1 ) {
              parsed_sans_tail.pop();
              parsed_sans_tail.push(fromPage.pathname);
              fromPage.pathname = parsed_sans_tail.join('/');
              if ( !(process.env['NOISY_PARSE'] === undefined) ) console.log("M> Possible tail replacement: %s", fromPage.pathname );
            }
          }
        }
      }
    }
    if ( !(process.env['NOISY_PARSE'] === undefined) ) console.log( "L> %s", fromPage.pathname );
  }
  if ( (fromPage.protocol || '').length == 0 ) fromPage.protocol = parsedUrl.protocol;
  if ( (fromPage.host     || '').length == 0 ) fromPage.host     = parsedUrl.host;
  if ( (fromPage.hostname || '').length == 0 ) fromPage.hostname = parsedUrl.hostname;

  let query_component = (q && q.length && q.length > 0 ? '?'.concat(q) : '');

  u = ''.concat(
    fromPage.protocol,
    '//',
    fromPage.hostname,
    fromPage.pathname.replace(/[\/]{1,}/gi,'/').replace(/\/([^\/]{1,})\/\.\.\//,'/').replace(/\/$/,''),
    query_component, // FIXME: URL query parts should be converted to path components
    (h && h.length && h.length > 0 ? h : '')
  ).replace(/#$/,''); // Scrub empty hash part
  //assert( !/[\?]/g.test( u ) ); // Ensure path excludes query delimiter
  fromPage.href = u;
  if ( !(process.env['NOISY_PARSE'] === undefined) ) {
    console.log( "C> %s", fromPage.href );
    console.log( " " );
  }
  return fromPage.href;
}//}}}

function stashInlineScript( s )
{//{{{
  console.log("Inline\r\n%s\r\n", s );
  inlineScripts.push(s);
}//}}}

function stashPageUrl( u )
{//{{{
  pageUrls.set(urlText, (pageUrls.get(urlText) || 0) + 1);
}//}}}

function stashUrlDomain( u )
{//{{{
  let p = url.parse(u);
  let h = p.host;
  if ( h && h.length ) {
    pageHosts.set( h, (pageHosts.get(h) || 0) + 1 );
  }
}//}}}

function parse_err_handler( e )
{//{{{
  console.log(e);
  return true;
}//}}}

function detag( index, element, depth = 0, elementParent = null, indexlimit = 0, tagstack )
{//{{{

  const $ = cheerio.load( element, {}, false );
  const tagname = $("*").prop('tagName');

  if ( depth == -1 ) {
    if ( indexlimit > 0 && index >= indexlimit )
      return false;
  }

  const className = $(tagname).prop("class") || ''; // element.attribs && element.attribs.class ? element.attribs.class : '';

  const logthis = loggedTags.has(tagname);

  try {
    if ( tagname == null ) {
      if ( String(element.data).trim().length > 0 ) {
        let parentName = $(elementParent).prop('tagName');
        if ( loggedTags.has( parentName ) || loggedTags.has("*") ) {
          //console.log( "%s%d> '%s'", pad.repeat(depth<<1), index, String(element.data).trim() );
          if ( (process.env['SILENT_PARSE'] === undefined) ) console.log( "%s%d ^[%s] > '%s'", pad.repeat(depth<<1), index, parentName, String(element.data).trim() );
        }
      }
    }
    else if ( tagname == 'META' ) {
      let attrlist = new Array;
      for ( a in element.attribs ) {
        attrlist.push( a.concat(': ',element.attribs[a]) );
      }
      if ( (process.env['SILENT_PARSE'] === undefined) ) if ( logthis ) console.log( "%s%d: %s{%s}", pad.repeat(depth<<1), index, tagname, attrlist.join(', ') );
    }
    else if ( tagname == 'LINK' ) {
      urlText = normalizeUrl( $("*").attr('href') );
      if ( (process.env['SILENT_PARSE'] === undefined) ) if ( logthis ) console.log( "%s%d: %s %s", pad.repeat(depth<<1), index, tagname, $("*").attr('rel').concat(": ",urlText) );
      stashPageUrl( urlText );
      stashUrlDomain( urlText );
    }
    else if ( tagname == 'A' ) {
      if ( undefined !== $("*").attr('href') ) {
        urlText = normalizeUrl( $("*").attr('href') );
        if ( (process.env['SILENT_PARSE'] === undefined) ) if ( logthis ) console.log( "%s%d: %s{%s} %s", pad.repeat(depth<<1), index, tagname, className, urlText );
        stashPageUrl( urlText );
        stashUrlDomain( urlText );
      }
    }
    else if ( tagname == 'SCRIPT' ) {
      let srcattr = $("*").attr('src');
      if ( undefined === srcattr ) {
        // Inline script
        stashInlineScript( $("*").text() );
      }
      else {
        urlText = normalizeUrl( srcattr );
        if ( (process.env['SILENT_PARSE'] === undefined) ) if ( logthis ) console.log( "%s%d: %s %s", pad.repeat(depth<<1), index, tagname, ($("*").attr('type') || '').concat(": ",urlText) );
        stashPageUrl( urlText );
        stashUrlDomain( urlText );
      }
    }
    else if ( tagname == 'BR' ) {
      if ( (process.env['SILENT_PARSE'] === undefined) ) if ( logthis ) console.log( "%s%d: ---------------------------------------", pad.repeat(depth<<1), index );
    }
    else if ( tagname.match(/(DIV|P|SPAN|LI)/) ) {
      if ( (process.env['SILENT_PARSE'] === undefined) ) if ( logthis ) console.log( "%s%d: %s{%s}", pad.repeat(depth<<1), index, tagname, className );
    }
    else if ( loggedTags.has("*") ) {
      if ( (process.env['SILENT_PARSE'] === undefined) ) console.log( "%s%d: %s", pad.repeat(depth<<1), index, tagname );
    }
  } catch(e) {
    console.log( "THROWN processing %s", tagname, e );
  }

  // Recurse to a reasonable tag nesting depth
  if ( depth < 20 ) {
    $(tagname).contents().each(function (i, e) {
      try {
        tagstack.push(tagname);
        detag( i, e, depth + 1, $, indexlimit, tagstack );
      }
      catch(err)
      {
        console.log( "Error parsing %s %s", tagname, e );
      }
    });
    // Newline between containing chunks
    if ( (process.env['SILENT_PARSE'] === undefined) ) if ( 0 == depth ) console.log( "" );
  }
  $("*").remove();
  return true;
};//}}}

function extract_urls( data, target, parse_roots, have_extracted_urls )
{//{{{
  assert( parse_roots !== undefined );

  let pageUrlsArray;

  if ( !( process.env['FORCE_EXTRACT'] === undefined ) )
    have_extracted_urls = false;

  if ( have_extracted_urls ) {
    try { 
      let extracted_urls = readFileSync( targetFile.concat(".urls.json") );
      pageUrlsArray = JSON.parse( extracted_urls );
      console.log( "+ Reloaded %d URLs", pageUrlsArray.length );
    }
    catch (e) {
      console.log( "* Preparsed URL load error", e );
      have_extracted_urls = false;
    }
  }

  if (!have_extracted_urls ) {

    let tagstack = new Array;
    const $ = cheerio.load( data, { onParseError: parse_err_handler } );
    console.log("Fetched markup.  Parsing...");

    parse_roots.forEach((parse_root) => { 
      $(parse_root)
        .children()
        .each( function(i,e) { 
          try {
            detag(i,e,0,null,sampleCount,tagstack); 
          }
          catch(err) {
            console.log( "Error parsing element %s", e );
          }
        });
    });

    $("*").remove();

    pageUrlsArray = new Array;

    if ( pageUrls.size > 0 ) {

      urlListFile = targetFile.concat(".urls.json");
      console.log("Dumping %d URLs rooted at '%s'", pageUrls.size, target ); 

      for ( const url of pageUrls.keys() ) {
        pageUrlsArray.push( url );
      }

      writeFile( urlListFile, JSON.stringify( pageUrlsArray.sort() ), function(err) {
        if ( err ) {
          console.log( "Extractor failed to write URLs from page at '%s'", target );
        }
        else {
          console.log( "Extractor wrote %d URLs from '%s' into '%s'", pageUrls.size, target, urlListFile );
        }
      });

      // FIXME: Use fsPromises.writeFile to serialize writes.
      if ( inlineScripts.length > 0 )
        writeFileSync( inlineScriptsFile, JSON.stringify( inlineScripts, null, 2 ), {
          flag  : 'w',
          flush : true
        }); 
    }
  }
  return pageUrlsArray;
};//}}}

function stringified_cookies( ca )
{//{{{
  let cookiearray = new Array;
  for ( i in ca ) {
    let co = ca[i];
    let ck_name = co.name || null;
    let ck_val  = co.value || null;
    if ( !ck_name || !ck_val ) continue;
    cookiearray.push( ck_name.concat( '=', ck_val ) );
  }
  return cookiearray.join('; ');
}//}}}

function mapified_cookies( ca )
{//{{{
  let cookiemap = new Map;
  for ( i in ca ) {
    let co = ca[i];
    let ck_name = co.name || null;
    let ck_val  = co.value || null;
    if ( !ck_name || !ck_val ) continue;
    cookiemap.set( ck_name, ck_val );
  }
  return cookiemap;
}//}}}

function keep_unique_host_path( u, result, unique_host_path, unique_entry, head_info )
{//{{{
  let content_type = head_info['content-type'] || null;
  // Retain unique paths and hosts
  if ( unique_host_path.get( unique_entry ) === undefined ) {
    unique_host_path.set( unique_entry, {
      hits: 1, 
      headinfo: {
        'content-length'   : head_info['content-length'] || null,
        'content-type'     : head_info['content-type'] || null,
        'content-encoding' : head_info['content-encoding'] || null,
        'last-modified'    : head_info['last-modified'] || null
      }
    });
  }
  else {
    let prior_entry = unique_host_path.get( unique_entry );
    unique_host_path.set( unique_entry, {
      hits: prior_entry.hits + 1,
      headinfo: prior_entry.headinfo
    });
  }
  if ( ( process.env['SKIP_HEAD_FETCH'] === undefined ) ) {
    if ( (process.env['SILENT_PARSE'] === undefined) || (process.env['SHOW_HEADINFO'] !== undefined) ) console.log("%d:\t%s %s %s",
      result.length,
      content_type,
      head_info['content-length'],
      u.href
    );
  }
}//}}}

function return_sorted_map( map_obj )
{
  let sorter = new Array;
  let sorted = new Map;
  map_obj.forEach((value, key, map) => {
    sorter.push(key);
  });
  sorter.sort();
  sorter.forEach((e) => {
    sorted.set( e, map_obj.get(e) );
    map_obj.delete(e);
  });
  sorter.forEach((e) => {
    map_obj.set( e, sorted.get(e) );
    sorted.delete(e);
  });
  while ( sorter.length > 0 ) { sorter.pop(); }
  sorted.clear();
  sorted = null
  sorter = null;
  return map_obj;
}
function write_map_to_file( description, map_file, map_obj, loadedUrl )
{//{{{
  const objson = Object.fromEntries( return_sorted_map(map_obj) );
  console.log( "Writing %s to %s", description, map_file );
  writeFileSync( map_file, JSON.stringify( objson, null, 2 ), {
    flag  : 'w',
    flush : true
  }); 
}//}}}

async function extract_hosts_from_urlarray( target_url, result )
{//{{{
  const head_standoff = 50;
  let unique_host_pathmap = new Map;
  let unique_hosts = new Map;

  // For writing lists of permitted hosts, hosts referred on page, and link HEAD info
  // targetDir is updated in fetch_and_extract()
  // let permittedHosts = targetDir.concat( '/', 'permitted.json' );
  let current_hosts = targetDir.concat( '/', 'hosts.json' );
  let linkinfo = targetDir.concat( '/', 'linkinfo.json' );
  let fileProps = statSync( permittedHosts, { throwIfNoEntry: false } );

  // Obtain any preexisting permitted hosts list from file
  if ( fileProps ) {
    let ofile = readFileSync(permittedHosts);
    let o = JSON.parse( ofile );
    unique_hosts = new Map(Object.entries(o));
    console.log( "Located permitted hosts list %s", permittedHosts, unique_hosts );
  }
  // Extract reachable hosts and unique paths lists

  while ( result.length > 0 )
  {//{{{
    // URLFIX
    // let g = normalizeUrl(result.shift()); // result.shift().replace(/\/\.\.\//,'/').replace(/\/$/,'').replace(/[.]{1,}$/,'').replace(/\/$/,'');
    let g = result.shift().replace(/\/\.\.\//,'/').replace(/\/$/,'').replace(/[.]{1,}$/,'').replace(/\/$/,'');

    if ( process.env.PERMIT_HTTP === undefined ) {
      let h = g.replace(/^http:/,'https:');
      g = h;
    }

    let u = url.parse( g );

    let unique_entry = g; // u.protocol.concat("//", u.hostname, u.pathname);
    let unique_host  = u.host;
    let head_options;

    // Issue HEAD requests to each URL when cookies are available
    // from a prior run executing fetch_and_extract( target_url ); 

    head_headers = {
      "User-Agent"                : root_request_header && root_request_header['User-Agent'] ? root_request_header['User-Agent'] : "Mozilla/5.0 rX11; Linux x86_64; rv: 109.0) Gecko/20100101 Firefox/115.0",
      "Accept"                    : "text/html,application/xhtml+xml,application/pdf,application/javascript,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8",
      "Accept-Language"           : "en-US,en;q=0.5",
      "Accept-Encoding"           : "gzip, deflate, br",
      "Connection"                : "keep-alive",
      "Referer"                   : target_url.replace(/\/\.\.\//,'/').replace(/\/$/,''),
      "Cookie"                    : stringified_cookies( cookies ), 
      "Upgrade-Insecure-Requests" : 1,
      "Sec-Fetch-Dest"            : "document",
      "Sec-Fetch-Mode"            : "navigate",
      "Sec-Fetch-Site"            : "same-origin",
      "Sec-Fetch-User"            : "?1",
      "Pragma"                    : "no-cache",
      "Cache-Control"             : "no-cache"
    }

    head_options = {
      method: "HEAD",
      headers: head_headers
    }

    let content_type = null;
    let excluded = false;

    // Test for exclusions:
    // - URL protocol neither http: nor https:
    // - Pathname potentially a PDF, shell script or some other application
    // - Page assets that appear irrelevant to content analysis (images)
    if ( /^javascript:.*/.test(g) )
      excluded = true;

    if ( /.*\.(sh|css|java|jpeg|jpg|gif|ico|png|tiff|tif|js)$/gi.test(u.pathname) )
      excluded = true;

    if ( excluded ) {
      console.log( "- %s", g );
    }
    else if ( !fileProps || unique_hosts.has( u.host ) ) {
      // Determine asset mimetype configured on server
      let head_info;
      if ( !(process.env['SKIP_HEAD_FETCH'] === undefined) ) {
        keep_unique_host_path( u, result, unique_host_pathmap, unique_entry, {} );
      }
      else 
      if ( u.protocol == 'https:' ) {
        try {
          await https.request( g, head_options, (res) => {
            // console.log("Intrinsic A", g, res.headers);
            if ( head_info === undefined ) {
              head_info = res.headers;
              keep_unique_host_path( u, result, unique_host_pathmap, unique_entry, head_info );
            }
            res.on('data', (d) => {
              if ( (process.env['SILENT_PARSE'] === undefined) ) console.log('Data from', g, d);
            });
          }).on('error', (e) => {
            if ( (process.env['SILENT_PARSE'] === undefined) ) console.log("Error", g, e);
          }).on('response', (m) => {
            if ( head_info === undefined ) {
              head_info = m.headers;
              keep_unique_host_path( u, result, unique_host_pathmap, unique_entry, head_info );
            }
          }).on('data', (d) => {
            if ( (process.env['SILENT_PARSE'] === undefined) ) console.log('Data B from', g, d);
          }).end();
          if ( head_standoff ) await sleep(head_standoff);
        } catch (e) {
          console.log("CATCH", g, e);
        }
      }
      else if ( u.protocol == 'http:' ) {
        try {
          await http.request( g, head_options, (res) => {
            // console.log("Intrinsic B", g, res.headers);
            if ( head_info === undefined ) {
              head_info = res.headers;
              keep_unique_host_path( u, result, unique_host_pathmap, unique_entry, head_info );
            }
            res.on('data', (d) => {
              if ( (process.env['SILENT_PARSE'] === undefined) ) console.log('Data from', g, d);
            });
          }).on('error', (e) => {
            if ( (process.env['SILENT_PARSE'] === undefined) ) console.log("Error", g, e);
          }).on('response', (m) => {
            if ( head_info === undefined ) {
              head_info = m.headers;
              keep_unique_host_path( u, result, unique_host_pathmap, unique_entry, head_info );
            }
          }).on('data', (d) => {
            if ( (process.env['SILENT_PARSE'] === undefined) ) console.log('Data B from', g, d);
          }).end();
          if ( head_standoff ) await sleep(head_standoff);
        } catch (e) {
          console.log("CATCH", g, e);
        }
      }
    }
    else {
      console.log( "* %s", g );
    }

    // Only update the map if not preloaded from cache file
    if ( !fileProps ) {
      if ( unique_hosts.get( unique_host ) === undefined ) {
        unique_hosts.set( unique_host, 1 );
      }
      else {
        unique_hosts.set( unique_host, unique_hosts.get( unique_host ) + 1 );
      }
    }

  } //}}}
  // while ( result.length > 0 )

  let unique_host_pathmap_sorted = new Map;
  let mapsorter = new Array;
  // Sort map: Copy keys to array, and empty map into a new one.
  unique_host_pathmap.forEach((value, key, map) => { mapsorter.push(key); });
  mapsorter.sort();
  mapsorter.forEach((url) => {
    unique_host_pathmap_sorted.set( url, unique_host_pathmap.get(url) );
    unique_host_pathmap.delete(url);
  });
  unique_host_pathmap.clear();
  while ( mapsorter.length > 0 ) { mapsorter.shift(); }
  mapsorter = null;

  console.log( "Obtained %d unique whole URLs", unique_host_pathmap_sorted.size, (process.env['SILENT_PARSE'] === undefined) ? unique_host_pathmap_sorted : '' );
  console.log( "Obtained %d unique hostnames", unique_hosts.size, (process.env['SILENT_PARSE'] === undefined) ? unique_hosts : '' );

  console.log( "Target path: %s", targetDir );

  // Write permitted and currently reachable URL lists 
  if ( !fileProps ) {
    write_map_to_file( "permitted hosts list", permittedHosts, unique_hosts, target_url );
  }
  write_map_to_file( "reachable hosts list", current_hosts, unique_hosts, target_url ); 

  if ( process.env['SKIP_HEAD_FETCH'] === undefined ) {
    // Write linkinfo.json containing unique_host_pathmap_sorted
    write_map_to_file( "unique HEAD info from links", linkinfo, unique_host_pathmap_sorted, target_url );
  }

  return new Promise((resolve) => {
    console.log('Done. Found %d unique host paths, %d unique hosts', unique_host_pathmap_sorted.size, unique_hosts.size );
    resolve({ paths: unique_host_pathmap_sorted, hosts: unique_hosts });
  });

}//}}}

function load_config_settings( config_file )
{//{{{
  // Read site- and per-page parse settings file
  let config_map;
  let ofile;
  let present = statSync( config_file, { throwIfNoEntry : false } );

  if ( !present ) {
    blank_config_settings = new Map;
    blank_config_settings.set( "parse_roots", [ "body" ] ); 
    write_map_to_file( "default parse config file", config_file, blank_config_settings, loadedUrl );
  }

  ofile = readFileSync( config_file, { flag: 'r' } );
  if ( ofile.length > 0 ) {
    let o = JSON.parse( ofile );
    config_map = new Map(Object.entries(o)); 
  }
  else {
    config_map = new Map;
  }
  return config_map;
}//}}}

function load_visit_map( visit_file )
{//{{{
  let visited_map;
  let ofile;
  let present = statSync( visit_file, { throwIfNoEntry : false } );

  if ( !present ) {
    blank_visit_map = new Map;
    blank_visit_map.set( "https://congress.gov.ph", { hits: 1 } ); 
    write_map_to_file( "new map of visited URLs", visit_file, blank_visit_map, loadedUrl );
  }

  ofile = readFileSync( visit_file, { flag: 'r' } );
  if ( ofile.length > 0 ) {
    let o = JSON.parse( ofile );
    visited_map = new Map(Object.entries(o)); 
  }
  else {
    visited_map = new Map;
  }
  return visited_map;
}//}}}

describe("Recursively descend ".concat(targetUrl), async () => {

function loadCookies()
{//{{{
  try {
    // Load any preexisting pageCookieFile
    let cookieProps = statSync( pageCookieFile, { throwIfNoEntry: false } );
    if ( !cookieProps ) {
      console.log( "No existing cookie file %s", pageCookieFile );
      cookies = null;
    } else {
      let data = readFileSync( pageCookieFile );
      cookies = JSON.parse( data );
      console.log( "Loaded cookies from %s", pageCookieFile );
    }
  } catch(e) {
    console.log( "Problem reloading existing cookies from %s", pageCookieFile );
    console.log( "Going without." );
  }
}//}}}

function recompute_filepaths_from_url(target)
{//{{{
  assert(target.length > 0);
  parsedUrl = url.parse(target);
  // Generate targetFile path based on URL path components.
  let relativePath = new Array();
  let pathParts;
  let pathComponent = 0; 

  targetDir = '';

  pathParts = parsedUrl.host.concat('/', parsedUrl.pathname)
    .replace(/[?]/g,'/')       // Replace query delimiter at any position with '/'
    .replace(/[\/]{1,}/gi,'/') // Replace multiple forward-slashes to '/'
    .replace(/[\/]{1,}$/,'')   // Trim multiple trailing slashes
    .replace(/^[\/]{1,}/,'')   // Trim multile leading slashes
    .replace(/\/\.\.\//,'/')   // Remove intervening double-dot components
    .replace(/[\/.]$/,'')      // Remove trailing slash-dot
    .split('/');

  // Unique visited URL and permitted hosts files
  visitFile      = parsedUrl.host.concat('/visited.json');
  permittedHosts = parsedUrl.host.concat('/permitted.json');
  siteConfigFile = parsedUrl.host.concat('/config.json');

  while ( pathParts.length > 0 ) {
    let part = pathParts.shift();
    relativePath.push(part);
    targetDir = relativePath.join('/');
    pathComponent++;
    if ( existsSync( targetDir ) ) continue;
    mkdirSync( targetDir );
  }
  targetFile        = targetDir.concat('/index.html');
  pageConfigFile    = targetDir.concat('/config.json');
  assetCatalogFile  = targetDir.concat('/index.assets.json');
  depthPlusOneFile  = targetDir.concat('/depth.json');
  inlineScriptsFile = targetDir.concat('/index.inlinescripts.json');
  pageCookieFile    = targetDir.concat('/cookies.json');
  console.log( 'Target path computed as %s', targetFile );
  console.log( '  Site config %s'          , siteConfigFile );
  console.log( '  Page config %s'          , pageConfigFile );
  console.log( '  Asset catalog %s'        , assetCatalogFile );
  console.log( '  Inline scripts %s'       , inlineScriptsFile );
  console.log( '  Page cookies %s'         , pageCookieFile );
  console.log( '  Visited URLs %s'         , visitFile );
  console.log( '  Permitted hosts %s'      , permittedHosts );
}//}}}

async function interaction_v2_test( browser, rr, site_parse_settings, url_params )
{
  let pager_selector = 'html body.flex.flex-col.mx-0.lg:mx-auto.antialiased main section.container-fluid.-mt-24.lg:container.lg:mt-2 div.container.items-start.lg:py-8.aos-init.aos-animate div.flex.justify-center.bg-white.pb-4 ul.pagination.flex.items-center';

  if ( await $(pager_selector).isExisting() ) {
    console.log("Booyah!  We will crawl this pager");
    await $(pager_selector).$('li a');
  }

}

async function interaction_test( browser, rr, site_parse_settings, url_params )
{//{{{
  //let congress_selector_css = 'html body div.container-fluid div.row.section div.col-md-8 div.container-fluid div.row form.form-inline div.form-group.input-group select.form-control.input-sm';
  let congress_selector_css = 'select.form-control.input-sm';
  let option_n = 0;
  //let gobutton = 'html body div.container-fluid div.row.section div.col-md-8 div.container-fluid div.row form.form-inline div.form-group.input-group span.input-group-btn input.btn.btn-default.input-sm';
  //let gobutton = 'submit=Go';
  let parentForm;
  //let gobutton = '//html/body/div[2]/div/div[1]/div[1]/div[1]/form[2]/div/span/input';
  let gobutton; // Submit trigger is a sibling element in the enclosing form
  let halttime = 2;
  let selector, trigger;
  let o;
  let option_val, initial_val, current_val;
  let index_limit = 0;
  let title = await browser.getTitle();
  let currentUrl = await browser.getUrl();
  let query_splitter = /([0-9a-z_]{1,})\=([0-9a-z_]{1,})/gi;
  let markup;

  async function get_parent( tagname, e )
  {//{{{
    let parent_element;
    let child = e;
    let iterations = 0;
    while ( parent_element === undefined ) {//{{{
      let parent_element = await child.$('..'); 
      let candidate_tagname = await parent_element.getTagName(); 
      iterations++;
      if ( candidate_tagname  === undefined || candidate_tagname == 'html' ) {
        console.log( "%d: Processing cannot continue.", iterations );
        break;
      }
      else if ( candidate_tagname == tagname ) {
        console.log( "Got parent %s", tagname );
        return Promise.resolve(parent_element);
      }
      else {
        console.log( "%d: Processing next ancestor %s", iterations, candidate_tagname );
        child = parent_element;
      }
    }//}}}
    return Promise.resolve(parent_element);
  }//}}}

  async function get_parent_form( select_e )
  {//{{{
    // Locate SELECT tag's parent FORM tag
    return Promise.resolve(await get_parent( 'form', select_e ));
  }//}}}

  async function get_submit_button_in( parent_form )
  {//{{{
    let go_button;
    // Fetch the "Go" button within parent_form
    if ( parent_form !== undefined ) {//{{{
      let candidate_trigger = await parent_form.$('input.btn.btn-default.input-sm');
      let trigger_tagname = await candidate_trigger.getProperty('tagName');
      let trigger_typename = await candidate_trigger.getAttribute('type');
      console.log( "Button search found '%s' (%s)", trigger_tagname, trigger_typename );
      if ( trigger_tagname == 'INPUT' && trigger_typename == 'submit' ) {
        console.log( "Located 'Go' button" );
        return Promise.resolve(candidate_trigger);
      }
      else {
        console.log( "Processing cannot continue - button not found." );
      }
    }//}}}
    return Promise.resolve(go_button);
  }//}}}

  async function get_select_index_limit( select_e )
  {//{{{
    // Determine index limit
    for ( ; option_n < 30; option_n++ ) {//{{{
      try {
        let o = await select_e.selectByIndex(option_n);
        let o_val = await select_e.getValue();
        console.log( "- Option %d[%s]: %s", option_n, typeof o, o_val);
        if ( o && current_val != o_val ) {
          await o.click();
          await select_e.click();
        }
        index_limit = option_n;
        await sleep( 200 );
      }
      catch (e) {
        console.log("No option with index %d, maximum %d.  Leaving", option_n, index_limit );
        index_limit++;
        option_n = 30;
      }
      if ( option_n == 30 ) {
        break;
      }
    }//}}}
    return new Promise((resolve) => { resolve(index_limit); });
  }//}}}

  async function bills_history_modal( b )
  {
    // Parameters:
    // - b: browser object from caller context
    // Trigger modal markup fetch event
    // - Click on "History" link to trigger event
    // - Wait for 'html body.modal-open div.container-fluid div.row.section div.col-md-8 div#HistoryModal.modal.fade.in'
    //   - Save modal markup
    // - Locate and click on "Close" button 'html body.modal-open div.container-fluid div.row.section div.col-md-8 div#HistoryModal.modal.fade.in div.modal-dialog div.modal-content div.modal-footer a.btn.btn-default.btn-success'
    // 
    return Promise.resolve(true);
  }

  // Enumerate options from registry selection tag
  console.log( "Waiting for presence of selector '%s'", congress_selector_css );
  await browser.$('title').waitUntil(async function() {
    title = await this.getText();
    console.log( "Checking title: '%s'", title);
    return title === "House of Representatives";
  },{
    timeout: 24 * 60 * 60 * 1000, 
    interval: 1000 
  });

  selector = await browser.$(congress_selector_css);
  assert.equal("House of Representatives", title);
  console.log( "Test of interaction in %d seconds", halttime );
  await sleep( halttime * 1000 );

  // Visibly enable selector
  await selector.click();
  await sleep( 1000 );

  current_val = selector.getValue();
  initial_val = current_val;

  parentForm  = await get_parent_form( selector );
  gobutton    = await get_submit_button_in( parentForm );
  index_limit = await get_select_index_limit( selector );

  console.log( "targetDir: %s", targetDir );

  for ( option_n = 0 ; option_n < index_limit ; option_n++ ) {

    let from_end = index_limit - option_n - 1;
    let rrv;
    let p_url; 
    let loaded_url;
    let request_map = new Map;
    let target_dir;

    // pageUrls.clear(); // FIXME: This global array MUST be cleared through each pass.
    // Done in fetch_reset():
    fetch_reset();

    // The document registry pages in a document type have 
    // homologous structure across document classes (bills, Republic Acts, etc.)
    // 
    selector   = await browser.$(congress_selector_css);
    parentForm = await get_parent_form( selector );
    trigger    = await get_submit_button_in( parentForm );
    o          = await selector.selectByIndex( from_end );
    option_val = await selector.getValue();

    console.log( "- Triggering option '%s'[%d]", option_val, from_end );
    await trigger.moveTo();
    await sleep( 200 );
    await trigger.click();

    console.log("Sleeping a second");
    await sleep( 1000 );
    loaded_url = await browser.getUrl();
    rrv = rr.get( loaded_url );

    console.log("Obtained %s", loaded_url, rrv ); 

    if ( rrv !== undefined ) {
      let query_map = new Map;
      let query_arr = rrv.data ? rrv.data.split('&') : [];
      let perform_head_fetch = process.env['FORCE_INTERACTABLE_HEAD_FETCH'] !== undefined; // false;

      // Construct Map with query components as key-value pair elements.
      query_arr.forEach((e) => {
        query_map.set( 
          e.replace(/^([^=]{1,})=.*$/, '$1'),
          e.replace(/^([^=]{1,})=/,'')
        );
      });

      query_map.delete('csrf_token');

      while ( query_arr.length > 0 ) query_arr.shift();

      query_arr.sort();

      query_map.forEach((value, key, map) => {
        query_arr.push( key.concat('=',value) );
      });

      query_arr.sort();

      // FIXME: Decompose query parameters into path components
      parsedUrl = loaded_url;
      p_url = url.parse( loaded_url );
      // The .data element is simply urlencoded POST data
      p_url.query = query_arr.join('&');
      p_url.parse( normalizeUrl( p_url.href, p_url ) );
      console.log( "Recording virtual URL %s", p_url.href );
      rr.set( p_url.href, rrv );
      request_map.set( p_url.href, rrv ); // WRITE

      // Exclude query segment delimiter '?'
      target_dir = p_url.href.split('?').join('/');
      recompute_filepaths_from_url( target_dir );
      target_dir = targetDir;

      console.log( "Interactable target dir %s", target_dir );
      if ( !existsSync( target_dir ) ) { 
        mkdirSync( target_dir, { recursive: true } );
        perform_head_fetch = true;
      }
      markup = await browser.$('html').getHTML(); // WRITE
      targetFile = target_dir.concat('/index.html');
      writeFileSync( targetFile, markup, {
        flag  : 'w',
        flush : true
      });

      // Fetch markup fragments only if the /lib directory hasn't yet been populated.
      if ( process.env['HISTORY_FETCH'] === undefined ) {
        console.log( "Skipping [History] fetch" );
      }
      else if ( existsSync( target_dir.concat('/lib/completed') ) ) {
        console.log( "Skip walking preexisting [History] for %s", p_url.href );
      }
      else {
        // At this point, we can scan through/wait for e.g. the "#HistoryModal" <A>
        // tags, which, when clicked, invoke POST events to retrieve bill history 
        // markup text. WIth the target directory for markup created, we can proceed
        // to fetch those fragments.
        for await (const e of browser.$$('a[href="#HistoryModal"]') ) 
        {//{{{
          let parent_div;
          let close_button;
          let history_modal;
          let data_attr = await $(e).getAttribute('data-id');
          let hasher = createHash('sha256');
          let matcher = new RegExp('('.concat(data_attr,')'), 'g');
          let fragment_hash;
          let cdp_rr;
          let parsed_u;
          let cache_path;
          let uncached_fragment = false;

          if ( data_attr && data_attr.length > 0 ) {
            hasher.update(data_attr);
            fragment_hash = hasher.digest('hex');
            cache_path = target_dir.concat('/lib/', fragment_hash );
            if ( !existsSync( cache_path ) ) {
              mkdirSync( cache_path, { recursive: true } );
            }
          }

          // We can avoid triggering HTML fragment fetches if a cache path and
          // cached markup is present.
          cdpRRdata.clear();

          console.log( "Match %s %s", await $(e).getText(), data_attr );
          await e.scrollIntoView({ behavior: 'instant', block: 'start', inline: 'nearest' });

          uncached_fragment = !existsSync( cache_path.concat('/fragment.html') );

          if ( uncached_fragment ) {

            parent_div = await get_parent( 'div', e );
            // Trigger modal fetch
            try {
              await e.waitForDisplayed( { timeout: 5000 } );
              await e.waitForClickable( { timeout: 10000 } );
              await e.click();

              history_modal = await browser.$('div.modal.fade.in');
              await history_modal.waitForDisplayed( { timeout: 5000 } ); 
              console.log("Got modal window");

              // Wait for the modal window close trigger to be available.
              close_button = await history_modal.$('button.close');
              console.log("Button '%s'", await await history_modal.$('button.close').getText() );
              await close_button.waitForDisplayed( { timeout: 5000 } );
              await close_button.waitForClickable( { timeout: 10000 } );

              console.log( "Test CDP response (if any) against '%s'", data_attr );
              cdpRRdata.forEach((value, key, map) => {
                if ( value.method == 'POST' && matcher.test(value.data) ) {
                  let t; 
                  cdp_rr = new Map;
                  cdp_rr.set( key, value );
                  console.log("Found CDP %s response from %s", value.method, key, value);
                  parsed_u = url.parse( key );
                  parsed_u.query = [data_attr, parsed_u.query].join('&').replace(/\&$/,'');
                  t = normalizeUrl( key, parsed_u );
                  console.log( ">> Target URL: %s", t );
                  console.log( ">> Request URL hash: %s", fragment_hash );
                  cdp_rr.set( 'meta_url', t );
                  cdp_rr.set( 'meta_hash', fragment_hash );
                  cdp_rr.set( 'record', key );
                }
              });
            }
            catch (e) {
              console.log( "Element event could not be completed", e );
            }

            console.log( "Closing dialog" );
            // Save the markup
            if ( cdp_rr !== undefined && cache_path !== undefined ) {
              let fragment_markup = await history_modal.getHTML(); 
              let markup_file = cache_path.concat('/fragment.html');
              let cdp_file = cache_path.concat('/network.json');
              writeFileSync( markup_file, fragment_markup, {
                flag: 'w',
                flush: true
              });
              write_map_to_file( "markup fragment metadata", cdp_file, cdp_rr, cdp_rr.meta_url ); 
            }

            if ( cdp_rr !== undefined ) {
              cdp_rr.clear();
              cdp_rr = null;
            }

            let done = false;
            // Close the dialog
            while ( !done ) {
              try {
                if ( await close_button.isClickable() ) {
                  console.log( "Closing modal" );
                  await close_button.click();
                  await close_button.waitForDisplayed( { reverse: true, timeout: 3000 } );
                }
                done = true;
              }
              catch(e) {
                console.log( "Must retry modal close" );
              }
            }
          }

          matcher = null;
          hasher = null;

          if ( uncached_fragment )
            await sleep(200);

        }//}}}
        writeFileSync( target_dir.concat('/lib/completed'), 'done', {
          flag  : 'w',
          flush : true
        });
      }

      if ( perform_head_fetch ) {
        let extracted_urls = extract_urls( 
          markup, 
          loaded_url, 
          site_parse_settings.get('parse_roots'), 
          false /* have_extracted_urls */ 
        );

        let iteration_subject_map = await extract_hosts_from_urlarray( 
          loaded_url, 
          extracted_urls
        );

        let iterable_list_file = target_dir.concat('/index.potentially-iterable.json');

        try {
          console.log( "Writing triggered fetch iterable URLs into %s", iterable_list_file );

          write_map_to_file(
            "iterable URLs including text/html",
            iterable_list_file,
            iteration_subject_map.paths,
            p_url.href
          );
        }
        catch(e) {
          console.log("Unable to write iterable URLs from %s into %s", 
            p_url.href,
            iterable_list_file
          );
        }
      } // perform_head_fetch

      write_map_to_file( 
        "request_map", 
        target_dir.concat('/index.assets.json'),
        request_map,
        p_url.href
      );
    }

    if ( from_end == initial_val ) 
      console.log( "Loaded page content at default selector value" ); 

    request_map.clear();
    request_map = null;

  }

  return Promise.resolve(markup);
}//}}}

async function fetch_and_extract( initial_target, depth )
{//{{{
  // Walk through all nodes in DOM

  let state_timeout = 14400000;
  let recursion_depth = 0;
  let depth_iterations = 0;
  let targets = new Array;
  let depth_plus_one = new Map;

  // Breadth-first link traversal state flags
  let step_targets = 0;
  let resweep = (process.env.REFRESH !== undefined);
  let interactable = new Map;

  interactable.set( "https://congress.gov.ph/legisdocs/?v=adopted" , { method: interaction_test, params: {} } );
  interactable.set( "https://congress.gov.ph/legisdocs/?v=bills"   , { method: interaction_test, params: {} } );
  interactable.set( "https://congress.gov.ph/legisdocs/?v=cdb"     , { method: interaction_test, params: {} } );
  interactable.set( "https://congress.gov.ph/legisdocs/?v=cr"      , { method: interaction_test, params: {} } );
  interactable.set( "https://congress.gov.ph/legisdocs/?v=journals", { method: interaction_test, params: {} } );
  interactable.set( "https://congress.gov.ph/legisdocs/?v=ob"      , { method: interaction_test, params: {} } );
  interactable.set( "https://congress.gov.ph/legisdocs/?v=ra"      , { method: interaction_test, params: {} } );
  interactable.set( "https://congress.gov.ph/legisdocs/?v=sb"      , { method: interaction_test, params: {} } );
  interactable.set( "https://congress.gov.ph/legislative-documents", { method: interaction_v2_test, params: {} } );

  it('Scrape starting at '.concat(initial_target), async function () {

    targets.push( initial_target );

    while ( targets.length > 0 && depth_iterations < 10 ) {

      let target = targets.shift().replace(/\/\.\.\//,'/').replace(/\/$/,'').replace(/[.]{1,}$/,'').replace(/\/$/,'');
      let page_assets = new Map;
      let have_extracted_urls = false;
      let iteration_subjects;
      let visited_pages;

      depth_iterations++;

      recompute_filepaths_from_url(target);

      // Load this just once
      if ( siteParseSettings == undefined )
        siteParseSettings = load_config_settings( siteConfigFile ); 

      // FIXME: This, and other globals, need to be factored out, and encapsulated in modules.
      // FIXME: Alter the main loop to use this and related globals 
      // FIXME: out of their own namespace (again, possibly using a class that encapsulates the page iteration state) 
      pageParseSettings = load_config_settings( pageConfigFile );

      console.log( "========================================" );
      console.log( "Process:", argv );
      console.log( "Target: %s", target );
      console.log( "Browser: ", browser, browser.addCommand ? browser.addCommand.name : {} );
      console.log( "Visit: ", visitFile );

      // Preload any visited pages catalog
      visited_pages = load_visit_map( visitFile ); 

      assert( visited_pages.size > 0 )

      // Record this URL as the first unique entry
      pageUrls.set( target, 1 ); 

      let visited = visited_pages.has( target );
      let fileProps = null;

      try {
        have_extracted_urls = statSync( targetFile.concat(".urls.json"), { throwIfNoEntry: true } );
        fileProps = statSync( targetFile, { throwIfNoEntry: true } );
        console.log( "Target '%s' props:", targetFile, (process.env['SILENT_PARSE'] === undefined) ? fileProps : '' );
      } catch(e) {
        console.log("Must fetch '%s' from %s", targetFile, target );
        fileProps = null;
        have_extracted_urls = false;
      }

      console.log( "%s url %s", visited ? "Already visited" : "Unvisited", target );

      if ( process.env['REFRESH'] !== undefined )
        have_extracted_urls = false;
      else if ( !fileProps ) 
        have_extracted_urls = false;

      if ( !fileProps || !visited || resweep ) 
      {//{{{

        console.log( "Fetching from %s", target );

        ////////////////////////////////
        if ( process.env['COOKIES_IGNORE'] === undefined ) loadCookies();

        await browser.setTimeout({ 'script': state_timeout });

        // Enable capture of HTTP requests and responses
        // https://stackoverflow.com/questions/61569000/get-all-websocket-messages-in-protractor/62210198#62210198 2024-02-21
        await browser.cdp('Network', 'enable');
        await browser.on('Network.requestWillBeSent', (event) => {
          if ( event.request.url == target )
            if ( (process.env['SILENT_PARSE'] === undefined) ) console.log(`Request: ${event.request.method} ${event.request.url}`, event.request.headers);
          root_request_header = event.request.headers;
          cdpRRdata.set( event.request.url, {
            target: event.request.url,
            status: null, 
            method: event.request.method,
            dataEntries: event.request.hasPostData ? event.request.postDataEntries : [],
            data: event.request.postData ? event.request.postData : "",
            req: event.request.headers,
            res: null
          });
          page_assets.set( event.request.url, {
            status: null, 
            method: event.request.method,
            dataEntries: event.request.hasPostData ? event.request.postDataEntries : [],
            data: event.request.postData ? event.request.postData : "",
            req: event.request.headers,
            res: null
          });
        });
        await browser.on('Network.responseReceived', (event) => {
          let pair = page_assets.get( event.response.url );
          if ( pair !== undefined ) {
            pair.res    = event.response.headers;
            pair.status = event.response.status;
            page_assets.set( event.response.url, pair );
            if ( (process.env['SILENT_PARSE'] === undefined) ) console.log(`${pair.method} Response: ${event.response.status} ${event.response.url}`, pair);
          }
          else {
            if ( (process.env['SILENT_PARSE'] === undefined) ) console.log(`Unpaired Response: ${event.response.status} ${event.response.url}`, pair);
            page_assets.set( event.response.url, {
              status: event.response.status,
              req: null,
              res: event.response.headers
            });
          }

          // Unlogged data
          pair = cdpRRdata.get( event.response.url );
          if ( pair !== undefined ) {
            pair.res    = event.response.headers;
            pair.status = event.response.status;
            cdpRRdata.set( event.response.url, pair );
          }
          else {
            cdpRRdata.set( event.response.url, {
              status: event.response.status,
              req: null,
              res: event.response.headers
            });
          }


        });

        if (process.env['SILENT_PARSE'] === undefined) console.log( "Browser status", await browser.status() );

        if ( !cookies || !( process.env['COOKIES_IGNORE'] === undefined ) ) {
          console.log( "Cookie-free fetch of %s", target );
          await browser.url(target);
        }
        else {
          let browser_p = browser.url(target);
          console.log( "Attempting to reuse previous cookies" );
          await browser.setCookies( cookies )
          await browser_p;
          cookies = await browser.getCookies();
          console.log( "Previous cookies", cookies );
        }

        await sleep(5000);

        // Record the URL as visited
        // Already-visited URLs will not reach this code at all
        visited_pages.set( target, { hits: 1 } ); 

        let markup;
        let loadedUrl = await browser.getUrl();
        let title     = await browser.getTitle();
        cookies       = await browser.getCookies();

        await browser.$('head title').waitUntil(async function() {
          title = await browser.getTitle();
          console.log( "Checking title: '%s'", title);
          return title === "House of Representatives";
        },{
          timeout: 24 * 60 * 60 * 1000, 
          interval: 1000 
        });

        console.log( "Loaded URL %s", loadedUrl );
        console.log( "Page title %s", title );

        if (process.env['SILENT_PARSE'] === undefined) console.log( "Cookies:", cookies );

        if ( interactable.has( target ) ) {
          let m = interactable.get( target );
          markup = await m.method( browser, page_assets, siteParseSettings, m.params );
        }
        else {//{{{
          markup = await browser.$('html').getHTML();
          await writeFile( targetFile, markup, function(err) {
            if ( err ) {
              console.log( "Unable to write %s to %s: %s", loadedUrl, targetFile, err );
            }
            else {
              console.log( "Wrote %s to %s", loadedUrl, targetFile );
            }
          });

          extractedUrls = extract_urls( markup, target, siteParseSettings.get('parse_roots'), have_extracted_urls );

          // Append all page asset URLs to the array of DOM-embedded URLs.
          page_assets.forEach( (headers, urlraw, map) => {
            // URLFIX
            let url = urlraw.replace(/\/\.\.\//,'/').replace(/\/$/,'').replace(/[.]{1,}$/,'').replace(/\/$/,''); 
            if ( (process.env['SILENT_PARSE'] === undefined) ) console.log("%d Adding %s", extractedUrls.length, urlraw );
            if ( (process.env['SILENT_PARSE'] === undefined) ) console.log("%d     as %s", extractedUrls.length, url );
            extractedUrls.push(url);
          });

          iteration_subjects = await extract_hosts_from_urlarray( target, extractedUrls );

          write_map_to_file( "catalog of assets", assetCatalogFile, page_assets  , loadedUrl );
          write_map_to_file( "visited URLs"     , visitFile       , visited_pages, loadedUrl );

        }//}}}

        await writeFile( pageCookieFile, JSON.stringify( cookies, null, 2 ), function(err) {
          if ( err ) {
            console.log( "Unable to jar cookies from %s into %s: %s", loadedUrl, pageCookieFile, err );
          }
          else {
            console.log( "Wrote cookies from %s into file %s", loadedUrl, pageCookieFile );
          }
        });

      }
      else {
        console.log( "! Skipping browser fetch of %s", target ); 
      };//}}}

      if ( iteration_subjects === undefined ) {
        console.log( "Loading page from %s", targetFile );
        let data = readFileSync( targetFile );
        extractedUrls = extract_urls( data, target, siteParseSettings.get('parse_roots'), have_extracted_urls );
        iteration_subjects = await extract_hosts_from_urlarray( target, extractedUrls );
      }

      // Insert entries into targets array
      let recursive = ( process.env["RECURSIVE"] !== undefined );

      if ( step_targets == 0 ) {
        if ( recursive ) {
          console.log("Breadth-first sweep of %d URLs", iteration_subjects.paths.size );
          step_targets = iteration_subjects.paths.size;
          iteration_subjects.paths.forEach((value, urlhere, map) => {
            // URLFIX
            let key = urlhere.replace(/\/\.\.\//,'/').replace(/\/$/,'').replace(/[.]{1,}$/,'').replace(/\/$/,'');
            let content_type = value['headinfo']['content-type'] || 'text/html';
            if ( (!visited_pages.has( key ) || resweep) && /^text\/html.*/.test( content_type ) ) {
              console.log( "%s page scan to %s", recursive ? "Extending" : "Deferring", key );
              targets.unshift( key );
            }
            // Sort the URLs
            targets.sort();
          });
        }
        depth_iterations = 0;
      }
      else {
        let childcount = 0;
        console.log(">>>>>>>>>>>>> Remaining %d", targets.length);
        console.log("+ %d children, %d probables", iteration_subjects.paths.size, depth_plus_one.size );
        iteration_subjects.paths.forEach((value, urlhere, map) => {
          if ( !depth_plus_one.has( urlhere ) ) {
            depth_plus_one.set( urlhere, value );
            childcount++;
          }
        });
        console.log("+ Added %d uniques %d total", childcount, depth_plus_one.size );
        step_targets--;

        if ( targets.length == 0 ) {
          recursion_depth++;
          write_map_to_file( "next-level recursion file", depthPlusOneFile, depth_plus_one, loadedUrl ); 
          if ( recursive ) { 
            depth_plus_one.forEach((value, urlhere, map) => {
              targets.push(urlhere);
            });
            depth_plus_one.clear();
            targets.sort();
          }
        }
      }
      console.log(">>>>>>>>>>>>>>>>>>>>>>> %d", targets.length);

      visited_pages.clear();
      visited_pages = null;

      page_assets.clear();
      page_assets = null;

      fetch_reset();
    }
  });

  console.log( "Resolution deadline: %dms", state_timeout );

  return new Promise((resolve) => {
    setTimeout(() => {
      console.log( "Resolving with object", typeof extractedUrls);
      resolve(targets);
    },state_timeout);
  });
}//}}}

let pagelinks = await fetch_and_extract( targetUrl, 1 );

});

