<?php

date_default_timezone_set('Australia/Sydney');
require("../connect.php");

class CountMembersScript {

	public $states = array(
		"NSW",
		"VIC",
		"QLD",
		"WA",
		"SA",
		"ACT",
		"NT",
		"TAS",
	);
	
	const ALL_STATES = 'All states';
	
	public $roles = array(
		"Architect"		=> array('CalcFromDB' => true, 'ShowInTable' => true, 'LetterInDB' => 'A'),	
		"Designer"		=> array('CalcFromDB' => true, 'ShowInTable' => true, 'LetterInDB' => 'S'),
		"Distributor"	=> array('CalcFromDB' => true, 'ShowInTable' => true, 'LetterInDB' => 'D'),
		"Builder"		=> array('CalcFromDB' => true, 'ShowInTable' => true, 'LetterInDB' => 'B'),
		"Installer"		=> array('CalcFromDB' => true, 'ShowInTable' => true, 'LetterInDB' => 'I'),
		"Admin"			=> array('CalcFromDB' => true, 'ShowInTable' => true, 'LetterInDB' => 'Z'),
		"JH Register"	=> array('CalcFromDB' => true, 'ShowInTable' => true, 'LetterInDB' => 'Y'),
		"Special"		=> array('CalcFromDB' => true, 'ShowInTable' => true, 'LetterInDB' => 'Q'),
		"TakeOff"		=> array('CalcFromDB' => true, 'ShowInTable' => true, 'LetterInDB' => 'T'),
		"JH Staff"		=> array('CalcFromDB' => true, 'ShowInTable' => true, 'LetterInDB' => 'J'),
	);
	
	const ALL = 'All';
	
	public $months = array(
		1 => 'Jan',
		2 => 'Feb',
		3 => 'Mar',
		4 => 'Apr',
		5 => 'May',
		6 => 'Jun',
		7 => 'Jul',
		8 => 'Aug',
		9 => 'Sep',
		10 => 'Oct',
		11 => 'Nov',
		12 => 'Dec'
	);
	
	public $years = array(
		2001,2002,2003,2004,2005,2006,2007,2008,2009,2010,2011,2012,2013,2014
	);
	
	public $totals = array();
	
	public $showMode = 'none';
	
	public $error = '';
	
	public $requestMonth = 0;
	public $requestYear = 0;
	public $requestYearReport = 0;
		
	public function makeSelectMonth($selectName,$selectedMonthNumber = 0) {
		if (!$selectedMonthNumber) {
			$selectedMonthNumber = $this->requestMonth;
		}
		$html = "<select name=\"$selectName\">\r\n";
		foreach ($this->months as $monthNumber => $monthName) {
			if ($monthNumber == $selectedMonthNumber) {
				$selected = 'selected="selected"';
			}
			else {
				$selected = '';
			}
			$html .= "<option $selected value=\"$monthNumber\">$monthName</option>\r\n";
		}
		$html .= "</select>\r\n";
		return $html;
	}
	
	public function makeSelectYear($selectName,$selectedYear = 0) {
		if (!$selectedYear) {
			$selectedYear = $this->requestYear;
		}
		$html = "<select name=\"$selectName\">\r\n";
		foreach ($this->years as $year) {
			if ($year == $selectedYear) {
				$selected = 'selected="selected"';
			}
			else {
				$selected = '';
			}
			$html .= "<option $selected value=\"$year\">$year</option>\r\n";
		}
		$html .= "</select>\r\n";
		return $html;
	}
	
	private function queryDB($sql) {
		$result = mysql_query($sql) or die(mysql_error());
		$data = array();
		while($row = mysql_fetch_array($result)) {
			$data[] = $row;
		}
		return $data;
	}
	
	public function getDataForPeriod($condition = '') {
		if ($condition != '') {
			$condition = ' AND ' . $condition;
		}
		$this->data = array();
		$data = array();
		
		foreach ($this->roles as $roleName => $roleSettings) {
			$this->data[$roleName] = array();
		}
		
		foreach ($this->states as $state) {

			$sql = 
"SELECT TYPE as 'type' , count( DISTINCT UPPER( REPLACE( company, ' ', '' ) ) ) AS 'co', count( firstname ) AS 'indiv'
FROM members
WHERE state = '" . $state . "' " . $condition . " 
GROUP BY TYPE ";
			$data[$state] = $this->queryDB($sql);
			foreach ($data[$state] as $dataByRole) {
				foreach ($this->roles as $roleName => $roleSettings) {
					if (!isset($this->data[$roleName][$state])) {
						$this->data[$roleName][$state] = array();
					}
					$roleLetter = $roleSettings['LetterInDB'];
					if ($dataByRole['type'] == $roleLetter) {
						$this->data[$roleName][$state]['co'] = $dataByRole['co'];
						$this->data[$roleName][$state]['indiv'] = $dataByRole['indiv'];
					}
				}
			}
		}
	}


	public function getDataByAllTime() {
		$this->getDataForPeriod();
		$this->calcTotalsForPeriod();
	}

	public function getDataByYear() {
		$currentYearStart = $this->requestYearReport . "-01-01 00:00:00";
		$currentYearEnd = $this->requestYearReport . "-12-31 23:59:59";
		
		$previousYearStart = ($this->requestYearReport - 1) . "-01-01 00:00:00";
		$previousYearEnd = ($this->requestYearReport - 1). "-12-31 23:59:59";
		
		
		$condition = "(created > \"$currentYearStart\") AND (created < \"$currentYearEnd\")";
		$previousPeriodCondition =  "(created > \"$previousYearStart\") AND (created < \"$previousYearEnd\")";
		
		$this->getDataForPeriod($condition);
		$this->calcTotalsForPeriod($condition);
		$this->calcComparisonForPeriod($previousPeriodCondition);
		//echo(" condition $condition ,  prev $previousPeriodCondition");
	}
	
	public function getDataByMonthAndYear() {
		$monthNumber = str_pad($this->requestMonth, 2, '0', STR_PAD_LEFT);
		$currentMonthStart = $this->requestYear . '-' . $monthNumber . '-01 00:00:00';
		$monthStartDate = $this->requestYear . '-' . $monthNumber . '-01';
		$currentMonthEnd = date("Y-m-t 23:59:59", strtotime($monthStartDate));

		$previousMonthEnd = date("Y-m-d H:i:s",strtotime($currentMonthStart)-1);
		$previousMonthStart = date("Y-m-01 00:00:00",strtotime($currentMonthStart)-1);
		
		
		$condition = "(created > \"$currentMonthStart\") AND (created < \"$currentMonthEnd\")";
		$previousPeriodCondition =  "(created > \"$previousMonthStart\") AND (created < \"$previousMonthEnd\")";
		
		//echo(" condition $condition ,  prev $previousPeriodCondition");
		$this->getDataForPeriod($condition);
		$this->calcTotalsForPeriod($condition);
		$this->calcComparisonForPeriod($previousPeriodCondition);
		
	}
	
	public function calcDataForPeriod($condition = '1') {
		
		$sql = 
"SELECT count( DISTINCT UPPER( REPLACE( company, ' ', '' ) ) ) AS 'co', count( firstname ) AS 'indiv'
FROM members
WHERE " . $condition;
		$result = $this->queryDB($sql);
		$data[self::ALL] = $result[0];
		
		foreach ($this->states as $state) {

			$sql = 
"SELECT TYPE as 'type' , count( DISTINCT UPPER( REPLACE( company, ' ', '' ) ) ) AS 'co', count( firstname ) AS 'indiv'
FROM members
WHERE state = '" . $state . "' AND " . $condition;
			$result = $this->queryDB($sql);
			$data[$state] = $result[0];
		}
		
		foreach ($this->roles as $roleName => $roleSettings) {

			$sql = 
"SELECT count( DISTINCT UPPER( REPLACE( company, ' ', '' ) ) ) AS 'co', count( firstname ) AS 'indiv'
FROM members
WHERE type = '" . $roleSettings['LetterInDB'] . "' AND " . $condition;
			$result = $this->queryDB($sql);
			$data[$roleName] = $result[0];
		}
		return $data;
	}
	
	public function calcTotalsForPeriod($condition = '1') {
		$this->totals = $this->calcDataForPeriod($condition);
	}
	
	
	// must be called after calcTotalsForPeriod()
	public function calcComparisonForPeriod($condition) {
		$this->comparison = $this->calcDataForPeriod($condition);
		foreach ($this->comparison as $valueName => $comparisonValue) {
			$this->comparison[$valueName]['co'] = $this->calcPercentsDifference($this->totals[$valueName]['co'],$comparisonValue['co']);
			$this->comparison[$valueName]['indiv'] = $this->calcPercentsDifference($this->totals[$valueName]['indiv'], $comparisonValue['indiv']);
		}
	}
	
	private function calcPercentsDifference($value,$oldValue) {
		if (floatval($oldValue) != 0) {
			$digits = round((($value - $oldValue)/ $oldValue) * 100,0);
		}
		else $digits = 0;
		return $digits . '%';
	}
	

	public function showTableByAllTime() {
		$withComparison = false;
		$this->getDataByAllTime();
		$this->showTable($withComparison);
	}

	public function showTableByYear() {
		$withComparison = true;
		$this->getDataByYear();
		$this->showTable($withComparison);
	}
	
	public function showTableByMonthAndYear() {
		$withComparison = true;
		$this->getDataByMonthAndYear();
		$this->showTable($withComparison);
	}
	
		public function makeTableHeader() {
		$html = "<th></th>\r\n";
		foreach ($this->states as $state) {
			$html .= '<th class="thHeader" colspan="2">' . $state . '</th>' . "\r\n";
		}
		
		$html .= '<th class="thHeader" colspan="2">ALL STATES</th>' . "\r\n";
		return $html;
	}
			
	public function makeTableSubHeader() {
		$html = "<th></th>\r\n";
		foreach ($this->states as $state) {
			$html .= "<th class=\"thSubHeader\">CO</th>\r\n";
			$html .= "<th class=\"thSubHeader\">Indiv</th>\r\n";
		}
		
		// for ALL_STATES
		$html .= "<th class=\"thSubHeader\">CO</th>\r\n";
		$html .= "<th class=\"thSubHeader\">Indiv</th>\r\n";
		
		return $html;
	}
	
			
	public function makeTableDataRows($withComparison) {
		$html = '';
		foreach ($this->roles as $roleName => $roleSettings) {
			if ($roleSettings['ShowInTable']) {
				$html .= "<tr class=\"trData\">\r\n";
				$html .= $this->makeTableDataRow($roleName) . "\r\n";
				$html .= "</tr>\r\n";
			}
		}
		
		$html .= "<tr class=\"trData\">\r\n";
		$html .= $this->makeTotalsTableDataRow() . "\r\n";
		$html .= "</tr>\r\n";
				
		if ($withComparison) {
			$html .= "<tr class=\"trData\">\r\n";
			$html .= $this->makeComparisonTableDataRow() . "\r\n";
			$html .= "</tr>\r\n";
		}
		
		return $html;
	}
	
	public function makeTableDataRow($roleName) {
		$html = "<td class=\"roleName\">$roleName</th>";
		foreach ($this->states as $state) {
			$html .= "<td>" . $this->data[$roleName][$state]['co'] . "</td><td>" . $this->data[$roleName][$state]['indiv'] . "</td>";
		}
		
		$html .= "<td>" . $this->totals[$roleName]['co'] . "</td><td>" . $this->totals[$roleName]['indiv'] . "</td>";
		return $html;
	}
	
	public function makeTotalsTableDataRow() {
		$html = "<td class=\"roleName\">ALL</th>";
		foreach ($this->states as $state) {
			$html .= "<td>" . $this->totals[$state]['co'] . "</td><td>" . $this->totals[$state]['indiv'] . "</td>";
		}
		
		$html .= "<td>" . $this->totals[self::ALL]['co'] . "</td><td>" . $this->totals[self::ALL]['indiv'] . "</td>";
		return $html;
	}
	
	public function makeComparisonTableDataRow() {
		$html = "<td class=\"roleName\">Versus Previous</th>";
		foreach ($this->states as $state) {
			$html .= "<td>" . $this->comparison[$state]['co'] . "</td><td>" . $this->comparison[$state]['indiv'] . "</td>";
		}
		
		$html .= "<td>" . $this->comparison[self::ALL]['co'] . "</td><td>" . $this->comparison[self::ALL]['indiv'] . "</td>";
		return $html;
	}
	
	private function showTable($withComparison = true) {
		?>
		<table class="tableResults" id="tablemonth">
		<thead>
			<tr>
				<?php echo $this->makeTableHeader(); ?>
			</tr>
			<tr>
				<?php echo $this->makeTableSubHeader(); ?>
			</tr>
		</thead>
		<tbody>
			<tr class="trData">
				<?php echo $this->makeTableDataRows($withComparison); ?>
			</tr>
		</tbody>
		</table>
		<?php
	}
	
	public function run() {
		$this->requestMonth = isset($_GET['month']) ? intval($_GET['month']) : date('n');
		$this->requestYear = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
		$this->requestYearReport = isset($_GET['year_report']) ? intval($_GET['year_report']) : date('Y') - 1;
	}

}

$script = new CountMembersScript();
$script->run();

?><!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Count Members script">
    <meta name="author" content="mgongee">

    <title>Count members</title>

    <!-- Bootstrap core CSS -->
    <link href="css/bootstrap.css" rel="stylesheet">

    <!-- custom CSS here -->
    <link href="css/style.css" rel="stylesheet">
	<link href="css/smoothness/jquery-ui-1.10.4.custom.css" rel="stylesheet">
	
	 <!-- Legacy JS -->
	<script type="text/javascript">
<!--
    function MM_swapImgRestore() { //v3.0
        var i, x, a = document.MM_sr; for (i = 0; a && i < a.length && (x = a[i]) && x.oSrc; i++) x.src = x.oSrc;
    }
    function MM_preloadImages() { //v3.0
        var d = document; if (d.images) {
            if (!d.MM_p) d.MM_p = new Array();
            var i, j = d.MM_p.length, a = MM_preloadImages.arguments; for (i = 0; i < a.length; i++)
                if (a[i].indexOf("#") != 0) { d.MM_p[j] = new Image; d.MM_p[j++].src = a[i]; } 
        }
    }

    function MM_findObj(n, d) { //v4.01
        var p, i, x; if (!d) d = document; if ((p = n.indexOf("?")) > 0 && parent.frames.length) {
            d = parent.frames[n.substring(p + 1)].document; n = n.substring(0, p);
        }
        if (!(x = d[n]) && d.all) x = d.all[n]; for (i = 0; !x && i < d.forms.length; i++) x = d.forms[i][n];
        for (i = 0; !x && d.layers && i < d.layers.length; i++) x = MM_findObj(n, d.layers[i].document);
        if (!x && d.getElementById) x = d.getElementById(n); return x;
    }

    function MM_swapImage() { //v3.0
        var i, j = 0, x, a = MM_swapImage.arguments; document.MM_sr = new Array; for (i = 0; i < (a.length - 2); i += 3)
            if ((x = MM_findObj(a[i])) != null) { document.MM_sr[j++] = x; if (!x.oSrc) x.oSrc = x.src; x.src = a[i + 2]; }
    }

    function switchGroup(obj) {
        GroType = $(obj).val();
        $(".Sdesign").hide(); $(".SBuild").hide();
        $(".SPartner").hide(); $(".SAdmin").hide();
        if (GroType == "ACCEL Design")
            $(".Sdesign").show();
        else if (GroType == "ACCEL Build")
            $(".SBuild").show();
        else if (GroType == "ACCEL Partner")
            $(".SPartner").show();
        else if (GroType == "ACCEL All Areas")
            $(".SAdmin").show();
    }

//-->
</script>
</head>

<body onLoad="MM_preloadImages('../../images2011/ACCEL_Dashboard_3_05r.jpg','../../images2011/ACCEL_Dashboard_3_06r.jpg','../../images2011/ACCEL_Dashboard_3_07r.jpg','../../images2011/ACCEL_Dashboard_3_08r.jpg','../../images2011/ACCEL_Dashboard_3_09r.jpg','../../images2011/ACCEL_Dashboard_3_10r.jpg','../../images2011/ACCEL_Dashboard_Chart_02r.jpg','../../images2011/ACCEL_Dashboard_3_03r.jpg')">
	
	<p class="error">
		<?php echo $script->error; ?>
	</p>
    <div class="container">
		<div class="row">
			<table border="0" cellspacing="0" cellpadding="0" style="border-style: solid; border-width: 0; padding-left: 0; padding-right: 0; padding-top: 0px; padding-bottom: 0px">
				<tr>
					<td height="42" colspan="4">
						<a href="../dashv3/countmember/countmember.php" onMouseOut="MM_swapImgRestore()" onMouseOver="MM_swapImage('Image9','','../dashnew_files/ACCEL_Dashboard_3_05r.jpg',1)"> 
							<img src="../dashnew_files/ACCEL_Dashboard_3_05.jpg" name="Image9" width="155" height="53" border="0"></a> 

						<a href="../dashv3/sales/salerep.php" onMouseOut="MM_swapImgRestore()" onMouseOver="MM_swapImage('Image10','','../dashnew_files/ACCEL_Dashboard_3_06r.jpg',1)"> 
							<img src="../dashnew_files/ACCEL_Dashboard_3_06.jpg" name="Image10" width="156" height="53" border="0"></a>

						<a href="../dashv3/usage/usage.php" onMouseOut="MM_swapImgRestore()" onMouseOver="MM_swapImage('Image11','','../dashnew_files/ACCEL_Dashboard_3_07r.jpg',1)">
							<img src="../../images2011/ACCEL_Dashboard_3_07.jpg" name="Image11" width="156" height="53" border="0"></a>

						<a href="../dashv3/ExtractPage/ExtractPage.php" onMouseOut="MM_swapImgRestore()" onMouseOver="MM_swapImage('Image12','','../dashnew_files/ACCEL_Dashboard_3_08r.jpg',1)"> 
							<img src="../dashnew_files/ACCEL_Dashboard_3_08.jpg" name="Image12" width="157" height="53" border="0"></a>

						<a href="../dashv3/sales/salerep_call_log.php" onMouseOut="MM_swapImgRestore()" onMouseOver="MM_swapImage('Image13','','../dashnew_files/ACCEL_Dashboard_3_09r.jpg',1)">
							<img src="../dashnew_files/ACCEL_Dashboard_3_09.jpg" name="Image13" width="157" height="53" border="0"></a>

						<a href="#" onMouseOut="MM_swapImgRestore()" onMouseOver="MM_swapImage('Image14','','../dashnew_files/ACCEL_Dashboard_3_10r.jpg',1)"> 

							<img src="../dashnew_files/ACCEL_Dashboard_3_10.jpg" name="Image14" width="157" height="53" border="0"></a> 

						<img src="../../images2011/ACCEL_Dashboard_3_11.jpg" width="179" height="53"></td>
				</tr>
			</table>
		</div>
        <div class="row">
            <div class="col-lg-12">
                <h1>Count Members</h1>
			</div>
		</div>
		<hr>		
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div id="accordion">
						<h3>Year to date</h3>
						<div>
							<h2>Data for year <?php echo ($script->requestYearReport); ?></h2>
							<?php $script->showTableByYear(); ?>
							<hr>
							<form method="get" action="count.php">
								<input type="hidden" name="action" value="getYearReport"/>
								<div class="col-lg-2">
									Year:
									<?php echo $script->makeSelectYear('year_report', $script->requestYearReport); ?>
								</div>
								<div class="col-lg-2">
									<input type="submit" value="Calculate Now"/>
								</div>
							</form>
						</div>
						<h3>Month to date</h3>
						<div>
							<h2>Data for <?php echo (date('F',mktime(0,0,0,$script->requestMonth,1,2000)) . ', ' . $script->requestYear); ?></h2>
							<?php $script->showTableByMonthAndYear(); ?>
							<hr>
							<form method="get" action="count.php">
								<input type="hidden" name="action" value="getMonthReport"/>
								<div class="col-lg-2">
									Month:      
									<?php echo $script->makeSelectMonth('month'); ?>
								</div>
								<div class="col-lg-2">
									Year:
									<?php echo $script->makeSelectYear('year'); ?>
								</div>
								<div class="col-lg-2">
									<input type="submit" value="Calculate Now"/>
								</div>
							</form>
						</div>
						<h3>All time</h3>
						<div>
							<?php $script->showTableByAllTime(); ?>
						</div>
					</div>
					
					
				</div>
				<hr>
			</div>
		</div>
        <footer>
            <div class="row">
                <div class="col-lg-12">
                    <p>&copy; mgongee 2014</p>
                </div>
            </div>
        </footer>

    </div>
    <!-- /.container -->

    <!-- JavaScript -->
    <script src="js/jquery-1.10.2.js"></script>
    <script src="js/bootstrap.js"></script>
	<script src="js/script.js"></script>	
	<script type="text/javascript" src="js/jquery-ui-1.10.4.custom.min.js"></script>
	<script type="text/javascript" src="js/script.js"></script>

</body>

</html>

