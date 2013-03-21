<?php
defined('_JEXEC') or die('Restricted access');
?>

Hello Admin,

A new user have been registered at your site. 

He/She needs your approval to activate His/her account.

His/her details are:
<?php foreach($vars['profile'] as $key => $val):?>
	<?php echo "$key : $val"; ?> 
<?php endforeach;?>

You can approve user by clicking on Approval Link: <?php echo $vars['link']?>

(You are not required to login in website)
<?php 
