<?php

include('includes/session.inc');

$Title = _('Stock Check Sheets Entry');

include('includes/header.inc');

echo '<form name="EnterCountsForm" onSubmit="return VerifyForm(this);" action="' . htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') . '" method="post" class="noPrint">';
echo '<input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" />';

echo '<p class="page_title_text noPrint" ><img src="' . $RootPath . '/css/' . $Theme . '/images/inventory.png" title="' . _('Inventory Adjustment') . '" alt="" />' . ' ' . $Title . '</p>';

if (!isset($_POST['Action']) and !isset($_GET['Action'])) {
	$_GET['Action'] = 'Enter';
}
if (isset($_POST['Action'])) {
	$_GET['Action'] = $_POST['Action'];
}

if ($_GET['Action'] != 'View' and $_GET['Action'] != 'Enter') {
	$_GET['Action'] = 'Enter';
}

echo '<table class="selection"><tr>';
if ($_GET['Action'] == 'View') {
	echo '<td><a href="' . $RootPath . '/StockCounts.php?&amp;Action=Enter">' . _('Resuming Entering Counts') . '</a> </td><td>' . _('Viewing Entered Counts') . '</td>';
} else {
	echo '<td>' . _('Entering Counts') . '</td><td> <a href="' . $RootPath . '/StockCounts.php?&amp;Action=View">' . _('View Entered Counts') . '</a></td>';
}
echo '</tr></table><br />';

if ($_GET['Action'] == 'Enter') {

	if (isset($_POST['EnterCounts'])) {

		$Added = 0;
		for ($i = 1; $i <= 10; $i++) {
			$InputError = False; //always assume the best to start with

			$Quantity = 'Qty_' . $i;
			$BarCode = 'BarCode_' . $i;
			$StockID = 'StockID_' . $i;
			$Reference = 'Ref_' . $i;

			if (strlen($_POST[$BarCode]) > 0) {
				$sql = "SELECT stockmaster.stockid
								FROM stockmaster
								WHERE stockmaster.barcode='" . $_POST[$BarCode] . "'";

				$ErrMsg = _('Could not determine if the part being ordered was a kitset or not because');
				$DbgMsg = _('The sql that was used to determine if the part being ordered was a kitset or not was ');
				$KitResult = DB_query($sql, $db, $ErrMsg, $DbgMsg);
				$myrow = DB_fetch_array($KitResult);

				$_POST[$StockID] = strtoupper($myrow['stockid']);
			}

			if (mb_strlen($_POST[$StockID]) > 0) {
				if (!is_numeric($_POST[$Quantity])) {
					prnMsg(_('The quantity entered for line') . ' ' . $i . ' ' . _('is not numeric') . ' - ' . _('this line was for the part code') . ' ' . $_POST[$StockID] . '. ' . _('This line will have to be re-entered'), 'warn');
					$InputError = True;
				}
				$SQL = "SELECT stockid FROM stockcheckfreeze WHERE stockid='" . $_POST[$StockID] . "'";
				$result = DB_query($SQL, $db);
				if (DB_num_rows($result) == 0) {
					prnMsg(_('The stock code entered on line') . ' ' . $i . ' ' . _('is not a part code that has been added to the stock check file') . ' - ' . _('the code entered was') . ' ' . $_POST[$StockID] . '. ' . _('This line will have to be re-entered'), 'warn');
					$InputError = True;
				}

				if ($InputError == False) {
					$Added++;
					$sql = "INSERT INTO stockcounts (stockid,
									loccode,
									qtycounted,
									reference)
								VALUES ('" . $_POST[$StockID] . "',
									'" . $_POST['Location'] . "',
									'" . $_POST[$Quantity] . "',
									'" . $_POST[$Reference] . "')";

					$ErrMsg = _('The stock count line number') . ' ' . $i . ' ' . _('could not be entered because');
					$EnterResult = DB_query($sql, $db, $ErrMsg);
				}
			}
		} // end of loop
		prnMsg($Added . _(' Stock Counts Entered'), 'success');
		unset($_POST['EnterCounts']);
	} // end of if enter counts button hit

	$CatsResult = DB_query("SELECT DISTINCT stockcategory.categoryid,
								categorydescription
							FROM stockcategory INNER JOIN stockmaster
								ON stockcategory.categoryid=stockmaster.categoryid
							INNER JOIN stockcheckfreeze
								ON stockmaster.stockid=stockcheckfreeze.stockid", $db);

	if (DB_num_rows($CatsResult) == 0) {
		prnMsg(_('The stock check sheets must be run first to create the stock check. Only once these are created can the stock counts be entered. Currently there is no stock check to enter counts for'), 'error');
		echo '<div class="center"><a href="' . $RootPath . '/StockCheck.php">' . _('Create New Stock Check') . '</a></div>';
	} else {
		echo '<table cellpadding="2" class="selection">
				<tr>
					<th colspan="3">' . _('Stock Check Counts at Location') . ':<select name="Location">';
		if ($_SESSION['RestrictLocations'] == 0) {
			$sql = "SELECT locationname,
							loccode
						FROM locations";
		} else {
			$sql = "SELECT locationname,
							loccode
						FROM locations
						INNER JOIN www_users
							ON locations.loccode=www_users.defaultlocation
						WHERE www_users.userid='" . $_SESSION['UserID'] . "'";
		}
		$result = DB_query($sql, $db);
		while ($myrow = DB_fetch_array($result)) {

			if (isset($_POST['Location']) and $myrow['loccode'] == $_POST['Location']) {
				echo '<option selected="selected" value="' . $myrow['loccode'] . '">' . $myrow['locationname'] . '</option>';
			} else {
				echo '<option value="' . $myrow['loccode'] . '">' . $myrow['locationname'] . '</option>';
			}
		}
		echo '</select>&nbsp;<input type="submit" name="EnterByCat" value="' . _('Enter By Category') . '" /><select name="StkCat" onChange="ReloadForm(EnterCountsForm.EnterByCat)" >';

		echo '<option value="">' . _('Not Yet Selected') . '</option>';

		while ($myrow = DB_fetch_array($CatsResult)) {
			if (isset($_POST['StkCat']) and $_POST['StkCat'] == $myrow['categoryid']) {
				echo '<option selected="selected" value="' . $myrow['categoryid'] . '">' . $myrow['categorydescription'] . '</option>';
			} else {
				echo '<option value="' . $myrow['categoryid'] . '">' . $myrow['categorydescription'] . '</option>';
			}
		}
		echo '</select>
				</th>
			</tr>';

		if (isset($_POST['EnterByCat'])) {

			$StkCatResult = DB_query("SELECT categorydescription FROM stockcategory WHERE categoryid='" . $_POST['StkCat'] . "'", $db);
			$StkCatRow = DB_fetch_row($StkCatResult);

			echo '<tr>
					<th colspan="4">' . _('Entering Counts For Stock Category') . ': ' . $StkCatRow[0] . '</th>
				</tr>
				<tr>
					<th>' . _('Stock Code') . '</th>
					<th>' . _('Description') . '</th>
					<th>' . _('Quantity') . '</th>
					<th>' . _('Reference') . '</th>
				</tr>';
			$StkItemsResult = DB_query("SELECT stockcheckfreeze.stockid,
												description
										FROM stockcheckfreeze INNER JOIN stockmaster
										ON stockcheckfreeze.stockid=stockmaster.stockid
										WHERE categoryid='" . $_POST['StkCat'] . "'
										ORDER BY stockcheckfreeze.stockid", $db);

			$i = 1;
			while ($StkRow = DB_fetch_array($StkItemsResult)) {
				echo '<tr>
						<td><input type="hidden" name="StockID_' . $i . '" value="' . $StkRow['stockid'] . '" />' . $StkRow['stockid'] . '</td>
						<td>' . $StkRow['description'] . '</td>
						<td><input type="text" name="Qty_' . $i . '" maxlength="10" size="10" /></td>
						<td><input type="text" name="Ref_' . $i . '" maxlength="20" size="20" /></td>
					</tr>';
				$i++;
			}

		} else {

			echo '<tr>
					<th>' . _('Bar Code') . '</th>
					<th>' . _('Stock Code') . '</th>
					<th>' . _('Quantity') . '</th>
					<th>' . _('Reference') . '</th>
				</tr>';

			for ($i = 1; $i <= 10; $i++) {

				echo '<tr>
						<td><input type="text" name="BarCode_' . $i . '" maxlength="20" size="20" /></td>
						<td><input type="text" name="StockID_' . $i . '" maxlength="20" size="20" /></td>
						<td><input type="text" name="Qty_' . $i . '" maxlength="10" size="10" /></td>
						<td><input type="text" name="Ref_' . $i . '" maxlength="20" size="20" /></td>
					</tr>';

			}
		}

		echo '</table>
				<br />
				<div class="centre">
					<input type="submit" name="EnterCounts" value="' . _('Enter Above Counts') . '" />
				</div>';
	} // there is a stock check to enter counts for

	//END OF action=ENTER
} elseif ($_GET['Action'] == 'View') {

	if (isset($_POST['DEL']) and is_array($_POST['DEL'])) {
		foreach ($_POST['DEL'] as $id => $val) {
			if ($val == 'on') {
				$sql = "DELETE FROM stockcounts WHERE id='" . $id . "'";
				$ErrMsg = _('Failed to delete StockCount ID #') . ' ' . $i;
				$EnterResult = DB_query($sql, $db, $ErrMsg);
				prnMsg(_('Deleted Id #') . ' ' . $id, 'success');
			}
		}
	}

	//START OF action=VIEW
	$SQL = "SELECT * FROM stockcounts";
	$result = DB_query($SQL, $db);
	echo '<input type="hidden" name="Action" value="View" />';
	echo '<table cellpadding="2" class="selection">
			<tr>
				<th>' . _('Stock Code') . '</th>
				<th>' . _('Location') . '</th>
				<th>' . _('Qty Counted') . '</th>
				<th>' . _('Reference') . '</th>
				<th>' . _('Delete?') . '</th>
			</tr>';
	while ($myrow = DB_fetch_array($result)) {
		echo '<tr>
				<td>' . $myrow['stockid'] . '</td>
				<td>' . $myrow['loccode'] . '</td>
				<td>' . $myrow['qtycounted'] . '</td>
				<td>' . $myrow['reference'] . '</td>
				<td>';
		echo '<input type="checkbox" name="DEL[' . $myrow['id'] . ']" minlength="0" maxlength="20" size="20" /></td></tr>';

	}
	echo '</table><br /><div class="centre"><input type="submit" name="SubmitChanges" value="' . _('Save Changes') . '" /></div>';

	//END OF action=VIEW
}

echo '</form>';
include('includes/footer.inc');

?>