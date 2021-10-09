<?php
/**
 * @package     Joomla - > Site and Administrator payment info
 * @subpackage  com_jgive
 * @subpackage 	RayPay JGive
 * @copyright   RayPay => https://raypay.ir
 * @copyright   Copyright (C) 2021 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

// No direct access
defined('_JEXEC') or die('Restricted access');
jimport('joomla.plugin.plugin');
require_once dirname(__FILE__) . '/raypay/helper.php';
if (!class_exists ('checkHack')) {
	require_once( dirname(__FILE__) . '/raypay/raypay_inputcheck.php');
}

class PlgPaymentRayPay extends JPlugin
{

	public function __construct(& $subject, $config)
	{
		parent::__construct($subject, $config);

		// Set the language in the class
		$config = JFactory::getConfig();
	}

	public function buildLayoutPath($layout)
	{
		$layout = trim($layout);

		if (empty($layout))
		{
			$layout = 'default';
		}

		$app = JFactory::getApplication();
		$core_file = dirname(__FILE__) . '/' . $this->_name . '/' . 'tmpl' . '/' . $layout . '.php';
	
			return  $core_file;
	}

	public function buildLayout($vars, $layout = 'default' )
	{
		// Load the layout & push variables
		ob_start();
		$layout = $this->buildLayoutPath($layout);
		include $layout;
		$html = ob_get_contents();
		ob_end_clean();

		return $html;
	}

	public function onTP_GetInfo($config)
	{
		if (!in_array($this->_name, $config))
		{
			return;
		}

		$obj = new stdClass;
		$obj->name = $this->params->get('plugin_name');
		$obj->id = $this->_name;

		return $obj;
	}

	public function onTP_GetHTML($vars) {
		$app	= JFactory::getApplication();
		$config = JFactory::getConfig();
        $Amount = round($vars->amount,0);
		$Description = 'پرداخت برای کامپوننت JGive سایت'.' ' .$config->get( 'sitename' );
		$CallbackURL = $vars->notify_url;

        $user_id                = $this->params->get('user_id','');
        $marketing_id          = $this->params->get('marketing_id','');
        $sandbox = !($this->params->get('sandbox','') == 0);
        $invoice_id             = round(microtime(true) * 1000);
        $url  = 'https://api.raypay.ir/raypay/api/v1/Payment/pay';
		

		try {
            $data = array(
                'amount'       => strval($Amount),
                'invoiceID'    => strval($invoice_id),
                'userID'       => $user_id,
                'redirectUrl'  => $CallbackURL,
                'marketingID' => $marketing_id,
                'comment'      => $Description,
                'enableSandBox'      => $sandbox
            );
            $jsonData = json_encode($data);
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));

            $result = curl_exec($ch);
            $err = curl_error($ch);
            $result = json_decode($result, true, JSON_PRETTY_PRINT);
            $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $app->enqueueMessage($http_status, 'error');
            curl_close($ch);
            
			if ($http_status == 200) {
                    $token = $result['Data'];
                    $link='https://my.raypay.ir/ipg?token=' . $token;
					$vars->action_url = $link;
				    $html = $this->buildLayout($vars);
				    return $html;
			} else {
				$msg= $result['Message']; 
				$link = JRoute::_(JUri::root().	'index.php?option=com_jgive&task=donations.cancel&processor=raypay&order_id='.$vars->order_id,false);
				$app->redirect($link, '<h2>'.$msg.'  خطای: '.$result['StatusCode'].'</h2>', $msgType='Error');
			}
		}
		catch(Exception $e) {
            $msg= 'خطا هنگام ایجاد تراکنش';
			$link = JRoute::_(JUri::root().	'index.php?option=com_jgive&task=donations.cancel&processor=raypay&order_id='.$vars->order_id,false);
			$app->redirect($link, '<h2>'.$msg.'</h2>', $msgType='Error'); 
		}

	}

	public function onTP_Processpayment($data, $vars = array()) {
		$app	= JFactory::getApplication();		
		$jinput = $app->input;
		$order_id = $jinput->get->get('order_id', 'STRING');
				if (!empty($_POST)) {
					try {
                        $jsonData = json_encode($_POST);
                        $url = 'https://api.raypay.ir/raypay/api/v1/payment/verify';
                        $ch = curl_init($url);
                        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
                        $result = curl_exec($ch);
                        curl_close($ch);
                        $result = json_decode($result, true);

						if ($result['Data']['Status'] == 1) {
							
							$msg= 'پرداخت با موفقیت انجام شد.';
							JFactory::getApplication()->enqueueMessage('<h2>'.$msg.'</h2>'.'<h3>'. $result['Data']['InvoiceID'] .'شناسه پرداخت رای پی ' .'</h3>', 'Message');
							
							plgPaymentRayPayHelper::saveComment($this->params->get('plugin_name'), str_replace('JGOID-','',$vars->order_id), $result['Data']['InvoiceID'] .'شناسه پرداخت رای پی ');
							$result                 = array(
							'transaction_id' => $result['Data']['InvoiceID'],
							'order_id' => $vars->order_id,
							'status' => 'C',
							'total_paid_amt' => $vars->amount,
							'raw_data' => '',
							'error' => '',
							'return' => $vars->return
							);

							return $result;
						} 
						else {
							$msg= 'پرداخت با خطا مواجه شد.';
							$link = JRoute::_(JUri::root().	'index.php?option=com_jgive&task=donations.cancel&processor=raypay&order_id='.$vars->order_id,false);
							$app->redirect($link, '<h2>'.$msg.'</h2>', $msgType='Error'); 
							return false;
						}
					}
					catch(Exception $e) {
						$msg= 'خطا هنگام استعلام تراکنش';
						$link = JRoute::_(JUri::root().	'index.php?option=com_jgive&task=donations.cancel&processor=raypay&order_id='.$vars->order_id,false);
						$app->redirect($link, '<h2>'.$msg.'</h2>', $msgType='Error'); 
						return false;
					}
			}
			else {
				$msg= 'خطا هنگام بازگشت از درگاه پرداخت';
				$link = JRoute::_(JUri::root().	'index.php?option=com_jgive&task=donations.cancel&processor=raypay&order_id='.$vars->order_id,false);
				$app->redirect($link, '<h2>'.$msg.'</h2>', $msgType='Error'); 
				return false;	
			}
	}


	public function onTP_Storelog($data)
	{
		$log_write = $this->params->get('log_write', '0');

		if ($log_write == 1)
		{
			$log = plgPaymentRayPayHelper::Storelog($this->_name, $data);
		}
	}
}
