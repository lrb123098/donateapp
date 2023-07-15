<?php
/**
 * @brief				[Front] DonationList Controller

 */

namespace IPS\donate\modules\front\donate;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * donationlist
 */
class _donationlist extends \IPS\Dispatcher\Controller
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
		\IPS\Session::i()->setLocation( \IPS\Http\Url::internal( 'app=donate&module=donate&controller=donationlist', 'front', 'donate_donationlist' ), array(), 'loc_donate_donationlist' );
		
		\IPS\Output::i()->sidebar['enabled'] = FALSE;
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('donate_donationlist');
		
		\IPS\Output::i()->breadcrumb = array();
		\IPS\Output::i()->breadcrumb[] = array( \IPS\Http\Url::internal( 'app=donate&module=donate&controller=donationlist', 'front', 'donate_donationlist' ), \IPS\Member::loggedIn()->language()->addToStack( 'donate_donationlist' ) );
		
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
			'latest' => 'donate_donationlist_latest',
			'top' => 'donate_donationlist_top'
		);
		$this->_activeTab = 'latest';
		
		if ( isset( \IPS\Request::i()->tab ) && isset( $this->_tabs[\IPS\Request::i()->tab] ) )
		{
			$this->_activeTab = \IPS\Request::i()->tab;
		}
		
		$this->_activeUrl = \IPS\Http\Url::internal( 'app=donate&module=donate&controller=donationlist&tab=' . $this->_activeTab, 'front', 'donate_donationlist_' . $this->_activeTab );
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
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global' )->page( 'donate_donationlist', \IPS\Theme::i()->getTemplate( 'global' )->tabs( $this->_tabs, $this->_activeTab, $content, array( 'app=donate&module=donate&controller=donationlist', 'donate_donationlist' ), TRUE, TRUE, 'ipsTabs_withIcons ipsTabs_large ipsTabs_stretch' ) );
		}
	}
	
	/**
	 * Latest
	 *
	 * @return	mixed
	 */
	protected function _latest()
	{
		$latest = new \IPS\Helpers\Table\Db( 'donate_transactions', $this->_activeUrl, array( 'type=? AND status=?', \IPS\donate\Transaction::TYPE_PAYMENT, \IPS\donate\Payment::STATUS_PAID ) );
		$latest->include = array( 'date', 'member', 'amount' );
		$latest->langPrefix = 'donate_donationlist_';
		$latest->rowsTemplate = array( \IPS\Theme::i()->getTemplate( 'donationlist' ), 'latestRows' );
		$latest->sortBy = $latest->sortBy ?: 'date';
		$latest->sortDirection = $latest->sortDirection ?: 'desc';
		$latest->sortOptions = array(
			'date' => 'date',
			'amount' => 'amount'
		);
		
		$latest->parsers = array(
			'member' => function( $val, $row )
			{
				return \IPS\Member::load( $val );
			},
			'amount' => function( $val, $row )
			{
				return \IPS\donate\Money::constructFromBase( $val );
			}
		);
		
		return $latest;
	}
	
	/**
	 * Top
	 *
	 * @return	mixed
	 */
	protected function _top()
	{
		$top = new \IPS\Helpers\Table\Db( 'core_members', $this->_activeUrl, array( 'donation_total IS NOT NULL AND donation_total > 0' ) );
		$top->include = array( 'member_id', 'donation_total' );
		$top->langPrefix = 'donate_donationlist_';
		$top->rowsTemplate = array( \IPS\Theme::i()->getTemplate( 'donationlist' ), 'topRows' );
		$top->sortBy = $top->sortBy ?: 'donation_total';
		$top->sortDirection = $top->sortDirection ?: 'desc';
		
		$top->parsers = array(
			'member_id' => function( $val, $row )
			{
				return \IPS\Member::constructFromData( $row );
			},
			'donation_total' => function( $val, $row )
			{
				return \IPS\donate\Money::constructFromBase( $val );
			}
		);
		
		return $top;
	}
}