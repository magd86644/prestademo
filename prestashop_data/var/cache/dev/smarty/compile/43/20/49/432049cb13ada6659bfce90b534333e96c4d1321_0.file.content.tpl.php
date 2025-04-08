<?php
/* Smarty version 3.1.43, created on 2025-04-08 23:57:43
  from '/var/www/html/admin612qzcmt5/themes/default/template/content.tpl' */

/* @var Smarty_Internal_Template $_smarty_tpl */
if ($_smarty_tpl->_decodeProperties($_smarty_tpl, array (
  'version' => '3.1.43',
  'unifunc' => 'content_67f59bd77d3155_94647792',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '432049cb13ada6659bfce90b534333e96c4d1321' => 
    array (
      0 => '/var/www/html/admin612qzcmt5/themes/default/template/content.tpl',
      1 => 1647359402,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
),false)) {
function content_67f59bd77d3155_94647792 (Smarty_Internal_Template $_smarty_tpl) {
?><div id="ajax_confirmation" class="alert alert-success hide"></div>
<div id="ajaxBox" style="display:none"></div>

<div class="row">
	<div class="col-lg-12">
		<?php if ((isset($_smarty_tpl->tpl_vars['content']->value))) {?>
			<?php echo $_smarty_tpl->tpl_vars['content']->value;?>

		<?php }?>
	</div>
</div>
<?php }
}
