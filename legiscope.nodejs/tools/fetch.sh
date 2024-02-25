#!/bin/bash

rm -f output.log
export SILENT_PARSE=1 
export RECURSIVE=1 
#export REFRESH=1
export TARGETURL="$1" 
time npx wdio run ./wdio.conf.js 2>&1 | tee -a output.log
cat lastrun.json | jq .

