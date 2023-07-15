<?php
/**
 * @brief				Member Group Item Perk Model

 */

namespace IPS\donate\Item\Perk;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * MemberGroup
 */
class _MemberGroup extends \IPS\donate\Item\Perk
{
	/**
	 * @brief	Traits
	 */
	public static $traits = array( \IPS\donate\Item\Perk::TRAIT_IPS );
	
	/**
	 * @brief	Data keys
	 */
	public static $dataKeys = array( 'group_id' );
	
	/**
	 * Set group
	 *
	 * @param	\IPS\Member	$member	Member
	 * @return	void
	 */
	protected function setGroup( \IPS\Member $member )
	{
		if ( !$member->member_id || $member->member_group_id === $this->group_id )
		{
			return;
		}
		
		$groups = array_filter( explode( ',', $member->mgroup_others ) );
		
		if ( array_search( $this->group_id, $groups ) !== FALSE )
		{
			return;
		}
		
		$groups[] = $this->group_id;
		$member->mgroup_others = implode( ',', $groups );
		$member->save();
	}
	
	/**
	 * Unset group
	 *
	 * @param	\IPS\Member	$member	Member
	 * @return	void
	 */
	protected function unsetGroup( \IPS\Member $member )
	{
		if ( !$member->member_id || $member->member_group_id === $this->group_id )
		{
			return;
		}
		
		$groups = array_filter( explode( ',', $member->mgroup_others ) );
		$key = array_search( $this->group_id, $groups );
		
		if ( $key === FALSE )
		{
			return;
		}
		
		unset( $groups[$key] );
		$member->mgroup_others = implode( ',', $groups );
		$member->save();
	}
	
	/**
	 * On purchase activated
	 *
	 * @param	\IPS\donate\purchase	$purchase	Purchase
	 * @return	void
	 */
	public function onPurchaseActivated( \IPS\donate\Purchase $purchase )
	{
		$this->setGroup( $purchase->member );
	}
	
	/**
	 * On purchase renewed
	 *
	 * @param	\IPS\donate\purchase	$purchase	Purchase
	 * @return	void
	 */
	public function onPurchaseRenewed( \IPS\donate\Purchase $purchase )
	{
		$this->setGroup( $purchase->member );
	}
	
	/**
	 * On purchase deactivated
	 *
	 * @param	\IPS\donate\purchase	$purchase	Purchase
	 * @return	void
	 */
	public function onPurchaseDeactivated( \IPS\donate\Purchase $purchase )
	{
		$this->unsetGroup( $purchase->member );
	}
}