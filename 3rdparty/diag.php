<?php

function callQbittorrent($_url, $postfields = null, $_timeout = null) {
	if (strpos($_url, '?') !== false) {
		$url = 'http://127.0.0.1:' . config::byKey('port_server', 'qbittorrent', 8083) . '/' . trim($_url, '/') . '&access_token=' . jeedom::getApiKey('qbittorrent');
	} else {
		$url = 'http://127.0.0.1:' . config::byKey('port_server', 'qbittorrent', 8083) . '/' . trim($_url, '/') . '?access_token=' . jeedom::getApiKey('qbittorrent');
	}

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
	if($_timeout !== null){
		curl_setopt($ch, CURLOPT_TIMEOUT, $_timeout);
	}
	if ($postfields !== null) {
		$headers[] = "Content-Type: application/json";
        curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode($postfields));
	}
		
	$result = curl_exec($ch);
	if (curl_errno($ch)) {
		$curl_error = curl_error($ch);
		curl_close($ch);
		throw new Exception(__('Echec de la requête http : ', __FILE__) . $url . ' Curl error : ' . $curl_error, 404);
	}
	curl_close($ch);
	return is_json($result, $result);
}

?>
