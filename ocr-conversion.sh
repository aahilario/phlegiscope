#!/bin/bash

clear

function tesseract_convert() {
  FILE=$1
  rm -f *.ppm *.pbm *.tif *.txt
  BASENAME=`echo ${FILE} | sed -E -e 's@(.pdf)$@@g'`

  PAGECOUNT=`pdfinfo ${BASENAME}.pdf | grep Pages: | cut -f2 -d: | sed -E -e 's@[^0-9]@@g'`
  P=1
  rm -f ${BASENAME}.txt
  touch ${BASENAME}.txt

  while [ $P -le $PAGECOUNT ]; do
    rm -f *.pbm
    ROTATION=`pdfinfo -f $P -l $P $FILE | tr '\t' ' ' | tr -s ' ' | grep "rot:" | cut -f2 -d: | sed -E -e 's@[^0-9]@@g'`
    [ $ROTATION == 270 ] && ROTATION=-90 
    ROTATION=`echo "${ROTATION} * -1" | bc`
    echo "Rotate ${P}/${PAGECOUNT} ${ROTATION}"
    SUFFIX=`printf "%03d" $P`
    pdfimages -f $P -l $P $FILE $BASENAME && { 
      cat ${BASENAME}*.pbm | pnmrotate -noantialias ${ROTATION} > ${BASENAME}.rot
      mv ${BASENAME}.rot ${BASENAME}.pbm
      ppm2tiff ${BASENAME}.pbm ${BASENAME}-${SUFFIX}.tif
      rm -f *.pbm
      tesseract ${BASENAME}-${SUFFIX}.tif result -l eng -psm 1 ${TESS_CONFIG}
      cat result.txt >> ${BASENAME}.txt
      rm -f result.txt
    }
    P=`echo "${P}" + 1 | bc`
  done
  rm -f *.ppm *.pbm *.tif
}

while true; do
  SRC=`find cache/ocr -type f -name *.pdf`
  for i in $SRC; do
    SOURCEDIR=`dirname $i`
    SOURCENAME=`basename $i`
    TARGETNAME=`echo "${SOURCENAME}" | sed -E -e 's@\.pdf$@.txt@g'`
    pushd $SOURCEDIR 2>&1 > /dev/null
    echo " "
    echo "-- Converting ${SOURCENAME} to ${TARGETNAME}"
    # pdfopt "${SOURCENAME}" "${SOURCENAME}.opt.pdf" 
    # pdftotext -layout -raw "${SOURCENAME}.opt.pdf" ${TARGETNAME}.tmp &&
    pdftotext -layout -raw "${SOURCENAME}" ${TARGETNAME}.tmp && {
      WORDCOUNT=`cat ${TARGETNAME}.tmp | tr '\f' '\n' | wc -w`
      WORDCOUNT=`echo ${WORDCOUNT}`
      [ $WORDCOUNT == "0" ] && {
        echo "-- Try alternate conversion method"
        rm -vf "${SOURCENAME}"{.orig.pdfx} "${SOURCENAME}".tmp "${TARGETNAME}"{,.tmp,.txt}
        tesseract_convert "${SOURCENAME}"
        rm -vf "${SOURCENAME}"
        # rm -vf "${SOURCENAME}" "${SOURCENAME}".tmp "${TARGETNAME}"{,.tmp,.txt}
      } || {
        mv ${TARGETNAME}.tmp ${TARGETNAME}
        cat ${TARGETNAME}
        pdfinfo ${SOURCENAME}
        rm -f "${SOURCENAME}" "${SOURCENAME}.opt.pdf"
      }
    } || {
      echo "-- Conversion failed"
      rm -f "${SOURCENAME}" "${SOURCENAME}".tmp "${TARGETNAME}" "${TARGETNAME}".tmp
    }
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
