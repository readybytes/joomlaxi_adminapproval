<?php
defined('_JEXEC') or die('Restricted access');
?>

Hello Admin,<br />

A new user have been registered at your site. <br />

He/She needs your approval to activate His/her account.<br />

His/her details are:<br />

<?php foreach($vars['profile'] as $key => $val):?>
	<?php echo "$key : $val"; ?> <br />
<?php endforeach;?>

You can approve user by clicking on Approval Link: <?php echo $vars['link']?> <br />
(You are not required to login in website)<br />
<?php 
