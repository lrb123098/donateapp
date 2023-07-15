<?php
 /**
 * @brief				Settings

 */

namespace IPS\donate\modules\admin\settings;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * settings
 */
class _settings extends \IPS\Dispatcher\Controller
{
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'donate_settings_manage' );
		parent::execute();
	}

	/**
	 * Manage
	 *
	 * @return	void
	 */
	protected function manage()
	{
		$tabs = array();
		
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'donate', 'settings', 'donate_settings_general_manage' ) )
		{
			$tabs['general'] = 'donate_settings_general';
		}
		
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'donate', 'settings', 'donate_settings_paypal_manage' ) )
		{
			$tabs['paypal'] = 'donate_settings_paypal';
		}
		
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'donate', 'settings', 'donate_settings_sourcebans_manage' ) )
		{
			$tabs['sourcebans'] = 'donate_settings_sourcebans';
		}
		
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'donate', 'settings', 'donate_settings_csgostore_manage' ) )
		{
			$tabs['csgostore'] = 'donate_settings_csgostore';
		}
		
		$activeTab = \IPS\Request::i()->tab ?: 'general';
		$tabMethod = '_' . $activeTab;
		$output = $this->{$tabMethod}();
		
		if ( \IPS\Request::i()->isAjax() && !isset( \IPS\Request::i()->ajaxValidate ) )
		{
			\IPS\Output::i()->output = $output;
		}
		else
		{		
			\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('menu__donate_settings_settings');
			\IPS\Output::i()->output 	= \IPS\Theme::i()->getTemplate( 'global', 'core' )->tabs( $tabs, $activeTab, $output, \IPS\Http\Url::internal( 'app=donate&module=settings&controller=settings' ) );
		}
	}
	
	/**
	 * General settings
	 *
	 * @return	string
	 */
	protected function _general()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'donate_settings_general_manage' );
		
		$form = new \IPS\Helpers\Form;
		$form->addHeader( 'donate_settings_general_settings' );
		$form->add( new \IPS\Helpers\Form\Number( 'donate_donation_goal', \IPS\Settings::i()->donate_donation_goal, FALSE, array( 'min' => 0, 'max' => 10000, 'decimals' => FALSE, 'step' => 1 ), NULL, '$' ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'donate_log_enabled', \IPS\Settings::i()->donate_log_enabled ) );
		$form->add( new \IPS\Helpers\Form\Editor( 'donate_provider_smallprint', \IPS\Settings::i()->donate_provider_smallprint, FALSE, array(
			'app' => 'core',
			'key' => 'Admin',
			'autoSaveKey' => 'donate-provider-smallprint',
			'ipsPlugins' => "ipsautolink,ipsautosave,ipsctrlenter,ipscode,ipscontextmenu,ipsemoticon,ipslink,ipsmentions,ipspage,ipspaste,ipsquote,ipsspoiler,ipssource,removeformat"
		) ) );
		
		if ( $values = $form->values() )
		{
			$form->saveAsSettings( $values );
			\IPS\Session::i()->log( 'acplogs__donate_settings_general' );
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=donate&module=settings&controller=settings&tab=general' ), 'saved' );
		}
		
		return $form;
	}
	
	/**
	 * PayPal settings
	 *
	 * @return	string
	 */
	protected function _paypal()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'donate_settings_paypal_manage' );
		
		$provider = \IPS\donate\Gateway::getProvider( 'paypal' );
		$form = new \IPS\Helpers\Form;
		$provider->settings( $form );
		
		if ( $values = $form->values() )
		{
			$values = $provider->formatSettingsValues( $values );
			$form->saveAsSettings( $values );
			\IPS\Session::i()->log( 'acplogs__donate_settings_paypal' );
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=donate&module=settings&controller=settings&tab=paypal' ), 'saved' );
		}
		
		return $form;
	}
	
	/**
	 * Sourcebans settings
	 *
	 * @return	string
	 */
	protected function _sourcebans()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'donate_settings_sourcebans_manage' );
		
		$form = new \IPS\Helpers\Form;
		$form->addHeader( 'donate_settings_sourcebans_settings' );
		$form->add( new \IPS\Helpers\Form\Text( 'donate_sourcebans_db_host', \IPS\Text\Encrypt::fromCipher( \IPS\Settings::i()->donate_sourcebans_db_host )->decrypt() ) );
		$form->add( new \IPS\Helpers\Form\Text( 'donate_sourcebans_db_user', \IPS\Text\Encrypt::fromCipher( \IPS\Settings::i()->donate_sourcebans_db_user )->decrypt() ) );
		$form->add( new \IPS\Helpers\Form\Password( 'donate_sourcebans_db_pass', \IPS\Text\Encrypt::fromCipher( \IPS\Settings::i()->donate_sourcebans_db_pass )->decrypt() ) );
		$form->add( new \IPS\Helpers\Form\Text( 'donate_sourcebans_db_database', \IPS\Text\Encrypt::fromCipher( \IPS\Settings::i()->donate_sourcebans_db_database )->decrypt() ) );
		$form->add( new \IPS\Helpers\Form\Text( 'donate_sourcebans_db_port', \IPS\Text\Encrypt::fromCipher( \IPS\Settings::i()->donate_sourcebans_db_port )->decrypt(), FALSE, array( 'placeholder' => '3306' ) ) );
		$form->add( new \IPS\Helpers\Form\TextArea( 'donate_sourcebans_groups', \IPS\Settings::i()->donate_sourcebans_groups, FALSE, array( 'rows' => 20 ) ) );
		
		if ( $values = $form->values() )
		{
			if ( $values['donate_sourcebans_db_host'] )
			{
				$values['donate_sourcebans_db_host'] = \IPS\Text\Encrypt::fromPlaintext( $values['donate_sourcebans_db_host'] )->cipher;
			}
			
			if ( $values['donate_sourcebans_db_user'] )
			{
				$values['donate_sourcebans_db_user'] = \IPS\Text\Encrypt::fromPlaintext( $values['donate_sourcebans_db_user'] )->cipher;
			}
			
			if ( $values['donate_sourcebans_db_pass'] )
			{
				$values['donate_sourcebans_db_pass'] = \IPS\Text\Encrypt::fromPlaintext( $values['donate_sourcebans_db_pass'] )->cipher;
			}
			
			if ( $values['donate_sourcebans_db_database'] )
			{
				$values['donate_sourcebans_db_database'] = \IPS\Text\Encrypt::fromPlaintext( $values['donate_sourcebans_db_database'] )->cipher;
			}
			
			if ( $values['donate_sourcebans_db_port'] )
			{
				$values['donate_sourcebans_db_port'] = \IPS\Text\Encrypt::fromPlaintext( $values['donate_sourcebans_db_port'] )->cipher;
			}
			
			$form->saveAsSettings( $values );
			\IPS\Session::i()->log( 'acplogs__donate_settings_sourcebans' );
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=donate&module=settings&controller=settings&tab=sourcebans' ), 'saved' );
		}
		
		return $form;
	}
	
	/**
	 * CS:GO Store settings
	 *
	 * @return	string
	 */
	protected function _csgostore()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'donate_settings_csgostore_manage' );
		
		$form = new \IPS\Helpers\Form;
		$form->addHeader( 'donate_settings_csgostore_settings' );
		$form->add( new \IPS\Helpers\Form\Text( 'donate_csgostore_db_host', \IPS\Text\Encrypt::fromCipher( \IPS\Settings::i()->donate_csgostore_db_host )->decrypt() ) );
		$form->add( new \IPS\Helpers\Form\Text( 'donate_csgostore_db_user', \IPS\Text\Encrypt::fromCipher( \IPS\Settings::i()->donate_csgostore_db_user )->decrypt() ) );
		$form->add( new \IPS\Helpers\Form\Password( 'donate_csgostore_db_pass', \IPS\Text\Encrypt::fromCipher( \IPS\Settings::i()->donate_csgostore_db_pass )->decrypt() ) );
		$form->add( new \IPS\Helpers\Form\Text( 'donate_csgostore_db_database', \IPS\Text\Encrypt::fromCipher( \IPS\Settings::i()->donate_csgostore_db_database )->decrypt() ) );
		$form->add( new \IPS\Helpers\Form\Text( 'donate_csgostore_db_port', \IPS\Text\Encrypt::fromCipher( \IPS\Settings::i()->donate_csgostore_db_port )->decrypt(), FALSE, array( 'placeholder' => '3306' ) ) );
		
		if ( $values = $form->values() )
		{
			if ( $values['donate_csgostore_db_host'] )
			{
				$values['donate_csgostore_db_host'] = \IPS\Text\Encrypt::fromPlaintext( $values['donate_csgostore_db_host'] )->cipher;
			}
			
			if ( $values['donate_csgostore_db_user'] )
			{
				$values['donate_csgostore_db_user'] = \IPS\Text\Encrypt::fromPlaintext( $values['donate_csgostore_db_user'] )->cipher;
			}
			
			if ( $values['donate_csgostore_db_pass'] )
			{
				$values['donate_csgostore_db_pass'] = \IPS\Text\Encrypt::fromPlaintext( $values['donate_csgostore_db_pass'] )->cipher;
			}
			
			if ( $values['donate_csgostore_db_database'] )
			{
				$values['donate_csgostore_db_database'] = \IPS\Text\Encrypt::fromPlaintext( $values['donate_csgostore_db_database'] )->cipher;
			}
			
			if ( $values['donate_csgostore_db_port'] )
			{
				$values['donate_csgostore_db_port'] = \IPS\Text\Encrypt::fromPlaintext( $values['donate_csgostore_db_port'] )->cipher;
			}
			
			$form->saveAsSettings( $values );
			\IPS\Session::i()->log( 'acplogs__donate_settings_csgostore' );
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=donate&module=settings&controller=settings&tab=csgostore' ), 'saved' );
		}
		
		return $form;
	}
}