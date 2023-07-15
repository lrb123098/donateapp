;( function($, _, undefined){
	"use strict";
	
	ips.controller.register( 'donate.front.checkout.form', {
		
		_fields: {},
		_itemLabels: [],
		_customSteamId: null,
		
		initialize: function () {
			this.on( 'submit', this.formSubmit );
			this.on( 'click', '.cPaymentOption input', this.paymentOptionSelected );
			this.on( 'click', '.cGift_toggle .ipsToggle', this.giftToggle );
			this.on( 'keyup', '.cSteamAccount_text input', this.steamAccountTextSubmit );
			this.on( 'click', '.cSteamAccount_text .ipsSocial_icon', this.steamAccountTextSubmit );
			this.setup();
		},
		
		/**
		 * Setup method
		 *
		 * @returns 	{void}
		 */
		setup: function () {
			for ( var i = 0, fields = this.scope.find( '.cCheckout_formField' ); i < fields.length; i++ ) {
				var field = $( fields[i] );
				this._fields[field.data('checkoutFormfield')] = field;
			}
			
			var fixedPaymentOption = this.scope.find( '.cPaymentOption input[data-paymentOption-fixed]' );
			var variablePaymentOption = this.scope.find( '.cPaymentOption input[data-paymentOption-variable]' );
			
			if ( fixedPaymentOption.length ) {
				if ( fixedPaymentOption.prop( 'checked' ) || ( variablePaymentOption.length && !variablePaymentOption.prop( 'checked' ) ) ) {
					this._fields['donate_checkout_amount'].hide();
				}
			}
			
			if ( this._fields['donate_checkout_gift'] ) {
				if ( this._fields['donate_checkout_gift'].find( '.cGift_toggle .ipsToggle.ipsToggle_on' ).length ) {
					this._fields['donate_checkout_steam_account'].hide();
				} else {
					this._fields['donate_checkout_gift'].find( '.cGift_member' ).hide();
				}
			}
			
			if ( this._fields['donate_checkout_items'] ) {
				for ( var i = 0, labels = this._fields['donate_checkout_items'].find( '.cItemCard > label' ); i < labels.length; i++ ) {
					var label = $( labels[i] );
					this._itemLabels.push( { element: label, id: label.attr( 'for' ) } );
				}
			}
		},
		
		/**
		 * On form submit
		 *
		 * @param 		{event} 	e 		Event object
		 * @returns 	{void}
		 */
		formSubmit: function( e ) {
			$( window ).scrollTop( $('#ipsLayout_body').offset().top );
		},
		
		/**
		 * On gift toggle
		 *
		 * @param 		{event} 	e 		Event object
		 * @returns 	{void}
		 */
		giftToggle: function( e ) {
			var target = $( e.currentTarget );
			var memberBlock = this._fields['donate_checkout_gift'].find( '.cGift_member' );
			var steamBlock = this._fields['donate_checkout_steam_account'];
			
			if ( target.hasClass( 'ipsToggle_on' ) )
			{
				memberBlock.slideDown();
				steamBlock.slideUp();
			} else {
				memberBlock.slideUp();
				steamBlock.slideDown();
			}
		},
		
		/**
		 * On payment option selected
		 *
		 * @param 		{event} 	e 		Event object
		 * @returns 	{void}
		 */
		paymentOptionSelected: function( e ) {
			var amountField = this._fields['donate_checkout_amount'];
			
			if ( typeof $( e.target ).data( 'paymentoptionVariable' ) !== 'undefined' ) {
				amountField.slideDown();
			} else {
				amountField.slideUp();
			}
		},
		
		/**
		 * On steam account text submit
		 *
		 * @param 		{event} 	e 		Event object
		 * @returns 	{void}
		 */
		steamAccountTextSubmit: function( e ) {
			var target = $( e.currentTarget );
			
			if ( target.hasClass( 'ipsSocial_icon' ) ) {
				target = target.siblings( 'input' );
			}
			
			var value = target.val();
			
			if ( value === this._customSteamId || target.hasClass( 'ipsField_loading' ) ) {
				return;
			}
			
			var validFormats = [
				/^(STEAM_[01]:[01]:\d+)|(\d+)$/,
				/^https?:\/\/steamcommunity.com\/profiles\/(.+?)(?:\/|$)/,
				/^https?:\/\/steamcommunity.com\/id\/([\w-]+)(?:\/|$)/
			];
			
			var validate = false;
			
			for ( var key in validFormats ) {
				var matches = validFormats[key].exec( value );
				if ( matches !== null && matches[0].length === value.length ) {
					validate = true;
					break;
				}
			}
			
			if ( !validate ) {
				return;
			}
			
			target.addClass( 'ipsField_loading' );
			var _this = this;
			
			ips.getAjax()( target.data( 'checkoutSteamprofiledata' ), {
				data: { steamId: value },
				type: 'post'
			}).done( function ( response ) {
				target.removeClass( 'ipsField_loading' );
				
				if ( !response || response.length === 0 ) {
					return;
				}
				
				_this._customSteamId = value;
				_this.scope.find( '#elSteamAccount_linkedCard input' ).prop( 'checked', false );
				_this.scope.find( '#elSteamAccount_customCard input' ).prop( 'checked', true );
				
				var customCard = _this.scope.find( '#elSteamAccount_customCard' );
				customCard.find( 'input' ).attr( 'value', response.steamid64 );
				customCard.find( 'img.ipsUserPhoto' ).attr( 'src', response.avatarfull );
				customCard.find( '.cSteamAccount_cardInfoName' ).html( response.personaname );
				customCard.find( '.cSteamAccount_cardInfoId' ).html( response.steamid2 );
				customCard.slideDown();
			}).fail(function( response, textStatus, errorThrown ){
				target.removeClass( 'ipsField_loading' );
			});
		}
	});
}(jQuery, _));