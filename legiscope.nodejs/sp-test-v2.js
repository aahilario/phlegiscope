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
    if (envSet('QA')) console.log("A[%s] %d %s %s", 
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
  if (envSet('QA')) console.log("Q[%s] %s %s", 
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
  if (envSet('QA')) console.log("L[%s]", params.requestId, outstanding_rr.size );
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
  writeFileSync( map_file, map_obj, {
  //writeFileSync( map_file, JSON.stringify( objson, null, 2 ), {
    flag  : 'w+',
    flush : true
  }); 
}//}}}

function envSet( v, w )
{
  return w === undefined
  ? ( process.env[v] !== undefined )
  : ( process.env[v] !== undefined && process.env[v] === w );
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
    if ( parent_node.content.size == 0 && !parent_node.isLeaf ) {
      let modified = 0;
      console.log( "- Attach %d nodes to parent[%d]",
        nodes.length,
        parentId
      );
      nodes.forEach((n,nn,node) => {
        if ( !parent_node.content.has( n.nodeId ) ) {
          parent_node.content.set( n.nodeId, null );
          modified++;
        }
      });
      if ( modified > 0 )
        nodes_seen.set( parentId, parent_node );
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
      if ( envSet('GRAFT','1') ) console.log("   Leaf %d { %s }", 
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
          attrarr.push( [key,'="',value,'"'].join('') );
        });
        attrarr.sort();
        attrinfo = attrarr.join(' ');
        while ( attrarr.length > 0 ) attrarr.shift();
        attrarr = null;
      }
      if ( envSet('GRAFT','1') ) console.log("Grafted %d | %s >", 
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

  function inorder_traversal( nm, d, cb, cb_param )
  {//{{{
    let br = new Map;
    let nr = new Map;
    // Depth-first inorder traversal of .content maps in each node.
    if ( cb ) cb('A', cb_param, d, nm);
    try {
      // Map insert order determines the traversal order
      nm.forEach((n, node_id, map) => {
        if ( !n ) return true;
        if ( !nr.has( n.nodeName ) ) {
          nr.set( n.nodeName, -1 );
        }
        let nrn = nr.get( n.nodeName ) + 1;
        let altname = [ n.nodeName,'[', nrn, ']', ].join('');
        let v;
        nr.set( n.nodeName, nrn );
        if ( n.isLeaf ) {
          v = cb 
            ? cb('L', cb_param, d, n, node_id ) 
            : n.content;
          if ( v !== undefined )
            br.set( altname, v );
        }
        else {
          v = cb 
            ? cb(
              'N', 
              cb_param, 
              d, 
              inorder_traversal( n.content, d + 1, cb, cb_param ),
              node_id )
            : inorder_traversal( n.content, d + 1, cb, cb_param );
          if ( v !== undefined )
            br.set( altname, v );
        }
      });
    } catch(e) {
      console.log("Exception at depth %d", 
        d, 
        nm.nodeName, 
        e, 
        inspect(nm, {showHidden: false, depth: null, colors: true})
      );
    }
    if ( cb ) cb('B', cb_param, d, nm);
    nr.clear();
    nr = null;
    return br; // After complete traversal, return complete tree
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

  async function finalize_metadata( step )
  {//{{{
    // Chew up, digest, dump, and clear captured nodes.
    
    if ( step == 0 ) {

      // First, sort nodes - just because we can.
      nodes_seen = return_sorted_map( nodes_seen );
      if ( envSet('FINALIZE_METADATA','0') ) console.log( "Pre-update", inspect(nodes_seen, {showHidden: false, depth: null, colors: true}) );
      write_map_to_file("Pre-transform", "pre-transform.json", 
        inspect(nodes_seen, {
          showHidden: false, 
          depth: null,
          colors: true 
        }), ""
      );
      console.log( "TRANSFORM" );

      let st = new Array;
      let runs = 0;

      ///////////////////////////////////////////////////////////
      // FIXME: Refactor to use tree traversal to populate nodes_tree

      if (0) {//{{{

        console.log( "Populating tree buffer with %d nodes", nodes_seen.size );
        nodes_seen.forEach((value, key, map) => {
          process.stdout.write("+");
          st.push( key );
        });

        console.log( "\r\nObtained key array of length", st.length );

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
        
      }//}}}
      else
      {//{{{
        // Ensure that child nodes are referenced in .content
        // across all nodes.
        if (0) {
          console.log("Fixing cross-references among %d nodes", nodes_seen.size);
          nodes_seen.forEach((value, key, map) => {
            st.push( key );
          });
          while ( st.length > 0 ) {
            let n = st.shift();
            if ( nodes_seen.has(n) ) {
              let s = nodes_seen.get(n);
              console.log( "Check", n );
              nodes_seen.delete(n);
              nodes_seen.forEach((value, key, map) => {
                if ( value.parentId == n ) {
                  if ( !s.content.has(key) ) {
                    console.log( "Fixup [%d] <- [%d]", key, n );
                    s.content.set( key, null );
                  }
                }
              });
              nodes_seen.set(n, s);
            }
          }
        }

        if (0) {
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
        }

        console.log( "Sorting %d nodes", nodes_seen.size );
        nodes_seen = return_sorted_map( nodes_seen );
        write_map_to_file(
          "Pre-processed",
          "pre-processed.json",
          inspect(nodes_seen, {showHidden: false, depth: null, colors: true}),
          ""
        );

        while ( nodes_seen.size > 1 && runs < 10 ) {
          console.log( "Run %d : %d", runs, nodes_seen.size );
          nodes_seen.forEach((value, key, map) => {
            st.push( key );
          });
          while ( st.length > 0 ) {
            let k = st.shift();
            if ( nodes_seen.has( k ) ) {
              let b = nodes_seen.get( k );

          if ( b.content.size == 0 ) {
            let sr = {
              nodeName   : b.nodeName,
              parentId   : b.parentId,
              attributes : b.attributes,
              isLeaf     : true,
              content    : (await DOM.getOuterHTML({ nodeId : k })).outerHTML
            };
            b = sr;
            nodes_seen.set( k, b );
          }

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
      }//}}}

      console.log( "\r\nDOM tree structures finalized\r\n" );

      // Inorder traversal demo to reconstruct "clean" HTML
      console.log( "Building" );
      let trie = inorder_traversal( nodes_seen, 0 );
      console.log( "Built", inspect(trie, {showHidden: false, depth: null, colors: true}) );

      if (envSet("DUMP_PRODUCT","1")) console.log( "Everything", inspect(nodes_seen, {showHidden: false, depth: null, colors: true}) );
      // Clear metadata storage
      write_map_to_file("Everything",
        "everything.json",
        inspect(nodes_seen, {showHidden: false, depth: null, colors: true}),
        ""
      );
      nodes_seen.clear();
      nodes_tree.clear();
    }
    else {
      // Trigger requestChildNodes
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
