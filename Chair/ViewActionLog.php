<?php 
require_once('../Code/header.inc');
$Me = $_SESSION["Me"];
$Me->goIfInvalid();
$Me->goIfNotPrivChair('../');

if (defval($_REQUEST["page"], "") == "earliest")
    $page = false;
else if (($page = cvtint($_REQUEST["page"], -1)) <= 0)
    $page = 1;
if (($count = cvtint($_REQUEST["n"], -1)) <= 0)
    $count = 25;
if (($offset = cvtint($_REQUEST["offset"], -1)) < 0 || $offset >= $count)
    $offset = 0;
if ($offset == 0 || $page == 1) {
    $start = ($page - 1) * $count;
    $offset = 0;
} else
    $start = ($page - 2) * $count + $offset;

$Conf->header("Log", "actionlog", actionBar());

$wheres = array();
$Eclass["q"] = $Eclass["pap"] = $Eclass["acct"] = $Eclass["n"] = $Eclass["date"] = "";

$_REQUEST["q"] = trim(defval($_REQUEST["q"], ""));
$_REQUEST["pap"] = trim(defval($_REQUEST["pap"], ""));
$_REQUEST["acct"] = trim(defval($_REQUEST["acct"], ""));
$_REQUEST["n"] = trim(defval($_REQUEST["n"], "25"));
$_REQUEST["date"] = trim(defval($_REQUEST["date"], "now"));

if ($_REQUEST["pap"] && !preg_match('/\A[\d\s]+\Z/', $_REQUEST["pap"])) {
    $Conf->errorMsg("The \"Concerning paper(s)\" field requires space-separated paper numbers.");
    $Eclass["pap"] = " error";
} else if ($_REQUEST["pap"]) {
    $where = array();
    foreach (preg_split('/\s+/', $_REQUEST["pap"]) as $pap) {
	$where[] = "paperId=$pap";
	$where[] = "action like '%(papers% $pap,%'";
	$where[] = "action like '%(papers% $pap)%'";
    }
    $wheres[] = "(" . join(" or ", $where) . ")";
}

if ($_REQUEST["acct"]) {
    $where = array();
    foreach (preg_split('/\s+/', $_REQUEST["acct"]) as $acct) {
	if (strpos($acct, "@") === false) {
	    $where[] = "firstName like '%" . sqlq_for_like($acct) . "%'";
	    $where[] = "lastName like '%" . sqlq_for_like($acct) . "%'";
	}
	$where[] = "email like '%" . sqlq_for_like($acct) . "%'";
    }
    $result = $Conf->qe("select contactId, email from ContactInfo where " . join(" or ", $where), "while finding matching accounts");
    $where = array();
    while (($row = edb_row($result))) {
	$where[] = "contactId=$row[0]";
	$where[] = "action like '%" . sqlq_for_like($row[1]) . "%'";
    }
    if (count($where) == 0) {
	$Conf->infoMsg("No accounts match '" . htmlspecialchars($_REQUEST["acct"]) . "'.");
	$wheres[] = "false";
    } else
	$wheres[] = "(" . join(" or ", $where) . ")";
}

if (($str = $_REQUEST["q"])) {
    $where = array();
    while (($str = ltrim($str)) != "") {
	preg_match('/^("[^"]+"?|[^"\s]+)/s', $str, $m);
	$str = substr($str, strlen($m[0]));
	$where[] = "action like '%" . sqlq_for_like($m[0]) . "%'";
    }
    $wheres[] = "(" . join(" or ", $where) . ")";
}

if (($count = cvtint($_REQUEST["n"])) <= 0) {
    $Conf->errorMsg("\"Show <i>n</i> records\" requires a number greater than 0.");
    $Eclass["n"] = " error";
    $count = 25;
}

$firstDate = false;
if ($_REQUEST["date"] == "")
    $_REQUEST["date"] = "now";
if ($_REQUEST["date"] != "now" && isset($_REQUEST["search"]))
    if (($firstDate = strtotime($_REQUEST["date"])) === false) {
	$Conf->errorMsg("\"" . htmlspecialchars($_REQUEST["date"]) . "\" is not a valid date.");
	$Eclass["date"] = " error";
    }

function searchbar() {
    global $ConfSiteBase, $Eclass, $page, $start, $count, $nrows, $maxNrows, $offset;
    
    echo "<form method='get' action='ViewActionLog.php'>
<table id='searchform'><tr>
  <td class='lcaption", $Eclass['q'], "'>With <b>any</b> of the words</td>
  <td class='lentry", $Eclass['q'], "'><input class='textlite' type='text' size='40' name='q' value=\"", htmlspecialchars(defval($_REQUEST["q"], "")), "\" /><span class='sep'></span></td>
  <td rowspan='3'><input class='button' type='submit' name='search' value='Search' /></td>
</tr><tr>
  <td class='lcaption", $Eclass['pap'], "'>Concerning paper(s)</td>
  <td class='lentry", $Eclass['pap'], "'><input class='textlite' type='text' size='40' name='pap' value=\"", htmlspecialchars(defval($_REQUEST["pap"], "")), "\" /></td>
</tr><tr>
  <td class='lcaption", $Eclass['acct'], "'>Concerning account(s)</td>
  <td class='lentry'><input class='textlite' type='text' size='40' name='acct' value=\"", htmlspecialchars(defval($_REQUEST["acct"], "")), "\" /></td>
</tr><tr>
  <td class='lcaption", $Eclass['n'], "'>Show</td>
  <td class='lentry", $Eclass['n'], "'><input class='textlite' type='text' size='3' name='n' value=\"", htmlspecialchars($_REQUEST["n"]), "\" /> &nbsp;records at a time</td>
</tr><tr>
  <td class='lcaption", $Eclass['date'], "'>Starting at</td>
  <td class='lentry", $Eclass['date'], "'><input class='textlite' type='text' size='40' name='date' value=\"", htmlspecialchars($_REQUEST["date"]), "\" /></td>
</tr></table></form>";

    if ($nrows > 0 || $page > 1) {
	$urls = array();
	$_REQUEST["offset"] = $offset;
	foreach (array("q", "pap", "acct", "n", "offset") as $x)
	    if ($_REQUEST[$x])
		$urls[] = "$x=" . urlencode($_REQUEST[$x]);
	$url = "ViewActionLog.php?" . join("&amp;", $urls);
	echo "<div class='smgap'></div><table class='center'><tr><td>";
	if ($page > 1)
	    echo "<a href='$url&amp;page=", ($page - 1), "'><strong><img src='${ConfSiteBase}images/prev.png' alt='&lt;-' /> Previous</strong></a> ";
	if ($page - 4 > 0)
	    echo "... ";
	for ($p = max($page - 4, 0); $p + 1 < $page; $p++)
	    echo "<a href='$url&amp;page=", ($p + 1), "'>", ($p + 1), "</a> ";
	echo "<strong class='thispage'>", $page, "</strong> ";
	for ($p = $page; $p * $count + $offset < $start + min(3*$count + 1, $nrows); $p++)
	    echo "<a href='$url&amp;page=", ($p + 1), "'>", ($p + 1), "</a> ";
	if ($nrows == $maxNrows)
	    echo "... ";
	if ($nrows > $count)
	    echo "<a href='$url&amp;page=", ($page + 1), "'><strong>Next <img src='${ConfSiteBase}images/next.png' alt='-&gt;' /> </strong></a>";
	if ($page > 1 || $nrows > $count) {
	    echo " &nbsp;|&nbsp; ";
	    echo "<a href='$url&amp;page=1'><strong>Latest</strong></a> &nbsp;<a href='$url&amp;page=earliest'><strong>Earliest</strong></a>";
	}
	echo "</td></tr></table><div class='smgap'></div>\n";
    }
}


$query = "select logId, unix_timestamp(time) as timestamp, "
    . " ipaddr, contactId, action, firstName, lastName, email, paperId "
    . " from ActionLog join ContactInfo using (contactId)";
if (count($wheres))
    $query .= " where " . join(" and ", $wheres);
$query .= " order by logId desc";
if (!$firstDate && $page !== false) {
    $maxNrows = 3 * $count + 1;
    $query .= " limit $start,$maxNrows";
}

$result = $Conf->qe($query);
$nrows = edb_nrows($result);
if ($firstDate || $page === false)
    $maxNrows = $nrows;

$k = 1;
$n = 0;
while (($row = edb_orow($result)) && ($n < $count || $page === false)) {
    if ($firstDate && $row->timestamp > $firstDate) {
	$start++;
	$nrows--;
	continue;
    } else if ($page === false && ($n % $count != 0 || $n + $count < $nrows)) {
	$n++;
	continue;
    } else if ($page === false) {
	$start = $n;
	$page = ($n / $count) + 1;
	$nrows -= $n;
	$maxNrows -= $n - 1;
	$n = 0;
    }
    
    $n++;
    if ($n == 1) {
	if ($start != 0 && !$firstDate)
	    $_REQUEST["date"] = $Conf->printableTimeShort($row->timestamp);
	else if ($firstDate) {
	    $offset = $start % $count;
	    $page = (int) ($start / $count) + ($offset ? 2 : 1);
	    $nrows = min(3 * $count + 1, $nrows);
	    $maxNrows = min(3 * $count + 1, $maxNrows);
	}
	searchbar();
	echo "<table class='altable'><tr class='al_headrow'>
  <th class='pl_id'>#</th>
  <th class='al_time'>Time</th>
  <th class='al_ip'>IP</th>
  <th class='pl_name'>Account</th>
  <th class='al_act'>Action</th>
</tr>\n";
    }
    
    $k = 1 - $k;
    echo "<tr class='k$k al'>";
    echo "<td class='pl_id'>", htmlspecialchars($row->logId), "</td>";
    echo "<td class='al_time'>", $Conf->printableTimeShort($row->timestamp), "</td>";
    echo "<td class='al_ip'>", htmlspecialchars($row->ipaddr), "</td>";
    echo "<td class='pl_name'>", contactHtml($row->firstName, $row->lastName, $row->email), "</td>";
    echo "<td class='al_act'>";
    
    $act = $row->action;
    if (preg_match('/^Review (\d+)/', $act, $m)) {
	echo "<a href=\"${ConfSiteBase}review.php?reviewId=$m[1]\">Review ",
	    $m[1], "</a>";
	$act = substr($act, strlen($m[0]));
    }
    if (preg_match('/^Comment (\d+)/', $act, $m)) {
	echo "<a href=\"${ConfSiteBase}comment.php?commentId=$m[1]\">Comment ",
	    $m[1], "</a>";
	$act = substr($act, strlen($m[0]));
    }
    if (preg_match('/ \(papers ([\d, ]+)\)?$/', $act, $m)) {
	echo htmlspecialchars(substr($act, 0, strlen($act) - strlen($m[0]))),
	    " (<a href=\"${ConfSiteBase}search.php?t=all&amp;q=",
	    preg_replace('/[\s,]+/', "+", $m[1]),
	    "\">papers</a> ",
	    preg_replace('/(\d+)/', "<a href=\"${ConfSiteBase}paper.php?paperId=\$1\">\$1</a>", $m[1]),
	    ")";
    } else 
	echo htmlspecialchars($act);

    if ($row->paperId)
	echo " (paper <a href=\"${ConfSiteBase}paper.php?paperId=", urlencode($row->paperId), "\">", htmlspecialchars($row->paperId), "</a>)";
    echo "</td>";
    echo "</tr>\n";
}

if ($n) {
    echo "<tr class='pl_footgap k$k'><td colspan='5'></td></tr>";
    echo "</table>\n";
} else {
    searchbar();
    echo "No records\n";
}

$Conf->footer();
