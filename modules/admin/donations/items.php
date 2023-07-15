<?php
 /**
 * @brief				Items

 */

namespace IPS\donate\modules\admin\donations;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * items
 */
class _items extends \IPS\Dispatcher\Controller
{	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'donate_donations_items_manage' );
		parent::execute();
	}
	
	/**
	 * Manage
	 *
	 * @return	void
	 */
	protected function manage()
	{
		$table = new \IPS\Helpers\Table\Db( 'donate_items', \IPS\Http\Url::internal( 'app=donate&module=donations&controller=items' ) );
		$table->include = array( 'name', 'price', 'active_length', 'perks' );
		$table->langPrefix = 'donate_items_';
		$table->mainColumn = 'name';
		$table->quickSearch = array( 'name', 'name' );
		$table->sortBy = $table->sortBy ?: 'id';
		$table->sortDirection = $table->sortDirection ?: 'asc';
		
		$table->parsers = array(
			'name' => function( $val, $row )
			{
				return \IPS\Theme::i()->getTemplate( 'items' )->name( $val, \IPS\donate\Item::constructFromData( $row )->color );
			},
			'price' => function( $val, $row )
			{
				$amount = \IPS\donate\Money::constructFromBase( $val );
				return $amount->currencySymbol . $amount->format;
			},
			'active_length' => function( $val, $row )
			{
				return $val ? \IPS\donate\Application::secondsFormat( \IPS\donate\Item::constructFromData( $row )->active_length ) : '';
			},
			'perks' => function( $val, $row )
			{
				return \IPS\Theme::i()->getTemplate( 'items' )->perks( \IPS\donate\Item::constructFromData( $row )->perks );
			}
		);
		
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'donate', 'donations', 'donate_donations_items_add' ) )
		{
			\IPS\Output::i()->sidebar['actions']['add'] = array(
				'primary' => true,
				'icon' => 'plus',
				'title' => 'donate_items_add',
				'link' => \IPS\Http\Url::internal( 'app=donate&module=donations&controller=items&do=form' )
			);
		}
		
		$table->rowButtons = function( $row )
		{
			$buttons = array();
			
			if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'donate', 'donations', 'donate_donations_items_edit' ) )
			{
				$buttons['edit'] = array(
					'icon' => 'pencil',
					'title' => 'edit',
					'link' => \IPS\Http\Url::internal( 'app=donate&module=donations&controller=items&do=form&id=' . $row['id'] )
				);
			}
			
			return $buttons;
		};
		
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'menu__donate_donations_items' );
		\IPS\Output::i()->output = (string) $table;
	}
	
	/**
	 * Form
	 *
	 * @return	void
	 */
	protected function form()
	{
		$item = NULL;
		
		try
		{
			\IPS\Dispatcher::i()->checkAcpPermission( 'donate_donations_items_edit' );
			$item = \IPS\donate\Item::load( \IPS\Request::i()->id );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Dispatcher::i()->checkAcpPermission( 'donate_donations_items_add' );
			$item = new \IPS\donate\Item;
			$item->price = new \IPS\donate\Money( 0 );
			$item->payment_options = array( new \IPS\donate\Item\PaymentOption( 'onetime', 'fixed' ) );
			$item->perks = array( \IPS\donate\Item\Perk::constructFromId( 'member_group', array( 'group_id' => NULL ) ) );
		}
		
		if ( !$item )
		{
			\IPS\Output::i()->error( 'generic_error', '2O103/1', 500 );
		}
		
		$form = new \IPS\Helpers\Form;
		$form->addHeader( 'donate_items_settings' );
		$form->add( new \IPS\Helpers\Form\Text( 'donate_items_name', $item->name, TRUE ) );
		$form->add( new \IPS\Helpers\Form\Number( 'donate_items_price', $item->price->format, TRUE, array( 'min' => 0, 'max' => 10000, 'decimals' => TRUE, ), NULL, '$' ) );
		$form->add( new \IPS\Helpers\Form\Text( 'donate_items_price_detail', $item->price_detail, FALSE, array( 'size' => 5 ) ) );	
		$form->add( new \IPS\Helpers\Form\YesNo( 'donate_items_color_use', $item->color ? TRUE : FALSE, FALSE, array( 'togglesOn' => array( 'donate_items_color' ) ) ) );
		$form->add( new \IPS\Helpers\Form\Color( 'donate_items_color', $item->color, FALSE, array(), NULL, NULL, NULL, 'donate_items_color' ) );
		
		$paymentOptionsDefault = array_map( function( $v )
		{
			return (string) $v;
		}, $item->payment_options );
		
		$form->add( new \IPS\Helpers\Form\Stack( 'donate_items_payment_options', $paymentOptionsDefault, TRUE, array(
			'stackFieldType' => 'Select',
			'minItems' => 1,
			'maxItems' => 2,
			'options' => array(
				'subscription,fixed' => 'donate_payment_option_subscriptionfixed',
				'subscription,variable' => 'donate_payment_option_subscriptionvariable',
				'onetime,fixed' => 'donate_payment_option_onetimefixed',
				'onetime,variable' => 'donate_payment_option_onetimevariable',
			)
		) ) );
		
		$form->add( new \IPS\Helpers\Form\Editor( 'donate_items_desc', $item->desc, FALSE, array(
			'app' => 'core',
			'key' => 'Admin',
			'autoSaveKey' => 'donate-items-desc',
			'ipsPlugins' => "ipsautolink,ipsautosave,ipsctrlenter,ipscode,ipscontextmenu,ipsemoticon,ipslink,ipsmentions,ipspage,ipspaste,ipsquote,ipsspoiler,ipssource,removeformat"
		) ) );
		
		$activeLengthDefault = $item->active_length_array;
		$form->add( new \IPS\Helpers\Form\YesNo( 'donate_items_active_length_use', $item->active_length ? TRUE : FALSE, FALSE, array( 'togglesOn' => array( 'donate_items_active_length' ) ) ) );
		$form->add( new \IPS\Helpers\Form\Custom( 'donate_items_active_length', array( 'length' => $item->active_length ? (int) $activeLengthDefault[0] : 1, 'time' => $item->active_length ? $activeLengthDefault[1] : 'M' ), FALSE, array(
			'getHtml' => function( $element )
			{
				$length = new \IPS\Helpers\Form\Number( $element->name . '[length]', $element->defaultValue['length'], FALSE, array( 'min' => 1, 'max' => 1000, 'decimals' => FALSE, 'step' => 1 ) );
				$time = new \IPS\Helpers\Form\Select( $element->name . '[time]', $element->defaultValue['time'], FALSE, array(
					'options' => array(
						'D' => 'days',
						'W' => 'weeks',
						'M' => 'months',
						'Y' => 'years'
					)
				) );
				
				return $length->html() . $time->html();
			}
		), NULL, NULL, NULL, 'donate_items_active_length' ) );
		
		$form->add( new \IPS\Helpers\Form\YesNo( 'donate_items_enabled', $item->enabled ) );
		
		$form->addHeader( 'donate_items_perks_settings' );
		$form->add( new \IPS\Helpers\Form\YesNo( 'donate_items_perks_use', $item->perks ? TRUE : FALSE, FALSE, array( 'togglesOn' => array( 'donate_items_perks' ) ) ) );
		
		$perksOptions = \IPS\donate\Item\Perk::PERKS;
		array_walk( $perksOptions, function( &$v, $k )
		{
			$v = 'donate_items_perk_' . $k;
		} );
		
		$form->add( new \IPS\Helpers\Form\Stack( 'donate_items_perks', $item->perks, FALSE, array(
			'stackFieldType' => 'Custom',
			'minItems' => 1,
			'maxItems' => 10,
			'getHtml' => function( $element ) use ( $perksOptions )
			{
				if ( !$element->defaultValue )
				{
					$element->defaultValue = \IPS\donate\Item\Perk::constructFromId( 'member_group', array( 'group_id' => NULL ) );
				}
				
				$html = '';
				$perk = new \IPS\Helpers\Form\Select( $element->name . '[perk]', $element->defaultValue->id, FALSE, array( 'options' => $perksOptions ) );
				$html .= $perk->html();
				
				if ( $dataKeys = $element->defaultValue::$dataKeys )
				{
					$data = new \IPS\Helpers\Form\Number( $element->name . '[' . $dataKeys[0] . ']', $element->defaultValue->{$dataKeys[0]}, FALSE, array( 'min' => 0, 'max' => 10000, 'decimals' => FALSE, 'step' => 1 ) );
					$html .= $data->html();
				}
				
				return $html;
			},
			'formatValue' => function( $element )
			{
				if ( \is_array( $element->value ) )
				{
					$data = NULL;
					
					if ( \count( $element->value ) > 1 )
					{
						$dataKey = array_keys( $element->value )[1];
						$data = array( $dataKey => $element->value[$dataKey] );
					}
					
					$perk = \IPS\donate\Item\Perk::constructFromId( $element->value['perk'], $data );
					return $perk;
				}
				
				return $element->value;
			}
		) ) );
		
		$form->add( new \IPS\Helpers\Form\TextArea( 'donate_items_perksdesc', $item->perks_desc ? json_encode( $item->perks_desc, JSON_PRETTY_PRINT ) : NULL, FALSE, array( 'rows' => 20 ) ) );
		
		$form->addHeader( 'donate_items_provider_settings' );
		
		foreach ( \IPS\donate\Gateway::PROVIDERS as $id => $class )
		{
			$provider = \IPS\donate\Gateway::getProvider( $id );
			$provider->itemData( $form, $item->getProviderData( $id ) );
		}
		
		if ( $values = $form->values() )
		{
			$item->name = $values['donate_items_name'];
			$item->price = new \IPS\donate\Money( (float) $values['donate_items_price'] );
			$item->price_detail = $values['donate_items_price_detail'] ?: NULL;
			
			if ( $values['donate_items_color_use'] && $values['donate_items_color'] )
			{
				$item->color = $values['donate_items_color'];
			}
			else
			{
				$item->color = NULL;
			}
			
			$item->payment_options = array_map( function( $v ) {
				$option = explode( ',', $v );
				return new \IPS\donate\Item\PaymentOption( $option[0], $option[1] );
			}, $values['donate_items_payment_options'] );
			
			$item->desc = $values['donate_items_desc'] ?: NULL;
			
			if ( $values['donate_items_active_length_use'] && $values['donate_items_active_length']['length'] && $values['donate_items_active_length']['time'] )
			{
				$item->active_length = array( $values['donate_items_active_length']['length'], $values['donate_items_active_length']['time'] );
			}
			else
			{
				$item->active_length = NULL;
			}
			
			$item->enabled = (bool) $values['donate_items_enabled'];
			$item->perks = $values['donate_items_perks_use'] ? $values['donate_items_perks'] : NULL;
			$item->perks_desc = $values['donate_items_perksdesc'] ?: NULL;
			
			foreach ( \IPS\donate\Gateway::PROVIDERS as $id => $class )
			{
				$provider = \IPS\donate\Gateway::getProvider( $id );
				
				if ( $data = $provider->formatItemDataValues( $values ) )
				{
					$item->setProviderData( $provider->id, $provider->formatItemDataValues( $values ) );
				}
				else
				{
					$item->setProviderData( $provider->id, NULL );
				}
			}
			
			$item->save();
			\IPS\donate\Item::resetCache();
			
			\IPS\Session::i()->log( 'acplogs__donate_items' );
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=donate&module=donations&controller=items' ), 'saved' );
		}
		
		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->sendOutput( (string) $form );
		}
		else
		{
			\IPS\Output::i()->title	= $item->name ?: \IPS\Member::loggedIn()->language()->addToStack( 'donate_items_add' );
			\IPS\Output::i()->output = (string) $form;
		}
	}
}