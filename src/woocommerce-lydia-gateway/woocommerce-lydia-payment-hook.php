<?php

/**
* Hold return from Lydia Services to modify order state
* This class is only used in server to server call
**/
class WC_Gateway_Lydia_Payment_Gateway_Hook {

	private $_gateway;

	/**
	* Add action to respond RPC call from Lydia Services
	*/
	public function __construct() {
  		add_action('woocommerce_api_wc_gateway_lydia_payment_gateway', array($this, 'dispatch'));
    }

	/**
	* Dispatch to the right method depending on the state of the transaction
	*/
	public function dispatch() {

		if (isset($_GET['action']) && isset($_GET['order'])) {
			$order_id = $_GET['order'];
			switch ($_GET['action']) {
				case 'confirm':
        			$this->_confirm_order($order_id);
					break;
				case 'cancel':
					$this->_cancel_order($order_id, 'Commande annulée');
					break;
				case 'expire':
					$this->_cancel_order($order_id, 'Commande expirée');
					break;
				default:
					break;
			}
		}
   		exit (-1);
	}

	/**
	* Check call author (via sig) and update order state.
	* If order contains only virtual products, it's updated as "complete" else
	* it's updated to "payment_complete"
	*/
	private function _confirm_order($order_id) {
		if ($this->_checkSig( $_POST['sig'])) {
			$customer_order = new WC_Order($order_id);
			$customer_order->payment_complete();

			if ($this->_isVirtualOrder($customer_order)) {
				$customer_order->update_status('completed');
			}
		}
 		exit(0);
	}

	/**
	* Not usable yet
	*/
	private function _cancel_order($order_id, $motif) {
		exit(0);
	}

	/**
	* Check if a signature is valid from a params list
	*/
	private function _checkSig( $sig) {
		$data = array();

		unset($_POST['sig']);
		foreach ($_POST as $key => $value) {
			$data[$key] = $value;
		}

		ksort($data);


		$sigData = array();
		foreach ($data as $key => $val) {
			$sigData[] = $key.'='.$val;
		}

		$gateway = $this->_getGateway();
		$sigCheck = md5(implode('&', $sigData).'&'.$gateway->private_token);

		return $sigCheck == $sig;
	}

	/**
	* WC_Gateway_Lydia_Payment_Gateway is used to get merchant private_token
	*/
	private function _getGateway() {
		if (!isset($this->_gateway)) {
			$this->_gateway = new WC_Gateway_Lydia_Payment_Gateway();
		}
  		return $this->_gateway;
	}

	/**
	* Fetch an order and check if it contains a non virtual item
	*/
	private function _isVirtualOrder($order) {
		$items = $order->get_items();
		foreach ($items as $item) {
			$product = $order->get_product_from_item($item);
			if (!$product->is_virtual()) {
				return false;
			}
		}
		return true;
	}
}
