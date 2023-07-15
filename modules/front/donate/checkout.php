<?php
/**
 * @brief				[Front] Checkout Controller

 */

namespace IPS\donate\modules\front\donate;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Checkout
 */
class _checkout extends \IPS\Dispatcher\Controller
{
	/**
	 * @brief	Base url
	 */
	protected $_baseUrl;
	
	/**
	 * @brief	Redirect urls
	 */
	protected $_redirectUrls = array();
	
	/**
	 * @brief	Wizard step key
	 */
	protected $_wizardStepKey;
	
	/**
	 * @brief	Wizard data key
	 */
	protected $_wizardDataKey;
	
	/**
	 * @brief	Steps
	 */
	protected $_steps;
	
	/**
	 * @brief	Step keys
	 */
	protected $_stepKeys;
	
	/**
	 * @brief	Active step
	 */
	protected $_activeStep;
	
	/**
	 * @brief	Data
	 */
	protected $_data;
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{		
		if ( !\IPS\Member::loggedIn()->member_id || \IPS\Member::loggedIn()->isBanned() )
		{
			\IPS\Output::i()->error( 'no_module_permission_guest', '2O100/1', 403 );
		}
		
		$this->_baseUrl = \IPS\Http\Url::internal( 'app=donate&module=donate&controller=checkout', 'front', 'donate_checkout' );
		$this->_redirectUrls = array(
			'cancel' => \IPS\Http\Url::internal( 'app=donate&module=donate&controller=checkout&do=cancel', 'front', 'donate_checkout_cancel' ),
			'confirm' => \IPS\Http\Url::internal( 'app=donate&module=donate&controller=checkout&do=confirm', 'front', 'donate_checkout_confirm' )->csrf(),
			'complete' => \IPS\Http\Url::internal( 'app=donate&module=donate&controller=checkout&do=complete', 'front', 'donate_checkout_complete' )->csrf(),
			'steamlogin' => \IPS\Http\Url::internal( 'app=donate&module=donate&controller=checkout&do=steamLogin', 'front', 'donate_checkout' )->csrf()
		);
		
		$this->_wizardStepKey = 'wizard-' . md5( $this->_baseUrl ) . '-step';
		$this->_wizardDataKey = 'wizard-' . md5( $this->_baseUrl ) . '-data';
		
		$this->_steps = array(
			'choose' => array( $this, '_choose' ),
			'options' => array( $this, '_options' ),
			'pay' => array( $this, '_pay' )
		);
		
		foreach ( $this->_steps as $step => $v )
		{
			$this->_redirectUrls[$step] = \IPS\Http\Url::internal( 'app=donate&module=donate&controller=checkout&_step=' . $step, 'front', 'donate_checkout_' . $step );
		}
		
		$this->_stepKeys = array_keys( $this->_steps );
		$this->_activeStep = isset( $_SESSION[$this->_wizardStepKey] ) ? $_SESSION[$this->_wizardStepKey] : $this->_stepKeys[0];
		
		if ( isset( $_SESSION[$this->_wizardDataKey] ) )
		{
			$this->_data =& $_SESSION[$this->_wizardDataKey];
		}
		
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'front_checkout.js', 'donate' ) );
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'checkout.css' ) );
		
		if ( \IPS\Theme::i()->settings['responsive'] )
		{
			\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'checkout_responsive.css' ) );
		}
		
		\IPS\Session::i()->setLocation( $this->_baseUrl, array(), 'loc_donate_checkout' );
		\IPS\Output::i()->sidebar['enabled'] = FALSE;
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'donate_checkout' );
		\IPS\Output::i()->breadcrumb = array();
		\IPS\Output::i()->breadcrumb[] = array( $this->_baseUrl, \IPS\Member::loggedIn()->language()->addToStack( 'donate_checkout' ) );
		
		parent::execute();
	}
	
	/**
	 * Manage
	 *
	 * @return	void
	 */
	protected function manage()
	{
		if ( isset( \IPS\Request::i()->_moveToStep ) )
		{
			unset( \IPS\Request::i()->_moveToStep );
		}
		
		if ( isset( \IPS\Request::i()->_step ) )
		{
			$this->_redirectToActiveStep();
		}
		
		$wizard = new \IPS\Helpers\Wizard( $this->_steps, $this->_baseUrl, TRUE );
		$wizard->template = array( \IPS\Theme::i()->getTemplate( 'checkout' ), 'wizard' );
		
		\IPS\Output::i()->output = (string) $wizard;
	}
	
	/**
	 * Choose
	 *
	 * @param	array	$data	Wizard data
	 * @return	string|array
	 */
	public function _choose( $data )
	{
		$item = NULL;
		
		if ( isset( \IPS\Request::i()->item_id ) )
		{
			\IPS\Session::i()->csrfCheck();
			$data['item_id'] = \IPS\Request::i()->item_id;
			return $data;
		}
		
		$form = new \IPS\Helpers\Form( 'donate_checkout_choose', NULL, $this->_redirectUrls['choose'] );
		$form->class = 'ipsForm_vertical';
		$form->addButton( 'continue', 'submit', NULL, 'ipsButton ipsButton_primary ipsButton_veryLarge ipsPos_right' );
		
		$form->add( new \IPS\Helpers\Form\Custom( 'donate_checkout_items', NULL, FALSE, array(
			'getHtml' => function( $element )
			{
				$items = array_filter( \IPS\donate\Item::items(), function( $item )
				{
					return $item->enabled;
				} );
				
				return \IPS\Theme::i()->getTemplate( 'checkout' )->itemCards( $items );
			},
			'validate' => function( $element )
			{
				if ( empty( $element->value ) )
				{
					throw new \DomainException('donate_checkout_error_items');
				}
			}
		) ) );
		
		if ( $values = $form->values() )
		{
			$itemId = (int) $values['donate_checkout_items'];
			
			if ( $itemId > 0)
			{
				$data['item_id'] = $itemId;
				return $data;
			}
		}
		
		return $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'checkout' ), 'form' ) );
	}
	
	/**
	 * Options
	 *
	 * @param	array	$data	Wizard data
	 * @return	string|array
	 */
	public function _options( $data )
	{
		$item = $this->_loadItem( $data['item_id'] );
		
		$form = new \IPS\Helpers\Form( 'donate_checkout_options', NULL, $this->_redirectUrls['options'] );
		$form->class = 'ipsForm_vertical';
		$form->addButton( 'cancel', 'link', $this->_redirectUrls['cancel'], 'ipsButton ipsButton_light ipsButton_veryLarge' );
		$form->addButton( 'continue', 'submit', NULL, 'ipsButton ipsButton_primary ipsButton_veryLarge ipsPos_right' );
		$form->addSidebar( \IPS\Theme::i()->getTemplate( 'checkout' )->itemCard( $item, 'vertical' ) );
		
		$form->add( new \IPS\Helpers\Form\Custom( 'donate_checkout_payment_options', array( 'item' => $item, 'default' => isset( $data['payment_option'] ) ? $data['payment_option'] : NULL ), FALSE, array(
			'getHtml' => function( $element )
			{
				return \IPS\Theme::i()->getTemplate( 'checkout' )->paymentOptions( $element->defaultValue['item'], $element->defaultValue['default'] );
			},
			'formatValue' => function( $element )
			{
				$value = NULL;
				
				if ( !empty( $element->value ) && \is_string( $element->value ) )
				{
					$paymentOption = explode( ',', $element->value );
					$value = new \IPS\donate\Item\PaymentOption( $paymentOption[0], $paymentOption[1] );
				}
				
				return $value;
			},
			'validate' => function( $element )
			{
				if ( empty( $element->value ) )
				{
					throw new \DomainException('donate_checkout_error_payment_options');
				}
			}
		) ) );
		
		if ( $item->hasPaymentOption( NULL, \IPS\donate\Order::PRICE_TYPE_VARIABLE ) )
		{
			$amount = isset( $data['cost'] ) ? $data['cost'] : $item->price->format;
			$form->add( new \IPS\Helpers\Form\Number( 'donate_checkout_amount', $amount, FALSE, array( 'min' => (float) $item->price->format, 'max' => 10000, 'decimals' => TRUE ), NULL, '<span class="cCheckout_numberField">' . $item->price->currencySymbol, '</span>' ) );
		}
		
		$form->add( new \IPS\Helpers\Form\Custom( 'donate_checkout_gift', array( 'member' => isset( $data['giftee'] ) && $data['giftee'] ? $data['giftee'] : NULL ), FALSE, array(
			'getHtml' => function( $element )
			{
				$toggleForm = new \IPS\Helpers\Form\YesNo( $element->name . '[toggle]', $element->defaultValue['member'] ? TRUE : FALSE );
				$memberForm = new \IPS\Helpers\Form\Member( $element->name . '[member]', $element->defaultValue['member'] ?: NULL );
				return \IPS\Theme::i()->getTemplate( 'checkout' )->gift( $toggleForm, $memberForm );
			},
			'formatValue' => function( $element )
			{
				$value = FALSE;
				
				if ( \is_array( $element->value ) && isset( $element->value['toggle_checkbox'] ) && $element->value['toggle_checkbox'] )
				{
					if ( isset( $element->value['member'] ) && !empty( $element->value['member'] ) )
					{
						try
						{
							$value = \IPS\Member::load( $element->value['member'], 'name' );
						}
						catch ( \Exception $e )
						{
							$value = NULL;
						}
					}
					else
					{
						$value = NULL;
					}
				}
				
				return $value;
			},
			'validate' => function( $element )
			{
				if ( $element->value === NULL )
				{
					throw new \DomainException('donate_checkout_error_gift');
				}
				else if ( $element->value instanceof \IPS\Member )
				{
					if ( $element->value->member_id === \IPS\Member::loggedIn()->member_id )
					{
						throw new \DomainException('donate_checkout_error_gift_self');
					}
				}
			}
		) ) );
		
		if ( $steamLoginMethod = \IPS\Login\Handler::findMethod( 'IPS\steamlogin\sources\Login\Steam' ) )
		{
			$steamProfileData = NULL;
			$steamCustomProfileData = NULL;
			
			if ( isset( \IPS\Member::loggedIn()->steamid ) )
			{
				try
				{
					$steamId = new \Steam\SteamID( $steamLoginMethod->userId( \IPS\Member::loggedIn() ) );
					$steamProfileData = array(
						'personaname' => $steamLoginMethod->userProfileName( \IPS\Member::loggedIn() ),
						'steamid64' => $steamId->ConvertToUInt64(),
						'steamid2' => $steamId->RenderSteam2(),
						'avatarfull' => $steamLoginMethod->userProfilePhoto( \IPS\Member::loggedIn() )
					);
				}
				catch ( \Exception $e )
				{
				}
			}
			
			if ( isset( $data['steam_id'] ) && $data['steam_id'] && ( !$steamProfileData || ( $steamProfileData && $data['steam_id'] !== $steamProfileData['steamid64']  ) ) )
			{
				$steamCustomProfileData = $this->_requestSteamProfileData( $data['steam_id'], $steamLoginMethod );
			}
			
			$form->add( new \IPS\Helpers\Form\Custom( 'donate_checkout_steam_account', array( 'method' => $steamLoginMethod, 'steamProfileData' => $steamProfileData, 'steamCustomProfileData' => $steamCustomProfileData ), FALSE, array(
				'getHtml' => function( $element )
				{
					return \IPS\Theme::i()->getTemplate( 'checkout' )->steamAccount( $element->defaultValue['method'], $element->defaultValue['steamProfileData'], $element->defaultValue['steamCustomProfileData'] );
				},
				'validate' => function( $element )
				{
					if ( empty( $element->value ) )
					{
						throw new \DomainException('donate_checkout_error_steam_account');
					}
				}
			) ) );
		}
		
		if ( $values = $form->values() )
		{
			$paymentOption = $values['donate_checkout_payment_options'];
			$cost = new \IPS\donate\Money( (float) $values['donate_checkout_amount'] );
			$giftee = $values['donate_checkout_gift'];
			$steamId = NULL;
			
			try
			{
				if ( $giftee === FALSE && !empty( $values['donate_checkout_steam_account'] ) )
				{
					$steamId = new \Steam\SteamID( (string) $values['donate_checkout_steam_account'] );
					
					if ( $steamId->ConvertToUInt64() !== \IPS\Member::loggedIn()->steamid )
					{
						$select = \IPS\Db::i()->select( '*', 'core_members', array( 'steamid=?', $steamId->ConvertToUInt64() ) )->first();
						$memberSearch = \IPS\Member::constructFromData( $select );
						
						if ( $memberSearch->member_id )
						{
							$giftee = $memberSearch;
						}
					}
				}
			}
			catch ( \Exception $e )
			{
			}
			
			if ( $paymentOption && !$cost->isLessThan( $item->price ) )
			{
				if ( $paymentOption->priceType === \IPS\donate\Order::PRICE_TYPE_FIXED )
				{
					$cost = $item->price;
				}
				
				$data['payment_option'] = (string) $paymentOption;
				$data['cost'] = $cost->format;
				$data['giftee'] = $giftee ? $giftee->name : NULL;
				$data['steam_id'] = $steamId ? $steamId->ConvertToUInt64() : NULL;
				return $data;
			}
		}
		
		return $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'checkout' ), 'form' ) );
	}
	
	/**
	 * Pay
	 *
	 * @param	array	$data	Wizard data
	 * @return	string|array
	 */
	public function _pay( $data )
	{
		if ( isset( $data['order_id'] ) )
		{
			try
			{
				$order = \IPS\donate\Order::load( (int) $data['order_id'] );
				
				if ( isset( $data['gateway_redirect'] ) && $order->status === \IPS\donate\Order::STATUS_PENDING && $order->member->member_id === \IPS\Member::loggedIn()->member_id )
				{
					\IPS\Output::i()->redirect( \IPS\Http\Url::external( $data['gateway_redirect'] ) );
				}
				else
				{
					throw new \Exception;
				}
			}
			catch ( \Exception $e )
			{
				$this->_clearSession();
				\IPS\Output::i()->error( 'generic_error', '3O100/2' );
			}
		}
		
		$item = $this->_loadItem( (int) $data['item_id'] );
		$paymentOption = explode( ',', $data['payment_option'] );
		$paymentOption = new \IPS\donate\Item\PaymentOption( $paymentOption[0], $paymentOption[1] );
		$cost = new \IPS\donate\Money( (float) $data['cost'] );
		$giftee = NULL;
		$steamId = NULL;
		
		try
		{
			if ( isset( $data['giftee'] ) )
			{
				$giftee = \IPS\Member::load( (string) $data['giftee'], 'name' );
			}
			
			if ( isset( $data['steam_id'] ) )
			{
				$steamId = new \Steam\SteamID( (string) $data['steam_id'] );
			}
		}
		catch ( \Exception $e )
		{
		}
		
		$details = array();
		
		if ( $item->active_length )
		{
			$langKey = 'donate_checkout_perks_review_' . $paymentOption->paymentType;
			$sprintf = '';
			
			switch ( $paymentOption->paymentType )
			{
				case \IPS\donate\Order::PAYMENT_TYPE_SUBSCRIPTION:
				{
					$format = \IPS\donate\Application::secondsFormat( \IPS\donate\Order::calcActiveLengthByCost( $item, $cost ), TRUE );
					$sprintf = $format['number'] <= 1 ? $format['word'] : implode( ' ', $format );
					break;
				}
				case \IPS\donate\Order::PAYMENT_TYPE_ONETIME:
				{
					$sprintf = \IPS\donate\Application::secondsFormat( \IPS\donate\Order::calcActiveLengthByCost( $item, $cost ) );
					break;
				}
			}
			
			$details[] = array(
				'content' => \IPS\Member::loggedIn()->language()->addToStack( $langKey, TRUE, array( 'sprintf' => $sprintf ) )
			);
		}
		
		if ( $perk = $item->getPerk( 'store_credits' ) )
		{
			$details[] = array(
				'content' => \IPS\Member::loggedIn()->language()->addToStack( 'donate_checkout_perks_review_credits', TRUE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->formatNumber( $perk->calculateCredits( $cost ) ) ) ) )
			);
		}
		
		if ( $giftee )
		{
			$details[] = array(
				'icon' => 'gift',
				'title' => 'donate_checkout_gift_review',
				'content' => \IPS\Theme::i()->getTemplate( 'global', 'core' )->userPhoto( $giftee, 'tiny' ) . ' ' . $giftee->link( NULL, TRUE )
			);
		}
		
		if ( $steamId )
		{
			$details[] = array(
				'icon' => 'steam',
				'title' => 'donate_checkout_steam_account_review',
				'content' => $steamId->RenderSteam2()
			);
		}
		
		$form = new \IPS\Helpers\Form( 'donate_checkout_pay', NULL, $this->_redirectUrls['pay'] );
		$form->class = 'ipsForm_vertical';
		$form->addButton( 'cancel', 'link', $this->_redirectUrls['cancel'], 'ipsButton ipsButton_light ipsButton_veryLarge' );
		$form->addButton( 'donate_back', 'link', $this->_redirectUrls['options'], 'ipsButton ipsButton_light ipsButton_veryLarge', array( 'data-action' => 'wizardLink' ) );
		$form->addButton( 'donate_pay', 'submit', NULL, 'ipsButton ipsButton_primary ipsButton_veryLarge ipsPos_right' );
		$form->addSidebar( \IPS\Theme::i()->getTemplate( 'checkout' )->reviewCard( $item, $paymentOption, $cost, $details ) );
		
		$form->add( new \IPS\Helpers\Form\Custom( 'donate_checkout_gateway_providers', array( 'providers' => \IPS\donate\Gateway::providers() ), FALSE, array(
			'getHtml' => function( $element )
			{
				return \IPS\Theme::i()->getTemplate( 'checkout' )->gatewayProviders( $element->defaultValue['providers'] );
			},
			'formatValue' => function( $element )
			{
				$value = NULL;
				
				if ( !empty( $element->value ) && \is_string( $element->value ) )
				{
					$value = \IPS\donate\Gateway::getProvider( $element->value );
				}
				
				return $value;
			},
			'validate' => function( $element )
			{
				if ( empty( $element->value ) )
				{
					throw new \DomainException('donate_checkout_error_gateway_providers');
				}
			}
		) ) );
		
		if ( $smallPrint = \IPS\Settings::i()->donate_provider_smallprint )
		{
			$form->addHtml( \IPS\Theme::i()->getTemplate( 'checkout' )->providerSmallPrint( $smallPrint ) );
		}
		
		if ( $values = $form->values() )
		{
			$provider = $values['donate_checkout_gateway_providers'];
			$extraData = NULL;
			
			if ( !$giftee && $steamId && ( !\IPS\Member::loggedIn()->steamid || \IPS\Member::loggedIn()->steamid !== $steamId->ConvertToUInt64() ) )
			{
				$extraData = array(
					'steam_id' => $steamId->ConvertToUInt64()
				);
			}
			else
			{
				$steamId = NULL;
			}
			
			try
			{
				if ( !$giftee && $steamId )
				{
					$giftee = \IPS\Member::load( 0 );
				}
				
				$response = $this->_createOrder( $item, $paymentOption, $cost, $provider, $giftee, $extraData );
				$this->_data['order_id'] = $response->order->id;
				
				if ( $response->redirectUrl )
				{
					$this->_data['gateway_redirect'] = (string) $response->redirectUrl;
					\IPS\Output::i()->redirect( $response->redirectUrl );
				}
			}
			catch ( \Exception $e )
			{
				\IPS\Output::i()->error( 'generic_error', '3O100/3' );
			}
		}
		
		return $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'checkout' ), 'form' ) );
	}
	
	/**
	 * Cancel
	 *
	 * @return	void
	 */
	public function cancel()
	{
		try
		{
			if ( isset( $this->_data['order_id'] ) )
			{
				$order = \IPS\donate\Order::load( (int) $this->_data['order_id'] );
				$provider = \IPS\donate\Gateway::getProvider( $order->provider );
				$provider->cancelOrder( $order );
			}
		}
		catch ( \Exception $e )
		{
		}
		
		$this->_clearSession();
		\IPS\Output::i()->redirect( $this->_baseUrl );
	}
	
	/**
	 * Confirm
	 *
	 * @return	void
	 */
	public function confirm()
	{
		\IPS\Session::i()->csrfCheck();
		
		if ( !isset( $this->_data['order_id'] ) )
		{
			$this->_clearSession();
			\IPS\Output::i()->error( 'generic_error', '3O100/4' );
		}
		
		try
		{
			$order = \IPS\donate\Order::load( (int) $this->_data['order_id'] );
			$provider = \IPS\donate\Gateway::getProvider( $order->provider );
			$provider->confirmOrder( $order );
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global' )->delayedRedirect( $this->_redirectUrls['complete'], 5 );
		}
		catch ( \Exception $e )
		{
			\IPS\Output::i()->error( 'generic_error', '3O100/5' );
		}
	}
	
	/**
	 * Complete
	 *
	 * @return	void
	 */
	public function complete()
	{
		\IPS\Session::i()->csrfCheck();
		
		if ( !isset( $this->_data['order_id'] ) )
		{
			$this->_clearSession();
			\IPS\Output::i()->error( 'generic_error', '3O100/6' );
		}
		
		try
		{
			$orderId = (int) $this->_data['order_id'];
			$this->_clearSession();
			
			$order = \IPS\donate\Order::load( $orderId );
			$detail = NULL;
			
			if ( $order->giftee )
			{
				$detail = array(
					'icon' => 'gift',
					'title' => 'donate_checkout_gift_review',
					'content' => \IPS\Theme::i()->getTemplate( 'global', 'core' )->userPhoto( $order->giftee, 'tiny' ) . ' ' . $order->giftee->link( NULL, TRUE )
				);
			}
			elseif ( ( $order->extraData && isset( $order->extraData['steam_id'] ) ) || $order->member->steamid )
			{
				$steamId = NULL;
				
				if ( $order->extraData && isset( $order->extraData['steam_id'] ) )
				{
					$steamId = $order->extraData['steam_id'];
				}
				elseif ( $order->member->steamid )
				{
					$steamId = $order->member->steamid;
				}
				
				try
				{
					$steamId = new \Steam\SteamID( $steamId );
				}
				catch ( \Exception $e )
				{
				}
				
				if ( $steamId )
				{
					$detail = array(
						'icon' => 'steam',
						'title' => 'donate_checkout_steam_account_review',
						'content' => $steamId->RenderSteam2()
					);
				}
			}
			
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'checkout' )->complete( $order, $detail );
		}
		catch ( \Exception $e )
		{
			\IPS\Output::i()->error( 'generic_error', '3O100/7' );
		}
	}
	
	/**
	 * Steam login
	 *
	 * @return	void
	 */
	public function steamLogin()
	{
		\IPS\Session::i()->csrfCheck();
		
		if ( $method = \IPS\Login\Handler::findMethod( 'IPS\steamlogin\sources\Login\Steam' ) )
		{
			if ( !$method->canProcess( \IPS\Member::loggedIn() ) )
			{
				try
				{
					$login = new \IPS\Login( $this->_redirectUrls['steamlogin'], \IPS\Login::LOGIN_UCP );
					$login->reauthenticateAs = \IPS\Member::loggedIn();
					
					if ( $success = $login->authenticate( $method ) )
					{
						if ( $success->member->member_id === \IPS\Member::loggedIn()->member_id )
						{
							$method->completeLink( \IPS\Member::loggedIn(), NULL );
						}
					}
				}
				catch ( \Exception $e )
				{
					if ( $e->getCode() === \IPS\Login\Exception::MERGE_SOCIAL_ACCOUNT )
					{
						if ( $e->member->member_id === \IPS\Member::loggedIn()->member_id )
						{
							$method->completeLink( \IPS\Member::loggedIn(), NULL );
						}
					}
				}
			}
		}
		
		\IPS\Output::i()->redirect( $this->_baseUrl );
	}
	
	/**
	 * Steam profile data
	 *
	 * @return	void
	 */
	public function steamProfileData()
	{
		\IPS\Session::i()->csrfCheck();
		\IPS\Request::i()->floodCheck();
		$output = NULL;
		
		if ( \IPS\Request::i()->isAjax() && !isset( \IPS\Request::i()->ajaxValidate ) && isset( \IPS\Request::i()->steamId ) )
		{
			if ( $method = \IPS\Login\Handler::findMethod( 'IPS\steamlogin\sources\Login\Steam' ) )
			{
				try
				{
					$steamId = \IPS\Request::i()->steamId;
					
					if ( !\is_numeric( $steamId ) )
					{
						if ( preg_match( '/^https?:\/\/steamcommunity.com\/profiles\/(.+?)(?:\/|$)/', \urldecode( $steamId ), $matches ) === 1 )
						{
							$steamId = $matches[1];
						}
						elseif ( preg_match( '/^https?:\/\/steamcommunity.com\/id\/([\w-]+)(?:\/|$)/', \urldecode( $steamId ), $matches ) === 1 )
						{
							$vanityUrl = \urlencode( \mb_strtolower( $matches[1] ) );
							$url = \IPS\Http\Url::external( 'https://api.steampowered.com/ISteamUser/ResolveVanityURL/v1/?key=' . $method->settings['api_key'] . '&vanityurl=' . $vanityUrl );
							
							if ( $response = $url->request()->get()->decodeJson() )
							{
								if ( !isset( $response['response']['steamid'] ) )
								{
									throw new \UnexpectedValueException;
								}
								
								$steamId = $response['response']['steamid'];
							}
						}
					}
					
					$output = $this->_requestSteamProfileData( $steamId, $method );
				}
				catch ( \Exception $e )
				{
				}
			}
		}
		
		\IPS\Output::i()->json( $output );
	}
	
	/**
	 * Request steam profile data
	 *
	 * @param	string	$steamId	Steam id
	 * @param	\IPS\Login\Handler	$method	Method
	 * @return	array
	 */
	protected function _requestSteamProfileData( $steamId, $method = NULL )
	{
		$output = NULL;
		$method = $method ?: \IPS\Login\Handler::findMethod( 'IPS\steamlogin\sources\Login\Steam' );
		
		if ( $method )
		{
			try
			{
				$steamId = new \Steam\SteamID( $steamId );
				$url = \IPS\Http\Url::external( 'https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?key=' . $method->settings['api_key'] . '&steamids=' . $steamId->ConvertToUInt64() );
				
				if ( $response = $url->request()->get()->decodeJson() )
				{
					$profileData = $response['response']['players'][0];
					$output = array(
						'personaname' => $profileData['personaname'],
						'steamid64' => $profileData['steamid'],
						'steamid2' => $steamId->RenderSteam2(),
						'avatarfull' => $profileData['avatarfull']
					);
				}
			}
			catch ( \Exception $e )
			{
			}
		}
		
		return $output;
	}
	
	/**
	 * Load item
	 *
	 * @param	integer	$itemId	Item id
	 * @return	\IPS\donate\Item
	 */
	protected function _loadItem( $itemId )
	{
		if ( !$itemId || $itemId <= 0 || $itemId > PHP_INT_MAX )
		{
			$this->_clearSession();
			\IPS\Output::i()->error( 'generic_error', '3O100/8', 400 );
		}
		
		try
		{
			$item = \IPS\donate\Item::load( $itemId );
			return $item;
		}
		catch ( \Exception $e )
		{
			$this->_clearSession();
			\IPS\Output::i()->error( 'generic_error', '3O100/9' );
		}
	}
	
	/**
	 * Create order
	 *
	 * @param	\IPS\donate\Item	$item	Item
	 * @param	\IPS\donate\Item\PaymentOption	$paymentOption	Payment option
	 * @param	\IPS\donate\Money	$cost	Cost
	 * @param	\IPS\donate\Gateway	$provider	Provider
	 * @param	\IPS\Member	$giftee	Giftee
	 * @param	array	$extraData	Extra data
	 * @return	\IPS\donate\Gateway\Response
	 */
	protected function _createOrder( \IPS\donate\Item $item, \IPS\donate\Item\PaymentOption $paymentOption, \IPS\donate\Money $cost, \IPS\donate\Gateway $provider, \IPS\Member $giftee = NULL, $extraData = NULL )
	{
		if ( !$item || !$paymentOption || !$cost || !$provider )
		{
			$this->_clearSession();
			\IPS\Output::i()->error( 'generic_error', '3O100/10' );
		}
		
		$request = new \IPS\donate\Order\Request;
		$request->member = \IPS\Member::loggedIn();
		$request->item = $item;
		$request->cost = $cost;
		$request->paymentOption = $paymentOption;
		
		if ( $giftee )
		{
			$request->giftee = $giftee;
		}
		
		if ( $extraData )
		{
			$request->extraData = $extraData;
		}
		
		return $provider->createOrder( $request );
	}
	
	/**
	 * Set active step
	 *
	 * @param	string	$step	Step
	 * @return	void
	 */
	protected function _setActiveStep( $step )
	{
		if ( isset( $_SESSION[$this->_wizardStepKey] ) )
		{
			$this->_activeStep = $step;
			$_SESSION[$this->_wizardStepKey] = $this->_activeStep;
		}
	}
	
	/**
	 * Redirect to active step
	 *
	 * @return	void
	 */
	protected function _redirectToActiveStep()
	{
		$currentStep = \IPS\Request::i()->_step ?: NULL;
		
		if ( !isset( $this->_activeStep ) && $currentStep === $this->_stepKeys[0] )
		{
			\IPS\Output::i()->redirect( $this->_baseUrl );
		}
		
		if ( $currentStep !== $this->_activeStep )
		{
			$currentStepKey = array_search( $currentStep, $this->_stepKeys );
			$activeStepKey = array_search( $this->_activeStep, $this->_stepKeys );
			
			if ( $currentStepKey === FALSE )
			{
				$currentStepKey = \count( $this->_stepKeys );
			}
			
			if ( $currentStepKey !== FALSE && $activeStepKey !== FALSE )
			{
				if ( $currentStepKey > $activeStepKey )
				{
					\IPS\Output::i()->redirect( $this->_redirectUrls[$this->_activeStep] );
				}
			}
		}
	}
	
	/**
	 * Clear session
	 *
	 * @return	void
	 */
	protected function _clearSession()
	{
		unset( $_SESSION[$this->_wizardStepKey] );
		unset( $_SESSION[$this->_wizardDataKey] );
	}
}