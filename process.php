<?php 
/* VARIABLES AND SETTINGS */
$inventory_filename = $_POST["inventory"];
$directory_filename = $_POST["directory"]; //"dir-xml.xml";
$directory_documents = "../DOCUMENTS/"; // With trailing /

$report_name = $_POST["rname"];

$minimum_time = $_POST["mtime"]; //"06:20:00";
$maximum_time = $_POST["ltime"]; // "22:10:00";
$maximum_duration = $_POST["duration"]; //"00:07:30";

/***  TEST VARIABLES   ***/
$runProgram = true;
$stopper = 1;
$reporting = true;
/*************************/
//processAuthority(0,0,0);


if($runProgram) {
	/**************** OPEN FILES ****************/
	$inventory = file_get_contents($inventory_filename); //opens the inventory to text
	$xml = new SimpleXMLElement($inventory); //opens the inventory to XML
	$directory = file_get_contents($directory_filename); // opens the Directory to text
	$directory_xml = new SimpleXMLElement($directory); // opens the Directory XML once to minimize instances
	/**************** CREATE REPORT FILE ********/
	if($reporting) {$report = fopen($directory_documents."REPORTS/".$report_name.".html", "w+"); $iterations = 0;}	// CREATE NEW REPORT FILE (../DOCUMENTS/REPORTS) name,date
	/**************** RUN TESTS *****************/
	foreach( $xml->Worksheet->Table->Row as $Row) {
		foreach ($Row->Cell as $Cell) {
			/****************** CONTRACT **/
			if($reporting && strpos($Cell->Data, "Період: ") !== false) addToReport($report, str_replace("Період: ","", $Cell->Data), "header", $iterations); //creates report
			if(strpos($Cell->Data, "№") !== false) {
				$contract = parseContract($Cell->Data); // Gets Contract #
				$directory_contract = searchDirectory($directory_xml, $contract);
				if(strlen($directory_contract[0]) < 7) $directory_contract[0] = $contract;
				//var_dump($directory_contract);
				if($reporting) {
					$iterations++;
					addToReport($report, $directory_contract, "contract",  $iterations);
				}
				//echo "CONTRACT NUMBER:" . $contract . "<br>";
				//if($iterations == 3) die();
			}
			/****************** CALLER **/
			if($Cell->Data == "Вхідні дзвінки  ") $caller = parseCall($Row, "in"); // Gets Call Info, Incoming
			if($Cell->Data == "Вихідні дзвінки ") $caller = parseCall($Row, "out"); // Gets Call Info, Outgoing
			$directory_caller = searchDirectory($directory_xml, $caller[0]);
			if($directory_caller[0] != false) {
				processAuthority($directory_contract, $directory_caller, $caller);
				break;
			}
			elseif(strlen($caller[0]) > 7) {
			break;
			}
			$stopper++;
		}
		$caller = array(); // This fixes a loop bug.. without this.. it will cycle until it hits php's limits.
	}
	if($reporting) {
		addToReport($report,0,"close",$iterations);
		fclose($report);
	}
}

?>


<html>
	<head>
		<title>Phone Program 2.0</title>
		<style>
			* html body{overflow:hidden;}
			body {background:#FEF9F0;}
			#form {padding: 2px 5px 5px 5px;}
		</style>
	</head>
	<body>
		<div id="global">
			<div id="form">
				<h2>Phone Report</h2>  		
				<hr>
				<br><br><br>
				<center>
					<h1>Done!</h1>
					<br>
					<a href="<?php echo realpath("../DOCUMENTS/REPORTS/".$report_name.".html"); ?>"><?php echo $report_name; ?></a><br><br>
					<span>Right-Click, save target as. Just like a webpage</span>
				</center>
				<br><br><br><br><br><br><br><br><br><span><?php echo $iterations; ?> Contracts (Phone Numbers) Found.</span>
				<hr>
				<form style="padding:0;margin:0;" method="post" action="quit.php"> 
				<button type="submit" style="float:right;">  Quit  </button>
				</form>
			</div>
		</div>
	</body>
</html>


<?php


/******** FUNCTIONS *******/
function addToReport($report, $var, $type, $iteration) { // Reporting function
	if($type=="header") {
		$report_head = file_get_contents("rescources/report-head.html");		
		fwrite($report, str_replace("[[DATE]]",$var,$report_head));
	}
	if($type=="contract") { //will open a new table and setup for adding call lines, if it's the 2nd, it closes the first.
		if($iteration == 1) {
			$report_contract = file_get_contents("rescources/report-contract.html");
			$report_contract = str_replace("[[AREA]]", $var[1], $report_contract);
			$report_contract = str_replace("[[PERSON1]]", $var[2], $report_contract);
			$report_contract = str_replace("[[PERSON2]]", $var[3], $report_contract);
			$report_contract = str_replace("[[NUMBER]]", $var[0], $report_contract);
			$report_contract = str_replace("[[LIST-NUMBER]]", $iteration, $report_contract);
			fwrite($report, $report_contract);
		}
		else {
			$report_contract = "\n\t\t\t</table>\n\t\t</div>\n\t\t";
			$report_contract .= file_get_contents("rescources/report-contract.html");
			$report_contract = str_replace("[[AREA]]", $var[1], $report_contract);
			$report_contract = str_replace("[[PERSON1]]", $var[2], $report_contract);
			$report_contract = str_replace("[[PERSON2]]", $var[3], $report_contract);
			$report_contract = str_replace("[[NUMBER]]", $var[0], $report_contract);
			$report_contract = str_replace("[[LIST-NUMBER]]", $iteration, $report_contract);
			fwrite($report, $report_contract);			
		}
	}
	if($type=="caller-ok") {
		$reporting_caller_ok = "<tr id=\"ok\" toggle".$iteration."=\"x\" style=\"display:none;\"><td>";
		if($var[10] == "in") $reporting_caller_ok .= "Incoming</td><td>";
		else $reporting_caller_ok .= "Outgoing</td><td>";
		$reporting_caller_ok .= $var[2]." / ".$var[3]." - 0".$var[0]."</td><td>".substr($var[6],0,10)."</td><td>".$var[7]."</td><td>".substr($var[8], 11,8)."</td></tr>";
		fwrite($report,$reporting_caller_ok);
	}
	if($type=="caller-flagged") { // Writes a flagged table line
		$reporting_caller_flagged = "<tr class=\"flagged\"><td>";
		if($var[10] == "in") $reporting_caller_flagged .= "Incoming</td><td>";
		else $reporting_caller_flagged .= "Outgoing</td><td>";
		$reporting_caller_flagged .= $var[2]." / ".$var[3]." - 0".$var[0]."</td><td>".substr($var[6],0,10)."</td><td>".$var[7]."</td>";
		if(strtotime(substr($var[8],11,8)) > strtotime($GLOBALS['maximum_duration'])) $reporting_caller_flagged .= "<td class=\"over\">".substr($var[8], 11,8)."</td>";
		else $reporting_caller_flagged .= "<td>".substr($var[8], 11,8)."</td>";
		fwrite($report,$reporting_caller_flagged);
	}
	if($type=="close") {
		fwrite($report, "</table></div></div></body></html>");
	}
}
function processAuthority($phone1,$phone2,$call) {
	/** DEFINITIONS ************
	The Plan is similar to the old program. It worked well:
	A = All powerful and can call to anyone
	1,2,3,4 (Whole Numbers) Represent the Zone #
	1.1, 1.2, 1.3 (tenth place decimal) Represents the District #/ Leader
	/** VARIABLES **************/
	$perms_contract = $phone1[4]; //move to uppercase
	//$perms_contract = "2.1.S"; //move to uppercase
	$perms_caller = $phone2[4]; //move to uppercase
	//$perms_caller = "3.2.S"; //move to uppercase
	$flagged = false;
	//die();
	
	if($perms_contract != "A") {     //  These two lines remove the A level, all the rest are judgable 
		if($perms_caller != "A") {
		if($perms_caller != "F") {
			// Zone Leader Catch 
			if(strlen($perms_contract) < 2 || strlen($perms_caller) < 2) { //Tests for a whole number 1, 2, 3..
				if(substr($perms_contract,0,1) != substr($perms_caller,0,1)) $flagged = true; // Checks if the (1).1 and (1) match, flags if not
			}
			// District Catch
			elseif(strlen($perms_contract) < 4 || strlen($perms_caller) < 4) { // Test for a float 1.2, 3.4..
				if(substr($perms_contract,0,1) != substr($perms_caller,0,1)) $flagged = true; // Checks if the (1).1 and (1).8 match, flags if not
				elseif($perms_contract == $perms_caller) $flagged = false;
				//elseif(substr($perms_contract,2,1) != substr($perms_caller,2,1) && substr($perms_contract,2,1) != "" || substr($perms_caller,2,1) != "") $flagged = true; // Checks if the 1.(1) and 2.(1) match, flags if not
			}
			// Sister Catch
			if(strpos($perms_contract, "S") !== false || strpos($perms_caller, "S") !== false) { //Tests for S
				if(strpos($perms_contract, "S") !== strpos($perms_caller, "S")) { //Tests for one of them not being a sister
					if(substr($perms_contract,0,1) != substr($perms_caller,0,1)) $flagged = true; // Checks if the (1).1 and (1).8 match, flags if not
					elseif(($perms_contract + ".S") == $perms_caller) $flagged = false;
					//elseif(substr($perms_contract,2,1) != substr($perms_caller,2,1) && substr($perms_contract,2,1) != "" || substr($perms_caller,2,1) != "") $flagged = true; // Checks if the 1.(1) and 2.(1) match, flags if not
				}
			}
		}
		}
	}
		// Duration Catch
	if(strtotime(substr($call[3],11,8)) > strtotime($GLOBALS['maximum_duration'])) $flagged = true;
	
	// Time Catch
	if(strtotime($call[2]) >= strtotime($GLOBALS['maximum_time'])) $flagged = true;
	elseif(strtotime($call[2]) <= strtotime($GLOBALS['minimum_time'])) $flagged = true;
	
	// Flagged from Directory
	if($perms_contract == "F" || $perms_caller == "F") $flagged = true;
	//if(!$flagged) echo "free! <br>";
	/** REPORTING ***************/
	if($GLOBALS['reporting']) {
		if($flagged) addToReport($GLOBALS['report'], array_merge($phone2,$call), "caller-flagged",$GLOBALS['iterations']); 
		else addToReport($GLOBALS['report'], array_merge($phone2,$call), "caller-ok",$GLOBALS['iterations']);
	}
	
}
function searchDirectory($directory,$num) {
	$array = array();
	foreach( $directory->Worksheet->Table->Row as $Row) {
				if($num == str_replace(" ", "", $Row->Cell[0]->Data)) {
				$array = array(0 => (string)$Row->Cell[0]->Data, //process an array manualy to save time
					1 => (string)$Row->Cell[1]->Data,
					2 => (string)$Row->Cell[2]->Data,
					3 => (string)$Row->Cell[3]->Data,
					4 => (string)$Row->Cell[4]->Data);
				if($array[4] == "") {
					$array[4] = $array[3];
					$array[3] = ""; 
				}
				return $array;
			}
	}
	return array(0 => false);
}
function verifyDirectory($directory_filename,$directory_filedir) {
	$directory = file_get_contents($directory_filename);
	$xml = new SimpleXMLElement($directory);
	echo "<table>";
	foreach( $xml->Worksheet->Table->Row as $Row) {
		echo "<tr>";
		foreach ($Row->Cell as $Cell) {
			echo "<td>";
			echo $Cell->Data; //Show all
			echo "</td>";
		}
		echo "</tr>";
	}
	echo "</table>";
}

function parseContract($string) {
	$re1='.*?(телефону).*?(\\d+)'; // Very strong Contact Number grabber
	preg_match_all("/".$re1."/is", $string, $matches);
	return $matches[2][0];
}
function parseCall($Row, $type) {
	$array = array(	0 => (string)$Row->Cell[2]->Data,
					1 => (string)$Row->Cell[3]->Data,
					2 => str_replace(" ", "",(string)$Row->Cell[4]->Data),
					3 => (string)$Row->Cell[5]->Data,
					4 => (string)$Row->Cell[6]->Data);
	array_push($array, $type);
	if(strpos($array[0],"380") !== false) $array[0] = substr($array[0],3);
	return $array;
}

function killProcess($process) {
	shell_exec("Taskkill /F /IM " . $process);
}

?>