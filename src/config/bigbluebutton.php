<?php

return [
    /**
     * Bigbluebutton security salt, this value must match the security salt
     * used on your bigbluebutton server.
     */
    'bbb_security_salt' => env('BBB_SECURITY_SALT'),

    /**
     * bigbluebutton server url, normally this url will be in the form of
     * http://localhost/bigbluebutton so be sure to include the /bigbluebutton
     * part unless your server has a different api endpoint.
     */
    'bbb_server_url'    => env('BBB_SERVER_URL')
];
