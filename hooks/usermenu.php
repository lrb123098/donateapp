//<?php

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	exit;
}

class donate_hook_usermenu extends _HOOK_CLASS_
{

/* !Hook Data - DO NOT REMOVE */
public static function hookData() {
 return array_merge_recursive( array (
  'userBar' => 
  array (
    0 => 
    array (
      'selector' => '#elAccountSettingsLink',
      'type' => 'add_after',
      'content' => '<li class="ipsMenu_item" data-menuitem="myDonations">
  <a href="{url=\'app=donate&module=donate&controller=mydonations\' seoTemplate=\'donate_mydonations\'}">{lang="donate_mydonations"}</a>
</li>',
    ),
  ),
  'mobileNavigation' => 
  array (
    0 => 
    array (
      'selector' => '#elAccountSettingsLinkMobile',
      'type' => 'add_after',
      'content' => '<li>
  <a href="{url=\'app=donate&module=donate&controller=mydonations\' seoTemplate=\'donate_mydonations\'}">{lang="donate_mydonations"}</a>
</li>',
    ),
  ),
), parent::hookData() );
}
/* End Hook Data */


}
