const { readFileSync, writeFile, writeFileSync, mkdirSync, existsSync, statSync } = require('node:fs');
const assert = require("assert");
const System = require("systemjs");
const cheerio = require("cheerio");
const { createHash } = require("node:crypto");
const url = require("node:url");
const http = require("node:http");
const https = require("node:https");
const { argv, pid, hrtime } = require("node:process");
const { spawnSync } = require("child_process");
const { inspect } = require("node:util");

const CDP = require('chrome-remote-interface');

const targetUrl = process.env.TARGETURL || '';
const rr_timeout_s = 5; // Seconds of inactivity before flushing page metadata 
const node_request_depth = 6;

let outstanding_rr = new Map;
let rr_map = new Map;
let rr_mark = 0; // hrtime.bigint(); 

function networkResponseReceived(params)
{//{{{
  let response = {
    status            : params.response.status,
    statusText        : params.response.statusText,
    headers           : params.response.headers,
    mimeType          : params.response.mimeType,
    charset           : params.response.charset,
    encodedDataLength : params.response.encodedDatalength,
    responseTime      : params.response.responseTime,
    protocol          : params.response.protocol
  };
  if ( rr_map.has( params.requestId ) ) {
    let m = rr_map.get( params.requestId );
    m.response = response;
    console.log("A[%s] %d %s %s", 
      params.requestId, 
      response.status,
      params.response.url, 
      response.mimeType
    );
    rr_map.set( params.requestId, m );
  }
  else {
    console.log("B[%s]", params.requestId, response );
  }
  rr_mark = hrtime.bigint();
}//}}}

function networkRequestWillBeSent(params)
{//{{{
  let markdata = {
    requestId : params.requestId,
    url       : params.request.url,
    method    : params.request.method,
    timestamp : params.timestamp,
    wallTime  : params.wallTime,
    initiator : params.initiator
  };
  if ( !rr_map.has( params.requestId ) ) {
    rr_map.set( params.requestId, {
      url      : markdata.url,
      request  : markdata,
      response : {}
    });
  }
  if ( !outstanding_rr.has( params.requestId ) ) {
    outstanding_rr.set( params.requestId, markdata );
  }
  console.log("Q[%s] %s %s", 
    params.requestId, 
    markdata.method,
    markdata.url
  );
  rr_mark = hrtime.bigint();
}//}}}

function networkLoadingFinished(params)
{//{{{
  if ( outstanding_rr.has( params.requestId ) ) {
    outstanding_rr.delete( params.requestId );
  }
  console.log("L[%s]", params.requestId, outstanding_rr.size );
  rr_mark = hrtime.bigint();
}//}}}

function return_sorted_map( map_obj )
{//{{{
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
}//}}}

function write_map_to_file( description, map_file, map_obj, loadedUrl )
{//{{{
  const objson = Object.fromEntries( return_sorted_map(map_obj) );
  console.log( "Writing %s to %s", description, map_file );
  writeFileSync( map_file, JSON.stringify( objson, null, 2 ), {
    flag  : 'w',
    flush : true
  }); 
}//}}}

async function monitor() {

  let client;
  let nodes_seen = new Map;
  let nodes_tree = new Map;
  let depth = 0;
  let rootnode = 0;
  let completed = false;

  client = await CDP();

  const {Network, Page, DOM} = client;

  async function watchdog(cb)
  {//{{{
    return new Promise((resolve) => {
      setTimeout(async () => {
        if ( rr_mark > 0 ) {
          let rr_current = hrtime.bigint();
          let delta = Number((rr_current - rr_mark)/BigInt(1000 * 1000));
          if ( delta > 1000 * rr_timeout_s ) { 
            rr_mark = 0;
            console.log("--CLEAR--");
            if ( cb ) await cb();
          }
          else {
            console.log("--MARK--");
          }
        }
        resolve(true);
      },1000);
    });
  }//}}}

  async function domSetChildNodes(params) 
  {//{{{
    const {parentId, nodes} = params;
    const descriptor = (await DOM.resolveNode({nodeId: parentId})).object;
    console.log( "Parent %d children %d {%s}", 
      parentId, 
      nodes.length,
      nodes.map((e) => e.nodeId).join(','),
      descriptor
    );
    let parent_node;
    if ( !nodes_seen.has( parentId ) ) {
      nodes_seen.set( parentId, {
        nodeName: descriptor.description,
        parentId: 0,
        branches: new Map 
      });
    }
    parent_node = nodes_seen.get( parentId );

    await nodes.forEach(async (n,nn,node) => {
      console.log("Node[%d] %s %d <- parent %d children %d", 
        nn,
        n.nodeName ? n.nodeName : '---',
        n.nodeId, 
        parentId,
        n.childNodeCount ? n.childNodeCount : 0, 
        (n.childNodeCount && n.childNodeCount <= 1) ? (await DOM.getOuterHTML({nodeId: n.nodeId})).outerHTML : '...',
        (await DOM.resolveNode({nodeId: n.nodeId})).object
      );

      let this_node;
      if ( !nodes_seen.has(n.nodeId) ) {
        nodes_seen.set( n.nodeId, {
          nodeName: n.nodeName ? n.nodeName : '---',
          parentId: parentId,
          branches: new Map 
        });
      }
      this_node = nodes_seen.get(n.nodeId);

      parent_node.branches.set( n.nodeId, null );
      nodes_seen.set( parentId, parent_node );

      try {
        if ( n.children && n.children.length > 0 ) {
          n.children.forEach(async function(m,nm,n_) {
            if ( m && m.nodeId ) {
              let child_node;
              if ( !nodes_seen.has(m.nodeId) ) {
                nodes_seen.set(m.nodeId, {
                  nodeName: m.nodeName ? m.nodeName : '---',
                  parentId: n.nodeId,
                  branches: new Map 
                });
              }
              child_node = nodes_seen.get(m.nodeId);

              this_node.branches.set(m.nodeId, null);
              nodes_seen.set( n.nodeId, this_node );

              console.log("Sub[%d] %s %d <- parent %d children %d",
                nm,
                m.nodeName ? m.nodeName : '---',
                m.nodeId,
                n.nodeId,

                m.childNodeCount ? m.childNodeCount : 0,
                (await DOM.resolveNode({nodeId: m.nodeId})).object, 
                (await DOM.getOuterHTML({nodeId: m.nodeId})).outerHTML
                // m.childNodeCount == 0 ? await DOM.getOuterHTML({nodeId: m.nodeId}) : '...'
              );
              if ( m.childNodeCount && m.childNodeCount > 0 )
                await DOM.requestChildNodes({nodeId: m.nodeId, depth: node_request_depth});
            }
            return true;
          });
        }
      }
      catch(e) {
        console.log('Err', e);
      }

      return true;
    });

    rr_mark = hrtime.bigint();
    return true;
  }//}}}

  function graft( m, depth )
  {
    if ( m.branches.size > 0 ) {
      let tstk = new Array;
      m.branches.forEach((value, key, map) => {
        tstk.push( key );
      });
      while ( tstk.length > 0 ) {
        let k = tstk.shift();
        if ( nodes_seen.has(k) ) {
          let b = nodes_seen.get(k);
          nodes_seen.delete(k);
          b = graft( b, depth + 1 );
          m.branches.set( k, b );
        }
      }
      console.log("Grafted", m.branches, depth );
    }
    return m;
  }

  async function finalize_metadata()
  {
    // Chew up, digest, dump, and clear captured nodes.
    
    console.log( "Pre-update", nodes_seen );
    console.log( "TRANSFORM" );
    let st = new Array;
    nodes_seen.forEach((value, key, map) => {
      st.push( key );
    });
    while ( st.length > 0 ) {
      let k = st.shift();
      if ( nodes_seen.has( k ) ) {
        let b = nodes_seen.get( k );
        console.log("Next %d", k);
        b = graft( b, 0 );
        nodes_seen.set( k, b );
      }
    }
    console.log( "Everything", inspect(nodes_seen, {showHidden: false, depth: null, colors: true}) );
    // Clear metadata storage
    write_map_to_file("Everything", "everything.json", nodes_seen, "" );
    nodes_seen.clear();
    nodes_tree.clear();

    return Promise.resolve(true);
  }

  try {

    Network.requestWillBeSent(networkRequestWillBeSent);
    Network.responseReceived(networkResponseReceived);
    Network.loadingFinished(networkLoadingFinished);
    DOM.setChildNodes(domSetChildNodes);
    await Page.windowOpen(async (wo) => {
      console.log("windowOpen", wo);
    });

    await Page.loadEventFired(async (ts) => {
      const { currentIndex, entries } = await Page.getNavigationHistory();
      const {root:{nodeId}} = await DOM.getDocument({ pierce: true });
      rootnode = nodeId;
      rr_mark = hrtime.bigint();
      console.log("LOAD EVENT %d", 
        nodeId, 
        ts,
        entries && entries.length > 0 && entries[currentIndex] && entries[currentIndex].url 
        ? entries[currentIndex].url 
        : '---'
      );
      await DOM.requestChildNodes({nodeId: nodeId, depth: node_request_depth});
    });

    await Page.domContentEventFired(async (ts) => {
      console.log("DOM Content Event", ts );
    });

    await Page.lifecycleEvent(async (p) => {
      console.log("Lifecycle", p);
    });

    await Network.enable();
    await DOM.enable({ includeWhitespace: "none" });
    await Page.enable();

    nodes_seen.clear();

    // Trigger a page fetch, else stand by for browser interaction or timeout
    if ( targetUrl.length > 0 ) {
      await Page.navigate({url: targetUrl});
    }

    while ( !completed ) {
      await watchdog( finalize_metadata );
    }

  } catch (err) {
    console.error(err);
  } finally {
    if (client) {
      console.log( "Close opportunity ended" );
      // client.close();
    }
  }
  return Promise.resolve(true);
}

monitor();
