// File: config/midtrans_config.php
<?php
class MidtransConfig {
    private static $instance = null;
    private $config;
    
    private function __construct() {
        $this->config = [
            'server_key' => 'SB-Mid-server-WURwTWswCZ2VeCzwCj5bHUGv',
            'client_key' => 'SB-Mid-client-zm-CX2pZMycoJDnp',
            'is_production' => false,
            'notification_url' => '../Sikompen/payment/notification_handler.php',
            'finish_redirect_url' => '../Sikompen/payment/finish.php',
            'unfinish_redirect_url' => '../Sikompen/payment/unfinish.php',
            'error_redirect_url' => '../Sikompen/payment/error.php'
        ];
    }
    
    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new MidtransConfig();
        }
        return self::$instance;
    }
    
    public function getConfig() {
        return $this->config;
    }
}