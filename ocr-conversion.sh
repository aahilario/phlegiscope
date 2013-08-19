#!/bin/bash

clear

while true; do
  SRC=`find cache/ocr -type f -name *.pdf`
  for i in $SRC; do
    SOURCEDIR=`dirname $i`
    SOURCENAME=`basename $i`
    TARGETNAME=`echo "${SOURCENAME}" | sed -E -e 's@\.pdf$@.txt@g'`
    pushd $SOURCEDIR 2>&1 > /dev/null
    echo " "
    echo "-- Converting ${SOURCENAME} to ${TARGETNAME}"
    pdftotext -layout -raw "${SOURCENAME}" ${TARGETNAME}.tmp &&
    mv ${TARGETNAME}.tmp ${TARGETNAME}
    cat ${TARGETNAME}
    # rm -f ${SOURCENAME} ${TARGETNAME}
    rm -f ${SOURCENAME}
    popd 2>&1 > /dev/null
#    -f <int>          : first page to convert
#    -l <int>          : last page to convert
#    -r <fp>           : resolution, in DPI (default is 72)
#    -x <int>          : x-coordinate of the crop area top left corner
#    -y <int>          : y-coordinate of the crop area top left corner
#    -W <int>          : width of crop area in pixels (default is 0)
#    -H <int>          : height of crop area in pixels (default is 0)
#    -layout           : maintain original physical layout
#    -fixed <fp>       : assume fixed-pitch (or tabular) text
#    -raw              : keep strings in content stream order
#    -htmlmeta         : generate a simple HTML file, including the meta information
#    -enc <string>     : output text encoding name
#    -listenc          : list available encodings
#    -eol <string>     : output end-of-line convention (unix, dos, or mac)
#    -nopgbrk          : don't insert page breaks between pages
#    -bbox             : output bounding box for each word and page size to html.  Sets -htmlmeta
#    -opw <string>     : owner password (for encrypted files)
#    -upw <string>     : user password (for encrypted files)
#    -q                : don't print any messages or errors
#    -v                : print copyright and version info
#    -h                : print usage information
#    -help             : print usage information
#    --help            : print usage information
#    -?                : print usage information
  done
  sleep 2
done
