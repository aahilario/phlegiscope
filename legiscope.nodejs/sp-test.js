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
const { browser, $, $$, expect } = require("@wdio/globals");
const { readFileSync, writeFile, writeFileSync, mkdirSync, existsSync, statSync } = require('node:fs');
const assert = require("assert");
const System = require("systemjs");
const cheerio = require("cheerio");
const url = require("node:url");
const http = require("node:http");
const https = require("node:https");
const { argv, pid } = require("node:process");
const { spawnSync } = require("child_process");
const controller = new AbortController();
const { signal } = controller;

const targetUrl     = process.env.TARGETURL || 'https://congress.gov.ph/';
const element_xpath = '/html';
const sampleCount   = 0;

const loggedTags = new Map([
  [ "SCRIPT", 0 ], // This allows inlined scripts to be dumped
  [ "LINK", 0 ],
  [ "A"   , 0 ]
]);

const pad = " ";

let targetFile       = '';
let visitFile        = '';
let permittedHosts  = '';
let assetCatalogFile = '';
let pageCookieFile   = '';
let targetDir        = '';

let loadedUrl;
let parsedUrl;
let pageUrls = new Map;
let pageHosts = new Map;
let extractedUrls; 
let cookies;
let root_request_header;

function fetch_reset()
{
  // Invoke to reset state before fetching a new URL
  targetFile          = '';
  // visitFile           = '';
  assetCatalogFile    = '';
  pageCookieFile      = '';
  targetDir           = '';

  loadedUrl           = null;
  parsedUrl           = null;
  pageUrls            = null
  pageUrls            = new Map;
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

function normalizeUrl( u )
{//{{{
  // URLFIX
  // Only fill in missing URL components from corresponding targetUrl parts
  let fromPage = url.parse(u);
  //console.log( "A> %s", parsedUrl.href || '' );
  //console.log( "B> %s", u || '' );
  if ( (fromPage.protocol || '').length == 0 && (fromPage.hostname || '').length == 0 ) {
    fromPage.path = [parsedUrl.path, fromPage.path].join('/');
    //console.log( "K> %s", fromPage.path );
  }
  if ( (fromPage.protocol || '').length == 0 ) fromPage.protocol = parsedUrl.protocol;
  if ( (fromPage.host     || '').length == 0 ) fromPage.host     = parsedUrl.host;
  if ( (fromPage.hostname || '').length == 0 ) fromPage.hostname = parsedUrl.hostname;
  fromPage.href = ''.concat(fromPage.protocol,'//',fromPage.hostname.concat('/',fromPage.path).replace(/[\/]{1,}/gi,'/').replace(/\/([^\/]{1,})\/\.\.\//,'/').replace(/\/$/,''));
  //console.log( "C> %s", fromPage.href );
  return fromPage.href;
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

function detag( index, element, depth = 0, elementParent = null, indexlimit = 0  )
{//{{{

  const $ = cheerio.load( element );
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
      if ( undefined !== typeof srcattr ) {
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
    console.log( "THROWN processing %s", tagname, urlText, e );
  }

  // Recurse to a reasonable tag nesting depth
  if ( depth < 20 ) {
    $(tagname).contents().each(function (i, e) {
      detag( i, e, depth + 1, $(tagname), indexlimit );
    });
    // Newline between containing chunks
    if ( (process.env['SILENT_PARSE'] === undefined) ) if ( 0 == depth ) console.log( "" );
  }
  return true;
};//}}}

function extract_urls( data, target )
{//{{{
  const $ = cheerio.load( data );
  console.log("Fetched markup.  Parsing...");
  $('html')
    .children()
    .each( function(i,e) { detag(i,e,0,null,sampleCount); });

  let pageUrlsArray = new Array;

  if ( pageUrls.size > 0 ) {

    urlListFile = targetFile.concat(".urls.json");
    console.log("Dumping %d URLs rooted at '%s'", pageUrls.size, target ); 

    for ( const url of pageUrls.keys() ) {
      pageUrlsArray.push( url );
    }

    writeFile( urlListFile, JSON.stringify( pageUrlsArray ), function(err) {
      if ( err ) {
        console.log( "Failed to write URLs from page at '%s'", target );
      }
      else {
        console.log( "Wrote %d URLs from '%s' into '%s'", pageUrls.size, target, urlListFile );
      }
    });
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
  let content_type = head_info['content-type'];
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
      //,headinfo_raw: head_info
    });
  }
  else {
    let prior_entry = unique_host_path.get( unique_entry );
    unique_host_path.set( unique_entry, {
      hits: prior_entry.hits + 1,
      headinfo: prior_entry.headinfo
    });
  }
  if ( (process.env['SILENT_PARSE'] === undefined) ) console.log("%d:\t%s %s %s",
    result.length,
    content_type,
    head_info['content-length'],
    u.href
  );
  //console.log("%d:\tHost %s type '%s' (%db) pathname '%s' %s %s", 
  //  result.length,
  //  u.host,
  //  content_type,
  //  head_info['content-length'],
  //  u.pathname, 
  //  u.query ? "query '".concat(u.query,"' ") : '',
  //  u.hash ? "hash '".concat(u.hash,"'") : '',
  //  u.href 
  //);
}//}}}

function write_map_to_file( description, map_file, map_obj, loadedUrl )
{//{{{
  const objson = Object.fromEntries( map_obj );
  writeFileSync( map_file, JSON.stringify( objson, null, 2 ), {
    flag  : 'w',
    flush : true
  }); 
}//}}}

async function extract_hosts_from_urlarray( target_url, result )
{//{{{
  const head_standoff = 50;
  let unique_host_path = new Map;
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

    head_options = {
      method: "HEAD",
      headers: {
        "User-Agent"                : root_request_header && root_request_header['User-Agent'] ? root_request_header['User-Agent'] : "Mozilla/5.0 rX11; Linux x86_64; rv: 109.0) Gecko/20100101 Firefox/115.0",
        "Accept"                    : "text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8",
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
    }

    let content_type = null;

    // Determine asset mimetype
    if ( !fileProps || unique_hosts.has( u.host ) ) {
      let head_info;
      //if ( process.env.SKIPHEADINFO !== undefined ) {
      //  keep_unique_host_path( u, result, unique_host_path, unique_entry, head_info );
      //}
      //else
      if ( u.protocol == 'https:' ) {
        try {
          await https.request( g, head_options, (res) => {
            // console.log("Intrinsic A", g, res.headers);
            if ( head_info === undefined ) {
              head_info = res.headers;
              keep_unique_host_path( u, result, unique_host_path, unique_entry, head_info );
            }
            res.on('data', (d) => {
              if ( (process.env['SILENT_PARSE'] === undefined) ) console.log('Data from', g, d);
            });
          }).on('error', (e) => {
            if ( (process.env['SILENT_PARSE'] === undefined) ) console.log("Error", g, e);
          }).on('response', (m) => {
            if ( head_info === undefined ) {
              head_info = m.headers;
              keep_unique_host_path( u, result, unique_host_path, unique_entry, head_info );
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
              keep_unique_host_path( u, result, unique_host_path, unique_entry, head_info );
            }
            res.on('data', (d) => {
              if ( (process.env['SILENT_PARSE'] === undefined) ) console.log('Data from', g, d);
            });
          }).on('error', (e) => {
            if ( (process.env['SILENT_PARSE'] === undefined) ) console.log("Error", g, e);
          }).on('response', (m) => {
            if ( head_info === undefined ) {
              head_info = m.headers;
              keep_unique_host_path( u, result, unique_host_path, unique_entry, head_info );
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

  console.log( "Obtained %d unique pathnames (sans URL query part)", unique_host_path.size, (process.env['SILENT_PARSE'] === undefined) ? unique_host_path : '' );
  console.log( "Obtained %d unique hostnames", unique_hosts.size, (process.env['SILENT_PARSE'] === undefined) ? unique_hosts : '' );

  console.log( "Target path: %s", targetDir );

  // Write permitted and currently reachable URL lists 
  if ( !fileProps ) {
    write_map_to_file( "permitted hosts list", permittedHosts, unique_hosts, target_url );
  }
  write_map_to_file( "reachable hosts list", current_hosts, unique_hosts, target_url ); 

  // Write linkinfo.json containing unique_host_path
  write_map_to_file( "unique HEAD info from links", linkinfo, unique_host_path, target_url );

  return new Promise((resolve) => {
    console.log('Done. Found %d unique host paths, %d unique hosts', unique_host_path.size, unique_hosts.size );
    resolve({ paths: unique_host_path, hosts: unique_hosts });
  });

}//}}}

function load_visit_map( visit_file )
{//{{{
  let visited_map;
  let ofile = readFileSync( visit_file, { flag: 'r' } );
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
  let pathParts = parsedUrl.host.concat('/', parsedUrl.path).replace(/[\/]{1,}/gi,'/').replace(/[\/]{1,}$/,'').replace(/^[\/]{1,}/,'').replace(/\/\.\.\//,'/').replace(/\/$/,'').split('/');
  let pathComponent = 0; 
  targetDir = '';

  // Unique visited URL and permitted hosts files
  visitFile = parsedUrl.host.concat('/visited.json');
  permittedHosts = parsedUrl.host.concat('/permitted.json');

  while ( pathParts.length > 0 ) {
    let part = pathParts.shift();
    relativePath.push(part);
    targetDir = relativePath.join('/');
    pathComponent++;
    if ( existsSync( targetDir ) ) continue;
    mkdirSync( targetDir );
  }
  targetFile = targetDir.concat('/index.html');
  assetCatalogFile = targetDir.concat('/index.assets.json'); 
  pageCookieFile = targetDir.concat('/cookies.json'); 
  console.log( 'Target path computed as %s', targetFile );
  console.log( ' Asset catalog %s', assetCatalogFile );
  console.log( ' Page cookies %s', pageCookieFile );
  console.log( ' Visited URLs %s', visitFile  );
  console.log( ' Permitted hosts %s', permittedHosts  );
}//}}}

async function fetch_and_extract( initial_target, depth )
{//{{{
  // Walk through all nodes in DOM

  let state_timeout = 7200000;
  let targets = new Array;

  // Breadth-first link traversal state flags
  let iteration_depth = 0;
  let step_targets = 0;
  let resweep = (process.env.REFRESH !== undefined);

  it('Scrape starting at '.concat(initial_target), async function () {

    targets.push( initial_target );

    while ( targets.length > 0 ) {

      // URLFIX
      let target = targets.shift().replace(/\/\.\.\//,'/').replace(/\/$/,'').replace(/[.]{1,}$/,'').replace(/\/$/,'');
      let page_assets = new Map;
      let iteration_subjects;
      let visited_pages;

      fetch_reset();

      recompute_filepaths_from_url(target);

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
        fileProps = statSync( targetFile, { throwIfNoEntry: true } );
        console.log( "Target '%s' props:", targetFile, fileProps );
      } catch(e) {
        console.log("Must fetch '%s' from %s", targetFile, target );
        fileProps = null;
      }

      console.log( "%s url %s", visited ? "Already visited" : "Unvisited", target );

      if ( !fileProps || !visited || resweep ) 
      {//{{{

        console.log( "Fetching from %s", target );

        ////////////////////////////////
        loadCookies();

        await browser.setTimeout({ 'script': state_timeout });

        // Enable capture of HTTP requests and responses
        // https://stackoverflow.com/questions/61569000/get-all-websocket-messages-in-protractor/62210198#62210198 2024-02-21
        await browser.cdp('Network', 'enable');
        await browser.on('Network.requestWillBeSent', (event) => {
          if ( event.request.url == target )
            if ( (process.env['SILENT_PARSE'] === undefined) ) console.log(`Request: ${event.request.method} ${event.request.url}`, event.request.headers);
          root_request_header = event.request.headers;
          page_assets.set( event.request.url, {
            status: null, 
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
            if ( (process.env['SILENT_PARSE'] === undefined) ) console.log(`Response: ${event.response.status} ${event.response.url}`, pair);
          }
          else {
            if ( (process.env['SILENT_PARSE'] === undefined) ) console.log(`Unpaired Response: ${event.response.status} ${event.response.url}`, pair);
            page_assets.set( event.response.url, {
              status: event.response.status,
              req: null,
              res: event.response.headers
            });
          }
        });

        console.log( "Browser status", await browser.status() );

        if ( !cookies ) {
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

        // Record the URL as visited
        // Already-visited URLs will not reach this code at all
        visited_pages.set( target, { hits: 1 } ); 

        let title = await browser.getTitle();
        let loadedUrl = await browser.getUrl(); 
        cookies = await browser.getCookies();

        console.log( "Loaded URL %s", loadedUrl );
        console.log( "Page title %s", title );
        if (process.env['SILENT_PARSE'] === undefined) console.log( "Cookies:", cookies );

        // assert.equal("House of Representatives", title);

        let markup = await browser.$('html').getHTML();

        await writeFile( pageCookieFile, JSON.stringify( cookies, null, 2 ), function(err) {
          if ( err ) {
            console.log( "Unable to jar cookies from %s into %s: %s", loadedUrl, pageCookieFile, err );
          }
          else {
            console.log( "Wrote cookies from %s into file %s", loadedUrl, pageCookieFile );
          }
        });

        await writeFile( targetFile, markup, function(err) {
          if ( err ) {
            console.log( "Unable to write %s to %s: %s", loadedUrl, targetFile, err );
          }
          else {
            console.log( "Wrote %s to %s", loadedUrl, targetFile );
          }
        });

        extractedUrls = extract_urls( markup, target );

        // Prepend all page asset URLs to the array of DOM-embedded URLs.
        page_assets.forEach( (headers, urlraw, map) => {
          // URLFIX
          let url = urlraw.replace(/\/\.\.\//,'/').replace(/\/$/,'').replace(/[.]{1,}$/,'').replace(/\/$/,''); 
          if ( (process.env['SILENT_PARSE'] === undefined) ) console.log("%d Adding %s", extractedUrls.length, urlraw );
          if ( (process.env['SILENT_PARSE'] === undefined) ) console.log("%d     as %s", extractedUrls.length, url );
          extractedUrls.push(url);
        });

        iteration_subjects = await extract_hosts_from_urlarray( target, extractedUrls );

        write_map_to_file( "catalog of assets", assetCatalogFile, page_assets, loadedUrl );
        write_map_to_file( "visited URLs", visitFile, visited_pages, loadedUrl );
      };//}}}

      if ( iteration_subjects === undefined ) {
        let data = readFileSync(targetFile);
        extractedUrls = extract_urls( data, target );
        iteration_subjects = await extract_hosts_from_urlarray( target, extractedUrls );
      }

      // Insert entries into targets array
      let recursive = ( process.env["RECURSIVE"] !== undefined );

      if ( step_targets == 0 ) {
        console.log("Breadth-first sweep of %d URLs", iteration_subjects.paths.size );
        step_targets = iteration_subjects.paths.size;
        iteration_subjects.paths.forEach((value, urlhere, map) => {
          // URLFIX
          let key = urlhere.replace(/\/\.\.\//,'/').replace(/\/$/,'').replace(/[.]{1,}$/,'').replace(/\/$/,'');
          let content_type = value['headinfo']['content-type'];
          if ( (!visited_pages.has( key ) || resweep) && /^text\/html.*/.test( content_type ) ) {
            console.log( "%s page scan to %s", recursive ? "Extending" : "Deferring", key );
            if ( recursive ) {
              targets.unshift( key );
            }
          }
        });
      }
      else {
        step_targets--;
        console.log(">>>>>>>>>>>>>>>>>>>>>>> %d", targets.length);
      }

      visited_pages = null;
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

