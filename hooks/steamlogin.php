//<?php

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	exit;
}

class donate_hook_steamlogin extends _HOOK_CLASS_
{
	/**
	 * {@inheritdoc}
	 */
	public function completeLink( \IPS\Member $member, $details )
	{		
		parent::completeLink( $member, $details );
		
		try
		{
			if ( !$member->steamid )
			{
				throw new \Exception;
			}
			
			try
			{
				$select = \IPS\Db::i()->select( '*', 'donate_purchases', array( "( extra_data IS NULL OR extra_data NOT LIKE CONCAT( '%', ?, '%' ) ) AND member=? AND active=1", '"steam_id":"', $member->member_id ) );
				$purchases = iterator_to_array(  new \IPS\Patterns\ActiveRecordIterator( $select, '\IPS\donate\Purchase' ) ) ?: array();
				
				foreach ( $purchases as $purchase )
				{
					$purchase->runItemPerkEvent( array( 'PurchaseActivated', \IPS\donate\Item\Perk::TRAIT_STEAMID ), $purchase );
				}
			}
			catch ( \Exception $e )
			{
			}
			
			try
			{
				$steamId = new \Steam\SteamID( $member->steamid );
				$select = \IPS\Db::i()->select( '*', 'donate_purchases', array( "extra_data LIKE CONCAT( '%', ?, '%' ) AND member=0 AND active=1", '"steam_id":"' . $steamId->ConvertToUInt64() . '"' ) );
				$purchases = iterator_to_array(  new \IPS\Patterns\ActiveRecordIterator( $select, '\IPS\donate\Purchase' ) ) ?: array();
				
				foreach ( $purchases as $purchase )
				{
					$purchase->member = $member;
					$purchase->save();
					$purchase->runItemPerkEvent( array( 'PurchaseActivated', \IPS\donate\Item\Perk::TRAIT_IPS ), $purchase );
					
					$order = $purchase->order;
					
					if ( !$order || $order->giftee->member_id )
					{
						continue;
					}
					
					$order->giftee = $member;
					$order->save();
				}
			}
			catch ( \Exception $e )
			{
			}
		}
		catch ( \Exception $e )
		{
		}
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function disassociate( \IPS\Member $member=NULL )
	{
		try
		{
			$member = $member ?: \IPS\Member::loggedIn();
			
			if ( !$member->steamid )
			{
				throw new \Exception;
			}
			
			$steamId = new \Steam\SteamID( $member->steamid );
			$select = \IPS\Db::i()->select( '*', 'donate_purchases', array( "( extra_data IS NULL OR extra_data NOT LIKE CONCAT( '%', ?, '%' ) ) AND member=? AND active=1", '"steam_id":"', $member->member_id ) );
			$purchases = iterator_to_array(  new \IPS\Patterns\ActiveRecordIterator( $select, '\IPS\donate\Purchase' ) ) ?: array();
			
			foreach ( $purchases as $purchase )
			{
				$purchase->runItemPerkEvent( array( 'PurchaseDeactivated', \IPS\donate\Item\Perk::TRAIT_STEAMID ), $purchase );
			}
		}
		catch ( \Exception $e )
		{
		}
		
		parent::disassociate( $member );
	}
}