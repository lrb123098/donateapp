<?php
/**
 * @brief				[Front] My Donations Controller

 */

namespace IPS\donate\modules\front\donate;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * mydonations
 */
class _mydonations extends \IPS\Dispatcher\Controller
{
	/**
	 * @brief	Tabs
	 */
	protected $_tabs;
	
	/**
	 * @brief	Active tab
	 */
	protected $_activeTab;
	
	/**
	 * @brief	Active tab url
	 */
	protected $_activeTabUrl;
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		if ( !\IPS\Member::loggedIn()->member_id )
		{
			\IPS\Output::i()->error( 'no_module_permission_guest', '2O102/1', 403 );
		}
		
		\IPS\Session::i()->setLocation( \IPS\Http\Url::internal( 'app=donate&module=donate&controller=mydonations', 'front', 'donate_mydonations' ), array(), 'loc_donate_mydonations' );
		
		\IPS\Output::i()->sidebar['enabled'] = FALSE;
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('donate_mydonations');
		
		\IPS\Output::i()->breadcrumb = array();
		\IPS\Output::i()->breadcrumb[] = array( \IPS\Http\Url::internal( 'app=donate&module=donate&controller=mydonations', 'front', 'donate_mydonations' ), \IPS\Member::loggedIn()->language()->addToStack( 'donate_mydonations' ) );
		
		parent::execute();
	}
	
	/**
	 * Manage
	 *
	 * @return	void
	 */
	protected function manage()
	{
		$this->_tabs = array(
			'overview' => 'donate_mydonations_overview',
			'payments' => 'donate_mydonations_payments'
		);
		$this->_activeTab = 'overview';
		
		if ( isset( \IPS\Request::i()->tab ) && isset( $this->_tabs[\IPS\Request::i()->tab] ) )
		{
			$this->_activeTab = \IPS\Request::i()->tab;
		}
		
		$this->_activeUrl = \IPS\Http\Url::internal( 'app=donate&module=donate&controller=mydonations&tab=' . $this->_activeTab, 'front', 'donate_mydonations_' . $this->_activeTab );
		$content = NULL;
		
		foreach ( $this->_tabs as $tab => $lang )
		{
			if ( $tab === $this->_activeTab )
			{
				$tabMethod = '_' . $tab;
				
				if ( method_exists( $this, $tabMethod ) )
				{
					$content = (string) $this->{$tabMethod}();
				}
				
				break;
			}
		}
		
		if ( \IPS\Request::i()->isAjax() && isset( \IPS\Request::i()->tab ) )
		{
			\IPS\Output::i()->sendOutput( $content );
		}
		else
		{
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global' )->page( 'donate_mydonations', \IPS\Theme::i()->getTemplate( 'global' )->tabs( $this->_tabs, $this->_activeTab, $content, array( 'app=donate&module=donate&controller=mydonations', 'donate_mydonations' ), TRUE, TRUE, 'ipsTabs_withIcons ipsTabs_large ipsTabs_stretch' ) );
		}
	}
	
	/**
	 * Overview
	 *
	 * @return	mixed
	 */
	protected function _overview()
	{
		$select = \IPS\Db::i()->select( '*', 'donate_orders', array( 'member=? OR giftee=?', \IPS\Member::loggedIn()->member_id, \IPS\Member::loggedIn()->member_id ), 'date DESC' );
		$orders = iterator_to_array( new \IPS\Patterns\ActiveRecordIterator( $select, '\IPS\donate\Order' ) ) ?: array();
		$select = \IPS\Db::i()->select( '*', 'donate_purchases', array( 'member=?', \IPS\Member::loggedIn()->member_id ), 'date DESC' );
		$purchases = iterator_to_array( new \IPS\Patterns\ActiveRecordIterator( $select, '\IPS\donate\Purchase' ) ) ?: array();
		$rows = array();
		
		foreach ( $orders as $order )
		{
			$row = array();
			$row['order'] = $order;
			
			if ( $order->purchase )
			{
				$row['purchase'] = $order->purchase;
			}
			
			$rows[] = $row;
		}
		
		foreach ( $purchases as $purchase )
		{
			if ( !$purchase->order )
			{
				$rows[] = array( 'purchase' => $purchase );
			}
		}
		
		$data = array(
			'active' => array(),
			'inactive' => array(),
			'pending' => array()
		);
		
		foreach ( $rows as $row )
		{
			if ( isset( $row['purchase'] ) && $row['purchase']->active )
			{
				$data['active'][] = $row;
			}
			elseif ( ( isset( $row['purchase'] ) && !$row['purchase']->active ) )
			{
				if ( !isset( $row['order'] ) || ( isset( $row['order'] ) && ( $row['order']->status === \IPS\donate\Order::STATUS_COMPLETED || $row['order']->status === \IPS\donate\Order::STATUS_SUSPENDED ) ) )
				{
					$data['inactive'][] = $row;
				}
			}
			
			if ( isset( $row['order'] ) && $row['order']->status === \IPS\donate\Order::STATUS_PENDING )
			{
				$data['pending'][] = $row;
			}
		}
		
		$elements = array();
		
		if ( \count( $data['active'] ) )
		{
			$elements[] = \IPS\Theme::i()->getTemplate( 'mydonations' )->overviewTable( 'donate_mydonations_overview_active', 'donate_mydonations_', $data['active'] );
		}
		
		if ( \count( $data['pending'] ) )
		{
			$elements[] = \IPS\Theme::i()->getTemplate( 'mydonations' )->overviewTable( 'donate_mydonations_overview_pending', 'donate_mydonations_', $data['pending'], array( 'item' => 1, 'type' => 1, 'cost' => 1, 'order' => 1, 'date' => 1, 'giftee' => 1 ) );
		}
		
		if ( \count( $data['inactive'] ) )
		{
			$elements[] = \IPS\Theme::i()->getTemplate( 'mydonations' )->overviewTable( 'donate_mydonations_overview_inactive', 'donate_mydonations_', $data['inactive'], array( 'item' => 1, 'type' => 1, 'cost' => 1, 'order' => 1, 'date' => 1, 'giftee' => 1 ) );
		}
		
		return \IPS\Theme::i()->getTemplate( 'mydonations' )->overview( $elements );
	}
	
	/**
	 * Payments
	 *
	 * @return	mixed
	 */
	protected function _payments()
	{
		$payments = new \IPS\Helpers\Table\Db( 'donate_transactions', $this->_activeUrl, array( 'member=? AND type=? AND status=?', \IPS\Member::loggedIn()->member_id, \IPS\donate\Transaction::TYPE_PAYMENT, \IPS\donate\Payment::STATUS_PAID ) );
		$payments->include = array( 'date', 'amount', 'order' );
		$payments->langPrefix = 'donate_mydonations_';
		$payments->rowsTemplate = array( \IPS\Theme::i()->getTemplate( 'mydonations' ), 'paymentsRows' );
		$payments->sortBy = $payments->sortBy ?: 'date';
		$payments->sortDirection = $payments->sortDirection ?: 'desc';
		$payments->sortOptions = array(
			'date' => 'date',
			'amount' => 'amount'
		);
		
		$payments->parsers = array(
			'order' => function( $val, $row )
			{
				$order = NULL;
				
				if ( $val )
				{
					try
					{
						$order = \IPS\donate\Order::load( $val );
					}
					catch ( \Exception $e )
					{
					}
				}
				
				return $order;
			},
			'amount' => function( $val, $row )
			{
				return \IPS\donate\Money::constructFromBase( $val );
			}
		);
		
		return $payments;
	}
}