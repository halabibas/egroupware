<?php php_track_vars?>
<?php
  /**************************************************************************\
  * phpGroupWare - Calendar                                                  *
  * http://www.phpgroupware.org                                              *
  * Based on Webcalendar by Craig Knudsen <cknudsen@radix.net>               *
  *          http://www.radix.net/~cknudsen                                  *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

  if ($friendly) {
     $phpgw_flags["noheader"] = True;
  }

  $phpgw_flags["currentapp"] = "calendar";
  include("../header.inc.php");
  if (strlen($date) > 0) {
     $thisyear = substr($date, 0, 4);
     $thismonth = substr($date, 4, 2);
     $thisday = substr($date, 6, 2);
  } else {
     if ($day == 0)
        $thisday = date("d");
     else
        $thisday = $day;
     if ($month == 0)
        $thismonth = date("m");
     else
        $thismonth = $month;
     if ($year == 0)
        $thisyear = date("Y");
     else
        $thisyear = $year;
  }

  $next = mktime(2,0,0,$thismonth + 1,1,$thisyear);
  $nextyear = date("Y", $next);
  $nextmonth = date("m", $next);
  $nextdate = date("Ymd");

  $prev = mktime(2,0,0,$thismonth - 1,1,$thisyear);
  $prevyear = date("Y",$prev);
  $prevmonth = date("m",$prev);
  $prevdate = date("Ymd");

  if ($friendly) {
     echo "<body bgcolor=\"".$phpgw_info["theme"][bg_color]."\">";
     $view = "month";
  }
?>

<HEAD>
<STYLE TYPE="text/css">
<?php echo "$CCS_DEFS";?>

  .tablecell {
    width: 80px;
    height: 80px;
  }
</STYLE>
</HEAD>

<TABLE BORDER=0 WIDTH=100%>
<TR>
<?php
  if (! $friendly) {
     echo "<TD ALIGN=\"left\">";
     display_small_month($prevmonth,$prevyear,True);
  }
?>

<TD ALIGN="middle"><FONT SIZE="+2" COLOR="<?php echo $H2COLOR;?>"><B>
<?php
  $m = mktime(2,0,0,$thismonth,1,$thisyear);
  print lang_common(strftime("%B",$m)) . " " . $thisyear;
?>
</B></FONT>
<FONT COLOR="<?php echo $H2COLOR;?>" SIZE="+1">
<br>
<?php
    if ($phpgw->session->firstname)
       echo $phpgw->session->firstname . " ";
    if ($phpgw->session->lastname)
       echo $phpgw->session->lastname;
?>
</FONT></TD>
<?php
  if (! $friendly) {
     echo '<TD ALIGN="right">';
     display_small_month($nextmonth,$nextyear,True);
  }
?>
</TR>
</TABLE>

<TABLE WIDTH=100% BORDER=0 bordercolor=FFFFFF cellspacing=2 cellpadding=2>

<TR>
<TH WIDTH=14% BGCOLOR="<?php echo $phpgw_info["theme"]["th_bg"]; ?>"><FONT COLOR="<?php echo $phpgw_info["theme"]["th_text"]; ?>"><?php echo lang_calendar("Sun"); ?></FONT></TH>
<TH WIDTH=14% BGCOLOR="<?php echo $phpgw_info["theme"]["th_bg"]; ?>"><FONT COLOR="<?php echo $phpgw_info["theme"]["th_text"]; ?>"><?php echo lang_calendar("Mon"); ?></FONT></TH>
<TH WIDTH=14% BGCOLOR="<?php echo $phpgw_info["theme"]["th_bg"]; ?>"><FONT COLOR="<?php echo $phpgw_info["theme"]["th_text"]; ?>"><?php echo lang_calendar("Tue"); ?></FONT></TH>
<TH WIDTH=14% BGCOLOR="<?php echo $phpgw_info["theme"]["th_bg"]; ?>"><FONT COLOR="<?php echo $phpgw_info["theme"]["th_text"]; ?>"><?php echo lang_calendar("Wed"); ?></FONT></TH>
<TH WIDTH=14% BGCOLOR="<?php echo $phpgw_info["theme"]["th_bg"]; ?>"><FONT COLOR="<?php echo $phpgw_info["theme"]["th_text"]; ?>"><?php echo lang_calendar("Thu"); ?></FONT></TH>
<TH WIDTH=14% BGCOLOR="<?php echo $phpgw_info["theme"]["th_bg"]; ?>"><FONT COLOR="<?php echo $phpgw_info["theme"]["th_text"]; ?>"><?php echo lang_calendar("Fri"); ?></FONT></TH>
<TH WIDTH=14% BGCOLOR="<?php echo $phpgw_info["theme"]["th_bg"]; ?>"><FONT COLOR="<?php echo $phpgw_info["theme"]["th_text"]; ?>"><?php echo lang_calendar("Sat"); ?></FONT></TH>
</TR>

<?php
  /* Pre-Load the repeated events for quckier access */
  $repeated_events = read_repeated_events();

  // We add 2 hours on to the time so that the switch to DST doesn't
  // throw us off.  So, all our dates are 2AM for that day.
  // $sun = get_sunday_before($thisyear,$thismonth,1) + 7200;
  $sun = get_sunday_before($thisyear,$thismonth,1) + 7200;
  // generate values for first day and last day of month
  $monthstart = mktime(2,0,0,$thismonth,1,$thisyear);
  $monthend = mktime(2,0,0,$thismonth + 1,0,$thisyear);

  // debugging
  //echo "<P>sun = "	    . date("D, m-d-Y", $sun)	    . "<BR>";
  //echo "<P>monthstart = " . date("D, m-d-Y", $monthstart) . "<BR>";
  //echo "<P>monthend = "   . date("D, m-d-Y", $monthend)   . "<BR>";

  $today = mktime(2,0,0,date("m"),date("d"),date("Y"));

  for ($i = $sun; date("Ymd",$i) <= date("Ymd",$monthend); $i += (24 * 3600 * 7) ) {
    $CELLBG = $phpgw->nextmatchs->alternate_row_color($CELLBG);

    echo "<TR>\n";
    for ($j = 0; $j < 7; $j++) {
      $date = $i + ($j * 24 * 3600);
      if (date("Ymd",$date) >= date("Ymd",$monthstart) &&
         date("Ymd",$date) <= date("Ymd",$monthend) ) {
         echo "<TD VALIGN=\"top\" WIDTH=75 HEIGHT=75 ID=\"tablecell\"";
         if (date("Ymd",$date) == date("Ymd",$today)) {
            echo " BGCOLOR=\"".$phpgw_info["theme"]["cal_today"]."\">";
         } else {
            echo " BGCOLOR=\"$CELLBG\">";
         }
         print_date_entries($date,$friendly,$phpgw->session->id);
         echo "</TD>\n";
      } else {
         echo "<TD></TD>\n";
      }
    }
    print "</TR>\n";
  }

?>

</TABLE>
<P>
<P>

<?php
  if (! $friendly) {
     $param = "";
     if ($thisyear)
        $param .= "year=$thisyear&month=$thismonth&";

     $param .= "friendly=1\" TARGET=\"cal_printer_friendly\" onMouseOver=\"window."
	. "status = '" . lang_calendar("Generate printer-friendly version"). "'";
     echo "<a href=\"".$phpgw->link($PHP_SELF,$param)."\">";
     echo "[". lang_calendar("Printer Friendly") . "]</A>";
     include($phpgw_info["server"]["api_dir"] . "/footer.inc.php");
  }
