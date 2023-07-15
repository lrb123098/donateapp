<?php
 /**
 * @brief				Transactions

 */

namespace IPS\donate\modules\admin\donations;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * transactions
 */
class _transactions extends \IPS\Dispatcher\Controller
{
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'donate_donations_transactions_manage' );
		parent::execute();
	}
	
	/**
	 * Manage
	 *
	 * @return	void
	 */
	protected function manage()
	{
		$table = new \IPS\Helpers\Table\Db( 'donate_transactions', \IPS\Http\Url::internal( 'app=donate&module=donations&controller=transactions' ) );
		$table->include = array( 'type', 'member', 'amount', 'token', 'status', 'date' );
		$table->langPrefix = 'donate_transactions_';
		$table->mainColumn = 'date';
		$table->noSort = array( 'token' );
		$table->sortBy = $table->sortBy ?: 'date';
		$table->sortDirection = $table->sortDirection ?: 'desc';
		
		$table->filters = array(
			'donate_transactions_type_payment_refund' => "type='payment_refund'",
			'donate_transactions_type_payment_reversal' => "type='payment_reversal'",
			'donate_transactions_type_payment' => "type='payment'"
		);
		
		$table->quickSearch = function( $string )
		{
			return \IPS\Db::i()->like( array( 'token', 'provider_token' ), $string );
		};
		
		$table->advancedSearch = array(
			'token' => \IPS\Helpers\Table\SEARCH_QUERY_TEXT,
			'member' => array( \IPS\Helpers\Table\SEARCH_NUMERIC, array(), function( $v ) {
				switch ( $v[0] )
				{
					case 'gt':
						return array( 'donate_transactions.member>?', (int) $v[1] );
					case 'lt':
						return array( 'donate_transactions.member<?', (int) $v[1] );
					case 'eq':
						return array( 'donate_transactions.member=?', (int) $v[1] );
				}
			} ),
			'amount' => array( \IPS\Helpers\Table\SEARCH_NUMERIC, array(), function( $v ) {
				switch ( $v[0] )
				{
					case 'gt':
						return array( 'donate_transactions.amount>?', (int) $v[1] * 100 );
					case 'lt':
						return array( 'donate_transactions.amount<?', (int) $v[1] * 100 );
					case 'eq':
						return array( 'donate_transactions.amount=?', (int) $v[1] * 100 );
				}
			} ),
			'type' => array( \IPS\Helpers\Table\SEARCH_SELECT, array( 'options' => array(
				'' => 'any',
				'payment' => $table->langPrefix . 'type_payment',
				'payment_refund' => $table->langPrefix . 'type_payment_refund',
				'payment_reversal' => $table->langPrefix . 'type_payment_reversal'
			) ), function( $val )
			{
				return array( 'donate_transactions.type=?', $val );
			} ),
			'status' => array( \IPS\Helpers\Table\SEARCH_SELECT, array( 'options' => array(
				'' => 'any',
				'paid' => $table->langPrefix . 'status_paid',
				'refunded' => $table->langPrefix . 'status_refunded',
				'reversed' => $table->langPrefix . 'status_reversed'
			) ), function( $val )
			{
				return array( 'donate_transactions.status=?', $val );
			} ),
			'date' => \IPS\Helpers\Table\SEARCH_DATE_RANGE
		);
		
		$table->parsers = array(
			'type' => function( $val, $row ) use ( $table )
			{
				return \IPS\Member::loggedIn()->language()->addToStack( $table->langPrefix . 'type_' . $val );
			},
			'member' => function( $val, $row )
			{
				return $val ? \IPS\Member::load( $val )->link() : '';
			},
			'amount' => function( $val, $row )
			{
				$amount = \IPS\donate\Money::constructFromBase( $val );
				return $amount->currencySymbol . $amount->format;
			},
			'status' => function( $val, $row )
			{
				return $val ? \IPS\Theme::i()->getTemplate( 'transactions' )->status( $val ) : '';
			},
			'date' => function( $val, $row )
			{
				return \IPS\DateTime::ts( $val )->localeDate();
			}
		);
		
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'menu__donate_donations_transactions' );
		\IPS\Output::i()->output = (string) $table;
	}
}