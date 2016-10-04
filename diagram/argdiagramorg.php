<?php

//origami arg

require_once('../connections/queriesMysql2.php');
require_once('../connections/queriesInflux2.php');

##################################################
# Influx clean version
# 'amplus_all_calculations' instead of 'amplus_calculation'
##################################################
# function combineIrradWithInverterEff()
# ------------------------------------------------
# replaces all loops containing queries to _devicedatavalue
# ------------------------------------------------
print ("<p><sub>argdiagramorg influx clean version  'amplus_all_calculations' </sub></p>");
#################################################


if (!isset($delta)) {
  $delta = 0;
}

set_time_limit(900);
require_once ('../connections/verbindung.php');
mysql_select_db($database_verbindung, $verbindung);

$anyArgs = false;
$now = mktime();
date_default_timezone_set('UTC');

if (!isset($stamp)) {
  $endstamp = mktime(18, 30, 0);
  $stamp = $endstamp - 24 * 3600;
}

if (!isset($resolution)) {
  $resolution = 2;
}
$startTime = $stamp;
$endTime = $endstamp;

if (is_null($args)) {
    return;
}

$argString = "";
if ($echoArgs == 1) {
  $argString = '<tr><td><iframe id="frame1" width="99%" height="99%" SRC="diagram/argdiagram5.php?args=' . $args . '&defaults=' . $defaults . '&hideClear=1&hideDelta=1&stamp=' . $stamp . '&endstamp=' . $endstamp . '" border="0"></iframe></td></tr><br />';
  $count++;
}

$defaultNames = array();
if (is_null($defaults) || strlen($defaults) == 0) {
  $defaults = false;
}
else {
  $defaults = split(",", $defaults);
}

$hideClear = $hideClear;
if (is_null($hideClear)) {
  $hideClear = 0;
}
$hideDelta = $hideDelta;
if (is_null($hideDelta)) {
  $hideDelta = 0;
}

function getdeviceData($startTime, $endTime, $deviceId, $field)
{
    $query = "SELECT ts,value 
    FROM amplus_all_calculations 
    WHERE ts > $startTime and ts < $endTime 
    AND park_no=57
    AND (device='" . $deviceId . "') and (field='" . $field . "')";
  $sql = mysql_query($query);
  if (mysql_num_rows($sql) > 0) {
    $tsdata = array();
    $valuedata = array();
    $result = array();
    while ($rlt = mysql_fetch_array($sql)) {
      $tsdata[] = $rlt['ts'];
      $valuedata[] = $rlt['value'];
    }
  }
  return array_combine($tsdata, $valuedata);
}


# round down $number to the nearest number dividable by $divisor
function alignInt($number,$divisor){
	return $number - fmod($number,$divisor);
}


#########################################################
# function alignTimestamps($inver1Eff)
#########################################################
# aligns timestamps to fix intervals of (default) 5 minutes
#        notice: 
#        - given timestamps are aligned _down_ to fix intervals 
#                this should be fine for 99% of all cases
#        -  in case several values are to be resembled
#           into the same interval,
#           the last value is taken.
#  
# param1: required
#         [arr] a series of [ts]=>[val] 
#                 example:
#                    Array
#                    (
#                        [1468024210] => 85
#                        [1468024509] => 91.67
#                        [1468068905] => 66.67
#                        [1468069205] => 60
#                    )
#
# param2: optional 
#         [int]  interval in seconds. 
#
# returns: [arr] aligned series of [ts]=>[val]
#                 example:
#                    Array
#                    (
#                        [1468024200] => 85
#                        [1468024500] => 91.67
#                        [1468068900] => 66.67
#                        [1468069200] => 60
#                    )
#######################################################
function alignTimestamps($inverEff, $interval=300){
  $alignedSeries = array();
  foreach($inverEff as $ts => $val){
    $alignedSeries[alignInt($ts,$interval)] = $val; 
  }
  return $alignedSeries;
}


###################################################################
# Influx Example query, does Averaging!
######################################################
#  SELECT MEAN(value) as groupval 
#  FROM v
#  WHERE 
#	   time>=1452396600s AND time<1452397600s 
#  AND (
#       iid='4032' AND d='SMU02' and f='Solar_Radiation'
#    OR iid='4032' AND d='SMU10' and f='Solar_Radiation'
#     ) 
#  GROUP BY time(300s)
###################################################################
# Influx Example result $series
#####################################################
#  Array ( 
#    [0] => Array ( 
#        [name] => v 
#        [tags] => Array ( 
#            [d] => SMU02 
#            [f] => Solar_Radiation 
#            [iid] => 4032 ) 
#        [columns] => Array ( 
#            [0] => time 
#            [1] => groupval ) 
#        [values] => Array ( 
#            [0] => Array ( 
#                [0] => 1452396600 
#                [1] => 295.89 ) 
#            [1] => Array ( 
#                [0] => 1452396900 
#                [1] => 289.43 ) 
#            [2] => Array ( 
#                [0] => 1452397200 
#                [1] => 299.64 ) 
#            [3] => Array ( 
#                [0] => 1452397500 
#                [1] => 311.31 ) 
#            ) 
#        ) 
##################################################

###################################################################
# function combineIrradWithEff
#     add irradiation values to inverter efficiency values
####################################################################
# param1: required  inverterEff
#                   [array] (ts => eff,  ...  )
#                    
# param2: required  device Id 
#                   [int,str]     
#                   [array] (deviceId1,  ...  )  if array,
#                           then the AVERAGE of devices is calculated
#
# param3: required  field name  
#                   [str]  
#
# param4: optional  reporting interval of values in seconds
#                   [int]
#                   default: 300 [seconds] = 5 min
#
# returns:          InverterEff
#                   [array] (irrad => eff,  ...  )
###################################################################


function combineIrradWithInverterEff($inverterEff, $deviceids, $fieldname,$interval=300){
  if (!is_array($deviceids)){
    $deviceids = array($deviceids);
  }
  $influxTagSets = array();
  $iid   = array();
  $d     = array();
  foreach ($deviceids as $deviceid){
    $influxTagSet    = resolve_deviceIds($deviceid);
    $influxTagSets[] = $influxTagSet;
    $iid[] = $influxTagSet[$deviceid]['iid'];
    $d[]   = $influxTagSet[$deviceid]['d'];
  }
  $timestamps = array_keys($inverterEff);  
  $min_ts = alignInt(min($timestamps),$interval);
  $max_ts = alignInt(max($timestamps),$interval) + $interval;
  $timestamps = "time >= ".$min_ts."s AND time < ".$max_ts."s";
  
  $i = 0;
  $whereDevices = array();
  foreach ($deviceids as $deviceid) {
    $whereDevices[] = "iid='".$iid[$i]."' AND d='".$d[$i]."' AND f='".$fieldname."'";
    $i++;
  }
  $whereDevices = "(" . implode(" OR ",$whereDevices) . ")";
  
  $query      = "SELECT MEAN(value) as groupval 
    FROM v 
    WHERE ".$timestamps."
    AND ". $whereDevices . "
    GROUP BY time(300s);";
  //print("<br>Influx query: $query<br>"); ////////////////////////////////////// DEBUG
  $series = influx_query_execute($query, $influxDBName='amplus');
  foreach($series[0]['values'] as $tsval) {
    if (isset($tsval[1]) and $tsval[1] >= 0){
      $result1[] = '[' . $tsval[1] . ',' . $inverterEff[ $tsval[0] ] . ']';
    }
  }
  $inverEff1 = str_replace('"', "", json_encode($result1));
  return $inverEff1;
}



if(($phase == 'ORGINV1ACPR' && $park_no == 57) || ($phase == 'ORGINV2ACPR' && $park_no == 57) || ($phase == 'ORGINV3ACPR' && $park_no == 57)
	|| ($phase == 'ORGINV4ACPR' && $park_no == 57) || ($phase == 'ORGINV5ACPR' && $park_no == 57) || ($phase == 'ORGINV6ACPR' && $park_no == 57)	)
	{ 
		$Module_temp_600=getdeviceData($startTime,$endTime,12028,'AC_Module_Temp_600');

		foreach($Module_temp_600 as $moduleindex1=>$modulevalue){
		$ts= $moduleindex1;
		$mod_value = $modulevalue;
		$qry_inverter1 = mysql_query("select  ROUND((value),3) as groupval,ts FROM amplus_all_calculations where ts=$ts and (device='12028') and (field='Inv_AC_PR_600')");
		$numCount1 =  mysql_num_rows($qry_inverter1); 
		if($numCount1!=0)
		{
			$fetchdata1=mysql_fetch_array($qry_inverter1);
			$inv1ACpr = $fetchdata1['groupval'];
			$result1[] = '['.$mod_value.','.$inv1ACpr.']';
		}
		
		$qry_inverter2 = mysql_query("select  ROUND((value),3) as groupval,ts FROM amplus_all_calculations where ts=$ts and (device='12028') and (field='Inv_AC_PR_600')");
		$numCount2 =  mysql_num_rows($qry_inverter2); 
		if($numCount2!=0)
		{
			$fetchdata2=mysql_fetch_array($qry_inverter2);
			$inv2ACpr = $fetchdata2['groupval'];
			$result2[] = '['.$mod_value.','.$inv2ACpr.']';
		}	
		$qry_inverter3 = mysql_query("select  ROUND((value),3) as groupval,ts FROM amplus_all_calculations where ts=$ts and (device='12028') and (field='Inv_AC_PR_600')");
		$numCount3 =  mysql_num_rows($qry_inverter3); 
		if($numCount3!=0)
		{
			$fetchdata3=mysql_fetch_array($qry_inverter3);
			$inv3ACpr = $fetchdata3['groupval'];
			$result3[] = '['.$mod_value.','.$inv3ACpr.']';
		}

		$qry_inverter4 = mysql_query("select  ROUND((value),3) as groupval,ts FROM amplus_all_calculations where ts=$ts and (device='12028') and (field='Inv_AC_PR_600')");
		$numCount4 =  mysql_num_rows($qry_inverter4); 
		if($numCount4!=0)
		{
			$fetchdata4=mysql_fetch_array($qry_inverter4);
			$inv4ACpr = $fetchdata4['groupval'];
			$result4[] = '['.$mod_value.','.$inv4ACpr.']';
		}
		
		$qry_inverter5 = mysql_query("select  ROUND((value),3) as groupval,ts FROM amplus_all_calculations where ts=$ts and (device='12028') and (field='Inv_AC_PR_600')");
		$numCount5 =  mysql_num_rows($qry_inverter5); 
		if($numCount5!=0)
		{
			$fetchdata5=mysql_fetch_array($qry_inverter5);
			$inv5ACpr = $fetchdata5['groupval'];
			$result5[] = '['.$mod_value.','.$inv5ACpr.']';
		}
		$qry_inverter6 = mysql_query("select  ROUND((value),3) as groupval,ts FROM amplus_all_calculations where ts=$ts and (device='12028') and (field='Inv_AC_PR_600')");
		$numCount6 =  mysql_num_rows($qry_inverter6); 
		if($numCount6!=0)
		{
			$fetchdata6=mysql_fetch_array($qry_inverter6);
			$inv6ACpr = $fetchdata6['groupval'];
			$result6[] = '['.$mod_value.','.$inv6ACpr.']';
		}
		}	
			
		$invACPR1=str_replace('"',"",json_encode($result1));
		$invACPR2=str_replace('"',"",json_encode($result2));
		$invACPR3=str_replace('"',"",json_encode($result3));
		$invACPR4=str_replace('"',"",json_encode($result4));
		$invACPR5=str_replace('"',"",json_encode($result5));
		$invACPR6=str_replace('"',"",json_encode($result6));
		
	}
	if(($phase == 'ORGINV1DCPR' && $park_no == 57) || ($phase == 'ORGINV2DCPR' && $park_no == 57) || ($phase == 'ORGINV3DCPR' && $park_no == 57)
|| ($phase == 'ORGINV4DCPR' && $park_no == 57)	|| ($phase == 'ORGINV5DCPR' && $park_no == 57) || ($phase == 'ORGINV6DCPR' && $park_no == 57))
	{
		
		$Module_temp_600=getdeviceData($startTime,$endTime,12028,'AC_Module_Temp_600');

		foreach($Module_temp_600 as $moduleindex1=>$modulevalue){
		$ts= $moduleindex1;
		$mod_value = $modulevalue;
		$qry_inverterdc1 = mysql_query("select  ROUND((value),3) as groupval,ts FROM amplus_all_calculations where ts=$ts and (device='12028') and (field='Inv_DC_Vol_Coeff')");
		$dcnumCount1 =  mysql_num_rows($qry_inverterdc1); 
		if($dcnumCount1!=0)
		{
			$dcfetchdata1=mysql_fetch_array($qry_inverterdc1);
			$inv1DCpr = $dcfetchdata1['groupval'];
			$dcresult1[] = '['.$mod_value.','.$inv1DCpr.']';
		}
		$qry_inverterdc2 = mysql_query("select  ROUND((value),3) as groupval,ts FROM amplus_all_calculations where ts=$ts and (device='12028') and (field='Inv_DC_Vol_Coeff')");
		$dcnumCount2 =  mysql_num_rows($qry_inverterdc2); 
		if($dcnumCount2!=0)
		{
			$dcfetchdata2=mysql_fetch_array($qry_inverterdc2);
			$inv2DCpr = $dcfetchdata2['groupval'];
			$dcresult2[] = '['.$mod_value.','.$inv2DCpr.']';
		}	
		$qry_inverterdc3 = mysql_query("select  ROUND((value),3) as groupval,ts FROM amplus_all_calculations where ts=$ts and (device='12028') and (field='Inv_DC_Vol_Coeff')");
		$dcnumCount3 =  mysql_num_rows($qry_inverterdc3); 
		if($dcnumCount3!=0)
		{
			$dcfetchdata3=mysql_fetch_array($qry_inverterdc3);
			$inv3DCpr = $dcfetchdata3['groupval'];
			$dcresult3[] = '['.$mod_value.','.$inv3DCpr.']';
		}	
		$qry_inverterdc4 = mysql_query("select  ROUND((value),3) as groupval,ts FROM amplus_all_calculations where ts=$ts and (device='12028') and (field='Inv_DC_Vol_Coeff')");
		$dcnumCount4 =  mysql_num_rows($qry_inverterdc4); 
		if($dcnumCount4!=0)
		{
			$dcfetchdata4=mysql_fetch_array($qry_inverterdc4);
			$inv4DCpr = $dcfetchdata4['groupval'];
			$dcresult4[] = '['.$mod_value.','.$inv4DCpr.']';
		}
		$qry_inverterdc5 = mysql_query("select  ROUND((value),3) as groupval,ts FROM amplus_all_calculations where ts=$ts and (device='12028') and (field='Inv_DC_Vol_Coeff')");
		$dcnumCount5 =  mysql_num_rows($qry_inverterdc5); 
		if($dcnumCount5!=0)
		{
			$dcfetchdata5=mysql_fetch_array($qry_inverterdc5);
			$inv5DCpr = $dcfetchdata5['groupval'];
			$dcresult5[] = '['.$mod_value.','.$inv5DCpr.']';
		}
		
		$qry_inverterdc6 = mysql_query("select  ROUND((value),3) as groupval,ts FROM amplus_all_calculations where ts=$ts and (device='12028') and (field='Inv_DC_Vol_Coeff')");
		$dcnumCount6 =  mysql_num_rows($qry_inverterdc6); 
		if($dcnumCount6!=0)
		{
			$dcfetchdata6=mysql_fetch_array($qry_inverterdc6);
			$inv6DCpr = $dcfetchdata6['groupval'];
			$dcresult6[] = '['.$mod_value.','.$inv6DCpr.']';
		}
		
		}	
		$inverDCPR1=str_replace('"',"",json_encode($dcresult1));
		$inverDCPR2=str_replace('"',"",json_encode($dcresult2));
		$inverDCPR3=str_replace('"',"",json_encode($dcresult3));
		$inverDCPR4=str_replace('"',"",json_encode($dcresult4));
		$inverDCPR5=str_replace('"',"",json_encode($dcresult5));
		$inverDCPR6=str_replace('"',"",json_encode($dcresult6));
	}
	if(($phase == 'ORGINV1EFF' && $park_no == 57) || ($phase == 'ORGINV2EFF' && $park_no == 57) || ($phase == 'ORGINV3EFF' && $park_no == 57) ||
 ($phase == 'ORGINV4EFF' && $park_no == 57) || ($phase == 'ORGINV5EFF' && $park_no == 57) || ($phase == 'ORGINV6EFF' && $park_no == 57))
	{
		$inver1Eff =getdeviceData($startTime,$endTime,12028,'Inv_Eff');
		$inver2Eff =getdeviceData($startTime,$endTime,12024,'Inv_Eff');
		$inver3Eff =getdeviceData($startTime,$endTime,12021,'Inv_Eff');
		$inver4Eff =getdeviceData($startTime,$endTime,12023,'Inv_Eff');
		$inver5Eff =getdeviceData($startTime,$endTime,12027,'Inv_Eff');
		$inver6Eff =getdeviceData($startTime,$endTime,12026,'Inv_Eff');
		
		
		//print_r($inver1Eff);
		
		if ($inver1Eff) {
    $inver1Eff = alignTimestamps($inver1Eff);
    $inverEff1 = combineIrradWithInverterEff($inver1Eff,12029,'Solar_Radiation');
  }
  if ($inver2Eff) {
    $inver2Eff = alignTimestamps($inver2Eff);
    $inverEff2 = combineIrradWithInverterEff($inver2Eff,12029,'Solar_Radiation');

  }
  if ($inver3Eff) {
    $inver3Eff = alignTimestamps($inver3Eff);
    $inverEff3 = combineIrradWithInverterEff($inver3Eff,12029,'Solar_Radiation'); 
  }
  if ($inver4Eff) {
    $inver4Eff = alignTimestamps($inver4Eff);
    $inverEff4 = combineIrradWithInverterEff($inver4Eff,12029,'Solar_Radiation');

  }
  if ($inver5Eff) {
    $inver5Eff = alignTimestamps($inver5Eff);
    $inverEff5 = combineIrradWithInverterEff($inver5Eff,12029,'Solar_Radiation'); 
  }
  if ($inver6Eff) {
    $inver6Eff = alignTimestamps($inver6Eff);
    $inverEff6 = combineIrradWithInverterEff($inver6Eff,12029,'Solar_Radiation');

  }
			
	}
	
?>

<html>
	<head>
		<meta http-equiv="Content-Type" content="text/html" charset="UTF-8" />
		<link rel="stylesheet" href="style.css" type="text/css" />
		<!--[if lte IE 8]><script language="javascript" type="text/javascript" src="js/excanvas.min.js"></script><![endif]-->
		<script language="javascript" type="text/javascript" src="../js/jquery.js"></script>
		<script language="javascript" type="text/javascript" src="../js/jquery.flot.js"></script>
		<script type="text/javascript" src="jscolor/jscolor.js"></script>
		<script type="text/javascript" src="../functions/flot/jquery.flot.selection.min.js"></script>
		<script language="javascript" type="text/javascript" src="../js/jquery.flot.symbol.js"></script>
		<script language="javascript" type="text/javascript" src="../js/jquery.flot.axislabels.js"></script>
		<!-- The styles -->
		<link href="../css/examples.css" rel="stylesheet" type="text/css">
	</head>
    <body>

        <div style="height: 98%; width: 100%">
            <div style="float: left; height: 99%; width: 100%; padding-top: 2px">
                <form name=ThisForm method="post" action="" target="_self">

                    <div>
                       <div style="font-family:Verdana, Geneva, sans-serif; font-size:13px; color:darkblue; font-weight:bold; padding-bottom:2px">
                            <?php
                            echo $title;
                            ?>
                        </div>
                    </div>
                    <div>
                        <!--<div id="placeholder" style="font-size: 95%; width: 99%; height: 98%"></div>-->
						
						<div class="demo-container">
			<div id="placeholder" class="demo-placeholder" style="text-align:center;  margin:0 auto; width:921px;"></div>
			
			<span id="hoverdata"></span>
			
             </div>
                    </div>
                </form>

<!-- Excel sheet link start -->
				<div style="position:absolute; right:10px; top:10px;" id="buttons">
                         <?php 
                         foreach ($displayItems as $myitem) {
                             foreach ($myitem as $myelement) {
                                 echo '<p style = "color:' . $myelement[color] . ';font-family:' . $myelement[font] . ';font-size:' . $myelement[size] . 'px">' . $myelement[text] . '<br>' . number_format($myelement[value], $myelement[decimals], '.', '') . $myelement[unit] . '</p>';
                             }
                         }
                         ?>
                   <!-- <input title="Reset the zoom"    style="flow: left;" id="resetZoom" onClick="resetZoom()" type="image"    src="../imgs/lupe_grey.png" disabled>-->

                    <?php 

                    $phpArg = "?stamp=" . $stamp . "&endstamp=" . $endstamp;
                    $deltaNew = 0;
                    if ($delta == 0) {
                        $deltaNew = "&delta=1";
                    } else {
                        $deltaNew = "&delta=0";
                    }
                    $endString = "&yearO=$yearO&monO=$monO&dayO=$dayO";
                    $startString = "&yearI=$yearI&monI=$monI&dayI=$dayI";

                    $phaseWord = "&showQueries=$showQueries&showAll=$showAll" . $endString . $startString;
					
					
					///export access permission
					$user_name = $_SESSION['user'];
					$query_ds = "select export FROM users WHERE user = '$user_name' and admin_id= 8";
					$ds = mysql_query($query_ds, $verbindung) or die(mysql_error());
					 $row_ds = mysql_fetch_assoc($ds);
					 $row_ds[0];
					
					
					$query_ds = "select export FROM users WHERE user = '$user_name' and export=1";
					$ds = mysql_query($query_ds, $verbindung) or die(mysql_error());
					$row_export = mysql_num_rows($ds);
					
					
					if($row_export==1){
                    if ($exception != 25) {
                        if ($anyArgs) {
                            if ($hideClear == 0) {
                                echo '<a href="../' . $sourcefile . $phpArg . $phaseWord . '&igate=' . $igate . '" target="_parent">';
                                echo '<img title="Reset Diagram" src="clear.png">';
                                echo '</a>';
                            }
							             
                        } else {
							
							if($phase == "ORGINV1EFF" && $park_no=="57"){
								echo '<a href="amplusExport.php?stamp='.$stamp.'&endstamp='.$endstamp.'&phase=' . $phase .'&args=0,1,0,12028,Inv1_Eff,Inverter1,15,%,8;0,1,0,12029,Solar_Radiation_Total,Irradiation,5,W/m&sup2;,\'Gold\';">';
								echo '    <img title="Export Meter data as .csv file" src="../imgs/xls.png">';
								echo '</a>';
							}
							
							if($phase == "ORGINV2EFF" && $park_no=="57"){
								echo '<a href="amplusExport.php?stamp='.$stamp.'&endstamp='.$endstamp.'&phase=' . $phase .'&args=0,1,0,12024,Inv2_Eff,Inverter2,15,%,8;0,1,0,12029,Solar_Radiation_Total,Irradiation,5,W/m&sup2;,\'Gold\';">';
								echo '    <img title="Export Meter data as .csv file" src="../imgs/xls.png">';
								echo '</a>';
							}
							if($phase == "ORGINV3EFF" && $park_no=="57"){
								echo '<a href="amplusExport.php?stamp='.$stamp.'&endstamp='.$endstamp.'&phase=' . $phase .'&args=0,1,0,12021,Inv3_Eff,Inverter3,15,%,8;0,1,0,12029,Solar_Radiation_Total,Irradiation,5,W/m&sup2;,\'Gold\';">';
								echo '    <img title="Export Meter data as .csv file" src="../imgs/xls.png">';
								echo '</a>';
							}
							if($phase == "ORGINV4EFF" && $park_no=="57"){
								echo '<a href="amplusExport.php?stamp='.$stamp.'&endstamp='.$endstamp.'&phase=' . $phase .'&args=0,1,0,12023,Inv4_Eff,Inverter4,15,%,8;0,1,0,12029,Solar_Radiation_Total,Irradiation,5,W/m&sup2;,\'Gold\';">';
								echo '    <img title="Export Meter data as .csv file" src="../imgs/xls.png">';
								echo '</a>';
							}
							if($phase == "ORGINV5EFF" && $park_no=="57"){
								echo '<a href="amplusExport.php?stamp='.$stamp.'&endstamp='.$endstamp.'&phase=' . $phase .'&args=0,1,0,12027,Inv5_Eff,Inverter5,15,%,8;0,1,0,12029,Solar_Radiation_Total,Irradiation,5,W/m&sup2;,\'Gold\';">';
								echo '    <img title="Export Meter data as .csv file" src="../imgs/xls.png">';
								echo '</a>';
							}							
							
							 if($phase == "ORGINV1ACPR" && $park_no=="57"){
								echo '<a href="amplusExport.php?stamp='.$stamp.'&endstamp='.$endstamp.'&phase=' . $phase .'&args=0,1,0,12028,Inv_AC_PR,Inv1%20AC%20PR,15,%,4;0,1,0,12029,Inv_irrad_600,Irradiation,15,W/m&sup2;,\'Gold\';0,1,0,12028,AC_Module_Temp_600,Module Temperature,15,&deg;C,\'darkred\'">';
								echo '    <img title="Export Meter data as .csv file" src="../imgs/xls.png">';
								echo '</a>';
							}  
							if($phase == "ORGINV2ACPR" && $park_no=="57"){
								echo '<a href="amplusExport.php?stamp='.$stamp.'&endstamp='.$endstamp.'&phase=' . $phase .'&args=0,1,0,12024,Inv_AC_PR,Inv2%20AC%20PR,15,%,4;0,1,0,12029,Inv_irrad_600,Irradiation,15,W/m&sup2;,\'Gold\';0,1,0,12028,AC_Module_Temp_600,Module Temperature,15,&deg;C,\'darkred\'">';
								echo '    <img title="Export Meter data as .csv file" src="../imgs/xls.png">';
								echo '</a>';
							} 
							if($phase == "ORGINV3ACPR" && $park_no=="57"){
								echo '<a href="amplusExport.php?stamp='.$stamp.'&endstamp='.$endstamp.'&phase=' . $phase .'&args=0,1,0,12021,Inv_AC_PR,Inv3%20AC%20PR,15,%,4;0,1,0,12029,Inv_irrad_600,Irradiation,15,W/m&sup2;,\'Gold\';0,1,0,12028,AC_Module_Temp_600,Module Temperature,15,&deg;C,\'darkred\'">';
								echo '    <img title="Export Meter data as .csv file" src="../imgs/xls.png">';
								echo '</a>';
							} 
							if($phase == "ORGINV4ACPR" && $park_no=="57"){
								echo '<a href="amplusExport.php?stamp='.$stamp.'&endstamp='.$endstamp.'&phase=' . $phase .'&args=0,1,0,12023,Inv_AC_PR,Inv4%20AC%20PR,15,%,4;0,1,0,12029,Inv_irrad_600,Irradiation,15,W/m&sup2;,\'Gold\';0,1,0,12028,AC_Module_Temp_600,Module Temperature,15,&deg;C,\'darkred\'">';
								echo '    <img title="Export Meter data as .csv file" src="../imgs/xls.png">';
								echo '</a>';
							} 
							if($phase == "ORGINV5ACPR" && $park_no=="57"){
								echo '<a href="amplusExport.php?stamp='.$stamp.'&endstamp='.$endstamp.'&phase=' . $phase .'&args=0,1,0,12027,Inv_AC_PR,Inv5%20AC%20PR,15,%,4;0,1,0,12029,Inv_irrad_600,Irradiation,15,W/m&sup2;,\'Gold\';0,1,0,12028,AC_Module_Temp_600,Module Temperature,15,&deg;C,\'darkred\'">';
								echo '    <img title="Export Meter data as .csv file" src="../imgs/xls.png">';
								echo '</a>';
							} 
							if($phase == "ORGINV6ACPR" && $park_no=="57"){
								echo '<a href="amplusExport.php?stamp='.$stamp.'&endstamp='.$endstamp.'&phase=' . $phase .'&args=0,1,0,12026,Inv_AC_PR,Inv6%20AC%20PR,15,%,4;0,1,0,12029,Inv_irrad_600,Irradiation,15,W/m&sup2;,\'Gold\';0,1,0,12028,AC_Module_Temp_600,Module Temperature,15,&deg;C,\'darkred\'">';
								echo '    <img title="Export Meter data as .csv file" src="../imgs/xls.png">';
								echo '</a>';
							}
							if($phase == "ORGINV1DCPR" && $park_no=="57"){
								echo '<a href="amplusExport.php?stamp='.$stamp.'&endstamp='.$endstamp.'&phase=' . $phase .'&args=0,1,0,12028,DC_Vol_Coeff,Inv 1(DC_Voltage),15,V,4;0,1,0,12029,Inv_irrad_600,Irradiation,15,W/m&sup2;,\'Gold\';0,1,0,12028,AC_Module_Temp_600,Module Temperature,15,&deg;C,\'darkred\'">';
								echo '    <img title="Export Meter data as .csv file" src="../imgs/xls.png">';
								echo '</a>';
							}
							if($phase == "ORGINV2DCPR" && $park_no=="57"){
								echo '<a href="amplusExport.php?stamp='.$stamp.'&endstamp='.$endstamp.'&phase=' . $phase .'&args=0,1,0,12024,DC_Vol_Coeff,Inv 2(DC_Voltage),15,V,4;0,1,0,12029,Inv_irrad_600,Irradiation,15,W/m&sup2;,\'Gold\';0,1,0,12028,AC_Module_Temp_600,Module Temperature,15,&deg;C,\'darkred\'">';
								echo '    <img title="Export Meter data as .csv file" src="../imgs/xls.png">';
								echo '</a>';
							}
							if($phase == "ORGINV3DCPR" && $park_no=="57"){
								echo '<a href="amplusExport.php?stamp='.$stamp.'&endstamp='.$endstamp.'&phase=' . $phase .'&args=0,1,0,12021,DC_Vol_Coeff,Inv 3(DC_Voltage),15,V,4;0,1,0,12029,Inv_irrad_600,Irradiation,15,W/m&sup2;,\'Gold\';0,1,0,12028,AC_Module_Temp_600,Module Temperature,15,&deg;C,\'darkred\'">';
								echo '    <img title="Export Meter data as .csv file" src="../imgs/xls.png">';
								echo '</a>';
							}
							if($phase == "ORGINV4DCPR" && $park_no=="57"){
								echo '<a href="amplusExport.php?stamp='.$stamp.'&endstamp='.$endstamp.'&phase=' . $phase .'&args=0,1,0,12023,DC_Vol_Coeff,Inv 4(DC_Voltage),15,V,4;0,1,0,12029,Inv_irrad_600,Irradiation,15,W/m&sup2;,\'Gold\';0,1,0,12028,AC_Module_Temp_600,Module Temperature,15,&deg;C,\'darkred\'">';
								echo '    <img title="Export Meter data as .csv file" src="../imgs/xls.png">';
								echo '</a>';
							}
							if($phase == "ORGINV5DCPR" && $park_no=="57"){
								echo '<a href="amplusExport.php?stamp='.$stamp.'&endstamp='.$endstamp.'&phase=' . $phase .'&args=0,1,0,12027,DC_Vol_Coeff,Inv 5(DC_Voltage),15,V,4;0,1,0,12029,Inv_irrad_600,Irradiation,15,W/m&sup2;,\'Gold\';0,1,0,12028,AC_Module_Temp_600,Module Temperature,15,&deg;C,\'darkred\'">';
								echo '    <img title="Export Meter data as .csv file" src="../imgs/xls.png">';
								echo '</a>';
							}
							if($phase == "ORGINV6DCPR" && $park_no=="57"){
								echo '<a href="amplusExport.php?stamp='.$stamp.'&endstamp='.$endstamp.'&phase=' . $phase .'&args=0,1,0,12026,DC_Vol_Coeff,Inv 6(DC_Voltage),15,V,4;0,1,0,12029,Inv_irrad_600,Irradiation,15,W/m&sup2;,\'Gold\';0,1,0,12028,AC_Module_Temp_600,Module Temperature,15,&deg;C,\'darkred\'">';
								echo '    <img title="Export Meter data as .csv file" src="../imgs/xls.png">';
								echo '</a>';
							}
                        }
                    } else {

                        if (!isset($park_no)) {
                            if (!isset($_SESSION ['park_no_s'])) {
                                $park_no = 0;
                            } else {
                                $park_no = $_SESSION ['park_no_s'];
                            }
                        }
                        $_SESSION ['park_no_s'] = $park_no;

                        if (!isset($subpark_id)) {
                            if (!isset($_SESSION ['subpark_s'])) {
                                $subpark_id = 0;
                            } else {
                                $subpark_id = $_SESSION ['subpark_s'];
                            }
                        }
                        $_SESSION ['subpark_s'] = $subpark_id;

                        if (!isset($area_id)) {
                            if (!isset($_SESSION ['area_s'])) {
                                $area_id = 0;
                            } else {
                                $area_id = $_SESSION ['area_s'];
                            }
                        }
                        $_SESSION ['area_s'] = $area_id;

                        if (!isset($phase)) {
                            if (!isset($_SESSION ['phase_s'])) {
                                $phase = "tag";
                            } else {
                                $phase = $_SESSION ['phase_s'];
                            }
                        }
                        $_SESSION ['phase_s'] = $phase;

                        if (!isset($jahr)) {
                            if (!isset($_SESSION ['jahr_s'])) {
                                $jahr = $jahr_heute;
                            } else {
                                $jahr = $_SESSION ['jahr_s'];
                            }
                        }
                        $_SESSION ['jahr_s'] = $jahr;

                        if (!isset($mon)) {
                            if (!isset($_SESSION ['mon_s'])) {
                                $mon = $monat_heute;
                            } else {
                                $mon = $_SESSION ['mon_s'];
                            }
                        }
                        $_SESSION ['mon_s'] = $mon;

                        if (!isset($tag)) {
                            if (!isset($_SESSION ['tag_s'])) {
                                $tag = $tag_heute;
                            } else {
                                $tag = $_SESSION ['tag_s'];
                            }
                        }
                        $_SESSION ['tag_s'] = $tag;

                        if ($phase == "tag") {
                            echo '<a href="pat.php?jahr=' . $jahr . '&mon=' . $mon . '&tag=' . $tag . '&portal=' . $park_no . '&phase=' . $phase . '">';

                            echo '     <img title="Export diagram as .csv file" src="../imgs/xls.png">';
                            echo '</a>';

                            echo '<a href="patPyr.php?jahr=' . $jahr . '&mon=' . $mon . '&tag=' . $tag . '&name=WeatherStation&device=108&offset=19800&useTs=0&showQueries=0">';
                            echo '    <img title="Export Pyranometer as .csv file" src="../imgs/sun.png">';
                            echo '</a>';

                            echo '<a href="patPr.php?jahr=' . $jahr . '&mon=' . $mon . '&tag=' . $tag . '&print=true&park_no=' . $park_no . '">';
                            echo '    <img title="Export Performance Ratio as .csv file" src="../imgs/xls_pr.png">';
                            echo '</a>';
                        }
                        
                    }
					}
                    if ($hideDelta == 0) {
                        if ($delta == 1) {
                            echo '<a href="../' . $sourcefile . $phpArg . '&args=' . $argBack . $phaseWord . $deltaNew . '&igate=' . $igate . '" target="_parent">';
                            echo '<img title="Toggle absolute values and d/dt" src="ddt1.png">';
                            echo '</a>';
                        } else {

                            echo '<a href="../' . $sourcefile . $phpArg . '&args=' . $argBack . $phaseWord . $deltaNew . '&igate=' . $igate . '" target="_parent">';
                           // echo '<img title="Toggle absolute values and d/dt" src="ddt0.png">';
                            echo '</a>';
                        }
                    }
                    if ($echoArgs == 1) {
                        ?>

                        <input width ="99%" type="text" name="unit" class="textfeld" 
                               value='$diagrammCode .= <?php echo $argString; ?>'>


                        <?php
                    }
                    ?>
                </div>
<!-- Excel sheet link end -->

            </div>
            <div style="float: left; height: 99%; display:none; width: 16%; text-align: center">
                <div
                    style="background-color: BlanchedAlmond; font-size: 85%; width: 99%; height: 60%; overflow: auto;"
                    id="legend"> <!-- displayheight-->
<p id="choices" style="float:right;   margin-right: 3px; height: 60%;
    overflow: auto;
    width: 99%;font: 13px/1.5em proxima-nova;"></p>
                </div>

                

            </div>
			<script type="text/javascript">
<?php if(($phase == 'ORGINV1EFF' && $park_no == 57) || ($phase == 'ORGINV2EFF' && $park_no == 57)|| ($phase == 'ORGINV3EFF' && $park_no == 57) ||
 ($phase == 'ORGINV4EFF' && $park_no == 57) || ($phase == 'ORGINV5EFF' && $park_no == 57) || ($phase == 'ORGINV6EFF' && $park_no == 57)){?>
	$(function() {

			var datasets = {
			"Inv Efficency": {
				
				<?php if($phase == 'ORGINV1EFF'){?>
				label: "Inv1 Efficency", points: { symbol: "circle" },
				data: <?php echo $inverEff1; ?>
				<?php } else if($phase == 'ORGINV2EFF'){?>
				label: "Inv2 Efficency", points: { symbol: "circle" },
				data: <?php echo $inverEff2; ?>
				<?php } else if($phase == 'ORGINV3EFF'){?>
				label: "Inv3 Efficency", points: { symbol: "circle" },
				data: <?php echo $inverEff3; ?>
				<?php } else if($phase == 'ORGINV4EFF'){?>
				label: "Inv4 Efficency", points: { symbol: "circle" },
				data: <?php echo $inverEff4; ?>
				<?php } else if($phase == 'ORGINV5EFF'){?>
				label: "Inv5 Efficency", points: { symbol: "circle" },
				data: <?php echo $inverEff5; ?>
				<?php } else if($phase == 'ORGINV6EFF'){?>
				label: "Inv6 Efficency", points: { symbol: "circle" },
				data: <?php echo $inverEff6; ?>
				<?php }?>
			}
		};
							
				
		// hard-code color indices to prevent them from shifting as
		// countries are turned on/off

		var i = 2;
		$.each(datasets, function(key, val) {
			val.color = i;
			++i;
		});

		// insert checkboxes 
		var choiceContainer = $("#choices");
		$.each(datasets, function(key, val) {
			choiceContainer.append("<br/><input type='checkbox' name='" + key +
				"' checked='checked' id='id" + key + "'></input>" +
				"<label for='id" + key + "'>"
				+ val.label + "</label>");
		});
		//show tooltips
		
		$("<div id='tooltip'></div>").css({
			position: "absolute",
			display: "none",
			border: "1px solid #fdd",
			padding: "2px",
			"background-color": "#fee",
			opacity: 0.80
		}).appendTo("body");
		
		choiceContainer.find("input").click(plotAccordingToChoices);
		$("#placeholder").bind("plothover", function (event, pos, item) {
		if (item) {
					var x = item.datapoint[0].toFixed(2),
						y = item.datapoint[1].toFixed(2);

					$("#tooltip").html(item.series.label + " of " + x + " = " + y)
						.css({top: item.pageY+5, left: item.pageX+5})
						.fadeIn(200);
				} else {
					$("#tooltip").hide();
				}
		});
		
		function plotAccordingToChoices() {

			var data = [];

			choiceContainer.find("input:checked").each(function () {
				var key = $(this).attr("name");
				if (key && datasets[key]) {
					data.push(datasets[key]);
				}
			});

			if (data.length > 0) {
				$.plot("#placeholder", data, {
					yaxis: {
						min: 0,
						axisLabel: 'Inverter Eff %',
						axisLabelUseCanvas: true,
						axisLabelFontSizePixels: 15,
						axisLabelFontFamily: 'Verdana, Arial, Helvetica, Tahoma, sans-serif',
						axisLabelPadding: 5

						
					},
					xaxis: {
						tickDecimals: 0,
						axisLabel: 'Solar Radiation(W/m²)',
						axisLabelUseCanvas: true,
						axisLabelFontSizePixels: 18,
						axisLabelFontFamily: 'Verdana, Arial, Helvetica, Tahoma, sans-serif',
						axisLabelPadding: 5
					}
				});
				
			}
		}

		plotAccordingToChoices();

		// Add the Flot version string to the footer

		//$("#footer").prepend("Flot " + $.plot.version + " &ndash; ");
	});
	
	<?php }elseif(($phase == 'ORGINV1ACPR' && $park_no == 57) || ($phase == 'ORGINV2ACPR' && $park_no == 57) || ($phase == 'ORGINV3ACPR' && $park_no == 57)
	|| ($phase == 'ORGINV4ACPR' && $park_no == 57) || ($phase == 'ORGINV5ACPR' && $park_no == 57) || ($phase == 'ORGINV6ACPR' && $park_no == 57)	){?>
	$(function() {

		var data = [
			<?php if($phase == 'ORGINV1ACPR'){?>
			{ data: <?php echo $invACPR1; ?>, points: { symbol: "diamond" }, label: "INV1 AC PR (%)" }
			<?php } else if($phase == 'ORGINV2ACPR'){?>
			{ data: <?php echo $invACPR2; ?>, points: { symbol: "diamond" }, label: "INV2 AC PR (%)" }
			<?php } else if($phase == 'ORGINV3ACPR'){?>
			{ data: <?php echo $invACPR3; ?>, points: { symbol: "diamond" }, label: "INV3 AC PR (%)" }
			<?php } else if($phase == 'ORGINV4ACPR'){?>
			{ data: <?php echo $invACPR4; ?>, points: { symbol: "diamond" }, label: "INV4 AC PR (%)" }
			<?php } else if($phase == 'ORGINV5ACPR'){?>
			{ data: <?php echo $invACPR5; ?>, points: { symbol: "diamond" }, label: "INV5 AC PR (%)" }
			<?php } else if($phase == 'ORGINV6ACPR'){?>
			{ data: <?php echo $invACPR6; ?>, points: { symbol: "diamond" }, label: "INV6 AC PR (%)" }
			<?php }?>
		];
	
		$.plot("#placeholder", data, {
			series: {
				points: {
					show: true,
					radius: 3
				}
			},
			grid: {
				hoverable: true
			},
			xaxis: {
				axisLabel: 'Module Temperature (°C)',
				axisLabelUseCanvas: true,
				axisLabelFontSizePixels: 18,
				axisLabelFontFamily: 'Verdana, Arial, Helvetica, Tahoma, sans-serif',
				axisLabelPadding: 10

			},
			yaxis: {
				axisLabel: 'Inv PR(%)',
				axisLabelUseCanvas: true,
				axisLabelFontSizePixels: 15,
				axisLabelFontFamily: 'Verdana, Arial, Helvetica, Tahoma, sans-serif',
				axisLabelPadding: 20

			},
		});	
		$("<div id='tooltip'></div>").css({
			position: "absolute",
			display: "none",
			border: "1px solid #fdd",
			padding: "2px",
            "font-size": "13px",
            "font-weight": "690",
			"background-color": "#fee",
			opacity: 0.80
		}).appendTo("body");

		$("#placeholder").bind("plothover", function (event, pos, item) {

				var str = "(" + pos.x.toFixed(2) + ", " + pos.y.toFixed(2) + ")";
				//$("#hoverdata").text(str);

				if (item) {
					var x = item.datapoint[0].toFixed(2),
						y = item.datapoint[1].toFixed(2);
						$("#tooltip").html(item.series.label + " at " + x + " Deg C = " + y)
						.css({top: item.pageY+5, left: item.pageX+5})
						.fadeIn(200);
				}
			});

		$("#placeholder").bind("plotclick", function (event, pos, item) {
			if (item) {
				$("#clickdata").text(" - click point " + item.dataIndex + " in " + item.series.label);
				plot.highlight(item.series, item.datapoint);
			}
		});
	});	
	<?php }elseif(($phase == 'ORGINV1DCPR' && $park_no == 57) || ($phase == 'ORGINV2DCPR' && $park_no == 57) || ($phase == 'ORGINV3DCPR' && $park_no == 57)
|| ($phase == 'ORGINV4DCPR' && $park_no == 57)	|| ($phase == 'ORGINV5DCPR' && $park_no == 57) || ($phase == 'ORGINV6DCPR' && $park_no == 57)){?>
	$(function() {

		var data = [
			<?php if($phase == 'ORGINV1DCPR'){?>
			{ data: <?php echo $inverDCPR1; ?>, points: { symbol: "circle" }, label: "INV1 DC Voltage(V)" }
			<?php } else if($phase == 'ORGINV2DCPR'){?>
			{ data: <?php echo $inverDCPR2; ?>, points: { symbol: "circle" }, label: "INV2 DC Voltage(V)" }
			<?php } else if($phase == 'ORGINV3DCPR'){?>
			{ data: <?php echo $inverDCPR3; ?>, points: { symbol: "circle" }, label: "INV3 DC Voltage(V)" }
			<?php } else if($phase == 'ORGINV4DCPR'){?>
			{ data: <?php echo $inverDCPR4; ?>, points: { symbol: "circle" }, label: "INV4 DC Voltage(V)" }
			<?php } else if($phase == 'ORGINV5DCPR'){?>
			{ data: <?php echo $inverDCPR5; ?>, points: { symbol: "circle" }, label: "INV5 DC Voltage(V)" }
			<?php } else if($phase == 'ORGINV6DCPR'){?>
			{ data: <?php echo $inverDCPR6; ?>, points: { symbol: "circle" }, label: "INV6 DC Voltage(V)" }
			<?php }?>
			
		
		];
	
		$.plot("#placeholder", data, {
			series: {
				points: {
					show: true,
					radius: 3
				}
			},
			grid: {
				hoverable: true
			},
			xaxis: {
				axisLabel: 'Module Temperature (°C)',
				axisLabelUseCanvas: true,
				axisLabelFontSizePixels: 18,
				axisLabelFontFamily: 'Verdana, Arial, Helvetica, Tahoma, sans-serif',
				axisLabelPadding: 10

			},
			yaxis: {
				axisLabel: 'Inv DC Volt(V)',
				axisLabelUseCanvas: true,
				axisLabelFontSizePixels: 15,
				axisLabelFontFamily: 'Verdana, Arial, Helvetica, Tahoma, sans-serif',
				axisLabelPadding: 20

			},
		});	
		$("<div id='tooltip'></div>").css({
			position: "absolute",
			display: "none",
			border: "1px solid #fdd",
			padding: "2px",
            "font-size": "13px",
            "font-weight": "690",
			"background-color": "#fee",
			opacity: 0.80
		}).appendTo("body");

		$("#placeholder").bind("plothover", function (event, pos, item) {

				var str = "(" + pos.x.toFixed(2) + ", " + pos.y.toFixed(2) + ")";
				//$("#hoverdata").text(str);

				if (item) {
					var x = item.datapoint[0].toFixed(2),
						y = item.datapoint[1].toFixed(2);

						$("#tooltip").html(item.series.label + " at " + x + " = " + y)
				   
						.css({top: item.pageY+5, left: item.pageX+5})
						.fadeIn(200);
				}
			});

		$("#placeholder").bind("plotclick", function (event, pos, item) {
			if (item) {
				$("#clickdata").text(" - click point " + item.dataIndex + " in " + item.series.label);
				plot.highlight(item.series, item.datapoint);
			}
		});
		

		// Add the Flot version string to the footer

		$("#footer").prepend("Flot " + $.plot.version + " &ndash; ");
	});
	

		
	<?php }?>

		
	</script>
			
			
          
        </div>
    </body>
</html>
