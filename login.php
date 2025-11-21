<?php
require_once 'config.php';

$state = random_str(16);
$_SESSION['oauth_state'] = $state;

$params = http_build_query([
    'response_type' => 'code',
    'client_id'     => OAUTH_CLIENT_ID,
    'redirect_uri'  => OAUTH_REDIRECT_URI,
    'scope'         => 'profile email',
    'state'         => $state
]);

$authUrl = OAUTH_AUTHORIZE_URL . '?' . $params;
header("Location: $authUrl");
exit;
