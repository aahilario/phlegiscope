const { readFileSync, writeFile, writeFileSync, mkdirSync, existsSync, statSync, linkSync, unlinkSync, symlinkSync } = require('node:fs');
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
let latest_rr = 0;
let xhr_fetch_rr = 0;
let append_buffer_to_rr_map = false;
let rr_map = new Map;
let rr_mark = 0; // hrtime.bigint(); 
let rr_begin = 0;
let cycle_date;
let mark_steps = 0;
let rootnode_n;

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
    if (envSet('QA','1')) console.log("A[%s] %d %s %s", 
      params.requestId, 
      response.status,
      params.response.url, 
      response.mimeType
    );
    rr_map.set( params.requestId, m );
  }
  else {
    if (envSet('QA','1')) console.log("B[%s]", params.requestId, response );
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
    latest_rr = params.requestId;
  }
  if (envSet('QA','1')) console.log("Q[%s] %s %s", 
    params.requestId, 
    markdata.method,
    markdata.url
  );
  rr_mark = hrtime.bigint();
}//}}}

function networkLoadingFinished(params)
{//{{{
  if ( outstanding_rr.has( params.requestId ) ) {
    latest_rr = params.requestId; // Potentially usable for tracking XMLHTTPRequest markup fetches
    outstanding_rr.delete( params.requestId );
  }
  if (envSet('QA','1')) console.log("L[%s]", params.requestId, outstanding_rr.size );
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

function write_to_file( fn, file_ts, content )
{//{{{
  let ts = file_ts === undefined ? datestring( cycle_date ) : file_ts; 
  let fn_parts = [ 
    fn.replace(/^(.*)\.([^.]{1,})$/i,'$1'), 
    fn.replace(/^(.*)\.([^.]{1,})$/i,'$2')
  ];
  let fn_ts = [ [ fn_parts[0], '-',  ts ].join(''), (fn_parts[1].length > 0 && fn_parts[1] != fn_parts[0]) ? ['.', fn_parts[1]].join('') : '' ].join(''); 
  try {
    // Plain name is used to create a symbolic link.
    // Unlink that if it is present.
    unlinkSync( fn );
  } catch (e) {} 
  writeFileSync( fn_ts, content, {
    flag : "w+",
    flush: true
  });
  symlinkSync( fn_ts, fn );
}//}}}

function write_map_to_file( description, map_file, map_obj, file_ts )
{//{{{
  console.log( "Writing %s to %s", description, map_file );
  write_to_file( map_file, file_ts,  JSON.stringify( map_obj, 
    // Stringify an ES6 Map
    // https://stackoverflow.com/questions/29085197/how-do-you-json-stringify-an-es6-map
    function (key, value) {
      if(value instanceof Map) {
        return Object.fromEntries(value);
      } else {
        return value;
      }
    }, 2 )
  );
}//}}}

function envSet( v, w )
{//{{{
  return w === undefined
  ? ( process.env[v] !== undefined )
  : ( process.env[v] !== undefined && process.env[v] === w );
}//}}}

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
            console.log("\r\n--CLEAR--\r\n");
            if ( cb ) await cb( mark_steps );
          }
          else {
            mark_steps++;
            if ( envSet('WATCHDOG','1') ) console.log("--MARK[%d]--", mark_steps, parents_pending_children.length);
            if ( cb ) await cb( mark_steps );
          }
        }
        resolve(true);
      },1);
    });
  }//}}}

  function register_parent_in_waiting( nodeId )
  {//{{{
    // Only call this method 
    // if node.childNodeCount > 0 AND node.children === undefined
    if ( envSet('DOMSETCHILDNODES','2') ) process.stdout.write('?');
    if ( envSet('DOMSETCHILDNODES','1') ) console.log("ENQUEUE [%d]", nodeId);
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

    if ( envSet('DOMSETCHILDNODES','2') ) process.stdout.write('.');
    if ( envSet('DOMSETCHILDNODES','1') ) console.log("Sub[%d] %s %d <- parent %d children %d",
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

    // Only enqueue requestChildNodes for parents missing .children array
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

    if ( envSet('DOMSETCHILDNODES','2') ) process.stdout.write('.');
    if ( envSet('DOMSETCHILDNODES','1') ) console.log( 
      "NodeDSC[%d] %s %d <== parent %d children %d { %s }", 
      nodes_seen.size,
      descriptor.description,
      parentId, 

      waiting_parent,
      nodes.length,
      nodes.map((e) => e.nodeId).join(',')
      //,descriptor
      //,params
    );
    let parent_node;
    if ( nodes_seen.has( waiting_parent ) ) {
      // Avoid need for fixup by fixing waiting_parent content map
      parent_node = nodes_seen.get( waiting_parent );
      if ( !parent_node.content.has( parentId ) && (waiting_parent != parentId) ) {
        parent_node.content.set( parentId, null );
        nodes_seen.set( waiting_parent, parent_node );
        if ( envSet('DOMSETCHILDNODES','1') ) 
          console.log( "Fixup %d[%d]", waiting_parent, parentId );
      }
    }
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
    }
    parent_node = nodes_seen.get( parentId );
    if ( 1/*parent_node.content.size == 0 && !parent_node.isLeaf*/ ) {
      let modified = 0;
      nodes.forEach((n,nn,node) => {
        if ( n.nodeId != parentId && !parent_node.content.has( n.nodeId ) ) {
          parent_node.content.set( n.nodeId, null );
          modified++;
        }
      });
      if ( envSet('DOMSETCHILDNODES','1') ) {
        console.log( "- Preregister %d/%d nodes with parent[%d]",
          modified,
          nodes.length,
          parentId
        );
      }
      if ( modified > 0 ) {
        parent_node.isLeaf = false;
        nodes_seen.set( parentId, parent_node );
      }
    }

    await nodes.forEach(async (n,nn,node) => {
      await recursively_add_and_register( n, parentId, 0 );
      return true;
    });
    rr_mark = hrtime.bigint();
    return true;
  }//}}}

  async function graft( m, nodeId, depth )
  {//{{{
    // Recursive descent through all nodes to attach all leaves to parents.
    tag_stack.push( m.nodeName );
    if ( !m.isLeaf && m.content && m.content.size == 0 ) {
      let sr = {
        nodeName   : m.nodeName,
        parentId   : m.parentId,
        attributes : m.attributes,
        isLeaf     : true,
        content    : (await DOM.getOuterHTML({ nodeId : nodeId })).outerHTML
      };
      if ( envSet('GRAFT','1') ) console.log("Leafify %d",
        depth,
        nodeId,
        m.content
      );
      m = sr;
    }
    if ( m.isLeaf ) {
      if ( envSet('GRAFT','1') ) console.log("   Leaf %d %d { %s }", 
        depth, 
        nodeId,
        tag_stack.join(' '), 
        m.content );
    }
    else if ( m.content && m.content.size && m.content.size > 0 ) {
      let tstk = new Array;
      let attrinfo;
      if ( m.nodeName == 'A' ) {
        let attrarr = new Array();
        m.attributes.forEach((value, key, map) => {
          attrarr.push( [key,'="',value,'"'].join('') );
        });
        attrarr.sort();
        attrinfo = attrarr.join(' ');
        while ( attrarr.length > 0 ) attrarr.shift();
        attrarr = null;
      }
      if ( envSet('GRAFT','1') ) console.log("Grafted %d %d | %s >", 
        depth,
        nodeId,
        tag_stack.join(' '),
        attrinfo ? attrinfo : ''
      );
      m.content.forEach((value, key, map) => {
        tstk.push( key );
      });
      tstk.sort((a,b) => {return a - b;});
      
      while ( tstk.length > 0 ) {
        let k = tstk.shift();
        if ( nodes_seen.has(k) ) {
          // Append newly-fetched nodes found in linear map
          // onto this node
          let b = await graft( nodes_seen.get(k), k, depth + 1 );
          m.content.set( k, b );
          nodes_seen.delete(k);
        }
      }
    }
    tag_stack.pop();
    return Promise.resolve(m);
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
      if ( envSet('TRIGGER_DOM_FETCH','1') ) console.log("requestChildNodes %d", 
        waiting_parent, 
        nodes_seen.has(waiting_parent), 
        parents_pending_children.length,
        rq_result
      ); 
      rr_mark = hrtime.bigint();
    }
  }//}}}

  function inorder_traversal_cb( mode, p, depth, nm, node_id )
  {//{{{
    // Parameters:
    // mode    : Indicates where we are invoked in the execution path. See inorder_traversal().
    // p       : Abbreviated node {n, node_id} to be placed in nodes_tree
    // d       : Traversal depth in destination tree nodes_tree
    // nm      : Depending on value of {mode}, either an abbreviated node, or a Map of such nodes from N.content
    // node_id : A unique ID identical to that in DOM.Node
    let retval;
    switch ( mode ) {
      case 'A':
        // nm      : Map of abbreviated nodes
        // node_id : Undefined
        // Return value: Unused
        break;
      case 'B':
        // nm      : Map of abbreviated nodes
        // node_id : Undefined
        // Return value: Unused
        break;
      case 'L':
        // nm: Abbreviated node from N.content 
        // node_id: node_id for {nm}
        // Return value: either of
        // - An abbreviated node to insert into returned Map
        // - undefined, to prevent altering Map returned from inorder_traversal
        break;
      case 'N':
        // nm: Abbreviated node from N.content 
        // node_id: node_id for {nm}
        // Return value: either of
        // - An abbreviated node to insert into returned Map
        // - undefined, to prevent altering Map returned from inorder_traversal
        break;
    }
    return retval;
  }//}}}

  async function trigger_page_fetch_cb( nm, p, node_id, d )
  {
    // This callback performs two functions:
    // 1. It takes each node nm passed to it by inorder_traversal(cb)
    //    and assembles these into a simplified DOM tree
    // 2. It traverses the (presumably still-loaded page) DOM, moves
    //    those text links into focus, and executes an Input mouse click on
    //    those selected nodes.
    //
    // Parameters
    // nm           : Abbreviated node
    // node_id      : node_id for {nm}
    // p            : Callback parameters passed to inorder_traversal
    // Return value : nm

    // await inorder_traversal( nodes_seen, 0, trigger_page_fetch_cb, { tagstack: tagstack, target_tree: trie } );
    let nr;

    if ( !p.tagstack.has(d) )
      p.tagstack.set(d, new Map);

    nr = p.tagstack.get(d);

    if ( !nr.has( nm.nodeName ) ) {
      nr.set( nm.nodeName, -1 );
    }

    let nrn = nr.get( nm.nodeName ) + 1;
    let altname = [ nm.nodeName,'[', nrn, ']', ].join('');

    nr.set( nm.nodeName, nrn );

    p.tagstack.set(d, nr);

    console.log(
      "%s%s[%d]", 
      ' '.repeat(d * 2),
      altname,
      d,
      nm.isLeaf ? nm.content : ''
    );

    if ( nm.nodeName == '#text' && nm.content == '[History]' ) {
      try {
        await DOM.scrollIntoViewIfNeeded({nodeId: node_id});
        let box = await DOM.getBoxModel({nodeId: node_id});
        console.log( "Box['%s']", nm.content, box );
      }
      catch(e) {
        console.log("Exception at depth %d", 
          d, 
          nm.nodeName, 
          e, 
          inspect(nm, {showHidden: false, depth: null, colors: true})
        );
      }
    }

    return Promise.resolve(nm);
  }
  
  async function inorder_traversal( nm, d, cb, cb_param, nodeId, parentId )
  {//{{{
    // Depth-first inorder traversal of .content maps in each node.

    // Parameters:
    // nm: Either a Map or an abbreviated node
    //
    // Return value:
    // - An abbreviated node
    if ( nm === undefined || !nm ) {
      if (envSet("INORDER_TRAVERSAL","1")) console.log( "@empty node[%d]", d, nodeId, nm );
    }
    else if ( nm instanceof Map ) {
      // We were passed a node tree - either the root or (possibly) 
      // the nm.content Map of a non-root node.
      // If nodeId is undefined, then we have received the root 
      // node container; otherwise, nodeId identifies an instance
      // containing Map nm as .content.
      let ka = new Array;
      nm.forEach((v, k, m) => {ka.push(k);});
      while ( ka.length > 0 ) {
        let k = ka.shift();
        if (envSet("INORDER_TRAVERSAL","1")) console.log( "@root %d[%d]", k, d ); 
        if ( nm.has( k ) ) {
          nm.set(k,await inorder_traversal(nm.get(k),d+1,cb,cb_param,k));
        }
      }
    }
    else if ( nm.content !== undefined ) {
      // nm is an abbreviated node, which should be the case
      // whenever d > 0. We expect nodeId to be a DOM.nodeId type
      // used as a search key for nm.
      if ( cb !== undefined ) nm = await cb(nm,cb_param,nodeId,d+1);
      if ( nm.isLeaf || nm.content.size === undefined ) {
        if (envSet("INORDER_TRAVERSAL","1")) console.log( "- Skipping leaf %d", nodeId );
      }
      else {
        let ka = new Array;
        nm.content.forEach((v,k,m)=>{ka.push(k);});
        if (envSet("INORDER_TRAVERSAL","1")) console.log( "- Traversing %d[%d]", nodeId, d, ka.length );
        while ( ka.length > 0 ) {
          let k = ka.shift();
          let n = nm.content.get(k);
          let rv = await inorder_traversal(n,d+1,cb,cb_param,k,nodeId);
          if ( rv === undefined ) {
            nm.content.delete(k);
          }
          else {
            nm.content.set(k,rv);
          }
        }
      }
    }
    return Promise.resolve(nm);
  }//}}}

  function sn_inorder_traversal( nm, d, p )
  {//{{{
    try {
      nm.forEach((n, node_id, map) => {
        if ( !n || n.isLeaf ) {
          // We cannot append to a leaf node
        }
        else {
          if (envSet("SN_INORDER_TRAVERSAL","2")) process.stdout.write('.');
          if ( p.n.parentId == node_id ) {
            if (envSet("SN_INORDER_TRAVERSAL","2")) console.log( "\r\nPlaced %d %d %d", d, node_id, nodes_seen.size );
            if (envSet("SN_INORDER_TRAVERSAL","1")) console.log( "Placed %d %d", d, node_id );
            n.content.set( p.node_id, p.n );
            return false;
          }
          else {
            sn_inorder_traversal( n.content, d + 1, p );
          }
        }
      });
    } catch(e) {
      console.log("Exception at depth %d", d, nm, e);
    }
  }//}}}

  function rr_time_delta()
  {//{{{
    let rr_now = hrtime.bigint();
    if ( rr_begin == 0 ) rr_begin = hrtime.bigint();
    let sec = Number.parseFloat(
      Number((rr_now - rr_begin)/BigInt(1000 * 1000))/1000.0
    );
    let min = Number.parseInt(Number.parseInt(sec) / 60);
    let s = Number.parseInt(sec) - (min*60);
    return [ min, 'm ', s, 's', ' (', sec , 's)' ].join('');
  }//}}}

  function datestring( d, fmt )
  { if ( d === undefined || !d ) d = new Date;
    if ( fmt === undefined ) fmt = "%Y%M%D-%H%i%s-%u";
    return fmt
      .replace(/%Y/g, d.getUTCFullYear())
      .replace(/%M/g, (d.getUTCMonth()+1).toString().padStart(2,'0'))
      .replace(/%D/g, d.getUTCDate().toString().padStart(2,'0'))
      .replace(/%H/g, d.getUTCHours().toString().padStart(2,'0'))
      .replace(/%i/g, d.getUTCMinutes().toString().padStart(2,'0'))
      .replace(/%s/g, d.getUTCSeconds().toString().padStart(2,'0'))
      .replace(/%u/g, d.getUTCMilliseconds());
  }

  async function finalize_metadata( step )
  {//{{{
    // Chew up, digest, dump, and clear captured nodes.
    let file_ts = datestring( cycle_date );
    if ( step == 0 ) {

      if ( nodes_seen.size > 0 ) {
        // First, sort nodes - just because we can.
        console.log( "TIME: finalize_metadata", rr_time_delta() );
        nodes_seen = return_sorted_map( nodes_seen );
        if ( envSet('FINALIZE_METADATA','1') ) console.log(
          "Pre-update",
          inspect(nodes_seen, {showHidden: false, depth: null, colors: true})
        );
        write_map_to_file("Pre-transform", "pre-transform.json", nodes_seen, file_ts);

        let markupfile = "index.html";
        try {
          console.log( "Writing markup %s [%d]", markupfile, rootnode );
          write_to_file( markupfile, file_ts,  
            (await DOM.getOuterHTML({nodeId: rootnode})).outerHTML
          );
        }
        catch(e) {
          console.log( "Unable to write markup file", markupfile );
        }

        console.log( "TIME: TRANSFORM", rr_time_delta() );

        let st = new Array;
        let runs = 0;

        console.log( "Populating tree buffer with %d nodes", nodes_seen.size );
        nodes_seen.forEach((value, key, map) => {
          process.stdout.write("+");
          st.push( key );
        });
        console.log( "\r\nTIME: Obtained key array of length", st.length, rr_time_delta() );

        console.log( "Sorting %d nodes", nodes_seen.size );
        nodes_seen = return_sorted_map( nodes_seen );
        write_to_file( "pre-processed.json", file_ts, inspect( nodes_seen, { showHidden: true, depth: null, colors: false } ));

        if (nodes_seen.size <= 1024) {//{{{

          // Slower O(n(n+q)), where q is a function of tree depth and length of the .content Map at each node

          // Here, nodes_tree contains parent nodes, 
          // and sn_inorder_traversal recurses through this tree
          // to find the parent of each node taken from nodes_seen.
          while ( nodes_seen.size > 0 && st.length > 0 ) {
            let ni = st.shift();
            let n = nodes_seen.get( ni );
            nodes_seen.delete( ni );

            if (envSet("SN_INORDER_TRAVERSAL","1")) console.log( "Pluck node", ni );

            if ( n.parentId == 0 ) {
              nodes_tree.set( ni, n );
            }
            else {
              sn_inorder_traversal( nodes_tree, 0, {
                n       : n,
                node_id : ni 
              }); 
            }
          }
          console.log(
            "Completed buffer",
            inspect(nodes_tree, {showHidden: false, depth: null, colors: true})
          );
          // Transfer nodes_tree back
          nodes_tree.forEach((value, key, map) => {
            st.push( key );
          });
          let xhr_fetch;
          if ( append_buffer_to_rr_map ) xhr_fetch = new Map;

          while ( st.length > 0 ) {
            let ni = st.shift();
            if ( nodes_tree.has( ni ) ) {
              let n = nodes_tree.get( ni );
              nodes_tree.delete( ni );
              nodes_seen.set( ni, n );
              if ( xhr_fetch !== undefined ) {
                let rr_entry = rr_map.get( xhr_fetch_rr );
                rr_entry.markup = n;
                rr_map.set( xhr_fetch_rr, rr_entry );
              }
            }
          }
          append_buffer_to_rr_map = false;
          xhr_fetch_rr = 0;

        }//}}}
        else
        {//{{{
          // Ensure that child nodes are referenced in .content
          // across all nodes.
          while ( nodes_seen.size > 1 && runs < 10 ) {
            console.log( "Run %d : %d", runs, nodes_seen.size, rr_time_delta() );
            while ( st.length > 0 ) {
              let k = st.shift();
              if ( nodes_seen.has( k ) ) {
                // Node[k] may have been relocated by graft(m,nodeId,depth)
                b = nodes_seen.get( k );
                if ( b.parentId > 0 ) {
                  console.log("Next %d, remaining %d", k, nodes_seen.size);
                  nodes_seen.set( k, await graft( b, k, 0 ) );

                  b = nodes_seen.get( k );

                  if ( nodes_seen.has( b.parentId ) ) {
                    let p = nodes_seen.get( b.parentId );
                    p.content.set( k, b );
                    nodes_seen.delete( b.parentId );
                    nodes_seen.set( b.parentId, p );
                    nodes_seen.delete( k );
                    console.log("Remaining nodes", nodes_seen.size);
                  }
                } // b.parentId > 0
              } // nodes_seen.has( k )
            } // st.length > 0
            runs++;
            if ( nodes_seen.size > 1 ) {
              nodes_seen.forEach((value, key, map) => {
                st.push( key );
              });
              st.sort((a,b) => {return b - a;});
              console.log( "Reduction of %d nodes", st.length, rr_time_delta() );
            }
          }
          console.log( "Reduced node tree to %d root nodes", nodes_seen.size ); 
        }//}}}

        rr_time = hrtime.bigint();
        console.log( "\r\nDOM tree structure finalized with %d nodes", nodes_seen.size, rr_time_delta() );

        // Inorder traversal demo to reconstruct "clean" HTML
        console.log( "Building %d nodes", nodes_seen.size );
        let tagstack = new Map; // At each recursive step up the tree (from root node d = 0), we use this Map of array elements to track unique HTML tags found 
        let trie = new Map; // Target tree containing nodes { "HTMLTAG" => Map(n) { "HTMLTAG" => ... { "HTMLTAG" => "<leaf node content>" } ... } }
        await inorder_traversal( 
          nodes_seen, 
          0, 
          trigger_page_fetch_cb, 
          { tagstack: tagstack, target_tree: trie }
        );
        rr_time = hrtime.bigint();
        console.log( "Built %d nodes", nodes_seen.size, rr_time_delta(),
          (envSet("DUMP_PRODUCT","1")) 
          ? inspect(trie, {showHidden: false, depth: null, colors: true})
          : nodes_seen.size 
        );
        write_to_file( "trie.txt", file_ts, 
          inspect(trie, {showHidden: false, depth: null, colors: true})
        );


        console.log( "Everything", 
          rr_time_delta(), 
          (envSet("DUMP_PRODUCT","1"))
          ? inspect(nodes_seen, {showHidden: false, depth: null, colors: true})
          : nodes_seen.size
        );
        // Clear metadata storage

        rr_time = hrtime.bigint();
        console.log( "DONE", rr_time_delta() );

        write_map_to_file("Everything",
          "everything.json",
          nodes_seen,
          file_ts 
        );

        console.log( "Currently", Date() );
      }
      nodes_seen.clear();
      nodes_tree.clear();

      if ( rr_map.size > 0 ) {
        write_map_to_file( "Network exchanges",
          "network.json",
          rr_map, 
          file_ts
        );
        rr_map.clear();
      }
      cycle_date = null;
    }
    else {
      // Trigger requestChildNodes
      await trigger_dom_fetch();
    }
    return Promise.resolve(true);
  }//}}}

  async function setup_dom_fetch( nodeId )
  {
    rootnode   = nodeId;
    rr_mark    = hrtime.bigint();
    cycle_date = new Date();
    rr_begin   = rr_mark;
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
    await trigger_dom_fetch();
    return Promise.resolve(true);
  }
  
  try {

    Network.requestWillBeSent(networkRequestWillBeSent);
    Network.responseReceived(networkResponseReceived);
    Network.loadingFinished(networkLoadingFinished);
    DOM.setChildNodes(domSetChildNodes);

    await DOM.attributeModified(async (params) => {
      console.log( 'DOM::attributeModified', params );
      // This event is triggered by clicking on [History] links on https://congress.gov.ph/legisdocs/?v=bills 
      if ( params.value == 'modal fade in' ) {
        let markup = (await DOM.getOuterHTML({nodeId: params.nodeId})).outerHTML;
        console.log( "Markup",  markup  );
        if ( latest_rr !== 0 && rr_map.has( latest_rr ) ) {
          let rr_entry = rr_map.get( latest_rr );
          rr_entry.markup = markup;
          rr_map.set( latest_rr, rr_entry );
          console.log( "Markup recorded", latest_rr ); 
          xhr_fetch_rr = latest_rr;
          latest_rr = 0;
          append_buffer_to_rr_map = true;

          await setup_dom_fetch( params.nodeId );
        }
      }
    });

    await DOM.attributeRemoved(async (params) => {
      console.log( 'DOM::attributeRemoved', params );
    });

    await DOM.characterDataModified(async (params) => {
      console.log( 'DOM::characterDataModified', params );
    });

    await DOM.childNodeCountUpdated(async (params) => {
      console.log( 'DOM::childNodeCountUpdated', params );
    });

    await DOM.childNodeInserted(async (params) => {
      console.log( 'DOM::childNodeInserted', params );
    });

    await DOM.childNodeRemoved(async (params) => {
      console.log( 'DOM::childNodeRemoved', params );
    });

    await DOM.documentUpdated(async (params) => {
      console.log( 'DOM::documentUpdated', params );
    });

    await Page.loadEventFired(async (ts) => {
      const { currentIndex, entries } = await Page.getNavigationHistory();
      const {root:{nodeId}} = await DOM.getDocument({ pierce: true });

      await setup_dom_fetch( nodeId );

      console.log("LOAD EVENT root[%d]", 
        nodeId, 
        ts,
        datestring( cycle_date ),
        entries && entries.length > 0 && entries[currentIndex] && entries[currentIndex].url 
        ? entries[currentIndex].url 
        : '---'
      );
    });

    await Page.windowOpen(async (wo) => {
      console.log("windowOpen", wo);
    });

    await Page.domContentEventFired(async (ts) => {
      console.log("DOM Content Event", ts );
    });

    await Page.lifecycleEvent(async (p) => {
      console.log("Lifecycle", p);
    });

    await Page.setLifecycleEventsEnabled({enabled: true});

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
