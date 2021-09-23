<?php

class Payeer_PaymentSystemDriver extends AMI_PaymentSystemDriver {
  
  protected $driverName = 'payeer';

  public function getPayButton(&$aRes, $aData, $bAutoRedirect = false) {
    
    foreach (array('return', 'description') as $k) {
      $aData[$fldName] = htmlspecialchars($aData[$k]);
    }

    $aEclusion = array(
			'return',
			'cancel',
			'callback',
			'pay_to_email',
			'amount',
			'currency',
			'description_title',
			'description',
			'order',
			'button_name',
			'button'
    );

    $hiddens = '';
    foreach ($aData as $key => $value) {
      if (!in_array($key, $aEclusion)) {
        $hiddens .= '<input type="hidden" name="' . $key . '" value="' . (is_null($value) ? $aData[$key] : $value) . '" />\n';
      }
    }
    
    $aData['hiddens'] = $hiddens;
    return parent::getPayButton($aRes, $aData, $bAutoRedirect);
  }

  public function getPayButtonParams($aData, &$aRes) {
    $aData += array('payeer_payment' => 0);
		
		if ($aData['currency'] == 'RUR') {
			$aData['currency'] = 'RUB';
		}
		
		$aData['description'] = base64_encode($aData['description']);
		$m_shop 	= $aData['payeer_shop'];
		$m_orderid 	= $aData['order'];
		$m_amount 	= number_format($aData['amount'], 2, '.', '');
		$m_curr 	= $aData['currency'];
		$m_desc 	= $aData['description'];
		$m_key 		= $aData['payeer_secret_key'];

		$arHash = array(
			$m_shop,
			$m_orderid,
			$m_amount,
			$m_curr,
			$m_desc,
			$m_key
		);
		$aData['sign'] = strtoupper(hash('sha256', implode(":", $arHash)));
    return parent::getPayButtonParams($aData, $aRes);
  }

  public function payProcess($aGet, $aPost, &$aRes, $aCheckData, $aOrderData) {
    return parent::payProcess($aGet, $aPost, $aRes, $aCheckData, $aOrderData);
  }

  public function payCallback($aGet, $aPost, &$aRes, $aCheckData, $aOrderData) {
    return 1;
  }

  public function getProcessOrder($aGet, $aPost, &$aRes, $aAdditionalParams) {
    return parent::getProcessOrder($aGet, $aPost, $aRes, $aAdditionalParams);
  }
}