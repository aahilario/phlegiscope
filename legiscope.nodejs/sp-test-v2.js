const { readFileSync, writeFile, writeFileSync, mkdirSync, existsSync, statSync, linkSync, unlinkSync, symlinkSync } = require('node:fs');
const assert = require("assert");
const System = require("systemjs");
const cheerio = require("cheerio");
const { createHash } = require("node:crypto");
const url = require("node:url");
const http = require("node:http");
const https = require("node:https");
const { argv, env, pid, hrtime } = require("node:process");
const { spawnSync } = require("child_process");
const { inspect } = require("node:util");

const CDP = require('chrome-remote-interface');

const db_user = process.env.LEGISCOPE_USER || '';
const db_pass = process.env.LEGISCOPE_PASS || '';
const db_host = process.env.LEGISCOPE_HOST || '';
const db_port = process.env.LEGISCOPE_PORT || '';
const db_name = process.env.LEGISCOPE_DB   || '';
const output_path = process.env.DEBUG_OUTPUT_PATH || '';
const targetUrl = process.env.TARGETURL || '';
const rr_timeout_s = 15; // Seconds of inactivity before flushing page metadata 
const node_request_depth = 7;

const mysqlx = require("@mysql/xdevapi");

let db_session;
let outstanding_rr          = new Map;
let rr_map                  = new Map;
let nodes_seen              = new Map;
let tag_stack               = new Array;
let latest_rr               = 0;
let xhr_fetch_rr            = 0;
let xhr_fetch_id            = 0;
let append_buffer_to_rr_map = 0;
let rr_mark                 = 0; // hrtime.bigint();
let rr_begin                = 0;
let cycle_date;
let mark_steps              = 0;
let rootnode_n;
let postprocessing          = 0;
let exception_abort         = false;
let file_ts;
let inorder_traversal_previsit = false;
let inorder_traversal_postvisit = false;
let current_url = null;

if ( envSet("CB_PREPROCESS","0") ) inorder_traversal_previsit = true;
if ( envSet("CB_PREPROCESS","1") ) inorder_traversal_postvisit = true;

async function setup_db()
{
  let s;
  try {
    // If database host, user, and password are specified in environment,
    // attempt to connect, and do not proceed if connection fails.
    if ( db_host.length > 0 && db_user.length > 0 && db_pass.length > 0 ) {
      let url_model;
      let db_config = {
        password : db_pass,
        user     : db_user,
        host     : db_host,
        port     : parseInt(db_port), 
        schema   : db_name
      };
      console.log( "DB", inspect( db_config, {showHidden: false, depth: null, colors: true} ) );
      s = mysqlx.getSession(db_config)
        .then(async (s) => {
          let ses = await s.getSchemas();
          db_session = s;
          ses.forEach(async (schema) => {
            let schemaname = await schema.getName();
            let tables;
            switch ( schemaname ) {
              case 'performance_schema':
              case 'information_schema':
                break;
              default:
                console.log("-", schemaname );
                tables = await schema.getTables();
                await tables.forEach(async (t) => {
                  console.log("  -", await t.getName() );
                });
                break;
            }
          });
          return s;
        })
        .then((s) => {
          url_model = s.getSchema(db_name).getTable('url_model');
          return url_model;
        })
        .then( function(u) {
          url_model = u;
          return u.select(['url','last_modified','last_fetch','hits'])
            .where('id = 2')
            .execute();
        })
        .then( async function( resultset ) {
          //console.log( "Result", inspect(resultset.fetchAll(), {showHidden: false, depth: null, colors: true}) );
          return resultset.fetchAll();
        })
        ;
    }
    else {
      console.log("No database");
    }
  }
  catch(e) {
    console.log("Database",inspect(e));
    process.exit(1);
  }
  return s;
}

function sleep( millis )
{//{{{
  return new Promise((resolve) => {
    setTimeout(() => {
      resolve(true);
    },millis);
  });
}//}}}

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
  latest_rr = params.requestId;
  if ( !rr_map.has( latest_rr ) ) {
    rr_map.set( latest_rr, {
      url      : markdata.url,
      request  : markdata,
      response : {}
    });
  }
  if ( !outstanding_rr.has( latest_rr ) ) {
    outstanding_rr.set( latest_rr, markdata );
  }
  if (envSet('QA','1')) console.log("Q[%s] %s %s", 
    latest_rr, 
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
    rr_mark = hrtime.bigint();
  });
  sorter.forEach((e) => {
    map_obj.set( e, sorted.get(e) );
    sorted.delete(e);
    rr_mark = hrtime.bigint();
  });
  while ( sorter.length > 0 ) { sorter.pop(); }
  sorted.clear();
  sorted = null
  sorter = null;
  rr_mark = hrtime.bigint();
  return map_obj;
}//}}}

function write_to_file( fn, file_ts, content, n )
{//{{{
  let ts = file_ts === undefined ? datestring( cycle_date ) : file_ts; 
  let fn_parts = [ 
    fn.replace(/^(.*)\.([^.]{1,})$/i,'$1'), 
    fn.replace(/^(.*)\.([^.]{1,})$/i,'$2')
  ];
  let fn_p = [ fn_parts[0], ts ];
  if ( n !== undefined ) fn_p.push( n.toString() );
  let fn_ts = [ fn_p.join('-'), (fn_parts[1].length > 0 && fn_parts[1] != fn_parts[0]) ? ['.', fn_parts[1]].join('') : '' ].join(''); 
  let outfile = [output_path, fn_ts].join('/')
  try {
    // Plain name is used to create a symbolic link.
    // Unlink that if it is present.
    unlinkSync( [output_path,fn].join('/') );
  } catch (e) {} 
  writeFileSync( outfile, content, {
    flag : "w+",
    flush: true
  });
  symlinkSync( fn_ts, [output_path,fn].join('/') );
}//}}}

function read_map_from_file( map_file )
{//{{{
  function reviver(key, value) {
    if(typeof value === 'object' && value !== null) {
      if (value.dataType === 'Map') {
        return new Map(Object.entries(value.value));
      }
    }
    return value;
  }
  let f = readFileSync( map_file, { flags : "r" } ); 
  return JSON.parse( f, reviver );
}//}}}

function write_map_to_file( description, map_file, map_obj, file_ts, n )
{//{{{
  console.log( "Writing %s to %s", description, map_file );

  // Stringify an ES6 Map
  // https://stackoverflow.com/questions/29085197/how-do-you-json-stringify-an-es6-map
  function recoverable(key, value) {
    if(value instanceof Map) {
      return {
        dataType : 'Map',
        value    : Object.fromEntries(value)
      };
    } else {
      return value;
    }
  }

  write_to_file( 
    map_file,
    file_ts,  
    JSON.stringify( map_obj, recoverable, 2 ),
    n
  );

}//}}}

function envSet( v, w )
{//{{{
  return w === undefined
  ? ( process.env[v] !== undefined )
  : ( process.env[v] !== undefined && process.env[v] === w );
}//}}}

function mapify_attributes( ma )
{//{{{
  let attrset = ma ? ma : [];
  let attrmap = new Map;
  while ( attrset.length > 0 ) {
    let attr = attrset.shift();
    let attrval = attrset.shift();
    attrmap.set( attr, attrval );
  }
  return attrmap;
}//}}}

function datestring( d, fmt )
{//{{{
  if ( d === undefined || !d ) d = new Date;
  if ( fmt === undefined ) fmt = "%Y%M%D-%H%i%s-%u";
  return fmt
    .replace(/%Y/g, d.getUTCFullYear())
    .replace(/%M/g, (d.getUTCMonth()+1).toString().padStart(2,'0'))
    .replace(/%D/g, d.getUTCDate().toString().padStart(2,'0'))
    .replace(/%H/g, d.getUTCHours().toString().padStart(2,'0'))
    .replace(/%i/g, d.getUTCMinutes().toString().padStart(2,'0'))
    .replace(/%s/g, d.getUTCSeconds().toString().padStart(2,'0'))
    .replace(/%u/g, d.getUTCMilliseconds());
}//}}}

async function flatten_container( sp, nm, p, node_id, d )
{//{{{
  if ( !p.flattened.has( node_id ) ) {
    p.flattened.set( node_id, nm );
    if ( !nm.isLeaf && nm.content instanceof Map ) {
      let lc = p.flattened.get( node_id );
      let lca = new Array;
      lc.content.forEach((v,k,m) => { lca.push(k); });
      lc.content.clear();
      while ( lca.length > 0 ) {
        let lk = lca.shift();
        lc.content.set( lk, null );
      }
      p.flattened.set( node_id, lc );
    }
  }
  return Promise.resolve(nm);
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

async function graft( sourcetree, m, nodeId, depth, get_content_cb )
{//{{{
  // Recursive descent through all nodes to attach all leaves to parents.
  tag_stack.push( m.nodeName );
  if ( !m.isLeaf && m.content && m.content.size == 0 ) {
    let sr = {
      nodeName   : m.nodeName,
      parentId   : parseInt(m.parentId),
      attributes : m.attributes,
      isLeaf     : true,
      content    : await get_content_cb( m, nodeId )
      // Originally: 
      // content    : (await DOM.getOuterHTML({ nodeId : nodeId })).outerHTML
    };
    if ( envSet('GRAFT','1') ) console.log("Leafify %d",
      depth,
      nodeId,
      m.content
    );
    m = sr;
  }
  if ( m.isLeaf ) {
    if (envSet("GRAFT","2")) process.stdout.write('g');
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
    if (envSet("GRAFT","2")) process.stdout.write('G');
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
      if ( sourcetree.has(k) ) {
        // Append newly-fetched nodes found in linear map
        // onto this node
        let b = await graft( 
          sourcetree, 
          sourcetree.get(k), 
          k, 
          depth + 1, 
          get_content_cb
        );
        m.content.set( k, b );
        sourcetree.delete(k);
      }
    }
  }
  tag_stack.pop();
  return Promise.resolve(m);
}//}}}

async function reduce_nodes( sourcetree, nodes, get_content_cb )
{//{{{

  let st = new Array;
  let runs = 0;
  if ( envSet("REDUCE_NODES","1") ) console.log( "Populating tree buffer with %d nodes", nodes.size );
  nodes.forEach((value, key, map) => {
    process.stdout.write("+");
    st.push( key );
  });
  console.log("<");
  if ( envSet("REDUCE_NODES","1") ) console.log( "\r\nTIME: Obtained key array of length", st.length, rr_time_delta() );

  while ( nodes.size > 1 && runs < 10 ) {
    if ( envSet("REDUCE_NODES","1") ) console.log( "Run %d : %d", runs, nodes.size, rr_time_delta() );
    while ( st.length > 0 ) {
      let k = st.shift();
      if ( nodes.has( k ) ) {
        // Node[k] may have been relocated by graft(m,nodeId,depth)
        b = nodes.get( k );
        if ( b.parentId > 0 ) {
          if ( envSet("REDUCE_NODES","1") ) console.log("Next %d, remaining %d", k, nodes.size);
          nodes.set( k, await graft( sourcetree, b, k, 0, get_content_cb ) );

          b = nodes.get( k );

          if ( nodes.has( b.parentId ) ) {
            let p = nodes.get( b.parentId );
            p.content.set( k, b );
            nodes.delete( b.parentId );
            nodes.set( b.parentId, p );
            nodes.delete( k );
            process.stdout.write("\r\n");
            if ( envSet("REDUCE_NODES","1") ) console.log("Remaining nodes", nodes.size);
          }
        } // b.parentId > 0
      } // nodes.has( k )
    } // st.length > 0
    runs++;
    if ( nodes.size > 1 ) {
      nodes.forEach((value, key, map) => {
        st.push( key );
      });
      st.sort((a,b) => {return b - a;});
      if ( envSet("REDUCE_NODES","1") ) console.log( "Reduction of %d nodes", st.length, rr_time_delta() );
    }
  }
  if ( envSet("REDUCE_NODES","1") ) console.log( "Reduced node tree to %d root nodes", nodes.size ); 
}//}}}

async function inorder_traversal( sp, nm, d, cb, cb_param, nodeId, parentId )
{//{{{
  // Depth-first inorder traversal of .content maps in each node.
  // Parameters:
  // nm: Either an abbreviated node or a Map of such nodes
  //
  // Return value:
  // - An abbreviated node

  function reverse_content( nm )
  {//{{{
    let ka = new Array;
    let revmap = new Map;
    nm.content.forEach((v,k,m) => { 
      let val = v;
      ka.push(parseInt(k)); 
      revmap.set(k,val);
    });
    ka.forEach((e) => { nm.content.delete(e); });
    nm.content.clear();
    ka.sort((a,b) => {return b - a;});
    if (envSet("REVERSE_CONTENT_DEBUG","1")) console.log( "Reversing %d-element container %d", ka.length, nodeId );
    while ( ka.length > 0 ) {
      let k = parseInt( ka.shift() );
      let v = revmap.get(k);
      nm.content.set( k, v );
      revmap.delete(k);
    }
  }//}}}

  if ( exception_abort ) {
    console.log( "Process Abort" );
  }
  else if ( nm === undefined || !nm ) {
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
        nm.set(k,await inorder_traversal(sp,nm.get(k),d,cb,cb_param,k));
      }
      if ( exception_abort ) break;
    }
  }
  else if ( nm.content !== undefined ) {
    // nm is an abbreviated node, which should be the case
    // whenever d > 0. We expect nodeId to be a DOM.nodeId type
    // used as a search key for nm.

    sp.branchpat.push(nm.nodeName);

    if ( cb !== undefined && inorder_traversal_previsit ) nm = await cb(sp,nm,cb_param,nodeId,d+1);
    if ( nm === undefined ) {
    }
    else if ( nm.isLeaf || nm.content.size === undefined ) {
      if (envSet("INORDER_TRAVERSAL","1")) console.log( "%sSkipping leaf %d",' '.repeat((d >= 0 ? d : 0) * 2), nodeId );
    }
    else {
      let ka = new Array;
      // Reverse order of child node Map elements
      if ( envSet("REVERSE_CONTENT") && !nm.isLeaf && nm.content instanceof Map && nm.content.size > parseInt(process.env.REVERSE_CONTENT) ) {
        reverse_content( nm );
      }
      nm.content.forEach((v,k,m)=>{ka.push(k);});

      if (envSet("INORDER_TRAVERSAL","1")) 
        console.log(
          "%sTraversing %d[%d]",
          ' '.repeat((d >= 0 ? d : 0) * 2),
          nodeId,
          d,
          ka.length
        );

      while ( ka.length > 0 ) {
        let k = ka.shift();
        let n = nm.content.get(k);
        let rv = await inorder_traversal(sp,n,d+1,cb,cb_param,k,nodeId);
        if ( rv === undefined ) {
          nm.content.delete(k);
        }
        else {
          nm.content.set(k,rv);
        }
        if ( exception_abort ) break;
      }

    }
    if ( cb !== undefined && inorder_traversal_postvisit ) nm = await cb(sp,nm,cb_param,nodeId,d+1);
    sp.branchpat.pop();
  }
  return Promise.resolve(nm);
}//}}}

async function treeify( t )
{//{{{
  let tempmap = new Map;
  let branchpat = new Array;
  let node_ids = new Array;
  
  t = return_sorted_map( t );

  // Move one node into target tempmap
  let rootnode_id;
  let rootnode;
  t.forEach((v,k,m) => {
    node_ids.push(k);
  });
  node_ids.sort((a,b) => { return a - b; });
  rootnode_id = node_ids.shift();
  rootnode = t.get(rootnode_id);
  t.delete(rootnode_id);
  tempmap.set(rootnode_id, rootnode);

  async function treeify_cb( sp, nm, p, node_id, d )
  {//{{{
    // Callback expected to be invoked before traversing .content
    let undefined_res;
    if ( nm.isLeaf ) {
      if ( nm.content instanceof Object ) nm.content = null;
    }
    else if (nm.nodeName === 'div.modal-dialog') {
      nm.content.clear();
      nm.isLeaf = true;
      nm.content = null;
    }
    else {
      let cl = new Array;
      nm.content.forEach((v,k,m) => { cl.push(k); });
      while ( cl.length > 0 ) {
        let k = cl.shift();
        let n = nm.content.get(k);
        if ( p.source_map.has(k) ) {
          let r = p.source_map.get(k);
          p.source_map.delete(k);
          if ( !n ) nm.content.set(k,r);
        }
        else if ( !n ) {
          nm.content.delete(k);
        }
      }
      if ( nm.content.size == 0 ) {
        nm.isLeaf = true;
        nm.content = null;
        return Promise.resolve(undefined_res);
      }
    }
    return Promise.resolve(nm);
  }//}}}

  async function prune_cb( sp, nm, p, node_id, d )
  {//{{{
    let undefined_res;

    if ( !(nm.content instanceof Map) ) {
    }
    else if ( nm.content.size == 0 ) {
      if ( envSet("PRUNE_CB","1") ) console.log( "%sDrop[1] map[%d] %d at %d",' '.repeat(d * 2), nm.content.size, node_id, d );
      nm.isLeaf = true;
      nm.content = null;
      return Promise.resolve(undefined_res);
    }
    else {
      let ks = new Array;

      if ( envSet("PRUNE_CB","1") ) console.log( "%sCheck map[%d] %d at %d",' '.repeat(d * 2), nm.content.size, node_id, d );
      nm.content.forEach((v,k,m) => { ks.push(k); });
      while ( ks.length > 0 ) {
        let k = ks.shift();
        let n = nm.content.get(k);
        if ( !n || ( n.content !== undefined && n.content instanceof Map && n.content.size == 0 ) ) {
          if ( envSet("PRUNE_CB","1") ) console.log( "%s- Prune[3] %d at %d",' '.repeat(d * 2), k, d );
          nm.content.delete(k);
        }
        else {
          if ( envSet("PRUNE_CB","1") ) console.log( "%s- Keep (%s)%d ",
            ' '.repeat(d * 2),
            typeof n.content,
            k, //, inspect(n.content, {showHidden: false, depth: null, colors: true}) //, inspect(n, {showHidden: false, depth: null, colors: true})
          );
        }
      }
      if ( nm.content.size == 0 ) {
        nm.isLeaf = true;
        nm.content = null;
        if ( envSet("PRUNE_CB","1") ) console.log( "%sDrop[2] map[%d] %d at %d",' '.repeat(d * 2), nm.content.size, node_id, d );
        return Promise.resolve(undefined_res);
      }
    }

    if ( nm.isLeaf && !nm.content ) {
      if ( envSet("PRUNE_CB","1") ) console.log( "%sPrune[1] %d at %d",' '.repeat(d * 2), node_id, d );
      return Promise.resolve(undefined_res);
    }


    return Promise.resolve(nm);
  }//}}}

  console.log( "Treeify" );
  await inorder_traversal(
    { branchpat : branchpat },
    tempmap, -1,
    treeify_cb,
    { source_map: t }
  );

  console.log( "Prune" );
  inorder_traversal_previsit = false;
  inorder_traversal_postvisit = true;
  await inorder_traversal(
    { branchpat : branchpat },
    tempmap, -1,
    prune_cb,
    { source_map: t }
  );

  while ( node_ids.length > 0 ) { node_ids.shift(); }

  t.clear();

  tempmap.forEach((v,k,m) => {
    node_ids.push(k);
  });

  while ( node_ids.length > 0 ) {
    let k = node_ids.shift();
    let v = tempmap.get(k);
    t.set(k,v);
    tempmap.delete(k);
  }

  while ( branchpat.length > 0 ) { branchpat.shift(); }

  return Promise.resolve(t);
}//}}}

async function monitor()
{//{{{

  let client;
  let nodes_tree  = new Map;
  let lookup_tree = new Map;
  let parents_pending_children = new Array; 
  let waiting_parent = 0;
  let depth          = 0;
  let rootnode       = 0;
  let completed      = false;
  let triggerable    = -1;

  client = await CDP();

  const { Network, Page, DOM, Input } = client;

  
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
    if ( envSet('DOMSETCHILDNODES','2') || postprocessing == 2 ) process.stdout.write('?');
    if ( envSet('DOMSETCHILDNODES','1') || postprocessing == 1 ) console.log("ENQUEUE [%d]", nodeId);
    parents_pending_children.push( parseInt(nodeId) );
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
    let parentNode = nodes_seen.get( parseInt(parent_nodeId) ); 
    let has_child_array = (m.children !== undefined) && (m.children.length !== undefined);
    let enqueue_m_nodeid = (m.childNodeCount !== undefined) && !has_child_array && m.childNodeCount > 0;
    if ( !nodes_seen.has(parseInt(m.nodeId)) ) {
      let attrmap = mapify_attributes( m.attributes );
      let isLeaf = !((m.childNodeCount && m.childNodeCount > 0) || has_child_array); 
      nodes_seen.set( parseInt(m.nodeId), {
        nodeName   : m.nodeName ? m.nodeName : '---',
        parentId   : parseInt(parent_nodeId),
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
    parentNode.content.set(parseInt(m.nodeId), null);
    nodes_seen.set( parent_nodeId, parentNode );

    if ( envSet('DOMSETCHILDNODES','2') || postprocessing == 2 ) process.stdout.write('.');
    if ( envSet('DOMSETCHILDNODES','1') || postprocessing == 1 ) console.log("Sub[%d] %s %d <- parent %d children %d",
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

    if ( envSet('DOMSETCHILDNODES','2') || postprocessing == 2 ) process.stdout.write('.');
    if ( envSet('DOMSETCHILDNODES','1') || postprocessing == 1 ) console.log( 
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
        if ( envSet('DOMSETCHILDNODES','1') || postprocessing == 1 ) 
          console.log( "Fixup %d.%d", parentId, waiting_parent );
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
      nodes_seen.set( parseInt(parentId), {
        nodeName   : R.nodeName,
        parentId   : parseInt(waiting_parent),
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
          parent_node.content.set( parseInt(n.nodeId), null );
          modified++;
        }
      });
      if ( envSet('DOMSETCHILDNODES','1') || postprocessing == 1 ) {
        console.log( "- Preregistered %d/%d nodes with parent[%d]",
          modified,
          nodes.length,
          parentId
        );
      }
      if ( modified > 0 ) {
        parent_node.isLeaf = false;
        nodes_seen.set( parseInt(parentId), parent_node );
      }
    }

    await nodes.forEach(async (n,nn,node) => {
      await recursively_add_and_register( n, parentId, 0 );
      return true;
    });
    rr_mark = hrtime.bigint();
    return true;
  }//}}}

  async function trigger_dom_fetch()
  {//{{{
    if ( parents_pending_children.length > 0 ) {
      waiting_parent = parents_pending_children.shift();
      // The DOM.requestChildNodes call should result in
      // invocation of domSetChildNodes(p) within milliseconds.
      let rq_result = await DOM.requestChildNodes({
        nodeId : waiting_parent,
        depth  : node_request_depth,
        pierce : true
      });
      if ( envSet('TRIGGER_DOM_FETCH','1') || postprocessing == 1 ) {
        console.log("requestChildNodes %d", 
          waiting_parent, 
          nodes_seen.has(waiting_parent), 
          parents_pending_children.length,
          rq_result
        ); 
      }
      rr_mark = hrtime.bigint();
    }
  }//}}}

  async function flatten_dialog_container( sp, nm, p, node_id, d )
  {//{{{
    if ( !p.dialog_nodes.has( node_id ) ) {
      p.dialog_nodes.set( node_id, nm );
      if ( 
        nm.nodeName == '#text' && 
        nm.isLeaf && 
        !(nm.content instanceof Map) &&
        nm.content == '×' // 'Close'
      ) {
        p.closer_node = node_id;
        console.log(
          "Found Modal Closer %d",
          node_id
        );
      }
    }
    return Promise.resolve(nm);
  }//}}}

  async function traverse_to( node_id, nm )
  {//{{{
    let result = true;
    try {
      await DOM.scrollIntoViewIfNeeded({nodeId: node_id});
      let {model:{content,width,height}} = await DOM.getBoxModel({nodeId: node_id});
      let cx = (content[0] + content[2])/2;
      let cy = (content[1] + content[5])/2;
      // Move mouse pointer to object on page, send mouse click and release
      if ( envSet("TRAVERSE_TO","1") ) console.log( "Box['%s'] (%d,%d)", nm.content, cx, cy, content );
    }
    catch(e) {
      console.log( "Non-traversable %d", node_id, inspect(nm, {showHidden: false, depth: null, colors: true}) );
      result = false;
    }
    return Promise.resolve(result);
  }//}}}

  async function congress_prune_panel_cb( sp, nm, p, node_id, d )
  {//{{{
    if ( nm.isLeaf ) {
      try {
        if ( envSet("CONGRESS_BILLRES_CB","1") ) console.log( "- Removing %d", node_id );
        DOM.removeNode({ nodeId: node_id });
      }
      catch (e) {
        // No-op
      }
    }
    rr_mark = hrtime.bigint();
    return Promise.resolve(nm);
  }//}}}

  async function congress_extract_history_panel( sp, nm, p, node_id, d )
  {//{{{
    if ( p.hit_depth > d && ( p.hit_depth == ( 3 + d ) ) ) {
      let extracted_info = {
        id       : null,
        links    : new Map,
        text     : new Map,
        skipthis : new Map
      };
      // Extract information from branch and leaves
      let local_sp = new Array;
      if ( envSet("CONGRESS_BILLRES_CB","1") ) {
        console.log( "---- MARK ----",
          inspect(nm, {showHidden: false, depth: null, colors: true})
        );
      }
      await inorder_traversal(
        { branchpat : local_sp },
        nm,
        -1,
        congress_billres_extract_cb,
        extracted_info
      );
      // Clean up extracted key-value pairs
      let ka = new Array;
      extracted_info.text.forEach((v,k,m) => { ka.push(k); });
      // Cannot use mapify_attributes owing to inconsistencies in formatting
      while ( ka.length > 0 ) {
        let k = ka.shift();
        let k_str = extracted_info.text.get(k).replace(/[:]*$/g,'');
        let k_arr = k_str.split(':');
        let v;
        let v_str;
        if ( k_arr.length == 2 ) {
          k_str = k_arr[0];
          v_str = k_arr[1];
        }
        else {
          v = ka.shift();
          v_str = extracted_info.text.get(v);
          extracted_info.text.delete(v);
        }
        extracted_info.text.set( k_str, v_str );
        extracted_info.text.delete(k);
      }
      extracted_info.text.forEach((v,k,m) => {
        if ( !p.textkeys.has(k) )
          p.textkeys.set(k,1);
        else {
          let tk = p.textkeys.get(k);
          tk++;
          p.textkeys.set(k,tk);
        }
      });
      ka.sort((a,b) => {return a - b;});
      // Remove branch and all leaves from frontend DOM
      await inorder_traversal(
        { branchpat : local_sp },
        nm,
        -1,
        congress_prune_panel_cb,
        extracted_info
      );
      extracted_info.links.delete('[History]');
      let final_data = {
        url     : current_url,
        id      : extracted_info.id,
        links   : extracted_info.links,
        text    : extracted_info.text,
        network : rr_map,
        history : p.flattened
      };
      let document_id = extracted_info.id.replace(/^\#/,'');
      write_map_to_file(
        extracted_info.id, 
        "panels.json",//[ extracted_info.id.replace(/^\#/,''), 'json' ].join('.'),
        final_data,
        file_ts,
        document_id
      );
      if ( envSet("CONGRESS_BILLRES_CB","1") ) console.log(
        inspect(final_data, {showHidden: false, depth: null, colors: true})
      );
      console.log( "---- MARK ----" );
      p.child_hits = 0;
      p.hit_depth = 0;
      p.flattened.clear();
      rr_map.clear();
    }

  }//}}}

  async function congress_billres_extract_cb( sp, nm, p, node_id, d )
  {//{{{
    if ( nm.nodeName == 'A' ) {
      let link = nm.attributes.has('href') 
        ? nm.attributes.get('href')
        : null
      ;
      let linkid = nm.attributes.has('data-id')
        ? nm.attributes.get('data-id')
        : null
      ;
      if ( envSet("CONGRESS_BILLRES_CB","1") ) console.log( "Extract A", inspect(nm) );
      if ( nm.content instanceof Map && nm.content.size == 1 ) {
        let eset = new Array;
        nm.content.forEach((v,k,map) => {
          eset.push(k);
        });
        while ( eset.length > 0 ) {
          let e = eset.shift();
          let child_node = nm.content.get(e);
          if ( child_node.isLeaf && child_node.nodeName == '#text' ) {
            let label = child_node.content.trim();
            p.links.set( label, { url : link, data_id : linkid } /* link */ );
            if ( linkid ) {
              p.id = linkid;
            }
            if ( !p.text.has( e ) )
              p.skipthis.set( e, label );
            else
              p.text.delete( e );
            if ( envSet("CONGRESS_BILLRES_CB","1") ) console.log( "extract[%d] %s: %s",
              e,
              child_node.content,
              { url : link, id : linkid }
            );
          }
        }
      }
    }
    if ( nm.isLeaf ) {
      if ( nm.nodeName == '#text' ) {
        let content = nm.content.trim();
        if ( content.length > 0 && !p.skipthis.has(node_id) ) {
          p.text.set( node_id, content );
        }
      }
    }
    rr_mark = hrtime.bigint();
    return Promise.resolve(nm);
  }//}}}

  function trigger_page_fetch_common_cb( sp, nm, p, node_id, d )
  {//{{{
    let nr;

    if ( !p.tagstack.has(d) )
      p.tagstack.set(d, new Map);

    nr = p.tagstack.get(d);

    if ( !nr.has( nm.nodeName ) ) {
      nr.set( nm.nodeName, -1 );
    }

    let nrn = nr.get( nm.nodeName ) + 1;
    let altname = [ nm.nodeName,'[', nrn, ']', ].join('');

    nr.set(nm.nodeName, nrn);

    p.tagstack.set(d, nr);

    // Update our branch motifs list:
    // Update or store the HTML tag pattern leading to this node.
    let branchpat = sp.branchpat.join('|');
    let motif = {
      n : 0
    };
    if ( p.motifs.has( branchpat ) ) {
      motif = p.motifs.get( branchpat );
    }
    motif.n++;
    p.motifs.set( branchpat, motif );

    let attrinfo = '';
    if ( nm.nodeName == 'A' ) {
      if ( nm.attributes.has('href') )
        attrinfo = nm.attributes.get('href');
    }

    if ( envSet('PAGE_FETCH_CB','1') ) console.log(
      "%s[%d] %s", 
      ' '.repeat(d * 2),
      d,
      altname,
      nm.isLeaf 
      ? nm.content.replace(/[\r\n]/g,' ').replace(/[ \t]{1,}/,' ')
      : attrinfo 
    );
  }//}}}

  async function trigger_page_traverse_cb( sp, nm, p, node_id, d )
  {//{{{
    if ( exception_abort )
      return Promise.resolve(nm);

    trigger_page_fetch_common_cb( sp, nm, p, node_id, d );

    if ( triggerable != 0 ) {

      if ( nm.nodeName == '#text' && nm.content == '[History]' ) {

        // Indicates that we've found a [History] trigger in
        // a child node, so that the triggering tag and surrounding
        // siblings can be captured post-traversal.
        p.child_hits++;
        p.hit_depth = d;

        try {

          triggerable--;
          await traverse_to( node_id, nm );

        }
        catch(e) {
          console.log("Exception at depth %d", 
            d, 
            nm.nodeName, 
            e && e.request !== undefined ? e.request : e, 
            e && e.response !== undefined ? e.response : e, 
            inspect(nm, {showHidden: false, depth: null, colors: true})
          );
          exception_abort = true;
        }
      }
      else if ( p.child_hits > 0 ) {
        await congress_extract_history_panel( sp, nm, p, node_id, d );
      }
    }

    rr_mark = hrtime.bigint();
    return Promise.resolve(nm);
  }//}}}

  async function clickon_node( node_id, nm )
  {//{{{

    let traversable = await traverse_to( node_id, nm );
    
    if ( traversable ) {

      let {model:{content,width,height}} = await DOM.getBoxModel({nodeId: node_id});
      let cx = (content[0] + content[2])/2;
      let cy = (content[1] + content[5])/2;

      await Input.dispatchMouseEvent({
        type: "mouseMoved",
        x: parseFloat(cx),
        y: parseFloat(cy)
      });
      await sleep(300);

      console.log( "Click at (%d,%d)", cx, cy );
      await Input.dispatchMouseEvent({
        type: "mousePressed",
        x: parseFloat(cx),
        y: parseFloat(cy),
        button: "left",
        clickCount: 1
      });
      await sleep(10);

      console.log( "Release (%d,%d)", cx, cy );
      await Input.dispatchMouseEvent({
        type: "mouseReleased",
        x: parseFloat(cx),
        y: parseFloat(cy),
        button: "left"
      });
      return sleep(200);
    }
    return sleep(10);
  }//}}}

  async function trigger_page_fetch_cb( sp, nm, p, node_id, d )
  {//{{{
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

    if ( exception_abort )
      return Promise.resolve(nm);

    trigger_page_fetch_common_cb( sp, nm, p, node_id, d );

    if ( triggerable != 0 ) {//{{{

      if ( nm.nodeName == '#text' && nm.content == '[History]' ) {//{{{

        // Indicates that we've found a [History] trigger in
        // a child node, so that the triggering tag and surrounding
        // siblings can be captured post-traversal.
        p.child_hits++;
        p.hit_depth = d;

        try {

          triggerable--;
          append_buffer_to_rr_map = 0;

          await clickon_node( node_id, nm );

          if ( append_buffer_to_rr_map > 0 ) {
            let R = (await DOM.describeNode({nodeId: append_buffer_to_rr_map})).node;
            let markup;
            await setup_dom_fetch( append_buffer_to_rr_map );
            await sleep(1000);

            // Climb the popup container tree
            if ( p.lookup_tree.has( append_buffer_to_rr_map ) ) {
              let dp = p.lookup_tree.get( append_buffer_to_rr_map );
              let cka = new Array;
              if ( envSet("VERBOSE","1") ) console.log("Populating container %d",
                append_buffer_to_rr_map
              );
              dp.content.forEach((v,ck,map) => {
                cka.push(ck);
              });
              while ( cka.length > 0 ) {
                let ck = cka.shift();
                if ( envSet("VERBOSE","1") ) console.log("- %d", ck);
                await setup_dom_fetch( ck, append_buffer_to_rr_map );
              }
              // Decompose the dialog container to get at 
              // the good bits: The Close button and link text.
              let dialog_motif = new Array;
              await inorder_traversal(
                {
                  branchpat : dialog_motif 
                },
                dp,
                -1,
                flatten_dialog_container,
                p
              );
              if ( envSet("VERBOSE","1") ) console.log("Dialog container %d",
                append_buffer_to_rr_map,
                inspect(dp,{showHidden: false, depth: null, colors: true}),
              );
            }
            else {
              console.log("MISSING: Container %d not in lookup tree", append_buffer_to_rr_map);
            }
            await sleep(500);
            markup = (await DOM.getOuterHTML({nodeId: append_buffer_to_rr_map})).outerHTML;
            console.log( "TRIGGERED FETCH %s INTO %s %d", 
              xhr_fetch_rr,
              p.lookup_tree.has( append_buffer_to_rr_map ) ? "in-tree" : "missing",
              append_buffer_to_rr_map,
              envSet("VERBOSE","1") ? markup : '',
              envSet("VERBOSE","1") ? inspect(R,{showHidden: false, depth: null, colors: true}) : '',
              nodes_seen.size
            );
            await sleep(500);
            await reduce_nodes( nodes_seen, nodes_seen, get_outerhtml );

            if ( envSet("VERBOSE","1") ) console.log( "Reduced", inspect(nodes_seen,{showHidden: false, depth: null, colors: true}) );

            write_map_to_file( "Fragment", "trie.txt", 
              nodes_seen,
              file_ts,
              p.n
            );
            p.n++;

            p.flattened.clear();
            await inorder_traversal(
              {
                branchpat : new Array 
              },
              nodes_seen,
              -1,
              flatten_container,
              p
            );
            p.flattened = return_sorted_map( p.flattened );

            if ( p.closer_node > 0 ) {
              console.log( "Closing modal using event on %d", p.closer_node );
              await clickon_node( p.closer_node, nm );
            }

            if ( rr_map.has( xhr_fetch_rr ) ) {
              let rr_entry = rr_map.get( xhr_fetch_rr );
              rr_entry.markup = p.flattened;
              rr_map.set( xhr_fetch_rr, rr_entry );
            }

            if ( p.closer_node == 0 ) {
              console.log( "Input needed: Close dialog in 10s" );
            }
            await sleep(5000);
          }
          nodes_seen.clear();
        }
        catch(e) {
          console.log("Exception at depth %d", 
            d, 
            nm.nodeName, 
            e && e.request !== undefined ? e.request : e, 
            e && e.response !== undefined ? e.response : e, 
            inspect(nm, {showHidden: false, depth: null, colors: true})
          );
          exception_abort = true;
        }
        // if ( nm.nodeName == '#text' && nm.content == '[History]' )
      }//}}}
      else if ( p.child_hits > 0 ) {
        await congress_extract_history_panel( sp, nm, p, node_id, d );
      }
      // triggerable != 0
    }//}}}

    rr_mark = hrtime.bigint();
    return Promise.resolve(nm);
  }//}}}
  
  async function setup_dom_fetch( nodeId, parentId )
  {//{{{
    rootnode   = nodeId;
    rr_mark    = hrtime.bigint();
    cycle_date = new Date();
    rr_begin   = rr_mark;
    rootnode_n = (await DOM.resolveNode({nodeId: nodeId}));
    waiting_parent = nodeId;
    nodes_seen.set( waiting_parent, {
      nodeName   : rootnode_n.object.description,
      parentId   : parentId !== undefined ? parseInt(parentId) : 0,
      attributes : new Map,
      isLeaf     : false,
      content    : new Map
    });
    if (envSet("SETUP_DOM_FETCH","1")) console.log("setup_dom_fetch( %d )", 
      nodeId, 
      inspect(rootnode_n,{showHidden: false, depth: null, colors: true})
    );
    parents_pending_children.unshift( nodeId );
    await trigger_dom_fetch();
    return Promise.resolve(true);
  }//}}}
  
  async function domAttributeModified(params)
  {//{{{
    // This event is triggered by clicking on [History] links on https://congress.gov.ph/legisdocs/?v=bills 
    if ( params.value == 'modal fade in' ) {
      append_buffer_to_rr_map = params.nodeId;
      let markup = (await DOM.getOuterHTML({nodeId: append_buffer_to_rr_map})).outerHTML;
      let R = (await DOM.describeNode({nodeId: append_buffer_to_rr_map})).node;
      if ( latest_rr !== 0 && rr_map.has( latest_rr ) ) {
        let rr_entry = rr_map.get( latest_rr );
        rr_entry.markup = markup;
        rr_map.set( latest_rr, rr_entry );
        console.log( "Markup from nodeId[%d] recorded to R/R[%s]",
          append_buffer_to_rr_map,
          latest_rr,
          envSet("VERBOSE","1") ? markup : '',
          envSet("VERBOSE","1") ? inspect(R,{showHidden: false, depth: null, colors: true}) : ''
        ); 
        xhr_fetch_rr = latest_rr;
        latest_rr = 0;
        return setup_dom_fetch( append_buffer_to_rr_map );
      }
    }
    return Promise.resolve(false);
  }//}}}

  async function domChildNodeInserted(params)
  {//{{{
    if ( postprocessing > 0 ) {
      // Update lookup_tree
      let nodeId = params.node.nodeId;
      let parentId = params.parentNodeId;
      let n = {
        nodeName   : params.node.nodeName,
        parentId   : parseInt(parentId),
        attributes : mapify_attributes( params.node.attributes ),
        isLeaf     : params.node.childNodeCount == 0,
        content    : new Map
      };
      // Ensure that parent exists and refers to this child node
      if ( parentId !== undefined && parentId > 0 ) {
        if ( lookup_tree.has( parentId ) ) {
          let pn = lookup_tree.get( parentId );
          if ( !pn.content instanceof Map ) {
            if ( envSet("DOM","1") ) console.log( "DOM::childNodeInserted: Replacing .content %s with Map", pn.content );
            pn.content = new Map;
          }
          if ( pn.content !== undefined  && pn.content instanceof Map ) {
            if ( pn.content.has( nodeId ) ) {
              if ( envSet("DOM","1") ) console.log( "DOM::childNodeInserted: Preexisting child %d in %d",
                nodeId,
                parentId
              );
            }
            else {
              pn.content.set( nodeId, null );
              if ( envSet("DOM","1") ) console.log( "DOM::childNodeInserted: Adding child %d to %d", nodeId, parentId );
            }
          }
          lookup_tree.set( parentId, pn );
        }
        else {
          console.log( "DOM::childNodeInserted: Parent %d missing", parentId );
        }
      }
      // Insert the node if not yet present in lookup_tree
      if ( lookup_tree.has( nodeId ) ) {
        let dn = lookup_tree.get( nodeId );
        console.log( "DOM::childNodeInserted",
          "Node %d already present in tree", 
          nodeId,
          dn,
          n
        );
      }
      else {
        lookup_tree.set( nodeId, n );
        if ( envSet("DOM","1") ) console.log(
          "DOM::childNodeInserted: Added node %d to tree",
          nodeId
        );
      }
    }
    else {
      if ( envSet("DOM","1") ) console.log( 'DOM::childNodeInserted', params );
    }
    return Promise.resolve(true);
  }//}}}

  async function domChildNodeRemoved(params)
  {//{{{
    if ( postprocessing > 0 ) {
      let nodeId   = params.nodeId;
      let parentId = params.parentNodeId;
      if ( lookup_tree.has( nodeId ) ) {
        let n = lookup_tree.get( nodeId );
        lookup_tree.delete( nodeId );
        if ( envSet("DOM","1") ) console.log( 'DOM::childNodeRemoved[%d]',
          nodeId,
          inspect(n,{showHidden: false, depth: null, colors: true}) 
        );
      }
      else {
        if ( envSet("DOM","1") ) console.log( 'DOM::childNodeRemoved[%d] not in tree', nodeId );
      }
      if ( lookup_tree.has( parentId ) ) {
        let pn = lookup_tree.get( parentId );
        if ( pn.content !== undefined && pn.content instanceof Map ) {
          if ( pn.content.has( nodeId ) ) {
            if ( envSet("DOM","1") ) console.log( 'DOM::childNodeRemoved[%d] from %d',
              nodeId, parentId
            );
            pn.content.delete( nodeId );
          }
        }
        lookup_tree.set( parentId, pn );
      }
    }
    return Promise.resolve(true);
  }//}}}

  async function get_outerhtml( nm, nodeId )
  {//{{{
    return (await DOM.getOuterHTML({ nodeId : nodeId })).outerHTML;
  }//}}}

  async function finalize_metadata( step )
  {//{{{
    // Chew up, digest, dump, and clear captured nodes.
    file_ts = datestring( cycle_date );
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

        // Copy into lookup_tree before reducing to tree
        nodes_seen.forEach((v,key,map) => {
          lookup_tree.set( key, {
            nodeName : v.nodeName,
            parentId : parseInt(v.parentId),
            content  : v.content,
            isLeaf   : v.isLeaf
          });
        });

        // Reduce nodes_seen to traversable tree
        await reduce_nodes( nodes_seen, nodes_seen, get_outerhtml );

        rr_time = hrtime.bigint();
        console.log( "\r\nDOM tree structure finalized with %d nodes", lookup_tree.size, rr_time_delta() );

        // Inorder traversal of "clean" HTML
        console.log( "Building %d nodes", nodes_seen.size );

        let tagstack = new Map; // At each recursive step up the tree (from root node d = 0), we use this Map of array elements to track unique HTML tags found
        let motifs   = new Map;
        let tagmotif = new Array; // A simple array of all nodes reached at a point in tree traversal

        postprocessing = 3;

        // Transfer working tree to nodes_tree; nodes_seen is reused
        // to refetch modal popups filled with XHR-fetched HTML fragments.
        nodes_seen.forEach((value,key,map) => {
          let v = value;
          nodes_tree.set( key, v );
          nodes_seen.delete( key );
        });

        let textkeys = new Map;
        await inorder_traversal( 
          {
            branchpat   : tagmotif
          },
          nodes_tree, 
          -1, 
          envSet("MODE","TRAVERSE") 
          ? trigger_page_traverse_cb
          : trigger_page_fetch_cb, 
          {
            tagstack     : tagstack, // Retains tag counts at depth d
            motifs       : motifs, // A 'fast list' of DOM tree branch tag patterns ending in leaf nodes
            lookup_tree  : lookup_tree, // Flat Map of document nodes 
            dialog_nodes : new Map,
            closer_node  : 0,
            child_hits   : 0, // Count of 'interesting' nodes in children
            hit_depth    : 0, // Viz. congress_extract_history_panel: Tunable to set depth of panel traversal
            flattened    : new Map, // Stores History markup tree
            textkeys     : textkeys, // Stores bill history dictionary 
            n            : 0
          }
        );
         
        postprocessing = 0;

        rr_time = hrtime.bigint();
        console.log( "Built %d nodes", nodes_tree.size, rr_time_delta(),
          (envSet("DUMP_PRODUCT","1")) 
          ? inspect(lookup_tree, {showHidden: false, depth: null, colors: true})
          : nodes_tree.size 
        );
        write_to_file( "tagstack.txt", file_ts, 
          inspect(tagstack, {showHidden: false, depth: null, colors: true})
        );
        write_to_file( "trie.txt", file_ts, 
          inspect(lookup_tree, {showHidden: false, depth: null, colors: true})
        );
        write_to_file( "motifs.txt", file_ts, 
          inspect(motifs, {showHidden: false, depth: null, colors: true})
        );
        write_map_to_file( "Panel key frequency", "panelkeys.txt",
          textkeys,
          file_ts
        );

        console.log( "Everything", 
          rr_time_delta(), 
          (envSet("DUMP_PRODUCT","1"))
          ? inspect(nodes_tree, {showHidden: false, depth: null, colors: true})
          : nodes_tree.size
        );
        // Clear metadata storage

        rr_time = hrtime.bigint();
        console.log( "DONE", rr_time_delta() );

        write_map_to_file("Everything",
          "everything.json",
          nodes_tree,
          file_ts 
        );

        console.log( "Currently", Date() );
      }

      if ( rr_map.size > 0 ) {
        write_map_to_file( "Network exchanges",
          "network.json",
          rr_map, 
          file_ts
        );
        rr_map.clear();
      }

      nodes_seen.clear();
      nodes_tree.clear();
      lookup_tree.clear();
      cycle_date = null;

      if ( exception_abort ) {
        console.log( "Process Abort" );
        process.exit(1);
      }
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

    await DOM.attributeModified(async (params) => {
      if ( envSet("DOM","1") ) console.log( 'DOM::attributeModified', params );
      await domAttributeModified(params);
    });

    await DOM.attributeRemoved(async (params) => {
      if ( envSet("DOM","1") ) console.log( 'DOM::attributeRemoved', params );
    });

    await DOM.characterDataModified(async (params) => {
      if ( envSet("DOM","1") ) console.log( 'DOM::characterDataModified', params );
    });

    await DOM.childNodeCountUpdated(async (params) => {
      if ( envSet("DOM","1") ) console.log( 'DOM::childNodeCountUpdated', params );
    });

    await DOM.childNodeInserted(async (params) => {
      await domChildNodeInserted(params);
    });

    await DOM.childNodeRemoved(async (params) => {
      if ( envSet("DOM","1") ) console.log( 'DOM::childNodeRemoved', params );
      await domChildNodeRemoved(params);
    });

    await DOM.documentUpdated(async (params) => {
      console.log( 'DOM::documentUpdated', params );
    });

    await Page.loadEventFired(async (ts) => {
      const { currentIndex, entries } = await Page.getNavigationHistory();
      const {root:{nodeId}} = await DOM.getDocument({ pierce: true });

      await setup_dom_fetch( nodeId );

      current_url = entries && entries.length > 0 && entries[currentIndex] && entries[currentIndex].url 
        ? entries[currentIndex].url 
        : '---';

      console.log("LOAD EVENT root[%d]", 
        nodeId, 
        ts,
        datestring( cycle_date ),
        current_url
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

    await Page.setInterceptFileChooserDialog({enabled : true});
    
    await Page.fileChooserOpened(async (p) => {
      console.log("Page::fileChooseOpened", p);
    });

    await Page.frameAttached(async (p) => {
      console.log("Page::frameAttached",p);
    });

    await Page.frameDetached(async (p) => {
      console.log("Page::frameDetached",p);
    });
    
    await Page.frameNavigated(async (p) => {
      console.log("Page::frameNavigated",p);
    });
    
    await Page.interstitialHidden(async (p) => {
      console.log("Page::interstitialHidden",p);
    });
    
    await Page.interstitialShown(async (p) => {
      console.log("Page::interstitialShown",p);
    });
    
    await Page.javascriptDialogClosed(async (p) => {
      console.log("Page::javascriptDialogClosed",p);
    });
    
    await Page.javascriptDialogOpening(async (p) => {
      console.log("Page::javascriptDialogOpening",p);
    });

    await Input.setIgnoreInputEvents({ignore: false});

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
}//}}}

async function ingest()
{
  let fn = env['TARGETURL'];

  let s = await setup_db();

  console.log( "Check", typeof s , inspect(s, {showHidden: false, depth: null, colors: true}) );

  if ( !existsSync( fn ) ) {
    console.log( "Unable to see %s", fn );
    process.exit(1);
  }

  let j = read_map_from_file( fn );

  if ( j.history !== undefined ) {
    if (1) console.log( 
      "Metadata",
      fn,
      inspect(j, {showHidden: false, depth: null, colors: true})
    );

    await treeify( j.history );

    if (0) console.log(
      "Reduced",
      inspect(j, {showHidden: false, depth: null, colors: true})
    );

    let branchpat = new Array;
    let markup_a = new Map;

    async function stack_markup( sp, nm, p, node_id, d )
    {
      if ( nm.isLeaf ) {
        let $ = cheerio.load( nm.content, null, false );
        if (0) console.log( "Element", typeof nm.content, $('td').text() || nm.content );
        if (0) $('td').children().each(function (i,e) {
          console.log("- %d", i, $(this).text, $(this).text() );
        });
        p.markup_a.set( parseInt(node_id), $('td').text() || nm.content );
      }
    }

    await inorder_traversal(
      { branchpat : branchpat },
      j.history, -1,
      stack_markup,
      { markup_a : markup_a }
    );

    console.log(
      "Markup",
      inspect({
        url     : j.url,
        id      : j.id,
        links   : j.links,
        text    : j.text,
        history : markup_a
      }, {showHidden: false, depth: null, colors: true})
    );

    await sleep(1000);
  }
  process.exit(0);
}

if ( envSet("ACTIVE_MONITOR","1") ) {
  monitor();
}

if ( envSet("PARSE","1") ) {
  ingest();
}
