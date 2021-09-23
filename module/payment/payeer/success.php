<?php
if (isset($_GET['m_orderid'])) {
	$order_id = preg_replace('/[^a-zA-Z0-9_-]/', '', substr($_GET['m_orderid'], 0, 32));
	ob_end_clean();
	header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
	header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
	header('Cache-Control: post-check=0, pre-check=0', false);
	header('Pragma: no-cache');
	header('Location: ' . $_SERVER['HOST'] . '/members/order?action=process&status=ok&item_number=' . $order_id, true, 301);
	die;
}
?>