<?
    # ===========================================================
    # file name:  present/index.php
    # purpose:    main page to display currently running scans
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

<? include '../creds.php'?>

<?
    $url = "https://qualysapi.qualys.com/msp/scan_running_list.php?";
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
        var $attrs=array();
        var $children;
    
        # this is when a tag is found
        function tag($name, $attrs, $children) {
            global $currenttag;
            global $currentvalue;
            global $error;
            global $aryData;
            global $i;

            $currenttag = trim($currenttag);
            $currentvalue = trim($currentvalue);
            $name = trim($name);
    
            # capture start date
            if($currenttag=="KEY"&&$currentvalue=="startdate"&&$name!=""&&$name!="KEY") {
                $aryData[$i][0] = $name;
                $finishTime= $aryData[$i][0];   #$i++;
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
                $finishTime = date("Y\-m\-d H:m:s", mktime($hour+5,$minute,$second,$month,$day,$year));
                $aryData[$i][0]= $finishTime;
            }
    
            # capture scan (but not used)
            if($currenttag=="SCAN"&&$currentvalue!=""&&$name=="KEY") {
                $aryData[$i][1] = $currentvalue;
            }
    
            # capture error
            if($currenttag=="ERROR"&&trim($name)!="") {
               $error = trim($name);
            }
    
            # capture targets
            if($currenttag=="KEY"&&$currentvalue=="target"&&$name!=""&&$name!="KEY"&&$name!="]") {
                if(strlen($aryData[$i][1])!=0) {
                   $aryData[$i][2] .= trim($name);
               } else {
                   $aryData[$i][2] = trim($name);
               }
            }
    
            # capture status
            if($currenttag=="KEY"&&$currentvalue=="status"&&$name!=""&&$name!="OPTION_PROFILE"&&$name!="ASSET_GROUPS") {
               $aryData[$i][3] = $name;
            }
    
            # capture profile
            if($currenttag=="OPTION_PROFILE_TITLE"&&$name!=""&&$name!="SCAN") {
                $aryData[$i][4] = $name;
                $i++;
            }
        }
    }

    # function used to parse XML
    function startTag($parser, $name, $attrs) {
        global $tag_tree, $stack;
        $tag = new tag($name,$attrs,'');
        global $currenttag;
        global $currentvalue;
        $currenttag = $name;
        array_push($stack,$tag);
        $element = array();
        $element['name'] = $name;
        foreach ($attrs as $key => $value) { 
            $element[$key]=$value;
            $currentvalue=$value;
        }
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

    # create XML parser and define handlers
    $xml_parser = xml_parser_create();
    xml_set_element_handler($xml_parser, "startTag", "endTag");
    xml_set_character_data_handler($xml_parser, "cdata");
    $data = xml_parse($xml_parser,$xmldata);

    global $dataTotal;
    global $i;
    $dataTotal = $i;
    
    if(!$data) {
        die(sprintf("XML error: %s at line %d",xml_error_string(xml_get_error_code($xml_parser)),xml_get_current_line_number($xml_parser)));
    }
    
    # release the parser!
    xml_parser_free($xml_parser);
?>
  
<html>
<head>
<link rel="stylesheet" href="../scantronitor.css"> 
</link> 
<title>Scantronitor</title> </head> 
<body>
<?include 'header.php'?>
	
<?if($error!="") {?>	
    <h2 align="center">No scan or map currently running</h2>
<?} else {?>
    <p align="center">
    <table id="datatable" width="1200">
	<tr>
	    <th width="150"><img src="../images/datetime.png" alt="DateTime"></th>
	    <th width="300"><img src="../images/profile.png" alt="Profile"></th>
	    <th width="600"><img src="../images/targets.png" alt="Targets"></th>
	    <th width="300"><img src="../images/status.png" alt="Status"></th>
	</tr>

<?global $aryData;
  global $i;
  for($i=0;$i<$dataTotal;$i++) {
      $aryData[$i][2] = str_replace(",",", ",$aryData[$i][2])?>
	<tr onMouseOver="this.bgColor='#dee7d1'" onMouseOut="this.bgColor='#ffffff'">
	    <td align="left"><?=$aryData[$i][0] ?></td>
	    <td align="left"><?=$aryData[$i][4] ?></td>
	    <td align="left"  width="600"><?=$aryData[$i][2] ?></td>
	    <td align="left"><?=$aryData[$i][3] ?></td>	
	</tr> 
	<?}?>
	<tr>
	    <th colspan="4" align="center"><?=$dataTotal?> Total Record(s)</th>
	</tr>
    </table>
    </p>
<?}?>
		
<?include '../footer.php'?>
	
</body>
</html>
