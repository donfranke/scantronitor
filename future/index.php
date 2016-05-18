<?php
    # ===========================================================
    # file name:  future/index.php
    # purpose:    main page to display scans that are scheduled
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
    $pageName = "future";
    
    $url = "https://qualysapi.qualys.com/msp/scheduled_scans.php?type=scan&active=yes";
    $error = "";
    $aryData = array();
    
    // set URL and other appropriate options
    $ch = curl_init(); 
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_USERPWD, $username .':'.$password); 
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
    
    // grab XML data
    $xmldata = curl_exec($ch);
    if(trim($xmldata) == "ACCESS DENIED") {
        die(sprintf("ACCESS DENIED.  Please check credentials."));
    }
    $tag_tree = array();
    $stack = array();
    $i=0;
    
    # this class is for each tag found in the XML return
    class tag {
        var $name;
        var $attrs;
        var $children;
    
        # this is when a tag is found
        function tag($name, $attrs, $children) {
            global $currenttag;
            global $error;
            global $i;
            global $aryData;
            if(trim($currenttag)!=""&&trim($name)!="") {
                # do nothing
            }
    
            # handle error such as cannot reach API
            if($currenttag=="ERROR"&&trim($name)!="") {
               $error = trim($name);
            }

            # capture targets
            if($currenttag=="TARGETS"&&trim($name)!="SCHEDULE"&&trim($name)!="") {
               $name = str_replace(",",", ",$name);
               if(strlen($aryData[$i][1])!=0) {
                   $aryData[$i][1] .= trim($name);
               } else {
                   $aryData[$i][1] = trim($name);
               }
            }

            # capture next run date
            if($currenttag=="NEXTLAUNCH_UTC") {
               $name = str_replace("OPTION","",$name);
               if(trim($name)!=""&&trim($name)!="DEFAULT_SCANNER") {
                    $aryData[$i][0] = trim($name);
				
					$finishTime= $aryData[$i][0];   
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
				
					$aryData[$i][0]= $finishTime;
               }
            }

            # capture title
            if($currenttag=="TITLE") {
               if(trim($name)!=""&&trim($name)!="TARGETS") {
                    $aryData[$i][2] = trim($name);
               }
            }

            # capture options
            if($currenttag=="OPTION_PROFILE_TITLE") {
               if(trim($name)!=""&&trim($name)!="SCAN") {
                   $aryData[$i][3] = trim($name);
                   $i++;
               }
            }
        }
    }
    
    # function used to parse XML
    function startTag($parser, $name, $attrs) {
        global $tag_tree, $stack;
        $tag = new tag($name,$attrs,'');
        global $currenttag;
        $currenttag = $name;
    }
    
    # function used to parse XML
    function endTag($parser, $name) {
        # do nothing
    }
    
    # function used to parse XML
    function cdata($parser, $element) 
    {
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
    
    if(!$data) {
        die(sprintf("XML error: %s at line %d",xml_error_string(xml_get_error_code($xml_parser)),xml_get_current_line_number($xml_parser)));
    }
    
    # release the kraken! (parser)
    xml_parser_free($xml_parser);
    
    $dataTotal = $i;
?>
  <html>
	<head>
		<link rel=stylesheet href=../scantronitor.css>
		</link>
		<title>Scantronitor</title>
	</head>
	<body>
		<?include 'header.php'?>
		
<h2 align=center><?=$error?></h2>
<p align=center>
	<table id=datatable width=1200>
		<tr>
			<th width=150><img src="../images/datetime.png" alt="DateTime"></th> 
			<th width=300><img src="../images/type.png" alt="Type"></th>
			<th width=300><img src="../images/profile.png" alt="Profile"></th>
			<th width=450><img src="../images/targets.png" alt="Targets"></th>
		</tr>
		
		<?global $aryData;
		  global $i;
		  for($i=0;$i<$dataTotal;$i++) {?>
			<tr onMouseOver="this.bgColor='#dee7d1'" onMouseOut="this.bgColor='#ffffff'">
				<td><?=$aryData[$i][0] ?></td>
                <td><?=$aryData[$i][2] ?></td>
                <td><?=$aryData[$i][3] ?></td>
				<td>
				<?=$aryData[$i][1] ?>
				</td>
			</tr>
		<?}?>
		<tr>
			<th colspan=4 align=center><?=$dataTotal?> Total Record(s)</th>
		</tr>
	</table>
</p>

<?include '../footer.php'?>
</body>
</html>
