<?php

// Usage: php fix_feir.php /path/to/html-src-dir /path/to/html-output-dir
$input_dir = getcwd() . '/' . $argv[1];
$output_dir = getcwd() . '/' . $argv[2];

include __DIR__ . '/fix.inc';

$dir_list = scandir($input_dir);
$dir_map = array();
$reference_map = array();
$backlink_reference_map = array();
$backlink_references = array();
$page_map = array();
$input_files = array();
foreach($dir_list as $dir) {
  // Build a mapping of directory names that are siblings of
  // the input directory.  We will use this later for replacements
  // in page links.
  if (preg_match('/^([0-9-]*)_/', $dir, $matches)) {
    $key = strtr($matches[1], '-', '.');
    if (!empty($key[0])) {
      $dir_map["#DIRECTORY_NAME_${key}#"] = $dir;
    }

    // Build a list of input files to process
    $file_list = scandir($input_dir . '/' . $dir);
    foreach ($file_list as $file) {
      if (preg_match('/.htm$/', $file)) {
        $input_files[] = $dir . '/' . $file;
      }
    }
  }
}

// Iterate over all the input files once just to build the
// mapping from subsection page number (e.g. 2.9.3-1) to
// section page number (e.g. 2.9-nnn).
foreach ($input_files as $file) {
  $input = $input_dir . '/' . $file;

  $page = file_get_contents($input);
  // Find the page number at the bottom of the page,
  // and make an entry in the page map for it.
  // We will use the page map to add links to the
  // table of contents.
  if (preg_match_all('/span id="\*[0-9]*"[^>]*>([1-9][0-9\.]*)-([1-9][0-9]*)/', $page, $match_sets, PREG_SET_ORDER)) {
    $matches = array_pop($match_sets);
    $key = $matches[1];
    $pg = sprintf("%04d", $matches[2]);
    $dir = dirname($file);
    // TODO:  We could use MATH here.
    //
    // This will make entries that look like this:
    //      '#DIRECTORY_NAME_2.10.3#/pg_0001.htm' => '2-10_individuals-rtc_feir/pg_0019.htm',
    // From here on out, all other entries in section
    // 2.10.3 will always point from page N to page (19 - 1) + N.
    // So, we could store only the first entry, and do math
    // to calculate the page of the others.  Maybe later.
    $page_map["#DIRECTORY_NAME_${key}#/pg_$pg.htm"] = $file;
  }

  // Find all of the "[See ..." references, and make an
  // entry in the reference map for it.  We will use the
  // reference map to make back-links from the original
  // question to the place that references it.
  if (preg_match_all('@\<div[^>]*style="[^"]*top:\s*([^;]*);[^>]*>\<span id="\*[0-9]*"[^>]*>([a-zA-Z][a-zA-Z0-9]*-[1-9][0-9]*)@im', $page, $match_sets, PREG_SET_ORDER)) {
    foreach ($match_sets as $matches) {
      $top = $matches[1];
      $reference_label = $matches[2];
      $backlink_url = $file;

      $reference_url = find_reference_url($page, $top);
      if ($reference_url) {
        $reference_map[$reference_label] = $reference_url;
        $backlink_reference_map[$reference_label] = '../' . $file;
        $backlink_references[$reference_url][] = $reference_label;
      }
    }
  }
}

// Iterate over all of the input files again and process them
foreach ($input_files as $file) {
  $input = $input_dir . '/' . $file;
  $output = $output_dir . '/' . $file;

  print "$file\n";
  @mkdir(dirname($output));

  $page = file_get_contents($input);

  if (basename($file) == 'index.htm') {
    $page = fix_go_to_page($page, $file);
  }
  else {
    $page = fix_page($page, $file);
  }

  $page = fix_common($page);

  // Fix up the directory names in links
  $page = str_replace(array_keys($page_map), array_values($page_map), $page);
  $page = str_replace(array_keys($dir_map), array_values($dir_map), $page);

  // Fix up any cross-references that might need hyperlinks
  foreach ($reference_map as $reference => $url) {
    $page = preg_replace('@' . $reference . '@', "<a href='$url'>$reference</a>", $page);
  }

  // Check to see if there are any back-references on this page.
  $this_page_url = '../' . $file;
  if (array_key_exists($this_page_url, $backlink_references)) {
    foreach ($backlink_references[$this_page_url] as $reference_label) {
      $backlink_url = $backlink_reference_map[$reference_label];
      $reference_number = preg_replace('@[^-]*-@', '', $reference_label);

      // First, check to see if the back link already exists on the page.
      preg_match('@\<a href="' . $backlink_url . '">\s*<span[^>]*>' . $reference_number . '@im', $page, $matches);
      if (empty($matches)) {
        $page = preg_replace('@(\<span[^>]*>' . $reference_number . '\</span>)@', '<a href="' . $backlink_url . '">${1}</a>', $page);
      }
    }
  }

  $page = fix_links($page);

  file_put_contents($output, $page);
}
