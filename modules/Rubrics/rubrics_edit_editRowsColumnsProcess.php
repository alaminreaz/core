<?php
/*
Gibbon, Flexible & Open School System
Copyright (C) 2010, Ross Parker

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

include "../../functions.php" ;
include "../../config.php" ;

include "./moduleFunctions.php" ;

//New PDO DB connection
try {
  	$connection2=new PDO("mysql:host=$databaseServer;dbname=$databaseName;charset=utf8", $databaseUsername, $databasePassword);
	$connection2->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$connection2->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
}
catch(PDOException $e) {
  echo $e->getMessage();
}

@session_start() ;

//Set timezone from session variable
date_default_timezone_set($_SESSION[$guid]["timezone"]);

//Search & Filters
$search=NULL ;
if (isset($_GET["search"])) {
	$search=$_GET["search"] ;
}
$filter2=NULL ;
if (isset($_GET["filter2"])) {
	$filter2=$_GET["filter2"] ;
}

$gibbonRubricID=$_GET["gibbonRubricID"] ;
$URL=$_SESSION[$guid]["absoluteURL"] . "/index.php?q=/modules/" . getModuleName($_POST["address"]) . "/rubrics_edit_editRowsColumns.php&gibbonRubricID=$gibbonRubricID&sidebar=false&search=$search&filter2=$filter2" ;
$URLSuccess=$_SESSION[$guid]["absoluteURL"] . "/index.php?q=/modules/" . getModuleName($_POST["address"]) . "/rubrics_edit.php&gibbonRubricID=$gibbonRubricID&sidebar=false&search=$search&filter2=$filter2" ;

if (isActionAccessible($guid, $connection2, "/modules/Rubrics/rubrics_edit.php")==FALSE) {
	//Fail 0
	$URL.="&updateReturn=fail0" ;
	header("Location: {$URL}");
}
else {
	$highestAction=getHighestGroupedAction($guid, $_POST["address"], $connection2) ;
	if ($highestAction==FALSE) {
		//Fail2
		$URL.="&updateReturn=fail2" ;
		header("Location: {$URL}");
	}
	else {
		if ($highestAction!="Manage Rubrics_viewEditAll" AND $highestAction!="Manage Rubrics_viewAllEditLearningArea") {
			//Fail 0
			$URL.="&updateReturn=fail0" ;
			header("Location: {$URL}");
		}
		else {
			//Proceed!
			//Check if school year specified
			if ($gibbonRubricID=="") {
				//Fail1
				$URL.="&updateReturn=fail1" ;
				header("Location: {$URL}");
			}
			else {
				try {
					if ($highestAction=="Manage Rubrics_viewEditAll") {
						$data=array("gibbonRubricID"=>$gibbonRubricID); 
						$sql="SELECT * FROM gibbonRubric WHERE gibbonRubricID=:gibbonRubricID" ;
					}
					else if ($highestAction=="Manage Rubrics_viewAllEditLearningArea") {
						$data=array("gibbonRubricID"=>$gibbonRubricID, "gibbonPersonID"=>$_SESSION[$guid]["gibbonPersonID"]); 
						$sql="SELECT * FROM gibbonRubric JOIN gibbonDepartment ON (gibbonRubric.gibbonDepartmentID=gibbonDepartment.gibbonDepartmentID) JOIN gibbonDepartmentStaff ON (gibbonDepartmentStaff.gibbonDepartmentID=gibbonDepartment.gibbonDepartmentID) AND NOT gibbonRubric.gibbonDepartmentID IS NULL WHERE gibbonRubricID=:gibbonRubricID AND (role='Coordinator' OR role='Teacher (Curriculum)') AND gibbonPersonID=:gibbonPersonID AND scope='Learning Area'" ;
					}
					$result=$connection2->prepare($sql);
					$result->execute($data);
				}
				catch(PDOException $e) { 
					//Fail2
					$URL.="&columnDeleteReturn=fail2" ;
					header("Location: {$URL}");
					break ;
				}
				
				if ($result->rowCount()!=1) {
					//Fail 2
					$URL.="&updateReturn=fail2" ;
					header("Location: {$URL}");
				}
				else {
					$row=$result->fetch() ;
					$gibbonScaleID=$row["gibbonScaleID"] ;
					$partialFail=FALSE ;
					
					//DEAL WITH ROWS
					$rowTitles=$_POST["rowTitle"] ;
					$rowOutcomes=$_POST["gibbonOutcomeID"] ;
					$rowIDs=$_POST["gibbonRubricRowID"] ;
					$count=0 ;
					foreach ($rowIDs as $gibbonRubricRowID) {
						if ($_POST["type-$count"]=="Standalone" OR $rowOutcomes[$count]=="") {
							try {
								$data=array("title"=>$rowTitles[$count], "gibbonRubricRowID"=> $gibbonRubricRowID); 
								$sql="UPDATE gibbonRubricRow SET title=:title, gibbonOutcomeID=NULL WHERE gibbonRubricRowID=:gibbonRubricRowID" ;
								$result=$connection2->prepare($sql);
								$result->execute($data);
							}
							catch(PDOException $e) { 
									$partialFail=TRUE ;
							}
						}
						else if ($_POST["type-$count"]=="Outcome Based") {
							try {
								$data=array("gibbonOutcomeID"=>$rowOutcomes[$count], "gibbonRubricRowID"=> $gibbonRubricRowID); 
								$sql="UPDATE gibbonRubricRow SET title='', gibbonOutcomeID=:gibbonOutcomeID WHERE gibbonRubricRowID=:gibbonRubricRowID" ;
								$result=$connection2->prepare($sql);
								$result->execute($data);
							}
							catch(PDOException $e) { 
									$partialFail=TRUE ;
							}
						}
						else {
							$partialFail=TRUE ;
						}
						
						$count++ ;
					}
					
					//DEAL WITH COLUMNS
					//If no grade scale specified
					if ($row["gibbonScaleID"]=="") {
						$columnTitles=$_POST["columnTitle"] ;
						$columnIDs=$_POST["gibbonRubricColumnID"] ;
						$count=0 ;
						foreach ($columnIDs as $gibbonRubricColumnID) {
							try {
								$data=array("title"=>$columnTitles[$count], "gibbonRubricColumnID"=> $gibbonRubricColumnID); 
								$sql="UPDATE gibbonRubricColumn SET title=:title, gibbonScaleGradeID=NULL WHERE gibbonRubricColumnID=:gibbonRubricColumnID" ;
								$result=$connection2->prepare($sql);
								$result->execute($data);
							}
							catch(PDOException $e) { 
									$partialFail=TRUE ;
							}
							$count++ ;
						}
					}
					//If scale specified	
					else {
						$columnGrades=$_POST["gibbonScaleGradeID"] ;
						$columnIDs=$_POST["gibbonRubricColumnID"] ;
						$count=0 ;
						foreach ($columnIDs as $gibbonRubricColumnID) {
							try {
								$data=array("gibbonScaleGradeID"=>$columnGrades[$count], "gibbonRubricColumnID"=> $gibbonRubricColumnID); 
								$sql="UPDATE gibbonRubricColumn SET title='', gibbonScaleGradeID=:gibbonScaleGradeID WHERE gibbonRubricColumnID=:gibbonRubricColumnID" ;
								$result=$connection2->prepare($sql);
								$result->execute($data);
							}
							catch(PDOException $e) { 
									$partialFail=TRUE ;
							}
							$count++ ;
						}
					}
					
					
					if ($partialFail) {
						//Fail 2
						$URL.="&updateReturn=fail4" ;
						header("Location: {$URL}");
					}
					else {
						//Success 0
						$URL=$URLSuccess . "&updateReturn=success0#rubricDesign" ;
						header("Location: {$URL}");
					}
				}
			}
		}
	}
}
?>