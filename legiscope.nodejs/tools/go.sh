#!/bin/bash
LOGFILE=output
YMDPATH=$(date +"%Y-%m-%d").d
rm -f ${YMDPATH}/${LOGFILE}.log
rm -f ${YMDPATH}/pre-transform.json
rm -f ${YMDPATH}/everything.json
OUTPUT_LOG=${YMDPATH}/${LOGFILE}-$(date +"%Y-%m-%d-%H%M%S").log
export ACTIVE_MONITOR=1
export CB_PREPROCESS=1
export CONGRESS_BILLRES_CB=0
export DEBUG_OUTPUT_PATH=$YMDPATH
export DOM=1
export DOMSETCHILDNODES=0
export DUMP_PRODUCT=0
export FINALIZE_METADATA=0
export GRAFT=2
export INORDER_TRAVERSAL=0
export MODE=XTRAVERSE
export PAGE_FETCH_CB=0
export PARSE=0
export PRUNE_CB=0
export QA=1
export REVERSE_CONTENT=10
export SETUP_DOM_FETCH=0
export SN_INORDER_TRAVERSAL=2
export TRIGGER_DOM_FETCH=0
export VERBOSE=0
export WATCHDOG=0
[ -d ${YMDPATH} ] || mkdir -p ${YMDPATH}

touch ${OUTPUT_LOG}
ln -sf $(basename ${OUTPUT_LOG}) ${YMDPATH}/${LOGFILE}.log

export TARGETURL=$1
shift
node \
  --max-old-space-size=8192 \
  --max-heap-size=8192 \
  --huge-max-old-generation-size \
  sp-test-v2.js $@ 2>&1 | tee -a ${OUTPUT_LOG}


