<?php

function ucfirst_with_exceptions($word) {
  $exceptions = array(
    'Feir' => 'FEIR',
    'Deir' => 'DEIR',
    'Toc' => 'TOC',
    'Rtc' => 'RTC',
    'A' => 'a',
    'Of' => 'of',
    'On' => 'on',
    'The' => 'the',
  );
  return str_replace(array_keys($exceptions), array_values($exceptions), ucfirst($word));
}

function ucfirst_each_word($words) {
  return implode(' ', array_map('ucfirst_with_exceptions', explode(' ', $words)));
}

function page_number($filename) {
  return preg_replace('/pg_0*([1-9][0-9]*).*/', '${1}', basename($filename));
}

function section_number($filename) {
  return strtr(preg_replace('/^([0-9-]*).*/', '${1}', basename(dirname($filename))), '-', '.');
}

function section_title($filename) {
  return ucfirst(ucfirst_each_word(preg_replace('/^([0-9 ]*)/', '', strtr(basename(dirname($filename)), '_-', '  '))));
}

function get_css() {
  return '<style type="text/css">
<!--
#header {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  width: 100%;
  height: 28px;
  background: #ddd;
  margin: 0;
  padding: 4px;
  text-align: center;
}
#header h3 {
  display: inline;
  margin: 0 auto;
  padding: 0;
}
#header a, #header span {
  padding: 4px;
  margin: 0 4px;
  text-align: center;
  width: 32px;
  -moz-border-radius: 8px;
  -webkit-border-radius: 8px;
  -khtml-border-radius: 8px;
  border-radius: 8px;
}
#header .up { padding-left: 8px; }
#header span { background: #ccc; color: #888;  }
#header span.selected {
  background: #f88;
  color: #000;
  padding-left: 8px;
}
#header a:link {text-decoration: none; color: black; border-bottom: 0; background: #aaa; }
#header a:visited {text-decoration: none; color: black; border-bottom: 0; background: #aaa; }
#header a:active {text-decoration: none; color: black; border-bottom: 0; background: #faa; }
#header a:hover {text-decoration: none; color: black; border-bottom: 0; background: #caa; }

#header .left-controls {
  position: absolute;
  top: 8px;
  left: 8px;
  margin-left: 16px;
}
#header .right-controls {
  position: absolute;
  top: 8px;
  right: 8px;
  margin-right: 16px;
}

ul.toc {
  margin-top: 48px;
  margin-left: 48px;
}

img.original {
  width: 64px;
  height: auto;
  position: absolute;
  top: 48px;
  border: 1px solid #000;
  left: 8px;
  padding: 2px;
}
-->
</style>';
}

function find_reference_url($page, $top) {
  $reference_url = FALSE;

  // First try to find a link on the same line.
  if (preg_match('@style="[^"]*top:\s*' . $top . ';[^>]*>\<a[^>]*href="([^"]*)">\<span[^>]*>\[*See@im', $page, $match)) {
    $reference_url = $match[1];
  }
  // If there is no cross-reference, look for the text "See page N-N" on the same line.
  // Note that in this case, there is no link on the page number; if there were, then
  // the regexp above would match.
  if (!$reference_url && preg_match('@page</span></div>\s*\<div[^>]*style="[^"]*top:\s*' . $top . ';[^>]*>\<span[^>]*>([1-9][0-9.]*)-([1-9][0-9]*)@im', $page, $match)) {
    $reference_url = $match[1];
  }
  if ($reference_url) {
    $reference_url = preg_replace("@file://localhost/[^.]*/([^.]*)\.pdf#page=([0-9]*)@", '../${1}/pg_000${2}.htm', $reference_url);
  }

  return $reference_url;
}


function fix_early($page) {
  // Convert "Meeting NNN-MMM" to "MeetingNNN-MMM" so that
  // it follows the same pattern as the other sections.
  $page = preg_replace('#(Meeting|SFBA|CPA)</span></div>\s*
<div[^>]*><span[^>]*>([1-9][0-9]*-[1-9][0-9]*)#im', '${1}${2}', $page);

  // When there are multiple links back to back with no
  // intervening text, join them together into a single link.
  // The general-case expression sometimes joined together
  // links with different targets.  Removing these correctly
  // with a back-reference is not convenient, so we'll just
  // special-case a few common instances where this happens.
  $page = preg_replace("#(Master|Response|\[See|[1-9][0-9]*-[1-9][0-9]*|for|the|original|comment\])</span></a></div>[ \t\n]*<div[^>]*><a[^>]*><span[^>]*>#m", '${1} ', $page);

  return $page;
}

function fix_page($page, $input) {
  $page = fix_early($page);

  // Fix the page title -- convert the input path into a title string
  $section_number = section_number($input);
  $pg_number = page_number($input);
  $section_title = section_title($input);
  $title = $section_number . ' ' . $section_title . ', p. ' . $pg_number;
  $page = preg_replace('#<title>([^<]*)</title>#', "<title>$title" . ', p. ${1}</title>', $page);

  // Get the previous and next links, if they exist
  $has_prev = preg_match('<a href="([^"]*)" title="Go to previous page">', $page, $matches);
  $prev_link = $has_prev ? " <a class='prev' href='${matches[1]}'>&lt;</a> " : '<span>&lt;</span>';
  $has_next = preg_match('<a href="([^"]*)" title="Go to next page">', $page, $matches);
  $next_link = $has_next ? " <a class='next' href='${matches[1]}'>&gt;</a> " : '<span>&gt;</span>';

  // We will put the TOC control on every page except for the first TOC page itself
  $toc_control = "<a class='up' href='../0_toc_feir/pg_0001.htm'> TOC </a>";
  if (strstr($toc_control, dirname($input)) !== FALSE) {
    $toc_control = "<span class='selected'> TOC </span>";
  }

  // Insert a page header with previous / next links
  $page = preg_replace('@<body([^>]*)>@', "<body\${1}><div id='header'> <div class='left-controls'>$prev_link $next_link</a> <a class='up' href='index.htm'> GO </a> $toc_control</div> <h3>$title</h3> <div class='right-controls'>$prev_link $next_link</a></div> </div>", $page);

  // Remove the provided links to the index page
  $page = preg_replace('@<div([^>]*)><font([^>]*)>Pages: <a href="index.htm">Index</a> *</font> *</div>@', '', $page);

  // Insert some page styles
  $page = preg_replace('@</head>@', get_css() . '</head>', $page);

  // Convert the background image into a thumbnail. Clicking on it will show the original background image.
  $page = preg_replace('#(.img) *(name="\*[0-9][0-9][0-9][0-9][^>]*) src="([^"]*)"([^>]*>)#', '<a href="${3}">${1} class="original" ${2} src="${3}"${4}</a>', $page);

  return $page;
}

function fix_common($page) {
  // Fix link colors
  $page = preg_replace("#A:link {text-decoration: none; color: blue}#", 'A:link {text-decoration: none; color: black; border-bottom: 1px dashed red;}', $page);
  $page = preg_replace("#A:visited {text-decoration: none; color: purple}#", 'A:visited {text-decoration: none; color: black; border-bottom: 1px dashed purple;}', $page);
  $page = preg_replace("#A:active {text-decoration: red}#", 'A:active {text-decoration: none; color: black; border-bottom: 2px solid red;}', $page);
  $page = preg_replace("#A:hover {text-decoration: underline; color:red}#", 'A:hover {text-decoration: none; color: black; border-bottom: 1px solid red;}', $page);

  // Add hyperlinks to page references
  $page = preg_replace('@<span id="\*[0-9]*"([^>]*)>([0-9.]*)-([0-9]*)([^a-zA-Z0-9<]*)</span>@', '<span id="*20"${1}><a href="../###_${2}#/pg_000$3.htm">${2}-${3}${4}</a></span>', $page);

  return fix_links($page);
}

function fix_links($page) {
  // Fix links from absolute paths to the original pdfs to relative links to the html
  $page = preg_replace("@file://localhost/[^.]*/([^.]*)\.pdf#page=([0-9]*)@", '../${1}/pg_000${2}.htm', $page);
  $page = preg_replace("/_000([1-9][0-9][0-9][0-9])\./", '_${1}.', $page);
  $page = preg_replace("/_000([1-9][0-9][0-9])\./", '_0${1}.', $page);
  $page = preg_replace("/_000([1-9][0-9])\./", '_00${1}.', $page);

  return $page;
}

function fix_go_to_page($page, $input) {
  // Fix the page title -- convert the input path into a title string
  $section_number = section_number($input);
  $pg_number = page_number($input);
  $section_title = section_title($input);
  $title = $section_number . ' ' . $section_title . ' - Go To Page';
  $page = preg_replace('#<title>([^<]*)</title>#', "<title>$title" . '</title>', $page);

  // Get the previous and next links, if they exist
  $has_prev = preg_match('<a href="([^"]*)" title="Go to previous page">', $page, $matches);
  $prev_link = $has_prev ? " <a class='prev' href='${matches[1]}'>&lt;</a> " : '<span>&lt;</span>';
  $has_next = preg_match('<a href="([^"]*)" title="Go to next page">', $page, $matches);
  $next_link = $has_next ? " <a class='next' href='${matches[1]}'>&gt;</a> " : '<span>&gt;</span>';

  // Insert a page header with previous / next links
  $page = "<html><head>" . get_css() . "</head><body><div id='header'> <div class='left-controls'>$prev_link $next_link</a> <span class='selected'> GO </span> <a class='up' href='../0_toc_feir/pg_0001.htm'> TOC </a></div> <h3>$title</h3> <div class='right-controls'>$prev_link $next_link</a></div> </div><ul class='toc'>" . $page . "</ul></body></html>";

  // Convert TOC links into a list
  $page = preg_replace('@<font[^>]*>[^<]*</font>@', '', $page);
  $page = preg_replace('@(<a href="[^"]*">[^>]*</a>)@', '<li>${1}</li>', $page);

  return $page;
}
