<?php
/* Only attempt to handle POSTs with a token variable included */
if (!empty($_POST) && isset($_POST['token'])) {
    $contexts = empty($contexts) ? array($modx->context->get('key')) : explode(',', $contexts);
    foreach (array_keys($contexts) as $ctxKey) {
        $contexts[$ctxKey] = trim($contexts[$ctxKey]);
    }
    if (!$modx->user->hasSessionContext($contexts)) {
        $token = $_POST['token'];
        $rpxRequest = array(
            'token' => $token
            ,'apiKey' => $modx->getOption('rpx.apikey', $scriptProperties, '')
            ,'format' => 'json'
        );

        /* TODO: replace direct cURL calls with abstract modRESTClient that can handle any response type */
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, 'https://rpxnow.com/api/v2/auth_info');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $rpxRequest);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $rpxResponse = curl_exec($ch);
        curl_close($ch);
    
        $rpxResponse = $modx->fromJSON($rpxResponse);
        if ($rpxResponse['stat'] == 'ok') {
            $user = null;
            $rpxProfile = $rpxResponse['profile'];
            $rpxIdentifier = $rpxProfile['identifier'];

            $user = $modx->getObjectGraph('rpxUser', '{"Profile":{}}', array('remote_key:=' => $rpxIdentifier, 'remote_key:!=' => null, 'class_key' => 'rpxUser'));
            if (!$user && $modx->getOption('rpx.autoadd', $scriptProperties, true)) {
                /* see if any user with that email OR remote_key exists */
                $user = $modx->getObjectGraph('modUser', '{"Profile":{}}', array('Profile.email:=' => $rpxProfile['email'], array('OR:remote_key:=' => $rpxIdentifier, 'remote_key:!=' => null)));
                
                if (!$user) {
                    /* create a new rpxUser */
                    $user = $modx->newObject('rpxUser');
                    $user->fromArray(array(
                        'username' => $rpxProfile['preferredUsername']
                        ,'active' => true
                        ,'remote_key' => $rpxIdentifier
                        ,'remote_data' => $rpxProfile
                    ));
                    $profile = $modx->newObject('modUserProfile');
                    $profile->fromArray(array(
                        'email' => isset($rpxProfile['email']) ? $rpxProfile['email'] : ''
                        ,'fullname' => isset($rpxProfile['displayName']) ? $rpxProfile['displayName'] : (isset($rpxProfile['preferredUsername']) ? $rpxProfile['preferredUsername'] : $rpxIdentifier)
                    ));
                    $user->addOne($profile, 'Profile');
                    $saved = $user->save();
                }
            }
            if ($user && !($user instanceof rpxUser) && $modx->getOption('rpx.automap_existing', $scriptProperties, false)) {
                $modx->log(modX::LOG_LEVEL_INFO, 'RPXNow converting modUser to rpxUser');
                $user->fromArray(array(
                    'remote_key' => $rpxIdentifier
                    ,'remote_data' => $rpxProfile
                    ,'class_key' => 'rpxUser'
                ));
                $saved = $user->save();
                $user = $modx->getObjectGraph('rpxUser', '{"Profile":{}}', array('remote_key:=' => $rpxIdentifier, 'remote_key:!=' => null));
            } elseif ($user && $user instanceof rpxUser) {
                /* update remote data for existing rpxUser */
                $modx->log(modX::LOG_LEVEL_INFO, 'RPXNow updating remote_data for rpxUser');
                $user->set('remote_data', $rpxProfile);
                $saved = $user->save();
            }
            if ($user && ($user instanceof rpxUser) && $saved) {
                $modx->log(modX::LOG_LEVEL_INFO, 'RPXNow logging in remote user into contexts: ' . print_r($contexts, true));
                foreach ($contexts as $context) {
                    $user->addSessionContext($context);
                }
            }
        } else {
            $modx->log(modX::LOG_LEVEL_WARN, 'RPXNow login attempt failed: ' . print_r($rpxResponse, true));
            $modx->sendUnauthorizedPage();
        }
    } elseif ($modx->user->hasSessionContext($contexts)) {
        if (!($modx->user instanceof rpxUser)) {
            $modx->log(modX::LOG_LEVEL_WARN, 'RPXNow login failed; user is logged in but is not an rpxUser. They must logout and attempt as anonymous to automap their existing account.');
            $modx->sendUnauthorizedPage();
        } else {
            $modx->log(modX::LOG_LEVEL_WARN, 'RPXNow login skipped; user is already logged in. They must logout and login again in order to update their remote profile data.');
        }
    }
} else {
    $modx->log(modX::LOG_LEVEL_WARN, 'Access to RPXNow attempted but skipped: ' . (!isset($_POST['token']) ? 'no token provided' : 'request is not a POST or the POST is empty'));
    $modx->sendErrorPage();
}
$onLoginResource = $modx->getOption('onLoginResource', $scriptProperties, $modx->getOption('site_start', null, 1));
$modx->sendRedirect($modx->makeUrl($onLoginResource));

return '';
?>