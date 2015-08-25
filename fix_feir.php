<?php

// Usage: php fix_feir.php /path/to/html-src-dir /path/to/html-output-dir
$input_dir = getcwd() . '/' . $argv[1];
$output_dir = getcwd() . '/' . $argv[2];
$singlepage_dir = FALSE;
$singlepage = FALSE;

include __DIR__ . '/fix.inc';

@mkdir($output_dir);

if (isset($argv[3])) {
  $singlepage_dir = getcwd() . '/' . $argv[3];
  $singlepage = $singlepage_dir . '/index.htm';
  @mkdir($singlepage_dir);
  file_put_contents($singlepage, get_singlepage_header());
  //
}

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
      $dir_map["###_${key}#"] = $dir;
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
$chapter_pages = array();
foreach ($input_files as $file) {
  $input = $input_dir . '/' . $file;

  $page = file_get_contents($input);
  $page = fix_early($page);

  // Find the page number at the bottom of the page,
  // and make an entry in the page map for it.
  // We will use the page map to add links to the
  // table of contents.
  if (preg_match_all('/span id="\*[0-9]*"[^>]*>([1-9][0-9\.]*)-([1-9][0-9]*)/', $page, $match_sets, PREG_SET_ORDER)) {
    $matches = array_pop($match_sets);
    $key = $matches[1];
    $pg = $matches[2];
    if (($file[0] > '0') && ((strlen($key) == 1) || ($key[1] == '.'))) {
      if ($key[0] != $file[0]) {
        print "Mismatch between $key and $file on pg $pg\n";
      }
      else {
        $pg_leading_zeros = sprintf("%04d", $pg);
        $dir = dirname($file);
        // We can use MATH here.
        //
        // This will make entries that look like this:
        //      '###_2.10.3#/pg_0001.htm' => '2-10_individuals-rtc_feir/pg_0019.htm',
        // From here on out, all other entries in section
        // 2.10.3 will always point from page N to page (19 - 1) + N.
        // So, we could store only the first entry, and do math
        // to calculate the page of the others.  Maybe later.
        preg_match('@pg_0*([0-9]+)@', $file, $matches);
        if (!empty($matches)) {
          $file_pg = $matches[1];
          $base_page = $file_pg - $pg;
          $base_file = preg_replace('@/pg_.*@', '', $file);
          if (isset($chapter_pages[$key][$base_file])) {
            if ($chapter_pages[$key][$base_file]['max'] < $pg) {
              $chapter_pages[$key][$base_file]['max'] = $pg;
            }
          }
          else {
            $chapter_pages[$key][$base_file] = array(
              'base' => $base_page,
              'max' => $pg,
            );
          }
        }

        // Make an entry in the page map
        $k = "###_${key}#/pg_$pg_leading_zeros.htm";
        $page_map[$k] = $file;
      }
    }
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

// Fill in the gaps in the page map using MATH.
foreach ($chapter_pages as $key => $file_info) {
  foreach ($file_info as $base_file => $info) {
    $base = $info['base'];
    $max = $info['max'];
    $delta = 0;
    if ($base < 0) {
      $delta = -$base;
      $max = $max + $base;
      $base = 0;
    }
    for ($pg = 1; $pg <= $max; $pg++) {
      $pg_leading_zeros = sprintf("%04d", $pg + $delta);
      $k = "###_${key}#/pg_$pg_leading_zeros.htm";
      $file_pg = $pg + $base;
      $file_pg_leading_zeros = sprintf("%04d", $file_pg);
      $file = $base_file . "/pg_$file_pg_leading_zeros.htm";
      if (FALSE) {
        if (array_key_exists($k, $page_map)) {
          if ($page_map[$k] != $file) {
            // This usually means that $k => $file was mis-OCR'ed
            // (e.g. p. "617" OCR'ed as "61 7", and interpreted
            // as p. 61.)  We will just allow our calculated value
            // to overwrite the OCR'ed value.
            //
            // Pages with OCR errors in page numbers:
            //  5_comments-on-deir_feir_pt1/pg_0019.htm
            //  5_comments-on-deir_feir_pt1/pg_0141.htm
            //  5_comments-on-deir_feir_pt2/pg_0038.htm
            //  5_comments-on-deir_feir_pt2/pg_0116.htm
            //  5_comments-on-deir_feir_pt2/pg_0213.htm
            //  5_comments-on-deir_feir_pt2/pg_0244.htm
            //  5_comments-on-deir_feir_pt2/pg_0314.htm
            //
            print "*** Inconsistent page map entry detected:\n";
            print "*** $k => $file and " . $page_map[$k] . "\n\n";
          }
        }
        else {
          print "::: $k => $file\n";
        }
      }
      // Always believe the Math.
      $page_map[$k] = $file;
    }
  }
}

$count = 0;
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

  // Fix up any cross-references that might need hyperlinks.
  // These cross-references are always in the form LABELNNN-MMM.
  // We insure that there is no number after the reference, so that
  // NNN-MM does not match NNN-MMM and split a label in the middle.
  foreach ($reference_map as $reference => $url) {
    $page = preg_replace('@' . $reference . '([^0-9])@', "<a href='$url'>$reference</a>\${1}", $page);
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

  if (($singlepage) && (basename($file) != 'index.htm')) {
    file_put_contents($singlepage, convert_singlepage($page, $input), FILE_APPEND);
  }

  // Remove any unreplaced page references; most of these were
  // probably linked in error.
  $page = preg_replace('@<a href="../###_[^"]*">([^<]*)</a>@', '${1}', $page);
  // Remove any links to the page that we are currently on.  This
  // is typically just the page number at the bottom of the page.
  $page = preg_replace('@<a href="' . $this_page_url . '">([^<]*)</a>@', '${1}', $page);

  file_put_contents($output, $page);

  $count++;
  if ($count > 4) {
    exit(0);
  }
}

if ($singlepage) {
  file_put_contents($singlepage, get_singlepage_footer(), FILE_APPEND);
}

