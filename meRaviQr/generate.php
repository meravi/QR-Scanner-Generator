<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<title>Qr Gen</title>
	<link rel="stylesheet" href="">
</head>
<body>
	<?php 
 	include "qrlib.php";
 	include "config.php";
 	if(isset($_POST['create']))
 	{
 		$qc =  $_POST['qrContent'];
 		$qrUname = $_POST['qrUname'];
 		if($qc=="" && $qrUname=="")
 		{
 			echo "<script>alert('Please Enter Your Name And Msg For QR Code');</script>";
 		}
 		elseif($qc=="")
 		{
 			echo "<script>alert('Please Enter QR Code Msg');</script>";
 		}
 		elseif($qrUname=="")
 		{
 			echo "<script>alert('Please Enter Your Name');</script>";
 		}
 		else
 		{
 		$dev = " ...Develop By Ravi Khadka";
 		$final = $qc.$dev;
 			QRcode::png($final,"new.png","H","3","3");
 		$insQr = $meravi->insertQr($qrUname,$final);
 		if($insQr==true)
 		{
 			echo "<script>alert('Thank You $qrUname. Success Create Your QR Code');</script>";
 			header("location:generate.php?success");
 		}
 		else
 		{
 			echo "<script>alert('cant create QR Code');</script>";
 		}
 	}
 }
	?>
	<?php 
	if(isset($_GET['success']))
	{
  ?>
  	<img src="new.png" alt="">
  	<a href="generate.php">Go Back To Gnerate Again</a>
  <?php
}
else
{
	?>
		<form method="post">
			<label for="UserName">Your Name:</label>
		<input type="text" name="qrUname" value="<?php if(isset($_POST['create'])){ echo $_POST['qrUname']; } ?>">
		<br><br>
		<label for="QRCon">QR Code Msg</label>
		<input type="text" name="qrContent" value="<?php if(isset($_POST['create'])){ echo $_POST['qrContent']; } ?>">
		<br><br>
		<input type="submit" name="create" value="Generate Now">
	</form>
	<?php 
}
	 ?>
	


</body>
</html>