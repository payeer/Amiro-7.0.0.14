<?php

chdir(realpath('../../'));
require_once 'ami_env.php';

require_once '_local/eshop/AtoPaymentSystem.php';

class Payeer_Callback
{
    public function __construct()
    {
        $this->_secretKey = (string)AtoPaymentSystem::getDriverParameter('payeer', 'payeer_secret_key');
		$this->_ipfilter = (string) AtoPaymentSystem::getDriverParameter('payeer', 'payeer_ip_filter');
		$this->_log = (string)AtoPaymentSystem::getDriverParameter('payeer', 'payeer_log');
		$this->_emailerr = (string)AtoPaymentSystem::getDriverParameter('payeer', 'payeer_email_error');
    }

    public function validateRequestParams(array $request)
    {
		if (isset($request['m_operation_id']) && isset($request['m_sign']))
		{
			$err = false;
			$message = '';
			
			// запись логов
			
			$log_text = 
				"--------------------------------------------------------\n" .
				"operation id		" . $request["m_operation_id"] . "\n" .
				"operation ps		" . $request["m_operation_ps"] . "\n" .
				"operation date		" . $request["m_operation_date"] . "\n" .
				"operation pay date	" . $request["m_operation_pay_date"] . "\n" .
				"shop				" . $request["m_shop"] . "\n" .
				"order id			" . $request["m_orderid"] . "\n" .
				"amount				" . $request["m_amount"] . "\n" .
				"currency			" . $request["m_curr"] . "\n" .
				"description		" . base64_decode($request["m_desc"]) . "\n" .
				"status				" . $request["m_status"] . "\n" .
				"sign				" . $request["m_sign"] . "\n\n";
			
			$log_file = $this->_log;
			
			if (!empty($log_file))
			{
				file_put_contents($_SERVER['DOCUMENT_ROOT'] . $log_file, $log_text, FILE_APPEND);
			}
			
			// проверка цифровой подписи и ip
			
			$sign_hash = strtoupper(hash('sha256', implode(":", array(
				$request['m_operation_id'],
				$request['m_operation_ps'],
				$request['m_operation_date'],
				$request['m_operation_pay_date'],
				$request['m_shop'],
				$request['m_orderid'],
				$request['m_amount'],
				$request['m_curr'],
				$request['m_desc'],
				$request['m_status'],
				$this->_secretKey
			))));
			
			$valid_ip = true;
			$sIP = str_replace(' ', '', $this->_ipfilter);
			
			if (!empty($sIP))
			{
				$arrIP = explode('.', $_SERVER['REMOTE_ADDR']);
				if (!preg_match('/(^|,)(' . $arrIP[0] . '|\*{1})(\.)' .
				'(' . $arrIP[1] . '|\*{1})(\.)' .
				'(' . $arrIP[2] . '|\*{1})(\.)' .
				'(' . $arrIP[3] . '|\*{1})($|,)/', $sIP))
				{
					$valid_ip = false;
				}
			}
			
			if (!$valid_ip)
			{
				$message .= " - ip address of the server is not trusted\n" . 
				"   trusted ip: " . $sIP . "\n" . 
				"   ip of the current server: " . $_SERVER['REMOTE_ADDR'] . "\n";
				$err = true;
			}

			if ($request["m_sign"] != $sign_hash)
			{
				$message .= " - Do not match the digital signature\n";
				$err = true;
			}
			
			if (!$err)
			{
				// загрузка заказа
			
				$oDB = AMI::getSingleton('db');

				$order = $oDB->fetchRow(
					DB_Query::getSnippet("SELECT `status`,`sysinfo`,`total` FROM `cms_es_orders` WHERE `id` = %s")
					->q($request['m_orderid'])
				);
				
				$sysinfo = unserialize($order['sysinfo']);
				$order_curr = ($sysinfo['fee_curr'] == 'RUR') ? 'RUB' : $sysinfo['fee_curr'];
				$order_amount = number_format($order['total'], 2, '.', '');
				
				// проверка суммы и валюты
				
				if ($request['m_amount'] != $order_amount)
				{
					$message .= " - Wrong amount\n";
					$err = true;
				}

				if ($request['m_curr'] != $order_curr)
				{
					$message .= " - Wrong currency\n";
					$err = true;
				}
				
				// проверка статуса
				
				if (!$err)
				{
					if ($order['status'] != 'checkout')
					{
						switch ($request['m_status'])
						{
							case 'success':
							
								if ($order['status'] != 'confirmed_done')
								{
									$qupdate = $oDB->fetchValue(DB_Query::getUpdateQuery(
										'cms_es_orders',
										array('status'  => 'confirmed_done'),
										DB_Query::getSnippet('WHERE id IN (%s)')->q($request['m_orderid'])
									));
								}
								
								return $request['m_orderid'] . '|success';
								break;
								
							default:
								$message .= " - The payment status is not success\n";
								$qupdate = $oDB->fetchValue(DB_Query::getUpdateQuery(
									'cms_es_orders',
									array('status'  => 'cancelled'),
									DB_Query::getSnippet('WHERE id IN (%s)')->q($request['m_orderid'])
								));
								
								$to = $this->_emailerr;
					
								if (!empty($to))
								{
									$message = "Failed to make the payment through Payeer for the following reasons:\n\n" . $message . "\n" . $log_text;
									$headers = "From: no-reply@" . $_SERVER['HTTP_SERVER'] . "\r\n" . 
									"Content-type: text/plain; charset=utf-8 \r\n";
									mail($to, 'Payment error', $message, $headers);
								}
					
								return $request['m_orderid'] . '|error';
								break;
						}
					}
					else
					{
						return false;
					}
				}
			}
			
			if ($err)
			{
				$to = $this->_emailerr;
				
				if (!empty($to))
				{
					$message = "Failed to make the payment through Payeer for the following reasons:\n\n" . $message . "\n" . $log_text;
					$headers = "From: no-reply@" . $_SERVER['HTTP_SERVER'] . "\r\n" . 
					"Content-type: text/plain; charset=utf-8 \r\n";
					mail($to, 'Payment error', $message, $headers);
				}
				
				return $request['m_orderid'] . '|error';
			}
		}
		else
		{
			return false;
		}
    }
}

$atoPayeerCallback = new Payeer_Callback();
$resultPayeer = $atoPayeerCallback->validateRequestParams($_POST);
exit($resultPayeer);