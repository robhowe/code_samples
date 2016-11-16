#!/bin/sh
#
# extract_doc.sh
#
# Author: Rob Howe
#
# Usage:  extract_doc [options] <filename>
#
# Options
#  -s  Strip off braces from around first word
#      of description comment.
#
# Extract documentation from code files.
# Prints out all blocks beginning with "/*+"
# and ending with "*/".
# Also does a lot of specific typesetting.


# Determine if strip-description option is on.
if [ $1 = "-s" ]
then
  STRIPD=1
  shift
else
  STRIPD=0
fi

if [ -f $1 ]
then
# Get raw documentation lines.
  sed -n '/\/\*+/,/\*\//p' $1 |

# Now we want to modify the raw lines for our purposes:
# Remove first "*" from every line, etc.

  gawk '{
      printed = 0
      if ($1 == "/*+") {
         numargs  = 0
         funcname = $2
         while (funcname == "") {
           getline
           funcname = $2
         }
         printf "  Name\n\t%s\n", substr($0, index($0, funcname))
         getline
         print substr($0,3)
         printed = 1
      }
      if ($2 == "SECTION:") {
#        printf "  Section%s\n", substr($0, 13)
         printed = 1
      }
      if ($2 == "SYNOPSIS:") {
         printf "  Synopsis%s\n", substr($0, 14)
         RS = "("
         getline
         printf "%s(", substr($0,3)
         RS = ")"
         getline
         printf "%s)", $0
         numargs = split($0,argarray,",")
         RS = "\n"
         printed = 1
      }
      if ($2 == "DESCRIPTION:") {
         printf "  Arguments\n"
         if (numargs == 0)
           printf "\tnone\n"
         else
           for (loop=0; loop<numargs; loop++) {
              if (substr(argarray[loop+1],1,1) == " ")
                 printf "\t%s\t\n", substr(argarray[loop+1],2)
              else
                 printf "\t%s\t\n", argarray[loop+1]
           }
         printf "\n  Description%s\n", substr($0, 17)
         if (stripdescript == 1) {
            FS = "}"
            getline
            if (NF < 2)
               print substr($0,3)
            else {
               printf "\t%s", funcname
               if ((substr($2,2,1) >= "A") && (substr($2,2,1) <= "Z"))
                  printf " %s%s\n", tolower(substr($2,2,1)), substr($2,3)
               else
                  printf "%s\n", $2
            }
            FS = " "
         }
         printed = 1
      }
      if ($2 == "SEE") {
         if ($3 == "ALSO:") {
            printf "  Structures\n\tnone\n\n  Errors\n\tnone\n\n"
            printf "  Related Commands%s\n", substr($0, 14)
            getline
            if ($1 == "*/")
               printf "\tnone\n"
            else
               print substr($0,3)
            printed = 1
         }
      }
      if ($1 == "*/") {
         printf "\n\f\n\n"
         printed = 1
      }
      if (printed == 0)
         print substr($0,3)
}' stripdescript=$STRIPD
else
  echo extract_doc: cannot access $1
fi
