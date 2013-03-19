<?php
defined('_JEXEC') or die('Restricted access');
?>

Hello Admin,\n\n

A new user have been registered at your site.\n 

He/She needs your approval to activate His/her account.\n\n

His/her details are:\n

<?php foreach($vars['profile'] as $key => $val):?>
	<?php echo "$key : $val \n"; ?>
<?php endforeach;?>

You can approve user by clicking on Approval Link: <?php echo $vars['_link']?>\n
(You are not required to login in website)
<?php 
