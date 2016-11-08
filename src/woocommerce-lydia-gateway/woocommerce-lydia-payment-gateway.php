<?php
/**
* WC_Gateway_Lydia_Payment
*/
class WC_Gateway_Lydia_Payment_Gateway extends WC_Payment_Gateway { // Setup our Gateway's id, description and other values

	function __construct() {

	    // The global ID for this Payment method
	    $this->id = "lydia_payment";

	    // The Title shown on the top of the Payment Gateways Page next to all the other Payment Gateways
	    $this->method_title = __("Lydia", 'lydia_payment');

	    // The title to be used for the vertical tabs that can be ordered top to bottom
	    $this->title = __("Carte bancaire ou Lydia", 'lydia_payment');

	    // If you want to show an image next to the gateway's name on the frontend, enter a URL to an image.
	    $this->icon = plugins_url('/assets/images/lydia.png', __FILE__);

	    // Bool. Can be set to true if you want payment fields to show on the checkout
	    // if doing a direct integration, which we are doing in this case
	    $this->has_fields = true;

	    // This basically defines your settings which are then loaded with init_settings()
	    $this->init_form_fields();

	    // After init_settings() is called, you can get the settings and load them into variables, e.g:
	    // $this->title = $this->get_option( 'title' );
	    $this->init_settings();

	    // Turn these settings into variables we can use
	    foreach ( $this->settings as $setting_key => $value ) {
	        $this->$setting_key = $value;
	    }

		if ($this->expire_time == 0) {
			$this->expire_time = 300;
		}

		// Supports the default credit card form
		// $this->supports = array('default_credit_card_form');

		// The description for this Payment Gateway, shown on the actual Payment options page on the backend
		$method_description = __("Lydia permet à vos client de régler par carte bancaire depuis une page de paiement sécurisée, sans avoir besoin de créer un compte. Ceux qui utilisent l'application mobile Lydia pourront même payer en un clic, sans saisir leur numéros de carte bancaire.", 'lydia_payment');
		if(empty($this->public_token) )
		{
			$method_description .= __('<br /><br /><a href="https://lydia-app.com/pro/solutions/online-payment" target="_blank">Créer votre compte Lydia Pro pour recevoir vos identifiants</a>');
		}

		if (!empty($this->public_token) && $this->test != 'yes') {
			$dashboardLink = $this->_getBaseUrl().'/console-pro/'.$this->public_token;
			$method_description .= __('<br /><br />Vous pouvez accéder à votre dashboard Lydia en cliquant sur le lien suivant : <a href="'.$dashboardLink.'" target="_blank">Accès au dashboard</a>');
		} else {
			$method_description .= __("<br /><br />Un accès à votre dashboard vous sera fournit lorsque vous ne serez plus en mode test");
		}
		$this->method_description = $method_description;



  		include_once('woocommerce-lydia-payment-hook.php' );
		new WC_Gateway_Lydia_Payment_Gateway_Hook();
    

	    // Save settings
	    if (is_admin()) {
	        // Versions over 2.0
	        // Save our administration options. Since we are not going to be doing anything special
	        // we have not defined 'process_admin_options' in this class so the method in the parent
	        // class will be used instead
	        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
	    }
	} // End __construct()

	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title'     => __('Activer / Désactiver', 'lydia-payment'),
				'label'     => __('Activer ce moyen de paiement', 'lydia-payment'),
				'type'      => 'checkbox',
				'default'   => 'no',
			),
			'public_token' => array(
            	'title'     => __('Identifiant public', 'lydia-payment'),
            	'type'      => 'text',
            	'desc_tip'  => __('Présent dans le mail d\'inscription ou sur demande au support.', 'lydia-payment'),
        	),
			'private_token' => array(
            	'title'     => __('Identifiant privé', 'lydia-payment'),
            	'type'      => 'text',
            	'desc_tip'  => __('Présent dans le mail d\'inscription ou sur demande au support.', 'lydia-payment'),
        	),

			'expire_time'	=> array(
				'title'     => __('Durée de validité du panier', 'lydia-payment'),
				'type'      => 'text',
				'desc_tip'  => __('Durée en seconde pendant laquelle le client peut effectuer son paiement.', 'lydia-payment'),
				'default'	=> '300'
			),

			'description' => array(
				'title' => __( 'Description', 'lydia-payment' ),
				'type' => 'textarea',
				'description' => __( 'Ce champ permet de définir la description que verra l\'utilisteur lors du paiement.', 'lydia-payment' ),
				'default' => __( 'Payez par carte bancaire grâce au module de paiement sécurisé Lydia.', 'lydia-payment' )
			),
			
			'test' => array(
				'title'     => __('Environnement de test (homologation)', 'lydia-payment'),
				'label'     => __('Cocher cette case uniquement pour vos tests. Vous devez avoir des identifiants de test.', 'lydia-payment'),
				'type'      => 'checkbox',
				'default'   => 'no',
			),
		);
	}

	public function process_payment($order_id) {
	    global $woocommerce;

	    // Get this Order's information so that we know
	    // who to charge and how much
		$customer_order = new WC_Order($order_id);

		$confirmUrl = add_query_arg( array(
		  'order' => $order_id,
		  'action' => 'confirm',
		  ),  WC()->api_request_url( 'WC_Gateway_Lydia_Payment_Gateway' )
		);

		$param = array(
			'order_ref'			=> $order_id,
			'amount'			=> $this->get_order_total($customer_order),
			'vendor_token'		=> $this->public_token,
			'expire_time'		=> ($this->expire_time == 0 ? 300 : $this->expire_time),
			'success_url'		=> $this->get_return_url($customer_order),
			'confirm_url'		=> $confirmUrl,
			//'cancel_url'		=> $callBackUrl.'&action=cancel',
			//'expire_url'		=> $callBackUrl.'&action=expire',
			'notify_payer'		=> 'no',
			'notify_collector'	=> 'no',
			'display_conf'		=> 'no',
		);

    
		$order = new WC_Order($order_id);
		$phone = $order->billing_phone;
		if ($phone) {
			$param['recipient'] = $phone;
		}

		$woocommerce->cart->empty_cart();

		ksort($param);
		$sig = array();
		foreach ($param as $key => $val) {
			$sig[] = $key.'='.urlencode($val);
		}

		$callSig = md5(implode('&', $sig).'&'.$this->private_token);
		$url = $this->_getBaseUrl().'/ecommerce/payment/phoneform?'.implode('&', $sig).'&sig='.$callSig;
		return array(
				'result'	=> 'success',
				'redirect'	=> $url
		);

	}

	private function _getBaseUrl() {
		if ($this->test == "yes") {
			return 'https://homologation.lydia-app.com';
		} else {
			return 'https://lydia-app.com';
		}
	}

}
