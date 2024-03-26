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
const node_request_depth = 7;

let outstanding_rr = new Map;
let rr_map = new Map;
let rr_mark = 0; // hrtime.bigint(); 
let mark_steps = 0;

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
  sorter.sort((a,b) => {return a - b;});
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
  //const objson = Object.fromEntries( return_sorted_map(map_obj) );
  console.log( "Writing %s to %s", description, map_file );
  writeFileSync( map_file, inspect(map_obj, {showHidden: false, depth: null, colors: true}), {
  //writeFileSync( map_file, JSON.stringify( objson, null, 2 ), {
    flag  : 'w+',
    flush : true
  }); 
}//}}}

function envSet( v )
{
  return ( process.env[v] !== undefined );
}

async function monitor() {

  let client;
  let nodes_seen = new Map;
  let nodes_tree = new Map;
  let tag_stack = new Array;
  let parents_pending_children = new Array; 
  let waiting_parent = 0;
  let depth = 0;
  let rootnode = 0;
  let completed = false;

  client = await CDP();

  const { Network, Page, DOM } = client;

  //client.DOM.on("setChildNodes", (params) => {
  //  console.log("Received Nodes", params);
  //});

  async function watchdog(cb)
  {//{{{
    return new Promise((resolve) => {
      setTimeout(async () => {
        if ( rr_mark > 0 ) {
          let rr_current = hrtime.bigint();
          let delta = Number((rr_current - rr_mark)/BigInt(1000 * 1000));
          if ( delta > 1000 * rr_timeout_s ) { 
            rr_mark = 0;
            mark_steps = 0;
            console.log("--CLEAR--");
            if ( cb ) await cb( mark_steps );
          }
          else {
            mark_steps++;
            if ( envSet('VERBOSE') ) console.log("--MARK[%d]--", mark_steps, parents_pending_children.length);
            if ( cb ) await cb( mark_steps );
          }
        }
        resolve(true);
      },50);
    });
  }//}}}

  function register_parent_in_waiting( nodeId )
  {//{{{
    // Only call this method 
    // if node.childNodeCount > 0 AND node.children === undefined
    if ( envSet('VERBOSE') ) console.log("ENQUEUE [%d]", nodeId);
    parents_pending_children.push( nodeId );
  }//}}}

  async function recursively_add_and_register( m, parent_nodeId, depth )
  {//{{{
    // Regular inorder tree traversal to populate nodes from 
    // array nodes_seen. Fully populating the tree requires a
    // second traversal to pick up newly-fetched nodes acquired 
    // by triggering requestChildNodes (in trigger_dom_fetch) 
    //
    // Parameters:
    // m: CDP DOM.Node
    // parentNode: Abbreviated node record ID
    // depth: Current recursive call nesting depth
    let parentNode = nodes_seen.get( parent_nodeId ); 
    let has_child_array = (m.children !== undefined) && (m.children.length !== undefined);
    let enqueue_m_nodeid = (m.childNodeCount !== undefined) && !has_child_array && m.childNodeCount > 0;
    if ( !nodes_seen.has(m.nodeId) ) {
      let attrset = m.attributes ? m.attributes : [];
      let attrmap = new Map;
      while ( attrset.length > 0 ) {
        let attr = attrset.shift();
        let attrval = attrset.shift();
        attrmap.set( attr, attrval );
      }
      let isLeaf = !((m.childNodeCount && m.childNodeCount > 0) || has_child_array); 
      nodes_seen.set( m.nodeId, {
        nodeName   : m.nodeName ? m.nodeName : '---',
        parentId   : parent_nodeId,
        attributes : attrmap.size > 0 ? attrmap : null,
        isLeaf     : isLeaf,
        content    : /*new Map */ isLeaf
        ? m.nodeValue
        : new Map
      });
      //attrmap.clear();
      //attrmap = null;
    }

    // Create placeholder flat Map node entry
    parentNode.content.set(m.nodeId, null);
    nodes_seen.set( parent_nodeId, parentNode );

    if ( envSet('VERBOSE') ) console.log("Sub[%d] %s %d <- parent %d children %d",
      depth,
      m.nodeName ? m.nodeName : '---',
      m.nodeId,
      parent_nodeId,
      m.childNodeCount ? m.childNodeCount : 0
      //(await DOM.resolveNode({nodeId: m.nodeId})).object, 
      //(await DOM.getOuterHTML({nodeId: m.nodeId})).outerHTML
      //,m
      //,inspect((await DOM.describeNode({ nodeId: m.nodeId })).node, {showHidden: false, depth: null, colors: true})
      //,inspect(m.children ? m.children : [], {showHidden: false, depth: null, colors: true})
    );

    if ( enqueue_m_nodeid ) {
      register_parent_in_waiting( m.nodeId );
    }

    if ( has_child_array && m.children.length > 0 ) {
      await m.children.forEach(async (c) => { // DOM.Node.children is an array
        return await recursively_add_and_register( c, m.nodeId, depth + 1 );
      });
    }
    return Promise.resolve(true);
  }//}}}

  async function domSetChildNodes(params) 
  {//{{{
    // Populate nodes_seen array with all DOM nodes.
    // Inorder traversal takes care of appending already-fetched nodes.
    const {parentId, nodes} = params;
    const descriptor = (await DOM.resolveNode({nodeId: parentId})).object;


    if ( envSet('VERBOSE') ) console.log( "NodeDSC[%d] %s %d <== parent %d children %d { %s }", 
      nodes_seen.size,
      descriptor.description,
      parentId, 
      waiting_parent,
      nodes.length,
      nodes.map((e) => e.nodeId).join(',')
      //,descriptor
      //,params
    );

    if ( !nodes_seen.has( parentId ) ) {
      let R = (await DOM.describeNode({nodeId: parentId})).node;
      let attrset = R.attributes ? R.attributes : [];
      let attrmap = new Map;
      while ( attrset.length > 0 ) {
        let attr = attrset.shift();
        let attrval = attrset.shift();
        attrmap.set( attr, attrval );
      }
      nodes_seen.set( parentId, {
        nodeName   : R.nodeName,
        parentId   : waiting_parent,
        attributes : attrmap,
        isLeaf     : !(R.childNodeCount && R.childNodeCount > 0),
        content    : R.childNodeCount && R.childNodeCount > 0
          ? new Map 
          : R.nodeValue 
      });
      //attrmap.clear();
      //attrmap = null;
    }

    await nodes.forEach(async (n,nn,node) => {
      await recursively_add_and_register( n, parentId, 0 );
      return true;
    });
    rr_mark = hrtime.bigint();
    return true;
  }//}}}

  function graft( m, depth )
  {//{{{
    // Recursive descent through all nodes to attach all leaves to parents.
    tag_stack.push( m.nodeName );
    if ( m.isLeaf ) {
      console.log("Grafted %d { %s }", 
        depth, 
        tag_stack.join(' '), 
        m.content );
    }
    else if ( m.content && m.content.size && m.content.size > 0 ) {
      let tstk = new Array;
      let attrinfo;
      if ( m.nodeName == 'A' ) {
        let attrarr = new Array();
        m.attributes.forEach((value, key, map) => {
          attrarr.push( key.concat('=',value) );
        });
        attrarr.sort();
        attrinfo = attrarr.join(' ');
        while ( attrarr.length > 0 ) attrarr.shift();
        attrarr = null;
      }
      console.log("Grafted %d | %s >", 
        depth,
        tag_stack.join(' '),
        attrinfo ? attrinfo : ''
      );
      m.content.forEach((value, key, map) => {
        tstk.push( key );
      });
      while ( tstk.length > 0 ) {
        let k = tstk.shift();
        if ( nodes_seen.has(k) ) {
          // Append newly-fetched nodes found in linear map
          // onto this node
          let b = nodes_seen.get(k);
          nodes_seen.delete(k);
          b = graft( b, depth + 1 );
          m.content.set( k, b );
        }
      }
    }
    tag_stack.pop();
    return m;
  }//}}}

  async function trigger_dom_fetch()
  {//{{{
    if ( parents_pending_children.length > 0 ) {
      waiting_parent = parents_pending_children.shift();
      let rq_result = await DOM.requestChildNodes({
        nodeId : waiting_parent,
        depth  : node_request_depth,
        pierce : true
      });
      if ( envSet('VERBOSE') ) console.log("requestChildNodes %d", 
        waiting_parent, 
        nodes_seen.has(waiting_parent), 
        parents_pending_children.length,
        rq_result
      ); 
      rr_mark = hrtime.bigint();
    }
  }//}}}

  async function finalize_metadata( step )
  {//{{{
    // Chew up, digest, dump, and clear captured nodes.
    
    if ( step == 0 ) {

      nodes_seen = return_sorted_map( nodes_seen );
      if ( envSet('VERBOSE') ) console.log( "Pre-update", inspect(nodes_seen, {showHidden: false, depth: null, colors: true}) );
      write_map_to_file("Pre-transform", "pre-transform.json", nodes_seen, "" );
      console.log( "TRANSFORM" );

      let st = new Array;
      let runs = 0;

      // Ensure that child nodes are referenced in .content
      // across all nodes.
      console.log("Fixing cross-references");
      nodes_seen.forEach((value, key, map) => {
        st.push( key );
      });
      while ( st.length > 0 ) {
        let n = st.shift();
        if ( nodes_seen.has(n) ) {
          let s = nodes_seen.get(n);
          nodes_seen.delete(n);
          nodes_seen.forEach((value, key, map) => {
            if ( value.parentId == n ) {
              s.content.set( key, null );
            }
          });
          nodes_seen.set(n, s);
        }
      }

      console.log("Replace leaf node contents");
      nodes_seen.forEach((value, key, map) => {
        st.push( key );
      });
      while ( st.length > 0 ) {
        let n = st.shift();
        let s = nodes_seen.get(n);
        nodes_seen.delete(n);
        if ( s.content.size == 0 ) {
          let sr = {
            nodeName   : s.nodeName,
            parentId   : s.parentId,
            attributes : s.attributes,
            isLeaf     : true,
            content    : (await DOM.getOuterHTML({ nodeId : n })).outerHTML
          };
          s = sr;
        }
        nodes_seen.set(n, s);
      }

      nodes_seen = return_sorted_map( nodes_seen );
      write_map_to_file("Pre-processed", "pre-processed.json", nodes_seen, "" );

      while ( nodes_seen.size > 1 && runs < 10 ) {
        console.log( "Run %d : %d", runs, nodes_seen.size );
        nodes_seen.forEach((value, key, map) => {
          st.push( key );
        });
        while ( st.length > 0 ) {
          let k = st.shift();
          if ( nodes_seen.has( k ) ) {
            let b = nodes_seen.get( k );
            if ( b.parentId > 0 ) {
              console.log("Next %d", k);
              nodes_seen.set( k, graft( b, 0 ) );

              b = nodes_seen.get( k );

              if ( nodes_seen.has( b.parentId ) ) {
                let p = nodes_seen.get( b.parentId );
                p.content.set( k, b );
                nodes_seen.delete( b.parentId );
                nodes_seen.set( b.parentId, p );
                nodes_seen.delete( k );
              }
            } // b.parentId > 0
          } // nodes_seen.has( k )
        } // st.length > 0
        runs++;
      }
      console.log( "Everything", inspect(nodes_seen, {showHidden: false, depth: null, colors: true}) );
      // Clear metadata storage
      write_map_to_file("Everything", "everything.json", nodes_seen, "" );
      nodes_seen.clear();
      nodes_tree.clear();
    }
    else {
      await trigger_dom_fetch();
    }
    return Promise.resolve(true);
  }//}}}

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
      let rootnode_n;
      rootnode = nodeId;
      rr_mark = hrtime.bigint();
      console.log("LOAD EVENT %d", 
        nodeId, 
        ts,
        entries && entries.length > 0 && entries[currentIndex] && entries[currentIndex].url 
        ? entries[currentIndex].url 
        : '---'
      );
      rootnode_n = (await DOM.resolveNode({nodeId: nodeId})).object;
      waiting_parent = nodeId;
      nodes_seen.set( waiting_parent, {
        nodeName   : rootnode_n.description,
        parentId   : 0,
        attributes : new Map,
        isLeaf     : false,
        content    : new Map
      });
      parents_pending_children.unshift( nodeId );
      trigger_dom_fetch();
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
