<?php
ini_set("display_errors",1);
error_reporting(E_ALL);
date_default_timezone_set("GMT");
session_start();

include("lib/AWS.php");
$aws = new AWS;

$response = $aws->get_info($_GET['distribution_id']);

if($response->Status != "Deployed" && $response->Status != "")
{
	echo "<br /><strong>Pending Distribution Delete:</strong> " . $response->DomainName;
	flush();
}

if($response->Status == "Deployed")
{
	echo "<br /><strong>Deleting Distribution:</strong> " . $response->DomainName ."<br />";
	flush();
	$aws->delete_distribution($response->Id,$aws->etag);
	
	foreach($_SESSION['pending_deletes'] as $key => $value)
	{
		if($value['distribution_id'] == $response->Id)
		{
			unset($_SESSION[$key]);
		}
	}
	
	echo "<script>location.href='index.php?distribution_deleted=".$response->Id."'</script>";
}
?>