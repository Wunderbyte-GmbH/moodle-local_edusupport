Description of the html2canvas import into local_edusupport
===========================================================

html2canvas renders the current page into a canvas, so that a support
request can carry a screenshot of the problem the user is looking at.

Library:   html2canvas
Version:   1.0.0-alpha.12
License:   MIT
Homepage:  https://html2canvas.hertzen.com
Repository: https://github.com/niklasvh/html2canvas

The library is declared in ../../thirdpartylibs.xml, which keeps the
Moodle linters and the coding style checks off it. Do not edit the file
itself - any local change would be lost on the next upgrade and would
make the declared version wrong.

To upgrade
----------

1. Download the distributed html2canvas.js of the wanted release from
   https://github.com/niklasvh/html2canvas/releases
2. Replace amd/src/html2canvas.js with it, keeping the file name.
3. Update the version in ../../thirdpartylibs.xml and in this file.
4. Rebuild the AMD modules from the Moodle root:
   npx grunt amd --root=local/edusupport
5. Commit amd/src/html2canvas.js together with the rebuilt
   amd/build/html2canvas.min.js and its source map.
