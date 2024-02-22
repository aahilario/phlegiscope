const { remote } = require("webdriverio");

const fs = require('fs');
const assert = require("assert");
const System = require("systemjs");
const cheerio = require("cheerio");
const url = require("node:url");
const http = require("node:http");
const https = require("node:https");

//const targetUrl = 'https://congress.gov.ph/legisdocs/?v=bills#HistoryModal';
//const element_xpath = '/html/body/div[2]/div/div[1]/div[2]';
//const targetFile = 'bills-text.html'; 
//const sampleCount = 100;

//const targetUrl     = 'https://congress.gov.ph/legisdocs/?v=bills';
const targetUrl     = 'https://congress.gov.ph/';
const element_xpath = '/html';
const sampleCount   = 0;
const loggedTags = new Map([
  [ "LINK", 0 ],
  [ "A"   , 0 ]
]);
let targetFile       = ''; // 'congress.gov.ph.html';
let assetCatalogFile = '';
let pageCookieFile   = '';
let targetDir        = '';

const pad = " ";

//let browser;
let loadedUrl;
let parsedUrl;
let pageUrls = new Map;
let pageHosts = new Map;
let extractedUrls; 
let cookies;

function normalizeUrl( u )
{//{{{
  let fromPage = url.parse(u);
  // Only fill in missing URL components from corresponding targetUrl parts
  for ( e in parsedUrl ) {
    let val = parsedUrl[e] || '';
    if ( val.length > 0 && (fromPage[e] || '').length == 0 ) {
      fromPage[e] = parsedUrl[e];
    }
  }
  return ''.concat(fromPage.protocol,'//',fromPage.hostname.concat('/',fromPage.path).replace(/[\/]{1,}/gi,'/').replace(/\/\.\//,'/').replace(/\/$/,''));
}//}}}

function stashPageUrl( u )
{//{{{
  pageUrls.set(urlText, (pageUrls.get(urlText) || 0) + 1);
}//}}}

function stashUrlDomain( u )
{//{{{
  let p = url.parse(u);
  let h = p.host;
  if ( h.length ) {
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
        console.log( "%s%d ^[%s] > '%s'", pad.repeat(depth<<1), index, parentName, String(element.data).trim() );
      }
    }
  }
  else if ( tagname == 'META' ) {
    let attrlist = new Array;
    for ( a in element.attribs ) {
      attrlist.push( a.concat(': ',element.attribs[a]) );
    }
    if ( logthis ) console.log( "%s%d: %s{%s}", pad.repeat(depth<<1), index, tagname, attrlist.join(', ') );
  }
  else if ( tagname == 'LINK' ) {
    urlText = normalizeUrl( $("*").attr('href') );
    if ( logthis ) console.log( "%s%d: %s %s", pad.repeat(depth<<1), index, tagname, $("*").attr('rel').concat(": ",urlText) );
    stashPageUrl( urlText );
    stashUrlDomain( urlText );
  }
  else if ( tagname == 'A' ) {
    urlText = normalizeUrl( $("*").attr('href') );
    if ( logthis ) console.log( "%s%d: %s{%s} %s", pad.repeat(depth<<1), index, tagname, className, urlText );
    stashPageUrl( urlText );
    stashUrlDomain( urlText );
  }
  else if ( tagname == 'SCRIPT' ) {
    urlText = normalizeUrl( $("*").attr('src') );
    if ( logthis ) console.log( "%s%d: %s %s", pad.repeat(depth<<1), index, tagname, ($("*").attr('type') || '').concat(": ",urlText) );
    stashPageUrl( urlText );
    stashUrlDomain( urlText );
  }
  else if ( tagname == 'BR' ) {
    if ( logthis ) console.log( "%s%d: ---------------------------------------", pad.repeat(depth<<1), index );
  }
  else if ( tagname.match(/(DIV|P|SPAN|LI)/) ) {
    if ( logthis ) console.log( "%s%d: %s{%s}", pad.repeat(depth<<1), index, tagname, className );
  }
  else if ( loggedTags.has("*") ) {
    console.log( "%s%d: %s", pad.repeat(depth<<1), index, tagname );
  }
  } catch(e) {
  }

  // Recurse to a reasonable tag nesting depth
  if ( depth < 20 ) {
    $(tagname).contents().each(function (i, e) {
      detag( i, e, depth + 1, $(tagname), indexlimit );
    });
    // Newline between containing chunks
    if ( 0 == depth ) console.log( "" );
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

    fs.writeFile( urlListFile, JSON.stringify( pageUrlsArray ), function(err) {
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
{
  let cookiearray = new Array;
  for ( i in ca ) {
    let co = ca[i];
    let ck_name = co.name || null;
    let ck_val  = co.value || null;
    if ( !ck_name || !ck_val ) continue;
    cookiearray.push( ck_name.concat( '=', ck_val ) );
  }
  return cookiearray.join('; ');
}

function mapified_cookies( ca )
{
  let cookiemap = new Map;
  for ( i in ca ) {
    let co = ca[i];
    let ck_name = co.name || null;
    let ck_val  = co.value || null;
    if ( !ck_name || !ck_val ) continue;
    cookiemap.set( ck_name, ck_val );
  }
  return cookiemap;
}

function keep_unique_host_path( u, result, unique_host_path, unique_entry, head_info )
{
  let content_type = head_info['content-type'];
  // Retain unique paths and hosts
  if ( unique_host_path.get( unique_entry ) === undefined ) {
    unique_host_path.set( unique_entry, {
      hits: 1, 
      headinfo: head_info
    });
  }
  else {
    let prior_entry = unique_host_path.get( unique_entry );
    unique_host_path.set( unique_entry, {
      hits: prior_entry.hits + 1,
      headinfo: prior_entry.headinfo
    });
  }
  console.log("%d:\tHost %s type '%s' pathname '%s' query %s hash %s", 
    result.length,
    u.host,
    content_type,
    u.pathname, 
    u.query ? "'".concat(u.query,"'") : '<null>',
    u.hash || '<null>'
  );
}

async function extract_hosts_from_urlarray( target_url, result )
{//{{{
  const head_standoff = 100;
  let unique_host_path = new Map;
  let unique_hosts = new Map;

  // For writing lists of permitted hosts, hosts referred on page, and link HEAD info
  // targetDir is updated in fetch_and_extract()
  let permitted_hosts = targetDir.concat( '/', 'permitted.json' );
  let current_hosts = targetDir.concat( '/', 'hosts.json' );
  let linkinfo = targetDir.concat( '/', 'linkinfo.json' );
  let fileProps = fs.statSync( permitted_hosts, { throwIfNoEntry: false } );

  // Obtain any preexisting permitted hosts list from file
  if ( fileProps ) {
    let ofile = fs.readFileSync(permitted_hosts);
    let o = JSON.parse( ofile );
    unique_hosts = new Map(Object.entries(o));
    console.log( "Located permitted hosts list %s", permitted_hosts, unique_hosts );
  }
  // Extract reachable hosts and unique paths lists

  while ( result.length > 0 )
  {//{{{

    let g = result.shift();
    let u = url.parse( g );

    let unique_entry = u.protocol.concat("//", u.hostname, u.pathname);
    let unique_host  = u.host;
    let head_options;

    // Issue HEAD requests to each URL when cookies are available
    // from a prior run executing fetch_and_extract( target_url ); 

    head_options = {
      method: "HEAD",
      headers: {
        "User-Agent"                : "Mozilla/5.0 rX11; Linux x86_64; rv: 109.0) Gecko/20100101 Firefox/115.0",
        "Accept"                    : "text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8",
        "Accept-Language"           : "en-US,en;q=0.5",
        "Accept-Encoding"           : "gzip, deflate, br",
        "Connection"                : "keep-alive",
        "Referer"                   : target_url,
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

    if ( !fileProps || unique_hosts.has( u.host ) ) {
      let head_info;
      if ( u.protocol == 'https:' ) {
        try {
          await https.request( g, head_options, (res) => {
            console.log("Intrinsic A", g, res.headers);
            if ( head_info === undefined ) {
              head_info = res.headers;
              keep_unique_host_path( u, result, unique_host_path, unique_entry, head_info );
            }
            res.on('data', (d) => {
              console.log('Data from', g, d);
            });
          }).on('error', (e) => {
            console.log("Error", g, e);
          }).on('response', (m) => {
            if ( head_info === undefined ) {
              head_info = m.headers;
              keep_unique_host_path( u, result, unique_host_path, unique_entry, head_info );
            }
          }).end();
          if ( head_standoff ) await sleep(head_standoff);
        } catch (e) {
          console.log("CATCH", g, e);
        }
      }
      else if ( u.protocol == 'http:' ) {
        try {
          await http.request( g, head_options, (res) => {
            console.log("Intrinsic B", g, res.headers);
            if ( head_info === undefined ) {
              head_info = res.headers;
              keep_unique_host_path( u, result, unique_host_path, unique_entry, head_info );
            }
            res.on('data', (d) => {
              console.log('Data from', g, d);
            });
          }).on('error', (e) => {
            console.log("Error", g, e);
          }).on('response', (m) => {
            if ( head_info === undefined ) {
              head_info = m.headers;
              keep_unique_host_path( u, result, unique_host_path, unique_entry, head_info );
            }
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

  console.log( "Obtained %d unique pathnames (sans URL query part)", unique_host_path.size, unique_host_path );
  console.log( "Obtained %d unique hostnames", unique_hosts.size, unique_hosts );

  console.log( "Target path: %s", targetDir );

  // Write list of permitted hosts 
  const objson = Object.fromEntries( unique_hosts );

  if ( !fileProps ) {
    console.log( "Creating permitted hosts list %s", permitted_hosts );
    await fs.writeFile( permitted_hosts, JSON.stringify( objson, null, 2 ), function(err) {
      if ( err ) {
        console.log( "Unable to write '%s' fresh permitted hosts list to %s", target_url, permitted_hosts );
      }
      else {
        console.log( "Wrote permitted hosts list for '%s' to %s", target_url, permitted_hosts );
      }
    });
  }

  await fs.writeFile( current_hosts, JSON.stringify( objson, null, 2 ), { flag: 'w' }, function(err) {
    if ( err ) {
      console.log( "Unable to write '%s' reachable hosts list to %s", target_url, current_hosts );
    }
    else {
      console.log( "Wrote reachable hosts list for '%s' to %s", target_url, current_hosts );
    }
  }); 

  // Write linkinfo.json containing unique_host_path

  const headinfo = Object.fromEntries( unique_host_path );
  await fs.writeFile( linkinfo, JSON.stringify( headinfo, null, 2 ), { flag: 'w' }, function(err) {
    if ( err ) {
      console.log( "Unable to write unique HEAD info from links in '%s' to %s", target_url, linkinfo );
    }
    else {
      console.log( "Wrote HEAD info for unique links in '%s' to %s", target_url, linkinfo );
    }
  });

  return new Promise((resolve) => {
    console.log('Done');
    resolve({ paths: unique_host_path, hosts: unique_hosts });
  });

}//}}}

function fetch_and_extract( target )
{
  // Walk through all nodes in DOM

  let state_timeout = 1800000;
  let page_assets = new Map;

  parsedUrl = url.parse(target);

  console.log( "Target: %s", target );

  // Generate targetFile path based on URL path components.
  if ( 0 == targetFile.length ) {//{{{
    let relativePath = new Array();
    let pathParts = parsedUrl.host.concat('/', parsedUrl.path).replace(/[\/]{1,}/gi,'/').replace(/[\/]{1,}$/,'').replace(/^[\/]{1,}/,'').split('/');
    targetDir = '';
    while ( pathParts.length > 0 ) {
      let part = pathParts.shift();
      relativePath.push(part);
      targetDir = relativePath.join('/');
      if ( fs.existsSync( targetDir ) ) continue;
      fs.mkdirSync( targetDir );
    }
    targetFile = targetDir.concat('/index.html');
    assetCatalogFile = targetDir.concat('/index.assets.json'); 
    console.log( 'Target path computed as %s', targetFile );
  }//}}}

  // Record this URL as the first unique entry
  pageUrls.set( target, 1 ); 

  let fileProps;
  
  try {
    fileProps = fs.statSync( targetFile );
    console.log( "Target '%s' props:", targetFile, fileProps );
  } catch(e) {
    console.log("Must fetch '%s' from %s", targetFile, target );
    fileProps = null;
  }

  if ( !fileProps ) {

    // state_timeout = 1800000;

    it('Fetch '.concat(target), async function () {

      await browser.setTimeout({ 'script': state_timeout });

      // Enable capture of HTTP requests and responses
      // https://stackoverflow.com/questions/61569000/get-all-websocket-messages-in-protractor/62210198#62210198 2024-02-21
      await browser.cdp('Network', 'enable');
      await browser.on('Network.requestWillBeSent', (event) => {
        console.log(`Request: ${event.request.method} ${event.request.url}`);
      });
      await browser.on('Network.responseReceived', (event) => {
        console.log(`Response: ${event.response.status} ${event.response.url}`, event.response.headers);
        page_assets.set( event.response.url, event.response.headers );
      });

      console.log( "Browser status", await browser.status() );

      await browser.url(target);

      let title = await browser.getTitle();
      let loadedUrl = await browser.getUrl(); 
      cookies = await browser.getCookies();

      console.log( "Loaded URL %s", loadedUrl );
      console.log( "Page title %s", title );
      console.log( "Cookies:", cookies );

      assert.equal("House of Representatives", title);
     
      let markup = await browser.$('html').getHTML();
      
      await fs.writeFile( targetFile, markup, function(err) {
        if ( err ) {
          console.log( "Unable to write %s to %s: %s", loadedUrl, targetFile, err );
        }
        else {
          console.log( "Wrote %s to %s", loadedUrl, targetFile );
        }
      });

      extractedUrls = extract_urls( markup, target );
      
      // Prepend all page asset URLs to the array of DOM-embedded URLs.
      page_assets.forEach( (headers, url, map) => {
        console.log("%d Adding %s", extractedUrls.length, url );
        extractedUrls.push(url);
      });

      await extract_hosts_from_urlarray( target, extractedUrls );

      const objson = Object.fromEntries( page_assets );

      await fs.writeFile( assetCatalogFile, JSON.stringify( objson, null, 2 ), { flag: 'w' }, function(err) {
        if ( err ) {
          console.log( "Unable to write catalog of assets '%s' at %s", assetCatalogFile, loadedUrl );
        }
        else {
          console.log( "Wrote catalog of assets '%s' at %s", assetCatalogFile, loadedUrl );
        }
      }); 
      state_timeout = 0;
    });
  }
  else {
    // state_timeout = 20000;
    data = fs.readFileSync(targetFile);
    it("Parse ".concat(target), async function() {
      await browser.setTimeout({ 'script': state_timeout });
      extractedUrls = extract_urls( data, target );
      await extract_hosts_from_urlarray( target, extractedUrls );
      state_timeout = 0;
    });
  }
  console.log( "Resolution deadline: %dms", state_timeout );
  
  return new Promise((resolve) => {
    setTimeout(() => {
      console.log( "Resolving with object", typeof extractedUrls);
      resolve(extractedUrls);
    },state_timeout);
  });
}

function sleep( millis )
{
  return new Promise((resolve) => {
    setTimeout(() => {
      resolve(true);
    },millis);
  });
}

async function execute_extraction( target_url ) {

  // Invoke fetch_and_extract( targetUrl );
  console.log("Setting up");
  const result = await fetch_and_extract( target_url );
  console.log("Tearing down");
  return result;
}

let pagelinks = execute_extraction( targetUrl );

(async function() {
  await pagelinks;
});

  console.log( "Booyah", pagelinks );

