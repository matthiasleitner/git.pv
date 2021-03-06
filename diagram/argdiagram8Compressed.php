<?php

//argdiagram8compressed.php

$sourcetable = " _devicedatacompressedvalue3 ";


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

$stamp = $stamp;
$endstamp = $endstamp;



if (is_null($args)) {
    return;
}

$argString = "";
if ($echoArgs == 1) {
    $argString = '<tr><td><iframe id="frame1" width="99%" height="99%" SRC="diagram/argdiagram5.php?args=' . $args . '&defaults=' . $defaults . '&hideClear=1&hideDelta=1&stamp=' . $stamp . '&endstamp=' . $endstamp . '" border="0"></iframe></td></tr><br>';
    $count++;
}


$defaultNames = array();
if (is_null($defaults) || strlen($defaults) == 0) {
    $defaults = false;
} else {

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

//print_r($args);

$argBack = $args;
$args = split(";", $args);
$infos = split(";;", $infos);

$diffs = split(";", $diffs);
$sums = split(";", $sums);
$avgs = split(";", $avgs);

$displayItems = array();

foreach ($diffs as $avg) {
    $avgItems = split(",", $avg);
    if (sizeof($avgItems) != 14) {

        continue;
    }
    $currentValue = array();
    $erg = 0;
    $query = "select max(((value+" . $avgItems[0] . ")*" . $avgItems[1] . "+" . $avgItems[2] . ")) as value from _devicedatavalue where device = $avgItems[3] and field='$avgItems[4]' and ts < $stamp and ts > ($stamp-24*3600)";
    if ($avgItems[5] != 'null') {
        $query.=" and value > $avgItems[5]";
    }

    if ($showQueries == 1) {
        echo $query . "<br>";
    }
    $ds2 = mysql_query($query, $verbindung) or die(mysql_error());
    while ($row_ds2 = mysql_fetch_array($ds2)) {
        $erg -= $row_ds2[value];
    }

    mysql_free_result($ds2);

    $query = "select max(((value+" . $avgItems[0] . ")*" . $avgItems[1] . "+" . $avgItems[2] . ")) as value from _devicedatavalue where device = $avgItems[3] and field='$avgItems[4]' and ts > $stamp and ts < ($endstamp)";
    if ($avgItems[5] != 'null') {
        $query.=" and value > $avgItems[5]";
    }

    if ($showQueries == 1) {
        echo $query . "<br>";
    }
    $ds2 = mysql_query($query, $verbindung) or die(mysql_error());
    while ($row_ds2 = mysql_fetch_array($ds2)) {
        $erg += $row_ds2[value];
    }


    $values = 0;
    $avgValue = 0;


    $currentValue[value] = $erg;
    $currentValue[text] = $avgItems[7];
    $currentValue[color] = $avgItems[8];
    $currentValue[decimals] = $avgItems[9];
    $currentValue[unit] = $avgItems[10];
    $currentValue[size] = $avgItems[11];
    $currentValue[type] = $avgItems[12];
    $currentValue[font] = $avgItems[13];

    $displayItems[$avgItems[6]][] = $currentValue;

    mysql_free_result($ds2);
}


foreach ($avgs as $avg) {

    $avgItems = split(",", $avg);
    if (sizeof($avgItems) != 14) {
        continue;
    }
    $currentValue = array();
    $query = "select floor(ts) as ts, ((value+" . $avgItems[0] . ")*" . $avgItems[1] . "+" . $avgItems[2] . ") as value from _devicedatavalue where device = $avgItems[3] and field='$avgItems[4]' and ts > $stamp and ts < $endstamp";
    if ($avgItems[5] != 'null') {
        $query.=" and value > $avgItems[5]";
    }

    if ($showQueries == 1) {
        echo $query . "<br>";
    }
    $ds2 = mysql_query($query, $verbindung) or die(mysql_error());
    while ($row_ds2 = mysql_fetch_array($ds2)) {
        $currentValue[$row_ds2[ts]][] = $row_ds2[value];
    }

    $values = 0;
    $avgValue = 0;
    foreach ($currentValue as $value) {
        $avgValue+= array_sum($value) / sizeof($value);
        $values++;
    }

    $avgValue/=$values;

    $currentValue[value] = $avgValue;
    $currentValue[text] = $avgItems[7];
    $currentValue[color] = $avgItems[8];
    $currentValue[decimals] = $avgItems[9];
    $currentValue[unit] = $avgItems[10];
    $currentValue[size] = $avgItems[11];
    $currentValue[type] = $avgItems[12];
    $currentValue[font] = $avgItems[13];

    $displayItems[$avgItems[6]][] = $currentValue;

    mysql_free_result($ds2);
}
//sums=;0,13,0 ,451,U3, null,6,Total%20Irradiation,yellow,2,W/m²
//&avgs=-22.68,1.15,0 ,451,U4, null,5,Avg.%20Temperature,red,2,°C;&sums=0,13,0,451,U3,null

foreach ($sums as $sum) {
    $avgItems = split(",", $sum);
    if (sizeof($avgItems) == 14) {
        $avgItems[] = 0;
    }
    if (sizeof($avgItems) != 15) {

        continue;
    }
    $currentValue = array();
    $query = "select floor(ts/3600) as ts, ((value+" . $avgItems[0] . ")*" . $avgItems[1] . "+" . $avgItems[2] . ") as value from _devicedatavalue where device = $avgItems[3] and field='$avgItems[4]' and ts > $stamp and ts < $endstamp";
    if ($avgItems[5] != 'null') {
        $query.=" and value > $avgItems[5]";
    }

    if ($showQueries == 1) {
        echo $query . "<br>";
    }
    $ds2 = mysql_query($query, $verbindung) or die(mysql_error());
    while ($row_ds2 = mysql_fetch_array($ds2)) {
        $currentValue[$row_ds2[ts]][] = $row_ds2[value];
    }

    $avgValue = 0;
    foreach ($currentValue as $value) {
        if ($avgItems[14] == 1) {
            $avgValue+= array_sum($value);
        } else {
            $avgValue+= array_sum($value) / sizeof($value);
        }
    }

    mysql_free_result($ds2);
    if ($avgItems[14] == 1) {
        $avgValue = $avgValue / 4;
    }
    $currentValue[value] = $avgValue;
    $currentValue[text] = $avgItems[7];
    $currentValue[color] = $avgItems[8];
    $currentValue[decimals] = $avgItems[9];
    $currentValue[unit] = $avgItems[10];
    $currentValue[size] = $avgItems[11];
    $currentValue[type] = $avgItems[12];
    $currentValue[font] = $avgItems[13];

    $displayItems[$avgItems[6]][] = $currentValue;
}

ksort($displayItems);


$values = array();
$axis = array();
$devices = array();

$digitals = 0;


$argIndex = 0;

$groups = 0;
$groupsArray = array();
$groupsArray[] = 0;
foreach ($args as $arg) {
    $words = split(',', $arg);
    if (sizeof($words) != 10) {
        continue;
    }

    if (!in_array($words[9], $groupsArray)) {
        $groups++;
        $groupsArray[] = $words[9];
    }
}

foreach ($args as $arg) {

    $words = split(',', $arg);
    if (sizeof($words) != 10) {
        continue;
    }

    if ($speed != 0) {
        echo "<br><br>Argument: " . $words[5] . " " . (mktime() - $now) . "<br>";
    }

    $words[5] = str_replace("PLUS", "+", $words[5]);

    $anyArgs = true;
    if (!array_key_exists($words[3], $devices)) {
        $devices[$words[3]][name] = $words[5];

        if (is_numeric($words[8])) {
            $devices[$words[3]][color] = $words[8];
        } else {
            $devices[$words[3]][color] = "'" . $words[8] . "'";
        }

        $devices[$words[3]][values] = array();
    }
    if (!in_array($words[7], $axis)) {
        $axis[] = $words[7];
    }

    $translatedField = $words[4];
    $translatedField = str_replace("PLUS", "+", $translatedField);
    $translatedField = str_replace("RAUTE", "#", $translatedField);

    if($words[3]=="8414_8412"){
	$arrv2=explode("_",$words[3]);
	$devid=implode(",",$arrv2);
	// $query = "select ts+19800 as ts, ROUND(sum(value),2)/2 as value FROM $sourcetable where ts >= $stamp and ts < $endstamp and device in (".$devid.") and (field='".$translatedField."') group by ts";
	  $query = "select floor((ts+19800)/1440/60) * 1440*60 as ts, ROUND(sum((((value+$words[0])*$words[1])+$words[2])),2) as value from $sourcetable where value is not null and device in (".$devid.") and field = '$translatedField' and ts >= ($stamp-3600*13) and ts < $endstamp group by ts";
    }else{
    $query = "select floor((ts+19800)/1440/60) * 1440*60 as ts, (((value+$words[0])*$words[1])+$words[2]) as value from $sourcetable where value is not null and device = $words[3] and field = '$translatedField' and ts >= ($stamp-3600*13) and ts < $endstamp";

	}




    if ($showQueries == 1) {
        echo $query . "<br>";
    } else {
        $showQueries = 0;
    }

    $ax = array_search($words[7], $axis);
    if ($words[7] == "DIGITAL") {
        $digitals++;
    }

    if ($defaults && in_array($argIndex, $defaults)) {
        $defaultNames[] = $words[5];
    }
    $values[$words[5]][axis] = $ax;
    $values[$words[5]][color] = $words[8];
    $values[$words[5]][diff] = $words[9];
    $values[$words[5]][size] = 1440;
    if ($words[9]) {
        $values[$words[5]][type] = "bars";
    } else {
        $values[$words[5]][type] = "lines";
    }

    $delta = $words[9];


    $lastTs = 0;
    $lastValuechangeTs = 0;
    $lastValue = 0;
    $first = true;

    $ds2 = mysql_query($query, $verbindung) or die(mysql_error());
    while ($row_ds2 = mysql_fetch_array($ds2)) {

//        if ($delta && ($lastTs + 1440 * 60) > $row_ds2[ts]) {
//            continue;
//        }
        if ($speed != 0) {
            echo "hit " . (mktime() - $now) . "<br>";
        }
        //echo $words[5] . $lastTs . " " . $row_ds2[ts] . "=" . $row_ds2[value] . "<br>";

        while ($lastTs > 0 && (($lastTs + 1440 * 60) < $row_ds2[ts])) {

            $lastTs+= 1440 * 60;
            $values[$words[5]][data][$lastTs - 1440 * 30][value] = 0;
        }
        if ($first) {
            $first = false;
            $lastTs = 0;
            $lastValue = $row_ds2[value];
        }
        // if ($delta && ($lastTs + 1440 * 60) > $row_ds2[ts]) {
        //          continue;
        //      }

        if ($words[7] == "DIGITAL") {
            $values[$words[5]][data][$row_ds2[ts]][value] = $row_ds2[value] + (2 * ($digitals - 1));
        }
        if ($phase == "jahr") {
            mktime(5, 0, 0, date("n", $row_ds2[ts]));
            //mktime ([ int $hour = date("H") [, int $minute = date("i") [, int $second = date("s") [, int $month = date("n") [, int $day = date("j") [, int $year = date("Y")
            if ($words[9]) {
                $values[$words[5]][data][mktime(5, 0, 0, date("n", $row_ds2[ts]), 0, date("Y", $row_ds2[ts])) - 18 * 32000 + (($words[9] - 1) / $groups * 1440 * 32 * 30)][value] += ( ($row_ds2[value]));
            } else {
                $values[$words[5]][data][mktime(5, 0, 0, date("n", $row_ds2[ts]), 0, date("Y", $row_ds2[ts]))][value] += ( ($row_ds2[value]));
            }
        } else {
            if ($words[9]) {

                $values[$words[5]][data][$row_ds2[ts] + (($words[9] - 1) / $groups * 44000) - 3600 * 6][value] = ( ($row_ds2[value]));
            } else {
                $values[$words[5]][data][$row_ds2[ts]][value] = ( ($row_ds2[value]));
            }
        }
        $lastValuechangeTs = $row_ds2[ts];
        $lastValue = $row_ds2[value];

        $lastTs = $row_ds2[ts];
    }
    while (($lastTs + 1440 * 60) < $endstamp) {
        $lastTs += 1440 * 60;
        $values[$words[5]][data][$lastTs - 1440 * 30][value] = 0;
    }
    mysql_free_result($ds2);
    $argIndex++;
}

if ($speed != 0) {
    echo "data: " . (mktime() - $now) . "<br>";
}


$deviceString = "(null ";
$firstDevice = false;
foreach ($devices as $key => $device) {
    if ($firstDevice) {
        $firstDevice = false;
        $deviceString.=$key;
    } else {
        $deviceString .=",$key";
    }
}
$deviceString .= ")";
/*

  $query = "select igate, sn, deviceid from _device where deviceid in $deviceString";
  if ($showQueries == 1) {
  echo $query . "<br>";
  }
  $ds2 = mysql_query($query, $verbindung) or die(mysql_error());
  while ($row_ds2 = mysql_fetch_array($ds2)) {
  $sn = $row_ds2[sn];
  $sn = substr($sn, 3);
  $query = "select * from alarm where igate_id = $row_ds2[igate] and seriennummer='$sn' and tstamp < $endstamp and tstamp > $stamp order by tstamp";
  if ($showQueries == 1) {
  echo $query . "<br>";
  }
  $ds1 = mysql_query($query, $verbindung) or die(mysql_error());
  $first = true;
  $devices[$row_ds2[deviceid]][displaydata] = "[";
  while ($row_ds1 = mysql_fetch_array($ds1)) {
  if ($first == true) {
  $first = false;
  $devices[$row_ds2[deviceid]][displaydata].="[($row_ds1[tstamp]+19800)*1000, 0]";
  } else {
  $devices[$row_ds2[deviceid]][displaydata].=", [($row_ds1[tstamp]+19800)*1000, 0]";
  }
  //$devices[$row_ds2[deviceid]][values][($row_ds1[tstamp] + 19800) * 1000][nr] = $row_ds1[fehler_nr];
  $devices[$row_ds2[deviceid]][values][($row_ds1[tstamp] + 19800) * 1000][txt] = $row_ds1[fehler_txt];
  }
  $devices[$row_ds2[deviceid]][displaydata].="]";
  mysql_free_result($ds1);
  }
  mysql_free_result($ds2);


  if ($speed != 0) {
  echo "alarms: " . (mktime() - $now) . "<br>";
  }
 * 
 */
?>

<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html" charset="UTF-8" />
        <link rel="stylesheet" href="style.css" type="text/css" />
        <!--[if lte IE 9]><script type="text/javascript" src="../functions/flot/excanvas.js"></script><![endif]-->
        <script type="text/javascript" src="../functions/flot/jquery-1.7.min.js"></script>
        <script type="text/javascript" src="genresize.js"></script>
        <script type="text/javascript"
        src="../functions/flot/jquery.flot.min.js"></script>
        <script type="text/javascript"
        src="../functions/flot/jquery.flot.stack.min.js"></script>
        <script type="text/javascript"
        src="../functions/flot/jquery.flot.selection.min.js"></script>
    </head>
    <body>

        <div style="height: 99%; width: 100%">
            <div style="float: left; height: 99%; width: 84%; padding-top: 2px">
                <div>
                    <p>
                        <?php
                        echo $title;
                        ?>
                    </p>
                </div>
                <div id="placeholder" style="font-size: 95%; width: 100%; height: 100%" ></div>

            </div>
            <div style="float: left; height: 99%; width: 15%; text-align: center">
                <div
                    style="background-color: BlanchedAlmond; font-size: 85%; width: 99%; height: 69%; overflow: auto;"
                    id="legend">

                </div>

                <div style="height: 30%; background-color: beige; width: 99%; overflow: auto;"
                     id="buttons">
                         <?php
                         foreach ($displayItems as $myitem) {
                             foreach ($myitem as $myelement) {
                                 echo '<p style = "color:' . $myelement[color] . ';font-family:' . $myelement[font] . ';font-size:' . $myelement[size] . 'px">' . $myelement[text] . '<br>' . number_format($myelement[value], $myelement[decimals], '.', '') . $myelement[unit] . '</p>';
                             }
                         }
                         ?>
                    <input title="Reset the zoom"    style="flow: left;" id="resetZoom" onclick="resetZoom()" type="image"    src="../imgs/lupe_grey.png" disabled>

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
					
					$user_name = $_SESSION['user'];
					$query_ds = "select export FROM users WHERE user = '$user_name' and export=1";
					$ds = mysql_query($query_ds, $verbindung) or die(mysql_error());
					$row_export = mysql_num_rows($ds);
					if($row_export==1){
                    if ($anyArgs) {
                        if ($hideClear == 0) {
                            echo '<a href="../' . $sourcefile . $phpArg . $phaseWord . '&igate=' . $igate . '" target="_parent">';
                            echo '<img title="Reset Diagram" src="clear.png">';
                            echo '</a>';
                        }

                        echo '<a href="exportMon.php' . $phpArg . '&args=' . $argBack . $phaseWord . '&compress=1&delta=' . $delta . '" target="_parent">';
                        echo '<img title="Export selection as Excel file" src="xls.png">';
                        echo '</a>';
                    } else {
                        if ($hideClear == 0) {
                            echo '<img title="Reset Diagram" src="clear0.png">';
                        }
                        echo '<img title="Export selection as Excel file" src="xls0.png">';
                    }

                    if ($hideDelta == 0) {
                        if ($delta == 1) {
                            echo '<a href="../' . $sourcefile . $phpArg . '&args=' . $argBack . $phaseWord . $deltaNew . '&igate=' . $igate . '" target="_parent">';
                            echo '<img title="Toggle absolute values and d/dt" src="ddt1.png">';
                            echo '</a>';
                        } else {

                            echo '<a href="../' . $sourcefile . $phpArg . '&args=' . $argBack . $phaseWord . $deltaNew . '&igate=' . $igate . '" target="_parent">';
                            echo '<img title="Toggle absolute values and d/dt" src="ddt0.png">';
                            echo '</a>';
                        }
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


            </div>
            <script type="text/javascript">
                var zeigeKurve = {};
                var alarmTexts = new Array();
<?php
echo "var start = ($stamp-3600*8)*1000;\n";
if ($phase == "jahr") {

    echo "start = start - 3600*24*20000;\n";
}
echo "var min = start;\n";
echo "var end = ($endstamp+16200)*1000;\n";
echo "var max = end;\n";

if (false) {

    foreach ($devices as $device) {

        foreach ($device[values] as $ts => $data) {
            echo "alarmTexts[$ts]='$data[txt]';\n";
        }
    }
}
?>
    
    
    var miny=null;
    var maxy=null;
    var resolution = <?php echo $resolution; ?>;
                        
    $(function () {
        $("#frame").resize();
        plotWithOptions();			
				
    });
    
    function toggleKurve(label){
        zeigeKurve[label] = !zeigeKurve[label];
        plotWithOptions();
    }
                    
    function resetZoom(){
        min = start;
        max = end;
        miny=null;
        maxy=null;
        document.getElementById("resetZoom").disabled=true;
        document.getElementById("resetZoom").src="../imgs/lupe_grey.png";
        plotWithOptions();
    }

    function showTooltip(x, y, contents, ts) {
        var content = contents;
        if (contents.indexOf("undefined") == 0){
            content = alarmTexts[ts];
            
        }
        $('<div id="tooltip">' + content + '</div>').css( {
            position: 'absolute',
            display: 'none',
            top: y - 25,
            left: x + 5,
            border: '1px solid #fdd',
            padding: '2px',
            'background-color': '#fee',
            opacity: 0.80
        }).appendTo("body").fadeIn(200);
    }
    
    var previousPoint = null;
    var previousLabel = null;
    $("#placeholder").bind("plothover", function (event, pos, item) {

        $("#x").text(pos.x);
        $("#y").text(pos.y);

        if (item) {
            if (previousPoint != item.dataIndex || previousLabel != item.series.label) {
                previousPoint = item.dataIndex;
                previousLabel = item.series.label;
                    
                $("#tooltip").remove();
                
                if (item.datapoint.length==3){
                    
                    
                
                    var x = new Date(item.datapoint[0]+1440*30000);
                    var  y = item.datapoint[1].toFixed(resolution);
                    var  y2 = item.datapoint[2].toFixed(resolution);
                

                    var xmin = (x.getUTCDate());
                    if (xmin<10){
                        xmin="0"+xmin;
                    }
                    var xh = x.getUTCMonth();
                    switch(xh){
                        case 0: 
                            xh="Jan ";
                            break;
                        case 1:
                            xh = "Feb ";
                            break;
                        case 2:
                            xh = "Mar ";
                            break;
                        case 3:
                            xh = "Apr ";
                            break;
                        case 4:
                            xh = "May ";
                            break;
                        case 5:
                            xh = "June ";
                            break;
                        case 6:
                            xh = "July ";
                            break;
                        case 7:
                            xh = "Aug ";
                            break;
                        case 8:
                            xh = "Sep ";
                            break;
                        case 9:
                            xh = "Oct ";
                            break;
                        case 10:
                            xh = "Nov ";
                            break;
                        case 11:
                            xh = "Dec ";
                        
                    }
                
                    var label = item.series.label;
                    var unit = "";
                    if (item.series.label.indexOf("DIGITAL")!=-1){
                        if (y%2==0){
                            y="LOW";
                        }else {
                            y="HIGH";
                        }
                    }else {
                        label = item.series.label.substring(0, item.series.label.indexOf("("));
                        unit = " "+item.series.label.substring(item.series.label.indexOf("(")+1, item.series.label.indexOf(")"));
                    }
                
                
                    showTooltip(pos.pageX, pos.pageY,
                    label +" ( "+xh+xmin + ". ) = " + (y-y2).toFixed(resolution)+unit +" ("+y+unit+")", item.datapoint[0]);
                }else{
                    var x = new Date(item.datapoint[0]+1440*30000);
                    x=x+1;
                    
                    
                    var unit = " "+item.series.label.substring(item.series.label.indexOf("(")+1, item.series.label.indexOf(")"));
                    showTooltip(pos.pageX, pos.pageY,
                    
                    item.series.label +" = " + (item.datapoint[1]).toFixed(resolution)+unit , item.datapoint[0]);
                }
            }
        }
        else {
            $("#tooltip").remove();
            previousPoint = null;   
            previousLabel = null;
        }
    });

    $("#placeholder").bind("plotselected", function(event, ranges)
    {
        document.getElementById("resetZoom").src="../imgs/lupe.png";
        document.getElementById("resetZoom").disabled=false;
        min = ranges.xaxis.from;
        max = ranges.xaxis.to;
        
<?php
echo "max = Math.max(max, min+3600000*2);";
?>
        plotWithOptions();	
    }
);
    var devValues=new Array();

<?php
$index = 0;
foreach ($values as $devkey => $device) {
    echo "devValues['$index']=new Array();\n";
    if ($defaults == false) {

        echo "zeigeKurve['$devkey']=true;\n";
    } else {
        if (in_array($devkey, $defaultNames)) {
            echo "zeigeKurve['$devkey']=true;\n";
        } else {
            echo "zeigeKurve['$devkey']=false;\n";
        }
    }
    foreach ($device[data] as $tskey => $value) {

        echo "devValues['" . $index . "'].push([" . ($tskey * 1000) . ", " . $value[value] . "]);\n";
    }
    $index++;
}
?>

    function plotWithOptions(){

        var options = 
            { 
            xaxis: 
                { 
                mode: "time" , 
                min: min, 
                max: max,
                tickSize: [1, 
<?php
if ($phase == "jahr") {
    echo '"month"';
} else {
    echo '"day"';
}
?>
                ]
            },
           
            grid: { hoverable: true, autoHighlight: true } 
            ,lines: {show: false}
            ,points: {show: false}
            ,bars: {show: false}
            ,yaxes: [ 
<?php
$first = true;
foreach ($axis as $ax) {
    if ($first) {
        $first = false;
    } else {
        echo ",";
    }
    $ax2 = str_replace("DEG", "&deg;", $ax);
    $ax2 = str_replace("%B0", "&deg;", $ax2);


    $ax2 = str_replace("SQUA", "&sup2;", $ax2);

    if ($ax2 == "DIGITAL") {
        echo "{ticks: [";

        for ($index = 0; $index < $digitals; $index++) {
            if ($index > 0) {
                echo ",";
            }
            echo "[" . ($index * 2 + 0.15) . ", 'LOW'], [" . ($index * 2 + 0.85) . ", 'HIGH']";
        }
        echo "]}";
    } else {
        echo "{tickFormatter: function(v, axis){return v.toFixed(axis.tickDecimals) +' $ax2'}}";
    }
}
?>
            ]
            ,selection: { mode: "x"}
            
            ,legend: {container: $("#legend"),
                labelFormatter: function (label, series) {
                    var cutLabel = label.substr(0, label.indexOf(" ("));
                    			
                    var zeige = ""
                    if (zeigeKurve[cutLabel]){
                        zeige = 'checked="checked"';

                    }
				                
                    var cb = '<input type="checkbox" name="' + cutLabel + '" '+zeige+' id="id' + cutLabel + '" onClick="toggleKurve(\''+cutLabel+'\');"> ' + label+'</input>';
                    return cb;
                }
            }
        };
        $.plot($("#placeholder"), [
<?php
$first = true;

$index = 0;
foreach ($values as $key => $value) {
    if (!$first) {
        echo ",";
    } else {
        $first = false;
    }
    $axisWord = "";
    if (isset($axis[$value[axis]])) {
        $axisWord = " (" . $axis[$value[axis]] . ")";
        $axisWord = str_replace("DEG", "&deg;", $axisWord);
        $axisWord = str_replace("SQUA", "&sup2;", $axisWord);
        $axisWord.="'";
        if ($axis[$value[axis]] == "DIGITAL") {
            $axisWord.=", lines: {show: zeigeKurve['" . $key . "'], steps: true}";
        }
    }
    $x = 1;
    if ($phase == "jahr") {

        $x = 28;
    }
    echo "{ stack: zeigeKurve['" . $key . "']+$value[diff], $value[type]: {show: zeigeKurve['" . $key . "'], barWidth: 1440*$x*30000/$groups}, yaxis: " . ($value[axis] + 1) . ", color: $value[color], label: '" . $key . $axisWord . ", data: devValues['" . ($index++) . "']}\n";
}



$first = true;
/*
  foreach ($devices as $key => $device) {
  echo ", { points: {show: true}, lines: {show: false}, color: $device[color], data: $device[displaydata] }\n";
  }
 */
?>
        ],options);

    }
    window.onresize = plotWithOptions;
<?php
if (false && $speed != 0) {
    echo "alert('total: " . (mktime() - $now) . "');";
}
?>
            </script>
        </div>
    </body>
</html>

