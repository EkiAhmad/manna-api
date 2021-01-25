<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Auth_model extends CI_Model
{
    public $publicKeyAdmin = "admin-2020-m4nna-m3nu";
    public $publicKeyMerchant = "merchant-2020-m4nna-m3nu";
    public $publicKeyStore = "store-2020-m4nna-m3nu";
    public $publicKeySupplier = "supplier-2020-m4nna-m3nu";

    public $roles = array(
        'admin' => 10,
        'store-admin' => 11,
        'store-casier' => 12,
        'merchant-admin' => 13,
        'supplier-admin' => 14,
    );

    public function __construct()
    {
        parent::__construct();
    }

    public function storeHashPassword($password = '')
    {
        if ($password) {
            $options = [
                'cost' => 10,
            ];
            return password_hash($password, PASSWORD_DEFAULT, $options);
        }
        return false;
    }
}
