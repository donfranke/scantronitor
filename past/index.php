<?php
    # ===========================================================
    # file name:  past/index.php
    # purpose:    main page to display scans that have run
    # created:    June 2011
    # authors:    Don Franke
    #             Josh Stevens
    #             Peter Babcock
    # ===========================================================
?>
<!----------------------------------------------------------------
  Scantronitor
  Front-end for the Qualys API
  Use this to provide visibility into scanning activity
  Created by Don Franke, Josh Stevens and Pete Babcock, 2011
  
  Licensed under the MIT license: http://www.opensource.org/licenses/mit-license.php  
  ---------------------------------------------------------------->

<?include '../creds.php'?>

<?
    date_default_timezone_set('America/Chicago');
    # variables
    $currenttag = "";
    $aryData = array();
    $aryDetails = array();
    $aryDetails2 = array();
    $aryColor = array("#BFD0A4","#9DD7B9","#A2A0D4","#9FCFD5","#D0D0A4","#D0B7A4","#D0A4A4","#CFA1D3","#72C06C","#6DBBBF","#6B86C1","#BBBD6F","#BB9B71","#B97373","#BE4696","#4CB866","#4CB3B8","#4F6FB5","#BABA4A","#BA8D4A","#BA4A4A");
    $aryTemp = array();
    $aryTemp2 = array();
    $error="false";
    $errormsg="";
    $i=0;
    $iAryTotal=0;
    $aryDays = array(-1,31,28,31,30,31,30,31,31,30,31,30,31);
    $nextmonth = date("m")+1;
    $prevmonth = date("m")-1;
    
    # get parameters
    $origcurrDate = trim(htmlspecialchars($_GET["dt"])) ;
    $currDate = trim(htmlspecialchars($_GET["dt"])) ;
    $scanType = trim(htmlspecialchars($_GET["st"])) ;
    
    # clean up values
    if($scanType=="") {
        $scanType="past";
    }
    $currMonth = substr($currDate,4,2);
    $currYear = substr($currDate,0,4);
    if($currDate=="") {
        $currMonth = date("m");
        $currYear = date("Y");
    }
    $currDate = $currYear . "-" . $currMonth . "-" . "01";
    
    if($currYear=="2016") {
        $aryOffset = array(-1,5,1,2,5,0,3,5,1,4,6,2,4);
    }
    $dtCurrMonth = strtotime(date("Y-m-d", strtotime($currDate)));
    $currMonth =date("m", $dtCurrMonth);  
    $currMonthName = date("F", $dtCurrMonth);  
    
    $dtNextMonth = strtotime(date("Y-m-d", strtotime($currDate)) . " +1 month");
    $nextMonth =date("Ym", $dtNextMonth);  
    $nextMonth2 =date("m", $dtNextMonth);  
    
    $dtPrevMonth = strtotime(date("Y-m-d", strtotime($currDate)) . " -1 month");
    $prevMonth = date("Ym", $dtPrevMonth);  
    
    # ================ parse IP(s) =======================
    $HostList = trim(htmlspecialchars($_GET["hosts"])) ;
    $HostList = str_replace(", ",",",$HostList);
    $HostList = str_replace(" ",",",$HostList);
    #print $HostList . "<br>";
    
    # 1. iterate list of hostnames
    # 2. get IP for each hostname
    # 3. build IP List for qualys API
    $aryHost = array();
    
    # trim off trailing comma
    $HostList = trim($HostList);
    if($HostList==",") {
        $HostList = "";
    }
    $reverse = strrev( $HostList );
    if($reverse[0]==",") {
        $HostList = substr($HostList,0,strlen($HostList)-1);
    }
    
    # if only one...
    if(strpos($HostList,",")==0) {
        # do nothing, it's just a single IP
        $aryHost = array($HostList);
        $IPAddr = gethostbyname($HostList);
        $IPList = $IPAddr;
    }
    
    # if more than one...
    if(strpos($HostList,",")>0) {
        # do nothing, it's just a single IP
        $aryHost = split(",",$HostList);
        # iterate
        for($i=0;$i<count($aryHost);$i++) {
            $IPAddr = gethostbyname(trim($aryHost[$i]));
            $aryIPs[$i] = $IPAddr;
            $IPList .= $IPAddr . ",";
        }
    }
    $IPList = str_replace(", ",",",$IPList);
    
    # build URL for API call
    $url="https://qualysapi.qualys.com/msp/scan_target_history.php?date_from=" . $currYear . "-" . $currMonth . "-01T06:00:00Z&date_to=" . $currYear . "-" . $nextMonth2 . "-01T06:00:00Z&ips=" . $IPList . "&ip_targeted_list=1&detailed_history=1";

    // set URL and other appropriate options
    $ch = curl_init(); 
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_USERPWD, $username .':'.$password); 
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
    
    // grab XML data
    if($IPList!="") {
        $xmldata = curl_exec($ch);
    }
    if(trim($xmldata) == "ACCESS DENIED") {
        die(sprintf("ACCESS DENIED.  Please check credentials."));
    }
    
    $tag_tree = array();
    $stack = array();
    $temp = array();
    
    # this class is for each tag found in the XML return
    class tag {
        var $name;
        var $attrs;
        var $children;
    
        # this is when a tag is found
        function tag($name, $attrs, $children) {
            $this->name = $name;
            $this->attrs = $attrs;
            $this->children = $children;
            $name = trim($name);
            global $currenttag;
            global $aryData;
            global $i;
            global $nbrscans;
            global $currentIP;
            
            if(strlen($name)>0&&$name!="") {

                # capture IP
                if($currenttag=="IP" && $name!="NB_SCANS") {
                    $aryData[$i][0] = $name;
                    $currentIP = $name;
                }

                # capture number of scans
                if($currenttag=="NB_SCANS" && $name!="IP_DETAILED_HISTORY") {
                    $name = str_replace(",",", ",$name);
                }

                # capture date
                if($currenttag=="DATE" && $name!="STATUS") {
                    $aryData[$i][0] = $currentIP;
                    $finishTime = $name;

                    # convert to datetime
                    $temp = split("-",$finishTime);
                    $year = $temp[0];
                    $month = $temp[1];
                    $day = substr($temp[2],0,2);
                    $temp = split(":",$finishTime);
                    $hour = $temp[0];
                    $hour = substr($temp[0],12,strlen($hour)-12);
                    if(strlen($hour)==1) {
                        $hour = "0" . $hour;
                    }
                    $minute = $temp[1];
                    $second = substr($temp[2],0,2);
    
                    $finishTime = date("Y\-m\-d H:m:s", mktime($hour-5,$minute,$second,$month,$day,$year));

                    # subtract 5 hours to make it CST (instead of GMT)
                    $aryData[$i][1] = $finishTime;     
                }
    
                # capture scan type
                if($currenttag=="SCAN_TYPE"&&$name!="SCAN_TITLE") {
                    $aryData[$i][3] = $name;
                }

                # capture scan return code (if there's an error)
                if($currenttag=="RETURN") {
                    if(strpos($name,"This API cannot be run")>-1) {
                        global $error;
                        global $errormsg;
                        $error="true";
                        $errormsg = $name;
                    }
                }

                # capture title
                if($currenttag=="SCAN_TITLE"&&$name!="OPTION_PROFILE_TITLE") {
                    $aryData[$i][4] = $name;   
                    $aryData[$i][4] = str_replace("\"","",$aryData[$i][4]);        
                }

                # capture status
                if($currenttag=="STATUS"&&$name!="REF") {
                    $aryData[$i][2] = $name;           
                }

                # capture profile
                if($currenttag=="OPTION_PROFILE_TITLE"&&$name!="SCAN"&&$name!="IP_TARGETED") {
                    $aryData[$i][5] = $name;  
                    $i++;
                }
            }
        }
    }
    
    # function used to parse XML
    function startTag($parser, $name, $attrs) {
        #print "Hello<br>";
        global $tag_tree, $stack;
        $tag = new tag($name,$attrs,'');
        global $currenttag;
        $currenttag = $name;
    }
    
    # function used to parse XML
    function endTag($parser, $name) {
        global $stack;
        $stack[count($stack)-2]->children[] = $stack[count($stack)-1];
    }
    
    # function used to parse XML
    function cdata($parser, $element) {
        global $tag_tree, $stack;
        $tag = new tag($element,$attrs,'');
    }
    
    #create xml parser
    $xml_parser = xml_parser_create();
    xml_set_element_handler($xml_parser, "startTag", "endTag");
    xml_set_character_data_handler($xml_parser, "cdata");
    
    $data = xml_parse($xml_parser,$xmldata);
    global $iAryTotal;
    global $i;
    $iAryTotal = $i+1;
    $dataTotal = $i;
    
    if(!$data) {
        die(sprintf("XML error: %s at line %d",xml_error_string(xml_get_error_code($xml_parser)),xml_get_current_line_number($xml_parser)));
    }
    
    xml_parser_free($xml_parser);
    
    # this function is used to determine whether to display font as black or white
    # depending on the brightness of the textbox containing the text
    function getBrightness($hex) {
        // returns brightness value from 0 to 255
        $hex = str_replace('#', '', $hex);
    
        $c_r = hexdec(substr($hex, 0, 2));
        $c_g = hexdec(substr($hex, 2, 2));
        $c_b = hexdec(substr($hex, 4, 2));
    
        return (($c_r * 299) + ($c_g * 587) + ($c_b * 114)) / 1000;
    }

?> 

<html>
	<head>
		<title>Scantronitor</title>
	<link rel="stylesheet" href="../scantronitor.css"/>
    <link rel="stylesheet" type="text/css" href="../tooltip/style.css" />
    <script language="JavaScript" src="../tooltip/script.js"></script>
    <script language="javascript">
        // this function is called when the Search button is clicked
        function submitForm() {
            var IPList = document.frmMain2.hosts.value;
            var aryIP=IPList.split(",");
            
            if(aryIP.length>20) {
                alert("You have entered " + aryIP.length + " Hosts.  Please reduce to 20 or less.");
            } else {
                document.frmMain2.submit();
            }
        }
        </script>

	</head>
<body>
	<? include 'header.php' ?>
        
<?if($error=="true") {?>
     <p class="error">API Failure</p>
     <p class="errormsg"><?=$errormsg?></p>
 <?} else {?>
<p align="center">
<table border=0><tr>
<td valign=top width=200 align=center class="data">
<form name="frmMain2" action="index.php">
    <br><br>
    <img src="../images/servers.png" alt="Server[s]"><br>
    <textarea name="hosts" rows=15 cols=22><?=$HostList?></textarea>
    <p align=left>Enter only a single host name or IP address, or a list of host names and IP addresses, separated by comma (20 entries maximum.)</p>
    	<p align=left>Hover over a server block to view the details.</p>
        <br><input type="button" value="Search" class="button1" onClick="javascript:submitForm()">
</form>
</td>
 <td width=50> &nbsp; </td>
 <td  valign=top>
 <p align=center>

    <table border=0><tr><td width=90>
    <?if($currMonth==$prevmonth) {?>
        <img src=../images/arrow_left_gray.png alt="End Of Line" border=0>
    <?} else {?>
        <a href="index.php?dt=<?=$prevMonth?>&hosts=<?=$HostList?>"><img src=../images/arrow_left.png alt="Previous Month" border=0></a>
    <?}?>
    </td>

    <td width=600 align=center class="showdate">
        <?=($currMonthName)?>    
        <?=strtolower($currYear)?>
    </td>
    <td width=90 align=right>
    <?if($currMonth==$nextmonth) {?>
        <img src=../images/arrow_right_gray.png alt="End Of Line" border=0>
    <?} else {?>
        <a href="index.php?dt=<?=$nextMonth?>&hosts=<?=$HostList?>"><img src=../images/arrow_right.png alt="Next Month" border=0></a>
    <?}?>

    </td>
    </tr>
    </table>

    <table id="datatable" width=800>
		<tr>
			<th><img src="../images/sunday.png" alt="Sunday"></th>
			<th><img src="../images/monday.png" alt="Monday"></th>
			<th><img src="../images/tuesday.png" alt="Tuesday"></th>
			<th><img src="../images/wednesday.png" alt="Wednesday"></th>
            <th><img src="../images/thursday.png" alt="Thursday"></th>
			<th><img src="../images/friday.png" alt="Friday"></th>
			<th><img src="../images/saturday.png" alt="Saturday"></th>
        </tr> 
    
<?# go thru the calendar, 6 weeks down, 7 days across
  for($i=0;$i<6;$i++) {?>
	<tr> 
		<?for($j=0;$j<7;$j++) { 
            $currNum = (7*$i)+$j+1-$aryOffset[(int)$currMonth];
             
             # highlight today's date
             if((int)date("d")==$currNum&&(int)date("m")==(int)$currMonth) {
                 $color="#ffffcc";
             } else {
                 $color="#ffffff";
             }

             # do not gray out boxes not within calendar
             if($currNum<=$aryDays[(int)$currMonth]&&$currNum>0) {
                
                print "<td class='box100' bgcolor='$color'>" . (int)$currNum . "<br>";
                # write out any events that are appropriate for cell (calendar date)
                # iterate thru array and show what matches
                for($k=0;$k<$dataTotal;$k++) {
                    $dataDay = substr($aryData[$k][1],8,2);
                    $dataMonth = substr($aryData[$k][1],5,2);
                    if((int)$dataDay==(int)$currNum&&(int)$currMonth==(int)$dataMonth) {
                        $Hostname = gethostbyaddr($aryData[$k][0]);

                        if(strpos($Hostname,".")>-1) {
                            $Hostname = substr($Hostname,0,strpos($Hostname,"."));
                        }
                        # find matching color
                        for($p=0;$p<count($aryHost);$p++) {
                            $HostFromArray = trim($aryHost[$p]);
                            if(is_numeric(substr($HostFromArray,0,1))) {
                                $HostFromArray = gethostbyaddr($HostFromArray);
                                # grab everything left of first dot
                                $HostFromArray = substr($HostFromArray,0,strpos($HostFromArray,"."));
                            }
                            # if it's numeric, then it's an IP
                            if($Hostname==$HostFromArray) {
                                $cellColor = $aryColor[$p];
                                if(getBrightness($cellColor)<100) {
                                    $fontColor="#ffffff";
                                } else {
                                    $fontColor="#000000";
                                }
                            }
                        }

                        $detailString = "IP: " . $aryData[$k][0] . "<br>Started: ". $aryData[$k][1] . " CST<br>Type: " . $aryData[$k][3] . "<br>Title: " . $aryData[$k][4] . "<br>Profile: " . $aryData[$k][5];
                        print "<table><tr><td onmouseover=\"tooltip.show('" . $detailString . "')\" class=element bgcolor=" . $cellColor . " onmouseout=\"tooltip.hide();\"><font color='" . $fontColor . "'> " . $Hostname . "</font></td></tr></table>";
                    }
                }

             } else {
                # gray box
                print "<td  class='box100' bgcolor=#cccccc>&nbsp;";
             }

             # close out calendar cell
             print "</td>";
            }?>
		</tr>
<?}?>

</table>

</td>
<td width=50> &nbsp; </td>
<td valign=top width=200>
    <br>
    <br>
    <table border=0 cellspacing=0 cellpadding=5>
        <tr>
            <td>
                <img src=../images/legend.png alt="Legend">
            </td>
        </tr>
        <tr>
            <td height=600 width=200 class="sidedata" valign=top>
                <font color="#666666">
                <table cellspacing=3 cellpadding=3>
                <?if($aryHost[0]!="") {
                    for($i=0;$i<count($aryHost);$i++) {
                        print "<tr><td bgcolor='$aryColor[$i]' width=20 height=20>&nbsp;</td><td style='font-size:11px'>$aryHost[$i]<tt></td></tr>";
                    }
                  }
                ?>
             </font>
            </td>
        </tr>
    </table>
            
    </td>
    </tr>
    </table>
    </p>

</td> 
</tr> 
</table>
<?}?>

<?include '../footer.php'?>

</body>
</html>

