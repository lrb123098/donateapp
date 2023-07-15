<?php
 /**
 * @brief				Subscriptions

 */

namespace IPS\donate\modules\admin\donations;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * subscriptions
 */
class _subscriptions extends \IPS\Dispatcher\Controller
{
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'donate_donations_subscriptions_manage' );
		parent::execute();
	}
	
	/**
	 * Manage
	 *
	 * @return	void
	 */
	protected function manage()
	{
		$table = new \IPS\Helpers\Table\Db( 'donate_purchases', \IPS\Http\Url::internal( 'app=donate&module=donations&controller=subscriptions' ), 'deactivate_date IS NOT NULL' );
		$table->include = array( 'giftee', 'member', 'item', 'active', 'deactivate_date', 'date' );
		$table->langPrefix = 'donate_subscriptions_';
		$table->mainColumn = 'date';
		$table->noSort = array( 'giftee' );
		$table->sortBy = $table->sortBy ?: 'date';
		$table->sortDirection = $table->sortDirection ?: 'desc';
		$table->rowClasses = array( 'giftee' => array( 'ipsType_center' ) );
		
		$table->joins = array(
			array(
				'select' => 'order.giftee',
				'from' => array( 'donate_orders', 'order' ),
				'where' => 'donate_purchases.order IS NOT NULL AND order.id=donate_purchases.order AND order.giftee IS NOT NULL AND order.giftee=donate_purchases.member'
			)
		);
		
		$table->filters = array(
			'donate_subscriptions_gift' => "giftee IS NOT NULL",
			'donate_subscriptions_inactive' => "active=0",
			'donate_subscriptions_active' => "active=1"
		);
		
		$table->parsers = array(
			'giftee' => function( $val, $row )
			{
				return $val ? '<i class="fa fa-gift fa-lg"></i>' : '';
			},
			'member' => function( $val, $row )
			{
				return \IPS\Member::load( $val )->link();
			},
			'item' => function( $val, $row )
			{
				$subscription = \IPS\donate\Item::load( $val );
				return \IPS\Theme::i()->getTemplate( 'items' )->name( $subscription->name, $subscription->color, FALSE );
			},
			'active' => function( $val, $row )
			{
				return \IPS\Theme::i()->getTemplate( 'subscriptions' )->active( $val );
			},
			'deactivate_date' => function( $val, $row )
			{
				return \IPS\DateTime::ts( $val )->localeDate();
			},
			'date' => function( $val, $row )
			{
				return \IPS\DateTime::ts( $val )->localeDate();
			}
		);
		
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'donate', 'donations', 'donate_donations_subscriptions_add' ) )
		{
			\IPS\Output::i()->sidebar['actions']['add'] = array(
				'primary' => true,
				'icon' => 'plus',
				'title' => 'donate_subscriptions_add',
				'link' => \IPS\Http\Url::internal( 'app=donate&module=donations&controller=subscriptions&do=form' ),
				'data' => array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack( 'donate_subscriptions_add' ) )
			);
		}
		
		$table->rowButtons = function( $row )
		{
			$buttons = array();
			
			if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'donate', 'donations', 'donate_donations_subscriptions_edit' ) )
			{
				$buttons['edit'] = array(
					'icon' => 'pencil',
					'title' => 'edit',
					'link' => \IPS\Http\Url::internal( 'app=donate&module=donations&controller=subscriptions&do=form&id=' . $row['id'] )
				);
			}
			
			return $buttons;
		};
		
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'menu__donate_donations_subscriptions' );
		\IPS\Output::i()->output = (string) $table;
	}
	
	/**
	 * Form
	 *
	 * @return	void
	 */
	protected function form()
	{
		$subscription = NULL;
		$new = FALSE;
		
		try
		{
			\IPS\Dispatcher::i()->checkAcpPermission( 'donate_donations_subscriptions_edit' );
			$subscription = \IPS\donate\Purchase::load( \IPS\Request::i()->id );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Dispatcher::i()->checkAcpPermission( 'donate_donations_subscriptions_add' );
			$subscription = new \IPS\donate\Purchase;
			$new = TRUE;
		}
		
		if ( !$subscription )
		{
			\IPS\Output::i()->error( 'generic_error', '2O104/1', 500 );
		}
		
		$form = new \IPS\Helpers\Form;
		$form->add( new \IPS\Helpers\Form\Member( 'donate_subscriptions_member', !$new ? $subscription->member : NULL, TRUE, array( 'disabled' => !$new ? TRUE : FALSE ) ) );
		
		$itemOptions = array();
		foreach ( \IPS\donate\Item::items() as $item )
		{
			if ( $item->hasPaymentOption( \IPS\donate\Order::PAYMENT_TYPE_SUBSCRIPTION ) )
			{
				$itemOptions[$item->id] = $item->name;
			}
		}
		
		$form->add( new \IPS\Helpers\Form\Select( 'donate_subscriptions_item', !$new ? $subscription->item->id : NULL, TRUE, array( 'options' => $itemOptions, 'parse' => 'normal', 'disabled' => !$new ? TRUE : FALSE ) ) );
		$form->add( new \IPS\Helpers\Form\Date( 'donate_subscriptions_date_expire', !$new ? $subscription->deactivate_date : NULL, TRUE, array( 'disabled' => !$new && !$subscription->active ? TRUE : FALSE ) ) );
		
		$extraData = NULL;
		
		if ( $subscription->extra_data )
		{
			$extraData = array();
			
			foreach ( $subscription->extra_data as $k => $v )
			{
				$extraData[] = array(
					'key' => $k,
					'value' => $v
				);
			}
		}
		
		$form->add( new \IPS\Helpers\Form\Stack( 'donate_subscriptions_extra_data', $extraData, FALSE, array(
			'stackFieldType' => 'KeyValue',
			'disabled' => !$new ? TRUE : FALSE
		) ) );
		
		if ( $values = $form->values() )
		{
			$subscription->member = $values['donate_subscriptions_member'];
			$subscription->item = \IPS\donate\Item::load( (int) $values['donate_subscriptions_item'] );
			$subscription->active = TRUE;
			$subscription->deactivate_date = $values['donate_subscriptions_date_expire'];
			
			if ( $values['donate_subscriptions_extra_data'] )
			{
				$extraData = array();
				
				foreach ( $values['donate_subscriptions_extra_data'] as $kv )
				{
					if ( $kv['key'] && $kv['value'] )
					{
						$extraData[$kv['key']] = $kv['value'];
					}
				}
				
				$subscription->extra_data = \count( $extraData ) ? $extraData : NULL;
			}
			else
			{
				$subscription->extra_data = NULL;
			}
			
			$subscription->save();
			
			if ( $new )
			{
				$subscription->runItemPerkEvent( 'PurchaseCreated', $subscription );
				\IPS\donate\Log::create( $subscription->member, \IPS\donate\Log::TYPE_PURCHASE_CREATED, NULL, $subscription );
				
				if ( $subscription->active )
				{
					$subscription->runItemPerkEvent( 'PurchaseActivated', $subscription );
					\IPS\donate\Log::create( $subscription->member, \IPS\donate\Log::TYPE_PURCHASE_ACTIVATED, NULL, $subscription );
				}
			}
			
			\IPS\Session::i()->log( 'acplogs__donate_subscriptions' );
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=donate&module=donations&controller=subscriptions' ), 'saved' );
		}
		
		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->sendOutput( (string) $form );
		}
		else
		{
			\IPS\Output::i()->title	= \IPS\Member::loggedIn()->language()->addToStack( 'donate_subscriptions_' . ( $new ? 'add' : 'edit' ) );
			\IPS\Output::i()->output = (string) $form;
		}
	}
}