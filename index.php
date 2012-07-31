<?php
ini_set("display_errors",1);
error_reporting(E_ALL);
date_default_timezone_set("GMT");
session_start();

include("lib/AWS.php");
$aws = new AWS;
//$aws->debug = true;

if (isset($_POST['docreate'])) {
	$aws->create_distribution($_POST['bucket'],$_POST['comment']);
}

if(isset($_GET['action']))
{
	if($_GET['action'] == "delete")
	{
		$aws->disable_distribution($_GET['bucket'],$_GET['distribution_id'],$_GET['etag'],$_GET['caller_reference']);
	}
}
?>
<html>
	<head>
		<meta http-equiv="Content-type" content="text/html; charset=utf-8">
		<title></title>
		<link rel="stylesheet" href="assets/css/screen.css" type="text/css" media="screen" title="AWS Styles" charset="utf-8">
		<script src="assets/js/jquery-1.2.6.min.js" type="text/javascript" charset="utf-8"></script>
		<script type="text/javascript" charset="utf-8">
		$(document).ready(function() {
			$(".create-btn").click(function() {
				if($(this).html() == "Create a Distribution")
				{
					$(this).html("Cancel");
				}
				else
				{
					$(this).html("Create a Distribution");
				}
				
				$("#create-dist").slideToggle("fast");
			});
			
			<?php foreach ($_SESSION['pending_deletes'] as $key => $value) : ?>
			function deleter_<?=$key?>()
			{
			      $.ajax({
			                    method: 'get',
			                    url : 'ajax.php?distribution_id=<?=$value['distribution_id']?>',
			                    dataType : 'text',
			                    success: function (text) {
						if(text)
						{
							$('#statuses .status_<?=$key?>').remove();
							$('#statuses').append('<div class="status_<?=$key?>">'+text+'</span>'); 
						}
					    }
			                 });

			}
			
			deleter_<?=$key?>();
			var myInterval_<?=$key?> = setInterval(deleter_<?=$key?>, 10000);
			<?php endforeach; ?>
		});
		</script>
	</head>
	
	<body>
		<div id="wrap">
			<div id="header">
				<h4>AWS 100 <span>Distribution Manager</span></h4>
				
				<ul>
					<li><a href="index.php">Refresh</a></li>
					<li><a href="#" class="create-btn">Create a Distribution</a></li>
				</ul>
				<div class="clear-left"></div>
				
				<div id="create-dist">
					<form method="post">
						<strong>Create Distribution</strong><br />
						<input title="Bucket Name" type="text" name="bucket" value="Bucket Name" onclick="if(this.value=='Bucket Name') { this.value=''; };" onblur="if(this.value=='') { this.value='Bucket Name'; };" id="distribution" class="input" />
						<input title="Comment (Optional)" type="text" name="comment" value="Comment" onclick="if(this.value=='Comment') { this.value=''; };" onblur="if(this.value=='') { this.value='Comment'; };" id="distribution" class="input" />
						<input type="submit" name="docreate" value="Create Distribution" id="docreate" class="submit" />
					</form>
				</div>
				
				<div id="statuses">
					<?php
					if (isset($_GET['distribution_deleted'])): 
					?>
					<br /><strong>Distribution Deleted:</strong> <?=$_GET['distribution_deleted']?>
					<?php endif ?>
				</div>
			</div>
		
			<div id="main-content">
				<?php 
				$distributions = $aws->list_distributions();
				if(count($distributions) > 0) :
				$i=1;
				foreach($distributions as $row) : 
				?>
				<div class="distribution">
					<ul class="line-items">
						<li><strong>Domain:</strong> <?=$row->DomainName?></li>
						<li><strong>Bucket:</strong> <?=$row->Origin?></li>
						<li><strong>Status:</strong> <?=$row->Status?></li>
						<?php if ($row->CNAME != ""): ?>
							<li><strong>CNAME:</strong> <?=$row->CNAME?></li>
						<?php endif ?>
					</ul>
					
					<div class="delete">
						<?php
						$headers = $aws->get_config($row->Id);
						#print_r($headers);
						?>
						<a title="Delete Distribution?" href="index.php?action=delete&amp;bucket=<?=$row->Origin?>&amp;distribution_id=<?=$row->Id?>&amp;etag=<?=$aws->etag?>&amp;caller_reference=<?=$headers->CallerReference?>" onclick="return confirm('Are you sure you want to delete this distribution?');">
							&times;
						</a>
					</div>
					<div class="clear-hack"></div>
				</div>
				<?php 
				endforeach; 
				else :
				?>
					<p align="center">You have not created any distributions yet.</p>
				<?php
				endif;
				?>
			</div>
			
			<div id="footer">
				Created by <a href="http://www.tommycutter.com/">Tommy Cutter</a>
			</div>
		</div>
	</body>
</html>