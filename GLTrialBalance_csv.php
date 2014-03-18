<?php

/* Through deviousness and cunning, this system allows trial balances for
 * any date range that recalcuates the p & l balances nand shows the balance
 * sheets as at the end of the period selected - so first off need to show
 * the input of criteria screen while the user is selecting the criteria the
 * system is posting any unposted transactions
 *
 * Needs to have FromPeriod and ToPeriod sent with URL
 * also need to work on authentication with username and password sent too
 */

//Page must be called with GLTrialBalance_csv.php?CompanyName=XXXXX&FromPeriod=Y&ToPeriod=Z
//htmlspecialchars($_SERVER['PHP_SELF'],ENT_QUOTES,'UTF-8') = dirname(htmlspecialchars($_SERVER['PHP_SELF'],ENT_QUOTES,'UTF-8')) .'/GLTrialBalance_csv.php?ToPeriod=' . $_GET['ToPeriod'] . '&FromPeriod=' . $_GET['FromPeriod'];

include('includes/session.inc');
include('includes/SQL_CommonFunctions.inc');

include('includes/GLPostings.inc'); //do any outstanding posting

$NumberOfMonths = $_GET['ToPeriod'] - $_GET['FromPeriod'] + 1;

$RetainedEarningsAct = $_SESSION['CompanyRecord']['retainedearnings'];

$SQL = "SELECT accountgroups.groupname,
			accountgroups.parentgroupname,
			accountgroups.pandl,
			chartdetails.accountcode ,
			chartmaster.accountname,
			Sum(CASE WHEN chartdetails.period='" . $_GET['FromPeriod'] . "' THEN chartdetails.bfwd ELSE 0 END) AS firstprdbfwd,
			Sum(CASE WHEN chartdetails.period='" . $_GET['FromPeriod'] . "' THEN chartdetails.bfwdbudget ELSE 0 END) AS firstprdbudgetbfwd,
			Sum(CASE WHEN chartdetails.period='" . $_GET['ToPeriod'] . "' THEN chartdetails.bfwd + chartdetails.actual ELSE 0 END) AS lastprdcfwd,
			Sum(CASE WHEN chartdetails.period='" . $_GET['ToPeriod'] . "' THEN chartdetails.actual ELSE 0 END) AS monthactual,
			Sum(CASE WHEN chartdetails.period='" . $_GET['ToPeriod'] . "' THEN chartdetails.budget ELSE 0 END) AS monthbudget,
			Sum(CASE WHEN chartdetails.period='" . $_GET['ToPeriod'] . "' THEN chartdetails.bfwdbudget + chartdetails.budget ELSE 0 END) AS lastprdbudgetcfwd
		FROM chartmaster INNER JOIN accountgroups ON chartmaster.group_ = accountgroups.groupname
			INNER JOIN chartdetails ON chartmaster.accountcode= chartdetails.accountcode
		GROUP BY accountgroups.groupname,
				accountgroups.parentgroupname,
				accountgroups.pandl,
				accountgroups.sequenceintb,
				chartdetails.accountcode,
				chartmaster.accountname
		ORDER BY accountgroups.pandl desc,
			accountgroups.sequenceintb,
			accountgroups.groupname,
			chartdetails.accountcode";

$AccountsResult = DB_query($SQL, $db);
$PeriodProfitLoss = 0;
$PeriodBudgetProfitLoss = 0;
$MonthProfitLoss = 0;
$MonthBudgetProfitLoss = 0;
$BFwdProfitLoss = 0;
$CSV_File = '';

while ($myrow = DB_fetch_array($AccountsResult)) {

	if ($myrow['pandl'] == 1) {
		$AccountPeriodActual = $myrow['lastprdcfwd'] - $myrow['firstprdbfwd'];
		$AccountPeriodBudget = $myrow['lastprdbudgetcfwd'] - $myrow['firstprdbudgetbfwd'];
		$PeriodProfitLoss += $AccountPeriodActual;
		$PeriodBudgetProfitLoss += $AccountPeriodBudget;
		$MonthProfitLoss += $myrow['monthactual'];
		$MonthBudgetProfitLoss += $myrow['monthbudget'];
		$BFwdProfitLoss += $myrow['firstprdbfwd'];
	} else {
		/*PandL ==0 its a balance sheet account */
		if ($myrow['accountcode'] == $RetainedEarningsAct) {
			$AccountPeriodActual = $BFwdProfitLoss + $myrow['lastprdcfwd'];
			$AccountPeriodBudget = $BFwdProfitLoss + $myrow['lastprdbudgetcfwd'] - $myrow['firstprdbudgetbfwd'];
		} else {
			$AccountPeriodActual = $myrow['lastprdcfwd'];
			$AccountPeriodBudget = $myrow['firstprdbfwd'] + $myrow['lastprdbudgetcfwd'] - $myrow['firstprdbudgetbfwd'];
		}
	}

	$CSV_File .= $myrow['accountcode'] . ', ' . html_entity_decode(stripcomma($myrow['accountname'])) . ', ' . locale_number_format($AccountPeriodActual, $_SESSION['CompanyRecord']['decimalplaces']) . ', ' . locale_number_format($AccountPeriodBudget, $_SESSION['CompanyRecord']['decimalplaces']) . "\n";
} //loop through the accounts

function stripcomma($str) { //because we're using comma as a delimiter
	return str_replace(',', '', $str);
}
header('Content-Encoding: UTF-8');
header('Content-type: text/csv; charset=UTF-8');
header("Content-disposition: attachment; filename=GL_Trial_Balance_" .  $_GET['FromPeriod']  . '-' .  $_GET['ToPeriod']  .'.csv');
header("Pragma: public");
header("Expires: 0");
echo "\xEF\xBB\xBF"; // UTF-8 BOM
echo $CSV_File;

?>
