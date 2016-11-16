<?php
//
// Copyright 2004 Robert Howe  -  This code may not be used in any way without the permission of Robert Howe.
// show_pictures.php
//  PHP script to dynamically display a web gallery of digital photos.
//  Setup - Create subdirectories for categories (eg.: /camping, /birthday_party)
//          Then under each subdir, create an index.html and sub-subdirs named:
//          /bigimages, /descriptions, /images, /thumbnails
//          In real-time, the list of files in the /images dir will be used to drive
//          the web gallery.  If a .txt file exists in the /descriptions dir, its content
//          will be displayed with it's corresponding filename's image picture.
//          If a filename exists in the /bigimages dir, it will be linked from the "images" picture.
//          Use existing provided index.html file as a template for new categories.
//  Other files - index.html, styles_content.css, styles_photos.css, image files
//
// For permission to use this code, email:  rob@robhowe.com
// Last updated:  2004/12/07
//

  // Programmer/developer must configure the following:
  $my_name            = "Rob Howe";  // configure
  $my_http_referer    = strtolower(".com/robhowe/pictures/");  // configure  (string should match rightmost portion)
  $my_webmaster_email = "rob@robhowe.com";  // configure
  $my_main_page       = "../pictures.html";  // configure
  $my_main_page_image = "../robsite.gif";  // configure
  $my_icon_file       = "../images/robsite_icon.ico";  // configure


  include_once "../stat_counter.php";

  print ("<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
    <html lang=\"en\">
    <head>
      <link rel=\"stylesheet\" href=\"../styles_content.css\" type=\"text/css\">
  ");
  print_copyright ($my_name);

  //
  // show_error_page()
  //
  function show_error_page ($error)
  {
      if ($error) {
        // print out debugging info so developer can "view->source" to determine problem
        printf ("
          <meta http-equiv=\"Content-Type\" content=\"text/html; charset=iso-8859-1\">
          <title>Website File Error</title>
          <!-- Debug: error_code=%s -->\n", $error);
        if (isset($_SERVER['HTTP_REFERER'])) {
          printf ("<!-- HTTP_REFERER=%s -->\n", $_SERVER['HTTP_REFERER']);
        }
        if (isset($_SERVER['HTTP_USER_AGENT'])) {
          printf ("<!-- HTTP_USER_AGENT=%s -->\n", $_SERVER['HTTP_USER_AGENT']);
        }
        if (isset($_SERVER['REQUEST_URI'])) {
          printf ("<!-- REQUEST_URI=%s -->\n", $_SERVER['REQUEST_URI']);
        }
        if (isset($_ENV['USERDNSDOMAIN'])) {
          printf ("<!-- USERDNSDOMAIN=%s -->\n", $_ENV['USERDNSDOMAIN']);
        }
        if (isset($_ENV['USERNAME'])) {
          printf ("<!-- USERNAME=%s -->\n", $_ENV['USERNAME']);
        }
        // configure if different error message is desired:
        printf ("</head>
          <body>
            <br />
            There appears to have been an error.<br />
            Please go <a href=\"%s\">back</a> and try again.<br />
            If this error persists, please contact: <a href=\"mailto:%s\">%s</a><br />
            <br />
        ", $GLOBALS['my_main_page'], $GLOBALS['my_webmaster_email'], $GLOBALS['my_webmaster_email']);
	    print_stat_counter();
        printf ("
          </body>
        </html>
        ");
//phpinfo();
	    exit(0);
	  }
  }


  //
  // get_base_filename()
  //
  function get_base_filename ($filename)
  {
    $ext_pos = strrpos($filename,".");
    if ($ext_pos != false) {
      return substr($filename,0,$ext_pos);
    }
    return $filename;
  }


  //
  // get_best_filename()
  //
  function get_best_filename ($filename, $dirname)
  {
    if (file_exists($dirname . '/' . $filename)) {
      return $filename;
    }
    $best_filename_ext_array = array(1 => '.jpg', '.jpeg', '.gif', '.mpg', '.mpeg');
    for ($loop=1; $loop <= count($best_filename_ext_array); $loop++) {
      $filename = get_base_filename($filename) . $best_filename_ext_array[$loop];
      if (file_exists($dirname . '/' . $filename)) {
        return $filename;
      }
    }
    return "";
  }


  //
  // print_copyright()
  //
  function print_copyright ($name)
  {
    printf ("\n<meta name=\"copyright\" content=\"All content Copyright %s\">\n", $name);
  }


  //
  // create_subtitle()
  //
  function create_subtitle ($filename, $filenum)
  {
      $subtitle = "<div class=\"blackFontSize2\">";
      if ($filenum != "") {
        $subtitle .= "#" . $filenum . ". &nbsp; &nbsp; ";
      }
      $subtitle .= $filename . " &nbsp; &nbsp; ";

      list($width, $height) = getimagesize($filename);
      $subtitle .= $width . "x" . $height . " &nbsp; &nbsp; ";

      $filesize = filesize($filename);
      if ($filesize>999999) {
        $subtitle .= round($filesize / 1000000,1) . " Mb";
      } else {
        if ($filesize>999) {
          $subtitle .= round($filesize / 1000) . " Kb";
        } else {
          $subtitle .= $filesize . " bytes";
        }
      }
      $subtitle .= "</div>";
      return $subtitle;
  }


  ////////////////////////////////
  // begin main code
  ////////////////////////////////

    //
    // Check input parameters
    //
    $error = 0;  /* Initialize to "no" errors */
    if (!(isset($_GET["show"]))) {
      $show = "main";
    } else {
      $show = str_replace("\\","",urldecode($_GET["show"]));
	}
    if (!(isset($_GET["dir"]))) {
      $error = 1;
    } else {
      $dir = str_replace("\\","",urldecode($_GET["dir"]));
	}
    if (!(isset($_GET["category"]))) {
      $error = 1;
    } else {
      $category = str_replace("\\","",urldecode($_GET["category"]));
	}
    if (!(isset($_GET["image_date"]))) {
      $image_date = "";
    } else {
      $image_date = str_replace("\\","",urldecode($_GET["image_date"]));
	}
    if (!(isset($_GET["cols"]))) {
      $cols = 4;
    } else {
      $cols = str_replace("\\","",urldecode($_GET["cols"]));
	}
    if (!(isset($_GET["rows"]))) {
      $rows = 10;
    } else {
      $rows = str_replace("\\","",urldecode($_GET["rows"]));
    }
    if (!(isset($_GET["page"]))) {
      $page = 1;
    } else {
      $page = str_replace("\\","",urldecode($_GET["page"]));
    }
    if (!(isset($_GET["file"]))) {
      $file = "";
    } else {
      $file = urldecode($_GET["file"]);
    }
    if (!(isset($_GET["filenum"]))) {
      $filenum = "";
    } else {
      $filenum = urldecode($_GET["filenum"]);
    }

    if (isset($_SERVER['HTTP_REFERER']) && (substr(strtolower($_SERVER['HTTP_REFERER']),-(strlen($my_http_referer))) == $my_http_referer)) {
      // If this script is run with no args, just show the main view
      $error = 0;
      $show = "main";
    }

    show_error_page($error);

    print ("
      <link rel=\"stylesheet\" href=\"../styles_photos.css\" type=\"text/css\">
      <meta http-equiv=\"Content-Type\" content=\"text/html; charset=iso-8859-1\">");



    //
    // show_index
    //
    if ($show == "index") {
      // configure if different content is desired:
      printf ("<meta name=\"keywords\" content=\"%s %s\">\n
        <meta name=\"description\" content=\"%s's personal web site\">
        <link href=\"%s\" rel=\"shortcut icon\">
      ", $my_name, $category, $my_name, $my_icon_file);
      printf ("<title>%s</title>\n", $category);
      print ("<style type=\"text/css\">
              <!--
                .no_margin_class {
                  padding: 0px;
                  margin-top: 0px;
                  margin-bottom: 1px;
                }
              -->
              </style>
      ");
      printf ("</head>
        <body>
          <center>
          <a href=\"%s\"><img border=\"0\" align=\"middle\" src=\"%s\" alt=\"Home\" class=\"no_margin_class\"></a>

          <table border=\"0\" cellpadding=\"5\" cellspacing=\"2\" width=\"90%%\">
           <tr>
             <td align=\"left\"><div class=\"blackFontSize4\">
      ", $my_main_page, $my_main_page_image);
      printf ("%s<br /></div>%s</td>\n", $category, $image_date);
      print ("</tr></table>");

      // Get dir list of files
      $image_dir = $dir . '/images';
      $thumbnail_dir = $dir . '/thumbnails';
      $thumbnail_not_found_img = '../../../images/pictures_thumbnail_not_found.gif';
      $list_file = "";
      $files_array_count = 0;

      if ($dh = opendir($image_dir)) {
        while (($list_file = readdir($dh)) !== false) {
          if ($list_file[0] != ".") {  // ignore all filenames starting with "."
            $files_array[$files_array_count++] = $list_file;
          }
        }
        closedir($dh);
      }
      else  // couldn't open dir
	  {
        show_error_page(2);
      }

    if ($files_array_count > ($rows * $cols)) {  // multiple pages involved
      print ("Page &nbsp;");
      for ($loop=1; $loop <= (($files_array_count / ($rows * $cols)) + 1); $loop++) {
        if ($loop == $page) {  // current page
          printf ("<strong><u>%s</u></strong> &nbsp;", $loop);
        } else {
          $a_href_text = sprintf ("<a href=\"show_pictures.php?show=index&dir=%s&category=%s&image_date=%s&cols=%s&rows=%s&page=%s\">", urlencode($dir), urlencode($category), urlencode($image_date), urlencode($cols), urlencode($rows), urlencode($loop));
          printf ("%s%s</a> ", $a_href_text, $loop);
        }
      }
    }
    print ("<br /><table border=\"0\" cellpadding=\"0\" cellspacing=\"2\">");


    $row = 0;
    $col = 0;
    $list_filenum = $rows * $cols * ($page - 1);

    while ($list_filenum < $files_array_count) {
          if (($row + 1) > $rows) {  // show the correct number of rows per page
			break;
          }

          if (($row == 0) && ($col == 0)) {
            print ("<tr>");
          }
          $col++;

          if (($files_array[$list_filenum] == $file) && ($list_filenum+1 == $filenum)) {
            $a_class = " class=\"a_last_visited\"";
          } else {
            $a_class = "";
          }

          $a_href_text = sprintf ("<a href=\"show_pictures.php?show=picture&dir=%s&category=%s&image_date=%s&cols=%s&rows=%s&page=%s&file=%s&filenum=%s\"%s>", urlencode($dir), urlencode($category), urlencode($image_date), urlencode($cols), urlencode($rows), urlencode($page), urlencode($files_array[$list_filenum]), urlencode($list_filenum+1), $a_class);

		  $thumbnail_file = $thumbnail_dir . '/' . $files_array[$list_filenum];
		  if (!file_exists($thumbnail_file)) {
            $thumbnail_file = $thumbnail_not_found_img;
		  }

          printf ("<td align=\"center\" valign=\"bottom\">%s<img src=\"%s\" border=\"0\" alt=\"#%d:  %s\"></a><br />", $a_href_text, $thumbnail_file, $list_filenum+1, $files_array[$list_filenum]);
          printf ("%s%s</a></td>", $a_href_text, get_base_filename($files_array[$list_filenum]));

          if (($col % $cols) == 0) {
            print ("</tr><tr>");
            $row++;
            $col = 0;
          }
      $list_filenum++;
    }

    print ("</tr>
    </table>
    <br />
    ");
    print_stat_counter();
    print ("</center>
  </body>
  </html>
    ");

      exit(0);
    }



    //
    // show_picture
    //
    if ($show == "picture") {
      // Get dir list of files to determine prev & next
      $prev_file = "";
      $curr_file = "";
      $next_file = "";
      $image_dir = $dir . '/images';
      $bigimage_dir = $dir . '/bigimages';
      $description_dir = $dir . '/descriptions';
      $file = get_best_filename($file, $image_dir);
      if ($file != "") {
        $image_file = $image_dir . '/' . $file;
      } else {
        $image_file = "";
      }


      // configure if different content is desired:
      print ("<link rel=\"stylesheet\" href=\"../styles_photos.css\" type=\"text/css\">
        <meta http-equiv=\"Content-Type\" content=\"text/html; charset=iso-8859-1\">");
      printf ("<meta name=\"keywords\" content=\"%s %s %s\">
        <meta name=\"description\" content=\"%s's personal web site\">
        <link href=\"%s\" rel=\"shortcut icon\">
      ", $my_name, $category, $file, $my_name, $my_icon_file);
      printf ("<title>%s / %s</title>\n", $category, $file);

      print ("
</head>
<body>
  <center>
  <table border=\"0\" cellpadding=\"5\" cellspacing=\"2\" width=\"90%\">
  <tr>
    <td align=\"left\"><div class=\"blackFontSize4\">
      ");
      printf ("%s / %s<br /></div>%s</td>\n", $category, $file, $image_date);
      print ("</tr></table>

        <table align=\"center\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"200\" class=\"nostyle\">
          <tr>
      ");


      if ($dh = opendir($image_dir)) {
        while (($curr_file = readdir($dh)) !== false) {
          if ($curr_file[0] != ".") {  // ignore all filenames starting with "."
            if ($curr_file == $file) {  // found this file
              $next_file = readdir($dh);
              break;
            }
            $prev_file = $curr_file;
          }
        }
        closedir($dh);
      }
      else  // couldn't open dir
      {
        show_error_page(2);
      }


      if ($prev_file != "") {
        printf ("<td width=\"80\" align=\"center\" class=\"nostyle\"><a href=\"show_pictures.php?show=picture&dir=%s&category=%s&image_date=%s&cols=%s&rows=%s&page=%s&file=%s&filenum=%s\"><img src=\"../images/pictures_prev.gif\" height=\"30\" width=\"30\" border=\"0\" alt=\"Previous\" class=\"navimg\"></a></td>\n", urlencode($dir), urlencode($category), urlencode($image_date), urlencode($cols), urlencode($rows), urlencode($page), urlencode($prev_file), urlencode($filenum-1));
      }

      if ($filenum == 1) {
        $up_page = 1;
      } else {
        $up_page = floor((($filenum - 1) / ($rows * $cols)) + 1);  // reset page # since user may have navigated to another page
      }
      printf ("<td width=\"80\" align=\"center\" class=\"nostyle\"><a href=\"show_pictures.php?show=index&dir=%s&category=%s&image_date=%s&cols=%s&rows=%s&page=%s&file=%s&filenum=%s\"><img src=\"../images/pictures_home.gif\" height=\"30\" width=\"30\" border=\"0\" alt=\"Home\" class=\"navimg\"></a></td>\n", urlencode($dir), urlencode($category), urlencode($image_date), urlencode($cols), urlencode($rows), urlencode($up_page), urlencode($file), urlencode($filenum));

      if ($next_file != "") {
        printf ("<td width=\"80\" align=\"center\" class=\"nostyle\"><a href=\"show_pictures.php?show=picture&dir=%s&category=%s&image_date=%s&cols=%s&rows=%s&page=%s&file=%s&filenum=%s\"><img src=\"../images/pictures_next.gif\" height=\"30\" width=\"30\" border=\"0\" alt=\"Next\" class=\"navimg\"></a></td>\n", urlencode($dir), urlencode($category), urlencode($image_date), urlencode($cols), urlencode($rows), urlencode($page), urlencode($next_file), urlencode($filenum+1));
      }

      print ("</tr></table>");


      $bigimage_file = get_best_filename($file, $bigimage_dir);
      if (($bigimage_file != "") && (file_exists($bigimage_dir . '/' . $bigimage_file))) {
        $a_href_text = sprintf ("<a href=\"show_pictures.php?show=bigimage&dir=%s&category=%s&image_date=%s&cols=%s&rows=%s&page=%s&file=%s&filenum=%s\">", urlencode($dir), urlencode($category), urlencode($image_date), urlencode($cols), urlencode($rows), urlencode($page), urlencode($bigimage_file), urlencode($filenum));
        printf ("%s<img src=\"%s\" border=\"0\" alt=\"#%d:  %s\"></a><br />", $a_href_text, $image_file, $filenum, $file);
      } else {
        printf ("<img src=\"%s\" border=\"0\" alt=\"%s\" class=\"nostyle\"><br />", $image_file, $file);
      }

      $description_file = $description_dir . '/' . get_base_filename($file) . '.txt';
      if (file_exists($description_file)) {
        $description = file_get_contents($description_file);
        if ($description != "") {
          printf ("%s<br />", $description);
        }
      }

      printf ("<br />%s", create_subtitle($image_dir . "/" . $file, $filenum));
	  print_stat_counter();
      printf ("</center></body></html>");

      exit(0);
    }



    //
    // show_bigimage
    //
    if ($show == "bigimage") {
      // configure if different content is desired:
      print ("<link rel=\"stylesheet\" href=\"../styles_photos.css\" type=\"text/css\">
        <meta http-equiv=\"Content-Type\" content=\"text/html; charset=iso-8859-1\">");
      printf ("<meta name=\"keywords\" content=\"%s %s %s\">\n
        <meta name=\"description\" content=\"%s's personal web site\">
        <link href=\"%s\" rel=\"shortcut icon\">
      ", $my_name, $category, $file, $my_name, $my_icon_file);
      printf ("<title>%s / %s</title>\n", $category, $file);

      print ("</head>
        <body>
          <center>
          <table border=\"0\" cellpadding=\"5\" cellspacing=\"2\" width=\"90%\">
            <tr>
              <td align=\"left\"><div class=\"blackFontSize4\">
      ");
      printf ("%s / %s<br /></div>%s</td>\n", $category, $file, $image_date);
      print ("</tr></table>

        <table align=\"center\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"200\" class=\"nostyle\">
          <tr>
      ");


      $image_dir = $dir . '/images';
      $bigimage_dir = $dir . '/bigimages';
      $description_dir = $dir . '/descriptions';

      printf ("<td width=\"80\" align=\"center\" class=\"nostyle\"><a href=\"show_pictures.php?show=picture&dir=%s&category=%s&image_date=%s&cols=%s&rows=%s&page=%s&file=%s&filenum=%s\"><img src=\"../images/pictures_home.gif\" height=\"30\" width=\"30\" border=\"0\" alt=\"Up to image\" class=\"navimg\"></a></td>\n", urlencode($dir), urlencode($category), urlencode($image_date), urlencode($cols), urlencode($rows), urlencode($page), urlencode($file), urlencode($filenum));
      print ("</tr></table>");

      $image_file = $image_dir . '/' . $file;
      $bigimage_file = $bigimage_dir . '/' . $file;
      if (substr($file,-4) == ".mpg") {
        printf ("<object data=\"%s\" type=\"video/mpeg\" border=\"0\" alt=\"%s\" class=\"nostyle\">Sorry, unable to embed file.</object>", $bigimage_file, $file);
	  } else {
        printf ("<img src=\"%s\" border=\"0\" alt=\"%s\" class=\"nostyle\">", $bigimage_file, $file);
      }

      printf ("<br /><br />%s", create_subtitle($bigimage_dir . "/" . $file, $filenum));
	  print_stat_counter();
      printf ("</center></body></html>");

      exit(0);
	}



	//
	// Otherwise, always show "main"
	//

    printf ("<meta name=\"keywords\" content=\"%'s pictures\">
        <meta name=\"description\" content=\"%s's personal web site\">
        <link href=\"%s\" rel=\"shortcut icon\">
        <title>Autoloading - %s's Pictures</title>\n
        <script language=\"JavaScript\" type=\"text/javascript\">
        <!--
          function loadUrl() { window.location = \"%s\"; }
        -->
        </script>
      </head>
      <body onLoad=\"loadUrl()\">
        One moment... Loading page for %s's Pictures
    ", $my_name, $my_name, $my_icon_file, $my_name, $my_main_page, $my_name);
	  print_stat_counter();
    printf ("</body>
      </html>
    ");
?>
